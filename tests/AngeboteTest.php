<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Domain\Account\Konten;
use MeinSlip\Domain\Catalog\AngebotFehler;
use MeinSlip\Domain\Catalog\Angebote;

final class AngeboteTest extends Testfall
{
    private Angebote $angebote;

    private Konten $konten;

    protected function setUp(): void
    {
        parent::setUp();
        $this->angebote = new Angebote($this->db);
        $this->konten = new Konten($this->db);
    }

    // --- Anlegen ---------------------------------------------------------

    /**
     * Kern des Kontenmodells: Verkaufen ist eine Faehigkeit, kein Kontotyp.
     * Ein Konto ohne Identitaetspruefung darf nichts einstellen.
     */
    public function testAnlegenOhneVerkaufsfaehigkeitScheitert(): void
    {
        $ohneFaehigkeit = $this->benutzer('Nora');

        try {
            $this->angebote->anlegen($ohneFaehigkeit, $this->kategorie(), 'Getragene Socken', 'Beschreibung', 2500);
            self::fail('Es haette ein AngebotFehler geworfen werden muessen.');
        } catch (AngebotFehler $fehler) {
            self::assertSame('keine_verkaufsfaehigkeit', $fehler->schluessel());
        }

        self::assertSame([], $this->angebote->meine($ohneFaehigkeit));
    }

    public function testAnlegenErzeugtEinenEntwurf(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $angebotId = $this->angebote->anlegen($verkaeufer, $this->kategorie(), 'Getragene Socken', 'Zwei Tage', 2500);

        $angebot = $this->angebote->laden($angebotId);

        self::assertNotNull($angebot);
        self::assertSame(Angebote::STATUS_ENTWURF, $angebot['status']);
        self::assertSame('Getragene Socken', $angebot['titel']);
        self::assertSame(2500, (int) $angebot['grundpreis_cent']);
        self::assertSame('EUR', $angebot['waehrung']);
        self::assertSame([], $angebot['optionen']);
    }

