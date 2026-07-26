<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Core\Lang;
use MeinSlip\Domain\Account\Konten;
use MeinSlip\Domain\Account\Profile;
use MeinSlip\Domain\Admin\Verwaltung;
use MeinSlip\Domain\Chat\Unterhaltungen;
use MeinSlip\Domain\Trust\Meldungen;
use MeinSlip\Domain\Trust\MeldungsFehler;

/**
 * Der Meldeweg nach Art. 16 DSA.
 *
 * Diese Suite sichert die Rechtsgrundlage des Torabbaus ab. Faellt hier etwas
 * aus, ist nicht eine Funktion kaputt — dann ist der Verzicht auf die
 * Vorabpruefung nicht mehr verteidigbar.
 */
final class MeldungenTest extends Testfall
{
    private Meldungen $meldungen;

    private Verwaltung $verwaltung;

    protected function setUp(): void
    {
        parent::setUp();
        $this->meldungen = new Meldungen($this->db);
        $this->verwaltung = new Verwaltung($this->db);
        Lang::einrichten(dirname(__DIR__) . '/resources/lang', 'de-DE');
    }

    // --- Entgegennahme -----------------------------------------------------

    public function testEineMeldungEntstehtOffenUndMitFrist(): void
    {
        $melder = $this->benutzer('Melderin');
        $verkaeufer = $this->benutzer('Verkaeuferin');
        $angebotId = $this->angebot($verkaeufer);

        $meldungId = $this->meldungen->melden(
            $melder,
            Verwaltung::GEGENSTAND_ANGEBOT,
            $angebotId,
            Meldungen::GRUND_VERBOTENE_WARE,
            'Der Artikel ist in Deutschland nicht verkehrsfaehig.'
        );

        $meldung = $this->db->eine('SELECT * FROM meldungen WHERE id = :id', ['id' => $meldungId]);

        self::assertNotNull($meldung);
        self::assertSame($melder, (int) $meldung['melder_id']);
        self::assertSame(Verwaltung::GEGENSTAND_ANGEBOT, $meldung['gegenstand_art']);
        self::assertSame($angebotId, (int) $meldung['gegenstand_id']);
        self::assertSame(Meldungen::GRUND_VERBOTENE_WARE, $meldung['grund']);
        self::assertSame(Verwaltung::MELDUNG_OFFEN, $meldung['status']);
        self::assertNull($meldung['erledigt_am']);
        self::assertNull($meldung['entscheidung']);
    }

    /**
     * Art. 16 Abs. 6 DSA verlangt zeitnahe Bearbeitung. Ohne Frist IM
     * Datensatz ist ihre Ueberschreitung nicht messbar — und
     * Verwaltung::kennzahlen() zaehlt genau diese Ueberschreitung.
     */
    public function testDieZugesagteFristStehtImDatensatzUndZaehltInDenKennzahlen(): void
    {
        $melder = $this->benutzer('Melderin');
        $angebotId = $this->angebot($this->benutzer('Verkaeuferin'));

        $meldungId = $this->meldungen->melden(
            $melder,
            Verwaltung::GEGENSTAND_ANGEBOT,
            $angebotId,
            Meldungen::GRUND_BETRUG,
            'Ware wurde bezahlt und nie versendet.'
        );

        $meldung = $this->db->eine('SELECT * FROM meldungen WHERE id = :id', ['id' => $meldungId]);

        $eingang = strtotime((string) $meldung['angelegt_am'] . ' UTC');
        $frist = strtotime((string) $meldung['zugesagt_bis'] . ' UTC');

        self::assertNotFalse($eingang);
        self::assertNotFalse($frist);
        self::assertSame(Meldungen::FRIST_STUNDEN * 3600, $frist - $eingang);

        // Und die Kennzahl greift wirklich: einmal vor der Frist, einmal danach.
        $vorher = gmdate('Y-m-d H:i:s', $frist - 60);
        $nachher = gmdate('Y-m-d H:i:s', $frist + 60);

        self::assertSame(1, $this->verwaltung->kennzahlen($vorher)['meldungen_offen']);
        self::assertSame(0, $this->verwaltung->kennzahlen($vorher)['meldungen_frist_ueberschritten']);
        self::assertSame(1, $this->verwaltung->kennzahlen($nachher)['meldungen_frist_ueberschritten']);
    }

