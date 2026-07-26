<?php

declare(strict_types=1);

namespace MeinSlip\Http;

use MeinSlip\Core\Database;
use MeinSlip\Core\Env;
use MeinSlip\Core\Lang;
use MeinSlip\Core\Request;
use MeinSlip\Core\Response;
use MeinSlip\Core\Router;
use MeinSlip\Core\View;
use MeinSlip\Domain\Account\Konten;
use MeinSlip\Domain\Account\Sitzungen;
use MeinSlip\Domain\Catalog\AngebotFehler;
use MeinSlip\Domain\Catalog\Angebote;
use MeinSlip\Domain\Ledger\Hauptbuch;
use MeinSlip\Domain\Order\BestellFehler;
use MeinSlip\Domain\Order\Bestellungen;
use MeinSlip\Domain\Order\Bestellzustand;
use MeinSlip\Domain\Order\Preisrechner;

/**
 * Routen des Warenmarktplatzes: Katalog, Angebotsseite mit Konfigurator,
 * Verkaufen und Bestellen.
 *
 * Getrennt von app/Http/Routen.php, weil der Marktplatz eine eigene Strecke
 * mit eigenem Zugriffsschutz ist. Eingehaengt wird die Klasse im
 * Einstiegspunkt neben Routen.
 *
 * Drei Dinge setzt diese Schicht durch, die die Fachklassen allein nicht
 * leisten koennen:
 *
 *  1. DER KONFIGURATOR ERZWINGT DIE SPEZIFIKATION. Bestellungen::anlegen()
 *     weist eine Position ohne Spezifikation ab. Die Oberflaeche darf den
 *     Menschen aber nicht erst dort auflaufen lassen: Pflichtoptionen tragen
 *     'required', und diese Klasse prueft ein zweites Mal serverseitig —
 *     'required' ist eine Bequemlichkeit, keine Sicherung.
 *
 *  2. DIE UNTERRICHTUNG UEBER DEN WIDERRUFSAUSSCHLUSS STEHT VOR DEM KNOPF.
 *     § 312d Abs. 1 BGB i. V. m. Art. 246a § 1 Abs. 3 Nr. 1 EGBGB verlangt
 *     die Information VOR Abgabe der Vertragserklaerung. Deshalb liegt der
 *     Hinweis im Formular und wird zusaetzlich bestaetigt.
 *
 *  3. JEDE FAEHIGKEIT WIRD SERVERSEITIG GEPRUEFT. Ein ausgeblendeter Knopf
 *     ist kein Zugriffsschutz.
 *
 *  4. DIE EINZELSEITE IST NICHT LAXER ALS DER KATALOG. Angebote::fuerKatalog()
 *     zeigt nur freigegebene Angebote aktiver, verkaufsfaehiger Konten. Genau
 *     dieselbe Bedingung entscheidet in angebotSeite() darueber, ob es das
 *     Angebot fuer eine Fremde ueberhaupt gibt — sonst waere die gesamte
 *     Freigabestrecke ueber /angebot/{id} zu umgehen.
 *
 *  5. BEZAHLT WIRD NUR, WAS VORHER AUF DEM BILDSCHIRM STAND. Zwischen dem
 *     Aufbau der Angebotsseite und dem Absenden kann die Verkaeuferin Preis
 *     oder Aufpreise geaendert haben. § 312j Abs. 2 BGB verlangt den
 *     Gesamtpreis unmittelbar vor Abgabe der Bestellung, also wird die
 *     Preisgrundlage beim Anzeigen festgehalten und beim Absenden verglichen.
 *
 *  6. SICHTBAR UND BESTELLBAR SIND ZWEI FRAGEN. Seit dem Modellwechsel gibt es
 *     kein Freigabetor mehr: Wer veroeffentlicht, steht sofort im Katalog. Ob
 *     dort auch bestellt werden kann, entscheiden zwei voneinander unabhaengige
 *     Dinge — der Schalter BESTELLVORGANG_AKTIV (aufsichtsrechtlich gesperrt,
 *     § 1 Abs. 1 S. 2 Nr. 1 KWG) und Angebote::istBestellbar() (§ 312g Abs. 2
 *     Nr. 1 BGB). Ist eines von beiden nicht erfuellt, ist "Nachricht
 *     schreiben" die Hauptaktion und der Konfigurator erscheint gar nicht.
 *     Die Gegenprobe steht in bestellen(): der Schalter wird dort noch einmal
 *     serverseitig gelesen, nicht nur beim Rendern.
 */
final class MarktRouten
{
    /** Angebote je Katalogseite. */
    private const PRO_SEITE = 24;

    /**
     * Cookie, das die zuletzt gezeigte Preisgrundlage festhaelt.
     *
     * Traegt '<angebotId>:<fingerabdruck>'. Siehe preisstandMerken() fuer die
     * Begruendung, warum das ein Cookie ist und kein Formularfeld.
     */
    private const PREISSTAND_COOKIE = 'ms_preisstand';

    /**
     * Rueckmeldungen, die als Abfrageparameter durch eine Weiterleitung
     * getragen werden duerfen. Nur diese Liste — sonst koennte eine
     * praeparierte Adresse beliebige Schluessel in die Seite schreiben.
     */
    private const ERFOLGE = [
        'angelegt',
        'gespeichert',
        'option_gespeichert',
        'option_entfernt',
        'veroeffentlicht',
        // 'eingereicht' bleibt: Die Route /verkaufen/{id}/einreichen ist als
        // Altpfad erhalten und setzt den Schluessel weiterhin. Aus der
        // Oberflaeche ist sie verschwunden, erreichbar ist sie noch.
        'eingereicht',
        'pausiert',
        'fortgesetzt',
        'entfernt',
    ];

    /** Fehlerschluessel aus AngebotFehler::schluessel() plus zwei eigene. */
    private const ANGEBOTSFEHLER = [
        'keine_verkaufsfaehigkeit',
        'titel_fehlt',
        'titel_zu_lang',
        'grundpreis_ungueltig',
        'kategorie_unbekannt',
        'bearbeitungstage_ungueltig',
        'uebergabe_region_zu_lang',
        'waehrung_ungueltig',
        'angebot_unbekannt',
        // 'nicht_der_eigentuemer' fehlt hier absichtlich: Keine Route dieser
        // Klasse setzt den Schluessel mehr (siehe ohneEigentuemerhinweis()).
        // Bliebe er in der Liste, liesse sich der Text 'Dieses Angebot gehoert
        // dir nicht.' ueber eine handgebaute Adresse doch noch hervorlocken —
        // und damit die Zusicherung aus bearbeitenFormular() aushebeln.
        'nicht_bearbeitbar',
        'feld_unbekannt',
        'option_schluessel_ungueltig',
        'option_bezeichnung_ungueltig',
        'option_art_unbekannt',
        'option_aufpreis_ungueltig',
        'option_unbekannt',
        'keine_spezifikation',
        // Wirft zurPruefungEinreichen(), wenn als Spezifikation nur Optionen
        // der Art 'auswahl' vorliegen. Der Schluessel fehlte hier bisher, und
        // weil ausListe() alles Unbekannte auf null zieht, blieb der Bildschirm
        // nach dem Fehlversuch stumm. Nach dem Torabbau traegt derselbe Text
        // den Hinweis auf /verkaufen/{id}, welche Optionsart zum Bestellen
        // fehlt — siehe bearbeitenFormular().
        'spezifikation_braucht_eingabe',
        'ablehnungsgrund_fehlt',
        'eigenpruefung_unzulaessig',
        'statuswechsel_unzulaessig',
        'status_unbekannt',
        'preis_unlesbar',
        'allgemein',
    ];

    /**
     * Zwischenspeicher fuer die Dauer einer Anfrage.
     *
     * Beide Merker sind noetig, weil null in beiden Faellen ein gueltiges
     * Ergebnis ist: keine Verbindung heisst Stoerung, keine Sitzung heisst
     * abgemeldet. Ohne die Merker wuerde beides bei jedem Aufruf erneut
     * versucht.
     */
    private ?Database $verbindung = null;

    private bool $verbindungVersucht = false;

    /** @var array<string,mixed>|null */
    private ?array $sitzungZwischen = null;

    private bool $sitzungGeladen = false;

