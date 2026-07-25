<?php

declare(strict_types=1);

namespace MeinSlip\Core;

final class Request
{
    /**
     * @param array<string,mixed> $abfrage
     * @param array<string,mixed> $formular
     * @param array<string,string> $kopfzeilen
     */
    private function __construct(
        public readonly string $methode,
        public readonly string $pfad,
        public readonly array $abfrage,
        public readonly array $formular,
        public readonly array $kopfzeilen,
    ) {
    }

    public static function ausGlobalen(): self
    {
        $pfad = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $pfad = is_string($pfad) ? $pfad : '/';
        $pfad = '/' . trim($pfad, '/');

        $kopfzeilen = [];
        foreach ($_SERVER as $schluessel => $wert) {
            if (str_starts_with((string) $schluessel, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr((string) $schluessel, 5)));
                $kopfzeilen[$name] = (string) $wert;
            }
        }

        return new self(
            methode: strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            pfad: $pfad,
            abfrage: $_GET,
            formular: $_POST,
            kopfzeilen: $kopfzeilen,
        );
    }

    /**
     * Baut eine Anfrage von Hand — fuer Tests und interne Aufrufe.
     *
     * @param array<string,mixed> $formular
     * @param array<string,string> $kopfzeilen Namen werden kleingeschrieben,
     *        damit sie wie bei ausGlobalen() nachgeschlagen werden koennen.
     */
    public static function erzeugen(
        string $methode,
        string $pfad,
        array $formular = [],
        array $kopfzeilen = []
    ): self {
        $normalisiert = [];
        foreach ($kopfzeilen as $name => $wert) {
            $normalisiert[strtolower((string) $name)] = (string) $wert;
        }

        return new self(strtoupper($methode), '/' . trim($pfad, '/'), [], $formular, $normalisiert);
    }

    public function eingabe(string $name, ?string $standard = null): ?string
    {
        $wert = $this->formular[$name] ?? $this->abfrage[$name] ?? $standard;

        return is_string($wert) ? trim($wert) : $standard;
    }

    public function ganzzahl(string $name, ?int $standard = null): ?int
    {
        $wert = $this->eingabe($name);

        return $wert === null || $wert === '' ? $standard : (int) $wert;
    }

    public function istJson(): bool
    {
        return str_contains($this->kopfzeilen['accept'] ?? '', 'application/json');
    }

    public function kopfzeile(string $name): ?string
    {
        return $this->kopfzeilen[strtolower($name)] ?? null;
    }
}
