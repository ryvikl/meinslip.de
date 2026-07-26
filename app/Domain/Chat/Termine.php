<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Chat;

use MeinSlip\Core\Database;

/**
 * Termine: vorschlagen, annehmen, beidseitig quittieren.
 *
 * Die geldfreie Parallelspur zur Uebergabe. Bestellungen::uebergabePlanen()
 * setzt eine Bestellung voraus, und ohne Zahlung entsteht keine — diese Klasse
 * kommt ohne beides aus. Sie bewegt kein Geld, sie kennt keinen Betrag, und
 * termine.bestellung_id bleibt NULL, bis ein spaeteres Paket beide Spuren
 * verbindet (die Spalte steht trotzdem schon im Schema, weil Ddl kein ALTER
 * TABLE kann).
 *
 * Sechs Zusicherungen entstehen hier und nirgends sonst:
 *
 *  1. EIN TERMIN GEHOERT ZU EINER UNTERHALTUNG. Dort sind genau zwei Personen
 *     definiert — nur deshalb ist "das Gegenueber" ueberhaupt bestimmbar, und
 *     ohne diese Bestimmbarkeit gibt es die Regel unter 2. nicht. Die
 *     ausfuehrliche Begruendung steht in database/migrations/012_termine.php.
 *
 *  2. ANNEHMEN DARF NUR DAS GEGENUEBER. Wer vorschlaegt, nimmt nicht an. Ohne
 *     diese Regel waere die Verabredung eine einseitige Behauptung: Eine
 *     Person setzte Zeit und Ort und bestaetigte sie sich selbst, und die
 *     andere faende in ihrem Chat einen "angenommenen" Termin, dem sie nie
 *     zugestimmt hat. Genau dieser Zustand ist im Zweifel ein Druckmittel
 *     ("wir waren doch verabredet").
 *
 *  3. QUITTIEREN DUERFEN BEIDE — JEDE NUR FUER SICH. quittiert_a_am und
 *     quittiert_b_am sind zwei Spalten und keine gemeinsame, und niemand
 *     schreibt in die des anderen. Eine einseitige Quittung genuegt NIE:
 *     Erst wenn beide Zeitstempel stehen, wechselt der Zustand nach
 *     'uebergeben' — im SELBEN Transaktionsblock wie die zweite Quittung.
 *     Waere der Wechsel ein zweiter Schreibvorgang, gaebe es einen Zustand
 *     "beide haben quittiert, aber der Termin steht noch auf angenommen", und
 *     genau der ist der Ansatzpunkt fuer jeden Streit ueber die Uebergabe.
 *
 *  4. QUITTIERT WIRD ERST, WENN DER ZEITPUNKT ERREICHT IST. Eine Quittung im
 *     Voraus ist keine Bestaetigung, sondern eine Vorabgabe — dasselbe
 *     Missverhaeltnis, an dem der urspruengliche QR-Entwurf gescheitert ist
 *     (docs/04-features/safe-meet.md): Die Verkaeuferin verlangt "erst
 *     quittieren, dann bekommst du es". Vor dem Zeitpunkt gibt es hier nichts
 *     zu quittieren.
 *
 *  5. EIN NEUER VORSCHLAG IST EINE NEUE ZEILE. Der Automat kennt keinen
 *     Rueckweg: 'abgelehnt' und 'verfallen' sind Endzustaende, und
 *     vorschlagen() ruehrt keine bestehende Zeile an. Wer eine abgelehnte
 *     Verabredung wieder auf 'vorgeschlagen' drehen koennte, koennte auch eine
 *     bereits quittierte Uebergabe zurueckdrehen — und der Verlauf, wer wann
 *     was zugesagt hat, waere nicht mehr rekonstruierbar.
 *
 *  6. KEIN STANDORT. Kein Feld dieser Klasse nimmt Koordinaten, eine Anschrift
 *     oder einen Kartenpunkt entgegen, auch nicht optional. treffpunkt_art ist
 *     eine feste Liste, region ist grob und 40 Zeichen kurz. Eine
 *     Standortfunktion ist das Bauteil, das aus "Warenuebergabe" ein "Treffen"
 *     macht — die Angriffsflaeche, gegen die die ganze rechtliche Brandmauer
 *     gebaut ist. Wer uebergibt, ist ueberwiegend die Verkaeuferin, also die
 *     Partei, die am dringendsten vor Nachstellung geschuetzt gehoert. Und ein
 *     Kartenpunkt ist unwiderruflich.
 *
 * ZUR DROSSELUNG. Ein Terminvorschlag ist eine Aufforderung, sich an einem Ort
 * einzufinden. Ohne Grenze waere er das bequemste Belaestigungswerkzeug, das
 * diese Plattform zu bieten haette — beliebig oft, jedes Mal mit einer neuen
 * Uhrzeit, und jedes Mal mit einer Meldung im Postfach. Zwei Grenzen fangen die
 * beiden Formen ab: die Zahl der Vorschlaege je Tag und Konto (die Breite) und
 * die Zahl der gleichzeitig offenen Vorschlaege je Unterhaltung (die Tiefe).
 */
final class Termine
{
    // --- Treffpunktarten ----------------------------------------------------

    /**
     * Ein oeffentlicher Ort mit Publikum — Platz, Park, Einkaufsstrasse.
     *
     * Steht bewusst an erster Stelle. Die Reihenfolge dieser Liste ist die
     * Reihenfolge im Formular, und die erste Angabe ist die, die am haeufigsten
     * gewaehlt wird.
     */
    public const TREFFPUNKT_OEFFENTLICH = 'oeffentlicher_ort';

    /** Bahnhof oder Haltestelle: belebt, beleuchtet, videoueberwacht. */
    public const TREFFPUNKT_BAHNHOF = 'bahnhof';

    /** Cafe oder Lokal: drinnen, mit Personal. */
    public const TREFFPUNKT_CAFE = 'cafe';

    /** Paketshop oder Packstation — die Uebergabe ohne Treffen. */
    public const TREFFPUNKT_PAKETSHOP = 'paketshop';

