<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Trust;

use MeinSlip\Core\Database;
use MeinSlip\Domain\Admin\Verwaltung;
use MeinSlip\Domain\Chat\Unterhaltungen;

/**
 * Der nutzerseitige Meldeweg nach Art. 16 DSA.
 *
 * DIESE KLASSE IST DIE RECHTSGRUNDLAGE DES TORABBAUS. Ohne Vorabpruefung ist
 * die Plattform nur dann verteidigbar, wenn jede Person jederzeit einen Inhalt
 * melden kann und die Meldung nachweisbar entgegengenommen wird. Die
 * Verwaltungsseite (Arbeitsliste, Entscheidung, Protokoll) gab es schon —
 * erzeugen konnte eine Meldung bisher niemand.
 *
 * Vier Zusicherungen, die hier und nirgends sonst entstehen:
 *
 *  1. JEDE MELDUNG BEKOMMT EINE FRIST. zugesagt_bis wird beim Anlegen gesetzt,
 *     nicht spaeter. Art. 16 Abs. 6 DSA verlangt eine zeitnahe Bearbeitung;
 *     ohne Frist IM DATENSATZ ist ihre Ueberschreitung nicht messbar.
 *     Verwaltung::kennzahlen() zaehlt sie bereits — die Zahl blieb bisher nur
 *     deshalb null, weil es keine Meldungen gab.
 *
 *  2. JEDE MELDUNG EINER ANGEMELDETEN PERSON WIRD BESTAETIGT. Art. 16 Abs. 4
 *     DSA verlangt die Empfangsbestaetigung ausdruecklich. Sie entsteht in
 *     DERSELBEN Transaktion wie die Meldung: Eine Meldung ohne Bestaetigung
 *     waere eine unbelegte Entgegennahme, eine Bestaetigung ohne Meldung eine
 *     Luege.
 *
 *  3. ANONYME MELDUNGEN BLEIBEN MOEGLICH. melder_id ist nullable, und das ist
 *     kein Zufall: Wer eine Belaestigung meldet, soll das tun koennen, ohne
 *     ein Konto anzulegen oder sich zu erkennen zu geben. Art. 16 Abs. 2 DSA
 *     macht die Kontaktdaten nur fuer die Faelle zur Pflicht, in denen es
 *     nicht um Straftaten nach Art. 3 bis 7 der Richtlinie 2011/93/EU geht.
 *     Ohne Konto gibt es dann natuerlich auch keine Zustellung.
 *
 *  4. KEINE DOPPELMELDUNG. Dieselbe Person meldet denselben Gegenstand nicht
 *     zweimal, solange die erste Meldung noch unerledigt ist. Sonst liesse
 *     sich die Arbeitsliste der Verwaltung mit einem Dutzend Klicks fluten und
 *     die Zahl der Meldungen — die Dringlichkeit ausdrueckt — waere wertlos.
 *
 * Die Klasse bewegt kein Geld und aendert keinen Status ausserhalb von
 * 'meldungen'. Insbesondere sperrt sie NICHTS: Ueber die Abhilfe entscheidet
 * ein Mensch ueber Verwaltung und Angebote. Eine Meldung, die von selbst
 * sperrt, waere ein Werkzeug zum Ausschalten von Mitbewerberinnen.
 */
final class Meldungen
{
    /** Zeilen je Seite in meineMeldungen(). */
    public const PRO_SEITE = 25;

    /**
     * Die zugesagte Bearbeitungsfrist in Stunden.
     *
     * 72 Stunden sind eine Selbstverpflichtung, keine Vorgabe des Gesetzes —
     * Art. 16 Abs. 6 DSA sagt nur "zeitnah". Eine zugesagte Zahl ist trotzdem
     * besser als keine: Sie macht die Ueberschreitung messbar und ist die
     * einzige Groesse, an der sich die Nachmoderation ueberhaupt pruefen
     * laesst.
     */
    public const FRIST_STUNDEN = 72;

