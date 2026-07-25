<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Ledger;

/**
 * Wird geworfen, wenn eine Funktion gesperrt ist, weil ihre
 * aufsichtsrechtliche Zulaessigkeit nicht geklaert ist.
 *
 * Eigene Ausnahme, damit sie in keinem allgemeinen catch untergeht und in
 * Protokollen sofort als das erkennbar ist, was sie ist: keine Stoerung,
 * sondern eine absichtliche Sperre.
 */
final class AufsichtsrechtGesperrt extends \RuntimeException
{
}
