<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Admin;

use MeinSlip\Core\Database;
use MeinSlip\Domain\Account\Konten;
use MeinSlip\Domain\Ledger\Hauptbuch;
use MeinSlip\Domain\Order\Bestellzustand;

/**
 * Fachlogik des Verwaltungsbereichs.
 *
 * Drei Grundsaetze:
 *
 *  1. DIESE KLASSE BEWEGT KEIN GELD. Sie erzeugt und aendert NIEMALS eine
 *     Buchung, einen Vorgang oder ein Hauptbuchkonto. Sie liest das Hauptbuch
 *     nur — kennzahlen() und hauptbuchVorgaenge() sind Diagnose, keine
 *     Korrektur. Eine falsche Buchung wird durch eine Gegenbuchung ueber
 *     Hauptbuch::buchen() berichtigt, nie durch ein UPDATE aus der Verwaltung
 *     heraus. Wer hier eine schreibende Hauptbuchmethode ergaenzt, hebt die
 *     Zusicherung der doppelten Buchfuehrung auf.
 *
 *  2. KEINE BESCHRAENKUNG OHNE PROTOKOLL, BEGRUENDUNG UND ZUSTELLUNG. Jede
 *     Beschraenkung schreibt in DERSELBEN Transaktion zwei Zeilen: eine nach
 *     'verwaltungs_ereignisse' und eine nach 'benachrichtigungen'.
 *
 *     Die beiden Tabellen erfuellen verschiedene Pflichten und keine ersetzt
 *     die andere. 'verwaltungs_ereignisse' ist die INTERNE Sicht — sie belegt
 *     gegenueber Aufsicht und Gericht, WER entschieden hat, und wird nur unter
 *     /verwaltung gelesen. Art. 17 Abs. 1 DSA verlangt aber, dass die
 *     betroffene Person die Begruendung ERHAELT; ein Journal, das nur
 *     Verwalter sehen, erfuellt das nicht. Es belegt, DASS begruendet wurde,
 *     nicht, dass die Begruendung angekommen ist. Dafuer steht
 *     'benachrichtigungen': die Sicht der betroffenen Person, ohne die
 *     Verwalteridentitaet, mit Lesezeitpunkt als Nachweis.
 *
 *     Beides zusammen macht beschraenkungProtokollierenUndZustellen(); jede
 *     beschraenkende Handlung dieser Klasse laeuft darueber. Eine leere
 *     Begruendung wird abgewiesen und nicht durch einen Standardtext ersetzt.
 *
 *  3. NIEMAND ENTSCHEIDET UEBER DAS EIGENE KONTO. Sperre, Entsperrung,
 *     Freischaltung und Entzug einer Faehigkeit sind allesamt Entscheidungen
 *     ueber ein Konto und werden abgewiesen, wenn es das eigene ist —
 *     pruefeFremdesKonto() ist die eine Stelle, an der das steht.
 *
 * Die Freigabe eines Angebots gehoert bewusst NICHT hierher: Den Statuswechsel
 * macht Angebote, weil dort die Zustandsmaschine und das Vier-Augen-Prinzip
 * sitzen. Diese Klasse liefert nur die Arbeitsliste (offeneAngebote()) und
 * protokolliert auf Zuruf ueber ereignisSchreiben() beziehungsweise
 * beschraenkungProtokollierenUndZustellen().
 *
 * Zwei Methoden richten sich ausnahmsweise NICHT an die Verwaltung, sondern an
 * die betroffene Person: benachrichtigungen() und benachrichtigungGelesen().
 * Sie stehen hier, weil die Zustellung an derselben Stelle entsteht wie die
 * Entscheidung — waeren sie anderswo, liesse sich die Zusicherung aus
 * Grundsatz 2 nicht mehr an einer Datei ablesen. Beide arbeiten
 * ausschliesslich auf dem Konto, das ihnen uebergeben wird.
 */
final class Verwaltung
{
    /** Zeilen je Seite in allen Listen dieses Bereichs. */
    public const PRO_SEITE = 25;

    public const STATUS_AKTIV = 'aktiv';
    public const STATUS_GESPERRT = 'gesperrt';

    public const MELDUNG_OFFEN = 'offen';
    public const MELDUNG_IN_PRUEFUNG = 'in_pruefung';
    public const MELDUNG_ERLEDIGT = 'erledigt';
    public const MELDUNG_ABGELEHNT = 'abgelehnt';

    /** Erlaubte Meldungsstatus, in fachlicher Reihenfolge. */
    public const MELDUNGSSTATUS = [
        self::MELDUNG_OFFEN,
        self::MELDUNG_IN_PRUEFUNG,
        self::MELDUNG_ERLEDIGT,
        self::MELDUNG_ABGELEHNT,
    ];

    /** Status, in denen eine Meldung noch Arbeit ist — und eine Frist laeuft. */
    public const MELDUNG_UNERLEDIGT = [self::MELDUNG_OFFEN, self::MELDUNG_IN_PRUEFUNG];

    /**
     * Angebotsstatus fuer die Uebersicht.
     *
     * Die Liste steht hier, damit die Uebersicht auch dann eine vollstaendige
     * Zeilenmenge hat, wenn zu einem Status gerade kein Angebot existiert — ein
     * GROUP BY liefert nur belegte Werte. Massgeblich ist und bleibt
     * Angebote::STATUSWERTE; diese Klasse ruft den Katalog aber nicht auf.
     */
    public const ANGEBOTSSTATUS = ['entwurf', 'in_pruefung', 'aktiv', 'pausiert', 'entfernt'];

    /** Angebote in diesem Status warten auf eine Entscheidung. */
    public const ANGEBOT_IN_PRUEFUNG = 'in_pruefung';

    /** Pruefungsstatus laut database/migrations/001_konten.php: offen | bestanden | abgelehnt | abgelaufen. */
    public const PRUEFUNG_OFFEN = 'offen';

    public const HANDLUNG_FAEHIGKEIT_FREIGESCHALTET = 'faehigkeit_freigeschaltet';
    public const HANDLUNG_FAEHIGKEIT_ENTZOGEN = 'faehigkeit_entzogen';
    public const HANDLUNG_KONTO_GESPERRT = 'konto_gesperrt';
    public const HANDLUNG_KONTO_ENTSPERRT = 'konto_entsperrt';
    public const HANDLUNG_MELDUNG_BEARBEITET = 'meldung_bearbeitet';

    /** Gegenstandsarten, wie sie auch meldungen.gegenstand_art benutzt. */
    public const GEGENSTAND_BENUTZER = 'benutzer';
    public const GEGENSTAND_ANGEBOT = 'angebot';
    public const GEGENSTAND_BESTELLUNG = 'bestellung';
    public const GEGENSTAND_MELDUNG = 'meldung';

    /** Vorsilbe der Gegenstandsart bei Faehigkeiten: 'faehigkeit_kaufen'. */
    public const GEGENSTAND_FAEHIGKEIT = 'faehigkeit';

    /**
     * Spaltenbreiten aus database/migrations/008_verwaltung.php.
     *
     * Sie gelten auch fuer 'benachrichtigungen' aus 009_nachbesserung.php:
     * art ist dort bewusst so breit wie handlung hier und gegenstand_art in
     * beiden Tabellen gleich, damit sich Protokolleintrag und Zustellung ohne
     * Uebersetzungstabelle zuordnen lassen.
     */
    private const HANDLUNG_MAXLAENGE = 60;
    private const GEGENSTAND_ART_MAXLAENGE = 40;

    /** Nur EUR-Konten existieren; siehe database/migrations/003_hauptbuch.php. */
    private const WAEHRUNG = 'EUR';

