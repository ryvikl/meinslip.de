<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Core\Lang;
use MeinSlip\Domain\Account\Konten;
use MeinSlip\Domain\Catalog\Angebote;
use MeinSlip\Domain\Media\Bilder;
use MeinSlip\Domain\Media\Medien;
use MeinSlip\Domain\Media\MedienFehler;
use MeinSlip\Http\MedienRouten;

/**
 * Zuordnung, Eigentum und Sichtbarkeit von Angebotsbildern.
 *
 * WAS DIESE SUITE ABDECKT UND BilderTest NICHT: BilderTest beweist Aussagen
 * ueber Bytes auf der Platte — EXIF ist weg, der Anhang ist weg, die Vorschau
 * traegt keine Information mehr. Hier geht es um die Fragen davor und danach:
 * Wem gehoert das Bild, an welchem Angebot haengt es, und wer bekommt es zu
 * sehen. Beides zusammen ist die Zusicherung; einzeln ist keins von beidem
 * eine.
 *
 * DAZU KOMMT EINE PRUEFUNG, DIE KEIN ANDERER TEST LEISTEN KANN.
 * tests/UebersetzungenTest.php erkennt nur Aufrufe der Form te('a.b'). Die
 * Oberflaeche baut ihre Medienmeldungen aber als te('medien.fehler.' . $x) aus
 * MedienRouten::FEHLER zusammen — ein Schluessel ohne Text faellt dort also
 * nicht auf, sondern erst der Verkaeuferin, deren Upload gerade gescheitert
 * ist und die statt einer Erklaerung '[[medien.fehler.upload_zu_gross]]' liest.
 * Genau in dem Moment.
 */
final class MedienZugriffTest extends Testfall
{
    private string $verzeichnis;

    /**
     * Die erzeugten Quelldateien.
     *
     * Bilder::annehmen() loescht tmp_name bewusst NICHT — in einer echten
     * Anfrage raeumt PHP dort selbst auf. In einer Testsuite tut das niemand,
     * und eine Suite, die bei jedem Lauf ein paar Dutzend Dateien im
     * Temporaerverzeichnis liegen laesst, fuellt es irgendwann.
     *
     * @var list<string>
     */
    private array $quelldateien = [];

    protected function setUp(): void
    {
        parent::setUp();
        Lang::einrichten(dirname(__DIR__) . '/resources/lang', 'de-DE');

        $this->verzeichnis = sys_get_temp_dir() . '/meinslip-medien-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->wegraeumen($this->verzeichnis);

        foreach ($this->quelldateien as $pfad) {
            @unlink($pfad);
        }

        $this->quelldateien = [];

        parent::tearDown();
    }

    // --- Uebersetzungen ----------------------------------------------------

    /**
     * Jeder Fehlerschluessel, der in eine Adresse geraten kann, hat einen Text.
     */
    public function testJederMedienfehlerHatEinenText(): void
    {
        $fehlend = [];

        foreach (MedienRouten::FEHLER as $schluessel) {
            if (str_starts_with(Lang::t('medien.fehler.' . $schluessel), '[[')) {
                $fehlend[] = $schluessel;
            }
        }

        self::assertSame(
            [],
            $fehlend,
            "Diese Fehlerschluessel erschienen als '[[medien.fehler.x]]' auf dem\n"
            . "Bildschirm. Ergaenze sie in resources/lang/de-DE/medien.php:\n"
            . implode("\n", $fehlend)
        );
    }

    public function testJedeMedienrueckmeldungHatEinenText(): void
    {
        $fehlend = [];

        foreach (MedienRouten::ERFOLGE as $schluessel) {
            if (str_starts_with(Lang::t('medien.erfolg.' . $schluessel), '[[')) {
                $fehlend[] = $schluessel;
            }
        }

        self::assertSame([], $fehlend, implode("\n", $fehlend));
    }