    /** @return iterable<string, array{string,int,bool,string}> */
    public static function ungueltigeStammdaten(): iterable
    {
        yield 'Titel leer' => ['   ', 2500, true, 'titel_fehlt'];
        yield 'Titel zu lang' => [str_repeat('a', 191), 2500, true, 'titel_zu_lang'];
        yield 'Grundpreis null' => ['Gueltiger Titel', 0, true, 'grundpreis_ungueltig'];
        yield 'Grundpreis negativ' => ['Gueltiger Titel', -100, true, 'grundpreis_ungueltig'];
        yield 'Kategorie unbekannt' => ['Gueltiger Titel', 2500, false, 'kategorie_unbekannt'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ungueltigeStammdaten')]
    public function testUngueltigeStammdatenWerdenAbgewiesen(
        string $titel,
        int $grundpreisCent,
        bool $kategorieBekannt,
        string $erwarteterSchluessel
    ): void {
        $verkaeufer = $this->verkaeufer('Lina');
        $kategorie = $kategorieBekannt ? $this->kategorie() : 999999;

        try {
            $this->angebote->anlegen($verkaeufer, $kategorie, $titel, 'Beschreibung', $grundpreisCent);
            self::fail('Es haette ein AngebotFehler geworfen werden muessen.');
        } catch (AngebotFehler $fehler) {
            self::assertSame($erwarteterSchluessel, $fehler->schluessel());
        }
    }

    // --- Eigentum --------------------------------------------------------

    public function testFremderBenutzerDarfNichtBearbeiten(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $fremde = $this->verkaeufer('Mara');
        $angebotId = $this->entwurf($verkaeufer);

        try {
            $this->angebote->bearbeiten($angebotId, $fremde, ['titel' => 'Uebernommen']);
            self::fail('Es haette ein AngebotFehler geworfen werden muessen.');
        } catch (AngebotFehler $fehler) {
            self::assertSame('nicht_der_eigentuemer', $fehler->schluessel());
        }

        $angebot = $this->angebote->laden($angebotId);
        self::assertSame('Getragene Socken', $angebot['titel'], 'Der Titel darf sich nicht geaendert haben.');
    }

    public function testFremderBenutzerDarfKeineOptionSetzen(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $fremde = $this->verkaeufer('Mara');
        $angebotId = $this->entwurf($verkaeufer);

        $this->expectException(AngebotFehler::class);

        $this->angebote->optionSetzen($angebotId, $fremde, 'tragedauer', 'Tragedauer');
    }

    public function testEigenpruefungIstUnzulaessig(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $angebotId = $this->entwurf($verkaeufer);
        $this->angebote->optionSetzen($angebotId, $verkaeufer, 'tragedauer', 'Tragedauer', Angebote::ART_ZAHL);
        $this->angebote->zurPruefungEinreichen($angebotId, $verkaeufer);

        try {
            $this->angebote->freigeben($angebotId, $verkaeufer);
            self::fail('Niemand darf das eigene Angebot freigeben.');
        } catch (AngebotFehler $fehler) {
            self::assertSame('eigenpruefung_unzulaessig', $fehler->schluessel());
        }
    }

    // --- Bearbeiten und Optionen ----------------------------------------

    public function testBearbeitenAendertNurErlaubteFelder(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $angebotId = $this->entwurf($verkaeufer);

        $this->angebote->bearbeiten($angebotId, $verkaeufer, [
            'titel' => 'Neuer Titel',
            'grundpreis_cent' => 3900,
            'uebergabe_moeglich' => true,
            'uebergabe_region' => 'Raum Muenchen',
        ]);

        $angebot = $this->angebote->laden($angebotId);
        self::assertSame('Neuer Titel', $angebot['titel']);
        self::assertSame(3900, (int) $angebot['grundpreis_cent']);
        self::assertSame(1, (int) $angebot['uebergabe_moeglich']);
        self::assertSame('Raum Muenchen', $angebot['uebergabe_region']);
    }

    public function testUnbekanntesFeldWirdAbgewiesen(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $angebotId = $this->entwurf($verkaeufer);

        try {
            $this->angebote->bearbeiten($angebotId, $verkaeufer, ['status' => Angebote::STATUS_AKTIV]);
            self::fail('Der Status darf nicht ueber bearbeiten() wandern.');
        } catch (AngebotFehler $fehler) {
            self::assertSame('feld_unbekannt', $fehler->schluessel());
        }
    }

    /** Ein aktives Angebot darf seine Beschaffenheit nicht mehr aendern. */
    public function testAktivesAngebotIstNichtBearbeitbar(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $angebotId = $this->aktivesAngebot($verkaeufer);

        try {
            $this->angebote->bearbeiten($angebotId, $verkaeufer, ['grundpreis_cent' => 100]);
            self::fail('Es haette ein AngebotFehler geworfen werden muessen.');
        } catch (AngebotFehler $fehler) {
            self::assertSame('nicht_bearbeitbar', $fehler->schluessel());
        }
    }

    public function testOptionSetzenUeberschreibtDieGleichnamige(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $angebotId = $this->entwurf($verkaeufer);

        $erste = $this->angebote->optionSetzen($angebotId, $verkaeufer, 'tragedauer', 'Tragedauer', Angebote::ART_ZAHL);
        $zweite = $this->angebote->optionSetzen(
            $angebotId,
            $verkaeufer,
            'tragedauer',
            'Tragedauer in Tagen',
            Angebote::ART_ZAHL,
            500
        );

        self::assertSame($erste, $zweite, 'Derselbe Schluessel darf keine zweite Option anlegen.');

        $angebot = $this->angebote->laden($angebotId);
        self::assertCount(1, $angebot['optionen']);
        self::assertSame('Tragedauer in Tagen', $angebot['optionen'][0]['bezeichnung']);
        self::assertSame(500, (int) $angebot['optionen'][0]['aufpreis_cent']);
    }

    /**
     * Die Zusage "ein Schluessel je Angebot" traegt der eindeutige Index aus
     * Migration 009, nicht die Abfrage in optionSetzen(): Zwei gleichzeitige
     * Anfragen sehen dort beide nichts und fuegen beide ein. Danach laeuft der
     * Bestellpfad ueber die Optionszeilen und addiert denselben Aufpreis
     * zweimal — die Kaeuferin zahlt einen einmal gewaehlten Aufpreis doppelt.
     *
     * Der Test haelt zugleich den SQLSTATE fest, auf den sich der Fangblock in
     * optionSetzen() stuetzt. Faellt der Index weg oder meldet die Datenbank
     * anders, faellt das hier auf und nicht erst im Hauptbuch.
     */
    public function testDatenbankWeistDieZweiteOptionMitGleichemSchluesselAb(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $angebotId = $this->entwurf($verkaeufer);
        $this->angebote->optionSetzen($angebotId, $verkaeufer, 'tragedauer', 'Tragedauer', Angebote::ART_ZAHL);

        try {
            $this->db->einfuegen('angebot_optionen', [
                'angebot_id' => $angebotId,
                'schluessel' => 'tragedauer',
                'bezeichnung' => 'Tragedauer, zweite Zeile',
                'erlaeuterung' => null,
                'aufpreis_cent' => 500,
                'art' => Angebote::ART_ZAHL,
                'ist_spezifikation' => 1,
                'pflicht' => 0,
                'reihenfolge' => 0,
                'aktiv' => 1,
                'angelegt_am' => gmdate('Y-m-d H:i:s'),
            ]);
            self::fail('Der eindeutige Index haette die zweite Zeile abweisen muessen.');
        } catch (\PDOException $fehler) {
            self::assertSame(
                '23000',
                (string) $fehler->getCode(),
                'optionSetzen() faengt genau diesen SQLSTATE ab.'
            );
        }

        self::assertSame(
            1,
            (int) $this->db->wert(
                'SELECT COUNT(*) FROM angebot_optionen WHERE angebot_id = :a AND schluessel = :s',
                ['a' => $angebotId, 's' => 'tragedauer']
            )
        );
    }

    public function testOptionEntfernenLoeschtSie(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $angebotId = $this->entwurf($verkaeufer);
        $this->angebote->optionSetzen($angebotId, $verkaeufer, 'tragedauer', 'Tragedauer', Angebote::ART_ZAHL);

        $this->angebote->optionEntfernen($angebotId, $verkaeufer, 'tragedauer');

        $angebot = $this->angebote->laden($angebotId);
        self::assertSame([], $angebot['optionen']);
    }

    public function testUnbekannteOptionsartWirdAbgewiesen(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $angebotId = $this->entwurf($verkaeufer);

        try {
            $this->angebote->optionSetzen($angebotId, $verkaeufer, 'tragedauer', 'Tragedauer', 'schieberegler');
            self::fail('Es haette ein AngebotFehler geworfen werden muessen.');
        } catch (AngebotFehler $fehler) {
            self::assertSame('option_art_unbekannt', $fehler->schluessel());
        }
    }

    // --- Pruefung --------------------------------------------------------

    /**
     * Ohne Spezifikationsoption ist die Ware nicht "nach Kundenspezifikation
     * angefertigt" (§ 312g Abs. 2 Nr. 1 BGB) — und Bestellungen::anlegen()
     * wuerde jede Position dazu ohnehin abweisen.
     */
    public function testEinreichenOhneSpezifikationsoptionScheitert(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $angebotId = $this->entwurf($verkaeufer);

        // Eine Option, die KEINE Spezifikation ist, genuegt ausdruecklich nicht.
        $this->angebote->optionSetzen(
            $angebotId,
            $verkaeufer,
            'geschenkbeutel',
            'Geschenkbeutel',
            Angebote::ART_AUSWAHL,
            200,
            false
        );

        try {
            $this->angebote->zurPruefungEinreichen($angebotId, $verkaeufer);
            self::fail('Es haette ein AngebotFehler geworfen werden muessen.');
        } catch (AngebotFehler $fehler) {
            self::assertSame('keine_spezifikation', $fehler->schluessel());
        }

        self::assertSame(Angebote::STATUS_ENTWURF, $this->statusVon($angebotId));
    }

    /**
     * Ein Ja/Nein-Kaestchen traegt den Widerrufsausschluss nicht.
     *
     * ART_AUSWAHL rendert als Ankreuzfeld mit dem festen Wert "ja"
     * (resources/views/markt/bestellen.php) — die Kaeuferin schreibt nichts,
     * an der Ware individualisiert sich nichts. § 312g Abs. 2 Nr. 1 BGB und
     * EuGH C-529/19 ("Moebel Kraft") verlangen aber eine Anfertigung nach
     * Kundenspezifikation. Ohne diese Grenze machte die Vorbelegung des
     * Verkaeuferformulars (art = auswahl, ist_spezifikation angekreuzt) aus
     * jeder Zusatzwahl eine Scheinspezifikation.
     */
    public function testEinreichenMitBlossemAnkreuzfeldAlsSpezifikationScheitert(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $angebotId = $this->entwurf($verkaeufer);

        $this->angebote->optionSetzen(
            $angebotId,
            $verkaeufer,
            'geschenkverpackung',
            'Geschenkverpackung',
            Angebote::ART_AUSWAHL,
            0,
            true
        );

        try {
            $this->angebote->zurPruefungEinreichen($angebotId, $verkaeufer);
            self::fail('Es haette ein AngebotFehler geworfen werden muessen.');
        } catch (AngebotFehler $fehler) {
            self::assertSame('spezifikation_braucht_eingabe', $fehler->schluessel());
        }

        self::assertSame(Angebote::STATUS_ENTWURF, $this->statusVon($angebotId));
    }

    /**
     * Der Weg aus dem Fehler heraus muss offen sein: Die Verkaeuferin aendert
     * die Art derselben Option und reicht erneut ein. Ohne diese Zusicherung
     * waere ein Angebot mit Kaestchen-Spezifikation eine Sackgasse.
     */
    public function testArtDerOptionAendernOeffnetDieEinreichungWieder(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $angebotId = $this->entwurf($verkaeufer);
        $this->angebote->optionSetzen(
            $angebotId,
            $verkaeufer,
            'tragedauer',
            'Tragedauer',
            Angebote::ART_AUSWAHL,
            0,
            true
        );

        $this->angebote->optionSetzen(
            $angebotId,
            $verkaeufer,
            'tragedauer',
            'Tragedauer in Tagen',
            Angebote::ART_ZAHL,
            0,
            true
        );

        $this->angebote->zurPruefungEinreichen($angebotId, $verkaeufer);

        self::assertSame(Angebote::STATUS_IN_PRUEFUNG, $this->statusVon($angebotId));
    }

    /** @return iterable<string, array{string}> */
    public static function tragendeSpezifikationsarten(): iterable
    {
        yield 'Zahl' => [Angebote::ART_ZAHL];
        yield 'Freitext' => [Angebote::ART_FREITEXT];
    }

    /** Nur diese beiden Arten nehmen einen von der Kaeuferin geschriebenen Wert auf. */
    #[\PHPUnit\Framework\Attributes\DataProvider('tragendeSpezifikationsarten')]
    public function testSpezifikationMitEigenemWertTraegtDieEinreichung(string $art): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $angebotId = $this->entwurf($verkaeufer);
        $this->angebote->optionSetzen($angebotId, $verkaeufer, 'tragedauer', 'Tragedauer', $art, 0, true);

        $this->angebote->zurPruefungEinreichen($angebotId, $verkaeufer);

        self::assertSame(Angebote::STATUS_IN_PRUEFUNG, $this->statusVon($angebotId));
    }

    /**
     * Die Grenze verbietet das Kaestchen nicht, sie laesst es nur nicht als
     * Spezifikation zaehlen. Neben einer echten Spezifikation darf es bleiben.
     */
    public function testKaestchenNebenEchterSpezifikationSchadetNicht(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $angebotId = $this->entwurf($verkaeufer);
        $this->angebote->optionSetzen(
            $angebotId,
            $verkaeufer,
            'geschenkverpackung',
            'Geschenkverpackung',
            Angebote::ART_AUSWAHL,
            200,
            true
        );
        $this->angebote->optionSetzen(
            $angebotId,
            $verkaeufer,
            'tragedauer',
            'Tragedauer',
            Angebote::ART_FREITEXT,
            0,
            true
        );

        $this->angebote->zurPruefungEinreichen($angebotId, $verkaeufer);

        self::assertSame(Angebote::STATUS_IN_PRUEFUNG, $this->statusVon($angebotId));
    }

    /**
     * Die Liste ist die veroeffentlichte Grenze: app/Http/MarktRouten.php muss
     * beim Bestellen dieselbe lesen, statt sie nachzubauen. Wer ART_AUSWAHL
     * hier ergaenzt, hebt den Widerrufsausschluss auf.
     */
    public function testAnkreuzenGehoertNichtZuDenSpezifikationsarten(): void
    {
        self::assertNotContains(Angebote::ART_AUSWAHL, Angebote::SPEZIFIKATIONSARTEN);
        self::assertContains(Angebote::ART_ZAHL, Angebote::SPEZIFIKATIONSARTEN);
        self::assertContains(Angebote::ART_FREITEXT, Angebote::SPEZIFIKATIONSARTEN);
    }

    public function testWegVomEntwurfUeberDiePruefungZuAktiv(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $pruefende = $this->benutzer('Pruefstelle');
        $angebotId = $this->entwurf($verkaeufer);
        $this->angebote->optionSetzen($angebotId, $verkaeufer, 'tragedauer', 'Tragedauer', Angebote::ART_ZAHL);

        self::assertSame(Angebote::STATUS_ENTWURF, $this->statusVon($angebotId));

        $this->angebote->zurPruefungEinreichen($angebotId, $verkaeufer);
        self::assertSame(Angebote::STATUS_IN_PRUEFUNG, $this->statusVon($angebotId));

        $this->angebote->freigeben($angebotId, $pruefende);
        self::assertSame(Angebote::STATUS_AKTIV, $this->statusVon($angebotId));
    }

    /**
     * Freigeben ist das Ergebnis einer Pruefung, nicht der Weg aus der Pause.
     *
     * Die Uebergangstabelle kann diesen Fall nicht erfassen: pausiert -> aktiv
     * ist erlaubt, weil fortsetzen() ihn braucht. Ohne die eigene Vorbedingung
     * in freigeben() entpausiert ein Klick der Verwaltung ein Angebot, das nie
     * in Pruefung war — mitten in der Bearbeitung, denn 'pausiert' ist neben
     * 'entwurf' der einzige Zustand, in dem Aenderungen erlaubt sind.
     */
    public function testFreigebenEntpausiertNicht(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $angebotId = $this->aktivesAngebot($verkaeufer);
        $this->angebote->pausieren($angebotId, $verkaeufer);

        try {
            $this->angebote->freigeben($angebotId, $this->pruefstelle());
            self::fail('Die Freigabe darf ein pausiertes Angebot nicht aktivieren.');
        } catch (AngebotFehler $fehler) {
            self::assertSame('statuswechsel_unzulaessig', $fehler->schluessel());
        }

        self::assertSame(Angebote::STATUS_PAUSIERT, $this->statusVon($angebotId));

        // Die Verkaeuferin behaelt ihren Bearbeitungszustand — genau das
        // nimmt ihr die Freigabe sonst weg.
        $this->angebote->bearbeiten($angebotId, $verkaeufer, ['grundpreis_cent' => 3900]);
        self::assertSame(3900, (int) $this->angebote->laden($angebotId)['grundpreis_cent']);
    }

    public function testAblehnenFuehrtZurueckInDenEntwurf(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $pruefende = $this->benutzer('Pruefstelle');
        $angebotId = $this->entwurf($verkaeufer);
        $this->angebote->optionSetzen($angebotId, $verkaeufer, 'tragedauer', 'Tragedauer', Angebote::ART_ZAHL);
        $this->angebote->zurPruefungEinreichen($angebotId, $verkaeufer);

        $this->angebote->ablehnen($angebotId, $pruefende, 'Das Bild zeigt eine andere Ware.');

        self::assertSame(Angebote::STATUS_ENTWURF, $this->statusVon($angebotId));
    }

    public function testAblehnungBrauchtEineBegruendung(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $pruefende = $this->benutzer('Pruefstelle');
        $angebotId = $this->entwurf($verkaeufer);
        $this->angebote->optionSetzen($angebotId, $verkaeufer, 'tragedauer', 'Tragedauer', Angebote::ART_ZAHL);
        $this->angebote->zurPruefungEinreichen($angebotId, $verkaeufer);

        try {
            $this->angebote->ablehnen($angebotId, $pruefende, '   ');
            self::fail('Es haette ein AngebotFehler geworfen werden muessen.');
        } catch (AngebotFehler $fehler) {
            self::assertSame('ablehnungsgrund_fehlt', $fehler->schluessel());
        }

        self::assertSame(Angebote::STATUS_IN_PRUEFUNG, $this->statusVon($angebotId));
    }

    // --- Veroeffentlichen und Nachmoderation -----------------------------

    /**
     * Der Kern des Modellwechsels: registrieren, einstellen, sichtbar — ohne
     * einen einzigen Verwaltungseingriff.
     *
     * Der Test setzt bewusst KEINE Option. Waere die Spezifikationspruefung
     * aus zurPruefungEinreichen() mit nach veroeffentlichen() gewandert,
     * stuende hier ein 'keine_spezifikation' und das Tor waere nur verschoben:
     * Wer eine Ware zeigen will, muesste sie erst verkaufsfertig
     * konfigurieren. Sichtbarkeit und Bestellbarkeit sind zwei Fragen, und die
     * zweite beantwortet istBestellbar().
     */
    public function testVeroeffentlichenGehtDirektVomEntwurfNachAktiv(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $kategorie = $this->kategorie();
        $angebotId = $this->entwurf($verkaeufer, $kategorie);

        $this->angebote->veroeffentlichen($angebotId, $verkaeufer);

        self::assertSame(Angebote::STATUS_AKTIV, $this->statusVon($angebotId));
        self::assertCount(1, $this->angebote->fuerKatalog($kategorie), 'Sofort im Katalog, ohne Freigabe.');
        self::assertFalse(
            $this->angebote->istBestellbar($angebotId),
            'Sichtbar ist nicht dasselbe wie bestellbar — sonst waere das Tor nur verschoben.'
        );
    }

    public function testFremderDarfNichtVeroeffentlichen(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $fremde = $this->verkaeufer('Mara');
        $angebotId = $this->entwurf($verkaeufer);

        try {
            $this->angebote->veroeffentlichen($angebotId, $fremde);
            self::fail('Nur die Eigentuemerin darf ihr Angebot veroeffentlichen.');
        } catch (AngebotFehler $fehler) {
            self::assertSame('nicht_der_eigentuemer', $fehler->schluessel());
        }

        self::assertSame(Angebote::STATUS_ENTWURF, $this->statusVon($angebotId));
    }

    /**
     * Die Abhilfemassnahme nach Art. 16 Abs. 6 DSA. Sie ist der Preis fuer die
     * abgeschaffte Vorabpruefung: Ein gemeldetes Angebot muss binnen kurzer
     * Zeit aus jeder oeffentlichen Liste verschwinden.
     */
    public function testSperrenNimmtDasAngebotVomMarkt(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $kategorie = $this->kategorie();
        $angebotId = $this->veroeffentlicht($verkaeufer, $kategorie);

        self::assertSame(1, $this->angebote->anzahlImKatalog($kategorie));

        $this->angebote->sperren($angebotId, $this->pruefstelle(), 'Gemeldet: Das Bild zeigt fremde Ware.');

        self::assertSame(Angebote::STATUS_GESPERRT, $this->statusVon($angebotId));
        self::assertSame([], $this->angebote->fuerKatalog($kategorie));
        self::assertSame(0, $this->angebote->anzahlImKatalog($kategorie));
        self::assertSame([], $this->angebote->vonVerkaeufer($verkaeufer));
    }

    /**
     * Ohne Grund keine Sperre: Der Grund ist nach Art. 17 DSA Teil der
     * Begruendung gegenueber der betroffenen Person und die Grundlage ihrer
     * Beschwerde nach Art. 20 DSA. Eine Sperre ohne ihn waere unanfechtbar.
     */
    public function testSperrenBrauchtEineBegruendung(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $angebotId = $this->veroeffentlicht($verkaeufer);

        try {
            $this->angebote->sperren($angebotId, $this->pruefstelle(), '   ');
            self::fail('Es haette ein AngebotFehler geworfen werden muessen.');
        } catch (AngebotFehler $fehler) {
            self::assertSame('sperrgrund_fehlt', $fehler->schluessel());
        }

        self::assertSame(Angebote::STATUS_AKTIV, $this->statusVon($angebotId));
    }

    /** Vier-Augen-Prinzip wie bei jeder anderen Entscheidung ueber fremde Ware. */
    public function testNiemandSperrtDasEigeneAngebot(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $angebotId = $this->veroeffentlicht($verkaeufer);

        try {
            $this->angebote->sperren($angebotId, $verkaeufer, 'Aus dem Weg geraeumt.');
            self::fail('Niemand darf ueber das eigene Angebot entscheiden.');
        } catch (AngebotFehler $fehler) {
            self::assertSame('eigenpruefung_unzulaessig', $fehler->schluessel());
        }

        self::assertSame(Angebote::STATUS_AKTIV, $this->statusVon($angebotId));
    }

    /** Art. 20 DSA: Eine Beschwerde muss die Sperre heilen koennen. */
    public function testEntsperrenStelltDasAngebotWiederHer(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $kategorie = $this->kategorie();
        $angebotId = $this->veroeffentlicht($verkaeufer, $kategorie);
        $this->angebote->sperren($angebotId, $this->pruefstelle(), 'Gemeldet: angeblich fremde Ware.');

        $this->angebote->entsperren($angebotId, $this->pruefstelle(), 'Beschwerde begruendet, Ware ist eigene.');

        self::assertSame(Angebote::STATUS_AKTIV, $this->statusVon($angebotId));
        self::assertCount(1, $this->angebote->fuerKatalog($kategorie));
    }

    public function testEntsperrenBrauchtEineBegruendung(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $angebotId = $this->veroeffentlicht($verkaeufer);
        $this->angebote->sperren($angebotId, $this->pruefstelle(), 'Gemeldet.');

        try {
            $this->angebote->entsperren($angebotId, $this->pruefstelle(), '');
            self::fail('Es haette ein AngebotFehler geworfen werden muessen.');
        } catch (AngebotFehler $fehler) {
            self::assertSame('entsperrgrund_fehlt', $fehler->schluessel());
        }

        self::assertSame(Angebote::STATUS_GESPERRT, $this->statusVon($angebotId));
    }

    public function testNiemandEntsperrtDasEigeneAngebot(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $angebotId = $this->veroeffentlicht($verkaeufer);
        $this->angebote->sperren($angebotId, $this->pruefstelle(), 'Gemeldet.');

        try {
            $this->angebote->entsperren($angebotId, $verkaeufer, 'Ich sehe das anders.');
            self::fail('Die Beschwerde entscheidet die Verwaltung, nicht die Betroffene.');
        } catch (AngebotFehler $fehler) {
            self::assertSame('eigenpruefung_unzulaessig', $fehler->schluessel());
        }

        self::assertSame(Angebote::STATUS_GESPERRT, $this->statusVon($angebotId));
    }

    /**
     * Die Nachmoderation traegt den gesamten Torabbau. Kann die Verkaeuferin
     * ihre eigene Sperre aufheben, ist sie wertlos — und der Verzicht auf die
     * Vorabpruefung nicht mehr verteidigbar.
     *
     * Die Uebergangstabelle allein reicht dafuer nicht: 'gesperrt -> aktiv'
     * MUSS erlaubt sein (Art. 20 DSA), also haengt die Absicherung an den
     * Vorbedingungen in veroeffentlichen() und fortsetzen().
     */
    public function testVerkaeuferinKannIhreSperreNichtSelbstAufheben(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $angebotId = $this->veroeffentlicht($verkaeufer);
        $this->angebote->sperren($angebotId, $this->pruefstelle(), 'Gemeldet und geprueft.');

        try {
            $this->angebote->fortsetzen($angebotId, $verkaeufer);
            self::fail('fortsetzen() darf keine Sperre aufheben.');
        } catch (AngebotFehler $fehler) {
            self::assertSame('statuswechsel_unzulaessig', $fehler->schluessel());
        }

        try {
            $this->angebote->veroeffentlichen($angebotId, $verkaeufer);
            self::fail('veroeffentlichen() darf keine Sperre aufheben.');
        } catch (AngebotFehler $fehler) {
            self::assertSame('statuswechsel_unzulaessig', $fehler->schluessel());
        }

        // Und umschreiben laesst sich ein gesperrtes Angebot auch nicht: Sonst
        // bliebe die Sperre stehen und der Inhalt wechselte darunter.
        try {
            $this->angebote->bearbeiten($angebotId, $verkaeufer, ['titel' => 'Anderer Titel']);
            self::fail('Ein gesperrtes Angebot ist nicht bearbeitbar.');
        } catch (AngebotFehler $fehler) {
            self::assertSame('nicht_bearbeitbar', $fehler->schluessel());
        }

        self::assertSame(Angebote::STATUS_GESPERRT, $this->statusVon($angebotId));
    }

    /**
     * Vier Methoden steuern 'aktiv' an, seit der Modellwechsel die
     * Uebergangstabelle geoeffnet hat. Die Tabelle sagt nur, WELCHER Wechsel
     * zulaessig ist — nicht, WER ihn ausloesen darf. Deshalb prueft jede
     * Methode ihren Ausgangsstatus selbst, und dieser Test haelt genau das
     * fest: Ohne die Vorbedingungen waere jede von ihnen ein Weg, eine Sperre
     * aufzuheben oder einen fremden Entwurf zu veroeffentlichen.
     */
    public function testJederWegNachAktivPruefstSeinenAusgangsstatus(): void
    {
        $erlaubterAusgang = [
            'veroeffentlichen' => Angebote::STATUS_ENTWURF,
            'fortsetzen' => Angebote::STATUS_PAUSIERT,
            'freigeben' => Angebote::STATUS_IN_PRUEFUNG,
            'entsperren' => Angebote::STATUS_GESPERRT,
        ];

        $verkaeufer = $this->verkaeufer('Lina');
        $pruefende = $this->pruefstelle();
        $angebotId = $this->entwurf($verkaeufer);

        foreach ($erlaubterAusgang as $methode => $ausgang) {
            foreach (Angebote::STATUSWERTE as $von) {
                if ($von === $ausgang) {
                    continue;
                }

                $this->statusSetzen($angebotId, $von);

                try {
                    match ($methode) {
                        'veroeffentlichen' => $this->angebote->veroeffentlichen($angebotId, $verkaeufer),
                        'fortsetzen' => $this->angebote->fortsetzen($angebotId, $verkaeufer),
                        'freigeben' => $this->angebote->freigeben($angebotId, $pruefende),
                        'entsperren' => $this->angebote->entsperren($angebotId, $pruefende, 'Beschwerde begruendet.'),
                    };
                    self::fail(sprintf('%s() haette aus "%s" nicht aktivieren duerfen.', $methode, $von));
                } catch (AngebotFehler $fehler) {
                    self::assertSame(
                        'statuswechsel_unzulaessig',
                        $fehler->schluessel(),
                        $methode . '() aus "' . $von . '"'
                    );
                    self::assertSame($von, $this->statusVon($angebotId), 'Der Status darf sich nicht geaendert haben.');
                }
            }
        }
    }

    /** @return iterable<string, array{?string,bool}> */
    public static function bestellbarkeit(): iterable
    {
        yield 'ohne Option' => [null, false];
        yield 'nur Ankreuzfeld' => [Angebote::ART_AUSWAHL, false];
        yield 'Zahl' => [Angebote::ART_ZAHL, true];
        yield 'Freitext' => [Angebote::ART_FREITEXT, true];
    }

    /**
     * istBestellbar() legt die beiden Pruefungen aus § 312g Abs. 2 Nr. 1 BGB
     * offen, die bisher nur privat in zurPruefungEinreichen() sassen. Nach dem
     * Torabbau werden sie oeffentlich gebraucht: Ein Angebot darf sichtbar
     * sein, ohne bestellbar zu sein, und die Oberflaeche muss den Unterschied
     * zeigen koennen, statt die Kaeuferin erst in Bestellungen::anlegen()
     * auflaufen zu lassen.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('bestellbarkeit')]
    public function testIstBestellbarKenntDieGrenzeDesWiderrufsausschlusses(?string $art, bool $erwartet): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $angebotId = $this->entwurf($verkaeufer);

        if ($art !== null) {
            $this->angebote->optionSetzen($angebotId, $verkaeufer, 'tragedauer', 'Tragedauer', $art, 0, true);
        }

        self::assertSame($erwartet, $this->angebote->istBestellbar($angebotId));
    }

    // --- Zustandsmaschine ------------------------------------------------

    public function testPausierenUndFortsetzen(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $angebotId = $this->aktivesAngebot($verkaeufer);

        $this->angebote->pausieren($angebotId, $verkaeufer);
        self::assertSame(Angebote::STATUS_PAUSIERT, $this->statusVon($angebotId));

        $this->angebote->fortsetzen($angebotId, $verkaeufer);
        self::assertSame(Angebote::STATUS_AKTIV, $this->statusVon($angebotId));
    }

    public function testEntferntesAngebotBleibtEntfernt(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $angebotId = $this->aktivesAngebot($verkaeufer);

        $this->angebote->entfernen($angebotId, $verkaeufer);
        self::assertSame(Angebote::STATUS_ENTFERNT, $this->statusVon($angebotId));

        // Der Datensatz bleibt erhalten — Bestellpositionen verweisen darauf.
        self::assertNotNull($this->angebote->laden($angebotId));
    }

    /**
     * Erlaubte Uebergaenge, hier bewusst noch einmal von Hand aufgeschrieben:
     * Der Test soll den Vertrag pruefen, nicht die Tabelle der Klasse
     * gegen sich selbst.
     *
     * Mit dem Modellwechsel von 5x5 auf 6x6 gewachsen. Was hinzugekommen ist
     * und warum:
     *
     *  - 'entwurf -> aktiv' ist der Torabbau selbst. Ohne diese Zeile im Test
     *    wuerde ein spaeteres Zurueckdrehen der Uebergangstabelle als grosse
     *    Zahl neuer roter Tests auffallen — genau das ist gewollt.
     *  - 'gesperrt' ist aus jedem lebenden Status erreichbar: Eine Meldung
     *    kommt, wann sie kommt, und ein kurz pausiertes Angebot darf sich
     *    der Abhilfemassnahme nicht entziehen.
     *  - 'gesperrt -> aktiv' muss erlaubt bleiben (Art. 20 DSA), 'gesperrt ->
     *    pausiert' und 'gesperrt -> entwurf' dagegen nicht: Beide waeren
     *    Zustaende, aus denen die Verkaeuferin ohne Verwaltung wieder
     *    hochkaeme, die Sperre also selbst aufheben koennte.
     *
     * @return iterable<string, array{string,string}>
     */
    public static function unerlaubteWechsel(): iterable
    {
        $erlaubt = [
            Angebote::STATUS_ENTWURF => [
                Angebote::STATUS_AKTIV,
                Angebote::STATUS_IN_PRUEFUNG,
                Angebote::STATUS_GESPERRT,
                Angebote::STATUS_ENTFERNT,
            ],
            Angebote::STATUS_IN_PRUEFUNG => [
                Angebote::STATUS_AKTIV,
                Angebote::STATUS_ENTWURF,
                Angebote::STATUS_GESPERRT,
                Angebote::STATUS_ENTFERNT,
            ],
            Angebote::STATUS_AKTIV => [
                Angebote::STATUS_PAUSIERT,
                Angebote::STATUS_GESPERRT,
                Angebote::STATUS_ENTFERNT,
            ],
            Angebote::STATUS_PAUSIERT => [
                Angebote::STATUS_AKTIV,
                Angebote::STATUS_GESPERRT,
                Angebote::STATUS_ENTFERNT,
            ],
            Angebote::STATUS_GESPERRT => [Angebote::STATUS_AKTIV, Angebote::STATUS_ENTFERNT],
            Angebote::STATUS_ENTFERNT => [],
        ];

        foreach (Angebote::STATUSWERTE as $von) {
            foreach (Angebote::STATUSWERTE as $nach) {
                if (in_array($nach, $erlaubt[$von], true)) {
                    continue;
                }

                yield $von . ' -> ' . $nach => [$von, $nach];
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unerlaubteWechsel')]
    public function testUnerlaubterStatuswechselScheitert(string $von, string $nach): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $pruefende = $this->benutzer('Pruefstelle');
        $angebotId = $this->entwurf($verkaeufer);

        // Die Spezifikationsoption direkt einfuegen: optionSetzen() waere in
        // den meisten Ausgangsstatus gesperrt, und geprueft werden soll hier
        // allein der Statuswechsel.
        $this->db->einfuegen('angebot_optionen', [
            'angebot_id' => $angebotId,
            'schluessel' => 'tragedauer',
            'bezeichnung' => 'Tragedauer',
            'erlaeuterung' => null,
            'aufpreis_cent' => 0,
            'art' => Angebote::ART_ZAHL,
            'ist_spezifikation' => 1,
            'pflicht' => 1,
            'reihenfolge' => 0,
            'aktiv' => 1,
            'angelegt_am' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->statusSetzen($angebotId, $von);

        try {
            $this->wechsleNach($angebotId, $nach, $verkaeufer, $pruefende);
            self::fail(sprintf('Der Wechsel von "%s" nach "%s" haette scheitern muessen.', $von, $nach));
        } catch (AngebotFehler $fehler) {
            self::assertSame('statuswechsel_unzulaessig', $fehler->schluessel());
            self::assertSame($von, $this->statusVon($angebotId), 'Der Status darf sich nicht geaendert haben.');
        }
    }

    // --- Katalog und Listen ----------------------------------------------

    public function testFuerKatalogLiefertNurAktive(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $kategorie = $this->kategorie();

        $aktiv = $this->aktivesAngebot($verkaeufer, $kategorie, 'Aktives Angebot');
        $this->entwurf($verkaeufer, $kategorie, 'Entwurf');
        $pausiert = $this->aktivesAngebot($verkaeufer, $kategorie, 'Pausiertes Angebot');
        $this->angebote->pausieren($pausiert, $verkaeufer);
        $entfernt = $this->aktivesAngebot($verkaeufer, $kategorie, 'Entferntes Angebot');
        $this->angebote->entfernen($entfernt, $verkaeufer);

        $katalog = $this->angebote->fuerKatalog($kategorie);

        self::assertCount(1, $katalog);
        self::assertSame($aktiv, (int) $katalog[0]['id']);
        self::assertSame('Lina', $katalog[0]['verkaeufer_pseudonym']);
        self::assertSame(1, $this->angebote->anzahlImKatalog($kategorie));
    }

    public function testFuerKatalogBlaettert(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $kategorie = $this->kategorie();

        for ($i = 1; $i <= 3; $i++) {
            $this->aktivesAngebot($verkaeufer, $kategorie, 'Angebot ' . $i);
        }

        self::assertCount(2, $this->angebote->fuerKatalog($kategorie, 1, 2));
        self::assertCount(1, $this->angebote->fuerKatalog($kategorie, 2, 2));
        self::assertSame([], $this->angebote->fuerKatalog($kategorie, 3, 2));
        self::assertSame(3, $this->angebote->anzahlImKatalog($kategorie));
    }

    public function testKatalogZeigtNurDieEigeneKategorie(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $slips = $this->kategorie('waesche_slips');
        $socken = $this->kategorie('socken_sport');

        $this->aktivesAngebot($verkaeufer, $slips, 'Slip');
        $this->aktivesAngebot($verkaeufer, $socken, 'Socken');

        self::assertCount(1, $this->angebote->fuerKatalog($slips));
        self::assertCount(1, $this->angebote->fuerKatalog($socken));
    }

    /**
     * Die Luecke, die der Modellwechsel schliessen muss.
     *
     * Bis hierher filterte der Katalog nur a.status = 'aktiv' und b.status =
     * 'aktiv'. Nach einem Faehigkeitsentzug stand das Angebot also weiter in
     * der Kategorieliste, waehrend /angebot/{id} bereits 404 lieferte — der
     * Katalog war LAXER als die Einzelseite, genau umgekehrt zu der Zusage im
     * Klassenkommentar von app/Http/MarktRouten.php:49-51.
     *
     * Mit dem Torabbau ist der Entzug das EINZIGE verbliebene Verkaufsverbot.
     * Bliebe die Luecke, waere die Sanktion der Verwaltung wirkungslos: Die
     * Ware bliebe dort sichtbar, wo Menschen sie tatsaechlich suchen.
     */
    public function testKatalogVerschweigtAngeboteNachFaehigkeitsentzug(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $kategorie = $this->kategorie();
        $angebotId = $this->veroeffentlicht($verkaeufer, $kategorie);

        self::assertCount(1, $this->angebote->fuerKatalog($kategorie));
        self::assertSame(1, $this->angebote->anzahlImKatalog($kategorie));

        $this->konten->faehigkeitEntziehen($verkaeufer, Konten::FAEHIGKEIT_VERKAUFEN);

        self::assertSame([], $this->angebote->fuerKatalog($kategorie));
        self::assertSame(
            0,
            $this->angebote->anzahlImKatalog($kategorie),
            'Sonst zeigt die Blaetterleiste eine Seite an, die leer ist.'
        );
        self::assertSame([], $this->angebote->vonVerkaeufer($verkaeufer), 'Das Profil ist genauso oeffentlich.');

        // Der Status des Angebots bleibt unberuehrt: Die Sanktion trifft das
        // Konto, nicht die einzelne Zeile. Wird die Faehigkeit
        // wiederhergestellt, steht das Angebot ohne weiteres Zutun wieder da.
        self::assertSame(Angebote::STATUS_AKTIV, $this->statusVon($angebotId));

        $this->konten->faehigkeitFreischalten($verkaeufer, Konten::FAEHIGKEIT_VERKAUFEN, 'beschwerde');
        self::assertCount(1, $this->angebote->fuerKatalog($kategorie));
    }

    /** Die Liste des spaeteren Creator-Profils: nur aktive Ware, nur die eigene. */
    public function testVonVerkaeuferLiefertNurEigeneAktive(): void
    {
        $lina = $this->verkaeufer('Lina');
        $mara = $this->verkaeufer('Mara');
        $kategorie = $this->kategorie();

        $sichtbar = $this->veroeffentlicht($lina, $kategorie, 'Sichtbar');
        $this->entwurf($lina, $kategorie, 'Entwurf');
        $pausiert = $this->veroeffentlicht($lina, $kategorie, 'Pausiert');
        $this->angebote->pausieren($pausiert, $lina);
        $this->veroeffentlicht($mara, $kategorie, 'Fremde Ware');

        $liste = $this->angebote->vonVerkaeufer($lina);

        self::assertCount(1, $liste);
        self::assertSame($sichtbar, (int) $liste[0]['id']);
        self::assertSame([], $this->angebote->vonVerkaeufer($lina, 2), 'Die zweite Seite ist leer.');
    }

    public function testLadenLiefertNullFuerUnbekanntesAngebot(): void
    {
        self::assertNull($this->angebote->laden(999999));
    }

    public function testMeineLiefertJedenStatus(): void
    {
        $verkaeufer = $this->verkaeufer('Lina');
        $fremde = $this->verkaeufer('Mara');
        $kategorie = $this->kategorie();

        $this->entwurf($verkaeufer, $kategorie, 'Entwurf');
        $this->aktivesAngebot($verkaeufer, $kategorie, 'Aktiv');
        $entfernt = $this->aktivesAngebot($verkaeufer, $kategorie, 'Entfernt');
        $this->angebote->entfernen($entfernt, $verkaeufer);
        $this->entwurf($fremde, $kategorie, 'Fremder Entwurf');

        $meine = $this->angebote->meine($verkaeufer);

        self::assertCount(3, $meine);
        $status = array_map(static fn (array $z): string => (string) $z['status'], $meine);
        sort($status);
        self::assertSame(
            [Angebote::STATUS_AKTIV, Angebote::STATUS_ENTFERNT, Angebote::STATUS_ENTWURF],
            $status
        );
    }

    // --- Hilfen ----------------------------------------------------------

    /** Ein Konto mit freigeschalteter Verkaufsfaehigkeit. */
    private function verkaeufer(string $pseudonym): int
    {
        $id = $this->benutzer($pseudonym);
        $this->konten->faehigkeitFreischalten($id, Konten::FAEHIGKEIT_VERKAUFEN, 'identitaetsnachweis');

        return $id;
    }

    private function kategorie(string $schluessel = 'waesche_slips'): int
    {
        return (int) $this->db->wert(
            'SELECT id FROM kategorien WHERE schluessel = :s',
            ['s' => $schluessel]
        );
    }

    private function entwurf(int $verkaeuferId, ?int $kategorieId = null, string $titel = 'Getragene Socken'): int
    {
        return $this->angebote->anlegen(
            $verkaeuferId,
            $kategorieId ?? $this->kategorie(),
            $titel,
            'Beschreibung fuer den Test',
            2500
        );
    }

    /**
     * Der neue Normalweg: anlegen und sofort sichtbar machen.
     *
     * Bewusst ohne Option — genau darin unterscheidet er sich von
     * aktivesAngebot(), das den alten Weg ueber die Pruefung geht und dafuer
     * eine tragende Spezifikation braucht. Beide Wege muessen weiter
     * funktionieren, deshalb bleiben beide Hilfen stehen.
     */
    private function veroeffentlicht(int $verkaeuferId, ?int $kategorieId = null, string $titel = 'Getragene Socken'): int
    {
        $angebotId = $this->entwurf($verkaeuferId, $kategorieId, $titel);
        $this->angebote->veroeffentlichen($angebotId, $verkaeuferId);

        return $angebotId;
    }

    /** Fuehrt ein Angebot den vollstaendigen Weg bis 'aktiv'. */
    private function aktivesAngebot(int $verkaeuferId, ?int $kategorieId = null, string $titel = 'Getragene Socken'): int
    {
        $angebotId = $this->entwurf($verkaeuferId, $kategorieId, $titel);
        $this->angebote->optionSetzen($angebotId, $verkaeuferId, 'tragedauer', 'Tragedauer', Angebote::ART_ZAHL);
        $this->angebote->zurPruefungEinreichen($angebotId, $verkaeuferId);
        $this->angebote->freigeben($angebotId, $this->pruefstelle());

        return $angebotId;
    }

    /** Eine pruefende Person, die nie selbst verkauft — Vier-Augen-Prinzip. */
    private function pruefstelle(): int
    {
        $id = $this->db->wert('SELECT id FROM benutzer WHERE pseudonym = :p', ['p' => 'Pruefstelle']);

        return $id === null ? $this->benutzer('Pruefstelle') : (int) $id;
    }

    private function statusVon(int $angebotId): string
    {
        return (string) $this->db->wert('SELECT status FROM angebote WHERE id = :id', ['id' => $angebotId]);
    }

    /** Setzt den Ausgangsstatus an der Fachklasse vorbei. */
    private function statusSetzen(int $angebotId, string $status): void
    {
        $this->db->ausfuehren(
            'UPDATE angebote SET status = :s WHERE id = :id',
            ['s' => $status, 'id' => $angebotId]
        );
    }

    /**
     * Ruft die Methode auf, die genau diesen Zielstatus ansteuert.
     *
     * Nach 'aktiv' fuehren seit dem Modellwechsel vier Methoden. Hier steht
     * stellvertretend veroeffentlichen(), weil es der neue Normalweg ist; dass
     * auch die drei anderen ihren Ausgangsstatus einzeln pruefen, sichert
     * testJederWegNachAktivPruefstSeinenAusgangsstatus() ab.
     */
    private function wechsleNach(int $angebotId, string $nach, int $verkaeuferId, int $pruefendeId): void
    {
        match ($nach) {
            Angebote::STATUS_ENTWURF => $this->angebote->ablehnen($angebotId, $pruefendeId, 'Begruendung'),
            Angebote::STATUS_IN_PRUEFUNG => $this->angebote->zurPruefungEinreichen($angebotId, $verkaeuferId),
            Angebote::STATUS_AKTIV => $this->angebote->veroeffentlichen($angebotId, $verkaeuferId),
            Angebote::STATUS_PAUSIERT => $this->angebote->pausieren($angebotId, $verkaeuferId),
            Angebote::STATUS_GESPERRT => $this->angebote->sperren($angebotId, $pruefendeId, 'Gemeldet und geprueft.'),
            Angebote::STATUS_ENTFERNT => $this->angebote->entfernen($angebotId, $verkaeuferId),
        };
    }
}
