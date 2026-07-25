<?php

declare(strict_types=1);

use MeinSlip\Core\Database;
use MeinSlip\Core\Ddl;

/**
 * Konten, Faehigkeiten, Identitaeten und Pruefungen.
 *
 * Grundsatz: EINE Registrierung, alle Rollen. Es gibt keine getrennten
 * Kaeufer- und Verkaeuferkonten. Wer sich registriert, kann kaufen; Verkaufen
 * ist eine zusaetzliche Faehigkeit, die durch die Identitaetspruefung
 * freigeschaltet wird — dieselbe Person, dasselbe Konto.
 */
return new class {
    public function bezeichnung(): string
    {
        return 'Konten und Faehigkeiten';
    }

    public function hoch(Database $db, Ddl $d): void
    {
        // --- Konten ------------------------------------------------------
        // Das Pseudonym ist das, was andere sehen. Der Klarname steht in
        // benutzer_identitaeten und wird nie oeffentlich.
        $db->ddl($d->tabelle('benutzer', [
            $d->id(),
            $d->text('pseudonym', 60),
            $d->text('email', 190),
            $d->text('passwort_hash', 255),
            $d->schluesselwort('status'),          // aktiv | gesperrt | geloescht
            $d->text('sprache', 10),               // de-DE
            $d->text('land', 2),                   // DE
            $d->text('homescreen_name', 40, true), // waehlbarer, diskreter App-Name
            $d->jaNein('push_vorschau', false),    // Vorschau standardmaessig AUS
            $d->angelegtAm(),
            $d->zeitpunkt('zuletzt_aktiv_am'),
        ]));
        $db->ddl($d->index('benutzer', ['email'], true));
        $db->ddl($d->index('benutzer', ['pseudonym'], true));

        // --- Faehigkeiten ------------------------------------------------
        // Rolle ist kein Kontotyp, sondern eine freigeschaltete Berechtigung.
        $db->ddl($d->tabelle('benutzer_faehigkeiten', [
            $d->id(),
            $d->fremdschluessel('benutzer_id'),
            $d->schluesselwort('faehigkeit'),   // kaufen | verkaufen | uebergabe
            $d->schluesselwort('grundlage'),    // altersnachweis | identitaetsnachweis | manuell
            $d->angelegtAm('freigeschaltet_am'),
            $d->zeitpunkt('entzogen_am'),
            $d->fremdschluesselBedingung('benutzer_id', 'benutzer'),
        ]));
        $db->ddl($d->index('benutzer_faehigkeiten', ['benutzer_id', 'faehigkeit'], true));

        // --- Klardaten ---------------------------------------------------
        // Getrennt von der Kontotabelle, weil hier die meldepflichtigen Daten
        // liegen (PStTG/DAC7). Pseudonym nach aussen, Klardaten intern.
        // Ausweisdokumente werden NICHT gespeichert — nur das Pruefergebnis.
        $db->ddl($d->tabelle('benutzer_identitaeten', [
            $d->id(),
            $d->fremdschluessel('benutzer_id'),
            $d->text('vorname', 100, true),
            $d->text('nachname', 100, true),
            $d->text('strasse', 190, true),
            $d->text('plz', 20, true),
            $d->text('ort', 120, true),
            $d->text('land', 2, true),
            $d->zeitpunkt('geburtsdatum'),
            $d->text('steuernummer', 60, true),
            $d->schluesselwort('umsatzsteuer_status'), // kleinunternehmer | regelbesteuert | unbekannt
            $d->angelegtAm(),
            $d->zeitpunkt('geaendert_am'),
            $d->fremdschluesselBedingung('benutzer_id', 'benutzer'),
        ]));
        $db->ddl($d->index('benutzer_identitaeten', ['benutzer_id'], true));

        // --- Pruefungen --------------------------------------------------
        // Hier steht ausschliesslich das ERGEBNIS einer Pruefung, nie das
        // Ausweisbild. Wer die Daten nicht hat, kann sie nicht verlieren.
        $db->ddl($d->tabelle('pruefungen', [
            $d->id(),
            $d->fremdschluessel('benutzer_id'),
            $d->schluesselwort('art'),        // altersidentifizierung | verkaeuferidentitaet | lebendnachweis
            $d->text('anbieter', 60),         // z. B. finapi_giroident, eid, video_ident
            $d->text('anbieter_referenz', 190, true),
            $d->schluesselwort('status'),     // offen | bestanden | abgelehnt | abgelaufen
            $d->jaNein('volljaehrig'),
            $d->zeitpunkt('geprueft_am'),
            $d->zeitpunkt('gueltig_bis'),
            $d->angelegtAm(),
            $d->fremdschluesselBedingung('benutzer_id', 'benutzer'),
        ]));
        $db->ddl($d->index('pruefungen', ['benutzer_id', 'art']));

        // --- Sitzungen ---------------------------------------------------
        // adult_gate_bestanden_am setzt § 4 Abs. 2 JMStV um: die KJM verlangt
        // eine Authentifizierung bei JEDEM Nutzungsvorgang, der nicht
        // unmittelbar auf die Identifizierung folgt. Angemeldet sein genuegt
        // ausdruecklich nicht.
        $db->ddl($d->tabelle('sitzungen', [
            $d->id(),
            $d->text('kennung', 128),
            $d->fremdschluessel('benutzer_id'),
            $d->text('geraet_fingerabdruck', 128, true),
            $d->text('ip_hash', 128, true),
            $d->zeitpunkt('adult_gate_bestanden_am'),
            $d->angelegtAm(),
            $d->zeitpunkt('laeuft_ab_am', false),
            $d->fremdschluesselBedingung('benutzer_id', 'benutzer'),
        ]));
        $db->ddl($d->index('sitzungen', ['kennung'], true));
        $db->ddl($d->index('sitzungen', ['benutzer_id']));
    }
};
