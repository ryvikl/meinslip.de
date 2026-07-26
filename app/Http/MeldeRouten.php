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
use MeinSlip\Domain\Admin\Verwaltung;
use MeinSlip\Domain\Chat\Unterhaltungen;
use MeinSlip\Domain\Trust\Meldungen;
use MeinSlip\Domain\Trust\MeldungsFehler;

/**
 * Die nutzerseitige Meldestrecke nach Art. 16 DSA.
 *
 * DIESE STRECKE IST DIE RECHTSGRUNDLAGE DES TORABBAUS. Ohne Vorabpruefung ist
 * die Plattform nur dann verteidigbar, wenn jede Person jederzeit einen Inhalt
 * melden kann. Die Fachklasse Meldungen nimmt eine Meldung entgegen, die
 * Verwaltung kann sie bearbeiten — erzeugen konnte sie bisher niemand. Faellt
 * diese Klasse aus, faellt die Begruendung fuer den Verzicht auf die
 * Vorabpruefung mit.
 *
 * KEINE ANMELDEPFLICHT — UND ZWAR ABSICHTLICH.
 * Art. 16 Abs. 1 DSA spricht von "Personen oder Einrichtungen" und knuepft das
 * Melderecht an keine Kontobeziehung. Eine Anmeldeschranke waere der
 * einfachste Weg, das Verfahren wirkungslos zu machen: Wer eine Belaestigung
 * oder ein Bild eines Kindes meldet, soll das tun koennen, ohne sich vorher
 * bei genau der Plattform zu registrieren, auf der es steht. Deshalb prueft
 * KEINE Route dieser Klasse eine Sitzung als Voraussetzung.
 *
 * Die Sitzung wird nur gelesen, um zwei Dinge zu entscheiden:
 *  - Angemeldete bekommen ihre Kennung als melder_id und damit die
 *    Empfangsbestaetigung nach Art. 16 Abs. 4 DSA im Profil.
 *  - Abgemeldete melden anonym; melder_id bleibt NULL, eine Zustellung gibt
 *    es dann nicht, und die Oberflaeche sagt das auch.
 *
 * BITTE NICHT "NACHRUESTEN": Wer hier eine Anmeldepflicht ergaenzt, nimmt der
 * Plattform ihre Rechtsgrundlage — nicht bloss eine Bequemlichkeit.
 *
 * WAS HIER GEDROSSELT WIRD UND WARUM.
 * Ein Meldeformular ohne Anmeldung ist zugleich ein Werkzeug, mit dem sich eine
 * Person mit Meldungen ueberziehen laesst: Die Arbeitsliste der Verwaltung
 * sortiert nach der Zahl der Meldungen (Verwaltung::gemeldeteAngebote()), also
 * macht ein Dutzend erfundener Meldungen aus einem harmlosen Angebot den
 * dringendsten Fall. Art. 23 Abs. 2 DSA erlaubt ausdruecklich, gegen haeufig
 * offensichtlich unbegruendete Meldungen vorzugehen. Zwei Grenzen ziehen das
 * hier praktisch:
 *
 *  1. TAKT JE BROWSER (Cookie). Hoechstens self::TAKT_JE_FENSTER Meldeversuche
 *     je Stunde. Gezaehlt werden VERSUCHE, nicht Erfolge — sonst waere das
 *     Formular ein kostenloser Existenztest fuer fremde Kennungen: "Gibt es
 *     Angebot 4711?" ergibt 'gegenstand_unbekannt' statt einer Meldung, und
 *     das beliebig oft.
 *
 *  2. DECKEL FUER ANONYME MELDUNGEN JE GEGENSTAND (Datenbank). Liegen zu einem
 *     Gegenstand bereits self::ANONYM_JE_GEGENSTAND unerledigte anonyme
 *     Meldungen, nimmt die Strecke keine weitere anonyme entgegen. Der
 *     Gegenstand IST dann in der Arbeitsliste — eine vierte anonyme Meldung
 *     traegt nichts bei, sie erhoeht nur die Zahl, an der die Dringlichkeit
 *     haengt. Angemeldete kommen weiter durch, ihre Doppelmeldung sperrt
 *     Meldungen selbst ('bereits_gemeldet').
 *
 * Zwei Dinge sind an dieser Drosselung ehrlich zu benennen:
 *
 *  - DAS COOKIE IST KEIN SCHLOSS. Wer es loescht, meldet weiter. Es haelt den
 *    Klickfinger und einfache Skripte auf, nicht einen entschlossenen Angriff.
 *    Dagegen hilft nur eine Zaehlung je Absender in der Datenbank — und die
 *    braeuchte einen Personenbezug (IP), den dieses Projekt nirgends
 *    speichert, oder eine eigene Tabelle samt Migration. Beides ist hier
 *    bewusst nicht entstanden; das Cookie ist die Grenze, die ohne neue Daten
 *    ueber Menschen auskommt. Dieselbe Ueberlegung wie beim Preisstand in
 *    MarktRouten: Wer den Wert faelscht, unterdrueckt allein seine eigene
 *    Warnung.
 *
 *  - DER DECKEL DARF DIE DRINGENDSTE MELDUNG NIE TREFFEN. Ein Angreifer
 *    koennte sonst mit drei belanglosen anonymen Meldungen den Deckel fuellen
 *    und damit die Meldung verhindern, auf die es ankommt. Deshalb gilt er
 *    fuer self::DRINGEND — Minderjaehrigkeit und gestohlene Identitaet —
 *    ausdruecklich NICHT. Der Preis einer Flut in diesen Gruenden ist
 *    Arbeitszeit; der Preis einer unterdrueckten Meldung ist ein Kind.
 *
 * WO DIE DROSSELUNG AUF DAUER HINGEHOERT: in die Fachklasse Meldungen, nach
 * dem Muster von Bestellungen::pruefeFreigabe(). In der Route ist sie nur so
 * lange vollstaendig, wie /melden der einzige Aufrufer von Meldungen::melden()
 * ist (heute: ja, ausser den Tests). Wer einen zweiten Aufrufer baut — etwa
 * einen Meldeknopf im Chat —, muss sie mitnehmen oder besser gleich
 * verschieben.
 */