    /**
     * Uebergabe an der Haustuer.
     *
     * Steht bewusst zuletzt und ohne jede Hervorhebung. Sie ist die Variante,
     * bei der eine Seite die Anschrift der anderen erfaehrt — nicht ueber diese
     * Plattform, aber im Ergebnis doch. Die Oberflaeche warnt an dieser Stelle
     * ausdruecklich; gespeichert wird auch hier nur die Art, nie eine Adresse.
     */
    public const TREFFPUNKT_HAUSTUER = 'haustuer';

    /**
     * Die feste Liste. KEIN FREITEXT.
     *
     * Ein Eingabefeld "Treffpunkt" waere binnen Wochen ein Adressfeld —
     * derselbe Trick, mit dem angebote.uebergabe_region schon heute eine
     * Anschrift strukturell verhindert. Wer hier einen Wert ergaenzt, braucht
     * Texte unter 'termin.treffpunkt.<wert>' und
     * 'termin.treffpunkt_erklaerung.<wert>'; tests/TermineTest.php haelt das
     * fest, bevor jemand '[[termin.treffpunkt.x]]' im Formular liest.
     *
     * @var list<string>
     */
    public const TREFFPUNKTARTEN = [
        self::TREFFPUNKT_OEFFENTLICH,
        self::TREFFPUNKT_BAHNHOF,
        self::TREFFPUNKT_CAFE,
        self::TREFFPUNKT_PAKETSHOP,
        self::TREFFPUNKT_HAUSTUER,
    ];

    // --- Zustaende ----------------------------------------------------------

    public const STATUS_VORGESCHLAGEN = 'vorgeschlagen';
    public const STATUS_ANGENOMMEN = 'angenommen';
    public const STATUS_UEBERGEBEN = 'uebergeben';
    public const STATUS_ABGELEHNT = 'abgelehnt';
    public const STATUS_VERFALLEN = 'verfallen';

    /**
     * Der Zustandsautomat als Matrix — nach dem Vorbild von
     * Angebote::UEBERGAENGE und Bestellzustand::erlaubteFolgen().
     *
     * Drei Endzustaende und kein Rueckweg. Das ist die Zusicherung 5 aus dem
     * Klassenkopf in einer Tabelle: Aus 'abgelehnt' fuehrt nichts zurueck nach
     * 'vorgeschlagen', aus 'uebergeben' fuehrt gar nichts mehr. Ein neuer
     * Anlauf ist eine neue Zeile.
     *
     * 'verfallen' ist aus beiden lebenden Zustaenden erreichbar, aber mit
     * verschiedenen Fristen (self::VERFALLSFRISTEN): Ein unbeantworteter
     * Vorschlag ist mit dem Zeitpunkt selbst erledigt, eine angenommene
     * Verabredung erst, wenn auch das Quittierfenster verstrichen ist.
     *
     * Die Tabelle sagt WELCHER Wechsel zulaessig ist, nicht WER ihn ausloesen
     * darf. Das steht je Methode: annehmen() und ablehnen() nur das Gegenueber,
     * quittieren() beide, verfallenLassen() niemand — es ist ein Fristablauf.
     *
     * @var array<string,list<string>>
     */
    public const UEBERGAENGE = [
        self::STATUS_VORGESCHLAGEN => [
            self::STATUS_ANGENOMMEN,
            self::STATUS_ABGELEHNT,
            self::STATUS_VERFALLEN,
        ],
        self::STATUS_ANGENOMMEN => [
            self::STATUS_UEBERGEBEN,
            self::STATUS_VERFALLEN,
        ],
        self::STATUS_UEBERGEBEN => [],
        self::STATUS_ABGELEHNT => [],
        self::STATUS_VERFALLEN => [],
    ];

    /**
     * Fristen des Verfalls, in Sekunden nach dem Zeitpunkt des Termins.
     *
     * Nur Zustaende, aus denen die Matrix oben ueberhaupt nach 'verfallen'
     * fuehrt, duerfen hier stehen — verfallenLassen() prueft das noch einmal,
     * damit ein Eingriff in die Matrix den Verfallslauf stoppt statt ihm zu
     * widersprechen.
     *
     * @var array<string,int>
     */
    private const VERFALLSFRISTEN = [
        // Ein Vorschlag, den niemand beantwortet hat, ist mit dem Zeitpunkt
        // selbst tot. Ihn danach noch annehmen zu koennen waere sinnlos:
        // annehmen() weist einen vergangenen Zeitpunkt ohnehin ab.
        self::STATUS_VORGESCHLAGEN => 0,
        // Eine angenommene Verabredung lebt weiter, bis das Quittierfenster
        // verstrichen ist. Sonst verloere die Uebergabe ihre Bestaetigung,
        // waehrend die beiden noch beieinanderstehen.
        self::STATUS_ANGENOMMEN => self::QUITTIERFENSTER_STUNDEN * 3600,
    ];

    // --- Grenzen ------------------------------------------------------------

    /**
     * Obergrenze der Region in Zeichen.
     *
     * Wortgleich mit Angebote::REGION_MAXLAENGE und aus demselben Grund: 40
     * Zeichen reichen fuer "Raum Muenchen" und sind zu knapp fuer eine
     * Anschrift. Die Spalte ist genauso breit — wer die Zahl hier erhoeht, ohne
     * die Migration anzufassen, bekommt einen abgeschnittenen Wert.
     */
    public const REGION_MAXLAENGE = 40;

    /**
     * Obergrenze der Ablehnungsbegruendung in Zeichen.
     *
     * Die Begruendung ist die EINZIGE Stelle dieser Klasse, an der ein Mensch
     * freien Text schreibt. Sie ist absichtlich kurz: 200 Zeichen tragen "Passt
     * mir zeitlich nicht" und tragen keine Verabredung am Freitextfeld vorbei.
     */
    public const GRUND_MAXLAENGE = 200;

