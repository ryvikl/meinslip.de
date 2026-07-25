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

// Produktiv hat die Anwendung keine externen Abhaengigkeiten. Ist Composer
// vorhanden, wird dessen Klassenlader genutzt, sonst der eigene — so laeuft
// sie auf jedem Webhosting ohne vendor-Verzeichnis.
require $wurzel . '/app/Core/Autoloader.php';
MeinSlip\Core\Autoloader::starten($wurzel);

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
    . '<title>' . te('allgemein.offline_titel') . '</title>'
    . '<link rel="stylesheet" href="/assets/css/app.css"></head>'
    . '<body><main class="ms-huelle ms-inhalt"><h1>' . te('allgemein.offline_titel') . '</h1>'
    . '<p>' . te('allgemein.offline_text') . '</p></main></body></html>'
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

/**
 * Einrichtung ueber den Browser.
 *
 * Damit laesst sich die Anwendung auf einem Webhosting ohne SSH-Zugang in
 * Betrieb nehmen: Dateien hochladen, .env anlegen, diese Adresse aufrufen.
 *
 * Geschuetzt durch EINRICHTUNG_TOKEN aus der .env. Ist der Wert leer, ist die
 * Seite gesperrt — es gibt bewusst keinen Weg, sie ohne Token zu erreichen.
 * Nach erfolgreicher Einrichtung sollte der Wert entfernt werden.
 */
$router->get('/einrichten', static function (Request $anfrage) use ($wurzel): Response {
    $erwartet = Env::get('EINRICHTUNG_TOKEN', '') ?? '';
    $angegeben = $anfrage->eingabe('token', '') ?? '';

    // Zeitkonstanter Vergleich, damit sich der Token nicht erraten laesst.
    if ($erwartet === '' || strlen($erwartet) < 16 || !hash_equals($erwartet, $angegeben)) {
        return Response::text(
            "Einrichtung gesperrt.\n\n"
            . "Setze EINRICHTUNG_TOKEN in der .env auf einen langen Zufallswert\n"
            . "(mindestens 16 Zeichen) und rufe diese Adresse mit ?token=... auf.\n"
            . "Erzeugen:  php -r \"echo bin2hex(random_bytes(24));\"\n",
            403
        );
    }

    $schritte = [];
    $fehler = 0;

    $pruefen = static function (string $name, bool $ok, string $hinweis = '') use (&$schritte, &$fehler): void {
        $schritte[] = ['name' => $name, 'ok' => $ok, 'hinweis' => $hinweis];
        if (!$ok) {
            $fehler++;
        }
    };

    $pruefen('PHP-Version ab 8.2', PHP_VERSION_ID >= 80200, 'gefunden: ' . PHP_VERSION);
    $pruefen('PDO mit MySQL', extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite'));
    $pruefen('storage/ beschreibbar', is_writable($wurzel . '/storage'), $wurzel . '/storage');

    // Liegt .env ausserhalb des Webverzeichnisses? Sonst ist sie abrufbar.
    $envImWeb = is_readable(__DIR__ . '/.env');
    $pruefen('.env nicht im oeffentlichen Verzeichnis', !$envImWeb,
        $envImWeb ? 'ACHTUNG: .env liegt in public/ und ist damit abrufbar!' : '');

    $db = null;
    try {
        $db = Database::ausEnv();
        $db->wert('SELECT 1');
        $pruefen('Datenbankverbindung', true);
    } catch (Throwable $f) {
        $pruefen('Datenbankverbindung', false, $f->getMessage());
    }

    $migrationen = [];
    if ($db !== null) {
        try {
            $migrationen = (new MeinSlip\Core\Migrator($db, $wurzel . '/database/migrations'))->hoch();
            $pruefen('Migrationen', true, $migrationen === []
                ? 'bereits aktuell'
                : count($migrationen) . ' ausgefuehrt: ' . implode(', ', $migrationen));
        } catch (Throwable $f) {
            $pruefen('Migrationen', false, $f->getMessage());
        }

        try {
            $abweichung = (new MeinSlip\Domain\Ledger\Hauptbuch($db))->abweichung();
            $pruefen('Hauptbuch ausgeglichen', $abweichung === 0, $abweichung . ' Cent Abweichung');
        } catch (Throwable $f) {
            $pruefen('Hauptbuch ausgeglichen', false, $f->getMessage());
        }
    }

    $zeilen = '';
    foreach ($schritte as $s) {
        $zeilen .= sprintf(
            '<li style="margin-bottom:8px"><strong style="color:%s">%s</strong> %s%s</li>',
            $s['ok'] ? '#22C55E' : '#F35B5B',
            $s['ok'] ? 'ok' : 'FEHLER',
            e($s['name']),
            $s['hinweis'] !== '' ? '<br><span style="color:#94A3B8;font-size:.9em">' . e($s['hinweis']) . '</span>' : ''
        );
    }

    $abschluss = $fehler === 0
        ? '<p style="color:#22C55E"><strong>Einrichtung abgeschlossen.</strong> '
          . 'Entferne jetzt EINRICHTUNG_TOKEN aus der .env und rufe die Startseite auf.</p>'
        : '<p style="color:#F35B5B"><strong>' . $fehler . ' Punkt(e) offen.</strong> '
          . 'Bitte beheben und die Seite neu laden.</p>';

    return Response::html(
        '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow">'
        . '<title>Einrichtung</title>'
        . '<link rel="stylesheet" href="/assets/css/app.css"></head>'
        . '<body><main class="ms-huelle ms-inhalt"><h1>Einrichtung</h1>'
        . '<ul style="list-style:none;padding:0">' . $zeilen . '</ul>'
        . $abschluss
        . '</main></body></html>',
        $fehler === 0 ? 200 : 500
    );
});

/**
 * Migrationen fuer die Deployment-Automatik.
 *
 * Nur POST, Token im Kopfzeilenfeld X-Deploy-Token. Ist DEPLOY_TOKEN nicht
 * gesetzt oder zu kurz, ist der Endpunkt gesperrt. Die Logik liegt in einer
 * eigenen Klasse, damit die Zugangspruefung testbar ist.
 */
$router->post('/deploy/migrieren', static function (Request $anfrage) use ($wurzel): Response {
    try {
        $db = Database::ausEnv();
    } catch (Throwable $fehler) {
        error_log('[MeinSlip/Deploy] Datenbankverbindung fehlgeschlagen: ' . $fehler->getMessage());

        return Response::json(['erfolg' => false, 'fehler' => 'datenbank'], 500);
    }

    $endpunkt = new MeinSlip\Http\DeployEndpunkt(
        Env::get('DEPLOY_TOKEN', '') ?? '',
        $db,
        $wurzel . '/database/migrations'
    );

    return $endpunkt->behandeln($anfrage);
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
