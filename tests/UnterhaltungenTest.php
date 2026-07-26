<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Domain\Account\Konten;
use MeinSlip\Domain\Account\Profile;
use MeinSlip\Domain\Chat\ChatFehler;
use MeinSlip\Domain\Chat\Unterhaltungen;

/**
 * Haelt den Chat zusammen.
 *
 * Die Tests hier sind keine Abdeckung, sondern Zusicherungen: Jeder einzelne
 * steht fuer einen Fehler, der ohne ihn still zurueckkehren koennte — eine
 * Sperre, die nur eine Richtung schliesst; ein Verlauf, den eine fremde Person
 * lesen kann; eine Deklaration, die sich rueckwirkend aendert; eine
 * Drosselung, die es nur im Kommentar gibt.
 */
final class UnterhaltungenTest extends Testfall
{
    /**
     * Eine Sperre ist keine Einbahnstrasse.
     *
     * Wer belaestigt wird, sperrt. Wuerde nur die Richtung der sperrenden
     * Person geprueft, duerfte ausgerechnet die belaestigende weiterschreiben.
     */
    public function testDieSperreWirktInBeidenRichtungen(): void
    {
        $lina = $this->plauderndesKonto('Lina');
        $mara = $this->plauderndesKonto('Mara');

        $chat = $this->chat();
        $unterhaltungId = $chat->eroeffnen($lina, $mara);
        $chat->senden($unterhaltungId, $lina, 'Hallo.');

        // Lina sperrt Mara.
        $this->sperren($lina, $mara);

        $this->assertChatFehler(
            'gesperrt',
            fn () => $chat->senden($unterhaltungId, $mara, 'Trotzdem.'),
            'Die gesperrte Person darf nicht schreiben.'
        );

        $this->assertChatFehler(
            'gesperrt',
            fn () => $chat->senden($unterhaltungId, $lina, 'Ich schon?'),
            'Auch die sperrende Person darf nicht mehr schreiben — sonst waere die Sperre eine Einbahnstrasse.'
        );

        $this->assertChatFehler(
            'gesperrt',
            fn () => $chat->eroeffnen($mara, $lina),
            'Und ein zweiter Faden entsteht auch nicht.'
        );
    }

    /**
     * Der Verlauf bleibt stehen, auch bei Sperre — er ist das Beweismittel.
     */
    public function testDerVerlaufBleibtTrotzSperreLesbar(): void
    {
        $lina = $this->plauderndesKonto('Lina');
        $mara = $this->plauderndesKonto('Mara');

        $chat = $this->chat();
        $unterhaltungId = $chat->eroeffnen($lina, $mara);
        $chat->senden($unterhaltungId, $lina, 'Das steht hier fuer immer.');

        $this->sperren($lina, $mara);

        $nachrichten = $chat->nachrichten($unterhaltungId, $mara);

        self::assertCount(1, $nachrichten);
        self::assertSame('Das steht hier fuer immer.', $nachrichten[0]['text']);
        self::assertNotNull($chat->laden($unterhaltungId, $mara));
    }

    /**
     * Eine fremde Person bekommt null, nicht den Inhalt — und auch keine
     * Auskunft darueber, dass es die Unterhaltung gibt.
     */
    public function testNichtteilnehmerBekommtNullStattInhalt(): void
    {
        $lina = $this->plauderndesKonto('Lina');
        $mara = $this->plauderndesKonto('Mara');
        $fremde = $this->plauderndesKonto('Fremde');

        $chat = $this->chat();
        $unterhaltungId = $chat->eroeffnen($lina, $mara);
        $chat->senden($unterhaltungId, $lina, 'Vertraulich.');

        self::assertNull($chat->laden($unterhaltungId, $fremde));
        self::assertNull($chat->laden(999999, $lina), 'Unbekannt und fremd liefern dasselbe null.');

        $this->assertChatFehler(
            'nicht_teilnehmer',
            fn () => $chat->nachrichten($unterhaltungId, $fremde)
        );

        $this->assertChatFehler(
            'nicht_teilnehmer',
            fn () => $chat->senden($unterhaltungId, $fremde, 'Ich lese mit.')
        );

        $this->assertChatFehler(
            'nicht_teilnehmer',
            fn () => $chat->gelesen($unterhaltungId, $fremde)
        );
    }