    private const LETZTE_BESTELLUNGEN = 10;

    private const NEU_SEIT_TAGEN = 7;

    private readonly Konten $konten;

    private readonly Hauptbuch $hauptbuch;

    public function __construct(private readonly Database $db)
    {
        // Beide Abhaengigkeiten haengen nur an der Datenbank und haben keinen
        // eigenen Zustand. Sie hier zu bauen haelt jede Aufrufstelle davon
        // frei, drei Objekte zusammenstecken zu muessen.
        $this->konten = new Konten($db);
        $this->hauptbuch = new Hauptbuch($db);
    }

    // --- Uebersicht --------------------------------------------------------

    /**
     * Kennzahlen fuer die Uebersicht.
     *
     * Die wichtigste Zahl ist 'hauptbuch_abweichung_cent'. Ist sie ungleich
     * null, ist Geld entstanden oder verschwunden — dann stimmt die doppelte
     * Buchfuehrung nicht mehr und alles andere ist zweitrangig. Damit die
     * Oberflaeche das hervorheben kann, ohne selbst zu rechnen, steht das
     * Ergebnis zusaetzlich als 'hauptbuch_in_ordnung' daneben.
     *
     * @param string|null $jetzt Zeitpunkt als 'Y-m-d H:i:s' in UTC; null = jetzt
     *
     * @return array<string,mixed>
     */
    public function kennzahlen(?string $jetzt = null): array
    {
        $jetzt = $this->zeitpunkt($jetzt);
        $abweichung = $this->hauptbuch->abweichung();

        [$platzhalter, $werte] = $this->inListe('m', self::MELDUNG_UNERLEDIGT);

        return [
            'konten_gesamt' => $this->zahl('SELECT COUNT(*) FROM benutzer'),
            'konten_neu_7_tage' => $this->zahl(
                'SELECT COUNT(*) FROM benutzer WHERE angelegt_am >= :seit',
                ['seit' => $this->vorTagen($jetzt, self::NEU_SEIT_TAGEN)]
            ),
            'konten_gesperrt' => $this->zahl(
                'SELECT COUNT(*) FROM benutzer WHERE status = :s',
                ['s' => self::STATUS_GESPERRT]
            ),
            'angebote_je_status' => $this->gruppenzahlen(
                'SELECT status AS wert, COUNT(*) AS anzahl FROM angebote GROUP BY status',
                self::ANGEBOTSSTATUS
            ),
            'bestellungen_je_zustand' => $this->gruppenzahlen(
                'SELECT zustand AS wert, COUNT(*) AS anzahl FROM bestellungen GROUP BY zustand',
                $this->bestellzustaende()
            ),
            'meldungen_offen' => $this->zahl(
                'SELECT COUNT(*) FROM meldungen WHERE status = :s',
                ['s' => self::MELDUNG_OFFEN]
            ),
            // Die zugesagte Frist ist eine Selbstverpflichtung aus dem
            // Meldeverfahren nach Art. 16 DSA. Sie zu reissen ist ein eigener
            // Missstand und deshalb eine eigene Zahl.
            'meldungen_frist_ueberschritten' => $this->zahl(
                'SELECT COUNT(*) FROM meldungen
                  WHERE zugesagt_bis IS NOT NULL AND zugesagt_bis < :jetzt
                    AND status IN (' . $platzhalter . ')',
                ['jetzt' => $jetzt] + $werte
            ),
            'pruefungen_offen' => $this->zahl(
                'SELECT COUNT(*) FROM pruefungen WHERE status = :s',
                ['s' => self::PRUEFUNG_OFFEN]
            ),
            'hauptbuch_abweichung_cent' => $abweichung,
            'hauptbuch_in_ordnung' => $abweichung === 0,
            'hauptbuch_unausgeglichene_vorgaenge' => count($this->hauptbuch->unausgeglicheneVorgaenge()),
        ];
    }

    // --- Konten ------------------------------------------------------------

    /**
     * Konten seitenweise, wahlweise gefiltert nach Pseudonym oder E-Mail.
     *
     * @return array{zeilen:list<array<string,mixed>>, anzahl:int, seite:int, seiten:int, pro_seite:int}
     */
    public function konten(?string $suche = null, int $seite = 1): array
    {
        $suche = $suche === null ? '' : trim($suche);

        $bedingung = '';
        $werte = [];

        if ($suche !== '') {
            // Zwei Platzhalter fuer denselben Wert: PDO bindet mit
            // abgeschalteter Emulation jeden Namen genau einmal.
            //
            // % und _ aus der Eingabe sind Suchtext, keine Platzhalter: Wer
            // 'max_muster' eintippt, meint den Unterstrich und nicht 'ein
            // beliebiges Zeichen' — sonst zeigt die Liste Konten, die die
            // Suchende nie gemeint hat, und entschieden wird direkt daneben.
            //
            // Das Fluchtzeichen ist '!' und ausdruecklich NICHT der Rueckstrich:
            // Der ist in MySQL schon im Zeichenkettenliteral ein Fluchtzeichen
            // und muesste dort doppelt stehen, waehrend SQLite genau das mit
            // "ESCAPE expression must be a single character" abweist. Mit '!'
            // steht in beiden Dialekten dasselbe da.
            $bedingung = " WHERE LOWER(pseudonym) LIKE :suche_p ESCAPE '!'"
                . " OR LOWER(email) LIKE :suche_e ESCAPE '!'";
            // Reihenfolge tragend: Erst '!' verdoppeln, dann % und _ maskieren.
            // str_replace arbeitet die Paare nacheinander auf dem
            // Zwischenergebnis ab; umgekehrt wuerden die eben gesetzten
            // Fluchtzeichen selbst noch einmal verdoppelt.
            $werte['suche_p'] = '%' . str_replace(
                ['!', '%', '_'],
                ['!!', '!%', '!_'],
                mb_strtolower($suche)
            ) . '%';
            $werte['suche_e'] = $werte['suche_p'];
        }

        $anzahl = $this->zahl('SELECT COUNT(*) FROM benutzer' . $bedingung, $werte);
        [$seite, $versatz, $seiten] = $this->blaettern($seite, $anzahl);

        $zeilen = $this->db->alle(
            'SELECT id, pseudonym, email, status, angelegt_am, zuletzt_aktiv_am,
                    (SELECT COALESCE(SUM(hb.betrag_cent), 0)
                       FROM hauptbuch_buchungen hb
                       JOIN hauptbuch_konten hk ON hk.id = hb.konto_id
                      WHERE hk.benutzer_id = benutzer.id
                        AND hk.art = :kontoart AND hk.waehrung = :waehrung) AS guthaben_cent
               FROM benutzer' . $bedingung . '
              ORDER BY angelegt_am DESC, id DESC
              LIMIT ' . self::PRO_SEITE . ' OFFSET ' . $versatz,
            $werte + ['kontoart' => Hauptbuch::KONTO_GUTHABEN, 'waehrung' => self::WAEHRUNG]
        );

        $faehigkeiten = $this->faehigkeitenJeKonto(
            array_map(static fn (array $z): int => (int) $z['id'], $zeilen)
        );

        foreach ($zeilen as $i => $zeile) {
            $zeilen[$i]['guthaben_cent'] = (int) $zeile['guthaben_cent'];
            $zeilen[$i]['faehigkeiten'] = $faehigkeiten[(int) $zeile['id']] ?? [];
        }

        return $this->seitenwerk($zeilen, $anzahl, $seite, $seiten);
    }