    /**
     * Die Weissliste muss jeden Schluessel kennen, den Bilder wirft.
     *
     * Sonst faellt ein neuer Schluessel der Bildpipeline stumm auf 'unbekannt'
     * zurueck, und die Person liest "Bitte versuche es noch einmal", obwohl ihr
     * Bild aus einem benennbaren Grund abgewiesen wurde — etwa weil es
     * animiert ist. Sie versucht es dann tatsaechlich noch einmal.
     */
    public function testDieWeisslisteKenntJedenSchluesselDerBildpipeline(): void
    {
        $quelle = (string) file_get_contents(dirname(__DIR__) . '/app/Domain/Media/Bilder.php');

        self::assertNotSame('', $quelle);
        preg_match_all("/new MedienFehler\('([a-z0-9_]+)'/", $quelle, $treffer);

        $unbekannt = array_values(array_diff(array_unique($treffer[1]), MedienRouten::FEHLER));

        self::assertSame(
            [],
            $unbekannt,
            "Bilder wirft Schluessel, die MedienRouten::FEHLER nicht kennt:\n" . implode("\n", $unbekannt)
        );
    }

    // --- Eigentum ----------------------------------------------------------

    public function testEinBildLandetAmAngebotUndAufDerPlatte(): void
    {
        $this->gdVoraussetzen();

        [$verkaeuferId, $angebotId] = $this->angebotMitVerkaeuferin('Silvia');
        $medien = $this->medien();

        $medienId = $medien->hinzufuegen($angebotId, $verkaeuferId, $this->hochladung(), false);

        $zeilen = $medien->zuAngebot($angebotId);

        self::assertCount(1, $zeilen);
        self::assertSame($medienId, $zeilen[0]['id']);
        self::assertSame(Bilder::ART_BILD, $zeilen[0]['art']);
        self::assertFalse($zeilen[0]['explizit']);

        // Der Pfad in der Datenbank ist relativ — sonst entwertet ein Umzug
        // des Servers jede Zeile auf einmal.
        self::assertStringStartsNotWith('/', $zeilen[0]['pfad']);

        self::assertNotNull($medien->datei($zeilen[0]['pfad']));
        self::assertNotNull($medien->datei($zeilen[0]['vorschau_pfad']));
    }

    public function testFremdeKoennenKeinBildAnhaengen(): void
    {
        $this->gdVoraussetzen();

        [, $angebotId] = $this->angebotMitVerkaeuferin('Silvia');
        $fremde = $this->benutzer('Mallory');

        $this->expectException(MedienFehler::class);

        try {
            $this->medien()->hinzufuegen($angebotId, $fremde, $this->hochladung(), false);
        } catch (MedienFehler $fehler) {
            // Derselbe Schluessel wie bei einer Kennung, die es gar nicht gibt.
            // Zwei verschiedene Antworten machten die fortlaufende
            // Angebotskennung durchzaehlbar.
            self::assertSame('angebot_unbekannt', $fehler->schluessel());

            throw $fehler;
        }
    }

    public function testFremdeKoennenKeinBildEntfernen(): void
    {
        $this->gdVoraussetzen();

        [$verkaeuferId, $angebotId] = $this->angebotMitVerkaeuferin('Silvia');
        $medien = $this->medien();
        $medienId = $medien->hinzufuegen($angebotId, $verkaeuferId, $this->hochladung(), false);

        $fremde = $this->benutzer('Mallory');

        try {
            $medien->entfernen($medienId, $fremde);
            self::fail('Eine fremde Person konnte ein Bild entfernen.');
        } catch (MedienFehler $fehler) {
            self::assertSame('medium_unbekannt', $fehler->schluessel());
        }

        self::assertCount(1, $medien->zuAngebot($angebotId));
    }

    public function testEntfernenNimmtZeileUndDateienMit(): void
    {
        $this->gdVoraussetzen();

        [$verkaeuferId, $angebotId] = $this->angebotMitVerkaeuferin('Silvia');
        $medien = $this->medien();
        $medienId = $medien->hinzufuegen($angebotId, $verkaeuferId, $this->hochladung(), false);

        $zeile = $medien->zuAngebot($angebotId)[0];
        $bild = (string) $medien->datei($zeile['pfad']);
        $vorschau = (string) $medien->datei($zeile['vorschau_pfad']);

        $medien->entfernen($medienId, $verkaeuferId);

        self::assertSame([], $medien->zuAngebot($angebotId));
        self::assertFileDoesNotExist($bild);
        self::assertFileDoesNotExist($vorschau);
    }

