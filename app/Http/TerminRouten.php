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
use MeinSlip\Domain\Chat\TerminFehler;
use MeinSlip\Domain\Chat\Termine;
use MeinSlip\Domain\Chat\Unterhaltungen;

/**
 * Die Terminstrecke: vorschlagen, annehmen, ablehnen, quittieren.
 *
 * WO DIE OBERFLAECHE LIEGT UND WARUM SIE ZWEIGETEILT IST. Die Termine SELBST
 * stehen im Chatfenster (resources/views/nachrichten/fenster.php) — dort, wo
 * die beiden Personen ohnehin miteinander reden, und dort, wo die Sperre und
 * die Deklarationspflicht schon greifen. Nur das ANLEGEN hat eine eigene Seite
 * (/termine/neu). Das ist kein Schoenheitsentwurf, sondern hat einen Grund:
 * Auf dieser Seite steht die Erklaerung zu jeder Treffpunktart und der Hinweis,
 * dass hier keine Anschrift hingehoert. Beides muss lesen, wer noch waehlt —
 * eingeklemmt zwischen Nachrichtenverlauf und Eingabefeld liest es niemand.
 *
 * Fuenf Entscheidungen tragen diese Klasse:
 *
 *  1. FORMULARSEITEN MIT POST/REDIRECT/GET, KEIN fetch(). Wortgleich die
 *     Begruendung aus NachrichtenRouten: Es gibt in diesem Projekt keine
 *     XHR-Infrastruktur, und deutscher Text, den JavaScript erzeugt, entzoege
 *     sich tests/UebersetzungenTest.php — die i18n-Regel haette genau dort ein
 *     Loch, wo am meisten Text entsteht.
 *
 *  2. DER FEHLERWEG BEIM ANLEGEN RENDERT NEU, ALLE ANDEREN LEITEN UM. Ein
 *     abgewiesener Vorschlag behaelt Datum, Uhrzeit und Region — bei
 *     'zu_schnell' waere erneutes Eintippen die zweite Bestrafung fuer
 *     dieselbe Sache. Annehmen, Ablehnen und Quittieren haben dagegen keine
 *     Eingabe, die verloren gehen koennte; sie fuehren zurueck ins
 *     Chatfenster, wo der Termin steht.
 *
 *  3. RUECKMELDUNGEN LAUFEN UNTER EIGENEM NAMEN. Die Adresse traegt
 *     '?termin_erfolg=' und '?termin_fehler=' und nicht die Felder des Chats.
 *     Sonst muesste jeder Terminfehler zusaetzlich einen Text unter
 *     'chat.fehler.' bekommen, und dieselbe Meldung stuende zweimal im
 *     Sprachverzeichnis — zwei Fassungen, die auseinanderlaufen. Die beiden
 *     Weisslisten sind oeffentlich, weil NachrichtenRouten sie zum Filtern
 *     braucht: Ohne Weissliste liesse sich ueber eine gebaute Adresse
 *     beliebiger Text in die Seite schieben.
 *
 *  4. KEINE AUSKUNFT UEBER FREMDE TERMINE. Termine::unterhaltungVon() wirft
 *     "gibt es nicht" und "gehoert dir nicht" in dasselbe null, und diese
 *     Klasse haelt das durch: Beide Faelle landen auf der Uebersicht der
 *     eigenen Gespraeche. Dass zwei bestimmte Menschen zu einer bestimmten
 *     Zeit verabredet sind, ist die empfindlichste Auskunft dieser Plattform —
 *     empfindlicher noch als der Nachrichtenverlauf, weil sie einen Ort und
 *     eine Uhrzeit nennt.
 *
 *  5. KEIN STANDORT, NIRGENDS. Keine Route nimmt Koordinaten entgegen, keine
 *     Vorlage fragt danach, und der Browser wird nicht gefragt.
 *     Permissions-Policy: geolocation=(self) steht global in
 *     Response::senden(), und kein Code dieser Strecke macht davon Gebrauch —
 *     das bleibt so.
 */
final class TerminRouten
{
    /** Wurzelpfad der Strecke. An einer Stelle, damit ein Umzug eine Zeile ist. */
    private const WURZEL = '/termine';

    /** Das Anlegeformular. Statisch, deshalb VOR jedem Platzhalter registriert. */
    private const NEU = '/termine/neu';

    /** Wohin die Strecke zurueckfuehrt, wenn nichts Besseres bekannt ist. */
    private const CHAT = '/nachrichten';