final class MeldeRouten
{
    /** Wurzelpfad der Strecke. An einer Stelle, damit ein Umzug eine Zeile ist. */
    private const WURZEL = '/melden';

    /** Die Bestaetigungsseite. */
    private const DANKE = '/melden/danke';

    /**
     * Meldbare Gegenstandsarten — die Weissliste fuer '?art='.
     *
     * Ein Wert aus der Adresszeile wird NIE ungeprueft weitergereicht: Er
     * waehlt nur einen Eintrag dieser Liste aus, und was nicht darin steht,
     * gibt es fuer diese Strecke nicht.
     *
     * ABSICHTLICH KUERZER ALS DAS, WAS DIE FACHKLASSE ANNIMMT. Meldungen kennt
     * zusaetzlich Verwaltung::GEGENSTAND_BESTELLUNG. Eine Bestellung ist aber
     * kein oeffentlicher Inhalt: Es gibt keine Seite, von der aus man sie
     * melden koennte, und beteiligt sind genau zwei Menschen. Stuende sie hier,
     * liesse sich ueber '/melden?art=bestellung&id=N' anonym durchprobieren,
     * welche Bestellnummern es gibt ('gegenstand_unbekannt' kommt nur bei einer
     * Kennung, die es nicht gibt) — ohne dass irgendjemand die Moeglichkeit je
     * bekaeme, sie zu benutzen. Wer den Meldeweg an der Bestellseite anbringt,
     * ergaenzt hier eine Zeile UND prueft dort, dass die meldende Person an der
     * Bestellung beteiligt ist. Der Text 'melden.art.bestellung' liegt dafuer
     * bereits bereit.
     *
     * 'nachricht' STEHT HIER, obwohl eine Nachricht so privat ist wie eine
     * Bestellung. Der Unterschied ist der Weg dorthin: Der Melden-Knopf im
     * Chatfenster prueft bereits, dass die meldende Person an der Unterhaltung
     * beteiligt ist und die Nachricht zu ihr gehoert, und leitet erst dann auf
     * '/melden?art=nachricht&id=N' weiter. Genau die Pruefung, die der
     * Absatz oben fuer die Bestellseite verlangt, gibt es hier also schon.
     *
     * Bliebe die Zeile weg, liefe dieser Knopf in "kein Gegenstand gewaehlt" —
     * und damit waere der einzige Meldeweg fuer Nachrichten tot. Das ist keine
     * Unbequemlichkeit: Das funktionierende Melde- und Abhilfeverfahren nach
     * Art. 16 DSA ist die Bedingung, unter der hier ueberhaupt ohne
     * Vorabpruefung veroeffentlicht wird.
     *
     * Der Rest-Preis ist bekannt und klein: Wer die Adresse von Hand baut,
     * kann ueber vollstaendig abgesendete Meldungen erraten, welche
     * Nachrichtenkennungen existieren. Das ist derselbe Rest, den 'angebot'
     * und 'benutzer' schon tragen, es braucht je Versuch eine abgesendete
     * Meldung statt eines blossen Aufrufs, und eine Kennung verraet weder
     * Inhalt noch Beteiligte.
     *
     * @var list<string>
     */
    private const ARTEN = [
        Verwaltung::GEGENSTAND_ANGEBOT,
        Verwaltung::GEGENSTAND_BENUTZER,
        Unterhaltungen::GEGENSTAND_NACHRICHT,
    ];