    /**
     * Ein Konto mit allem, was fuer eine Entscheidung noetig ist.
     *
     * @return array<string,mixed>|null null, wenn es das Konto nicht gibt
     */
    public function konto(int $benutzerId): ?array
    {
        $konto = $this->db->eine(
            'SELECT id, pseudonym, email, status, sprache, land, homescreen_name,
                    push_vorschau, angelegt_am, zuletzt_aktiv_am
               FROM benutzer WHERE id = :id',
            ['id' => $benutzerId]
        );

        if ($konto === null) {
            return null;
        }

        // passwort_hash steht bewusst nicht in der Auswahl oben: Was nie in die
        // Vorlage wandert, kann auch nicht versehentlich ausgegeben werden.

        [$platzhalter, $werte] = $this->inListe('m', self::MELDUNG_UNERLEDIGT);

        return [
            'konto' => $konto,
            'faehigkeiten' => $this->konten->faehigkeiten($benutzerId),
            'guthaben_cent' => $this->saldo($benutzerId, Hauptbuch::KONTO_GUTHABEN),
            'einnahmen_cent' => $this->saldo($benutzerId, Hauptbuch::KONTO_EINNAHMEN),
            'pruefungen' => array_values($this->db->alle(
                'SELECT id, art, anbieter, anbieter_referenz, status, volljaehrig,
                        geprueft_am, gueltig_bis, angelegt_am
                   FROM pruefungen
                  WHERE benutzer_id = :b
                  ORDER BY angelegt_am DESC, id DESC',
                ['b' => $benutzerId]
            )),
            'bestellungen' => $this->letzteBestellungen($benutzerId),
            'meldungen' => array_values($this->db->alle(
                'SELECT m.id, m.melder_id, m.gegenstand_art, m.gegenstand_id, m.grund,
                        m.beschreibung, m.status, m.zugesagt_bis, m.erledigt_am, m.angelegt_am,
                        b.pseudonym AS melder_pseudonym
                   FROM meldungen m
                   LEFT JOIN benutzer b ON b.id = m.melder_id
                  WHERE m.gegenstand_art = :art AND m.gegenstand_id = :id
                    AND m.status IN (' . $platzhalter . ')
                  ORDER BY m.angelegt_am DESC, m.id DESC',
                ['art' => self::GEGENSTAND_BENUTZER, 'id' => $benutzerId] + $werte
            )),
        ];
    }

    /**
     * Schaltet eine Faehigkeit frei und protokolliert die Entscheidung.
     *
     * Beides in einer Transaktion: Eine freigeschaltete Faehigkeit ohne
     * Protokolleintrag waere eine Berechtigung, die niemand zu verantworten
     * hat — und eine Zeile im Protokoll ohne Wirkung waere eine Luege.
     *
     * @return int Kennung des Protokolleintrags
     *
     * @throws VerwaltungsFehler
     */
    public function faehigkeitFreischalten(
        int $verwalterId,
        int $benutzerId,
        string $faehigkeit,
        string $begruendung
    ): int {
        $faehigkeit = $this->gepruefteFaehigkeit($faehigkeit, true);
        $begruendung = $this->pflichtBegruendung($begruendung);
        // Auch das Vergeben ist eine Entscheidung ueber ein Konto (Grundsatz 3).
        // Ohne diese Zeile koennte eine Verwalterin sich selbst 'kaufen' geben
        // und damit die Altersverifikation umgehen.
        $this->pruefeFremdesKonto($verwalterId, $benutzerId);
        $this->pruefeKontoVorhanden($benutzerId);

        return $this->db->transaktion(function () use ($verwalterId, $benutzerId, $faehigkeit, $begruendung): int {
            // Die Grundlage bleibt am Recht selbst haengen: Wer sie spaeter
            // anzweifelt, sieht in benutzer_faehigkeiten sofort, dass sie aus
            // der Verwaltung stammt und von wem.
            $this->konten->faehigkeitFreischalten($benutzerId, $faehigkeit, 'verwaltung:' . $verwalterId);

            return $this->ereignisSchreiben(
                $verwalterId,
                self::HANDLUNG_FAEHIGKEIT_FREIGESCHALTET,
                self::GEGENSTAND_FAEHIGKEIT . '_' . $faehigkeit,
                $benutzerId,
                $begruendung
            );
        });
    }

    /**
     * Entzieht eine Faehigkeit, protokolliert die Entscheidung und stellt die
     * Begruendung der betroffenen Person zu.
     *
     * Die Selbstpruefung steht VOR der Transaktion, nicht darin. Sonst liefe
     * zuerst Konten::faehigkeitEntziehen() und pruefeVerwalter() saehe beim
     * Selbstentzug von 'verwalten' das eben entzogene Recht schon als entzogen
     * — die Meldung waere 'kein_verwaltungsrecht' und behauptete damit das
     * Gegenteil der Lage: Das Konto HAT das Recht, sonst waere es gar nicht
     * bis hierher gekommen.
     *
     * @return int Kennung des Protokolleintrags
     *
     * @throws VerwaltungsFehler
     */
    public function faehigkeitEntziehen(
        int $verwalterId,
        int $benutzerId,
        string $faehigkeit,
        string $begruendung
    ): int {
        $faehigkeit = $this->gepruefteFaehigkeit($faehigkeit, false);
        $begruendung = $this->pflichtBegruendung($begruendung);
        $this->pruefeFremdesKonto($verwalterId, $benutzerId);
        $this->pruefeKontoVorhanden($benutzerId);

        return $this->db->transaktion(function () use ($verwalterId, $benutzerId, $faehigkeit, $begruendung): int {
            $this->konten->faehigkeitEntziehen($benutzerId, $faehigkeit);

            // Auch wenn nichts zu entziehen war, wird protokolliert UND
            // zugestellt: Die Entscheidung ist gefallen und muss belegbar
            // bleiben — und die betroffene Person muss sie erfahren.
            return $this->beschraenkungSchreiben(
                $verwalterId,
                $benutzerId,
                self::HANDLUNG_FAEHIGKEIT_ENTZOGEN,
                self::GEGENSTAND_FAEHIGKEIT . '_' . $faehigkeit,
                $benutzerId,
                $begruendung
            );
        });
    }

    /**
     * Sperrt ein Konto.
     *
     * Laufende Sitzungen laufen dadurch von selbst ins Leere: Sitzungen::laden()
     * filtert auf benutzer.status = 'aktiv'. Es braucht also kein zusaetzliches
     * Abmelden, das man vergessen koennte.
     *
     * @return int Kennung des Protokolleintrags
     *
     * @throws VerwaltungsFehler
     */
    public function kontoSperren(int $verwalterId, int $benutzerId, string $begruendung): int
    {
        return $this->statusSetzen(
            $verwalterId,
            $benutzerId,
            self::STATUS_GESPERRT,
            self::HANDLUNG_KONTO_GESPERRT,
            $begruendung,
            true
        );
    }

    /**
     * Hebt eine Sperre auf.
     *
     * @return int Kennung des Protokolleintrags
     *
     * @throws VerwaltungsFehler
     */
    public function kontoEntsperren(int $verwalterId, int $benutzerId, string $begruendung): int
    {
        return $this->statusSetzen(
            $verwalterId,
            $benutzerId,
            self::STATUS_AKTIV,
            self::HANDLUNG_KONTO_ENTSPERRT,
            $begruendung,
            false
        );
    }

    // --- Angebote ----------------------------------------------------------

