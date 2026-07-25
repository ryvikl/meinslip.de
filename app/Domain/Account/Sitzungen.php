<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Account;

use MeinSlip\Core\Database;

/**
 * Sitzungsverwaltung ueber die Datenbank statt ueber PHP-Sessions.
 *
 * Zwei Gruende: Auf geteiltem Webhosting liegen PHP-Sitzungsdateien in einem
 * gemeinsamen Verzeichnis, und eine eigene Tabelle erlaubt es, Sitzungen
 * gezielt zu beenden — etwa wenn ein Konto gesperrt wird oder jemand sein
 * Passwort aendert.
 *
 * Das Feld adult_gate_bestanden_am setzt § 4 Abs. 2 JMStV um: Die KJM
 * verlangt eine Authentifizierung bei JEDEM Nutzungsvorgang, der nicht
 * unmittelbar auf die Identifizierung folgt. Angemeldet zu sein genuegt
 * ausdruecklich nicht — deshalb ist das ein eigenes Feld und kein Nebeneffekt
 * der Anmeldung.
 */
final class Sitzungen
{
    public const COOKIE = 'ms_sitzung';

    /** Wie lange eine Sitzung gilt. */
    private const GUELTIG_STUNDEN = 720;

    /** Wie lange das Zugangs-Gate innerhalb einer Sitzung traegt. */
    public const GATE_GUELTIG_MINUTEN = 30;

    public function __construct(private readonly Database $db)
    {
    }

    /** Legt eine Sitzung an und liefert die Kennung fuer das Cookie. */
    public function starten(int $benutzerId, ?string $geraet = null, ?string $ip = null): string
    {
        $kennung = bin2hex(random_bytes(32));

        $this->db->einfuegen('sitzungen', [
            'kennung' => hash('sha256', $kennung),
            'benutzer_id' => $benutzerId,
            'geraet_fingerabdruck' => $geraet === null ? null : hash('sha256', $geraet),
            'ip_hash' => $ip === null ? null : hash('sha256', $ip),
            'adult_gate_bestanden_am' => null,
            'angelegt_am' => gmdate('Y-m-d H:i:s'),
            'laeuft_ab_am' => gmdate('Y-m-d H:i:s', time() + self::GUELTIG_STUNDEN * 3600),
        ]);

        // Zurueckgegeben wird der Klartext, gespeichert nur der Hash. Wer die
        // Datenbank liest, kann damit keine Sitzung uebernehmen.
        return $kennung;
    }

    /** @return array<string,mixed>|null Sitzung samt Konto, oder null */
    public function laden(?string $kennung): ?array
    {
        if ($kennung === null || $kennung === '') {
            return null;
        }

        return $this->db->eine(
            'SELECT s.*, b.pseudonym, b.email, b.status, b.sprache, b.land
               FROM sitzungen s
               JOIN benutzer b ON b.id = s.benutzer_id
              WHERE s.kennung = :k AND s.laeuft_ab_am > :jetzt AND b.status = :aktiv',
            [
                'k' => hash('sha256', $kennung),
                'jetzt' => gmdate('Y-m-d H:i:s'),
                'aktiv' => 'aktiv',
            ]
        );
    }

    public function beenden(?string $kennung): void
    {
        if ($kennung === null || $kennung === '') {
            return;
        }

        $this->db->ausfuehren(
            'DELETE FROM sitzungen WHERE kennung = :k',
            ['k' => hash('sha256', $kennung)]
        );
    }

    /** Beendet alle Sitzungen eines Kontos — etwa nach einer Passwortaenderung. */
    public function alleBeenden(int $benutzerId): void
    {
        $this->db->ausfuehren('DELETE FROM sitzungen WHERE benutzer_id = :b', ['b' => $benutzerId]);
    }

    /** Vermerkt, dass das Zugangs-Gate soeben bestanden wurde. */
    public function gateBestanden(string $kennung): void
    {
        $this->db->ausfuehren(
            'UPDATE sitzungen SET adult_gate_bestanden_am = :z WHERE kennung = :k',
            ['z' => gmdate('Y-m-d H:i:s'), 'k' => hash('sha256', $kennung)]
        );
    }

    /**
     * Gilt das Zugangs-Gate noch?
     *
     * @param array<string,mixed> $sitzung
     */
    public function gateGilt(array $sitzung): bool
    {
        $bestanden = $sitzung['adult_gate_bestanden_am'] ?? null;

        if (!is_string($bestanden) || $bestanden === '') {
            return false;
        }

        return strtotime($bestanden) > time() - self::GATE_GUELTIG_MINUTEN * 60;
    }

    /** Raeumt abgelaufene Sitzungen weg. Aufruf durch den Cronjob. */
    public function abgelaufeneEntfernen(): int
    {
        $anweisung = $this->db->ausfuehren(
            'DELETE FROM sitzungen WHERE laeuft_ab_am <= :jetzt',
            ['jetzt' => gmdate('Y-m-d H:i:s')]
        );

        return $anweisung->rowCount();
    }
}
