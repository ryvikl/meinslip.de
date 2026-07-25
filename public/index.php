<?php

declare(strict_types=1);

/**
 * Einziger Einstiegspunkt der Anwendung.
 *
 * Auf dem Webhosting zeigt das Dokumentenstammverzeichnis der Domain auf
 * dieses Verzeichnis. Alles ausserhalb von public/ ist damit nicht ueber das
 * Web erreichbar — zusaetzlich abgesichert durch .htaccess.
 */

use MeinSlip\Core\Database;
use MeinSlip\Core\Env;
use MeinSlip\Core\Lang;
use MeinSlip\Core\Request;
use MeinSlip\Core\Response;
use MeinSlip\Core\Router;
use MeinSlip\Core\View;

$wurzel = dirname(__DIR__);

require $wurzel . '/vendor/autoload.php';
require $wurzel . '/app/Support/hilfen.php';

Env::laden($wurzel . '/.env');
Lang::einrichten($wurzel . '/resources/lang', Env::get('APP_SPRACHE', 'de-DE') ?? 'de-DE');

$debug = Env::bool('APP_DEBUG', false);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED);

$ansicht = new View($wurzel . '/resources/views');
$router = new Router();

/** Rendert eine Seite im Layout. */
$seite = static function (string $name, string $titel, array $daten = []) use ($ansicht): Response {
    return Response::html($ansicht->rendern($name, $daten + [
        '__layout' => 'layout',
        'titel' => $titel,
        'sprache' => Lang::sprache(),
    ]));
};

// --- Seiten --------------------------------------------------------------

$router->get('/', static fn (): Response => $seite(
    'start',
    t('allgemein.marke_lang') . ' — ' . t('allgemein.hero_titel'),
    ['aktiv' => '/']
));

$router->get('/offline', static fn (): Response => Response::html(
    '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
    . '<meta name="viewport" content="width=device-width,initial-scale=1">'
    . '<title>Offline</title><link rel="stylesheet" href="/assets/css/app.css"></head>'
    . '<body><main class="ms-huelle ms-inhalt"><h1>Keine Verbindung</h1>'
    . '<p>Sobald du wieder online bist, geht es hier weiter.</p></main></body></html>'
));

/**
 * Manifest wird dynamisch ausgeliefert, damit der Name auf dem Homescreen
 * waehlbar ist. Das ist der Kern des Diskretionsversprechens: Wer die App
 * "MS Space" nennen will, soll das koennen — ein statisches Manifest kann das
 * nicht.
 */
$router->get('/manifest.webmanifest', static function (Request $anfrage): Response {
    // Spaeter aus dem angemeldeten Konto; bis dahin die neutrale Vorgabe.
    $kurzname = $anfrage->eingabe('name', 'MS Space') ?? 'MS Space';
    $erlaubt = ['MeinSlip', 'MS Space', 'MS Connect', 'M Platform'];

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
        'background_color' => '#080B14',
        'theme_color' => '#080B14',
        'lang' => Lang::sprache(),
        'icons' => [
            ['src' => '/assets/symbole/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/assets/symbole/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/assets/symbole/icon-maskiert.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ],
    ]);
});

/** Betriebspruefung — auch fuer die Ueberwachung nach dem Deployment. */
$router->get('/zustand', static function () use ($wurzel): Response {
    $zustand = ['anwendung' => 'ok', 'zeit' => gmdate('c')];

    try {
        $db = Database::ausEnv();
        $db->wert('SELECT 1');
        $zustand['datenbank'] = 'ok';

        $hauptbuch = new MeinSlip\Domain\Ledger\Hauptbuch($db);
        $abweichung = $hauptbuch->abweichung();
        $zustand['hauptbuch'] = $abweichung === 0 ? 'ausgeglichen' : 'ABWEICHUNG';
        $zustand['hauptbuch_abweichung_cent'] = $abweichung;
    } catch (Throwable $fehler) {
        $zustand['datenbank'] = 'fehler';
    }

    $inOrdnung = ($zustand['datenbank'] ?? '') === 'ok'
        && ($zustand['hauptbuch'] ?? '') === 'ausgeglichen';

    return Response::json($zustand, $inOrdnung ? 200 : 503);
});

// --- Ausliefern ----------------------------------------------------------

try {
    $antwort = $router->behandeln(Request::ausGlobalen());
} catch (Throwable $fehler) {
    error_log('[MeinSlip] ' . $fehler->getMessage() . ' @ ' . $fehler->getFile() . ':' . $fehler->getLine());

    $antwort = $debug
        ? Response::text((string) $fehler, 500)
        : Response::html('<h1>' . te('allgemein.fehler_allgemein') . '</h1>', 500);
}

$antwort->senden();