    public function __construct(
        // Gleiche Bauform wie Routen: Wurzel und Ansicht. Der Marktplatz
        // braucht die Wurzel heute nicht — sie bleibt trotzdem im
        // Konstruktor, damit der Einstiegspunkt beide Routenklassen
        // gleich verdrahtet und ein spaeterer Dateizugriff (Bilder eines
        // Angebots) nicht die Signatur bricht.
        private readonly string $wurzel,
        private readonly View $ansicht,
    ) {
    }

    public function registrieren(Router $router): void
    {
        $this->katalog($router);
        $this->verkaufen($router);
        $this->kaufen($router);
    }

    // --- Katalog und Angebotsseite ---------------------------------------

    private function katalog(Router $router): void
    {
        /*
         * ZWEI ROUTEN STATT EINER, UND KEIN ABFRAGEPARAMETER.
         *
         * Kategoriepfade tragen Schraegstriche ('waesche/slips'), der Router
         * wandelt {name} aber in ([^/]+) — ein Schraegstrich passt nicht.
         * Router.php gehoert einer anderen Zustaendigkeit, wird also nicht
         * angefasst.
         *
         * Gewaehlt ist die Aufteilung in eine Route je Ebene, nicht
         * '/kategorie?pfad=waesche/slips'. Grund: Die Kategorieschluessel sind
         * ausdruecklich Teil der Adresse und aendern sich nie
         * (database/migrations/007_kategorien.php) — Suche ist der einzige
         * Wachstumskanal, der dieser Plattform offensteht, und eine
         * sprechende Adresse ist dafuer die halbe Miete. Ein Abfrageparameter
         * waere zwar bequemer, verschenkt das aber.
         *
         * Der Baum ist heute zwei Ebenen tief. Eine dritte Ebene braucht hier
         * eine dritte Route — bewusst sichtbar gemacht, statt sie hinter einem
         * Fangmuster verschwinden zu lassen.
         */
        $router->get('/kategorie/{ebene1}', fn (Request $a, array $p): Response => $this->kategorieSeite(
            $a,
            (string) ($p['ebene1'] ?? '')
        ));

        $router->get('/kategorie/{ebene1}/{ebene2}', fn (Request $a, array $p): Response => $this->kategorieSeite(
            $a,
            (string) ($p['ebene1'] ?? '') . '/' . (string) ($p['ebene2'] ?? '')
        ));

        $router->get('/angebot/{id}', fn (Request $a, array $p): Response => $this->angebotSeite(
            $a,
            (int) ($p['id'] ?? 0)
        ));
    }

    private function kategorieSeite(Request $anfrage, string $pfad): Response
    {
        $daten = [
            'aktiv' => '/entdecken',
            'kategorie' => null,
            'angebote' => [],
            'anzahl' => 0,
            'seite' => 1,
            'seiten' => 1,
            'pfad' => $pfad,
            'gestoert' => false,
        ];

        $db = $this->datenbank();

        if ($db === null) {
            $daten['gestoert'] = true;

            return $this->rendern($anfrage, 'markt.kategorie', t('entdecken.titel'), $daten);
        }

        $kategorie = $db->eine(
            'SELECT * FROM kategorien WHERE pfad = :p AND aktiv = 1',
            ['p' => $pfad]
        );

        if ($kategorie === null) {
            return $this->rendern($anfrage, 'markt.kategorie', t('markt.kategorie_unbekannt_titel'), $daten);
        }

        $angebote = new Angebote($db);
        $kategorieId = (int) $kategorie['id'];
        $anzahl = $angebote->anzahlImKatalog($kategorieId);
        $seiten = max(1, (int) ceil($anzahl / self::PRO_SEITE));
        $seite = min($seiten, max(1, $anfrage->ganzzahl('seite', 1) ?? 1));

        $daten['kategorie'] = $kategorie;
        $daten['angebote'] = $angebote->fuerKatalog($kategorieId, $seite, self::PRO_SEITE);
        $daten['anzahl'] = $anzahl;
        $daten['seite'] = $seite;
        $daten['seiten'] = $seiten;

        return $this->rendern(
            $anfrage,
            'markt.kategorie',
            t('kategorie.' . (string) $kategorie['schluessel']) . ' — ' . t('allgemein.marke'),
            $daten
        );
    }

    /**
     * Die Angebotsseite mit dem Konfigurator.
     *
     * Wird auch nach einer gescheiterten Bestellung gerendert — dann mit
     * Fehlerschluessel und den bereits getroffenen Angaben, damit niemand
     * alles noch einmal eintippen muss.
     *
     * @param array<string,string> $eingaben
     */
    private function angebotSeite(
        Request $anfrage,
        int $angebotId,
        ?string $fehler = null,
        array $eingaben = []
    ): Response {
        $sitzung = $this->sitzung($anfrage);

        $daten = [
            'aktiv' => '/entdecken',
            'angebot' => null,
            'optionen' => [],
            'verkaeufer' => null,
            'kategorie' => null,
            'darfKaufen' => false,
            'angemeldet' => $sitzung !== null,
            'fehler' => $fehler,
            'eingaben' => $eingaben,
            'gestoert' => false,
            // Solange der Bestellvorgang ruht, rendert die Vorlage den
            // Konfigurator gar nicht erst. Der Schalter wird hier gelesen und
            // nicht in der Vorlage, damit die Entscheidung an einer Stelle
            // faellt und nicht in jeder Verzweigung des HTML wiederholt wird.
            'bestellvorgangAktiv' => Env::bool('BESTELLVORGANG_AKTIV', false),
            'bestellbar' => false,
        ];

        $db = $this->datenbank();

        if ($db === null) {
            $daten['gestoert'] = true;

            return $this->rendern($anfrage, 'markt.angebot', t('markt.angebot_kicker'), $daten);
        }

        $angebote = new Angebote($db);
        $angebot = $angebote->laden($angebotId);

        if ($angebot === null) {
            return $this->rendern($anfrage, 'markt.angebot', t('markt.angebot_unbekannt_titel'), $daten);
        }

        $verkaeufer = $db->eine(
            'SELECT id, pseudonym, status FROM benutzer WHERE id = :id',
            ['id' => (int) $angebot['verkaeufer_id']]
        );

        $konten = new Konten($db);
        $verkaeuferId = (int) $angebot['verkaeufer_id'];

        // Oeffentlich ist ein Angebot unter genau den Bedingungen, unter denen
        // fuerKatalog() es auch listet: freigegeben, Konto aktiv, Faehigkeit
        // 'verkaufen' vorhanden. Alles andere — Entwurf, in Pruefung,
        // abgelehnt, pausiert, entfernt, gesperrtes Konto, entzogene
        // Verkaufsfaehigkeit — existiert fuer Fremde nicht.
        //
        // Vorher stand hier nur eine Herabstufung auf 'pausiert'. Die hat den
        // Bestellknopf verborgen, den Inhalt aber weiter ausgeliefert: Titel,
        // Beschreibung, Preis und Pseudonym eines ungeprueften oder gesperrten
        // Angebots waren ueber die fortlaufende Kennung /angebot/1..N komplett
        // abrufbar. Damit lief die ganze Freigabestrecke ins Leere.
        $oeffentlich = (string) $angebot['status'] === Angebote::STATUS_AKTIV
            && $verkaeufer !== null
            && (string) $verkaeufer['status'] === 'aktiv'
            && $konten->hatFaehigkeit($verkaeuferId, Konten::FAEHIGKEIT_VERKAUFEN);

        $benutzerId = $sitzung !== null ? (int) $sitzung['benutzer_id'] : 0;

        // Die Vorschau bleibt erhalten: Die Eigentuemerin muss ihr Angebot vor
        // der Freigabe ansehen koennen, die Verwaltung muss es zum Pruefen
        // sehen. Ein gesperrtes Konto kommt hier nicht durch — Sitzungen::laden()
        // filtert auf benutzer.status = 'aktiv', dann gibt es keine Sitzung.
        $darfVorschau = $benutzerId !== 0
            && ($benutzerId === $verkaeuferId
                || $konten->hatFaehigkeit($benutzerId, Konten::FAEHIGKEIT_VERWALTEN));

        if (!$oeffentlich && !$darfVorschau) {
            // Bewusst dieselbe Antwort wie bei unbekannter Kennung — dasselbe
            // Muster wie in bearbeitenFormular(): Wer das Angebot nicht sehen
            // darf, soll nicht einmal erfahren, ob es die Kennung gibt.
            return $this->rendern($anfrage, 'markt.angebot', t('markt.angebot_unbekannt_titel'), $daten);
        }

        // In der Vorschau bleibt der Konfigurator zu. Die Vorlage entscheidet
        // das allein am Status, deshalb wird ein 'aktiv', das nur an Konto oder
        // Faehigkeit der Verkaeuferin scheitert, hier auf 'pausiert' gesetzt.
        if (!$oeffentlich && (string) $angebot['status'] === Angebote::STATUS_AKTIV) {
            $angebot['status'] = Angebote::STATUS_PAUSIERT;
        }

        $daten['angebot'] = $angebot;
        $daten['verkaeufer'] = $verkaeufer;
        $daten['optionen'] = $this->aktiveOptionen($angebot);
        $daten['kategorie'] = $db->eine(
            'SELECT schluessel, pfad FROM kategorien WHERE id = :id',
            ['id' => (int) $angebot['kategorie_id']]
        );

        // Beide Pruefungen aus § 312g Abs. 2 Nr. 1 BGB in einem Wert. Die
        // Vorlage rechnete das bisher selbst nach, zaehlte dabei aber nur das
        // Kennzeichen ist_spezifikation und uebersah die Art: Ein angekreuztes
        // Kaestchen traegt keinen Wert der Kaeuferin und damit den
        // Widerrufsausschluss nicht. Die Fachklasse ist die einzige Stelle, an
        // der diese Bedingung steht.
        $daten['bestellbar'] = $angebote->istBestellbar($angebotId);

        if ($benutzerId !== 0) {
            $daten['darfKaufen'] = $konten->hatFaehigkeit($benutzerId, Konten::FAEHIGKEIT_KAUFEN);
        }

        // Nur wo das Bestellformular tatsaechlich erscheint, wird die gezeigte
        // Preisgrundlage festgehalten. Sonst legte jeder Streifzug durch den
        // Katalog ein Cookie an, das nie jemand einloest. Ruht der
        // Bestellvorgang, erscheint es nirgends.
        if ($oeffentlich
            && $daten['darfKaufen'] === true
            && $daten['bestellvorgangAktiv'] === true
            && $daten['bestellbar'] === true) {
            $this->preisstandMerken($angebot);
        }

        return $this->rendern(
            $anfrage,
            'markt.angebot',
            (string) $angebot['titel'] . ' — ' . t('allgemein.marke'),
            $daten
        );
    }