    public function testDieObergrenzeGiltJeAngebot(): void
    {
        $this->gdVoraussetzen();

        [$verkaeuferId, $angebotId] = $this->angebotMitVerkaeuferin('Silvia');
        $medien = $this->medien();

        for ($i = 0; $i < Medien::JE_ANGEBOT; ++$i) {
            $medien->hinzufuegen($angebotId, $verkaeuferId, $this->hochladung(), false);
        }

        try {
            $medien->hinzufuegen($angebotId, $verkaeuferId, $this->hochladung(), false);
            self::fail('Die Obergrenze von ' . Medien::JE_ANGEBOT . ' Bildern hat nicht gegriffen.');
        } catch (MedienFehler $fehler) {
            self::assertSame('zu_viele_bilder', $fehler->schluessel());
        }

        self::assertCount(Medien::JE_ANGEBOT, $medien->zuAngebot($angebotId));
    }

    // --- Sichtbarkeit ------------------------------------------------------

    /**
     * Die Kernaussage des Pakets: explizite Bilder gibt es fuer Fremde nicht.
     *
     * Weder scharf noch unscharf, und auch nicht fuer Angemeldete. Eine
     * Selbsterklaerung ist keine geschlossene Benutzergruppe nach § 4 Abs. 2
     * JMStV; solange keine echte Schranke gebunden ist, ist Nichtausliefern die
     * einzige verteidigbare Antwort.
     */
    public function testExpliziteBilderSindFuerFremdeNichtSichtbar(): void
    {
        [$verkaeuferId, $angebotId] = $this->angebotMitVerkaeuferin('Silvia');
        $fremde = $this->benutzer('Neugier');
        $medien = $this->medien();

        self::assertFalse($medien->explizitSichtbar($angebotId, $fremde));

        // Abgemeldet erst recht.
        self::assertFalse($medien->explizitSichtbar($angebotId, 0));

        // Die Hochladende sieht ihr eigenes Bild — sie muss pruefen koennen,
        // was sie eingestellt hat.
        self::assertTrue($medien->explizitSichtbar($angebotId, $verkaeuferId));
    }

    public function testDieVerwaltungSiehtExpliziteBilder(): void
    {
        [, $angebotId] = $this->angebotMitVerkaeuferin('Silvia');

        $verwalterin = $this->benutzer('Chefin');
        (new Konten($this->db))->faehigkeitFreischalten(
            $verwalterin,
            Konten::FAEHIGKEIT_VERWALTEN,
            'test'
        );

        // Ohne diese Ausnahme liesse sich eine Meldung ueber ein Bild nicht
        // bearbeiten, ohne dass der Verwaltung der Gegenstand vorenthalten
        // wird — Art. 16 DSA verlangt eine Pruefung, nicht ein Raten.
        self::assertTrue($this->medien()->explizitSichtbar($angebotId, $verwalterin));
    }

    public function testDieAltersschrankeIstNichtGebunden(): void
    {
        // Solange das false ist, gilt die harte Regel oben. Wer es aendert,
        // muss ein lizenziertes Verfahren angebunden haben — und diesen Test
        // dann bewusst mit umschreiben.
        self::assertFalse(Medien::altersschrankeGebunden());
    }

    public function testDieKatalogkachelBevorzugtDasHarmloseBild(): void
    {
        $this->gdVoraussetzen();

        [$verkaeuferId, $angebotId] = $this->angebotMitVerkaeuferin('Silvia');
        $medien = $this->medien();

        // Zuerst das explizite, danach das harmlose. Waere die Reihenfolge
        // allein massgeblich, zeigte die Kachel ein Schloss, obwohl daneben
        // ein zeigbares Bild liegt.
        $medien->hinzufuegen($angebotId, $verkaeuferId, $this->hochladung(), true);
        $harmlos = $medien->hinzufuegen($angebotId, $verkaeuferId, $this->hochladung(), false);

        $kachel = $medien->ersteVorschau($angebotId);

        self::assertNotNull($kachel);
        self::assertSame($harmlos, $kachel['id']);
        self::assertFalse($kachel['explizit']);
    }