    /**
     * Wie weit ein Termin hoechstens in der Zukunft liegen darf, in Tagen.
     *
     * Die Obergrenze ist keine Schikane. Ein Vorschlag fuer "in drei Jahren"
     * ist kein Termin, sondern eine Zeile, die drei Jahre lang im Chatfenster
     * steht und niemandem gehoert — und ein bequemer Weg, das Kontingent an
     * offenen Vorschlaegen dauerhaft zu belegen.
     */
    public const VORLAUF_MAX_TAGE = 90;

    /**
     * Wie lange nach dem Zeitpunkt noch quittiert werden kann, in Stunden.
     *
     * Grosszuegig: Wer abends uebergibt, quittiert vielleicht erst am naechsten
     * Morgen, und die zweite Seite noch einen Tag spaeter. Drei Tage sind kurz
     * genug, dass eine Verabredung nicht ewig offen steht, und lang genug, dass
     * niemand seine Bestaetigung wegen eines leeren Akkus verliert.
     */
    public const QUITTIERFENSTER_STUNDEN = 72;

    /**
     * Terminvorschlaege je Tag und Konto.
     *
     * Die Grenze gegen die Breite. Gezaehlt wird ueber vorschlagender_id, also
     * nur, was dieses Konto selbst vorgeschlagen hat — wer einen Vorschlag
     * BEKOMMT, verliert dadurch nichts. Sonst legte ein Angreifer ein beliebtes
     * Konto still, indem er ihm zehn Termine anbietet. Dieselbe Ueberlegung wie
     * bei Unterhaltungen::UNTERHALTUNGEN_JE_TAG.
     */
    public const VORSCHLAEGE_JE_TAG = 10;

    /**
     * Gleichzeitig offene eigene Vorschlaege je Unterhaltung.
     *
     * Die Grenze gegen die Tiefe. Drei unbeantwortete Vorschlaege sind ein
     * Angebot an Terminen; dreissig sind eine Belaestigung. Gezaehlt werden
     * wieder nur die EIGENEN — sonst koennte das Gegenueber das Kontingent
     * fuellen und damit jeden weiteren Vorschlag verhindern.
     */
    public const OFFENE_JE_UNTERHALTUNG = 3;

    /**
     * Termine je Abruf und Unterhaltung.
     *
     * Wie Unterhaltungen::PRO_ABRUF eine Grenze gegen die unbegrenzt wachsende
     * Seite. Sie greift im Alltag nie — die Drosselung oben laesst es gar nicht
     * so weit kommen.
     */
    public const PRO_ABRUF = 50;

    public function __construct(private readonly Database $db)
    {
    }

    // --- Schreibwege --------------------------------------------------------

    /**
     * Schlaegt einen Termin vor — und legt dabei IMMER eine neue Zeile an.
     *
     * Keine bestehende Zeile wird angefasst, auch nicht die eigene vom
     * Vormittag. Ein neuer Vorschlag ist ein neuer Vorgang; der alte laeuft
     * unabhaengig davon in 'abgelehnt' oder 'verfallen'. Erst dadurch bleibt
     * nachvollziehbar, was wann angeboten wurde.
     *
     * angebot_id kommt aus dem Kontext der Unterhaltung und NICHT aus einem
     * Parameter: Es gibt damit keinen Weg, einen Termin auf ein fremdes Angebot
     * zeigen zu lassen. bestellung_id bleibt NULL — diese Spur ist geldfrei.
     *
     * @param string $zeitpunkt     'Y-m-d H:i' oder 'Y-m-d\TH:i' (so liefert es
     *                              <input type="datetime-local">), in UTC wie
     *                              jeder Zeitstempel dieses Schemas
     * @param string $treffpunktArt Ein Wert aus self::TREFFPUNKTARTEN
     * @param string $region        Grobe Region, nie eine Anschrift
     *
     * @return int Kennung des Termins
     *
     * @throws TerminFehler 'unterhaltung_unbekannt', 'nicht_teilnehmer',
     *                      'gesperrt', 'zeitpunkt_ungueltig',
     *                      'zeitpunkt_vergangen', 'zeitpunkt_zu_fern',
     *                      'treffpunkt_unbekannt', 'region_fehlt',
     *                      'region_zu_lang', 'zu_schnell', 'zu_viele_offen'
     */
    public function vorschlagen(
        int $unterhaltungId,
        int $vorschlagenderId,
        string $zeitpunkt,
        string $treffpunktArt,
        string $region
    ): int {
        $unterhaltung = $this->gepruefteTeilnahme($unterhaltungId, $vorschlagenderId);

        // Bei JEDEM Vorschlag, nicht nur beim ersten. Eine vor der Sperre
        // eroeffnete Unterhaltung waere sonst ein Kanal, den die Sperre nicht
        // erreicht — und ein Terminvorschlag ist genau die Nachricht, die eine
        // Sperre verhindern soll.
        $this->pruefeKeineSperre($vorschlagenderId, $this->partnerVon($unterhaltung, $vorschlagenderId));

        $zeitpunkt = $this->gepruefterZeitpunkt($zeitpunkt);
        $treffpunktArt = $this->gepruefteTreffpunktart($treffpunktArt);
        $region = $this->gepruefteRegion($region);

        // Erst pruefen, dann drosseln: Ein abgewiesener Vorschlag soll kein
        // Kontingent kosten. Sonst bestraft ein Tippfehler in der Uhrzeit
        // zweimal.
        $this->pruefeDrosselung($vorschlagenderId);
        $this->pruefeOffeneVorschlaege($unterhaltungId, $vorschlagenderId);

        return $this->db->einfuegen('termine', [
            'unterhaltung_id' => $unterhaltungId,
            'vorschlagender_id' => $vorschlagenderId,
            // Aus dem Kontext, nie aus der Eingabe.
            'angebot_id' => $unterhaltung['angebot_id'] === null ? null : (int) $unterhaltung['angebot_id'],
            // Diese Spur ist geldfrei. Siehe Klassenkopf.
            'bestellung_id' => null,
            'zeitpunkt' => $zeitpunkt,
            'treffpunkt_art' => $treffpunktArt,
            'region' => $region,
            'status' => self::STATUS_VORGESCHLAGEN,
            'quittiert_a_am' => null,
            'quittiert_b_am' => null,
            'abgelehnt_am' => null,
            'grund' => null,
            'angelegt_am' => $this->jetzt(),
            'geaendert_am' => null,
        ]);
    }

