<?php

declare(strict_types=1);

use MeinSlip\Core\Database;
use MeinSlip\Core\Ddl;

/**
 * Der Modellwechsel: von der Vorabpruefung zur Nachmoderation.
 *
 * Bisher standen zwei Tore vor jedem Verkauf: Die Verwaltung musste erst die
 * Faehigkeit 'verkaufen' vergeben, und danach jedes einzelne Angebot freigeben.
 * Beide fallen. Kuenftig gilt Marktplatzlogik — registrieren genuegt, das
 * Angebot ist sofort sichtbar, die Verwaltung greift erst auf Meldung hin ein.
 * Art. 16 DSA traegt das nur, weil der Meldeweg im selben Paket entsteht: Ohne
 * funktionierendes Melde- und Abhilfeverfahren waere der Abbau der
 * Vorabpruefung nicht verteidigbar.
 *
 * Die Faehigkeit verschwindet dabei nicht, sie wechselt die Rolle. Aus der
 * Vorbedingung wird der Sanktionsgriff: Nach dem Torabbau ist ihr Entzug das
 * einzige verbliebene Verkaufsverbot unterhalb der Kontosperre — protokolliert,
 * begruendet und zugestellt nach Art. 17 DSA.
 *
 * Diese Datei aendert deshalb kein Schema, sondern ausschliesslich Daten. Zwei
 * Schritte, die beide nur den Bestand betreffen: Neue Konten bekommen die
 * Faehigkeit kuenftig bei der Registrierung, die vorhandenen brauchen sie
 * nachgetragen; und wer in der abgeschafften Vorabpruefung haengt, muss dort
 * heraus.
 *
 * Beide Schritte sind idempotent. Das ist keine Kuer: Migrationen laufen ohne
 * Transaktionsklammer (Migrator.php). Bricht hoch() zwischen den Schritten ab,
 * fehlt der Eintrag in 'migrationen', und der naechste Lauf startet diese Datei
 * von vorn — der erste Schritt liefe dann ein zweites Mal.
 */
