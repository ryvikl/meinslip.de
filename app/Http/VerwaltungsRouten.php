<?php

declare(strict_types=1);

namespace MeinSlip\Http;

use MeinSlip\Core\Database;
use MeinSlip\Core\Lang;
use MeinSlip\Core\Request;
use MeinSlip\Core\Response;
use MeinSlip\Core\Router;
use MeinSlip\Core\View;
use MeinSlip\Domain\Account\Konten;
use MeinSlip\Domain\Account\Sitzungen;
use MeinSlip\Domain\Admin\Verwaltung;
use MeinSlip\Domain\Admin\VerwaltungsFehler;
use MeinSlip\Domain\Catalog\AngebotFehler;
use MeinSlip\Domain\Catalog\Angebote;
use MeinSlip\Domain\Ledger\Hauptbuch;

/**
 * Alle Routen des Verwaltungsbereichs.
 *
 * Eigene Klasse statt weiterer Methoden in Routen: Der Bereich hat ein eigenes
 * Layout, eine eigene Sprachdatei und vor allem eine eigene Zugangsregel. Wer
 * ihn spaeter abschaltet, entfernt eine Zeile im Einstiegspunkt.
 *
 * ZUGANG. Jede Route dieses Bereichs — auch jede schreibende — laeuft durch
 * geschuetzt(). Fehlt die Sitzung oder die Faehigkeit 'verwalten', ist die
 * Antwort 404 und nicht 403. Eine 403 wuerde bestaetigen, dass es den Bereich
 * gibt; wer ihn sucht, bekommt genau die Antwort, die auch ein erfundener Pfad
 * bekommt. Deshalb ist die Pruefung genau EINMAL geschrieben und keine Route
 * registriert sich an ihr vorbei.
 *
 * ZUSTELLUNG. Jede beschraenkende Entscheidung — Angebot abgelehnt, Konto
 * gesperrt, Faehigkeit entzogen — muss der betroffenen Person nach Art. 17
 * Abs. 1 DSA begruendet werden. Ein Protokolleintrag erfuellt das nicht: Er
 * belegt, DASS begruendet wurde, nicht, dass die Begruendung angekommen ist.
 * Wirkung, Protokolleintrag und Zustellung gehoeren deshalb in dieselbe
 * Transaktion. Faellt eines davon aus, faellt alles aus — eine Beschraenkung
 * ohne Begruendung ist so wenig hinnehmbar wie eine Begruendung ohne
 * Beschraenkung.
 */
final class VerwaltungsRouten
{
    /** Wurzelpfad des Bereichs. An einer Stelle, damit ein Umzug eine Zeile ist. */
    private const WURZEL = '/verwaltung';

    /**
     * Handlungen fuer das Protokoll, die es in Verwaltung nicht als Konstante
     * gibt: Die Freigabe eines Angebots macht Angebote, protokolliert wird sie
     * hier.
     */
    private const HANDLUNG_ANGEBOT_FREIGEGEBEN = 'angebot_freigegeben';
    private const HANDLUNG_ANGEBOT_ABGELEHNT = 'angebot_abgelehnt';

    /**
     * Erlaubte Werte des Erfolgshinweises in der Adresszeile.
     *
     * Ohne diese Liste liesse sich ueber '?erfolg=' beliebiger Text in die
     * Uebersetzung schieben und die Seite zeigte '[[verwaltung.erfolg.x]]'.
     *
     * @var list<string>
     */
    private const ERFOLGE = [
        'faehigkeit_freigeschaltet',
        'faehigkeit_entzogen',
        'konto_gesperrt',
        'konto_entsperrt',
        'angebot_freigegeben',
        'angebot_abgelehnt',
        'meldung_bearbeitet',
    ];

