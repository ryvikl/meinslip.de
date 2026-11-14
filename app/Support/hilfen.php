<?php

declare(strict_types=1);

use MeinSlip\Core\Lang;

if (!function_exists('e')) {
    /** Maskiert Text fuer die HTML-Ausgabe. */
    function e(?string $wert): string
    {
        return htmlspecialchars($wert ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('t')) {
    /**
     * Uebersetzt einen Schluessel. Im Code steht nie deutscher Text.
     *
     * @param array<string,string|int> $platzhalter
     */
    function t(string $schluessel, array $platzhalter = []): string
    {
        return Lang::t($schluessel, $platzhalter);
    }
}

if (!function_exists('te')) {
    /**
     * Uebersetzt und maskiert in einem Schritt — der Normalfall in Vorlagen.
     *
     * @param array<string,string|int> $platzhalter
     */
    function te(string $schluessel, array $platzhalter = []): string
    {
        return e(Lang::t($schluessel, $platzhalter));
    }
}

if (!function_exists('asset')) {
    /**
     * Adresse einer Datei unter public/, mit Versionsmarke.
     *
     * Ohne die Marke hing der Stand der Gestaltung am Zwischenspeicher des
     * Geraets: Nach einem Deployment kam neues HTML mit altem Stylesheet an,
     * und die Seite sah zerbrochen aus, bis der Speicher zufaellig ablief. Die
     * Marke aendert sich mit der Datei — eine neue Fassung ist fuer den
     * Browser eine neue Adresse.
     */
    function asset(string $pfad): string
    {
        static $marken = [];

        if (!isset($marken[$pfad])) {
            $datei = dirname(__DIR__, 2) . '/public' . $pfad;
            $zeit = is_file($datei) ? filemtime($datei) : false;
            $groesse = is_file($datei) ? filesize($datei) : false;
            $marken[$pfad] = $zeit === false ? '' : '?v=' . substr(sha1($zeit . '-' . $groesse), 0, 10);
        }

        return $pfad . $marken[$pfad];
    }
}

if (!function_exists('geld')) {
    /**
     * Formatiert Cent als Euro-Betrag.
     *
     * Betraege werden im gesamten System als ganzzahlige Cent gefuehrt.
     * Diese Funktion ist die einzige Stelle, an der daraus Text wird.
     */
    function geld(int $cent, string $waehrung = 'EUR'): string
    {
        $zeichen = match ($waehrung) {
            'EUR' => ' €',
            'CHF' => ' CHF',
            default => ' ' . $waehrung,
        };

        return number_format($cent / 100, 2, ',', '.') . $zeichen;
    }
}

if (!function_exists('prozent')) {
    /** Formatiert Hundertstel Prozent, z. B. 1900 als "19 %". */
    function prozent(int $hundertstel): string
    {
        $wert = $hundertstel / 100;

        return rtrim(rtrim(number_format($wert, 2, ',', '.'), '0'), ',') . ' %';
    }
}