    /**
     * Erlaubte Fehlerschluessel.
     *
     * Die ersten acht sind woertlich die von MeldungsFehler::schluessel(), die
     * letzten vier entstehen in dieser Klasse. Ohne die Liste liesse sich ueber
     * eine gebaute Adresse beliebiger Text in die Uebersetzung schieben, und
     * die Seite zeigte '[[melden.fehler.x]]'.
     *
     * @var list<string>
     */
    private const FEHLER = [
        'gegenstand_art_unbekannt',
        'grund_unbekannt',
        'grund_zu_lang',
        'beschreibung_fehlt',
        'beschreibung_zu_lang',
        'gegenstand_unbekannt',
        'melder_unbekannt',
        'bereits_gemeldet',
        'zu_viele_meldungen',
        'bereits_in_pruefung',
        'gestoert',
        'unbekannt',
    ];

    /**
     * Cookie des Meldetakts. Traegt '<fensterbeginn>:<anzahl>'.
     *
     * httponly: Anders als beim Formularschutz-Token braucht JavaScript diesen
     * Wert nicht.
     */
    private const TAKT_COOKIE = 'ms_melde_takt';

    /** Laenge des Zaehlfensters in Sekunden. */
    private const TAKT_FENSTER_SEKUNDEN = 3600;

    /**
     * Meldeversuche je Fenster und Browser.
     *
     * Fuenf, weil eine Person, die auf eine Spamwelle stoesst, tatsaechlich
     * mehrere Angebote hintereinander meldet — eine Grenze von eins waere eine
     * Schranke gegen die Meldenden statt gegen den Missbrauch. Mehr als fuenf
     * Meldungen in einer Stunde aus demselben Browser ist dagegen kein
     * Meldeverhalten mehr.
     */
    private const TAKT_JE_FENSTER = 5;

    /** Unerledigte anonyme Meldungen, die ein Gegenstand hoechstens traegt. */
    private const ANONYM_JE_GEGENSTAND = 3;

    /**
     * Gruende, die der Deckel fuer anonyme Meldungen NICHT abweist.
     *
     * Es sind die beiden ersten aus Meldungen::GRUENDE, deren Reihenfolge
     * ausdruecklich das Dringendste zuerst nennt. Sie stehen hier trotzdem
     * einzeln als Konstante und nicht als array_slice(): Welcher Grund
     * unaufhaltsam ist, muss eine Entscheidung sein, keine Nebenwirkung einer
     * Sortierung, die jemand spaeter aendert.
     *
     * @var list<string>
     */
    private const DRINGEND = [
        Meldungen::GRUND_MINDERJAEHRIG,
        Meldungen::GRUND_GESTOHLENE_IDENTITAET,
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
        // Gleiche Bauform wie Routen, MarktRouten und VerwaltungsRouten:
        // Wurzel und Ansicht. Die Wurzel braucht diese Strecke heute nicht,
        // sie bleibt trotzdem im Konstruktor, damit der Einstiegspunkt alle
        // Routenklassen gleich verdrahtet.
        private readonly string $wurzel,
        private readonly View $ansicht,
    ) {
    }