    /**
     * Art. 16 Abs. 4 DSA verlangt die Empfangsbestaetigung ausdruecklich.
     */
    public function testDieMeldendePersonBekommtEineEmpfangsbestaetigung(): void
    {
        $melder = $this->benutzer('Melderin');
        $angebotId = $this->angebot($this->benutzer('Verkaeuferin'));

        $meldungId = $this->meldungen->melden(
            $melder,
            Verwaltung::GEGENSTAND_ANGEBOT,
            $angebotId,
            Meldungen::GRUND_URHEBERRECHT,
            'Das Bild stammt von meiner Seite.'
        );

        $zustellungen = $this->verwaltung->benachrichtigungen($melder);

        self::assertCount(1, $zustellungen);
        self::assertSame(Meldungen::ART_EINGEGANGEN, $zustellungen[0]['art']);
        // Die Bestaetigung zeigt auf die MELDUNG, nicht auf das gemeldete
        // Angebot: Sie ist der Beleg ueber die eigene Meldung.
        self::assertSame(Verwaltung::GEGENSTAND_MELDUNG, $zustellungen[0]['gegenstand_art']);
        self::assertSame($meldungId, $zustellungen[0]['gegenstand_id']);
        self::assertSame('Das Bild stammt von meiner Seite.', $zustellungen[0]['begruendung']);
        self::assertFalse($zustellungen[0]['gelesen']);
    }

    /**
     * Die Empfangsbestaetigung erscheint auf der Profilseite als
     * te('profil.art.' . $art). Solche zusammengesetzten Schluessel prueft
     * UebersetzungenTest NICHT — ohne diesen Test liest die meldende Person
     * '[[profil.art.meldung_eingegangen]]' als Bestaetigung ihrer Meldung.
     */
    public function testDieEmpfangsbestaetigungHatEinenText(): void
    {
        self::assertStringNotContainsString(
            '[[',
            Lang::t('profil.art.' . Meldungen::ART_EINGEGANGEN),
            'Der Text zu "' . Meldungen::ART_EINGEGANGEN . '" fehlt in '
            . 'resources/lang/de-DE/profil.php. Er ist die Empfangsbestaetigung '
            . 'nach Art. 16 Abs. 4 DSA und steht auf /profil.'
        );
    }

    /**
     * Meldung und Bestaetigung sind EIN Vorgang: Scheitert die Zustellung,
     * darf auch die Meldung nicht stehen bleiben. Sonst gaebe es eine
     * entgegengenommene Meldung, deren Entgegennahme niemand belegen kann.
     */
    public function testOhneBestaetigungEntstehtAuchKeineMeldung(): void
    {
        $melder = $this->benutzer('Melderin');
        $angebotId = $this->angebot($this->benutzer('Verkaeuferin'));

        // Verwaltung::benachrichtigen() weist eine leere Begruendung ab. Der
        // einzige Weg, das ueber melden() auszuloesen, ist eine Erlaeuterung,
        // die nur aus Leerraum besteht — die faengt aber schon die Pruefung
        // ab. Deshalb wird die Zustellung hier von aussen unmoeglich gemacht:
        // Das Konto verschwindet zwischen Pruefung und Transaktion nicht, wohl
        // aber die Tabelle, in die zugestellt wird.
        $this->db->ddl('DROP TABLE benachrichtigungen');

        try {
            $this->meldungen->melden(
                $melder,
                Verwaltung::GEGENSTAND_ANGEBOT,
                $angebotId,
                Meldungen::GRUND_BETRUG,
                'Ware wurde bezahlt und nie versendet.'
            );
            self::fail('Ohne Zustellung haette die Meldung nicht entstehen duerfen.');
        } catch (\Throwable) {
            // Der Fehler selbst ist hier nicht die Zusicherung — die Zeile ist es.
        }

        self::assertSame(0, (int) $this->db->wert('SELECT COUNT(*) FROM meldungen'));
    }

