<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Order;

/**
 * Wird geworfen, wenn eine Bestellung angelegt werden soll, obwohl der
 * Bestellvorgang verriegelt ist.
 *
 * Eigene Ausnahme nach dem Vorbild von
 * MeinSlip\Domain\Ledger\AufsichtsrechtGesperrt, damit die Sperre in keinem
 * allgemeinen catch untergeht und in Protokollen sofort als das erkennbar
 * ist, was sie ist: keine Stoerung, sondern eine absichtliche Verriegelung.
 *
 * Bewusst KEIN BestellFehler: Wer BestellFehler faengt, behandelt die
 * Abweisung EINER Bestellung, die grundsaetzlich moeglich waere. Hier ist
 * dagegen der ganze Vorgang zu. Das sind zwei verschiedene Aussagen, und
 * eine Oberflaeche, die sie zusammenwirft, meldet dem Kaeufer einen
 * Eingabefehler, wo eine Rechtsfrage offen ist.
 */
final class BestellvorgangGesperrt extends \RuntimeException
{
}
