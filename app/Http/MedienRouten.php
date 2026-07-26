<?php

declare(strict_types=1);

namespace MeinSlip\Http;

use MeinSlip\Core\Database;
use MeinSlip\Core\Request;
use MeinSlip\Core\Response;
use MeinSlip\Core\Router;
use MeinSlip\Core\View;
use MeinSlip\Domain\Account\Konten;
use MeinSlip\Domain\Account\Sitzungen;
use MeinSlip\Domain\Catalog\Angebote;
use MeinSlip\Domain\Media\Bilder;
use MeinSlip\Domain\Media\Medien;
use MeinSlip\Domain\Media\MedienFehler;

/**
 * Hochladen, Entfernen und Ausliefern von Bildern.
 *
 * Die Bilder liegen unter storage/medien und damit AUSSERHALB von public/.
 * Kein Webserver liefert sie je direkt aus; jeder Abruf laeuft durch diese
 * Klasse. Das ist der ganze Grund, warum es sie gibt — ein Bild in public/
 * waere fuer jeden abrufbar, der die Adresse errät, und "Adresse errät" heisst
 * bei getragener Waesche: fuer jeden.
 *
 * DER PFAD KOMMT NIEMALS AUS DER ANFRAGE. Die Adresse traegt eine Ganzzahl,
 * die Ganzzahl waehlt eine Datenbankzeile, und die Zeile traegt den Pfad.
 * Traversal ist damit strukturell unmoeglich, nicht weggefiltert. Es gibt
 * keinen Filter, den jemand vergessen, falsch schreiben oder mit einer
 * doppelten Prozentkodierung umgehen koennte, weil es nichts zu filtern gibt.
 * Wer diese Klasse spaeter erweitert: Sobald irgendein Teil eines Pfades aus
 * $anfrage stammt, ist diese Zusicherung weg, und sie kommt nicht zurueck,
 * indem man '..' entfernt.
 *
 * DIE BERECHTIGUNG KOMMT VOM GEBUNDENEN OBJEKT, NICHT VON DER MEDIENZEILE.
 * Ein Bild hat keine eigene Sichtbarkeit — es ist genau so sichtbar wie das
 * Angebot, an dem es haengt. Deshalb gilt hier woertlich dieselbe Bedingung
 * wie in MarktRouten::angebotSeite(): Status 'aktiv', Konto der Verkaeuferin
 * aktiv, Faehigkeit 'verkaufen' vorhanden; daneben die Vorschau fuer die
 * Eigentuemerin und die Verwaltung. Waere die Pruefung hier laxer, liesse sich
 * die gesamte Sichtbarkeitslogik der Angebotsseite ueber die Bildadresse
 * umgehen — und Bilder sind genau der Teil, um den es dabei ginge.
 *
 * ZWEI ZONEN, UND DIE ZWEITE IST HEUTE GESCHLOSSEN. Ein Bild mit explizit = 0
 * wird voll ausgeliefert. Ein Bild mit explizit = 1 bekommen Fremde
 * ueberhaupt nicht — auch nicht als unscharfe Vorschau. Die Begruendung steht
 * bei Medien::explizitSichtbar(): Eine Selbsterklaerung ist keine geschlossene
 * Benutzergruppe nach § 4 Abs. 2 JMStV, und solange es keine echte Schranke
 * gibt, ist Nichtausliefern die einzige verteidigbare Antwort.
 *
 * WARUM DIE SCHREIBENDEN ROUTEN HIER STEHEN UND NICHT IN MarktRouten:
 * Ein Upload ist der einzige Weg, auf dem fremde Bytes auf die Platte dieses
 * Servers kommen. Diese Strecke gehoert deshalb in eine Datei, deren Kopf man
 * gelesen hat, bevor man sie anfasst — nicht zwischen Formularfelder eines
 * Angebots.
 */
final class MedienRouten
{
    /** Wurzel der Strecke. An einer Stelle, damit ein Umzug eine Zeile ist. */
    private const WURZEL = '/medien';