    // --- Verkaufen --------------------------------------------------------

    private function verkaufen(Router $router): void
    {
        // Reihenfolge ist tragend: '/verkaufen/neu' muss vor '/verkaufen/{id}'
        // stehen, sonst schluckt der Platzhalter das Wort 'neu'.
        $router->get('/verkaufen', fn (Request $a): Response => $this->meineAngebote($a));
        $router->get('/verkaufen/neu', fn (Request $a): Response => $this->anlegenFormular($a));
        $router->post('/verkaufen/neu', fn (Request $a): Response => $this->anlegen($a));
        $router->get('/verkaufen/{id}', fn (Request $a, array $p): Response => $this->bearbeitenFormular(
            $a,
            (int) ($p['id'] ?? 0)
        ));
        $router->post('/verkaufen/{id}', fn (Request $a, array $p): Response => $this->bearbeiten(
            $a,
            (int) ($p['id'] ?? 0)
        ));

        // Der Hauptweg nach dem Torabbau. Die Adresse traegt einen statischen
        // Teil hinter dem Platzhalter; der Router verankert seine Muster mit
        // ^...$ und {id} steht fuer ([^/]+), das keinen Schraegstrich frisst —
        // eine Kollision mit '/verkaufen/{id}' ist damit ausgeschlossen.
        $router->post(
            '/verkaufen/{id}/veroeffentlichen',
            fn (Request $a, array $p): Response => $this->veroeffentlichen($a, (int) ($p['id'] ?? 0))
        );

        // ALTPFAD, WOERTLICH UNVERAENDERT. Aus der Oberflaeche ist der Weg
        // verschwunden, die Route bleibt: Produktiv liegen Angebote in
        // 'in_pruefung', und wer die Vorabdurchsicht ausdruecklich will, soll
        // sie behalten koennen. Entfernen hiesse, ein Lesezeichen und einen
        // fachlich weiterhin gueltigen Weg ohne Not zu brechen.
        $router->post('/verkaufen/{id}/einreichen', fn (Request $a, array $p): Response => $this->einreichen(
            $a,
            (int) ($p['id'] ?? 0)
        ));
    }

    private function meineAngebote(Request $anfrage): Response
    {
        $zugang = $this->verkaufszugang($anfrage);

        if ($zugang['gesperrt'] !== null) {
            return $this->rendern($anfrage, 'markt.meine_angebote', t('markt.verkaufen_titel'), [
                'gesperrt' => $zugang['gesperrt'],
                'angebote' => [],
            ]);
        }

        /** @var Database $db */
        $db = $zugang['db'];

        return $this->rendern($anfrage, 'markt.meine_angebote', t('markt.verkaufen_titel'), [
            'gesperrt' => null,
            'angebote' => (new Angebote($db))->meine($zugang['benutzerId']),
        ]);
    }

    /** @param array<string,string> $eingaben */
    private function anlegenFormular(
        Request $anfrage,
        ?string $fehler = null,
        array $eingaben = []
    ): Response {
        $zugang = $this->verkaufszugang($anfrage);

        if ($zugang['gesperrt'] !== null) {
            return $this->rendern($anfrage, 'markt.meine_angebote', t('markt.verkaufen_titel'), [
                'gesperrt' => $zugang['gesperrt'],
                'angebote' => [],
            ]);
        }

        /** @var Database $db */
        $db = $zugang['db'];

        return $this->rendern($anfrage, 'markt.angebot_anlegen', t('markt.anlegen_titel'), [
            'kategorien' => $db->alle(
                'SELECT id, schluessel, pfad FROM kategorien WHERE aktiv = 1 ORDER BY reihenfolge, pfad'
            ),
            'fehler' => $fehler,
            'eingaben' => $eingaben,
        ]);
    }

    private function anlegen(Request $anfrage): Response
    {
        if (!Formularschutz::gueltig($anfrage)) {
            return Response::weiterleitung('/verkaufen/neu');
        }

        $zugang = $this->verkaufszugang($anfrage);

        if ($zugang['gesperrt'] !== null) {
            return Response::weiterleitung('/verkaufen');
        }

        /** @var Database $db */
        $db = $zugang['db'];

        $eingaben = [
            'titel' => $anfrage->eingabe('titel', '') ?? '',
            'beschreibung' => $anfrage->eingabe('beschreibung', '') ?? '',
            'kategorie_id' => $anfrage->eingabe('kategorie_id', '') ?? '',
            'grundpreis' => $anfrage->eingabe('grundpreis', '') ?? '',
            'bearbeitungstage' => $anfrage->eingabe('bearbeitungstage', '3') ?? '3',
            'uebergabe_region' => $anfrage->eingabe('uebergabe_region', '') ?? '',
            'versand_moeglich' => $this->angekreuzt($anfrage, 'versand_moeglich') ? 'ja' : '',
            'uebergabe_moeglich' => $this->angekreuzt($anfrage, 'uebergabe_moeglich') ? 'ja' : '',
        ];

        $grundpreisCent = $this->centAusEuro($eingaben['grundpreis']);

        if ($grundpreisCent === null) {
            return $this->anlegenFormular($anfrage, 'preis_unlesbar', $eingaben);
        }

        try {
            $angebotId = (new Angebote($db))->anlegen(
                verkaeuferId: $zugang['benutzerId'],
                kategorieId: (int) $eingaben['kategorie_id'],
                titel: $eingaben['titel'],
                beschreibung: $eingaben['beschreibung'],
                grundpreisCent: $grundpreisCent,
                versandMoeglich: $eingaben['versand_moeglich'] === 'ja',
                uebergabeMoeglich: $eingaben['uebergabe_moeglich'] === 'ja',
                uebergabeRegion: $eingaben['uebergabe_region'] === '' ? null : $eingaben['uebergabe_region'],
                bearbeitungstage: (int) $eingaben['bearbeitungstage']
            );
        } catch (AngebotFehler $fehler) {
            return $this->anlegenFormular($anfrage, $fehler->schluessel(), $eingaben);
        }

        return Response::weiterleitung('/verkaufen/' . $angebotId . '?erfolg=angelegt');
    }

