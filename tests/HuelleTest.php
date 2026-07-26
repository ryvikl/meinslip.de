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

    /**
     * Jedes Ablageverzeichnis, das .gitignore kennt, legt das Paket auch an.
     *
     * DER FEHLER, DEN DIESE PRUEFUNG VERHINDERT, IST SCHON PASSIERT: Mit der
     * Verifizierung kam /storage/belege/ in die .gitignore, aber nicht in
     * paket-bauen.sh. Auf geteiltem Webhosting darf der PHP-Benutzer nicht
     * ueberall Verzeichnisse anlegen — der erste Selfie-Upload waere mit
     * 'ziel_unbrauchbar' gescheitert, und zwar bei genau der Person, die
     * gerade Vertrauen fassen soll.
     *
     * Die .gitignore ist die richtige Quelle fuer diese Liste: Ein Verzeichnis
     * dort einzutragen ist der Schritt, den niemand vergisst — es faellt beim
     * naechsten 'git status' sofort auf. Das mkdir im Paketskript vergisst man
     * lautlos, weil nichts danach fragt, bis es produktiv fehlt.
     */
    public function testJedesAblageverzeichnisWirdImPaketAngelegt(): void
    {
        $ignoriert = (string) file_get_contents(self::WURZEL . '/.gitignore');
        $skript = (string) file_get_contents(self::WURZEL . '/deployment/paket-bauen.sh');

        preg_match_all('#^/storage/([a-z]+)/$#m', $ignoriert, $treffer);

        self::assertNotEmpty($treffer[1], 'In .gitignore steht kein einziges /storage/<name>/ — wurde die Datei umgebaut?');

        /*
         * NUR DIE mkdir-AUFRUFE ZAEHLEN, NICHT DIE GANZE DATEI.
         *
         * Diese Pruefung hat beim ersten Versuch aus dem falschen Grund
         * bestanden: Sie durchsuchte das ganze Skript, und der Kommentar ueber
         * dem mkdir nennt die Verzeichnisse beim Namen. Damit war sie gruen,
         * auch nachdem das Verzeichnis aus dem Befehl entfernt war — ein Test,
         * der den Kommentar liest, prueft die Absicht statt der Tat.
         *
         * Gesammelt werden ALLE mkdir-Aufrufe samt Zeilenfortsetzungen: Das
         * Skript hat legitim mehr als einen (zuerst das Zielverzeichnis
         * selbst), und welcher davon ein Ablageverzeichnis anlegt, ist keine
         * Zusicherung, die dieser Test treffen sollte.
         */
        preg_match_all('/^mkdir -p((?:[^\n]*\\\\\n)*[^\n]*)$/m', $skript, $befehle);

        self::assertNotEmpty(
            $befehle[1],
            'In paket-bauen.sh steht kein "mkdir -p" — wurde das Skript umgebaut?'
        );

        $angelegt = implode("\n", $befehle[1]);
        $fehlend = [];

        foreach (array_unique($treffer[1]) as $verzeichnis) {
            // 'cache' und 'media' sind Altlasten ohne Schreiber im Code; sie
            // stehen in .gitignore als Netz, brauchen aber kein Verzeichnis.
            if (in_array($verzeichnis, ['cache', 'media'], true)) {
                continue;
            }

            if (!str_contains($angelegt, 'storage/' . $verzeichnis)) {
                $fehlend[] = 'storage/' . $verzeichnis;
            }
        }

        self::assertSame(
            [],
            $fehlend,
            "deployment/paket-bauen.sh legt diese Verzeichnisse nicht an:\n" . implode("\n", $fehlend)
        );
    }
}