    /**
     * Angebote, die auf eine Entscheidung warten — aelteste zuerst.
     *
     * Nur die Liste: Freigabe und Ablehnung macht Angebote, weil dort die
     * Zustandsmaschine und das Vier-Augen-Prinzip sitzen.
     *
     * Beide Abfragen sind so geschrieben, dass sie
     * idx_angebote_status_angelegt_am aus 009_nachbesserung.php benutzen
     * koennen: Gleichheitsfilter auf a.status ohne Funktion darum, danach
     * ORDER BY a.angelegt_am — genau die Spaltenfolge des Index. Nachgemessen
     * mit EXPLAIN QUERY PLAN: 'SEARCH ... USING COVERING INDEX' beim COUNT,
     * 'SEARCH a USING INDEX' bei der Liste, in beiden Faellen ohne temporaeren
     * B-Baum fuer die Sortierung. Wer hier ein OR, ein LOWER(status) oder eine
     * andere Sortierung einbaut, nimmt dem Index seine Wirkung.
     *
     * @return array{zeilen:list<array<string,mixed>>, anzahl:int, seite:int, seiten:int, pro_seite:int}
     */
    public function offeneAngebote(int $seite = 1): array
    {
        $anzahl = $this->zahl(
            'SELECT COUNT(*) FROM angebote WHERE status = :s',
            ['s' => self::ANGEBOT_IN_PRUEFUNG]
        );
        [$seite, $versatz, $seiten] = $this->blaettern($seite, $anzahl);

        $zeilen = $this->db->alle(
            'SELECT a.id, a.titel, a.beschreibung, a.grundpreis_cent, a.waehrung, a.status,
                    a.angelegt_am, a.geaendert_am, a.verkaeufer_id, a.kategorie_id,
                    v.pseudonym AS verkaeufer_pseudonym, v.status AS verkaeufer_status,
                    k.schluessel AS kategorie_schluessel, k.pfad AS kategorie_pfad
               FROM angebote a
               JOIN benutzer v ON v.id = a.verkaeufer_id
               JOIN kategorien k ON k.id = a.kategorie_id
              WHERE a.status = :s
              ORDER BY a.angelegt_am ASC, a.id ASC
              LIMIT ' . self::PRO_SEITE . ' OFFSET ' . $versatz,
            ['s' => self::ANGEBOT_IN_PRUEFUNG]
        );

        foreach ($zeilen as $i => $zeile) {
            $zeilen[$i]['grundpreis_cent'] = (int) $zeile['grundpreis_cent'];
        }

        return $this->seitenwerk($zeilen, $anzahl, $seite, $seiten);
    }

    // --- Protokoll ---------------------------------------------------------

    /**
     * Schreibt einen Protokolleintrag.
     *
     * Oeffentlich, damit Routen protokollieren koennen, was sie ueber andere
     * Fachklassen entschieden haben — etwa die Freigabe eines Angebots.
     *
     * Eine leere Begruendung wird hier bewusst NICHT abgewiesen: Ein Vorgang,
     * der stattgefunden hat, muss auch dann im Protokoll landen, wenn der
     * Anlass nur ein Klick war. Die Begruendungspflicht sitzt dort, wo eine
     * Beschraenkung entsteht — in faehigkeitEntziehen(), kontoSperren() und
     * meldungBearbeiten().
     *
     * NUR fuer Vorgaenge, die niemanden beschraenken — die Freigabe eines
     * Angebots etwa. Fuer jede Beschraenkung ist
     * beschraenkungProtokollierenUndZustellen() zu nehmen: Ein Eintrag allein
     * in diesem Journal ist der interne Nachweis und erfuellt Art. 17 Abs. 1
     * DSA nicht, weil die betroffene Person ihn nie zu sehen bekommt.
     *
     * @return int Kennung des Eintrags
     *
     * @throws VerwaltungsFehler
     */
    public function ereignisSchreiben(
        int $verwalterId,
        string $handlung,
        string $gegenstandArt,
        ?int $gegenstandId,
        string $begruendung
    ): int {
        $handlung = trim($handlung);
        $gegenstandArt = trim($gegenstandArt);

        if ($handlung === '') {
            throw new VerwaltungsFehler('handlung_fehlt');
        }

        // Abweisen statt abschneiden: MySQL wuerde im strengen Modus ohnehin
        // scheitern, SQLite wuerde stillschweigend zu viel speichern — und ein
        // halber Protokolleintrag ist schlimmer als eine klare Absage.
        if (mb_strlen($handlung) > self::HANDLUNG_MAXLAENGE) {
            throw new VerwaltungsFehler(
                'handlung_zu_lang',
                'Handlung "' . $handlung . '" ist laenger als ' . self::HANDLUNG_MAXLAENGE . ' Zeichen.'
            );
        }

        if ($gegenstandArt === '') {
            throw new VerwaltungsFehler('gegenstand_art_fehlt');
        }

        if (mb_strlen($gegenstandArt) > self::GEGENSTAND_ART_MAXLAENGE) {
            throw new VerwaltungsFehler(
                'gegenstand_art_zu_lang',
                'Gegenstandsart "' . $gegenstandArt . '" ist laenger als '
                . self::GEGENSTAND_ART_MAXLAENGE . ' Zeichen.'
            );
        }

        $this->pruefeVerwalter($verwalterId);

        return $this->db->einfuegen('verwaltungs_ereignisse', [
            'verwalter_id' => $verwalterId,
            'handlung' => $handlung,
            'gegenstand_art' => $gegenstandArt,
            'gegenstand_id' => $gegenstandId,
            'begruendung' => trim($begruendung),
            'angelegt_am' => $this->zeitpunkt(null),
        ]);
    }

    /**
     * Das Protokoll, neueste zuerst.
     *
     * Die zweite Sortierstufe nach id ist noetig, weil angelegt_am nur auf
     * Sekunden genau ist: Zwei Entscheidungen in derselben Sekunde stuenden
     * sonst in beliebiger Reihenfolge.
     *
     * @return array{zeilen:list<array<string,mixed>>, anzahl:int, seite:int, seiten:int, pro_seite:int}
     */
    public function ereignisse(int $seite = 1): array
    {
        $anzahl = $this->zahl('SELECT COUNT(*) FROM verwaltungs_ereignisse');
        [$seite, $versatz, $seiten] = $this->blaettern($seite, $anzahl);

        $zeilen = $this->db->alle(
            'SELECT e.id, e.verwalter_id, e.handlung, e.gegenstand_art, e.gegenstand_id,
                    e.begruendung, e.angelegt_am, v.pseudonym AS verwalter_pseudonym
               FROM verwaltungs_ereignisse e
               LEFT JOIN benutzer v ON v.id = e.verwalter_id
              ORDER BY e.angelegt_am DESC, e.id DESC
              LIMIT ' . self::PRO_SEITE . ' OFFSET ' . $versatz
        );

        return $this->seitenwerk($zeilen, $anzahl, $seite, $seiten);
    }

    // --- Zustellung an die betroffene Person (Art. 17 DSA) -----------------