    private function bearbeitenFormular(Request $anfrage, int $angebotId): Response
    {
        $zugang = $this->verkaufszugang($anfrage);

        if ($zugang['gesperrt'] !== null) {
            return $this->rendern($anfrage, 'markt.meine_angebote', t('markt.verkaufen_titel'), [
                'gesperrt' => $zugang['gesperrt'],
                'angebote' => [],
            ]);
        }

        /** @var Database $db */
        $db = $zugang['db'];
        $angebot = (new Angebote($db))->laden($angebotId);

        // Fremde Angebote und unbekannte Kennungen sind derselbe Fall: Wer
        // nicht Eigentuemer ist, darf nicht einmal erfahren, ob es das
        // Angebot gibt.
        if ($angebot === null || (int) $angebot['verkaeufer_id'] !== $zugang['benutzerId']) {
            return $this->rendern($anfrage, 'markt.angebot_bearbeiten', t('markt.bearbeiten_kicker'), [
                'angebot' => null,
                'kategorien' => [],
                'fehler' => 'angebot_unbekannt',
                'erfolg' => null,
                'bestellbar' => false,
                'bestellvorgangAktiv' => false,
            ]);
        }

        return $this->rendern(
            $anfrage,
            'markt.angebot_bearbeiten',
            (string) $angebot['titel'] . ' — ' . t('markt.bearbeiten_kicker'),
            [
                'angebot' => $angebot,
                'kategorien' => $db->alle(
                    'SELECT id, schluessel, pfad FROM kategorien WHERE aktiv = 1 ORDER BY reihenfolge, pfad'
                ),
                'fehler' => $this->ausListe($anfrage->eingabe('fehler'), self::ANGEBOTSFEHLER),
                'erfolg' => $this->ausListe($anfrage->eingabe('erfolg'), self::ERFOLGE),
                // Beide Werte tragen denselben Hinweis: Solange der
                // Bestellvorgang ruht, ist die fehlende Spezifikation folgenlos
                // und ein Hinweis darauf waere blosses Rauschen. Ist er offen,
                // ist sie der Unterschied zwischen sichtbar und verkaeuflich —
                // und die Verkaeuferin erfaehrt hier, welche Optionsart fehlt.
                'bestellbar' => (new Angebote($db))->istBestellbar($angebotId),
                'bestellvorgangAktiv' => Env::bool('BESTELLVORGANG_AKTIV', false),
            ]
        );
    }

    /**
     * Alle schreibenden Schritte am eigenen Angebot.
     *
     * Ein Formularfeld 'aktion' statt eigener Adressen je Schritt: Der Router
     * kennt nur GET und POST, und jede weitere Adresse waere eine weitere
     * Stelle, an der die Eigentuemerpruefung fehlen koennte.
     */
    private function bearbeiten(Request $anfrage, int $angebotId): Response
    {
        if (!Formularschutz::gueltig($anfrage)) {
            return Response::weiterleitung('/verkaufen/' . $angebotId);
        }

        $zugang = $this->verkaufszugang($anfrage);

        if ($zugang['gesperrt'] !== null) {
            return Response::weiterleitung('/verkaufen');
        }

        /** @var Database $db */
        $db = $zugang['db'];
        $angebote = new Angebote($db);
        $verkaeuferId = $zugang['benutzerId'];
        $aktion = $anfrage->eingabe('aktion', 'stammdaten') ?? 'stammdaten';

        try {
            switch ($aktion) {
                case 'stammdaten':
                    $angebote->bearbeiten($angebotId, $verkaeuferId, $this->stammdaten($anfrage));
                    $erfolg = 'gespeichert';
                    break;

                case 'option_setzen':
                    $aufpreis = $this->centAusEuro($anfrage->eingabe('aufpreis', '0') ?? '0');

                    if ($aufpreis === null) {
                        return Response::weiterleitung('/verkaufen/' . $angebotId . '?fehler=preis_unlesbar');
                    }

                    $angebote->optionSetzen(
                        angebotId: $angebotId,
                        verkaeuferId: $verkaeuferId,
                        schluessel: $anfrage->eingabe('schluessel', '') ?? '',
                        bezeichnung: $anfrage->eingabe('bezeichnung', '') ?? '',
                        art: $anfrage->eingabe('art', Angebote::ART_AUSWAHL) ?? Angebote::ART_AUSWAHL,
                        aufpreisCent: $aufpreis,
                        istSpezifikation: $this->angekreuzt($anfrage, 'ist_spezifikation'),
                        pflicht: $this->angekreuzt($anfrage, 'pflicht'),
                        reihenfolge: $anfrage->ganzzahl('reihenfolge', 0) ?? 0,
                        erlaeuterung: ($anfrage->eingabe('erlaeuterung', '') ?? '') === ''
                            ? null
                            : $anfrage->eingabe('erlaeuterung')
                    );
                    $erfolg = 'option_gespeichert';
                    break;

                case 'option_entfernen':
                    $angebote->optionEntfernen($angebotId, $verkaeuferId, $anfrage->eingabe('schluessel', '') ?? '');
                    $erfolg = 'option_entfernt';
                    break;

                case 'pausieren':
                    $angebote->pausieren($angebotId, $verkaeuferId);
                    $erfolg = 'pausiert';
                    break;

                case 'fortsetzen':
                    $angebote->fortsetzen($angebotId, $verkaeuferId);
                    $erfolg = 'fortgesetzt';
                    break;

                case 'entfernen':
                    $angebote->entfernen($angebotId, $verkaeuferId);

                    return Response::weiterleitung('/verkaufen');

                default:
                    return Response::weiterleitung('/verkaufen/' . $angebotId);
            }
        } catch (AngebotFehler $fehler) {
            return Response::weiterleitung(
                '/verkaufen/' . $angebotId . '?fehler=' . $this->ohneEigentuemerhinweis($fehler->schluessel())
            );
        }

        return Response::weiterleitung('/verkaufen/' . $angebotId . '?erfolg=' . $erfolg);
    }

    /**
     * Stellt das eigene Angebot sofort oeffentlich.
     *
     * Der Knopf, der die Vorabpruefung abloest. Es gibt hier bewusst keine
     * zusaetzliche Bedingung ueber Angebote::veroeffentlichen() hinaus — die
     * Fachklasse prueft Eigentum und Ausgangsstatus, und mehr soll das Tor
     * nicht sein. Insbesondere wird die Spezifikation NICHT verlangt: Sie
     * gehoert zur Bestellbarkeit, nicht zur Sichtbarkeit. Ein Angebot ohne sie
     * ist sichtbar und ueber "Nachricht schreiben" erreichbar, nur eben nicht
     * bestellbar — genau das sagt der Hinweis in bearbeitenFormular().
     */
    private function veroeffentlichen(Request $anfrage, int $angebotId): Response
    {
        if (!Formularschutz::gueltig($anfrage)) {
            return Response::weiterleitung('/verkaufen/' . $angebotId);
        }

        $zugang = $this->verkaufszugang($anfrage);

        if ($zugang['gesperrt'] !== null) {
            return Response::weiterleitung('/verkaufen');
        }

        /** @var Database $db */
        $db = $zugang['db'];

        try {
            (new Angebote($db))->veroeffentlichen($angebotId, $zugang['benutzerId']);
        } catch (AngebotFehler $fehler) {
            return Response::weiterleitung(
                '/verkaufen/' . $angebotId . '?fehler=' . $this->ohneEigentuemerhinweis($fehler->schluessel())
            );
        }

        return Response::weiterleitung('/verkaufen/' . $angebotId . '?erfolg=veroeffentlicht');
    }

