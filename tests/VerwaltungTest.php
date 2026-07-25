<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Domain\Account\Konten;
use MeinSlip\Domain\Admin\Verwaltung;
use MeinSlip\Domain\Admin\VerwaltungsFehler;
use MeinSlip\Domain\Ledger\Hauptbuch;

final class VerwaltungTest extends Testfall
{
    private Verwaltung $verwaltung;

    private Konten $konten;

    protected function setUp(): void
    {
        parent::setUp();
        $this->verwaltung = new Verwaltung($this->db);
        $this->konten = new Konten($this->db);
    }

    // --- Kennzahlen --------------------------------------------------------

    public function testKennzahlenSindBeiLeererDatenbankUeberallNull(): void
    {
        $kennzahlen = $this->verwaltung->kennzahlen();

        self::assertSame(0, $kennzahlen['konten_gesamt']);
        self::assertSame(0, $kennzahlen['konten_neu_7_tage']);
        self::assertSame(0, $kennzahlen['konten_gesperrt']);
        self::assertSame(0, $kennzahlen['meldungen_offen']);
        self::assertSame(0, $kennzahlen['meldungen_frist_ueberschritten']);
        self::assertSame(0, $kennzahlen['pruefungen_offen']);
        self::assertSame(0, $kennzahlen['hauptbuch_abweichung_cent']);
        self::assertSame(0, $kennzahlen['hauptbuch_unausgeglichene_vorgaenge']);
        self::assertTrue($kennzahlen['hauptbuch_in_ordnung']);

        // Jeder Status ist mit 0 vorbelegt, damit die Uebersicht eine
        // vollstaendige Tabelle zeichnen kann statt Luecken zu zeigen.
        self::assertSame([0, 0, 0, 0, 0], array_values($kennzahlen['angebote_je_status']));
        self::assertSame(Verwaltung::ANGEBOTSSTATUS, array_keys($kennzahlen['angebote_je_status']));
        self::assertCount(14, $kennzahlen['bestellungen_je_zustand']);
        self::assertSame([0], array_values(array_unique($kennzahlen['bestellungen_je_zustand'])));
    }

    public function testKennzahlenZaehlenNeueKontenUndFristueberschreitungen(): void
    {
        $frisch = $this->benutzer('Frisch');
        $alt = $this->benutzer('Alt');
        // Feste Zeitpunkte statt der Systemuhr: Sonst haengt das Ergebnis am
        // Tag, an dem der Test laeuft.
        $this->db->ausfuehren(
            'UPDATE benutzer SET angelegt_am = :a WHERE id = :id',
            ['a' => '2026-07-24 08:00:00', 'id' => $frisch]
        );
        $this->db->ausfuehren(
            'UPDATE benutzer SET angelegt_am = :a WHERE id = :id',
            ['a' => '2026-01-01 00:00:00', 'id' => $alt]
        );

        $this->meldung($frisch, Verwaltung::MELDUNG_OFFEN, '2026-07-01 00:00:00');
        $this->meldung($frisch, Verwaltung::MELDUNG_ERLEDIGT, '2026-07-01 00:00:00');

        $kennzahlen = $this->verwaltung->kennzahlen('2026-07-25 12:00:00');

        self::assertSame(2, $kennzahlen['konten_gesamt']);
        self::assertSame(1, $kennzahlen['konten_neu_7_tage']);
        self::assertSame(1, $kennzahlen['meldungen_offen']);
        // Die erledigte Meldung zaehlt nicht mehr mit, auch wenn ihre Frist
        // laengst verstrichen ist.
        self::assertSame(1, $kennzahlen['meldungen_frist_ueberschritten']);
    }

