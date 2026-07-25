<?php

declare(strict_types=1);

use MeinSlip\Core\Database;
use MeinSlip\Core\Ddl;

/**
 * Hauptbuch mit doppelter Buchfuehrung.
 *
 * Zentrale Regel: Es gibt keinen gespeicherten Kontostand. Ein Saldo ist immer
 * die Summe der Buchungen. Kein UPDATE bewegt jemals Geld — Bewegungen
 * entstehen ausschliesslich durch neue, unveraenderliche Buchungszeilen.
 *
 * Jeder Vorgang besteht aus mindestens zwei Zeilen, deren Summe null ergibt.
 * Weicht sie ab, ist ein Fehler in der Geldlogik passiert, und das muss laut
 * auffallen statt still zu bleiben (siehe stuendliche Pruefung in docs/08).
 *
 * Zwei Toepfe je Konto, wie in der Projektentscheidung festgelegt:
 *   guthaben  — aufgeladenes Geld zum Ausgeben
 *   einnahmen — Erloese aus Verkaeufen, auszahlbar
 * Ob Einnahmen direkt wieder ausgegeben werden duerfen, ist ein Schalter mit
 * Betragsgrenze: bequem, aber ein Geldwaeschepfad ueber zwei abgestimmte Konten.
 */
return new class {
    public function bezeichnung(): string
    {
        return 'Hauptbuch';
    }

    public function hoch(Database $db, Ddl $d): void
    {
        // --- Konten ------------------------------------------------------
        // benutzer_id ist NULL bei Plattformkonten (Provision, Umsatzsteuer).
        $db->ddl($d->tabelle('hauptbuch_konten', [
            $d->id(),
            $d->fremdschluessel('benutzer_id', true),
            $d->schluesselwort('art'),   // guthaben | einnahmen | treuhand | provision | umsatzsteuer | zahlungseingang | auszahlung
            $d->text('waehrung', 3),
            $d->angelegtAm(),
            $d->fremdschluesselBedingung('benutzer_id', 'benutzer'),
        ]));
        $db->ddl($d->index('hauptbuch_konten', ['benutzer_id', 'art', 'waehrung'], true));

        // --- Vorgaenge ---------------------------------------------------
        $db->ddl($d->tabelle('hauptbuch_vorgaenge', [
            $d->id(),
            $d->schluesselwort('art', 60),      // aufladung | bestellung_treuhand | freigabe | erstattung | auszahlung | ...
            $d->text('bezug_art', 40, true),    // bestellung | auszahlung | ...
            $d->fremdschluessel('bezug_id', true),
            $d->text('beschreibung', 255, true),
            $d->text('idempotenz_schluessel', 128, true),
            $d->angelegtAm(),
        ]));
        $db->ddl($d->index('hauptbuch_vorgaenge', ['bezug_art', 'bezug_id']));
        // Verhindert Doppelbuchungen bei wiederholtem Aufruf desselben Vorgangs.
        $db->ddl($d->index('hauptbuch_vorgaenge', ['idempotenz_schluessel'], true));

        // --- Buchungen ---------------------------------------------------
        // betrag ist vorzeichenbehaftet: negativ = Abgang, positiv = Zugang.
        // Immer ganzzahlige Cent, nie Gleitkomma.
        $db->ddl($d->tabelle('hauptbuch_buchungen', [
            $d->id(),
            $d->fremdschluessel('vorgang_id'),
            $d->fremdschluessel('konto_id'),
            $d->betrag('betrag_cent'),
            $d->text('waehrung', 3),
            $d->angelegtAm(),
            $d->fremdschluesselBedingung('vorgang_id', 'hauptbuch_vorgaenge'),
            $d->fremdschluesselBedingung('konto_id', 'hauptbuch_konten', 'id', 'RESTRICT'),
        ]));
        $db->ddl($d->index('hauptbuch_buchungen', ['konto_id']));
        $db->ddl($d->index('hauptbuch_buchungen', ['vorgang_id']));

        // --- Plattformkonten ---------------------------------------------
        // Werden einmalig angelegt, damit sie im Code nicht erzeugt werden
        // muessen und ihre Kennungen stabil bleiben.
        foreach (['provision', 'umsatzsteuer', 'zahlungseingang', 'treuhand'] as $art) {
            $db->einfuegen('hauptbuch_konten', [
                'benutzer_id' => null,
                'art' => $art,
                'waehrung' => 'EUR',
            ]);
        }
    }
};
