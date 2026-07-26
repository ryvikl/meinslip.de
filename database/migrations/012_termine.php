<?php

declare(strict_types=1);

use MeinSlip\Core\Database;
use MeinSlip\Core\Ddl;

/**
 * Termine: die geldfreie Parallelspur zur Uebergabe.
 *
 * WARUM EINE EIGENE TABELLE UND NICHT bestellungen.
 * Bestellungen::uebergabePlanen() setzt eine Bestellung voraus, und ohne
 * Zahlung entsteht keine. Der Bestellvorgang ist ausserdem aufsichtsrechtlich
 * verriegelt (Bestellungen::pruefeFreigabe). Ein Termin, der an einer
 * Bestellung haengt, waere damit erst nach der Freischaltung der Treuhand
 * benutzbar — also nie in der ersten Fassung. 'termine' ist deshalb bewusst
 * geldfrei: Zwei Leute verabreden eine Uebergabe, und ob dabei Geld fliesst,
 * entscheidet eine spaetere Spur.
 *
 * bestellung_id STEHT TROTZDEM SCHON HIER, NULL-BAR. Ddl kann kein ALTER
 * TABLE (app/Core/Ddl.php). Was heute fehlt, laesst sich spaeter nur als
 * Handarbeit mit Dialektfall nachtragen — dieselbe Begruendung, aus der
 * 011_profile_und_chat.php die Creator-Spalten von 'profile' schon mitbringt.
 * Eine leere Spalte kostet nichts. Sie wird von keinem Weg beschrieben; erst
 * das Paket, das Termin und Treuhand verbindet, setzt sie.
 *
 * DER TERMIN HAENGT AN DER UNTERHALTUNG, NICHT AM ANGEBOT.
 * Drei Gruende, alle drei tragend:
 *
 *  1. In einer Unterhaltung sind GENAU ZWEI Personen definiert
 *     (teilnehmer_a_id, teilnehmer_b_id). "Das Gegenueber" ist damit
 *     bestimmbar — und ohne diese Bestimmbarkeit gibt es die Regel "annehmen
 *     darf nur das Gegenueber" nicht. An einem Angebot haengt nur eine
 *     Person; wer die zweite ist, waere Auslegung.
 *  2. Die Sperrliste gilt dort bereits. Unterhaltungen::pruefeKeineSperre()
 *     prueft beide Richtungen, und Termine tut dasselbe. Ein Terminvorschlag
 *     an eine Person, die einen gesperrt hat, waere sonst genau der Kanal,
 *     den die Sperre schliessen sollte.
 *  3. Ein Angebot kann verschwinden (ON DELETE SET NULL), eine Unterhaltung
 *     bleibt — sie ist Beweismittel. Ein Termin ohne Bezugsperson waere
 *     unbrauchbar, ein Termin ohne Angebotsbezug ist bloss kontextlos.
 *
 * angebot_id wird trotzdem mitgefuehrt: Sie kommt aus dem Kontext der
 * Unterhaltung und macht spaeter nachvollziehbar, worum es ging. ON DELETE
 * SET NULL, wie bei unterhaltungen.angebot_id.
 *
 * KEIN FREITEXTFELD "TREFFPUNKT". treffpunkt_art ist ein Schluesselwort aus
 * einer festen Liste (Termine::TREFFPUNKTARTEN), region ist die grobe Region
 * mit 40 Zeichen — dieselbe Grenze wie angebote.uebergabe_region, und aus
 * demselben Grund: 40 Zeichen reichen fuer "Raum Muenchen" und sind zu knapp
 * fuer eine Anschrift. Ein Freitextfeld "Treffpunkt" waere binnen Wochen ein
 * Adressfeld, und eine Anschrift in einer Datenbank dieser Plattform ist der
 * Schaden, gegen den die ganze rechtliche Brandmauer gebaut ist.
 *
 * KEINE KOORDINATEN, KEIN KARTENPUNKT, KEINE ANSCHRIFT — auch nicht optional
 * und auch nicht "fuer spaeter". Das ist der einzige Punkt, an dem die
 * Vorratsspalte oben NICHT gilt: Eine Spalte, die es gibt, wird eines Tages
 * gefuellt. docs/04-features/safe-meet.md nennt Standortdaten im Kontext
 * dieser Plattform Daten besonderer Kategorie nach Art. 9 DSGVO; wer uebergibt,
 * ist ueberwiegend die Verkaeuferin, also die Partei, die am dringendsten vor
 * Nachstellung geschuetzt gehoert. Ein Kartenpunkt ist unwiderruflich.
 *
 * quittiert_a_am UND quittiert_b_am FOLGEN DER UNTERHALTUNG, NICHT DEM
 * VORSCHLAG. a ist unterhaltungen.teilnehmer_a_id, b ist teilnehmer_b_id —
 * unabhaengig davon, wer diesen Termin vorgeschlagen hat. Waeren die beiden
 * Spalten als "vorschlagende Seite" und "andere Seite" gemeint, kehrte ihre
 * Bedeutung bei jedem Vorschlag der Gegenseite um, und eine Auswertung ueber
 * mehrere Termine hinweg waere nicht mehr moeglich.
 *
 * Die Migration legt nur Schema an. Sie ist so gebaut, dass ein zweiter Lauf
 * folgenlos bleibt: Migrationen laufen ohne Transaktionsklammer (Migrator.php),
 * ein Abbruch in der Mitte startet die Datei beim naechsten Lauf von vorn.
 * Ddl::tabelle() bringt IF NOT EXISTS mit; CREATE INDEX kennt es auf MySQL
 * nicht, deshalb steht jeder Index hinter Ddl::indexVorhanden().
 */