    public function testSelbstgespraechScheitert(): void
    {
        $lina = $this->plauderndesKonto('Lina');

        $this->assertChatFehler('selbstgespraech', fn () => $this->chat()->eroeffnen($lina, $lina));
    }

    public function testUnbekannterEmpfaengerScheitert(): void
    {
        $lina = $this->plauderndesKonto('Lina');

        $this->assertChatFehler('empfaenger_unbekannt', fn () => $this->chat()->eroeffnen($lina, 987654));
    }

    public function testUnbekannteUnterhaltungScheitert(): void
    {
        $lina = $this->plauderndesKonto('Lina');

        $this->assertChatFehler('unterhaltung_unbekannt', fn () => $this->chat()->senden(987654, $lina, 'Hallo.'));
    }

    /**
     * Zweimal eroeffnen ergibt eine Unterhaltung — auch mit vertauschten
     * Rollen.
     *
     * Das ist die Leistung des kontext_schluessels. Ohne ihn saessen zwei
     * Leute, die einander schreiben, in zwei getrennten Faeden und saehen die
     * Antwort des anderen nie.
     */
    public function testZweimalEroeffnenLiefertDieselbeUnterhaltung(): void
    {
        $lina = $this->plauderndesKonto('Lina');
        $mara = $this->plauderndesKonto('Mara');

        $chat = $this->chat();
        $erste = $chat->eroeffnen($lina, $mara);

        self::assertSame($erste, $chat->eroeffnen($lina, $mara));
        self::assertSame($erste, $chat->eroeffnen($mara, $lina), 'A->B und B->A sind eine Unterhaltung.');

        self::assertSame(
            1,
            (int) $this->db->wert('SELECT COUNT(*) FROM unterhaltungen'),
            'Es darf wirklich nur eine Zeile geben.'
        );

        self::assertSame(
            $lina,
            $chat->laden($erste, $lina)['eroeffner_id'],
            'Die Rolle der eroeffnenden Person bleibt erhalten — normalisiert wird nur der Schluessel.'
        );
    }

    /**
     * Ohne Angebotsbezug entsteht ein eigener Faden, mit Bezug ein zweiter.
     *
     * Der kontextlose Fall ist der gefaehrliche: NULL gilt in eindeutigen
     * Indizes beider Datenbanken als "immer verschieden". Die 0 im Schluessel
     * ist genau der Grund, warum hier nicht beliebig viele Zeilen entstehen.
     */
    public function testKontextloseUnterhaltungenEntstehenNichtDoppelt(): void
    {
        $lina = $this->plauderndesKonto('Lina');
        $mara = $this->plauderndesKonto('Mara');
        $angebotId = $this->angebot($lina);

        $chat = $this->chat();
        $ohne = $chat->eroeffnen($lina, $mara);
        $mit = $chat->eroeffnen($lina, $mara, $angebotId);

        self::assertNotSame($ohne, $mit, 'Der Angebotsbezug ist Teil des Kontexts.');
        self::assertSame($ohne, $chat->eroeffnen($mara, $lina));
        self::assertSame($ohne, $chat->eroeffnen($lina, $mara, 0));
        self::assertSame($mit, $chat->eroeffnen($mara, $lina, $angebotId));

        self::assertSame(2, (int) $this->db->wert('SELECT COUNT(*) FROM unterhaltungen'));
    }