    private function einreichen(Request $anfrage, int $angebotId): Response
    {
        if (!Formularschutz::gueltig($anfrage)) {
            return Response::weiterleitung('/verkaufen/' . $angebotId);
        }

        $zugang = $this->verkaufszugang($anfrage);

        if ($zugang['gesperrt'] !== null) {
            return Response::weiterleitung('/verkaufen');
        }

        /** @var Database $db */
        $db = $zugang['db'];

        try {
            (new Angebote($db))->zurPruefungEinreichen($angebotId, $zugang['benutzerId']);
        } catch (AngebotFehler $fehler) {
            return Response::weiterleitung(
                '/verkaufen/' . $angebotId . '?fehler=' . $this->ohneEigentuemerhinweis($fehler->schluessel())
            );
        }

        return Response::weiterleitung('/verkaufen/' . $angebotId . '?erfolg=eingereicht');
    }

    /**
     * Liest die Stammdaten aus dem Formular.
     *
     * Nur Felder, die tatsaechlich gesendet wurden, wandern ins Ergebnis —
     * Angebote::bearbeiten() weist unbekannte Schluessel ohnehin ab, und ein
     * leeres Array laesst es unveraendert.
     *
     * @return array<string,mixed>
     */
    private function stammdaten(Request $anfrage): array
    {
        $felder = [];

        foreach (['titel', 'beschreibung', 'uebergabe_region'] as $name) {
            $wert = $anfrage->eingabe($name);

            if ($wert !== null) {
                $felder[$name] = $name === 'uebergabe_region' && $wert === '' ? null : $wert;
            }
        }

        $kategorie = $anfrage->ganzzahl('kategorie_id');
        if ($kategorie !== null) {
            $felder['kategorie_id'] = $kategorie;
        }

        $tage = $anfrage->ganzzahl('bearbeitungstage');
        if ($tage !== null) {
            $felder['bearbeitungstage'] = $tage;
        }

        $preis = $anfrage->eingabe('grundpreis');
        if ($preis !== null && $preis !== '') {
            // Ein unlesbarer Betrag darf nicht stillschweigend zu null Cent
            // werden — Angebote::bearbeiten() wiese das mit
            // 'grundpreis_ungueltig' ab, und genau das soll die Person sehen.
            $felder['grundpreis_cent'] = $this->centAusEuro($preis) ?? 0;
        }

        // Kaestchen melden sich nur, wenn sie angekreuzt sind. Das Formular
        // sendet die beiden Lieferwege deshalb immer mit, sichtbar am
        // Steuerfeld 'lieferwege'.
        if (($anfrage->eingabe('lieferwege') ?? '') === 'ja') {
            $felder['versand_moeglich'] = $this->angekreuzt($anfrage, 'versand_moeglich');
            $felder['uebergabe_moeglich'] = $this->angekreuzt($anfrage, 'uebergabe_moeglich');
        }

        return $felder;
    }

    // --- Kaufen -----------------------------------------------------------

    private function kaufen(Router $router): void
    {
        $router->post('/bestellen/{id}', fn (Request $a, array $p): Response => $this->bestellen(
            $a,
            (int) ($p['id'] ?? 0)
        ));

        $router->get('/bestellung/{id}', fn (Request $a, array $p): Response => $this->bestellungSeite(
            $a,
            (int) ($p['id'] ?? 0)
        ));
    }

