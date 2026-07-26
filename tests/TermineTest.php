<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Core\Lang;
use MeinSlip\Domain\Account\Profile;
use MeinSlip\Domain\Chat\TerminFehler;
use MeinSlip\Domain\Chat\Termine;
use MeinSlip\Domain\Chat\Unterhaltungen;
use MeinSlip\Http\TerminRouten;

/**
 * Haelt die Terminspur zusammen.
 *
 * Die Tests hier sind keine Abdeckung, sondern Zusicherungen: Jeder einzelne
 * steht fuer einen Fehler, der ohne ihn still zurueckkehren koennte — eine
 * Verabredung, die sich selbst bestaetigt; eine Uebergabe, die als bestaetigt
 * gilt, obwohl nur eine Seite quittiert hat; eine Quittung im Voraus; ein
 * Rueckweg im Zustandsautomaten, den es nicht geben darf.
 *
 * Vorbild ist tests/UnterhaltungenTest.php. Ein Unterschied ist noetig und
 * steht bei zurueckdatieren(): Vorschlagen verlangt einen Zeitpunkt in der
 * Zukunft, Quittieren verlangt einen erreichten. Beides in einem Test geht nur,
 * wenn die Zeile dazwischen von Hand zurueckdatiert wird.
 */
final class TermineTest extends Testfall
{
    protected function setUp(): void
    {
        parent::setUp();

        // Fuer die Textpruefungen am Ende dieser Datei. Lang haelt seine Werte
        // statisch; ein zweiter Aufruf ist folgenlos.
        require_once dirname(__DIR__) . '/app/Support/hilfen.php';
        Lang::einrichten(dirname(__DIR__) . '/resources/lang', 'de-DE');
    }

    // --- Die vier Zusicherungen des Pakets ----------------------------------

    /**
     * ANNEHMEN DARF NUR DAS GEGENUEBER.
     *
     * Ohne diese Regel waere die Verabredung eine einseitige Behauptung: Eine
     * Person setzte Zeit und Ort und bestaetigte sie sich selbst, und die
     * andere faende in ihrem Chat einen „angenommenen" Termin, dem sie nie
     * zugestimmt hat.
     */
    public function testNurDasGegenueberKannAnnehmen(): void
    {
        [$lina, $mara, $unterhaltungId] = $this->gespraech();
        $termine = $this->termine();
        $terminId = $termine->vorschlagen($unterhaltungId, $lina, $this->inTagen(1), Termine::TREFFPUNKT_BAHNHOF, 'Raum Muenchen');

        $this->assertTerminFehler(
            'eigener_vorschlag',
            fn () => $termine->annehmen($terminId, $lina),
            'Wer vorschlaegt, nimmt nicht an.'
        );

        $this->assertTerminFehler(
            'eigener_vorschlag',
            fn () => $termine->ablehnen($terminId, $lina),
            'Und ablehnen kann er seinen Vorschlag auch nicht — beides ist die Antwort des Gegenuebers.'
        );

        self::assertSame(
            Termine::STATUS_VORGESCHLAGEN,
            $this->stand($terminId),
            'Der abgewiesene Versuch darf nichts veraendert haben.'
        );

        $termine->annehmen($terminId, $mara);

        self::assertSame(Termine::STATUS_ANGENOMMEN, $this->stand($terminId));
    }

    /**
     * EINE EINSEITIGE QUITTUNG GENUEGT NICHT.
     *
     * Das ist der Kern des Pakets. Waere es anders, koennte eine Seite die
     * Uebergabe im Alleingang als bestaetigt hinstellen — genau die
     * Endgueltigkeit im Uebergabemoment, an der der urspruengliche QR-Entwurf
     * gescheitert ist (docs/04-features/safe-meet.md).
     */
    public function testEineEinseitigeQuittungGenuegtNicht(): void
    {
        [$lina, $mara, $terminId] = $this->angenommenerTermin();
        $termine = $this->termine();

        $stand = $termine->quittieren($terminId, $lina);

        self::assertSame(
            Termine::STATUS_ANGENOMMEN,
            $stand,
            'Nach einer Quittung ist noch nichts uebergeben.'
        );
        self::assertSame(Termine::STATUS_ANGENOMMEN, $this->stand($terminId));

        $this->assertTerminFehler(
            'bereits_quittiert',
            fn () => $termine->quittieren($terminId, $lina),
            'Und zweimal fuer sich selbst quittieren ersetzt die andere Seite nicht.'
        );

        self::assertSame(Termine::STATUS_ANGENOMMEN, $this->stand($terminId));

        $zeile = $this->zeile($terminId);
        self::assertNotNull($zeile['quittiert_a_am'], 'Lina ist teilnehmer_a der Unterhaltung.');
        self::assertNull($zeile['quittiert_b_am'], 'In die Spalte der anderen Seite schreibt niemand.');

        // Der Vollstaendigkeit halber: Mara ist noch nicht ausgesperrt.
        self::assertSame(Termine::STATUS_UEBERGEBEN, $termine->quittieren($terminId, $mara));
    }

