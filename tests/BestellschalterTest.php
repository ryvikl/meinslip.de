<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Core\Database;
use MeinSlip\Core\Env;
use MeinSlip\Core\Migrator;
use MeinSlip\Domain\Ledger\Hauptbuch;
use MeinSlip\Domain\Order\Bestellungen;
use MeinSlip\Domain\Order\BestellvorgangGesperrt;
use MeinSlip\Domain\Order\Preisrechner;
use PHPUnit\Framework\TestCase;

/**
 * Der Verriegelungsschalter des Bestellvorgangs.
 *
 * Diese Klasse erbt bewusst NICHT von Testfall: Testfall::setUp() schaltet
 * BESTELLVORGANG_AKTIV auf 'true', damit die uebrige Bestellsuite gegen den
 * freigeschalteten Schalter faehrt. Hier soll aber gerade der gesperrte
 * Zustand geprueft werden — deshalb wird die Datenbank von Hand aufgebaut,
 * genau so wie Testfall::setUp() es tut.
 *
 * Zwei Zusicherungen zusammen:
 *  - Ohne Schalter entsteht keine Bestellung, und es bewegt sich kein Cent.
 *  - Mit Schalter laeuft derselbe Vorgang unveraendert durch. Die Sperre ist
 *    eine Verriegelung, kein Abbau — was verriegelt ist, ist noch da.
 */
final class BestellschalterTest extends TestCase
{
    private Database $db;

    private Hauptbuch $hauptbuch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::imArbeitsspeicher();
        (new Migrator($this->db, dirname(__DIR__) . '/database/migrations'))->hoch();

        $this->hauptbuch = new Hauptbuch($this->db);

