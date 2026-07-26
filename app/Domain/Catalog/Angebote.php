<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Catalog;

use MeinSlip\Core\Database;
use MeinSlip\Domain\Account\Konten;

/**
 * Angebote des Warenmarktplatzes.
 *
 * Vier Dinge setzt diese Klasse durch:
 *
 *  1. VERKAUFEN IST EINE FAEHIGKEIT, KEIN KONTOTYP. Wer anlegt, braucht
 *     Konten::FAEHIGKEIT_VERKAUFEN. Sie wird seit dem Modellwechsel bereits
 *     bei der Registrierung vergeben (Konten::registrieren(), grundlage
 *     'registrierung') und ist damit KEIN Tor mehr, durch das die Verwaltung
 *     erst jemanden hindurchlassen muesste — sondern der Sanktionsgriff:
 *     Verwaltung::faehigkeitEntziehen() nimmt sie wieder weg, protokolliert
 *     und stellt nach Art. 17 DSA zu. Das ist das mildere Mittel neben der
 *     Kontosperre. Genau deshalb muss der Entzug ueberall wirken, wo Ware
 *     oeffentlich sichtbar wird — siehe fuerKatalog().
 *
 *  2. KEIN ANGEBOT OHNE SPEZIFIKATIONSOPTION, DIE EINEN WERT DER KAEUFERIN
 *     AUFNIMMT. Ein angekreuztes Kaestchen genuegt ausdruecklich NICHT. Siehe
 *     self::SPEZIFIKATIONSARTEN und zurPruefungEinreichen() — Grundlage des
 *     Widerrufsausschlusses.
 *
 *  3. JEDER STATUSWECHSEL GEHT DURCH EINE EINZIGE PRUEFUNG. Es gibt keinen
 *     zweiten Pfad, der 'status' schreibt; ein unerlaubter Wechsel wirft
 *     AngebotFehler statt still durchzugehen.
 *
 *  4. VERAENDERT WIRD NUR IM ENTWURF ODER IN DER PAUSE. Ein aktives Angebot
 *     laesst sich weder umschreiben noch in seinen Optionen aendern —
 *     sonst wechselte die Ware unter den Augen der Kaeuferin ihre
 *     Beschaffenheit, und die Spezifikation, auf die sich der
 *     Widerrufsausschluss stuetzt, waere nicht mehr die gezeigte.
 *
 * STATUSWERTE: entwurf | in_pruefung | aktiv | pausiert | gesperrt | entfernt.
 * 'in_pruefung' und 'gesperrt' sind gegenueber dem Kommentar in
 * database/migrations/004_katalog.php NEU hinzugekommen. Das ist bewusst und
 * ohne Schemaaenderung moeglich, weil die Spalte ein Schluesselwort-Feld
 * (VARCHAR) ohne Datenbank-ENUM ist — genau dafuer hat Ddl::schluesselwort()
 * auf ENUM verzichtet. Der Kommentar in der Migration ist damit unvollstaendig,
 * die Liste hier ist massgeblich.
 */
final class Angebote
{
    public const STATUS_ENTWURF = 'entwurf';
    public const STATUS_IN_PRUEFUNG = 'in_pruefung';
    public const STATUS_AKTIV = 'aktiv';
    public const STATUS_PAUSIERT = 'pausiert';

    /**
     * DAS IST DIE NACHMODERATION, NICHT 'in_pruefung 2.0'.
     *
     * Der Unterschied ist der Zeitpunkt und damit die ganze Rechtsfigur:
     * 'in_pruefung' war ein Tor VOR der Sichtbarkeit — nichts kam durch, bevor
     * ein Mensch es angesehen hatte. 'gesperrt' liegt DAHINTER: Das Angebot war
     * oeffentlich, jemand hat es nach Art. 16 DSA gemeldet, die Verwaltung hat
     * entschieden und es aus dem Verkehr gezogen. Wer diesen Status als
     * Vorstufe wieder in den Anlageweg einbaut, stellt das abgebaute Tor
     * wieder auf.
     *
     * Deshalb fuehrt hier auch kein Weg der Verkaeuferin hinein oder heraus:
     * Hinein kommt ein Angebot nur ueber sperren(), heraus nur ueber
     * entsperren() — beide mit Vier-Augen-Prinzip und Begruendung.
     */
    public const STATUS_GESPERRT = 'gesperrt';

    public const STATUS_ENTFERNT = 'entfernt';

    /** Alle Statuswerte in fachlicher Reihenfolge — fuer Filter und Anzeige. */
    public const STATUSWERTE = [
        self::STATUS_ENTWURF,
        self::STATUS_IN_PRUEFUNG,
        self::STATUS_AKTIV,
        self::STATUS_PAUSIERT,
        self::STATUS_GESPERRT,
        self::STATUS_ENTFERNT,
    ];

    public const ART_AUSWAHL = 'auswahl';
    public const ART_ZAHL = 'zahl';
    public const ART_FREITEXT = 'freitext';

    /** Arten einer Konfigurator-Option, wie in 004_katalog.php vorgesehen. */
    public const OPTIONSARTEN = [self::ART_AUSWAHL, self::ART_ZAHL, self::ART_FREITEXT];

    /**
     * Arten, die als Spezifikation im Sinne von § 312g Abs. 2 Nr. 1 BGB taugen.
     *
     * ART_AUSWAHL fehlt hier absichtlich und das ist der Kern der Regel:
     * resources/views/markt/bestellen.php rendert diese Art als Ankreuzfeld mit
     * dem FESTEN Wert "ja". Die Kaeuferin kann es nur setzen oder weglassen —
     * einen eigenen Wert traegt es nie, und in bestellung_spezifikationen.wert
     * steht danach bei jeder Kaeuferin dasselbe. An der Ware individualisiert
     * sich dadurch nichts.
     *
     * § 312g Abs. 2 Nr. 1 BGB nimmt aber nur Ware aus, die "nach
     * Kundenspezifikation angefertigt" wird; der EuGH (C-529/19 "Moebel Kraft")
     * verlangt dafuer eine Anfertigung nach der Spezifikation des Verbrauchers
     * und nicht eine Zusatzwahl aus einem Standardkatalog. Ein
     * Ja/Nein-Kaestchen wie "Geschenkverpackung" ist genau so eine Zusatzwahl.
     * Traegt der Ausschluss nicht, kommt getragene Waesche binnen 14 Tagen
     * zurueck — und die Hygiene-Ausnahme nach Nr. 3 faengt das nicht auf
     * (EuGH C-681/17 "slewo", siehe zurPruefungEinreichen()).
     *
     * ART_ZAHL und ART_FREITEXT nehmen dagegen einen von der Kaeuferin
     * geschriebenen Wert auf ("drei Tage", "38"). Sie sind damit die Grenze,
     * und sie steht hier als Konstante, damit der Bestellpfad in
     * app/Http/MarktRouten.php dieselbe Liste liest statt sie nachzubauen.
     */
    public const SPEZIFIKATIONSARTEN = [self::ART_ZAHL, self::ART_FREITEXT];

