<?php

declare(strict_types=1);

use MeinSlip\Core\Database;
use MeinSlip\Core\Ddl;

/**
 * Erste Kategorien.
 *
 * Bewusst als Migration und nicht als Startdatensatz: Die Schluessel sind
 * Teil der Adressen und aendern sich nie — nur so bleiben Kategorieseiten
 * stabil auffindbar. Die Bezeichnung steht in resources/lang und darf sich
 * jederzeit aendern, ohne dass eine Adresse bricht.
 *
 * Das ist der Discovery-Motor: Die Marktanalyse zeigt, dass eine tiefe
 * Taxonomie allein Kaufabsicht traegt, waehrend der groesste Anbieter mit
 * Millionen Creator praktisch keine Suchfunktion hat. Hier fangen wir klein
 * an — die Tiefe kommt, sobald es Angebote gibt, die sie fuellen.
 */
return new class {
    public function bezeichnung(): string
    {
        return 'Erste Kategorien';
    }

    public function hoch(Database $db, Ddl $d): void
    {
        $baum = [
            ['waesche', 'waesche', null, 10],
            ['waesche_slips', 'waesche/slips', 'waesche', 11],
            ['waesche_strumpfhosen', 'waesche/strumpfhosen', 'waesche', 12],
            ['waesche_bhs', 'waesche/bhs', 'waesche', 13],
            ['socken', 'socken', null, 20],
            ['socken_sport', 'socken/sport', 'socken', 21],
            ['socken_kniestruempfe', 'socken/kniestruempfe', 'socken', 22],
            ['schuhe', 'schuhe', null, 30],
            ['sonstiges', 'sonstiges', null, 90],
        ];

        $kennungen = [];

        foreach ($baum as [$schluessel, $pfad, $eltern, $reihenfolge]) {
            $kennungen[$schluessel] = $db->einfuegen('kategorien', [
                'eltern_id' => $eltern === null ? null : $kennungen[$eltern],
                'schluessel' => $schluessel,
                'pfad' => $pfad,
                'reihenfolge' => $reihenfolge,
                'aktiv' => 1,
                'angelegt_am' => gmdate('Y-m-d H:i:s'),
            ]);
        }
    }
};
