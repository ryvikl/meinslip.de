<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Order;

/**
 * Zerlegt einen Bruttopreis in die drei Teile, die das Kommissionsmodell
 * verlangt: Umsatzsteuer, Provision und Auszahlungsbetrag an die Verkaeuferin.
 *
 * Warum brutto zerlegt und nicht netto aufgeschlagen wird: Der Kaeufer sieht
 * einen Endpreis, und die Preisangabenverordnung verlangt genau das. Alles
 * andere ergibt sich daraus.
 *
 * Die GmbH ist nach dem Kommissionsmodell selbst Verkaeuferin. Sie schuldet
 * Umsatzsteuer auf den vollen Kaeuferpreis, nicht auf ihre Provision — so vom
 * EuGH in C-695/20 gegen OnlyFans entschieden. Deshalb wird die Umsatzsteuer
 * zuerst abgezogen und die Provision erst vom Nettobetrag berechnet.
 *
 * Alle Betraege sind ganzzahlige Cent. Saetze sind Hundertstel Prozent:
 * 1900 = 19,00 Prozent. Ganzzahlen ueberall, damit sich keine Rundungsfehler
 * einschleichen und die Summe immer exakt aufgeht.
 */
final class Preisrechner
{
    /**
     * @param int $provisionssatz Hundertstel Prozent, z. B. 1500 = 15,00 %
     */
    public function __construct(private readonly int $provisionssatz)
    {
        if ($provisionssatz < 0 || $provisionssatz > 10000) {
            throw new \InvalidArgumentException('Provisionssatz muss zwischen 0 und 10000 liegen.');
        }
    }

    /**
     * @param int $bruttoCent Was der Kaeufer zahlt
     * @param int $ustSatz Hundertstel Prozent, z. B. 1900
     */
    public function zerlegen(int $bruttoCent, int $ustSatz): Preisaufteilung
    {
        if ($bruttoCent < 0) {
            throw new \InvalidArgumentException('Bruttobetrag darf nicht negativ sein.');
        }
        if ($ustSatz < 0 || $ustSatz > 10000) {
            throw new \InvalidArgumentException('Umsatzsteuersatz muss zwischen 0 und 10000 liegen.');
        }

        // Umsatzsteuer aus dem Bruttobetrag herausrechnen:
        //   ust = brutto * satz / (10000 + satz)
        // Kaufmaennisch gerundet.
        $ustCent = (int) round(($bruttoCent * $ustSatz) / (10000 + $ustSatz));

        $nettoCent = $bruttoCent - $ustCent;

        $provisionCent = (int) round(($nettoCent * $this->provisionssatz) / 10000);

        // Der Rest geht an die Verkaeuferin. Bewusst als Differenz berechnet
        // und nicht separat gerundet — so ergibt die Summe der drei Teile
        // immer exakt den Bruttobetrag, ohne verlorene Cent.
        $einkaufCent = $nettoCent - $provisionCent;

        return new Preisaufteilung(
            bruttoCent: $bruttoCent,
            ustCent: $ustCent,
            ustSatz: $ustSatz,
            provisionCent: $provisionCent,
            einkaufCent: $einkaufCent,
        );
    }

    public function provisionssatz(): int
    {
        return $this->provisionssatz;
    }
}
