<?php

declare(strict_types=1);

/**
 * Einziger Einstiegspunkt der Anwendung.
 *
 * Auf dem Webhosting zeigt das Dokumentenstammverzeichnis der Domain auf
 * dieses Verzeichnis. Alles ausserhalb von public/ ist damit nicht ueber das
 * Web erreichbar — zusaetzlich abgesichert durch .htaccess.
 *
 * Diese Datei richtet nur ein und liefert aus. Die Routen stehen in
 * app/Http/Routen.php.
 */

use MeinSlip\Core\Autoloader;
use MeinSlip\Core\Env;
use MeinSlip\Core\Lang;
use MeinSlip\Core\Request;
use MeinSlip\Core\Response;
use MeinSlip\Core\Router;
use MeinSlip\Core\View;
use MeinSlip\Http\MarktRouten;
use MeinSlip\Http\MeldeRouten;
use MeinSlip\Http\Routen;
use MeinSlip\Http\VerwaltungsRouten;

$wurzel = dirname(__DIR__);

// Produktiv hat die Anwendung keine externen Abhaengigkeiten. Ist Composer
// vorhanden, wird dessen Klassenlader genutzt, sonst der eigene — so laeuft
// sie auf jedem Webhosting ohne vendor-Verzeichnis.
require $wurzel . '/app/Core/Autoloader.php';
Autoloader::starten($wurzel);

require $wurzel . '/app/Support/hilfen.php';

Env::laden($wurzel . '/.env');
Lang::einrichten($wurzel . '/resources/lang', Env::get('APP_SPRACHE', 'de-DE') ?? 'de-DE');

$debug = Env::bool('APP_DEBUG', false);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED);

$router = new Router();
$ansicht = new View($wurzel . '/resources/views');

// Vier Routenklassen statt einer: Grundseiten, Marktplatz, Meldeweg,
// Verwaltung. Die Trennung ist keine Kosmetik — sie haelt den
// Verwaltungsbereich in einer eigenen Datei mit eigener Zugangspruefung,
// statt ihn zwischen oeffentliche Routen zu mischen, wo eine vergessene
// Pruefung nicht auffiele.
//
// Der Meldeweg steht bewusst daneben und nicht darin: Er ist die einzige
// Rechtfertigung dafuer, dass Angebote ohne Vorabpruefung erscheinen
// (Art. 16 DSA). Wer ihn ausbaut, muss die Vorabpruefung zurueckbauen.
//
// Die Reihenfolge ist unerheblich: Der Router vergleicht Muster der Reihe
// nach, und die vier Klassen teilen sich keinen Pfad.
(new Routen($wurzel, $ansicht))->registrieren($router);
(new MarktRouten($wurzel, $ansicht))->registrieren($router);
(new MeldeRouten($wurzel, $ansicht))->registrieren($router);
(new VerwaltungsRouten($wurzel, $ansicht))->registrieren($router);

try {
    $antwort = $router->behandeln(Request::ausGlobalen());
} catch (Throwable $fehler) {
    error_log('[MeinSlip] ' . $fehler->getMessage() . ' @ ' . $fehler->getFile() . ':' . $fehler->getLine());

    $antwort = $debug
        ? Response::text((string) $fehler, 500)
        : Response::html('<h1>' . te('allgemein.fehler_allgemein') . '</h1>', 500);
}

$antwort->senden();