    /**
     * Erlaubte Fehlerschluessel — woertlich die von VerwaltungsFehler und
     * AngebotFehler, soweit sie hier entstehen koennen, plus 'unbekannt'.
     *
     * @var list<string>
     */
    private const FEHLER = [
        'begruendung_fehlt',
        'entscheidung_fehlt',
        'handlung_fehlt',
        'handlung_zu_lang',
        'gegenstand_art_fehlt',
        'gegenstand_art_zu_lang',
        'konto_unbekannt',
        'meldung_unbekannt',
        'meldungsstatus_unbekannt',
        'faehigkeit_unbekannt',
        'faehigkeit_nicht_vergebbar',
        'selbstsperre_unzulaessig',
        'verwalter_unbekannt',
        'kein_verwaltungsrecht',
        'zeitpunkt_ungueltig',
        'angebot_unbekannt',
        'ablehnungsgrund_fehlt',
        'eigenpruefung_unzulaessig',
        'statuswechsel_unzulaessig',
        'status_unbekannt',
        'unbekannt',
    ];

    public function __construct(
        // $wurzel wird hier nicht gebraucht, die Signatur bleibt trotzdem die
        // von Routen: Der Einstiegspunkt haengt beide gleich ein.
        private readonly string $wurzel,
        private readonly View $ansicht,
    ) {
    }

    public function registrieren(Router $router): void
    {
        $this->uebersicht($router);
        $this->konten($router);
        $this->angebote($router);
        $this->meldungen($router);
        $this->nachweise($router);
    }

    // --- Uebersicht ------------------------------------------------------

    private function uebersicht(Router $router): void
    {
        $router->get(self::WURZEL, $this->geschuetzt(
            fn (Request $a, array $p, array $z): Response => $this->rendern(
                $z,
                'verwaltung.uebersicht',
                'verwaltung.uebersicht_titel',
                [
                    'aktiv' => self::WURZEL,
                    'kennzahlen' => (new Verwaltung($z['db']))->kennzahlen(),
                ]
            )
        ));
    }

    // --- Konten ----------------------------------------------------------

