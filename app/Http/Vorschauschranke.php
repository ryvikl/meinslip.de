<?php

declare(strict_types=1);

namespace MeinSlip\Http;

use MeinSlip\Core\Env;
use MeinSlip\Core\Request;
use MeinSlip\Core\Response;
use MeinSlip\Core\View;

/**
 * Die Vorschau-Schranke: legt die gesamte Anwendung hinter ein Passwort.
 *
 * WARUM ES SIE GIBT. Die Impressumspflicht des § 5 DDG (vormals § 5 TMG)
 * trifft geschaeftsmaessige, in der Regel gegen Entgelt angebotene digitale
 * Dienste, die OEFFENTLICH zugaenglich sind. Solange die Rechtstexte
 * Entwuerfe sind (resources/lang/de-DE/allgemein.php: 'rechtstext_entwurf'),
 * darf die Seite deshalb nicht oeffentlich stehen: Ein oeffentlich
 * erreichbares, unvollstaendiges Impressum ist ein gaengiger
 * Abmahngrund. Eine passwortgeschuetzte Vorschau ist kein oeffentliches
 * Angebot — nach ueberwiegender Auffassung besteht fuer sie keine
 * Impressumspflicht. Eine Garantie ist das nicht; der Stand gehoert vor dem
 * oeffentlichen Start anwaltlich bestaetigt (docs/05-recht-compliance.md).
 *
 * DIE SCHRANKE FAELLT GESCHLOSSEN AUS, NIE OFFEN. Ist VORSCHAU_PASSWORT
 * gesetzt, aber zu kurz, bleibt die Seite fuer alle zu (503) — sie wird
 * NICHT stillschweigend oeffentlich. Der umgekehrte Fehler (Betreiber
 * glaubt die Seite geschuetzt, sie ist es nicht) waere genau der Fall,
 * gegen den die Schranke gebaut ist. Nur ein LEERES VORSCHAU_PASSWORT
 * schaltet sie bewusst ab.
 *
 * WAS AUSGENOMMEN IST — GENAU EIN PFAD. /deploy/migrieren ist der Endpunkt
 * der Deployment-Automatik: eine Maschine mit eigenem, langem Token
 * (DEPLOY_TOKEN), die weder Cookies annehmen noch ein Formular ausfuellen
 * kann. Er bleibt erreichbar, sonst bricht jedes Deployment, sobald die
 * Schranke steht. /einrichten dagegen bleibt HINTER der Schranke: Wer die
 * Einrichtung faehrt, kennt das Vorschau-Passwort und kommt durch.
 *
 * WARUM 401 UND NICHT 200. Die Schrankenseite antwortet mit 401 und
 * Cache-Control: no-store. Das haelt zwei Zwischenspeicher sauber: Der
 * Service Worker fuellt seine Huelle mit cache.addAll(), und das bricht bei
 * nicht-ok-Antworten ab, statt die Schrankenseite als '/offline' zu
 * verbuchen. Und Suchmaschinen verbuchen 401 als "nicht verfuegbar" statt
 * die Schranke zu indexieren — zusaetzlich abgesichert durch X-Robots-Tag.
 *
 * WAS DIE SCHRANKE NICHT SCHUETZT. Statische Dateien unter /assets/ liefert
 * der Webserver aus, bevor PHP laeuft — Schrift, CSS und App-Icon bleiben
 * abrufbar. Das ist Absicht: Die Schrankenseite selbst braucht das CSS, und
 * Inhalte stecken dort keine. Alles Inhaltliche (Seiten, Bilder der
 * Angebote ueber /medien/...) laeuft durch PHP und damit durch die Schranke.
 *
 * KEIN KONTENSCHUTZ. Das eine, geteilte Passwort schuetzt eine nicht
 * oeffentliche Baustelle, keine Konten — es gibt keine Drosselung und kein
 * Sperren nach Fehlversuchen. Fuer diesen Zweck ist das angemessen; als
 * Anmeldung taugte es nicht und darf nie eine ersetzen.
 */
final class Vorschauschranke
{
    public const COOKIE = 'ms_vorschau';

    public const FELD = 'vorschau_passwort';