    /**
     * Die Art der Empfangsbestaetigung in 'benachrichtigungen'.
     *
     * ACHTUNG: Jede Art, die in dieser Tabelle landen kann, braucht einen Text
     * unter 'profil.art.<art>' — die Profilseite baut ihre Ueberschrift als
     * te('profil.art.' . $art). Fehlt er, liest die meldende Person
     * '[[profil.art.meldung_eingegangen]]' als Bestaetigung. MeldungenTest
     * haelt das fest.
     */
    public const ART_EINGEGANGEN = 'meldung_eingegangen';

    /**
     * Die Meldegruende.
     *
     * Sie sind die Grundlage der Uebersetzungsschluessel: Die Oberflaeche baut
     * 'melden.grund.<grund>'. Eine feste Liste statt eines Freitextfelds hat
     * drei Gruende. Erstens laesst sie sich uebersetzen. Zweitens laesst sie
     * sich zaehlen — 'dreimal verbotene_ware' ist eine Aussage, dreimal
     * Freitext ist keine. Und drittens ist sie die Stelle, an der die
     * Verwaltung eine Meldung ohne Lesen einordnen kann: 'minderjaehrig' hat
     * Vorrang vor allem anderen.
     *
     * 'sonstiges' bleibt drin, obwohl es nichts einordnet. Ohne diesen Eintrag
     * waehlt die meldende Person den naechstbesten falschen Grund, und dann
     * ist die Statistik schlechter als vorher.
     */
    public const GRUND_VERBOTENE_WARE = 'verbotene_ware';
    public const GRUND_MINDERJAEHRIG = 'minderjaehrig';
    public const GRUND_GESTOHLENE_IDENTITAET = 'gestohlene_identitaet';
    public const GRUND_BETRUG = 'betrug';
    public const GRUND_BELAESTIGUNG = 'belaestigung';
    public const GRUND_URHEBERRECHT = 'urheberrecht';
    public const GRUND_SONSTIGES = 'sonstiges';

    /** @var list<string> */
    public const GRUENDE = [
        self::GRUND_MINDERJAEHRIG,
        self::GRUND_GESTOHLENE_IDENTITAET,
        self::GRUND_VERBOTENE_WARE,
        self::GRUND_BETRUG,
        self::GRUND_BELAESTIGUNG,
        self::GRUND_URHEBERRECHT,
        self::GRUND_SONSTIGES,
    ];

    /**
     * Meldbare Gegenstaende und die Tabelle, in der sie stehen muessen.
     *
     * Der Tabellenname wird in die Abfrage eingesetzt — deshalb kommt er
     * AUSSCHLIESSLICH aus dieser Konstanten und niemals aus der Eingabe. Die
     * Eingabe waehlt nur den Schluessel, und ein unbekannter Schluessel wird
     * vorher abgewiesen. Damit ist die Einsetzung strukturell sicher statt
     * gefiltert.
     *
     * 'nachricht' stand hier zunaechst nicht, weil die Tabelle 'nachrichten'
     * erst mit dem Chat entstand (P2) und ein Eintrag ohne Tabelle die
     * Existenzpruefung mit einem SQL-Fehler statt mit einer Absage scheitern
     * liesse. Der Chat steht jetzt, die Zeile ist nachgetragen.
     *
     * SIE ZU VERGESSEN WAR TEURER ALS SIE AUSSIEHT: Der Melden-Knopf im
     * Chatfenster leitet auf '/melden?art=nachricht' weiter, und ohne diesen
     * Eintrag lief er in "kein Gegenstand gewaehlt". Damit war der einzige
     * Meldeweg fuer Nachrichten tot — und ein funktionierendes Melde- und
     * Abhilfeverfahren ist die Bedingung, unter der diese Plattform ueberhaupt
     * ohne Vorabpruefung veroeffentlicht (Art. 16 DSA).
     */
    private const GEGENSTAENDE = [
        Verwaltung::GEGENSTAND_ANGEBOT => 'angebote',
        Verwaltung::GEGENSTAND_BENUTZER => 'benutzer',
        Verwaltung::GEGENSTAND_BESTELLUNG => 'bestellungen',
        Unterhaltungen::GEGENSTAND_NACHRICHT => 'nachrichten',
    ];