    private function konten(Router $router): void
    {
        $router->get(self::WURZEL . '/konten', $this->geschuetzt(
            function (Request $a, array $p, array $z): Response {
                $suche = $a->eingabe('suche', '') ?? '';

                return $this->rendern($z, 'verwaltung.konten', 'verwaltung.konten_titel', [
                    'aktiv' => self::WURZEL . '/konten',
                    'liste' => (new Verwaltung($z['db']))->konten($suche, $this->seite($a)),
                    'suche' => $suche,
                ]);
            }
        ));

        $router->get(self::WURZEL . '/konten/{id}', $this->geschuetzt(
            function (Request $a, array $p, array $z): Response {
                $id = $this->kennung($p);

                if ($id === null) {
                    return $this->nichtGefunden();
                }

                $daten = (new Verwaltung($z['db']))->konto($id);

                if ($daten === null) {
                    return $this->nichtGefunden();
                }

                return $this->rendern($z, 'verwaltung.konto', 'verwaltung.konten_titel', [
                    'aktiv' => self::WURZEL . '/konten',
                    'daten' => $daten,
                    'alleFaehigkeiten' => Konten::bekannteFaehigkeiten(),
                    'nichtVergebbar' => Konten::FAEHIGKEIT_VERWALTEN,
                    'eigenesKonto' => $z['verwalter_id'] === $id,
                ] + $this->rueckmeldung($a));
            }
        ));

        $this->schreibroute(
            $router,
            self::WURZEL . '/konten/{id}/faehigkeit',
            function (Request $a, array $p, array $z): Response {
                $id = $this->kennung($p);

                if ($id === null) {
                    return $this->nichtGefunden();
                }

                $ziel = self::WURZEL . '/konten/' . $id;

                if (!Formularschutz::gueltig($a)) {
                    return Response::weiterleitung($ziel);
                }

                // Die Handlung muss ausdruecklich dastehen. Vorher galt
                // "alles, was nicht 'entziehen' heisst" als Freischalten —
                // eine unvollstaendige oder veraenderte Anfrage hat damit
                // Rechte VERGEBEN, statt abgewiesen zu werden. Bei einer
                // Verwaltungshandlung ist der stille Rueckfall immer die
                // falsche Antwort: im Zweifel geschieht nichts.
                $handlung = $a->eingabe('handlung', '') ?? '';

                if (!in_array($handlung, ['freischalten', 'entziehen'], true)) {
                    return $this->zurueck($ziel, 'fehler', 'handlung_fehlt');
                }

                $entziehen = $handlung === 'entziehen';
                $verwaltung = new Verwaltung($z['db']);

                try {
                    if ($entziehen) {
                        $verwaltung->faehigkeitEntziehen(
                            $z['verwalter_id'],
                            $id,
                            $a->eingabe('faehigkeit', '') ?? '',
                            $a->eingabe('begruendung', '') ?? ''
                        );
                    } else {
                        $verwaltung->faehigkeitFreischalten(
                            $z['verwalter_id'],
                            $id,
                            $a->eingabe('faehigkeit', '') ?? '',
                            $a->eingabe('begruendung', '') ?? ''
                        );
                    }
                } catch (VerwaltungsFehler $fehler) {
                    return $this->zurueck($ziel, 'fehler', $fehler->schluessel());
                }

                return $this->zurueck(
                    $ziel,
                    'erfolg',
                    $entziehen ? 'faehigkeit_entzogen' : 'faehigkeit_freigeschaltet'
                );
            },
            static fn (array $p): string => self::WURZEL . '/konten/' . (int) ($p['id'] ?? 0)
        );

        $this->schreibroute(
            $router,
            self::WURZEL . '/konten/{id}/sperren',
            function (Request $a, array $p, array $z): Response {
                $id = $this->kennung($p);

                if ($id === null) {
                    return $this->nichtGefunden();
                }

                $ziel = self::WURZEL . '/konten/' . $id;

                if (!Formularschutz::gueltig($a)) {
                    return Response::weiterleitung($ziel);
                }

                // Wie bei der Faehigkeit: ausdruecklich oder gar nicht. Hier
                // wiegt es sogar schwerer — der bisherige Rueckfall SPERRTE
                // ein Konto, sobald die Handlung fehlte oder verrutscht war.
                $handlung = $a->eingabe('handlung', '') ?? '';

                if (!in_array($handlung, ['sperren', 'entsperren'], true)) {
                    return $this->zurueck($ziel, 'fehler', 'handlung_fehlt');
                }

                $entsperren = $handlung === 'entsperren';
                $verwaltung = new Verwaltung($z['db']);
                $begruendung = $a->eingabe('begruendung', '') ?? '';

                try {
                    if ($entsperren) {
                        $verwaltung->kontoEntsperren($z['verwalter_id'], $id, $begruendung);
                    } else {
                        $verwaltung->kontoSperren($z['verwalter_id'], $id, $begruendung);
                    }
                } catch (VerwaltungsFehler $fehler) {
                    return $this->zurueck($ziel, 'fehler', $fehler->schluessel());
                }

                return $this->zurueck($ziel, 'erfolg', $entsperren ? 'konto_entsperrt' : 'konto_gesperrt');
            },
            static fn (array $p): string => self::WURZEL . '/konten/' . (int) ($p['id'] ?? 0)
        );
    }

    // --- Angebote --------------------------------------------------------

    private function angebote(Router $router): void
    {
        $router->get(self::WURZEL . '/angebote', $this->geschuetzt(
            fn (Request $a, array $p, array $z): Response => $this->rendern(
                $z,
                'verwaltung.angebote',
                'verwaltung.angebote_titel',
                [
                    'aktiv' => self::WURZEL . '/angebote',
                    'liste' => (new Verwaltung($z['db']))->offeneAngebote($this->seite($a)),
                    'verwalterId' => $z['verwalter_id'],
                ] + $this->rueckmeldung($a)
            )
        ));

        $this->schreibroute(
            $router,
            self::WURZEL . '/angebote/{id}/freigeben',
            fn (Request $a, array $p, array $z): Response => $this->angebotEntscheiden($a, $p, $z, true),
            static fn (array $p): string => self::WURZEL . '/angebote'
        );

        $this->schreibroute(
            $router,
            self::WURZEL . '/angebote/{id}/ablehnen',
            fn (Request $a, array $p, array $z): Response => $this->angebotEntscheiden($a, $p, $z, false),
            static fn (array $p): string => self::WURZEL . '/angebote'
        );
    }

