<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Account;

use MeinSlip\Core\Database;

/**
 * Die Verweise auf dem oeffentlichen Profil.
 *
 * WAS EIN VERWEIS SEIN DARF. Ausschliesslich eine https-Adresse mit einem
 * Rechnernamen. Alles andere wird abgewiesen, nicht repariert:
 *
 *  - 'javascript:', 'data:' und Verwandte waeren im href ausfuehrbar.
 *  - 'http:' fuehrt aus einer verschluesselten Seite unverschluesselt hinaus.
 *  - Zugangsdaten in der Adresse ('https://bank.de@boese.example') zeigen
 *    etwas anderes an, als sie oeffnen.
 *  - Steuerzeichen und Leerraum gehoeren in keine Adresse; sie sind das
 *    uebliche Mittel, eine Pruefung zu taeuschen, die nur den Anfang liest.
 *
 * Die Oberflaeche gibt die Adresse trotzdem nur durch e() aus und setzt
 * rel="noopener noreferrer nofollow ugc". Beides zusammen, nicht eines.
 */
final class ProfilLinks
{
    /** Mehr ist keine Liste mehr, sondern eine Wand. */
    public const HOECHSTENS = 8;

    private const TITEL_MAXLAENGE = 60;

    private const ADRESSE_MAXLAENGE = 500;

    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array{id:int, titel:string, adresse:string, rechner:string}> */
    public function fuer(int $benutzerId): array
    {
        $zeilen = $this->db->alle(
            'SELECT id, titel, adresse FROM profil_links WHERE benutzer_id = :b ORDER BY reihenfolge, id',
            ['b' => $benutzerId]
        );

        return array_map(static fn (array $z): array => [
            'id' => (int) $z['id'],
            'titel' => (string) $z['titel'],
            'adresse' => (string) $z['adresse'],
            // Der Rechnername steht sichtbar unter dem Titel: Wer klickt, soll
            // vorher sehen, wohin — der Titel allein ist frei waehlbar.
            'rechner' => (string) (parse_url((string) $z['adresse'], PHP_URL_HOST) ?: ''),
        ], $zeilen);
    }

    /**
     * @throws KontoFehler 'link_titel_fehlt', 'link_titel_zu_lang',
     *                     'link_adresse_ungueltig', 'link_zu_viele'
     */
    public function hinzufuegen(int $benutzerId, string $titel, string $adresse): int
    {
        $titel = trim($titel);

        if ($titel === '') {
            throw new KontoFehler('link_titel_fehlt');
        }

        if (mb_strlen($titel) > self::TITEL_MAXLAENGE) {
            throw new KontoFehler('link_titel_zu_lang');
        }

        $adresse = self::gepruefteAdresse($adresse);

        return $this->db->transaktion(function () use ($benutzerId, $titel, $adresse): int {
            $anzahl = (int) $this->db->wert(
                'SELECT COUNT(*) FROM profil_links WHERE benutzer_id = :b',
                ['b' => $benutzerId]
            );

            if ($anzahl >= self::HOECHSTENS) {
                throw new KontoFehler('link_zu_viele');
            }

            $naechste = (int) $this->db->wert(
                'SELECT COALESCE(MAX(reihenfolge), 0) + 1 FROM profil_links WHERE benutzer_id = :b',
                ['b' => $benutzerId]
            );

            return $this->db->einfuegen('profil_links', [
                'benutzer_id' => $benutzerId,
                'titel' => $titel,
                'adresse' => $adresse,
                'reihenfolge' => $naechste,
                'angelegt_am' => gmdate('Y-m-d H:i:s'),
            ]);
        });
    }

    /** Fremde und unbekannte Kennung sind derselbe Fall: Es passiert nichts. */
    public function entfernen(int $benutzerId, int $linkId): void
    {
        $this->db->ausfuehren(
            'DELETE FROM profil_links WHERE id = :id AND benutzer_id = :b',
            ['id' => $linkId, 'b' => $benutzerId]
        );
    }

    /** Tauscht den Verweis mit seinem Nachbarn; $richtung ist -1 (hoch) oder 1 (runter). */
    public function verschieben(int $benutzerId, int $linkId, int $richtung): void
    {
        $liste = $this->fuer($benutzerId);
        $stellen = array_column($liste, 'id');
        $von = array_search($linkId, $stellen, true);

        if ($von === false) {
            return;
        }

        $nach = $von + ($richtung < 0 ? -1 : 1);

        if (!isset($stellen[$nach])) {
            return;
        }

        [$stellen[$von], $stellen[$nach]] = [$stellen[$nach], $stellen[$von]];

        // Alle neu durchnummerieren statt zwei Werte tauschen: Gleiche
        // Reihenfolgewerte (aus einem frueheren Abbruch) heilen dabei mit.
        $this->db->transaktion(function () use ($stellen, $benutzerId): void {
            foreach ($stellen as $platz => $id) {
                $this->db->ausfuehren(
                    'UPDATE profil_links SET reihenfolge = :r WHERE id = :id AND benutzer_id = :b',
                    ['r' => $platz + 1, 'id' => $id, 'b' => $benutzerId]
                );
            }
        });
    }

    /** @throws KontoFehler 'link_adresse_ungueltig' */
    public static function gepruefteAdresse(string $adresse): string
    {
        $adresse = trim($adresse);

        // Wer "instagram.com/name" tippt, meint https. Ein anderes Schema wird
        // dadurch nicht geheilt: 'http://…' und 'javascript:…' tragen bereits
        // einen Doppelpunkt vor dem ersten Schraegstrich.
        if ($adresse !== '' && preg_match('#^[a-z][a-z0-9+.\-]*:#i', $adresse) !== 1) {
            $adresse = 'https://' . $adresse;
        }

        if ($adresse === ''
            || mb_strlen($adresse) > self::ADRESSE_MAXLAENGE
            || preg_match('/[\x00-\x20\x7f"\'<>\\\\`]/', $adresse) === 1) {
            throw new KontoFehler('link_adresse_ungueltig');
        }

        $teile = parse_url($adresse);

        if (!is_array($teile)
            || strtolower((string) ($teile['scheme'] ?? '')) !== 'https'
            || !isset($teile['host'])
            || isset($teile['user'])
            || isset($teile['pass'])
            // Ein Rechnername mit Punkt und Buchstaben-Endung: keine nackte
            // IP, kein 'localhost', kein internes Geraet.
            || preg_match('/^(?:[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?\.)+[a-z]{2,}$/i', (string) $teile['host']) !== 1) {
            throw new KontoFehler('link_adresse_ungueltig');
        }

        return $adresse;
    }
}
