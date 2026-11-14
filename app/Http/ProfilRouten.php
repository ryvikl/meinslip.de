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
use MeinSlip\Domain\Account\KontoFehler;
use MeinSlip\Domain\Account\Konten;
use MeinSlip\Domain\Account\Kontoschliessung;
use MeinSlip\Domain\Account\ProfilLinks;
use MeinSlip\Domain\Account\Profile;
use MeinSlip\Domain\Account\Sitzungen;
use MeinSlip\Domain\Admin\Verwaltung;
use MeinSlip\Domain\Catalog\Angebote;
use MeinSlip\Domain\Media\Ausschnitt;
use MeinSlip\Domain\Media\Bilder;
use MeinSlip\Domain\Media\Medien;
use MeinSlip\Domain\Media\MedienFehler;
use MeinSlip\Domain\Trust\Drossel;
use MeinSlip\Domain\Verification\Pruefbelege;

/**
 * Der Profilbereich — und die oeffentliche Seite, die man teilen kann.
 *
 * ZWEI SEITEN DERSELBEN SACHE.
 *
 *  /profil…        der eigene Bereich, vier Reiter: Uebersicht, Profil,
 *                  Links, Einstellungen. Nur mit Anmeldung.
 *  /p/{pseudonym}  die oeffentliche Seite: Bild, Name, Vorstellung, Verweise,
 *                  die letzten Angebote, "Nachricht schreiben". Sie existiert
 *                  nur, wenn die Person sie eingeschaltet hat — die Regel
 *                  sitzt in Profile::nachPseudonym() und nicht hier.
 *
 * Die oeffentliche Seite steht wie alles hinter der Altersfrage (Alterstor)
 * und traegt das Indexierungsverbot der gesamten Plattform. "Teilen" heisst:
 * Wer den Verweis bekommt, kommt hin — nicht: Suchmaschinen finden sie.
 *
 * DAS PROFILBILD IST IMMER OEFFENTLICH und geht deshalb nie durch die
 * 'explizit'-Frage der Angebotsbilder: Es gibt keine weichgezeichnete
 * Fassung, die stattdessen gezeigt werden koennte. Die Regel steht im
 * Formular und in den AGB; durchgesetzt wird sie ueber den Meldeweg, der am
 * Profil haengt.
 *
 * Diese Klasse uebernimmt /profil, /profil/gelesen und /profil/loeschen aus
 * Routen.php. Der Router nimmt den ersten Treffer — zwei Klassen mit
 * demselben Pfad hiessen, dass eine davon still nie laeuft.
 */
final class ProfilRouten
{
    private const WURZEL = '/profil';

    private const OEFFENTLICH = '/p';

    /** Unterordner im Medienverzeichnis. Neben 'angebot', nie darin. */
    private const BILDORDNER = 'profil';

    /** So viele Angebote zeigt die oeffentliche Seite. */
    private const ANGEBOTE_AUF_PROFIL = 6;

    /** @var list<string> Rueckmeldungen, die eine Adresse tragen darf. */
    private const MELDUNGEN = [
        'gespeichert', 'bild_gespeichert', 'bild_entfernt', 'ausschnitt_gespeichert', 'kein_original', 'link_gespeichert', 'link_entfernt',
        'anzeigename_zu_lang', 'vorstellung_zu_lang', 'link_titel_fehlt', 'link_titel_zu_lang',
        'link_adresse_ungueltig', 'link_zu_viele', 'keine_datei', 'gestoert',
        'unbestaetigt', 'passwort_falsch', 'bestellung_offen', 'verwalter_bleibt', 'zu_viele_versuche',
    ];

    private ?Database $verbindung = null;

    private bool $verbindungVersucht = false;

    /** @var array<string,mixed>|null */
    private ?array $sitzungZwischen = null;

    private bool $sitzungGeladen = false;

    public function __construct(
        private readonly string $wurzel,
        private readonly View $ansicht,
    ) {
    }

