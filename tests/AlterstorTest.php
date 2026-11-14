<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Core\Lang;
use MeinSlip\Core\Request;
use MeinSlip\Core\View;
use MeinSlip\Http\Alterstor;
use PHPUnit\Framework\TestCase;

/**
 * Die Altersfrage vor dem ersten Seitenaufruf.
 *
 * Drei Dinge, die still kaputtgehen koennen: dass ohne Antwort doch Inhalt
 * kommt, dass eine Pflichtseite hinter der Frage verschwindet, und dass das
 * Weiterleitungsziel nach aussen zeigt.
 */
final class AlterstorTest extends TestCase
{
    private View $ansicht;

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../app/Support/hilfen.php';
        Lang::einrichten(__DIR__ . '/../resources/lang', 'de-DE');
        $this->ansicht = new View(__DIR__ . '/../resources/views');
    }

    public function testOhneAntwortKommtDieFrageStattDerSeite(): void
    {
        foreach (['/', '/entdecken', '/angebot/1', '/medien/1/vorschau', '/registrieren'] as $pfad) {
            $antwort = Alterstor::pruefen(Request::erzeugen('GET', $pfad), [], $this->ansicht);

            self::assertNotNull($antwort, $pfad);
            self::assertStringContainsString('name="antwort" value="ja"', $antwort->inhalt, $pfad);
            self::assertSame('no-store', $antwort->kopfzeilen['Cache-Control'] ?? null, $pfad);
        }
    }

    public function testMitAntwortGehtEsDurch(): void
    {
        self::assertNull(Alterstor::pruefen(Request::erzeugen('GET', '/entdecken'), ['ms_alter' => '1'], $this->ansicht));
    }

    public function testEinBeliebigerCookieWertGenuegtNicht(): void
    {
        self::assertNotNull(Alterstor::pruefen(Request::erzeugen('GET', '/'), ['ms_alter' => 'nein'], $this->ansicht));
    }

    /** § 5 DDG, § 312k BGB, Art. 16 DSA — und die Betriebspruefung des Deployments. */
    public function testPflichtseitenUndTechnikBleibenFrei(): void
    {
        $frei = [
            '/impressum', '/datenschutz', '/agb', '/widerruf', '/kuendigen',
            '/melden', '/melden/angebot/3', '/zustand', '/offline', '/manifest.webmanifest',
        ];

        foreach ($frei as $pfad) {
            self::assertNull(Alterstor::pruefen(Request::erzeugen('GET', $pfad), [], $this->ansicht), $pfad);
        }
    }

    public function testEinAehnlicherPfadIstNichtFrei(): void
    {
        self::assertNotNull(Alterstor::pruefen(Request::erzeugen('GET', '/impressum-anders'), [], $this->ansicht));
    }

    public function testJaFuehrtZumGewuenschtenZielAufDieserSeite(): void
    {
        $antwort = Alterstor::antworten(
            Request::erzeugen('POST', '/alter', ['antwort' => 'ja', 'ziel' => '/angebot/12']),
            $this->ansicht
        );

        self::assertSame(302, $antwort?->status);
        self::assertSame('/angebot/12', $antwort->kopfzeilen['Location'] ?? null);
    }

    public function testEinFremdesZielWirdZurStartseite(): void
    {
        foreach (['https://boese.example/', '//boese.example', '/\\boese.example', 'javascript:alert(1)'] as $ziel) {
            $antwort = Alterstor::antworten(
                Request::erzeugen('POST', '/alter', ['antwort' => 'ja', 'ziel' => $ziel]),
                $this->ansicht
            );

            self::assertSame('/', $antwort?->kopfzeilen['Location'] ?? null, $ziel);
        }
    }

    public function testNeinZeigtDieAbsageUndKeineSchaltflaeche(): void
    {
        $antwort = Alterstor::antworten(Request::erzeugen('POST', '/alter', ['antwort' => 'nein']), $this->ansicht);

        self::assertSame(200, $antwort?->status);
        self::assertStringNotContainsString('value="ja"', $antwort->inhalt);
    }

    public function testAndereWegeBeantwortetDasTorNicht(): void
    {
        self::assertNull(Alterstor::antworten(Request::erzeugen('GET', '/entdecken'), $this->ansicht));
    }
}