    public function registrieren(Router $router): void
    {
        // Reihenfolge ohne Wirkung, weil kein Muster einen Platzhalter traegt:
        // Der Router verankert mit ^...$, '/melden' und '/melden/danke' koennen
        // einander nicht schlucken. Die statische Seite steht trotzdem zuerst,
        // damit die Regel sichtbar bleibt, sobald hier je ein '{...}' auftaucht.
        $router->get(self::DANKE, fn (Request $a): Response => $this->dankeSeite($a));

        $router->get(self::WURZEL, fn (Request $a): Response => $this->formularSeite($a));
        $router->post(self::WURZEL, fn (Request $a): Response => $this->melden($a));
    }

    // --- Formular ---------------------------------------------------------

    /**
     * Das Meldeformular.
     *
     * Wird auch nach einem gescheiterten Absenden gerendert — dann mit
     * Fehlerschluessel und den bereits eingetippten Angaben. Eine Erlaeuterung
     * neu zu tippen ist nicht bloss laestig: Art. 16 Abs. 2 lit. a DSA verlangt
     * genau diese Erlaeuterung, und wer sie zweimal schreiben muss, meldet beim
     * naechsten Mal gar nicht mehr.
     *
     * @param array<string,string> $eingaben
     */
    private function formularSeite(Request $anfrage, ?string $fehler = null, array $eingaben = []): Response
    {
        return $this->rendern($anfrage, 'melden.formular', t('melden.titel') . ' — ' . t('allgemein.marke'), [
            // Aus der Adresszeile, aber nur ueber die Weissliste. Kommt nichts
            // Gueltiges an, zeigt die Vorlage statt des Formulars den Weg zum
            // Melden — ein Formular, in das sich eine beliebige Kennung tippen
            // liesse, waere ein Fernauslöser fuer fremde Inhalte.
            'gegenstand' => $this->gegenstand($anfrage),
            'gruende' => Meldungen::GRUENDE,
            'fehler' => $this->ausListe($fehler, self::FEHLER),
            'eingaben' => $eingaben,
            'angemeldet' => $this->sitzung($anfrage) !== null,
        ]);
    }

    /**
     * Nimmt die Meldung entgegen.
     *
     * Reihenfolge tragend: erst der Formularschutz, dann die eigenen
     * Pruefungen, dann der Takt, dann die Fachklasse. Was diese Klasse selbst
     * abweisen kann (fehlende Auswahl, leere Erlaeuterung), verbraucht keinen
     * Takt — es ist ein Vertipper, kein Meldeversuch.
     */
    private function melden(Request $anfrage): Response
    {
        if (!Formularschutz::gueltig($anfrage)) {
            // Stille Rueckleitung wie ueberall im Projekt. Die Adresse traegt
            // den Gegenstand mit, damit der zweite Anlauf nicht bei null
            // beginnt — beide Werte sind zu diesem Zeitpunkt schon durch die
            // Weissliste gegangen.
            return Response::weiterleitung($this->formularAdresse($this->gegenstand($anfrage)));
        }

        $gegenstand = $this->gegenstand($anfrage);
        $eingaben = [
            'grund' => $anfrage->eingabe('grund', '') ?? '',
            'beschreibung' => $anfrage->eingabe('beschreibung', '') ?? '',
        ];

        if ($gegenstand === null) {
            return $this->formularSeite($anfrage, 'gegenstand_art_unbekannt', $eingaben);
        }

        if (!in_array($eingaben['grund'], Meldungen::GRUENDE, true)) {
            return $this->formularSeite($anfrage, 'grund_unbekannt', $eingaben);
        }

        if ($eingaben['beschreibung'] === '') {
            return $this->formularSeite($anfrage, 'beschreibung_fehlt', $eingaben);
        }

        if ($this->taktErschoepft()) {
            return $this->formularSeite($anfrage, 'zu_viele_meldungen', $eingaben);
        }

        $db = $this->datenbank();

        if ($db === null) {
            // Ein Datenbankausfall ist keine Absage an die Meldung. Die Person
            // bekommt eine ehrliche Stoerungsmeldung und behaelt ihren Text.
            return $this->formularSeite($anfrage, 'gestoert', $eingaben);
        }

        $sitzung = $this->sitzung($anfrage);
        $melderId = $sitzung !== null ? (int) $sitzung['benutzer_id'] : null;

        // Ab hier ist die Anfrage ein vollstaendiger Meldeversuch, und ab hier
        // zaehlt sie — nicht erst bei Erfolg. Sonst waere jede abgewiesene
        // Meldung kostenlos: Die Strecke liesse sich als Existenztest fuer
        // fremde Kennungen durchprobieren ('gegenstand_unbekannt' kommt nur bei
        // einer Kennung, die es nicht gibt), und der Deckel darunter liesse
        // sich beliebig oft anrennen.
        $this->taktZaehlen();

        if ($melderId === null
            && !in_array($eingaben['grund'], self::DRINGEND, true)
            && $this->anonymGedeckelt($db, $gegenstand['art'], $gegenstand['id'])) {
            return $this->formularSeite($anfrage, 'bereits_in_pruefung', $eingaben);
        }

        try {
            (new Meldungen($db))->melden(
                $melderId,
                $gegenstand['art'],
                $gegenstand['id'],
                $eingaben['grund'],
                $eingaben['beschreibung']
            );
        } catch (MeldungsFehler $fehler) {
            return $this->formularSeite($anfrage, $fehler->schluessel(), $eingaben);
        } catch (\Throwable $fehler) {
            // Nicht verschlucken, aber auch nicht ungefiltert nach aussen
            // tragen: Der Mensch bekommt eine ruhige Meldung, das Protokoll
            // bekommt den Grund.
            error_log('[MeinSlip/Melden] Meldung gescheitert: ' . $fehler->getMessage());

            return $this->formularSeite($anfrage, 'gestoert', $eingaben);
        }

        return Response::weiterleitung(self::DANKE);
    }