    /**
     * Rueckmeldungen, die als Abfrageparameter durch eine Weiterleitung
     * getragen werden duerfen.
     *
     * Oeffentlich, weil MarktRouten::bearbeitenFormular() sie einliest: Die
     * Weiterleitung nach dem Upload landet auf /verkaufen/{id}, und die Vorlage
     * dort zeigt den Text. Ohne Weissliste liesse sich ueber eine gebaute
     * Adresse ein beliebiger Schluessel in die Uebersetzung schieben.
     *
     * @var list<string>
     */
    public const ERFOLGE = [
        'bild_hinzugefuegt',
        'bild_entfernt',
    ];

    /**
     * Erlaubte Fehlerschluessel.
     *
     * Die ersten sechzehn sind woertlich die von MedienFehler::schluessel() aus
     * Bilder, danach die drei aus Medien, danach vier eigene. Jeder einzelne
     * braucht einen Text in resources/lang/de-DE/medien.php — tests/
     * MedienZugriffTest.php haelt das nach, weil der zusammengesetzte
     * Schluessel te('medien.fehler.' . $x) von UebersetzungenTest nicht
     * geprueft wird.
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
        'angebot_unbekannt',
        'medium_unbekannt',
        'zu_viele_bilder',
        'keine_datei',
        'post_zu_gross',
        'gestoert',
        'unbekannt',
    ];

    /** Name des Dateifeldes im Formular. */
    private const FELD = 'bild';

    /**
     * Fester Inhaltstyp der Auslieferung.
     *
     * Nach der Neukodierung in Bilder IST jede gespeicherte Datei ein JPEG,
     * gleich was hochgeladen wurde. Der Typ wird deshalb nicht aus der Zeile
     * gelesen und schon gar nicht aus dem Dateinamen geraten — er ist eine
     * Tatsache der Pipeline.
     */
    private const TYP = 'image/jpeg';

    /** Zwischenspeicher fuer die Dauer einer Anfrage. */
    private ?Database $verbindung = null;

    private bool $verbindungVersucht = false;

    /** @var array<string,mixed>|null */
    private ?array $sitzungZwischen = null;

    private bool $sitzungGeladen = false;

    public function __construct(
        // Gleiche Bauform wie Routen, MarktRouten und MeldeRouten, damit der
        // Einstiegspunkt alle Routenklassen gleich verdrahtet.
        //
        // Die Wurzel wird hier — anders als dort — tatsaechlich gebraucht: Sie
        // zeigt auf das Medienverzeichnis. Die Ansicht dagegen ist heute
        // ungenutzt, weil diese Strecke keine eigene Seite rendert: Sie
        // liefert Bytes aus oder leitet nach /verkaufen/{id} zurueck. Sie
        // bleibt trotzdem im Konstruktor — die erste eigene Seite (etwa eine
        // Bilderverwaltung ausserhalb des Angebotsformulars) braeuchte sie
        // sonst als Signaturaenderung mitsamt Anpassung in public/index.php.
        private readonly string $wurzel,
        private readonly View $ansicht,
    ) {
    }

    public function registrieren(Router $router): void
    {
        /*
         * REIHENFOLGE UND FORM DER MUSTER.
         *
         * Die lesenden Adressen tragen den Gegenstand vorn ('/medien/angebot/
         * {id}'), die schreibenden das Verb ('/medien/hinzufuegen/{angebot}').
         * Das ist kein Geschmack: Beide Familien tragen eine Ganzzahl, aber
         * eine ganz verschiedene — beim Lesen die Kennung der MEDIENZEILE, beim
         * Schreiben die des ANGEBOTS. Stuenden sie unter demselben Praefix,
         * saehe man einer Adresse nicht mehr an, welche Kennung sie meint, und
         * genau daraus entsteht die Zeile, die eine Berechtigung gegen das
         * falsche Objekt prueft.
         *
         * Die spezifischere Route zuerst: '{id}' wird zu ([^/]+) und frisst
         * keinen Schraegstrich, eine Kollision zwischen '/medien/angebot/{id}'
         * und '/medien/angebot/{id}/vorschau' ist damit ohnehin ausgeschlossen.
         * Die Reihenfolge steht trotzdem so da, damit die Regel sichtbar
         * bleibt, wenn hier je ein Fangmuster auftaucht.
         */
        $router->get(
            self::WURZEL . '/angebot/{id}/vorschau',
            fn (Request $a, array $p): Response => $this->ausliefern($a, self::kennung($p['id'] ?? null), true)
        );

        $router->get(
            self::WURZEL . '/angebot/{id}',
            fn (Request $a, array $p): Response => $this->ausliefern($a, self::kennung($p['id'] ?? null), false)
        );

        $router->post(
            self::WURZEL . '/hinzufuegen/{angebot}',
            fn (Request $a, array $p): Response => $this->hinzufuegen($a, self::kennung($p['angebot'] ?? null))
        );

        $router->post(
            self::WURZEL . '/entfernen/{medium}',
            fn (Request $a, array $p): Response => $this->entfernen($a, self::kennung($p['medium'] ?? null))
        );
    }

