<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Domain\Ledger\Hauptbuch;
use MeinSlip\Domain\Order\BestellFehler;
use MeinSlip\Domain\Order\Bestellzustand;
use MeinSlip\Domain\Order\ZustandsFehler;

final class BestellungenTest extends Testfall
{
    public function testVollstaendigerAblaufVomKaufBisZurFreigabe(): void
    {
        $kaeufer = $this->benutzer('Kaeuferin');
        $verkaeufer = $this->benutzer('Verkaeuferin');
        $this->aufladen($kaeufer, 10000);

        $bestellungen = $this->bestellungen(1500);

        $id = $bestellungen->anlegen($kaeufer, $verkaeufer, [$this->position(5950)]);

        // Nach dem Anlegen liegt das Geld in der Treuhand, nicht beim Verkaeufer.
        self::assertSame(10000 - 5950, $this->hauptbuch->guthaben($kaeufer));
        self::assertSame(0, $this->hauptbuch->einnahmen($verkaeufer));
        self::assertSame(
            5950,
            $this->hauptbuch->saldo($this->hauptbuch->plattformkonto(Hauptbuch::KONTO_TREUHAND))
        );

        $bestellungen->annehmen($id, $verkaeufer);
        $bestellungen->versenden($id, 'dhl', 'EINLIEFERUNG-ABC123');
        $bestellungen->zustellen($id);

        // Auch nach der Zustellung ist das Geld noch gebunden — erst das
        // Einspruchsfenster muss ablaufen.
        self::assertSame(0, $this->hauptbuch->einnahmen($verkaeufer));
        self::assertSame(
            Bestellzustand::Einspruchsfenster->value,
            $bestellungen->laden($id)['zustand']
        );

        $bestellungen->freigeben($id);

        // 5950 brutto bei 19 % Umsatzsteuer: 950 Steuer, 5000 netto.
        // 15 % Provision auf 5000 = 750. Bleiben 4250 fuer die Verkaeuferin.
        self::assertSame(4250, $this->hauptbuch->einnahmen($verkaeufer));
        self::assertSame(750, $this->hauptbuch->saldo($this->hauptbuch->plattformkonto(Hauptbuch::KONTO_PROVISION)));
        self::assertSame(950, $this->hauptbuch->saldo($this->hauptbuch->plattformkonto(Hauptbuch::KONTO_UMSATZSTEUER)));
        self::assertSame(0, $this->hauptbuch->saldo($this->hauptbuch->plattformkonto(Hauptbuch::KONTO_TREUHAND)));

        $this->assertHauptbuchAusgeglichen();
    }

    /**
     * Der Kern des Widerrufsausschlusses: Ohne Kundenspezifikation darf gar
     * keine Bestellung entstehen (§ 312g Abs. 2 Nr. 1 BGB).
     */
    public function testBestellungOhneSpezifikationWirdAbgewiesen(): void
    {
        $kaeufer = $this->benutzer('Kaeufer2');
        $verkaeufer = $this->benutzer('Verkaeufer2');
        $this->aufladen($kaeufer, 10000);

        $this->expectException(BestellFehler::class);
        $this->expectExceptionMessageMatches('/keine Spezifikation/');

        $this->bestellungen()->anlegen($kaeufer, $verkaeufer, [[
            'angebot_id' => null,
            'bezeichnung' => 'Ohne Spezifikation',
            'brutto_cent' => 1000,
            'spezifikationen' => [],
        ]]);
    }

    public function testGeschaeftMitSichSelbstWirdAbgewiesen(): void
    {
        $benutzer = $this->benutzer('Selbst');
        $this->aufladen($benutzer, 10000);

        $this->expectException(BestellFehler::class);
        $this->expectExceptionMessageMatches('/mit sich selbst/');

        $this->bestellungen()->anlegen($benutzer, $benutzer, [$this->position(1000)]);
    }