    /**
     * Der einzige Weg, auf dem eine Beschraenkung entstehen darf.
     *
     * Schreibt in EINER Transaktion den Protokolleintrag nach
     * 'verwaltungs_ereignisse' und die Zustellung nach 'benachrichtigungen'.
     * Faellt eines von beiden aus, faellt alles aus: Eine Beschraenkung ohne
     * Begruendung ist so wenig hinnehmbar wie eine Begruendung ohne
     * Beschraenkung, und ueber die Oberflaeche liesse sich weder das eine noch
     * das andere nachtragen.
     *
     * Oeffentlich, weil auch Handlungen darueber laufen muessen, deren Wirkung
     * eine andere Fachklasse ausloest — die Ablehnung eines Angebots etwa
     * macht Angebote, weil dort die Zustandsmaschine sitzt. Der Aufrufer legt
     * dann seine eigene Transaktion um Wirkung und diesen Aufruf;
     * Database::transaktion() ist verschachtelbar, es entsteht genau eine
     * Klammer.
     *
     * @param int    $betroffenerId Wer die Beschraenkung traegt — nicht wer sie anordnet
     * @param string $handlung      Wie in verwaltungs_ereignisse.handlung, z. B. 'konto_gesperrt'
     *
     * @return int Kennung des Verwaltungsereignisses
     *
     * @throws VerwaltungsFehler
     */
    public function beschraenkungProtokollierenUndZustellen(
        int $verwalterId,
        int $betroffenerId,
        string $handlung,
        string $gegenstandArt,
        ?int $gegenstandId,
        string $begruendung
    ): int {
        $begruendung = $this->pflichtBegruendung($begruendung);
        $this->pruefeFremdesKonto($verwalterId, $betroffenerId);
        $this->pruefeKontoVorhanden($betroffenerId);

        return $this->db->transaktion(
            fn (): int => $this->beschraenkungSchreiben(
                $verwalterId,
                $betroffenerId,
                $handlung,
                $gegenstandArt,
                $gegenstandId,
                $begruendung
            )
        );
    }

    /**
     * Legt eine Zustellung an — ohne Protokolleintrag.
     *
     * Getrennt von beschraenkungProtokollierenUndZustellen(), damit ein
     * Aufrufer, der den Protokolleintrag bereits selbst geschrieben hat
     * (VerwaltungsRouten beim Angebot), die Zustellung an genau diesen Eintrag
     * haengen kann. Wer beides braucht, nimmt die Methode darueber.
     *
     * @param string|null $gegenstandArt         Leer oder null = ohne Bezug
     * @param int|null    $verwaltungsEreignisId Protokolleintrag, auf dem die Zustellung beruht
     *
     * @return int Kennung der Zustellung
     *
     * @throws VerwaltungsFehler
     */
    public function benachrichtigen(
        int $benutzerId,
        string $art,
        ?string $gegenstandArt,
        ?int $gegenstandId,
        string $begruendung,
        ?int $verwaltungsEreignisId = null
    ): int {
        $art = trim($art);
        $gegenstandArt = $gegenstandArt === null ? '' : trim($gegenstandArt);

        // Dieselben Schluessel wie in ereignisSchreiben(): Die Spalte 'art' ist
        // die Entsprechung zu 'handlung' und genauso breit. Zwei Wortpaare
        // fuer denselben Sachverhalt braeuchten nur zwei Uebersetzungen mehr.
        if ($art === '') {
            throw new VerwaltungsFehler('handlung_fehlt');
        }

        if (mb_strlen($art) > self::HANDLUNG_MAXLAENGE) {
            throw new VerwaltungsFehler(
                'handlung_zu_lang',
                'Art "' . $art . '" ist laenger als ' . self::HANDLUNG_MAXLAENGE . ' Zeichen.'
            );
        }

        if (mb_strlen($gegenstandArt) > self::GEGENSTAND_ART_MAXLAENGE) {
            throw new VerwaltungsFehler(
                'gegenstand_art_zu_lang',
                'Gegenstandsart "' . $gegenstandArt . '" ist laenger als '
                . self::GEGENSTAND_ART_MAXLAENGE . ' Zeichen.'
            );
        }

        // Eine Zustellung ohne Text erfuellt Art. 17 Abs. 1 DSA nicht — sie
        // teilt der betroffenen Person mit, dass etwas geschehen ist, ohne ihr
        // zu sagen, warum. Das ist schlechter als nichts.
        $begruendung = $this->pflichtBegruendung($begruendung);

        // Der Fremdschluessel wuerde ein unbekanntes Konto ebenfalls abweisen,
        // aber als PDOException — und die faengt oben niemand ab.
        $this->pruefeKontoVorhanden($benutzerId);

        return $this->db->einfuegen('benachrichtigungen', [
            'benutzer_id' => $benutzerId,
            'art' => $art,
            'gegenstand_art' => $gegenstandArt === '' ? null : $gegenstandArt,
            'gegenstand_id' => $gegenstandId,
            'begruendung' => $begruendung,
            'verwaltungs_ereignis_id' => $verwaltungsEreignisId,
            'gelesen_am' => null,
            'angelegt_am' => $this->zeitpunkt(null),
        ]);
    }

    /**
     * Die Zustellungen einer Person, neueste zuerst.
     *
     * Die Sicht der BETROFFENEN Person, nicht die der Verwaltung — deshalb
     * steht verwaltungs_ereignis_id bewusst nicht in der Auswahl. Der
     * Protokolleintrag traegt die Verwalteridentitaet; er ist der Nachweis
     * gegenueber der Aufsicht und geht die betroffene Person nichts an.
     *
     * @return list<array<string,mixed>>
     */
    public function benachrichtigungen(int $benutzerId, bool $nurUngelesene = false): array
    {
        $bedingung = $nurUngelesene ? ' AND gelesen_am IS NULL' : '';

        $zeilen = $this->db->alle(
            'SELECT id, art, gegenstand_art, gegenstand_id, begruendung, gelesen_am, angelegt_am
               FROM benachrichtigungen
              WHERE benutzer_id = :b' . $bedingung . '
              ORDER BY angelegt_am DESC, id DESC',
            ['b' => $benutzerId]
        );

        foreach ($zeilen as $i => $zeile) {
            $zeilen[$i]['gegenstand_id'] = $zeile['gegenstand_id'] === null
                ? null
                : (int) $zeile['gegenstand_id'];
            $zeilen[$i]['gelesen'] = $zeile['gelesen_am'] !== null;
        }

        return array_values($zeilen);
    }

    /**
     * Markiert eine Zustellung als gelesen — ausschliesslich die eigene.
     *
     * benutzer_id steht in der Bedingung und nicht nur in einer vorgelagerten
     * Pruefung: Die Kennung kommt aus der Adresszeile und ist durchzaehlbar.
     * Eine fremde Zustellung meldet denselben Fehler wie eine, die es gar
     * nicht gibt — sonst liesse sich ueber die Fehlermeldung abzaehlen, wie
     * viele Beschraenkungen die Plattform ausgesprochen hat.
     *
     * @throws VerwaltungsFehler
     */
    public function benachrichtigungGelesen(int $benutzerId, int $id): void
    {
        $vorhanden = $this->zahl(
            'SELECT COUNT(*) FROM benachrichtigungen WHERE id = :id AND benutzer_id = :b',
            ['id' => $id, 'b' => $benutzerId]
        );

        if ($vorhanden === 0) {
            throw new VerwaltungsFehler(
                'benachrichtigung_unbekannt',
                'Benachrichtigung ' . $id . ' gehoert nicht zu Konto ' . $benutzerId . '.'
            );
        }

        // 'gelesen_am IS NULL' haelt den ERSTEN Lesezeitpunkt fest. Er ist der
        // Nachweis, wann die Begruendung angekommen ist; ein zweiter Aufruf
        // darf ihn nicht nach hinten schieben.
        $this->db->ausfuehren(
            'UPDATE benachrichtigungen SET gelesen_am = :z
              WHERE id = :id AND benutzer_id = :b AND gelesen_am IS NULL',
            ['z' => $this->zeitpunkt(null), 'id' => $id, 'b' => $benutzerId]
        );
    }

    // --- Meldungen ---------------------------------------------------------

