<?php

declare(strict_types=1);

namespace MeinSlip\Http;

use MeinSlip\Core\Database;
use MeinSlip\Core\Lang;
use MeinSlip\Core\Request;
use MeinSlip\Core\Response;
use MeinSlip\Core\Router;
use MeinSlip\Core\View;
use MeinSlip\Domain\Account\KontoFehler;
use MeinSlip\Domain\Account\Profile;
use MeinSlip\Domain\Account\Sitzungen;
use MeinSlip\Domain\Chat\ChatFehler;
use MeinSlip\Domain\Chat\Termine;
use MeinSlip\Domain\Chat\Unterhaltungen;

/**
 * Die Chatoberflaeche: Liste der Unterhaltungen, Fenster, Deklarationspflicht.
 *
 * Seit dem Modellwechsel ist "Nachricht schreiben" die Hauptaktion der
 * Angebotsseite. Diese Strecke ist damit der meistbenutzte Weg der Plattform —
 * und der einzige, ueber den zwei Menschen einander unaufgefordert erreichen.
 * Fuenf Entscheidungen tragen sie:
 *
 *  1. DIE DEKLARATION IST DER KERN, NICHT DAS BEIWERK. Wer den Chat zum ersten
 *     Mal oeffnet und nichts angegeben hat, landet auf /nachrichten/deklaration
 *     und kommt ohne Angabe nicht weiter. Ohne diese Umleitung liefe die Person
 *     in 'deklaration_fehlt' — also in einen Fehler statt in eine Frage. Und
 *     die Antwort auf "Rede ich mit ihr oder mit einem Chatter?"
 *     (docs/04-features/chat-monetarisierung.md) waere eine Fussnote im Profil
 *     statt eines Labels im Fensterkopf, wo sie laut Dokument hingehoert.
 *
 *  2. FORMULARSEITE MIT POST/REDIRECT/GET, KEIN fetch(). Es gibt in diesem
 *     Projekt keine XHR-Infrastruktur, kein Fehler- und kein
 *     Wiederholungsverhalten. Schwerer wiegt die i18n-Regel: Deutscher Text
 *     gehoert nach resources/lang, und UebersetzungenTest durchsucht dafuer
 *     ausschliesslich resources/views/. In JavaScript erzeugter Text waere
 *     also weder verboten noch geprueft — die Regel haette genau dort ein Loch,
 *     wo am meisten Text entsteht. Der spaetere Umbau ist eine Ergaenzung und
 *     kein Umbau: Request::istJson() existiert bereits, und dieselbe Route
 *     kann bei 'Accept: application/json' die Zeilen ab '?seit={id}' liefern —
 *     Unterhaltungen::nachrichten() nimmt den Parameter schon entgegen.
 *
 *  3. DER FEHLERWEG RENDERT NEU, DER ERFOLGSWEG LEITET UM. Nur so ueberlebt
 *     ein abgewiesener Text den Fehlversuch. Wer eine abgewiesene Nachricht
 *     ueber eine Weiterleitung schickt, verlangt vom Menschen, sie noch einmal
 *     zu tippen — bei 'zu_schnell' waere das die zweite Bestrafung fuer
 *     dieselbe Sache. Dasselbe Muster wie in MarktRouten und MeldeRouten.
 *
 *  4. ANGEZEIGT WIRD DIE DEKLARATION DER NACHRICHT, NICHT DIE DES KONTOS.
 *     Unterhaltungen::senden() schreibt die Angabe als Momentaufnahme in die
 *     Zeile. Wuerde die Oberflaeche stattdessen den aktuellen Kontostand
 *     anzeigen, waere diese Momentaufnahme sinnlos: Ein Konto koennte
 *     rueckwirkend behaupten, alles selbst geschrieben zu haben. Der Kopf zeigt
 *     den aktuellen Stand beider Seiten, jede Blase zeigt ihren eigenen.
 *
 *  5. KEINE AUSKUNFT UEBER FREMDE UNTERHALTUNGEN. Unterhaltungen::laden() wirft
 *     "gibt es nicht" und "gehoert dir nicht" bewusst in dasselbe null. Diese
 *     Klasse haelt das durch: Beide Faelle ergeben dieselbe Seite. Wer wem
 *     schreibt, ist die empfindlichste Auskunft dieser Plattform.
 *
 * ZUR SPERRE. Sie wird hier direkt in die Tabelle 'sperren' geschrieben, weil
 * es fuer sie noch keine Fachklasse gibt — Unterhaltungen LIEST die Tabelle
 * nur. Das ist die schwaechste Stelle dieser Datei und ausdruecklich als
 * solche benannt: Sobald ein zweiter Schreibweg entsteht (Profilseite,
 * Verwaltung), gehoert beides in eine Klasse app/Domain/Trust/Sperrliste.php,
 * nach dem Muster von Meldungen. Bis dahin gilt: Es gibt genau diesen einen
 * Schreibweg, er ist idempotent, und er ist umkehrbar. Eine Sperre ohne
 * Ruecknahme waere eine Falle — ein Fehlgriff beendete ein Gespraech fuer
 * immer, in beiden Richtungen, weil Unterhaltungen::senden() beide Seiten
 * prueft.
 */