    /**
     * Die Deklaration ist eine Momentaufnahme und aendert sich nicht
     * rueckwirkend.
     *
     * Ohne diese Zusicherung waere die Chatter-Deklaration dekorativ: Wer
     * heute "Sie schreibt selbst" behauptet und morgen auf "KI" umstellt,
     * haette nie etwas Falsches gesagt, weil die alte Behauptung mitwandert.
     */
    public function testDieDeklarationWirdAlsMomentaufnahmeFestgehalten(): void
    {
        $lina = $this->benutzer('Lina');
        $mara = $this->plauderndesKonto('Mara');

        $profile = new Profile($this->db);
        $profile->deklarationSetzen($lina, Profile::DEKLARATION_PERSON);

        $chat = $this->chat();
        $unterhaltungId = $chat->eroeffnen($lina, $mara);
        $chat->senden($unterhaltungId, $lina, 'Ich schreibe selbst.');

        $profile->deklarationSetzen($lina, Profile::DEKLARATION_KI);
        $chat->senden($unterhaltungId, $lina, 'Jetzt hilft eine Maschine.');

        $nachrichten = $chat->nachrichten($unterhaltungId, $lina);

        self::assertCount(2, $nachrichten);
        self::assertSame('person', $nachrichten[0]['deklaration'], 'Die alte Nachricht behaelt ihre Behauptung.');
        self::assertSame('ki', $nachrichten[1]['deklaration']);
    }

    /**
     * Ohne gesetzte Deklaration wird nicht gesendet.
     */
    public function testOhneDeklarationWirdNichtGesendet(): void
    {
        $lina = $this->benutzer('Lina');
        $mara = $this->plauderndesKonto('Mara');

        $chat = $this->chat();
        $unterhaltungId = $chat->eroeffnen($lina, $mara);

        $this->assertChatFehler('deklaration_fehlt', fn () => $chat->senden($unterhaltungId, $lina, 'Hallo.'));

        self::assertSame(0, (int) $this->db->wert('SELECT COUNT(*) FROM nachrichten'));
    }

    public function testLeererUndZuLangerTextWerdenAbgewiesen(): void
    {
        $lina = $this->plauderndesKonto('Lina');
        $mara = $this->plauderndesKonto('Mara');

        $chat = $this->chat();
        $unterhaltungId = $chat->eroeffnen($lina, $mara);

        $this->assertChatFehler('text_leer', fn () => $chat->senden($unterhaltungId, $lina, "   \n  "));
        $this->assertChatFehler(
            'text_zu_lang',
            fn () => $chat->senden($unterhaltungId, $lina, str_repeat('a', Unterhaltungen::TEXT_MAXLAENGE + 1))
        );
    }

    /**
     * Die Drosselung der Nachrichten greift wirklich.
     *
     * Ohne Vorabpruefung und ohne E-Mail-Bestaetigung ist ein freies
     * Anschreiben ab Tag eins ein Spamziel. Diese Grenze ist keine Kuer.
     */
    public function testDieDrosselungGreiftBeiNachrichten(): void
    {
        $lina = $this->plauderndesKonto('Lina');
        $mara = $this->plauderndesKonto('Mara');

        $chat = $this->chat();
        $unterhaltungId = $chat->eroeffnen($lina, $mara);

        for ($i = 0; $i < Unterhaltungen::NACHRICHTEN_JE_MINUTE; $i++) {
            $chat->senden($unterhaltungId, $lina, 'Nachricht ' . $i);
        }

        $this->assertChatFehler('zu_schnell', fn () => $chat->senden($unterhaltungId, $lina, 'Eine zu viel.'));

        // Die andere Seite hat ihr eigenes Kontingent — die Grenze gilt je
        // Absender, nicht je Unterhaltung.
        self::assertGreaterThan(0, $chat->senden($unterhaltungId, $mara, 'Ich darf noch.'));
    }

    /**
     * Die Drosselung der neuen Unterhaltungen greift — und trifft nur die
     * eroeffnende Seite.
     */
    public function testDieDrosselungGreiftBeiNeuenUnterhaltungen(): void
    {
        $spam = $this->plauderndesKonto('Spammer');
        $chat = $this->chat();

        for ($i = 0; $i < Unterhaltungen::UNTERHALTUNGEN_JE_TAG; $i++) {
            $chat->eroeffnen($spam, $this->zielkonto('Ziel' . $i));
        }

        $letztes = $this->zielkonto('ZielZuViel');
        $this->assertChatFehler('zu_schnell', fn () => $chat->eroeffnen($spam, $letztes));

        // Angeschrieben zu WERDEN kostet kein Kontingent — sonst legte ein
        // Angreifer ein beliebtes Konto still, indem er es dreissigmal
        // anschreibt.
        self::assertGreaterThan(0, $chat->eroeffnen($letztes, $spam));
    }

