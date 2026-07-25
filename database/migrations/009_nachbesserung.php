<?php

declare(strict_types=1);

use MeinSlip\Core\Database;
use MeinSlip\Core\Ddl;

/**
 * Nachbesserung: Eindeutigkeit der Angebotsoptionen, Index fuer die Pruefliste
 * und die Zustellung der Begruendung an die betroffene Person.
 *
 * DREI GRUENDE, IN DIESER REIHENFOLGE:
 *
 * 1. angebot_optionen braucht UNIQUE(angebot_id, schluessel).
 *    Angebote::optionSetzen() liest erst ('Legt eine Option an oder
 *    ueberschreibt die gleichnamige') und fuegt dann ein. Das Schema hat diese
 *    Zusage bisher nicht getragen: 004_katalog.php legt nur einen einfachen
 *    Index auf angebot_id an. Zwei gleichzeitige Anfragen mit demselben
 *    Schluessel sehen im SELECT beide nichts und fuegen beide ein. Danach laeuft
 *    MarktRouten::bestellen() ueber die OPTIONSZEILEN, nicht ueber die
 *    Formularfelder — der einmal gewaehlte Aufpreis wird zweimal addiert, und
 *    das Hauptbuch bucht den falschen Betrag widerspruchsfrei durch. Eine
 *    Transaktion um SELECT+INSERT hilft dagegen nicht: weder unter READ
 *    COMMITTED noch unter REPEATABLE READ sieht die zweite Anfrage die erste.
 *    Nur die Datenbankbedingung schliesst den Wettlauf.
 *
 * 2. angebote braucht einen Index, der bei 'status' beginnt.
 *    Verwaltung::offeneAngebote() filtert auf status und sortiert nach
 *    angelegt_am. Der vorhandene idx_angebote_kategorie_id_status fuehrt
 *    kategorie_id an und ist fuer ein Praedikat allein auf status nicht
 *    nutzbar; auf angelegt_am liegt gar kein Index. Die Kosten der Pruefliste
 *    haengen damit an der Gesamtzahl der Angebote statt an der Laenge der
 *    Arbeitsliste.
 *
 * 3. benachrichtigungen — Art. 17 Abs. 1 DSA.
 *    Die Vorschrift verlangt, dass der Anbieter der betroffenen Person eine
 *    klare und spezifische Begruendung ZUR VERFUEGUNG STELLT. Bisher landet die
 *    Begruendung ausschliesslich in verwaltungs_ereignisse, und diese Tabelle
 *    wird an genau einer Stelle gelesen: im Protokoll unter /verwaltung, also
 *    nur von Verwaltern. Die betroffene Person erfaehrt nichts. Ein internes
 *    Protokoll erfuellt Art. 17 Abs. 1 DSA nicht — es belegt, DASS begruendet
 *    wurde, nicht, dass die Begruendung angekommen ist.
 *
 *    Deshalb zwei Tabellen, und keine ersetzt die andere:
 *      - verwaltungs_ereignisse ist die INTERNE Sicht. Reines Journal, wird nur
 *        angehaengt, haelt fest, WER entschieden hat. Es ist der Nachweis
 *        gegenueber Aufsicht und Gericht und darf der betroffenen Person nicht
 *        roh vorgelegt werden (es enthaelt die Verwalteridentitaet).
 *      - benachrichtigungen ist die Sicht der BETROFFENEN PERSON. Sie traegt
 *        nur, was diese Person erfahren darf und muss, und sie haelt fest, ob
 *        und wann sie gelesen wurde.
 *    verwaltungs_ereignis_id verbindet beide, damit sich Protokolleintrag und
 *    Zustellung gegenseitig belegen: zu jeder Beschraenkung im Journal muss
 *    eine Zustellung auffindbar sein, und jede Zustellung nennt den Vorgang,
 *    auf dem sie beruht. Ohne diese Verbindung liesse sich weder zeigen, dass
 *    zugestellt wurde, noch dass das Zugestellte der Entscheidung entspricht.
 *
 * Alle drei Schritte sind gegen einen Abbruch mitten in hoch() abgesichert:
 * Migrationen laufen ohne Transaktionsklammer (Migrator.php), ein Abbruch
 * hinterlaesst also keinen Eintrag in 'migrationen' und der naechste Lauf
 * startet diese Datei von vorn. CREATE TABLE traegt dafuer IF NOT EXISTS,
 * CREATE INDEX aber nur unter SQLite — MySQL kennt es dort nicht. Deshalb steht
 * vor jedem Index eine indexVorhanden()-Abfrage.
 */
