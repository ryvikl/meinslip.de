<?php

declare(strict_types=1);

namespace MeinSlip\Core;

final class Response
{
    /** @param array<string,string> $kopfzeilen */
    private function __construct(
        public readonly int $status,
        public readonly string $inhalt,
        public readonly array $kopfzeilen = [],
    ) {
    }

    /** @param array<string,string> $kopfzeilen */
    public static function html(string $inhalt, int $status = 200, array $kopfzeilen = []): self
    {
        return new self($status, $inhalt, ['Content-Type' => 'text/html; charset=utf-8'] + $kopfzeilen);
    }

    /** @param mixed $daten */
    public static function json(mixed $daten, int $status = 200): self
    {
        return new self(
            $status,
            json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public static function weiterleitung(string $ziel, int $status = 302): self
    {
        return new self($status, '', ['Location' => $ziel]);
    }

    public static function text(string $inhalt, int $status = 200): self
    {
        return new self($status, $inhalt, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function senden(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->kopfzeilen as $name => $wert) {
                header($name . ': ' . $wert, true);
            }

            // Sicherheitskopfzeilen fuer jede Antwort.
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: no-referrer');
            header('X-Frame-Options: DENY');
            // Diskretion: Die Seite soll nicht in fremden Vorschauen auftauchen.
            header('Permissions-Policy: geolocation=(self), camera=(self), microphone=()');
        }

        echo $this->inhalt;
    }
}