    /**
     * Wer eine Belaestigung meldet, soll das ohne Konto tun koennen.
     */
    public function testAnonymeMeldungIstMoeglichUndBekommtKeineZustellung(): void
    {
        $ziel = $this->benutzer('Verkaeuferin');

        $meldungId = $this->meldungen->melden(
            null,
            Verwaltung::GEGENSTAND_BENUTZER,
            $ziel,
            Meldungen::GRUND_BELAESTIGUNG,
            'Schreibt mich nach jeder Absage erneut an.'
        );

        self::assertNull($this->db->wert(
            'SELECT melder_id FROM meldungen WHERE id = :id',
            ['id' => $meldungId]
        ));
        self::assertSame(0, (int) $this->db->wert('SELECT COUNT(*) FROM benachrichtigungen'));
    }

    /**
     * Anonyme Meldungen lassen sich nicht auf eine Person zurueckfuehren —
     * auch nicht ueber meineMeldungen(0). Der Vergleich mit NULL trifft in SQL
     * niemals zu; die Zusicherung ist strukturell, nicht gefiltert.
     */
    public function testAnonymeMeldungenTauchenInKeinerEigenenListeAuf(): void
    {
        $ziel = $this->benutzer('Verkaeuferin');

        $this->meldungen->melden(
            null,
            Verwaltung::GEGENSTAND_BENUTZER,
            $ziel,
            Meldungen::GRUND_BELAESTIGUNG,
            'Schreibt mich nach jeder Absage erneut an.'
        );

        self::assertSame(0, $this->meldungen->meineMeldungen(0)['anzahl']);
        self::assertSame(0, $this->meldungen->meineMeldungen($ziel)['anzahl']);
    }

    /**
     * Ein gesperrtes Konto ist kein gemeldetes: Wer sein eigenes Konto meldet,
     * bittet um Hilfe ("mein Konto wurde uebernommen"). Das muss gehen.
     */
    public function testDasEigeneKontoDarfGemeldetWerden(): void
    {
        $melder = $this->benutzer('Melderin');

        $meldungId = $this->meldungen->melden(
            $melder,
            Verwaltung::GEGENSTAND_BENUTZER,
            $melder,
            Meldungen::GRUND_GESTOHLENE_IDENTITAET,
            'Mein Konto wurde uebernommen, bitte sperren.'
        );

        self::assertGreaterThan(0, $meldungId);
    }

    public function testAlleGegenstandsartenDerWeisslisteFunktionieren(): void
    {
        $kaeufer = $this->benutzer('Kaeuferin');
        $verkaeufer = $this->benutzer('Verkaeuferin');
        $melder = $this->benutzer('Melderin');
        $this->aufladen($kaeufer, 10000);

        $bestellungId = $this->bestellungen()->anlegen($kaeufer, $verkaeufer, [$this->position(5950)]);

        $faelle = [
            Verwaltung::GEGENSTAND_ANGEBOT => $this->angebot($verkaeufer),
            Verwaltung::GEGENSTAND_BENUTZER => $verkaeufer,
            Verwaltung::GEGENSTAND_BESTELLUNG => $bestellungId,
        ];

        foreach ($faelle as $art => $id) {
            $meldungId = $this->meldungen->melden(
                $melder,
                $art,
                $id,
                Meldungen::GRUND_BETRUG,
                'Etwas stimmt hier nicht.'
            );

            self::assertSame($art, $this->db->wert(
                'SELECT gegenstand_art FROM meldungen WHERE id = :id',
                ['id' => $meldungId]
            ));
        }
    }

