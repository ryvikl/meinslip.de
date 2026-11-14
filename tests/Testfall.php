<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Core\Database;
use MeinSlip\Core\Env;
use MeinSlip\Core\Migrator;
use MeinSlip\Domain\Ledger\Hauptbuch;
use MeinSlip\Domain\Order\Bestellungen;
use MeinSlip\Domain\Order\Preisrechner;
use PHPUnit\Framework\TestCase;

/**
 * Basisklasse: frische Datenbank im Arbeitsspeicher je Test, mit allen
 * Migrationen. Dadurch pruefen die Tests dasselbe Schema, das auch produktiv
 * entsteht — und nicht ein von Hand nachgebautes.
 */
abstract class Testfall extends TestCase
{
    /**
     * Fertiger Hash von 'geheim-fuer-tests' mit den geringsten bcrypt-Kosten.
     *
     * Vorher rechnete jedes Testkonto password_hash(PASSWORD_DEFAULT). Seit
     * PHP 8.4 liegen die Standardkosten bei 12 statt 10 — das Vierfache. Die
     * Suite brauchte dadurch auf 8.4 und 8.5 rund 105 Sekunden statt 29 auf
     * 8.2, und genau auf diese Laeufe wartet jedes Deployment. Die Helfer
     * pruefen kein Passwort, sie brauchen nur eine gueltige Spalte. Wo es auf
     * das echte Verfahren ankommt (KontenTest), laeuft weiter Konten selbst.
     */
    public const PASSWORT_HASH = '$2y$04$G90oFfVl7sXi3KVzkhLjPuyEosF69gKAcnFgTi7LUoPG.Yf7CGeFm';

    protected Database $db;

    protected Hauptbuch $hauptbuch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::imArbeitsspeicher();
        (new Migrator($this->db, dirname(__DIR__) . '/database/migrations'))->hoch();

        $this->hauptbuch = new Hauptbuch($this->db);

        // Der Bestellvorgang ist produktiv verriegelt (KWG, siehe
        // Bestellungen::pruefeFreigabe). Hier laeuft er eingeschaltet: Damit
        // faehrt die komplette bestehende Bestellsuite dauerhaft gegen den
        // freigeschalteten Schalter, statt gegen den gesperrten. Der Tag der
        // produktiven Freischaltung ist dadurch durch die gesamte Testsuite
        // abgedeckt statt durch einen Kommentar — es wird an ihm nichts
        // Ungetestetes in Betrieb genommen.
        //
        // Env haelt seine Werte statisch ueber alle Tests hinweg. Wer den
        // gesperrten Zustand pruefen will, muss ihn deshalb ausdruecklich
        // setzen (Env::setzen(..., 'false')) — sich auf das Fehlen des
        // Schluessels zu verlassen, haengt von der Testreihenfolge ab.
        // Genau das tut tests/BestellschalterTest.php.
        Env::setzen('BESTELLVORGANG_AKTIV', 'true');
    }

    protected function bestellungen(int $provisionssatz = 1500): Bestellungen
    {
        return new Bestellungen($this->db, $this->hauptbuch, new Preisrechner($provisionssatz));
    }

    /** Legt ein Konto an und gibt seine Kennung zurueck. */
    protected function benutzer(string $pseudonym): int
    {
        return $this->db->einfuegen('benutzer', [
            'pseudonym' => $pseudonym,
            'email' => strtolower($pseudonym) . '@beispiel.test',
            'passwort_hash' => self::PASSWORT_HASH,
            'status' => 'aktiv',
            'sprache' => 'de-DE',
            'land' => 'DE',
            'homescreen_name' => null,
            'push_vorschau' => 0,
            'angelegt_am' => gmdate('Y-m-d H:i:s'),
            'zuletzt_aktiv_am' => null,
        ]);
    }

    /** Laedt Guthaben auf ein Konto — der Gegenposten ist der Zahlungseingang. */
    protected function aufladen(int $benutzerId, int $cent): void
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

    /**
     * Eine Bestellposition mit gueltiger Spezifikation.
     *
     * @return array<string,mixed>
     */
    protected function position(int $bruttoCent, string $bezeichnung = 'Testartikel'): array
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

    /** Stellt sicher, dass das gesamte Hauptbuch ausgeglichen ist. */
    protected function assertHauptbuchAusgeglichen(): void
    {
        self::assertSame(
            0,
            $this->hauptbuch->abweichung(),
            'Das Hauptbuch ist nicht ausgeglichen — es ist Geld entstanden oder verschwunden.'
        );
        self::assertSame(
            [],
            $this->hauptbuch->unausgeglicheneVorgaenge(),
            'Mindestens ein Vorgang summiert sich nicht zu null.'
        );
    }
}