    /**
     * Eine bestehende Unterhaltung wieder zu oeffnen ist kein neues
     * Anschreiben und laeuft deshalb nicht in die Tagesgrenze.
     */
    public function testEineBestehendeUnterhaltungLaeuftNichtInDieDrosselung(): void
    {
        $spam = $this->plauderndesKonto('Spammer');
        $chat = $this->chat();
        $ziele = [];

        for ($i = 0; $i < Unterhaltungen::UNTERHALTUNGEN_JE_TAG; $i++) {
            $ziele[$i] = $this->zielkonto('Ziel' . $i);
        }

        $erste = $chat->eroeffnen($spam, $ziele[0]);

        for ($i = 1; $i < Unterhaltungen::UNTERHALTUNGEN_JE_TAG; $i++) {
            $chat->eroeffnen($spam, $ziele[$i]);
        }

        $this->assertChatFehler('zu_schnell', fn () => $chat->eroeffnen($spam, $this->zielkonto('ZielZuViel')));

        self::assertSame(
            $erste,
            $chat->eroeffnen($spam, $ziele[0]),
            'Ein laufendes Gespraech wieder zu oeffnen ist kein neues Anschreiben.'
        );
    }

    public function testUngeleseneAnzahlStimmt(): void
    {
        $lina = $this->plauderndesKonto('Lina');
        $mara = $this->plauderndesKonto('Mara');
        $nina = $this->plauderndesKonto('Nina');

        $chat = $this->chat();
        $mitMara = $chat->eroeffnen($mara, $lina);
        $mitNina = $chat->eroeffnen($nina, $lina);

        // Reihenfolge tragend: Der Faden mit Nina wird zuletzt beschrieben und
        // hat die hoehere Kennung. Damit steht er in beiden Faellen vorn — bei
        // gleichem Sekundenstempel wie bei einem Wechsel dazwischen.
        $chat->senden($mitMara, $mara, 'Erstens.');
        $chat->senden($mitMara, $mara, 'Zweitens.');
        $chat->senden($mitMara, $lina, 'Eigene zaehlen nicht.');
        $chat->senden($mitNina, $nina, 'Auch noch.');

        self::assertSame(3, $chat->ungeleseneAnzahl($lina));
        self::assertSame(1, $chat->ungeleseneAnzahl($mara), 'Maras eigene zaehlen bei ihr nicht.');
        self::assertSame(0, $chat->ungeleseneAnzahl($nina));

        $chat->gelesen($mitMara, $lina);

        self::assertSame(1, $chat->ungeleseneAnzahl($lina), 'Nur der gelesene Faden ist abgeraeumt.');

        $uebersicht = $chat->meine($lina);
        self::assertSame(2, $uebersicht['anzahl']);
        self::assertSame($mitNina, $uebersicht['zeilen'][0]['id'], 'Zuletzt beschriebene Unterhaltung zuerst.');
        self::assertSame(1, $uebersicht['zeilen'][0]['ungelesen']);
        self::assertSame('Auch noch.', $uebersicht['zeilen'][0]['letzter_text']);
        self::assertSame($nina, $uebersicht['zeilen'][0]['partner_id']);
    }

    /**
     * verbergen() loescht nie — es setzt einen Zeitpunkt.
     *
     * Der Verlauf ist das Beweismittel des Verfahrens gegen genau diese
     * Nachricht. Wer sie loescht, nimmt der Meldung und der Beschwerde nach
     * Art. 20 DSA ihre Grundlage.
     */
    public function testVerbergenLoeschtNichts(): void
    {
        $lina = $this->plauderndesKonto('Lina');
        $mara = $this->plauderndesKonto('Mara');
        $verwalterin = $this->verwalterin('Chefin');

        $chat = $this->chat();
        $unterhaltungId = $chat->eroeffnen($lina, $mara);
        $nachrichtId = $chat->senden($unterhaltungId, $lina, 'Etwas Verbotenes.');

        $chat->verbergen($nachrichtId, $verwalterin, 'Verstoss gegen die Regeln.');

        self::assertSame(
            1,
            (int) $this->db->wert('SELECT COUNT(*) FROM nachrichten WHERE id = :id', ['id' => $nachrichtId]),
            'Die Zeile bleibt stehen.'
        );
        self::assertSame(
            'Etwas Verbotenes.',
            (string) $this->db->wert('SELECT text FROM nachrichten WHERE id = :id', ['id' => $nachrichtId]),
            'Und der Wortlaut auch — er ist das Beweismittel.'
        );

        $nachrichten = $chat->nachrichten($unterhaltungId, $mara);
        self::assertCount(1, $nachrichten, 'Der Faden behaelt seine Gestalt.');
        self::assertTrue($nachrichten[0]['verborgen']);
        self::assertSame('', $nachrichten[0]['text'], 'Der Text wird serverseitig entfernt, nicht per CSS.');

        self::assertSame(0, $chat->ungeleseneAnzahl($mara), 'Ein unabbaubarer Zaehler waere ein Fehler.');
    }