final class NachrichtenRouten
{
    /** Wurzelpfad der Strecke. An einer Stelle, damit ein Umzug eine Zeile ist. */
    private const WURZEL = '/nachrichten';

    /** Die Pflichtwahl. Statisch, deshalb VOR '/nachrichten/{id}' registriert. */
    private const DEKLARATION = '/nachrichten/deklaration';

    /**
     * Rueckmeldungen, die eine Weiterleitung als Abfrageparameter tragen darf.
     *
     * Ohne diese Liste liesse sich ueber eine gebaute Adresse beliebiger Text
     * in die Seite schieben, und die Vorlage zeigte '[[chat.erfolg.x]]'.
     *
     * @var list<string>
     */
    private const ERFOLGE = [
        'deklaration_gesetzt',
        'sperre_gesetzt',
        'sperre_aufgehoben',
    ];

    /**
     * Erlaubte Fehlerschluessel.
     *
     * Die ersten neun sind woertlich die von ChatFehler::schluessel(), danach
     * kommen zwei aus KontoFehler (Profile::deklarationSetzen()) und vier
     * eigene. tests/ChatTexteTest.php haelt die Liste gegen die Fachklassen —
     * ein neuer Fehlerschluessel dort faellt hier auf, bevor ihn jemand als
     * '[[chat.fehler.x]]' liest.
     *
     * @var list<string>
     */
    private const FEHLER = [
        'nicht_teilnehmer',
        'gesperrt',
        'text_leer',
        'text_zu_lang',
        'deklaration_fehlt',
        'selbstgespraech',
        'unterhaltung_unbekannt',
        'zu_schnell',
        'empfaenger_unbekannt',
        'deklaration_unbekannt',
        'benutzer_unbekannt',
        'empfaenger_ungueltig',
        'nachricht_unbekannt',
        'gestoert',
        'unbekannt',
    ];

    /**
     * Zwischenspeicher fuer die Dauer einer Anfrage.
     *
     * Beide Merker sind noetig, weil null in beiden Faellen ein gueltiges
     * Ergebnis ist: keine Verbindung heisst Stoerung, keine Sitzung heisst
     * abgemeldet. Ohne die Merker wuerde beides bei jedem Aufruf erneut
     * versucht — rendern() fragt die Sitzung ohnehin ein zweites Mal.
     */
    private ?Database $verbindung = null;

    private bool $verbindungVersucht = false;

    /** @var array<string,mixed>|null */
    private ?array $sitzungZwischen = null;

    private bool $sitzungGeladen = false;

    public function __construct(
        // Gleiche Bauform wie Routen, MarktRouten, MeldeRouten und
        // VerwaltungsRouten: Wurzel und Ansicht. Die Wurzel braucht diese
        // Strecke heute nicht, sie bleibt trotzdem im Konstruktor, damit der
        // Einstiegspunkt alle Routenklassen gleich verdrahtet.
        private readonly string $wurzel,
        private readonly View $ansicht,
    ) {
    }

    public function registrieren(Router $router): void
    {
        /*
         * DIE REIHENFOLGE IST TRAGEND. Der Router wandelt {id} in ([^/]+) und
         * vergleicht die Muster der Reihe nach — '/nachrichten/{id}' passt
         * damit auch auf '/nachrichten/deklaration' und '/nachrichten/neu'.
         * Beide statischen Pfade muessen deshalb vorher stehen, je Methode
         * getrennt: Der Router bricht bei einem Pfadtreffer mit falscher
         * Methode nicht ab, aber die Auswahl innerhalb einer Methode
         * entscheidet die Registrierungsreihenfolge.
         */
        $router->get(self::DEKLARATION, fn (Request $a): Response => $this->deklarationSeite($a));
        $router->post(self::DEKLARATION, fn (Request $a): Response => $this->deklarationSetzen($a));

        $router->post(self::WURZEL . '/neu', fn (Request $a): Response => $this->neu($a));

        $router->get(self::WURZEL, fn (Request $a): Response => $this->listeSeite($a));

        // Die beiden zusammengesetzten Pfade koennen '/nachrichten/{id}' nicht
        // schlucken (ein Schraegstrich passt nicht in ([^/]+)), stehen der
        // Lesbarkeit halber aber trotzdem davor.
        $router->post(self::WURZEL . '/{id}/sperren', fn (Request $a, array $p): Response => $this->sperren(
            $a,
            (string) ($p['id'] ?? '')
        ));

        $router->post(self::WURZEL . '/{id}/melden', fn (Request $a, array $p): Response => $this->melden(
            $a,
            (string) ($p['id'] ?? '')
        ));

        $router->get(self::WURZEL . '/{id}', fn (Request $a, array $p): Response => $this->fensterSeite(
            $a,
            (string) ($p['id'] ?? '')
        ));

        $router->post(self::WURZEL . '/{id}', fn (Request $a, array $p): Response => $this->senden(
            $a,
            (string) ($p['id'] ?? '')
        ));
    }

