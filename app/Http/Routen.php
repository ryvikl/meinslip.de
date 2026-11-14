<?php

declare(strict_types=1);

namespace MeinSlip\Http;

use MeinSlip\Core\Database;
use MeinSlip\Core\Env;
use MeinSlip\Core\Lang;
use MeinSlip\Core\Migrator;
use MeinSlip\Core\Request;
use MeinSlip\Core\Response;
use MeinSlip\Core\Router;
use MeinSlip\Core\View;
use MeinSlip\Domain\Account\Konten;
use MeinSlip\Domain\Account\KontoFehler;
use MeinSlip\Domain\Account\Sitzungen;
use MeinSlip\Domain\Ledger\Hauptbuch;
use MeinSlip\Domain\Trust\Drossel;

/**
 * Alle Routen der Anwendung.
 *
 * Ausgelagert aus public/index.php, damit der Einstiegspunkt nur noch
 * einrichtet und ausliefert.
 */
final class Routen
{
    /**
     * Indexierungsverbot der Rechtsseiten: nicht aufnehmen, Verweisen nicht
     * folgen, keine Kopie im Zwischenspeicher, kein Textauszug im Treffer.
     */
    public const ROBOTS_RECHTSSEITE = 'noindex, nofollow, noarchive, nosnippet';

    public function __construct(
        private readonly string $wurzel,
        private readonly View $ansicht,
    ) {
    }

    public function registrieren(Router $router): void
    {
        $this->seiten($router);
        $this->konten($router);
        $this->technik($router);
    }

    // --- Seiten ----------------------------------------------------------

    private function seiten(Router $router): void
    {
        $router->get('/', fn (Request $a): Response => $this->rendern(
            $a,
            'start',
            t('allgemein.marke_lang') . ' — ' . t('allgemein.hero_titel_1') . ' ' . t('allgemein.hero_titel_2'),
            ['aktiv' => '/']
        ));

        $router->get('/entdecken', function (Request $a): Response {
            $kategorien = [];
            $angebote = 0;

            try {
                $db = Database::ausEnv();
                $kategorien = $db->alle(
                    'SELECT schluessel, pfad FROM kategorien WHERE aktiv = 1 ORDER BY reihenfolge, pfad'
                );
                $angebote = (int) $db->wert("SELECT COUNT(*) FROM angebote WHERE status = 'aktiv'");
            } catch (\Throwable) {
                // Der Katalog darf die Seite nicht mitreissen, wenn die
                // Datenbank gerade nicht erreichbar ist.
            }

            return $this->rendern($a, 'entdecken', t('entdecken.titel') . ' — ' . t('allgemein.marke'), [
                'aktiv' => '/entdecken',
                'kategorien' => $kategorien,
                'angebote' => $angebote,
            ]);
        });

        // Textseiten. Der dritte Wert sagt, ob es eine Rechtsseite ist.
        //
        // Rechtsseiten nennen die Betreibergesellschaft mit Namen und
        // Anschrift. Sie bekommen deshalb ein eigenes Indexierungsverbot, das
        // NICHT an der .htaccess haengt: Dort steht heute ein Verbot fuer die
        // ganze Seite, aber das kann zum oeffentlichen Start fallen — und dann
        // darf eine Suche nach dem Firmennamen trotzdem nicht hierher fuehren.
        // Die Seiten bleiben fuer Menschen einen Klick entfernt (Fusszeile),
        // nur Suchmaschinen nehmen sie nicht auf. Eine robots.txt-Sperre waere
        // das falsche Mittel: Wer nicht abrufen darf, sieht das Verbot nicht
        // und listet die Adresse womoeglich trotzdem.
        $textseiten = [
            '/sicherheit' => ['sicherheit', ['treuhand', 'versand', 'identitaet', 'inhalte', 'chat'], false],
            '/fuer-creator' => ['creator', ['anteil', 'adresse', 'impressum', 'zeit', 'steuer', 'stand'], false],
            '/impressum' => ['impressum', [
                'anbieter', 'vertretung', 'kontakt', 'steuer', 'verantwortlich', 'jugendschutz', 'kontaktstelle',
                'vertragspartner', 'streit', 'haftung', 'urheber', 'stand',
            ], true],
            '/datenschutz' => ['datenschutz', [
                'verantwortlich', 'grundsatz', 'besondere', 'hosting', 'konto', 'sitzung', 'cookies',
                'inhalte', 'nachrichten', 'termine', 'identitaet', 'meldungen', 'zahlung', 'empfaenger',
                'dauer', 'rechte', 'beschwerde', 'pflicht', 'stand',
            ], true],
            '/agb' => ['agb', [
                'geltung', 'konto', 'leistung', 'angebote', 'verbote', 'vertrag', 'preise', 'treuhand',
                'versand', 'treffen', 'maengel', 'moderation', 'rechte', 'haftung', 'laufzeit',
                'aenderung', 'schluss', 'stand',
            ], true],
            '/widerruf' => ['widerruf', [
                'geltung', 'recht', 'folgen', 'ausnahmen', 'digital', 'schutz', 'formular', 'stand',
            ], true],
            '/kuendigen' => ['kuendigen', ['stand', 'grundsatz'], false],
        ];

        foreach ($textseiten as $pfad => [$bereich, $abschnitte, $rechtsseite]) {
            $router->get($pfad, fn (Request $a): Response => $this->rendern(
                $a,
                'seite',
                t($bereich . '.titel') . ' — ' . t('allgemein.marke'),
                ['bereich' => $bereich, 'abschnitte' => $abschnitte, 'rechtsseite' => $rechtsseite],
                $rechtsseite ? ['X-Robots-Tag' => self::ROBOTS_RECHTSSEITE] : []
            ));
        }

        // Bereiche der unteren Navigation, die noch kein Backend haben. Sie
        // duerfen nicht ins Leere laufen — eine ehrliche Auskunft ist besser
        // als eine Fehlerseite.
        //
        // '/nachrichten' ist hier ABSICHTLICH nicht mehr dabei: Der Chat steht
        // (app/Http/NachrichtenRouten.php). Diese Klasse wird im Einstiegspunkt
        // ZUERST registriert, und der Router nimmt den ersten Treffer — ein
        // Platzhalter an dieser Stelle wuerde die echte Strecke also verdecken,
        // ohne dass irgendetwas kaputt aussieht. '/guthaben' bleibt: Es ist aus
        // Aufsichtsgruenden gesperrt und wird es auf Monate bleiben.
        foreach (['/guthaben' => 'guthaben'] as $pfad => $name) {
            $router->get($pfad, fn (Request $a): Response => $this->rendern(
                $a,
                'seite',
                t('bald.' . $name . '_titel') . ' — ' . t('allgemein.marke'),
                [
                    'aktiv' => $pfad,
                    'bereich' => 'bald',
                    'abschnitte' => [$name, $name . '_warum'],
                ]
            ));
        }

        // /profil und /p/{pseudonym} liegen in ProfilRouten.
    }