    /** Sprungmarke auf den Terminabschnitt im Chatfenster. */
    private const MARKE = '#termine';

    /**
     * Rueckmeldungen, die eine Weiterleitung als '?termin_erfolg=' tragen darf.
     *
     * Oeffentlich, weil NachrichtenRouten damit filtert — siehe Klassenkopf.
     * 'termin_quittiert' und 'termin_uebergeben' sind zwei verschiedene Dinge:
     * Das erste heisst "deine Quittung steht, die andere fehlt noch", das
     * zweite "beide stehen, die Uebergabe gilt". Eine gemeinsame Meldung waere
     * an genau der Stelle unklar, an der die Zusicherung dieses Pakets liegt.
     *
     * @var list<string>
     */
    public const ERFOLGE = [
        'termin_vorgeschlagen',
        'termin_angenommen',
        'termin_abgelehnt',
        'termin_quittiert',
        'termin_uebergeben',
    ];

    /**
     * Erlaubte Fehlerschluessel.
     *
     * Die ersten zwanzig sind woertlich die von TerminFehler::schluessel(),
     * danach kommen drei eigene. tests/TermineTest.php haelt die Liste gegen
     * die Wurfstellen in app/ — ein neuer Fehlerschluessel dort faellt hier
     * auf, bevor ihn jemand als '[[termin.fehler.x]]' liest.
     *
     * @var list<string>
     */
    public const FEHLER = [
        'unterhaltung_unbekannt',
        'nicht_teilnehmer',
        'gesperrt',
        'termin_unbekannt',
        'zeitpunkt_ungueltig',
        'zeitpunkt_vergangen',
        'zeitpunkt_zu_fern',
        'treffpunkt_unbekannt',
        'region_fehlt',
        'region_zu_lang',
        'grund_zu_lang',
        'eigener_vorschlag',
        'nicht_offen',
        'nicht_angenommen',
        'zu_frueh',
        'bereits_quittiert',
        'zu_schnell',
        'zu_viele_offen',
        'status_unbekannt',
        'unerlaubter_wechsel',
        'termin_ungueltig',
        'gestoert',
        'unbekannt',
    ];

    /**
     * Zwischenspeicher fuer die Dauer einer Anfrage — wie in NachrichtenRouten.
     *
     * Beide Merker sind noetig, weil null in beiden Faellen ein gueltiges
     * Ergebnis ist: keine Verbindung heisst Stoerung, keine Sitzung heisst
     * abgemeldet.
     */
    private ?Database $verbindung = null;

    private bool $verbindungVersucht = false;

    /** @var array<string,mixed>|null */
    private ?array $sitzungZwischen = null;

    private bool $sitzungGeladen = false;

    public function __construct(
        // Gleiche Bauform wie Routen, MarktRouten, NachrichtenRouten,
        // MeldeRouten und VerwaltungsRouten: Wurzel und Ansicht. Die Wurzel
        // braucht diese Strecke heute nicht, sie bleibt trotzdem im
        // Konstruktor, damit der Einstiegspunkt alle Routenklassen gleich
        // verdrahtet.
        private readonly string $wurzel,
        private readonly View $ansicht,
    ) {
    }

    public function registrieren(Router $router): void
    {
        /*
         * DIE REIHENFOLGE IST TRAGEND — auch wenn sie es hier ausnahmsweise
         * nicht sein muesste. Der Router wandelt {id} in ([^/]+); ein
         * Schraegstrich passt darin nicht, '/termine/{id}/annehmen' kann
         * '/termine/neu' also gar nicht schlucken. Der statische Pfad steht
         * trotzdem zuerst, weil das die Regel dieses Projekts ist und weil die
         * naechste Route, die jemand ergaenzt, vielleicht '/termine/{id}' heisst.
         *
         * Diese Klasse teilt sich mit keiner anderen einen Pfad: '/termine'
         * kommt in Routen, MarktRouten, NachrichtenRouten, MedienRouten,
         * MeldeRouten und VerwaltungsRouten nicht vor.
         */
        $router->get(self::NEU, fn (Request $a): Response => $this->vorschlagSeite($a));
        $router->post(self::NEU, fn (Request $a): Response => $this->vorschlagen($a));

        $router->post(self::WURZEL . '/{id}/annehmen', fn (Request $a, array $p): Response => $this->annehmen(
            $a,
            (string) ($p['id'] ?? '')
        ));

        $router->post(self::WURZEL . '/{id}/ablehnen', fn (Request $a, array $p): Response => $this->ablehnen(
            $a,
            (string) ($p['id'] ?? '')
        ));

        $router->post(self::WURZEL . '/{id}/quittieren', fn (Request $a, array $p): Response => $this->quittieren(
            $a,
            (string) ($p['id'] ?? '')
        ));
    }