    /**
     * ERST NACH BEIDEN QUITTUNGEN WECHSELT DER ZUSTAND — und zwar im selben
     * Transaktionsblock wie die zweite Quittung.
     */
    public function testNachBeidenQuittungenWechseltDerZustand(): void
    {
        [$lina, $mara, $terminId] = $this->angenommenerTermin();
        $termine = $this->termine();

        // Umgekehrte Reihenfolge als im Test darueber: Wer zuerst quittiert,
        // darf keine Rolle spielen.
        self::assertSame(Termine::STATUS_ANGENOMMEN, $termine->quittieren($terminId, $mara));
        self::assertSame(Termine::STATUS_UEBERGEBEN, $termine->quittieren($terminId, $lina));

        $zeile = $this->zeile($terminId);

        self::assertSame(Termine::STATUS_UEBERGEBEN, (string) $zeile['status']);
        self::assertNotNull($zeile['quittiert_a_am']);
        self::assertNotNull($zeile['quittiert_b_am']);

        // 'uebergeben' ist ein Endzustand: Danach geht nichts mehr.
        $this->assertTerminFehler('nicht_angenommen', fn () => $termine->quittieren($terminId, $lina));
        $this->assertTerminFehler('nicht_offen', fn () => $termine->ablehnen($terminId, $mara));
    }

    /**
     * VOR DEM ZEITPUNKT GEHT QUITTIEREN NICHT.
     *
     * Eine Quittung im Voraus ist keine Bestaetigung, sondern eine Vorabgabe —
     * „erst quittieren, dann bekommst du es". Genau dieses Missverhaeltnis soll
     * die Bedingung verhindern.
     */
    public function testVorDemZeitpunktGehtQuittierenNicht(): void
    {
        [$lina, $mara, $unterhaltungId] = $this->gespraech();
        $termine = $this->termine();
        $terminId = $termine->vorschlagen($unterhaltungId, $lina, $this->inTagen(2), Termine::TREFFPUNKT_CAFE, 'Raum Koeln');
        $termine->annehmen($terminId, $mara);

        $this->assertTerminFehler('zu_frueh', fn () => $termine->quittieren($terminId, $lina));
        $this->assertTerminFehler('zu_frueh', fn () => $termine->quittieren($terminId, $mara));

        $zeile = $this->zeile($terminId);
        self::assertNull($zeile['quittiert_a_am']);
        self::assertNull($zeile['quittiert_b_am']);
        self::assertSame(Termine::STATUS_ANGENOMMEN, (string) $zeile['status']);

        // Sobald der Zeitpunkt erreicht ist, geht es.
        $this->zurueckdatieren($terminId, 60);
        self::assertSame(Termine::STATUS_ANGENOMMEN, $termine->quittieren($terminId, $lina));
    }

    /**
     * EIN NEUER VORSCHLAG IST EINE NEUE ZEILE, KEIN RUECKSPRUNG.
     *
     * Waere es anders, liesse sich eine abgelehnte Verabredung wieder auf
     * „vorgeschlagen" drehen — und dann auch eine quittierte Uebergabe
     * zurueck. Der Verlauf, wer wann was zugesagt hat, waere nicht mehr
     * rekonstruierbar.
     */
    public function testEinNeuerVorschlagLegtEineNeueZeileAn(): void
    {
        [$lina, $mara, $unterhaltungId] = $this->gespraech();
        $termine = $this->termine();

        $erster = $termine->vorschlagen($unterhaltungId, $lina, $this->inTagen(1), Termine::TREFFPUNKT_BAHNHOF, 'Raum Muenchen');
        $termine->ablehnen($erster, $mara, 'Passt mir zeitlich nicht.');

        $zweiter = $termine->vorschlagen($unterhaltungId, $lina, $this->inTagen(3), Termine::TREFFPUNKT_CAFE, 'Raum Muenchen');

        self::assertNotSame($erster, $zweiter, 'Ein neuer Vorschlag ist eine neue Zeile.');
        self::assertSame(
            2,
            (int) $this->db->wert('SELECT COUNT(*) FROM termine WHERE unterhaltung_id = :u', ['u' => $unterhaltungId])
        );

        $alt = $this->zeile($erster);
        self::assertSame(Termine::STATUS_ABGELEHNT, (string) $alt['status'], 'Die alte Zeile bleibt abgelehnt.');
        self::assertNotNull($alt['abgelehnt_am']);
        self::assertSame('Passt mir zeitlich nicht.', (string) $alt['grund']);

        self::assertSame(Termine::STATUS_VORGESCHLAGEN, $this->stand($zweiter));

        // Und aus 'abgelehnt' fuehrt kein Weg zurueck.
        $this->assertTerminFehler('nicht_offen', fn () => $termine->annehmen($erster, $mara));
        $this->assertTerminFehler('nicht_angenommen', fn () => $termine->quittieren($erster, $mara));
    }

