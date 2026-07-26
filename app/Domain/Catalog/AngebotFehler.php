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

    /**
     * Der Wechsel war nicht erlaubt — gleich, ob die Uebergangstabelle ihn
     * verbietet oder eine Methode ihren Ausgangsstatus zusaetzlich einengt.
     *
     * Vier Methoden in Angebote steuern 'aktiv' an und pruefen dafuer je eine
     * eigene Vorbedingung: veroeffentlichen() nur aus 'entwurf', fortsetzen()
     * nur aus 'pausiert', freigeben() nur aus 'in_pruefung', entsperren() nur
     * aus 'gesperrt'. Sie alle melden bewusst DIESEN Schluessel und keinen
     * eigenen: Der Fall IST fuer die aufrufende Person ein unerlaubter
     * Wechsel, 'statuswechsel_unzulaessig' ist in der Oberflaeche uebersetzt,
     * und die Zusicherung der Tests — jeder verbotene Uebergang meldet
     * denselben Schluessel — bleibt damit erhalten. Die beteiligten Status
     * stehen in der Meldung, also im Protokoll.
     */
    public static function unerlaubterWechsel(string $von, string $nach): self
    {
        return new self('statuswechsel_unzulaessig', sprintf(
            'Statuswechsel von "%s" nach "%s" ist nicht erlaubt.',
            $von,
            $nach
        ));
    }
}