    /**
     * Freigabe und Ablehnung unterscheiden sich nur in einem Aufruf und einem
     * Pflichtfeld — der ganze Rahmen aus Kennung, Formularschutz, Wirkung,
     * Protokoll, Zustellung und Rueckleitung ist derselbe.
     *
     * @param array<string,string>                                             $parameter
     * @param array{db: Database, sitzung: array<string,mixed>, verwalter_id: int} $zugang
     */
    private function angebotEntscheiden(Request $anfrage, array $parameter, array $zugang, bool $freigeben): Response
    {
        $id = $this->kennung($parameter);

        if ($id === null) {
            return $this->nichtGefunden();
        }

        $ziel = self::WURZEL . '/angebote';

        if (!Formularschutz::gueltig($anfrage)) {
            return Response::weiterleitung($ziel);
        }

        $grund = $anfrage->eingabe('grund', '') ?? '';
        $angebote = new Angebote($zugang['db']);

        try {
            // EINE Transaktion um Wirkung, Protokoll und Zustellung. Vorher
            // standen die Aufrufe nebeneinander: Scheiterte der Protokolleintrag,
            // war der Statuswechsel schon geschrieben und ueber die Oberflaeche
            // nicht mehr einzuholen — der zweite Versuch scheiterte am
            // Uebergang. Die Verwalterin sah eine Fehlermeldung, das Angebot war
            // trotzdem entschieden: eine Beschraenkung, die niemand zu
            // verantworten hat. Database::transaktion() rollt bei jedem
            // Throwable zurueck und wirft weiter, der catch unten bleibt also
            // wirksam. Angebote und Verwaltung haengen beide an $zugang['db'],
            // teilen sich damit Verbindung und Transaktionstiefe.
            $zugang['db']->transaktion(function () use ($angebote, $zugang, $id, $freigeben, $grund): void {
                // Vor dem Statuswechsel gelesen: Die Empfaengerin der
                // Begruendung muss feststehen, bevor irgendetwas geschrieben
                // wird. verkaeufer_id aendert sich durch die Entscheidung nicht.
                $angebot = $angebote->laden($id);

                if ($angebot === null) {
                    // Woertlich derselbe Schluessel, den ablehnen()/freigeben()
                    // eine Zeile spaeter werfen wuerden — die Rueckmeldung
                    // bleibt damit unveraendert.
                    throw new AngebotFehler('angebot_unbekannt');
                }

                if ($freigeben) {
                    $angebote->freigeben($id, $zugang['verwalter_id']);
                } else {
                    $angebote->ablehnen($id, $zugang['verwalter_id'], $grund);
                }

                $verwaltung = new Verwaltung($zugang['db']);

                // Der Statuswechsel steht in angebote, die Verantwortung dafuer
                // nur hier. Ohne diesen Eintrag liesse sich spaeter nicht
                // belegen, wer das Angebot durchgewinkt hat.
                $ereignisId = $verwaltung->ereignisSchreiben(
                    $zugang['verwalter_id'],
                    $freigeben ? self::HANDLUNG_ANGEBOT_FREIGEGEBEN : self::HANDLUNG_ANGEBOT_ABGELEHNT,
                    Verwaltung::GEGENSTAND_ANGEBOT,
                    $id,
                    $grund
                );

                if ($freigeben) {
                    // Eine Freigabe beschraenkt nichts. Art. 17 DSA gilt fuer
                    // Beschraenkungen, nicht fuer Entscheidungen zugunsten der
                    // betroffenen Person — hier gibt es nichts zuzustellen.
                    return;
                }

                // Art. 17 Abs. 1 DSA: Die Begruendung muss der betroffenen
                // Person ZUR VERFUEGUNG GESTELLT werden. verwaltungs_ereignisse
                // wird ausschliesslich unter /verwaltung gelesen und traegt die
                // Verwalteridentitaet — es ist der interne Nachweis, nicht die
                // Zustellung. Deshalb geht derselbe Text zusaetzlich in
                // 'benachrichtigungen', verknuepft mit dem Protokolleintrag:
                // Zu jeder Beschraenkung im Journal muss eine Zustellung
                // auffindbar sein und umgekehrt. Beides in DIESER Transaktion,
                // sonst entstuende entweder eine Ablehnung ohne Begruendung
                // oder eine Begruendung ohne Ablehnung.
                //
                // VERLASS: Verwaltung::benachrichtigen() (app/Domain/Admin/
                // Verwaltung.php) mit der Signatur
                //   benachrichtigen(int $benutzerId, string $art,
                //       ?string $gegenstandArt, ?int $gegenstandId,
                //       string $begruendung, ?int $verwaltungsEreignisId): int
                // schreibt eine Zeile in 'benachrichtigungen'
                // (database/migrations/009_nachbesserung.php) und gibt deren
                // Kennung zurueck. Fehlt die Methode, bricht dieser Aufruf ab
                // und die Transaktion nimmt die Ablehnung mit zurueck — der
                // Bereich faellt geschlossen aus, es entsteht keine
                // Beschraenkung ohne Begruendung.
                $verwaltung->benachrichtigen(
                    (int) $angebot['verkaeufer_id'],
                    self::HANDLUNG_ANGEBOT_ABGELEHNT,
                    Verwaltung::GEGENSTAND_ANGEBOT,
                    $id,
                    $grund,
                    $ereignisId
                );
            });
        } catch (AngebotFehler | VerwaltungsFehler $fehler) {
            return $this->zurueck($ziel, 'fehler', $fehler->schluessel());
        }

        return $this->zurueck($ziel, 'erfolg', $freigeben ? 'angebot_freigegeben' : 'angebot_abgelehnt');
    }