    // --- Die Regeln daneben --------------------------------------------------

    /**
     * Vergangene und zu ferne Zeitpunkte werden abgewiesen — und Daten, die es
     * gar nicht gibt.
     */
    public function testUnbrauchbareZeitpunkteWerdenAbgewiesen(): void
    {
        [$lina, , $unterhaltungId] = $this->gespraech();
        $termine = $this->termine();

        $this->assertTerminFehler(
            'zeitpunkt_vergangen',
            fn () => $termine->vorschlagen($unterhaltungId, $lina, gmdate('Y-m-d H:i', time() - 3600), Termine::TREFFPUNKT_BAHNHOF, 'Raum Muenchen')
        );

        $this->assertTerminFehler(
            'zeitpunkt_zu_fern',
            fn () => $termine->vorschlagen($unterhaltungId, $lina, $this->inTagen(Termine::VORLAUF_MAX_TAGE + 1), Termine::TREFFPUNKT_BAHNHOF, 'Raum Muenchen')
        );

        $this->assertTerminFehler(
            'zeitpunkt_ungueltig',
            fn () => $termine->vorschlagen($unterhaltungId, $lina, 'irgendwann', Termine::TREFFPUNKT_BAHNHOF, 'Raum Muenchen')
        );

        // Den 31. Februar rechnet strtotime stillschweigend in den 3. Maerz um.
        // Ohne die Rueckrechnung stuende im Chat ein anderer Tag als der
        // eingegebene.
        $this->assertTerminFehler(
            'zeitpunkt_ungueltig',
            fn () => $termine->vorschlagen($unterhaltungId, $lina, gmdate('Y') + 1 . '-02-31 10:00', Termine::TREFFPUNKT_BAHNHOF, 'Raum Muenchen')
        );

        self::assertSame(0, (int) $this->db->wert('SELECT COUNT(*) FROM termine'));
    }

    /**
     * Der Treffpunkt ist eine feste Liste, kein Freitext — und die Region ist
     * Pflicht und kurz.
     *
     * Das ist die strukturelle Sperre gegen ein Adressfeld. Waere hier Freitext
     * moeglich, stuende binnen Wochen eine Anschrift in der Datenbank.
     */
    public function testFreitextAlsTreffpunktWirdAbgewiesen(): void
    {
        [$lina, , $unterhaltungId] = $this->gespraech();
        $termine = $this->termine();

        $this->assertTerminFehler(
            'treffpunkt_unbekannt',
            fn () => $termine->vorschlagen($unterhaltungId, $lina, $this->inTagen(1), 'Musterstrasse 12, 80331 Muenchen', 'Raum Muenchen'),
            'Ein Freitext ist keine Treffpunktart.'
        );

        $this->assertTerminFehler(
            'region_fehlt',
            fn () => $termine->vorschlagen($unterhaltungId, $lina, $this->inTagen(1), Termine::TREFFPUNKT_BAHNHOF, '   ')
        );

        $this->assertTerminFehler(
            'region_zu_lang',
            fn () => $termine->vorschlagen(
                $unterhaltungId,
                $lina,
                $this->inTagen(1),
                Termine::TREFFPUNKT_BAHNHOF,
                str_repeat('a', Termine::REGION_MAXLAENGE + 1)
            ),
            'Die Grenze ist knapp genug, dass eine Anschrift nicht hineinpasst.'
        );

        self::assertSame(0, (int) $this->db->wert('SELECT COUNT(*) FROM termine'));

        // Es gibt in der Tabelle keine Spalte, in die ein Ort geschrieben
        // werden koennte. Bricht dieser Test, ist eine dazugekommen.
        $verboten = ['treffpunkt', 'adresse', 'strasse', 'plz', 'ort', 'breitengrad', 'laengengrad', 'standort'];
        foreach ($verboten as $spalte) {
            self::assertNotContains($spalte, $this->spaltenVonTermine(), 'Kein Standort, nirgends.');
        }
    }