    /** Spaltenbreite von meldungen.grund laut database/migrations/006_vertrauen.php. */
    private const GRUND_MAXLAENGE = 60;

    /**
     * Obergrenze der Erlaeuterung.
     *
     * Die Spalte ist TEXT und koennte mehr. Die Grenze schuetzt nicht das
     * Schema, sondern die Bearbeiterin: Eine Meldung, die niemand zu Ende
     * liest, wird nicht sorgfaeltiger bearbeitet, sondern schlechter.
     */
    private const BESCHREIBUNG_MAXLAENGE = 2000;

    private readonly Verwaltung $verwaltung;

    public function __construct(private readonly Database $db)
    {
        // Die Zustellung laeuft ueber Verwaltung::benachrichtigen() und nicht
        // ueber ein eigenes INSERT: Grundsatz 2 der Verwaltung sagt zu, dass
        // sich der Zustellweg an EINER Datei ablesen laesst. Ein zweites
        // Schreiben in 'benachrichtigungen' von hier aus wuerde diese
        // Zusicherung aufheben, ohne etwas zu gewinnen.
        $this->verwaltung = new Verwaltung($db);
    }

    /**
     * Nimmt eine Meldung entgegen.
     *
     * Reihenfolge tragend: Erst wird alles geprueft, dann wird geschrieben.
     * Die Transaktion umschliesst nur noch das Schreiben — Meldung und
     * Empfangsbestaetigung entstehen gemeinsam oder gar nicht.
     *
     * @param int|null $melderId null = anonyme Meldung, dann ohne Zustellung
     *
     * @return int Kennung der Meldung
     *
     * @throws MeldungsFehler Jeder Eingabefehler — das ist der Regelfall
     * @throws \MeinSlip\Domain\Admin\VerwaltungsFehler Nur, wenn das meldende
     *         Konto zwischen Pruefung und Zustellung verschwindet. Bewusst
     *         nicht umgehuellt: Der Fall ist kein Eingabefehler, und ein
     *         MeldungsFehler daraus zu machen wuerde ihn als solchen tarnen.
     */
    public function melden(
        ?int $melderId,
        string $gegenstandArt,
        int $gegenstandId,
        string $grund,
        string $beschreibung
    ): int {
        $gegenstandArt = $this->gepruefteGegenstandsart($gegenstandArt);
        $grund = $this->gepruefterGrund($grund);
        $beschreibung = $this->gepruefteBeschreibung($beschreibung);

        $this->pruefeGegenstandVorhanden($gegenstandArt, $gegenstandId);

        if ($melderId !== null) {
            // Der Fremdschluessel auf benutzer(id) wuerde ein unbekanntes Konto
            // ebenfalls abweisen — aber als PDOException, die oben niemand
            // faengt. Und Verwaltung::benachrichtigen() wuerde ohnehin
            // scheitern, nur eben mitten in der Transaktion.
            $this->pruefeMelderVorhanden($melderId);
            $this->pruefeKeineDoppelmeldung($melderId, $gegenstandArt, $gegenstandId);
        }

        // EIN Zeitstempel fuer beide Spalten: Wuerde die Frist aus einem
        // zweiten gmdate() entstehen, laege sie bei einem Sekundenwechsel
        // 71:59:59 hinter dem Eingang. Das ist harmlos, aber unerklaerbar.
        $stempel = time();
        $jetzt = gmdate('Y-m-d H:i:s', $stempel);
        $frist = gmdate('Y-m-d H:i:s', $stempel + self::FRIST_STUNDEN * 3600);

        return (int) $this->db->transaktion(function () use (
            $melderId,
            $gegenstandArt,
            $gegenstandId,
            $grund,
            $beschreibung,
            $jetzt,
            $frist
        ): int {
            $meldungId = $this->db->einfuegen('meldungen', [
                'melder_id' => $melderId,
                'gegenstand_art' => $gegenstandArt,
                'gegenstand_id' => $gegenstandId,
                'grund' => $grund,
                'beschreibung' => $beschreibung,
                'status' => Verwaltung::MELDUNG_OFFEN,
                'zugesagt_bis' => $frist,
                'erledigt_am' => null,
                'entscheidung' => null,
                'angelegt_am' => $jetzt,
            ]);

            if ($melderId !== null) {
                // Die Bestaetigung zeigt auf die MELDUNG, nicht auf den
                // gemeldeten Gegenstand: Sie ist der Beleg der meldenden
                // Person, und worauf sie sich beruft, ist die Meldung. Nach
                // Art. 16 Abs. 5 DSA wird ihr spaeter die Entscheidung zu
                // genau dieser Meldung mitgeteilt.
                //
                // Als Begruendungstext steht ihre eigene Erlaeuterung dort.
                // Das ist kein Notbehelf: Eine Empfangsbestaetigung, die den
                // Wortlaut zurueckgibt, belegt, WAS entgegengenommen wurde —
                // ein Satz wie "Deine Meldung ist eingegangen" belegt nur,
                // DASS etwas ankam. Und die Ueberschrift dazu steht ohnehin
                // uebersetzt unter 'profil.art.meldung_eingegangen'.
                $this->verwaltung->benachrichtigen(
                    $melderId,
                    self::ART_EINGEGANGEN,
                    Verwaltung::GEGENSTAND_MELDUNG,
                    $meldungId,
                    $beschreibung
                );
            }

            return $meldungId;
        });
    }

