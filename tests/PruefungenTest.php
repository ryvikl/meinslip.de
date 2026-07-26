<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Core\Database;
use MeinSlip\Core\Lang;
use MeinSlip\Core\Migrator;
use MeinSlip\Domain\Account\Konten;
use MeinSlip\Domain\Account\Sitzungen;
use MeinSlip\Domain\Catalog\Angebote;
use MeinSlip\Domain\Media\Bilder;
use MeinSlip\Domain\Media\Medien;
use MeinSlip\Domain\Verification\Pruefbelege;
use MeinSlip\Domain\Verification\PruefbelegFehler;
use MeinSlip\Http\VerifizierungsRouten;

/**
 * Die manuelle Identitaetspruefung und die Altersschranke.
 *
 * WAS DIESE SUITE BEWEISEN MUSS, UND WARUM GERADE DAS:
 *
 *  1. DIE LOESCHFRIST HAELT. Sie ist die einzige Zusage dieses Pakets, die
 *     sich nicht durch Hinsehen pruefen laesst — ein Beleg, der zu lange
 *     liegt, sieht genauso aus wie einer, der noch darf. Geprueft wird
 *     deshalb die Rechnung selbst (das jeweils Fruehere) und beide
 *     Durchsetzungen: die Fachmethode und bin/pflege.
 *
 *  2. bin/pflege LOESCHT ZEILE UND DATEIEN. Das laeuft als echter
 *     Unterprozess gegen eine echte Datenbankdatei. Ein Test, der nur die
 *     Fachmethode aufruft, bewiese vom Cronjob genau nichts — und der Cronjob
 *     ist die Haelfte der Zusage.
 *
 *  3. EIN ABGELAUFENER CODE WIRD ABGEWIESEN. Ohne Frist ist ein Foto
 *     wiederverwendbar oder kaufbar.
 *
 *  4. EIN ZWEITES FOTO ZUM SELBEN VORGANG WIRD ABGEWIESEN. Wer nachbessern
 *     darf, bis es passt, probiert aus, welches Bild durchkommt.
 *
 *  5. EXPLIZITE MEDIEN BLEIBEN AUCH MIT BESTANDENER SELBSTERKLAERUNG FUER
 *     FREMDE UNERREICHBAR. Das ist der Punkt, an dem dieses Paket
 *     erfahrungsgemaess kippt: Wer eine Altersschranke baut, baut fast
 *     zwangslaeufig auch die Freischaltung dahinter. Genau die darf es nicht
 *     geben, solange kein lizenziertes Verfahren gebunden ist.
 */
final class PruefungenTest extends Testfall
{
    /** Verzeichnis der Belege dieses Testlaufs. */
    private string $verzeichnis;

    /**
     * Die erzeugten Quelldateien.
     *
     * Bilder::annehmen() loescht tmp_name bewusst nicht — in einer echten
     * Anfrage raeumt PHP dort selbst auf, in einer Testsuite niemand.
     *
     * @var list<string>
     */
    private array $quelldateien = [];

    /** Was der bin/pflege-Test im echten Projektbaum angelegt hat. */
    private string $pflegeVerzeichnis = '';

    /** Das Verzeichnis, das bin/pflege NICHT anfassen darf. */
    private string $pflegeKoeder = '';

    private string $pflegeDatenbank = '';

    protected function setUp(): void
    {
        parent::setUp();
        Lang::einrichten(dirname(__DIR__) . '/resources/lang', 'de-DE');

        $this->verzeichnis = sys_get_temp_dir() . '/meinslip-belege-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->wegraeumen($this->verzeichnis);

        if ($this->pflegeVerzeichnis !== '') {
            $this->wegraeumen($this->pflegeVerzeichnis);
        }

        if ($this->pflegeKoeder !== '') {
            $this->wegraeumen($this->pflegeKoeder);
        }

        if ($this->pflegeDatenbank !== '') {
            @unlink($this->pflegeDatenbank);
        }

        foreach ($this->quelldateien as $pfad) {
            @unlink($pfad);
        }

        $this->quelldateien = [];

        parent::tearDown();
    }

    // --- Das Schema ---------------------------------------------------------

