<?php

declare(strict_types=1);

use MeinSlip\Core\Database;
use MeinSlip\Core\Ddl;

/**
 * Verweise auf dem oeffentlichen Profil — die Liste, die das Profil zu einer
 * teilbaren Seite macht.
 *
 * Eigene Tabelle und keine JSON-Spalte auf 'profile': Jeder Verweis wird
 * einzeln angelegt, entfernt und verschoben, und Ddl kann kein ALTER TABLE.
 *
 * ON DELETE CASCADE (Standard): Ein Verweis ohne Konto gehoert niemandem. Die
 * Kontoschliessung loescht die Zeilen ausserdem ausdruecklich, weil sie die
 * Benutzerzeile stehen laesst — siehe Kontoschliessung.
 */
return new class {
    public function bezeichnung(): string
    {
        return 'Profil: Verweise fuer die oeffentliche Seite';
    }

    public function hoch(Database $db, Ddl $d): void
    {
        $db->ddl($d->tabelle('profil_links', [
            $d->id(),
            $d->fremdschluessel('benutzer_id'),
            $d->text('titel', 60),
            $d->text('adresse', 500),
            $d->ganzzahl('reihenfolge', false, 0),
            $d->angelegtAm(),
            $d->fremdschluesselBedingung('benutzer_id', 'benutzer'),
        ]));

        $name = $d->indexName('profil_links', ['benutzer_id', 'reihenfolge']);

        if (!$d->indexVorhanden('profil_links', $name)) {
            $db->ddl($d->index('profil_links', ['benutzer_id', 'reihenfolge']));
        }
    }
};