    /**
     * Eine fremde Person sieht nichts und kann nichts.
     */
    public function testFremdeSehenUndKoennenNichts(): void
    {
        [$lina, $mara, $unterhaltungId] = $this->gespraech();
        $fremde = $this->plauderndesKonto('Fremde');
        $termine = $this->termine();
        $terminId = $termine->vorschlagen($unterhaltungId, $lina, $this->inTagen(1), Termine::TREFFPUNKT_BAHNHOF, 'Raum Muenchen');

        $this->assertTerminFehler('nicht_teilnehmer', fn () => $termine->annehmen($terminId, $fremde));
        $this->assertTerminFehler('nicht_teilnehmer', fn () => $termine->ablehnen($terminId, $fremde));
        $this->assertTerminFehler('nicht_teilnehmer', fn () => $termine->quittieren($terminId, $fremde));
        $this->assertTerminFehler('nicht_teilnehmer', fn () => $termine->fuerUnterhaltung($unterhaltungId, $fremde));
        $this->assertTerminFehler(
            'nicht_teilnehmer',
            fn () => $termine->vorschlagen($unterhaltungId, $fremde, $this->inTagen(1), Termine::TREFFPUNKT_BAHNHOF, 'Raum Muenchen')
        );

        // Unbekannt und fremd liefern dasselbe null — kein Existenztest ueber
        // fremde Verabredungen.
        self::assertNull($termine->unterhaltungVon($terminId, $fremde));
        self::assertNull($termine->unterhaltungVon(987654, $lina));
        self::assertSame($unterhaltungId, $termine->unterhaltungVon($terminId, $mara));

        $this->assertTerminFehler('termin_unbekannt', fn () => $termine->annehmen(987654, $lina));
    }

    /**
     * Die Sperre wirkt auch hier, und zwar in beiden Richtungen.
     *
     * Ein Terminvorschlag an eine Person, die einen gesperrt hat, waere genau
     * der Kanal, den die Sperre schliessen soll — und der belastendste.
     */
    public function testDieSperreVerhindertTermine(): void
    {
        [$lina, $mara, $unterhaltungId] = $this->gespraech();
        $termine = $this->termine();
        $terminId = $termine->vorschlagen($unterhaltungId, $lina, $this->inTagen(1), Termine::TREFFPUNKT_BAHNHOF, 'Raum Muenchen');

        $this->sperren($mara, $lina);

        $this->assertTerminFehler(
            'gesperrt',
            fn () => $termine->vorschlagen($unterhaltungId, $lina, $this->inTagen(2), Termine::TREFFPUNKT_CAFE, 'Raum Muenchen')
        );
        $this->assertTerminFehler(
            'gesperrt',
            fn () => $termine->vorschlagen($unterhaltungId, $mara, $this->inTagen(2), Termine::TREFFPUNKT_CAFE, 'Raum Muenchen'),
            'Auch die sperrende Seite verabredet nichts mehr.'
        );
        $this->assertTerminFehler('gesperrt', fn () => $termine->annehmen($terminId, $mara));

        // Ablehnen bleibt moeglich: Es ist der Weg AUS dem Vorgang heraus. Sonst
        // stuende der offene Vorschlag bis zum Verfall im Fenster.
        $termine->ablehnen($terminId, $mara);
        self::assertSame(Termine::STATUS_ABGELEHNT, $this->stand($terminId));
    }

    /**
     * Beide Drosselungen greifen: die Breite und die Tiefe.
     *
     * Ein Terminvorschlag ist eine Aufforderung, sich an einem Ort einzufinden.
     * Ohne Grenze waere er das bequemste Belaestigungswerkzeug der Plattform.
     */
    public function testDieDrosselungGreift(): void
    {
        $spam = $this->plauderndesKonto('Spammer');
        $chat = new Unterhaltungen($this->db);
        $termine = $this->termine();

        // Tiefe: drei offene Vorschlaege in EINEM Gespraech, dann ist Schluss.
        $eines = $chat->eroeffnen($spam, $this->plauderndesKonto('Ziel0'));

        for ($i = 0; $i < Termine::OFFENE_JE_UNTERHALTUNG; $i++) {
            $termine->vorschlagen($eines, $spam, $this->inTagen($i + 1), Termine::TREFFPUNKT_BAHNHOF, 'Raum Muenchen');
        }

        $this->assertTerminFehler(
            'zu_viele_offen',
            fn () => $termine->vorschlagen($eines, $spam, $this->inTagen(9), Termine::TREFFPUNKT_BAHNHOF, 'Raum Muenchen')
        );

        // Breite: ueber alle Gespraeche hinweg zaehlt die Tagesgrenze.
        $offen = Termine::OFFENE_JE_UNTERHALTUNG;

        for ($i = $offen; $i < Termine::VORSCHLAEGE_JE_TAG; $i++) {
            $weiteres = $chat->eroeffnen($spam, $this->plauderndesKonto('Ziel' . $i));
            $termine->vorschlagen($weiteres, $spam, $this->inTagen(1), Termine::TREFFPUNKT_BAHNHOF, 'Raum Muenchen');
        }

        $letztes = $chat->eroeffnen($spam, $this->plauderndesKonto('ZielZuViel'));

        $this->assertTerminFehler(
            'zu_schnell',
            fn () => $termine->vorschlagen($letztes, $spam, $this->inTagen(1), Termine::TREFFPUNKT_BAHNHOF, 'Raum Muenchen')
        );

        // Einen Vorschlag zu BEKOMMEN kostet kein Kontingent — sonst legte ein
        // Angreifer ein beliebtes Konto still, indem er ihm zehn Termine
        // anbietet.
        $ziel = (int) $this->db->wert(
            'SELECT teilnehmer_b_id FROM unterhaltungen WHERE id = :id',
            ['id' => $letztes]
        );
        self::assertGreaterThan(
            0,
            $termine->vorschlagen($letztes, $ziel, $this->inTagen(1), Termine::TREFFPUNKT_BAHNHOF, 'Raum Muenchen')
        );
    }