    /**
     * Legt die Bestellung an.
     *
     * Hier haengt der Widerrufsausschluss: § 312g Abs. 2 Nr. 1 BGB nimmt Ware
     * aus, die nach Kundenspezifikation angefertigt wird. Traegt die
     * Bestellung keine echte, vom Kaeufer gesetzte Spezifikation, traegt der
     * Ausschluss nicht — dann waere die Ware widerrufbar, und getragene
     * Waesche kaeme zurueck. Deshalb wird die Auswahl hier ein zweites Mal
     * geprueft, unabhaengig von 'required' im Formular.
     */
    private function bestellen(Request $anfrage, int $angebotId): Response
    {
        if (!Formularschutz::gueltig($anfrage)) {
            return Response::weiterleitung('/angebot/' . $angebotId);
        }

        // DER GESCHLOSSENE BESTELLVORGANG IST KEIN AUSGEBLENDETER KNOPF.
        // Die Vorlage rendert das Formular nicht, solange der Schalter aus ist —
        // das allein waere Zugriffsschutz durch Verbergen. Bestellungen::anlegen()
        // wirft in diesem Fall BestellvorgangGesperrt; ohne die Zeile hier
        // liefe eine handgebaute Anfrage in den allgemeinen \Throwable-Zweig
        // weiter unten und erzeugte eine Protokollzeile fuer einen Zustand, der
        // gar keine Stoerung ist. Stattdessen bekommt die Person die ehrliche
        // Auskunft.
        if (!Env::bool('BESTELLVORGANG_AKTIV', false)) {
            return $this->angebotSeite($anfrage, $angebotId, 'bestellvorgang_gesperrt');
        }

        $sitzung = $this->sitzung($anfrage);

        if ($sitzung === null) {
            return Response::weiterleitung('/anmelden');
        }

        $db = $this->datenbank();

        if ($db === null) {
            return $this->angebotSeite($anfrage, $angebotId, 'allgemein');
        }

        $kaeuferId = (int) $sitzung['benutzer_id'];
        $konten = new Konten($db);

        if (!$konten->hatFaehigkeit($kaeuferId, Konten::FAEHIGKEIT_KAUFEN)) {
            return $this->angebotSeite($anfrage, $angebotId, 'kaufen_gesperrt');
        }

        $angebot = (new Angebote($db))->laden($angebotId);

        if ($angebot === null || (string) $angebot['status'] !== Angebote::STATUS_AKTIV) {
            return $this->angebotSeite($anfrage, $angebotId, 'angebot');
        }

        $verkaeuferId = (int) $angebot['verkaeufer_id'];
        $verkaeufer = $db->eine('SELECT status FROM benutzer WHERE id = :id', ['id' => $verkaeuferId]);

        // Die Faehigkeit 'verkaufen' zaehlt hier genauso wie der Kontostatus.
        // Ihr Entzug ist eine eigenstaendige, begruendungspflichtige Massnahme
        // neben der Kontosperre (Art. 17 DSA) — das mildere Mittel. Ohne diese
        // Pruefung verkauft das Konto danach unveraendert weiter und nimmt
        // weiter Geld ein, waehrend das Protokoll die Massnahme als vollzogen
        // ausweist. Das ist zugleich die Sicherung gegen das Rennen zwischen
        // Seitenaufbau und Absenden: Der Entzug wirkt ab dem naechsten POST.
        if ($verkaeufer === null
            || (string) $verkaeufer['status'] !== 'aktiv'
            || !$konten->hatFaehigkeit($verkaeuferId, Konten::FAEHIGKEIT_VERKAUFEN)) {
            return $this->angebotSeite($anfrage, $angebotId, 'angebot');
        }

        $waehrung = strtoupper((string) $angebot['waehrung']);

        // Plattformkonten gibt es nur in Euro (003_hauptbuch.php). Eine
        // fremde Waehrung stuerbe sonst tief im Hauptbuch statt hier.
        if ($waehrung !== 'EUR') {
            return $this->angebotSeite($anfrage, $angebotId, 'waehrung');
        }

        // Die Umsatzsteuer richtet sich nach dem Land der Kaeuferin. Ist es
        // nicht freigeschaltet, wird nicht ersatzweise deutsche Steuer
        // berechnet — das waere schlicht falsch.
        $land = strtoupper((string) ($sitzung['land'] ?? 'DE'));
        $landAktiv = (int) $db->wert(
            'SELECT COUNT(*) FROM laender WHERE code = :c AND aktiv = 1',
            ['c' => $land]
        );

        if ($landAktiv === 0) {
            return $this->angebotSeite($anfrage, $angebotId, 'land');
        }

        $lieferart = $anfrage->eingabe('lieferart', 'versand') ?? 'versand';
        $erlaubteLieferarten = [];
        if ((int) $angebot['versand_moeglich'] === 1) {
            $erlaubteLieferarten[] = 'versand';
        }
        if ((int) $angebot['uebergabe_moeglich'] === 1) {
            $erlaubteLieferarten[] = 'uebergabe';
        }

        if (!in_array($lieferart, $erlaubteLieferarten, true)) {
            return $this->angebotSeite($anfrage, $angebotId, 'lieferart', $this->gewaehlt($anfrage, $angebot));
        }

        // Die Bestaetigung ist kein Haekchen um des Haekchens willen: Sie
        // belegt, dass die Unterrichtung nach Art. 246a § 1 Abs. 3 Nr. 1
        // EGBGB vor Abgabe der Vertragserklaerung stattgefunden hat.
        if (!$this->angekreuzt($anfrage, 'widerruf_verstanden')) {
            return $this->angebotSeite($anfrage, $angebotId, 'widerruf', $this->gewaehlt($anfrage, $angebot));
        }

        $optionen = $this->aktiveOptionen($angebot);
        $spezifikationen = [];
        $aufpreisSumme = 0;
        $echteSpezifikationen = 0;

        foreach ($optionen as $option) {
            $schluessel = (string) $option['schluessel'];
            $wert = trim($anfrage->eingabe('option_' . $schluessel, '') ?? '');

            if ($wert === '') {
                if ((int) $option['pflicht'] === 1) {
                    return $this->angebotSeite(
                        $anfrage,
                        $angebotId,
                        'pflichtoption',
                        $this->gewaehlt($anfrage, $angebot)
                    );
                }

                continue;
            }

            $aufpreisSumme += (int) $option['aufpreis_cent'];

            // Auch Optionen ohne Spezifikationskennzeichen wandern in die
            // Aufstellung: Ihr Aufpreis steckt im Betrag, also muss im Beleg
            // stehen, wofuer. Der Widerrufsausschluss stuetzt sich aber
            // ausschliesslich auf die gezaehlten echten Spezifikationen.
            $spezifikationen[] = [
                'schluessel' => $schluessel,
                'bezeichnung' => (string) $option['bezeichnung'],
                'wert' => mb_substr($wert, 0, 500),
                'option_id' => (int) $option['id'],
                'aufpreis_cent' => (int) $option['aufpreis_cent'],
            ];

            if ((int) $option['ist_spezifikation'] === 1) {
                ++$echteSpezifikationen;
            }
        }

        if ($echteSpezifikationen === 0) {
            return $this->angebotSeite(
                $anfrage,
                $angebotId,
                'spezifikation',
                $this->gewaehlt($anfrage, $angebot)
            );
        }

        // DER PREIS, DER GEBUCHT WIRD, MUSS DER PREIS SEIN, DER VOR DEM KNOPF
        // STAND. Zwischen dem Aufbau der Angebotsseite und diesem POST kann die
        // Verkaeuferin pausiert, den Grundpreis oder einen Aufpreis geaendert
        // und wieder fortgesetzt haben — das ist der vorgesehene
        // Bearbeitungsweg, kein Angriff mit Sonderrechten. Dieselbe Divergenz
        // entsteht gutglaeubig, wenn die Seite eine Stunde offen liegt.
        //
        // Gerechnet wird deshalb weiter mit dem Datenbankwert; verglichen wird
        // gegen den Stand, der beim Anzeigen festgehalten wurde. Ein Betrag aus
        // dem Formular waere genau die Preismanipulation, die zu vermeiden ist:
        // aus der Preiserhoehung der Verkaeuferin wuerde eine Preissenkung
        // durch die Kaeuferin.
        //
        // Der Rueckweg ueber angebotSeite() rendert die Seite mit dem neuen
        // Preis und allen bereits getroffenen Angaben, haelt den neuen Stand
        // fest und verlangt ein zweites Absenden. Damit steht der Gesamtpreis
        // nachweislich unmittelbar vor der Bestellung (§ 312j Abs. 2 BGB), und
        // niemand tippt seine Auswahl neu.
        if (!$this->preisstandGezeigt($angebot)) {
            return $this->angebotSeite(
                $anfrage,
                $angebotId,
                'preis_geaendert',
                $this->gewaehlt($anfrage, $angebot)
            );
        }

        // Aufpreise muessen hier aufaddiert werden: Bestellungen::anlegen()
        // speichert spezifikationen[]['aufpreis_cent'], rechnet damit aber
        // nicht — der Positionspreis ist allein brutto_cent mal Menge.
        $bruttoCent = (int) $angebot['grundpreis_cent'] + $aufpreisSumme;

        $position = [
            'angebot_id' => (int) $angebot['id'],
            'bezeichnung' => (string) $angebot['titel'],
            'brutto_cent' => $bruttoCent,
            'menge' => 1,
            'spezifikationen' => $spezifikationen,
        ];

        // ZWEIMAL ABSENDEN DARF NICHT ZWEIMAL BINDEN. Das Formularschutz-Token
        // lebt 30 Tage und wird nicht verbraucht (bewusst — Registrierung und
        // Anmeldung finden vor der Sitzung statt), der Absendeknopf sperrt sich
        // nicht, und Bestellungen::anlegen() nimmt keinen Idempotenzschluessel
        // entgegen. Ein Doppelklick auf traeger Mobilverbindung erzeugte damit
        // zwei vollstaendige Bestellungen mit zwei Treuhandbindungen.
        //
        // Hier greift deshalb die fachliche Sperre: Haelt dieselbe Kaeuferin auf
        // dasselbe Angebot bereits eine Bestellung, die noch nicht angenommen
        // ist, fuehrt der zweite POST auf genau diese Bestellung statt auf eine
        // neue — dieselbe Antwort wie beim ersten, also idempotent nach aussen.
        // Kein Fehler, weil der zweite Klick kein Fehler der Kaeuferin ist.
        $laufende = $this->laufendeBestellung($db, $kaeuferId, $angebotId);

        if ($laufende !== null) {
            return Response::weiterleitung('/bestellung/' . $laufende);
        }

        $satz = (int) (Env::get('PROVISION_SATZ', '1500') ?? '1500');
        $bestellungen = new Bestellungen($db, new Hauptbuch($db), new Preisrechner($satz));

        try {
            $bestellungId = $bestellungen->anlegen(
                $kaeuferId,
                $verkaeuferId,
                [$position],
                $lieferart,
                $land,
                $waehrung
            );
        } catch (BestellFehler $fehler) {
            return $this->angebotSeite(
                $anfrage,
                $angebotId,
                $this->bestellfehlerSchluessel($fehler),
                $this->gewaehlt($anfrage, $angebot)
            );
        } catch (\Throwable $fehler) {
            // Nicht verschlucken, aber auch nicht ungefiltert nach aussen
            // tragen: Der Mensch bekommt eine ruhige Meldung, das Protokoll
            // bekommt den Grund.
            error_log('[MeinSlip/Markt] Bestellung gescheitert: ' . $fehler->getMessage());

            return $this->angebotSeite(
                $anfrage,
                $angebotId,
                'allgemein',
                $this->gewaehlt($anfrage, $angebot)
            );
        }

        return Response::weiterleitung('/bestellung/' . $bestellungId . '?neu=ja');
    }

    private function bestellungSeite(Request $anfrage, int $bestellungId): Response
    {
        $sitzung = $this->sitzung($anfrage);

        if ($sitzung === null) {
            return Response::weiterleitung('/anmelden');
        }

        $daten = [
            'bestellung' => null,
            'positionen' => [],
            'rolle' => null,
            'gegenueber' => null,
            'neu' => ($anfrage->eingabe('neu') ?? '') === 'ja',
            'zugang' => false,
        ];

        $db = $this->datenbank();

        if ($db === null) {
            return $this->rendern($anfrage, 'markt.bestellung', t('markt.bestellung_kicker'), $daten);
        }

        $bestellung = $db->eine('SELECT * FROM bestellungen WHERE id = :id', ['id' => $bestellungId]);

        if ($bestellung === null) {
            return $this->rendern($anfrage, 'markt.bestellung', t('markt.bestellung_unbekannt_titel'), $daten);
        }

        $benutzerId = (int) $sitzung['benutzer_id'];
        $istKaeufer = (int) $bestellung['kaeufer_id'] === $benutzerId;
        $istVerkaeufer = (int) $bestellung['verkaeufer_id'] === $benutzerId;

        if (!$istKaeufer && !$istVerkaeufer) {
            return $this->rendern($anfrage, 'markt.bestellung', t('markt.bestellung_kein_zugang_titel'), $daten);
        }

        $positionen = $db->alle(
            'SELECT * FROM bestellpositionen WHERE bestellung_id = :b ORDER BY id',
            ['b' => $bestellungId]
        );

        foreach ($positionen as $index => $position) {
            $positionen[$index]['spezifikationen'] = $db->alle(
                'SELECT * FROM bestellung_spezifikationen WHERE position_id = :p ORDER BY id',
                ['p' => (int) $position['id']]
            );
        }

        $daten['bestellung'] = $bestellung;
        $daten['positionen'] = array_values($positionen);
        $daten['rolle'] = $istKaeufer ? 'kaeufer' : 'verkaeufer';
        $daten['zugang'] = true;
        $daten['gegenueber'] = $db->eine(
            'SELECT pseudonym FROM benutzer WHERE id = :id',
            ['id' => $istKaeufer ? (int) $bestellung['verkaeufer_id'] : (int) $bestellung['kaeufer_id']]
        );

        return $this->rendern(
            $anfrage,
            'markt.bestellung',
            t('markt.bestellung_kicker') . ' ' . (string) $bestellung['nummer'],
            $daten
        );
    }