    /**
     * Verbergen ist eine Beschraenkung: Sie wird protokolliert und zugestellt.
     */
    public function testVerbergenWirdProtokolliertUndZugestellt(): void
    {
        $lina = $this->plauderndesKonto('Lina');
        $mara = $this->plauderndesKonto('Mara');
        $verwalterin = $this->verwalterin('Chefin');

        $chat = $this->chat();
        $unterhaltungId = $chat->eroeffnen($lina, $mara);
        $nachrichtId = $chat->senden($unterhaltungId, $lina, 'Etwas Verbotenes.');

        $chat->verbergen($nachrichtId, $verwalterin, 'Verstoss gegen die Regeln.');
        // Ein zweiter Aufruf darf keine zweite Zustellung erzeugen.
        $chat->verbergen($nachrichtId, $verwalterin, 'Verstoss gegen die Regeln.');

        $zustellungen = $this->db->alle(
            'SELECT art, gegenstand_art, gegenstand_id, begruendung FROM benachrichtigungen WHERE benutzer_id = :b',
            ['b' => $lina]
        );

        self::assertCount(1, $zustellungen);
        self::assertSame(Unterhaltungen::ART_VERBORGEN, $zustellungen[0]['art']);
        self::assertSame(Unterhaltungen::GEGENSTAND_NACHRICHT, $zustellungen[0]['gegenstand_art']);
        self::assertSame($nachrichtId, (int) $zustellungen[0]['gegenstand_id']);
        self::assertStringContainsString('Verstoss', (string) $zustellungen[0]['begruendung']);

        self::assertSame(
            1,
            (int) $this->db->wert(
                'SELECT COUNT(*) FROM verwaltungs_ereignisse WHERE handlung = :h',
                ['h' => Unterhaltungen::ART_VERBORGEN]
            )
        );
    }

    /**
     * Ohne Verwaltungsrecht wird nichts verborgen — und ohne Begruendung auch
     * nicht.
     */
    public function testVerbergenBrauchtVerwaltungsrechtUndBegruendung(): void
    {
        $lina = $this->plauderndesKonto('Lina');
        $mara = $this->plauderndesKonto('Mara');
        $verwalterin = $this->verwalterin('Chefin');

        $chat = $this->chat();
        $unterhaltungId = $chat->eroeffnen($lina, $mara);
        $nachrichtId = $chat->senden($unterhaltungId, $lina, 'Etwas Verbotenes.');

        $this->assertVerwaltungsFehler(
            'kein_verwaltungsrecht',
            fn () => $chat->verbergen($nachrichtId, $mara, 'Gefaellt mir nicht.')
        );

        $this->assertVerwaltungsFehler(
            'begruendung_fehlt',
            fn () => $chat->verbergen($nachrichtId, $verwalterin, '   ')
        );

        $this->assertVerwaltungsFehler(
            'nachricht_unbekannt',
            fn () => $chat->verbergen(987654, $verwalterin, 'Begruendung.')
        );

        self::assertNull(
            $this->db->wert('SELECT verborgen_am FROM nachrichten WHERE id = :id', ['id' => $nachrichtId]),
            'Keine der abgewiesenen Anfragen hat etwas verborgen.'
        );
        self::assertSame(0, (int) $this->db->wert('SELECT COUNT(*) FROM benachrichtigungen'));
    }