    /**
     * Der Verfallslauf schliesst, was abgelaufen ist — und nur das.
     */
    public function testVerfallenLassenSchliesstNurAbgelaufenes(): void
    {
        [$lina, $mara, $unterhaltungId] = $this->gespraech();
        $termine = $this->termine();

        $offenUndAlt = $termine->vorschlagen($unterhaltungId, $lina, $this->inTagen(1), Termine::TREFFPUNKT_BAHNHOF, 'Raum Muenchen');
        $offenUndFrisch = $termine->vorschlagen($unterhaltungId, $lina, $this->inTagen(5), Termine::TREFFPUNKT_CAFE, 'Raum Muenchen');

        $angenommenUndFrisch = $termine->vorschlagen($unterhaltungId, $mara, $this->inTagen(2), Termine::TREFFPUNKT_PAKETSHOP, 'Raum Koeln');
        $termine->annehmen($angenommenUndFrisch, $lina);

        $angenommenUndAlt = $termine->vorschlagen($unterhaltungId, $mara, $this->inTagen(3), Termine::TREFFPUNKT_HAUSTUER, 'Raum Koeln');
        $termine->annehmen($angenommenUndAlt, $lina);

        $this->zurueckdatieren($offenUndAlt, 60);
        // Frisch uebergangen: gerade eben erreicht, aber das Quittierfenster
        // laeuft noch.
        $this->zurueckdatieren($angenommenUndFrisch, 60);
        $this->zurueckdatieren($angenommenUndAlt, Termine::QUITTIERFENSTER_STUNDEN * 3600 + 60);

        self::assertSame(2, $termine->verfallenLassen($unterhaltungId));
        self::assertSame(0, $termine->verfallenLassen($unterhaltungId), 'Ein zweiter Lauf ist folgenlos.');

        self::assertSame(Termine::STATUS_VERFALLEN, $this->stand($offenUndAlt));
        self::assertSame(Termine::STATUS_VERFALLEN, $this->stand($angenommenUndAlt));
        self::assertSame(Termine::STATUS_VORGESCHLAGEN, $this->stand($offenUndFrisch));
        self::assertSame(
            Termine::STATUS_ANGENOMMEN,
            $this->stand($angenommenUndFrisch),
            'Wer gerade uebergibt, verliert seine Bestaetigung nicht.'
        );

        // Und der erreichte, aber noch nicht verfallene Termin ist genau der,
        // der jetzt quittierbar ist.
        self::assertSame(Termine::STATUS_ANGENOMMEN, $termine->quittieren($angenommenUndFrisch, $lina));
        $this->assertTerminFehler('nicht_angenommen', fn () => $termine->quittieren($angenommenUndAlt, $lina));
    }

    /**
     * Der Zustandsautomat hat drei Endzustaende und keinen Rueckweg.
     *
     * Die Matrix wird direkt geprueft und nicht nur ueber die Methoden: Sie ist
     * die Stelle, an der ein Rueckweg am leichtesten unbemerkt entstuende.
     */
    public function testDerAutomatKenntKeinenRueckweg(): void
    {
        foreach ([Termine::STATUS_UEBERGEBEN, Termine::STATUS_ABGELEHNT, Termine::STATUS_VERFALLEN] as $ende) {
            self::assertSame([], Termine::UEBERGAENGE[$ende], 'Aus "' . $ende . '" fuehrt nichts heraus.');
        }

        foreach (Termine::UEBERGAENGE as $von => $nach) {
            self::assertNotContains(
                Termine::STATUS_VORGESCHLAGEN,
                $nach,
                'Nach "vorgeschlagen" fuehrt kein Weg zurueck — ein neuer Vorschlag ist eine neue Zeile.'
            );

            foreach ($nach as $ziel) {
                self::assertArrayHasKey($ziel, Termine::UEBERGAENGE, 'Unbekanntes Ziel "' . $ziel . '".');
            }

            self::assertNotContains($von, $nach, 'Kein Zustand fuehrt auf sich selbst.');
        }
    }

