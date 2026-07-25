<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Ledger;

use MeinSlip\Core\Database;

/**
 * Doppelte Buchfuehrung.
 *
 * Die einzige Stelle im System, an der sich Geld bewegt. Alles andere ruft
 * hier hinein.
 *
 * Drei Regeln, die diese Klasse durchsetzt:
 *
 *  1. Ein Vorgang besteht aus mindestens zwei Buchungen, deren Summe null ist.
 *     Buchungen, die das verletzen, werden abgewiesen — nicht korrigiert.
 *  2. Buchungen werden nie geaendert oder geloescht. Eine Korrektur ist eine
 *     Gegenbuchung.
 *  3. Ein Saldo wird immer berechnet, nie gespeichert.
 *
 * Betraege sind ganzzahlige Cent. Gleitkommazahlen kommen hier nicht vor.
 */
final class Hauptbuch
{
    public const KONTO_GUTHABEN = 'guthaben';
    public const KONTO_EINNAHMEN = 'einnahmen';
    public const KONTO_TREUHAND = 'treuhand';
    public const KONTO_PROVISION = 'provision';
    public const KONTO_UMSATZSTEUER = 'umsatzsteuer';
    public const KONTO_ZAHLUNGSEINGANG = 'zahlungseingang';

    /** Konten, die zu einem Benutzer gehoeren. */
    private const BENUTZERKONTEN = [self::KONTO_GUTHABEN, self::KONTO_EINNAHMEN];

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Bucht einen Vorgang. Die Summe aller Betraege muss null ergeben.
     *
     * @param list<array{konto_id:int, betrag_cent:int}> $buchungen
     * @param string|null $idempotenzSchluessel Verhindert Doppelbuchung bei
     *        wiederholtem Aufruf. Existiert der Schluessel schon, wird der
     *        vorhandene Vorgang zurueckgegeben statt erneut gebucht.
     */
    public function buchen(
        string $art,
        array $buchungen,
        ?string $bezugArt = null,
        ?int $bezugId = null,
        ?string $beschreibung = null,
        ?string $idempotenzSchluessel = null,
        string $waehrung = 'EUR'
    ): int {
        if (count($buchungen) < 2) {
            throw new BuchungsFehler('Ein Vorgang braucht mindestens zwei Buchungen.');
        }

        $summe = 0;
        foreach ($buchungen as $b) {
            if (!isset($b['konto_id'], $b['betrag_cent'])) {
                throw new BuchungsFehler('Buchung ohne konto_id oder betrag_cent.');
            }
            if ($b['betrag_cent'] === 0) {
                throw new BuchungsFehler('Buchungen ueber null Cent sind nicht zulaessig.');
            }
            $summe += $b['betrag_cent'];
        }

        if ($summe !== 0) {
            throw new BuchungsFehler(
                sprintf('Vorgang "%s" ist nicht ausgeglichen: Summe %d Cent statt 0.', $art, $summe)
            );
        }

        return $this->db->transaktion(function () use (
            $art, $buchungen, $bezugArt, $bezugId, $beschreibung, $idempotenzSchluessel, $waehrung
        ): int {
            if ($idempotenzSchluessel !== null) {
                $vorhanden = $this->db->wert(
                    'SELECT id FROM hauptbuch_vorgaenge WHERE idempotenz_schluessel = :s',
                    ['s' => $idempotenzSchluessel]
                );
                if ($vorhanden !== null) {
                    return (int) $vorhanden;
                }
            }

            $vorgangId = $this->db->einfuegen('hauptbuch_vorgaenge', [
                'art' => $art,
                'bezug_art' => $bezugArt,
                'bezug_id' => $bezugId,
                'beschreibung' => $beschreibung,
                'idempotenz_schluessel' => $idempotenzSchluessel,
                'angelegt_am' => gmdate('Y-m-d H:i:s'),
            ]);

            foreach ($buchungen as $b) {
                $this->db->einfuegen('hauptbuch_buchungen', [
                    'vorgang_id' => $vorgangId,
                    'konto_id' => $b['konto_id'],
                    'betrag_cent' => $b['betrag_cent'],
                    'waehrung' => $waehrung,
                    'angelegt_am' => gmdate('Y-m-d H:i:s'),
                ]);
            }

            return $vorgangId;
        });
    }