    public function registrieren(Router $router): void
    {
        $router->get(self::WURZEL, fn (Request $a): Response => $this->bereich($a, 'uebersicht'));
        $router->get(self::WURZEL . '/bearbeiten', fn (Request $a): Response => $this->bereich($a, 'bearbeiten'));
        $router->get(self::WURZEL . '/links', fn (Request $a): Response => $this->bereich($a, 'links'));
        $router->get(self::WURZEL . '/einstellungen', fn (Request $a): Response => $this->bereich($a, 'einstellungen'));

        $router->post(self::WURZEL . '/bearbeiten', fn (Request $a): Response => $this->speichern($a));
        $router->post(self::WURZEL . '/sichtbarkeit', fn (Request $a): Response => $this->sichtbarkeit($a));
        $router->post(self::WURZEL . '/bild', fn (Request $a): Response => $this->bildHochladen($a));
        $router->get(self::WURZEL . '/bild/ausschnitt', fn (Request $a): Response => $this->ausschnittSeite($a));
        $router->post(self::WURZEL . '/bild/ausschnitt', fn (Request $a): Response => $this->ausschnittSpeichern($a));
        $router->get(self::WURZEL . '/bild/original', fn (Request $a): Response => $this->original($a));
        $router->post(self::WURZEL . '/bild/entfernen', fn (Request $a): Response => $this->bildEntfernen($a));
        $router->post(self::WURZEL . '/links', fn (Request $a): Response => $this->linkHinzufuegen($a));
        $router->post(
            self::WURZEL . '/links/{id}/{aktion}',
            fn (Request $a, array $p): Response => $this->linkAendern($a, (string) ($p['id'] ?? ''), (string) ($p['aktion'] ?? ''))
        );
        $router->post(self::WURZEL . '/gelesen', fn (Request $a): Response => $this->gelesen($a));
        $router->post(self::WURZEL . '/loeschen', fn (Request $a): Response => $this->loeschen($a));

        $router->get(
            self::OEFFENTLICH . '/{pseudonym}/bild',
            fn (Request $a, array $p): Response => $this->bild((string) ($p['pseudonym'] ?? ''))
        );
        $router->get(
            self::OEFFENTLICH . '/{pseudonym}',
            fn (Request $a, array $p): Response => $this->oeffentlich($a, (string) ($p['pseudonym'] ?? ''))
        );
    }

    // --- Der eigene Bereich -------------------------------------------------

    private function bereich(Request $anfrage, string $reiter): Response
    {
        $sitzung = $this->sitzung($anfrage);

        if ($sitzung === null) {
            return Response::weiterleitung('/anmelden');
        }

        $ich = (int) $sitzung['benutzer_id'];
        $db = $this->datenbank();
        $daten = [
            'reiter' => $reiter,
            'aktiv' => self::WURZEL,
            'gestoert' => $db === null,
            'profil' => null,
            'links' => [],
            'benachrichtigungen' => [],
            'angebote' => 0,
            'abzeichen' => false,
            'oeffentlicheAdresse' => null,
            'meldung' => $this->meldung($anfrage),
            'bildGrenzeBytes' => Bilder::MAX_BYTES,
            'bildFormate' => implode(',', Bilder::formate()),
            'linksHoechstens' => ProfilLinks::HOECHSTENS,
        ];

        if ($db !== null) {
            try {
                $profil = (new Profile($db))->laden($ich);
                $daten['profil'] = $profil;
                $daten['links'] = (new ProfilLinks($db))->fuer($ich);
                $daten['benachrichtigungen'] = (new Verwaltung($db))->benachrichtigungen($ich);
                $daten['angebote'] = (int) $db->wert(
                    "SELECT COUNT(*) FROM angebote WHERE verkaeufer_id = :b AND status = 'aktiv'",
                    ['b' => $ich]
                );
                $daten['abzeichen'] = (new Pruefbelege($db, Pruefbelege::verzeichnis($this->wurzel)))
                    ->abzeichenVorhanden($ich);
                $daten['oeffentlicheAdresse'] = $this->oeffentlicheAdresse((string) $profil['pseudonym']);
            } catch (\Throwable $fehler) {
                error_log('[MeinSlip/Profil] ' . $fehler->getMessage());
                $daten['gestoert'] = true;
            }
        }

        return $this->rendern($anfrage, 'profil.bereich', t('profil.titel') . ' — ' . t('allgemein.marke'), $daten);
    }