    /**
     * Nachladen ab einer Kennung liefert nur das Neue.
     */
    public function testNachladenAbEinerKennungLiefertNurDasNeue(): void
    {
        $lina = $this->plauderndesKonto('Lina');
        $mara = $this->plauderndesKonto('Mara');

        $chat = $this->chat();
        $unterhaltungId = $chat->eroeffnen($lina, $mara);
        $erste = $chat->senden($unterhaltungId, $lina, 'Eins.');
        $chat->senden($unterhaltungId, $mara, 'Zwei.');

        $neu = $chat->nachrichten($unterhaltungId, $lina, $erste);

        self::assertCount(1, $neu);
        self::assertSame('Zwei.', $neu[0]['text']);
        self::assertFalse($neu[0]['eigene']);
        self::assertSame([], $chat->nachrichten($unterhaltungId, $lina, $neu[0]['id']));
    }

    // --- Hilfen -------------------------------------------------------------

    private function chat(): Unterhaltungen
    {
        return new Unterhaltungen($this->db);
    }

    /** Ein Konto, das schreiben darf — also eines mit gesetzter Deklaration. */
    private function plauderndesKonto(string $pseudonym): int
    {
        $id = $this->benutzer($pseudonym);
        (new Profile($this->db))->deklarationSetzen($id, Profile::DEKLARATION_PERSON);

        return $id;
    }

    private function verwalterin(string $pseudonym): int
    {
        $id = $this->benutzer($pseudonym);
        (new Konten($this->db))->faehigkeitFreischalten($id, Konten::FAEHIGKEIT_VERWALTEN, 'Test');

        return $id;
    }

    /**
     * Ein Konto, das nur angeschrieben wird.
     *
     * Bewusst nicht ueber Testfall::benutzer(): Das dortige
     * password_hash(PASSWORD_DEFAULT) kostet rund eine Fuenftelsekunde je
     * Konto — und die Drosselungstests brauchen mehr als dreissig davon. Ein
     * Konto, das sich nie anmeldet, braucht keinen echten Hash; das Feld ist
     * nur NOT NULL.
     */
    private function zielkonto(string $pseudonym): int
    {
        return $this->db->einfuegen('benutzer', [
            'pseudonym' => $pseudonym,
            'email' => strtolower($pseudonym) . '@beispiel.test',
            'passwort_hash' => 'nicht-anmeldbar',
            'status' => 'aktiv',
            'sprache' => 'de-DE',
            'land' => 'DE',
            'angelegt_am' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function sperren(int $benutzerId, int $gesperrterId): void
    {
        $this->db->einfuegen('sperren', [
            'benutzer_id' => $benutzerId,
            'gesperrter_id' => $gesperrterId,
            'grund' => 'Test',
            'angelegt_am' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /** Ein Angebot als Gespraechskontext. */
    private function angebot(int $verkaeuferId): int
    {
        $kategorieId = (int) $this->db->wert('SELECT id FROM kategorien ORDER BY id LIMIT 1');

        return $this->db->einfuegen('angebote', [
            'verkaeufer_id' => $verkaeuferId,
            'kategorie_id' => $kategorieId,
            'titel' => 'Testangebot',
            'grundpreis_cent' => 1000,
            'waehrung' => 'EUR',
            'status' => 'aktiv',
            'angelegt_am' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function assertChatFehler(string $schluessel, callable $tun, string $hinweis = ''): void
    {
        try {
            $tun();
        } catch (ChatFehler $fehler) {
            self::assertSame($schluessel, $fehler->schluessel(), $hinweis);

            return;
        }

        self::fail('Erwartet wurde ein ChatFehler mit dem Schluessel "' . $schluessel . '". ' . $hinweis);
    }

    private function assertVerwaltungsFehler(string $schluessel, callable $tun): void
    {
        try {
            $tun();
        } catch (\MeinSlip\Domain\Admin\VerwaltungsFehler $fehler) {
            self::assertSame($schluessel, $fehler->schluessel());

            return;
        }

        self::fail('Erwartet wurde ein VerwaltungsFehler mit dem Schluessel "' . $schluessel . '".');
    }
}
