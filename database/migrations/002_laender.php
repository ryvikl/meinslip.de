<?php

declare(strict_types=1);

use MeinSlip\Core\Database;
use MeinSlip\Core\Ddl;

/**
 * Laender-Adapter.
 *
 * Zum Start ist nur Deutschland aktiv. Die Tabelle existiert trotzdem von
 * Anfang an, damit eine spaetere Erweiterung eine Konfigurationsaenderung
 * bleibt und kein Umbau wird.
 *
 * Der Funktionsschalter uebergabe_erlaubt ist kein Komfort: In Laendern mit
 * Sexkaufverbot muss die Uebergabe-Funktion abschaltbar sein, ohne den Rest
 * der Anwendung anzufassen.
 */
return new class {
    public function bezeichnung(): string
    {
        return 'Laender-Adapter';
    }

    public function hoch(Database $db, Ddl $d): void
    {
        $db->ddl($d->tabelle('laender', [
            $d->text('code', 2),                       // ISO-3166-1 alpha-2
            $d->text('name', 80),
            $d->jaNein('aktiv', false),
            $d->ganzzahl('ust_satz_normal'),           // in Hundertstel Prozent: 1900 = 19,00 %
            $d->ganzzahl('ust_satz_ermaessigt'),
            $d->text('waehrung', 3),
            $d->text('altersverifikation_anbieter', 60, true),
            $d->text('versanddienstleister', 190, true), // kommagetrennt
            $d->text('zahlarten', 190, true),            // kommagetrennt
            $d->jaNein('uebergabe_erlaubt', false),
            $d->jaNein('treffen_erlaubt', false),
            $d->langtext('hinweis'),
            'PRIMARY KEY (code)',
        ]));

        // Deutschland: einziges aktives Land zum Start.
        $db->einfuegen('laender', [
            'code' => 'DE',
            'name' => 'Deutschland',
            'aktiv' => 1,
            'ust_satz_normal' => 1900,
            'ust_satz_ermaessigt' => 700,
            'waehrung' => 'EUR',
            'altersverifikation_anbieter' => 'kjm_positiv_bewertet',
            'versanddienstleister' => 'dhl,hermes',
            'zahlarten' => 'sepa_ueberweisung,sofortueberweisung,karte',
            'uebergabe_erlaubt' => 1,
            'treffen_erlaubt' => 1,
            'hinweis' => 'Strengster Massstab in der EU. Altersverifikation zweistufig nach KJM-AVS-Raster.',
        ]);

        // Vorbereitet, aber nicht aktiv. Die Werte sind Platzhalter und muessen
        // vor einer Freischaltung geprueft werden — insbesondere, ob die
        // Uebergabe- und Treffen-Funktionen dort zulaessig sind.
        foreach ([
            ['AT', 'Oesterreich', 2000, 1000, 'EUR'],
            ['CH', 'Schweiz', 810, 260, 'CHF'],
            ['FR', 'Frankreich', 2000, 550, 'EUR'],
            ['NL', 'Niederlande', 2100, 900, 'EUR'],
        ] as [$code, $name, $normal, $ermaessigt, $waehrung]) {
            $db->einfuegen('laender', [
                'code' => $code,
                'name' => $name,
                'aktiv' => 0,
                'ust_satz_normal' => $normal,
                'ust_satz_ermaessigt' => $ermaessigt,
                'waehrung' => $waehrung,
                'altersverifikation_anbieter' => null,
                'versanddienstleister' => null,
                'zahlarten' => null,
                'uebergabe_erlaubt' => 0,
                'treffen_erlaubt' => 0,
                'hinweis' => 'Nicht freigeschaltet. Steuersaetze und Funktionsschalter vor Aktivierung pruefen.',
            ]);
        }
    }
};