return new class {
    public function bezeichnung(): string
    {
        return 'Termine mit beidseitiger Uebergabebestaetigung';
    }

    public function hoch(Database $db, Ddl $d): void
    {
        $this->termine($db, $d);
    }

    /**
     * Der Termin.
     *
     * VOLLSTAENDIG ANGELEGT. Jede Spalte, die dieser Vorgang je braucht, steht
     * hier — einschliesslich bestellung_id, die heute niemand schreibt.
     *
     * status traegt den Zustandsautomaten aus Termine::UEBERGAENGE
     * (vorgeschlagen, angenommen, uebergeben, abgelehnt, verfallen). Als
     * VARCHAR und nicht als ENUM, weil SQLite kein ENUM kennt und eine
     * Zustandserweiterung sonst eine Schemaaenderung waere statt einer
     * Codeaenderung (Ddl::schluesselwort).
     *
     * abgelehnt_am und grund gehoeren zusammen: Die Ablehnung ist die einzige
     * Stelle, an der ein Mensch etwas Eigenes schreiben darf, und sie ist
     * freiwillig. 200 Zeichen sind genug fuer "Passt mir zeitlich nicht" und
     * zu knapp fuer eine Verabredung am Freitextfeld vorbei.
     *
     * Drei Indizes, jeder mit einer Abfrage dahinter:
     *  - (unterhaltung_id, id): die Terminliste im Chatfenster, in Reihenfolge.
     *  - (vorschlagender_id, angelegt_am): die Drosselung, die bei JEDEM
     *    Vorschlag zaehlt, wie viele dieses Konto in den letzten 24 Stunden
     *    abgesetzt hat.
     *  - (status, zeitpunkt): der Verfallslauf, der offene Vorschlaege und
     *    unquittierte Verabredungen nach Fristablauf schliesst.
     */
    private function termine(Database $db, Ddl $d): void
    {
        $db->ddl($d->tabelle('termine', [
            $d->id(),
            $d->fremdschluessel('unterhaltung_id'),
            $d->fremdschluessel('vorschlagender_id'),
            $d->fremdschluessel('angebot_id', true),
            // Heute von keinem Weg beschrieben. Siehe Kopf dieser Datei.
            $d->fremdschluessel('bestellung_id', true),
            $d->zeitpunkt('zeitpunkt', false),
            $d->schluesselwort('treffpunkt_art', 40),
            // Grobe Region, nie eine Adresse — gleiche Grenze wie
            // angebote.uebergabe_region in 004_katalog.php.
            $d->text('region', 40),
            $d->schluesselwort('status', 20),
            // a und b sind die Teilnehmer der Unterhaltung, nicht Vorschlagende
            // und Gegenueber. Siehe Kopf dieser Datei.
            $d->zeitpunkt('quittiert_a_am'),
            $d->zeitpunkt('quittiert_b_am'),
            $d->zeitpunkt('abgelehnt_am'),
            $d->text('grund', 200, true),
            $d->angelegtAm(),
            $d->zeitpunkt('geaendert_am'),
            $d->fremdschluesselBedingung('unterhaltung_id', 'unterhaltungen'),
            $d->fremdschluesselBedingung('vorschlagender_id', 'benutzer'),
            $d->fremdschluesselBedingung('angebot_id', 'angebote', 'id', 'SET NULL'),
            $d->fremdschluesselBedingung('bestellung_id', 'bestellungen', 'id', 'SET NULL'),
        ]));

        $this->index($db, $d, 'termine', ['unterhaltung_id', 'id']);
        $this->index($db, $d, 'termine', ['vorschlagender_id', 'angelegt_am']);
        $this->index($db, $d, 'termine', ['status', 'zeitpunkt']);
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
