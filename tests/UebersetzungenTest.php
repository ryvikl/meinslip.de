<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Core\Lang;
use PHPUnit\Framework\TestCase;

/**
 * Haelt die i18n-Regel aus docs/08-architektur.md durch:
 * Im Code stehen ausschliesslich Schluessel, nie deutscher Text.
 *
 * Ohne diesen Test schleicht sich hartkodierter Text zurueck, und die
 * EU-Erweiterung wird spaeter ein Umbau statt einer weiteren Sprachdatei.
 * Der Test hat beim Einfuehren sofort sechs Verstoesse gefunden.
 */
final class UebersetzungenTest extends TestCase
{
    private const SPRACHVERZEICHNIS = __DIR__ . '/../resources/lang';

    /**
     * Der Produktname wird in keiner Sprache uebersetzt und gehoert deshalb
     * nicht nach resources/lang — dort wuerde er Uebersetzer zum Uebersetzen
     * einladen. Er wird vor der Pruefung aus der Zeile entfernt, nicht die
     * ganze Zeile uebersprungen: So faellt echter Text daneben weiterhin auf.
     */
    private const PRODUKTNAME = 'MeinSlip';

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../app/Support/hilfen.php';
        Lang::einrichten(self::SPRACHVERZEICHNIS, 'de-DE');
    }

    public function testVorlagenEnthaltenKeinenHartkodiertenText(): void
    {
        $verstoesse = [];

        foreach ($this->vorlagen() as $pfad) {
            foreach (file($pfad) ?: [] as $nummer => $roh) {
                if ($this->istUnverdaechtig($roh)) {
                    continue;
                }

                $zeile = str_replace(self::PRODUKTNAME, '', $roh);

                // Sichtbarer Text zwischen Tags oder in einem aria-label,
                // der nicht aus einem Uebersetzungsaufruf stammt.
                $treffer = preg_match('/>\s*[A-ZÄÖÜ][a-zäöüß]{3,}/u', $zeile)
                    || preg_match('/aria-label="[A-ZÄÖÜ][a-zäöüß]{3,}/u', $zeile);

                if ($treffer && !str_contains($zeile, 'te(') && !str_contains($zeile, 't(')) {
                    $verstoesse[] = basename($pfad) . ':' . ($nummer + 1) . ' ' . trim($roh);
                }
            }
        }

        self::assertSame(
            [],
            $verstoesse,
            "Hartkodierter Text in Vorlagen gefunden. Gehoert nach resources/lang/:\n"
            . implode("\n", $verstoesse)
        );
    }

    public function testAlleVerwendetenSchluesselExistieren(): void
    {
        $fehlend = [];

        foreach ($this->vorlagen() as $pfad) {
            $inhalt = (string) file_get_contents($pfad);
            preg_match_all("/\bte?\('([a-z0-9_]+\.[a-z0-9_.]+)'\)/", $inhalt, $treffer);

            foreach ($treffer[1] as $schluessel) {
                if (str_starts_with(Lang::t($schluessel), '[[')) {
                    $fehlend[] = basename($pfad) . ': ' . $schluessel;
                }
            }
        }

        self::assertSame([], $fehlend, "Fehlende Uebersetzungen:\n" . implode("\n", $fehlend));
    }

    public function testFehlenderSchluesselWirdSichtbarStattStillErsetzt(): void
    {
        // Eine fehlende Uebersetzung soll auffallen, nicht durch etwas
        // Plausibles ersetzt werden.
        self::assertSame('[[allgemein.gibt_es_nicht]]', Lang::t('allgemein.gibt_es_nicht'));
    }

    public function testPlatzhalterWerdenErsetzt(): void
    {
        self::assertStringContainsString(
            '72',
            Lang::t('bestellung.erklaerung.einspruchsfenster', ['stunden' => 72])
        );
    }

    /** @return list<string> */
    private function vorlagen(): array
    {
        return array_values(array_filter(
            glob(__DIR__ . '/../resources/views/*.php') ?: [],
            'is_readable'
        ));
    }

    private function istUnverdaechtig(string $zeile): bool
    {
        $z = ltrim($zeile);

        return $z === ''
            || str_starts_with($z, '*')
            || str_starts_with($z, '//')
            || str_starts_with($z, '/*')
            || str_starts_with($z, '<?php');
    }
}