    // --- Abweisungen -------------------------------------------------------

    public function testUnbekannteGegenstandsartWirdAbgewiesen(): void
    {
        $melder = $this->benutzer('Melderin');

        // 'nachricht' stand hier, solange der Chat fehlte und die Tabelle
        // 'nachrichten' nicht existierte. Beides ist da; die Art ist jetzt
        // zugelassen und wird von
        // testEineNachrichtLaesstSichMeldenSeitDerChatSteht geprueft.
        //
        // 'angebote' bleibt: der Tabellenname statt der Art — der Tippfehler,
        // der ohne Weissliste in den SQL-Text geriete.
        foreach (['angebote', 'nachrichten', '', 'BENUTZER'] as $art) {
            try {
                $this->meldungen->melden($melder, $art, 1, Meldungen::GRUND_BETRUG, 'Erlaeuterung.');
                self::fail('Die Gegenstandsart "' . $art . '" haette abgewiesen werden muessen.');
            } catch (MeldungsFehler $fehler) {
                self::assertSame('gegenstand_art_unbekannt', $fehler->schluessel());
            }
        }

        self::assertSame(0, (int) $this->db->wert('SELECT COUNT(*) FROM meldungen'));
    }

    /**
     * Eine Nachricht ist meldbar, seit es Nachrichten gibt.
     *
     * Diese Pruefung schliesst eine Luecke, die zwischen zwei Paketen lag: Der
     * Melden-Knopf im Chatfenster leitet auf '/melden?art=nachricht' weiter,
     * aber weder Meldungen::GEGENSTAENDE noch MeldeRouten::ARTEN kannten die
     * Art. Der Knopf lief damit in "kein Gegenstand gewaehlt" — der einzige
     * Meldeweg fuer Nachrichten war tot, und mit ihm die Voraussetzung, unter
     * der diese Plattform ohne Vorabpruefung veroeffentlicht (Art. 16 DSA).
     *
     * Beide Kommentare hatten die fehlende Zeile woertlich vorgesehen. Genau
     * deshalb steht hier jetzt ein Test und kein weiterer Kommentar.
     */
    public function testEineNachrichtLaesstSichMeldenSeitDerChatSteht(): void
    {
        $melder = $this->benutzer('Melderin');
        $absender = $this->benutzer('Absender');
        $nachrichtId = $this->nachricht($absender, $melder);

        $meldungId = $this->meldungen->melden(
            $melder,
            Unterhaltungen::GEGENSTAND_NACHRICHT,
            $nachrichtId,
            Meldungen::GRUND_BELAESTIGUNG,
            'Die Person schreibt mich nach der Sperre weiter an.'
        );

        $meldung = $this->db->eine('SELECT * FROM meldungen WHERE id = :id', ['id' => $meldungId]);

        self::assertNotNull($meldung);
        self::assertSame(Unterhaltungen::GEGENSTAND_NACHRICHT, $meldung['gegenstand_art']);
        self::assertSame($nachrichtId, (int) $meldung['gegenstand_id']);
    }

    /**
     * Eine Nachrichtenkennung, die es nicht gibt, wird abgewiesen.
     *
     * Die Existenzpruefung kommt mit der Weissliste geschenkt — aber nur, wenn
     * der Tabellenname dort richtig steht. Ein Zahlendreher in 'nachrichten'
     * faellt sonst erst auf, wenn jemand meldet.
     */
    public function testEineUnbekannteNachrichtWirdAbgewiesen(): void
    {
        $melder = $this->benutzer('Melderin');

        try {
            $this->meldungen->melden(
                $melder,
                Unterhaltungen::GEGENSTAND_NACHRICHT,
                987654,
                Meldungen::GRUND_BELAESTIGUNG,
                'Erlaeuterung.'
            );
            self::fail('Eine unbekannte Nachrichtenkennung haette abgewiesen werden muessen.');
        } catch (MeldungsFehler $fehler) {
            self::assertSame('gegenstand_unbekannt', $fehler->schluessel());
        }
    }

