<?php

declare(strict_types=1);

use MeinSlip\Core\Database;
use MeinSlip\Core\Ddl;

/**
 * Profil und Chat: die drei Tabellen, auf denen der Kontakt zwischen zwei
 * Konten beruht.
 *
 * 'profile' entsteht als eigene Tabelle im Verhaeltnis 1:1 zu 'benutzer' und
 * nicht als neue Spaltengruppe dort. Zwei Gruende, beide zwingend:
 *
 *  1. tests/Testfall.php::benutzer() fuegt in 'benutzer' eine feste
 *     Spaltenliste ein. Eine neue NOT-NULL-Spalte ohne Vorgabewert braeche
 *     dort praktisch jeden Domaenentest auf einmal.
 *  2. Ddl kann kein ALTER TABLE. Was hier nicht steht, laesst sich spaeter nur
 *     als Handarbeit mit Dialektfall nachtragen.
 *
 * Aus Grund 2 folgt die zweite Entscheidung: 'profile' entsteht VOLLSTAENDIG,
 * einschliesslich der Spalten, die erst das Creator-Paket braucht
 * (bild_pfad, bild_vorschau_pfad, oeffentlich). Eine leere Spalte kostet
 * nichts; ein fehlendes ALTER TABLE kostet eine Migration von Hand.
 *
 * ZWEI ENTWURFSENTSCHEIDUNGEN, die je einen Fehler verhindern — sie stehen
 * ausfuehrlich bei den jeweiligen Tabellen:
 *
 *  - unterhaltungen.kontext_schluessel statt UNIQUE(starter, empfaenger,
 *    angebot_id): sonst waeren kontextlose Unterhaltungen beliebig oft
 *    anlegbar und A->B waere eine andere Unterhaltung als B->A.
 *  - nachrichten.deklaration je NACHRICHT statt nur je Konto: sonst waere die
 *    Chatter-Deklaration dekorativ statt ueberpruefbar.
 *
 * Die Migration legt nur Schema an, keine Daten. Sie ist trotzdem so gebaut,
 * dass ein zweiter Lauf folgenlos bleibt — Migrationen laufen ohne
 * Transaktionsklammer (Migrator.php), ein Abbruch in der Mitte startet die
 * Datei beim naechsten Lauf von vorn. Ddl::tabelle() bringt IF NOT EXISTS von
 * sich aus mit; CREATE INDEX kennt es auf MySQL nicht, deshalb steht jeder
 * Index hinter Ddl::indexVorhanden().
 */
