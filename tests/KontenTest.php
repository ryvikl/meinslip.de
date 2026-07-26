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
     * Kern des Kontenmodells: Ein frisches Konto bekommt GENAU 'verkaufen'
     * und sonst nichts. Registrieren genuegt zum Einstellen; entzogen wird die
     * Faehigkeit erst auf Meldung hin (Art. 16 DSA).
     *
     * Nichts wird stillschweigend mitvergeben: 'kaufen' setzt die
     * Altersverifikation voraus (§ 4 Abs. 2 JMStV), 'verwalten' kommt
     * ausschliesslich ueber bin/verwalter. Beides bleibt hier aus.
     */
    public function testNeuesKontoHatGenauDieVerkaufsfaehigkeit(): void
    {
        $id = $this->konten->registrieren('Nora', 'nora@beispiel.test', 'ein-langes-passwort');

        self::assertSame([Konten::FAEHIGKEIT_VERKAUFEN], $this->konten->faehigkeiten($id));
        self::assertTrue($this->konten->hatFaehigkeit($id, Konten::FAEHIGKEIT_VERKAUFEN));
        self::assertFalse($this->konten->hatFaehigkeit($id, Konten::FAEHIGKEIT_KAUFEN));
        self::assertFalse($this->konten->hatFaehigkeit($id, Konten::FAEHIGKEIT_VERWALTEN));
    }

    /**
     * Die Grundlage muss ablesbar bleiben: nur so laesst sich spaeter trennen,
     * wer die Faehigkeit durch blosses Registrieren hat und wer durch eine
     * echte Pruefung.
     */
    public function testVerkaufsfaehigkeitTraegtDieGrundlageRegistrierung(): void
    {
        $id = $this->konten->registrieren('Nadja', 'nadja@beispiel.test', 'ein-langes-passwort');

        $zeile = $this->db->eine(
            'SELECT * FROM benutzer_faehigkeiten WHERE benutzer_id = :b AND faehigkeit = :f',
            ['b' => $id, 'f' => Konten::FAEHIGKEIT_VERKAUFEN]
        );

        self::assertNotNull($zeile);
        self::assertSame(Konten::GRUNDLAGE_REGISTRIERUNG, $zeile['grundlage']);
        self::assertNull($zeile['entzogen_am']);
    }

    /**
     * Konto und Faehigkeit gehoeren in EINE Transaktion.
     *
     * Ohne die Klammer entstuende bei einem Abbruch ein Konto, das nicht
     * verkaufen darf, ohne dass es jemand entschieden haette — und niemand
     * merkt es, weil das Konto sich anmelden kann und erst beim Einstellen
     * abgewiesen wird.
     *
     * Der Abbruch wird erzwungen, indem die Faehigkeitstabelle vorher entfernt
     * wird: das ist der einzige Weg, den zweiten Schritt scheitern zu lassen,
     * nachdem der erste bereits geschrieben hat. Danach darf die Kontotabelle
     * keine Zeile mehr enthalten.
     */
    public function testAbbruchHinterlaesstKeinKontoOhneFaehigkeit(): void
    {
        $this->db->ddl('DROP TABLE benutzer_faehigkeiten');

        try {
            $this->konten->registrieren('Nelly', 'nelly@beispiel.test', 'ein-langes-passwort');
            self::fail('Die Registrierung haette scheitern muessen.');
        } catch (\Throwable) {
            // Erwartet — entscheidend ist allein, was danach in der Datenbank steht.
        }

        self::assertSame(
            0,
            (int) $this->db->wert('SELECT COUNT(*) FROM benutzer WHERE email = :e', ['e' => 'nelly@beispiel.test']),
            'Das Konto haette mit zurueckgerollt werden muessen.'
        );
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

        // Gezaehlt wird jetzt je Faehigkeit statt insgesamt: seit der
        // Registrierung traegt jedes Konto bereits 'verkaufen'.
        self::assertSame(
            1,
            (int) $this->db->wert(
                'SELECT COUNT(*) FROM benutzer_faehigkeiten WHERE benutzer_id = :b AND faehigkeit = :f',
                ['b' => $id, 'f' => Konten::FAEHIGKEIT_KAUFEN]
            )
        );
        self::assertCount(2, $this->konten->faehigkeiten($id));
    }

    /**
     * Ein Tippfehler darf nicht still eine wirkungslose Zeile anlegen.
     * Ohne diese Pruefung nimmt faehigkeitFreischalten() jede Zeichenkette an,
     * und niemand bemerkt, dass die gemeinte Berechtigung nie ankam.
     */
    public function testUnbekannteFaehigkeitWirdAbgelehnt(): void
    {
        $id = $this->konten->registrieren('Quinta', 'quinta@beispiel.test', 'ein-langes-passwort');

        try {
            $this->konten->faehigkeitFreischalten($id, 'verwaltn', 'kommandozeile');
            self::fail('Es haette ein KontoFehler geworfen werden muessen.');
        } catch (KontoFehler $fehler) {
            self::assertSame('faehigkeit_unbekannt', $fehler->schluessel());
        }

        // Und es darf auch keine Zeile zurueckgeblieben sein. Die eine Zeile,
        // die zulaessig da ist, stammt aus der Registrierung.
        self::assertSame([Konten::FAEHIGKEIT_VERKAUFEN], $this->konten->faehigkeiten($id));
        self::assertSame(
            0,
            (int) $this->db->wert(
                'SELECT COUNT(*) FROM benutzer_faehigkeiten WHERE benutzer_id = :b AND faehigkeit = :f',
                ['b' => $id, 'f' => 'verwaltn']
            )
        );
        self::assertSame(
            1,
            (int) $this->db->wert('SELECT COUNT(*) FROM benutzer_faehigkeiten WHERE benutzer_id = :b', ['b' => $id])
        );
    }

    public function testUnbekannteFaehigkeitKannAuchNichtEntzogenWerden(): void
    {
        $id = $this->konten->registrieren('Rosa', 'rosa@beispiel.test', 'ein-langes-passwort');

        $this->expectException(KontoFehler::class);
        $this->expectExceptionMessage('faehigkeit_unbekannt');

        $this->konten->faehigkeitEntziehen($id, 'verwaltn');
    }

    public function testBekannteFaehigkeitenSindVollstaendig(): void
    {
        self::assertSame(
            [Konten::FAEHIGKEIT_KAUFEN, Konten::FAEHIGKEIT_VERKAUFEN, Konten::FAEHIGKEIT_VERWALTEN],
            Konten::bekannteFaehigkeiten()
        );
        self::assertTrue(Konten::faehigkeitBekannt(Konten::FAEHIGKEIT_VERWALTEN));
        self::assertFalse(Konten::faehigkeitBekannt('verwaltn'));
    }

    /**
     * Der Verwaltungsbereich haengt allein an dieser Faehigkeit. Sie darf auf
     * keinem Weg beilaeufig entstehen — weder bei der Registrierung noch als
     * Nebenwirkung anderer Freischaltungen. Vergeben wird sie ausschliesslich
     * ueber bin/verwalter, also von jemandem mit Serverzugriff.
     */
    public function testVerwaltenWirdNiemalsAutomatischVergeben(): void
    {
        $id = $this->konten->registrieren('Selma', 'selma@beispiel.test', 'ein-langes-passwort');

        self::assertFalse($this->konten->hatFaehigkeit($id, Konten::FAEHIGKEIT_VERWALTEN));

        $this->konten->faehigkeitFreischalten($id, Konten::FAEHIGKEIT_KAUFEN, 'altersnachweis');
        $this->konten->faehigkeitFreischalten($id, Konten::FAEHIGKEIT_VERKAUFEN, 'identitaetsnachweis');

        self::assertFalse($this->konten->hatFaehigkeit($id, Konten::FAEHIGKEIT_VERWALTEN));
        self::assertNotContains(Konten::FAEHIGKEIT_VERWALTEN, $this->konten->faehigkeiten($id));
        self::assertSame([], $this->konten->mitFaehigkeit(Konten::FAEHIGKEIT_VERWALTEN));
    }

    public function testEntziehenWirktUndIstIdempotent(): void
    {
        $id = $this->konten->registrieren('Thea', 'thea@beispiel.test', 'ein-langes-passwort');

        $this->konten->faehigkeitFreischalten($id, Konten::FAEHIGKEIT_VERWALTEN, 'kommandozeile');
        self::assertTrue($this->konten->hatFaehigkeit($id, Konten::FAEHIGKEIT_VERWALTEN));

        $this->konten->faehigkeitEntziehen($id, Konten::FAEHIGKEIT_VERWALTEN);
        $this->konten->faehigkeitEntziehen($id, Konten::FAEHIGKEIT_VERWALTEN);

        self::assertFalse($this->konten->hatFaehigkeit($id, Konten::FAEHIGKEIT_VERWALTEN));
        // Uebrig bleibt allein die Faehigkeit aus der Registrierung.
        self::assertSame([Konten::FAEHIGKEIT_VERKAUFEN], $this->konten->faehigkeiten($id));
        self::assertSame([], $this->konten->mitFaehigkeit(Konten::FAEHIGKEIT_VERWALTEN));
    }

    /** Entzug loescht nicht, sondern stempelt — sonst fehlt die Spur. */
    public function testEntziehenLoeschtDieZeileNicht(): void
    {
        $id = $this->konten->registrieren('Ulla', 'ulla@beispiel.test', 'ein-langes-passwort');

        $this->konten->faehigkeitFreischalten($id, Konten::FAEHIGKEIT_KAUFEN, 'altersnachweis');
        $this->konten->faehigkeitEntziehen($id, Konten::FAEHIGKEIT_KAUFEN);

        $zeile = $this->db->eine(
            'SELECT * FROM benutzer_faehigkeiten WHERE benutzer_id = :b AND faehigkeit = :f',
            ['b' => $id, 'f' => Konten::FAEHIGKEIT_KAUFEN]
        );

        self::assertNotNull($zeile, 'Die Zeile muss als Nachweis stehen bleiben.');
        self::assertNotNull($zeile['entzogen_am']);
    }

    public function testEntziehenBeruehrtAndereFaehigkeitenNicht(): void
    {
        $id = $this->konten->registrieren('Vroni', 'vroni@beispiel.test', 'ein-langes-passwort');

        $this->konten->faehigkeitFreischalten($id, Konten::FAEHIGKEIT_KAUFEN, 'altersnachweis');
        $this->konten->faehigkeitFreischalten($id, Konten::FAEHIGKEIT_VERKAUFEN, 'identitaetsnachweis');

        $this->konten->faehigkeitEntziehen($id, Konten::FAEHIGKEIT_VERKAUFEN);

        self::assertSame([Konten::FAEHIGKEIT_KAUFEN], $this->konten->faehigkeiten($id));
    }

    /**
     * Der eindeutige Index ueber (benutzer_id, faehigkeit) verbietet eine
     * zweite Zeile. Wiederfreischalten muss die entzogene Zeile wiederbeleben,
     * sonst scheitert jede zweite Ernennung am Index.
     */
    public function testNachEntzugKannWiederFreigeschaltetWerden(): void
    {
        $id = $this->konten->registrieren('Wanda', 'wanda@beispiel.test', 'ein-langes-passwort');

        $this->konten->faehigkeitFreischalten($id, Konten::FAEHIGKEIT_VERWALTEN, 'erste-ernennung');
        $this->konten->faehigkeitEntziehen($id, Konten::FAEHIGKEIT_VERWALTEN);
        $this->konten->faehigkeitFreischalten($id, Konten::FAEHIGKEIT_VERWALTEN, 'zweite-ernennung');

        self::assertTrue($this->konten->hatFaehigkeit($id, Konten::FAEHIGKEIT_VERWALTEN));
        self::assertContains(Konten::FAEHIGKEIT_VERWALTEN, $this->konten->faehigkeiten($id));
        self::assertCount(1, $this->konten->mitFaehigkeit(Konten::FAEHIGKEIT_VERWALTEN));

        // Weiterhin genau eine Zeile, jetzt mit der neuen Grundlage.
        $zeilen = $this->db->alle(
            'SELECT * FROM benutzer_faehigkeiten WHERE benutzer_id = :b AND faehigkeit = :f',
            ['b' => $id, 'f' => Konten::FAEHIGKEIT_VERWALTEN]
        );

        self::assertCount(1, $zeilen);
        self::assertSame('zweite-ernennung', $zeilen[0]['grundlage']);
        self::assertNull($zeilen[0]['entzogen_am']);
    }

    public function testMitFaehigkeitFindetDieVerwaltung(): void
    {
        $eine = $this->konten->registrieren('Xandra', 'xandra@beispiel.test', 'ein-langes-passwort');
        $andere = $this->konten->registrieren('Yvonne', 'yvonne@beispiel.test', 'ein-langes-passwort');

        $this->konten->faehigkeitFreischalten($eine, Konten::FAEHIGKEIT_VERWALTEN, 'kommandozeile');
        $this->konten->faehigkeitFreischalten($andere, Konten::FAEHIGKEIT_KAUFEN, 'altersnachweis');

        $verwaltung = $this->konten->mitFaehigkeit(Konten::FAEHIGKEIT_VERWALTEN);

        self::assertCount(1, $verwaltung);
        self::assertSame('Xandra', $verwaltung[0]['pseudonym']);
        self::assertSame('xandra@beispiel.test', $verwaltung[0]['email']);
    }

    /** bin/verwalter kennt nur die Adresse, nicht die Kontonummer. */
    public function testKontoWirdUeberDieAdresseGefunden(): void
    {
        $id = $this->konten->registrieren('Zelda', 'zelda@beispiel.test', 'ein-langes-passwort');

        $konto = $this->konten->nachEmail('  ZELDA@beispiel.test ');

        self::assertNotNull($konto);
        self::assertSame($id, (int) $konto['id']);
        self::assertNull($this->konten->nachEmail('gibtesnicht@beispiel.test'));
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
