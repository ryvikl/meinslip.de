<?php

declare(strict_types=1);

namespace MeinSlip\Core;

/**
 * Schlanker Router. Unterstuetzt Platzhalter der Form {name}.
 */
final class Router
{
    /** @var list<array{methode:string, muster:string, regex:string, namen:list<string>, ziel:callable}> */
    private array $routen = [];

    public function get(string $muster, callable $ziel): void
    {
        $this->hinzufuegen('GET', $muster, $ziel);
    }

    public function post(string $muster, callable $ziel): void
    {
        $this->hinzufuegen('POST', $muster, $ziel);
    }

    private function hinzufuegen(string $methode, string $muster, callable $ziel): void
    {
        $muster = '/' . trim($muster, '/');
        $namen = [];

        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static function (array $treffer) use (&$namen): string {
                $namen[] = $treffer[1];

                return '([^/]+)';
            },
            $muster
        );

        $this->routen[] = [
            'methode' => $methode,
            'muster' => $muster,
            'regex' => '#^' . $regex . '$#',
            'namen' => $namen,
            'ziel' => $ziel,
        ];
    }

    public function behandeln(Request $anfrage): Response
    {
        $pfadVorhanden = false;

        foreach ($this->routen as $route) {
            if (!preg_match($route['regex'], $anfrage->pfad, $treffer)) {
                continue;
            }

            $pfadVorhanden = true;

            if ($route['methode'] !== $anfrage->methode) {
                continue;
            }

            array_shift($treffer);
            $parameter = array_combine($route['namen'], $treffer) ?: [];

            return ($route['ziel'])($anfrage, $parameter);
        }

        // Ein vorhandener Pfad mit falscher Methode ist 405, nicht 404 —
        // das erleichtert die Fehlersuche erheblich.
        return $pfadVorhanden
            ? Response::text('Methode nicht erlaubt', 405)
            : Response::text('Nicht gefunden', 404);
    }
}
