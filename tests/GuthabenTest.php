<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Core\Env;
use MeinSlip\Domain\Ledger\AufsichtsrechtGesperrt;
use MeinSlip\Domain\Ledger\BuchungsFehler;
use MeinSlip\Domain\Ledger\Guthaben;

final class GuthabenTest extends Testfall
{
    private Guthaben $guthaben;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guthaben = new Guthaben($this->hauptbuch);
        Env::setzen('ZAHLUNG_GUTHABEN_AKTIV', 'false');
    }

    /**
     * Die wichtigste Zusicherung dieser Klasse: Ohne dokumentierte
     * aufsichtsrechtliche Freigabe laesst sich kein Guthaben aufladen.
     */
    public function testAufladenIstOhneFreigabeGesperrt(): void
    {
        $benutzer = $this->benutzer('Gesperrt');

        $this->expectException(AufsichtsrechtGesperrt::class);
        $this->expectExceptionMessageMatches('/KWG/');

        $this->guthaben->aufladen($benutzer, 5000, 'zahlung-1');
    }

    public function testUmbuchenIstOhneFreigabeGesperrt(): void
    {
        $benutzer = $this->benutzer('Gesperrt2');

        $this->expectException(AufsichtsrechtGesperrt::class);

        $this->guthaben->einnahmenUmbuchen($benutzer, 1000, true);
    }

    public function testAufladenFunktioniertNachFreigabe(): void
    {
        Env::setzen('ZAHLUNG_GUTHABEN_AKTIV', 'true');
        Env::setzen('GUTHABEN_OBERGRENZE_CENT', '50000');

        $benutzer = $this->benutzer('Frei');
        $this->guthaben->aufladen($benutzer, 5000, 'zahlung-2');

        self::assertSame(5000, $this->hauptbuch->guthaben($benutzer));
        $this->assertHauptbuchAusgeglichen();
    }

    public function testDieselbeZahlungsreferenzLaedtNurEinmalAuf(): void
    {
        Env::setzen('ZAHLUNG_GUTHABEN_AKTIV', 'true');
        Env::setzen('GUTHABEN_OBERGRENZE_CENT', '50000');

        $benutzer = $this->benutzer('Doppelt');
        $this->guthaben->aufladen($benutzer, 2500, 'zahlung-3');
        $this->guthaben->aufladen($benutzer, 2500, 'zahlung-3');

        self::assertSame(2500, $this->hauptbuch->guthaben($benutzer));
        $this->assertHauptbuchAusgeglichen();
    }

    public function testObergrenzeWirdDurchgesetzt(): void
    {
        Env::setzen('ZAHLUNG_GUTHABEN_AKTIV', 'true');
        Env::setzen('GUTHABEN_OBERGRENZE_CENT', '10000');

        $benutzer = $this->benutzer('Grenze');
        $this->guthaben->aufladen($benutzer, 9000, 'zahlung-4');

        $this->expectException(BuchungsFehler::class);
        $this->expectExceptionMessageMatches('/Obergrenze/');

        $this->guthaben->aufladen($benutzer, 2000, 'zahlung-5');
    }

    /**
     * Einnahmen wieder ausgeben zu duerfen ist ein Geldwaeschepfad ueber zwei
     * abgestimmte Konten — deshalb nur nach vollstaendiger Identitaetspruefung.
     */
    public function testUmbuchenOhneIdentitaetspruefungWirdAbgewiesen(): void
    {
        Env::setzen('ZAHLUNG_GUTHABEN_AKTIV', 'true');

        $benutzer = $this->benutzer('Ungeprueft');

        $this->expectException(BuchungsFehler::class);
        $this->expectExceptionMessageMatches('/Identitaetspruefung/');

        $this->guthaben->einnahmenUmbuchen($benutzer, 1000, false);
    }

    public function testUmbuchenVerschiebtZwischenDenBeidenToepfen(): void
    {
        Env::setzen('ZAHLUNG_GUTHABEN_AKTIV', 'true');
        Env::setzen('EINNAHMEN_UMBUCHUNG_OBERGRENZE_CENT', '20000');

        $verkaeufer = $this->benutzer('Verdienerin');

        // Einnahmen entstehen lassen.
        $this->hauptbuch->buchen('test_einnahme', [
            ['konto_id' => $this->hauptbuch->plattformkonto(\MeinSlip\Domain\Ledger\Hauptbuch::KONTO_TREUHAND), 'betrag_cent' => -8000],
            ['konto_id' => $this->hauptbuch->benutzerkonto($verkaeufer, \MeinSlip\Domain\Ledger\Hauptbuch::KONTO_EINNAHMEN), 'betrag_cent' => 8000],
        ]);

        $this->guthaben->einnahmenUmbuchen($verkaeufer, 3000, true);

        self::assertSame(5000, $this->hauptbuch->einnahmen($verkaeufer));
        self::assertSame(3000, $this->hauptbuch->guthaben($verkaeufer));
        $this->assertHauptbuchAusgeglichen();
    }
}