return new class {
    public function bezeichnung(): string
    {
        return 'Marktmodell: Verkaufsfaehigkeit fuer Bestandskonten, Bestand aus der Vorabpruefung';
    }

    public function hoch(Database $db, Ddl $d): void
    {
        // Ein Zeitpunkt fuer beide Schritte: Sie gehoeren zu einer Entscheidung
        // und sollen sich in den Daten auch als eine wiederfinden lassen.
        $jetzt = gmdate('Y-m-d H:i:s');

        $this->verkaufsfaehigkeitNachtragen($db, $jetzt);
        $this->vorabpruefungAufloesen($db, $jetzt);
    }

    /**
     * Schritt 1: die Faehigkeit 'verkaufen' fuer alle Bestandskonten.
     *
     * Ohne diesen Schritt koennte niemand, der heute schon ein Konto hat, morgen
     * verkaufen. Konten::registrieren() vergibt die Faehigkeit kuenftig beim
     * Anlegen — das hilft aber nur neuen Konten, und der bisherige Weg ueber die
     * Verwaltung faellt mit dem Torabbau weg.
     *
     * Das NOT EXISTS ist zwingend, nicht bloss sparsam: benutzer_faehigkeiten
     * traegt UNIQUE(benutzer_id, faehigkeit) (001_konten.php:54). Ein zweiter
     * Lauf ohne die Bedingung braeche mit SQLSTATE 23000 ab, und weil ein
     * Abbruch die Datei von vorn startet, scheiterte danach jeder weitere Lauf
     * an derselben Stelle.
     *
     * Die Bedingung fragt bewusst NICHT nach 'entzogen_am IS NULL'. Wem die
     * Faehigkeit einmal entzogen wurde, dem traegt die Migration sie nicht
     * wieder ein: Die vorhandene Zeile bleibt mit ihrem entzogen_am stehen, und
     * der Entzug bleibt wirksam. Ein Filter auf entzogen_am haette gleich zwei
     * Fehler — er hoebe genau die Sanktion auf, die nach dem Torabbau das
     * einzige Verkaufsverbot ist, und liefe zudem in den Eindeutigkeitsindex.
     *
     * Nur Konten mit status 'aktiv'. Gesperrte und geloeschte Konten bekommen
     * nichts: Eine Kontosperre ist eine begruendete Verwaltungsentscheidung, und
     * eine Migration darf sie nicht im Vorbeigehen aufweichen, indem sie der
     * gesperrten Person still eine Berechtigung zuschreibt. Der Preis ist
     * bekannt: Ein spaeter entsperrtes Bestandskonto steht dann ohne Faehigkeit
     * da. Das Nachziehen gehoert in die Entsperrung, wo eine verantwortliche
     * Person entscheidet — nicht hierher.
     *
     * INSERT ... SELECT statt einer Schleife in PHP: ein Rundlauf statt einem je
     * Konto, auf SQLite wie auf MySQL 8 wortgleich, und die Doppeltenpruefung
     * liegt in derselben Anweisung wie das Einfuegen, kann also nicht zwischen
     * Pruefung und Schreiben veralten. Fehler 1093 ('target table ... in FROM
     * clause') betrifft nur UPDATE und DELETE; fuer INSERT ... SELECT ist der
     * Blick in die Zieltabelle auf MySQL zulaessig.
     *
     * 'verkaufen' und 'registrierung' stehen hier als Zeichenketten und nicht
     * als Konten::FAEHIGKEIT_VERKAUFEN / Konten::GRUNDLAGE_REGISTRIERUNG. Eine
     * Migration ist ein Bericht ueber die Vergangenheit: Wuerde eine spaetere
     * Umbenennung der Konstante ihren Inhalt mitaendern, beschriebe sie nicht
     * mehr, was damals wirklich geschah.
     */
    private function verkaufsfaehigkeitNachtragen(Database $db, string $jetzt): void
    {
        $db->ausfuehren(
            'INSERT INTO benutzer_faehigkeiten'
            . ' (benutzer_id, faehigkeit, grundlage, freigeschaltet_am)'
            . " SELECT b.id, 'verkaufen', 'registrierung', :jetzt"
            . ' FROM benutzer b'
            . " WHERE b.status = 'aktiv'"
            . ' AND NOT EXISTS ('
            . ' SELECT 1 FROM benutzer_faehigkeiten f'
            . ' WHERE f.benutzer_id = b.id'
            . " AND f.faehigkeit = 'verkaufen'"
            . ')',
            ['jetzt' => $jetzt]
        );
    }

    /**
     * Schritt 2: den Bestand aus 'in_pruefung' aktivieren.
     *
     * Die Vorabpruefung faellt weg, also darf niemand in ihr haengenbleiben.
     * Angebote::freigeben() bleibt zwar bestehen, aber es fuehrt kein Weg mehr
     * nach 'in_pruefung' hinein, und die Arbeitsliste der Verwaltung zeigt
     * kuenftig gemeldete Angebote. Ein Angebot, das jetzt noch dort steht,
     * wartete also auf eine Pruefung, die es nicht mehr gibt — ohne diesen
     * Schritt bliebe es unsichtbar, und niemand wuerde es je bemerken.
     *
     * Durch die Filterbedingung von selbst idempotent: Nach dem ersten Lauf gibt
     * es keine Zeile mehr, auf die sie zutraefe.
     *
     * Bewusst OHNE Benachrichtigung. Eine Migration hat keine verwalter_id fuer
     * verwaltungs_ereignisse, und eine benachrichtigungen-Zeile ohne Journalzeile
     * wuerde die Beweiskette aus 009_nachbesserung.php aufweichen: Dort belegen
     * sich Protokolleintrag und Zustellung gegenseitig, damit keine Zustellung
     * ohne nachweisbare Entscheidung entsteht. Vertretbar ist das, weil hier
     * ausschliesslich zugunsten der Betroffenen entschieden wird — Art. 17 DSA
     * verlangt die Begruendung fuer BESCHRAENKUNGEN, fuer eine Beguenstigung
     * verlangt er nichts.
     *
     * geaendert_am wird mitgesetzt, damit die Zeilen nicht so aussehen, als
     * haetten sie sich nie bewegt: Der Zeitpunkt ist die einzige Spur, die von
     * diesem Statuswechsel bleibt.
     */
    private function vorabpruefungAufloesen(Database $db, string $jetzt): void
    {
        $db->ausfuehren(
            "UPDATE angebote SET status = 'aktiv', geaendert_am = :jetzt"
            . " WHERE status = 'in_pruefung'",
            ['jetzt' => $jetzt]
        );
    }
};