    private function speichern(Request $anfrage): Response
    {
        $ich = $this->angemeldet($anfrage);

        if ($ich === null) {
            return Response::weiterleitung(self::WURZEL . '/bearbeiten');
        }

        try {
            (new Profile($this->datenbankOderFehler()))->speichern($ich, [
                'anzeigename' => $anfrage->eingabe('anzeigename', '') ?? '',
                'vorstellung' => $anfrage->eingabe('vorstellung', '') ?? '',
            ]);
        } catch (KontoFehler $fehler) {
            return $this->zurueck('/bearbeiten', $fehler->schluessel());
        } catch (\Throwable $fehler) {
            return $this->gestoert('/bearbeiten', $fehler);
        }

        return $this->zurueck('/bearbeiten', 'gespeichert');
    }

    private function sichtbarkeit(Request $anfrage): Response
    {
        $ich = $this->angemeldet($anfrage);

        if ($ich === null) {
            return Response::weiterleitung(self::WURZEL . '/einstellungen');
        }

        try {
            (new Profile($this->datenbankOderFehler()))->speichern($ich, [
                // Das Kaestchen schickt '1' oder gar nichts.
                'oeffentlich' => $anfrage->eingabe('oeffentlich', '') === '1' ? '1' : '',
            ]);
        } catch (\Throwable $fehler) {
            return $this->gestoert('/einstellungen', $fehler);
        }

        return $this->zurueck('/einstellungen', 'gespeichert');
    }

    private function bildHochladen(Request $anfrage): Response
    {
        // Sprengt der Upload post_max_size, sind $_POST und $_FILES leer — und
        // damit auch das Formular-Token. Das ist kein Angriff, sondern eine zu
        // grosse Datei; MedienRouten erklaert es ausfuehrlich.
        if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            return $this->zurueck('/bearbeiten', 'keine_datei');
        }

        $ich = $this->angemeldet($anfrage);

        if ($ich === null) {
            return Response::weiterleitung(self::WURZEL . '/bearbeiten');
        }

        $datei = $_FILES['bild'] ?? null;