    public function testVerbundeneKontenWerdenAngehalten(): void
    {
        $kaeufer = $this->benutzer('Kaeufer3');
        $verkaeufer = $this->benutzer('Verkaeufer3');
        $this->aufladen($kaeufer, 10000);

        // Beide Konten melden sich vom selben Geraet.
        $geraet = hash('sha256', 'geraet-4711');
        foreach ([$kaeufer, $verkaeufer] as $id) {
            $this->db->einfuegen('konto_signale', [
                'benutzer_id' => $id,
                'art' => 'geraet',
                'wert_hash' => $geraet,
                'haeufigkeit' => 1,
                'zuerst_am' => gmdate('Y-m-d H:i:s'),
                'zuletzt_am' => gmdate('Y-m-d H:i:s'),
            ]);
        }

        $this->expectException(BestellFehler::class);
        $this->expectExceptionMessageMatches('/teilen sich Geraet/');

        $this->bestellungen()->anlegen($kaeufer, $verkaeufer, [$this->position(1000)]);
    }

    public function testBestellungOhneAusreichendesGuthabenWirdAbgewiesen(): void
    {
        $kaeufer = $this->benutzer('Knapp');
        $verkaeufer = $this->benutzer('VerkaeuferKnapp');
        $this->aufladen($kaeufer, 500);

        $this->expectException(BestellFehler::class);
        $this->expectExceptionMessageMatches('/Guthaben reicht nicht/');

        $this->bestellungen()->anlegen($kaeufer, $verkaeufer, [$this->position(1000)]);
    }

    public function testEinspruchFuehrtInDenStreitfallUndVerhindertAutomatischeFreigabe(): void
    {
        $kaeufer = $this->benutzer('Kaeufer4');
        $verkaeufer = $this->benutzer('Verkaeufer4');
        $this->aufladen($kaeufer, 10000);

        $bestellungen = $this->bestellungen();
        $id = $bestellungen->anlegen($kaeufer, $verkaeufer, [$this->position(2380)]);
        $bestellungen->annehmen($id, $verkaeufer);
        $bestellungen->versenden($id, 'dhl', 'CODE');
        $bestellungen->zustellen($id);

        $bestellungen->einspruchErheben($id, $kaeufer, 'Ware entspricht nicht der Beschreibung');

        self::assertSame(Bestellzustand::Streitfall->value, $bestellungen->laden($id)['zustand']);

        // Der Cronjob darf einen Streitfall nicht automatisch freigeben.
        $freigegeben = $bestellungen->faelligeFreigeben(gmdate('Y-m-d H:i:s', time() + 999999));
        self::assertSame([], $freigegeben);
        self::assertSame(0, $this->hauptbuch->einnahmen($verkaeufer));

        // Die Moderation entscheidet zugunsten des Kaeufers.
        $bestellungen->erstatten($id, 'moderation', 'Streitfall zugunsten des Kaeufers');

        self::assertSame(10000, $this->hauptbuch->guthaben($kaeufer));
        self::assertSame(0, $this->hauptbuch->einnahmen($verkaeufer));
        $this->assertHauptbuchAusgeglichen();
    }

    public function testFaelligeBestellungenWerdenFreigegeben(): void
    {
        $kaeufer = $this->benutzer('Kaeufer5');
        $verkaeufer = $this->benutzer('Verkaeufer5');
        $this->aufladen($kaeufer, 10000);

        $bestellungen = $this->bestellungen();
        $id = $bestellungen->anlegen($kaeufer, $verkaeufer, [$this->position(1190)]);
        $bestellungen->annehmen($id, $verkaeufer);
        $bestellungen->versenden($id, 'dhl', 'CODE');
        $bestellungen->zustellen($id);

        // Noch nicht faellig.
        self::assertSame([], $bestellungen->faelligeFreigeben());
        self::assertSame(0, $this->hauptbuch->einnahmen($verkaeufer));

        // Nach Ablauf des Einspruchsfensters.
        $spaeter = gmdate('Y-m-d H:i:s', time() + 73 * 3600);
        self::assertSame([$id], $bestellungen->faelligeFreigeben($spaeter));
        self::assertGreaterThan(0, $this->hauptbuch->einnahmen($verkaeufer));
        $this->assertHauptbuchAusgeglichen();
    }