    // --- Meldungen -------------------------------------------------------

    private function meldungen(Router $router): void
    {
        $router->get(self::WURZEL . '/meldungen', $this->geschuetzt(
            function (Request $a, array $p, array $z): Response {
                $status = $a->eingabe('status', '') ?? '';

                // Ein unbekannter Filterwert wird zu 'kein Filter' statt zu
                // einem Fehler: Er kommt aus der Adresszeile, nicht aus einer
                // Entscheidung.
                $gefiltert = in_array($status, Verwaltung::MELDUNGSSTATUS, true) ? $status : null;

                return $this->rendern($z, 'verwaltung.meldungen', 'verwaltung.meldungen_titel', [
                    'aktiv' => self::WURZEL . '/meldungen',
                    'liste' => (new Verwaltung($z['db']))->meldungen($gefiltert, $this->seite($a)),
                    'status' => $gefiltert,
                    'statuswerte' => Verwaltung::MELDUNGSSTATUS,
                ] + $this->rueckmeldung($a));
            }
        ));

        $this->schreibroute(
            $router,
            self::WURZEL . '/meldungen/{id}',
            function (Request $a, array $p, array $z): Response {
                $id = $this->kennung($p);

                if ($id === null) {
                    return $this->nichtGefunden();
                }

                $ziel = self::WURZEL . '/meldungen';

                if (!Formularschutz::gueltig($a)) {
                    return Response::weiterleitung($ziel);
                }

                try {
                    (new Verwaltung($z['db']))->meldungBearbeiten(
                        $z['verwalter_id'],
                        $id,
                        $a->eingabe('status', '') ?? '',
                        $a->eingabe('entscheidung', '') ?? ''
                    );
                } catch (VerwaltungsFehler $fehler) {
                    return $this->zurueck($ziel, 'fehler', $fehler->schluessel());
                }

                return $this->zurueck($ziel, 'erfolg', 'meldung_bearbeitet');
            },
            static fn (array $p): string => self::WURZEL . '/meldungen'
        );
    }