    /**
     * Eine Kennung aus dem Pfad — oder 0, was ueberall 404 bedeutet.
     *
     * ctype_digit statt (int), aus demselben Grund wie in
     * NachrichtenRouten::neu(): '1abc' wuerde sonst stillschweigend zu 1.
     *
     * Fuer die Berechtigung ist das folgenlos — die haengt am gebundenen
     * Angebot und nicht an der Schreibweise der Adresse. Es geht um etwas
     * anderes: Ohne diese Pruefung liefern '/medien/angebot/1',
     * '/medien/angebot/1x' und beliebig viele weitere Adressen dieselbe private
     * Datei aus. Ein Bild mit unbegrenzt vielen gueltigen Adressen ist kein
     * Loch, aber es ist auch nichts, was man an einem Gegenstand haben will,
     * bei dem Diskretion die Hauptzusage ist.
     *
     * Der Rueckvergleich auf die Zeichenkette erledigt zugleich die fuehrenden
     * Nullen: ctype_digit('007') ist wahr, und ohne diesen Vergleich waeren
     * '7', '07' und '007' wieder drei Adressen fuer dieselbe Datei.
     */
    private static function kennung(mixed $roh): int
    {
        if (!is_string($roh) || !ctype_digit($roh)) {
            return 0;
        }

        $kennung = (int) $roh;

        return (string) $kennung === $roh ? $kennung : 0;
    }

    // --- Auslieferung ------------------------------------------------------