    /**
     * Die Meldungen einer Person, neueste zuerst.
     *
     * Anonyme Meldungen sind hier strukturell unerreichbar: melder_id ist bei
     * ihnen NULL, und ein Vergleich mit NULL trifft in SQL niemals zu — auch
     * nicht fuer meineMeldungen(0). Es braucht also keine Sonderbehandlung,
     * die man vergessen koennte.
     *
     * 'entscheidung' steht mit in der Auswahl, weil Art. 16 Abs. 5 DSA
     * verlangt, dass die meldende Person die Entscheidung ueber ihre Meldung
     * erfaehrt — samt Rechtsbehelfsbelehrung. Ohne die Spalte waere die Liste
     * ein Postausgang ohne Antwort.
     *
     * @return array{zeilen:list<array<string,mixed>>, anzahl:int, seite:int, seiten:int, pro_seite:int}
     */
    public function meineMeldungen(int $melderId, int $seite = 1): array
    {
        $anzahl = (int) $this->db->wert(
            'SELECT COUNT(*) FROM meldungen WHERE melder_id = :m',
            ['m' => $melderId]
        );

        $seiten = max(1, (int) ceil($anzahl / self::PRO_SEITE));
        $seite = max(1, min($seite, $seiten));
        $versatz = ($seite - 1) * self::PRO_SEITE;

        $zeilen = $this->db->alle(
            'SELECT id, gegenstand_art, gegenstand_id, grund, beschreibung, status,
                    zugesagt_bis, erledigt_am, entscheidung, angelegt_am
               FROM meldungen
              WHERE melder_id = :m
              ORDER BY angelegt_am DESC, id DESC
              LIMIT ' . self::PRO_SEITE . ' OFFSET ' . $versatz,
            ['m' => $melderId]
        );

        foreach ($zeilen as $i => $zeile) {
            $zeilen[$i]['gegenstand_id'] = (int) $zeile['gegenstand_id'];
            $zeilen[$i]['erledigt'] = $zeile['erledigt_am'] !== null;
        }

        return [
            'zeilen' => array_values($zeilen),
            'anzahl' => $anzahl,
            'seite' => $seite,
            'seiten' => $seiten,
            'pro_seite' => self::PRO_SEITE,
        ];
    }

