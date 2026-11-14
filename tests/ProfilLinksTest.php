<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Domain\Account\KontoFehler;
use MeinSlip\Domain\Account\ProfilLinks;
use MeinSlip\Domain\Account\Profile;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Verweise auf dem oeffentlichen Profil.
 *
 * Der wichtigste Teil ist die Adresspruefung: Was hier durchgeht, steht als
 * href auf einer Seite, die Menschen einander schicken.
 */
final class ProfilLinksTest extends Testfall
{
    /** @return array<string,array{string}> */
    public static function gefaehrlicheAdressen(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'javascript gross' => ['JaVaScRiPt:alert(1)'],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'unverschluesselt' => ['http://beispiel.de/'],
            'zugangsdaten' => ['https://bank.de@boese.example/'],
            'nackte ip' => ['https://203.0.113.7/'],
            'localhost' => ['https://localhost/'],
            'leerraum' => ["https://beispiel.de/\njavascript:alert(1)"],
            'anfuehrungszeichen' => ['https://beispiel.de/"onmouseover="alert(1)'],
            'spitze klammer' => ['https://beispiel.de/<script>'],
            'ftp' => ['ftp://beispiel.de/'],
            'leer' => ['   '],
            'zu lang' => ['https://beispiel.de/' . str_repeat('a', 500)],
        ];
    }

    #[DataProvider('gefaehrlicheAdressen')]
    public function testGefaehrlicheAdressenWerdenAbgewiesen(string $adresse): void
    {
        $this->expectException(KontoFehler::class);
        $this->expectExceptionMessage('link_adresse_ungueltig');

        ProfilLinks::gepruefteAdresse($adresse);
    }

    public function testEineNormaleAdresseGehtDurch(): void
    {
        self::assertSame('https://beispiel.de/mira?x=1', ProfilLinks::gepruefteAdresse(' https://beispiel.de/mira?x=1 '));
    }

    public function testOhneSchemaWirdHttpsErgaenzt(): void
    {
        self::assertSame('https://instagram.com/mira', ProfilLinks::gepruefteAdresse('instagram.com/mira'));
    }

    public function testReihenfolgeUndVerschieben(): void
    {
        $mira = $this->benutzer('Mira');
        $links = new ProfilLinks($this->db);

        $a = $links->hinzufuegen($mira, 'Erster', 'https://a.example.org/');
        $b = $links->hinzufuegen($mira, 'Zweiter', 'https://b.example.org/');
        $c = $links->hinzufuegen($mira, 'Dritter', 'https://c.example.org/');

        $links->verschieben($mira, $c, -1);
        self::assertSame([$a, $c, $b], array_column($links->fuer($mira), 'id'));

        $links->verschieben($mira, $a, -1);
        self::assertSame([$a, $c, $b], array_column($links->fuer($mira), 'id'), 'Der oberste bleibt oben.');

        $links->verschieben($mira, $a, 1);
        self::assertSame([$c, $a, $b], array_column($links->fuer($mira), 'id'));

        self::assertSame('a.example.org', $links->fuer($mira)[1]['rechner']);
    }

    public function testMehrAlsAchtGehenNicht(): void
    {
        $mira = $this->benutzer('Mira');
        $links = new ProfilLinks($this->db);

        for ($i = 1; $i <= ProfilLinks::HOECHSTENS; $i++) {
            $links->hinzufuegen($mira, 'Link ' . $i, 'https://beispiel.de/' . $i);
        }

        $this->expectException(KontoFehler::class);
        $this->expectExceptionMessage('link_zu_viele');
        $links->hinzufuegen($mira, 'Einer zu viel', 'https://beispiel.de/9');
    }

    public function testFremdeVerweiseBleibenUnberuehrt(): void
    {
        $mira = $this->benutzer('Mira');
        $ben = $this->benutzer('Ben');
        $links = new ProfilLinks($this->db);
        $id = $links->hinzufuegen($mira, 'Meins', 'https://beispiel.de/');

        $links->entfernen($ben, $id);
        $links->verschieben($ben, $id, 1);

        self::assertCount(1, $links->fuer($mira));
        self::assertSame([], $links->fuer($ben));
    }

    public function testBeschriftungIstPflicht(): void
    {
        $this->expectException(KontoFehler::class);
        $this->expectExceptionMessage('link_titel_fehlt');
        (new ProfilLinks($this->db))->hinzufuegen($this->benutzer('Mira'), '  ', 'https://beispiel.de/');
    }

    /** Das Profil ist erst oeffentlich, wenn die Person es einschaltet. */
    public function testEinProfilIstZunaechstNichtOeffentlich(): void
    {
        $mira = $this->benutzer('Mira');
        $profile = new Profile($this->db);
        $profile->laden($mira);

        self::assertNull($profile->nachPseudonym('Mira'));

        $profile->speichern($mira, ['oeffentlich' => '1']);
        self::assertSame($mira, $profile->nachPseudonym('Mira')['benutzer_id'] ?? null);

        $profile->speichern($mira, ['oeffentlich' => '']);
        self::assertNull($profile->nachPseudonym('Mira'));
    }
}