    public function testUnbekannterGrundWirdAbgewiesen(): void
    {
        $melder = $this->benutzer('Melderin');
        $angebotId = $this->angebot($this->benutzer('Verkaeuferin'));

        try {
            $this->meldungen->melden(
                $melder,
                Verwaltung::GEGENSTAND_ANGEBOT,
                $angebotId,
                'gefaellt_mir_nicht',
                'Weil es mir nicht gefaellt.'
            );
            self::fail('Ein unbekannter Grund haette abgewiesen werden muessen.');
        } catch (MeldungsFehler $fehler) {
            self::assertSame('grund_unbekannt', $fehler->schluessel());
        }

        self::assertSame(0, (int) $this->db->wert('SELECT COUNT(*) FROM meldungen'));
    }

    /**
     * Art. 16 Abs. 2 lit. a DSA verlangt eine hinreichend begruendete
     * Erlaeuterung; erst sie begruendet die tatsaechliche Kenntnis nach
     * Art. 16 Abs. 3 DSA.
     */
    public function testMeldungOhneErlaeuterungWirdAbgewiesen(): void
    {
        $melder = $this->benutzer('Melderin');
        $angebotId = $this->angebot($this->benutzer('Verkaeuferin'));

        try {
            $this->meldungen->melden(
                $melder,
                Verwaltung::GEGENSTAND_ANGEBOT,
                $angebotId,
                Meldungen::GRUND_SONSTIGES,
                "   \n  "
            );
            self::fail('Eine leere Erlaeuterung haette abgewiesen werden muessen.');
        } catch (MeldungsFehler $fehler) {
            self::assertSame('beschreibung_fehlt', $fehler->schluessel());
        }

        self::assertSame(0, (int) $this->db->wert('SELECT COUNT(*) FROM meldungen'));
    }

    public function testZuLangeErlaeuterungWirdAbgewiesen(): void
    {
        $melder = $this->benutzer('Melderin');
        $angebotId = $this->angebot($this->benutzer('Verkaeuferin'));

        try {
            $this->meldungen->melden(
                $melder,
                Verwaltung::GEGENSTAND_ANGEBOT,
                $angebotId,
                Meldungen::GRUND_SONSTIGES,
                str_repeat('a', 2001)
            );
            self::fail('Eine zu lange Erlaeuterung haette abgewiesen werden muessen.');
        } catch (MeldungsFehler $fehler) {
            self::assertSame('beschreibung_zu_lang', $fehler->schluessel());
        }
    }

    /**
     * meldungen.gegenstand_id ist polymorph und hat deshalb keinen
     * Fremdschluessel. Ohne diese Pruefung stuenden Meldungen auf Kennungen in
     * der Arbeitsliste, zu denen sich nichts anzeigen laesst.
     */
    public function testMeldungAufEinenNichtVorhandenenGegenstandWirdAbgewiesen(): void
    {
        $melder = $this->benutzer('Melderin');

        foreach ([4711, 0, -1] as $kennung) {
            try {
                $this->meldungen->melden(
                    $melder,
                    Verwaltung::GEGENSTAND_ANGEBOT,
                    $kennung,
                    Meldungen::GRUND_BETRUG,
                    'Erlaeuterung.'
                );
                self::fail('Die Kennung ' . $kennung . ' haette abgewiesen werden muessen.');
            } catch (MeldungsFehler $fehler) {
                self::assertSame('gegenstand_unbekannt', $fehler->schluessel());
            }
        }

        self::assertSame(0, (int) $this->db->wert('SELECT COUNT(*) FROM meldungen'));
    }