    // --- Protokoll und Hauptbuch -----------------------------------------

    private function nachweise(Router $router): void
    {
        $router->get(self::WURZEL . '/protokoll', $this->geschuetzt(
            fn (Request $a, array $p, array $z): Response => $this->rendern(
                $z,
                'verwaltung.protokoll',
                'verwaltung.protokoll_titel',
                [
                    'aktiv' => self::WURZEL . '/protokoll',
                    'liste' => (new Verwaltung($z['db']))->ereignisse($this->seite($a)),
                ]
            )
        ));

        $router->get(self::WURZEL . '/hauptbuch', $this->geschuetzt(
            function (Request $a, array $p, array $z): Response {
                // Nur lesend, ausdruecklich: Diese Route ruft keine Methode auf,
                // die eine Buchung erzeugen oder aendern koennte.
                $hauptbuch = new Hauptbuch($z['db']);

                return $this->rendern($z, 'verwaltung.hauptbuch', 'verwaltung.hauptbuch_titel', [
                    'aktiv' => self::WURZEL . '/hauptbuch',
                    'liste' => (new Verwaltung($z['db']))->hauptbuchVorgaenge($this->seite($a)),
                    'abweichung' => $hauptbuch->abweichung(),
                ]);
            }
        ));
    }

    // --- Zugang ----------------------------------------------------------

    /**
     * Legt die Zugangspruefung um eine Route.
     *
     * Jede Route dieses Bereichs wird ausschliesslich so registriert. Die
     * Pruefung steht damit an einer einzigen Stelle und kann in keiner Route
     * vergessen werden — anders als eine Zeile, die man am Anfang jeder
     * Closure wiederholen muesste.
     *
     * @param callable(Request, array<string,string>, array{db: Database, sitzung: array<string,mixed>, verwalter_id: int}): Response $arbeit
     *
     * @return callable(Request, array<string,string>): Response
     */
    private function geschuetzt(callable $arbeit): callable
    {
        return function (Request $anfrage, array $parameter = []) use ($arbeit): Response {
            $zugang = $this->zugang();

            if ($zugang === null) {
                return $this->nichtGefunden();
            }

            return $arbeit($anfrage, $parameter, $zugang);
        };
    }

    /**
     * Registriert eine schreibende Route.
     *
     * Neben dem POST entsteht ein GET auf denselben Pfad, der nur zurueckleitet.
     * Ohne ihn antwortet der Router auf ein GET mit 405 statt 404 — und die 405
     * verraet, dass es den Pfad gibt, waehrend der ganze Bereich sonst 404
     * antwortet. Der Zwilling nimmt zusaetzlich einem Neuladen die Wirkung.
     *
     * @param callable(Request, array<string,string>, array{db: Database, sitzung: array<string,mixed>, verwalter_id: int}): Response $arbeit
     * @param callable(array<string,string>): string                                                                                 $rueckweg
     */
    private function schreibroute(Router $router, string $muster, callable $arbeit, callable $rueckweg): void
    {
        $router->post($muster, $this->geschuetzt($arbeit));

        $router->get($muster, $this->geschuetzt(
            static fn (Request $a, array $p, array $z): Response => Response::weiterleitung($rueckweg($p))
        ));
    }