    /**
     * Unter 12 Zeichen ist ein geteiltes Passwort Deko. Die Schranke lehnt
     * die Konfiguration dann ab (503) — siehe Klassenkopf: geschlossen, nie
     * offen.
     */
    private const MINDESTLAENGE = 12;

    private const AUSGENOMMEN = ['/deploy/migrieren'];

    public function __construct(private readonly View $ansicht)
    {
    }

    /**
     * Liefert die Schrankenantwort — oder null, wenn die Anfrage durchdarf.
     */
    public function pruefen(Request $anfrage): ?Response
    {
        $passwort = Env::get('VORSCHAU_PASSWORT', '') ?? '';

        if ($passwort === '') {
            return null;
        }

        if (in_array($anfrage->pfad, self::AUSGENOMMEN, true)) {
            return null;
        }

        if (strlen($passwort) < self::MINDESTLAENGE) {
            // Betreibertext wie bei der Einrichtung: Klartext, ASCII, keine
            // Vorlage — die Seite ist in diesem Zustand ohnehin fuer alle zu.
            return Response::text(
                "Vorschau gesperrt.\n\n"
                . "VORSCHAU_PASSWORT ist gesetzt, aber kuerzer als " . self::MINDESTLAENGE . " Zeichen.\n"
                . "Die Schranke faellt geschlossen aus, nie offen: Bitte ein laengeres Passwort\n"
                . "setzen — oder den Wert leeren, um die Seite bewusst oeffentlich zu machen.\n",
                503
            );
        }

        if ($this->durchgelassen($passwort)) {
            return null;
        }

        // Der Einlassversuch: das Formular der Schrankenseite.
        if ($anfrage->methode === 'POST' && $anfrage->eingabe(self::FELD) !== null) {
            if (!Formularschutz::gueltig($anfrage)) {
                return $this->schranke($anfrage, 'fehler_schutz');
            }

            if (!hash_equals($passwort, $anfrage->eingabe(self::FELD, '') ?? '')) {
                return $this->schranke($anfrage, 'fehler_falsch');
            }

            $this->einlassen($passwort);

            // 303 statt 302: Der Browser soll das Ziel mit GET holen und den
            // Einlass-POST nicht wiederholen. Der Pfad kommt aus
            // Request::ausGlobalen() und traegt genau einen fuehrenden
            // Schraegstrich — eine fremde Adresse kann hier nicht stehen.
            return Response::weiterleitung($anfrage->pfad, 303);
        }

        return $this->schranke($anfrage);
    }

    /**
     * Der Ausweis: HMAC ueber das Passwort mit dem Anwendungsschluessel.
     *
     * Beides steckt im Wert — wer das Passwort ODER den Schluessel wechselt,
     * macht damit jeden ausgegebenen Ausweis ungueltig, ohne dass irgendwo
     * etwas gespeichert oder geloescht werden muesste.
     */
    private function ausweis(string $passwort): string
    {
        return hash_hmac('sha256', 'vorschau|' . $passwort, Env::get('APP_SCHLUESSEL', '') ?? '');
    }

    private function durchgelassen(string $passwort): bool
    {
        $wert = $_COOKIE[self::COOKIE] ?? '';

        return is_string($wert) && $wert !== '' && hash_equals($this->ausweis($passwort), $wert);
    }

    private function einlassen(string $passwort): void
    {
        $ausweis = $this->ausweis($passwort);

        // Wie Formularschutz::token(): setcookie nur, wenn noch Kopfzeilen
        // gesendet werden koennen; $_COOKIE immer, damit derselbe Ablauf im
        // Test ohne echte Kopfzeilen pruefbar ist.
        if (!headers_sent()) {
            setcookie(self::COOKIE, $ausweis, [
                'expires' => time() + 86400 * 30,
                'path' => '/',
                'secure' => ($_SERVER['HTTPS'] ?? '') === 'on'
                    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        $_COOKIE[self::COOKIE] = $ausweis;
    }

    private function schranke(Request $anfrage, ?string $fehler = null): Response
    {
        return Response::html(
            $this->ansicht->rendern('vorschau/schranke', [
                'pfad' => $anfrage->pfad,
                'fehler' => $fehler,
            ]),
            401,
            [
                'Cache-Control' => 'no-store',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]
        );
    }
}