    // --- intern ------------------------------------------------------------

    /** @throws MeldungsFehler */
    private function gepruefteGegenstandsart(string $gegenstandArt): string
    {
        $gegenstandArt = trim($gegenstandArt);

        if (!array_key_exists($gegenstandArt, self::GEGENSTAENDE)) {
            throw new MeldungsFehler(
                'gegenstand_art_unbekannt',
                'Unbekannte Gegenstandsart "' . $gegenstandArt . '".'
            );
        }

        return $gegenstandArt;
    }

    /** @throws MeldungsFehler */
    private function gepruefterGrund(string $grund): string
    {
        $grund = trim($grund);

        if (!in_array($grund, self::GRUENDE, true)) {
            throw new MeldungsFehler(
                'grund_unbekannt',
                'Unbekannter Meldegrund "' . $grund . '".'
            );
        }

        // Die Liste ist kuerzer als die Spalte; die Pruefung steht trotzdem
        // hier, damit ein spaeter ergaenzter Grund nicht erst in der Datenbank
        // auffaellt — MySQL wiese ihn im strengen Modus ab, SQLite wuerde ihn
        // stillschweigend zu lang speichern.
        if (mb_strlen($grund) > self::GRUND_MAXLAENGE) {
            throw new MeldungsFehler(
                'grund_zu_lang',
                'Meldegrund "' . $grund . '" ist laenger als ' . self::GRUND_MAXLAENGE . ' Zeichen.'
            );
        }

        return $grund;
    }

    /**
     * Die Erlaeuterung ist Pflicht.
     *
     * Art. 16 Abs. 2 lit. a DSA verlangt fuer eine wirksame Meldung eine
     * "hinreichend begruendete Erlaeuterung". Erst eine solche Meldung
     * begruendet die tatsaechliche Kenntnis nach Art. 16 Abs. 3 DSA — eine
     * Meldung ohne jede Erlaeuterung ist rechtlich keine und praktisch nicht
     * bearbeitbar, weil niemand weiss, wonach zu sehen ist.
     *
     * Eine MINDESTlaenge gibt es bewusst nicht. "Das Bild zeigt ein Kind." ist
     * die dringendste denkbare Meldung und waere an jeder Zeichenschranke
     * gescheitert.
     *
     * @throws MeldungsFehler
     */
    private function gepruefteBeschreibung(string $beschreibung): string
    {
        $beschreibung = trim($beschreibung);

        if ($beschreibung === '') {
            throw new MeldungsFehler('beschreibung_fehlt');
        }

        if (mb_strlen($beschreibung) > self::BESCHREIBUNG_MAXLAENGE) {
            throw new MeldungsFehler(
                'beschreibung_zu_lang',
                'Erlaeuterung ist laenger als ' . self::BESCHREIBUNG_MAXLAENGE . ' Zeichen.'
            );
        }

        return $beschreibung;
    }

    /**
     * Der gemeldete Gegenstand muss es wirklich geben.
     *
     * Ohne diese Pruefung stuenden Meldungen auf Kennungen in der
     * Arbeitsliste, zu denen sich nichts anzeigen laesst — und weil
     * meldungen.gegenstand_id polymorph und damit ohne Fremdschluessel ist,
     * faengt das Schema es nicht ab.
     *
     * @throws MeldungsFehler
     */
    private function pruefeGegenstandVorhanden(string $gegenstandArt, int $gegenstandId): void
    {
        if ($gegenstandId <= 0) {
            throw new MeldungsFehler(
                'gegenstand_unbekannt',
                'Kennung ' . $gegenstandId . ' ist keine gueltige Kennung.'
            );
        }

        // Der Tabellenname stammt aus self::GEGENSTAENDE, der Schluessel wurde
        // vorher gegen dieselbe Konstante geprueft. Es gibt keinen Weg, ueber
        // den Eingabetext hierher zu gelangen.
        $tabelle = self::GEGENSTAENDE[$gegenstandArt];

        $vorhanden = (int) $this->db->wert(
            'SELECT COUNT(*) FROM ' . $tabelle . ' WHERE id = :id',
            ['id' => $gegenstandId]
        );

        if ($vorhanden === 0) {
            throw new MeldungsFehler(
                'gegenstand_unbekannt',
                'Gegenstand ' . $gegenstandArt . ' ' . $gegenstandId . ' existiert nicht.'
            );
        }
    }