    // --- Liste ------------------------------------------------------------

    /**
     * Die eigenen Unterhaltungen, zuletzt beschriebene zuerst.
     */
    private function listeSeite(Request $anfrage): Response
    {
        $sitzung = $this->sitzung($anfrage);

        if ($sitzung === null) {
            return Response::weiterleitung('/anmelden');
        }

        $db = $this->datenbank();

        if ($db === null) {
            return $this->rendern($anfrage, 'nachrichten.liste', $this->titel('chat.titel'), [
                'aktiv' => self::WURZEL,
                'gestoert' => true,
            ]);
        }

        $ich = (int) $sitzung['benutzer_id'];
        $umleitung = $this->deklarationVerlangen($db, $ich, self::WURZEL);

        if ($umleitung !== null) {
            return $umleitung;
        }

        $seite = max(1, $anfrage->ganzzahl('seite', 1) ?? 1);

        try {
            $blatt = (new Unterhaltungen($db))->meine($ich, $seite);
            $angebote = $this->angebotstitel($db, array_column($blatt['zeilen'], 'angebot_id'));
            $eigene = (new Profile($db))->deklaration($ich);
        } catch (\Throwable $fehler) {
            // Eine abgerissene Verbindung mitten in der Abfrage sieht anders aus
            // als eine, die gar nicht erst zustande kam — die Seite soll aber
            // dasselbe sagen und nicht als 500 enden.
            error_log('[MeinSlip/Chat] Liste: ' . $fehler->getMessage());

            return $this->rendern($anfrage, 'nachrichten.liste', $this->titel('chat.titel'), [
                'aktiv' => self::WURZEL,
                'gestoert' => true,
            ]);
        }

        return $this->rendern($anfrage, 'nachrichten.liste', $this->titel('chat.titel'), [
            'aktiv' => self::WURZEL,
            'gestoert' => false,
            'blatt' => $blatt,
            'angebote' => $angebote,
            'eigeneDeklaration' => $eigene,
            'erfolg' => $this->ausListe($anfrage->eingabe('erfolg'), self::ERFOLGE),
            'fehler' => $this->ausListe($anfrage->eingabe('fehler'), self::FEHLER),
        ]);
    }

    // --- Deklarationspflicht ----------------------------------------------

    /**
     * Die Pflichtwahl.
     *
     * KEINE VORBELEGUNG. Ein vorausgewaehltes "Sie schreibt selbst" waere die
     * staerkste Behauptung als Standard — genau das, was
     * profile.chat_deklaration mit "nullable ohne Vorgabewert" ausschliesst.
     * Auch beim Aendern bleibt das Formular leer: Die Frage soll jedes Mal
     * beantwortet und nicht bloss bestaetigt werden.
     */
    private function deklarationSeite(Request $anfrage, ?string $fehler = null): Response
    {
        $sitzung = $this->sitzung($anfrage);

        if ($sitzung === null) {
            return Response::weiterleitung('/anmelden');
        }

        return $this->rendern($anfrage, 'nachrichten.deklaration', $this->titel('chat.deklaration_titel'), [
            'aktiv' => self::WURZEL,
            'werte' => Profile::DEKLARATIONEN,
            'weiter' => $this->gepruefteRueckkehr($anfrage->eingabe('weiter')),
            'fehler' => $this->ausListe($fehler, self::FEHLER),
        ]);
    }

