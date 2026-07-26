<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Domain\Media\Bilder;
use MeinSlip\Domain\Media\MedienFehler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Die Bildpipeline — echter Rundlauf mit von GD selbst erzeugten Bildern.
 *
 * Diese Suite braucht keine Datenbank und erbt deshalb direkt von TestCase.
 * Sie schreibt in ein eigenes Verzeichnis unter sys_get_temp_dir() und raeumt
 * es hinterher weg.
 *
 * WARUM MIT ECHTEN DATEIEN UND NICHT MIT ATTRAPPEN: Die drei Zusicherungen
 * dieser Klasse — EXIF ist weg, angehaengter Schadcode ist weg, die Vorschau
 * traegt die Information nicht mehr — sind Aussagen ueber BYTES AUF DER
 * PLATTE. Eine Attrappe koennte sie nur behaupten. Jeder Test hier erzeugt
 * deshalb eine Datei, schickt sie durch annehmen() und liest das Ergebnis
 * zurueck.
 *
 * Fehlt GD, werden die Tests uebersprungen statt rot: Ein Entwicklungsrechner
 * ohne GD soll die Suite nicht scheitern lassen — die Einrichtungsseite und
 * composer.json sind der Ort, an dem das Fehlen auffaellt.
 */
final class BilderTest extends TestCase
{
    private string $verzeichnis;

    /** @var list<string> */
    private array $temporaeren = [];

