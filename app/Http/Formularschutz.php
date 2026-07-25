<?php

declare(strict_types=1);

namespace MeinSlip\Http;

use MeinSlip\Core\Request;

/**
 * Schutz gegen seitenuebergreifende Anfragefaelschung (CSRF).
 *
 * Verfahren: doppelt gesendetes Token. Der Wert steht in einem Cookie und
 * zusaetzlich im Formular; beim Absenden muessen beide uebereinstimmen. Eine
 * fremde Seite kann zwar eine Anfrage ausloesen, aber das Cookie nicht lesen
 * und den Formularwert deshalb nicht setzen.
 *
 * Gewaehlt statt eines sitzungsgebundenen Tokens, weil Registrierung und
 * Anmeldung stattfinden, BEVOR es eine Sitzung gibt — dort waere ein
 * sitzungsgebundenes Token nicht verfuegbar.
 *
 * Das Cookie traegt SameSite=Lax: Das allein schuetzt bereits gegen die
 * meisten Faelle, das Token deckt den Rest ab.
 */
final class Formularschutz
{
    public const COOKIE = 'ms_formular';
    public const FELD = '_token';

    /** Liefert das Token und setzt es bei Bedarf als Cookie. */
    public static function token(): string
    {
        $vorhanden = $_COOKIE[self::COOKIE] ?? null;

        if (is_string($vorhanden) && strlen($vorhanden) === 64 && ctype_xdigit($vorhanden)) {
            return $vorhanden;
        }

        $token = bin2hex(random_bytes(32));

        if (!headers_sent()) {
            setcookie(self::COOKIE, $token, [
                'expires' => time() + 86400 * 30,
                'path' => '/',
                'secure' => self::verschluesselt(),
                'httponly' => false, // muss vom Formular lesbar sein
                'samesite' => 'Lax',
            ]);
        }

        $_COOKIE[self::COOKIE] = $token;

        return $token;
    }

    /** Prueft eine abgesendete Anfrage. */
    public static function gueltig(Request $anfrage): bool
    {
        $ausCookie = $_COOKIE[self::COOKIE] ?? '';
        $ausFormular = $anfrage->eingabe(self::FELD, '') ?? '';

        if (!is_string($ausCookie) || $ausCookie === '' || $ausFormular === '') {
            return false;
        }

        return hash_equals($ausCookie, $ausFormular);
    }

    /** Verstecktes Feld fuer das Formular. */
    public static function feld(): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            self::FELD,
            htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8')
        );
    }

    private static function verschluesselt(): bool
    {
        return ($_SERVER['HTTPS'] ?? '') === 'on'
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}