    // --- Vorschlagen --------------------------------------------------------

    /**
     * Das Anlegeformular.
     *
     * KEINE VORBELEGUNG DER TREFFPUNKTART. Kein Feld traegt 'checked' — genau
     * wie auf der Deklarationsseite. Eine vorausgewaehlte "Haustuer-Uebergabe"
     * waere die folgenreichste Wahl als Standard, und wer nur weiterklickt,
     * haette sie getroffen, ohne sie gelesen zu haben.
     *
     * @param array<string,string> $eingaben
     */
    private function vorschlagSeite(
        Request $anfrage,
        ?string $fehler = null,
        array $eingaben = []
    ): Response {
        $sitzung = $this->sitzung($anfrage);

        if ($sitzung === null) {
            return Response::weiterleitung('/anmelden');
        }

        $daten = [
            'aktiv' => self::CHAT,
            'gestoert' => false,
            'unterhaltung' => null,
            'arten' => Termine::TREFFPUNKTARTEN,
            'eingaben' => $eingaben,
            'fehler' => $this->ausListe($fehler, self::FEHLER),
            'regionGrenze' => Termine::REGION_MAXLAENGE,
            'vorlaufTage' => Termine::VORLAUF_MAX_TAGE,
            // Die Grenzen des Datumsfeldes kommen aus der Fachklasse, damit
            // Browser und Server dasselbe sagen. Massgeblich ist trotzdem die
            // Fachklasse: Termine::gepruefterZeitpunkt() prueft noch einmal.
            'frueheste' => gmdate('Y-m-d\TH:i', time() + 60),
            'spaeteste' => gmdate('Y-m-d\TH:i', time() + Termine::VORLAUF_MAX_TAGE * 86400),
        ];

        $db = $this->datenbank();

        if ($db === null) {
            $daten['gestoert'] = true;

            return $this->rendern($anfrage, 'termine.vorschlagen', $this->titel('termin.neu_titel'), $daten);
        }

        $roh = $anfrage->eingabe('unterhaltung', '') ?? '';
        $unterhaltungId = ctype_digit($roh) && (int) $roh > 0 ? (int) $roh : 0;

        try {
            $unterhaltung = $unterhaltungId > 0
                ? (new Unterhaltungen($db))->laden($unterhaltungId, (int) $sitzung['benutzer_id'])
                : null;
        } catch (\Throwable $ausnahme) {
            error_log('[MeinSlip/Termin] Formular: ' . $ausnahme->getMessage());

            $daten['gestoert'] = true;

            return $this->rendern($anfrage, 'termine.vorschlagen', $this->titel('termin.neu_titel'), $daten);
        }

        // Unbekannt und fremd ergeben dieselbe Seite — siehe Klassenkopf.
        $daten['unterhaltung'] = $unterhaltung;

        return $this->rendern($anfrage, 'termine.vorschlagen', $this->titel('termin.neu_titel'), $daten);
    }