    /**
     * Die Bestaetigungsseite.
     *
     * Sie nennt die zugesagte Frist im Klartext. Die Empfangsbestaetigung nach
     * Art. 16 Abs. 4 DSA leistet sie NICHT: Eine Seite, die man wegklickt, ist
     * kein Nachweis. Die Zustellung macht Meldungen::melden() ueber
     * 'benachrichtigungen' — in derselben Transaktion wie die Meldung, und
     * ausschliesslich fuer Angemeldete, weil es sonst niemanden gibt, dem
     * zuzustellen waere. Genau das sagt die Seite auch.
     */
    private function dankeSeite(Request $anfrage): Response
    {
        return $this->rendern($anfrage, 'melden.danke', t('melden.danke_titel') . ' — ' . t('allgemein.marke'), [
            'stunden' => Meldungen::FRIST_STUNDEN,
            'angemeldet' => $this->sitzung($anfrage) !== null,
        ]);
    }

    // --- Gegenstand -------------------------------------------------------

    /**
     * Gegenstandsart und Kennung aus der Anfrage — oder null.
     *
     * Request::eingabe() liest Formular vor Abfrage; dasselbe Feldpaar traegt
     * also den GET-Aufruf aus der Angebotsseite und den POST aus dem Formular.
     *
     * ctype_digit statt (int): '7abc' wuerde sonst stillschweigend zu 7 und
     * damit zu einer Meldung ueber einen Gegenstand, den niemand gemeint hat.
     *
     * @return array{art: string, id: int}|null
     */
    private function gegenstand(Request $anfrage): ?array
    {
        $art = $anfrage->eingabe('art', '') ?? '';

        if (!in_array($art, self::ARTEN, true)) {
            return null;
        }

        $roh = $anfrage->eingabe('id', '') ?? '';

        if (!ctype_digit($roh)) {
            return null;
        }

        $id = (int) $roh;

        return $id > 0 ? ['art' => $art, 'id' => $id] : null;
    }

    /**
     * Adresse des Formulars, mit Gegenstand, falls einer feststeht.
     *
     * @param array{art: string, id: int}|null $gegenstand
     */
    private function formularAdresse(?array $gegenstand): string
    {
        if ($gegenstand === null) {
            return self::WURZEL;
        }

        return self::WURZEL . '?art=' . rawurlencode($gegenstand['art']) . '&id=' . $gegenstand['id'];
    }

    // --- Drosselung -------------------------------------------------------

    /** Ist der Takt dieses Browsers fuer das laufende Fenster aufgebraucht? */
    private function taktErschoepft(): bool
    {
        return $this->takt()['anzahl'] >= self::TAKT_JE_FENSTER;
    }

