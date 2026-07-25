<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Domain\Account\Konten;
use MeinSlip\Domain\Account\KontoFehler;
use MeinSlip\Domain\Account\Sitzungen;

final class KontenTest extends Testfall
{
    private Konten $konten;

    private Sitzungen $sitzungen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->konten = new Konten($this->db);
        $this->sitzungen = new Sitzungen($this->db);
    }

    public function testRegistrierungLegtKontoAn(): void
    {
        $id = $this->konten->registrieren('Lina', 'lina@beispiel.test', 'ein-langes-passwort');

        $konto = $this->konten->laden($id);
        self::assertNotNull($konto);
        self::assertSame('Lina', $konto['pseudonym']);
        self::assertSame('lina@beispiel.test', $konto['email']);
    }

    public function testPasswortWirdNichtImKlartextGespeichert(): void
    {
        $id = $this->konten->registrieren('Mara', 'mara@beispiel.test', 'ein-langes-passwort');
        $konto = $this->konten->laden($id);

        self::assertNotSame('ein-langes-passwort', $konto['passwort_hash']);
        self::assertTrue(password_verify('ein-langes-passwort', (string) $konto['passwort_hash']));
    }

    /**
     * Kern des Kontenmodells: Ein frisches Konto hat noch KEINE Faehigkeiten.
     * Kaufen setzt die Altersverifikation voraus, Verkaufen zusaetzlich die
     * Identitaetspruefung — beides laeuft ueber Verfahren, die noch fehlen.
     */
    public function testNeuesKontoHatKeineFaehigkeiten(): void
    {
        $id = $this->konten->registrieren('Nora', 'nora@beispiel.test', 'ein-langes-passwort');

        self::assertSame([], $this->konten->faehigkeiten($id));
        self::assertFalse($this->konten->hatFaehigkeit($id, Konten::FAEHIGKEIT_KAUFEN));
        self::assertFalse($this->konten->hatFaehigkeit($id, Konten::FAEHIGKEIT_VERKAUFEN));
    }

    public function testFaehigkeitenKommenZumSelbenKontoHinzu(): void
    {
        $id = $this->konten->registrieren('Olga', 'olga@beispiel.test', 'ein-langes-passwort');

        $this->konten->faehigkeitFreischalten($id, Konten::FAEHIGKEIT_KAUFEN, 'altersnachweis');
        $this->konten->faehigkeitFreischalten($id, Konten::FAEHIGKEIT_VERKAUFEN, 'identitaetsnachweis');

        // Dieselbe Person, dasselbe Konto, zwei Berechtigungen.
        self::assertTrue($this->konten->hatFaehigkeit($id, Konten::FAEHIGKEIT_KAUFEN));
        self::assertTrue($this->konten->hatFaehigkeit($id, Konten::FAEHIGKEIT_VERKAUFEN));
        self::assertCount(2, $this->konten->faehigkeiten($id));
    }

    public function testDoppeltesFreischaltenLegtNichtsDoppeltAn(): void
    {
        $id = $this->konten->registrieren('Pia', 'pia@beispiel.test', 'ein-langes-passwort');

        $this->konten->faehigkeitFreischalten($id, Konten::FAEHIGKEIT_KAUFEN, 'altersnachweis');
        $this->konten->faehigkeitFreischalten($id, Konten::FAEHIGKEIT_KAUFEN, 'altersnachweis');

        self::assertCount(1, $this->konten->faehigkeiten($id));
    }

    /** @return iterable<string, array{string,string,string,string}> */
    public static function ungueltigeEingaben(): iterable
    {
        yield 'Pseudonym zu kurz' => ['ab', 'a@b.test', 'ein-langes-passwort', 'pseudonym_ungueltig'];
        yield 'Pseudonym mit Leerzeichen' => ['a b', 'a@b.test', 'ein-langes-passwort', 'pseudonym_ungueltig'];
        yield 'E-Mail ohne At' => ['Gueltig', 'keine-email', 'ein-langes-passwort', 'email_ungueltig'];
        yield 'Passwort zu kurz' => ['Gueltig', 'a@b.test', 'kurz', 'passwort_zu_kurz'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ungueltigeEingaben')]
    public function testUngueltigeEingabenWerdenAbgewiesen(
        string $pseudonym,
        string $email,
        string $passwort,
        string $erwarteterSchluessel
    ): void {
        try {
            $this->konten->registrieren($pseudonym, $email, $passwort);
            self::fail('Es haette ein KontoFehler geworfen werden muessen.');
        } catch (KontoFehler $fehler) {
            self::assertSame($erwarteterSchluessel, $fehler->schluessel());
        }
    }

    public function testEmailWirdNurEinmalVergeben(): void
    {
        $this->konten->registrieren('Erste', 'gleich@beispiel.test', 'ein-langes-passwort');

        $this->expectException(KontoFehler::class);
        $this->expectExceptionMessage('email_vergeben');

        $this->konten->registrieren('Zweite', 'GLEICH@beispiel.test', 'ein-langes-passwort');
    }

    /** Sonst stehen "Lina" und "lina" nebeneinander und sind verwechselbar. */
    public function testPseudonymOhneRuecksichtAufGrossschreibungEindeutig(): void
    {
        $this->konten->registrieren('Lina', 'a@beispiel.test', 'ein-langes-passwort');

        $this->expectException(KontoFehler::class);
        $this->expectExceptionMessage('pseudonym_vergeben');

        $this->konten->registrieren('LINA', 'b@beispiel.test', 'ein-langes-passwort');
    }

    public function testAnmeldungMitRichtigenDaten(): void
    {
        $this->konten->registrieren('Rita', 'rita@beispiel.test', 'ein-langes-passwort');

        $konto = $this->konten->anmelden('rita@beispiel.test', 'ein-langes-passwort');

        self::assertNotNull($konto);
        self::assertSame('Rita', $konto['pseudonym']);
    }

    public function testAnmeldungMitFalschemPasswort(): void
    {
        $this->konten->registrieren('Sara', 'sara@beispiel.test', 'ein-langes-passwort');

        self::assertNull($this->konten->anmelden('sara@beispiel.test', 'falsches-passwort'));
    }

    public function testAnmeldungMitUnbekannterAdresse(): void
    {
        self::assertNull($this->konten->anmelden('gibtesnicht@beispiel.test', 'ein-langes-passwort'));
    }

    // --- Sitzungen -------------------------------------------------------

    public function testSitzungWirdNurAlsHashGespeichert(): void
    {
        $id = $this->konten->registrieren('Tina', 'tina@beispiel.test', 'ein-langes-passwort');
        $kennung = $this->sitzungen->starten($id);

        $gespeichert = $this->db->wert('SELECT kennung FROM sitzungen WHERE benutzer_id = :b', ['b' => $id]);

        self::assertNotSame($kennung, $gespeichert, 'Die Kennung darf nicht im Klartext in der Datenbank stehen.');
        self::assertSame(hash('sha256', $kennung), $gespeichert);
    }

    public function testSitzungLaedtDasKonto(): void
    {
        $id = $this->konten->registrieren('Uta', 'uta@beispiel.test', 'ein-langes-passwort');
        $kennung = $this->sitzungen->starten($id);

        $sitzung = $this->sitzungen->laden($kennung);

        self::assertNotNull($sitzung);
        self::assertSame('Uta', $sitzung['pseudonym']);
        self::assertSame($id, (int) $sitzung['benutzer_id']);
    }

    public function testUnbekannteKennungLaedtNichts(): void
    {
        self::assertNull($this->sitzungen->laden('gibtesnicht'));
        self::assertNull($this->sitzungen->laden(null));
        self::assertNull($this->sitzungen->laden(''));
    }

    public function testAbmeldenBeendetDieSitzung(): void
    {
        $id = $this->konten->registrieren('Vera', 'vera@beispiel.test', 'ein-langes-passwort');
        $kennung = $this->sitzungen->starten($id);

        $this->sitzungen->beenden($kennung);

        self::assertNull($this->sitzungen->laden($kennung));
    }

    /**
     * § 4 Abs. 2 JMStV verlangt eine Authentifizierung bei jedem
     * Nutzungsvorgang. Angemeldet zu sein genuegt ausdruecklich nicht.
     */
    public function testAngemeldetSeinReichtFuerDasZugangsGateNicht(): void
    {
        $id = $this->konten->registrieren('Wilma', 'wilma@beispiel.test', 'ein-langes-passwort');
        $kennung = $this->sitzungen->starten($id);
        $sitzung = $this->sitzungen->laden($kennung);

        self::assertFalse($this->sitzungen->gateGilt($sitzung), 'Frische Sitzung darf das Gate nicht bestanden haben.');

        $this->sitzungen->gateBestanden($kennung);
        $sitzung = $this->sitzungen->laden($kennung);

        self::assertTrue($this->sitzungen->gateGilt($sitzung));
    }

    public function testAbgelaufenesGateGiltNichtMehr(): void
    {
        $id = $this->konten->registrieren('Xenia', 'xenia@beispiel.test', 'ein-langes-passwort');
        $kennung = $this->sitzungen->starten($id);

        $alt = gmdate('Y-m-d H:i:s', time() - (Sitzungen::GATE_GUELTIG_MINUTEN + 5) * 60);
        $this->db->ausfuehren(
            'UPDATE sitzungen SET adult_gate_bestanden_am = :z WHERE kennung = :k',
            ['z' => $alt, 'k' => hash('sha256', $kennung)]
        );

        self::assertFalse($this->sitzungen->gateGilt($this->sitzungen->laden($kennung)));
    }

    public function testAbgelaufeneSitzungenWerdenEntfernt(): void
    {
        $id = $this->konten->registrieren('Yara', 'yara@beispiel.test', 'ein-langes-passwort');
        $kennung = $this->sitzungen->starten($id);

        $this->db->ausfuehren(
            'UPDATE sitzungen SET laeuft_ab_am = :z WHERE kennung = :k',
            ['z' => gmdate('Y-m-d H:i:s', time() - 60), 'k' => hash('sha256', $kennung)]
        );

        self::assertNull($this->sitzungen->laden($kennung), 'Abgelaufene Sitzung darf nicht mehr laden.');
        self::assertSame(1, $this->sitzungen->abgelaufeneEntfernen());
    }
}