    private function vorschlagen(Request $anfrage): Response
    {
        if (!Formularschutz::gueltig($anfrage)) {
            return Response::weiterleitung(self::CHAT);
        }

        $sitzung = $this->sitzung($anfrage);

        if ($sitzung === null) {
            return Response::weiterleitung('/anmelden');
        }

        // Die Eingaben werden vor jeder Pruefung eingesammelt, damit der
        // Fehlerweg sie zurueckgeben kann.
        $eingaben = [
            'unterhaltung' => $anfrage->eingabe('unterhaltung', '') ?? '',
            'zeitpunkt' => $anfrage->eingabe('zeitpunkt', '') ?? '',
            'treffpunkt_art' => $anfrage->eingabe('treffpunkt_art', '') ?? '',
            'region' => $anfrage->eingabe('region', '') ?? '',
        ];

        $db = $this->datenbank();

        if ($db === null) {
            return $this->vorschlagSeite($anfrage, 'gestoert', $eingaben);
        }

        // ctype_digit statt (int): '7abc' wuerde sonst stillschweigend zu 7 —
        // und der Termin landete in einem Gespraech, das niemand gemeint hat.
        if (!ctype_digit($eingaben['unterhaltung']) || (int) $eingaben['unterhaltung'] <= 0) {
            return $this->vorschlagSeite($anfrage, 'unterhaltung_unbekannt', $eingaben);
        }

        $unterhaltungId = (int) $eingaben['unterhaltung'];

        try {
            (new Termine($db))->vorschlagen(
                $unterhaltungId,
                (int) $sitzung['benutzer_id'],
                $eingaben['zeitpunkt'],
                $eingaben['treffpunkt_art'],
                $eingaben['region']
            );
        } catch (TerminFehler $fehler) {
            return $this->vorschlagSeite($anfrage, $fehler->schluessel(), $eingaben);
        } catch (\Throwable $fehler) {
            error_log('[MeinSlip/Termin] Vorschlagen: ' . $fehler->getMessage());

            return $this->vorschlagSeite($anfrage, 'gestoert', $eingaben);
        }

        return Response::weiterleitung($this->zurueck($unterhaltungId, 'termin_erfolg', 'termin_vorgeschlagen'));
    }

    // --- Antworten ----------------------------------------------------------

    private function annehmen(Request $anfrage, string $kennung): Response
    {
        return $this->handeln($anfrage, $kennung, function (Termine $termine, int $terminId, int $ich): string {
            $termine->annehmen($terminId, $ich);

            return 'termin_angenommen';
        });
    }

    private function ablehnen(Request $anfrage, string $kennung): Response
    {
        $grund = $anfrage->eingabe('grund', '') ?? '';

        return $this->handeln(
            $anfrage,
            $kennung,
            function (Termine $termine, int $terminId, int $ich) use ($grund): string {
                $termine->ablehnen($terminId, $ich, $grund);

                return 'termin_abgelehnt';
            }
        );
    }

    /**
     * Quittiert die Uebergabe.
     *
     * Die Rueckmeldung unterscheidet die beiden Faelle, weil sie sich fachlich
     * unterscheiden: Nach der ersten Quittung ist noch nichts uebergeben, nach
     * der zweiten schon. Termine::quittieren() liefert dafuer den Zustand nach
     * dem Schreiben zurueck — die Route raet ihn nicht.
     */
    private function quittieren(Request $anfrage, string $kennung): Response
    {
        return $this->handeln($anfrage, $kennung, function (Termine $termine, int $terminId, int $ich): string {
            $stand = $termine->quittieren($terminId, $ich);

            return $stand === Termine::STATUS_UEBERGEBEN ? 'termin_uebergeben' : 'termin_quittiert';
        });
    }

    /**
     * Der gemeinsame Rahmen der drei Antwortwege.
     *
     * Alle drei tun dasselbe: Sitzung pruefen, Kennung pruefen, das Gespraech
     * bestimmen, handeln, zurueckleiten. Nur der Handgriff in der Mitte
     * unterscheidet sich. Ihn dreimal auszuschreiben hiesse, die
     * Zugangspruefung dreimal auszuschreiben — und eine vergessene Kopie faellt
     * hier niemandem auf.
     *
     * @param callable(Termine, int, int):string $handgriff Liefert den
     *        Erfolgsschluessel
     */
    private function handeln(Request $anfrage, string $kennung, callable $handgriff): Response
    {
        if (!Formularschutz::gueltig($anfrage)) {
            return Response::weiterleitung(self::CHAT);
        }

        $sitzung = $this->sitzung($anfrage);

        if ($sitzung === null) {
            return Response::weiterleitung('/anmelden');
        }

        $db = $this->datenbank();

        if ($db === null) {
            return Response::weiterleitung(
                $this->mitRueckmeldung(self::CHAT, 'termin_fehler', 'gestoert')
            );
        }

        if (!ctype_digit($kennung) || (int) $kennung <= 0) {
            return Response::weiterleitung(
                $this->mitRueckmeldung(self::CHAT, 'termin_fehler', 'termin_ungueltig')
            );
        }

        $ich = (int) $sitzung['benutzer_id'];
        $termine = new Termine($db);

        try {
            // Zuerst das Gespraech bestimmen: Ohne die Kennung gaebe es keinen
            // Weg zurueck an die Stelle, an der der Termin steht. Unbekannt und
            // fremd liefern dasselbe null — siehe Klassenkopf.
            $unterhaltungId = $termine->unterhaltungVon((int) $kennung, $ich);

            if ($unterhaltungId === null) {
                return Response::weiterleitung(
                    $this->mitRueckmeldung(self::CHAT, 'termin_fehler', 'termin_unbekannt')
                );
            }

            $erfolg = $handgriff($termine, (int) $kennung, $ich);
        } catch (TerminFehler $fehler) {
            return Response::weiterleitung(
                $this->zurueck($unterhaltungId ?? 0, 'termin_fehler', $fehler->schluessel())
            );
        } catch (\Throwable $fehler) {
            error_log('[MeinSlip/Termin] Handlung: ' . $fehler->getMessage());

            return Response::weiterleitung(
                $this->zurueck($unterhaltungId ?? 0, 'termin_fehler', 'gestoert')
            );
        }

        return Response::weiterleitung($this->zurueck($unterhaltungId, 'termin_erfolg', $erfolg));
    }