    /**
     * Die vollstaendige Zustandsmaschine. Was hier nicht steht, ist verboten.
     *
     * NEU: 'entwurf -> aktiv'. Das ist der Modellwechsel in einer Zeile. Bis
     * hierher fuehrte der einzige Weg nach 'aktiv' ueber 'in_pruefung', also
     * ueber einen Menschen in der Verwaltung. Kuenftig gilt Nachmoderation:
     * veroeffentlichen() geht direkt, und eingegriffen wird erst auf eine
     * Meldung hin (Art. 16 DSA).
     *
     * 'in_pruefung' bleibt vollstaendig erhalten, obwohl niemand mehr dorthin
     * gelangen SOLL: Produktiv liegen Zeilen in diesem Status, und ein Status
     * ohne Ausgang waere eine Sackgasse fuer echte Angebote.
     *
     * 'gesperrt -> aktiv' ist erlaubt und muss es sein: Art. 20 DSA verlangt
     * ein internes Beschwerdeverfahren, das eine Sperre wieder aufheben kann.
     * Eine Sperre, die nur die Loeschung als Ausgang haette, waere keine
     * ueberpruefbare Entscheidung, sondern ein Urteil.
     *
     * Achtung, die Tabelle sagt WELCHER Wechsel zulaessig ist, nicht WER ihn
     * ausloesen darf. Weil nach 'aktiv' jetzt vier Wege fuehren, tragen
     * veroeffentlichen(), fortsetzen(), freigeben() und entsperren() je eine
     * eigene Vorbedingung auf den Ausgangsstatus — sonst waere jede dieser
     * Methoden ein Weg, die Sperre der Verwaltung aufzuheben.
     *
     * 'entfernt' hat bewusst keine Folgen: Ein zurueckgezogenes Angebot kommt
     * nicht zurueck, sondern wird neu angelegt. Sonst koennte ein Angebot nach
     * dem Entfernen mit anderem Inhalt unter derselben Kennung wieder
     * auftauchen — und Bestellpositionen zeigen ueber angebot_id genau dorthin.
     */
    private const UEBERGAENGE = [
        self::STATUS_ENTWURF => [
            self::STATUS_AKTIV,
            self::STATUS_IN_PRUEFUNG,
            self::STATUS_GESPERRT,
            self::STATUS_ENTFERNT,
        ],
        self::STATUS_IN_PRUEFUNG => [
            self::STATUS_AKTIV,
            self::STATUS_ENTWURF,
            self::STATUS_GESPERRT,
            self::STATUS_ENTFERNT,
        ],
        self::STATUS_AKTIV => [self::STATUS_PAUSIERT, self::STATUS_GESPERRT, self::STATUS_ENTFERNT],
        self::STATUS_PAUSIERT => [self::STATUS_AKTIV, self::STATUS_GESPERRT, self::STATUS_ENTFERNT],
        self::STATUS_GESPERRT => [self::STATUS_AKTIV, self::STATUS_ENTFERNT],
        self::STATUS_ENTFERNT => [],
    ];

    /** Status, in denen die Verkaeuferin Stammdaten und Optionen aendern darf. */
    private const VERAENDERBAR = [self::STATUS_ENTWURF, self::STATUS_PAUSIERT];