    /**
     * Uebersetzt die Meldung eines BestellFehlers in einen Schluessel.
     *
     * Die Meldungstexte sind Teil des Testvertrags von
     * tests/BestellungenTest.php und deshalb bewusst nicht umgestellt
     * worden — hier werden sie gelesen, nicht geaendert.
     */
    private function bestellfehlerSchluessel(BestellFehler $fehler): string
    {
        $meldung = $fehler->getMessage();

        return match (true) {
            // Erwarteter Zustand, kein Ausfall: Guthaben ist aufsichtsrechtlich
            // gesperrt (§ 1 Abs. 1 S. 2 Nr. 1 KWG), solange kein
            // Zahlungsdienstleister angebunden ist.
            str_contains($meldung, 'Guthaben reicht nicht') => 'guthaben',
            str_contains($meldung, 'mit sich selbst') => 'selbstkauf',
            str_contains($meldung, 'teilen sich Geraet') => 'verbundene_konten',
            str_contains($meldung, 'keine Spezifikation') => 'spezifikation',
            str_contains($meldung, 'Lieferart') => 'lieferart',
            str_contains($meldung, 'nicht konfiguriert') => 'land',
            default => 'allgemein',
        };
    }

    // --- Hilfen -----------------------------------------------------------

    /**
     * Kennung einer noch nicht angenommenen Bestellung derselben Kaeuferin auf
     * dasselbe Angebot, oder null.
     *
     * Bewusst nur 'zahlung_offen' und 'treuhand_gebunden': Das sind die
     * Zustaende vor der Annahme durch die Verkaeuferin, und genau dort landet
     * ein Doppelklick. Ab 'angenommen' laeuft die erste Bestellung, teils
     * wochenlang bis zur Freigabe — eine zweite Bestellung ist dann ein
     * eigener Kaufwunsch und darf nicht abgefangen werden.
     *
     * EINSCHRAENKUNG, die in der Route nicht zu schliessen ist: Zwei wirklich
     * gleichzeitige Anfragen lesen beide, bevor die jeweils andere einfuegt.
     * Dagegen hilft nur eine Datenbankbedingung — ein Idempotenzschluessel auf
     * 'bestellungen' mit eindeutigem Index, den Bestellungen::anlegen()
     * durchreicht (dieselbe Bauform, die Hauptbuch::buchen() bereits kennt).
     */
    private function laufendeBestellung(Database $db, int $kaeuferId, int $angebotId): ?int
    {
        $zeile = $db->eine(
            'SELECT b.id
               FROM bestellungen b
               JOIN bestellpositionen p ON p.bestellung_id = b.id
              WHERE b.kaeufer_id = :k
                AND p.angebot_id = :a
                AND b.zustand IN (:offen, :gebunden)
              ORDER BY b.id DESC',
            [
                'k' => $kaeuferId,
                'a' => $angebotId,
                'offen' => Bestellzustand::ZahlungOffen->value,
                'gebunden' => Bestellzustand::TreuhandGebunden->value,
            ]
        );

        return $zeile === null ? null : (int) $zeile['id'];
    }

    /**
     * Fingerabdruck der Preisgrundlage eines Angebots.
     *
     * Enthaelt alles, woraus sich der Gesamtbetrag ergibt: Grundpreis, Waehrung
     * und den Aufpreis JEDER aktiven Option — nicht nur der gewaehlten. Sonst
     * haenge der Stand an der Auswahl und schluege schon beim blossen
     * Umkonfigurieren an.
     *
     * Bewusst ein Fingerabdruck ohne Geheimnis und keine Signatur: Der Wert
     * wird nie zum Rechnen benutzt, sondern ausschliesslich auf Gleichheit
     * geprueft. Wer ihn faelscht, unterdrueckt allein seine eigene Warnung und
     * zahlt trotzdem den Preis aus der Datenbank — eine Signatur schuetzte hier
     * also niemanden, verlangte aber einen gesetzten APP_SCHLUESSEL und liesse
     * die Angebotsseite bei leerer Konfiguration mit einer Ausnahme abbrechen.
     *
     * @param array<string,mixed> $angebot
     */
    private function preisStand(array $angebot): string
    {
        $teile = [
            (int) $angebot['id'],
            (int) $angebot['grundpreis_cent'],
            strtoupper((string) $angebot['waehrung']),
        ];

        foreach ($this->aktiveOptionen($angebot) as $option) {
            $teile[] = (int) $option['id'] . ':' . (int) $option['aufpreis_cent'];
        }

        return (int) $angebot['id'] . ':' . hash('sha256', implode('|', $teile));
    }

