<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Domain\Media\Ausschnitt;
use PHPUnit\Framework\TestCase;

/**
 * Der Ausschnitt des Profilbilds.
 *
 * Die Rechnung steht zweimal im Projekt — hier und in public/assets/js/app.js
 * fuer die Vorschau. Dieser Test haelt die Serverseite fest; sie ist die, die
 * zaehlt.
 */
final class AusschnittTest extends TestCase
{
    public function testOhneAngabenKommtDasGroessteQuadratAusDerMitte(): void
    {
        self::assertSame([200, 0, 600], Ausschnitt::rechteck(1000, 600, 100, 50, 50));
        self::assertSame([0, 200, 600], Ausschnitt::rechteck(600, 1000, 100, 50, 50));
    }

    public function testDieLageSchiebtImSpielraum(): void
    {
        self::assertSame([0, 0, 600], Ausschnitt::rechteck(1000, 600, 100, 0, 50));
        self::assertSame([400, 0, 600], Ausschnitt::rechteck(1000, 600, 100, 100, 50));
    }

    public function testZoomVerkleinertDasQuadratUndOeffnetBeideRichtungen(): void
    {
        self::assertSame([350, 150, 300], Ausschnitt::rechteck(1000, 600, 200, 50, 50));
        self::assertSame([700, 300, 300], Ausschnitt::rechteck(1000, 600, 200, 100, 100));
    }

    /** Was auch immer ankommt: Das Quadrat liegt im Bild. */
    public function testUnsinnigeWerteBleibenImBild(): void
    {
        foreach ([[-50, -9, 999], [100000, 500, -3], [0, 50, 50], [399, 100, 0]] as [$zoom, $x, $y]) {
            [$links, $oben, $seite] = Ausschnitt::rechteck(800, 1200, $zoom, $x, $y);

            self::assertGreaterThanOrEqual(0, $links);
            self::assertGreaterThanOrEqual(0, $oben);
            self::assertGreaterThan(0, $seite);
            self::assertLessThanOrEqual(800, $links + $seite);
            self::assertLessThanOrEqual(1200, $oben + $seite);
        }
    }

    public function testDasQuadratWirdGeschriebenUndZeigtDenGewaehltenTeil(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD fehlt.');
        }

        $ordner = sys_get_temp_dir() . '/meinslip-ausschnitt-' . bin2hex(random_bytes(6));
        mkdir($ordner);

        // Linke Haelfte rot, rechte blau.
        $bild = imagecreatetruecolor(800, 400);
        imagefilledrectangle($bild, 0, 0, 399, 399, imagecolorallocate($bild, 220, 20, 20));
        imagefilledrectangle($bild, 400, 0, 799, 399, imagecolorallocate($bild, 20, 20, 220));
        imagejpeg($bild, $ordner . '/quelle.jpg', 95);

        $ausschnitt = new Ausschnitt();
        $ausschnitt->quadrat($ordner . '/quelle.jpg', $ordner . '/links.jpg', 100, 0, 50);
        $ausschnitt->quadrat($ordner . '/quelle.jpg', $ordner . '/rechts.jpg', 100, 100, 50);

        foreach (['links' => 'rot', 'rechts' => 'blau'] as $name => $farbe) {
            $fertig = imagecreatefromjpeg($ordner . '/' . $name . '.jpg');
            self::assertSame(Ausschnitt::KANTE, imagesx($fertig));
            self::assertSame(Ausschnitt::KANTE, imagesy($fertig));

            $punkt = imagecolorsforindex($fertig, imagecolorat($fertig, 256, 256));
            self::assertGreaterThan(150, $farbe === 'rot' ? $punkt['red'] : $punkt['blue'], $name);
            self::assertLessThan(90, $farbe === 'rot' ? $punkt['blue'] : $punkt['red'], $name);
        }

        self::assertFileDoesNotExist($ordner . '/links.jpg.neu');
    }
}