    /**
     * Die Liste liefert je Zeile mit, was diese Person darf.
     *
     * Die Oberflaeche rechnet das nicht selbst aus; sonst stuenden die Regeln
     * ein zweites Mal in einer Vorlage.
     */
    public function testDieListeLiefertDieErlaubnisseMit(): void
    {
        [$lina, $mara, $unterhaltungId] = $this->gespraech();
        $termine = $this->termine();
        $terminId = $termine->vorschlagen($unterhaltungId, $lina, $this->inTagen(1), Termine::TREFFPUNKT_BAHNHOF, 'Raum Muenchen');

        $beiLina = $termine->fuerUnterhaltung($unterhaltungId, $lina)[0];
        $beiMara = $termine->fuerUnterhaltung($unterhaltungId, $mara)[0];

        self::assertTrue($beiLina['eigener_vorschlag']);
        self::assertFalse($beiLina['darf_annehmen'], 'Der eigene Vorschlag bekommt keinen Knopf.');
        self::assertFalse($beiLina['darf_ablehnen']);

        self::assertFalse($beiMara['eigener_vorschlag']);
        self::assertTrue($beiMara['darf_annehmen']);
        self::assertTrue($beiMara['darf_ablehnen']);

        self::assertFalse($beiLina['darf_quittieren'], 'Vor der Annahme quittiert niemand.');
        self::assertFalse($beiMara['darf_quittieren']);

        $termine->annehmen($terminId, $mara);
        $this->zurueckdatieren($terminId, 60);

        $beiLina = $termine->fuerUnterhaltung($unterhaltungId, $lina)[0];
        $beiMara = $termine->fuerUnterhaltung($unterhaltungId, $mara)[0];

        self::assertTrue($beiLina['darf_quittieren'], 'Ab dem Zeitpunkt duerfen BEIDE.');
        self::assertTrue($beiMara['darf_quittieren']);
        self::assertTrue($beiLina['zeitpunkt_erreicht']);

        $termine->quittieren($terminId, $lina);

        $beiLina = $termine->fuerUnterhaltung($unterhaltungId, $lina)[0];
        $beiMara = $termine->fuerUnterhaltung($unterhaltungId, $mara)[0];

        self::assertFalse($beiLina['darf_quittieren'], 'Jede nur einmal.');
        self::assertNotNull($beiLina['eigene_quittung']);
        self::assertNull($beiLina['fremde_quittung']);
        self::assertTrue($beiMara['darf_quittieren']);
        self::assertNotNull($beiMara['fremde_quittung'], 'Aus Maras Sicht ist Linas Quittung die fremde.');
    }

    /**
     * Die Liste zeigt aelteste zuerst — und schneidet bei Ueberlaenge die
     * AELTESTEN ab, nie die neuesten.
     *
     * Die naheliegende Fassung 'ORDER BY id ASC LIMIT n' tut genau das
     * Verkehrte: Sie schneidet in einem langen Gespraech den offenen Vorschlag
     * ab, um den es gerade geht. Die Zeilen entstehen hier ohne die Fachklasse,
     * weil die Drosselung sonst lange vorher greift — geprueft wird der
     * Leseweg, nicht der Schreibweg.
     */
    public function testDieListeSchneidetDieAeltestenAbUndNichtDieNeuesten(): void
    {
        [$lina, , $unterhaltungId] = $this->gespraech();
        $ids = [];

        for ($i = 0; $i < Termine::PRO_ABRUF + 2; $i++) {
            $ids[] = $this->db->einfuegen('termine', [
                'unterhaltung_id' => $unterhaltungId,
                'vorschlagender_id' => $lina,
                'angebot_id' => null,
                'bestellung_id' => null,
                'zeitpunkt' => gmdate('Y-m-d H:i:s', time() + ($i + 1) * 3600),
                'treffpunkt_art' => Termine::TREFFPUNKT_BAHNHOF,
                'region' => 'Raum Muenchen',
                'status' => Termine::STATUS_VORGESCHLAGEN,
                'quittiert_a_am' => null,
                'quittiert_b_am' => null,
                'abgelehnt_am' => null,
                'grund' => null,
                'angelegt_am' => gmdate('Y-m-d H:i:s'),
                'geaendert_am' => null,
            ]);
        }

        $liste = $this->termine()->fuerUnterhaltung($unterhaltungId, $lina);

        self::assertCount(Termine::PRO_ABRUF, $liste);
        self::assertSame(end($ids), $liste[Termine::PRO_ABRUF - 1]['id'], 'Der neueste Termin muss dabei sein.');
        self::assertSame($ids[2], $liste[0]['id'], 'Abgeschnitten werden die aeltesten.');

        $kennungen = array_column($liste, 'id');
        $sortiert = $kennungen;
        sort($sortiert);
        self::assertSame($sortiert, $kennungen, 'Aelteste zuerst.');
    }

    /**
     * Ein Termin erbt den Angebotsbezug seiner Unterhaltung — und traegt keine
     * Bestellung.
     *
     * Die geldfreie Parallelspur in einer Zusicherung: bestellung_id bleibt
     * NULL, weil kein Weg sie schreibt.
     */
    public function testDerTerminErbtDenAngebotsbezugUndBleibtGeldfrei(): void
    {
        $lina = $this->plauderndesKonto('Lina');
        $mara = $this->plauderndesKonto('Mara');
        $angebotId = $this->angebot($lina);

        $unterhaltungId = (new Unterhaltungen($this->db))->eroeffnen($lina, $mara, $angebotId);
        $terminId = $this->termine()->vorschlagen($unterhaltungId, $lina, $this->inTagen(1), Termine::TREFFPUNKT_CAFE, 'Raum Muenchen');

        $zeile = $this->zeile($terminId);

        self::assertSame($angebotId, (int) $zeile['angebot_id'], 'Der Bezug kommt aus dem Kontext, nicht aus der Eingabe.');
        self::assertNull($zeile['bestellung_id'], 'Diese Spur ist geldfrei — die Spalte steht nur fuer spaeter da.');
    }