        // Env haelt seine Werte statisch ueber alle Testklassen hinweg. Ein
        // vorher gelaufener Testfall hat den Schalter bereits auf 'true'
        // gesetzt, deshalb wird der gesperrte Ausgangszustand hier
        // ausdruecklich hergestellt statt vorausgesetzt.
        Env::setzen('BESTELLVORGANG_AKTIV', 'false');
    }

    /** Die Kernzusicherung: ohne Freigabe entsteht keine Bestellung. */
    public function testAnlegenIstOhneSchalterGesperrt(): void
    {
        $kaeufer = $this->benutzer('Kaeuferin');
        $verkaeufer = $this->benutzer('Verkaeuferin');
        $this->aufladen($kaeufer, 10000);

        $this->expectException(BestellvorgangGesperrt::class);

        $this->bestellungen()->anlegen($kaeufer, $verkaeufer, [$this->position(5950)]);
    }

    /**
     * Die Meldung muss den Grund nennen, nicht nur die Tatsache. Wer sie
     * liest, soll die KWG-Frage sehen und den Schalter nicht wegklicken.
     */
    public function testDieAusnahmeNenntDenRechtsgrund(): void
    {
        $kaeufer = $this->benutzer('Kaeuferin');
        $verkaeufer = $this->benutzer('Verkaeuferin');

        $this->expectException(BestellvorgangGesperrt::class);
        $this->expectExceptionMessageMatches('/KWG/');

        $this->bestellungen()->anlegen($kaeufer, $verkaeufer, [$this->position(5950)]);
    }

    /**
     * Die Sperre greift vor jeder Pruefung und vor jeder Buchung. Eine
     * abgewiesene Bestellung darf keine Spur hinterlassen — weder eine Zeile
     * in 'bestellungen' noch einen Vorgang im Hauptbuch.
     */
    public function testAbgewieseneBestellungLaesstDasHauptbuchUnberuehrt(): void
    {
        $kaeufer = $this->benutzer('Kaeuferin');
        $verkaeufer = $this->benutzer('Verkaeuferin');

        // Voll gedeckt, damit ausschliesslich der Schalter die Bestellung
        // aufhaelt und nicht etwa fehlendes Guthaben.
        $this->aufladen($kaeufer, 10000);
        $vorgaengeVorher = $this->vorgangsZahl();

        try {
            $this->bestellungen()->anlegen($kaeufer, $verkaeufer, [$this->position(5950)]);
            self::fail('Der verriegelte Bestellvorgang haette BestellvorgangGesperrt werfen muessen.');
        } catch (BestellvorgangGesperrt) {
            // Erwartet — geprueft wird, was danach NICHT in der Datenbank steht.
        }

        self::assertSame(
            $vorgaengeVorher,
            $this->vorgangsZahl(),
            'Die abgewiesene Bestellung hat einen Hauptbuchvorgang erzeugt.'
        );
        self::assertSame(
            0,
            (int) $this->db->wert('SELECT COUNT(*) FROM bestellungen'),
            'Die abgewiesene Bestellung wurde trotzdem angelegt.'
        );
        self::assertSame(
            0,
            $this->hauptbuch->abweichung(),
            'Das Hauptbuch ist nicht ausgeglichen — es ist Geld entstanden oder verschwunden.'
        );
        self::assertSame([], $this->hauptbuch->unausgeglicheneVorgaenge());
        self::assertSame(
            10000,
            $this->hauptbuch->guthaben($kaeufer),
            'Das Guthaben der Kaeuferin wurde angetastet, obwohl die Bestellung abgewiesen wurde.'
        );
    }

    /**
     * Die Gegenprobe, und der eigentliche Zweck der Verriegelung: mit
     * gesetztem Schalter laeuft der vollstaendige Vorgang durch. Am Tag der
     * Freischaltung wird nichts Neues in Betrieb genommen.
     */
    public function testMitSchalterLaeuftEineGueltigeBestellungDurch(): void
    {
        Env::setzen('BESTELLVORGANG_AKTIV', 'true');

        $kaeufer = $this->benutzer('Kaeuferin');
        $verkaeufer = $this->benutzer('Verkaeuferin');
        $this->aufladen($kaeufer, 10000);

        $bestellungId = $this->bestellungen()->anlegen($kaeufer, $verkaeufer, [$this->position(5950)]);

        $bestellung = $this->bestellungen()->laden($bestellungId);

        self::assertSame('treuhand_gebunden', $bestellung['zustand']);
        self::assertSame(5950, (int) $bestellung['summe_verkauf_cent']);
        self::assertSame(4050, $this->hauptbuch->guthaben($kaeufer));
        self::assertSame(0, $this->hauptbuch->abweichung());
        self::assertSame([], $this->hauptbuch->unausgeglicheneVorgaenge());
    }

    // --- Aufbauhilfen, gleichlautend zu Testfall ---------------------------

    private function bestellungen(int $provisionssatz = 1500): Bestellungen
    {
        return new Bestellungen($this->db, $this->hauptbuch, new Preisrechner($provisionssatz));
    }

    private function benutzer(string $pseudonym): int
    {
        return $this->db->einfuegen('benutzer', [
            'pseudonym' => $pseudonym,
            'email' => strtolower($pseudonym) . '@beispiel.test',
            'passwort_hash' => password_hash('geheim-fuer-tests', PASSWORD_DEFAULT),
            'status' => 'aktiv',
            'sprache' => 'de-DE',
            'land' => 'DE',
            'homescreen_name' => null,
            'push_vorschau' => 0,
            'angelegt_am' => gmdate('Y-m-d H:i:s'),
            'zuletzt_aktiv_am' => null,
        ]);
    }

    private function aufladen(int $benutzerId, int $cent): void
    {
        $this->hauptbuch->buchen(
            art: 'aufladung',
            buchungen: [
                ['konto_id' => $this->hauptbuch->plattformkonto(Hauptbuch::KONTO_ZAHLUNGSEINGANG), 'betrag_cent' => -$cent],
                ['konto_id' => $this->hauptbuch->benutzerkonto($benutzerId, Hauptbuch::KONTO_GUTHABEN), 'betrag_cent' => $cent],
            ],
            bezugArt: 'aufladung',
            bezugId: $benutzerId,
            beschreibung: 'Testaufladung'
        );
    }

    /** @return array<string,mixed> */
    private function position(int $bruttoCent, string $bezeichnung = 'Testartikel'): array
    {
        return [
            'angebot_id' => null,
            'bezeichnung' => $bezeichnung,
            'brutto_cent' => $bruttoCent,
            'menge' => 1,
            'spezifikationen' => [[
                'schluessel' => 'tragedauer',
                'bezeichnung' => 'Tragedauer',
                'wert' => '3 Tage',
                'aufpreis_cent' => 0,
            ]],
        ];
    }

    private function vorgangsZahl(): int
    {
        return (int) $this->db->wert('SELECT COUNT(*) FROM hauptbuch_vorgaenge');
    }
}
