<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Catalog;

/**
 * Fehler des Katalogs.
 *
 * Zweigeteilt, weil zwei Leser bedient werden muessen: Der Schluessel ist
 * stabil und uebersetzbar — die Oberflaeche baut daraus 'angebot.fehler.' .
 * $fehler->schluessel() und zeigt NIE getMessage(). Die Meldung darf
 * zusaetzlich Kontext tragen (welcher Status, welches Feld), damit ein
 * Protokolleintrag ohne Nachschlagen verstaendlich ist.
 *
 * KontoFehler loest dasselbe Problem mit Meldung == Schluessel. Hier reicht
 * das nicht: Ein unerlaubter Statuswechsel ist ohne die beiden beteiligten
 * Status nicht diagnostizierbar, und beide in den Schluessel zu ziehen wuerde
 * die Zahl der Uebersetzungen mit dem Quadrat der Statuswerte aufblaehen.
 */
final class AngebotFehler extends \RuntimeException
{
    public function __construct(private readonly string $schluessel, string $meldung = '')
    {
        parent::__construct($meldung === '' ? $schluessel : $meldung);
    }

    /** Der uebersetzbare Grund, z. B. 'keine_verkaufsfaehigkeit'. */
    public function schluessel(): string
    {
        return $this->schluessel;
    }

    public static function unerlaubterWechsel(string $von, string $nach): self
    {
        return new self('statuswechsel_unzulaessig', sprintf(
            'Statuswechsel von "%s" nach "%s" ist nicht erlaubt.',
            $von,
            $nach
        ));
    }
}
