<?php

declare(strict_types=1);

namespace MeinSlip\Core;

/**
 * Einfache Vorlagen auf PHP-Basis.
 *
 * Ausgabe wird ueber e() maskiert. Es gibt bewusst keine Methode, die
 * ungefiltert ausgibt — wer rohes HTML braucht, schreibt es sichtbar in die
 * Vorlage.
 */
final class View
{
    public function __construct(private readonly string $verzeichnis)
    {
    }

    /** @param array<string,mixed> $daten */
    public function rendern(string $name, array $daten = []): string
    {
        $pfad = rtrim($this->verzeichnis, '/') . '/' . str_replace('.', '/', $name) . '.php';

        if (!is_readable($pfad)) {
            throw new \RuntimeException("Vorlage '{$name}' nicht gefunden ({$pfad}).");
        }

        $inhalt = $this->einbinden($pfad, $daten);

        // Eine Vorlage kann ein Layout anfordern, indem sie __layout setzt.
        // Der Schluessel muss dabei entfernt werden, sonst fordert das Layout
        // sich selbst erneut an und der Aufruf laeuft endlos.
        if (isset($daten['__layout'])) {
            $layout = (string) $daten['__layout'];
            unset($daten['__layout']);

            return $this->rendern($layout, ['inhalt' => $inhalt] + $daten);
        }

        return $inhalt;
    }

    /** @param array<string,mixed> $daten */
    private function einbinden(string $pfad, array $daten): string
    {
        $rendern = static function (string $__pfad, array $__daten): string {
            extract($__daten, EXTR_SKIP);
            ob_start();
            require $__pfad;

            return (string) ob_get_clean();
        };

        return $rendern($pfad, $daten);
    }
}