    /**
     * Ein Beleg ohne Loeschdatum ist schemaseitig unmoeglich.
     *
     * DIE WICHTIGSTE ZUSICHERUNG DES PAKETS. Sie steht als NOT NULL ohne
     * Vorgabewert in 013_pruefungsbelege.php, und dieser Test ist der Grund,
     * warum niemand sie spaeter "der Bequemlichkeit halber" nullbar macht:
     * Die Datenminimierung nach Art. 5 Abs. 1 lit. c DSGVO waere dann wieder
     * eine Gewohnheit statt einer Zusage.
     */
    public function testEinBelegOhneLoeschdatumLaesstSichNichtAnlegen(): void
    {
        $benutzerId = $this->benutzer('Silvia');

        $this->expectException(\PDOException::class);

        $this->db->einfuegen('pruefungsbelege', [
            'benutzer_id' => $benutzerId,
            'code' => 'ABCD-EFGH',
            'code_ausgegeben_am' => gmdate('Y-m-d H:i:s'),
            'code_gueltig_bis' => gmdate('Y-m-d H:i:s'),
            'status' => Pruefbelege::STATUS_OFFEN,
            'loeschen_ab' => null,
            'angelegt_am' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * In 'pruefungen' steht das Ergebnis und sonst nichts.
     *
     * docs/04-features/verifizierung-altersstufen.md verbietet an Zeile 42 bis
     * 44 ausdruecklich, Ausweisdokumente zu speichern. Die Tabelle hat kein
     * Feld dafuer — und dieser Test verhindert, dass eines dazukommt, weil
     * jemand den Beleg "praktischerweise" gleich an das Ergebnis haengt.
     */
    public function testDieErgebnistabelleTraegtKeinBild(): void
    {
        $spalten = array_map(
            static fn (array $z): string => (string) $z['name'],
            $this->db->alle('PRAGMA table_info(pruefungen)')
        );

        self::assertNotSame([], $spalten, 'Die Tabelle pruefungen fehlt.');

        foreach (['pfad', 'vorschau_pfad', 'bild', 'bild_pfad', 'dokument', 'beleg'] as $verboten) {
            self::assertNotContains(
                $verboten,
                $spalten,
                "In 'pruefungen' darf niemals ein Bild oder Dokument landen — nur das Ergebnis."
            );
        }
    }

    // --- Der Code -----------------------------------------------------------

    public function testDerCodeKommtVonDerPlattformUndGiltAchtundvierzigStunden(): void
    {
        $benutzerId = $this->benutzer('Silvia');
        $jetzt = '2026-07-01 12:00:00';

        $vorgang = $this->belege()->codeVergeben($benutzerId, $jetzt);

        self::assertSame(Pruefbelege::STATUS_OFFEN, $vorgang['status']);
        self::assertSame($jetzt, $vorgang['code_ausgegeben_am']);
        self::assertSame('2026-07-03 12:00:00', $vorgang['code_gueltig_bis']);
        self::assertSame(48, Pruefbelege::CODE_GUELTIG_STUNDEN);

        // 'XXXX-XXXX' aus dem festgelegten Vorrat, nichts sonst.
        self::assertMatchesRegularExpression(
            '/^[' . preg_quote(Pruefbelege::CODE_ZEICHEN, '/') . ']{4}-['
            . preg_quote(Pruefbelege::CODE_ZEICHEN, '/') . ']{4}$/',
            $vorgang['code']
        );
    }

    /**
     * Der Zeichenvorrat enthaelt kein Paar, das sich handschriftlich
     * verwechseln laesst.
     *
     * Der Zettel wird mit der Hand geschrieben und von einem Menschen
     * abgelesen. Ein 'O' neben einer '0' kostet die Person ein zweites Foto —
     * und ein zweites Foto gibt es je Vorgang nicht.
     */
    public function testDerZeichenvorratKenntKeineVerwechselbarenZeichen(): void
    {
        $vorrat = str_split(Pruefbelege::CODE_ZEICHEN);

        self::assertSame($vorrat, array_values(array_unique($vorrat)), 'Doppeltes Zeichen im Vorrat.');

        foreach (['0', 'O', 'Q', '1', 'I', '2', 'Z', '5', 'S', '6', 'G', '7', '8', 'B', 'U', 'V'] as $verboten) {
            self::assertNotContains(
                $verboten,
                $vorrat,
                'Das Zeichen "' . $verboten . '" ist handschriftlich verwechselbar.'
            );
        }
    }

    public function testZweiCodesSindNichtGleich(): void
    {
        $codes = [];

        for ($i = 0; $i < 50; ++$i) {
            $codes[] = Pruefbelege::codeErzeugen();
        }

        self::assertCount(50, array_unique($codes), 'Der Zufallsgenerator wiederholt sich.');
    }

    public function testEinNeuerCodeVerlaengertDieLoeschfristNicht(): void
    {
        $benutzerId = $this->benutzer('Silvia');
        $belege = $this->belege();

        $erst = $belege->codeVergeben($benutzerId, '2026-07-01 12:00:00');
        // Zehn Tage spaeter noch einmal — das darf die Uhr nicht zuruecksetzen.
        $zweit = $belege->codeVergeben($benutzerId, '2026-07-11 12:00:00');

        self::assertSame($erst['id'], $zweit['id'], 'Es darf nur einen laufenden Vorgang geben.');
        self::assertNotSame($erst['code'], $zweit['code']);
        self::assertSame(
            $erst['loeschen_ab'],
            $zweit['loeschen_ab'],
            'Ueber einen Knopf beliebig dehnbar waere keine Frist.'
        );
    }

    // --- Der Beleg ----------------------------------------------------------

    public function testEinAbgelaufenerCodeWirdAbgewiesen(): void
    {
        $this->gdVoraussetzen();

        $benutzerId = $this->benutzer('Silvia');
        $belege = $this->belege();
        $belege->codeVergeben($benutzerId, '2026-07-01 12:00:00');

        try {
            // Eine Stunde nach Ablauf der 48.
            $belege->belegHochladen($benutzerId, $this->hochladung(), '2026-07-03 13:00:00');
            self::fail('Ein abgelaufener Code wurde angenommen.');
        } catch (PruefbelegFehler $fehler) {
            self::assertSame('code_abgelaufen', $fehler->schluessel());
        }

        // Und es liegt nichts auf der Platte: Die Fristpruefung steht VOR der
        // Bildpipeline, damit ein Selfie erst gar nicht geschrieben wird.
        self::assertDirectoryDoesNotExist($this->verzeichnis);
    }

    public function testEinZweitesFotoZumSelbenVorgangWirdAbgewiesen(): void
    {
        $this->gdVoraussetzen();

        $benutzerId = $this->benutzer('Silvia');
        $belege = $this->belege();
        $belege->codeVergeben($benutzerId, '2026-07-01 12:00:00');

        $belegId = $belege->belegHochladen($benutzerId, $this->hochladung(), '2026-07-01 13:00:00');
        $ersteZeile = $belege->beleg($belegId);
        self::assertNotNull($ersteZeile);

        try {
            $belege->belegHochladen($benutzerId, $this->hochladung(), '2026-07-01 14:00:00');
            self::fail('Ein zweites Foto zum selben Vorgang wurde angenommen.');
        } catch (PruefbelegFehler $fehler) {
            self::assertSame('beleg_schon_eingereicht', $fehler->schluessel());
        }

        // Das erste Foto steht unveraendert da — ein abgewiesener zweiter
        // Versuch darf den ersten nicht ueberschreiben.
        $nachher = $belege->beleg($belegId);
        self::assertNotNull($nachher);
        self::assertSame($ersteZeile['pfad'], $nachher['pfad']);
        self::assertNotNull($belege->datei($nachher['pfad']));
    }

    public function testOhneLaufendenVorgangGibtEsKeinenUpload(): void
    {
        $this->gdVoraussetzen();

        $benutzerId = $this->benutzer('Silvia');

        try {
            $this->belege()->belegHochladen($benutzerId, $this->hochladung());
            self::fail('Ein Foto ohne Vorgang wurde angenommen.');
        } catch (PruefbelegFehler $fehler) {
            self::assertSame('kein_vorgang', $fehler->schluessel());
        }
    }

    public function testZuEinemEingereichtenVorgangGibtEsKeinenNeuenCode(): void
    {
        $this->gdVoraussetzen();

        $benutzerId = $this->benutzer('Silvia');
        $belege = $this->belege();
        $belege->codeVergeben($benutzerId, '2026-07-01 12:00:00');
        $belege->belegHochladen($benutzerId, $this->hochladung(), '2026-07-01 13:00:00');

        try {
            $belege->codeVergeben($benutzerId, '2026-07-01 14:00:00');
            self::fail('Ein eingereichter Vorgang hat einen neuen Code bekommen.');
        } catch (PruefbelegFehler $fehler) {
            self::assertSame('beleg_schon_eingereicht', $fehler->schluessel());
        }
    }

    public function testDasBildLaeuftUeberDieselbePipelineUndTraegtEineVorschau(): void
    {
        $this->gdVoraussetzen();

        $benutzerId = $this->benutzer('Silvia');
        $belege = $this->belege();
        $belege->codeVergeben($benutzerId, '2026-07-01 12:00:00');
        $belegId = $belege->belegHochladen($benutzerId, $this->hochladung(), '2026-07-01 13:00:00');

        $beleg = $belege->beleg($belegId);

        self::assertNotNull($beleg);
        self::assertSame(Pruefbelege::STATUS_EINGEREICHT, $beleg['status']);

        // Relativ gespeichert — ein Serverumzug entwertet die Zeile sonst.
        self::assertStringStartsNotWith('/', (string) $beleg['pfad']);

        $bild = $belege->datei($beleg['pfad']);
        $vorschau = $belege->datei($beleg['vorschau_pfad']);

        self::assertNotNull($bild);
        self::assertNotNull($vorschau);

        // Das Ergebnis der Neukodierung ist IMMER ein JPEG — und ein JPEG aus
        // dieser Pipeline hat keinen EXIF-Abschnitt mehr. Das ist bei einem
        // Selfie die wichtigste Eigenschaft der ganzen Strecke: Ein Handyfoto
        // traegt sonst die Koordinaten der eigenen Wohnung.
        $masse = getimagesize($bild);
        self::assertIsArray($masse);
        self::assertSame(IMAGETYPE_JPEG, $masse[2]);
        self::assertStringNotContainsString('Exif', (string) file_get_contents($bild));
    }

    public function testEinPfadAusserhalbDesBelegverzeichnissesLiefertNichts(): void
    {
        $belege = $this->belege();
        mkdir($this->verzeichnis, 0750, true);

        self::assertNull($belege->datei('../../etc/passwd'));
        self::assertNull($belege->datei('/etc/passwd'));
        self::assertNull($belege->datei(''));
        self::assertNull($belege->datei(null));
    }

    // --- Die Entscheidung ---------------------------------------------------

    public function testDieFreigabeSchreibtNurDasErgebnisUndNiemalsVolljaehrigkeit(): void
    {
        $this->gdVoraussetzen();

        [$benutzerId, $verwalterId, $belegId] = $this->eingereichterBeleg();

        $this->belege()->entscheiden(
            $belegId,
            $verwalterId,
            true,
            'Code und Datum stimmten, das Gesicht war erkennbar.',
            '2026-07-02 09:00:00'
        );

        $pruefung = $this->db->eine(
            'SELECT * FROM pruefungen WHERE benutzer_id = :b',
            ['b' => $benutzerId]
        );

        self::assertNotNull($pruefung);
        self::assertSame(Pruefbelege::PRUEFUNG_ART, (string) $pruefung['art']);
        self::assertSame(Pruefbelege::PRUEFUNG_ANBIETER, (string) $pruefung['anbieter']);
        self::assertSame('bestanden', (string) $pruefung['status']);

        // DIE ZEILE, AUF DIE ES ANKOMMT. Ein handgeschriebener Zettel sagt
        // nichts ueber ein Geburtsdatum. Eine 1 hier waere die eine Aenderung,
        // mit der aus einer Identitaetspruefung stillschweigend eine
        // Altersfreigabe wuerde — und damit ein Gate nach § 4 Abs. 2 JMStV,
        // das keines ist.
        self::assertSame(0, (int) $pruefung['volljaehrig']);

        // Das Abzeichen kommt aus dem Ergebnis, nicht aus dem Beleg — es
        // ueberlebt dessen Loeschung.
        self::assertTrue($this->belege()->abzeichenVorhanden($benutzerId));
    }

    public function testEineAblehnungVergibtKeinAbzeichen(): void
    {
        $this->gdVoraussetzen();

        [$benutzerId, $verwalterId, $belegId] = $this->eingereichterBeleg();

        $this->belege()->entscheiden($belegId, $verwalterId, false, 'Der Code auf dem Zettel passt nicht.');

        self::assertFalse($this->belege()->abzeichenVorhanden($benutzerId));

        $pruefung = $this->db->eine('SELECT * FROM pruefungen WHERE benutzer_id = :b', ['b' => $benutzerId]);
        self::assertNotNull($pruefung);
        self::assertSame('abgelehnt', (string) $pruefung['status']);
        self::assertSame(0, (int) $pruefung['volljaehrig']);
    }

    public function testOhneBegruendungWirdNichtEntschieden(): void
    {
        $this->gdVoraussetzen();

        [, $verwalterId, $belegId] = $this->eingereichterBeleg();

        try {
            $this->belege()->entscheiden($belegId, $verwalterId, false, '   ');
            self::fail('Eine Entscheidung ohne Begruendung wurde angenommen.');
        } catch (PruefbelegFehler $fehler) {
            self::assertSame('entscheidung_fehlt', $fehler->schluessel());
        }
    }

    public function testUeberEinenBelegWirdNurEinmalEntschieden(): void
    {
        $this->gdVoraussetzen();

        [, $verwalterId, $belegId] = $this->eingereichterBeleg();
        $belege = $this->belege();

        $belege->entscheiden($belegId, $verwalterId, true, 'Passt.');

        try {
            $belege->entscheiden($belegId, $verwalterId, false, 'Doch nicht.');
            self::fail('Ein Beleg wurde zweimal entschieden.');
        } catch (PruefbelegFehler $fehler) {
            self::assertSame('beleg_nicht_offen', $fehler->schluessel());
        }
    }

    // --- Die Loeschfrist ----------------------------------------------------

    public function testDieFristLaeuftAbDemAnlegenUndBetraegtDreissigTage(): void
    {
        $benutzerId = $this->benutzer('Silvia');

        $vorgang = $this->belege()->codeVergeben($benutzerId, '2026-07-01 12:00:00');

        self::assertSame(30, Pruefbelege::FRIST_UNBEARBEITET_TAGE);
        self::assertSame('2026-07-31 12:00:00', $vorgang['loeschen_ab']);
    }

    /**
     * Nach der Entscheidung gilt das jeweils Fruehere.
     *
     * Beide Richtungen werden geprueft, weil nur eine davon selbstverstaendlich
     * ist: Wird frueh entschieden, verkuerzt die Sieben-Tage-Frist; wird spaet
     * entschieden, darf sie die Dreissig-Tage-Frist NICHT verlaengern. Der
     * zweite Fall ist der, den eine naive Zuweisung kaputtmacht.
     */
    public function testNachDerEntscheidungGiltDasJeweilsFruehere(): void
    {
        $this->gdVoraussetzen();

        // Fall 1: frueh entschieden — sieben Tage gewinnen.
        [, $verwalterId, $belegId] = $this->eingereichterBeleg();
        $frueh = $this->belege()->entscheiden($belegId, $verwalterId, true, 'Passt.', '2026-07-02 09:00:00');

        self::assertSame(7, Pruefbelege::FRIST_ENTSCHIEDEN_TAGE);
        self::assertSame('2026-07-09 09:00:00', $frueh['loeschen_ab']);

        // Fall 2: spaet entschieden — die dreissig Tage bleiben stehen. Ohne
        // das Minimum stuende hier '2026-08-04', also vier Tage laenger, als
        // die Zusage erlaubt.
        [, $verwalterZwei, $belegZwei] = $this->eingereichterBeleg('Nadja', 'Zweite');
        $spaet = $this->belege()->entscheiden($belegZwei, $verwalterZwei, true, 'Passt.', '2026-07-28 09:00:00');

        self::assertSame('2026-07-31 12:00:00', $spaet['loeschen_ab']);
    }

    public function testFaelligeBelegeVerschwindenMitIhrenDateien(): void
    {
        $this->gdVoraussetzen();

        [, $verwalterId, $belegId] = $this->eingereichterBeleg();
        $belege = $this->belege();
        $belege->entscheiden($belegId, $verwalterId, true, 'Passt.', '2026-07-02 09:00:00');

        $beleg = $belege->beleg($belegId);
        self::assertNotNull($beleg);

        $bild = (string) $belege->datei($beleg['pfad']);
        $vorschau = (string) $belege->datei($beleg['vorschau_pfad']);

        self::assertFileExists($bild);
        self::assertFileExists($vorschau);

        // Einen Tag vor der Frist geschieht nichts. Ohne diese Haelfte wuerde
        // ein Test auch dann gruen, wenn faelligeLoeschen() schlicht alles
        // loescht.
        self::assertSame(0, $belege->faelligeLoeschen('2026-07-08 09:00:00'));
        self::assertFileExists($bild);
        self::assertNotNull($belege->beleg($belegId));

        self::assertSame(1, $belege->faelligeLoeschen('2026-07-09 09:00:01'));

        self::assertNull($belege->beleg($belegId));
        self::assertFileDoesNotExist($bild);
        self::assertFileDoesNotExist($vorschau);
        self::assertDirectoryDoesNotExist($this->verzeichnis . '/' . $belegId);
    }

    public function testEinUnbearbeiteterBelegVerschwindetNachDreissigTagen(): void
    {
        $this->gdVoraussetzen();

        [, , $belegId] = $this->eingereichterBeleg();
        $belege = $this->belege();

        $beleg = $belege->beleg($belegId);
        self::assertNotNull($beleg);
        $bild = (string) $belege->datei($beleg['pfad']);

        // Tag 29: noch da. Die Verwaltung hat nie hingesehen.
        self::assertSame(0, $belege->faelligeLoeschen('2026-07-30 12:00:00'));
        self::assertFileExists($bild);

        // Tag 30: weg, ungesehen. Das ist die Zusage — nicht ein Versehen.
        self::assertSame(1, $belege->faelligeLoeschen('2026-07-31 12:00:01'));
        self::assertNull($belege->beleg($belegId));
        self::assertFileDoesNotExist($bild);
    }

    /**
     * Ein Verzeichnis ohne Zeile wird ebenfalls weggeraeumt — aber erst nach
     * der Hoechstfrist.
     *
     * Der Hosentraeger zur Loeschzusage: Verschwindet ein Konto wirklich aus
     * der Datenbank, nimmt das ON DELETE CASCADE den Beleg mit und laesst die
     * Dateien liegen. Ohne diesen Schritt laege dann ein Selfie da, das keine
     * Frist mehr erfasst.
     *
     * DIE ZWEITE HAELFTE DIESES TESTS IST DIE WICHTIGERE. Diese Raeumung
     * entscheidet ueber Dateien anhand der gerade verbundenen Datenbank und
     * kann nicht wissen, ob es die richtige ist. Ohne Altersschwelle loescht
     * ein Lauf gegen eine frische oder falsche Verbindung SAEMTLICHE Belege,
     * weil zu keinem eine Zeile existiert — genau das ist beim Bau dieses
     * Pakets passiert.
     */
    public function testVerwaisteDateienWerdenWeggeraeumtAberNichtSofort(): void
    {
        $this->gdVoraussetzen();

        [, , $belegId] = $this->eingereichterBeleg();
        $belege = $this->belege();

        // Genau das, was ein CASCADE tut: nur die Zeile.
        $this->db->ausfuehren('DELETE FROM pruefungsbelege WHERE id = :id', ['id' => $belegId]);

        self::assertDirectoryExists($this->verzeichnis . '/' . $belegId);

        // Frisch: unantastbar. Ein Verzeichnis von heute koennte zu einem
        // laufenden Vorgang gehoeren, dessen Zeile diese Verbindung nur nicht
        // sieht.
        self::assertSame(0, $belege->verwaisteEntfernen(time()));
        self::assertDirectoryExists($this->verzeichnis . '/' . $belegId);

        // Nach der Hoechstfrist: weg. Laenger als ein lebender Beleg darf auch
        // ein verwaister nicht liegen.
        $spaeter = time() + (Pruefbelege::FRIST_UNBEARBEITET_TAGE + 1) * 86400;

        self::assertSame(1, $belege->verwaisteEntfernen($spaeter));
        self::assertDirectoryDoesNotExist($this->verzeichnis . '/' . $belegId);
    }

    // --- bin/pflege ---------------------------------------------------------

    /**
     * bin/pflege loescht Zeile UND Dateien — als echter Unterprozess.
     *
     * Der Test laeuft gegen eine eigene SQLite-Datei und ueber die
     * Umgebungsvariable DB_PFAD, die Env::get() vor der .env auswertet. Er
     * benutzt Belegkennungen weit oberhalb jedes echten Bestands, damit er
     * niemals ein Verzeichnis einer laufenden Entwicklungsinstanz anfasst.
     *
     * Warum ueberhaupt ein Unterprozess: Die Fachmethode ist eine Zeile
     * darueber schon geprueft. Was hier bewiesen werden muss, ist der Cronjob
     * selbst — dass die Datei existiert, ausfuehrbar ist, ihre Verbindung
     * aufbaut, das richtige Verzeichnis findet und mit 0 endet. Genau daran
     * scheitert eine Pflege in der Praxis, nicht an der Rechnung.
     */
    public function testDerCronjobLoeschtZeileUndDateien(): void
    {
        if (!function_exists('exec') || PHP_BINARY === '') {
            self::markTestSkipped('exec() oder PHP_BINARY fehlt — bin/pflege ist hier nicht pruefbar.');
        }

        $wurzel = dirname(__DIR__);
        $belegId = 900000001;

        $this->pflegeDatenbank = sys_get_temp_dir() . '/meinslip-pflege-' . bin2hex(random_bytes(6)) . '.sqlite';

        $db = new Database(new \PDO('sqlite:' . $this->pflegeDatenbank));
        (new Migrator($db, $wurzel . '/database/migrations'))->hoch();

        $benutzerId = $db->einfuegen('benutzer', [
            'pseudonym' => 'Pflegetest',
            'email' => 'pflege@beispiel.test',
            'passwort_hash' => 'x',
            'status' => 'aktiv',
            'sprache' => 'de-DE',
            'land' => 'DE',
            'homescreen_name' => null,
            'push_vorschau' => 0,
            'angelegt_am' => gmdate('Y-m-d H:i:s'),
            'zuletzt_aktiv_am' => null,
        ]);

        // Die Dateien liegen dort, wo bin/pflege sie sucht: im echten
        // Belegverzeichnis des Projekts.
        $this->pflegeVerzeichnis = Pruefbelege::verzeichnis($wurzel) . '/' . $belegId;
        mkdir($this->pflegeVerzeichnis, 0750, true);

        $bild = $this->pflegeVerzeichnis . '/bild.jpg';
        $vorschau = $this->pflegeVerzeichnis . '/vorschau.jpg';
        file_put_contents($bild, 'kein echtes Bild, aber eine echte Datei');
        file_put_contents($vorschau, 'ebenso');

        // DER KOEDER. Ein frisches Belegverzeichnis, zu dem diese Datenbank
        // keine Zeile kennt — genau die Lage, in der die verwaiste Raeumung
        // frueher den gesamten Bestand weggeloescht hat. bin/pflege laeuft
        // hier gegen eine fremde Datenbank; wenn es den Koeder anfasst, faellt
        // es auch ueber echte Belege her, sobald jemand DB_NAME verstellt.
        $this->pflegeKoeder = Pruefbelege::verzeichnis($wurzel) . '/900000002';
        mkdir($this->pflegeKoeder, 0750, true);
        file_put_contents($this->pflegeKoeder . '/fremd.jpg', 'gehoert einer anderen Datenbank');

        $db->einfuegen('pruefungsbelege', [
            'id' => $belegId,
            'benutzer_id' => $benutzerId,
            'code' => 'AAAA-BBBB',
            'code_ausgegeben_am' => '2026-01-01 00:00:00',
            'code_gueltig_bis' => '2026-01-03 00:00:00',
            'status' => Pruefbelege::STATUS_FREIGEGEBEN,
            'pfad' => $belegId . '/bild.jpg',
            'vorschau_pfad' => $belegId . '/vorschau.jpg',
            'eingereicht_am' => '2026-01-01 10:00:00',
            'entschieden_am' => '2026-01-02 10:00:00',
            'verwalter_id' => null,
            'entscheidung' => 'Passt.',
            'pruefung_id' => null,
            // Laengst faellig.
            'loeschen_ab' => '2026-01-09 10:00:00',
            'angelegt_am' => '2026-01-01 00:00:00',
        ]);

        $befehl = 'DB_TREIBER=sqlite DB_PFAD=' . escapeshellarg($this->pflegeDatenbank)
            . ' ' . escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg($wurzel . '/bin/pflege') . ' 2>&1';

        $ausgabe = [];
        $status = 1;
        exec($befehl, $ausgabe, $status);

        $text = implode("\n", $ausgabe);

        self::assertSame(0, $status, "bin/pflege endete mit {$status}:\n{$text}");
        self::assertStringContainsString('pruefungsbelege: 1', $text);

        self::assertSame(
            0,
            (int) $db->wert('SELECT COUNT(*) FROM pruefungsbelege WHERE id = :id', ['id' => $belegId]),
            'bin/pflege hat die Zeile stehen lassen.'
        );
        self::assertFileDoesNotExist($bild, 'bin/pflege hat das Bild liegen lassen.');
        self::assertFileDoesNotExist($vorschau, 'bin/pflege hat die Vorschau liegen lassen.');
        self::assertDirectoryDoesNotExist($this->pflegeVerzeichnis);

        // Und der Koeder steht unangetastet da. Eine Pflege, die alles
        // wegraeumt, wozu sie gerade keine Zeile findet, ist keine Pflege,
        // sondern ein Loeschknopf mit Zeitschaltuhr.
        self::assertFileExists(
            $this->pflegeKoeder . '/fremd.jpg',
            'bin/pflege hat ein frisches Verzeichnis angefasst, zu dem es keine Zeile kannte.'
        );
    }

    /**
     * Die Loeschung wird doppelt durchgesetzt.
     *
     * Ein Cronjob, den niemand beobachtet, ist keine Sicherung. Die Pruefseite
     * der Verwaltung raeumt deshalb bei jedem Aufruf mit auf — hier
     * nachgehalten an der Quelle, weil ein Routentest dafuer den ganzen
     * Verwaltungszugang nachstellen muesste.
     */
    public function testDiePruefseiteRaeumtBeiJedemAufrufMitAuf(): void
    {
        $quelle = (string) file_get_contents(dirname(__DIR__) . '/app/Http/VerwaltungsRouten.php');

        self::assertNotSame('', $quelle);
        self::assertGreaterThanOrEqual(
            2,
            substr_count($quelle, 'faelligeLoeschen()'),
            'Liste und Einzelansicht der Pruefbelege muessen beide raeumen.'
        );
    }

    // --- Die Altersschranke -------------------------------------------------

    /**
     * DER KERNTEST DES PAKETS.
     *
     * Eine bestandene Selbsterklaerung macht explizite Medien fuer Fremde
     * NICHT sichtbar. Sie ist keine geschlossene Benutzergruppe nach § 4
     * Abs. 2 JMStV, und solange kein lizenziertes Verfahren gebunden ist, ist
     * Nichtausliefern die einzige verteidigbare Antwort.
     */
    public function testEineBestandeneSelbsterklaerungOeffnetKeineExplizitenMedien(): void
    {
        [$verkaeuferId, $angebotId] = $this->angebotMitVerkaeuferin('Silvia');

        $fremdeId = $this->benutzer('Neugier');
        $sitzungen = new Sitzungen($this->db);
        $kennung = $sitzungen->starten($fremdeId);
        $sitzungen->gateBestanden($kennung);

        $sitzung = $sitzungen->laden($kennung);
        self::assertNotNull($sitzung);

        // Die Erklaerung gilt — es liegt also nicht daran, dass das Gate nicht
        // funktioniert. Genau deshalb steht diese Zusicherung hier: Ohne sie
        // wuerde der Test auch dann gruen, wenn gateBestanden() gar nichts tut.
        self::assertTrue($sitzungen->gateGilt($sitzung));

        $medien = new Medien($this->db, $this->verzeichnis);

        self::assertFalse(
            $medien->explizitSichtbar($angebotId, $fremdeId, true),
            'Eine Selbsterklaerung darf kein einziges explizites Bild freischalten.'
        );

        // Ohne Erklaerung erst recht nicht, und abgemeldet auch nicht.
        self::assertFalse($medien->explizitSichtbar($angebotId, $fremdeId, false));
        self::assertFalse($medien->explizitSichtbar($angebotId, 0, true));

        // Die Hochladende sieht ihr eigenes Bild unveraendert — die
        // Aenderung darf die bestehende Eigenschaft nicht in die andere
        // Richtung aufweichen.
        self::assertTrue($medien->explizitSichtbar($angebotId, $verkaeuferId, false));

        // Und die Verwaltung ebenfalls, ohne jede Erklaerung: Sie muss eine
        // Meldung bearbeiten koennen.
        $verwalterin = $this->benutzer('Chefin');
        (new Konten($this->db))->faehigkeitFreischalten($verwalterin, Konten::FAEHIGKEIT_VERWALTEN, 'test');
        self::assertTrue($medien->explizitSichtbar($angebotId, $verwalterin, false));
    }

    /**
     * Die Schranke ist nicht gebunden — und das ist die Voraussetzung des
     * Tests darueber.
     *
     * Wer hier ein Verfahren anbindet, muss beide Tests bewusst umschreiben.
     * Ein stilles Umdrehen faellt sonst niemandem auf.
     */
    public function testDieAltersschrankeIstNichtGebunden(): void
    {
        self::assertFalse(Medien::altersschrankeGebunden());
    }

    public function testDieSelbsterklaerungLaeuftAb(): void
    {
        $benutzerId = $this->benutzer('Silvia');
        $sitzungen = new Sitzungen($this->db);
        $kennung = $sitzungen->starten($benutzerId);

        self::assertFalse($sitzungen->gateGilt((array) $sitzungen->laden($kennung)));

        $sitzungen->gateBestanden($kennung);
        self::assertTrue($sitzungen->gateGilt((array) $sitzungen->laden($kennung)));

        // Angemeldet zu sein genuegt ausdruecklich nicht: Das AVS-Raster
        // verlangt eine Bestaetigung je Nutzungsvorgang, deshalb laeuft die
        // Erklaerung ab, waehrend die Sitzung weiterlebt.
        $this->db->ausfuehren(
            'UPDATE sitzungen SET adult_gate_bestanden_am = :z WHERE kennung = :k',
            [
                'z' => gmdate('Y-m-d H:i:s', time() - (Sitzungen::GATE_GUELTIG_MINUTEN + 1) * 60),
                'k' => hash('sha256', $kennung),
            ]
        );

        self::assertNotNull($sitzungen->laden($kennung), 'Die Sitzung selbst laeuft nicht mit ab.');
        self::assertFalse($sitzungen->gateGilt((array) $sitzungen->laden($kennung)));
    }

    // --- Texte --------------------------------------------------------------

    /**
     * Jeder Fehlerschluessel, der in eine Adresse geraten kann, hat einen Text.
     *
     * tests/UebersetzungenTest.php erkennt nur Aufrufe der Form te('a.b'). Die
     * Oberflaeche baut ihre Meldungen aber als te('verifizierung.fehler.' . $x)
     * zusammen — ein Schluessel ohne Text faellt dort also nicht auf, sondern
     * erst der Person, deren Identitaetsnachweis gerade gescheitert ist.
     */
    public function testJederFehlerDerVerifizierungHatEinenText(): void
    {
        $fehlend = [];

        foreach (VerifizierungsRouten::FEHLER as $schluessel) {
            if (str_starts_with(Lang::t('verifizierung.fehler.' . $schluessel), '[[')) {
                $fehlend[] = $schluessel;
            }
        }

        self::assertSame(
            [],
            $fehlend,
            "Diese Schluessel erschienen als '[[verifizierung.fehler.x]]' auf dem Bildschirm.\n"
            . "Ergaenze sie in resources/lang/de-DE/verifizierung.php:\n" . implode("\n", $fehlend)
        );
    }

    public function testJedeRueckmeldungDerVerifizierungHatEinenText(): void
    {
        $fehlend = [];

        foreach (VerifizierungsRouten::ERFOLGE as $schluessel) {
            if (str_starts_with(Lang::t('verifizierung.erfolg.' . $schluessel), '[[')) {
                $fehlend[] = $schluessel;
            }
        }

        self::assertSame([], $fehlend, implode("\n", $fehlend));
    }

    /**
     * Die Weissliste muss jeden Schluessel kennen, den die Fachklasse wirft.
     *
     * Sonst faellt ein neuer Schluessel stumm auf 'unbekannt' zurueck, und die
     * Person liest "Bitte versuche es noch einmal", obwohl ihr Foto aus einem
     * benennbaren Grund abgewiesen wurde — etwa weil der Code abgelaufen ist.
     * Sie versucht es dann tatsaechlich noch einmal, mit demselben Ergebnis.
     */
    public function testDieWeisslisteKenntJedenSchluesselDerFachklasse(): void
    {
        $quelle = (string) file_get_contents(
            dirname(__DIR__) . '/app/Domain/Verification/Pruefbelege.php'
        );

        self::assertNotSame('', $quelle);
        preg_match_all("/new PruefbelegFehler\('([a-z0-9_]+)'/", $quelle, $treffer);
        self::assertNotSame([], $treffer[1]);

        $unbekannt = array_values(array_diff(array_unique($treffer[1]), VerifizierungsRouten::FEHLER));

        self::assertSame(
            [],
            $unbekannt,
            "Pruefbelege wirft Schluessel, die VerifizierungsRouten::FEHLER nicht kennt:\n"
            . implode("\n", $unbekannt)
        );
    }

    /**
     * Das Abzeichen heisst nirgends "Alter geprueft".
     *
     * Ein handgeschriebener Zettel darf sich nicht als KJM-Verfahren ausgeben.
     * Der Test liest die Sprachdateien, weil genau dort der Ruecksturz
     * passiert: Der Code kennt nur Schluessel, formuliert wird in
     * resources/lang.
     */
    public function testKeinTextGibtSichAlsAlterspruefungAus(): void
    {
        $verstoesse = [];

        foreach (['verifizierung', 'verwaltung', 'profil', 'medien'] as $bereich) {
            $pfad = dirname(__DIR__) . '/resources/lang/de-DE/' . $bereich . '.php';
            /** @var array<string,string> $texte */
            $texte = (array) require $pfad;

            foreach ($texte as $schluessel => $text) {
                if (!is_string($text)) {
                    continue;
                }

                // 'Altersprüfung' und 'Altersverfahren' sind erlaubt, solange
                // sie verneint werden — die Texte dieses Pakets sprechen
                // gerade davon, dass es keine gibt. Verboten sind die
                // Behauptungen selbst.
                foreach (['alter geprüft', 'altersgeprüft', 'altersverifiziert', 'altersverifikation erfolgt'] as $verboten) {
                    if (str_contains(mb_strtolower($text), $verboten)) {
                        $verstoesse[] = $bereich . '.' . $schluessel . ': ' . $verboten;
                    }
                }
            }
        }

        self::assertSame(
            [],
            $verstoesse,
            "Diese Texte behaupten eine Alterspruefung, die nicht stattfindet:\n"
            . implode("\n", $verstoesse)
        );
    }

    /**
     * Wo das Abzeichen steht, steht auch, was es nicht ist.
     *
     * Beide Texte liegen in derselben Sprachdatei und werden in derselben
     * Karte ausgegeben (resources/views/verifizierung/uebersicht.php). Dieser
     * Test haelt fest, dass der zweite ueberhaupt existiert und das Alter
     * ausdruecklich ausnimmt.
     */
    public function testNebenDemAbzeichenStehtWasEsNichtIst(): void
    {
        $name = Lang::t('verifizierung.abzeichen');
        $kleingedrucktes = Lang::t('verifizierung.abzeichen_was_nicht');

        self::assertSame('Identität geprüft (manuell)', $name);
        self::assertStringNotContainsString('[[', $kleingedrucktes);
        self::assertStringContainsString('nichts über dein Alter', $kleingedrucktes);

        $vorlage = (string) file_get_contents(
            dirname(__DIR__) . '/resources/views/verifizierung/uebersicht.php'
        );

        self::assertStringContainsString("te('verifizierung.abzeichen')", $vorlage);
        self::assertStringContainsString("te('verifizierung.abzeichen_was_nicht')", $vorlage);
    }

    /** Die Seite der Altersschranke nennt die Norm, die sie nicht erfuellt. */
    public function testDieAltersschrankenseiteSagtWasSieNichtIst(): void
    {
        $text = Lang::t('verifizierung.schranke_ehrlich_1') . ' ' . Lang::t('verifizierung.schranke_ehrlich_3');

        self::assertStringNotContainsString('[[', $text);
        self::assertStringContainsString('§ 4 Abs. 2 JMStV', $text);
        self::assertStringContainsString('keine geschlossene Benutzergruppe', $text);
    }

    // --- Werkzeuge ----------------------------------------------------------

    private function belege(): Pruefbelege
    {
        // Die Uploadpruefung wird ersetzt: is_uploaded_file() ist ausserhalb
        // einer echten HTTP-Anfrage immer false. In app/ kommt diese Naht
        // nirgends vor.
        return new Pruefbelege(
            $this->db,
            $this->verzeichnis,
            new Bilder(static fn (string $pfad): bool => is_file($pfad))
        );
    }

    /**
     * Ein Konto mit Code und eingereichtem Foto, dazu eine Verwalterin.
     *
     * @return array{0:int,1:int,2:int} Benutzer, Verwalter, Beleg
     */
    private function eingereichterBeleg(string $pseudonym = 'Silvia', string $verwalterin = 'Chefin'): array
    {
        $benutzerId = $this->benutzer($pseudonym);
        $verwalterId = $this->benutzer($verwalterin);

        $belege = $this->belege();
        $belege->codeVergeben($benutzerId, '2026-07-01 12:00:00');
        $belegId = $belege->belegHochladen($benutzerId, $this->hochladung(), '2026-07-01 13:00:00');

        return [$benutzerId, $verwalterId, $belegId];
    }

    /**
     * Eine verkaufsfaehige Person mit einem veroeffentlichten Angebot.
     *
     * @return array{0:int,1:int} Benutzerkennung, Angebotskennung
     */
    private function angebotMitVerkaeuferin(string $pseudonym): array
    {
        $benutzerId = $this->benutzer($pseudonym);

        (new Konten($this->db))->faehigkeitFreischalten($benutzerId, Konten::FAEHIGKEIT_VERKAUFEN, 'test');

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
     * Mit GD erzeugt statt aus einer Datei im Repository geladen — ein
     * eingechecktes Selfie waere in diesem Projekt das Letzte, was jemand
     * committen sollte.
     *
     * @return array<string,mixed>
     */
    private function hochladung(): array
    {
        $bild = imagecreatetruecolor(80, 60);

        if (!$bild instanceof \GdImage) {
            self::markTestSkipped('GD kann kein Bild erzeugen.');
        }

        $farbe = imagecolorallocate($bild, 200, 170, 140);

        if ($farbe !== false) {
            imagefilledrectangle($bild, 0, 0, 79, 59, $farbe);
        }

        $pfad = sys_get_temp_dir() . '/meinslip-beleg-' . bin2hex(random_bytes(6)) . '.jpg';
        imagejpeg($bild, $pfad, 85);
        imagedestroy($bild);

        $this->quelldateien[] = $pfad;

        return [
            'name' => 'selfie.jpg',
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
