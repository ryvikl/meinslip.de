<?php

declare(strict_types=1);

namespace MeinSlip\Http;

use MeinSlip\Core\Database;
use MeinSlip\Core\Lang;
use MeinSlip\Core\Request;
use MeinSlip\Core\Response;
use MeinSlip\Core\Router;
use MeinSlip\Core\View;
use MeinSlip\Domain\Account\Sitzungen;
use MeinSlip\Domain\Media\Bilder;
use MeinSlip\Domain\Media\Medien;
use MeinSlip\Domain\Media\MedienFehler;
use MeinSlip\Domain\Verification\Pruefbelege;
use MeinSlip\Domain\Verification\PruefbelegFehler;

/**
 * Zwei Strecken, die zusammengehoeren, weil sie beide beantworten, was ein
 * Konto belegt hat — und vor allem, was es NICHT belegt hat.
 *
 * ERSTENS: /verifizierung. Die manuelle Identitaetspruefung. Die Plattform
 * vergibt einen Code, die Person schreibt ihn samt Datum auf einen Zettel,
 * haelt ihn ins Bild und laedt genau ein Selfie hoch. Die Fachlogik steht in
 * MeinSlip\Domain\Verification\Pruefbelege; diese Klasse ist Formular,
 * Weiterleitung und Rueckmeldung, sonst nichts.
 *
 * Das Bild laeuft ueber MeinSlip\Domain\Media\Bilder — dieselbe Pipeline wie
 * jedes Angebotsbild, keine zweite Uploadstrecke. Der EXIF-Strip der
 * Neukodierung ist hier das Wichtigste ueberhaupt: Ein Selfie traegt fast
 * immer die Koordinaten der eigenen Wohnung.
 *
 * ZWEITENS: /altersschranke. Die Selbsterklaerung — und die Seite, die
 * ausspricht, was sie nicht ist.
 *
 * Sitzungen::gateBestanden() und gateGilt() lagen seit 001_konten.php
 * unbenutzt im Code. Sie werden hier angeschlossen: Die Erklaerung wird
 * vermerkt, ihre Gueltigkeit wird gelesen, und sie laeuft nach
 * Sitzungen::GATE_GUELTIG_MINUTEN ab — das ist die zweite Stufe des
 * AVS-Rasters, die Authentifizierung je Nutzungsvorgang.
 *
 * WAS DIE ERKLAERUNG FREISCHALTET: NICHTS. Und das ist keine unfertige
 * Stelle, sondern das Ergebnis.
 *
 * Eine Selbsterklaerung ist keine geschlossene Benutzergruppe nach § 4 Abs. 2
 * JMStV. Der BGH hat 2007 (I ZR 102/05, "ueber18.de") sogar die Kombination
 * aus Ausweisnummer, Adresse und Kontoueberweisung als unzureichend verworfen
 * — ein Knopf mit "ich bin ueber 18" ist ueberhaupt keine Schranke. Wer
 * daraufhin explizite Inhalte ausliefert, baut ein rechtswidriges Gate und
 * gibt zugleich vor, eines zu haben. Deshalb bleiben Medien mit explizit = 1
 * fuer alle ausser der Hochladenden und der Verwaltung unabrufbar, mit
 * Erklaerung wie ohne — Medien::explizitSichtbar() prueft die Erklaerung
 * erst, wenn Medien::altersschrankeGebunden() true meldet, und das tut es
 * heute nicht.
 *
 * Die Seite sagt der Person genau das. Sie verspricht nichts, sie schaltet
 * nichts frei, und sie behauptet nicht, eine Pruefung zu sein. Wer spaeter ein
 * lizenziertes Verfahren anbindet, findet hier den fertigen Anschluss vor und
 * dreht in Medien::altersschrankeGebunden() ein false um.
 */
final class VerifizierungsRouten
{
    /** Wurzel der Verifizierungsstrecke. */
    private const WURZEL = '/verifizierung';

    /** Die Seite der Selbsterklaerung. */
    private const SCHRANKE = '/altersschranke';

    /** Name des Dateifeldes im Formular. */
    private const FELD = 'beleg';

