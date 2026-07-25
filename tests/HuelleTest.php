<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Haelt die Huellenliste des Service Workers und die Wirklichkeit zusammen.
 *
 * Hintergrund: cache.addAll() ist alles-oder-nichts. Fehlt eine einzige der
 * aufgezaehlten Dateien, schlaegt die Installation des Service Workers fehl —
 * und zwar lautlos. Die Seite funktioniert online weiter, der Offline-Betrieb
 * und die Installierbarkeit auf dem Homescreen sind aber weg. Niemand merkt
 * das, bis jemand ohne Netz aufmacht.
 *
 * Genau das war passiert: tokens.css wurde durch nocturne.css ersetzt, die
 * Liste blieb stehen. Dieser Test haette es sofort gezeigt.
 */
final class HuelleTest extends TestCase
{
    private const WURZEL = __DIR__ . '/..';

    /** @return list<string> */
    private function huelle(): array
    {
        $quelle = (string) file_get_contents(self::WURZEL . '/public/sw.js');

        self::assertSame(
            1,
            preg_match('/const HUELLE = \[(.*?)\];/s', $quelle, $treffer),
            'Die Huellenliste in public/sw.js wurde nicht gefunden — wurde sie umbenannt?'
        );

        preg_match_all('/"([^"]+)"/', $treffer[1], $pfade);

        self::assertNotEmpty($pfade[1], 'Die Huellenliste ist leer.');

        return $pfade[1];
    }

    public function testJedeDateiDerHuelleExistiert(): void
    {
        $fehlend = [];

        foreach ($this->huelle() as $pfad) {
            // Seitenadressen wie /offline liefert PHP aus, nicht das Dateisystem.
            if (!str_starts_with($pfad, '/assets/')) {
                continue;
            }

            if (!is_readable(self::WURZEL . '/public' . $pfad)) {
                $fehlend[] = $pfad;
            }
        }

        self::assertSame(
            [],
            $fehlend,
            "Diese Eintraege in public/sw.js zeigen ins Leere. cache.addAll() bricht\n"
            . "damit ab und der Service Worker installiert sich nicht:\n"
            . implode("\n", $fehlend)
        );
    }

    public function testJedeSeiteDerHuelleIstEineRoute(): void
    {
        $routen = (string) file_get_contents(self::WURZEL . '/app/Http/Routen.php');
        $fehlend = [];

        foreach ($this->huelle() as $pfad) {
            if (str_starts_with($pfad, '/assets/')) {
                continue;
            }

            if (!str_contains($routen, "'" . $pfad . "'")) {
                $fehlend[] = $pfad;
            }
        }

        self::assertSame(
            [],
            $fehlend,
            "Diese Eintraege in public/sw.js haben keine Route in app/Http/Routen.php:\n"
            . implode("\n", $fehlend)
        );
    }

    public function testAppCssZiehtNurVorhandeneDateienNach(): void
    {
        // Ein @import ist eine eigene Anfrage. Zeigt es ins Leere, bleibt die
        // Seite ungestaltet — online wie offline.
        $css = (string) file_get_contents(self::WURZEL . '/public/assets/css/app.css');
        preg_match_all('/@import\s+url\(\s*["\']?([^"\')]+)["\']?\s*\)/', $css, $treffer);

        $fehlend = [];

        foreach ($treffer[1] as $ziel) {
            if (str_starts_with($ziel, 'http')) {
                continue;
            }

            if (!is_readable(self::WURZEL . '/public/assets/css/' . $ziel)) {
                $fehlend[] = $ziel;
            }
        }

        self::assertSame([], $fehlend, "Fehlende @import-Ziele in app.css:\n" . implode("\n", $fehlend));
    }

    public function testKeineSchriftVonFremdenServern(): void
    {
        // DSGVO: Ein Aufruf an Google Fonts uebertraegt die IP-Adresse des
        // Besuchers in die USA, ohne dass er zustimmen konnte. Die Schriften
        // liegen deshalb selbst gehostet unter public/assets/fonts/.
        foreach (['app.css', 'nocturne.css'] as $datei) {
            $css = (string) file_get_contents(self::WURZEL . '/public/assets/css/' . $datei);

            self::assertStringNotContainsString('fonts.googleapis', $css, $datei . ' laedt von Google Fonts.');
            self::assertStringNotContainsString('fonts.gstatic', $css, $datei . ' laedt von Google Fonts.');
        }
    }

    public function testSelbstGehosteteSchriftenLiegenVor(): void
    {
        $css = (string) file_get_contents(self::WURZEL . '/public/assets/css/nocturne.css');
        preg_match_all('#url\(\s*["\']?(/assets/fonts/[^"\')]+)["\']?\s*\)#', $css, $treffer);

        self::assertNotEmpty($treffer[1], 'nocturne.css bindet keine eigenen Schriftdateien ein.');

        $fehlend = [];

        foreach (array_unique($treffer[1]) as $pfad) {
            if (!is_readable(self::WURZEL . '/public' . $pfad)) {
                $fehlend[] = $pfad;
            }
        }

        self::assertSame([], $fehlend, "Fehlende Schriftdateien:\n" . implode("\n", $fehlend));
    }
}
