<?php

declare(strict_types=1);

namespace MeinSlip\Http;

use MeinSlip\Core\Database;
use MeinSlip\Core\Env;
use MeinSlip\Core\Migrator;
use MeinSlip\Core\Request;
use MeinSlip\Core\Response;
use MeinSlip\Domain\Ledger\Hauptbuch;
use MeinSlip\Domain\Media\Bilder;

/**
 * Einrichtung ueber den Browser.
 *
 * Damit laesst sich die Anwendung auf einem Webhosting ohne SSH-Zugang in
 * Betrieb nehmen. Geschuetzt durch EINRICHTUNG_TOKEN aus der .env; ist der
 * Wert leer, ist die Seite gesperrt.
 *
 * Laeuft das Deployment ueber SSH, wird sie nicht gebraucht — dann bleibt der
 * Token leer und die Route vollstaendig zu.
 */
final class Einrichtung
{
    private const MINDESTLAENGE = 16;

    public function __construct(private readonly string $wurzel)
    {
    }

    public function behandeln(Request $anfrage): Response
    {
        $erwartet = Env::get('EINRICHTUNG_TOKEN', '') ?? '';
        $angegeben = $anfrage->eingabe('token', '') ?? '';

        if (strlen($erwartet) < self::MINDESTLAENGE || !hash_equals($erwartet, $angegeben)) {
            return Response::text(
                "Einrichtung gesperrt.\n\n"
                . "Setze EINRICHTUNG_TOKEN in der .env auf einen langen Zufallswert\n"
                . "(mindestens " . self::MINDESTLAENGE . " Zeichen) und rufe diese Adresse mit ?token=... auf.\n"
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
        $pruefen('PDO vorhanden', extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite'));
        $pruefen('storage/ beschreibbar', is_writable($this->wurzel . '/storage'), $this->wurzel . '/storage');

        // Ohne GD und fileinfo nimmt Bilder::annehmen() gar nichts an — kein
        // Bild wird dann roh gespeichert. Das ist richtig so, aber es faellt
        // sonst erst der ersten Verkaeuferin auf, die ein Foto hochladen will.
        // Deshalb steht die Frage hier und nicht in einem Wartungsdokument.
        $pruefen(
            'Bildverarbeitung vorhanden',
            Bilder::verfuegbar(),
            Bilder::verfuegbar()
                ? 'lesbare Formate: ' . implode(', ', Bilder::formate())
                : 'ext-gd oder ext-fileinfo fehlt — Bilduploads werden abgewiesen'
        );

        // Liegt die .env im oeffentlichen Verzeichnis, ist sie abrufbar.
        $envImWeb = is_readable($this->wurzel . '/public/.env');
        $pruefen(
            '.env nicht im oeffentlichen Verzeichnis',
            !$envImWeb,
            $envImWeb ? 'ACHTUNG: .env liegt in public/ und ist damit abrufbar!' : ''
        );

        $db = null;
        try {
            $db = Database::ausEnv();
            $db->wert('SELECT 1');
            $pruefen('Datenbankverbindung', true);
        } catch (\Throwable $f) {
            $pruefen('Datenbankverbindung', false, $f->getMessage());
        }

        if ($db !== null) {
            try {
                $ausgefuehrt = (new Migrator($db, $this->wurzel . '/database/migrations'))->hoch();
                $pruefen('Migrationen', true, $ausgefuehrt === []
                    ? 'bereits aktuell'
                    : count($ausgefuehrt) . ' ausgefuehrt: ' . implode(', ', $ausgefuehrt));
            } catch (\Throwable $f) {
                $pruefen('Migrationen', false, $f->getMessage());
            }

            try {
                $abweichung = (new Hauptbuch($db))->abweichung();
                $pruefen('Hauptbuch ausgeglichen', $abweichung === 0, $abweichung . ' Cent Abweichung');
            } catch (\Throwable $f) {
                $pruefen('Hauptbuch ausgeglichen', false, $f->getMessage());
            }
        }

        $zeilen = '';
        foreach ($schritte as $s) {
            $zeilen .= sprintf(
                '<li style="margin-bottom:10px"><strong style="color:%s">%s</strong> %s%s</li>',
                $s['ok'] ? '#22c55e' : '#d2cefd',
                $s['ok'] ? 'ok' : 'FEHLER',
                e($s['name']),
                $s['hinweis'] !== ''
                    ? '<br><span style="color:#9397ab;font-size:.9em">' . e($s['hinweis']) . '</span>'
                    : ''
            );
        }

        $abschluss = $fehler === 0
            ? '<p style="color:#22c55e"><strong>Einrichtung abgeschlossen.</strong> '
              . 'Entferne jetzt EINRICHTUNG_TOKEN aus der .env.</p>'
            : '<p style="color:#d2cefd"><strong>' . $fehler . ' Punkt(e) offen.</strong> '
              . 'Bitte beheben und die Seite neu laden.</p>';

        return Response::html(
            '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow"><title>Einrichtung</title>'
            . '<link rel="stylesheet" href="/assets/css/app.css"></head>'
            . '<body><main class="ms-huelle ms-inhalt"><h1>Einrichtung</h1>'
            . '<ul style="list-style:none;padding:0">' . $zeilen . '</ul>'
            . $abschluss
            . '</main></body></html>',
            $fehler === 0 ? 200 : 500
        );
    }
}
