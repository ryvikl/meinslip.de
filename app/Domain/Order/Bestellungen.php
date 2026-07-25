<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Order;

use MeinSlip\Core\Database;
use MeinSlip\Domain\Ledger\Hauptbuch;

/**
 * Bestellvorgang der Warenstrecke.
 *
 * Vier Dinge setzt diese Klasse durch, die anderswo nur Absichtserklaerungen
 * waeren:
 *
 *  1. KEINE BESTELLUNG OHNE SPEZIFIKATION. Der Widerrufsausschluss stuetzt
 *     sich auf § 312g Abs. 2 Nr. 1 BGB — Anfertigung nach Kundenspezifikation.
 *     Die Hygiene-Ausnahme nach Nr. 3 traegt bei getragener Waesche nicht
 *     verlaesslich (EuGH C-681/17). Also muss jede Position mindestens eine
 *     echte, vom Kaeufer gesetzte Spezifikation mit Zeitstempel tragen.
 *
 *  2. KEIN GESCHAEFT MIT SICH SELBST. Ohne diese Sperre wird die Provision
 *     zur Geldwaescheschleife — und getragene Waesche hat keinen objektiven
 *     Preisanker, an dem ein auffaelliger Betrag erkennbar waere.
 *
 *  3. GELD BEWEGT SICH NUR UEBER DAS HAUPTBUCH. Kein Zustandswechsel schreibt
 *     einen Saldo.
 *
 *  4. FREIGABE ERST NACH ABLAUF DES EINSPRUCHSFENSTERS. Kein Ereignis — auch
 *     keine bestaetigte Uebergabe — gibt Geld sofort frei.
 */
final class Bestellungen
{
    /** Dauer des Einspruchsfensters nach Zustellung oder Uebergabe. */
    public const EINSPRUCHSFENSTER_STUNDEN = 72;

    /** Frist, innerhalb derer die Verkaeuferin annehmen muss. */
    public const ANNAHMEFRIST_STUNDEN = 48;

    public function __construct(
        private readonly Database $db,
        private readonly Hauptbuch $hauptbuch,
        private readonly Preisrechner $preisrechner,
    ) {
    }