    /**
     * Meldungen seitenweise, wahlweise nach Status gefiltert.
     *
     * @param string|null $status null = alle
     * @param string|null $jetzt  Zeitpunkt als 'Y-m-d H:i:s' in UTC; null = jetzt
     *
     * @return array{zeilen:list<array<string,mixed>>, anzahl:int, seite:int, seiten:int, pro_seite:int}
     *
     * @throws VerwaltungsFehler
     */
    public function meldungen(?string $status = null, int $seite = 1, ?string $jetzt = null): array
    {
        $jetzt = $this->zeitpunkt($jetzt);

        $bedingung = '';
        $werte = [];

        if ($status !== null && trim($status) !== '') {
            // Ein unbekannter Status wird abgewiesen und nicht ignoriert: Ein
            // stillschweigend uebergangener Filter zeigt Meldungen, die die
            // Bearbeiterin gerade nicht sehen wollte.
            $bedingung = ' WHERE m.status = :status';
            $werte['status'] = $this->gepruefterMeldungsstatus($status);
        }

        $anzahl = $this->zahl('SELECT COUNT(*) FROM meldungen m' . $bedingung, $werte);
        [$seite, $versatz, $seiten] = $this->blaettern($seite, $anzahl);

        $zeilen = $this->db->alle(
            'SELECT m.id, m.melder_id, m.gegenstand_art, m.gegenstand_id, m.grund,
                    m.beschreibung, m.status, m.zugesagt_bis, m.erledigt_am,
                    m.entscheidung, m.angelegt_am, b.pseudonym AS melder_pseudonym
               FROM meldungen m
               LEFT JOIN benutzer b ON b.id = m.melder_id' . $bedingung . '
              ORDER BY m.angelegt_am DESC, m.id DESC
              LIMIT ' . self::PRO_SEITE . ' OFFSET ' . $versatz,
            $werte
        );

        foreach ($zeilen as $i => $zeile) {
            $frist = $zeile['zugesagt_bis'];
            $zeilen[$i]['frist_ueberschritten'] = is_string($frist)
                && $frist !== ''
                && $frist < $jetzt
                && in_array((string) $zeile['status'], self::MELDUNG_UNERLEDIGT, true);
        }

        return $this->seitenwerk($zeilen, $anzahl, $seite, $seiten);
    }

    /**
     * Entscheidet ueber eine Meldung.
     *
     * erledigt_am wird nur in den abschliessenden Status gesetzt und beim
     * Zurueckholen in die Pruefung wieder geleert — eine Meldung, an der noch
     * gearbeitet wird, ist nicht erledigt, auch wenn sie es einmal war.
     *
     * @return int Kennung des Protokolleintrags
     *
     * @throws VerwaltungsFehler
     */
    public function meldungBearbeiten(
        int $verwalterId,
        int $meldungId,
        string $status,
        string $entscheidung
    ): int {
        $status = $this->gepruefterMeldungsstatus($status);
        $entscheidung = trim($entscheidung);

        if ($entscheidung === '') {
            throw new VerwaltungsFehler('entscheidung_fehlt');
        }

        $vorhanden = $this->zahl('SELECT COUNT(*) FROM meldungen WHERE id = :id', ['id' => $meldungId]);

        if ($vorhanden === 0) {
            throw new VerwaltungsFehler('meldung_unbekannt', 'Meldung ' . $meldungId . ' existiert nicht.');
        }

        return $this->db->transaktion(function () use ($verwalterId, $meldungId, $status, $entscheidung): int {
            $abgeschlossen = in_array($status, [self::MELDUNG_ERLEDIGT, self::MELDUNG_ABGELEHNT], true);

            $this->db->ausfuehren(
                'UPDATE meldungen SET status = :s, entscheidung = :e, erledigt_am = :z WHERE id = :id',
                [
                    's' => $status,
                    'e' => $entscheidung,
                    'z' => $abgeschlossen ? $this->zeitpunkt(null) : null,
                    'id' => $meldungId,
                ]
            );

            return $this->ereignisSchreiben(
                $verwalterId,
                self::HANDLUNG_MELDUNG_BEARBEITET,
                self::GEGENSTAND_MELDUNG,
                $meldungId,
                $entscheidung
            );
        });
    }

    // --- Hauptbuch (nur lesen) ---------------------------------------------

    /**
     * Die letzten Vorgaenge mit ihrer Summe.
     *
     * NUR LESEN. Der Zweck ist, eine Abweichung einzugrenzen: Ein Vorgang, in
     * dem 'ausgeglichen' false ist, ist die Fundstelle. Korrigiert wird
     * ausschliesslich mit einer Gegenbuchung ueber Hauptbuch::buchen().
     *
     * @return array{zeilen:list<array<string,mixed>>, anzahl:int, seite:int, seiten:int, pro_seite:int}
     */
    public function hauptbuchVorgaenge(int $seite = 1): array
    {
        $anzahl = $this->zahl('SELECT COUNT(*) FROM hauptbuch_vorgaenge');
        [$seite, $versatz, $seiten] = $this->blaettern($seite, $anzahl);

        // Zwei Abfragen statt einer: Erst die 25 Vorgaenge dieser Seite, dann
        // die Summen nur zu diesen Kennungen.
        //
        // Vorher stand hier EIN Aufruf, der hauptbuch_vorgaenge per LEFT JOIN
        // mit saemtlichen Buchungen verband, ueber sieben Spalten gruppierte,
        // sortierte — und erst danach 25 Zeilen abschnitt. Der Aufwand hing
        // damit an der Groesse des gesamten Hauptbuchs statt an der Laenge der
        // Seite. Ausgerechnet die Seite, die man bei einer Abweichung oeffnet,
        // wurde also genau dann langsam, wenn viele Daten da sind.
        $zeilen = $this->db->alle(
            'SELECT id, art, bezug_art, bezug_id, beschreibung,
                    idempotenz_schluessel, angelegt_am
               FROM hauptbuch_vorgaenge
              ORDER BY angelegt_am DESC, id DESC
              LIMIT ' . self::PRO_SEITE . ' OFFSET ' . $versatz
        );

        $summen = $this->buchungssummen(
            array_map(static fn (array $z): int => (int) $z['id'], $zeilen)
        );

        foreach ($zeilen as $i => $zeile) {
            // Ein Vorgang ohne Buchungen taucht in $summen nicht auf. Er zaehlt
            // wie zuvor beim LEFT JOIN als 0/0 — und bleibt damit
            // 'ausgeglichen', denn eine leere Summe ist null.
            $summe = $summen[(int) $zeile['id']]['summe_cent'] ?? 0;
            $zeilen[$i]['summe_cent'] = $summe;
            $zeilen[$i]['buchungen'] = $summen[(int) $zeile['id']]['buchungen'] ?? 0;
            $zeilen[$i]['ausgeglichen'] = $summe === 0;
        }

        return $this->seitenwerk($zeilen, $anzahl, $seite, $seiten);
    }

    // --- intern ------------------------------------------------------------

    /**
     * Setzt benutzer.status und protokolliert — in einer Transaktion.
     *
     * @param bool $beschraenkung true bei der Sperre, false beim Aufheben.
     *                            Nur die Sperre wird zugestellt: Art. 17 DSA
     *                            gilt fuer Beschraenkungen, nicht fuer
     *                            Entscheidungen zugunsten der betroffenen
     *                            Person. Wer das Aufheben ebenfalls zustellen
     *                            will, ruft benachrichtigen() zusaetzlich auf —
     *                            eine Pflicht ist es nicht.
     *
     * @throws VerwaltungsFehler
     */
    private function statusSetzen(
        int $verwalterId,
        int $benutzerId,
        string $status,
        string $handlung,
        string $begruendung,
        bool $beschraenkung
    ): int {
        $begruendung = $this->pflichtBegruendung($begruendung);
        $this->pruefeFremdesKonto($verwalterId, $benutzerId);
        $this->pruefeKontoVorhanden($benutzerId);

        return $this->db->transaktion(function () use ($verwalterId, $benutzerId, $status, $handlung, $begruendung, $beschraenkung): int {
            $this->db->ausfuehren(
                'UPDATE benutzer SET status = :s WHERE id = :id',
                ['s' => $status, 'id' => $benutzerId]
            );

            if ($beschraenkung) {
                return $this->beschraenkungSchreiben(
                    $verwalterId,
                    $benutzerId,
                    $handlung,
                    self::GEGENSTAND_BENUTZER,
                    $benutzerId,
                    $begruendung
                );
            }

            return $this->ereignisSchreiben(
                $verwalterId,
                $handlung,
                self::GEGENSTAND_BENUTZER,
                $benutzerId,
                $begruendung
            );
        });
    }