    /**
     * Die wichtigste Zahl des ganzen Bereichs: Eine Abweichung bedeutet, dass
     * Geld entstanden oder verschwunden ist.
     */
    public function testKennzahlenMeldenEineHauptbuchAbweichung(): void
    {
        $kaeufer = $this->benutzer('Kaeuferin');

        // Bewusst an Hauptbuch::buchen vorbei — nur so laesst sich der Fall
        // ueberhaupt herstellen, den die Uebersicht sichtbar machen soll.
        $vorgangId = $this->db->einfuegen('hauptbuch_vorgaenge', [
            'art' => 'kaputt',
            'bezug_art' => null,
            'bezug_id' => null,
            'beschreibung' => 'Halbe Buchung',
            'idempotenz_schluessel' => null,
            'angelegt_am' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->db->einfuegen('hauptbuch_buchungen', [
            'vorgang_id' => $vorgangId,
            'konto_id' => $this->hauptbuch->benutzerkonto($kaeufer, Hauptbuch::KONTO_GUTHABEN),
            'betrag_cent' => 500,
            'waehrung' => 'EUR',
            'angelegt_am' => gmdate('Y-m-d H:i:s'),
        ]);

        $kennzahlen = $this->verwaltung->kennzahlen();

        self::assertSame(500, $kennzahlen['hauptbuch_abweichung_cent']);
        self::assertFalse($kennzahlen['hauptbuch_in_ordnung']);
        self::assertSame(1, $kennzahlen['hauptbuch_unausgeglichene_vorgaenge']);
    }

    // --- Konten ------------------------------------------------------------

    public function testKontenlisteZeigtFaehigkeitenUndGuthaben(): void
    {
        $lina = $this->benutzer('Lina');
        $this->benutzer('Mara');
        $this->aufladen($lina, 2500);
        $this->konten->faehigkeitFreischalten($lina, Konten::FAEHIGKEIT_KAUFEN, 'test');

        $seite = $this->verwaltung->konten();

        self::assertSame(2, $seite['anzahl']);
        self::assertSame(1, $seite['seiten']);
        self::assertCount(2, $seite['zeilen']);

        $zeile = $this->zeileNachPseudonym($seite['zeilen'], 'Lina');
        self::assertSame(2500, $zeile['guthaben_cent']);
        self::assertSame([Konten::FAEHIGKEIT_KAUFEN], $zeile['faehigkeiten']);

        $ohne = $this->zeileNachPseudonym($seite['zeilen'], 'Mara');
        self::assertSame(0, $ohne['guthaben_cent']);
        self::assertSame([], $ohne['faehigkeiten']);
    }

    public function testKontensucheFiltertNachPseudonymUndEMail(): void
    {
        $this->benutzer('Lina');
        $this->benutzer('Mara');

        self::assertSame(1, $this->verwaltung->konten('lin')['anzahl']);
        self::assertSame(1, $this->verwaltung->konten('MARA@BEISPIEL')['anzahl']);
        self::assertSame(0, $this->verwaltung->konten('gibtesnicht')['anzahl']);
        self::assertSame(2, $this->verwaltung->konten('  ')['anzahl']);
    }

    /**
     * % und _ der Eingabe sind Suchtext, keine Platzhalter.
     *
     * Ohne die Maskierung liefert die Suche nach '%' die ganze Tabelle und
     * 'l_na' auch 'lina' — an genau der Stelle, an der als naechstes gesperrt
     * oder entrechtet wird.
     */
    public function testKontensucheBehandeltProzentUndUnterstrichAlsText(): void
    {
        $this->benutzer('Lina');
        $this->benutzer('Mara');
        // Ein Konto mit echtem Unterstrich in der Adresse: Sonst liesse sich
        // nicht unterscheiden, ob die Suche den Unterstrich findet oder nur
        // alles verwirft.
        $this->benutzer('max_muster');

        self::assertSame(0, $this->verwaltung->konten('%')['anzahl']);
        self::assertSame(0, $this->verwaltung->konten('l_na')['anzahl']);
        self::assertSame(0, $this->verwaltung->konten('100%')['anzahl']);

        // Der Unterstrich findet nur das Konto, das ihn wirklich traegt.
        $treffer = $this->verwaltung->konten('_');
        self::assertSame(1, $treffer['anzahl']);
        self::assertSame('max_muster', $treffer['zeilen'][0]['pseudonym']);

        // Und die gewoehnliche Suche bleibt unveraendert.
        self::assertSame(1, $this->verwaltung->konten('lina')['anzahl']);
        self::assertSame(3, $this->verwaltung->konten('  ')['anzahl']);
    }

    /**
     * Das Fluchtzeichen selbst darf nicht durchschlagen: Wer '!' eintippt,
     * sucht nach einem Ausrufezeichen.
     */
    public function testKontensucheNimmtDasFluchtzeichenAlsGewoehnlichesZeichen(): void
    {
        $this->benutzer('Lina');

        self::assertSame(0, $this->verwaltung->konten('!')['anzahl']);
        self::assertSame(0, $this->verwaltung->konten('!%')['anzahl']);
        self::assertSame(1, $this->verwaltung->konten('lina')['anzahl']);
    }

    public function testKontoZeigtPruefungenBestellungenUndMeldungen(): void
    {
        $kaeufer = $this->benutzer('Kaeuferin');
        $verkaeufer = $this->benutzer('Verkaeuferin');
        $melder = $this->benutzer('Melderin');
        $this->aufladen($kaeufer, 10000);

        $this->db->einfuegen('pruefungen', [
            'benutzer_id' => $kaeufer,
            'art' => 'altersidentifizierung',
            'anbieter' => 'eid',
            'anbieter_referenz' => null,
            'status' => Verwaltung::PRUEFUNG_OFFEN,
            'volljaehrig' => 0,
            'geprueft_am' => null,
            'gueltig_bis' => null,
            'angelegt_am' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->meldung($kaeufer, Verwaltung::MELDUNG_OFFEN, null, $melder);
        $this->meldung($kaeufer, Verwaltung::MELDUNG_ERLEDIGT, null, $melder);
        $this->bestellungen()->anlegen($kaeufer, $verkaeufer, [$this->position(5950)]);

        $ansicht = $this->verwaltung->konto($kaeufer);

        self::assertNotNull($ansicht);
        self::assertSame('Kaeuferin', $ansicht['konto']['pseudonym']);
        self::assertArrayNotHasKey('passwort_hash', $ansicht['konto']);
        self::assertCount(1, $ansicht['pruefungen']);
        self::assertCount(1, $ansicht['bestellungen']);
        self::assertSame('kaeufer', $ansicht['bestellungen'][0]['rolle']);
        // Nur die unerledigten Meldungen — die erledigte ist keine Arbeit mehr.
        self::assertCount(1, $ansicht['meldungen']);
        self::assertSame('Melderin', $ansicht['meldungen'][0]['melder_pseudonym']);
        self::assertSame(10000 - 5950, $ansicht['guthaben_cent']);
        self::assertSame(0, $ansicht['einnahmen_cent']);
    }

    public function testKontoLiefertNullBeiUnbekannterKennung(): void
    {
        self::assertNull($this->verwaltung->konto(4711));
    }

    /**
     * Die Uebersicht darf nichts anlegen: Hauptbuch::guthaben() legt das
     * Benutzerkonto bei Bedarf an, ein blosses Hinsehen darf das nicht.
     */
    public function testAnsichtLegtKeineHauptbuchkontenAn(): void
    {
        $id = $this->benutzer('Lina');

        $this->verwaltung->konten();
        $this->verwaltung->konto($id);

        self::assertSame(4, (int) $this->db->wert('SELECT COUNT(*) FROM hauptbuch_konten'));
    }

    // --- Faehigkeiten ------------------------------------------------------

    public function testFreischaltenOhneBegruendungScheitert(): void
    {
        $verwalter = $this->verwalterin();
        $ziel = $this->benutzer('Lina');

        try {
            $this->verwaltung->faehigkeitFreischalten($verwalter, $ziel, Konten::FAEHIGKEIT_KAUFEN, '   ');
            self::fail('Eine leere Begruendung haette abgewiesen werden muessen.');
        } catch (VerwaltungsFehler $fehler) {
            self::assertSame('begruendung_fehlt', $fehler->schluessel());
        }

        self::assertFalse($this->konten->hatFaehigkeit($ziel, Konten::FAEHIGKEIT_KAUFEN));
        self::assertSame(0, $this->anzahlEreignisse());
    }

    public function testFreischaltenSchreibtGenauEinEreignis(): void
    {
        $verwalter = $this->verwalterin();
        $ziel = $this->benutzer('Lina');

        $ereignisId = $this->verwaltung->faehigkeitFreischalten(
            $verwalter,
            $ziel,
            Konten::FAEHIGKEIT_VERKAUFEN,
            'Identitaet geprueft, Nachweis liegt vor.'
        );

        self::assertTrue($this->konten->hatFaehigkeit($ziel, Konten::FAEHIGKEIT_VERKAUFEN));
        self::assertSame(1, $this->anzahlEreignisse());

        $eintrag = $this->db->eine('SELECT * FROM verwaltungs_ereignisse WHERE id = :id', ['id' => $ereignisId]);
        self::assertNotNull($eintrag);
        self::assertSame($verwalter, (int) $eintrag['verwalter_id']);
        self::assertSame(Verwaltung::HANDLUNG_FAEHIGKEIT_FREIGESCHALTET, $eintrag['handlung']);
        self::assertSame('faehigkeit_verkaufen', $eintrag['gegenstand_art']);
        self::assertSame($ziel, (int) $eintrag['gegenstand_id']);
        self::assertSame('Identitaet geprueft, Nachweis liegt vor.', $eintrag['begruendung']);
    }

    public function testEntziehenNimmtDieFaehigkeitUndProtokolliert(): void
    {
        $verwalter = $this->verwalterin();
        $ziel = $this->benutzer('Lina');
        $this->konten->faehigkeitFreischalten($ziel, Konten::FAEHIGKEIT_KAUFEN, 'test');

        $this->verwaltung->faehigkeitEntziehen(
            $verwalter,
            $ziel,
            Konten::FAEHIGKEIT_KAUFEN,
            'Altersnachweis abgelaufen.'
        );

        self::assertFalse($this->konten->hatFaehigkeit($ziel, Konten::FAEHIGKEIT_KAUFEN));
        self::assertSame(1, $this->anzahlEreignisse());

        // Entzogen heisst nicht geloescht: Die Zeile bleibt als Historie stehen.
        self::assertSame(1, (int) $this->db->wert(
            'SELECT COUNT(*) FROM benutzer_faehigkeiten WHERE benutzer_id = :b AND entzogen_am IS NOT NULL',
            ['b' => $ziel]
        ));
    }

    public function testUnbekannteFaehigkeitWirdAbgewiesen(): void
    {
        $verwalter = $this->verwalterin();
        $ziel = $this->benutzer('Lina');

        $this->expectException(VerwaltungsFehler::class);
        $this->expectExceptionMessageMatches('/Unbekannte Faehigkeit/');

        $this->verwaltung->faehigkeitFreischalten($verwalter, $ziel, 'alles', 'Weil ich es kann.');
    }

    /**
     * Freischalten und Protokollieren sind EIN Vorgang. Scheitert das
     * Protokoll, darf auch die Berechtigung nicht bestehen bleiben — sonst
     * gaebe es eine Faehigkeit, die niemand zu verantworten hat.
     */
    public function testOhneProtokollBleibtDieFaehigkeitAus(): void
    {
        $ziel = $this->benutzer('Lina');

        try {
            $this->verwaltung->faehigkeitFreischalten(999, $ziel, Konten::FAEHIGKEIT_KAUFEN, 'Begruendung liegt vor.');
            self::fail('Ein unbekanntes verwaltendes Konto haette abgewiesen werden muessen.');
        } catch (VerwaltungsFehler $fehler) {
            self::assertSame('verwalter_unbekannt', $fehler->schluessel());
        }

        self::assertFalse($this->konten->hatFaehigkeit($ziel, Konten::FAEHIGKEIT_KAUFEN));
        self::assertSame(0, $this->anzahlEreignisse());
    }

    // --- Sperren -----------------------------------------------------------

    public function testSperrenSetztDenStatusUndProtokolliert(): void
    {
        $verwalter = $this->verwalterin();
        $ziel = $this->benutzer('Lina');

        $this->verwaltung->kontoSperren($verwalter, $ziel, 'Mehrfach gemeldet, Ware nie versendet.');

        self::assertSame(Verwaltung::STATUS_GESPERRT, $this->kontostatus($ziel));
        self::assertSame(1, $this->anzahlEreignisse());

        $eintrag = $this->db->eine('SELECT * FROM verwaltungs_ereignisse ORDER BY id DESC');
        self::assertSame(Verwaltung::HANDLUNG_KONTO_GESPERRT, $eintrag['handlung']);
        self::assertSame(Verwaltung::GEGENSTAND_BENUTZER, $eintrag['gegenstand_art']);
        self::assertSame($ziel, (int) $eintrag['gegenstand_id']);

        $this->verwaltung->kontoEntsperren($verwalter, $ziel, 'Sachverhalt geklaert.');

        self::assertSame(Verwaltung::STATUS_AKTIV, $this->kontostatus($ziel));
        self::assertSame(2, $this->anzahlEreignisse());
    }

    public function testSelbstsperreScheitert(): void
    {
        $verwalter = $this->verwalterin();

        try {
            $this->verwaltung->kontoSperren($verwalter, $verwalter, 'Aus Versehen.');
            self::fail('Eine Selbstsperre haette abgewiesen werden muessen.');
        } catch (VerwaltungsFehler $fehler) {
            self::assertSame('selbstsperre_unzulaessig', $fehler->schluessel());
        }

        self::assertSame(Verwaltung::STATUS_AKTIV, $this->kontostatus($verwalter));
        self::assertSame(0, $this->anzahlEreignisse());
    }

    public function testSperrenOhneBegruendungScheitert(): void
    {
        $verwalter = $this->verwalterin();
        $ziel = $this->benutzer('Lina');

        $this->expectException(VerwaltungsFehler::class);

        $this->verwaltung->kontoSperren($verwalter, $ziel, '');
    }

    // --- Zustellung (Art. 17 DSA) ------------------------------------------

    /**
     * Art. 17 Abs. 1 DSA verlangt, dass die betroffene Person die Begruendung
     * ERHAELT. Ein Protokolleintrag, den nur die Verwaltung sieht, erfuellt das
     * nicht — die Zustellung muss entstehen und am Protokolleintrag haengen.
     */
    public function testSperrenStelltDieBegruendungZu(): void
    {
        $verwalter = $this->verwalterin();
        $ziel = $this->benutzer('Lina');

        $ereignisId = $this->verwaltung->kontoSperren(
            $verwalter,
            $ziel,
            'Mehrfach gemeldet, Ware nie versendet.'
        );

        $zustellungen = $this->verwaltung->benachrichtigungen($ziel);
        self::assertCount(1, $zustellungen);
        self::assertSame(Verwaltung::HANDLUNG_KONTO_GESPERRT, $zustellungen[0]['art']);
        self::assertSame(Verwaltung::GEGENSTAND_BENUTZER, $zustellungen[0]['gegenstand_art']);
        self::assertSame($ziel, $zustellungen[0]['gegenstand_id']);
        self::assertSame('Mehrfach gemeldet, Ware nie versendet.', $zustellungen[0]['begruendung']);
        self::assertNull($zustellungen[0]['gelesen_am']);
        self::assertFalse($zustellungen[0]['gelesen']);

        // Die Verbindung zum Journal: Zu jeder Beschraenkung muss eine
        // Zustellung auffindbar sein und umgekehrt.
        self::assertSame($ereignisId, (int) $this->db->wert(
            'SELECT verwaltungs_ereignis_id FROM benachrichtigungen WHERE id = :id',
            ['id' => $zustellungen[0]['id']]
        ));

        // Und die Verwalteridentitaet bleibt im Journal: Die Sicht der
        // betroffenen Person traegt sie nicht.
        self::assertArrayNotHasKey('verwalter_id', $zustellungen[0]);
        self::assertArrayNotHasKey('verwaltungs_ereignis_id', $zustellungen[0]);
    }

    public function testEntziehenStelltDieBegruendungZu(): void
    {
        $verwalter = $this->verwalterin();
        $ziel = $this->benutzer('Lina');
        $this->konten->faehigkeitFreischalten($ziel, Konten::FAEHIGKEIT_VERKAUFEN, 'test');

        $this->verwaltung->faehigkeitEntziehen(
            $verwalter,
            $ziel,
            Konten::FAEHIGKEIT_VERKAUFEN,
            'Identitaetsnachweis abgelaufen.'
        );

        $zustellungen = $this->verwaltung->benachrichtigungen($ziel);
        self::assertCount(1, $zustellungen);
        self::assertSame(Verwaltung::HANDLUNG_FAEHIGKEIT_ENTZOGEN, $zustellungen[0]['art']);
        self::assertSame('faehigkeit_verkaufen', $zustellungen[0]['gegenstand_art']);
        self::assertSame('Identitaetsnachweis abgelaufen.', $zustellungen[0]['begruendung']);
    }

    /**
     * Wirkung, Protokoll und Zustellung sind EIN Vorgang. Scheitert das
     * Protokoll, darf weder die Beschraenkung noch die Zustellung stehen
     * bleiben.
     */
    public function testOhneProtokollEntstehtAuchKeineZustellung(): void
    {
        $ziel = $this->benutzer('Lina');
        $this->konten->faehigkeitFreischalten($ziel, Konten::FAEHIGKEIT_KAUFEN, 'test');

        try {
            $this->verwaltung->faehigkeitEntziehen(999, $ziel, Konten::FAEHIGKEIT_KAUFEN, 'Begruendung liegt vor.');
            self::fail('Ein unbekanntes verwaltendes Konto haette abgewiesen werden muessen.');
        } catch (VerwaltungsFehler $fehler) {
            self::assertSame('verwalter_unbekannt', $fehler->schluessel());
        }

        self::assertTrue($this->konten->hatFaehigkeit($ziel, Konten::FAEHIGKEIT_KAUFEN));
        self::assertSame(0, $this->anzahlEreignisse());
        self::assertSame([], $this->verwaltung->benachrichtigungen($ziel));
    }

    /**
     * Das Aufheben einer Sperre beschraenkt nichts — Art. 17 DSA verlangt
     * dafuer keine Zustellung, und eine Nachricht ohne Anlass ist keine.
     */
    public function testEntsperrenProtokolliertOhneZuzustellen(): void
    {
        $verwalter = $this->verwalterin();
        $ziel = $this->benutzer('Lina');

        $this->verwaltung->kontoSperren($verwalter, $ziel, 'Mehrfach gemeldet.');
        $this->verwaltung->kontoEntsperren($verwalter, $ziel, 'Sachverhalt geklaert.');

        self::assertSame(2, $this->anzahlEreignisse());
        // Nur die Sperre wurde zugestellt, nicht ihre Aufhebung.
        self::assertCount(1, $this->verwaltung->benachrichtigungen($ziel));
    }

    public function testBeschraenkungOhneBegruendungWirdAbgewiesen(): void
    {
        $verwalter = $this->verwalterin();
        $ziel = $this->benutzer('Lina');

        try {
            $this->verwaltung->beschraenkungProtokollierenUndZustellen(
                $verwalter,
                $ziel,
                'angebot_abgelehnt',
                Verwaltung::GEGENSTAND_ANGEBOT,
                7,
                '   '
            );
            self::fail('Eine leere Begruendung haette abgewiesen werden muessen.');
        } catch (VerwaltungsFehler $fehler) {
            self::assertSame('begruendung_fehlt', $fehler->schluessel());
        }

        self::assertSame(0, $this->anzahlEreignisse());
        self::assertSame([], $this->verwaltung->benachrichtigungen($ziel));
    }

    public function testSelbstbeschraenkungWirdAbgewiesen(): void
    {
        $verwalter = $this->verwalterin();

        try {
            $this->verwaltung->beschraenkungProtokollierenUndZustellen(
                $verwalter,
                $verwalter,
                'angebot_abgelehnt',
                Verwaltung::GEGENSTAND_ANGEBOT,
                7,
                'Passt mir nicht.'
            );
            self::fail('Eine Beschraenkung des eigenen Kontos haette abgewiesen werden muessen.');
        } catch (VerwaltungsFehler $fehler) {
            self::assertSame('selbstsperre_unzulaessig', $fehler->schluessel());
        }

        self::assertSame(0, $this->anzahlEreignisse());
    }

    /**
     * Der Weg, den VerwaltungsRouten fuer die Angebotsablehnung nimmt: Die
     * Wirkung loest Angebote aus, Protokoll und Zustellung entstehen hier.
     */
    public function testBeschraenkungSchreibtProtokollUndZustellungGemeinsam(): void
    {
        $verwalter = $this->verwalterin();
        $verkaeufer = $this->benutzer('Verkaeuferin');
        $angebotId = $this->angebot($verkaeufer, Verwaltung::ANGEBOT_IN_PRUEFUNG, 'Wartet');

        $ereignisId = $this->verwaltung->beschraenkungProtokollierenUndZustellen(
            $verwalter,
            $verkaeufer,
            'angebot_abgelehnt',
            Verwaltung::GEGENSTAND_ANGEBOT,
            $angebotId,
            'Die Bilder zeigen eine dritte Person.'
        );

        self::assertSame(1, $this->anzahlEreignisse());

        $zustellungen = $this->verwaltung->benachrichtigungen($verkaeufer);
        self::assertCount(1, $zustellungen);
        self::assertSame('angebot_abgelehnt', $zustellungen[0]['art']);
        self::assertSame($angebotId, $zustellungen[0]['gegenstand_id']);
        self::assertSame($ereignisId, (int) $this->db->wert(
            'SELECT verwaltungs_ereignis_id FROM benachrichtigungen WHERE id = :id',
            ['id' => $zustellungen[0]['id']]
        ));
    }

    /**
     * Die Kennungen sind fortlaufend und damit durchzaehlbar: Eine fremde
     * Zustellung darf sich weder lesen noch als gelesen markieren lassen.
     */
    public function testFremdeZustellungLaesstSichNichtAlsGelesenMarkieren(): void
    {
        $verwalter = $this->verwalterin();
        $betroffen = $this->benutzer('Lina');
        $fremd = $this->benutzer('Mara');

        $this->verwaltung->kontoSperren($verwalter, $betroffen, 'Mehrfach gemeldet.');
        $id = $this->verwaltung->benachrichtigungen($betroffen)[0]['id'];

        try {
            $this->verwaltung->benachrichtigungGelesen($fremd, (int) $id);
            self::fail('Eine fremde Zustellung haette nicht markiert werden duerfen.');
        } catch (VerwaltungsFehler $fehler) {
            self::assertSame('benachrichtigung_unbekannt', $fehler->schluessel());
        }

        // Unveraendert: Der Nachweis, wann die Begruendung angekommen ist,
        // haengt an dieser Spalte.
        self::assertNull($this->db->wert(
            'SELECT gelesen_am FROM benachrichtigungen WHERE id = :id',
            ['id' => $id]
        ));

        // Und die fremde Liste bleibt leer — sie zeigt nichts Fremdes an.
        self::assertSame([], $this->verwaltung->benachrichtigungen($fremd));
    }

    public function testGelesenHaeltDenErstenZeitpunktFestUndFiltertDieListe(): void
    {
        $verwalter = $this->verwalterin();
        $betroffen = $this->benutzer('Lina');

        $this->verwaltung->kontoSperren($verwalter, $betroffen, 'Mehrfach gemeldet.');
        $this->verwaltung->faehigkeitEntziehen(
            $verwalter,
            $betroffen,
            Konten::FAEHIGKEIT_KAUFEN,
            'Altersnachweis abgelaufen.'
        );

        self::assertCount(2, $this->verwaltung->benachrichtigungen($betroffen, true));

        $id = (int) $this->verwaltung->benachrichtigungen($betroffen)[0]['id'];
        $this->verwaltung->benachrichtigungGelesen($betroffen, $id);

        $ungelesen = $this->verwaltung->benachrichtigungen($betroffen, true);
        self::assertCount(1, $ungelesen);
        self::assertNotSame($id, (int) $ungelesen[0]['id']);

        $zuerst = (string) $this->db->wert(
            'SELECT gelesen_am FROM benachrichtigungen WHERE id = :id',
            ['id' => $id]
        );
        self::assertNotSame('', $zuerst);

        // Ein zweiter Aufruf darf den Nachweis nicht nach hinten schieben.
        $this->db->ausfuehren(
            'UPDATE benachrichtigungen SET gelesen_am = :z WHERE id = :id',
            ['z' => '2020-01-01 00:00:00', 'id' => $id]
        );
        $this->verwaltung->benachrichtigungGelesen($betroffen, $id);

        self::assertSame('2020-01-01 00:00:00', $this->db->wert(
            'SELECT gelesen_am FROM benachrichtigungen WHERE id = :id',
            ['id' => $id]
        ));
    }

    // --- Angebote ----------------------------------------------------------

    public function testOffeneAngeboteZeigenVerkaeuferUndKategorie(): void
    {
        $verkaeufer = $this->benutzer('Verkaeuferin');
        $this->angebot($verkaeufer, Verwaltung::ANGEBOT_IN_PRUEFUNG, 'Wartet auf Pruefung');
        $this->angebot($verkaeufer, 'aktiv', 'Laeuft schon');

        $seite = $this->verwaltung->offeneAngebote();

        self::assertSame(1, $seite['anzahl']);
        self::assertSame('Wartet auf Pruefung', $seite['zeilen'][0]['titel']);
        self::assertSame('Verkaeuferin', $seite['zeilen'][0]['verkaeufer_pseudonym']);
        self::assertSame('waesche_slips', $seite['zeilen'][0]['kategorie_schluessel']);
    }

    // --- Protokoll ---------------------------------------------------------

    public function testProtokollKommtNeuesteZuerst(): void
    {
        $verwalter = $this->verwalterin();
        $ziel = $this->benutzer('Lina');

        $erste = $this->verwaltung->ereignisSchreiben(
            $verwalter,
            'angebot_freigegeben',
            Verwaltung::GEGENSTAND_ANGEBOT,
            null,
            'Erste Entscheidung.'
        );
        $zweite = $this->verwaltung->kontoSperren($verwalter, $ziel, 'Zweite Entscheidung.');
        $dritte = $this->verwaltung->kontoEntsperren($verwalter, $ziel, 'Dritte Entscheidung.');

        $seite = $this->verwaltung->ereignisse();

        self::assertSame(3, $seite['anzahl']);
        self::assertSame(
            [$dritte, $zweite, $erste],
            array_map(static fn (array $z): int => (int) $z['id'], $seite['zeilen'])
        );
        self::assertSame('Verwalterin', $seite['zeilen'][0]['verwalter_pseudonym']);
    }

    public function testZuLangeHandlungWirdAbgewiesen(): void
    {
        $verwalter = $this->verwalterin();

        $this->expectException(VerwaltungsFehler::class);
        $this->expectExceptionMessageMatches('/laenger als 60/');

        $this->verwaltung->ereignisSchreiben(
            $verwalter,
            str_repeat('a', 61),
            Verwaltung::GEGENSTAND_ANGEBOT,
            null,
            'Begruendung liegt vor.'
        );
    }

    // --- Meldungen ---------------------------------------------------------

    public function testMeldungBearbeitenSetztStatusUndProtokolliert(): void
    {
        $verwalter = $this->verwalterin();
        $ziel = $this->benutzer('Lina');
        $meldungId = $this->meldung($ziel, Verwaltung::MELDUNG_OFFEN);

        $this->verwaltung->meldungBearbeiten(
            $verwalter,
            $meldungId,
            Verwaltung::MELDUNG_ERLEDIGT,
            'Angebot entfernt, Konto verwarnt.'
        );

        $meldung = $this->db->eine('SELECT * FROM meldungen WHERE id = :id', ['id' => $meldungId]);
        self::assertSame(Verwaltung::MELDUNG_ERLEDIGT, $meldung['status']);
        self::assertSame('Angebot entfernt, Konto verwarnt.', $meldung['entscheidung']);
        self::assertNotNull($meldung['erledigt_am']);
        self::assertSame(1, $this->anzahlEreignisse());

        // Zurueck in die Pruefung: Dann ist die Meldung nicht mehr erledigt.
        $this->verwaltung->meldungBearbeiten(
            $verwalter,
            $meldungId,
            Verwaltung::MELDUNG_IN_PRUEFUNG,
            'Beschwerde eingegangen, wird erneut geprueft.'
        );

        $meldung = $this->db->eine('SELECT * FROM meldungen WHERE id = :id', ['id' => $meldungId]);
        self::assertSame(Verwaltung::MELDUNG_IN_PRUEFUNG, $meldung['status']);
        self::assertNull($meldung['erledigt_am']);
    }

    public function testMeldungBearbeitenMitUnbekanntemStatusScheitert(): void
    {
        $verwalter = $this->verwalterin();
        $ziel = $this->benutzer('Lina');
        $meldungId = $this->meldung($ziel, Verwaltung::MELDUNG_OFFEN);

        try {
            $this->verwaltung->meldungBearbeiten($verwalter, $meldungId, 'geschlossen', 'Passt schon.');
            self::fail('Ein unbekannter Status haette abgewiesen werden muessen.');
        } catch (VerwaltungsFehler $fehler) {
            self::assertSame('meldungsstatus_unbekannt', $fehler->schluessel());
        }

        self::assertSame(
            Verwaltung::MELDUNG_OFFEN,
            $this->db->wert('SELECT status FROM meldungen WHERE id = :id', ['id' => $meldungId])
        );
        self::assertSame(0, $this->anzahlEreignisse());
    }

    public function testMeldungslisteFiltertUndMarkiertFristueberschreitung(): void
    {
        $ziel = $this->benutzer('Lina');
        $this->meldung($ziel, Verwaltung::MELDUNG_OFFEN, '2026-07-01 00:00:00');
        $this->meldung($ziel, Verwaltung::MELDUNG_ERLEDIGT, '2026-07-01 00:00:00');

        $alle = $this->verwaltung->meldungen(null, 1, '2026-07-25 12:00:00');
        self::assertSame(2, $alle['anzahl']);

        $offen = $this->verwaltung->meldungen(Verwaltung::MELDUNG_OFFEN, 1, '2026-07-25 12:00:00');
        self::assertSame(1, $offen['anzahl']);
        self::assertTrue($offen['zeilen'][0]['frist_ueberschritten']);

        $erledigt = $this->verwaltung->meldungen(Verwaltung::MELDUNG_ERLEDIGT, 1, '2026-07-25 12:00:00');
        self::assertFalse($erledigt['zeilen'][0]['frist_ueberschritten']);
    }

    // --- Hauptbuch ---------------------------------------------------------

    public function testHauptbuchVorgaengeZeigenIhreSumme(): void
    {
        $kaeufer = $this->benutzer('Kaeuferin');
        $this->aufladen($kaeufer, 5000);

        $seite = $this->verwaltung->hauptbuchVorgaenge();

        self::assertSame(1, $seite['anzahl']);
        self::assertSame('aufladung', $seite['zeilen'][0]['art']);
        self::assertSame(0, $seite['zeilen'][0]['summe_cent']);
        self::assertSame(2, $seite['zeilen'][0]['buchungen']);
        self::assertTrue($seite['zeilen'][0]['ausgeglichen']);

        $this->assertHauptbuchAusgeglichen();
    }

    /**
     * Die Summen werden nicht mehr ueber das ganze Hauptbuch aggregiert,
     * sondern in einem zweiten Schritt nur zu den Vorgaengen dieser Seite
     * geholt. Ein Vorgang ohne Buchungen taucht dabei gar nicht auf — er muss
     * trotzdem wie beim frueheren LEFT JOIN als 0/0 und ausgeglichen gelten.
     */
    public function testVorgangOhneBuchungenBleibtAusgeglichen(): void
    {
        $kaeufer = $this->benutzer('Kaeuferin');
        $this->aufladen($kaeufer, 5000);

        $this->db->einfuegen('hauptbuch_vorgaenge', [
            'art' => 'leerlauf',
            'bezug_art' => null,
            'bezug_id' => null,
            'beschreibung' => 'Vorgang ganz ohne Buchungen',
            'idempotenz_schluessel' => null,
            'angelegt_am' => gmdate('Y-m-d H:i:s'),
        ]);

        $seite = $this->verwaltung->hauptbuchVorgaenge();

        self::assertSame(2, $seite['anzahl']);

        $leer = $this->zeileNachArt($seite['zeilen'], 'leerlauf');
        self::assertSame(0, $leer['summe_cent']);
        self::assertSame(0, $leer['buchungen']);
        self::assertTrue($leer['ausgeglichen']);

        // Die Zeile mit Buchungen wird davon nicht beruehrt.
        $aufladung = $this->zeileNachArt($seite['zeilen'], 'aufladung');
        self::assertSame(0, $aufladung['summe_cent']);
        self::assertSame(2, $aufladung['buchungen']);
    }

    // --- Zugang ------------------------------------------------------------

    /**
     * Die Faehigkeit 'verwalten' darf ueber das Web nur genommen, nie gegeben
     * werden. Sonst reichte ein uebernommenes Verwalterkonto, um beliebig
     * viele weitere anzulegen.
     */
    public function testVerwaltungsrechtLaesstSichNurEntziehenNichtVergeben(): void
    {
        $verwalter = $this->verwalterin();
        $zweite = $this->verwalterin('Zweite');

        try {
            $this->verwaltung->faehigkeitFreischalten(
                $verwalter,
                $this->benutzer('Lina'),
                Konten::FAEHIGKEIT_VERWALTEN,
                'Soll auch verwalten duerfen.'
            );
            self::fail('Verwaltungsrecht darf nicht ueber das Web vergeben werden.');
        } catch (VerwaltungsFehler $fehler) {
            self::assertSame('faehigkeit_nicht_vergebbar', $fehler->schluessel());
        }

        // Entziehen dagegen muss sofort moeglich sein.
        $this->verwaltung->faehigkeitEntziehen(
            $verwalter,
            $zweite,
            Konten::FAEHIGKEIT_VERWALTEN,
            'Konto uebernommen, Zugang gesperrt.'
        );

        self::assertFalse($this->konten->hatFaehigkeit($zweite, Konten::FAEHIGKEIT_VERWALTEN));
    }

    /**
     * Der Selbstentzug von 'verwalten' meldete frueher 'kein_verwaltungsrecht'
     * und behauptete damit das Gegenteil der Lage: Das Konto HAT das Recht,
     * sonst waere es nie bis hierher gekommen. Ursache war die Reihenfolge —
     * Konten::faehigkeitEntziehen() lief zuerst, danach sah die Pruefung im
     * Protokoll das eben entzogene Recht schon als entzogen.
     */
    public function testSelbstentzugMeldetDenSelbstbezugUndNichtFehlendesRecht(): void
    {
        $verwalter = $this->verwalterin();

        try {
            $this->verwaltung->faehigkeitEntziehen(
                $verwalter,
                $verwalter,
                Konten::FAEHIGKEIT_VERWALTEN,
                'Ich hoere auf.'
            );
            self::fail('Der Selbstentzug haette abgewiesen werden muessen.');
        } catch (VerwaltungsFehler $fehler) {
            self::assertSame('selbstsperre_unzulaessig', $fehler->schluessel());
        }

        self::assertTrue($this->konten->hatFaehigkeit($verwalter, Konten::FAEHIGKEIT_VERWALTEN));
        self::assertSame(0, $this->anzahlEreignisse());
        self::assertSame([], $this->verwaltung->benachrichtigungen($verwalter));
    }

    /**
     * Grundsatz 3 gilt fuer jede Faehigkeit, nicht nur fuer 'verwalten': Wer
     * sich selbst 'kaufen' geben oder nehmen duerfte, entschiede ueber das
     * eigene Konto — und umginge im Fall des Vergebens die Altersverifikation.
     */
    public function testUeberDieEigenenFaehigkeitenEntscheidetJemandAnderes(): void
    {
        $verwalter = $this->verwalterin();
        $this->konten->faehigkeitFreischalten($verwalter, Konten::FAEHIGKEIT_KAUFEN, 'altersnachweis');

        foreach (['entziehen', 'freischalten'] as $richtung) {
            try {
                if ($richtung === 'entziehen') {
                    $this->verwaltung->faehigkeitEntziehen(
                        $verwalter,
                        $verwalter,
                        Konten::FAEHIGKEIT_KAUFEN,
                        'Brauche ich nicht mehr.'
                    );
                } else {
                    $this->verwaltung->faehigkeitFreischalten(
                        $verwalter,
                        $verwalter,
                        Konten::FAEHIGKEIT_VERKAUFEN,
                        'Will ich auch.'
                    );
                }

                self::fail('Die Entscheidung ueber das eigene Konto haette abgewiesen werden muessen.');
            } catch (VerwaltungsFehler $fehler) {
                self::assertSame('selbstsperre_unzulaessig', $fehler->schluessel());
            }
        }

        self::assertTrue($this->konten->hatFaehigkeit($verwalter, Konten::FAEHIGKEIT_KAUFEN));
        self::assertFalse($this->konten->hatFaehigkeit($verwalter, Konten::FAEHIGKEIT_VERKAUFEN));
        self::assertSame(0, $this->anzahlEreignisse());
    }

    public function testOhneVerwaltungsrechtWirdNichtProtokolliert(): void
    {
        $ohneRecht = $this->benutzer('Lina');
        $ziel = $this->benutzer('Mara');

        try {
            $this->verwaltung->kontoSperren($ohneRecht, $ziel, 'Weil ich es kann.');
            self::fail('Ein Konto ohne Verwaltungsrecht haette abgewiesen werden muessen.');
        } catch (VerwaltungsFehler $fehler) {
            self::assertSame('kein_verwaltungsrecht', $fehler->schluessel());
        }

        self::assertSame(Verwaltung::STATUS_AKTIV, $this->kontostatus($ziel));
        self::assertSame(0, $this->anzahlEreignisse());
    }

    // --- Hilfen ------------------------------------------------------------

    /** Ein Konto mit Verwaltungsrecht — wie es bin/verwalter anlegt. */
    private function verwalterin(string $pseudonym = 'Verwalterin'): int
    {
        $id = $this->benutzer($pseudonym);
        $this->konten->faehigkeitFreischalten($id, Konten::FAEHIGKEIT_VERWALTEN, 'kommandozeile');

        return $id;
    }

    private function anzahlEreignisse(): int
    {
        return (int) $this->db->wert('SELECT COUNT(*) FROM verwaltungs_ereignisse');
    }

    private function kontostatus(int $benutzerId): string
    {
        return (string) $this->db->wert('SELECT status FROM benutzer WHERE id = :id', ['id' => $benutzerId]);
    }

    /** @param list<array<string,mixed>> $zeilen */
    private function zeileNachArt(array $zeilen, string $art): array
    {
        foreach ($zeilen as $zeile) {
            if ($zeile['art'] === $art) {
                return $zeile;
            }
        }

        self::fail('Kein Vorgang der Art "' . $art . '" gefunden.');
    }

    /** @param list<array<string,mixed>> $zeilen */
    private function zeileNachPseudonym(array $zeilen, string $pseudonym): array
    {
        foreach ($zeilen as $zeile) {
            if ($zeile['pseudonym'] === $pseudonym) {
                return $zeile;
            }
        }

        self::fail('Keine Zeile fuer "' . $pseudonym . '" gefunden.');
    }

    private function meldung(
        int $gegenBenutzerId,
        string $status,
        ?string $zugesagtBis = null,
        ?int $melderId = null
    ): int {
        return $this->db->einfuegen('meldungen', [
            'melder_id' => $melderId,
            'gegenstand_art' => Verwaltung::GEGENSTAND_BENUTZER,
            'gegenstand_id' => $gegenBenutzerId,
            'grund' => 'belaestigung',
            'beschreibung' => 'Testmeldung',
            'status' => $status,
            'zugesagt_bis' => $zugesagtBis,
            'erledigt_am' => null,
            'entscheidung' => null,
            'angelegt_am' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function angebot(int $verkaeuferId, string $status, string $titel): int
    {
        $kategorieId = (int) $this->db->wert(
            'SELECT id FROM kategorien WHERE schluessel = :s',
            ['s' => 'waesche_slips']
        );

        return $this->db->einfuegen('angebote', [
            'verkaeufer_id' => $verkaeuferId,
            'kategorie_id' => $kategorieId,
            'titel' => $titel,
            'beschreibung' => null,
            'grundpreis_cent' => 2500,
            'waehrung' => 'EUR',
            'status' => $status,
            'versand_moeglich' => 1,
            'uebergabe_moeglich' => 0,
            'uebergabe_region' => null,
            'bearbeitungstage' => 3,
            'angelegt_am' => gmdate('Y-m-d H:i:s'),
            'geaendert_am' => null,
        ]);
    }
}