    /**
     * Legt eine Bestellung an und bindet den Betrag in der Treuhand.
     *
     * @param list<array{
     *     angebot_id:int,
     *     bezeichnung:string,
     *     brutto_cent:int,
     *     menge?:int,
     *     spezifikationen:list<array{schluessel:string, bezeichnung:string, wert:string, option_id?:int, aufpreis_cent?:int}>
     * }> $positionen
     */
    public function anlegen(
        int $kaeuferId,
        int $verkaeuferId,
        array $positionen,
        string $lieferart = 'versand',
        string $land = 'DE',
        string $waehrung = 'EUR'
    ): int {
        if ($kaeuferId === $verkaeuferId) {
            throw new BestellFehler('Geschaefte mit sich selbst sind nicht zulaessig.');
        }

        if ($positionen === []) {
            throw new BestellFehler('Eine Bestellung braucht mindestens eine Position.');
        }

        if (!in_array($lieferart, ['versand', 'uebergabe'], true)) {
            throw new BestellFehler("Unbekannte Lieferart '{$lieferart}'.");
        }

        // Jede Position braucht eine Spezifikation — sonst traegt der
        // Widerrufsausschluss nicht.
        foreach ($positionen as $i => $position) {
            if (empty($position['spezifikationen'])) {
                throw new BestellFehler(sprintf(
                    'Position %d hat keine Spezifikation. Jede Warenbestellung muss mindestens '
                    . 'eine vom Kaeufer gesetzte Spezifikation tragen (§ 312g Abs. 2 Nr. 1 BGB).',
                    $i + 1
                ));
            }
        }

        $this->pruefeVerbundeneKonten($kaeuferId, $verkaeuferId);

        $ustSatz = $this->ustSatz($land);

        return $this->db->transaktion(function () use (
            $kaeuferId, $verkaeuferId, $positionen, $lieferart, $land, $waehrung, $ustSatz
        ): int {
            $summeBrutto = 0;
            $summeEinkauf = 0;
            $summeProvision = 0;
            $summeUst = 0;
            $aufteilungen = [];

            foreach ($positionen as $position) {
                $menge = max(1, (int) ($position['menge'] ?? 1));
                $brutto = (int) $position['brutto_cent'] * $menge;

                if ($brutto <= 0) {
                    throw new BestellFehler('Positionsbetraege muessen groesser als null sein.');
                }

                $aufteilung = $this->preisrechner->zerlegen($brutto, $ustSatz);
                $aufteilungen[] = [$position, $menge, $aufteilung];

                $summeBrutto += $aufteilung->bruttoCent;
                $summeEinkauf += $aufteilung->einkaufCent;
                $summeProvision += $aufteilung->provisionCent;
                $summeUst += $aufteilung->ustCent;
            }

            $guthaben = $this->hauptbuch->guthaben($kaeuferId, $waehrung);
            if ($guthaben < $summeBrutto) {
                throw new BestellFehler(sprintf(
                    'Guthaben reicht nicht: %d Cent vorhanden, %d Cent noetig.',
                    $guthaben,
                    $summeBrutto
                ));
            }

            $jetzt = gmdate('Y-m-d H:i:s');

            $bestellungId = $this->db->einfuegen('bestellungen', [
                'nummer' => $this->naechsteNummer(),
                'kaeufer_id' => $kaeuferId,
                'verkaeufer_id' => $verkaeuferId,
                'zustand' => Bestellzustand::Entwurf->value,
                'lieferart' => $lieferart,
                'land' => $land,
                'waehrung' => $waehrung,
                'summe_verkauf_cent' => $summeBrutto,
                'summe_einkauf_cent' => $summeEinkauf,
                'summe_provision_cent' => $summeProvision,
                'summe_ust_cent' => $summeUst,
                'annahmefrist_bis' => $this->inStunden(self::ANNAHMEFRIST_STUNDEN),
                'angelegt_am' => $jetzt,
                'geaendert_am' => $jetzt,
            ]);

            foreach ($aufteilungen as [$position, $menge, $aufteilung]) {
                $positionId = $this->db->einfuegen('bestellpositionen', [
                    'bestellung_id' => $bestellungId,
                    'angebot_id' => $position['angebot_id'] ?? null,
                    'bezeichnung' => $position['bezeichnung'],
                    'menge' => $menge,
                    'einkaufspreis_cent' => $aufteilung->einkaufCent,
                    'verkaufspreis_cent' => $aufteilung->bruttoCent,
                    'provision_cent' => $aufteilung->provisionCent,
                    'ust_cent' => $aufteilung->ustCent,
                    'ust_satz' => $aufteilung->ustSatz,
                    'ust_land' => $land,
                    'angelegt_am' => $jetzt,
                ]);

                foreach ($position['spezifikationen'] as $spezifikation) {
                    $this->db->einfuegen('bestellung_spezifikationen', [
                        'position_id' => $positionId,
                        'option_id' => $spezifikation['option_id'] ?? null,
                        'schluessel' => $spezifikation['schluessel'],
                        'bezeichnung' => $spezifikation['bezeichnung'],
                        'wert' => $spezifikation['wert'],
                        'aufpreis_cent' => (int) ($spezifikation['aufpreis_cent'] ?? 0),
                        'festgelegt_am' => $jetzt,
                    ]);
                }
            }

            $this->wechsleZustand($bestellungId, Bestellzustand::ZahlungOffen, 'kaeufer', $kaeuferId);
            $this->treuhandBinden($bestellungId);

            return $bestellungId;
        });
    }

