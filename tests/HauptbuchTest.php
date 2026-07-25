<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Domain\Ledger\BuchungsFehler;
use MeinSlip\Domain\Ledger\Hauptbuch;

final class HauptbuchTest extends Testfall
{
    public function testAufladungErhoehtDasGuthaben(): void
    {
        $benutzer = $this->benutzer('Anna');
        $this->aufladen($benutzer, 5000);

        self::assertSame(5000, $this->hauptbuch->guthaben($benutzer));
        $this->assertHauptbuchAusgeglichen();
    }

    public function testUnausgeglicheneBuchungWirdAbgewiesen(): void
    {
        $benutzer = $this->benutzer('Bea');
        $konto = $this->hauptbuch->benutzerkonto($benutzer, Hauptbuch::KONTO_GUTHABEN);
        $gegenkonto = $this->hauptbuch->plattformkonto(Hauptbuch::KONTO_ZAHLUNGSEINGANG);

        $this->expectException(BuchungsFehler::class);
        $this->expectExceptionMessageMatches('/nicht ausgeglichen/');

        $this->hauptbuch->buchen('aufladung', [
            ['konto_id' => $konto, 'betrag_cent' => 1000],
            ['konto_id' => $gegenkonto, 'betrag_cent' => -999],
        ]);
    }

    public function testEinzelneBuchungWirdAbgewiesen(): void
    {
        $benutzer = $this->benutzer('Cara');
        $konto = $this->hauptbuch->benutzerkonto($benutzer, Hauptbuch::KONTO_GUTHABEN);

        $this->expectException(BuchungsFehler::class);
        $this->expectExceptionMessageMatches('/mindestens zwei Buchungen/');

        $this->hauptbuch->buchen('aufladung', [
            ['konto_id' => $konto, 'betrag_cent' => 1000],
        ]);
    }

    public function testNullbetragWirdAbgewiesen(): void
    {
        $benutzer = $this->benutzer('Dana');
        $konto = $this->hauptbuch->benutzerkonto($benutzer, Hauptbuch::KONTO_GUTHABEN);
        $gegenkonto = $this->hauptbuch->plattformkonto(Hauptbuch::KONTO_ZAHLUNGSEINGANG);

        $this->expectException(BuchungsFehler::class);

        $this->hauptbuch->buchen('aufladung', [
            ['konto_id' => $konto, 'betrag_cent' => 0],
            ['konto_id' => $gegenkonto, 'betrag_cent' => 0],
        ]);
    }

    public function testIdempotenzVerhindertDoppelbuchung(): void
    {
        $benutzer = $this->benutzer('Emma');
        $konto = $this->hauptbuch->benutzerkonto($benutzer, Hauptbuch::KONTO_GUTHABEN);
        $gegenkonto = $this->hauptbuch->plattformkonto(Hauptbuch::KONTO_ZAHLUNGSEINGANG);

        $buchungen = [
            ['konto_id' => $gegenkonto, 'betrag_cent' => -2500],
            ['konto_id' => $konto, 'betrag_cent' => 2500],
        ];

        $ersterVorgang = $this->hauptbuch->buchen('aufladung', $buchungen, idempotenzSchluessel: 'zahlung-4711');
        $zweiterVorgang = $this->hauptbuch->buchen('aufladung', $buchungen, idempotenzSchluessel: 'zahlung-4711');

        self::assertSame($ersterVorgang, $zweiterVorgang, 'Derselbe Schluessel muss denselben Vorgang liefern.');
        self::assertSame(2500, $this->hauptbuch->guthaben($benutzer), 'Der Betrag darf nur einmal gebucht sein.');
        $this->assertHauptbuchAusgeglichen();
    }

    public function testGuthabenUndEinnahmenSindGetrennteToepfe(): void
    {
        $benutzer = $this->benutzer('Frida');
        $this->aufladen($benutzer, 1000);

        $this->hauptbuch->buchen('test_einnahme', [
            ['konto_id' => $this->hauptbuch->plattformkonto(Hauptbuch::KONTO_TREUHAND), 'betrag_cent' => -700],
            ['konto_id' => $this->hauptbuch->benutzerkonto($benutzer, Hauptbuch::KONTO_EINNAHMEN), 'betrag_cent' => 700],
        ]);

        self::assertSame(1000, $this->hauptbuch->guthaben($benutzer));
        self::assertSame(700, $this->hauptbuch->einnahmen($benutzer));
    }

    public function testPlattformkontoAlsBenutzerkontoWirdAbgewiesen(): void
    {
        $benutzer = $this->benutzer('Gina');

        $this->expectException(BuchungsFehler::class);
        $this->hauptbuch->benutzerkonto($benutzer, Hauptbuch::KONTO_PROVISION);
    }
}