    /**
     * Zaehlt einen Meldeversuch.
     *
     * Das Cookie laeuft mit dem Fenster ab und raeumt sich damit selbst weg.
     */
    private function taktZaehlen(): void
    {
        if (headers_sent()) {
            return;
        }

        $takt = $this->takt();
        $wert = $takt['beginn'] . ':' . ($takt['anzahl'] + 1);

        setcookie(self::TAKT_COOKIE, $wert, [
            'expires' => $takt['beginn'] + self::TAKT_FENSTER_SEKUNDEN,
            'path' => '/',
            'secure' => ($_SERVER['HTTPS'] ?? '') === 'on'
                || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // Damit ein weiterer Aufruf im selben Prozess den frischen Stand sieht —
        // $_COOKIE wird von setcookie() nicht fortgeschrieben.
        $_COOKIE[self::TAKT_COOKIE] = $wert;
    }

    /**
     * Fensterbeginn und Zaehlerstand aus dem Cookie.
     *
     * Jeder unlesbare, abgelaufene oder in der Zukunft liegende Wert beginnt
     * ein frisches Fenster. Ein Cookie aus der Zukunft entsteht durch eine
     * verstellte Uhr genauso wie durch Basteln; beide Male ist ein neues
     * Fenster die richtige Antwort und nicht eine dauerhafte Sperre.
     *
     * @return array{beginn: int, anzahl: int}
     */
    private function takt(): array
    {
        $jetzt = time();
        $frisch = ['beginn' => $jetzt, 'anzahl' => 0];
        $roh = $_COOKIE[self::TAKT_COOKIE] ?? '';

        if (!is_string($roh) || !preg_match('/^\d{1,12}:\d{1,4}$/', $roh)) {
            return $frisch;
        }

        [$beginn, $anzahl] = array_map('intval', explode(':', $roh));

        if ($beginn > $jetzt || $beginn + self::TAKT_FENSTER_SEKUNDEN <= $jetzt) {
            return $frisch;
        }

        return ['beginn' => $beginn, 'anzahl' => $anzahl];
    }

    /**
     * Traegt der Gegenstand bereits genug unerledigte anonyme Meldungen?
     *
     * Gezaehlt wird ausschliesslich melder_id IS NULL: Meldungen Angemeldeter
     * sind ueber 'bereits_gemeldet' schon einzeln gedeckelt und sollen von
     * einer anonymen Flut nicht mitverdraengt werden.
     */
    private function anonymGedeckelt(Database $db, string $art, int $id): bool
    {
        $platzhalter = [];
        $werte = ['art' => $art, 'gegenstand' => $id];

        foreach (array_values(Verwaltung::MELDUNG_UNERLEDIGT) as $i => $status) {
            $platzhalter[] = ':status' . $i;
            $werte['status' . $i] = $status;
        }

        try {
            $offen = (int) $db->wert(
                'SELECT COUNT(*) FROM meldungen
                  WHERE melder_id IS NULL
                    AND gegenstand_art = :art
                    AND gegenstand_id = :gegenstand
                    AND status IN (' . implode(', ', $platzhalter) . ')',
                $werte
            );
        } catch (\Throwable) {
            // Eine gestoerte Zaehlung darf keine Meldung verhindern. Der
            // Deckel faellt im Zweifel aus, nicht die Meldestrecke — das
            // Melderecht wiegt schwerer als die Drosselung.
            return false;
        }

        return $offen >= self::ANONYM_JE_GEGENSTAND;
    }

    // --- Hilfen -----------------------------------------------------------

    /**
     * Laesst nur bekannte Schluessel durch.
     *
     * @param list<string> $erlaubt
     */
    private function ausListe(?string $wert, array $erlaubt): ?string
    {
        if ($wert === null) {
            return null;
        }

        // Ein unbekannter Schluessel wird zu 'unbekannt' statt zu null: Er
        // entsteht nur, wenn eine Fachklasse einen neuen Fehler wirft, den
        // diese Liste nicht kennt. Stumm zu bleiben waere dann das Schlimmste —
        // die Person saehe ein unveraendertes Formular ohne jeden Hinweis.
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
     * Die Sitzung dieser Anfrage — Auskunft, nie Voraussetzung.
     *
     * Sie entscheidet allein darueber, ob die Meldung eine Kennung traegt und
     * damit bestaetigt werden kann. Keine Route dieser Klasse weist ab, weil
     * hier null steht.
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
