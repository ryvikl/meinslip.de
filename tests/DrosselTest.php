<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Domain\Chat\Unterhaltungen;
use MeinSlip\Domain\Trust\Drossel;

/**
 * Die Versuchsgrenzen — und die zwei Luecken, die mit ihnen geschlossen wurden.
 */
final class DrosselTest extends Testfall
{
    public function testNachFuenfFehlversuchenIstSchluss(): void
    {
        $drossel = new Drossel($this->db);

        for ($i = 0; $i < 5; $i++) {
            self::assertFalse($drossel->erschoepft(Drossel::ANMELDUNG_KONTO, 'mira@beispiel.test'));
            $drossel->zaehlen(Drossel::ANMELDUNG_KONTO, 'mira@beispiel.test');
        }

        self::assertTrue($drossel->erschoepft(Drossel::ANMELDUNG_KONTO, 'mira@beispiel.test'));
        self::assertTrue($drossel->erschoepft(Drossel::ANMELDUNG_KONTO, ' MIRA@beispiel.test '), 'Schreibweise darf die Grenze nicht umgehen.');
        self::assertFalse($drossel->erschoepft(Drossel::ANMELDUNG_KONTO, 'jemand-anders@beispiel.test'));
        self::assertFalse($drossel->erschoepft(Drossel::ANMELDUNG_IP, 'mira@beispiel.test'), 'Zwecke zaehlen getrennt.');
    }

    public function testEineGelungeneAnmeldungSetztZurueck(): void
    {
        $drossel = new Drossel($this->db);

        for ($i = 0; $i < 5; $i++) {
            $drossel->zaehlen(Drossel::ANMELDUNG_KONTO, 'mira@beispiel.test');
        }

        $drossel->vergessen(Drossel::ANMELDUNG_KONTO, 'mira@beispiel.test');

        self::assertFalse($drossel->erschoepft(Drossel::ANMELDUNG_KONTO, 'mira@beispiel.test'));
    }

    public function testAlteVersucheZaehlenNichtMehr(): void
    {
        $drossel = new Drossel($this->db);

        for ($i = 0; $i < 5; $i++) {
            $drossel->zaehlen(Drossel::ANMELDUNG_KONTO, 'mira@beispiel.test');
        }

        $this->db->ausfuehren('UPDATE versuche SET angelegt_am = :z', ['z' => gmdate('Y-m-d H:i:s', time() - 7200)]);

        self::assertFalse($drossel->erschoepft(Drossel::ANMELDUNG_KONTO, 'mira@beispiel.test'));
        self::assertSame(5, $drossel->abgelaufeneEntfernen());
    }

    public function testInDerTabelleStehtKeinKlartext(): void
    {
        (new Drossel($this->db))->zaehlen(Drossel::ANMELDUNG_IP, '203.0.113.7');

        $schluessel = (string) $this->db->wert('SELECT schluessel FROM versuche');

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $schluessel);
        self::assertNotSame(hash('sha256', '203.0.113.7'), $schluessel, 'Ein blanker Hash einer IP ist in Minuten durchprobiert.');
    }

    public function testOhneMerkmalWirdNiemandAusgesperrt(): void
    {
        $drossel = new Drossel($this->db);
        $drossel->zaehlen(Drossel::ANMELDUNG_IP, null);

        self::assertFalse($drossel->erschoepft(Drossel::ANMELDUNG_IP, null));
        self::assertSame(0, (int) $this->db->wert('SELECT COUNT(*) FROM versuche'));
    }

    /**
     * Der Chat haengte jeden beliebigen Angebotsbezug an — und zeigte damit
     * den Titel fremder Entwuerfe.
     */
    public function testFremdesAngebotWirdNichtAlsBezugUebernommen(): void
    {
        $anna = $this->benutzer('Anna');
        $ben = $this->benutzer('Ben');
        $clara = $this->benutzer('Clara');

        $kategorie = (int) $this->db->wert('SELECT id FROM kategorien ORDER BY id LIMIT 1');
        $vorlage = $this->db->eine('SELECT * FROM angebote LIMIT 1');
        self::assertNull($vorlage, 'Der Test legt sein Angebot selbst an.');

        $spalten = [];
        foreach ($this->db->alle('PRAGMA table_info(angebote)') as $spalte) {
            if ((int) $spalte['pk'] === 1) {
                continue;
            }
            $spalten[(string) $spalte['name']] = (int) $spalte['notnull'] === 1
                ? (str_contains(strtolower((string) $spalte['type']), 'int') ? 0 : '')
                : null;
        }
        $spalten = array_merge($spalten, [
            'verkaeufer_id' => $clara,
            'kategorie_id' => $kategorie,
            'status' => 'entwurf',
            'titel' => 'Geheimer Entwurf',
            'angelegt_am' => gmdate('Y-m-d H:i:s'),
        ]);
        $fremd = $this->db->einfuegen('angebote', $spalten);

        $chat = new Unterhaltungen($this->db);
        $id = $chat->eroeffnen($anna, $ben, $fremd);

        self::assertNull($this->db->wert('SELECT angebot_id FROM unterhaltungen WHERE id = :id', ['id' => $id]));
    }
}