    // --- Konten ----------------------------------------------------------

    private function konten(Router $router): void
    {
        $router->get('/registrieren', fn (Request $a): Response => $this->rendern(
            $a,
            'registrieren',
            t('konto.registrieren_titel') . ' — ' . t('allgemein.marke')
        ));

        $router->post('/registrieren', function (Request $a): Response {
            if (!Formularschutz::gueltig($a)) {
                return Response::weiterleitung('/registrieren');
            }

            $pseudonym = $a->eingabe('pseudonym', '') ?? '';
            $email = $a->eingabe('email', '') ?? '';

            try {
                // Im Browser erzwingt 'required' das Kaestchen. Das hier ist
                // fuer jede Anfrage, die nicht aus dem Formular kommt.
                if ($a->eingabe('volljaehrig', '') !== '1') {
                    throw new KontoFehler('nicht_bestaetigt');
                }

                $db = Database::ausEnv();
                $drossel = new Drossel($db);
                $ip = $_SERVER['REMOTE_ADDR'] ?? null;

                // Jedes neue Konto darf sofort oeffentlich einstellen. Ohne
                // Grenze legte ein Skript davon beliebig viele an.
                if ($drossel->erschoepft(Drossel::REGISTRIERUNG_IP, $ip)) {
                    throw new KontoFehler('zu_viele_versuche');
                }

                $konten = new Konten($db);
                $id = $konten->registrieren($pseudonym, $email, $a->eingabe('passwort', '') ?? '');
                $drossel->zaehlen(Drossel::REGISTRIERUNG_IP, $ip);

                $sitzungen = new Sitzungen($db);
                $kennung = $sitzungen->starten($id, $a->kopfzeile('user-agent'), $_SERVER['REMOTE_ADDR'] ?? null);
                $this->sitzungCookieSetzen($kennung);

                return Response::weiterleitung('/');
            } catch (KontoFehler $fehler) {
                return $this->rendern($a, 'registrieren', t('konto.registrieren_titel'), [
                    'fehler' => $fehler->schluessel(),
                    'pseudonym' => $pseudonym,
                    'email' => $email,
                ]);
            }
        });

        $router->get('/anmelden', fn (Request $a): Response => $this->rendern(
            $a,
            'anmelden',
            t('konto.anmelden_titel') . ' — ' . t('allgemein.marke')
        ));

        $router->post('/anmelden', function (Request $a): Response {
            if (!Formularschutz::gueltig($a)) {
                return Response::weiterleitung('/anmelden');
            }

            $email = $a->eingabe('email', '') ?? '';
            $db = Database::ausEnv();
            $drossel = new Drossel($db);
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;

            // Gezaehlt wird je Adresse UND je IP, und zwar jeder Fehlversuch —
            // auch der gegen eine Adresse ohne Konto. Sonst verriete die
            // Sperre selbst, welche Adressen registriert sind.
            if ($drossel->erschoepft(Drossel::ANMELDUNG_KONTO, $email)
                || $drossel->erschoepft(Drossel::ANMELDUNG_IP, $ip)) {
                return $this->rendern($a, 'anmelden', t('konto.anmelden_titel'), [
                    'fehler' => 'zu_viele_versuche',
                    'email' => $email,
                ]);
            }

            $konto = (new Konten($db))->anmelden($email, $a->eingabe('passwort', '') ?? '');

            if ($konto === null) {
                $drossel->zaehlen(Drossel::ANMELDUNG_KONTO, $email);
                $drossel->zaehlen(Drossel::ANMELDUNG_IP, $ip);

                return $this->rendern($a, 'anmelden', t('konto.anmelden_titel'), [
                    'fehler' => 'anmeldung_fehlgeschlagen',
                    'email' => $email,
                ]);
            }

            $drossel->vergessen(Drossel::ANMELDUNG_KONTO, $email);

            $kennung = (new Sitzungen($db))->starten(
                (int) $konto['id'],
                $a->kopfzeile('user-agent'),
                $_SERVER['REMOTE_ADDR'] ?? null
            );
            $this->sitzungCookieSetzen($kennung);

            return Response::weiterleitung('/');
        });

        $router->post('/abmelden', function (Request $a): Response {
            // Ohne diese Pruefung meldete jede fremde Seite Besucher per
            // selbstabschickendem Formular ab.
            if (!Formularschutz::gueltig($a)) {
                return Response::weiterleitung('/');
            }

            $kennung = $_COOKIE[Sitzungen::COOKIE] ?? null;

            if (is_string($kennung)) {
                try {
                    (new Sitzungen(Database::ausEnv()))->beenden($kennung);
                } catch (\Throwable) {
                    // Abmelden darf nie an der Datenbank scheitern.
                }
            }

            $this->sitzungCookieLoeschen();

            return Response::weiterleitung('/');
        });
    }