    /**
     * Nimmt einen Vorschlag an — NUR das Gegenueber darf das.
     *
     * Die vorschlagende Person laeuft in 'eigener_vorschlag'. Ohne diese Regel
     * waere die Verabredung eine einseitige Behauptung, und der angenommene
     * Termin im Chat der anderen Person ein Druckmittel.
     *
     * Ein bereits verstrichener Zeitpunkt wird abgewiesen, auch wenn der
     * Verfallslauf noch nicht darueber gegangen ist: Eine Zusage zu einem
     * Termin, der vorbei ist, hat keinen Gegenstand.
     *
     * @throws TerminFehler 'termin_unbekannt', 'nicht_teilnehmer', 'gesperrt',
     *                      'eigener_vorschlag', 'nicht_offen',
     *                      'zeitpunkt_vergangen', 'status_unbekannt',
     *                      'unerlaubter_wechsel'
     */
    public function annehmen(int $terminId, int $benutzerId): void
    {
        [$termin, $unterhaltung] = $this->gepruefterTermin($terminId, $benutzerId);

        $this->pruefeGegenueber($termin, $benutzerId);
        $this->pruefeKeineSperre($benutzerId, $this->partnerVon($unterhaltung, $benutzerId));
        $this->pruefeOffen($termin);

        if (strtotime((string) $termin['zeitpunkt'] . ' UTC') <= time()) {
            throw new TerminFehler(
                'zeitpunkt_vergangen',
                'Termin ' . $terminId . ' liegt in der Vergangenheit.'
            );
        }

        $this->statusWechseln($termin, self::STATUS_ANGENOMMEN);
    }

    /**
     * Lehnt einen Vorschlag ab — NUR das Gegenueber darf das.
     *
     * Dieselbe Regel wie beim Annehmen, und aus demselben Grund: Ablehnen ist
     * die Antwort auf einen Vorschlag, und beantworten kann ihn nur, wer ihn
     * bekommen hat.
     *
     * Die Begruendung ist FREIWILLIG. Wer einen Termin nicht will, muss das
     * niemandem erklaeren — dieselbe Haltung wie bei der Sperre, die ebenfalls
     * ohne Grund auskommt (NachrichtenRouten::sperreSetzen).
     *
     * @throws TerminFehler 'termin_unbekannt', 'nicht_teilnehmer',
     *                      'eigener_vorschlag', 'nicht_offen',
     *                      'grund_zu_lang', 'status_unbekannt',
     *                      'unerlaubter_wechsel'
     */
    public function ablehnen(int $terminId, int $benutzerId, string $grund = ''): void
    {
        [$termin] = $this->gepruefterTermin($terminId, $benutzerId);

        $this->pruefeGegenueber($termin, $benutzerId);
        $this->pruefeOffen($termin);

        // Keine Sperrpruefung: Ablehnen ist der Weg AUS dem Vorgang heraus.
        // Wer gerade gesperrt hat, muss den offenen Vorschlag noch schliessen
        // koennen — sonst bliebe er bis zum Verfall im Fenster stehen.
        $grund = $this->gepruefterGrund($grund);
        $jetzt = $this->jetzt();

        $this->db->transaktion(function () use ($termin, $grund, $jetzt): void {
            $this->statusWechseln($termin, self::STATUS_ABGELEHNT, $jetzt);

            $this->db->ausfuehren(
                'UPDATE termine SET abgelehnt_am = :jetzt, grund = :grund WHERE id = :id',
                ['jetzt' => $jetzt, 'grund' => $grund, 'id' => (int) $termin['id']]
            );
        });
    }

