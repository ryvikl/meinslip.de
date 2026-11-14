<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Media;

/**
 * Schneidet aus einem bereits angenommenen Bild ein Quadrat.
 *
 * Fuer das Profilbild: Hochgeladen wird das ganze Foto, gezeigt wird ein
 * Ausschnitt, den die Person selbst waehlt. Der Ausschnitt entsteht hier auf
 * dem Server als eigene, kleine Datei — nicht per CSS im Browser. Sonst laege
 * das ganze Foto bei jedem Besucher, auch der Teil, den die Person gerade
 * weggeschnitten hat.
 *
 * DREI ZAHLEN BESCHREIBEN DEN AUSSCHNITT, alle in Prozent:
 *
 *   zoom  100 bis 400. 100 ist das groesste Quadrat, das ins Bild passt.
 *   x, y  0 bis 100. Wo das Quadrat im verbleibenden Spielraum sitzt;
 *         50/50 ist die Mitte.
 *
 * Prozent statt Pixel, damit die Angaben vom Formular unabhaengig von der
 * Anzeigegroesse sind — und damit kein Wert ausserhalb des Bildes zeigen kann:
 * Jede Zahl wird in ihren Bereich geklemmt, bevor gerechnet wird.
 *
 * Die Quelle ist immer eine Datei, die Bilder::annehmen() geschrieben hat:
 * neu kodiertes JPEG, hoechstens 1600 Pixel Kante. Diese Klasse nimmt nichts
 * von aussen an und prueft deshalb weder Typ noch Bombe ein zweites Mal.
 */
final class Ausschnitt
{
    /** Kantenlaenge des fertigen Profilbilds. Reicht fuer 112px bei dreifacher Pixeldichte. */
    public const KANTE = 512;

    public const ZOOM_MIN = 100;

    public const ZOOM_MAX = 400;

    private const QUALITAET = 86;

    /**
     * @throws MedienFehler 'bildverarbeitung_fehlt', 'dekodierung_fehlgeschlagen',
     *                      'speichern_fehlgeschlagen'
     */
    public function quadrat(string $quelle, string $ziel, int $zoom = 100, int $x = 50, int $y = 50): void
    {
        if (!function_exists('imagecreatefromjpeg')) {
            throw new MedienFehler('bildverarbeitung_fehlt', 'GD fehlt auf diesem Server.');
        }

        $bild = @imagecreatefromjpeg($quelle);

        if ($bild === false) {
            throw new MedienFehler('dekodierung_fehlgeschlagen', 'Quelle ' . $quelle . ' ist nicht lesbar.');
        }

        [$links, $oben, $seite] = self::rechteck(imagesx($bild), imagesy($bild), $zoom, $x, $y);

        $fertig = imagecreatetruecolor(self::KANTE, self::KANTE);
        imagecopyresampled($fertig, $bild, 0, 0, $links, $oben, self::KANTE, self::KANTE, $seite, $seite);
        imagedestroy($bild);

        // Erst neben das Ziel schreiben, dann umbenennen: Bricht das Schreiben
        // ab, bleibt das bisherige Profilbild heil.
        $vorlaeufig = $ziel . '.neu';
        $geschrieben = @imagejpeg($fertig, $vorlaeufig, self::QUALITAET);
        imagedestroy($fertig);

        if (!$geschrieben || !@rename($vorlaeufig, $ziel)) {
            @unlink($vorlaeufig);

            throw new MedienFehler('speichern_fehlgeschlagen', 'Ausschnitt nach ' . $ziel . ' fehlgeschlagen.');
        }
    }

    /**
     * Linke obere Ecke und Seitenlaenge des Quadrats, in Pixeln der Quelle.
     *
     * Oeffentlich und ohne GD, damit sich die Rechnung pruefen laesst, ohne
     * ein Bild zu erzeugen.
     *
     * @return array{0:int,1:int,2:int}
     */
    public static function rechteck(int $breite, int $hoehe, int $zoom, int $x, int $y): array
    {
        $zoom = max(self::ZOOM_MIN, min(self::ZOOM_MAX, $zoom));
        $x = max(0, min(100, $x));
        $y = max(0, min(100, $y));

        $seite = max(1, (int) round(min($breite, $hoehe) * 100 / $zoom));
        $links = (int) round(($breite - $seite) * $x / 100);
        $oben = (int) round(($hoehe - $seite) * $y / 100);

        return [$links, $oben, $seite];
    }
}