    // --- Texte ---------------------------------------------------------------

    /**
     * Jede Treffpunktart braucht Label und Erklaerung.
     *
     * Die Erklaerung ist nicht schmueckend: Sie ist die Stelle, an der steht,
     * was eine Haustuer-Uebergabe bedeutet. Fehlt sie, waehlt jemand sie
     * blind.
     */
    public function testJedeTreffpunktartHatTexte(): void
    {
        self::assertSame(
            [],
            array_merge(
                $this->ohneText('termin.treffpunkt.', Termine::TREFFPUNKTARTEN),
                $this->ohneText('termin.treffpunkt_erklaerung.', Termine::TREFFPUNKTARTEN)
            ),
            'Ergaenze die fehlenden Texte in resources/lang/de-DE/termin.php.'
        );
    }

    /** Jeder Zustand braucht Bezeichnung und Erklaerung. */
    public function testJederZustandHatTexte(): void
    {
        $zustaende = array_keys(Termine::UEBERGAENGE);

        self::assertSame(
            [],
            array_merge(
                $this->ohneText('termin.status.', $zustaende),
                $this->ohneText('termin.status_erklaerung.', $zustaende)
            ),
            'Ergaenze die fehlenden Texte in resources/lang/de-DE/termin.php.'
        );
    }

    /**
     * Jeder TerminFehler braucht einen Text UND einen Platz in der Weissliste.
     *
     * Die Schluessel kommen aus den Wurfstellen selbst und nicht aus einer
     * abgeschriebenen Liste — die waere beim naechsten neuen Fehler stumm
     * veraltet. Vorbild: tests/ChatTexteTest.php.
     */
    public function testJederTerminFehlerHatTextUndWeisslistenplatz(): void
    {
        $schluessel = $this->terminFehlerSchluessel();

        self::assertSame([], $this->ohneText('termin.fehler.', $schluessel));

        $fehlend = [];

        foreach ($schluessel as $einer) {
            if (!in_array($einer, TerminRouten::FEHLER, true)) {
                $fehlend[] = $einer;
            }
        }

        self::assertSame(
            [],
            $fehlend,
            "Diese Terminfehler wuerden von TerminRouten::ausListe() auf 'unbekannt' gezogen\n"
            . "und nie im Klartext erscheinen. Ergaenze sie in app/Http/TerminRouten.php::FEHLER:\n"
            . implode("\n", $fehlend)
        );
    }

    /**
     * Und umgekehrt: Was die Route durchlaesst, muss lesbar sein — samt des
     * Rueckfalls 'unbekannt', auf den ausListe() jeden fremden Wert zieht.
     */
    public function testBeideWeisslistenSindVollstaendigUebersetzt(): void
    {
        self::assertSame(
            [],
            array_merge(
                $this->ohneText('termin.fehler.', TerminRouten::FEHLER),
                $this->ohneText('termin.erfolg.', TerminRouten::ERFOLGE),
                // ausListe() zieht jeden unbekannten Wert auf 'unbekannt'. Ohne
                // Text stuende dort '[[termin.erfolg.unbekannt]]'.
                $this->ohneText('termin.erfolg.', ['unbekannt']),
                $this->ohneText('termin.fehler.', ['unbekannt'])
            ),
            'Diese Rueckmeldungen der Terminstrecke haben keinen Text.'
        );
    }

    // --- Hilfen --------------------------------------------------------------

    private function termine(): Termine
    {
        return new Termine($this->db);
    }

    /**
     * Zwei Konten und ihr Gespraech.
     *
     * Lina eroeffnet und ist damit teilnehmer_a — darauf beruht die Zuordnung
     * von quittiert_a_am.
     *
     * @return array{0:int, 1:int, 2:int} Lina, Mara, Unterhaltung
     */
    private function gespraech(): array
    {
        $lina = $this->plauderndesKonto('Lina');
        $mara = $this->plauderndesKonto('Mara');

        return [$lina, $mara, (new Unterhaltungen($this->db))->eroeffnen($lina, $mara)];
    }

