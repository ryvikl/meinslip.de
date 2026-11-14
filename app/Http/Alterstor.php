<?php

declare(strict_types=1);

namespace MeinSlip\Http;

use MeinSlip\Core\Lang;
use MeinSlip\Core\Request;
use MeinSlip\Core\Response;
use MeinSlip\Core\View;

/**
 * Die Altersfrage vor dem ersten Seitenaufruf: "Bist du mindestens 18?"
 *
 * WAS DAS IST UND WAS NICHT. Eine Selbstauskunft, wie sie Seiten fuer Alkohol
 * oder Tabak zeigen. Sie haelt niemanden auf, der luegt, und sie ist KEINE
 * geschlossene Benutzergruppe nach § 4 Abs. 2 JMStV. Sie traegt deshalb nur,
 * solange die Plattform nichts Pornografisches ausliefert — und genau das
 * stellt Medien::explizitSichtbar() sicher: Als explizit markierte Bilder
 * bleiben weichgezeichnet, bis ein anerkanntes Altersverfahren angebunden ist.
 * Wer diese Sperre lockert, braucht vorher ein echtes Verfahren; dieses Tor
 * ersetzt es nicht.
 *
 * Das Tor ist NICHT dasselbe wie /altersschranke (VerifizierungsRouten). Jene
 * haengt an der Sitzung und ist der Platzhalter fuer das spaetere Verfahren;
 * dieses hier haengt an einem Cookie und steht vor allem, auch vor Gaesten.
 *
 * WARUM VOR DEM ROUTER UND NICHT IN DER VORLAGE. Ein Dialog, der ueber dem
 * fertigen Inhalt liegt, liefert den Inhalt trotzdem aus — im Quelltext, ohne
 * JavaScript, fuer jeden Abrufer. Hier entsteht die Seite gar nicht erst.
 *
 * Die Antwort "Ja" ist ein POST mit eigenem Cookie. Kein Formularschutz: Es
 * gibt nichts zu schuetzen, die Erklaerung aendert keinen Zustand auf dem
 * Server, und ein fremdes Formular koennte hoechstens das Tor fuer jemanden
 * oeffnen, der es mit einem Klick selbst oeffnen kann.
 */
final class Alterstor
{
    public const COOKIE = 'ms_alter';

    public const PFAD = '/alter';

    /** Dreissig Tage, wie die Anmeldung. */
    private const DAUER_SEKUNDEN = 86400 * 30;

    /**
     * Wege, die ohne Antwort erreichbar bleiben muessen.
     *
     * Rechtsseiten: § 5 DDG verlangt das Impressum "unmittelbar erreichbar",
     * die Kuendigung muss nach § 312k BGB ohne Huerde gehen. Melden: Art. 16
     * DSA kennt keine Vorbedingung. Der Rest ist Technik, die kein Mensch
     * aufruft — die Betriebspruefung des Deployments etwa fragt /zustand ab
     * und kann keine Frage beantworten.
     *
     * @var list<string>
     */
    private const FREI = [
        '/impressum', '/datenschutz', '/agb', '/widerruf', '/kuendigen',
        '/melden',
        '/zustand', '/offline', '/manifest.webmanifest', '/einrichten', '/deploy',
        self::PFAD,
    ];

    /**
     * Liefert die Torseite, wenn die Anfrage nicht durch darf — sonst null.
     *
     * @param array<string,mixed> $cookies in aller Regel $_COOKIE
     */
    public static function pruefen(Request $anfrage, array $cookies, View $ansicht): ?Response
    {
        if (($cookies[self::COOKIE] ?? null) === '1' || self::istFrei($anfrage->pfad)) {
            return null;
        }

        // Nur Seitenaufrufe bekommen die Frage. Ein POST ohne Cookie kommt von
        // keinem Menschen, der die Seite benutzt — er wird zur Frage geschickt.
        if ($anfrage->methode !== 'GET') {
            return Response::weiterleitung('/');
        }

        return Response::html(
            $ansicht->rendern('altersfrage', [
                'sprache' => Lang::sprache(),
                'ziel' => self::sicheresZiel($anfrage->pfad),
                'abgelehnt' => false,
            ]),
            // 200 und nicht 403: Die Person ist nicht abgewiesen, sie ist
            // gefragt. Zwischenspeichern darf das niemand — sonst saehe sie
            // die Frage nach dem Ja noch einmal.
            200,
            ['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex, nofollow']
        );
    }

    /** Die Routen des Tors: Antwort annehmen, und die Seite nach einem Nein. */
    public static function antworten(Request $anfrage, View $ansicht): ?Response
    {
        if ($anfrage->pfad !== self::PFAD) {
            return null;
        }

        if ($anfrage->methode === 'POST' && $anfrage->eingabe('antwort') === 'ja') {
            if (!headers_sent()) {
                setcookie(self::COOKIE, '1', [
                    'expires' => time() + self::DAUER_SEKUNDEN,
                    'path' => '/',
                    'secure' => ($_SERVER['HTTPS'] ?? '') === 'on'
                        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }

            return Response::weiterleitung(self::sicheresZiel((string) $anfrage->eingabe('ziel', '/')));
        }

        // Alles andere — das Nein, ein direkter Aufruf — endet auf der Seite,
        // die sagt, dass es hier nicht weitergeht.
        return Response::html(
            $ansicht->rendern('altersfrage', [
                'sprache' => Lang::sprache(),
                'ziel' => '/',
                'abgelehnt' => $anfrage->methode === 'POST',
            ]),
            200,
            ['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex, nofollow']
        );
    }

    private static function istFrei(string $pfad): bool
    {
        foreach (self::FREI as $frei) {
            if ($pfad === $frei || str_starts_with($pfad, $frei . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Nur ein Pfad auf dieser Seite. Alles andere — eine fremde Adresse, ein
     * "//host", ein Backslash — wird zur Startseite. Sonst waere das Tor ein
     * Weiterleitungsdienst fuer beliebige Ziele.
     */
    private static function sicheresZiel(string $ziel): string
    {
        if (preg_match('#^/[A-Za-z0-9/_\-.]*$#', $ziel) !== 1 || str_starts_with($ziel, '//')) {
            return '/';
        }

        return $ziel;
    }
}
