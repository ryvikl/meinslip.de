<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Domain\Order\Preisrechner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PreisrechnerTest extends TestCase
{
    public function testZerlegtEinenGlattenBetragKorrekt(): void
    {
        // 5950 brutto bei 19 %: 950 Umsatzsteuer, 5000 netto.
        // 15 % Provision auf 5000 = 750. Rest 4250.
        $aufteilung = (new Preisrechner(1500))->zerlegen(5950, 1900);

        self::assertSame(950, $aufteilung->ustCent);
        self::assertSame(5000, $aufteilung->nettoCent());
        self::assertSame(750, $aufteilung->provisionCent);
        self::assertSame(4250, $aufteilung->einkaufCent);
    }

    /**
     * Die wichtigste Eigenschaft: Es darf nie ein Cent verloren gehen oder
     * entstehen. Deshalb ueber einen breiten Bereich krummer Betraege pruefen.
     */
    #[DataProvider('krummeBetraege')]
    public function testSummeGehtImmerExaktAuf(int $brutto, int $ustSatz, int $provisionssatz): void
    {
        $aufteilung = (new Preisrechner($provisionssatz))->zerlegen($brutto, $ustSatz);

        self::assertSame(
            $brutto,
            $aufteilung->einkaufCent + $aufteilung->provisionCent + $aufteilung->ustCent,
            'Einkauf plus Provision plus Umsatzsteuer muss exakt dem Bruttobetrag entsprechen.'
        );
    }

    /** @return iterable<string, array{int,int,int}> */
    public static function krummeBetraege(): iterable
    {
        foreach ([1, 3, 7, 99, 101, 999, 1234, 4999, 12345, 99999, 1000003] as $brutto) {
            foreach ([1900, 700, 0, 2700] as $ust) {
                foreach ([1500, 2000, 1000, 0, 3333] as $provision) {
                    yield "brutto {$brutto}, ust {$ust}, provision {$provision}" => [$brutto, $ust, $provision];
                }
            }
        }
    }

    public function testOhneUmsatzsteuerBleibtAllesNetto(): void
    {
        $aufteilung = (new Preisrechner(2000))->zerlegen(10000, 0);

        self::assertSame(0, $aufteilung->ustCent);
        self::assertSame(10000, $aufteilung->nettoCent());
        self::assertSame(2000, $aufteilung->provisionCent);
        self::assertSame(8000, $aufteilung->einkaufCent);
    }

    public function testOhneProvisionErhaeltDieVerkaeuferinDenGesamtenNettobetrag(): void
    {
        $aufteilung = (new Preisrechner(0))->zerlegen(1190, 1900);

        self::assertSame(190, $aufteilung->ustCent);
        self::assertSame(0, $aufteilung->provisionCent);
        self::assertSame(1000, $aufteilung->einkaufCent);
    }

    public function testNegativerBetragWirdAbgewiesen(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Preisrechner(1500))->zerlegen(-100, 1900);
    }

    public function testUnsinnigerProvisionssatzWirdAbgewiesen(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Preisrechner(10001);
    }
}