    /**
     * Ein angenommener Termin, dessen Zeitpunkt bereits erreicht ist.
     *
     * @return array{0:int, 1:int, 2:int} Lina, Mara, Termin
     */
    private function angenommenerTermin(): array
    {
        [$lina, $mara, $unterhaltungId] = $this->gespraech();
        $termine = $this->termine();

        $terminId = $termine->vorschlagen(
            $unterhaltungId,
            $lina,
            $this->inTagen(1),
            Termine::TREFFPUNKT_BAHNHOF,
            'Raum Muenchen'
        );
        $termine->annehmen($terminId, $mara);
        $this->zurueckdatieren($terminId, 60);

        return [$lina, $mara, $terminId];
    }

    /** Ein Zeitpunkt in $tage Tagen, im Format des Formulars. */
    private function inTagen(int $tage): string
    {
        return gmdate('Y-m-d\TH:i', time() + $tage * 86400);
    }

    /**
     * Setzt den Zeitpunkt eines Termins um $sekunden in die Vergangenheit.
     *
     * WARUM DAS VON HAND PASSIERT. vorschlagen() verlangt einen Zeitpunkt in
     * der Zukunft, quittieren() einen erreichten. Beides in einem Test geht nur
     * ueber diesen Griff. Der Alternativentwurf waere eine einspeisbare Uhr in
     * der Fachklasse — die haette aber genau einen Zweck: diesen Test. Eine
     * Abhaengigkeit, die nur der Test braucht, ist eine Stelle mehr, an der
     * produktiv etwas anderes gelten kann als hier.
     */
    private function zurueckdatieren(int $terminId, int $sekunden): void
    {
        $this->db->ausfuehren(
            'UPDATE termine SET zeitpunkt = :z WHERE id = :id',
            ['z' => gmdate('Y-m-d H:i:s', time() - $sekunden), 'id' => $terminId]
        );
    }

    private function stand(int $terminId): string
    {
        return (string) $this->db->wert('SELECT status FROM termine WHERE id = :id', ['id' => $terminId]);
    }

    /** @return array<string,mixed> */
    private function zeile(int $terminId): array
    {
        $zeile = $this->db->eine('SELECT * FROM termine WHERE id = :id', ['id' => $terminId]);

        self::assertNotNull($zeile, 'Termin ' . $terminId . ' fehlt.');

        return $zeile;
    }

    /** @return list<string> */
    private function spaltenVonTermine(): array
    {
        $namen = [];

        foreach ($this->db->alle('PRAGMA table_info(termine)') as $spalte) {
            $namen[] = strtolower((string) $spalte['name']);
        }

        self::assertNotEmpty($namen, 'Die Tabelle "termine" gibt es nicht.');

        return $namen;
    }

    /** Ein Konto, das schreiben darf — also eines mit gesetzter Deklaration. */
    private function plauderndesKonto(string $pseudonym): int
    {
        $id = $this->benutzer($pseudonym);
        (new Profile($this->db))->deklarationSetzen($id, Profile::DEKLARATION_PERSON);

        return $id;
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

    /**
     * Alle Schluessel aus $werte, fuer die es unter $praefix keinen Text gibt.
     *
     * @param list<string> $werte
     *
     * @return list<string>
     */
    private function ohneText(string $praefix, array $werte): array
    {
        $fehlend = [];

        foreach ($werte as $wert) {
            if (str_starts_with(Lang::t($praefix . $wert), '[[')) {
                $fehlend[] = $praefix . $wert;
            }
        }

        return $fehlend;
    }

    /**
     * Die Schluessel, mit denen irgendwo in app/ ein TerminFehler erzeugt wird.
     *
     * @return list<string>
     */
    private function terminFehlerSchluessel(): array
    {
        $gefunden = [];
        $verzeichnis = new \RecursiveDirectoryIterator(
            dirname(__DIR__) . '/app',
            \FilesystemIterator::SKIP_DOTS
        );

        foreach (new \RecursiveIteratorIterator($verzeichnis) as $datei) {
            if (!$datei->isFile() || $datei->getExtension() !== 'php' || !$datei->isReadable()) {
                continue;
            }

            preg_match_all(
                "/new TerminFehler\(\s*'([a-z0-9_]+)'/",
                (string) file_get_contents($datei->getPathname()),
                $treffer
            );

            foreach ($treffer[1] as $schluessel) {
                $gefunden[$schluessel] = $schluessel;
            }
        }

        $liste = array_values($gefunden);
        sort($liste);

        // Greift der regulaere Ausdruck eines Tages ins Leere — etwa weil die
        // Wurfstellen auf Konstanten umgestellt werden —, soll dieser Test
        // scheitern und nicht stillschweigend nichts mehr pruefen.
        self::assertNotEmpty($liste, 'Keine TerminFehler-Wurfstelle gefunden.');

        return $liste;
    }

    private function assertTerminFehler(string $schluessel, callable $tun, string $hinweis = ''): void
    {
        try {
            $tun();
        } catch (TerminFehler $fehler) {
            self::assertSame($schluessel, $fehler->schluessel(), $hinweis);

            return;
        }

        self::fail('Erwartet wurde ein TerminFehler mit dem Schluessel "' . $schluessel . '". ' . $hinweis);
    }
}
