<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Verification;

/**
 * Fehler der manuellen Identitaetspruefung.
 *
 * Zweigeteilt wie MedienFehler, AngebotFehler und MeldungsFehler: Der
 * Schluessel ist stabil und uebersetzbar — die Oberflaeche baut daraus
 * 'verifizierung.fehler.' . $fehler->schluessel() und zeigt NIE getMessage().
 * Die Meldung darf zusaetzlich Kontext tragen (welche Kennung, welcher
 * Status), damit ein Protokolleintrag ohne Nachschlagen verstaendlich ist.
 *
 * WAS IN EINE MELDUNG DIESER KLASSE NIEMALS GEHOERT: der Code. Er steht auf
 * einem Zettel im Foto und ist 48 Stunden lang der einzige Beweis, dass Foto
 * und Vorgang zusammengehoeren. Eine Fehlermeldung wandert ins Protokoll
 * (error_log), und ein Protokoll wird an Stellen gelesen, an denen der Beleg
 * selbst bewusst nicht liegt.
 */
final class PruefbelegFehler extends \RuntimeException
{
    public function __construct(private readonly string $schluessel, string $meldung = '')
    {
        parent::__construct($meldung === '' ? $schluessel : $meldung);
    }

    /** Der uebersetzbare Grund, z. B. 'code_abgelaufen'. */
    public function schluessel(): string
    {
        return $this->schluessel;
    }
}
