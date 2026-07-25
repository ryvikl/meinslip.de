<?php

declare(strict_types=1);

use MeinSlip\Core\Database;
use MeinSlip\Core\Ddl;

/**
 * Katalog: Kategorien, Angebote, Konfigurator-Optionen, Medien.
 *
 * Die Kategorien sind nicht Beiwerk, sondern der Discovery-Motor. Die
 * Marktanalyse zeigt: Clips4Sale traegt mit ueber tausend Fetisch-Kategorien
 * eine ganze Plattform allein ueber die Taxonomie, waehrend JOYclub und
 * FetLife die reichsten Vorlieben-Taxonomien im deutschsprachigen Raum haben
 * und sie ausschliesslich fuers Partner-Matching nutzen — nie zum Verkaufen.
 *
 * Die Optionen sind ebenfalls nicht Beiwerk: Eine Option mit
 * ist_spezifikation = 1 ist der Nachweis, dass die Ware nach Kundenspezifikation
 * angefertigt wurde. Darauf stuetzt sich der Widerrufsausschluss nach
 * § 312g Abs. 2 Nr. 1 BGB — die Hygiene-Ausnahme nach Nr. 3 traegt bei
 * getragener Waesche nicht verlaesslich (EuGH C-681/17 "slewo").
 */
return new class {
    public function bezeichnung(): string
    {
        return 'Katalog';
    }

    public function hoch(Database $db, Ddl $d): void
    {
        // --- Kategorien --------------------------------------------------
        $db->ddl($d->tabelle('kategorien', [
            $d->id(),
            $d->fremdschluessel('eltern_id', true),
            $d->text('schluessel', 80),        // stabiler Bezeichner fuer Uebersetzungen
            $d->text('pfad', 255),             // z. B. waesche/slips/sport
            $d->ganzzahl('reihenfolge', false, 0),
            $d->jaNein('aktiv', true),
            $d->angelegtAm(),
            $d->fremdschluesselBedingung('eltern_id', 'kategorien', 'id', 'SET NULL'),
        ]));
        $db->ddl($d->index('kategorien', ['schluessel'], true));
        $db->ddl($d->index('kategorien', ['pfad']));

        // --- Angebote ----------------------------------------------------
        $db->ddl($d->tabelle('angebote', [
            $d->id(),
            $d->fremdschluessel('verkaeufer_id'),
            $d->fremdschluessel('kategorie_id'),
            $d->text('titel', 190),
            $d->langtext('beschreibung'),
            $d->betrag('grundpreis_cent'),
            $d->text('waehrung', 3),
            $d->schluesselwort('status'),          // entwurf | aktiv | pausiert | entfernt
            $d->jaNein('versand_moeglich', true),
            $d->jaNein('uebergabe_moeglich', false),
            $d->text('uebergabe_region', 40, true), // grobe Region, nie eine Adresse
            $d->ganzzahl('bearbeitungstage', false, 3),
            $d->angelegtAm(),
            $d->zeitpunkt('geaendert_am'),
            $d->fremdschluesselBedingung('verkaeufer_id', 'benutzer'),
            $d->fremdschluesselBedingung('kategorie_id', 'kategorien', 'id', 'RESTRICT'),
        ]));
        $db->ddl($d->index('angebote', ['verkaeufer_id']));
        $db->ddl($d->index('angebote', ['kategorie_id', 'status']));

        // --- Konfigurator-Optionen ---------------------------------------
        // ist_spezifikation kennzeichnet Optionen, die eine echte
        // Kundenspezifikation darstellen — Grundlage des Widerrufsausschlusses.
        $db->ddl($d->tabelle('angebot_optionen', [
            $d->id(),
            $d->fremdschluessel('angebot_id'),
            $d->text('schluessel', 80),
            $d->text('bezeichnung', 190),
            $d->langtext('erlaeuterung'),
            $d->betrag('aufpreis_cent'),
            $d->schluesselwort('art'),             // auswahl | zahl | freitext
            $d->jaNein('ist_spezifikation', true),
            $d->jaNein('pflicht', false),
            $d->ganzzahl('reihenfolge', false, 0),
            $d->jaNein('aktiv', true),
            $d->angelegtAm(),
            $d->fremdschluesselBedingung('angebot_id', 'angebote'),
        ]));
        $db->ddl($d->index('angebot_optionen', ['angebot_id']));

        // --- Medien ------------------------------------------------------
        // Die unscharfe Vorschau ist eine EIGENE Datei, serverseitig erzeugt.
        // Ein Weichzeichner per CSS waere wirkungslos: Das Original laege
        // dann im Browser. Umsetzungsdetail mit rechtlicher Wirkung.
        $db->ddl($d->tabelle('angebot_medien', [
            $d->id(),
            $d->fremdschluessel('angebot_id'),
            $d->text('pfad', 255),                 // ausserhalb von public/
            $d->text('vorschau_pfad', 255, true),  // unscharfe Fassung fuer Gaeste
            $d->schluesselwort('art'),             // bild | video
            $d->jaNein('explizit', false),
            $d->ganzzahl('reihenfolge', false, 0),
            $d->angelegtAm(),
            $d->fremdschluesselBedingung('angebot_id', 'angebote'),
        ]));
        $db->ddl($d->index('angebot_medien', ['angebot_id']));
    }
};