    public function testOhneBilderGibtEsKeineKachel(): void
    {
        [, $angebotId] = $this->angebotMitVerkaeuferin('Silvia');

        self::assertNull($this->medien()->ersteVorschau($angebotId));
    }

    /**
     * Die Katalogseite waehlt fuer jede Kachel dasselbe Bild wie die
     * Einzelabfrage.
     *
     * DAS IST DER GANZE ZWECK DER PRUEFUNG. ersteVorschauZuAngeboten() gibt es
     * nur, damit eine Katalogseite nicht eine Abfrage je Kachel ausloest; sie
     * beantwortet dieselbe Frage ein zweites Mal. Eine zweite Antwort auf
     * dieselbe Frage darf nicht anders ausfallen — sonst zeigt die Kachel im
     * Katalog ein anderes Bild als die Vorschau auf der Angebotsseite, und bei
     * expliziten Bildern waere das kein Schoenheitsfehler.
     */
    public function testDieStapelabfrageWaehltDieselbenKachelnWieDieEinzelne(): void
    {
        $this->gdVoraussetzen();

        $medien = $this->medien();

        // Drei Angebote mit drei verschiedenen Lagen: gemischt, nur explizit,
        // ganz ohne Bild.
        [$einsVerkaeufer, $eins] = $this->angebotMitVerkaeuferin('Silvia');
        $medien->hinzufuegen($eins, $einsVerkaeufer, $this->hochladung(), true);
        $medien->hinzufuegen($eins, $einsVerkaeufer, $this->hochladung(), false);

        [$zweiVerkaeufer, $zwei] = $this->angebotMitVerkaeuferin('Nadja');
        $medien->hinzufuegen($zwei, $zweiVerkaeufer, $this->hochladung(), true);

        [, $drei] = $this->angebotMitVerkaeuferin('Ruth');

        $stapel = $medien->ersteVorschauZuAngeboten([$eins, $zwei, $drei]);

        foreach ([$eins, $zwei] as $angebotId) {
            $einzeln = $medien->ersteVorschau($angebotId);

            self::assertNotNull($einzeln);
            self::assertArrayHasKey($angebotId, $stapel);
            self::assertSame($einzeln, $stapel[$angebotId]);
        }

        // Ein Angebot ohne Bild taucht gar nicht erst auf — die Route setzt
        // daraus null, und die Vorlage zeigt eine Kachel ohne Bild.
        self::assertArrayNotHasKey($drei, $stapel);
    }

    /**
     * Eine leere Liste loest keine Abfrage aus.
     *
     * Ohne diesen Ausstieg entstuende 'WHERE angebot_id IN ()' — auf beiden
     * Treibern ein Syntaxfehler. Eine leere Katalogseite ist aber der
     * Normalfall einer noch unbenutzten Kategorie.
     */
    public function testDieStapelabfrageVertraegtEineLeereListe(): void
    {
        self::assertSame([], $this->medien()->ersteVorschauZuAngeboten([]));
        self::assertSame([], $this->medien()->ersteVorschauZuAngeboten([0, -3]));
    }

    // --- Pfade -------------------------------------------------------------

    /**
     * Eine Zeile, die aus dem Medienverzeichnis herauszeigt, liefert nichts.
     *
     * Der Pfad kommt nie aus der Anfrage, ein '..' kann also gar nicht erst
     * hineingeraten. Diese Pruefung ist der Hosentraeger fuer den Fall, dass
     * eine Zeile von Hand veraendert oder aus einer Sicherung falsch
     * eingespielt wird.
     */
    public function testEinPfadAusserhalbDesVerzeichnissesLiefertNichts(): void
    {
        $medien = $this->medien();

        // Das Verzeichnis muss existieren, sonst scheitert schon realpath()
        // der Wurzel und der Test bewiese nichts.
        mkdir($this->verzeichnis, 0750, true);

        self::assertNull($medien->datei('../../etc/passwd'));
        self::assertNull($medien->datei('/etc/passwd'));
        self::assertNull($medien->datei(''));
        self::assertNull($medien->datei(null));
        self::assertNull($medien->datei('angebot/1/gibtesnicht.jpg'));
    }