    /**
     * Haelt fest, welche Preisgrundlage der Kaeuferin zuletzt gezeigt wurde.
     *
     * WARUM EIN COOKIE UND KEIN VERSTECKTES FORMULARFELD: Ein Feld waere die
     * genauere Loesung — es haengt am einzelnen Formular und traegt deshalb
     * auch dann den richtigen Stand, wenn zwei Fenster mit demselben Angebot
     * offen sind. Es setzt aber voraus, dass resources/views/markt/bestellen.php
     * es ausgibt. Solange die Vorlage das nicht tut, gibt es nur zwei
     * Moeglichkeiten: die Pruefung ganz auslassen (dann bleibt die Luecke) oder
     * jede Bestellung abweisen (dann ist die einzige Kaufstrecke tot). Das
     * Cookie ist der dritte Weg und traegt dieselbe Aussage — 'diese
     * Preisgrundlage stand vor dem Knopf' —, nur an der Anfrage statt am
     * Formular.
     *
     * Ein Cookie fuer alle Angebote, nicht eines je Angebot: Sonst sammelte ein
     * Streifzug durch den Katalog Dutzende Cookies an, die in jeder weiteren
     * Anfrage mitgeschickt wuerden. Der Preis dafuer ist, dass zwei gleichzeitig
     * offene Bestellformulare zu verschiedenen Angeboten eine zusaetzliche
     * Bestaetigung kosten — die Seite zeigt dann den aktuellen Gesamtbetrag,
     * und das zweite Absenden geht durch.
     *
     * @param array<string,mixed> $angebot
     */
    private function preisstandMerken(array $angebot): void
    {
        if (headers_sent()) {
            return;
        }

        $stand = $this->preisStand($angebot);

        setcookie(self::PREISSTAND_COOKIE, $stand, [
            // Kein Ablauf: Das Cookie soll genau so lange gelten wie das
            // Fenster offen ist. Liefe es vorher ab, meldete die Seite eine
            // Preisaenderung, die es nie gegeben hat.
            'expires' => 0,
            'path' => '/',
            'secure' => ($_SERVER['HTTPS'] ?? '') === 'on'
                || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
            // Anders als das Formularschutz-Token braucht JavaScript diesen
            // Wert nicht.
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // Damit ein Aufruf im selben Prozess den frisch gesetzten Wert sieht —
        // $_COOKIE wird von setcookie() nicht fortgeschrieben.
        $_COOKIE[self::PREISSTAND_COOKIE] = $stand;
    }

    /**
     * Stimmt die Preisgrundlage noch mit der ueberein, die gezeigt wurde?
     *
     * Ein fehlendes Cookie faellt in denselben Zweig wie ein abweichendes. Es
     * gibt also keinen stillschweigenden Altfall, in dem ungeprueft gebucht
     * wird — das war der Kern des Befunds.
     *
     * @param array<string,mixed> $angebot
     */
    private function preisstandGezeigt(array $angebot): bool
    {
        $gemerkt = $_COOKIE[self::PREISSTAND_COOKIE] ?? '';

        if (!is_string($gemerkt) || $gemerkt === '') {
            return false;
        }

        return hash_equals($this->preisStand($angebot), $gemerkt);
    }

    /**
     * 'nicht_der_eigentuemer' verraet, dass es das Angebot gibt.
     *
     * bearbeitenFormular() verschmilzt fremdes und unbekanntes Angebot bewusst
     * zu einem Fall. Die schreibenden Routen muessen dieselbe Zusicherung
     * halten, sonst ist jede Angebotskennung durchzaehlbar: Der Schluessel der
     * Fachklasse landete ungefiltert in der Adresszeile, und zwei sonst
     * gleiche Antworten wurden unterscheidbar.
     *
     * Alle anderen Schluessel duerfen unveraendert durch — sie werden erst nach
     * bestandener Eigentuemerpruefung geworfen, betreffen also nur eigene
     * Angebote und muessen der Verkaeuferin ihren Fehler zeigen.
     */
    private function ohneEigentuemerhinweis(string $schluessel): string
    {
        return $schluessel === 'nicht_der_eigentuemer' ? 'angebot_unbekannt' : $schluessel;
    }

    /**
     * Sitzung, Datenbank und Verkaufsfaehigkeit in einem Griff.
     *
     * @return array{gesperrt:string|null, db:Database|null, benutzerId:int}
     */
    private function verkaufszugang(Request $anfrage): array
    {
        $sitzung = $this->sitzung($anfrage);

        if ($sitzung === null) {
            return ['gesperrt' => 'anmeldung', 'db' => null, 'benutzerId' => 0];
        }

        $db = $this->datenbank();

        if ($db === null) {
            return ['gesperrt' => 'gestoert', 'db' => null, 'benutzerId' => 0];
        }

        $benutzerId = (int) $sitzung['benutzer_id'];

        if (!(new Konten($db))->hatFaehigkeit($benutzerId, Konten::FAEHIGKEIT_VERKAUFEN)) {
            return ['gesperrt' => 'faehigkeit', 'db' => $db, 'benutzerId' => $benutzerId];
        }

        return ['gesperrt' => null, 'db' => $db, 'benutzerId' => $benutzerId];
    }

    /**
     * Nur aktive Optionen, in der Reihenfolge des Angebots.
     *
     * @param array<string,mixed> $angebot
     *
     * @return list<array<string,mixed>>
     */
    private function aktiveOptionen(array $angebot): array
    {
        $optionen = is_array($angebot['optionen'] ?? null) ? $angebot['optionen'] : [];

        return array_values(array_filter(
            $optionen,
            static fn (array $option): bool => (int) ($option['aktiv'] ?? 0) === 1
        ));
    }

    /**
     * Die bereits getroffene Auswahl, damit ein Fehlversuch nichts loescht.
     *
     * @param array<string,mixed> $angebot
     *
     * @return array<string,string>
     */
    private function gewaehlt(Request $anfrage, array $angebot): array
    {
        $eingaben = ['lieferart' => $anfrage->eingabe('lieferart', '') ?? ''];

        // Die Optionen behalten ihren Feldnamen mit Praefix. Ein Options-
        // schluessel darf 'lieferart' heissen — ohne Praefix wuerde er die
        // Lieferart ueberschreiben.
        foreach ($this->aktiveOptionen($angebot) as $option) {
            $feld = 'option_' . (string) $option['schluessel'];
            $eingaben[$feld] = $anfrage->eingabe($feld, '') ?? '';
        }

        if ($this->angekreuzt($anfrage, 'widerruf_verstanden')) {
            $eingaben['widerruf_verstanden'] = 'ja';
        }

        return $eingaben;
    }

    /** Ein Kaestchen meldet sich nur, wenn es angekreuzt ist. */
    private function angekreuzt(Request $anfrage, string $name): bool
    {
        return ($anfrage->eingabe($name, '') ?? '') === 'ja';
    }

    /**
     * Wandelt eine Euro-Eingabe in ganzzahlige Cent.
     *
     * Bewusst ohne Gleitkomma: Geld ist im ganzen System ganzzahliger Cent,
     * und 19.99 * 100 ergibt in Gleitkomma 1998,9999… — genau dieser Cent
     * fehlt spaeter im Hauptbuch.
     */
    private function centAusEuro(string $eingabe): ?int
    {
        $roh = str_replace(',', '.', trim($eingabe));

        if ($roh === '') {
            return null;
        }

        if (!preg_match('/^\d{1,7}(?:\.\d{1,2})?$/', $roh)) {
            return null;
        }

        $teile = explode('.', $roh);
        $euro = (int) $teile[0];
        $cent = isset($teile[1]) ? (int) str_pad($teile[1], 2, '0') : 0;

        return $euro * 100 + $cent;
    }

    /**
     * Laesst nur bekannte Schluessel durch.
     *
     * @param list<string> $erlaubt
     */
    private function ausListe(?string $wert, array $erlaubt): ?string
    {
        return $wert !== null && in_array($wert, $erlaubt, true) ? $wert : null;
    }

    /**
     * Die Datenbankverbindung dieser Anfrage.
     *
     * Database::ausEnv() oeffnet mit jedem Aufruf eine neue PDO-Verbindung. Ein
     * GET auf /angebot/{id} lief dadurch dreimal durch den Verbindungsaufbau:
     * einmal fuer die Sitzung, einmal fuer die Seite, einmal noch aus
     * rendern(). Auf geteiltem Webhosting ist jede Verbindung ein eigener TCP-
     * und Anmeldevorgang.
     *
     * Der Zwischenspeicher lebt nur so lange wie diese Instanz, und die wird in
     * public/index.php je Anfrage frisch erzeugt.
     *
     * 'versucht' ist ein eigenes Feld, weil null ein gueltiges Ergebnis ist
     * (Datenbankausfall) — ohne den Merker wuerde bei jeder Stoerung erneut
     * verbunden.
     */
    private function datenbank(): ?Database
    {
        if ($this->verbindungVersucht) {
            return $this->verbindung;
        }

        $this->verbindungVersucht = true;

        try {
            $this->verbindung = Database::ausEnv();
        } catch (\Throwable) {
            // Ein Datenbankausfall darf nicht die ganze Seite mitreissen —
            // die Vorlagen zeigen dann eine ehrliche Stoerungsmeldung.
            $this->verbindung = null;
        }

        return $this->verbindung;
    }

    /**
     * Wie Routen::rendern(), das dort aber privat ist.
     *
     * @param array<string,mixed> $daten
     */
    private function rendern(Request $anfrage, string $vorlage, string $titel, array $daten = []): Response
    {
        return Response::html($this->ansicht->rendern($vorlage, $daten + [
            '__layout' => 'layout',
            'titel' => $titel,
            'sprache' => Lang::sprache(),
            'sitzung' => $this->sitzung($anfrage),
        ]));
    }

    /**
     * Die Sitzung dieser Anfrage.
     *
     * Wird je Seitenaufbau mindestens zweimal gebraucht — von der Route selbst
     * und von rendern() fuer die Kopfleiste. Die JOIN-Abfrage aus
     * Sitzungen::laden() lief deshalb doppelt mit demselben Ergebnis. Auch hier
     * ist null gueltig (kein Cookie, abgelaufen, Konto gesperrt), also braucht
     * es den eigenen Merker.
     *
     * @return array<string,mixed>|null
     */
    private function sitzung(Request $anfrage): ?array
    {
        if ($this->sitzungGeladen) {
            return $this->sitzungZwischen;
        }

        $this->sitzungGeladen = true;
        $kennung = $_COOKIE[Sitzungen::COOKIE] ?? null;

        if (!is_string($kennung) || $kennung === '') {
            return null;
        }

        $db = $this->datenbank();

        if ($db === null) {
            return null;
        }

        try {
            return $this->sitzungZwischen = (new Sitzungen($db))->laden($kennung);
        } catch (\Throwable) {
            return null;
        }
    }
}
