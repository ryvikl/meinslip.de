<?php

declare(strict_types=1);

use MeinSlip\Core\Database;
use MeinSlip\Core\Ddl;

/**
 * Bewertungen, Sperren, Meldungen und Missbrauchserkennung.
 *
 * Zwei Entscheidungen aus dem Konzept schlagen hier direkt aufs Schema durch:
 *
 * 1. GETRENNTE REPUTATION JE RICHTUNG. Das Verhalten als Kaeufer darf die
 *    Verkaeufer-Bewertung nicht beeinflussen und umgekehrt. Deshalb traegt
 *    jede Bewertung eine Richtung.
 *
 * 2. VERBUNDENE KONTEN ERKENNEN. Getragene Waesche hat keinen objektiven
 *    Preisanker — 800 Euro sind nicht als auffaellig erkennbar. Ohne
 *    Erkennung verbundener Konten wird die Provision zur Geldwaescheschleife.
 */
return new class {
    public function bezeichnung(): string
    {
        return 'Vertrauen und Missbrauchsabwehr';
    }

    public function hoch(Database $db, Ddl $d): void
    {
        // --- Bewertungen -------------------------------------------------
        $db->ddl($d->tabelle('bewertungen', [
            $d->id(),
            $d->fremdschluessel('bestellung_id'),
            $d->fremdschluessel('bewerter_id'),
            $d->fremdschluessel('bewerteter_id'),
            $d->schluesselwort('richtung'),   // als_kaeufer | als_verkaeufer
            $d->ganzzahl('punkte'),           // 1..5
            $d->langtext('text'),
            $d->jaNein('sichtbar', true),
            $d->angelegtAm(),
            $d->fremdschluesselBedingung('bestellung_id', 'bestellungen'),
            $d->fremdschluesselBedingung('bewerter_id', 'benutzer'),
            $d->fremdschluesselBedingung('bewerteter_id', 'benutzer'),
        ]));
        // Eine Bewertung je Bestellung und Richtung.
        $db->ddl($d->index('bewertungen', ['bestellung_id', 'richtung'], true));
        $db->ddl($d->index('bewertungen', ['bewerteter_id', 'richtung']));

        // --- Sperren -----------------------------------------------------
        $db->ddl($d->tabelle('sperren', [
            $d->id(),
            $d->fremdschluessel('benutzer_id'),
            $d->fremdschluessel('gesperrter_id'),
            $d->langtext('grund'),
            $d->angelegtAm(),
            $d->fremdschluesselBedingung('benutzer_id', 'benutzer'),
            $d->fremdschluesselBedingung('gesperrter_id', 'benutzer'),
        ]));
        $db->ddl($d->index('sperren', ['benutzer_id', 'gesperrter_id'], true));

        // --- Meldungen ---------------------------------------------------
        // Der DSA verlangt ein funktionierendes Melde- und Abhilfeverfahren
        // mit Beschwerdemoeglichkeit gegen die eigene Entscheidung.
        $db->ddl($d->tabelle('meldungen', [
            $d->id(),
            $d->fremdschluessel('melder_id', true),
            $d->text('gegenstand_art', 40),   // angebot | benutzer | nachricht | bestellung
            $d->fremdschluessel('gegenstand_id'),
            $d->schluesselwort('grund', 60),
            $d->langtext('beschreibung'),
            $d->schluesselwort('status'),     // offen | in_pruefung | erledigt | abgelehnt
            $d->zeitpunkt('zugesagt_bis'),
            $d->zeitpunkt('erledigt_am'),
            $d->langtext('entscheidung'),
            $d->angelegtAm(),
            $d->fremdschluesselBedingung('melder_id', 'benutzer', 'id', 'SET NULL'),
        ]));
        $db->ddl($d->index('meldungen', ['status', 'zugesagt_bis']));
        $db->ddl($d->index('meldungen', ['gegenstand_art', 'gegenstand_id']));

        // --- Kontosignale ------------------------------------------------
        // Grundlage der Erkennung verbundener Konten. Es werden ausschliesslich
        // Hashwerte gespeichert, keine Klarwerte.
        $db->ddl($d->tabelle('konto_signale', [
            $d->id(),
            $d->fremdschluessel('benutzer_id'),
            $d->schluesselwort('art'),        // geraet | netz | zahlungsmittel
            $d->text('wert_hash', 128),
            $d->ganzzahl('haeufigkeit', false, 1),
            $d->angelegtAm('zuerst_am'),
            $d->zeitpunkt('zuletzt_am'),
            $d->fremdschluesselBedingung('benutzer_id', 'benutzer'),
        ]));
        $db->ddl($d->index('konto_signale', ['benutzer_id', 'art', 'wert_hash'], true));
        // Der eigentliche Zweck: ueber diesen Index findet man alle Konten,
        // die sich ein Geraet, ein Netz oder ein Zahlungsmittel teilen.
        $db->ddl($d->index('konto_signale', ['art', 'wert_hash']));
    }
};
