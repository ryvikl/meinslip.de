<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Http\Navigation;

/**
 * Die untere Navigationsleiste.
 *
 * Geprueft wird hier nicht das Aussehen, sondern die eine Eigenschaft, die
 * teuer waere, wenn sie kippt: DER ZAEHLER DARF KEINE SEITE MITREISSEN. Er ist
 * Beiwerk auf einer Leiste, die unterhalb von 860 px der einzige Weg durch die
 * Anwendung ist — faellt er mit einer Ausnahme aus, ist nicht der Zaehler weg,
 * sondern jede Seite.
 *
 * Die Zahl selbst zaehlt Unterhaltungen::ungeleseneAnzahl(); das steht in
 * tests/UnterhaltungenTest.php und wird hier nicht wiederholt.
 */
final class NavigationTest extends Testfall
{
    protected function setUp(): void
    {
        parent::setUp();
        Navigation::zuruecksetzen();
    }

    protected function tearDown(): void
    {
        Navigation::zuruecksetzen();

        parent::tearDown();
    }

    public function testOhneSitzungGibtEsKeinAbzeichen(): void
    {
        self::assertSame(0, Navigation::ungelesen(null));
        self::assertNull(Navigation::abzeichen(null));
    }

    public function testEineUnbrauchbareSitzungGibtNullUndWirftNicht(): void
    {
        foreach ([[], ['benutzer_id' => 0], ['benutzer_id' => -1], ['benutzer_id' => 'abc']] as $sitzung) {
            self::assertSame(0, Navigation::ungelesen($sitzung));
            self::assertNull(Navigation::abzeichen($sitzung));
        }
    }

    /**
     * Ein Datenbankausfall kostet den Zaehler, nicht die Seite.
     *
     * Die Testumgebung faehrt SQLite im Arbeitsspeicher; Database::ausEnv()
     * findet dort keine brauchbare Verbindung und wirft. Genau dieser Fall
     * wird hier ausgenutzt: Er ist derselbe, den ein ausgefallener
     * Datenbankserver im Betrieb erzeugt.
     */
    public function testEinAusfallLiefertNullStattEinerAusnahme(): void
    {
        self::assertSame(0, Navigation::ungelesen(['benutzer_id' => 1]));
        self::assertNull(Navigation::abzeichen(['benutzer_id' => 1]));
    }

    /**
     * Ab 100 steht '99+' — sonst schoebe die Zahl die Beschriftung aus ihrem
     * Feld. Auf 360 px hat jeder der fuenf Plaetze rund 68 px.
     */
    public function testGrosseZahlenWerdenGedeckelt(): void
    {
        self::assertSame(99, Navigation::HOECHSTE_ANZEIGE);

        // Der Deckel wird an der Formatierung geprueft, ohne die Datenbank:
        // ueber den Zwischenspeicher, den die Klasse fuer genau diesen Zweck
        // offenlegt.
        $this->zaehlerSetzen(7, 3);
        self::assertSame('3', Navigation::abzeichen(['benutzer_id' => 7]));

        $this->zaehlerSetzen(8, 99);
        self::assertSame('99', Navigation::abzeichen(['benutzer_id' => 8]));

        $this->zaehlerSetzen(9, 100);
        self::assertSame('99+', Navigation::abzeichen(['benutzer_id' => 9]));

        $this->zaehlerSetzen(10, 4711);
        self::assertSame('99+', Navigation::abzeichen(['benutzer_id' => 10]));
    }

    /**
     * Der Zwischenspeicher liegt JE KONTO.
     *
     * Mit einem einzelnen Platz bekaeme das zweite Konto im selben Prozess die
     * Zahl des ersten — eine falsche Zahl sieht aus wie eine richtige.
     */
    public function testDerZwischenspeicherVerwechseltKontenNicht(): void
    {
        $this->zaehlerSetzen(1, 5);
        $this->zaehlerSetzen(2, 0);

        self::assertSame(5, Navigation::ungelesen(['benutzer_id' => 1]));
        self::assertSame(0, Navigation::ungelesen(['benutzer_id' => 2]));
        self::assertSame('5', Navigation::abzeichen(['benutzer_id' => 1]));
        self::assertNull(Navigation::abzeichen(['benutzer_id' => 2]));
    }

    /**
     * Schreibt direkt in den Zwischenspeicher, um die Formatierung ohne
     * Datenbank pruefen zu koennen.
     */
    private function zaehlerSetzen(int $benutzerId, int $anzahl): void
    {
        $spiegel = new \ReflectionProperty(Navigation::class, 'ungelesen');
        $werte = $spiegel->getValue();
        $werte[$benutzerId] = $anzahl;
        $spiegel->setValue(null, $werte);
    }
}