    /** Bindet den Bestellbetrag aus dem Guthaben in die Treuhand. */
    public function treuhandBinden(int $bestellungId): void
    {
        $bestellung = $this->laden($bestellungId);
        $zustand = Bestellzustand::from($bestellung['zustand']);

        if ($zustand !== Bestellzustand::ZahlungOffen) {
            throw new BestellFehler('Treuhand kann nur im Zustand "zahlung_offen" gebunden werden.');
        }

        $betrag = (int) $bestellung['summe_verkauf_cent'];
        $waehrung = (string) $bestellung['waehrung'];

        $this->db->transaktion(function () use ($bestellung, $bestellungId, $betrag, $waehrung): void {
            $this->hauptbuch->buchen(
                art: 'bestellung_treuhand',
                buchungen: [
                    ['konto_id' => $this->hauptbuch->benutzerkonto((int) $bestellung['kaeufer_id'], Hauptbuch::KONTO_GUTHABEN, $waehrung), 'betrag_cent' => -$betrag],
                    ['konto_id' => $this->hauptbuch->plattformkonto(Hauptbuch::KONTO_TREUHAND, $waehrung), 'betrag_cent' => $betrag],
                ],
                bezugArt: 'bestellung',
                bezugId: $bestellungId,
                beschreibung: 'Treuhandbindung fuer Bestellung ' . $bestellung['nummer'],
                idempotenzSchluessel: 'treuhand-bind-' . $bestellungId,
                waehrung: $waehrung
            );

            $this->db->einfuegen('treuhand_bindungen', [
                'bestellung_id' => $bestellungId,
                'betrag_cent' => $betrag,
                'waehrung' => $waehrung,
                'status' => 'gebunden',
                'gebunden_am' => gmdate('Y-m-d H:i:s'),
                'faellig_am' => null,
            ]);

            $this->wechsleZustand($bestellungId, Bestellzustand::TreuhandGebunden, 'system');
        });
    }

    public function annehmen(int $bestellungId, int $verkaeuferId): void
    {
        $bestellung = $this->laden($bestellungId);

        if ((int) $bestellung['verkaeufer_id'] !== $verkaeuferId) {
            throw new BestellFehler('Nur die Verkaeuferin dieser Bestellung darf sie annehmen.');
        }

        $this->wechsleZustand($bestellungId, Bestellzustand::Angenommen, 'verkaeufer', $verkaeuferId);
        $this->wechsleZustand($bestellungId, Bestellzustand::InVorbereitung, 'system');
    }

    public function versenden(int $bestellungId, string $dienstleister, string $einlieferungscode): void
    {
        $bestellung = $this->laden($bestellungId);

        if ($bestellung['lieferart'] !== 'versand') {
            throw new BestellFehler('Diese Bestellung ist auf persoenliche Uebergabe eingestellt.');
        }

        $this->db->transaktion(function () use ($bestellungId, $dienstleister, $einlieferungscode): void {
            $this->db->einfuegen('versendungen', [
                'bestellung_id' => $bestellungId,
                'dienstleister' => $dienstleister,
                // Der Creator erhaelt einen Einlieferungscode, kein fertiges
                // Label — sonst saehe er die Kaeuferadresse und Safe-Ship
                // anonymisierte nur eine Richtung.
                'einlieferungscode' => $einlieferungscode,
                'sendungsnummer' => null,
                'status' => 'vorbereitet',
                'angelegt_am' => gmdate('Y-m-d H:i:s'),
            ]);

            $this->wechsleZustand($bestellungId, Bestellzustand::Versendet, 'verkaeufer');
        });
    }

    /**
     * Plant eine persoenliche Uebergabe.
     *
     * Eigener Schritt, nicht Beiwerk: Erst mit Zeitpunkt und Treffpunkt kann
     * die Sicherheitskette greifen — Vertrauenskontakt, Fristen, Eskalation.
     * Eine Uebergabe ohne geplanten Rahmen darf es deshalb nicht geben.
     */
    public function uebergabePlanen(int $bestellungId, string $zeitpunkt, string $treffpunkt): void
    {
        $bestellung = $this->laden($bestellungId);

        if ($bestellung['lieferart'] !== 'uebergabe') {
            throw new BestellFehler('Diese Bestellung ist auf Versand eingestellt.');
        }

        if (trim($treffpunkt) === '') {
            throw new BestellFehler('Eine Uebergabe braucht einen Treffpunkt.');
        }

        $this->wechsleZustand(
            $bestellungId,
            Bestellzustand::UebergabeGeplant,
            'verkaeufer',
            (int) $bestellung['verkaeufer_id'],
            sprintf('Geplant fuer %s, Treffpunkt: %s', $zeitpunkt, $treffpunkt)
        );
    }