    private Bilder $bilder;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Bilder::verfuegbar()) {
            self::markTestSkipped('GD oder fileinfo fehlt auf diesem Rechner.');
        }

        $this->verzeichnis = sys_get_temp_dir() . '/meinslip-bilder-' . bin2hex(random_bytes(8));

        // Die Naht: is_uploaded_file() ist ausserhalb einer echten HTTP-Anfrage
        // immer false. Ohne sie liesse sich die Aufnahmekette nur von Hand im
        // Browser pruefen. testEineNichtHochgeladeneDateiWirdAbgewiesen
        // benutzt bewusst KEINE Naht und sichert damit die Voreinstellung.
        $this->bilder = new Bilder(static fn (string $pfad): bool => is_file($pfad));
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaeren as $pfad) {
            @unlink($pfad);
        }

        if (is_dir($this->verzeichnis)) {
            foreach ((array) glob($this->verzeichnis . '/*') as $datei) {
                @unlink((string) $datei);
            }

            @rmdir($this->verzeichnis);
        }

        parent::tearDown();
    }

    // --- Der gute Fall -----------------------------------------------------

    public function testDieBildverarbeitungWirdGeprueftUndNichtVorausgesetzt(): void
    {
        self::assertTrue(Bilder::verfuegbar());
        self::assertContains('image/jpeg', Bilder::formate(), 'Ohne JPEG-Ausgabe kann die Klasse gar nichts.');
    }

    public function testEinGueltigesJpegLiefertZweiDateien(): void
    {
        $ergebnis = $this->bilder->annehmen($this->upload($this->jpeg(1200, 800)), $this->verzeichnis);

        self::assertSame(Bilder::ART_BILD, $ergebnis['art']);
        self::assertFileExists($ergebnis['pfad']);
        self::assertFileExists($ergebnis['vorschau_pfad']);
        self::assertNotSame($ergebnis['pfad'], $ergebnis['vorschau_pfad']);

        $bild = getimagesize($ergebnis['pfad']);
        $vorschau = getimagesize($ergebnis['vorschau_pfad']);

        self::assertIsArray($bild);
        self::assertIsArray($vorschau);
        self::assertSame(IMAGETYPE_JPEG, $bild[2], 'Gespeichert wird immer JPEG, egal was hochgeladen wurde.');
        self::assertSame(IMAGETYPE_JPEG, $vorschau[2]);
        self::assertSame(Bilder::VORSCHAU_KANTE, max((int) $vorschau[0], (int) $vorschau[1]));
    }

    public function testEinGrossesBildWirdAufDieLangeKanteGerechnet(): void
    {
        $ergebnis = $this->bilder->annehmen($this->upload($this->jpeg(3000, 1500)), $this->verzeichnis);

        $masse = getimagesize($ergebnis['pfad']);

        self::assertIsArray($masse);
        self::assertSame(Bilder::MAX_KANTE, (int) $masse[0]);
        self::assertSame(800, (int) $masse[1], 'Das Seitenverhaeltnis bleibt erhalten.');
    }

    public function testEinKleinesBildWirdNichtAufgeblasen(): void
    {
        $ergebnis = $this->bilder->annehmen($this->upload($this->jpeg(320, 240)), $this->verzeichnis);

        $masse = getimagesize($ergebnis['pfad']);

        self::assertIsArray($masse);
        self::assertSame(320, (int) $masse[0]);
        self::assertSame(240, (int) $masse[1]);
    }

    public function testDerDateinameKommtNieVomClient(): void
    {
        $datei = $this->upload($this->jpeg(400, 300));
        $datei['name'] = '../../public/schoen.php.jpg';
        $datei['type'] = 'application/x-httpd-php';

        $ergebnis = $this->bilder->annehmen($datei, $this->verzeichnis);

        foreach ([$ergebnis['pfad'], $ergebnis['vorschau_pfad']] as $pfad) {
            self::assertMatchesRegularExpression('/^[0-9a-f]{32}\.jpg$/', basename($pfad));
            self::assertSame($this->verzeichnis, dirname($pfad), 'Der Clientname darf das Verzeichnis nicht verlassen.');
        }
    }

    public function testDieDateienLiegenMitEngenRechten(): void
    {
        $ergebnis = $this->bilder->annehmen($this->upload($this->jpeg(400, 300)), $this->verzeichnis);

        self::assertSame(0750, fileperms($this->verzeichnis) & 0777);
        self::assertSame(0640, fileperms($ergebnis['pfad']) & 0777);
        self::assertSame(0640, fileperms($ergebnis['vorschau_pfad']) & 0777);
    }

    public function testPngUndWebpWerdenAlsJpegGespeichert(): void
    {
        foreach (['png', 'webp'] as $format) {
            if (!in_array('image/' . $format, Bilder::formate(), true)) {
                continue;
            }

            $ergebnis = $this->bilder->annehmen($this->upload($this->{$format}(500, 500)), $this->verzeichnis);
            $masse = getimagesize($ergebnis['pfad']);

            self::assertIsArray($masse);
            self::assertSame(IMAGETYPE_JPEG, $masse[2], $format . ' haette als JPEG ankommen muessen.');
        }
    }

    // --- Die Vorschau ------------------------------------------------------

    /**
     * Die Vorschau muss nachweislich informationsaermer sein.
     *
     * Gemessen wird die Kantenenergie: der mittlere Helligkeitssprung zwischen
     * waagerecht benachbarten Bildpunkten. Beide Dateien werden dafuer auf
     * DIESELBE Groesse gerechnet — sonst verglichen wir nur Bildgroessen, und
     * das Ergebnis waere immer wahr, ohne etwas zu zeigen.
     *
     * Die Vorlage traegt feine Streifen, also viel hochfrequente Information.
     * Genau die ueberlebt den Umweg ueber 24 Pixel nicht: gemessen bleiben
     * unter 2 Prozent uebrig. Die Schwelle steht auf 10 Prozent, damit
     * Rundungsunterschiede zwischen GD-Bauten den Test nicht faerben.
     */
    public function testDieVorschauTraegtDeutlichWenigerInformationAlsDasBild(): void
    {
        $ergebnis = $this->bilder->annehmen($this->upload($this->jpeg(1200, 800)), $this->verzeichnis);

        $imBild = $this->kantenenergie($ergebnis['pfad']);
        $inVorschau = $this->kantenenergie($ergebnis['vorschau_pfad']);

        self::assertGreaterThan(5.0, $imBild, 'Die Vorlage muss ueberhaupt Kanten tragen, sonst misst der Test nichts.');
        self::assertLessThan(
            $imBild * 0.10,
            $inVorschau,
            sprintf('Kantenenergie Bild %.2f, Vorschau %.2f — die Vorschau ist nicht unscharf genug.', $imBild, $inVorschau)
        );
    }

    // --- Die Abweisungen ---------------------------------------------------

    public function testEineAlsBildGetarnteTextdateiWirdAbgewiesen(): void
    {
        $inhalt = "Das ist kein Bild.\n<?php echo shell_exec(\$_GET['x']); ?>\n";

        $this->assertAbgewiesen('kein_bild', $this->upload($inhalt));
    }

    public function testEineSvgDateiWirdAbgewiesen(): void
    {
        // SVG ist ein XML-Dokument mit <script>-Unterstuetzung und kein Bild,
        // das sich neu kodieren liesse. getimagesize() weist es ab — der Test
        // haelt fest, dass niemand es spaeter "nachtraegt".
        $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10">'
            . '<script>alert(1)</script></svg>';

        $this->assertAbgewiesen('kein_bild', $this->upload($svg));
    }

    public function testEinGifWirdAbgewiesen(): void
    {
        $bild = imagecreatetruecolor(40, 40);
        ob_start();
        imagegif($bild);
        $roh = (string) ob_get_clean();
        imagedestroy($bild);

        $this->assertAbgewiesen('format_nicht_erlaubt', $this->upload($roh));
    }

    /**
     * Ein animiertes WebP besteht BEIDE Typpruefungen.
     *
     * getimagesize meldet IMAGETYPE_WEBP, finfo meldet image/webp — die
     * Kreuzprobe sagt also brav ja. Nur der Blick in den RIFF-Abschnitt 'ANIM'
     * verhindert, dass aus einer Bewegtdarstellung stillschweigend ein
     * einzelnes Standbild wird, das die Hochladende so nie ausgewaehlt hat.
     */
    public function testEinAnimiertesWebpWirdAbgewiesen(): void
    {
        if (!in_array('image/webp', Bilder::formate(), true)) {
            self::markTestSkipped('Dieser GD-Bau kann kein WebP lesen.');
        }

        $pfad = $this->ablegen($this->animiertesWebp());
        $masse = getimagesize($pfad);

        self::assertIsArray($masse);
        self::assertSame(IMAGETYPE_WEBP, $masse[2], 'Die Vorlage muss die Typpruefung bestehen, sonst misst der Test etwas anderes.');

        $this->assertAbgewiesen('animation_nicht_erlaubt', $this->uploadVon($pfad));
    }

    /**
     * Ein PNG, das nach dem Kopf abbricht, besteht beide Typpruefungen.
     *
     * Das ist der Beleg fuer die Begruendung in typPruefen(): Weder
     * getimagesize noch finfo beweisen Dekodierbarkeit. Den Beweis liefert
     * erst der Dekodierer.
     */
    public function testEinUnvollstaendigesPngWirdErstBeimDekodierenAbgewiesen(): void
    {
        $pfad = $this->ablegen(substr($this->png(80, 60), 0, 33));
        $masse = getimagesize($pfad);

        self::assertIsArray($masse);
        self::assertSame(IMAGETYPE_PNG, $masse[2], 'getimagesize liest nur den IHDR-Abschnitt und meldet deshalb weiter PNG.');

        $this->assertAbgewiesen('dekodierung_fehlgeschlagen', $this->uploadVon($pfad));
    }

    /**
     * Die Dekompressionsbombe wird VOR dem Dekodieren abgewiesen.
     *
     * Die Vorlage ist unter einem Kilobyte gross und behauptet im Kopf
     * 60000 x 60000 Punkte — dekodiert waeren das rund 14 Gigabyte. Der Test
     * prueft ausdruecklich beides: dass getimagesize die absurden Masse
     * tatsaechlich meldet (sonst wuerde die Datei aus einem anderen Grund
     * abgewiesen und der Test bewiese nichts) und dass die Antwort
     * 'zu_viele_pixel' lautet und kein Speicherfehler.
     */
    public function testEinBildMitAbsurdenMassenWirdVorDemDekodierenAbgewiesen(): void
    {
        $pfad = $this->ablegen($this->pngMitBehauptetenMassen(60000, 60000));
        $masse = getimagesize($pfad);

        self::assertIsArray($masse);
        self::assertSame(60000, (int) $masse[0]);
        self::assertLessThan(1024, (int) filesize($pfad), 'Die Bombe muss klein sein, sonst greift schon die Byte-Grenze.');

        $this->assertAbgewiesen('zu_viele_pixel', $this->uploadVon($pfad));
    }

    /**
     * Die Speicherrechnung ist eine Gratwanderung und wird deshalb direkt
     * geprueft.
     *
     * Nach oben droht der Fatal Error, der die ganze Anfrage mitreisst. Nach
     * unten droht etwas, das niemand bemerkt: ein Server, der jedes normale
     * Handyfoto abweist, waehrend auf dem Entwicklungsrechner (memory_limit
     * -1) alles funktioniert. Die Zahlen unten sind die beiden Raender.
     */
    #[DataProvider('speicherfaelle')]
    public function testDerSpeicherbedarfWirdRealistischGeschaetzt(int $breite, int $hoehe, int $megabyte, bool $passt): void
    {
        self::assertSame($passt, Bilder::passtInSpeicher($breite, $hoehe, $megabyte * 1024 * 1024, 4 * 1024 * 1024));
    }

    /** @return iterable<string,array{0:int,1:int,2:int,3:bool}> */
    public static function speicherfaelle(): iterable
    {
        yield 'Handyfoto 12 MP auf 128 MB' => [4032, 3024, 128, true];
        yield 'Handyfoto 48 MP auf 256 MB' => [8000, 6000, 256, true];
        yield 'Bombe 36 MP auf 128 MB' => [6000, 6000, 128, false];
        yield 'Handyfoto 48 MP auf 128 MB' => [8000, 6000, 128, false];
        yield 'ohne Grenze geht alles' => [30000, 30000, 0, true];
    }

    public function testEineZuGrosseDateiWirdAbgewiesen(): void
    {
        $pfad = $this->grosseDatei(Bilder::MAX_BYTES + 1024);

        self::assertGreaterThan(Bilder::MAX_BYTES, (int) filesize($pfad));

        // 'datei_zu_gross' und nicht 'kein_bild': Die Byte-Grenze muss VOR der
        // Typpruefung greifen, sonst liest getimagesize erst einmal alles ein.
        $this->assertAbgewiesen('datei_zu_gross', $this->uploadVon($pfad));
    }

    /**
     * Der Transportfehler wird nach Bedienfehler und Rest getrennt.
     *
     * UPLOAD_ERR_INI_SIZE und UPLOAD_ERR_FORM_SIZE sind kein Angriff, sondern
     * ein zu grosses Handyfoto. Sie brauchen eine eigene Antwort mit einer
     * Zahl darin — sonst wiederholt die Nutzerin denselben Upload dreimal.
     */
    #[DataProvider('transportfehler')]
    public function testTransportfehlerWerdenUnterschieden(int $code, string $schluessel): void
    {
        $datei = $this->upload($this->jpeg(100, 100));
        $datei['error'] = $code;

        $this->assertAbgewiesen($schluessel, $datei);
    }

    /** @return iterable<string,array{0:int,1:string}> */
    public static function transportfehler(): iterable
    {
        yield 'php.ini-Grenze' => [UPLOAD_ERR_INI_SIZE, 'upload_zu_gross'];
        yield 'Formulargrenze' => [UPLOAD_ERR_FORM_SIZE, 'upload_zu_gross'];
        yield 'abgebrochen' => [UPLOAD_ERR_PARTIAL, 'upload_fehlgeschlagen'];
        yield 'keine Datei' => [UPLOAD_ERR_NO_FILE, 'upload_fehlgeschlagen'];
        yield 'kein Verzeichnis' => [UPLOAD_ERR_NO_TMP_DIR, 'upload_fehlgeschlagen'];
    }

    /**
     * Ohne Naht gilt is_uploaded_file() — und damit ist keine Serverdatei
     * hochladbar.
     *
     * Dieser Test benutzt bewusst new Bilder() ohne Argument. Er sichert die
     * VOREINSTELLUNG ab: Faellt die Pruefung weg, genuegt ein erfundenes
     * tmp_name im Formular, um die .env oder die SQLite-Datei durch die
     * Bildpipeline zu schicken.
     */
    public function testEineNichtHochgeladeneDateiWirdAbgewiesen(): void
    {
        $datei = $this->upload($this->jpeg(200, 200));

        try {
            (new Bilder())->annehmen($datei, $this->verzeichnis);
            self::fail('Eine Datei ohne echten Upload haette abgewiesen werden muessen.');
        } catch (MedienFehler $fehler) {
            self::assertSame('nicht_hochgeladen', $fehler->schluessel());
        }
    }

    // --- Die Neukodierung --------------------------------------------------

    /**
     * Angehaengter Schadcode ueberlebt die Neukodierung nicht.
     *
     * Die Vorlage ist ein gueltiges JPEG mit angehaengtem PHP-Text — eine
     * Polyglot-Datei. Sie besteht getimagesize UND finfo, weil beide nur den
     * Kopf lesen. Erst das Neuschreiben aus Bildpunkten entfernt den Anhang,
     * denn er war nie Teil des Bildes.
     */
    public function testAngehaengterSchadcodeUeberlebtDieNeukodierungNicht(): void
    {
        $gift = '<?php system($_GET["x"]); __halt_compiler();';
        $pfad = $this->ablegen($this->jpeg(600, 400) . $gift);

        $masse = getimagesize($pfad);
        $kennung = finfo_open(FILEINFO_MIME_TYPE);
        $gemeldet = finfo_file($kennung, $pfad);
        finfo_close($kennung);

        self::assertIsArray($masse);
        self::assertSame(IMAGETYPE_JPEG, $masse[2], 'Die Polyglot-Datei muss beide Typpruefungen bestehen.');
        self::assertSame('image/jpeg', $gemeldet);
        self::assertStringContainsString($gift, (string) file_get_contents($pfad));

        $ergebnis = $this->bilder->annehmen($this->uploadVon($pfad), $this->verzeichnis);

        self::assertStringNotContainsString($gift, (string) file_get_contents($ergebnis['pfad']));
        self::assertStringNotContainsString('<?php', (string) file_get_contents($ergebnis['pfad']));
        self::assertStringNotContainsString('<?php', (string) file_get_contents($ergebnis['vorschau_pfad']));
    }

    /**
     * EXIF ist nach der Verarbeitung weg — die Orientierung vorher angewendet.
     *
     * Die Vorlage traegt Orientierung 6 (hochkant aufgenommen) und die
     * Koordinaten 52 Grad Nord. Beides steht im selben APP1-Abschnitt. Der
     * Test prueft drei Dinge auf einmal:
     *
     *  1. Die Vorlage traegt die Koordinaten wirklich — sonst bewiese der Rest
     *     nichts.
     *  2. Das Ergebnis traegt sie nicht mehr, weder als EXIF-Struktur noch als
     *     Zeichenkette in den rohen Bytes.
     *  3. Das Bild wurde trotzdem richtig gedreht: aus 400 x 200 quer wird
     *     200 x 400 hochkant. Wer die Orientierung nur wegwirft statt sie
     *     anzuwenden, veroeffentlicht jedes Handyfoto liegend.
     */
    public function testExifWirdEntferntUndDieOrientierungVorherAngewendet(): void
    {
        $pfad = $this->ablegen($this->jpegMitExif(400, 200));

        $vorher = @exif_read_data($pfad);

        self::assertIsArray($vorher);
        self::assertSame(6, (int) ($vorher['Orientation'] ?? 0));
        self::assertSame('N', $vorher['GPSLatitudeRef'] ?? null, 'Die Vorlage muss Koordinaten tragen.');

        $ergebnis = $this->bilder->annehmen($this->uploadVon($pfad), $this->verzeichnis);

        foreach ([$ergebnis['pfad'], $ergebnis['vorschau_pfad']] as $erzeugt) {
            $nachher = @exif_read_data($erzeugt);

            self::assertArrayNotHasKey('GPSLatitude', is_array($nachher) ? $nachher : []);
            self::assertArrayNotHasKey('GPSLatitudeRef', is_array($nachher) ? $nachher : []);
            self::assertArrayNotHasKey('Orientation', is_array($nachher) ? $nachher : []);
            self::assertStringNotContainsString("Exif\0\0", (string) file_get_contents($erzeugt));
        }

        $masse = getimagesize($ergebnis['pfad']);

        self::assertIsArray($masse);
        self::assertSame(200, (int) $masse[0], 'Orientierung 6 muss angewendet worden sein.');
        self::assertSame(400, (int) $masse[1]);
    }

    // --- Hilfen ------------------------------------------------------------

    /**
     * @param array<string,mixed> $datei
     */
    private function assertAbgewiesen(string $schluessel, array $datei): void
    {
        try {
            $this->bilder->annehmen($datei, $this->verzeichnis);
            self::fail('Erwartet war die Abweisung mit dem Schluessel "' . $schluessel . '".');
        } catch (MedienFehler $fehler) {
            self::assertSame($schluessel, $fehler->schluessel(), 'Meldung: ' . $fehler->getMessage());
        }

        self::assertSame([], glob($this->verzeichnis . '/*.jpg') ?: [], 'Eine abgewiesene Datei darf nichts hinterlassen.');
    }

    /**
     * Der mittlere Helligkeitssprung zwischen waagerechten Nachbarpunkten.
     *
     * Beide Bilder werden zuvor auf dieselbe Kantenlaenge gerechnet, damit die
     * Zahl die Schaerfe misst und nicht die Bildgroesse.
     */
    private function kantenenergie(string $pfad): float
    {
        $bild = imagecreatefromjpeg($pfad);

        self::assertInstanceOf(\GdImage::class, $bild);

        $kante = 200;
        $klein = imagecreatetruecolor($kante, $kante);
        imagecopyresampled($klein, $bild, 0, 0, 0, 0, $kante, $kante, imagesx($bild), imagesy($bild));
        imagedestroy($bild);

        $summe = 0.0;
        $anzahl = 0;

        for ($y = 0; $y < $kante; $y++) {
            for ($x = 1; $x < $kante; $x++) {
                $links = imagecolorat($klein, $x - 1, $y);
                $rechts = imagecolorat($klein, $x, $y);
                $summe += abs($this->helligkeit($links) - $this->helligkeit($rechts));
                $anzahl++;
            }
        }

        imagedestroy($klein);

        return $summe / max(1, $anzahl);
    }

    private function helligkeit(int $farbe): float
    {
        return (($farbe >> 16 & 255) + ($farbe >> 8 & 255) + ($farbe & 255)) / 3;
    }

    /**
     * Ein Testbild mit feinen Streifen — also mit viel Information, die sich
     * verlieren kann.
     */
    private function bild(int $breite, int $hoehe): \GdImage
    {
        $bild = imagecreatetruecolor($breite, $hoehe);
        $hell = (int) imagecolorallocate($bild, 245, 245, 250);
        $dunkel = (int) imagecolorallocate($bild, 20, 20, 40);

        imagefilledrectangle($bild, 0, 0, $breite - 1, $hoehe - 1, $hell);

        for ($x = 0; $x < $breite; $x += 8) {
            imagefilledrectangle($bild, $x, 0, min($x + 3, $breite - 1), $hoehe - 1, $dunkel);
        }

        for ($y = 0; $y < $hoehe; $y += 20) {
            imagefilledrectangle($bild, 0, $y, $breite - 1, min($y + 2, $hoehe - 1), $dunkel);
        }

        return $bild;
    }

    private function jpeg(int $breite, int $hoehe): string
    {
        $bild = $this->bild($breite, $hoehe);
        ob_start();
        imagejpeg($bild, null, 92);
        $roh = (string) ob_get_clean();
        imagedestroy($bild);

        return $roh;
    }

    private function png(int $breite, int $hoehe): string
    {
        $bild = $this->bild($breite, $hoehe);
        ob_start();
        imagepng($bild);
        $roh = (string) ob_get_clean();
        imagedestroy($bild);

        return $roh;
    }

    private function webp(int $breite, int $hoehe): string
    {
        $bild = $this->bild($breite, $hoehe);
        ob_start();
        imagewebp($bild, null, 90);
        $roh = (string) ob_get_clean();
        imagedestroy($bild);

        return $roh;
    }

    /**
     * Ein JPEG mit EXIF-Kopf: Orientierung 6 und die Koordinaten 52/31 Nord.
     *
     * Von Hand gebaut, weil GD kein EXIF schreiben kann. Aufbau: APP1 direkt
     * hinter dem Startzeichen, darin "Exif\0\0" und ein TIFF-Block mit zwei
     * Verzeichnissen — IFD0 mit Orientierung und Zeiger auf das GPS-IFD.
     */
    private function jpegMitExif(int $breite, int $hoehe): string
    {
        $roh = $this->jpeg($breite, $hoehe);

        // TIFF-Kopf, little endian, IFD0 bei Offset 8.
        $tiff = 'II' . pack('v', 42) . pack('V', 8);

        // IFD0: zwei Eintraege, danach der Abschluss — belegt Offset 8 bis 37.
        $ifd0 = pack('v', 2);
        $ifd0 .= pack('v', 0x0112) . pack('v', 3) . pack('V', 1) . pack('v', 6) . pack('v', 0);
        $ifd0 .= pack('v', 0x8825) . pack('v', 4) . pack('V', 1) . pack('V', 38);
        $ifd0 .= pack('V', 0);

        // GPS-IFD bei Offset 38, zwei Eintraege — belegt Offset 38 bis 67.
        $gps = pack('v', 2);
        $gps .= pack('v', 0x0001) . pack('v', 2) . pack('V', 2) . "N\0\0\0";
        $gps .= pack('v', 0x0002) . pack('v', 5) . pack('V', 3) . pack('V', 68);
        $gps .= pack('V', 0);

        // Drei Brueche ab Offset 68: 52 Grad, 31 Minuten, 0 Sekunden.
        $werte = pack('V', 52) . pack('V', 1) . pack('V', 31) . pack('V', 1) . pack('V', 0) . pack('V', 1);

        $tiff .= $ifd0 . $gps . $werte;
        $app1 = "\xFF\xE1" . pack('n', strlen($tiff) + 8) . "Exif\0\0" . $tiff;

        return "\xFF\xD8" . $app1 . substr($roh, 2);
    }

    /**
     * Ein gueltiges PNG, dessen Kopf absurde Masse behauptet.
     *
     * Der IHDR-Abschnitt liegt bei jedem PNG an derselben Stelle: 8 Byte
     * Signatur, 4 Byte Laenge, 4 Byte Kennung, 13 Byte Daten, 4 Byte
     * Pruefsumme. Breite und Hoehe stehen ganz vorn in den Daten; die
     * Pruefsumme wird mitgerechnet, damit die Datei kein kaputtes PNG ist,
     * sondern ein wohlgeformtes mit absurden Angaben.
     */
    private function pngMitBehauptetenMassen(int $breite, int $hoehe): string
    {
        $roh = $this->png(1, 1);
        $daten = pack('N', $breite) . pack('N', $hoehe) . substr($roh, 24, 5);

        return substr($roh, 0, 16) . $daten . pack('N', crc32('IHDR' . $daten)) . substr($roh, 33);
    }

    /** Eine Datei der gewuenschten Groesse, ohne sie wirklich zu schreiben. */
    private function grosseDatei(int $bytes): string
    {
        $pfad = $this->neuerPfad();
        $griff = fopen($pfad, 'wb');

        self::assertIsResource($griff);

        fseek($griff, $bytes - 1);
        fwrite($griff, "\0");
        fclose($griff);

        return $pfad;
    }

    /**
     * Ein animiertes WebP: RIFF-Rumpf mit VP8X, ANIM und zwei Einzelbildern.
     *
     * Der Bilddatenabschnitt stammt aus einem echten, von GD erzeugten WebP —
     * damit ist die Datei nicht nur formal richtig, sondern auch dekodierbar.
     */
    private function animiertesWebp(): string
    {
        $statisch = $this->webp(60, 40);
        $abschnitt = '';
        $rest = substr($statisch, 12);
        $pos = 0;

        while ($pos + 8 <= strlen($rest)) {
            $kennung = substr($rest, $pos, 4);
            /** @var array{1:int} $kopf */
            $kopf = unpack('V', substr($rest, $pos + 4, 4));
            $laenge = $kopf[1];

            if ($kennung === 'VP8 ' || $kennung === 'VP8L') {
                $abschnitt = $kennung . pack('V', $laenge) . substr($rest, $pos + 8, $laenge);
                break;
            }

            $pos += 8 + $laenge + ($laenge % 2);
        }

        self::assertNotSame('', $abschnitt, 'Im WebP von GD steckt kein Bilddatenabschnitt.');

        $dreiByte = static fn (int $wert): string => substr(pack('V', $wert), 0, 3);

        $vp8x = 'VP8X' . pack('V', 10) . chr(0x02) . "\0\0\0" . $dreiByte(59) . $dreiByte(39);
        $anim = 'ANIM' . pack('V', 6) . pack('V', 0xFFFFFFFF) . pack('v', 0);
        $rahmen = $dreiByte(0) . $dreiByte(0) . $dreiByte(59) . $dreiByte(39) . $dreiByte(100) . chr(0) . $abschnitt;
        $anmf = 'ANMF' . pack('V', strlen($rahmen)) . $rahmen;

        $rumpf = 'WEBP' . $vp8x . $anim . $anmf . $anmf;

        return 'RIFF' . pack('V', strlen($rumpf)) . $rumpf;
    }

    /**
     * Legt Inhalt in einer temporaeren Datei ab und merkt sie zum Aufraeumen.
     */
    private function ablegen(string $inhalt): string
    {
        $pfad = $this->neuerPfad();
        file_put_contents($pfad, $inhalt);

        return $pfad;
    }

    private function neuerPfad(): string
    {
        $pfad = sys_get_temp_dir() . '/meinslip-quelle-' . bin2hex(random_bytes(8));
        $this->temporaeren[] = $pfad;

        return $pfad;
    }

    /** @return array<string,mixed> */
    private function upload(string $inhalt): array
    {
        return $this->uploadVon($this->ablegen($inhalt));
    }

    /** @return array<string,mixed> */
    private function uploadVon(string $pfad): array
    {
        return [
            'name' => 'foto.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $pfad,
            'error' => UPLOAD_ERR_OK,
            'size' => (int) filesize($pfad),
        ];
    }
}