    /**
     * Erlaubte Erfolgsschluessel in der Adresszeile.
     *
     * Ohne Weissliste liesse sich ueber '?erfolg=' beliebiger Text in die
     * Uebersetzung schieben, und die Seite zeigte '[[verifizierung.erfolg.x]]'.
     *
     * @var list<string>
     */
    public const ERFOLGE = [
        'code_vergeben',
        'beleg_eingereicht',
        'schranke_bestanden',
    ];

    /**
     * Erlaubte Fehlerschluessel.
     *
     * Die ersten sechzehn sind woertlich die von MedienFehler::schluessel() aus
     * Bilder, danach die von PruefbelegFehler, danach drei eigene. Jeder
     * einzelne braucht einen Text in resources/lang/de-DE/verifizierung.php —
     * tests/PruefungenTest.php haelt das nach, weil der zusammengesetzte
     * Schluessel te('verifizierung.fehler.' . $x) von UebersetzungenTest nicht
     * erfasst wird.
     *
     * @var list<string>
     */
    public const FEHLER = [
        'animation_nicht_erlaubt',
        'bildverarbeitung_fehlt',
        'datei_zu_gross',
        'dekodierung_fehlgeschlagen',
        'format_nicht_erlaubt',
        'format_nicht_unterstuetzt',
        'kein_bild',
        'nicht_hochgeladen',
        'speicher_reicht_nicht',
        'speichern_fehlgeschlagen',
        'typ_widerspruch',
        'upload_fehlgeschlagen',
        'upload_zu_gross',
        'verarbeitung_fehlgeschlagen',
        'ziel_unbrauchbar',
        'zu_viele_pixel',
        'kein_vorgang',
        'code_abgelaufen',
        'beleg_schon_eingereicht',
        'beleg_unbekannt',
        'beleg_nicht_offen',
        'entscheidung_fehlt',
        'zeitpunkt_ungueltig',
        'gestoert',
        'keine_datei',
        'post_zu_gross',
        'unbekannt',
    ];

    /** Zwischenspeicher fuer die Dauer einer Anfrage. */
    private ?Database $verbindung = null;

    private bool $verbindungVersucht = false;

    /** @var array<string,mixed>|null */
    private ?array $sitzungZwischen = null;

    private bool $sitzungGeladen = false;

    public function __construct(
        // Gleiche Bauform wie Routen, MarktRouten, MedienRouten und
        // MeldeRouten, damit der Einstiegspunkt alle Routenklassen gleich
        // verdrahtet. Die Wurzel wird hier — anders als bei MeldeRouten —
        // tatsaechlich gebraucht: Sie zeigt auf das Belegverzeichnis.
        private readonly string $wurzel,
        private readonly View $ansicht,
    ) {
    }

    public function registrieren(Router $router): void
    {
        /*
         * Kein Muster dieser Klasse traegt einen Platzhalter — die Adressen
         * beziehen sich immer auf das eigene Konto, und eine Kennung darin
         * waere die Einladung, eine fremde einzusetzen. Die Reihenfolge ist
         * damit ohne Wirkung; die statischen Pfade stehen trotzdem vor den
         * Unterpfaden, damit die Regel sichtbar bleibt, sobald hier je ein
         * '{...}' auftaucht.
         */
        $router->get(self::WURZEL, fn (Request $a): Response => $this->uebersicht($a));

        $router->post(self::WURZEL . '/code', fn (Request $a): Response => $this->codeVergeben($a));
        $router->post(self::WURZEL . '/beleg', fn (Request $a): Response => $this->belegHochladen($a));

        $router->get(self::SCHRANKE, fn (Request $a): Response => $this->schrankeSeite($a));
        $router->post(self::SCHRANKE, fn (Request $a): Response => $this->schrankeBestanden($a));
    }

    // --- Verifizierung ------------------------------------------------------