    public function testAbgelaufeneAnnahmefristErstattetVollstaendig(): void
    {
        $kaeufer = $this->benutzer('Kaeufer6');
        $verkaeufer = $this->benutzer('Verkaeufer6');
        $this->aufladen($kaeufer, 10000);

        $bestellungen = $this->bestellungen();
        $id = $bestellungen->anlegen($kaeufer, $verkaeufer, [$this->position(4000)]);

        self::assertSame(6000, $this->hauptbuch->guthaben($kaeufer));

        $spaeter = gmdate('Y-m-d H:i:s', time() + 49 * 3600);
        self::assertSame([$id], $bestellungen->abgelaufeneErstatten($spaeter));

        self::assertSame(10000, $this->hauptbuch->guthaben($kaeufer), 'Der Kaeufer muss alles zurueckbekommen.');
        self::assertSame(0, $this->hauptbuch->einnahmen($verkaeufer));
        $this->assertHauptbuchAusgeglichen();
    }

    /**
     * Die persoenliche Uebergabe darf kein Geld bewegen. Sie setzt nur den
     * Zeitanker, ab dem das Einspruchsfenster laeuft — das ist der Kernfehler,
     * den der urspruengliche Entwurf hatte.
     */
    public function testUebergabeBewegtKeinGeld(): void
    {
        $kaeufer = $this->benutzer('Kaeufer7');
        $verkaeufer = $this->benutzer('Verkaeufer7');
        $this->aufladen($kaeufer, 50000);

        $bestellungen = $this->bestellungen();
        $id = $bestellungen->anlegen($kaeufer, $verkaeufer, [$this->position(30000)], 'uebergabe');
        $bestellungen->annehmen($id, $verkaeufer);
        $bestellungen->uebergabePlanen($id, '2026-08-01 18:00', 'Hauptbahnhof, Haupthalle');
        $bestellungen->uebergabeBestaetigen($id);

        self::assertSame(
            0,
            $this->hauptbuch->einnahmen($verkaeufer),
            'Nach der Uebergabe darf noch kein Geld beim Verkaeufer sein.'
        );
        self::assertSame(
            Bestellzustand::Einspruchsfenster->value,
            $bestellungen->laden($id)['zustand']
        );
        $this->assertHauptbuchAusgeglichen();
    }

    public function testUnerlaubterZustandswechselWirdAbgewiesen(): void
    {
        $kaeufer = $this->benutzer('Kaeufer8');
        $verkaeufer = $this->benutzer('Verkaeufer8');
        $this->aufladen($kaeufer, 10000);

        $bestellungen = $this->bestellungen();
        $id = $bestellungen->anlegen($kaeufer, $verkaeufer, [$this->position(1000)]);

        // Versenden ohne vorherige Annahme.
        $this->expectException(ZustandsFehler::class);
        $bestellungen->versenden($id, 'dhl', 'CODE');
    }

    public function testFreigabeIstIdempotent(): void
    {
        $kaeufer = $this->benutzer('Kaeufer9');
        $verkaeufer = $this->benutzer('Verkaeufer9');
        $this->aufladen($kaeufer, 10000);

        $bestellungen = $this->bestellungen();
        $id = $bestellungen->anlegen($kaeufer, $verkaeufer, [$this->position(1190)]);
        $bestellungen->annehmen($id, $verkaeufer);
        $bestellungen->versenden($id, 'dhl', 'CODE');
        $bestellungen->zustellen($id);
        $bestellungen->freigeben($id);

        $einnahmenNachErsterFreigabe = $this->hauptbuch->einnahmen($verkaeufer);

        // Ein zweiter Aufruf muss am Zustandsautomaten scheitern, nicht
        // doppelt buchen.
        try {
            $bestellungen->freigeben($id);
        } catch (BestellFehler | ZustandsFehler) {
            // erwartet
        }

        self::assertSame($einnahmenNachErsterFreigabe, $this->hauptbuch->einnahmen($verkaeufer));
        $this->assertHauptbuchAusgeglichen();
    }
}