    /**
     * Bestaetigte persoenliche Uebergabe.
     *
     * Diese Methode bewegt bewusst KEIN Geld. Sie setzt nur den Zeitanker,
     * ab dem das Einspruchsfenster laeuft.
     *
     * Der urspruengliche Entwurf sah hier eine unwiderrufliche Uebertragung
     * per QR-Scan vor. Ein Scan beweist aber nur, dass zwei Geraete
     * nebeneinander waren — nie, dass eine Ware den Besitzer gewechselt hat
     * oder der Beschreibung entspricht.
     */
    public function uebergabeBestaetigen(int $bestellungId): void
    {
        $bestellung = $this->laden($bestellungId);

        if ($bestellung['lieferart'] !== 'uebergabe') {
            throw new BestellFehler('Diese Bestellung ist auf Versand eingestellt.');
        }

        $this->wechsleZustand($bestellungId, Bestellzustand::Uebergeben, 'system');
        $this->zustellen($bestellungId);
    }

    /** Zustellung bestaetigt — startet das Einspruchsfenster. */
    public function zustellen(int $bestellungId): void
    {
        $faelligAm = $this->inStunden(self::EINSPRUCHSFENSTER_STUNDEN);

        $this->db->transaktion(function () use ($bestellungId, $faelligAm): void {
            $this->wechsleZustand($bestellungId, Bestellzustand::Zugestellt, 'system');
            $this->wechsleZustand($bestellungId, Bestellzustand::Einspruchsfenster, 'system');

            $this->db->ausfuehren(
                'UPDATE bestellungen SET einspruchsfenster_bis = :bis WHERE id = :id',
                ['bis' => $faelligAm, 'id' => $bestellungId]
            );

            $this->db->ausfuehren(
                'UPDATE treuhand_bindungen SET faellig_am = :bis WHERE bestellung_id = :id AND status = :s',
                ['bis' => $faelligAm, 'id' => $bestellungId, 's' => 'gebunden']
            );
        });
    }

    public function einspruchErheben(int $bestellungId, int $kaeuferId, string $grund): void
    {
        $bestellung = $this->laden($bestellungId);

        if ((int) $bestellung['kaeufer_id'] !== $kaeuferId) {
            throw new BestellFehler('Nur der Kaeufer dieser Bestellung darf Einspruch erheben.');
        }

        $zustand = Bestellzustand::from($bestellung['zustand']);
        if (!$zustand->erlaubtEinspruch()) {
            throw new BestellFehler('In diesem Zustand ist kein Einspruch mehr moeglich.');
        }

        $this->wechsleZustand($bestellungId, Bestellzustand::Streitfall, 'kaeufer', $kaeuferId, $grund);
    }