    /**
     * Quittiert die Uebergabe — beide duerfen, jede nur fuer sich.
     *
     * DER KERN DIESES PAKETS. Drei Dinge stehen und fallen mit dieser Methode:
     *
     *  - Jede Seite schreibt ausschliesslich in ihre EIGENE Spalte. Welche das
     *    ist, entscheidet die Unterhaltung (teilnehmer_a_id -> quittiert_a_am),
     *    nicht der Vorschlag. Der Spaltenname stammt aus einer festen Zuordnung
     *    und nie aus einer Eingabe.
     *  - Eine einseitige Quittung genuegt NICHT. Der Termin bleibt auf
     *    'angenommen', bis beide Zeitstempel stehen.
     *  - Der Wechsel nach 'uebergeben' liegt im SELBEN Transaktionsblock wie
     *    die zweite Quittung. Sonst gaebe es ein Fenster, in dem beide
     *    quittiert haben und der Termin trotzdem noch offen ist — und genau
     *    dieses Fenster waere der Ansatzpunkt fuer jeden Streit.
     *
     * Vor dem Zeitpunkt geht nichts ('zu_frueh'). Eine Quittung im Voraus ist
     * keine Bestaetigung, sondern eine Vorabgabe; siehe Klassenkopf.
     *
     * @return string Der Zustand NACH der Quittung — 'angenommen', solange die
     *                zweite fehlt, sonst 'uebergeben'. Die Oberflaeche
     *                unterscheidet daran ihre Rueckmeldung.
     *
     * @throws TerminFehler 'termin_unbekannt', 'nicht_teilnehmer',
     *                      'nicht_angenommen', 'zu_frueh',
     *                      'bereits_quittiert', 'status_unbekannt',
     *                      'unerlaubter_wechsel'
     */
    public function quittieren(int $terminId, int $benutzerId): string
    {
        [$termin, $unterhaltung] = $this->gepruefterTermin($terminId, $benutzerId);

        if ((string) $termin['status'] !== self::STATUS_ANGENOMMEN) {
            throw new TerminFehler(
                'nicht_angenommen',
                'Termin ' . $terminId . ' steht auf "' . (string) $termin['status']
                . '" und nicht auf "' . self::STATUS_ANGENOMMEN . '".'
            );
        }

        if (strtotime((string) $termin['zeitpunkt'] . ' UTC') > time()) {
            throw new TerminFehler(
                'zu_frueh',
                'Termin ' . $terminId . ' liegt noch in der Zukunft.'
            );
        }

        // Feste Zuordnung, kein Wert aus der Anfrage: Der Spaltenname geht in
        // den SQL-Text ein und darf deshalb nur aus dieser Verzweigung stammen.
        $spalte = (int) $unterhaltung['teilnehmer_a_id'] === $benutzerId
            ? 'quittiert_a_am'
            : 'quittiert_b_am';

        if ($termin[$spalte] !== null) {
            throw new TerminFehler(
                'bereits_quittiert',
                'Konto ' . $benutzerId . ' hat Termin ' . $terminId . ' bereits quittiert.'
            );
        }

        $jetzt = $this->jetzt();

        return (string) $this->db->transaktion(function () use ($terminId, $spalte, $jetzt): string {
            $this->db->ausfuehren(
                'UPDATE termine SET ' . $spalte . ' = :jetzt, geaendert_am = :g'
                . ' WHERE id = :id AND ' . $spalte . ' IS NULL',
                ['jetzt' => $jetzt, 'g' => $jetzt, 'id' => $terminId]
            );

            // Innerhalb der Transaktion neu lesen und nicht auf die Zeile von
            // vorhin vertrauen: Zwischen Pruefung und Schreiben kann die andere
            // Seite quittiert haben. Genau dann muss DIESER Aufruf den Wechsel
            // ausloesen — sonst bliebe der Termin auf 'angenommen' stehen,
            // obwohl beide Zeitstempel da sind.
            $frisch = $this->terminZeile($terminId);

            if ($frisch === null) {
                throw new TerminFehler('termin_unbekannt', 'Termin ' . $terminId . ' ist verschwunden.');
            }

            if ($frisch['quittiert_a_am'] === null || $frisch['quittiert_b_am'] === null) {
                return (string) $frisch['status'];
            }

            $this->statusWechseln($frisch, self::STATUS_UEBERGEBEN, $jetzt);

            return self::STATUS_UEBERGEBEN;
        });
    }

    /**
     * Schliesst abgelaufene Termine einer Unterhaltung.
     *
     * Ein Fristablauf, kein Handeln einer Person — deshalb nimmt die Methode
     * keine Kennung eines Kontos entgegen und prueft keine Berechtigung. Sie
     * ist idempotent und darf bei jedem Aufruf des Chatfensters laufen.
     *
     * KEINE KORREKTHEIT HAENGT DARAN. annehmen() weist einen vergangenen
     * Zeitpunkt ohnehin ab und quittieren() verlangt 'angenommen' — der
     * Verfallslauf raeumt nur auf, damit die Liste nicht behauptet, ein
     * Vorschlag von gestern sei noch offen. Wer ihn vergisst, bekommt eine
     * ungenaue Anzeige und keinen falschen Zustand.
     *
     * Auf eine Unterhaltung begrenzt und nicht global: Diese Methode laeuft bei
     * jedem Oeffnen eines Chatfensters, und ein Lauf ueber die ganze Tabelle
     * waere dort die teuerste Abfrage der Seite. Es gibt in diesem Projekt
     * keinen Zeitplaner, der das anders erledigen koennte.
     *
     * @return int Zahl der geschlossenen Termine
     */
    public function verfallenLassen(int $unterhaltungId): int
    {
        $jetzt = time();
        $geschlossen = 0;

        foreach (self::VERFALLSFRISTEN as $von => $frist) {
            // Doppelte Sicherung: Wer den Uebergang aus der Matrix nimmt, soll
            // den Verfallslauf stoppen und nicht einen Wechsel bekommen, den
            // die Matrix verbietet.
            if (!in_array(self::STATUS_VERFALLEN, self::UEBERGAENGE[$von] ?? [], true)) {
                continue;
            }

            $anweisung = $this->db->ausfuehren(
                'UPDATE termine SET status = :neu, geaendert_am = :g'
                . ' WHERE unterhaltung_id = :u AND status = :alt AND zeitpunkt <= :grenze',
                [
                    'neu' => self::STATUS_VERFALLEN,
                    'g' => gmdate('Y-m-d H:i:s', $jetzt),
                    'u' => $unterhaltungId,
                    'alt' => $von,
                    'grenze' => gmdate('Y-m-d H:i:s', $jetzt - $frist),
                ]
            );

            $geschlossen += $anweisung->rowCount();
        }

        return $geschlossen;
    }

    // --- Lesewege -----------------------------------------------------------