return new class {
    public function bezeichnung(): string
    {
        return 'Profile, Unterhaltungen und Nachrichten';
    }

    public function hoch(Database $db, Ddl $d): void
    {
        $this->profile($db, $d);
        $this->unterhaltungen($db, $d);
        $this->nachrichten($db, $d);
    }

    /**
     * Das Profil: eine Zeile je Konto, angelegt bei Bedarf.
     *
     * anzeigename ist NULL-bar, und das ist der ganze Zweck: Der Rueckfall ist
     * benutzer.pseudonym. Ein Vorgabewert waere hier falsch, weil er das
     * Pseudonym in eine zweite Spalte kopierte — und ab der ersten Umbenennung
     * stuenden zwei Namen im Umlauf, von denen einer veraltet ist.
     *
     * chat_deklaration IST NULL-BAR OHNE VORGABEWERT. Kein DEFAULT 'person':
     * Sonst schliche sich ausgerechnet die staerkste Behauptung ("Sie schreibt
     * selbst") als Standard ein, und zwar fuer jedes Konto, das nie eine
     * Angabe gemacht hat. Die Deklarationspflicht waere damit von Anfang an
     * entwertet — sie behauptete etwas ueber Konten, die nichts behauptet
     * haben. NULL heisst hier genau das Richtige: noch nichts gesagt, also
     * darf auch nichts gesendet werden (Unterhaltungen::senden weist mit
     * 'deklaration_fehlt' ab). Zulaessige Werte sind person | team | ki,
     * fachlicher Hintergrund in docs/04-features/chat-monetarisierung.md.
     *
     * oeffentlich steht auf 0. Ein Profil wird nicht dadurch oeffentlich, dass
     * es entsteht — es entsteht beim ersten Aufruf der eigenen Profilseite,
     * und diesen Aufruf als Einwilligung in eine oeffentliche Seite zu deuten
     * waere eine Unterstellung. Profile::nachPseudonym() liefert deshalb nur
     * Zeilen mit oeffentlich = 1.
     *
     * Der eindeutige Index auf benutzer_id ist das 1:1-Verhaeltnis: Ein
     * zweites Profil zu demselben Konto waere ein zweiter Satz Behauptungen,
     * und keine Abfrage koennte entscheiden, welcher gilt. ON DELETE CASCADE,
     * weil ein Profil ohne Konto niemandem gehoert.
     */
    private function profile(Database $db, Ddl $d): void
    {
        $db->ddl($d->tabelle('profile', [
            $d->id(),
            $d->fremdschluessel('benutzer_id'),
            $d->text('anzeigename', 60, true),
            $d->langtext('vorstellung'),
            $d->text('chat_deklaration', 20, true),
            $d->text('bild_pfad', 255, true),
            $d->text('bild_vorschau_pfad', 255, true),
            $d->jaNein('oeffentlich'),
            $d->angelegtAm(),
            $d->zeitpunkt('geaendert_am'),
            $d->fremdschluesselBedingung('benutzer_id', 'benutzer'),
        ]));

        $this->index($db, $d, 'profile', ['benutzer_id'], true);
    }

    /**
     * Die Unterhaltung: ein Gespraechsfaden zwischen genau zwei Konten.
     *
     * ENTWURFSENTSCHEIDUNG 1 — kontext_schluessel statt eines eindeutigen
     * Index ueber (starter_id, empfaenger_id, angebot_id).
     *
     * Der naheliegende Index scheitert an zwei Stellen, und zwar an beiden
     * still:
     *
     *  a) MySQL wie SQLite behandeln NULL in eindeutigen Indizes als
     *     VERSCHIEDEN. Ein Paar koennte also beliebig viele kontextlose
     *     Unterhaltungen (angebot_id IS NULL) anlegen, ohne dass der Index
     *     jemals anschlaegt — genau derselbe Fehler, an dem der Index auf
     *     hauptbuch_konten die Plattformkonten nicht schuetzt
     *     (003_hauptbuch.php, siehe dortiger Kommentar).
     *  b) A -> B und B -> A waeren zwei verschiedene Spaltenpaare und damit
     *     zwei Unterhaltungen. Zwei Leute, die einander schreiben, saessen in
     *     zwei getrennten Faeden und saehen die Antwort des anderen nie.
     *
     * Die Zeichenkette "<kleinereId>:<groessereId>:<angebotId|0>" loest beides
     * mit einem Griff: Die Sortierung der beiden Kennungen macht A -> B und
     * B -> A zum selben Wert, und die 0 ersetzt das NULL durch einen Wert, den
     * ein eindeutiger Index auch wirklich vergleicht. 80 Zeichen reichen fuer
     * drei Kennungen samt Trennern um ein Vielfaches.
     *
     * WICHTIG, weil es leicht zu verwechseln ist: Die Normalisierung liegt
     * AUSSCHLIESSLICH im Schluessel. teilnehmer_a_id ist die Person, die die
     * Unterhaltung EROEFFNET hat, teilnehmer_b_id die angeschriebene. Diese
     * Rolle geht sonst verloren, und mit ihr die einzige Grundlage, auf der
     * sich "30 neue Unterhaltungen je Tag und Konto" ueberhaupt messen laesst
     * — wer angeschrieben WIRD, darf davon nichts abbekommen, sonst legt ein
     * Angreifer das Postfach einer beliebten Verkaeuferin still, indem er sie
     * dreissigmal anschreibt.
     *
     * Deshalb auch zwei Indizes statt einem: Die eigenen Unterhaltungen liegen
     * je zur Haelfte in der einen und der anderen Spalte, beide Haelften
     * werden nach letzte_nachricht_am sortiert gelesen.
     *
     * angebot_id ist ON DELETE SET NULL: Verschwindet das Angebot, bleibt die
     * Unterhaltung stehen und verliert nur ihren Bezug. Der Verlauf ist
     * Beweismittel fuer Meldungen — er darf an einem geloeschten Angebot nicht
     * mit haengen.
     *
     * geschlossen_am ist heute von keinem Weg gesetzt und steht trotzdem
     * schon hier: Ddl kann kein ALTER TABLE, und eine leere Spalte ist
     * billiger als eine Migration von Hand.
     */
    private function unterhaltungen(Database $db, Ddl $d): void
    {
        $db->ddl($d->tabelle('unterhaltungen', [
            $d->id(),
            $d->text('kontext_schluessel', 80),
            $d->fremdschluessel('teilnehmer_a_id'),
            $d->fremdschluessel('teilnehmer_b_id'),
            $d->fremdschluessel('angebot_id', true),
            $d->zeitpunkt('letzte_nachricht_am'),
            $d->zeitpunkt('geschlossen_am'),
            $d->angelegtAm(),
            $d->fremdschluesselBedingung('teilnehmer_a_id', 'benutzer'),
            $d->fremdschluesselBedingung('teilnehmer_b_id', 'benutzer'),
            $d->fremdschluesselBedingung('angebot_id', 'angebote', 'id', 'SET NULL'),
        ]));

        $this->index($db, $d, 'unterhaltungen', ['kontext_schluessel'], true);
        $this->index($db, $d, 'unterhaltungen', ['teilnehmer_a_id', 'letzte_nachricht_am']);
        $this->index($db, $d, 'unterhaltungen', ['teilnehmer_b_id', 'letzte_nachricht_am']);
    }

    /**
     * Die Nachricht.
     *
     * ENTWURFSENTSCHEIDUNG 2 — deklaration steht je NACHRICHT, nicht nur je
     * Konto.
     *
     * Die Angabe im Profil aendert sich: Wer heute selbst schreibt, laesst
     * morgen ein Team antworten. Laege die Deklaration nur dort, wuerde jede
     * frueher geschriebene Nachricht rueckwirkend unter der neuen Behauptung
     * erscheinen. Eine Nachricht, die unter "Sie schreibt selbst" entstand,
     * muss aber genau dieser Behauptung zugeordnet bleiben — sonst laesst sich
     * eine Falschangabe nie belegen, und die Deklaration ist dekorativ statt
     * ueberpruefbar. Sie ist damit eine Momentaufnahme, kein Verweis: NOT NULL
     * und ohne Fremdschluessel auf das Profil.
     *
     * verborgen_am statt DELETE. Eine Nachricht, die gegen die Regeln
     * verstiess, ist das Beweismittel des Verfahrens gegen sie — mit dem
     * Loeschen verschwaende die Grundlage jeder Meldung, jeder Beschwerde nach
     * Art. 20 DSA und jeder Auskunft an eine Behoerde. Unterhaltungen::
     * verbergen() setzt deshalb nur den Zeitpunkt; die Leseabfragen blenden
     * den Text aus, statt die Zeile zu entfernen.
     *
     * gelesen_am liegt ebenfalls je Nachricht und nicht als "zuletzt gelesen"
     * je Teilnehmer: Nur so bleibt die Zahl der Ungelesenen zaehlbar, ohne
     * einen Zeitvergleich ueber alle Nachrichten zu fuehren.
     *
     * Der Index (unterhaltung_id, id) traegt beides — den Verlauf in
     * Reihenfolge und das Nachladen ab einer Kennung (WHERE id > :seit). Der
     * Index (absender_id, angelegt_am) traegt die Drosselung, die bei jedem
     * Senden zaehlt, wie viele Nachrichten dieses Konto in der letzten Minute
     * abgesetzt hat.
     */
    private function nachrichten(Database $db, Ddl $d): void
    {
        $db->ddl($d->tabelle('nachrichten', [
            $d->id(),
            $d->fremdschluessel('unterhaltung_id'),
            $d->fremdschluessel('absender_id'),
            $d->langtext('text', false),
            $d->schluesselwort('deklaration', 20),
            $d->zeitpunkt('gelesen_am'),
            $d->zeitpunkt('verborgen_am'),
            $d->angelegtAm(),
            $d->fremdschluesselBedingung('unterhaltung_id', 'unterhaltungen'),
            $d->fremdschluesselBedingung('absender_id', 'benutzer'),
        ]));

        $this->index($db, $d, 'nachrichten', ['unterhaltung_id', 'id']);
        $this->index($db, $d, 'nachrichten', ['absender_id', 'angelegt_am']);
    }

    /**
     * Legt einen Index an, wenn er noch fehlt.
     *
     * MySQL kennt bei CREATE INDEX kein IF NOT EXISTS (Ddl::index setzt es nur
     * bei SQLite). Weil eine abgebrochene Migration beim naechsten Lauf von
     * vorn beginnt, wuerde ein blindes CREATE INDEX dort mit "1061 Duplicate
     * key name" scheitern — und zwar bei jedem weiteren Versuch erneut. Genau
     * daran ist ab dem zweiten Deployment schon einmal alles gescheitert.
     *
     * @param list<string> $spalten
     */
    private function index(Database $db, Ddl $d, string $tabelle, array $spalten, bool $eindeutig = false): void
    {
        if ($d->indexVorhanden($tabelle, $d->indexName($tabelle, $spalten))) {
            return;
        }

        $db->ddl($d->index($tabelle, $spalten, $eindeutig));
    }
};
