<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Core\Lang;
use MeinSlip\Core\Request;
use MeinSlip\Core\Response;
use MeinSlip\Core\Router;
use MeinSlip\Core\View;
use MeinSlip\Http\Routen;
use PHPUnit\Framework\TestCase;

/**
 * Impressum, Datenschutz, AGB und Widerruf.
 *
 * Zwei Dinge haelt dieser Test fest, die sonst niemand bemerkt:
 *
 *  1. Die Seiten nennen die Betreibergesellschaft und duerfen ueber deren
 *     Namen NICHT auffindbar sein. Das Verbot muss aus der Anwendung selbst
 *     kommen — die .htaccess sperrt heute die ganze Seite, aber das kann zum
 *     oeffentlichen Start fallen, und dann stuende das Impressum im Index.
 *  2. Jeder Abschnitt, den die Route nennt, hat einen Text. Ein vergessener
 *     Schluessel stuende sonst als '[[agb.xyz]]' in einem Rechtstext.
 */
final class RechtsseitenTest extends TestCase
{
    private const SEITEN = ['/impressum', '/datenschutz', '/agb', '/widerruf'];

    private const FIRMA = 'Chic &amp; Chill';

    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../app/Support/hilfen.php';

        $wurzel = dirname(__DIR__);
        Lang::einrichten($wurzel . '/resources/lang', 'de-DE');

        $this->router = new Router();
        (new Routen($wurzel, new View($wurzel . '/resources/views')))->registrieren($this->router);
    }

    private function abrufen(string $pfad): Response
    {
        return $this->router->behandeln(Request::erzeugen('GET', $pfad));
    }

    public function testRechtsseitenVerbietenDieIndexierungSelbst(): void
    {
        foreach (self::SEITEN as $pfad) {
            $antwort = $this->abrufen($pfad);

            self::assertSame(200, $antwort->status, $pfad);
            self::assertSame(
                'noindex, nofollow, noarchive, nosnippet',
                $antwort->kopfzeilen['X-Robots-Tag'] ?? null,
                $pfad . ' muss das Indexierungsverbot selbst mitschicken.'
            );
            self::assertStringContainsString('data-nosnippet', $antwort->inhalt, $pfad);
            self::assertMatchesRegularExpression(
                '/<meta name="robots" content="noindex/',
                $antwort->inhalt,
                $pfad
            );
        }
    }

    public function testKeinAbschnittFehlt(): void
    {
        foreach (self::SEITEN as $pfad) {
            self::assertStringNotContainsString('[[', $this->abrufen($pfad)->inhalt, $pfad);
        }
    }

    public function testDerFirmennameStehtNieImSeitentitel(): void
    {
        foreach (self::SEITEN as $pfad) {
            preg_match('/<title>(.*?)<\/title>/s', $this->abrufen($pfad)->inhalt, $treffer);

            self::assertStringNotContainsString(self::FIRMA, $treffer[1] ?? '', $pfad);
        }
    }

    /**
     * Ausserhalb der Rechtsseiten taucht die Gesellschaft nicht auf. Sonst
     * fuehrte die Suche nach ihrem Namen ueber einen Umweg doch hierher.
     */
    public function testAndereSeitenNennenDieFirmaNicht(): void
    {
        foreach (['/', '/sicherheit', '/fuer-creator', '/kuendigen', '/registrieren'] as $pfad) {
            self::assertStringNotContainsString('Chic &amp;', $this->abrufen($pfad)->inhalt, $pfad);
        }
    }

    public function testImpressumTraegtDiePflichtangaben(): void
    {
        $inhalt = $this->abrufen('/impressum')->inhalt;

        foreach ([self::FIRMA . ' GmbH', '04552 Borna', 'HRB 45544', 'DE462251567', '03433 859 94 60'] as $angabe) {
            self::assertStringContainsString($angabe, $inhalt);
        }

        // Anschriften brauchen Zeilenumbrueche, sonst steht alles in einer Zeile.
        self::assertStringContainsString('Leipziger Straße 90<br>04552 Borna', $inhalt);
    }

    public function testListenWerdenAlsListenAusgegeben(): void
    {
        $inhalt = $this->abrufen('/datenschutz')->inhalt;

        self::assertStringContainsString('<li>ms_sitzung:', $inhalt);
        self::assertStringNotContainsString('- ms_sitzung', $inhalt);
    }
}