    /**
     * Protokolleintrag und Zustellung, ohne eigene Transaktion.
     *
     * Der Kern von beschraenkungProtokollierenUndZustellen(). Getrennt, damit
     * ihn Methoden benutzen koennen, die die Wirkung selbst ausloesen und
     * bereits eine Transaktion offen haben — ohne dass deren Vorpruefungen ein
     * zweites Mal laufen.
     *
     * @return int Kennung des Verwaltungsereignisses
     *
     * @throws VerwaltungsFehler
     */
    private function beschraenkungSchreiben(
        int $verwalterId,
        int $betroffenerId,
        string $handlung,
        string $gegenstandArt,
        ?int $gegenstandId,
        string $begruendung
    ): int {
        $ereignisId = $this->ereignisSchreiben(
            $verwalterId,
            $handlung,
            $gegenstandArt,
            $gegenstandId,
            $begruendung
        );

        // verwaltungs_ereignis_id verbindet beide Seiten: Zu jeder
        // Beschraenkung im Journal muss eine Zustellung auffindbar sein, und
        // jede Zustellung nennt den Vorgang, auf dem sie beruht. Ohne diese
        // Verbindung liesse sich weder zeigen, dass zugestellt wurde, noch dass
        // das Zugestellte der Entscheidung entspricht.
        $this->benachrichtigen(
            $betroffenerId,
            $handlung,
            $gegenstandArt,
            $gegenstandId,
            $begruendung,
            $ereignisId
        );

        return $ereignisId;
    }

    /**
     * Buchungssummen zu einer Handvoll Vorgaengen.
     *
     * Die Gruppierung laeuft ueber den vorhandenen
     * idx_hauptbuch_buchungen_vorgang_id und beruehrt nur die Buchungen der
     * angefragten Vorgaenge — nicht das ganze Hauptbuch.
     *
     * @param list<int> $vorgangIds
     *
     * @return array<int,array{summe_cent:int, buchungen:int}>
     */
    private function buchungssummen(array $vorgangIds): array
    {
        if ($vorgangIds === []) {
            return [];
        }

        [$platzhalter, $werte] = $this->inListe('v', $vorgangIds);

        $ergebnis = [];

        foreach ($this->db->alle(
            'SELECT vorgang_id, COALESCE(SUM(betrag_cent), 0) AS summe_cent, COUNT(id) AS buchungen
               FROM hauptbuch_buchungen
              WHERE vorgang_id IN (' . $platzhalter . ')
              GROUP BY vorgang_id',
            $werte
        ) as $zeile) {
            $ergebnis[(int) $zeile['vorgang_id']] = [
                'summe_cent' => (int) $zeile['summe_cent'],
                'buchungen' => (int) $zeile['buchungen'],
            ];
        }

        return $ergebnis;
    }

    /** @return list<array<string,mixed>> */
    private function letzteBestellungen(int $benutzerId): array
    {
        $zeilen = $this->db->alle(
            'SELECT b.id, b.nummer, b.zustand, b.lieferart, b.land, b.waehrung,
                    b.summe_verkauf_cent, b.kaeufer_id, b.verkaeufer_id, b.angelegt_am,
                    k.pseudonym AS kaeufer_pseudonym, v.pseudonym AS verkaeufer_pseudonym
               FROM bestellungen b
               JOIN benutzer k ON k.id = b.kaeufer_id
               JOIN benutzer v ON v.id = b.verkaeufer_id
              WHERE b.kaeufer_id = :b1 OR b.verkaeufer_id = :b2
              ORDER BY b.angelegt_am DESC, b.id DESC
              LIMIT ' . self::LETZTE_BESTELLUNGEN,
            ['b1' => $benutzerId, 'b2' => $benutzerId]
        );

        foreach ($zeilen as $i => $zeile) {
            $zeilen[$i]['summe_verkauf_cent'] = (int) $zeile['summe_verkauf_cent'];
            $zeilen[$i]['rolle'] = (int) $zeile['kaeufer_id'] === $benutzerId ? 'kaeufer' : 'verkaeufer';
        }

        return array_values($zeilen);
    }

    /**
     * Saldo eines Benutzerkontos, ohne es anzulegen.
     *
     * Hauptbuch::guthaben() legt das Konto bei Bedarf an. Eine Uebersicht darf
     * nichts anlegen — sonst entstuenden allein durch Hinsehen Kontozeilen.
     */
    private function saldo(int $benutzerId, string $art): int
    {
        return (int) $this->db->wert(
            'SELECT COALESCE(SUM(hb.betrag_cent), 0)
               FROM hauptbuch_buchungen hb
               JOIN hauptbuch_konten hk ON hk.id = hb.konto_id
              WHERE hk.benutzer_id = :b AND hk.art = :a AND hk.waehrung = :w',
            ['b' => $benutzerId, 'a' => $art, 'w' => self::WAEHRUNG]
        );
    }

    /**
     * Faehigkeiten mehrerer Konten in EINER Abfrage.
     *
     * In PHP gesammelt statt per GROUP_CONCAT: Das gibt es zwar in MySQL und
     * SQLite, aber mit unterschiedlicher Trennzeichen-Syntax.
     *
     * @param list<int> $benutzerIds
     *
     * @return array<int,list<string>>
     */
    private function faehigkeitenJeKonto(array $benutzerIds): array
    {
        if ($benutzerIds === []) {
            return [];
        }

        [$platzhalter, $werte] = $this->inListe('b', $benutzerIds);

        $zeilen = $this->db->alle(
            'SELECT benutzer_id, faehigkeit FROM benutzer_faehigkeiten
              WHERE entzogen_am IS NULL AND benutzer_id IN (' . $platzhalter . ')
              ORDER BY faehigkeit ASC',
            $werte
        );

        $ergebnis = [];
        foreach ($zeilen as $zeile) {
            $ergebnis[(int) $zeile['benutzer_id']][] = (string) $zeile['faehigkeit'];
        }

        return $ergebnis;
    }

    /**
     * Zaehlt die Treffer je Gruppe und fuellt fehlende Gruppen mit 0 auf.
     *
     * @param list<string>        $erwartet
     * @param array<string,mixed> $werte
     *
     * @return array<string,int>
     */
    private function gruppenzahlen(string $sql, array $erwartet, array $werte = []): array
    {
        $ergebnis = array_fill_keys($erwartet, 0);

        foreach ($this->db->alle($sql, $werte) as $zeile) {
            // Ein Wert, den die Liste nicht kennt, wird angehaengt statt
            // verschluckt: Sonst bliebe ein unbekannter Status in der
            // Datenbank unsichtbar, obwohl er genau dann auffallen muss.
            $ergebnis[(string) $zeile['wert']] = (int) $zeile['anzahl'];
        }

        return $ergebnis;
    }