    /**
     * Liefert Bild oder Vorschau aus — oder 404.
     *
     * JEDE ABLEHNUNG IST DIESELBE 404, ohne Rumpf und ohne Unterschied. Es gibt
     * hier bewusst keine 403: Ein 403 sagt "das gibt es, du darfst nur nicht",
     * und damit liesse sich ueber /medien/angebot/1..N durchzaehlen, welche
     * Bilder existieren und welche als explizit gekennzeichnet sind. Das ist
     * bei diesem Gegenstand keine akademische Unterscheidung.
     */
    private function ausliefern(Request $anfrage, int $medienId, bool $vorschau): Response
    {
        if ($medienId <= 0) {
            return $this->nichtGefunden();
        }

        $db = $this->datenbank();

        if ($db === null) {
            // Eine Stoerung ist keine Auskunft. Auch sie sieht aus wie ein
            // fehlendes Bild — die Alternative waere ein 500, das dem Abrufer
            // verriete, dass es die Kennung gibt.
            return $this->nichtGefunden();
        }

        $medien = new Medien($db, Medien::verzeichnis($this->wurzel));
        $zeile = $medien->mitAngebot($medienId);

        if ($zeile === null) {
            return $this->nichtGefunden();
        }

        $verkaeuferId = (int) $zeile['verkaeufer_id'];
        $konten = new Konten($db);
        $verkaeufer = $db->eine('SELECT status FROM benutzer WHERE id = :id', ['id' => $verkaeuferId]);

        // WOERTLICH DIE BEDINGUNG AUS MarktRouten::angebotSeite(). Wer sie dort
        // aendert, muss sie hier aendern — sonst zeigt die eine Stelle das
        // Angebot nicht mehr und die andere liefert seine Bilder weiter aus.
        $oeffentlich = $zeile['angebot_status'] === Angebote::STATUS_AKTIV
            && $verkaeufer !== null
            && (string) $verkaeufer['status'] === 'aktiv'
            && $konten->hatFaehigkeit($verkaeuferId, Konten::FAEHIGKEIT_VERKAUFEN);

        $sitzung = $this->sitzung($anfrage);
        $betrachterId = $sitzung !== null ? (int) $sitzung['benutzer_id'] : 0;

        $darfVorschau = $betrachterId !== 0
            && ($betrachterId === $verkaeuferId
                || $konten->hatFaehigkeit($betrachterId, Konten::FAEHIGKEIT_VERWALTEN));

        if (!$oeffentlich && !$darfVorschau) {
            return $this->nichtGefunden();
        }

        // Die zweite Zone. Sie liegt NACH der ersten und ersetzt sie nicht:
        // Ein explizites Bild an einem nicht oeffentlichen Angebot ist doppelt
        // verschlossen.
        //
        // Der dritte Wert ist die Selbsterklaerung dieser Sitzung
        // (§ 4 Abs. 2 JMStV, zweite Stufe des AVS-Rasters, vermerkt von
        // VerifizierungsRouten unter /altersschranke). Er wird hier
        // ANGESCHLOSSEN und oeffnet trotzdem nichts: explizitSichtbar()
        // verlangt zusaetzlich Medien::altersschrankeGebunden(), und das ist
        // hart false. Die Erklaerung ist eine Schaltflaeche, keine
        // geschlossene Benutzergruppe — sie darf kein einziges Bild
        // freischalten, solange kein lizenziertes Verfahren gebunden ist.
        $selbsterklaerung = $sitzung !== null && (new Sitzungen($db))->gateGilt($sitzung);

        if ($zeile['explizit'] === true
            && !$medien->explizitSichtbar((int) $zeile['angebot_id'], $betrachterId, $selbsterklaerung)) {
            return $this->nichtGefunden();
        }

        $datei = $medien->datei($vorschau ? $zeile['vorschau_pfad'] : $zeile['pfad']);

        if ($datei === null) {
            return $this->nichtGefunden();
        }

        // Oeffentlich zwischenspeichern darf ausschliesslich die Vorschau eines
        // oeffentlichen, nicht expliziten Angebots. Alles andere haengt an
        // einer Person: das scharfe Bild, weil es der eigentliche Gegenstand
        // ist, und jede Vorschau in der Eigentuemer- oder Verwaltungsansicht,
        // weil sie dort ueberhaupt nur wegen der Anmeldung erscheint. Ein
        // zwischengeschalteter Proxy, der so etwas aufhebt, gibt es beim
        // naechsten Abruf ohne Anmeldung wieder heraus — und 'private' allein
        // waere zu wenig, weil es den BROWSERcache nicht ausschliesst.
        $oeffentlicheVorschau = $oeffentlich && $vorschau && $zeile['explizit'] === false;

        return Response::datei($datei, self::TYP, [
            'Cache-Control' => $oeffentlicheVorschau ? 'public, max-age=86400' : 'private, no-store',
        ]);
    }

    /** Die eine Ablehnung dieser Strecke. */
    private function nichtGefunden(): Response
    {
        // Ohne Rumpf: Es gibt nichts zu sagen, und jeder Text waere ein
        // Unterschied, an dem sich Faelle auseinanderhalten liessen.
        return Response::text('', 404);
    }

    // --- Hochladen ---------------------------------------------------------