    /** Saldo eines Kontos in Cent. Immer berechnet, nie gespeichert. */
    public function saldo(int $kontoId): int
    {
        $wert = $this->db->wert(
            'SELECT COALESCE(SUM(betrag_cent), 0) FROM hauptbuch_buchungen WHERE konto_id = :k',
            ['k' => $kontoId]
        );

        return (int) $wert;
    }

    /**
     * Konto eines Benutzers, wird bei Bedarf angelegt.
     */
    public function benutzerkonto(int $benutzerId, string $art, string $waehrung = 'EUR'): int
    {
        if (!in_array($art, self::BENUTZERKONTEN, true)) {
            throw new BuchungsFehler("'{$art}' ist keine gueltige Kontoart fuer einen Benutzer.");
        }

        $vorhanden = $this->db->wert(
            'SELECT id FROM hauptbuch_konten WHERE benutzer_id = :b AND art = :a AND waehrung = :w',
            ['b' => $benutzerId, 'a' => $art, 'w' => $waehrung]
        );

        if ($vorhanden !== null) {
            return (int) $vorhanden;
        }

        return $this->db->einfuegen('hauptbuch_konten', [
            'benutzer_id' => $benutzerId,
            'art' => $art,
            'waehrung' => $waehrung,
            'angelegt_am' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /** Plattformkonto. Wird von der Migration angelegt und muss existieren. */
    public function plattformkonto(string $art, string $waehrung = 'EUR'): int
    {
        $wert = $this->db->wert(
            'SELECT id FROM hauptbuch_konten WHERE benutzer_id IS NULL AND art = :a AND waehrung = :w',
            ['a' => $art, 'w' => $waehrung]
        );

        if ($wert === null) {
            throw new BuchungsFehler("Plattformkonto '{$art}' ({$waehrung}) fehlt.");
        }

        return (int) $wert;
    }

    /** Verfuegbares Guthaben eines Benutzers. */
    public function guthaben(int $benutzerId, string $waehrung = 'EUR'): int
    {
        return $this->saldo($this->benutzerkonto($benutzerId, self::KONTO_GUTHABEN, $waehrung));
    }

    /** Auszahlbare Einnahmen eines Benutzers. */
    public function einnahmen(int $benutzerId, string $waehrung = 'EUR'): int
    {
        return $this->saldo($this->benutzerkonto($benutzerId, self::KONTO_EINNAHMEN, $waehrung));
    }

    /**
     * Prueft, ob das gesamte Hauptbuch ausgeglichen ist.
     *
     * Laeuft stuendlich als Cronjob. Eine Abweichung bedeutet einen Fehler in
     * der Geldlogik und muss laut auffallen statt still zu bleiben.
     *
     * @return int Abweichung in Cent, 0 bedeutet in Ordnung
     */
    public function abweichung(): int
    {
        return (int) $this->db->wert('SELECT COALESCE(SUM(betrag_cent), 0) FROM hauptbuch_buchungen');
    }

    /**
     * Findet Vorgaenge, deren Buchungen sich nicht zu null summieren.
     * Sollte immer leer sein — buchen() laesst nichts anderes zu. Die Pruefung
     * existiert fuer den Fall, dass jemand an der Klasse vorbei geschrieben hat.
     *
     * @return list<array<string,mixed>>
     */
    public function unausgeglicheneVorgaenge(): array
    {
        return $this->db->alle(
            'SELECT vorgang_id, SUM(betrag_cent) AS abweichung
               FROM hauptbuch_buchungen
              GROUP BY vorgang_id
             HAVING SUM(betrag_cent) <> 0'
        );
    }
}