    /** @return list<string> */
    private function bestellzustaende(): array
    {
        return array_map(
            static fn (Bestellzustand $zustand): string => $zustand->value,
            Bestellzustand::cases()
        );
    }

    /**
     * Baut eine IN-Liste aus benannten Platzhaltern.
     *
     * @param list<string|int> $werte
     *
     * @return array{0:string, 1:array<string,mixed>}
     */
    private function inListe(string $praefix, array $werte): array
    {
        $platzhalter = [];
        $parameter = [];

        foreach (array_values($werte) as $i => $wert) {
            $name = $praefix . $i;
            $platzhalter[] = ':' . $name;
            $parameter[$name] = $wert;
        }

        return [implode(', ', $platzhalter), $parameter];
    }

    /** @param array<string,mixed> $werte */
    private function zahl(string $sql, array $werte = []): int
    {
        return (int) $this->db->wert($sql, $werte);
    }

    /**
     * @return array{0:int, 1:int, 2:int} Seite, Versatz, Zahl der Seiten
     */
    private function blaettern(int $seite, int $anzahl): array
    {
        $seiten = max(1, (int) ceil($anzahl / self::PRO_SEITE));
        $seite = max(1, min($seite, $seiten));

        return [$seite, ($seite - 1) * self::PRO_SEITE, $seiten];
    }

    /**
     * @param list<array<string,mixed>> $zeilen
     *
     * @return array{zeilen:list<array<string,mixed>>, anzahl:int, seite:int, seiten:int, pro_seite:int}
     */
    private function seitenwerk(array $zeilen, int $anzahl, int $seite, int $seiten): array
    {
        return [
            'zeilen' => array_values($zeilen),
            'anzahl' => $anzahl,
            'seite' => $seite,
            'seiten' => $seiten,
            'pro_seite' => self::PRO_SEITE,
        ];
    }

    /** @throws VerwaltungsFehler */
    private function pflichtBegruendung(string $begruendung): string
    {
        $begruendung = trim($begruendung);

        if ($begruendung === '') {
            throw new VerwaltungsFehler('begruendung_fehlt');
        }

        return $begruendung;
    }

    /**
     * @param bool $zumVergeben true beim Freischalten, false beim Entziehen
     *
     * @throws VerwaltungsFehler
     */
    private function gepruefteFaehigkeit(string $faehigkeit, bool $zumVergeben): string
    {
        $faehigkeit = trim($faehigkeit);

        if (!Konten::faehigkeitBekannt($faehigkeit)) {
            throw new VerwaltungsFehler(
                'faehigkeit_unbekannt',
                'Unbekannte Faehigkeit "' . $faehigkeit . '".'
            );
        }

        // Bewusst unsymmetrisch: Entziehen darf ueber das Web, Vergeben nicht.
        // Koennte der Verwaltungsbereich die Faehigkeit 'verwalten' vergeben,
        // reichte EIN uebernommenes Verwalterkonto, um beliebig viele weitere
        // anzulegen — die Absicherung ueber bin/verwalter waere hinfaellig.
        // Umgekehrt muss ein uebernommenes Konto sofort entrechtet werden
        // koennen, ohne auf Serverzugriff zu warten.
        if ($zumVergeben && $faehigkeit === Konten::FAEHIGKEIT_VERWALTEN) {
            throw new VerwaltungsFehler(
                'faehigkeit_nicht_vergebbar',
                'Die Faehigkeit "' . Konten::FAEHIGKEIT_VERWALTEN
                . '" wird ausschliesslich ueber bin/verwalter vergeben.'
            );
        }

        return $faehigkeit;
    }

    /** @throws VerwaltungsFehler */
    private function gepruefterMeldungsstatus(string $status): string
    {
        $status = trim($status);

        if (!in_array($status, self::MELDUNGSSTATUS, true)) {
            throw new VerwaltungsFehler(
                'meldungsstatus_unbekannt',
                'Unbekannter Meldungsstatus "' . $status . '".'
            );
        }

        return $status;
    }

    /**
     * Grundsatz 3: Niemand entscheidet ueber das eigene Konto.
     *
     * Eine Stelle fuer alle Entscheidungswege — Sperre, Entsperrung,
     * Freischaltung, Entzug, Beschraenkung. Wer sich selbst sperren duerfte,
     * duerfte sich auch selbst entsperren; wer sich eine Faehigkeit selbst
     * geben duerfte, braeuchte keine Pruefung mehr.
     *
     * Der Schluessel heisst weiterhin 'selbstsperre_unzulaessig', weil sein
     * Text ("Ueber das eigene Konto entscheidet jemand anderes.") den ganzen
     * Fall traegt und in resources/lang/de-DE/verwaltung.php sowie in der
     * Allowlist von VerwaltungsRouten bereits steht.
     *
     * @throws VerwaltungsFehler
     */
    private function pruefeFremdesKonto(int $verwalterId, int $benutzerId): void
    {
        if ($verwalterId === $benutzerId) {
            throw new VerwaltungsFehler(
                'selbstsperre_unzulaessig',
                'Konto ' . $verwalterId . ' darf nicht ueber sich selbst entscheiden.'
            );
        }
    }

    /** @throws VerwaltungsFehler */
    private function pruefeKontoVorhanden(int $benutzerId): void
    {
        if ($this->zahl('SELECT COUNT(*) FROM benutzer WHERE id = :id', ['id' => $benutzerId]) === 0) {
            throw new VerwaltungsFehler('konto_unbekannt', 'Konto ' . $benutzerId . ' existiert nicht.');
        }
    }

    /**
     * Prueft, dass hinter einem Protokolleintrag wirklich ein Verwalterkonto
     * steht.
     *
     * Die Zugangspruefung der Route ersetzt das nicht: Ein Protokoll, in dem
     * eine Handlung einem Konto zugeschrieben wird, das gar nicht verwalten
     * darf, waere als Nachweis wertlos. Deshalb wird die Faehigkeit hier
     * ein zweites Mal geprueft — an der Stelle, an der geschrieben wird.
     *
     * @throws VerwaltungsFehler
     */
    private function pruefeVerwalter(int $verwalterId): void
    {
        if ($this->zahl('SELECT COUNT(*) FROM benutzer WHERE id = :id', ['id' => $verwalterId]) === 0) {
            throw new VerwaltungsFehler(
                'verwalter_unbekannt',
                'Verwaltendes Konto ' . $verwalterId . ' existiert nicht.'
            );
        }

        if (!$this->konten->hatFaehigkeit($verwalterId, Konten::FAEHIGKEIT_VERWALTEN)) {
            throw new VerwaltungsFehler(
                'kein_verwaltungsrecht',
                'Konto ' . $verwalterId . ' hat die Faehigkeit "'
                . Konten::FAEHIGKEIT_VERWALTEN . '" nicht.'
            );
        }
    }

    /** Zeitpunkt in UTC, wie ihn das gesamte Schema erwartet. */
    private function zeitpunkt(?string $jetzt): string
    {
        return $jetzt ?? gmdate('Y-m-d H:i:s');
    }

    /**
     * @throws VerwaltungsFehler
     */
    private function vorTagen(string $jetzt, int $tage): string
    {
        // Die Zeitzone wird ausdruecklich angehaengt, weil strtotime sonst die
        // lokale annimmt — und alle Zeitstempel im Schema sind UTC.
        $stempel = strtotime($jetzt . ' UTC');

        if ($stempel === false) {
            throw new VerwaltungsFehler(
                'zeitpunkt_ungueltig',
                'Zeitpunkt "' . $jetzt . '" ist nicht lesbar.'
            );
        }

        return gmdate('Y-m-d H:i:s', $stempel - $tage * 86400);
    }
}
