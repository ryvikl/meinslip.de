<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Domain\Order\Bestellzustand;
use PHPUnit\Framework\TestCase;

final class BestellzustandTest extends TestCase
{
    /**
     * Die zentrale Zusicherung des Zustandsautomaten: Es gibt keinen Weg von
     * einer bestaetigten Uebergabe oder Zustellung direkt zur Freigabe.
     * Dazwischen liegt immer das Einspruchsfenster.
     */
    public function testKeineFreigabeOhneEinspruchsfenster(): void
    {
        foreach ([Bestellzustand::Uebergeben, Bestellzustand::Zugestellt, Bestellzustand::Versendet] as $zustand) {
            self::assertNotContains(
                Bestellzustand::Freigegeben,
                $zustand->erlaubteFolgen(),
                "Aus '{$zustand->value}' darf nicht direkt freigegeben werden."
            );
        }

        self::assertContains(Bestellzustand::Freigegeben, Bestellzustand::Einspruchsfenster->erlaubteFolgen());
        self::assertContains(Bestellzustand::Freigegeben, Bestellzustand::Streitfall->erlaubteFolgen());
    }

    public function testEndzustaendeHabenKeineFolgen(): void
    {
        foreach ([Bestellzustand::Freigegeben, Bestellzustand::Erstattet, Bestellzustand::Abgebrochen] as $zustand) {
            self::assertTrue($zustand->istEndzustand());
            self::assertSame([], $zustand->erlaubteFolgen());
        }
    }

    public function testJederNichtEndzustandIstErreichbar(): void
    {
        $erreichbar = [Bestellzustand::Entwurf];

        foreach (Bestellzustand::cases() as $zustand) {
            foreach ($zustand->erlaubteFolgen() as $folge) {
                $erreichbar[] = $folge;
            }
        }

        foreach (Bestellzustand::cases() as $zustand) {
            self::assertContains(
                $zustand,
                $erreichbar,
                "Zustand '{$zustand->value}' ist von nirgends aus erreichbar — toter Zustand."
            );
        }
    }

    public function testTreuhandWirdInAllenZwischenzustaendenGehalten(): void
    {
        // In jedem Zustand zwischen Bindung und Aufloesung muss Geld gebunden sein.
        self::assertTrue(Bestellzustand::TreuhandGebunden->haeltTreuhand());
        self::assertTrue(Bestellzustand::Versendet->haeltTreuhand());
        self::assertTrue(Bestellzustand::Uebergeben->haeltTreuhand());
        self::assertTrue(Bestellzustand::Einspruchsfenster->haeltTreuhand());
        self::assertTrue(Bestellzustand::Streitfall->haeltTreuhand());

        // In den Endzustaenden ist die Treuhand aufgeloest.
        self::assertFalse(Bestellzustand::Freigegeben->haeltTreuhand());
        self::assertFalse(Bestellzustand::Erstattet->haeltTreuhand());
        self::assertFalse(Bestellzustand::Entwurf->haeltTreuhand());
    }

    public function testEinspruchNurSolangeDieWareUnterwegsOderNeuIst(): void
    {
        self::assertTrue(Bestellzustand::Versendet->erlaubtEinspruch());
        self::assertTrue(Bestellzustand::Uebergeben->erlaubtEinspruch());
        self::assertTrue(Bestellzustand::Einspruchsfenster->erlaubtEinspruch());

        self::assertFalse(Bestellzustand::Freigegeben->erlaubtEinspruch());
        self::assertFalse(Bestellzustand::Erstattet->erlaubtEinspruch());
        self::assertFalse(Bestellzustand::TreuhandGebunden->erlaubtEinspruch());
    }

    public function testUnerlaubteWechselWerdenErkannt(): void
    {
        self::assertFalse(Bestellzustand::Entwurf->darfWechselnZu(Bestellzustand::Freigegeben));
        self::assertFalse(Bestellzustand::TreuhandGebunden->darfWechselnZu(Bestellzustand::Versendet));
        self::assertFalse(Bestellzustand::Freigegeben->darfWechselnZu(Bestellzustand::Erstattet));

        self::assertTrue(Bestellzustand::Entwurf->darfWechselnZu(Bestellzustand::ZahlungOffen));
        self::assertTrue(Bestellzustand::Einspruchsfenster->darfWechselnZu(Bestellzustand::Freigegeben));
    }
}
