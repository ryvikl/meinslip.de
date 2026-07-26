<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Domain\Account\KontoFehler;
use MeinSlip\Domain\Account\Profile;

/**
 * Haelt das Profil und die Chatter-Deklaration zusammen.
 *
 * Zwei Zusicherungen tragen hier mehr als der Rest: Der Anzeigename faellt auf
 * das Pseudonym zurueck (sonst zeigt die Oberflaeche leere Namen), und die
 * Deklaration hat KEINEN Vorgabewert (sonst behauptet die Plattform fuer jedes
 * Konto "Sie schreibt selbst", ohne dass es jemand gesagt haette).
 */
final class ProfileTest extends Testfall
{
    public function testLadenLegtAnUndFaelltAufDasPseudonymZurueck(): void
    {
        $lina = $this->benutzer('Lina');

        $profil = $this->profile()->laden($lina);

        self::assertSame($lina, $profil['benutzer_id']);
        self::assertNull($profil['anzeigename']);
        self::assertSame('Lina', $profil['name'], 'Ohne Anzeigename zeigt die Seite das Pseudonym.');
        self::assertFalse($profil['oeffentlich'], 'Ein neu entstandenes Profil ist nicht oeffentlich.');
    }

    public function testAnzeigenameGewinntUeberDasPseudonym(): void
    {
        $lina = $this->benutzer('Lina');
        $profile = $this->profile();

        $profile->speichern($lina, ['anzeigename' => '  Lina L.  ']);

        self::assertSame('Lina L.', $profile->laden($lina)['name']);
    }

    public function testZweimalLadenLegtNurEinProfilAn(): void
    {
        $lina = $this->benutzer('Lina');
        $profile = $this->profile();

        $profile->laden($lina);
        $profile->laden($lina);

        self::assertSame(
            1,
            (int) $this->db->wert('SELECT COUNT(*) FROM profile WHERE benutzer_id = :b', ['b' => $lina]),
            'Das Verhaeltnis zu benutzer ist 1:1 — ein zweites Profil waere ein zweiter Satz Behauptungen.'
        );
    }

    public function testLadenWeistEinUnbekanntesKontoAb(): void
    {
        $this->assertKontoFehler('benutzer_unbekannt', fn () => $this->profile()->laden(987654));
    }

    /**
     * Der Kern der Deklarationspflicht: Ohne Angabe steht dort NICHTS.
     *
     * Ein DEFAULT 'person' im Schema wuerde die staerkste Behauptung als
     * Standard einschleichen — die Plattform behauptete dann fuer jedes Konto
     * etwas, das niemand gesagt hat.
     */
    public function testDieDeklarationHatKeinenVorgabewert(): void
    {
        $lina = $this->benutzer('Lina');
        $profile = $this->profile();

        $profile->laden($lina);

        self::assertNull($profile->deklaration($lina));
        self::assertNull($profile->laden($lina)['chat_deklaration']);
    }

    public function testDeklarationSetzenPrueftDenWert(): void
    {
        $lina = $this->benutzer('Lina');
        $profile = $this->profile();

        $profile->deklarationSetzen($lina, Profile::DEKLARATION_TEAM);
        self::assertSame('team', $profile->deklaration($lina));

        $this->assertKontoFehler(
            'deklaration_unbekannt',
            fn () => $profile->deklarationSetzen($lina, 'vielleicht')
        );
        self::assertSame('team', $profile->deklaration($lina), 'Die abgewiesene Angabe hat nichts ueberschrieben.');
    }

    /**
     * Die Deklaration ist ueber speichern() nicht erreichbar.
     *
     * Sie ist eine Behauptung ueber die Urheberschaft, kein Profilfeld. Waere
     * sie eines, liesse sich ihre Pruefung umgehen, indem man sie als Feld
     * unter anderen mitschickt.
     */
    public function testDieDeklarationIstUeberSpeichernNichtErreichbar(): void
    {
        $lina = $this->benutzer('Lina');

        $this->assertKontoFehler(
            'feld_unbekannt',
            fn () => $this->profile()->speichern($lina, ['chat_deklaration' => 'person'])
        );
    }

    /**
     * Unbekannte Felder werden abgewiesen, nicht ueberlesen.
     *
     * Ein ueberlesener Tippfehler hiesse: Die Person speichert, die Seite
     * bestaetigt, der Text ist trotzdem weg — und bemerkt wird es nie.
     */
    public function testSpeichernWeistUnbekannteFelderAb(): void
    {
        $lina = $this->benutzer('Lina');

        $this->assertKontoFehler(
            'feld_unbekannt',
            fn () => $this->profile()->speichern($lina, ['anzeigenname' => 'Tippfehler'])
        );
    }

    public function testSpeichernWeistEinenZuLangenAnzeigenamenAb(): void
    {
        $lina = $this->benutzer('Lina');

        $this->assertKontoFehler(
            'anzeigename_zu_lang',
            fn () => $this->profile()->speichern($lina, ['anzeigename' => str_repeat('a', 61)])
        );

        self::assertSame(
            0,
            (int) $this->db->wert('SELECT COUNT(*) FROM profile WHERE benutzer_id = :b', ['b' => $lina]),
            'Eine verworfene Eingabe legt kein Profil an.'
        );
    }

    /**
     * nachPseudonym() ist die OEFFENTLICHE Nachschlage — sie liefert nur, was
     * wirklich oeffentlich sein soll.
     */
    public function testNachPseudonymLiefertNurOeffentlicheProfile(): void
    {
        $lina = $this->benutzer('Lina');
        $profile = $this->profile();

        $profile->laden($lina);
        self::assertNull($profile->nachPseudonym('Lina'), 'oeffentlich = 0 ist keine oeffentliche Seite.');

        $profile->speichern($lina, ['oeffentlich' => '1', 'vorstellung' => 'Hallo.']);

        $oeffentlich = $profile->nachPseudonym('Lina');
        self::assertNotNull($oeffentlich);
        self::assertSame('Hallo.', $oeffentlich['vorstellung']);
        self::assertSame('Lina', $oeffentlich['name']);
    }

    public function testNachPseudonymLiefertGesperrteKontenNicht(): void
    {
        $lina = $this->benutzer('Lina');
        $profile = $this->profile();

        $profile->speichern($lina, ['oeffentlich' => '1']);
        self::assertNotNull($profile->nachPseudonym('Lina'));

        $this->db->ausfuehren("UPDATE benutzer SET status = 'gesperrt' WHERE id = :b", ['b' => $lina]);

        self::assertNull(
            $profile->nachPseudonym('Lina'),
            'Eine Kontosperre muss die oeffentliche Seite mitnehmen.'
        );
    }

    public function testNachPseudonymLiefertNullFuerUnbekannteNamen(): void
    {
        self::assertNull($this->profile()->nachPseudonym('GibtEsNicht'));
        self::assertNull($this->profile()->nachPseudonym('   '));
    }

    // --- Hilfen -------------------------------------------------------------

    private function profile(): Profile
    {
        return new Profile($this->db);
    }

    private function assertKontoFehler(string $schluessel, callable $tun): void
    {
        try {
            $tun();
        } catch (KontoFehler $fehler) {
            self::assertSame($schluessel, $fehler->schluessel());

            return;
        }

        self::fail('Erwartet wurde ein KontoFehler mit dem Schluessel "' . $schluessel . '".');
    }
}