    private function deklarationSetzen(Request $anfrage): Response
    {
        if (!Formularschutz::gueltig($anfrage)) {
            return Response::weiterleitung(self::DEKLARATION);
        }

        $sitzung = $this->sitzung($anfrage);

        if ($sitzung === null) {
            return Response::weiterleitung('/anmelden');
        }

        $db = $this->datenbank();

        if ($db === null) {
            return $this->deklarationSeite($anfrage, 'gestoert');
        }

        $weiter = $this->gepruefteRueckkehr($anfrage->eingabe('weiter'));

        try {
            (new Profile($db))->deklarationSetzen(
                (int) $sitzung['benutzer_id'],
                $anfrage->eingabe('deklaration', '') ?? ''
            );
        } catch (KontoFehler $fehler) {
            return $this->deklarationSeite($anfrage, $fehler->schluessel());
        } catch (\Throwable $fehler) {
            error_log('[MeinSlip/Chat] Deklaration: ' . $fehler->getMessage());

            return $this->deklarationSeite($anfrage, 'gestoert');
        }

        return Response::weiterleitung($this->mitRueckmeldung($weiter, 'erfolg', 'deklaration_gesetzt'));
    }

    // --- Eroeffnen ---------------------------------------------------------

    /**
     * Eroeffnet eine Unterhaltung — der Weg von der Angebotsseite hierher.
     *
     * POST und nicht GET, obwohl nichts "Gefaehrliches" passiert: Der Aufruf
     * legt eine Zeile an und zaehlt gegen Unterhaltungen::UNTERHALTUNGEN_JE_TAG.
     * Als GET liesse sich das Tageskontingent einer fremden Person mit einer
     * eingebetteten Grafik aufbrauchen, und ein Vorauslader des Browsers
     * eroeffnete Gespraeche, die niemand wollte.
     */
    private function neu(Request $anfrage): Response
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
            return Response::weiterleitung($this->mitRueckmeldung(self::WURZEL, 'fehler', 'gestoert'));
        }

        $ich = (int) $sitzung['benutzer_id'];
        $umleitung = $this->deklarationVerlangen($db, $ich, self::WURZEL);

        if ($umleitung !== null) {
            return $umleitung;
        }

        // ctype_digit statt (int): '7abc' wuerde sonst stillschweigend zu 7 und
        // damit zu einem Gespraech mit jemandem, den niemand gemeint hat.
        $roh = $anfrage->eingabe('empfaenger_id', '') ?? '';

        if (!ctype_digit($roh) || (int) $roh <= 0) {
            return Response::weiterleitung($this->mitRueckmeldung(self::WURZEL, 'fehler', 'empfaenger_ungueltig'));
        }

        $angebotRoh = $anfrage->eingabe('angebot_id', '') ?? '';
        $angebotId = ctype_digit($angebotRoh) && (int) $angebotRoh > 0 ? (int) $angebotRoh : null;

        try {
            $unterhaltungId = (new Unterhaltungen($db))->eroeffnen($ich, (int) $roh, $angebotId);
        } catch (ChatFehler $fehler) {
            return Response::weiterleitung(
                $this->mitRueckmeldung(self::WURZEL, 'fehler', $fehler->schluessel())
            );
        } catch (\Throwable $fehler) {
            error_log('[MeinSlip/Chat] Eroeffnen: ' . $fehler->getMessage());

            return Response::weiterleitung($this->mitRueckmeldung(self::WURZEL, 'fehler', 'gestoert'));
        }

        return Response::weiterleitung(self::WURZEL . '/' . $unterhaltungId);
    }

    // --- Fenster -----------------------------------------------------------

    /**
     * Der Verlauf einer Unterhaltung.
     *
     * @param array<string,string> $eingaben
     */
    private function fensterSeite(
        Request $anfrage,
        string $kennung,
        ?string $fehler = null,
        array $eingaben = []
    ): Response {
        $sitzung = $this->sitzung($anfrage);

        if ($sitzung === null) {
            return Response::weiterleitung('/anmelden');
        }

        $daten = [
            'aktiv' => self::WURZEL,
            'gestoert' => false,
            'unterhaltung' => null,
            'nachrichten' => [],
            'angebot' => null,
            'selbstGesperrt' => false,
            'eingaben' => $eingaben,
            'fehler' => $this->ausListe($fehler, self::FEHLER),
            'erfolg' => $this->ausListe($anfrage->eingabe('erfolg'), self::ERFOLGE),
            'textGrenze' => Unterhaltungen::TEXT_MAXLAENGE,
            // Die Termine gehoeren in dieses Fenster — Begruendung im Kopf der
            // Vorlage und in database/migrations/012_termine.php. Ihre
            // Rueckmeldungen laufen unter EIGENEN Feldnamen und gegen die
            // Weisslisten von TerminRouten: Sonst braeuchte jeder Terminfehler
            // zusaetzlich einen Text unter 'chat.fehler.', und dieselbe Meldung
            // stuende zweimal im Sprachverzeichnis.
            'termine' => [],
            'terminErfolg' => $this->ausListe($anfrage->eingabe('termin_erfolg'), TerminRouten::ERFOLGE),
            'terminFehler' => $this->ausListe($anfrage->eingabe('termin_fehler'), TerminRouten::FEHLER),
            'grundGrenze' => Termine::GRUND_MAXLAENGE,
        ];

        $db = $this->datenbank();

        if ($db === null) {
            $daten['gestoert'] = true;

            return $this->rendern($anfrage, 'nachrichten.fenster', $this->titel('chat.titel'), $daten);
        }

        $ich = (int) $sitzung['benutzer_id'];
        $unterhaltungId = ctype_digit($kennung) ? (int) $kennung : 0;
        $umleitung = $this->deklarationVerlangen($db, $ich, self::WURZEL . '/' . $unterhaltungId);

        if ($umleitung !== null) {
            return $umleitung;
        }

        $chat = new Unterhaltungen($db);

        try {
            $unterhaltung = $unterhaltungId > 0 ? $chat->laden($unterhaltungId, $ich) : null;

            if ($unterhaltung === null) {
                // Unbekannt und fremd ergeben dieselbe Seite — siehe Klassenkopf.
                return $this->rendern($anfrage, 'nachrichten.fenster', $this->titel('chat.unbekannt_titel'), $daten);
            }

            // Erst quittieren, dann lesen: Sonst zeigte die Seite einen Stand,
            // den sie im selben Atemzug ueberschrieben hat.
            $chat->gelesen($unterhaltungId, $ich);

            $daten['unterhaltung'] = $unterhaltung;
            $daten['nachrichten'] = $chat->nachrichten($unterhaltungId, $ich);
            $daten['selbstGesperrt'] = $this->hatGesperrt($db, $ich, (int) $unterhaltung['partner_id']);

            // Erst aufraeumen, dann lesen — sonst behauptet die Liste, ein
            // Vorschlag von gestern sei noch offen. Es haengt keine
            // Korrektheit daran: Termine::annehmen() weist einen vergangenen
            // Zeitpunkt ohnehin ab. Der Verfallslauf ist idempotent und
            // begrenzt sich auf diese eine Unterhaltung; ein Zeitplaner, der
            // das sonst erledigen koennte, existiert in diesem Projekt nicht.
            $termine = new Termine($db);
            $termine->verfallenLassen($unterhaltungId);
            $daten['termine'] = $termine->fuerUnterhaltung($unterhaltungId, $ich);

            if ($unterhaltung['angebot_id'] !== null) {
                $titel = $this->angebotstitel($db, [$unterhaltung['angebot_id']]);
                $daten['angebot'] = $titel[(int) $unterhaltung['angebot_id']] ?? null;
            }
        } catch (\Throwable $ausnahme) {
            error_log('[MeinSlip/Chat] Fenster: ' . $ausnahme->getMessage());

            $daten['gestoert'] = true;
            $daten['unterhaltung'] = null;

            return $this->rendern($anfrage, 'nachrichten.fenster', $this->titel('chat.titel'), $daten);
        }

        return $this->rendern(
            $anfrage,
            'nachrichten.fenster',
            $this->titel('chat.fenster_titel', ['gegenueber' => (string) $unterhaltung['partner_name']]),
            $daten
        );
    }

    /**
     * Schreibt eine Nachricht.
     *
     * Der Fehlerweg rendert neu und behaelt den Text; der Erfolgsweg leitet um,
     * damit ein Neuladen die Nachricht nicht ein zweites Mal absendet.
     */
    private function senden(Request $anfrage, string $kennung): Response
    {
        if (!Formularschutz::gueltig($anfrage)) {
            return Response::weiterleitung(
                ctype_digit($kennung) ? self::WURZEL . '/' . (int) $kennung : self::WURZEL
            );
        }

        $sitzung = $this->sitzung($anfrage);

        if ($sitzung === null) {
            return Response::weiterleitung('/anmelden');
        }

        $text = $anfrage->eingabe('text', '') ?? '';
        $eingaben = ['text' => $text];
        $db = $this->datenbank();

        if ($db === null) {
            return $this->fensterSeite($anfrage, $kennung, 'gestoert', $eingaben);
        }

        if (!ctype_digit($kennung)) {
            return $this->fensterSeite($anfrage, $kennung, 'unterhaltung_unbekannt', $eingaben);
        }

        try {
            (new Unterhaltungen($db))->senden((int) $kennung, (int) $sitzung['benutzer_id'], $text);
        } catch (ChatFehler $fehler) {
            return $this->fensterSeite($anfrage, $kennung, $fehler->schluessel(), $eingaben);
        } catch (\Throwable $fehler) {
            error_log('[MeinSlip/Chat] Senden: ' . $fehler->getMessage());

            return $this->fensterSeite($anfrage, $kennung, 'gestoert', $eingaben);
        }

        // Die Sprungmarke traegt den Blick ans Ende des Verlaufs, ohne dass
        // dafuer JavaScript noetig waere.
        return Response::weiterleitung(self::WURZEL . '/' . (int) $kennung . '#ende');
    }

    // --- Sperren und Melden -------------------------------------------------

    /**
     * Sperrt das Gegenueber — oder hebt die eigene Sperre wieder auf.
     *
     * Beides auf einer Route, weil beides dieselbe Zeile betrifft und die
     * Oberflaeche immer nur eine der beiden Moeglichkeiten anbietet. Eine
     * Sperre, die sich nicht zuruecknehmen laesst, waere eine Falle: Sie wirkt
     * nach Unterhaltungen::pruefeKeineSperre() in BEIDEN Richtungen, ein
     * Fehlgriff beendete das Gespraech also auch fuer die sperrende Person.
     */
    private function sperren(Request $anfrage, string $kennung): Response
    {
        if (!Formularschutz::gueltig($anfrage)) {
            return Response::weiterleitung(self::WURZEL);
        }

        $sitzung = $this->sitzung($anfrage);

        if ($sitzung === null) {
            return Response::weiterleitung('/anmelden');
        }

        $ziel = self::WURZEL . '/' . (ctype_digit($kennung) ? (int) $kennung : 0);
        $db = $this->datenbank();

        if ($db === null || !ctype_digit($kennung)) {
            return Response::weiterleitung($this->mitRueckmeldung($ziel, 'fehler', 'gestoert'));
        }

        $ich = (int) $sitzung['benutzer_id'];
        $aufheben = ($anfrage->eingabe('handlung', '') ?? '') === 'aufheben';

        try {
            // Die Teilnahme entscheidet, WEN diese Person ueberhaupt sperren
            // kann. Ohne sie waere die Route ein Fernschalter fuer beliebige
            // Kontopaare — und nebenbei ein Existenztest fuer fremde Kennungen.
            $unterhaltung = (new Unterhaltungen($db))->laden((int) $kennung, $ich);

            if ($unterhaltung === null) {
                return Response::weiterleitung($this->mitRueckmeldung($ziel, 'fehler', 'unterhaltung_unbekannt'));
            }

            $anderer = (int) $unterhaltung['partner_id'];

            if ($aufheben) {
                $this->sperreAufheben($db, $ich, $anderer);
            } else {
                $this->sperreSetzen($db, $ich, $anderer);
            }
        } catch (\Throwable $fehler) {
            error_log('[MeinSlip/Chat] Sperre: ' . $fehler->getMessage());

            return Response::weiterleitung($this->mitRueckmeldung($ziel, 'fehler', 'gestoert'));
        }

        return Response::weiterleitung(
            $this->mitRueckmeldung($ziel, 'erfolg', $aufheben ? 'sperre_aufgehoben' : 'sperre_gesetzt')
        );
    }

    /**
     * Fuehrt zum Meldeformular — mit der Kennung der gemeldeten Nachricht.
     *
     * WARUM DAS EIN POST IST UND KEIN VERWEIS. Ein '<a href="/melden?art=
     * nachricht&id=N">' waere schneller gebaut, aber die Kennung stuende dann
     * ungeprueft in der Adresse: Jede Person koennte '/melden?art=nachricht&
     * id=4711' aufrufen und bekaeme ueber 'gegenstand_unbekannt' Auskunft
     * darueber, welche Nachrichten es gibt. Diese Route prueft vorher, dass die
     * meldende Person an der Unterhaltung beteiligt ist UND die Nachricht zu
     * genau dieser Unterhaltung gehoert. Erst danach wird weitergeleitet.
     *
     * ACHTUNG, OFFENE ABHAENGIGKEIT: MeldeRouten::ARTEN kennt heute nur
     * 'angebot' und 'benutzer'. Bis dort Unterhaltungen::GEGENSTAND_NACHRICHT
     * ergaenzt ist, landet diese Weiterleitung auf der Seite "kein Gegenstand
     * gewaehlt" statt im Formular. Der Weg ist damit vollstaendig gebaut, aber
     * noch nicht durchgaengig — die fehlende Zeile steht im Bericht.
     */
    private function melden(Request $anfrage, string $kennung): Response
    {
        if (!Formularschutz::gueltig($anfrage)) {
            return Response::weiterleitung(self::WURZEL);
        }

        $sitzung = $this->sitzung($anfrage);

        if ($sitzung === null) {
            return Response::weiterleitung('/anmelden');
        }

        $ziel = self::WURZEL . '/' . (ctype_digit($kennung) ? (int) $kennung : 0);
        $db = $this->datenbank();

        if ($db === null || !ctype_digit($kennung)) {
            return Response::weiterleitung($this->mitRueckmeldung($ziel, 'fehler', 'gestoert'));
        }

        $roh = $anfrage->eingabe('nachricht_id', '') ?? '';
        $gehoertDazu = false;

        try {
            $unterhaltung = (new Unterhaltungen($db))->laden((int) $kennung, (int) $sitzung['benutzer_id']);

            if ($unterhaltung === null) {
                return Response::weiterleitung($this->mitRueckmeldung($ziel, 'fehler', 'unterhaltung_unbekannt'));
            }

            if (ctype_digit($roh) && (int) $roh > 0) {
                $gehoertDazu = (int) $db->wert(
                    'SELECT COUNT(*) FROM nachrichten WHERE id = :n AND unterhaltung_id = :u',
                    ['n' => (int) $roh, 'u' => (int) $kennung]
                ) > 0;
            }
        } catch (\Throwable $ausnahme) {
            error_log('[MeinSlip/Chat] Melden: ' . $ausnahme->getMessage());

            return Response::weiterleitung($this->mitRueckmeldung($ziel, 'fehler', 'gestoert'));
        }

        if (!$gehoertDazu) {
            return Response::weiterleitung($this->mitRueckmeldung($ziel, 'fehler', 'nachricht_unbekannt'));
        }

        // Das kaufmaennische Und steht hier roh: Es geht in eine Kopfzeile,
        // nicht in Markup. Maskiert wuerde es zu '&amp;' in der Adresse.
        return Response::weiterleitung(
            '/melden?art=' . rawurlencode(Unterhaltungen::GEGENSTAND_NACHRICHT) . '&id=' . (int) $roh
        );
    }

    // --- Sperrliste ---------------------------------------------------------

    /**
     * Traegt die Sperre ein, ohne sie doppelt einzutragen.
     *
     * INSERT ... SELECT ... WHERE NOT EXISTS statt "pruefen, dann schreiben":
     * Die Doppelpruefung liegt damit in derselben Anweisung wie das Einfuegen
     * und kann nicht dazwischen veralten. Das FROM ist nicht schmueckend —
     * MySQL erlaubt kein WHERE in einem SELECT ohne FROM, und es prueft
     * nebenbei, dass das eigene Konto ueberhaupt existiert.
     *
     * Kein Grund wird gespeichert: Wen jemand nicht lesen will, ist seine
     * Entscheidung und muss niemandem erklaert werden. Die Spalte bleibt der
     * Verwaltung vorbehalten.
     */
    private function sperreSetzen(Database $db, int $ich, int $anderer): void
    {
        if ($ich === $anderer) {
            return;
        }

        try {
            $db->ausfuehren(
                'INSERT INTO sperren (benutzer_id, gesperrter_id, grund, angelegt_am)'
                . ' SELECT b.id, :anderer, NULL, :jetzt FROM benutzer b'
                . ' WHERE b.id = :ich'
                . ' AND NOT EXISTS (SELECT 1 FROM sperren s'
                . ' WHERE s.benutzer_id = :ich_p AND s.gesperrter_id = :anderer_p)',
                [
                    'anderer' => $anderer,
                    'jetzt' => gmdate('Y-m-d H:i:s'),
                    'ich' => $ich,
                    // Eigene Namen fuer die Wiederholung: Bei abgeschalteter
                    // Emulation (Database setzt EMULATE_PREPARES = false) darf
                    // derselbe benannte Platzhalter nicht zweimal vorkommen.
                    'ich_p' => $ich,
                    'anderer_p' => $anderer,
                ]
            );
        } catch (\PDOException) {
            // Wettlauf zweier gleichzeitiger Anfragen: Der eindeutige Index
            // entscheidet, und das Ergebnis ist genau das gewuenschte.
        }
    }

    private function sperreAufheben(Database $db, int $ich, int $anderer): void
    {
        // Nur die EIGENE Richtung. Hat die andere Person gesperrt, bleibt ihre
        // Sperre bestehen — sonst liesse sich eine Sperre dadurch aufheben,
        // dass die gesperrte Person selbst kurz sperrt und wieder freigibt.
        $db->ausfuehren(
            'DELETE FROM sperren WHERE benutzer_id = :ich AND gesperrter_id = :anderer',
            ['ich' => $ich, 'anderer' => $anderer]
        );
    }

    /**
     * Ob DIESE Person die andere gesperrt hat.
     *
     * Die Gegenrichtung wird ausdruecklich NICHT abgefragt und nicht angezeigt.
     * "Diese Person hat dich gesperrt" ist die Auskunft, mit der eine
     * Belaestigung weitergeht — auf einem anderen Konto, aus einer anderen
     * Richtung. Wer trotzdem sendet, bekommt das neutrale 'gesperrt' aus
     * Unterhaltungen::senden(), das beide Richtungen gleich behandelt.
     */
    private function hatGesperrt(Database $db, int $ich, int $anderer): bool
    {
        return (int) $db->wert(
            'SELECT COUNT(*) FROM sperren WHERE benutzer_id = :ich AND gesperrter_id = :anderer',
            ['ich' => $ich, 'anderer' => $anderer]
        ) > 0;
    }

    // --- Hilfen -------------------------------------------------------------

    /**
     * Leitet zur Pflichtwahl um, solange keine Deklaration gesetzt ist.
     *
     * Gibt null zurueck, wenn es weitergehen darf. Der Rueckweg wird
     * mitgegeben, damit die Person nach der Wahl dort landet, wo sie hinwollte,
     * und nicht auf der Liste.
     */
    private function deklarationVerlangen(Database $db, int $benutzerId, string $rueckweg): ?Response
    {
        try {
            $gesetzt = (new Profile($db))->deklaration($benutzerId);
        } catch (\Throwable $fehler) {
            error_log('[MeinSlip/Chat] Deklaration lesen: ' . $fehler->getMessage());

            return null;
        }

        if ($gesetzt !== null) {
            return null;
        }

        return Response::weiterleitung(
            self::DEKLARATION . '?weiter=' . rawurlencode($this->gepruefteRueckkehr($rueckweg))
        );
    }

    /**
     * Nur eigene Adressen als Rueckweg.
     *
     * Ohne diese Pruefung waere '?weiter=' eine offene Weiterleitung: Eine
     * praeparierte Adresse traege nach dem Absenden des Formulars auf eine
     * fremde Seite, die genauso aussieht wie diese hier.
     */
    private function gepruefteRueckkehr(?string $roh): string
    {
        if ($roh !== null && preg_match('#^/nachrichten(/[1-9]\d{0,17})?$#', $roh) === 1) {
            return $roh;
        }

        return self::WURZEL;
    }

    /**
     * Haengt eine Rueckmeldung an eine Adresse.
     *
     * Der Wert ist zu diesem Zeitpunkt bereits durch eine Weissliste gegangen;
     * rawurlencode() steht trotzdem da, weil sich das an dieser Stelle nicht
     * mehr ansehen laesst.
     */
    private function mitRueckmeldung(string $adresse, string $feld, string $wert): string
    {
        return $adresse . '?' . $feld . '=' . rawurlencode($wert);
    }

    /**
     * Titel je Angebotskennung.
     *
     * Eine Abfrage fuer die ganze Seite statt einer je Zeile. Der Titel darf
     * gezeigt werden, weil die Unterhaltung den Bezug traegt und die Person an
     * ihr beteiligt ist — es entsteht keine Auskunft, die sie nicht schon hat.
     *
     * @param list<int|null> $ids
     *
     * @return array<int,string>
     */
    private function angebotstitel(Database $db, array $ids): array
    {
        $sauber = [];

        foreach ($ids as $id) {
            if (is_int($id) && $id > 0) {
                $sauber[$id] = $id;
            }
        }

        if ($sauber === []) {
            return [];
        }

        $platzhalter = [];
        $werte = [];

        foreach (array_values($sauber) as $i => $id) {
            $platzhalter[] = ':id' . $i;
            $werte['id' . $i] = $id;
        }

        $ergebnis = [];

        try {
            $zeilen = $db->alle(
                'SELECT id, titel FROM angebote WHERE id IN (' . implode(', ', $platzhalter) . ')',
                $werte
            );
        } catch (\Throwable) {
            // Ein fehlender Angebotstitel darf den Verlauf nicht kosten.
            return [];
        }

        foreach ($zeilen as $zeile) {
            $ergebnis[(int) $zeile['id']] = (string) $zeile['titel'];
        }

        return $ergebnis;
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
     * Anders als bei MeldeRouten ist sie hier Voraussetzung: Der Chat ist kein
     * oeffentlicher Bereich. Jede Route dieser Klasse weist ohne Sitzung zur
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