    public function testDasMedienverzeichnisLiegtAusserhalbVonPublic(): void
    {
        $pfad = Medien::verzeichnis('/var/www/meinslip');

        self::assertSame('/var/www/meinslip/storage/medien', $pfad);
        self::assertStringNotContainsString('/public/', $pfad . '/');
    }

    // --- Werkzeuge ---------------------------------------------------------

    private function medien(): Medien
    {
        // Die Uploadpruefung wird ersetzt: is_uploaded_file() ist ausserhalb
        // einer echten HTTP-Anfrage immer false, ohne diese Naht kaeme kein
        // Test bis zum ersten INSERT. In app/ kommt sie nirgends vor.
        return new Medien(
            $this->db,
            $this->verzeichnis,
            new Bilder(static fn (string $pfad): bool => is_file($pfad))
        );
    }

    /**
     * Eine verkaufsfaehige Person mit einem veroeffentlichten Angebot.
     *
     * @return array{0:int,1:int} Benutzerkennung, Angebotskennung
     */
    private function angebotMitVerkaeuferin(string $pseudonym): array
    {
        $benutzerId = $this->benutzer($pseudonym);

        (new Konten($this->db))->faehigkeitFreischalten(
            $benutzerId,
            Konten::FAEHIGKEIT_VERKAUFEN,
            'test'
        );

        $kategorieId = (int) $this->db->wert('SELECT id FROM kategorien WHERE aktiv = 1 ORDER BY id LIMIT 1');

        $angebotId = (new Angebote($this->db))->anlegen(
            verkaeuferId: $benutzerId,
            kategorieId: $kategorieId,
            titel: 'Getragene Socken',
            beschreibung: 'Zwei Tage getragen.',
            grundpreisCent: 2500
        );

        return [$benutzerId, $angebotId];
    }

    /**
     * Ein echtes JPEG in der Form, in der $_FILES es liefert.
     *
     * Mit GD erzeugt statt aus einer Datei im Repository geladen: Ein
     * eingechecktes Testbild waere die einzige Binaerdatei des Projekts, und es
     * gaebe keine Zusicherung, dass sie das enthaelt, was ihr Name behauptet.
     *
     * @return array<string,mixed>
     */
    private function hochladung(): array
    {
        $bild = imagecreatetruecolor(60, 40);

        if (!$bild instanceof \GdImage) {
            self::markTestSkipped('GD kann kein Bild erzeugen.');
        }

        $farbe = imagecolorallocate($bild, 120, 90, 200);

        if ($farbe !== false) {
            imagefilledrectangle($bild, 0, 0, 59, 39, $farbe);
        }

        $pfad = sys_get_temp_dir() . '/meinslip-upload-' . bin2hex(random_bytes(6)) . '.jpg';
        imagejpeg($bild, $pfad, 85);
        imagedestroy($bild);

        $this->quelldateien[] = $pfad;

        return [
            'name' => 'foto.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $pfad,
            'error' => UPLOAD_ERR_OK,
            'size' => (int) filesize($pfad),
        ];
    }

    private function gdVoraussetzen(): void
    {
        if (!Bilder::verfuegbar()) {
            self::markTestSkipped('GD oder fileinfo fehlt — die Aufnahmekette ist hier nicht pruefbar.');
        }
    }

    /** Raeumt ein Verzeichnis mitsamt Inhalt weg. */
    private function wegraeumen(string $verzeichnis): void
    {
        if (!is_dir($verzeichnis)) {
            return;
        }

        foreach (scandir($verzeichnis) ?: [] as $eintrag) {
            if ($eintrag === '.' || $eintrag === '..') {
                continue;
            }

            $pfad = $verzeichnis . '/' . $eintrag;

            if (is_dir($pfad)) {
                $this->wegraeumen($pfad);
            } else {
                @unlink($pfad);
            }
        }

        @rmdir($verzeichnis);
    }
}