        if (!is_array($datei) || (int) ($datei['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $this->zurueck('/bearbeiten', 'keine_datei');
        }

        try {
            $db = $this->datenbankOderFehler();
            $profile = new Profile($db);
            $alt = $profile->laden($ich);

            $medienwurzel = Medien::verzeichnis($this->wurzel);
            $ergebnis = (new Bilder())->annehmen($datei, $medienwurzel . '/' . self::BILDORDNER . '/' . $ich);

            // Die weichgezeichnete Vorschau braucht ein Profilbild nicht — es
            // gibt hier keine Zone, in der sie gezeigt wuerde.
            @unlink($ergebnis['vorschau_pfad']);

            // Sofort ein Quadrat aus der Mitte: Wer die Ausschnittwahl gleich
            // wieder verlaesst, hat trotzdem ein brauchbares Profilbild.
            $quadrat = dirname($ergebnis['pfad']) . '/a-' . bin2hex(random_bytes(12)) . '.jpg';
            (new Ausschnitt())->quadrat($ergebnis['pfad'], $quadrat);

            // ZWEI DATEIEN, ZWEI SPALTEN. bild_pfad ist das Quadrat, das alle
            // sehen. bild_vorschau_pfad traegt das ganze Foto — es wird nie
            // oeffentlich ausgeliefert und dient nur dazu, den Ausschnitt
            // spaeter noch einmal anders zu waehlen. Der Spaltenname stammt aus
            // der Zeit vor dieser Funktion; Ddl kann kein ALTER TABLE.
            $profile->speichern($ich, [
                'bild_pfad' => $this->relativ($medienwurzel, $quadrat),
                'bild_vorschau_pfad' => $this->relativ($medienwurzel, $ergebnis['pfad']),
            ]);

            // Die alten Dateien erst jetzt entfernen: Scheitert der Upload,
            // bleibt das bisherige Bild stehen.
            foreach (['bild_pfad', 'bild_vorschau_pfad'] as $spalte) {
                $this->bilddateiEntfernen($db, is_string($alt[$spalte]) ? $alt[$spalte] : null);
            }
        } catch (MedienFehler $fehler) {
            return Response::weiterleitung(
                self::WURZEL . '/bearbeiten?bildfehler=' . rawurlencode($fehler->schluessel()) . '#bild'
            );
        } catch (\Throwable $fehler) {
            return $this->gestoert('/bearbeiten', $fehler);
        }

        return Response::weiterleitung(self::WURZEL . '/bild/ausschnitt');
    }

    /** Die Seite, auf der die Person den Ausschnitt waehlt. */
    private function ausschnittSeite(Request $anfrage): Response
    {
        $sitzung = $this->sitzung($anfrage);

        if ($sitzung === null) {
            return Response::weiterleitung('/anmelden');
        }

        if ($this->originalDatei((int) $sitzung['benutzer_id']) === null) {
            return $this->zurueck('/bearbeiten', 'kein_original');
        }

        return $this->rendern($anfrage, 'profil.ausschnitt', t('profil.ausschnitt_titel') . ' — ' . t('allgemein.marke'), [
            'aktiv' => self::WURZEL,
            'zoomMin' => Ausschnitt::ZOOM_MIN,
            'zoomMax' => Ausschnitt::ZOOM_MAX,
        ]);
    }

    private function ausschnittSpeichern(Request $anfrage): Response
    {
        $ich = $this->angemeldet($anfrage);

        if ($ich === null) {
            return Response::weiterleitung(self::WURZEL . '/bearbeiten');
        }

        $quelle = $this->originalDatei($ich);

        if ($quelle === null) {
            return $this->zurueck('/bearbeiten', 'kein_original');
        }

        try {
            $db = $this->datenbankOderFehler();
            $profile = new Profile($db);
            $alt = $profile->laden($ich);

            // Neue Datei mit neuem Namen statt Ueberschreiben: Die Adresse des
            // Profilbilds bleibt gleich, aber der Browser haelt es fuenf
            // Minuten vor — ein neuer Name macht die Aenderung nicht schneller
            // sichtbar, verhindert aber, dass ein halb geschriebenes Bild
            // ausgeliefert wird.
            $ziel = dirname($quelle) . '/a-' . bin2hex(random_bytes(12)) . '.jpg';

            (new Ausschnitt())->quadrat(
                $quelle,
                $ziel,
                (int) ($anfrage->ganzzahl('zoom', 100) ?? 100),
                (int) ($anfrage->ganzzahl('x', 50) ?? 50),
                (int) ($anfrage->ganzzahl('y', 50) ?? 50)
            );

            $profile->speichern($ich, ['bild_pfad' => $this->relativ(Medien::verzeichnis($this->wurzel), $ziel)]);
            $this->bilddateiEntfernen($db, is_string($alt['bild_pfad']) ? $alt['bild_pfad'] : null);
        } catch (MedienFehler $fehler) {
            return Response::weiterleitung(
                self::WURZEL . '/bearbeiten?bildfehler=' . rawurlencode($fehler->schluessel()) . '#bild'
            );
        } catch (\Throwable $fehler) {
            return $this->gestoert('/bearbeiten', $fehler);
        }

        return $this->zurueck('/bearbeiten', 'ausschnitt_gespeichert');
    }

    /**
     * Das ganze Foto — ausschliesslich fuer die Person selbst.
     *
     * Kein Pseudonym in der Adresse und keine Kennung: Welche Datei kommt,
     * entscheidet allein die Sitzung. Es gibt damit keine Adresse, die man
     * weitergeben oder durchzaehlen koennte.
     */
    private function original(Request $anfrage): Response
    {
        $sitzung = $this->sitzung($anfrage);
        $datei = $sitzung === null ? null : $this->originalDatei((int) $sitzung['benutzer_id']);

        if ($datei === null) {
            return Response::text('', 404);
        }

        return Response::datei($datei, 'image/jpeg', ['Cache-Control' => 'private, no-store']);
    }

    /** Voller Pfad des ganzen Fotos, wenn es eines gibt. */
    private function originalDatei(int $benutzerId): ?string
    {
        try {
            $db = $this->datenbankOderFehler();
            $profil = (new Profile($db))->laden($benutzerId);

            return (new Medien($db, Medien::verzeichnis($this->wurzel)))
                ->datei(is_string($profil['bild_vorschau_pfad']) ? $profil['bild_vorschau_pfad'] : null);
        } catch (\Throwable) {
            return null;
        }
    }

    private function bildEntfernen(Request $anfrage): Response
    {
        $ich = $this->angemeldet($anfrage);

        if ($ich === null) {
            return Response::weiterleitung(self::WURZEL . '/bearbeiten');
        }

        try {
            $db = $this->datenbankOderFehler();
            $profile = new Profile($db);
            $alt = $profile->laden($ich);
            $profile->speichern($ich, ['bild_pfad' => '', 'bild_vorschau_pfad' => '']);

            foreach (['bild_pfad', 'bild_vorschau_pfad'] as $spalte) {
                $this->bilddateiEntfernen($db, is_string($alt[$spalte]) ? $alt[$spalte] : null);
            }
        } catch (\Throwable $fehler) {
            return $this->gestoert('/bearbeiten', $fehler);
        }

        return $this->zurueck('/bearbeiten', 'bild_entfernt');
    }

    private function linkHinzufuegen(Request $anfrage): Response
    {
        $ich = $this->angemeldet($anfrage);

        if ($ich === null) {
            return Response::weiterleitung(self::WURZEL . '/links');
        }

        try {
            (new ProfilLinks($this->datenbankOderFehler()))->hinzufuegen(
                $ich,
                $anfrage->eingabe('titel', '') ?? '',
                $anfrage->eingabe('adresse', '') ?? ''
            );
        } catch (KontoFehler $fehler) {
            return $this->zurueck('/links', $fehler->schluessel());
        } catch (\Throwable $fehler) {
            return $this->gestoert('/links', $fehler);
        }

        return $this->zurueck('/links', 'link_gespeichert');
    }

    private function linkAendern(Request $anfrage, string $roh, string $aktion): Response
    {
        $ich = $this->angemeldet($anfrage);

        if ($ich === null || !ctype_digit($roh) || !in_array($aktion, ['entfernen', 'hoch', 'runter'], true)) {
            return Response::weiterleitung(self::WURZEL . '/links');
        }

        try {
            $links = new ProfilLinks($this->datenbankOderFehler());

            if ($aktion === 'entfernen') {
                $links->entfernen($ich, (int) $roh);

                return $this->zurueck('/links', 'link_entfernt');
            }

            $links->verschieben($ich, (int) $roh, $aktion === 'hoch' ? -1 : 1);
        } catch (\Throwable $fehler) {
            return $this->gestoert('/links', $fehler);
        }

        return Response::weiterleitung(self::WURZEL . '/links');
    }

    private function gelesen(Request $anfrage): Response
    {
        $ich = $this->angemeldet($anfrage);

        if ($ich !== null) {
            try {
                // benachrichtigungGelesen() prueft selbst, dass die Zustellung
                // zu diesem Konto gehoert.
                (new Verwaltung($this->datenbankOderFehler()))
                    ->benachrichtigungGelesen($ich, (int) ($anfrage->eingabe('id', '0') ?? '0'));
            } catch (\Throwable $fehler) {
                error_log('[MeinSlip/Profil] gelesen: ' . $fehler->getMessage());
            }
        }

        return Response::weiterleitung(self::WURZEL . '#entscheidungen');
    }

    /** Konto schliessen (Art. 17 DSGVO). */
    private function loeschen(Request $anfrage): Response
    {
        $ich = $this->angemeldet($anfrage);

        if ($ich === null) {
            return Response::weiterleitung(self::WURZEL . '/einstellungen');
        }

        // Das Kaestchen ist die zweite Hand am Hebel: Ein Passwort-Manager
        // fuellt das Feld von selbst, ankreuzen muss ein Mensch.
        if ($anfrage->eingabe('bestaetigt', '') !== '1') {
            return $this->zurueck('/einstellungen', 'unbestaetigt', 'konto-loeschen');
        }

        try {
            $db = $this->datenbankOderFehler();
            $drossel = new Drossel($db);

            if ($drossel->erschoepft(Drossel::LOESCHUNG_KONTO, (string) $ich)) {
                return $this->zurueck('/einstellungen', 'zu_viele_versuche', 'konto-loeschen');
            }

            (new Kontoschliessung(
                $db,
                new Medien($db, Medien::verzeichnis($this->wurzel)),
                new Pruefbelege($db, Pruefbelege::verzeichnis($this->wurzel)),
            ))->schliessen($ich, (string) ($anfrage->eingabe('passwort', '') ?? ''));
        } catch (KontoFehler $fehler) {
            if ($fehler->schluessel() === 'passwort_falsch') {
                // Eine offene Sitzung am fremden Geraet darf das Passwort
                // nicht beliebig oft raten.
                (new Drossel($this->datenbankOderFehler()))->zaehlen(Drossel::LOESCHUNG_KONTO, (string) $ich);
            }

            return $this->zurueck('/einstellungen', $fehler->schluessel(), 'konto-loeschen');
        } catch (\Throwable $fehler) {
            return $this->gestoert('/einstellungen', $fehler);
        }

        if (!headers_sent()) {
            setcookie(Sitzungen::COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
        }

        return Response::weiterleitung('/?konto=geloescht');
    }

    // --- Die oeffentliche Seite ---------------------------------------------

    private function oeffentlich(Request $anfrage, string $pseudonym): Response
    {
        $db = $this->datenbank();
        $profil = null;

        try {
            $profil = $db === null ? null : (new Profile($db))->nachPseudonym(rawurldecode($pseudonym));
        } catch (\Throwable $fehler) {
            error_log('[MeinSlip/Profil] oeffentlich: ' . $fehler->getMessage());
        }

        if ($db === null || $profil === null) {
            // Ein Fall fuer drei Ursachen — gibt es nicht, ist nicht aktiv, ist
            // nicht oeffentlich. Die Seite verraet nicht, welche.
            return Response::html($this->ansicht->rendern('profil.oeffentlich', [
                '__layout' => 'layout',
                'titel' => t('profil.oeffentlich_unbekannt_titel') . ' — ' . t('allgemein.marke'),
                'sprache' => Lang::sprache(),
                'sitzung' => $this->sitzung($anfrage),
                'profil' => null,
            ]), 404);
        }

        $sitzung = $this->sitzung($anfrage);
        $besitzerId = (int) $profil['benutzer_id'];
        $angebote = [];
        $abzeichen = false;

        try {
            $angebote = array_slice((new Angebote($db))->vonVerkaeufer($besitzerId), 0, self::ANGEBOTE_AUF_PROFIL);
            $vorschauen = (new Medien($db, Medien::verzeichnis($this->wurzel)))
                ->ersteVorschauZuAngeboten(array_map(static fn (array $a): int => (int) $a['id'], $angebote));

            foreach ($angebote as $nummer => $angebot) {
                $angebote[$nummer]['vorschau'] = $vorschauen[(int) $angebot['id']] ?? null;
            }

            $abzeichen = (new Pruefbelege($db, Pruefbelege::verzeichnis($this->wurzel)))
                ->abzeichenVorhanden($besitzerId);
        } catch (\Throwable $fehler) {
            // Die Seite kommt trotzdem — ohne Angebote ist sie immer noch ein Profil.
            error_log('[MeinSlip/Profil] Angebote: ' . $fehler->getMessage());
        }

        return $this->rendern($anfrage, 'profil.oeffentlich', (string) $profil['name'] . ' — ' . t('allgemein.marke'), [
            'profil' => $profil,
            'links' => (new ProfilLinks($db))->fuer($besitzerId),
            'angebote' => $angebote,
            'abzeichen' => $abzeichen,
            'eigenes' => $sitzung !== null && (int) $sitzung['benutzer_id'] === $besitzerId,
            'oeffentlicheAdresse' => $this->oeffentlicheAdresse((string) $profil['pseudonym']),
        ]);
    }

    private function bild(string $pseudonym): Response
    {
        $db = $this->datenbank();

        try {
            $profil = $db === null ? null : (new Profile($db))->nachPseudonym(rawurldecode($pseudonym));

            // Das eigene Bild sieht die Person auch, solange das Profil noch
            // nicht oeffentlich ist — sonst koennte sie es vor dem Einschalten
            // nicht pruefen.
            if ($profil === null && $db !== null) {
                $sitzung = $this->sitzungZwischen ?? $this->sitzungLaden();

                if ($sitzung !== null && (string) $sitzung['pseudonym'] === rawurldecode($pseudonym)) {
                    $profil = (new Profile($db))->laden((int) $sitzung['benutzer_id']);
                }
            }

            $datei = $profil === null || $db === null
                ? null
                : (new Medien($db, Medien::verzeichnis($this->wurzel)))
                    ->datei(is_string($profil['bild_pfad']) ? $profil['bild_pfad'] : null);
        } catch (\Throwable) {
            $datei = null;
        }

        if ($datei === null) {
            return Response::text('', 404);
        }

        // Kurz zwischenspeichern, aber privat: Wer das Bild tauscht oder das
        // Profil abschaltet, soll das nicht einen Tag lang nicht bemerken.
        return Response::datei($datei, 'image/jpeg', ['Cache-Control' => 'private, max-age=300']);
    }

    // --- Hilfen -------------------------------------------------------------

    /** Die Kennung des angemeldeten Kontos — nur bei gueltigem Formular-Token. */
    private function angemeldet(Request $anfrage): ?int
    {
        if (!Formularschutz::gueltig($anfrage)) {
            return null;
        }

        $sitzung = $this->sitzung($anfrage);

        return $sitzung === null ? null : (int) $sitzung['benutzer_id'];
    }

    private function zurueck(string $reiter, string $meldung, string $anker = ''): Response
    {
        return Response::weiterleitung(
            self::WURZEL . $reiter . '?meldung=' . rawurlencode($meldung) . ($anker === '' ? '' : '#' . $anker)
        );
    }

    private function gestoert(string $reiter, \Throwable $fehler): Response
    {
        error_log('[MeinSlip/Profil] ' . $fehler->getMessage());

        return $this->zurueck($reiter, 'gestoert');
    }

    /**
     * Die Rueckmeldung aus der Adresse — nur bekannte Schluessel. Alles andere
     * waere ein frei waehlbarer Text, den jemand per Verweis unterschiebt.
     *
     * @return array{art:string, schluessel:string, werte:array<string,int>}|null
     */
    private function meldung(Request $anfrage): ?array
    {
        $bild = $anfrage->eingabe('bildfehler');

        if (is_string($bild) && preg_match('/^[a-z_]{1,40}$/', $bild) === 1
            && !str_starts_with(t('medien.fehler.' . $bild), '[[')) {
            return [
                'art' => 'fehler',
                'schluessel' => 'medien.fehler.' . $bild,
                'werte' => ['mb' => intdiv(Bilder::MAX_BYTES, 1024 * 1024)],
            ];
        }

        $schluessel = $anfrage->eingabe('meldung');

        if (!is_string($schluessel) || !in_array($schluessel, self::MELDUNGEN, true)) {
            return null;
        }

        $erfolg = in_array(
            $schluessel,
            ['gespeichert', 'bild_gespeichert', 'bild_entfernt', 'ausschnitt_gespeichert', 'link_gespeichert', 'link_entfernt'],
            true
        );

        return [
            'art' => $erfolg ? 'erfolg' : 'fehler',
            'schluessel' => 'profil.meldung.' . $schluessel,
            'werte' => ['anzahl' => ProfilLinks::HOECHSTENS],
        ];
    }

    private function oeffentlicheAdresse(string $pseudonym): string
    {
        return rtrim((string) (Env::get('APP_URL', '') ?? ''), '/') . self::OEFFENTLICH . '/' . rawurlencode($pseudonym);
    }

    private function bilddateiEntfernen(Database $db, ?string $relativ): void
    {
        $voll = (new Medien($db, Medien::verzeichnis($this->wurzel)))->datei($relativ);

        // Nur innerhalb des Profilordners: Die Spalte ist ein Pfad aus der
        // Datenbank, und ein Pfad aus der Datenbank loescht nichts ausserhalb.
        if ($voll !== null && str_contains($voll, '/' . self::BILDORDNER . '/')) {
            @unlink($voll);
        }
    }

    private function relativ(string $wurzel, string $voll): string
    {
        $praefix = rtrim($wurzel, '/') . '/';

        return str_starts_with($voll, $praefix) ? substr($voll, strlen($praefix)) : $voll;
    }

    /** @throws \RuntimeException */
    private function datenbankOderFehler(): Database
    {
        return $this->datenbank() ?? throw new \RuntimeException('Datenbank nicht erreichbar.');
    }

    private function datenbank(): ?Database
    {
        if (!$this->verbindungVersucht) {
            $this->verbindungVersucht = true;

            try {
                $this->verbindung = Database::ausEnv();
            } catch (\Throwable) {
                // Ein Datenbankausfall darf nicht die ganze Seite mitreissen.
                $this->verbindung = null;
            }
        }

        return $this->verbindung;
    }

    /** @param array<string,mixed> $daten */
    private function rendern(Request $anfrage, string $vorlage, string $titel, array $daten = []): Response
    {
        $sitzung = $this->sitzung($anfrage);
        $verwalter = false;

        try {
            $db = $this->datenbank();
            $verwalter = $sitzung !== null && $db !== null
                && (new Konten($db))->hatFaehigkeit((int) $sitzung['benutzer_id'], Konten::FAEHIGKEIT_VERWALTEN);
        } catch (\Throwable) {
            // Der Verweis auf die Verwaltung ist Anzeige, keine Berechtigung.
        }

        return Response::html($this->ansicht->rendern($vorlage, $daten + [
            '__layout' => 'layout',
            'titel' => $titel,
            'sprache' => Lang::sprache(),
            'sitzung' => $sitzung,
            'verwalter' => $verwalter,
        ]));
    }

    /** @return array<string,mixed>|null */
    private function sitzung(Request $anfrage): ?array
    {
        return $this->sitzungGeladen ? $this->sitzungZwischen : $this->sitzungLaden();
    }

    /** @return array<string,mixed>|null */
    private function sitzungLaden(): ?array
    {
        $this->sitzungGeladen = true;
        $kennung = $_COOKIE[Sitzungen::COOKIE] ?? null;
        $db = is_string($kennung) && $kennung !== '' ? $this->datenbank() : null;

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