    public function testMeldungEinesUnbekanntenKontosWirdAbgewiesen(): void
    {
        $angebotId = $this->angebot($this->benutzer('Verkaeuferin'));

        try {
            $this->meldungen->melden(
                999,
                Verwaltung::GEGENSTAND_ANGEBOT,
                $angebotId,
                Meldungen::GRUND_BETRUG,
                'Erlaeuterung.'
            );
            self::fail('Ein unbekanntes meldendes Konto haette abgewiesen werden muessen.');
        } catch (MeldungsFehler $fehler) {
            self::assertSame('melder_unbekannt', $fehler->schluessel());
        }
    }

    // --- Doppelmeldung -----------------------------------------------------

    /**
     * Ohne diese Sperre liesse sich die Arbeitsliste der Verwaltung mit einem
     * Dutzend Klicks fluten — und die Zahl der Meldungen, die Dringlichkeit
     * ausdrueckt, waere wertlos.
     */
    public function testDieselbePersonMeldetDenselbenGegenstandNichtZweimalOffen(): void
    {
        $melder = $this->benutzer('Melderin');
        $angebotId = $this->angebot($this->benutzer('Verkaeuferin'));

        $this->meldungen->melden(
            $melder,
            Verwaltung::GEGENSTAND_ANGEBOT,
            $angebotId,
            Meldungen::GRUND_BETRUG,
            'Ware wurde nie versendet.'
        );

        try {
            $this->meldungen->melden(
                $melder,
                Verwaltung::GEGENSTAND_ANGEBOT,
                $angebotId,
                Meldungen::GRUND_BELAESTIGUNG,
                'Und ausserdem schreibt sie mich an.'
            );
            self::fail('Die zweite offene Meldung haette abgewiesen werden muessen.');
        } catch (MeldungsFehler $fehler) {
            self::assertSame('bereits_gemeldet', $fehler->schluessel());
        }

        self::assertSame(1, (int) $this->db->wert('SELECT COUNT(*) FROM meldungen'));
        // Und keine zweite Empfangsbestaetigung.
        self::assertCount(1, $this->verwaltung->benachrichtigungen($melder));
    }

    /**
     * Die Sperre haengt am Status, nicht am Gegenstand: Nach der Entscheidung
     * muss dieselbe Person denselben Gegenstand erneut melden koennen — der
     * Inhalt kann sich geaendert haben.
     */
    public function testNachDerEntscheidungLaesstSichErneutMelden(): void
    {
        $verwalter = $this->verwalterin();
        $melder = $this->benutzer('Melderin');
        $angebotId = $this->angebot($this->benutzer('Verkaeuferin'));

        $ersteId = $this->meldungen->melden(
            $melder,
            Verwaltung::GEGENSTAND_ANGEBOT,
            $angebotId,
            Meldungen::GRUND_BETRUG,
            'Ware wurde nie versendet.'
        );

        $this->verwaltung->meldungBearbeiten(
            $verwalter,
            $ersteId,
            Verwaltung::MELDUNG_ABGELEHNT,
            'Kein Verstoss erkennbar.'
        );

        $zweiteId = $this->meldungen->melden(
            $melder,
            Verwaltung::GEGENSTAND_ANGEBOT,
            $angebotId,
            Meldungen::GRUND_BETRUG,
            'Jetzt ist es wieder passiert.'
        );

        self::assertNotSame($ersteId, $zweiteId);
        self::assertSame(2, (int) $this->db->wert('SELECT COUNT(*) FROM meldungen'));
    }

    /**
     * Die Sperre gilt je Person und je Gegenstand — nicht darueber hinaus.
     */
    public function testVerschiedenePersonenUndVerschiedeneGegenstaendeBleibenMoeglich(): void
    {
        $eine = $this->benutzer('Eine');
        $andere = $this->benutzer('Andere');
        $verkaeufer = $this->benutzer('Verkaeuferin');
        $ersterId = $this->angebot($verkaeufer, 'Erstes');
        $zweiterId = $this->angebot($verkaeufer, 'Zweites');

        $this->meldungen->melden($eine, Verwaltung::GEGENSTAND_ANGEBOT, $ersterId, Meldungen::GRUND_BETRUG, 'A');
        $this->meldungen->melden($andere, Verwaltung::GEGENSTAND_ANGEBOT, $ersterId, Meldungen::GRUND_BETRUG, 'B');
        $this->meldungen->melden($eine, Verwaltung::GEGENSTAND_ANGEBOT, $zweiterId, Meldungen::GRUND_BETRUG, 'C');
        // Dieselbe Kennung, andere Art: 'benutzer 1' ist nicht 'angebot 1'.
        $this->meldungen->melden($eine, Verwaltung::GEGENSTAND_BENUTZER, $verkaeufer, Meldungen::GRUND_BETRUG, 'D');

        self::assertSame(4, (int) $this->db->wert('SELECT COUNT(*) FROM meldungen'));
    }

