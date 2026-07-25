<?php

declare(strict_types=1);

namespace MeinSlip\Core;

/**
 * Uebersetzungen.
 *
 * Im Code stehen ausschliesslich Schluessel, nie deutscher Text. Sonst waere
 * die Mehrsprachigkeit spaeter ein Umbau statt einer weiteren Sprachdatei —
 * und der DE-Start soll die EU-Erweiterung nicht verbauen.
 */
final class Lang
{
    /** @var array<string, array<string,string>> */
    private static array $geladen = [];

    private static string $sprache = 'de-DE';

    private static string $rueckfall = 'de-DE';

    private static string $verzeichnis = '';

    public static function einrichten(string $verzeichnis, string $sprache = 'de-DE'): void
    {
        self::$verzeichnis = rtrim($verzeichnis, '/');
        self::$sprache = $sprache;
        self::$geladen = [];
    }

    public static function sprache(): string
    {
        return self::$sprache;
    }

    /**
     * Uebersetzt einen Schluessel der Form "bereich.schluessel".
     *
     * @param array<string,string|int> $platzhalter
     */
    public static function t(string $schluessel, array $platzhalter = []): string
    {
        [$bereich, $rest] = array_pad(explode('.', $schluessel, 2), 2, '');

        if ($rest === '') {
            return $schluessel;
        }

        $text = self::ausDatei(self::$sprache, $bereich, $rest)
            ?? self::ausDatei(self::$rueckfall, $bereich, $rest);

        if ($text === null) {
            // Fehlende Uebersetzung wird sichtbar gemacht, nicht stillschweigend
            // durch etwas Plausibles ersetzt.
            return '[[' . $schluessel . ']]';
        }

        foreach ($platzhalter as $name => $wert) {
            $text = str_replace(':' . $name, (string) $wert, $text);
        }

        return $text;
    }

    private static function ausDatei(string $sprache, string $bereich, string $schluessel): ?string
    {
        $kennung = $sprache . '/' . $bereich;

        if (!array_key_exists($kennung, self::$geladen)) {
            $pfad = self::$verzeichnis . '/' . $sprache . '/' . $bereich . '.php';
            /** @var array<string,string> $daten */
            $daten = is_readable($pfad) ? (array) require $pfad : [];
            self::$geladen[$kennung] = $daten;
        }

        $wert = self::$geladen[$kennung][$schluessel] ?? null;

        return is_string($wert) ? $wert : null;
    }
}
