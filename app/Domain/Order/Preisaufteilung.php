<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Order;

/**
 * Ergebnis der Preiszerlegung. Unveraenderlich.
 *
 * Es gilt immer: einkaufCent + provisionCent + ustCent === bruttoCent.
 * Diese Zusicherung wird im Konstruktor geprueft, damit ein Rundungsfehler
 * sofort auffaellt und nicht erst in der Buchhaltung.
 */
final class Preisaufteilung
{
    public function __construct(
        public readonly int $bruttoCent,
        public readonly int $ustCent,
        public readonly int $ustSatz,
        public readonly int $provisionCent,
        public readonly int $einkaufCent,
    ) {
        $summe = $einkaufCent + $provisionCent + $ustCent;
        if ($summe !== $bruttoCent) {
            throw new \LogicException(sprintf(
                'Preisaufteilung geht nicht auf: %d + %d + %d = %d, erwartet %d.',
                $einkaufCent,
                $provisionCent,
                $ustCent,
                $summe,
                $bruttoCent
            ));
        }
    }

    public function nettoCent(): int
    {
        return $this->bruttoCent - $this->ustCent;
    }

    /** @return array<string,int> */
    public function alsFeld(): array
    {
        return [
            'brutto_cent' => $this->bruttoCent,
            'ust_cent' => $this->ustCent,
            'ust_satz' => $this->ustSatz,
            'provision_cent' => $this->provisionCent,
            'einkauf_cent' => $this->einkaufCent,
        ];
    }
}