    /**
     * Anonyme Meldungen lassen sich nicht entdoppeln — es gibt keine Person,
     * an der sich die Doppelung festmachen liesse. Das ist der Preis der
     * Anonymitaet und darf die Entgegennahme nicht blockieren.
     */
    public function testAnonymeMeldungenWerdenNichtGegeneinanderGeprueft(): void
    {
        $ziel = $this->benutzer('Verkaeuferin');

        $this->meldungen->melden(null, Verwaltung::GEGENSTAND_BENUTZER, $ziel, Meldungen::GRUND_BELAESTIGUNG, 'A');
        $this->meldungen->melden(null, Verwaltung::GEGENSTAND_BENUTZER, $ziel, Meldungen::GRUND_BELAESTIGUNG, 'B');

        self::assertSame(2, (int) $this->db->wert('SELECT COUNT(*) FROM meldungen'));
    }

    // --- Eigene Meldungen --------------------------------------------------

    public function testMeineMeldungenZeigenNurDieEigenenUndNeuesteZuerst(): void
    {
        $verwalter = $this->verwalterin();
        $eine = $this->benutzer('Eine');
        $andere = $this->benutzer('Andere');
        $verkaeufer = $this->benutzer('Verkaeuferin');
        $ersterId = $this->angebot($verkaeufer, 'Erstes');
        $zweiterId = $this->angebot($verkaeufer, 'Zweites');

        $erste = $this->meldungen->melden(
            $eine,
            Verwaltung::GEGENSTAND_ANGEBOT,
            $ersterId,
            Meldungen::GRUND_BETRUG,
            'Erste Meldung.'
        );
        $zweite = $this->meldungen->melden(
            $eine,
            Verwaltung::GEGENSTAND_ANGEBOT,
            $zweiterId,
            Meldungen::GRUND_URHEBERRECHT,
            'Zweite Meldung.'
        );
        $this->meldungen->melden(
            $andere,
            Verwaltung::GEGENSTAND_ANGEBOT,
            $ersterId,
            Meldungen::GRUND_BETRUG,
            'Fremde Meldung.'
        );

        $seite = $this->meldungen->meineMeldungen($eine);

        self::assertSame(2, $seite['anzahl']);
        self::assertSame(1, $seite['seiten']);
        self::assertSame(Meldungen::PRO_SEITE, $seite['pro_seite']);
        self::assertSame(
            [$zweite, $erste],
            array_map(static fn (array $z): int => (int) $z['id'], $seite['zeilen'])
        );
        self::assertSame($zweiterId, $seite['zeilen'][0]['gegenstand_id']);
        self::assertFalse($seite['zeilen'][0]['erledigt']);

        // Art. 16 Abs. 5 DSA: Die Entscheidung ueber die eigene Meldung muss
        // ankommen — sonst waere die Liste ein Postausgang ohne Antwort.
        $this->verwaltung->meldungBearbeiten(
            $verwalter,
            $erste,
            Verwaltung::MELDUNG_ERLEDIGT,
            'Angebot gesperrt, Konto verwarnt.'
        );

        $zeilen = $this->meldungen->meineMeldungen($eine)['zeilen'];
        $bearbeitet = $zeilen[0]['id'] === $erste ? $zeilen[0] : $zeilen[1];

        self::assertSame(Verwaltung::MELDUNG_ERLEDIGT, $bearbeitet['status']);
        self::assertSame('Angebot gesperrt, Konto verwarnt.', $bearbeitet['entscheidung']);
        self::assertTrue($bearbeitet['erledigt']);
    }