    /**
     * Gibt den Treuhandbetrag frei: Verkaeuferin, Provision und Umsatzsteuer.
     *
     * Wird vom Cronjob aufgerufen, sobald das Einspruchsfenster abgelaufen ist,
     * oder von der Moderation nach einem Streitfall.
     */
    public function freigeben(int $bestellungId, string $ausgeloestVon = 'system'): void
    {
        $bestellung = $this->laden($bestellungId);
        $zustand = Bestellzustand::from($bestellung['zustand']);

        if (!in_array($zustand, [Bestellzustand::Einspruchsfenster, Bestellzustand::Streitfall], true)) {
            throw new BestellFehler('Freigabe ist nur nach Zustellung oder aus einem Streitfall heraus moeglich.');
        }

        $waehrung = (string) $bestellung['waehrung'];
        $betrag = (int) $bestellung['summe_verkauf_cent'];
        $einkauf = (int) $bestellung['summe_einkauf_cent'];
        $provision = (int) $bestellung['summe_provision_cent'];
        $ust = (int) $bestellung['summe_ust_cent'];

        $this->db->transaktion(function () use (
            $bestellung, $bestellungId, $waehrung, $betrag, $einkauf, $provision, $ust, $ausgeloestVon
        ): void {
            $buchungen = [
                ['konto_id' => $this->hauptbuch->plattformkonto(Hauptbuch::KONTO_TREUHAND, $waehrung), 'betrag_cent' => -$betrag],
                ['konto_id' => $this->hauptbuch->benutzerkonto((int) $bestellung['verkaeufer_id'], Hauptbuch::KONTO_EINNAHMEN, $waehrung), 'betrag_cent' => $einkauf],
            ];

            // Nullbetraege werden nicht gebucht — das Hauptbuch weist sie ab.
            if ($provision !== 0) {
                $buchungen[] = ['konto_id' => $this->hauptbuch->plattformkonto(Hauptbuch::KONTO_PROVISION, $waehrung), 'betrag_cent' => $provision];
            }
            if ($ust !== 0) {
                $buchungen[] = ['konto_id' => $this->hauptbuch->plattformkonto(Hauptbuch::KONTO_UMSATZSTEUER, $waehrung), 'betrag_cent' => $ust];
            }

            $this->hauptbuch->buchen(
                art: 'bestellung_freigabe',
                buchungen: $buchungen,
                bezugArt: 'bestellung',
                bezugId: $bestellungId,
                beschreibung: 'Freigabe Bestellung ' . $bestellung['nummer'],
                idempotenzSchluessel: 'treuhand-frei-' . $bestellungId,
                waehrung: $waehrung
            );

            $this->db->ausfuehren(
                'UPDATE treuhand_bindungen SET status = :s, aufgeloest_am = :z WHERE bestellung_id = :id',
                ['s' => 'freigegeben', 'z' => gmdate('Y-m-d H:i:s'), 'id' => $bestellungId]
            );

            $this->wechsleZustand($bestellungId, Bestellzustand::Freigegeben, $ausgeloestVon);
        });
    }

    /** Erstattet den Treuhandbetrag vollstaendig an den Kaeufer. */
    public function erstatten(int $bestellungId, string $ausgeloestVon = 'system', ?string $grund = null): void
    {
        $bestellung = $this->laden($bestellungId);
        $waehrung = (string) $bestellung['waehrung'];
        $betrag = (int) $bestellung['summe_verkauf_cent'];

        $this->db->transaktion(function () use ($bestellung, $bestellungId, $waehrung, $betrag, $ausgeloestVon, $grund): void {
            $this->hauptbuch->buchen(
                art: 'bestellung_erstattung',
                buchungen: [
                    ['konto_id' => $this->hauptbuch->plattformkonto(Hauptbuch::KONTO_TREUHAND, $waehrung), 'betrag_cent' => -$betrag],
                    ['konto_id' => $this->hauptbuch->benutzerkonto((int) $bestellung['kaeufer_id'], Hauptbuch::KONTO_GUTHABEN, $waehrung), 'betrag_cent' => $betrag],
                ],
                bezugArt: 'bestellung',
                bezugId: $bestellungId,
                beschreibung: 'Erstattung Bestellung ' . $bestellung['nummer'],
                idempotenzSchluessel: 'treuhand-erst-' . $bestellungId,
                waehrung: $waehrung
            );

            $this->db->ausfuehren(
                'UPDATE treuhand_bindungen SET status = :s, aufgeloest_am = :z WHERE bestellung_id = :id',
                ['s' => 'erstattet', 'z' => gmdate('Y-m-d H:i:s'), 'id' => $bestellungId]
            );

            $this->wechsleZustand($bestellungId, Bestellzustand::Erstattet, $ausgeloestVon, null, $grund);
        });
    }

    /**
     * Faellige Bestellungen freigeben. Aufruf durch den Cronjob.
     *
     * @return list<int> Kennungen der freigegebenen Bestellungen
     */
    public function faelligeFreigeben(?string $jetzt = null): array
    {
        $jetzt ??= gmdate('Y-m-d H:i:s');

        $zeilen = $this->db->alle(
            'SELECT id FROM bestellungen
              WHERE zustand = :z AND einspruchsfenster_bis IS NOT NULL AND einspruchsfenster_bis <= :jetzt',
            ['z' => Bestellzustand::Einspruchsfenster->value, 'jetzt' => $jetzt]
        );

        $freigegeben = [];
        foreach ($zeilen as $zeile) {
            $this->freigeben((int) $zeile['id']);
            $freigegeben[] = (int) $zeile['id'];
        }

        return $freigegeben;
    }