return new class {
    public function bezeichnung(): string
    {
        return 'Eindeutige Angebotsoptionen, Pruefliste-Index und Zustellung nach Art. 17 DSA';
    }

    public function hoch(Database $db, Ddl $d): void
    {
        $this->optionenEindeutig($db, $d);
        $this->prueflisteIndex($db, $d);
        $this->hauptbuchsichtIndex($db, $d);
        $this->benachrichtigungen($db, $d);
    }

    /**
     * Schritt 2b: die Hauptbuchsicht der Verwaltung.
     *
     * Verwaltung::hauptbuchVorgaenge() holt die Seite mit
     * 'ORDER BY angelegt_am DESC, id DESC LIMIT 25'. Ohne Index ist das ein
     * vollstaendiger Sortierdurchlauf ueber alle Vorgaenge — und diese Tabelle
     * ist die eine, die mit jeder Buchung waechst und nie kleiner wird.
     *
     * Die Sicht dient dem Eingrenzen einer Hauptbuchabweichung. Genau dann
     * zaehlt sie, und genau dann ist die Tabelle am groessten.
     */
    private function hauptbuchsichtIndex(Database $db, Ddl $d): void
    {
        $name = $d->indexName('hauptbuch_vorgaenge', ['angelegt_am', 'id']);

        if (!$d->indexVorhanden('hauptbuch_vorgaenge', $name)) {
            $db->ddl($d->index('hauptbuch_vorgaenge', ['angelegt_am', 'id']));
        }
    }

    /**
     * Schritt 1: Doubletten raeumen, dann die Bedingung setzen.
     *
     * Die Reihenfolge ist zwingend. Auf einer Datenbank, die den Wettlauf schon
     * erlebt hat, scheitert CREATE UNIQUE INDEX an den vorhandenen Zeilen —
     * und weil die Migration dann abbricht, bliebe sie dauerhaft offen.
     */
    private function optionenEindeutig(Database $db, Ddl $d): void
    {
        // Bewusst zwei Schritte in PHP statt eines DELETE mit Unterabfrage:
        // MySQL verbietet in DELETE das Lesen der Zieltabelle im Unterausdruck
        // (Fehler 1093). Die ueblichen Umgehungen ueber eine abgeleitete Tabelle
        // haengen daran, dass der Optimierer sie nicht wegoptimiert. Die IDs
        // hier zu sammeln ist auf beiden Datenbanken dasselbe und laesst nichts
        // vom Optimierer abhaengen.
        //
        // Behalten wird je (angebot_id, schluessel) die kleinste id: Sie ist die
        // zuerst angelegte Zeile, auf die bestehende Verweise am ehesten zeigen.
        // Das Loeschen der uebrigen ist unbedenklich, weil
        // bestellung_spezifikationen.option_id an ON DELETE SET NULL haengt und
        // Schluessel, Bezeichnung, Wert und Aufpreis ohnehin als eigene Kopie
        // fuehrt — bereits geschlossene Bestellungen verlieren nichts.
        $gruppen = $db->alle(
            'SELECT angebot_id, schluessel, MIN(id) AS behalten'
            . '  FROM angebot_optionen'
            . ' GROUP BY angebot_id, schluessel'
            . ' HAVING COUNT(*) > 1'
        );

        foreach ($gruppen as $gruppe) {
            $db->ausfuehren(
                'DELETE FROM angebot_optionen'
                . ' WHERE angebot_id = :angebot_id'
                . '   AND schluessel = :schluessel'
                . '   AND id > :behalten',
                [
                    'angebot_id' => (int) $gruppe['angebot_id'],
                    'schluessel' => (string) $gruppe['schluessel'],
                    'behalten' => (int) $gruppe['behalten'],
                ]
            );
        }

        // idx_angebot_optionen_angebot_id aus 004_katalog.php bleibt stehen.
        // Er wird vom neuen Index als Linkspraefix ueberdeckt und koennte
        // entfallen; ihn hier zu loeschen brauchte aber wieder eine
        // Dialektfallunterscheidung und braechte nichts ausser Risiko.
        $name = $d->indexName('angebot_optionen', ['angebot_id', 'schluessel']);

        if (!$d->indexVorhanden('angebot_optionen', $name)) {
            $db->ddl($d->index('angebot_optionen', ['angebot_id', 'schluessel'], true));
        }
    }

    /**
     * Schritt 2: Index fuer die Pruefliste der Verwaltung.
     *
     * Die Spaltenreihenfolge traegt: status zuerst, weil darauf der
     * Gleichheitsfilter liegt, danach angelegt_am fuer die Sortierung.
     * Umgekehrt bediente der Index nur die Sortierung und nicht den Filter.
     *
     * Keine dritte Spalte 'id': InnoDB fuehrt den Primaerschluessel implizit im
     * Sekundaerindex, SQLite die rowid — 'ORDER BY angelegt_am ASC, id ASC' ist
     * damit bereits vollstaendig bedient.
     *
     * Der Befund nennt den Filter auf status. angelegt_am kommt hinzu, weil
     * dieselbe Abfrage danach sortiert: ohne die zweite Spalte bliebe die
     * Sortierung ein temporaerer B-Baum beziehungsweise ein filesort, der
     * Aufwand haenge also weiter an der Gesamtzahl der Angebote. Der Index
     * beginnt mit status und bedient jeden Filter auf status als Linkspraefix
     * mit — etwa den Katalogzaehler auf /entdecken.
     *
     * idx_angebote_kategorie_id_status bleibt unangetastet: Angebote::fuerKatalog()
     * filtert auf (kategorie_id, status) und braucht ihn weiterhin.
     */
    private function prueflisteIndex(Database $db, Ddl $d): void
    {
        $name = $d->indexName('angebote', ['status', 'angelegt_am']);

        if (!$d->indexVorhanden('angebote', $name)) {
            $db->ddl($d->index('angebote', ['status', 'angelegt_am']));
        }
    }

    /**
     * Schritt 3: die Zustellung nach Art. 17 DSA.
     */
    private function benachrichtigungen(Database $db, Ddl $d): void
    {
        $db->ddl($d->tabelle('benachrichtigungen', [
            $d->id(),
            // Die betroffene Person. Loescht sie ihr Konto, gehen ihre
            // Zustellungen mit (Standard CASCADE) — der Nachweis, DASS
            // begruendet wurde, bleibt im Journal verwaltungs_ereignisse
            // erhalten, das genau dafuer an ON DELETE RESTRICT haengt.
            $d->fremdschluessel('benutzer_id'),
            // faehigkeit_entzogen | konto_gesperrt | angebot_abgelehnt | ...
            // Bewusst dieselbe Laenge und Schreibweise wie
            // verwaltungs_ereignisse.handlung, damit sich beide Seiten ohne
            // Uebersetzungstabelle zuordnen lassen.
            $d->text('art', 60),
            // Worauf sich die Beschraenkung bezieht. Polymorph wie bei
            // meldungen und verwaltungs_ereignisse: je nach Art liegt der
            // Gegenstand in einer anderen Tabelle, deshalb keine Bedingung
            // darauf. Die Datenbank prueft hier nichts — das muss der Code tun.
            $d->text('gegenstand_art', 40, true),
            $d->fremdschluessel('gegenstand_id', true),
            // Der Text, den die betroffene Person zu lesen bekommt. Nullable
            // wie verwaltungs_ereignisse.begruendung, damit sich der Wert von
            // dort ohne Umweg uebernehmen laesst; die Pflicht zur Begruendung
            // steht im Code (Verwaltung::pflichtBegruendung), nicht im Schema.
            $d->langtext('begruendung'),
            // Verbindung zum Protokolleintrag. Nullable, weil nicht jede
            // Zustellung aus einer Verwaltungshandlung stammen muss; SET NULL,
            // weil eine geloeschte Journalzeile die bereits erfolgte Zustellung
            // nicht mitreissen darf — die betroffene Person hat sie erhalten,
            // das bleibt wahr.
            $d->fremdschluessel('verwaltungs_ereignis_id', true),
            // Nachweis, dass die Begruendung angekommen ist, und Grundlage der
            // Ungelesen-Anzeige.
            $d->zeitpunkt('gelesen_am'),
            $d->angelegtAm(),
            $d->fremdschluesselBedingung('benutzer_id', 'benutzer'),
            $d->fremdschluesselBedingung('verwaltungs_ereignis_id', 'verwaltungs_ereignisse', 'id', 'SET NULL'),
        ]));

        // Der Normalfall: die ungelesenen Zustellungen einer Person. gelesen_am
        // als zweite Spalte bedient sowohl 'IS NULL' als auch die Sortierung
        // nach Lesezeitpunkt.
        $name = $d->indexName('benachrichtigungen', ['benutzer_id', 'gelesen_am']);

        if (!$d->indexVorhanden('benachrichtigungen', $name)) {
            $db->ddl($d->index('benachrichtigungen', ['benutzer_id', 'gelesen_am']));
        }

        // Fuer die Auswertung ueber alle Personen hinweg — etwa der Nachweis
        // gegenueber der Aufsicht, wie viele Beschraenkungen in einem Zeitraum
        // begruendet zugestellt wurden.
        $name = $d->indexName('benachrichtigungen', ['angelegt_am']);

        if (!$d->indexVorhanden('benachrichtigungen', $name)) {
            $db->ddl($d->index('benachrichtigungen', ['angelegt_am']));
        }
    }
};