    public function testMeineMeldungenBlaetternUndBegrenzenDieSeite(): void
    {
        $melder = $this->benutzer('Melderin');
        $verkaeufer = $this->benutzer('Verkaeuferin');

        for ($i = 0; $i < Meldungen::PRO_SEITE + 3; $i++) {
            $this->meldungen->melden(
                $melder,
                Verwaltung::GEGENSTAND_ANGEBOT,
                $this->angebot($verkaeufer, 'Angebot ' . $i),
                Meldungen::GRUND_SONSTIGES,
                'Meldung ' . $i
            );
        }

        $erste = $this->meldungen->meineMeldungen($melder);
        self::assertSame(Meldungen::PRO_SEITE + 3, $erste['anzahl']);
        self::assertSame(2, $erste['seiten']);
        self::assertCount(Meldungen::PRO_SEITE, $erste['zeilen']);

        self::assertCount(3, $this->meldungen->meineMeldungen($melder, 2)['zeilen']);

        // Eine Seite jenseits des Endes rutscht auf die letzte, statt leer zu
        // sein — dieselbe Regel wie in Verwaltung::blaettern().
        self::assertSame(2, $this->meldungen->meineMeldungen($melder, 99)['seite']);
        self::assertSame(1, $this->meldungen->meineMeldungen($melder, 0)['seite']);
    }

    public function testMeineMeldungenSindOhneMeldungenLeerAberVollstaendig(): void
    {
        $seite = $this->meldungen->meineMeldungen($this->benutzer('Melderin'));

        self::assertSame([], $seite['zeilen']);
        self::assertSame(0, $seite['anzahl']);
        self::assertSame(1, $seite['seite']);
        self::assertSame(1, $seite['seiten']);
    }

    // --- Hilfen ------------------------------------------------------------

    private function verwalterin(string $pseudonym = 'Verwalterin'): int
    {
        $id = $this->benutzer($pseudonym);
        (new Konten($this->db))->faehigkeitFreischalten($id, Konten::FAEHIGKEIT_VERWALTEN, 'kommandozeile');

        return $id;
    }

    private function angebot(int $verkaeuferId, string $titel = 'Getragener Slip'): int
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
            'status' => 'aktiv',
            'versand_moeglich' => 1,
            'uebergabe_moeglich' => 0,
            'uebergabe_region' => null,
            'bearbeitungstage' => 3,
            'angelegt_am' => gmdate('Y-m-d H:i:s'),
            'geaendert_am' => null,
        ]);
    }

    /**
     * Eine Unterhaltung mit einer Nachricht darin, und deren Kennung.
     *
     * Bewusst ueber Unterhaltungen und nicht ueber zwei INSERTs von Hand: Der
     * kontext_schluessel entsteht dort und nirgends sonst. Ein von Hand
     * zusammengesetzter Schluessel waere eine zweite Fassung derselben Regel
     * und ginge beim naechsten Umbau auseinander.
     */
    private function nachricht(int $absenderId, int $empfaengerId): int
    {
        $unterhaltungen = new Unterhaltungen($this->db);

        // Beide Seiten brauchen eine Deklaration, bevor sie schreiben duerfen.
        $profile = new Profile($this->db);
        $profile->deklarationSetzen($absenderId, Profile::DEKLARATION_PERSON);
        $profile->deklarationSetzen($empfaengerId, Profile::DEKLARATION_PERSON);

        $unterhaltungId = $unterhaltungen->eroeffnen($absenderId, $empfaengerId);

        return $unterhaltungen->senden($unterhaltungId, $absenderId, 'Hallo, bist du noch da?');
    }
}
