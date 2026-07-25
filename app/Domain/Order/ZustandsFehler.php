<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Order;

final class ZustandsFehler extends \RuntimeException
{
    public static function unerlaubterWechsel(Bestellzustand $von, Bestellzustand $nach): self
    {
        return new self(sprintf(
            'Wechsel von "%s" nach "%s" ist nicht erlaubt. Moegliche Ziele: %s',
            $von->value,
            $nach->value,
            implode(', ', array_map(static fn (Bestellzustand $z): string => $z->value, $von->erlaubteFolgen())) ?: 'keine'
        ));
    }
}
