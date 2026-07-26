<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Trust;

/**
 * Fehler des Meldewegs.
 *
 * Zweigeteilt wie AngebotFehler und VerwaltungsFehler: Der Schluessel ist
 * stabil und uebersetzbar — die Oberflaeche baut daraus 'melden.fehler.' .
 * $fehler->schluessel() und zeigt NIE getMessage(). Die Meldung darf
 * zusaetzlich Kontext tragen (welche Gegenstandsart, welche Kennung), damit
 * eine Fehlersuche ohne Nachschlagen auskommt.
 *
 * Warum die Meldung hier nicht selbst der Schluessel sein darf (wie bei
 * KontoFehler): Die Meldungen dieser Klasse nennen fast immer eine konkrete
 * Kennung oder einen konkreten Eingabewert. Der gehoert NICHT in die
 * Oberflaeche — 'gegenstand_unbekannt' mit der Kennung im Text wuerde einer
 * meldenden Person verraten, welche Angebotskennungen es gibt und welche
 * nicht. Deshalb sieht sie nur den Schluessel.
 */
final class MeldungsFehler extends \RuntimeException
{
    public function __construct(private readonly string $schluessel, string $meldung = '')
    {
        parent::__construct($meldung === '' ? $schluessel : $meldung);
    }

    /** Der uebersetzbare Grund, z. B. 'bereits_gemeldet'. */
    public function schluessel(): string
    {
        return $this->schluessel;
    }
}