    /**
     * Bestellungen erstatten, deren Annahmefrist verstrichen ist.
     *
     * @return list<int>
     */
    public function abgelaufeneErstatten(?string $jetzt = null): array
    {
        $jetzt ??= gmdate('Y-m-d H:i:s');

        $zeilen = $this->db->alle(
            'SELECT id FROM bestellungen
              WHERE zustand = :z AND annahmefrist_bis IS NOT NULL AND annahmefrist_bis <= :jetzt',
            ['z' => Bestellzustand::TreuhandGebunden->value, 'jetzt' => $jetzt]
        );

        $erstattet = [];
        foreach ($zeilen as $zeile) {
            $this->erstatten((int) $zeile['id'], 'system', 'Annahmefrist verstrichen');
            $erstattet[] = (int) $zeile['id'];
        }

        return $erstattet;
    }

    // --- intern ----------------------------------------------------------

    /** @return array<string,mixed> */
    public function laden(int $bestellungId): array
    {
        $zeile = $this->db->eine('SELECT * FROM bestellungen WHERE id = :id', ['id' => $bestellungId]);

        if ($zeile === null) {
            throw new BestellFehler("Bestellung {$bestellungId} existiert nicht.");
        }

        return $zeile;
    }

    private function wechsleZustand(
        int $bestellungId,
        Bestellzustand $nach,
        string $ausgeloestVon,
        ?int $benutzerId = null,
        ?string $anmerkung = null
    ): void {
        $bestellung = $this->laden($bestellungId);
        $von = Bestellzustand::from($bestellung['zustand']);

        if (!$von->darfWechselnZu($nach)) {
            throw ZustandsFehler::unerlaubterWechsel($von, $nach);
        }

        $this->db->ausfuehren(
            'UPDATE bestellungen SET zustand = :z, geaendert_am = :g WHERE id = :id',
            ['z' => $nach->value, 'g' => gmdate('Y-m-d H:i:s'), 'id' => $bestellungId]
        );

        $this->db->einfuegen('bestellung_ereignisse', [
            'bestellung_id' => $bestellungId,
            'von_zustand' => $von->value,
            'nach_zustand' => $nach->value,
            'ausgeloest_von' => $ausgeloestVon,
            'benutzer_id' => $benutzerId,
            'anmerkung' => $anmerkung,
            'angelegt_am' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Blockiert Geschaefte zwischen Konten, die sich Geraet, Netz oder
     * Zahlungsmittel teilen.
     */
    private function pruefeVerbundeneKonten(int $kaeuferId, int $verkaeuferId): void
    {
        $treffer = $this->db->wert(
            'SELECT COUNT(*)
               FROM konto_signale a
               JOIN konto_signale b ON a.art = b.art AND a.wert_hash = b.wert_hash
              WHERE a.benutzer_id = :k AND b.benutzer_id = :v',
            ['k' => $kaeuferId, 'v' => $verkaeuferId]
        );

        if ((int) $treffer > 0) {
            throw new BestellFehler(
                'Kaeufer und Verkaeuferin teilen sich Geraet, Netz oder Zahlungsmittel. '
                . 'Diese Bestellung wurde zur Pruefung angehalten.'
            );
        }
    }

    private function ustSatz(string $land): int
    {
        $satz = $this->db->wert('SELECT ust_satz_normal FROM laender WHERE code = :c', ['c' => $land]);

        if ($satz === null) {
            throw new BestellFehler("Land '{$land}' ist nicht konfiguriert.");
        }

        return (int) $satz;
    }

    private function naechsteNummer(): string
    {
        return 'MS-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    private function inStunden(int $stunden): string
    {
        return gmdate('Y-m-d H:i:s', time() + $stunden * 3600);
    }
}