    /**
     * Die Termine einer Unterhaltung, aelteste zuerst.
     *
     * Liefert je Zeile mit, WAS DIESE PERSON DARF. Die Oberflaeche entscheidet
     * das nicht selbst: Sonst stuenden die Regeln aus dem Klassenkopf ein
     * zweites Mal in einer Vorlage, und die beiden Fassungen liefen
     * auseinander. Die Vorlage zeigt einen Knopf, wenn das Merkmal wahr ist —
     * die Fachklasse weist ihn trotzdem noch einmal ab, wenn er doch gedrueckt
     * wird.
     *
     * @return list<array<string,mixed>>
     *
     * @throws TerminFehler 'unterhaltung_unbekannt', 'nicht_teilnehmer'
     */
    public function fuerUnterhaltung(int $unterhaltungId, int $benutzerId): array
    {
        $unterhaltung = $this->gepruefteTeilnahme($unterhaltungId, $benutzerId);
        $binA = (int) $unterhaltung['teilnehmer_a_id'] === $benutzerId;

        // Absteigend holen, aufsteigend zeigen — wie
        // Unterhaltungen::nachrichten(). Ein 'ORDER BY id ASC LIMIT 50' waere
        // die naheliegende und falsche Fassung: Sie schnitte in einem langen
        // Gespraech ausgerechnet die NEUESTEN Termine ab, also den offenen
        // Vorschlag, um den es gerade geht.
        $zeilen = array_reverse($this->db->alle(
            'SELECT id, unterhaltung_id, vorschlagender_id, angebot_id, bestellung_id,
                    zeitpunkt, treffpunkt_art, region, status,
                    quittiert_a_am, quittiert_b_am, abgelehnt_am, grund,
                    angelegt_am, geaendert_am
               FROM termine
              WHERE unterhaltung_id = :u
              ORDER BY id DESC
              LIMIT ' . self::PRO_ABRUF,
            ['u' => $unterhaltungId]
        ));

        $jetzt = time();
        $ergebnis = [];

        foreach ($zeilen as $zeile) {
            $status = (string) $zeile['status'];
            $eigener = (int) $zeile['vorschlagender_id'] === $benutzerId;
            $erreicht = strtotime((string) $zeile['zeitpunkt'] . ' UTC') <= $jetzt;

            $eigeneQuittung = $binA ? $zeile['quittiert_a_am'] : $zeile['quittiert_b_am'];
            $fremdeQuittung = $binA ? $zeile['quittiert_b_am'] : $zeile['quittiert_a_am'];

            $ergebnis[] = [
                'id' => (int) $zeile['id'],
                'zeitpunkt' => (string) $zeile['zeitpunkt'],
                'treffpunkt_art' => (string) $zeile['treffpunkt_art'],
                'region' => (string) $zeile['region'],
                'status' => $status,
                'grund' => $zeile['grund'],
                'angelegt_am' => $zeile['angelegt_am'],
                'angebot_id' => $zeile['angebot_id'] === null ? null : (int) $zeile['angebot_id'],
                'eigener_vorschlag' => $eigener,
                'zeitpunkt_erreicht' => $erreicht,
                'eigene_quittung' => $eigeneQuittung,
                'fremde_quittung' => $fremdeQuittung,
                // Annehmen und Ablehnen: nur das Gegenueber, nur solange offen.
                // Annehmen zusaetzlich nur, solange der Zeitpunkt noch kommt.
                'darf_annehmen' => $status === self::STATUS_VORGESCHLAGEN && !$eigener && !$erreicht,
                'darf_ablehnen' => $status === self::STATUS_VORGESCHLAGEN && !$eigener,
                // Quittieren: beide, aber erst ab dem Zeitpunkt und je nur
                // einmal.
                'darf_quittieren' => $status === self::STATUS_ANGENOMMEN
                    && $erreicht
                    && $eigeneQuittung === null,
            ];
        }

        return $ergebnis;
    }

    /**
     * Zu welcher Unterhaltung ein Termin gehoert — oder null.
     *
     * Der Weg zurueck fuer die Oberflaeche: Nach einer Handlung muss sie in das
     * richtige Chatfenster weiterleiten, und die Kennung dafuer steht nur an
     * der Zeile.
     *
     * Unbekannt und fremd ergeben dasselbe null, genau wie bei
     * Unterhaltungen::laden(). Ein eigener Fehler fuer "gibt es, aber nicht
     * deiner" waere die Auskunft, dass eine bestimmte Person zu einer
     * bestimmten Zeit verabredet ist — die empfindlichste Auskunft, die diese
     * Plattform ueberhaupt hat.
     */
    public function unterhaltungVon(int $terminId, int $benutzerId): ?int
    {
        $termin = $this->terminZeile($terminId);

        if ($termin === null) {
            return null;
        }

        $unterhaltung = $this->unterhaltungZeile((int) $termin['unterhaltung_id']);

        if ($unterhaltung === null || !$this->istTeilnehmer($unterhaltung, $benutzerId)) {
            return null;
        }

        return (int) $termin['unterhaltung_id'];
    }

    // --- intern -------------------------------------------------------------

