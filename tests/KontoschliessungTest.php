<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Domain\Account\KontoFehler;
use MeinSlip\Domain\Account\Konten;
use MeinSlip\Domain\Account\Kontoschliessung;
use MeinSlip\Domain\Account\Sitzungen;
use MeinSlip\Domain\Media\Medien;
use MeinSlip\Domain\Verification\Pruefbelege;

/**
 * Konto loeschen: Was die Datenschutzerklaerung zusagt, muss hier stehen.
 *
 * Geprueft wird beides — dass das Eigene verschwindet UND dass der Verlauf
 * der Gegenseite stehen bleibt. Das zweite ist der Grund, warum die Zeile
 * nicht einfach geloescht wird.
 */
final class KontoschliessungTest extends Testfall
{
    private const PASSWORT = 'ein-langes-passwort';

    private string $ablage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ablage = sys_get_temp_dir() . '/meinslip-schliessung-' . bin2hex(random_bytes(6));
        mkdir($this->ablage . '/medien', 0777, true);
        mkdir($this->ablage . '/belege', 0777, true);
    }

    private function schliessung(): Kontoschliessung
    {
        return new Kontoschliessung(
            $this->db,
            new Medien($this->db, $this->ablage . '/medien'),
            new Pruefbelege($this->db, $this->ablage . '/belege'),
        );
    }

    private function konto(string $pseudonym): int
    {
        return (new Konten($this->db))->registrieren($pseudonym, $pseudonym . '@beispiel.test', self::PASSWORT);
    }

    public function testFalschesPasswortLaesstAllesStehen(): void
    {
        $id = $this->konto('Mira');

        try {
            $this->schliessung()->schliessen($id, 'falsch-falsch-falsch');
            self::fail('Ein falsches Passwort darf kein Konto schliessen.');
        } catch (KontoFehler $fehler) {
            self::assertSame('passwort_falsch', $fehler->schluessel());
        }

        self::assertSame('aktiv', $this->db->wert('SELECT status FROM benutzer WHERE id = :id', ['id' => $id]));
    }

    public function testDasEigeneVerschwindet(): void
    {
        $id = $this->konto('Mira');
        $konten = new Konten($this->db);
        (new Sitzungen($this->db))->starten($id);
        $this->db->einfuegen('profile', [
            'benutzer_id' => $id,
            'anzeigename' => 'Mira',
            'vorstellung' => 'Hallo',
            'chat_deklaration' => null,
            'bild_pfad' => null,
            'bild_vorschau_pfad' => null,
            'oeffentlich' => 1,
            'angelegt_am' => gmdate('Y-m-d H:i:s'),
            'geaendert_am' => null,
        ]);

        $this->schliessung()->schliessen($id, self::PASSWORT);

        $zeile = $this->db->eine('SELECT * FROM benutzer WHERE id = :id', ['id' => $id]);
        self::assertNotNull($zeile);
        self::assertSame('geloescht', $zeile['status']);
        self::assertSame('geloescht-' . $id, $zeile['pseudonym']);
        self::assertStringNotContainsString('beispiel.test', (string) $zeile['email']);

        self::assertNull($konten->anmelden('mira@beispiel.test', self::PASSWORT), 'Die alte Adresse meldet niemanden mehr an.');
        self::assertNull($konten->anmelden((string) $zeile['email'], 'geloescht'));
        self::assertFalse($konten->hatFaehigkeit($id, Konten::FAEHIGKEIT_VERKAUFEN));

        foreach (['profile', 'sitzungen'] as $tabelle) {
            self::assertSame(
                0,
                (int) $this->db->wert('SELECT COUNT(*) FROM ' . $tabelle . ' WHERE benutzer_id = :b', ['b' => $id]),
                $tabelle
            );
        }
    }

    public function testAdresseUndPseudonymWerdenWiederFrei(): void
    {
        $id = $this->konto('Mira');
        $this->schliessung()->schliessen($id, self::PASSWORT);

        self::assertGreaterThan($id, $this->konto('Mira'));
    }

    public function testEinZweitesSchliessenFindetKeinKontoMehr(): void
    {
        $id = $this->konto('Mira');
        $this->schliessung()->schliessen($id, self::PASSWORT);

        $this->expectException(KontoFehler::class);
        $this->expectExceptionMessage('konto_unbekannt');
        $this->schliessung()->schliessen($id, self::PASSWORT);
    }

    public function testVerwaltungszugangSperrtDieSchliessung(): void
    {
        $id = $this->konto('Mira');
        (new Konten($this->db))->faehigkeitFreischalten($id, Konten::FAEHIGKEIT_VERWALTEN, 'manuell');

        $this->expectException(KontoFehler::class);
        $this->expectExceptionMessage('verwalter_bleibt');
        $this->schliessung()->schliessen($id, self::PASSWORT);
    }

    public function testNiemandDarfSichAlsGeloeschtesKontoAusgeben(): void
    {
        $this->expectException(KontoFehler::class);
        $this->expectExceptionMessage('pseudonym_ungueltig');
        $this->konto('Geloescht-7');
    }
}