    /**
     * Die eigene Seite: Stand des Vorgangs, Code, Formular, Abzeichen.
     */
    private function uebersicht(Request $anfrage): Response
    {
        $sitzung = $this->sitzung($anfrage);

        if ($sitzung === null) {
            return Response::weiterleitung('/anmelden');
        }

        $db = $this->datenbank();

        if ($db === null) {
            return $this->rendern($anfrage, [
                'vorgang' => null,
                'abzeichen' => false,
                'gestoert' => true,
            ] + $this->rueckmeldung($anfrage));
        }

        $benutzerId = (int) $sitzung['benutzer_id'];
        $belege = $this->belege($db);

        return $this->rendern($anfrage, [
            'vorgang' => $belege->letzterVorgang($benutzerId),
            'abzeichen' => $belege->abzeichenVorhanden($benutzerId),
            'gestoert' => false,
        ] + $this->rueckmeldung($anfrage));
    }

    /** Vergibt einen Code oder erneuert den eines laufenden Vorgangs. */
    private function codeVergeben(Request $anfrage): Response
    {
        if (!Formularschutz::gueltig($anfrage)) {
            return Response::weiterleitung(self::WURZEL);
        }

        $sitzung = $this->sitzung($anfrage);

        if ($sitzung === null) {
            return Response::weiterleitung('/anmelden');
        }

        $db = $this->datenbank();

        if ($db === null) {
            return $this->zurueck('fehler', 'gestoert');
        }

        try {
            $this->belege($db)->codeVergeben((int) $sitzung['benutzer_id']);
        } catch (PruefbelegFehler $fehler) {
            return $this->zurueck('fehler', $fehler->schluessel());
        } catch (\Throwable $fehler) {
            error_log('[MeinSlip/Verifizierung] Code vergeben gescheitert: ' . $fehler->getMessage());

            return $this->zurueck('fehler', 'gestoert');
        }

        return $this->zurueck('erfolg', 'code_vergeben');
    }

    /**
     * Nimmt das eine Selfie entgegen.
     *
     * Die Reihenfolge der ersten beiden Pruefungen ist tragend und woertlich
     * die von MedienRouten::hinzufuegen().
     */
    private function belegHochladen(Request $anfrage): Response
    {
        /*
         * DIE post_max_size-FALLE, UND SIE STEHT ABSICHTLICH VOR DEM
         * FORMULARSCHUTZ.
         *
         * Wird post_max_size ueberschritten, verwirft PHP den gesamten Rumpf:
         * $_POST ist LEER und $_FILES ist LEER. Damit fehlt auch das
         * CSRF-Token, und Formularschutz::gueltig() meldet falsch — die
         * Anfrage saehe aus wie ein Angriff und wuerde kommentarlos
         * zurueckgeleitet. Die Person sieht dann dasselbe Formular ohne jeden
         * Hinweis und probiert es mit demselben Foto noch einmal.
         *
         * Es ist kein Randfall, sondern der Normalfall beim ersten Selfie: Ein
         * aktuelles Telefon liefert 10 bis 15 MB, die uebliche Voreinstellung
         * von post_max_size ist 8 MB. Und hier wiegt es schwerer als beim
         * Angebotsbild — wer beim Identitaetsnachweis dreimal ohne Erklaerung
         * abgewiesen wird, haelt die Plattform fuer kaputt und hoert auf.
         *
         * Erkennbar ist der Fall genau an dieser Kombination: kein
         * Formularfeld angekommen, aber der Browser hat einen Rumpf
         * angekuendigt.
         */
        if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            return $this->zurueck('fehler', 'post_zu_gross');
        }

        if (!Formularschutz::gueltig($anfrage)) {
            return Response::weiterleitung(self::WURZEL);
        }

        $sitzung = $this->sitzung($anfrage);

        if ($sitzung === null) {
            return Response::weiterleitung('/anmelden');
        }

        $db = $this->datenbank();

        if ($db === null) {
            return $this->zurueck('fehler', 'gestoert');
        }

        // $_FILES direkt und nicht ueber Request: Request kennt nur $_GET,
        // $_POST und Kopfzeilen. Eine zweite Dateiabstraktion neben
        // MedienRouten waere eine zweite Wahrheit ueber Uploads.
        $datei = $_FILES[self::FELD] ?? null;

