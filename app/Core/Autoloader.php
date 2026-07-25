<?php

declare(strict_types=1);

namespace MeinSlip\Core;

/**
 * Eigener Klassenlader nach PSR-4.
 *
 * Warum das existiert: Produktiv hat die Anwendung KEINE externen
 * Abhaengigkeiten — PHPUnit ist reine Entwicklungsabhaengigkeit. Composer
 * liefert also nur den Klassenlader. Mit diesem hier laeuft die Anwendung
 * auf jedem Webhosting, ohne dass dort composer verfuegbar sein muss oder
 * ein vendor-Verzeichnis hochgeladen werden muesste.
 *
 * Ist vendor/autoload.php vorhanden, hat es Vorrang — dann gilt die von
 * Composer erzeugte, optimierte Zuordnung.
 */
final class Autoloader
{
    /** @var array<string,string> Namensraum-Praefix => Verzeichnis */
    private array $zuordnung = [];

    public function hinzufuegen(string $praefix, string $verzeichnis): void
    {
        $this->zuordnung[rtrim($praefix, '\\') . '\\'] = rtrim($verzeichnis, '/') . '/';
    }

    public function registrieren(): void
    {
        spl_autoload_register([$this, 'laden']);
    }

    public function laden(string $klasse): void
    {
        foreach ($this->zuordnung as $praefix => $verzeichnis) {
            if (!str_starts_with($klasse, $praefix)) {
                continue;
            }

            $rest = substr($klasse, strlen($praefix));
            $pfad = $verzeichnis . str_replace('\\', '/', $rest) . '.php';

            if (is_readable($pfad)) {
                require $pfad;

                return;
            }
        }
    }

    /**
     * Richtet den Klassenlader ein — bevorzugt Composer, sonst den eigenen.
     */
    public static function starten(string $wurzel): void
    {
        $composer = $wurzel . '/vendor/autoload.php';

        if (is_readable($composer)) {
            require $composer;

            return;
        }

        $lader = new self();
        $lader->hinzufuegen('MeinSlip', $wurzel . '/app');
        $lader->registrieren();
    }
}