    /**
     * Der EINZIGE Weg, an dem 'status' geschrieben wird — ausser dem
     * Verfallslauf, der seinerseits gegen dieselbe Matrix prueft.
     *
     * Nach dem Vorbild von Angebote::statusWechseln().
     *
     * @param array<string,mixed> $termin
     *
     * @throws TerminFehler 'status_unbekannt', 'unerlaubter_wechsel'
     */
    private function statusWechseln(array $termin, string $nach, ?string $jetzt = null): void
    {
        $von = (string) $termin['status'];
        $erlaubte = self::UEBERGAENGE[$von] ?? null;

        if ($erlaubte === null) {
            throw new TerminFehler(
                'status_unbekannt',
                'Unbekannter Status "' . $von . '" an Termin ' . (int) $termin['id'] . '.'
            );
        }

        if (!in_array($nach, $erlaubte, true)) {
            throw new TerminFehler(
                'unerlaubter_wechsel',
                'Wechsel von "' . $von . '" nach "' . $nach . '" ist nicht vorgesehen.'
            );
        }

        // Die WHERE-Bedingung nennt den Ausgangsstatus noch einmal: Zwei
        // gleichzeitige Anfragen kaemen sonst beide durch die Pruefung oben,
        // und die zweite ueberschriebe das Ergebnis der ersten.
        $this->db->ausfuehren(
            'UPDATE termine SET status = :neu, geaendert_am = :g WHERE id = :id AND status = :alt',
            [
                'neu' => $nach,
                'g' => $jetzt ?? $this->jetzt(),
                'id' => (int) $termin['id'],
                'alt' => $von,
            ]
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    private function terminZeile(int $terminId): ?array
    {
        return $this->db->eine(
            'SELECT id, unterhaltung_id, vorschlagender_id, angebot_id, bestellung_id,
                    zeitpunkt, treffpunkt_art, region, status,
                    quittiert_a_am, quittiert_b_am, abgelehnt_am, grund,
                    angelegt_am, geaendert_am
               FROM termine
              WHERE id = :id',
            ['id' => $terminId]
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    private function unterhaltungZeile(int $unterhaltungId): ?array
    {
        return $this->db->eine(
            'SELECT id, teilnehmer_a_id, teilnehmer_b_id, angebot_id
               FROM unterhaltungen
              WHERE id = :id',
            ['id' => $unterhaltungId]
        );
    }

    /**
     * Laedt den Termin samt seiner Unterhaltung und stellt sicher, dass das
     * Konto beteiligt ist.
     *
     * @return array{0:array<string,mixed>, 1:array<string,mixed>}
     *
     * @throws TerminFehler 'termin_unbekannt', 'nicht_teilnehmer'
     */
    private function gepruefterTermin(int $terminId, int $benutzerId): array
    {
        $termin = $this->terminZeile($terminId);

        if ($termin === null) {
            throw new TerminFehler('termin_unbekannt', 'Termin ' . $terminId . ' existiert nicht.');
        }

        $unterhaltung = $this->unterhaltungZeile((int) $termin['unterhaltung_id']);

        if ($unterhaltung === null || !$this->istTeilnehmer($unterhaltung, $benutzerId)) {
            throw new TerminFehler(
                'nicht_teilnehmer',
                'Konto ' . $benutzerId . ' ist nicht an Termin ' . $terminId . ' beteiligt.'
            );
        }

        return [$termin, $unterhaltung];
    }

    /**
     * @return array<string,mixed>
     *
     * @throws TerminFehler 'unterhaltung_unbekannt', 'nicht_teilnehmer'
     */
    private function gepruefteTeilnahme(int $unterhaltungId, int $benutzerId): array
    {
        $unterhaltung = $this->unterhaltungZeile($unterhaltungId);

        if ($unterhaltung === null) {
            throw new TerminFehler(
                'unterhaltung_unbekannt',
                'Unterhaltung ' . $unterhaltungId . ' existiert nicht.'
            );
        }

        if (!$this->istTeilnehmer($unterhaltung, $benutzerId)) {
            throw new TerminFehler(
                'nicht_teilnehmer',
                'Konto ' . $benutzerId . ' ist nicht an Unterhaltung ' . $unterhaltungId . ' beteiligt.'
            );
        }

        return $unterhaltung;
    }

    /** @param array<string,mixed> $unterhaltung */
    private function istTeilnehmer(array $unterhaltung, int $benutzerId): bool
    {
        return (int) $unterhaltung['teilnehmer_a_id'] === $benutzerId
            || (int) $unterhaltung['teilnehmer_b_id'] === $benutzerId;
    }

    /** @param array<string,mixed> $unterhaltung */
    private function partnerVon(array $unterhaltung, int $benutzerId): int
    {
        $a = (int) $unterhaltung['teilnehmer_a_id'];
        $b = (int) $unterhaltung['teilnehmer_b_id'];

        return $a === $benutzerId ? $b : $a;
    }

    /**
     * Nur das Gegenueber, nie die vorschlagende Person.
     *
     * @param array<string,mixed> $termin
     *
     * @throws TerminFehler 'eigener_vorschlag'
     */
    private function pruefeGegenueber(array $termin, int $benutzerId): void
    {
        if ((int) $termin['vorschlagender_id'] === $benutzerId) {
            throw new TerminFehler(
                'eigener_vorschlag',
                'Konto ' . $benutzerId . ' hat Termin ' . (int) $termin['id'] . ' selbst vorgeschlagen.'
            );
        }
    }

    /**
     * @param array<string,mixed> $termin
     *
     * @throws TerminFehler 'nicht_offen'
     */
    private function pruefeOffen(array $termin): void
    {
        if ((string) $termin['status'] !== self::STATUS_VORGESCHLAGEN) {
            throw new TerminFehler(
                'nicht_offen',
                'Termin ' . (int) $termin['id'] . ' steht auf "' . (string) $termin['status']
                . '" und ist nicht mehr offen.'
            );
        }
    }

    /**
     * Die Sperrliste, in BEIDEN Richtungen — wortgleich mit
     * Unterhaltungen::pruefeKeineSperre().
     *
     * Bewusst kopiert statt geerbt: Unterhaltungen ist final und hat die
     * Pruefung privat. Ein gemeinsamer Ort waere die noch fehlende Klasse
     * app/Domain/Trust/Sperrliste.php, die der Klassenkopf von
     * NachrichtenRouten bereits vorgesehen hat. Bis es sie gibt, ist eine
     * zweite, identische Pruefung besser als eine fehlende.
     *
     * @throws TerminFehler 'gesperrt'
     */
    private function pruefeKeineSperre(int $einer, int $anderer): void
    {
        $anzahl = (int) $this->db->wert(
            'SELECT COUNT(*) FROM sperren
              WHERE (benutzer_id = :a1 AND gesperrter_id = :b1)
                 OR (benutzer_id = :b2 AND gesperrter_id = :a2)',
            ['a1' => $einer, 'b1' => $anderer, 'b2' => $anderer, 'a2' => $einer]
        );

        if ($anzahl > 0) {
            throw new TerminFehler(
                'gesperrt',
                'Zwischen Konto ' . $einer . ' und Konto ' . $anderer . ' besteht eine Sperre.'
            );
        }
    }

    /**
     * Prueft und normalisiert den Zeitpunkt.
     *
     * ALLES IN UTC. Das gesamte Schema fuehrt Zeitstempel in UTC im Format
     * 'Y-m-d H:i:s', und diese Klasse weicht davon nicht ab — auch nicht fuer
     * die Eingabe. Das 'T' aus <input type="datetime-local"> wird ersetzt,
     * Sekunden sind erlaubt und werden auf null gesetzt, wenn sie fehlen.
     *
     * Die Rueckrechnung ('2026-02-31' wird zu '2026-03-03') faengt Daten ab,
     * die es nicht gibt: strtotime rechnet sie stillschweigend weiter, und
     * ohne den Vergleich stuende im Chat ein anderer Tag als der eingegebene.
     *
     * @throws TerminFehler 'zeitpunkt_ungueltig', 'zeitpunkt_vergangen',
     *                      'zeitpunkt_zu_fern'
     */
    private function gepruefterZeitpunkt(string $roh): string
    {
        $roh = str_replace('T', ' ', trim($roh));

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $roh) !== 1) {
            throw new TerminFehler('zeitpunkt_ungueltig', 'Zeitpunkt "' . $roh . '" ist nicht lesbar.');
        }

        // Die Zeitzone wird ausdruecklich angehaengt, weil strtotime sonst die
        // lokale des Servers annimmt — dieselbe Falle wie in
        // Verwaltung::vorTagen().
        $stempel = strtotime($roh . ' UTC');

        if ($stempel === false) {
            throw new TerminFehler('zeitpunkt_ungueltig', 'Zeitpunkt "' . $roh . '" ist nicht lesbar.');
        }

        $normal = gmdate('Y-m-d H:i:s', $stempel);

        if (substr($normal, 0, 16) !== substr($roh, 0, 16)) {
            throw new TerminFehler('zeitpunkt_ungueltig', 'Zeitpunkt "' . $roh . '" gibt es nicht.');
        }

        $jetzt = time();

        if ($stempel <= $jetzt) {
            throw new TerminFehler('zeitpunkt_vergangen', 'Zeitpunkt "' . $roh . '" liegt nicht in der Zukunft.');
        }

        if ($stempel > $jetzt + self::VORLAUF_MAX_TAGE * 86400) {
            throw new TerminFehler(
                'zeitpunkt_zu_fern',
                'Zeitpunkt "' . $roh . '" liegt weiter als ' . self::VORLAUF_MAX_TAGE . ' Tage voraus.'
            );
        }

        return $normal;
    }

    /**
     * @throws TerminFehler 'treffpunkt_unbekannt'
     */
    private function gepruefteTreffpunktart(string $art): string
    {
        $art = trim($art);

        if (!in_array($art, self::TREFFPUNKTARTEN, true)) {
            // Der eingegebene Wert steht bewusst NICHT in der Meldung: Waere
            // hier ein Freitext moeglich, landete er ueber die Fehlermeldung
            // doch im Protokoll. Die feste Liste ist der ganze Zweck.
            throw new TerminFehler('treffpunkt_unbekannt', 'Unbekannte Treffpunktart.');
        }

        return $art;
    }

    /**
     * Grobe Region, nie eine Adresse — wortgleich zu Angebote::gepruefteRegion,
     * mit einem Unterschied: Sie ist hier PFLICHT.
     *
     * Bei einem Angebot ist die Region eine Zusatzangabe. Bei einer Verabredung
     * ist sie die einzige Orientierung, die die andere Person ueberhaupt hat,
     * bevor sie zusagt — ein Termin ohne jede Ortsangabe waere eine Zusage ins
     * Blaue. Deshalb 'region_fehlt' statt eines stillen null.
     *
     * @throws TerminFehler 'region_fehlt', 'region_zu_lang'
     */
    private function gepruefteRegion(string $region): string
    {
        $region = trim($region);

        if ($region === '') {
            throw new TerminFehler('region_fehlt');
        }

        // 40 Zeichen reichen fuer "Raum Muenchen" und sind zu knapp fuer eine
        // Anschrift. Genau darum geht es.
        if (mb_strlen($region) > self::REGION_MAXLAENGE) {
            throw new TerminFehler('region_zu_lang');
        }

        return $region;
    }

    /**
     * @throws TerminFehler 'grund_zu_lang'
     */
    private function gepruefterGrund(string $grund): ?string
    {
        $grund = trim($grund);

        if ($grund === '') {
            return null;
        }

        if (mb_strlen($grund) > self::GRUND_MAXLAENGE) {
            throw new TerminFehler('grund_zu_lang');
        }

        return $grund;
    }

    /**
     * Vorschlaege je Tag und Konto.
     *
     * Der Zeitpunkt wird in PHP gerechnet und als Zeichenkette verglichen —
     * kein DATE_SUB und kein datetime('now', '-1 day'), weil beide je nur eine
     * der beiden Datenbanken kennen. Alle Zeitstempel im Schema stehen in UTC
     * im Format 'Y-m-d H:i:s'; in diesem Format ist der lexikografische
     * Vergleich, den SQLite auf TEXT anwendet, mit dem zeitlichen identisch.
     *
     * @throws TerminFehler 'zu_schnell'
     */
    private function pruefeDrosselung(int $vorschlagenderId): void
    {
        $grenze = gmdate('Y-m-d H:i:s', time() - 86400);

        $anzahl = (int) $this->db->wert(
            'SELECT COUNT(*) FROM termine WHERE vorschlagender_id = :v AND angelegt_am > :grenze',
            ['v' => $vorschlagenderId, 'grenze' => $grenze]
        );

        if ($anzahl >= self::VORSCHLAEGE_JE_TAG) {
            throw new TerminFehler(
                'zu_schnell',
                'Konto ' . $vorschlagenderId . ' hat in den letzten 24 Stunden bereits '
                . $anzahl . ' Termine vorgeschlagen.'
            );
        }
    }

    /**
     * Gleichzeitig offene EIGENE Vorschlaege je Unterhaltung.
     *
     * @throws TerminFehler 'zu_viele_offen'
     */
    private function pruefeOffeneVorschlaege(int $unterhaltungId, int $vorschlagenderId): void
    {
        $anzahl = (int) $this->db->wert(
            'SELECT COUNT(*) FROM termine
              WHERE unterhaltung_id = :u AND vorschlagender_id = :v AND status = :s',
            ['u' => $unterhaltungId, 'v' => $vorschlagenderId, 's' => self::STATUS_VORGESCHLAGEN]
        );

        if ($anzahl >= self::OFFENE_JE_UNTERHALTUNG) {
            throw new TerminFehler(
                'zu_viele_offen',
                'Konto ' . $vorschlagenderId . ' hat in Unterhaltung ' . $unterhaltungId
                . ' bereits ' . $anzahl . ' offene Vorschlaege.'
            );
        }
    }

    private function jetzt(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
