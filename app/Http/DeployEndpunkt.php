<?php

declare(strict_types=1);

namespace MeinSlip\Http;

use MeinSlip\Core\Database;
use MeinSlip\Core\Migrator;
use MeinSlip\Core\Request;
use MeinSlip\Core\Response;
use MeinSlip\Domain\Ledger\Hauptbuch;

/**
 * Migrationsendpunkt fuer die Deployment-Automatik.
 *
 * Warum eine eigene Route neben /einrichten: Die Einrichtungsseite ist fuer
 * Menschen gedacht — GET, Token in der Adresszeile, HTML-Ausgabe. Adressen
 * landen aber in Serverprotokollen, Browserverlaeufen und Referrern. Fuer
 * einen Token, der dauerhaft gueltig ist, ist das der falsche Ort.
 *
 * Deshalb hier: nur POST, Token im Kopfzeilenfeld, JSON zurueck.
 *
 * Als eigene Klasse statt als Abschluss in public/index.php, damit die
 * Zugangspruefung testbar ist. Eine Zugangspruefung, die niemand testen kann,
 * ist eine Zugangspruefung, auf die man sich nicht verlassen sollte.
 */
final class DeployEndpunkt
{
    /** Kuerzere Token werden abgelehnt, egal ob sie stimmen. */
    public const MINDESTLAENGE = 24;

    public const KOPFZEILE = 'x-deploy-token';

    public function __construct(
        private readonly string $erwarteterToken,
        private readonly Database $db,
        private readonly string $migrationsVerzeichnis,
    ) {
    }

    public function behandeln(Request $anfrage): Response
    {
        if (!$this->zugangErlaubt($anfrage)) {
            // Bewusst dieselbe Antwort fuer "kein Token", "falscher Token" und
            // "Endpunkt gesperrt": Wer probiert, soll nicht erfahren, woran es lag.
            return Response::json(['fehler' => 'nicht_erlaubt'], 403);
        }

        try {
            $ausgefuehrt = (new Migrator($this->db, $this->migrationsVerzeichnis))->hoch();
        } catch (\Throwable $fehler) {
            error_log('[MeinSlip/Deploy] Migration fehlgeschlagen: ' . $fehler->getMessage());

            return Response::json([
                'erfolg' => false,
                'fehler' => 'migration_fehlgeschlagen',
                'meldung' => $fehler->getMessage(),
            ], 500);
        }

        $abweichung = (new Hauptbuch($this->db))->abweichung();

        return Response::json([
            'erfolg' => $abweichung === 0,
            'migrationen' => $ausgefuehrt,
            'anzahl' => count($ausgefuehrt),
            'hauptbuch_abweichung_cent' => $abweichung,
        ], $abweichung === 0 ? 200 : 500);
    }

    private function zugangErlaubt(Request $anfrage): bool
    {
        if ($anfrage->methode !== 'POST') {
            return false;
        }

        // Ein nicht gesetzter oder zu kurzer Token sperrt den Endpunkt ganz.
        if (strlen($this->erwarteterToken) < self::MINDESTLAENGE) {
            return false;
        }

        $angegeben = $anfrage->kopfzeile(self::KOPFZEILE) ?? '';

        return hash_equals($this->erwarteterToken, $angegeben);
    }
}
