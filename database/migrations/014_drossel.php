<?php

declare(strict_types=1);

use MeinSlip\Core\Database;
use MeinSlip\Core\Ddl;

/**
 * Versuchszaehler fuer Anmeldung, Registrierung, Kontoloeschung und anonyme
 * Meldungen (app/Domain/Trust/Drossel.php).
 *
 * Bis hierher gab es an keiner dieser Stellen eine Grenze: Ein Skript konnte
 * Passwoerter unbegrenzt durchprobieren, Konten in beliebiger Zahl anlegen und
 * die Arbeitsliste der Moderation fluten. Der Takt des Meldewegs hing allein
 * an einem Cookie, das der Absender einfach weglaesst.
 *
 * GESPEICHERT WIRD NUR EIN HASH. 'schluessel' ist SHA-256 ueber Zweck und
 * Merkmal (E-Mail-Adresse oder IP-Adresse) mit dem Anwendungsschluessel als
 * Geheimnis. Aus der Tabelle laesst sich weder eine Adresse noch eine IP
 * zuruecklesen, und sie verraet auch nicht, ob es ein Konto zu einer Adresse
 * gibt — gezaehlt wird jeder Versuch, auch der gegen eine unbekannte.
 *
 * Kein Fremdschluessel und keine Kontokennung: Die Zeilen leben hoechstens so
 * lange wie das laengste Fenster und werden von bin/pflege und von der Drossel
 * selbst entfernt.
 */
return new class {
    public function bezeichnung(): string
    {
        return 'Drossel: Versuchszaehler fuer Anmeldung, Registrierung und Meldungen';
    }

    public function hoch(Database $db, Ddl $d): void
    {
        $db->ddl($d->tabelle('versuche', [
            $d->id(),
            $d->text('schluessel', 64),
            $d->zeitpunkt('angelegt_am', false),
        ]));

        $name = $d->indexName('versuche', ['schluessel', 'angelegt_am']);

        // Migrationen laufen ohne Transaktionsklammer; bricht eine ab, startet
        // sie von vorn. MySQL kennt kein CREATE INDEX IF NOT EXISTS.
        if (!$d->indexVorhanden('versuche', $name)) {
            $db->ddl($d->index('versuche', ['schluessel', 'angelegt_am']));
        }
    }
};