        if (!is_array($datei)) {
            return $this->zurueck('fehler', 'keine_datei');
        }

        if ((int) ($datei['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $this->zurueck('fehler', 'keine_datei');
        }

        try {
            $this->belege($db)->belegHochladen((int) $sitzung['benutzer_id'], $datei);
        } catch (PruefbelegFehler | MedienFehler $fehler) {
            return $this->zurueck('fehler', $fehler->schluessel());
        } catch (\Throwable $fehler) {
            error_log('[MeinSlip/Verifizierung] Beleg gescheitert: ' . $fehler->getMessage());

            return $this->zurueck('fehler', 'gestoert');
        }

        return $this->zurueck('erfolg', 'beleg_eingereicht');
    }

    // --- Altersschranke -----------------------------------------------------

    /**
     * Die Seite der Selbsterklaerung.
     *
     * Sie ist fuer Abgemeldete erreichbar und sagt ihnen, dass die Erklaerung
     * an der Sitzung haengt — ein Vermerk ohne Sitzung waere ein Vermerk ohne
     * Traeger. Weitergeleitet wird bewusst NICHT: Wer die Seite liest, soll
     * ihren Text lesen und nicht auf einem Anmeldeformular landen, das von der
     * Rechtslage nichts sagt.
     */
    private function schrankeSeite(Request $anfrage): Response
    {
        $sitzung = $this->sitzung($anfrage);
        $db = $this->datenbank();

        // gateGilt() liest ausschliesslich die Sitzungszeile — es braucht
        // keine zweite Abfrage. Ohne Verbindung gibt es keine Sitzung, dann
        // gilt auch nichts.
        $gilt = $sitzung !== null
            && $db !== null
            && (new Sitzungen($db))->gateGilt($sitzung);

        return Response::html($this->ansicht->rendern('verifizierung.altersschranke', [
            '__layout' => 'layout',
            'titel' => t('verifizierung.schranke_titel') . ' — ' . t('allgemein.marke'),
            'sprache' => Lang::sprache(),
            'sitzung' => $sitzung,
            'aktiv' => self::SCHRANKE,
            'gilt' => $gilt,
            'minuten' => Sitzungen::GATE_GUELTIG_MINUTEN,
            // Die eine Angabe, an der die Seite ihre eigene Wirkungslosigkeit
            // festmacht. Sie kommt aus der Fachklasse und nicht aus der
            // Vorlage: Wer das Verfahren spaeter anbindet, aendert eine
            // Methode und nicht sieben Vorlagen.
            'schrankeGebunden' => Medien::altersschrankeGebunden(),
        ] + $this->rueckmeldung($anfrage)));
    }

    /**
     * Vermerkt die Selbsterklaerung an der Sitzung.
     *
     * Sitzungen::gateBestanden() erwartet die Kennung im Klartext und hasht
     * sie selbst — genau der Wert, der im Cookie steht.
     */
    private function schrankeBestanden(Request $anfrage): Response
    {
        if (!Formularschutz::gueltig($anfrage)) {
            return Response::weiterleitung(self::SCHRANKE);
        }

        $kennung = $_COOKIE[Sitzungen::COOKIE] ?? null;

        if (!is_string($kennung) || $kennung === '' || $this->sitzung($anfrage) === null) {
            return Response::weiterleitung('/anmelden');
        }

        $db = $this->datenbank();

        if ($db === null) {
            return Response::weiterleitung(self::SCHRANKE . '?fehler=gestoert');
        }

        try {
            (new Sitzungen($db))->gateBestanden($kennung);
        } catch (\Throwable $fehler) {
            error_log('[MeinSlip/Verifizierung] Altersschranke gescheitert: ' . $fehler->getMessage());

            return Response::weiterleitung(self::SCHRANKE . '?fehler=gestoert');
        }

        return Response::weiterleitung(self::SCHRANKE . '?erfolg=schranke_bestanden');
    }

    // --- Hilfen -------------------------------------------------------------

    /** @param array<string,mixed> $daten */
    private function rendern(Request $anfrage, array $daten): Response
    {
        $zahlen = [
            'stunden' => Pruefbelege::CODE_GUELTIG_STUNDEN,
            'tage' => Pruefbelege::FRIST_UNBEARBEITET_TAGE,
            'entscheidungstage' => Pruefbelege::FRIST_ENTSCHIEDEN_TAGE,
            'mb' => intdiv(Bilder::MAX_BYTES, 1024 * 1024),
            'kante' => Bilder::MAX_KANTE,
        ];

        return Response::html($this->ansicht->rendern('verifizierung.uebersicht', $daten + [
            '__layout' => 'layout',
            'titel' => t('verifizierung.titel') . ' — ' . t('allgemein.marke'),
            'sprache' => Lang::sprache(),
            'sitzung' => $this->sitzung($anfrage),
            'aktiv' => self::WURZEL,
            'zahlen' => $zahlen,
            'accept' => implode(',', Bilder::formate()),
            'maxBytes' => Bilder::MAX_BYTES,
            'statusOffen' => Pruefbelege::STATUS_OFFEN,
            'statusEingereicht' => Pruefbelege::STATUS_EINGEREICHT,
            'statusFreigegeben' => Pruefbelege::STATUS_FREIGEGEBEN,
            'statusAbgelehnt' => Pruefbelege::STATUS_ABGELEHNT,
            'jetzt' => gmdate('Y-m-d H:i:s'),
        ]));
    }

    /**
     * Post/Redirect/Get mit Rueckmeldung.
     *
     * Ein unbekannter Schluessel wird zu 'unbekannt' und nicht zu nichts:
     * Stumm zu bleiben waere das Schlimmste — die Person saehe ein
     * unveraendertes Formular ohne jeden Hinweis.
     */
    private function zurueck(string $feld, string $schluessel): Response
    {
        $erlaubt = $feld === 'erfolg' ? self::ERFOLGE : self::FEHLER;
        $wert = in_array($schluessel, $erlaubt, true) ? $schluessel : 'unbekannt';

        return Response::weiterleitung(self::WURZEL . '?' . $feld . '=' . rawurlencode($wert));
    }

    /**
     * Liest Erfolgs- und Fehlerhinweis aus der Adresszeile.
     *
     * @return array{erfolg: string|null, fehler: string|null}
     */
    private function rueckmeldung(Request $anfrage): array
    {
        return [
            'erfolg' => $this->ausListe($anfrage->eingabe('erfolg'), self::ERFOLGE),
            'fehler' => $this->ausListe($anfrage->eingabe('fehler'), self::FEHLER),
        ];
    }

    /** @param list<string> $erlaubt */
    private function ausListe(?string $wert, array $erlaubt): ?string
    {
        return $wert !== null && in_array($wert, $erlaubt, true) ? $wert : null;
    }

    private function belege(Database $db): Pruefbelege
    {
        return new Pruefbelege($db, Pruefbelege::verzeichnis($this->wurzel));
    }

    /**
     * Die Datenbankverbindung dieser Anfrage.
     *
     * 'versucht' ist ein eigenes Feld, weil null ein gueltiges Ergebnis ist.
     */
    private function datenbank(): ?Database
    {
        if ($this->verbindungVersucht) {
            return $this->verbindung;
        }

        $this->verbindungVersucht = true;

        try {
            $this->verbindung = Database::ausEnv();
        } catch (\Throwable) {
            $this->verbindung = null;
        }

        return $this->verbindung;
    }

    /**
     * Die Sitzung dieser Anfrage.
     *
     * @return array<string,mixed>|null
     */
    private function sitzung(Request $anfrage): ?array
    {
        if ($this->sitzungGeladen) {
            return $this->sitzungZwischen;
        }

        $this->sitzungGeladen = true;
        $kennung = $_COOKIE[Sitzungen::COOKIE] ?? null;

        if (!is_string($kennung) || $kennung === '') {
            return null;
        }

        $db = $this->datenbank();

        if ($db === null) {
            return null;
        }

        try {
            return $this->sitzungZwischen = (new Sitzungen($db))->laden($kennung);
        } catch (\Throwable) {
            return null;
        }
    }
}
