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
 *     Konten::FAEHIGKEIT_VERKAUFEN — freigeschaltet erst nach der
 *     Identitaetspruefung. Ein frisches Konto hat sie nicht.
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
 * STATUSWERTE: entwurf | in_pruefung | aktiv | pausiert | entfernt.
 * 'in_pruefung' ist gegenueber dem Kommentar in database/migrations/
 * 004_katalog.php NEU hinzugekommen. Das ist bewusst und ohne
 * Schemaaenderung moeglich, weil die Spalte ein Schluesselwort-Feld
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
    public const STATUS_ENTFERNT = 'entfernt';

    /** Alle Statuswerte in fachlicher Reihenfolge — fuer Filter und Anzeige. */
    public const STATUSWERTE = [
        self::STATUS_ENTWURF,
        self::STATUS_IN_PRUEFUNG,
        self::STATUS_AKTIV,
        self::STATUS_PAUSIERT,
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
     * 'entfernt' hat bewusst keine Folgen: Ein zurueckgezogenes Angebot kommt
     * nicht zurueck, sondern wird neu angelegt. Sonst koennte ein Angebot nach
     * dem Entfernen mit anderem Inhalt unter derselben Kennung wieder
     * auftauchen — und Bestellpositionen zeigen ueber angebot_id genau dorthin.
     */
    private const UEBERGAENGE = [
        self::STATUS_ENTWURF => [self::STATUS_IN_PRUEFUNG, self::STATUS_ENTFERNT],
        self::STATUS_IN_PRUEFUNG => [self::STATUS_AKTIV, self::STATUS_ENTWURF, self::STATUS_ENTFERNT],
        self::STATUS_AKTIV => [self::STATUS_PAUSIERT, self::STATUS_ENTFERNT],
        self::STATUS_PAUSIERT => [self::STATUS_AKTIV, self::STATUS_ENTFERNT],
        self::STATUS_ENTFERNT => [],
    ];

    /** Status, in denen die Verkaeuferin Stammdaten und Optionen aendern darf. */
    private const VERAENDERBAR = [self::STATUS_ENTWURF, self::STATUS_PAUSIERT];

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
     * Entwurf und nicht sofort sichtbar: Ein Angebot muss erst durch die
     * Pruefung, siehe zurPruefungEinreichen().
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
     * Reicht das Angebot zur Pruefung ein: entwurf -> in_pruefung.
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
     */
    public function fortsetzen(int $angebotId, int $verkaeuferId): void
    {
        $this->statusWechseln($this->eigenesAngebot($angebotId, $verkaeuferId), self::STATUS_AKTIV);
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
              ORDER BY a.angelegt_am DESC, a.id DESC
              LIMIT ' . $proSeite . ' OFFSET ' . $versatz,
            ['k' => $kategorieId, 's' => self::STATUS_AKTIV, 'bs' => 'aktiv']
        );

        return array_values($zeilen);
    }

    /** Zahl der aktiven Angebote einer Kategorie — fuer die Blaetterleiste. */
    public function anzahlImKatalog(int $kategorieId): int
    {
        return (int) $this->db->wert(
            'SELECT COUNT(*)
               FROM angebote a
               JOIN benutzer b ON b.id = a.verkaeufer_id
              WHERE a.kategorie_id = :k AND a.status = :s AND b.status = :bs',
            ['k' => $kategorieId, 's' => self::STATUS_AKTIV, 'bs' => 'aktiv']
        );
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