    // --- Hilfen -------------------------------------------------------------

    /**
     * Der Weg zurueck ins Chatfenster, mit Rueckmeldung und Sprungmarke.
     *
     * Die Sprungmarke steht am Ende und nach dem Abfrageteil — sie gehoert
     * nicht in die Adresse, sondern hinter sie. Ohne sie landet der Blick nach
     * dem Absenden oben im Fensterkopf, und der Termin, um den es gerade ging,
     * steht ausserhalb des Bildes.
     */
    private function zurueck(int $unterhaltungId, string $feld, string $wert): string
    {
        $ziel = $unterhaltungId > 0 ? self::CHAT . '/' . $unterhaltungId : self::CHAT;

        return $this->mitRueckmeldung($ziel, $feld, $wert) . self::MARKE;
    }

    /**
     * Haengt eine Rueckmeldung an eine Adresse.
     *
     * Der Wert ist zu diesem Zeitpunkt bereits durch eine Weissliste gegangen
     * oder stammt aus einer Fachklasse; rawurlencode() steht trotzdem da, weil
     * sich das an dieser Stelle nicht mehr ansehen laesst.
     */
    private function mitRueckmeldung(string $adresse, string $feld, string $wert): string
    {
        return $adresse . '?' . $feld . '=' . rawurlencode($wert);
    }

    /**
     * Seitentitel mit Markenzusatz.
     *
     * @param array<string,mixed> $platzhalter
     */
    private function titel(string $schluessel, array $platzhalter = []): string
    {
        return t($schluessel, $platzhalter) . ' — ' . t('allgemein.marke');
    }

    /**
     * Laesst nur bekannte Schluessel durch.
     *
     * Ein unbekannter Schluessel wird zu 'unbekannt' statt zu null: Er entsteht
     * nur, wenn eine Fachklasse einen neuen Fehler wirft, den self::FEHLER
     * nicht kennt. Stumm zu bleiben waere dann das Schlimmste — die Person
     * saehe ein unveraendertes Formular ohne jeden Hinweis.
     *
     * @param list<string> $erlaubt
     */
    private function ausListe(?string $wert, array $erlaubt): ?string
    {
        if ($wert === null || $wert === '') {
            return null;
        }

        return in_array($wert, $erlaubt, true) ? $wert : 'unbekannt';
    }

    /**
     * Die Datenbankverbindung dieser Anfrage.
     *
     * Database::ausEnv() oeffnet mit jedem Aufruf eine neue PDO-Verbindung;
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
            // Ein Datenbankausfall darf nicht die ganze Seite mitreissen.
            $this->verbindung = null;
        }

        return $this->verbindung;
    }

    /**
     * Wie Routen::rendern(), das dort aber privat ist.
     *
     * @param array<string,mixed> $daten
     */
    private function rendern(Request $anfrage, string $vorlage, string $titel, array $daten = []): Response
    {
        return Response::html($this->ansicht->rendern($vorlage, $daten + [
            '__layout' => 'layout',
            'titel' => $titel,
            'sprache' => Lang::sprache(),
            'sitzung' => $this->sitzung($anfrage),
        ]));
    }

    /**
     * Die Sitzung dieser Anfrage.
     *
     * Wie beim Chat Voraussetzung und nicht Beiwerk: Ein Termin ist kein
     * oeffentlicher Gegenstand. Jede Route dieser Klasse weist ohne Sitzung zur
     * Anmeldung, und keine gibt vorher etwas preis.
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