    // --- Technik ---------------------------------------------------------

    private function technik(Router $router): void
    {
        $router->get('/offline', fn (): Response => Response::html(
            '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . te('allgemein.offline_titel') . '</title>'
            . '<link rel="stylesheet" href="/assets/css/app.css"></head>'
            . '<body><main class="ms-huelle ms-inhalt"><h1>' . te('allgemein.offline_titel') . '</h1>'
            . '<p>' . te('allgemein.offline_text') . '</p></main></body></html>'
        ));

        /**
         * Das Manifest kommt dynamisch, damit der Name auf dem Homescreen
         * waehlbar ist. Das ist der Kern des Diskretionsversprechens — ein
         * statisches Manifest kann das nicht.
         */
        $router->get('/manifest.webmanifest', static function (Request $a): Response {
            $erlaubt = ['MeinSlip', 'MS Space', 'MS Connect', 'M Platform'];
            $kurzname = $a->eingabe('name', 'MS Space') ?? 'MS Space';

            if (!in_array($kurzname, $erlaubt, true)) {
                $kurzname = 'MS Space';
            }

            return Response::json([
                'name' => 'MeinSlip',
                'short_name' => $kurzname,
                'description' => 'Diskrete Verbindungen. Sichere Transaktionen. Volle Selbstbestimmung.',
                'id' => '/',
                'start_url' => '/',
                'scope' => '/',
                'display' => 'standalone',
                'orientation' => 'portrait',
                'background_color' => '#161826',
                'theme_color' => '#161826',
                'lang' => Lang::sprache(),
                'icons' => [
                    ['src' => '/assets/symbole/app-icon.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                    ['src' => '/assets/symbole/app-icon.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
                ],
            ]);
        });

        $router->post('/deploy/migrieren', function (Request $a): Response {
            try {
                $db = Database::ausEnv();
            } catch (\Throwable $fehler) {
                error_log('[MeinSlip/Deploy] Datenbankverbindung fehlgeschlagen: ' . $fehler->getMessage());

                return Response::json(['erfolg' => false, 'fehler' => 'datenbank'], 500);
            }

            return (new DeployEndpunkt(
                Env::get('DEPLOY_TOKEN', '') ?? '',
                $db,
                $this->wurzel . '/database/migrations'
            ))->behandeln($a);
        });

        $router->get('/zustand', static function (): Response {
            $zustand = ['anwendung' => 'ok', 'zeit' => gmdate('c')];

            try {
                $db = Database::ausEnv();
                $db->wert('SELECT 1');
                $zustand['datenbank'] = 'ok';

                $abweichung = (new Hauptbuch($db))->abweichung();
                // Nur ob, nicht wie viel: Der Betrag einer Abweichung geht
                // niemanden an, der diese Adresse ohne Anmeldung abruft. Er
                // steht im Protokoll.
                $zustand['hauptbuch'] = $abweichung === 0 ? 'ausgeglichen' : 'ABWEICHUNG';

                if ($abweichung !== 0) {
                    error_log('[MeinSlip/Zustand] Hauptbuch weicht um ' . $abweichung . ' Cent ab.');
                }
            } catch (\Throwable) {
                $zustand['datenbank'] = 'fehler';
            }

            $inOrdnung = ($zustand['datenbank'] ?? '') === 'ok'
                && ($zustand['hauptbuch'] ?? '') === 'ausgeglichen';

            return Response::json($zustand, $inOrdnung ? 200 : 503);
        });

        $router->get('/einrichten', fn (Request $a): Response => (new Einrichtung($this->wurzel))->behandeln($a));
    }

    // --- Hilfen ----------------------------------------------------------

    /** @param array<string,mixed> $daten */
    private function rendern(
        Request $anfrage,
        string $vorlage,
        string $titel,
        array $daten = [],
        array $kopfzeilen = [],
    ): Response {
        $sitzung = $this->sitzung($anfrage);

        return Response::html($this->ansicht->rendern($vorlage, $daten + [
            '__layout' => 'layout',
            'titel' => $titel,
            'sprache' => Lang::sprache(),
            'sitzung' => $sitzung,
            'verwalter' => $this->istVerwalter($sitzung),
        ]), 200, $kopfzeilen);
    }

    /**
     * Ob dieser Sitzung der Verweis auf die Verwaltung gezeigt werden darf.
     *
     * Das ist reine Anzeige und ersetzt keine Pruefung: VerwaltungsRouten
     * fragt dieselbe Faehigkeit in jeder einzelnen Route erneut ab und
     * antwortet sonst mit 404. Wer die Adresse errät, kommt trotzdem nicht
     * hinein — hier geht es nur darum, ob der Weg sichtbar ist.
     *
     * @param array<string,mixed>|null $sitzung
     */
    private function istVerwalter(?array $sitzung): bool
    {
        if ($sitzung === null) {
            return false;
        }

        try {
            return (new Konten(Database::ausEnv()))
                ->hatFaehigkeit((int) $sitzung['benutzer_id'], Konten::FAEHIGKEIT_VERWALTEN);
        } catch (\Throwable) {
            return false;
        }
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

    private function sitzungCookieSetzen(string $kennung): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie(Sitzungen::COOKIE, $kennung, [
            'expires' => time() + 86400 * 30,
            'path' => '/',
            'secure' => ($_SERVER['HTTPS'] ?? '') === 'on'
                || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function sitzungCookieLoeschen(): void
    {
        if (!headers_sent()) {
            setcookie(Sitzungen::COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
        }
    }
}
