<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Ledger;

/**
 * Wird geworfen, wenn eine Buchung die Regeln der doppelten Buchfuehrung
 * verletzt. Bewusst eine eigene Ausnahme: Fehler in der Geldlogik duerfen
 * nirgends versehentlich mit einem allgemeinen catch verschluckt werden.
 */
final class BuchungsFehler extends \RuntimeException
{
}
