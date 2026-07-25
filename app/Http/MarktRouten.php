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
 */
final class MarktRouten
{
    /** Angebote je Katalogseite. */
    private const PRO_SEITE = 24;

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
        'nicht_der_eigentuemer',
        'nicht_bearbeitbar',
        'feld_unbekannt',
        'option_schluessel_ungueltig',
        'option_bezeichnung_ungueltig',
        'option_art_unbekannt',
        'option_aufpreis_ungueltig',
        'option_unbekannt',
        'keine_spezifikation',
        'ablehnungsgrund_fehlt',
        'eigenpruefung_unzulaessig',
        'statuswechsel_unzulaessig',
        'status_unbekannt',
        'preis_unlesbar',
        'allgemein',
    ];

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
        ];

        $db = $this->datenbank();

        if ($db === null) {
            $daten['gestoert'] = true;

            return $this->rendern($anfrage, 'markt.angebot', t('markt.angebot_kicker'), $daten);
        }

        $angebot = (new Angebote($db))->laden($angebotId);

        if ($angebot === null) {
            return $this->rendern($anfrage, 'markt.angebot', t('markt.angebot_unbekannt_titel'), $daten);
        }

        $verkaeufer = $db->eine(
            'SELECT id, pseudonym, status FROM benutzer WHERE id = :id',
            ['id' => (int) $angebot['verkaeufer_id']]
        );

        // Ein gesperrtes Verkaeuferkonto nimmt das Angebot mit vom Markt —
        // fuerKatalog() filtert genauso, die Einzelseite darf nicht laxer sein.
        if ($verkaeufer === null || (string) $verkaeufer['status'] !== 'aktiv') {
            $angebot['status'] = Angebote::STATUS_PAUSIERT;
        }

        $daten['angebot'] = $angebot;
        $daten['verkaeufer'] = $verkaeufer;
        $daten['optionen'] = $this->aktiveOptionen($angebot);
        $daten['kategorie'] = $db->eine(
            'SELECT schluessel, pfad FROM kategorien WHERE id = :id',
            ['id' => (int) $angebot['kategorie_id']]
        );

        if ($sitzung !== null) {
            $daten['darfKaufen'] = (new Konten($db))->hatFaehigkeit(
                (int) $sitzung['benutzer_id'],
                Konten::FAEHIGKEIT_KAUFEN
            );
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
            return Response::weiterleitung('/verkaufen/' . $angebotId . '?fehler=' . $fehler->schluessel());
        }

        return Response::weiterleitung('/verkaufen/' . $angebotId . '?erfolg=' . $erfolg);
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
            return Response::weiterleitung('/verkaufen/' . $angebotId . '?fehler=' . $fehler->schluessel());
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

        $sitzung = $this->sitzung($anfrage);

        if ($sitzung === null) {
            return Response::weiterleitung('/anmelden');
        }

        $db = $this->datenbank();

        if ($db === null) {
            return $this->angebotSeite($anfrage, $angebotId, 'allgemein');
        }

        $kaeuferId = (int) $sitzung['benutzer_id'];

        if (!(new Konten($db))->hatFaehigkeit($kaeuferId, Konten::FAEHIGKEIT_KAUFEN)) {
            return $this->angebotSeite($anfrage, $angebotId, 'kaufen_gesperrt');
        }

        $angebot = (new Angebote($db))->laden($angebotId);

        if ($angebot === null || (string) $angebot['status'] !== Angebote::STATUS_AKTIV) {
            return $this->angebotSeite($anfrage, $angebotId, 'angebot');
        }

        $verkaeuferId = (int) $angebot['verkaeufer_id'];
        $verkaeufer = $db->eine('SELECT status FROM benutzer WHERE id = :id', ['id' => $verkaeuferId]);

        if ($verkaeufer === null || (string) $verkaeufer['status'] !== 'aktiv') {
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

    private function datenbank(): ?Database
    {
        try {
            return Database::ausEnv();
        } catch (\Throwable) {
            // Ein Datenbankausfall darf nicht die ganze Seite mitreissen —
            // die Vorlagen zeigen dann eine ehrliche Stoerungsmeldung.
            return null;
        }
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

    /** @return array<string,mixed>|null */
    private function sitzung(Request $anfrage): ?array
    {
        $kennung = $_COOKIE[Sitzungen::COOKIE] ?? null;

        if (!is_string($kennung) || $kennung === '') {
            return null;
        }

        try {
            return (new Sitzungen(Database::ausEnv()))->laden($kennung);
        } catch (\Throwable) {
            return null;
        }
    }
}