    /** @throws MeldungsFehler */
    private function pruefeMelderVorhanden(int $melderId): void
    {
        $vorhanden = (int) $this->db->wert(
            'SELECT COUNT(*) FROM benutzer WHERE id = :id',
            ['id' => $melderId]
        );

        if ($vorhanden === 0) {
            throw new MeldungsFehler(
                'melder_unbekannt',
                'Meldendes Konto ' . $melderId . ' existiert nicht.'
            );
        }
    }

    /**
     * Dieselbe Person meldet denselben Gegenstand nicht zweimal offen.
     *
     * ABFRAGE STATT EINDEUTIGEM INDEX — mit Absicht, aus drei Gruenden:
     *
     *  1. Die Bedingung ist kein Spaltenpaar, sondern haengt am STATUS: Nach
     *     'erledigt' oder 'abgelehnt' muss dieselbe Person denselben
     *     Gegenstand erneut melden koennen, weil sich der Inhalt geaendert
     *     haben kann. Ein Index ueber (melder_id, gegenstand_art,
     *     gegenstand_id) verboete das dauerhaft. Ein Teilindex mit
     *     WHERE-Bedingung kann SQLite, MySQL 8 nicht.
     *
     *  2. Fuer anonyme Meldungen wuerde ein Index ohnehin nichts leisten:
     *     Beide Datenbanken behandeln NULL in eindeutigen Indizes als
     *     verschieden. Ohne Konto gibt es keine Person, an der sich eine
     *     Doppelung festmachen liesse — und ein Sperren nach IP waere ein
     *     Personenbezug, den diese Plattform nirgends speichert.
     *
     *  3. Ddl kann kein ALTER TABLE, und 'meldungen' steht seit
     *     006_vertrauen.php. Ein neuer Index braeuchte eine eigene Migration.
     *
     * Die Luecke, die das laesst: Zwei GLEICHZEITIGE Anfragen derselben Person
     * kommen beide durch die Abfrage. Die Folge ist ein doppelter Eintrag in
     * der Arbeitsliste — laestig, aber kein Rechtsverstoss und keine
     * Beschraenkung. Ein Sperrmechanismus dagegen waere teurer als der
     * Schaden.
     *
     * @throws MeldungsFehler
     */
    private function pruefeKeineDoppelmeldung(int $melderId, string $gegenstandArt, int $gegenstandId): void
    {
        $platzhalter = [];
        $werte = [
            'melder' => $melderId,
            'art' => $gegenstandArt,
            'gegenstand' => $gegenstandId,
        ];

        foreach (array_values(Verwaltung::MELDUNG_UNERLEDIGT) as $i => $status) {
            $platzhalter[] = ':status' . $i;
            $werte['status' . $i] = $status;
        }

        $offen = (int) $this->db->wert(
            'SELECT COUNT(*) FROM meldungen
              WHERE melder_id = :melder
                AND gegenstand_art = :art
                AND gegenstand_id = :gegenstand
                AND status IN (' . implode(', ', $platzhalter) . ')',
            $werte
        );

        if ($offen > 0) {
            throw new MeldungsFehler(
                'bereits_gemeldet',
                'Konto ' . $melderId . ' hat ' . $gegenstandArt . ' ' . $gegenstandId
                . ' bereits gemeldet; die Meldung ist noch unerledigt.'
            );
        }
    }
}
