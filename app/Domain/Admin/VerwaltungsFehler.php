<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Admin;

/**
 * Fehler des Verwaltungsbereichs.
 *
 * Zweigeteilt wie AngebotFehler: Der Schluessel ist stabil und uebersetzbar —
 * die Oberflaeche baut daraus 'verwaltung.fehler.' . $fehler->schluessel() und
 * zeigt NIE getMessage(). Die Meldung darf zusaetzlich Kontext tragen (welches
 * Konto, welcher Status), damit ein Protokolleintrag ohne Nachschlagen
 * verstaendlich ist.
 *
 * Warum die Meldung nicht selbst der Schluessel ist (wie bei KontoFehler): Die
 * Fehler dieser Klasse betreffen fast immer eine konkrete Kennung. Ohne sie
 * laesst sich hinterher nicht mehr feststellen, welches Konto gemeint war —
 * und genau das muss ein Verwaltungsvorgang belegen koennen.
 */
final class VerwaltungsFehler extends \RuntimeException
{
    public function __construct(private readonly string $schluessel, string $meldung = '')
    {
        parent::__construct($meldung === '' ? $schluessel : $meldung);
    }

    /** Der uebersetzbare Grund, z. B. 'begruendung_fehlt'. */
    public function schluessel(): string
    {
        return $this->schluessel;
    }
}
