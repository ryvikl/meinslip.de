<?php

declare(strict_types=1);

namespace MeinSlip\Core;

/**
 * Konfiguration aus einer .env-Datei.
 *
 * Zugangsdaten stehen ausschliesslich hier und niemals im Repository.
 * .env ist in .gitignore erfasst, .env.example dokumentiert die noetigen
 * Schluessel ohne Werte.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $werte = [];

    private static bool $geladen = false;

    public static function laden(string $pfad): void
    {
        self::$geladen = true;

        if (!is_readable($pfad)) {
            return;
        }

        foreach (file($pfad, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $zeile) {
            $zeile = trim($zeile);
            if ($zeile === '' || str_starts_with($zeile, '#')) {
                continue;
            }
            if (!str_contains($zeile, '=')) {
                continue;
            }

            [$schluessel, $wert] = explode('=', $zeile, 2);
            $schluessel = trim($schluessel);
            $wert = trim($wert);

            // Umschliessende Anfuehrungszeichen entfernen
            if (strlen($wert) >= 2) {
                $erstes = $wert[0];
                $letztes = $wert[strlen($wert) - 1];
                if (($erstes === '"' && $letztes === '"') || ($erstes === "'" && $letztes === "'")) {
                    $wert = substr($wert, 1, -1);
                }
            }

            self::$werte[$schluessel] = $wert;
        }
    }

    public static function get(string $schluessel, ?string $standard = null): ?string
    {
        if (!self::$geladen) {
            throw new \RuntimeException('Env::laden() wurde nicht aufgerufen.');
        }

        // Echte Umgebungsvariablen haben Vorrang — so kann der Server sie ueberschreiben.
        $ausUmgebung = getenv($schluessel);
        if ($ausUmgebung !== false && $ausUmgebung !== '') {
            return $ausUmgebung;
        }

        return self::$werte[$schluessel] ?? $standard;
    }

    /**
     * Pflichtwert. Wirft, wenn er fehlt — damit eine unvollstaendige Konfiguration
     * beim Start auffaellt und nicht erst beim ersten Zahlungsvorgang.
     */
    public static function pflicht(string $schluessel): string
    {
        $wert = self::get($schluessel);
        if ($wert === null || $wert === '') {
            throw new \RuntimeException("Pflichtwert {$schluessel} fehlt in der .env-Datei.");
        }

        return $wert;
    }

    public static function bool(string $schluessel, bool $standard = false): bool
    {
        $wert = self::get($schluessel);
        if ($wert === null) {
            return $standard;
        }

        return in_array(strtolower($wert), ['1', 'true', 'ja', 'yes', 'on'], true);
    }

    /** Nur fuer Tests. */
    public static function setzen(string $schluessel, string $wert): void
    {
        self::$geladen = true;
        self::$werte[$schluessel] = $wert;
    }
}
