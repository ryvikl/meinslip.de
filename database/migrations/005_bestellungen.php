<?php

declare(strict_types=1);

use MeinSlip\Core\Database;
use MeinSlip\Core\Ddl;

/**
 * Bestellungen, Treuhand und Protokoll.
 *
 * Zwei Dinge, die hier anders sind als bei einem gewoehnlichen Shop:
 *
 * 1. KOMMISSIONSMODELL. Jede Bestellung ist zwei Geschaeftsvorfaelle, nicht
 *    einer mit Provisionsabzug: Die GmbH kauft beim Creator ein UND verkauft
 *    an den Kaeufer. Deshalb fuehrt jede Position einkaufspreis_cent und
 *    verkaufspreis_cent getrennt. Umsatzsteuersatz und Land stehen PRO
 *    POSITION, nicht global — sonst waere die Expansion ein Umbau.
 *
 * 2. FREIGABE ERST NACH EINSPRUCHSFENSTER. Kein Ereignis gibt Geld sofort
 *    frei. Der urspruengliche Entwurf sah vor, dass ein QR-Scan das Geld
 *    unwiderruflich uebertraegt — genau diese Endgueltigkeit im
 *    Uebergabemoment ist die Eigenschaft, die Betrueger suchen.
 */
return new class {
    public function bezeichnung(): string
    {
        return 'Bestellungen und Treuhand';
    }

    public function hoch(Database $db, Ddl $d): void
    {
        // --- Bestellungen ------------------------------------------------
        $db->ddl($d->tabelle('bestellungen', [
            $d->id(),
            $d->text('nummer', 30),
            $d->fremdschluessel('kaeufer_id'),
            $d->fremdschluessel('verkaeufer_id'),
            $d->schluesselwort('zustand', 40),
            $d->schluesselwort('lieferart'),          // versand | uebergabe
            $d->text('land', 2),
            $d->text('waehrung', 3),
            $d->betrag('summe_verkauf_cent'),        // was der Kaeufer zahlt, brutto
            $d->betrag('summe_einkauf_cent'),        // was der Creator erhaelt
            $d->betrag('summe_provision_cent'),
            $d->betrag('summe_ust_cent'),
            $d->zeitpunkt('einspruchsfenster_bis'),
            $d->zeitpunkt('annahmefrist_bis'),
            $d->angelegtAm(),
            $d->zeitpunkt('geaendert_am'),
            $d->fremdschluesselBedingung('kaeufer_id', 'benutzer', 'id', 'RESTRICT'),
            $d->fremdschluesselBedingung('verkaeufer_id', 'benutzer', 'id', 'RESTRICT'),
        ]));
        $db->ddl($d->index('bestellungen', ['nummer'], true));
        $db->ddl($d->index('bestellungen', ['kaeufer_id']));
        $db->ddl($d->index('bestellungen', ['verkaeufer_id']));
        $db->ddl($d->index('bestellungen', ['zustand']));

        // --- Positionen --------------------------------------------------
        $db->ddl($d->tabelle('bestellpositionen', [
            $d->id(),
            $d->fremdschluessel('bestellung_id'),
            $d->fremdschluessel('angebot_id', true),
            $d->text('bezeichnung', 190),
            $d->ganzzahl('menge', false, 1),
            $d->betrag('einkaufspreis_cent'),
            $d->betrag('verkaufspreis_cent'),
            $d->betrag('provision_cent'),
            $d->betrag('ust_cent'),
            $d->ganzzahl('ust_satz'),        // Hundertstel Prozent, 1900 = 19,00 %
            $d->text('ust_land', 2),
            $d->angelegtAm(),
            $d->fremdschluesselBedingung('bestellung_id', 'bestellungen'),
            $d->fremdschluesselBedingung('angebot_id', 'angebote', 'id', 'SET NULL'),
        ]));
        $db->ddl($d->index('bestellpositionen', ['bestellung_id']));

        // --- Spezifikationen ---------------------------------------------
        // Der Rechtsnachweis. Eine Warenbestellung ohne mindestens eine
        // Spezifikation darf technisch nicht entstehen koennen — siehe
        // Bestellung::anlegen(). Zeitstempel ist Pflicht, weil er den
        // Nachweis traegt.
        $db->ddl($d->tabelle('bestellung_spezifikationen', [
            $d->id(),
            $d->fremdschluessel('position_id'),
            $d->fremdschluessel('option_id', true),
            $d->text('schluessel', 80),
            $d->text('bezeichnung', 190),
            $d->langtext('wert'),
            $d->betrag('aufpreis_cent'),
            $d->zeitpunkt('festgelegt_am', false),
            $d->fremdschluesselBedingung('position_id', 'bestellpositionen'),
            $d->fremdschluesselBedingung('option_id', 'angebot_optionen', 'id', 'SET NULL'),
        ]));
        $db->ddl($d->index('bestellung_spezifikationen', ['position_id']));

        // --- Protokoll ---------------------------------------------------
        // Jeder Zustandswechsel schreibt hier hin. Kein stiller Wechsel.
        $db->ddl($d->tabelle('bestellung_ereignisse', [
            $d->id(),
            $d->fremdschluessel('bestellung_id'),
            $d->schluesselwort('von_zustand', 40),
            $d->schluesselwort('nach_zustand', 40),
            $d->text('ausgeloest_von', 40),   // kaeufer | verkaeufer | system | moderation
            $d->fremdschluessel('benutzer_id', true),
            $d->langtext('anmerkung'),
            $d->angelegtAm(),
            $d->fremdschluesselBedingung('bestellung_id', 'bestellungen'),
            $d->fremdschluesselBedingung('benutzer_id', 'benutzer', 'id', 'SET NULL'),
        ]));
        $db->ddl($d->index('bestellung_ereignisse', ['bestellung_id']));

        // --- Treuhandbindung ---------------------------------------------
        $db->ddl($d->tabelle('treuhand_bindungen', [
            $d->id(),
            $d->fremdschluessel('bestellung_id'),
            $d->betrag('betrag_cent'),
            $d->text('waehrung', 3),
            $d->schluesselwort('status'),      // gebunden | freigegeben | erstattet
            $d->zeitpunkt('gebunden_am', false),
            $d->zeitpunkt('faellig_am'),
            $d->zeitpunkt('aufgeloest_am'),
            $d->fremdschluesselBedingung('bestellung_id', 'bestellungen'),
        ]));
        $db->ddl($d->index('treuhand_bindungen', ['bestellung_id']));
        $db->ddl($d->index('treuhand_bindungen', ['status', 'faellig_am']));

        // --- Versand -----------------------------------------------------
        // Safe-Ship: Der Creator erhaelt einen Einlieferungscode, NICHT das
        // fertige Label. Wer das Label druckt, saehe die Kaeuferadresse — der
        // urspruengliche Entwurf anonymisierte damit nur eine Richtung.
        $db->ddl($d->tabelle('versendungen', [
            $d->id(),
            $d->fremdschluessel('bestellung_id'),
            $d->text('dienstleister', 40),
            $d->text('einlieferungscode', 190, true),
            $d->text('sendungsnummer', 120, true),
            $d->schluesselwort('status'),       // vorbereitet | eingeliefert | zugestellt | problem
            $d->zeitpunkt('eingeliefert_am'),
            $d->zeitpunkt('zugestellt_am'),
            $d->angelegtAm(),
            $d->fremdschluesselBedingung('bestellung_id', 'bestellungen'),
        ]));
        $db->ddl($d->index('versendungen', ['bestellung_id']));
    }
};