    /**
     * Nimmt ein Bild zu einem eigenen Angebot entgegen.
     *
     * Die Reihenfolge der ersten drei Pruefungen ist tragend und steht bei
     * jeder einzeln.
     */
    private function hinzufuegen(Request $anfrage, int $angebotId): Response
    {
        /*
         * DIE post_max_size-FALLE, UND SIE STEHT ABSICHTLICH VOR DEM
         * FORMULARSCHUTZ.
         *
         * Wird post_max_size ueberschritten, verwirft PHP den gesamten Rumpf:
         * $_POST ist LEER und $_FILES ist LEER. Damit fehlt auch das
         * CSRF-Token, und Formularschutz::gueltig() meldet falsch — die
         * Anfrage saehe aus wie ein Angriff und wuerde kommentarlos
         * zurueckgeleitet. Die Nutzerin sieht dann dasselbe Formular ohne jeden
         * Hinweis und probiert es mit demselben Foto noch einmal.
         *
         * Das ist kein Randfall: Es passiert beim ERSTEN Handyfoto. Ein
         * aktuelles Telefon liefert 10 bis 15 MB, die uebliche Voreinstellung
         * von post_max_size ist 8 MB.
         *
         * Erkennbar ist der Fall genau an dieser Kombination — kein
         * Formularfeld angekommen, aber der Browser hat einen Rumpf
         * angekuendigt. Eine echte anfrage ohne Rumpf hat CONTENT_LENGTH 0
         * oder gar nicht.
         */
        if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            return $this->zurueck($angebotId, 'medienfehler', 'post_zu_gross');
        }

        if (!Formularschutz::gueltig($anfrage)) {
            return Response::weiterleitung('/verkaufen/' . $angebotId);
        }

        $zugang = $this->verkaufszugang($anfrage);

        if ($zugang['db'] === null) {
            return Response::weiterleitung('/verkaufen');
        }

        // $_FILES DIREKT UND NICHT UEBER Request: Request kennt nur $_GET,
        // $_POST und Kopfzeilen; eine Dateiabstraktion gibt es im ganzen
        // Projekt nicht. Sie hier zu erfinden hiesse, eine zweite Wahrheit
        // ueber Uploads einzufuehren, die nur diese eine Route benutzt. Der
        // Formularschutz greift trotzdem: PHP fuellt $_POST auch bei
        // multipart/form-data, das Token kommt also an.
        $datei = $_FILES[self::FELD] ?? null;

        if (!is_array($datei)) {
            return $this->zurueck($angebotId, 'medienfehler', 'keine_datei');
        }

