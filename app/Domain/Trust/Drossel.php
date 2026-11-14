<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Trust;

use MeinSlip\Core\Database;
use MeinSlip\Core\Env;

/**
 * Begrenzt, wie oft etwas in einem Zeitfenster versucht werden darf.
 *
 * Bewusst serverseitig und an einem Merkmal, das der Absender nicht einfach
 * weglassen kann: der Adresse, gegen die er Passwoerter probiert, oder seiner
 * IP-Adresse. Ein Cookie taugt dafuer nicht — wer flutet, schickt keines mit.
 *
 * Die Grenzen stehen hier an einer Stelle, damit sie sich gemeinsam lesen
 * lassen. Sie sind grosszuegig fuer Menschen und eng fuer Skripte: Fuenf
 * falsche Passwoerter in einer Viertelstunde vertippt niemand zufaellig
 * zwanzigmal am Tag.
 *
 * Eine geteilte IP (Mobilfunk, Firmennetz) trifft mehrere Menschen zugleich.
 * Deshalb sind die IP-Grenzen deutlich weiter als die je Konto, und deshalb
 * sperrt die Drossel nie dauerhaft — sie wartet das Fenster ab.
 */
final class Drossel
{
    /** Fehlversuche je E-Mail-Adresse. */
    public const ANMELDUNG_KONTO = ['anmeldung_konto', 5, 900];

    /** Fehlversuche je IP-Adresse, ueber alle Adressen hinweg. */
    public const ANMELDUNG_IP = ['anmeldung_ip', 30, 900];

    /** Neue Konten je IP-Adresse. */
    public const REGISTRIERUNG_IP = ['registrierung_ip', 5, 3600];

    /** Meldungen ohne Konto je IP-Adresse. */
    public const MELDUNG_IP = ['meldung_ip', 5, 3600];

    /** Falsche Passwoerter beim Loeschen des eigenen Kontos. */
    public const LOESCHUNG_KONTO = ['loeschung_konto', 5, 900];

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Ob das Fenster ausgeschoepft ist. Zaehlt selbst nichts.
     *
     * @param array{0:string,1:int,2:int} $regel Zweck, Hoechstzahl, Fenster in Sekunden
     */
    public function erschoepft(array $regel, ?string $merkmal): bool
    {
        if ($merkmal === null || $merkmal === '') {
            // Ohne Merkmal laesst sich nichts zuordnen. Lieber durchlassen als
            // alle aussperren, die hinter einem Proxy ohne Adresse ankommen.
            return false;
        }

        [$zweck, $hoechstens, $fenster] = $regel;

        $anzahl = (int) $this->db->wert(
            'SELECT COUNT(*) FROM versuche WHERE schluessel = :s AND angelegt_am > :seit',
            ['s' => self::schluessel($zweck, $merkmal), 'seit' => gmdate('Y-m-d H:i:s', time() - $fenster)]
        );

        return $anzahl >= $hoechstens;
    }

    /** @param array{0:string,1:int,2:int} $regel */
    public function zaehlen(array $regel, ?string $merkmal): void
    {
        if ($merkmal === null || $merkmal === '') {
            return;
        }

        $this->db->einfuegen('versuche', [
            'schluessel' => self::schluessel($regel[0], $merkmal),
            'angelegt_am' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /** Setzt den Zaehler zurueck — nach einer gelungenen Anmeldung. */
    public function vergessen(array $regel, ?string $merkmal): void
    {
        if ($merkmal === null || $merkmal === '') {
            return;
        }

        $this->db->ausfuehren(
            'DELETE FROM versuche WHERE schluessel = :s',
            ['s' => self::schluessel($regel[0], $merkmal)]
        );
    }

    /** Entfernt alles, was aelter ist als das laengste Fenster. Fuer bin/pflege. */
    public function abgelaufeneEntfernen(): int
    {
        return $this->db->ausfuehren(
            'DELETE FROM versuche WHERE angelegt_am <= :seit',
            ['seit' => gmdate('Y-m-d H:i:s', time() - 3600)]
        )->rowCount();
    }

    /**
     * HMAC mit dem Anwendungsschluessel: Ohne ihn waere der Hash einer
     * IPv4-Adresse in Minuten durchprobiert und damit keine Pseudonymisierung.
     */
    private static function schluessel(string $zweck, string $merkmal): string
    {
        return hash_hmac(
            'sha256',
            $zweck . '|' . mb_strtolower(trim($merkmal)),
            Env::get('APP_SCHLUESSEL', '') ?: 'meinslip-ohne-schluessel'
        );
    }
}
