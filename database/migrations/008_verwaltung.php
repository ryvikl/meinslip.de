<?php

declare(strict_types=1);

use MeinSlip\Core\Database;
use MeinSlip\Core\Ddl;

/**
 * Protokoll der Verwaltungshandlungen.
 *
 * Art. 17 DSA verlangt fuer JEDE Beschraenkung — gesperrtes Konto, entzogene
 * Faehigkeit, entferntes Angebot — eine Begruendung gegenueber der betroffenen
 * Person. Eine Begruendung, die nur im Kopf der entscheidenden Person existiert,
 * ist keine: Ohne unveraenderliches Protokoll laesst sich weder belegen, dass
 * ueberhaupt begruendet wurde, noch nachvollziehen, wer entschieden hat.
 *
 * Deshalb ist diese Tabelle bewusst ein reines Journal: es wird nur angehaengt,
 * nie geaendert und nie geloescht. Aus demselben Grund haengt der Fremdschluessel
 * auf benutzer an ON DELETE RESTRICT statt am Standard CASCADE — ein geloeschtes
 * Verwalterkonto duerfte sonst die Beweislage mitnehmen.
 */
return new class {
    public function bezeichnung(): string
    {
        return 'Protokoll der Verwaltungshandlungen';
    }

    public function hoch(Database $db, Ddl $d): void
    {
        $db->ddl($d->tabelle('verwaltungs_ereignisse', [
            $d->id(),
            $d->fremdschluessel('verwalter_id'),
            $d->text('handlung', 60),         // faehigkeit_freigeschaltet | konto_gesperrt | ...
            $d->text('gegenstand_art', 40),   // benutzer | angebot | meldung | bestellung
            // Polymorpher Verweis wie bei meldungen.gegenstand_id: je nach Art
            // zeigt er in eine andere Tabelle, deshalb keine Bedingung darauf.
            // Die Datenbank prueft hier nichts — das muss der Code tun.
            $d->fremdschluessel('gegenstand_id', true),
            $d->langtext('begruendung'),
            $d->angelegtAm(),
            $d->fremdschluesselBedingung('verwalter_id', 'benutzer', 'id', 'RESTRICT'),
        ]));

        // MySQL kennt kein IF NOT EXISTS bei CREATE INDEX (Ddl::index setzt es
        // nur fuer SQLite). Blank ist das hier vertretbar, weil der Migrator
        // jede Datei genau einmal ausfuehrt. Es traegt aber nur solange, wie das
        // stimmt: Migrationen laufen ohne Transaktion, bricht hoch() nach der
        // Tabelle und vor den Indizes ab, fehlt der Eintrag in 'migrationen' und
        // der naechste Lauf startet diese Datei von vorn. Muss die Migration je
        // wiederholt werden, gehoert vor jede Zeile ein
        // $d->indexVorhanden('verwaltungs_ereignisse', $d->indexName(...)).
        $db->ddl($d->index('verwaltungs_ereignisse', ['verwalter_id']));
        // Der Blick aus Sicht der betroffenen Person: alles, was je gegen
        // diesen einen Gegenstand entschieden wurde.
        $db->ddl($d->index('verwaltungs_ereignisse', ['gegenstand_art', 'gegenstand_id']));
        $db->ddl($d->index('verwaltungs_ereignisse', ['angelegt_am']));
    }
};