    /**
     * Semi-Join: Das Konto hinter a.verkaeufer_id darf derzeit verkaufen.
     *
     * Gehoert in JEDE oeffentliche Liste. Warum das erst jetzt traegt und
     * vorher nicht: Bis zum Modellwechsel gab es ZWEI Verkaufsverbote — die
     * fehlende Faehigkeit und die fehlende Freigabe des einzelnen Angebots.
     * Der Katalog prueft nur die zweite (a.status = 'aktiv'), und solange die
     * Verwaltung jedes Angebot einzeln freigab, fiel das kaum auf. Mit dem
     * Torabbau faellt die Freigabe weg, und der Faehigkeitsentzug ist das
     * EINZIGE verbliebene Verkaufsverbot. Er muss deshalb ab jetzt allein
     * tragen, was vorher zwei Bedingungen zusammen trugen.
     *
     * Die Luecke war schon vorher eine: Nach einem Entzug stand das Angebot
     * weiter in der Kategorieliste, /angebot/{id} lieferte aber 404
     * (MarktRouten::angebotSeite() prueft die Faehigkeit seit jeher). Der
     * Katalog war also LAXER als die Einzelseite — genau umgekehrt zu der
     * Zusicherung im Klassenkommentar von app/Http/MarktRouten.php:49-51.
     *
     * EXISTS und nicht IN (SELECT ...): Beides liefe auf SQLite, aber MySQL 8
     * optimiert ein IN mit Unterabfrage je nach Fassung als abhaengige
     * Unterabfrage je Zeile. EXISTS ist die Form, die beide Systeme als
     * Semi-Join verstehen — und sie bricht bei der ersten Treffzeile ab.
     *
     * Der Platzhalter :faehigkeit wird gebunden; der Alias 'a' muss in der
     * umgebenden Abfrage fuer 'angebote' stehen.
     */
    private const VERKAUFSFAEHIG = 'EXISTS (
                    SELECT 1 FROM benutzer_faehigkeiten f
                     WHERE f.benutzer_id = a.verkaeufer_id
                       AND f.faehigkeit = :faehigkeit
                       AND f.entzogen_am IS NULL
                  )';

    /** Felder, die bearbeiten() entgegennimmt. Alles andere wird abgewiesen. */
    private const BEARBEITBARE_FELDER = [
        'titel',
        'beschreibung',
        'kategorie_id',
        'grundpreis_cent',
        'versand_moeglich',
        'uebergabe_moeglich',
        'uebergabe_region',
        'bearbeitungstage',
    ];

    /**
     * Angebote je Profilseite.
     *
     * Eigene Zahl statt eines Vorgabewerts am Parameter: vonVerkaeufer() hat
     * laut Bauplan genau zwei Parameter, damit die Aufrufstellen im
     * Creator-Profil die Seitengroesse nicht je Seite anders raten.
     */
    private const PROFIL_PRO_SEITE = 24;

    private const TITEL_MAXLAENGE = 190;
    private const REGION_MAXLAENGE = 40;
    private const OPTIONSSCHLUESSEL_MUSTER = '/^[a-z0-9_]{2,80}$/';

    private readonly Konten $konten;

    public function __construct(private readonly Database $db)
    {
        // Die Faehigkeitspruefung gehoert untrennbar zum Anlegen. Sie hier
        // selbst zu bauen haelt jede Aufrufstelle davon frei, sich zwei
        // Abhaengigkeiten merken zu muessen — und macht es unmoeglich, die
        // Pruefung durch Weglassen des Arguments zu umgehen.
        $this->konten = new Konten($db);
    }

    /**
     * Legt ein Angebot im Status 'entwurf' an.
     *
     * Entwurf und nicht sofort 'aktiv' — und das ist nach dem Torabbau KEINE
     * Pruefung mehr, sondern reine Reihenfolge: An ein Angebot, das es noch
     * nicht gibt, laesst sich kein Bild haengen und keine Option setzen. Der
     * Entwurf ist der Zustand, in dem das alles entsteht; sichtbar wird er mit
     * veroeffentlichen(), ohne Zutun der Verwaltung.
     *
     * Die Faehigkeitspruefung unten bleibt wortgleich stehen. Sie ist kein Tor
     * mehr, weil Konten::registrieren() die Faehigkeit sofort vergibt — sie ist
     * der Griff, mit dem die Verwaltung sie einer auffaelligen Person wieder
     * wegnimmt.
     *
     * @throws AngebotFehler
     */
    public function anlegen(
        int $verkaeuferId,
        int $kategorieId,
        string $titel,
        string $beschreibung,
        int $grundpreisCent,
        bool $versandMoeglich = true,
        bool $uebergabeMoeglich = false,
        ?string $uebergabeRegion = null,
        int $bearbeitungstage = 3,
        string $waehrung = 'EUR'
    ): int {
        if (!$this->konten->hatFaehigkeit($verkaeuferId, Konten::FAEHIGKEIT_VERKAUFEN)) {
            throw new AngebotFehler(
                'keine_verkaufsfaehigkeit',
                'Konto ' . $verkaeuferId . ' hat die Faehigkeit "' . Konten::FAEHIGKEIT_VERKAUFEN . '" nicht.'
            );
        }

        $titel = $this->gepruefterTitel($titel);
        $grundpreisCent = $this->gepruefterGrundpreis($grundpreisCent);
        $kategorieId = $this->gepruefteKategorie($kategorieId);
        $bearbeitungstage = $this->gepruefteBearbeitungstage($bearbeitungstage);
        $uebergabeRegion = $this->gepruefteRegion($uebergabeRegion);
        $waehrung = $this->gepruefteWaehrung($waehrung);

        return $this->db->einfuegen('angebote', [
            'verkaeufer_id' => $verkaeuferId,
            'kategorie_id' => $kategorieId,
            'titel' => $titel,
            'beschreibung' => $beschreibung,
            'grundpreis_cent' => $grundpreisCent,
            'waehrung' => $waehrung,
            'status' => self::STATUS_ENTWURF,
            'versand_moeglich' => $versandMoeglich ? 1 : 0,
            'uebergabe_moeglich' => $uebergabeMoeglich ? 1 : 0,
            'uebergabe_region' => $uebergabeRegion,
            'bearbeitungstage' => $bearbeitungstage,
            'angelegt_am' => $this->jetzt(),
            'geaendert_am' => null,
        ]);
    }

    /**
     * Aendert Stammdaten — nur im Entwurf oder in der Pause, nur durch die
     * Eigentuemerin.
     *
     * @param array<string,mixed> $felder Erlaubt sind ausschliesslich die
     *        Schluessel aus self::BEARBEITBARE_FELDER.
     *
     * @throws AngebotFehler
     */
    public function bearbeiten(int $angebotId, int $verkaeuferId, array $felder): void
    {
        $angebot = $this->eigenesAngebot($angebotId, $verkaeuferId);
        $this->pruefeVeraenderbar($angebot);

        $unbekannt = array_diff(array_keys($felder), self::BEARBEITBARE_FELDER);
        if ($unbekannt !== []) {
            throw new AngebotFehler(
                'feld_unbekannt',
                'Nicht bearbeitbare Felder: ' . implode(', ', array_map('strval', $unbekannt))
            );
        }

        if ($felder === []) {
            return;
        }

        $werte = [];
        foreach ($felder as $feld => $wert) {
            $werte[$feld] = match ($feld) {
                'titel' => $this->gepruefterTitel((string) $wert),
                'beschreibung' => $wert === null ? null : (string) $wert,
                'kategorie_id' => $this->gepruefteKategorie((int) $wert),
                'grundpreis_cent' => $this->gepruefterGrundpreis((int) $wert),
                'versand_moeglich', 'uebergabe_moeglich' => $wert ? 1 : 0,
                'uebergabe_region' => $this->gepruefteRegion($wert === null ? null : (string) $wert),
                'bearbeitungstage' => $this->gepruefteBearbeitungstage((int) $wert),
            };
        }

        // Die Spaltennamen stammen aus der Weissliste oben, nie aus der
        // Eingabe — nur deshalb duerfen sie in den SQL-Text eingesetzt werden.
        $zuweisungen = [];
        $parameter = [];
        foreach ($werte as $feld => $wert) {
            $zuweisungen[] = $feld . ' = :' . $feld;
            $parameter[$feld] = $wert;
        }

        $zuweisungen[] = 'geaendert_am = :geaendert_am';
        $parameter['geaendert_am'] = $this->jetzt();
        $parameter['id'] = $angebotId;

        $this->db->ausfuehren(
            'UPDATE angebote SET ' . implode(', ', $zuweisungen) . ' WHERE id = :id',
            $parameter
        );
    }

    /**
     * Legt eine Option an oder ueberschreibt die gleichnamige.
     *
     * Der Schluessel ist der stabile Bezeichner (wie bei den Kategorien): Er
     * wandert in bestellung_spezifikationen.schluessel und muss dort auch dann
     * noch lesbar sein, wenn die Option laengst geloescht ist.
     *
     * "Die gleichnamige" ist keine blosse Absichtserklaerung mehr, sondern eine
     * Zusage der Datenbank: UNIQUE(angebot_id, schluessel) aus Migration 009
     * traegt sie. Diese Methode arbeitet mit dem Index, nicht gegen ihn — sie
     * ueberschreibt, wenn es den Schluessel gibt, und faengt den Verstoss ab,
     * wenn eine gleichzeitige Anfrage schneller war.
     *
     * @return int Kennung der Option
     *
     * @throws AngebotFehler
     */
    public function optionSetzen(
        int $angebotId,
        int $verkaeuferId,
        string $schluessel,
        string $bezeichnung,
        string $art = self::ART_AUSWAHL,
        int $aufpreisCent = 0,
        bool $istSpezifikation = true,
        bool $pflicht = false,
        int $reihenfolge = 0,
        ?string $erlaeuterung = null
    ): int {
        $angebot = $this->eigenesAngebot($angebotId, $verkaeuferId);
        $this->pruefeVeraenderbar($angebot);

        $schluessel = strtolower(trim($schluessel));
        if (!preg_match(self::OPTIONSSCHLUESSEL_MUSTER, $schluessel)) {
            throw new AngebotFehler('option_schluessel_ungueltig');
        }

        $bezeichnung = trim($bezeichnung);
        if ($bezeichnung === '' || mb_strlen($bezeichnung) > self::TITEL_MAXLAENGE) {
            throw new AngebotFehler('option_bezeichnung_ungueltig');
        }

        if (!in_array($art, self::OPTIONSARTEN, true)) {
            throw new AngebotFehler('option_art_unbekannt', 'Unbekannte Optionsart "' . $art . '".');
        }

        // Ein negativer Aufpreis waere ein Rabatt, der den Grundpreis
        // unterlaufen koennte — bis auf null oder darunter. Preisminderung
        // gehoert in den Grundpreis, nicht in den Konfigurator.
        if ($aufpreisCent < 0) {
            throw new AngebotFehler('option_aufpreis_ungueltig');
        }

        $daten = [
            'bezeichnung' => $bezeichnung,
            'erlaeuterung' => $erlaeuterung,
            'aufpreis_cent' => $aufpreisCent,
            'art' => $art,
            'ist_spezifikation' => $istSpezifikation ? 1 : 0,
            'pflicht' => $pflicht ? 1 : 0,
            'reihenfolge' => $reihenfolge,
            'aktiv' => 1,
        ];

        $vorhanden = $this->vorhandeneOption($angebotId, $schluessel);

        if ($vorhanden !== null) {
            return $this->optionUeberschreiben($angebotId, $vorhanden, $daten);
        }

        try {
            $optionId = $this->db->einfuegen('angebot_optionen', $daten + [
                'angebot_id' => $angebotId,
                'schluessel' => $schluessel,
                'angelegt_am' => $this->jetzt(),
            ]);
        } catch (\PDOException $fehler) {
            // SQLSTATE 23000 ist die Verletzung einer Eindeutigkeitsbedingung —
            // MySQL wie SQLite melden sie so. Auf angebot_optionen kann das nur
            // UNIQUE(angebot_id, schluessel) aus Migration 009 sein, und das
            // heisst genau eines: Zwischen der Abfrage oben und diesem INSERT
            // war eine zweite Anfrage mit demselben Schluessel schneller
            // (Doppelklick auf 'Option speichern' bei traeger Verbindung).
            //
            // Der Index ist das tragende Teil, nicht dieser Block: Eine
            // Transaktion um Abfrage und INSERT wuerde nichts helfen, weil
            // beide Anfragen unter READ COMMITTED wie unter REPEATABLE READ in
            // der Abfrage nichts sehen. Hier steht nur die Umgangsform —
            // nachlesen und ueberschreiben. Fachlich ist das genau richtig:
            // Der Aufruf wollte diesen Schluessel setzen, und das Ergebnis ist
            // eine einzige Zeile mit den zuletzt gesendeten Werten. Ohne den
            // Block bekaeme die Verkaeuferin statt 'option_gespeichert' eine
            // Fehlerseite, denn MarktRouten faengt nur AngebotFehler.
            if ((string) $fehler->getCode() !== '23000') {
                throw $fehler;
            }

            $inzwischen = $this->vorhandeneOption($angebotId, $schluessel);

            // Kein Treffer heisst: Der Verstoss kam von woanders her. Dann ist
            // Verschlucken falsch — die Ausnahme muss sichtbar bleiben.
            if ($inzwischen === null) {
                throw $fehler;
            }

            return $this->optionUeberschreiben($angebotId, $inzwischen, $daten);
        }

        $this->geaendertAm($angebotId);

        return $optionId;
    }

    /**
     * Entfernt eine Option.
     *
     * Bewusst ein echtes Loeschen: bestellung_spezifikationen fuehrt den
     * Fremdschluessel mit ON DELETE SET NULL und traegt Schluessel,
     * Bezeichnung, Wert und Aufpreis als eigene Kopie. Bereits verkaufte
     * Spezifikationen bleiben dadurch vollstaendig lesbar, auch wenn die
     * Option im Angebot nicht mehr angeboten wird.
     *
     * @throws AngebotFehler
     */
    public function optionEntfernen(int $angebotId, int $verkaeuferId, string $schluessel): void
    {
        $angebot = $this->eigenesAngebot($angebotId, $verkaeuferId);
        $this->pruefeVeraenderbar($angebot);

        $betroffen = $this->db->ausfuehren(
            'DELETE FROM angebot_optionen WHERE angebot_id = :a AND schluessel = :s',
            ['a' => $angebotId, 's' => strtolower(trim($schluessel))]
        )->rowCount();

        if ($betroffen === 0) {
            throw new AngebotFehler('option_unbekannt', 'Option "' . $schluessel . '" existiert nicht.');
        }

        $this->geaendertAm($angebotId);
    }

    /**
     * Stellt ein Angebot oeffentlich: entwurf -> aktiv.
     *
     * DER HAUPTWEG NACH DEM TORABBAU. Geprueft werden genau zwei Dinge:
     * Eigentum und der Ausgangsstatus. Sonst nichts.
     *
     * KEINE SPEZIFIKATIONSPRUEFUNG — das ist Absicht und keine Vergesslichkeit.
     * Die Spezifikationspflicht aus § 312g Abs. 2 Nr. 1 BGB haengt an der
     * BESTELLBARKEIT, nicht an der Sichtbarkeit. Sie steht bereits zweimal im
     * Bestellpfad (Bestellungen::anlegen(), MarktRouten) und ist hier ueber
     * istBestellbar() abfragbar. Sie ins Veroeffentlichen zu ziehen hiesse: wer
     * eine Ware zeigen will, muss sie erst verkaufsfertig konfigurieren — und
     * das waere ein neues Tor an der Stelle, an der wir gerade eines abgebaut
     * haben.
     *
     * Die Vorbedingung 'von === entwurf' ist dagegen tragend. UEBERGAENGE
     * erlaubt nach dem Modellwechsel vier Wege nach 'aktiv'; ohne diese Zeile
     * waere veroeffentlichen() auch der Weg von 'gesperrt' nach 'aktiv'. Die
     * Verkaeuferin koennte damit eine Sperre der Verwaltung selbst aufheben,
     * und die gesamte Nachmoderation waere ein Knopfdruck wert. Dasselbe
     * Argument steht in freigeben(), fortsetzen() und entsperren().
     *
     * @throws AngebotFehler
     */
    public function veroeffentlichen(int $angebotId, int $verkaeuferId): void
    {
        $angebot = $this->eigenesAngebot($angebotId, $verkaeuferId);
        $von = (string) $angebot['status'];

        if ($von !== self::STATUS_ENTWURF) {
            throw AngebotFehler::unerlaubterWechsel($von, self::STATUS_AKTIV);
        }

        $this->statusWechseln($angebot, self::STATUS_AKTIV);
    }

    /**
     * Reicht das Angebot zur Pruefung ein: entwurf -> in_pruefung.
     *
     * NICHT MEHR DER NORMALWEG — der heisst veroeffentlichen(). Diese Methode
     * und der Status 'in_pruefung' bleiben unveraendert bestehen, weil
     * produktiv Zeilen darin liegen und weil eine Verkaeuferin, die eine
     * Vorabdurchsicht ausdruecklich will, sie behalten soll. Ihre
     * Spezifikationspruefung bleibt wortgleich: Sie steht hier seit jeher und
     * ist die Grenze des Widerrufsausschlusses, nicht ein Freigabetor.
     *
     * HIER SITZT DIE GRENZE DES WIDERRUFSAUSSCHLUSSES. Zwei Bedingungen, und
     * die zweite ist die schaerfere:
     *
     *  a) Mindestens eine aktive Option mit ist_spezifikation = 1.
     *  b) Mindestens eine davon von einer Art aus self::SPEZIFIKATIONSARTEN,
     *     also eine, die einen von der Kaeuferin geschriebenen Wert aufnimmt.
     *
     * Grund: Nur eine echte, vom Kaeufer gesetzte Spezifikation macht die Ware
     * "nach Kundenspezifikation angefertigt" im Sinne von § 312g Abs. 2 Nr. 1
     * BGB — und genau darauf stuetzt sich der Widerrufsausschluss. Bis hierher
     * genuegte das blosse Kennzeichen ist_spezifikation, gleich welcher Art:
     * ein angekreuztes "Geschenkverpackung" trug den Ausschluss formal mit,
     * obwohl sich an der Ware nichts individualisiert hat. Das haelt der
     * Auslegung des EuGH (C-529/19 "Moebel Kraft") nicht stand. Die
     * Hygiene-Ausnahme nach Nr. 3 traegt bei getragener Waesche nicht
     * verlaesslich (EuGH C-681/17 "slewo") und faengt den Ausfall nicht auf.
     *
     * Die Pruefung steht bewusst HIER und nicht in optionSetzen(): Ob ein
     * Angebot den Ausschluss traegt, entscheidet sich am ganzen Angebot, nicht
     * an der einzelnen Option. Eine Verkaeuferin darf ein Kaestchen als
     * Zusatzwahl anbieten und es sogar als Spezifikation meinen — sie darf das
     * Angebot nur nicht auf dieser Grundlage einreichen. Ein Wurf schon beim
     * Speichern der Option wuerde ausserdem den Entwurf sperren, in dem sie die
     * Option gerade erst zurechtruecken will.
     *
     * Die Abweisung ist ausserdem eine Freundlichkeit: Bestellungen::anlegen()
     * weist Positionen ohne Spezifikation ohnehin ab. Ein Angebot ohne
     * Spezifikationsoption waere also unverkaeuflich — es erst im Katalog
     * scheitern zu lassen, wuerde die Verkaeuferin ratlos zuruecklassen.
     *
     * @throws AngebotFehler
     */
    public function zurPruefungEinreichen(int $angebotId, int $verkaeuferId): void
    {
        $angebot = $this->eigenesAngebot($angebotId, $verkaeuferId);

        if (!$this->hatSpezifikationsoption($angebotId)) {
            throw new AngebotFehler(
                'keine_spezifikation',
                'Angebot ' . $angebotId . ' hat keine Option mit ist_spezifikation = 1.'
            );
        }

        // Eigener Schluessel statt 'keine_spezifikation': Die Verkaeuferin HAT
        // hier eine als Spezifikation gekennzeichnete Option. Ihr zu sagen, es
        // fehle eine, schickt sie in die falsche Richtung — sie muss die ART
        // aendern, nicht eine weitere Option anlegen.
        if (!$this->hatTragendeSpezifikationsoption($angebotId)) {
            throw new AngebotFehler(
                'spezifikation_braucht_eingabe',
                'Angebot ' . $angebotId . ' hat als Spezifikation nur Optionen der Art "'
                . self::ART_AUSWAHL . '". Ein Ankreuzfeld traegt keinen Wert der Kaeuferin.'
            );
        }

        $this->statusWechseln($angebot, self::STATUS_IN_PRUEFUNG);
    }

    /**
     * Gibt ein geprueftes Angebot frei: in_pruefung -> aktiv.
     *
     * Ruft der Verwaltungsbereich auf.
     *
     * BLEIBT NACH DEM TORABBAU WOERTLICH BESTEHEN, aus zwei Gruenden. Erstens
     * liegen produktiv Zeilen in 'in_pruefung'; ohne diese Methode haetten sie
     * keinen Ausgang mehr ausser der Loeschung. Zweitens kann sie kein neues
     * Tor werden: Sie erzwingt unten von === in_pruefung, und in diesen Status
     * gelangt nur noch, wer zurPruefungEinreichen() von sich aus aufruft.
     * Niemand wird mehr dorthin geleitet, also wartet auch niemand mehr auf
     * eine Freigabe.
     *
     * @throws AngebotFehler
     */
    public function freigeben(int $angebotId, int $pruefendeId): void
    {
        $angebot = $this->angebotZeile($angebotId);
        $this->pruefeVierAugen($angebot, $pruefendeId);

        // Der Ausgangsstatus muss hier eigens geprueft werden, weil UEBERGAENGE
        // ihn nicht ausdruecken kann: pausiert -> aktiv ist erlaubt, denn
        // fortsetzen() braucht genau diesen Weg. Die Tabelle sagt, WELCHER
        // Wechsel zulaessig ist, nicht WER ihn ausloesen darf.
        //
        // Ohne die Pruefung wirkt ein Klick auf /verwaltung/angebote/{id}/
        // freigeben als Entpausierung: Das Angebot war nie in Pruefung, geht
        // aber sofort live und ist bestellbar. Der Verkaeuferin nimmt das
        // mitten in der Bearbeitung den einzigen Zustand, in dem sie
        // Stammdaten und Optionen aendern darf (self::VERAENDERBAR) — das
        // halbfertige Angebot steht dann im Katalog. Ein veraltetes
        // Listenfenster der Verwaltung reicht dafuer aus.
        //
        // Bewusst AngebotFehler::unerlaubterWechsel() und kein neuer Schluessel:
        // Der Fall IST ein unerlaubter Wechsel, 'statuswechsel_unzulaessig' ist
        // in der Oberflaeche bereits uebersetzt, und die Zusicherung der Tests,
        // dass jeder verbotene Uebergang denselben Schluessel meldet, bleibt.
        $von = (string) $angebot['status'];

        if ($von !== self::STATUS_IN_PRUEFUNG) {
            throw AngebotFehler::unerlaubterWechsel($von, self::STATUS_AKTIV);
        }

        $this->statusWechseln($angebot, self::STATUS_AKTIV);
    }

    /**
     * Weist ein Angebot zurueck: in_pruefung -> entwurf.
     *
     * Zurueck in den Entwurf und nicht auf 'entfernt': Die Verkaeuferin soll
     * nachbessern koennen, ohne alles neu zu erfassen.
     *
     * BLEIBT EBENFALLS WOERTLICH BESTEHEN. Sie ist der zweite Ausgang aus
     * 'in_pruefung' und braucht keine eigene Vorbedingung: 'entwurf' ist nur
     * von 'in_pruefung' aus erreichbar, die Uebergangstabelle allein genuegt
     * hier also. Die Sperre auf Meldung hin heisst nicht ablehnen(), sondern
     * sperren() — eine Ablehnung schickt zurueck an die Verkaeuferin, eine
     * Sperre nimmt vom Markt.
     *
     * @throws AngebotFehler
     */
    public function ablehnen(int $angebotId, int $pruefendeId, string $grund): void
    {
        $angebot = $this->angebotZeile($angebotId);
        $this->pruefeVierAugen($angebot, $pruefendeId);

        // Eine Ablehnung ohne Begruendung ist fuer die Verkaeuferin wertlos —
        // sie kann dann nur raten, was zu aendern ist.
        if (trim($grund) === '') {
            throw new AngebotFehler('ablehnungsgrund_fehlt');
        }

        $this->statusWechseln($angebot, self::STATUS_ENTWURF);
    }

    /**
     * Nimmt ein Angebot auf Meldung hin vom Markt: * -> gesperrt.
     *
     * DAS IST DIE ABHILFEMASSNAHME NACH ART. 16 ABS. 6 DSA. Sie ist der Preis
     * dafuer, dass die Vorabpruefung entfaellt: Ohne einen Griff, der ein
     * gemeldetes Angebot binnen kurzer Zeit unsichtbar macht, waere der
     * Verzicht auf das Tor nicht verteidigbar.
     *
     * Aus jedem Status ausser 'entfernt' — auch aus 'entwurf' und
     * 'in_pruefung'. Das ist bewusst: Ein gemeldetes Angebot kann zwischen
     * Meldung und Entscheidung pausiert oder in den Entwurf zurueckgegangen
     * sein, und dann muss die Entscheidung trotzdem greifen. Sonst waere
     * "kurz pausieren" die Umgehung der Sperre — das Angebot laege danach in
     * einem Status, aus dem die Verkaeuferin es jederzeit wieder aktiviert.
     *
     * Vier-Augen-Prinzip und Begruendungszwang wie bei ablehnen(): Der Grund
     * ist nach Art. 17 DSA Teil der Begruendungspflicht gegenueber der
     * betroffenen Person und die Grundlage ihrer Beschwerde nach Art. 20 DSA.
     * Gespeichert wird er hier so wenig wie bei ablehnen() — die Zustellung
     * und ihre Protokollierung liegen im Verwaltungsbereich, der die Meldung
     * fuehrt. Diese Klasse erzwingt nur, dass es ihn ueberhaupt gibt.
     *
     * @throws AngebotFehler
     */
    public function sperren(int $angebotId, int $verwalterId, string $grund): void
    {
        $angebot = $this->angebotZeile($angebotId);
        $this->pruefeVierAugen($angebot, $verwalterId);

        if (trim($grund) === '') {
            throw new AngebotFehler('sperrgrund_fehlt');
        }

        $this->statusWechseln($angebot, self::STATUS_GESPERRT);
    }

    /**
     * Hebt eine Sperre auf: gesperrt -> aktiv.
     *
     * DER RUECKWEG IST ART. 20 DSA. Ein internes Beschwerdeverfahren, das die
     * Entscheidung nicht aendern kann, ist keines. Deshalb steht diese Methode
     * hier und nicht in einer spaeteren Ausbaustufe.
     *
     * Die Vorbedingung 'von === gesperrt' ist tragend, nicht kosmetisch: Nach
     * 'aktiv' fuehren seit dem Modellwechsel vier Wege. Ohne sie waere
     * entsperren() auch der Weg von 'entwurf' nach 'aktiv' und wuerde einen
     * halbfertigen fremden Entwurf veroeffentlichen — mitten in der
     * Bearbeitung, in der die Verkaeuferin ihn gerade zurechtruecken will. Ein
     * veraltetes Listenfenster der Verwaltung reicht dafuer aus. Dasselbe
     * Argument steht ausfuehrlich in freigeben().
     *
     * Ein entsperrtes Angebot geht nach 'aktiv' und nicht dorthin zurueck, wo
     * es herkam: Die Herkunft steht nirgends, und eine erfolgreiche Beschwerde
     * bedeutet, dass die Sperre unrichtig war — also gehoert das Angebot auf
     * den Markt. Wer es doch nicht zeigen will, pausiert es.
     *
     * @throws AngebotFehler
     */
    public function entsperren(int $angebotId, int $verwalterId, string $grund): void
    {
        $angebot = $this->angebotZeile($angebotId);
        $this->pruefeVierAugen($angebot, $verwalterId);

        if (trim($grund) === '') {
            throw new AngebotFehler('entsperrgrund_fehlt');
        }

        $von = (string) $angebot['status'];

        if ($von !== self::STATUS_GESPERRT) {
            throw AngebotFehler::unerlaubterWechsel($von, self::STATUS_AKTIV);
        }

        $this->statusWechseln($angebot, self::STATUS_AKTIV);
    }

    /** Nimmt ein aktives Angebot vom Markt: aktiv -> pausiert. */
    public function pausieren(int $angebotId, int $verkaeuferId): void
    {
        $this->statusWechseln($this->eigenesAngebot($angebotId, $verkaeuferId), self::STATUS_PAUSIERT);
    }

    /**
     * Stellt ein pausiertes Angebot zurueck: pausiert -> aktiv.
     *
     * Ohne erneute Pruefung, weil eine Pause nichts am Inhalt aendert. Wer in
     * der Pause bearbeitet, aendert nur Stammdaten und Optionen — die Pruefung
     * hat den Verkaeufer freigegeben, nicht jede einzelne Formulierung.
     *
     * Die Vorbedingung 'von === pausiert' ist mit dem Status 'gesperrt'
     * notwendig geworden. Vorher trug die Uebergangstabelle sie allein: Nach
     * 'aktiv' fuehrte nur der Weg aus der Pause. Seit 'gesperrt -> aktiv'
     * erlaubt ist (Art. 20 DSA), waere fortsetzen() ohne diese Zeile der Knopf,
     * mit dem die Verkaeuferin ihre eigene Sperre aufhebt — und die
     * Nachmoderation, auf der der ganze Torabbau ruht, waere wertlos.
     * Aufgehoben wird eine Sperre nur ueber entsperren(), mit Vier-Augen-
     * Prinzip und Begruendung.
     *
     * @throws AngebotFehler
     */
    public function fortsetzen(int $angebotId, int $verkaeuferId): void
    {
        $angebot = $this->eigenesAngebot($angebotId, $verkaeuferId);
        $von = (string) $angebot['status'];

        if ($von !== self::STATUS_PAUSIERT) {
            throw AngebotFehler::unerlaubterWechsel($von, self::STATUS_AKTIV);
        }

        $this->statusWechseln($angebot, self::STATUS_AKTIV);
    }

    /**
     * Zieht ein Angebot endgueltig zurueck.
     *
     * Kein DELETE: Bestellpositionen verweisen ueber angebot_id hierher, und
     * ein Kaufbeleg muss auch Jahre spaeter noch zeigen, was gekauft wurde.
     */
    public function entfernen(int $angebotId, int $verkaeuferId): void
    {
        $this->statusWechseln($this->eigenesAngebot($angebotId, $verkaeuferId), self::STATUS_ENTFERNT);
    }

    /**
     * Aktive Angebote einer Kategorie, seitenweise, mit Verkaeufer-Pseudonym.
     *
     * Drei Bedingungen, und die dritte ist neu: Das Angebot ist aktiv, das
     * Konto ist aktiv, UND das Konto darf verkaufen (self::VERKAUFSFAEHIG,
     * dort steht die ausfuehrliche Begruendung). Damit zeigt der Katalog
     * dasselbe wie die Einzelseite in MarktRouten::angebotSeite() — bis hierher
     * war er laxer, ein Angebot nach Faehigkeitsentzug stand weiter in der
     * Liste und lief auf /angebot/{id} in ein 404.
     *
     * @return list<array<string,mixed>>
     */
    public function fuerKatalog(int $kategorieId, int $seite = 1, int $proSeite = 24): array
    {
        $seite = max(1, $seite);
        $proSeite = max(1, min(100, $proSeite));
        $versatz = ($seite - 1) * $proSeite;

        // LIMIT und OFFSET werden eingesetzt statt gebunden: Database bindet
        // alle Werte als Zeichenkette, und MySQL weist 'LIMIT ?' mit einer
        // Zeichenkette ab, sobald EMULATE_PREPARES aus ist — das ist es hier.
        // Beide Werte sind oben auf ganze Zahlen begrenzt, also unbedenklich.
        $zeilen = $this->db->alle(
            'SELECT a.*, b.pseudonym AS verkaeufer_pseudonym
               FROM angebote a
               JOIN benutzer b ON b.id = a.verkaeufer_id
              WHERE a.kategorie_id = :k AND a.status = :s AND b.status = :bs
                AND ' . self::VERKAUFSFAEHIG . '
              ORDER BY a.angelegt_am DESC, a.id DESC
              LIMIT ' . $proSeite . ' OFFSET ' . $versatz,
            [
                'k' => $kategorieId,
                's' => self::STATUS_AKTIV,
                'bs' => 'aktiv',
                'faehigkeit' => Konten::FAEHIGKEIT_VERKAUFEN,
            ]
        );

        return array_values($zeilen);
    }

    /**
     * Zahl der aktiven Angebote einer Kategorie — fuer die Blaetterleiste.
     *
     * Muss Zeile fuer Zeile dieselbe Bedingung tragen wie fuerKatalog():
     * Weicht die Zahl von der Liste ab, zeigt die Blaetterleiste eine Seite an,
     * die leer ist.
     */
    public function anzahlImKatalog(int $kategorieId): int
    {
        return (int) $this->db->wert(
            'SELECT COUNT(*)
               FROM angebote a
               JOIN benutzer b ON b.id = a.verkaeufer_id
              WHERE a.kategorie_id = :k AND a.status = :s AND b.status = :bs
                AND ' . self::VERKAUFSFAEHIG,
            [
                'k' => $kategorieId,
                's' => self::STATUS_AKTIV,
                'bs' => 'aktiv',
                'faehigkeit' => Konten::FAEHIGKEIT_VERKAUFEN,
            ]
        );
    }

    /**
     * Aktive Angebote einer Verkaeuferin, seitenweise — fuer das Creator-Profil.
     *
     * Bewusst dieselben Bedingungen wie fuerKatalog(): Ein oeffentliches Profil
     * ist eine oeffentliche Liste. Waere sie laxer, entstuende genau die
     * Luecke wieder, die der Semi-Join gerade schliesst — nur eben unter
     * /p/{pseudonym} statt unter /kategorie/{pfad}.
     *
     * Ohne Pseudonymspalte im Ergebnis: Wer diese Liste anzeigt, kennt die
     * Person bereits, deren Profil er gerade rendert.
     *
     * @return list<array<string,mixed>>
     */
    public function vonVerkaeufer(int $verkaeuferId, int $seite = 1): array
    {
        $seite = max(1, $seite);
        $versatz = ($seite - 1) * self::PROFIL_PRO_SEITE;

        // LIMIT/OFFSET eingesetzt statt gebunden, aus demselben Grund wie in
        // fuerKatalog(); beide Werte sind hier ganze Zahlen aus einer
        // Klassenkonstanten und einer nach unten begrenzten Seitenzahl.
        $zeilen = $this->db->alle(
            'SELECT a.*
               FROM angebote a
               JOIN benutzer b ON b.id = a.verkaeufer_id
              WHERE a.verkaeufer_id = :v AND a.status = :s AND b.status = :bs
                AND ' . self::VERKAUFSFAEHIG . '
              ORDER BY a.angelegt_am DESC, a.id DESC
              LIMIT ' . self::PROFIL_PRO_SEITE . ' OFFSET ' . $versatz,
            [
                'v' => $verkaeuferId,
                's' => self::STATUS_AKTIV,
                'bs' => 'aktiv',
                'faehigkeit' => Konten::FAEHIGKEIT_VERKAUFEN,
            ]
        );

        return array_values($zeilen);
    }

    /**
     * Traegt dieses Angebot eine Bestellung? Beide Pruefungen aus § 312g
     * Abs. 2 Nr. 1 BGB, oeffentlich abfragbar.
     *
     * Die Bedingung ist unveraendert dieselbe wie in zurPruefungEinreichen();
     * neu ist nur, dass sie von aussen lesbar ist. Nach dem Torabbau wird sie
     * gebraucht: Ein Angebot darf sichtbar sein, ohne bestellbar zu sein, und
     * die Oberflaeche muss den Unterschied zeigen koennen, statt die Kaeuferin
     * erst in Bestellungen::anlegen() auflaufen zu lassen.
     *
     * ANTWORTET NICHT AUF DIE FRAGE NACH DEM STATUS. Ein entfernter oder
     * gesperrter Entwurf mit tragender Spezifikation ist hier 'true'. Wer
     * wissen will, ob JETZT bestellt werden darf, prueft zusaetzlich
     * status === STATUS_AKTIV — genau so wie MarktRouten es bereits tut.
     */
    public function istBestellbar(int $angebotId): bool
    {
        return $this->hatSpezifikationsoption($angebotId)
            && $this->hatTragendeSpezifikationsoption($angebotId);
    }

    /**
     * Ein Angebot mit seinen Optionen, oder null.
     *
     * @return array<string,mixed>|null Die Angebotszeile, ergaenzt um
     *         'optionen' => list<array<string,mixed>>
     */
    public function laden(int $angebotId): ?array
    {
        $angebot = $this->db->eine('SELECT * FROM angebote WHERE id = :id', ['id' => $angebotId]);

        if ($angebot === null) {
            return null;
        }

        $angebot['optionen'] = array_values($this->db->alle(
            'SELECT * FROM angebot_optionen
              WHERE angebot_id = :a
              ORDER BY reihenfolge ASC, id ASC',
            ['a' => $angebotId]
        ));

        return $angebot;
    }

    /**
     * Alle Angebote einer Verkaeuferin, jeden Status.
     *
     * @return list<array<string,mixed>>
     */
    public function meine(int $verkaeuferId): array
    {
        return array_values($this->db->alle(
            'SELECT * FROM angebote
              WHERE verkaeufer_id = :v
              ORDER BY angelegt_am DESC, id DESC',
            ['v' => $verkaeuferId]
        ));
    }

    // --- intern ----------------------------------------------------------

    /**
     * Der EINZIGE Weg, an dem 'status' geschrieben wird.
     *
     * @param array<string,mixed> $angebot
     *
     * @throws AngebotFehler
     */
    private function statusWechseln(array $angebot, string $nach): void
    {
        $von = (string) $angebot['status'];
        $erlaubte = self::UEBERGAENGE[$von] ?? null;

        if ($erlaubte === null) {
            throw new AngebotFehler('status_unbekannt', 'Unbekannter Status "' . $von . '" in der Datenbank.');
        }

        if (!in_array($nach, $erlaubte, true)) {
            throw AngebotFehler::unerlaubterWechsel($von, $nach);
        }

        $this->db->ausfuehren(
            'UPDATE angebote SET status = :s, geaendert_am = :g WHERE id = :id',
            ['s' => $nach, 'g' => $this->jetzt(), 'id' => (int) $angebot['id']]
        );
    }

    /**
     * @return array<string,mixed>
     *
     * @throws AngebotFehler
     */
    private function angebotZeile(int $angebotId): array
    {
        $angebot = $this->db->eine('SELECT * FROM angebote WHERE id = :id', ['id' => $angebotId]);

        if ($angebot === null) {
            throw new AngebotFehler('angebot_unbekannt', 'Angebot ' . $angebotId . ' existiert nicht.');
        }

        return $angebot;
    }

    /**
     * @return array<string,mixed>
     *
     * @throws AngebotFehler
     */
    private function eigenesAngebot(int $angebotId, int $verkaeuferId): array
    {
        $angebot = $this->angebotZeile($angebotId);

        if ((int) $angebot['verkaeufer_id'] !== $verkaeuferId) {
            throw new AngebotFehler(
                'nicht_der_eigentuemer',
                'Konto ' . $verkaeuferId . ' gehoert Angebot ' . $angebotId . ' nicht.'
            );
        }

        return $angebot;
    }

    /** @param array<string,mixed> $angebot */
    private function pruefeVeraenderbar(array $angebot): void
    {
        if (!in_array((string) $angebot['status'], self::VERAENDERBAR, true)) {
            throw new AngebotFehler(
                'nicht_bearbeitbar',
                'Im Status "' . $angebot['status'] . '" sind keine Aenderungen erlaubt.'
            );
        }
    }

    /**
     * Vier-Augen-Prinzip: Niemand prueft das eigene Angebot.
     *
     * Ohne diese Sperre koennte ein Konto mit Verwaltungszugang beliebige
     * eigene Ware am Katalog vorbei freischalten — die Pruefung waere dann
     * eine Selbstauskunft.
     *
     * @param array<string,mixed> $angebot
     */
    private function pruefeVierAugen(array $angebot, int $pruefendeId): void
    {
        if ((int) $angebot['verkaeufer_id'] === $pruefendeId) {
            throw new AngebotFehler('eigenpruefung_unzulaessig');
        }
    }

    /**
     * Kennung der gleichnamigen Option, oder null.
     *
     * Eigene Methode, weil optionSetzen() zweimal danach fragt: einmal vor dem
     * INSERT und einmal, nachdem der eindeutige Index einen Wettlauf gemeldet
     * hat. Beide Male muss dieselbe Bedingung gelten wie im Index.
     */
    private function vorhandeneOption(int $angebotId, string $schluessel): ?int
    {
        $zeile = $this->db->eine(
            'SELECT id FROM angebot_optionen WHERE angebot_id = :a AND schluessel = :s',
            ['a' => $angebotId, 's' => $schluessel]
        );

        return $zeile === null ? null : (int) $zeile['id'];
    }

    /**
     * Schreibt eine vorhandene Option um und haelt geaendert_am nach.
     *
     * @param array<string,mixed> $daten Die Spalten aus optionSetzen(); der
     *        Schluessel selbst steht nicht darin und bleibt unveraendert.
     *
     * @return int Kennung der Option
     */
    private function optionUeberschreiben(int $angebotId, int $optionId, array $daten): int
    {
        $this->db->ausfuehren(
            'UPDATE angebot_optionen
                SET bezeichnung = :bezeichnung, erlaeuterung = :erlaeuterung,
                    aufpreis_cent = :aufpreis_cent, art = :art,
                    ist_spezifikation = :ist_spezifikation, pflicht = :pflicht,
                    reihenfolge = :reihenfolge, aktiv = :aktiv
              WHERE id = :id',
            $daten + ['id' => $optionId]
        );

        $this->geaendertAm($angebotId);

        return $optionId;
    }

    /** Gibt es ueberhaupt eine als Spezifikation gekennzeichnete Option? */
    private function hatSpezifikationsoption(int $angebotId): bool
    {
        $treffer = $this->db->wert(
            'SELECT COUNT(*) FROM angebot_optionen
              WHERE angebot_id = :a AND ist_spezifikation = 1 AND aktiv = 1',
            ['a' => $angebotId]
        );

        return (int) $treffer > 0;
    }

    /**
     * Gibt es eine Spezifikationsoption, die einen Wert der Kaeuferin aufnimmt?
     *
     * Das ist die Bedingung, an der der Widerrufsausschluss haengt — siehe
     * self::SPEZIFIKATIONSARTEN und zurPruefungEinreichen().
     */
    private function hatTragendeSpezifikationsoption(int $angebotId): bool
    {
        // Die Platzhalter entstehen aus der Klassenkonstanten, nie aus einer
        // Eingabe — nur deshalb darf ihre Zahl in den SQL-Text wachsen. Die
        // Werte selbst werden weiterhin gebunden.
        $platzhalter = [];
        $werte = ['a' => $angebotId];

        foreach (self::SPEZIFIKATIONSARTEN as $nummer => $spezifikationsart) {
            $platzhalter[] = ':art' . $nummer;
            $werte['art' . $nummer] = $spezifikationsart;
        }

        $treffer = $this->db->wert(
            'SELECT COUNT(*) FROM angebot_optionen
              WHERE angebot_id = :a AND ist_spezifikation = 1 AND aktiv = 1
                AND art IN (' . implode(', ', $platzhalter) . ')',
            $werte
        );

        return (int) $treffer > 0;
    }

    private function gepruefterTitel(string $titel): string
    {
        $titel = trim($titel);

        if ($titel === '') {
            throw new AngebotFehler('titel_fehlt');
        }

        // Die Spalte fasst 190 Zeichen; laenger abzuschneiden waere stiller
        // Datenverlust, deshalb lieber abweisen.
        if (mb_strlen($titel) > self::TITEL_MAXLAENGE) {
            throw new AngebotFehler('titel_zu_lang');
        }

        return $titel;
    }

    private function gepruefterGrundpreis(int $grundpreisCent): int
    {
        if ($grundpreisCent <= 0) {
            throw new AngebotFehler('grundpreis_ungueltig');
        }

        return $grundpreisCent;
    }

    private function gepruefteKategorie(int $kategorieId): int
    {
        $treffer = $this->db->wert(
            'SELECT COUNT(*) FROM kategorien WHERE id = :id AND aktiv = 1',
            ['id' => $kategorieId]
        );

        if ((int) $treffer === 0) {
            throw new AngebotFehler('kategorie_unbekannt', 'Kategorie ' . $kategorieId . ' gibt es nicht.');
        }

        return $kategorieId;
    }

    private function gepruefteBearbeitungstage(int $tage): int
    {
        // Null Tage waere eine Zusage, die niemand halten kann; mehr als ein
        // Quartal ist kein Angebot mehr, sondern eine Absichtserklaerung.
        if ($tage < 1 || $tage > 90) {
            throw new AngebotFehler('bearbeitungstage_ungueltig');
        }

        return $tage;
    }

    private function gepruefteRegion(?string $region): ?string
    {
        if ($region === null) {
            return null;
        }

        $region = trim($region);

        if ($region === '') {
            return null;
        }

        // Grobe Region, nie eine Adresse — 40 Zeichen reichen fuer "Raum
        // Muenchen" und sind zu knapp fuer eine Anschrift.
        if (mb_strlen($region) > self::REGION_MAXLAENGE) {
            throw new AngebotFehler('uebergabe_region_zu_lang');
        }

        return $region;
    }

    private function gepruefteWaehrung(string $waehrung): string
    {
        $waehrung = strtoupper(trim($waehrung));

        if (!preg_match('/^[A-Z]{3}$/', $waehrung)) {
            throw new AngebotFehler('waehrung_ungueltig');
        }

        return $waehrung;
    }

    /** Haelt geaendert_am nach, wenn sich Optionen aendern. */
    private function geaendertAm(int $angebotId): void
    {
        $this->db->ausfuehren(
            'UPDATE angebote SET geaendert_am = :g WHERE id = :id',
            ['g' => $this->jetzt(), 'id' => $angebotId]
        );
    }

    private function jetzt(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
