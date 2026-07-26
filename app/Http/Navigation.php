<?php

declare(strict_types=1);

namespace MeinSlip\Http;

use MeinSlip\Core\Database;
use MeinSlip\Domain\Chat\Unterhaltungen;

/**
 * Die Angaben, die JEDE Seite in der Navigationsleiste braucht.
 *
 * WARUM DAS EINE EIGENE KLASSE IST UND NICHT SECHSMAL IN rendern() STEHT.
 *
 * Es gibt sechs Routenklassen, jede mit ihrer eigenen privaten rendern(), und
 * jede reicht ihre eigene Auswahl an das Layout durch. Den Ungelesenzaehler
 * dort einzutragen hiesse, ihn sechsmal einzutragen — und die siebte Klasse,
 * die jemand anlegt, bekaeme ihn nicht. Das Ergebnis waere kein Fehler, den
 * man sieht: Die Leiste zeigte auf manchen Seiten eine Zahl und auf anderen
 * keine, und niemand koennte sagen, welches der beiden das Richtige ist.
 *
 * Deshalb fragt das Layout selbst, genau einmal je Anfrage. Eine Vorlage, die
 * eine Fachklasse aufruft, ist im Uebrigen die Ausnahme und soll es bleiben —
 * sie ist hier vertretbar, weil die Leiste kein Inhalt der Seite ist, sondern
 * ihr Rahmen, und weil dieser Rahmen auf jeder einzelnen Seite derselbe ist.
 *
 * ALLES HIER IST AUSFALLSICHER. Ein Zaehler ist Beiwerk; er darf unter keinen
 * Umstaenden eine Seite mitreissen, die ohne ihn vollstaendig waere. Jeder
 * Fehlerweg endet deshalb in 0 und nicht in einer Ausnahme.
 */
final class Navigation
{
    /**
     * Ab dieser Zahl steht "99+" statt der Zahl.
     *
     * Nicht Kosmetik: Der Platz in der unteren Leiste ist auf einem 360 px
     * breiten Telefon der knappste der ganzen Anwendung, und eine vierstellige
     * Zahl schoebe die Beschriftung aus ihrem Feld.
     */
    public const HOECHSTE_ANZEIGE = 99;

    /**
     * Das Ergebnis JE KONTO, damit die Abfrage nicht zweimal laeuft.
     *
     * Der Schluessel ist die Benutzerkennung und nicht ein einzelner Platz.
     * Im Betrieb macht das keinen Unterschied — ein PHP-Prozess bearbeitet
     * eine Anfrage —, aber ein einzelner Platz waere eine Falle fuer jeden
     * anderen Kontext: In einer Testsuite oder einem Skript, das zwei Layouts
     * hintereinander rendert, bekaeme das zweite Konto die Zahl des ersten.
     * Das faellt niemandem auf, weil eine falsche Zahl genauso aussieht wie
     * eine richtige.
     *
     * @var array<int,int>
     */
    private static array $ungelesen = [];

    /**
     * Die Zahl ungelesener Nachrichten — oder 0, wenn irgendetwas fehlt.
     *
     * @param array<string,mixed>|null $sitzung
     */
    public static function ungelesen(?array $sitzung): int
    {
        $benutzerId = (int) ($sitzung['benutzer_id'] ?? 0);

        if ($benutzerId <= 0) {
            return 0;
        }

        if (isset(self::$ungelesen[$benutzerId])) {
            return self::$ungelesen[$benutzerId];
        }

        try {
            self::$ungelesen[$benutzerId] = (new Unterhaltungen(Database::ausEnv()))
                ->ungeleseneAnzahl($benutzerId);
        } catch (\Throwable $fehler) {
            error_log('[MeinSlip/Navigation] Ungelesenzaehler: ' . $fehler->getMessage());

            self::$ungelesen[$benutzerId] = 0;
        }

        return self::$ungelesen[$benutzerId];
    }

    /**
     * Die Beschriftung des Abzeichens — oder null, wenn keines noetig ist.
     *
     * Getrennt von ungelesen(), damit die Vorlage keine Zahl formatiert. Eine
     * Vorlage, die rechnet, ist eine Vorlage, die irgendwann falsch rechnet.
     */
    public static function abzeichen(?array $sitzung): ?string
    {
        $anzahl = self::ungelesen($sitzung);

        if ($anzahl <= 0) {
            return null;
        }

        return $anzahl > self::HOECHSTE_ANZEIGE
            ? self::HOECHSTE_ANZEIGE . '+'
            : (string) $anzahl;
    }

    /**
     * Setzt den Zwischenspeicher zurueck. Nur fuer Tests.
     */
    public static function zuruecksetzen(): void
    {
        self::$ungelesen = [];
    }
}