        // Ein leeres Dateifeld ist kein Fehler der Technik, sondern eine
        // vergessene Auswahl — mit eigenem Text statt 'upload_fehlgeschlagen'.
        if ((int) ($datei['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $this->zurueck($angebotId, 'medienfehler', 'keine_datei');
        }

        // OHNE ANTWORT GILT EXPLIZIT. Das Formular verlangt eine Auswahl, aber
        // 'required' ist eine Bequemlichkeit des Browsers und keine Sicherung.
        // Faellt die Angabe weg — abgeschaltetes JavaScript, altes Geraet, von
        // Hand gebaute Anfrage —, gilt die vorsichtigere Annahme. Andersherum
        // waere ein vergessenes Feld ein veroeffentlichtes Bild.
        $explizit = ($anfrage->eingabe('explizit', '') ?? '') !== 'nein';

        try {
            (new Medien($zugang['db'], Medien::verzeichnis($this->wurzel)))
                ->hinzufuegen($angebotId, $zugang['benutzerId'], $datei, $explizit);
        } catch (MedienFehler $fehler) {
            return $this->zurueck($angebotId, 'medienfehler', $fehler->schluessel());
        } catch (\Throwable $fehler) {
            error_log('[MeinSlip/Medien] Upload gescheitert: ' . $fehler->getMessage());

            return $this->zurueck($angebotId, 'medienfehler', 'gestoert');
        }

        return $this->zurueck($angebotId, 'medienerfolg', 'bild_hinzugefuegt');
    }

    /**
     * Entfernt ein eigenes Bild.
     *
     * Die Adresse traegt die Kennung des MEDIUMS; wohin danach geleitet wird,
     * steht erst nach dem Laden fest — deshalb wird die Angebotskennung aus der
     * Zeile geholt und nicht aus dem Formular. Ein Formularfeld waere die
     * bequemere Loesung und zugleich die Stelle, an der jemand die
     * Weiterleitung auf ein fremdes Angebot lenken koennte.
     */
    private function entfernen(Request $anfrage, int $medienId): Response
    {
        if (!Formularschutz::gueltig($anfrage)) {
            return Response::weiterleitung('/verkaufen');
        }

        $zugang = $this->verkaufszugang($anfrage);

        if ($zugang['db'] === null) {
            return Response::weiterleitung('/verkaufen');
        }

        $medien = new Medien($zugang['db'], Medien::verzeichnis($this->wurzel));
        $zeile = $medien->mitAngebot($medienId);

        // Vor dem Entfernen gelesen, damit das Ziel der Weiterleitung
        // feststeht. Medien::entfernen() prueft das Eigentum gleich noch
        // einmal selbst — diese Zeile ersetzt die Pruefung nicht.
        $angebotId = $zeile !== null && (int) $zeile['verkaeufer_id'] === $zugang['benutzerId']
            ? (int) $zeile['angebot_id']
            : 0;

        try {
            $medien->entfernen($medienId, $zugang['benutzerId']);
        } catch (MedienFehler $fehler) {
            return $angebotId === 0
                ? Response::weiterleitung('/verkaufen')
                : $this->zurueck($angebotId, 'medienfehler', $fehler->schluessel());
        } catch (\Throwable $fehler) {
            error_log('[MeinSlip/Medien] Entfernen gescheitert: ' . $fehler->getMessage());

            return $this->zurueck($angebotId, 'medienfehler', 'gestoert');
        }

        return $this->zurueck($angebotId, 'medienerfolg', 'bild_entfernt');
    }

    // --- Hilfen ------------------------------------------------------------

    /**
     * Zurueck zur Bearbeitungsseite, mit Rueckmeldung.
     *
     * Post/Redirect/Get, damit ein Neuladen nicht dieselbe Datei ein zweites
     * Mal hochlaedt. Der Schluessel geht durch die Weissliste, bevor er in die
     * Adresse kommt — ein unbekannter wird zu 'unbekannt', nicht zu nichts:
     * Stumm zu bleiben waere das Schlimmste, die Person saehe ein unveraendertes
     * Formular ohne jeden Hinweis.
     */
    private function zurueck(int $angebotId, string $feld, string $schluessel): Response
    {
        if ($angebotId <= 0) {
            return Response::weiterleitung('/verkaufen');
        }

        $erlaubt = $feld === 'medienerfolg' ? self::ERFOLGE : self::FEHLER;
        $wert = in_array($schluessel, $erlaubt, true) ? $schluessel : 'unbekannt';

        return Response::weiterleitung('/verkaufen/' . $angebotId . '?' . $feld . '=' . rawurlencode($wert));
    }

    /**
     * Sitzung, Datenbank und Verkaufsfaehigkeit in einem Griff.
     *
     * Dieselbe Bauform wie MarktRouten::verkaufszugang() — die Strecke ist eine
     * Verlaengerung von /verkaufen und muss dieselbe Schwelle haben. Wer die
     * Verkaufsfaehigkeit verloren hat, laedt auch keine Bilder mehr hoch.
     *
     * @return array{db:Database|null, benutzerId:int}
     */
    private function verkaufszugang(Request $anfrage): array
    {
        $sitzung = $this->sitzung($anfrage);

        if ($sitzung === null) {
            return ['db' => null, 'benutzerId' => 0];
        }

        $db = $this->datenbank();

        if ($db === null) {
            return ['db' => null, 'benutzerId' => 0];
        }

        $benutzerId = (int) $sitzung['benutzer_id'];

        if (!(new Konten($db))->hatFaehigkeit($benutzerId, Konten::FAEHIGKEIT_VERKAUFEN)) {
            return ['db' => null, 'benutzerId' => $benutzerId];
        }

        return ['db' => $db, 'benutzerId' => $benutzerId];
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

    /**
     * Die Formate, die dieser Server lesen kann — fuer das accept-Attribut.
     *
     * Steht hier und nicht in der Vorlage, damit die Vorlage keine Fachklasse
     * kennen muss. Ist die Bildverarbeitung ausgefallen, kommt eine leere
     * Zeichenkette zurueck; das Formular laesst dann alles zu und Bilder weist
     * mit 'bildverarbeitung_fehlt' ab — das ist die ehrlichere Reihenfolge als
     * ein accept-Attribut, das nichts erlaubt.
     */
    public static function accept(): string
    {
        return implode(',', Bilder::formate());
    }
}