    /**
     * Sitzung und Verwaltungsrecht.
     *
     * Fehlt eines von beidem, gibt es keinen Zugang — und zwar ohne Unterschied
     * in der Antwort. Auch ein Datenbankausfall endet hier bei null: Der Bereich
     * faellt geschlossen aus, nicht offen.
     *
     * @return array{db: Database, sitzung: array<string,mixed>, verwalter_id: int}|null
     */
    private function zugang(): ?array
    {
        $kennung = $_COOKIE[Sitzungen::COOKIE] ?? null;

        if (!is_string($kennung) || $kennung === '') {
            return null;
        }

        try {
            $db = Database::ausEnv();
            $sitzung = (new Sitzungen($db))->laden($kennung);

            if ($sitzung === null) {
                return null;
            }

            // Sitzungen::laden() liefert die Benutzerkennung als 'benutzer_id';
            // 'id' waere die der Sitzungszeile.
            $benutzerId = (int) $sitzung['benutzer_id'];

            if (!(new Konten($db))->hatFaehigkeit($benutzerId, Konten::FAEHIGKEIT_VERWALTEN)) {
                return null;
            }

            return ['db' => $db, 'sitzung' => $sitzung, 'verwalter_id' => $benutzerId];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Die Antwort fuer alles, was hier nichts zu suchen hat.
     *
     * Wortlaut und Status sind absichtlich die des Routers fuer einen
     * unbekannten Pfad (app/Core/Router.php). Eine eigene Formulierung waere
     * ein Fingerabdruck: Wer beide Antworten vergleicht, wuesste wieder, dass es
     * den Bereich gibt.
     */
    private function nichtGefunden(): Response
    {
        return Response::text('Nicht gefunden', 404);
    }

    // --- Hilfen ----------------------------------------------------------

    /**
     * @param array{db: Database, sitzung: array<string,mixed>, verwalter_id: int} $zugang
     * @param array<string,mixed>                                                  $daten
     */
    private function rendern(array $zugang, string $vorlage, string $titelschluessel, array $daten = []): Response
    {
        return Response::html($this->ansicht->rendern($vorlage, $daten + [
            '__layout' => 'verwaltung.layout',
            'titel' => t($titelschluessel) . ' · ' . t('verwaltung.bereich'),
            'sprache' => Lang::sprache(),
            'sitzung' => $zugang['sitzung'],
        ]));
    }

    /** Weiterleitung mit einer Rueckmeldung in der Adresszeile. */
    private function zurueck(string $ziel, string $art, string $schluessel): Response
    {
        $erlaubt = $art === 'erfolg' ? self::ERFOLGE : self::FEHLER;
        $wert = in_array($schluessel, $erlaubt, true) ? $schluessel : 'unbekannt';

        return Response::weiterleitung($ziel . '?' . $art . '=' . rawurlencode($wert));
    }

    /**
     * Liest Erfolgs- und Fehlerhinweis aus der Adresszeile.
     *
     * @return array{erfolg: string|null, fehler: string|null}
     */
    private function rueckmeldung(Request $anfrage): array
    {
        return [
            'erfolg' => $this->ausListe($anfrage->eingabe('erfolg'), self::ERFOLGE),
            'fehler' => $this->ausListe($anfrage->eingabe('fehler'), self::FEHLER),
        ];
    }

    /** @param list<string> $erlaubt */
    private function ausListe(?string $wert, array $erlaubt): ?string
    {
        return $wert !== null && in_array($wert, $erlaubt, true) ? $wert : null;
    }

    /**
     * Kennung aus dem Platzhalter.
     *
     * ctype_digit statt (int): '7abc' wuerde sonst stillschweigend zu 7 und
     * '/konten/0' zu einer Abfrage auf ein Konto, das es nie gibt.
     *
     * @param array<string,string> $parameter
     */
    private function kennung(array $parameter): ?int
    {
        $roh = $parameter['id'] ?? '';

        if (!is_string($roh) || !ctype_digit($roh)) {
            return null;
        }

        $id = (int) $roh;

        return $id > 0 ? $id : null;
    }

    private function seite(Request $anfrage): int
    {
        return max(1, $anfrage->ganzzahl('seite', 1) ?? 1);
    }
}
