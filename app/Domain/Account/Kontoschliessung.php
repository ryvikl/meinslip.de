<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Account;

use MeinSlip\Core\Database;
use MeinSlip\Domain\Media\Medien;
use MeinSlip\Domain\Order\Bestellzustand;
use MeinSlip\Domain\Verification\Pruefbelege;

/**
 * Schliesst ein Konto auf Wunsch der Person (Art. 17 DSGVO).
 *
 * WARUM DIE ZEILE BLEIBT UND NUR IHR INHALT GEHT.
 *
 * Ein DELETE auf 'benutzer' risse per ON DELETE CASCADE jede Unterhaltung mit,
 * an der das Konto beteiligt war — also auch die Nachrichten der ANDEREN
 * Person. Die gehoeren ihr ebenso, und sie sind ihr Beweismittel, wenn sie
 * spaeter eine Belaestigung meldet. Wer belaestigt und danach sein Konto
 * loescht, haette sonst die Spuren gleich mit beseitigt. An Bestellungen
 * haengt die Zeile ohnehin mit RESTRICT.
 *
 * Deshalb wird das Konto entpersonalisiert: Pseudonym, E-Mail-Adresse und
 * Passwort werden ueberschrieben, alles Eigene wird entfernt. Uebrig bleibt
 * eine Zeile, die niemanden mehr bezeichnet, und der Verlauf der Gegenseite
 * unter dem Namen 'geloescht-<Kennung>'.
 *
 * WAS GEHT: Profil samt Bild und Verweisen, Angebote samt Bildern, Pruefbelege samt Dateien,
 * Identitaetsangaben, Sitzungen, eigene Sperrliste, offene Termine,
 * Faehigkeiten, Zustellungen.
 *
 * WAS BLEIBT: Nachrichten in Unterhaltungen mit anderen, Meldungen und
 * Moderationsentscheidungen (Art. 6 Abs. 1 lit. c DSGVO, Art. 16 und 17 DSA),
 * abgeschlossene Bestellungen (§ 147 AO, § 257 HGB).
 *
 * Die Datenschutzerklaerung beschreibt genau diese Aufteilung. Wer hier etwas
 * aendert, aendert resources/lang/de-DE/datenschutz.php im selben Paket.
 */
final class Kontoschliessung
{
    /** Vorsilbe des Pseudonyms eines geschlossenen Kontos; bei der Registrierung gesperrt. */
    public const PSEUDONYM_VORSILBE = 'geloescht-';

    public function __construct(
        private readonly Database $db,
        private readonly Medien $medien,
        private readonly Pruefbelege $belege,
    ) {
    }

    /**
     * @throws KontoFehler 'konto_unbekannt', 'passwort_falsch',
     *                     'bestellung_offen', 'verwalter_bleibt'
     */
    public function schliessen(int $benutzerId, string $passwort): void
    {
        $konto = $this->db->eine(
            'SELECT id, passwort_hash, status FROM benutzer WHERE id = :id',
            ['id' => $benutzerId]
        );

        if ($konto === null || (string) $konto['status'] === 'geloescht') {
            throw new KontoFehler('konto_unbekannt');
        }

        // Das Passwort noch einmal: Eine offen gelassene Sitzung am fremden
        // Geraet darf nicht genuegen, um ein Konto unwiederbringlich zu leeren.
        if (!password_verify($passwort, (string) $konto['passwort_hash'])) {
            throw new KontoFehler('passwort_falsch');
        }

        // Die letzte Verwalterin darf nicht per Knopfdruck verschwinden; die
        // Faehigkeit wird ueber bin/verwalter entzogen, nicht nebenbei.
        if ((new Konten($this->db))->hatFaehigkeit($benutzerId, Konten::FAEHIGKEIT_VERWALTEN)) {
            throw new KontoFehler('verwalter_bleibt');
        }

        if ($this->hatOffeneBestellung($benutzerId)) {
            throw new KontoFehler('bestellung_offen');
        }

        // Dateien zuerst einsammeln, entfernt werden sie NACH der Transaktion:
        // Ein Rollback bringt Zeilen zurueck, aber keine geloeschte Datei.
        $medienIds = array_map(
            static fn (array $z): int => (int) $z['id'],
            $this->db->alle(
                'SELECT m.id FROM angebot_medien m JOIN angebote a ON a.id = m.angebot_id
                  WHERE a.verkaeufer_id = :b',
                ['b' => $benutzerId]
            )
        );

        // Zwei Dateien: das Quadrat, das alle sehen, und das ganze Foto fuer
        // die Ausschnittwahl (siehe ProfilRouten::bildHochladen).
        $profilbilder = $this->db->eine(
            'SELECT bild_pfad, bild_vorschau_pfad FROM profile WHERE benutzer_id = :b',
            ['b' => $benutzerId]
        ) ?? [];

        $jetzt = gmdate('Y-m-d H:i:s');

        $this->db->transaktion(function () use ($benutzerId, $jetzt): void {
            $b = ['b' => $benutzerId];

            $this->db->ausfuehren(
                "UPDATE angebote SET status = 'entfernt' WHERE verkaeufer_id = :b AND status <> 'entfernt'",
                $b
            );
            $this->db->ausfuehren(
                "UPDATE termine SET status = 'abgelehnt'
                  WHERE status IN ('vorgeschlagen', 'angenommen')
                    AND unterhaltung_id IN (
                        SELECT id FROM unterhaltungen
                         WHERE teilnehmer_a_id = :a OR teilnehmer_b_id = :b
                    )",
                ['a' => $benutzerId, 'b' => $benutzerId]
            );

            foreach (['profile', 'profil_links', 'benutzer_identitaeten', 'sitzungen', 'benachrichtigungen'] as $tabelle) {
                $this->db->ausfuehren('DELETE FROM ' . $tabelle . ' WHERE benutzer_id = :b', $b);
            }

            $this->db->ausfuehren('DELETE FROM sperren WHERE benutzer_id = :b', $b);
            $this->db->ausfuehren(
                'UPDATE benutzer_faehigkeiten SET entzogen_am = :z WHERE benutzer_id = :b AND entzogen_am IS NULL',
                ['z' => $jetzt, 'b' => $benutzerId]
            );

            // Belege sofort faellig stellen; faelligeLoeschen() nimmt Zeile
            // und Dateien gemeinsam weg.
            $this->db->ausfuehren(
                'UPDATE pruefungsbelege SET loeschen_ab = :z WHERE benutzer_id = :b',
                ['z' => $jetzt, 'b' => $benutzerId]
            );

            $this->db->ausfuehren(
                "UPDATE benutzer
                    SET status = 'geloescht', pseudonym = :p, email = :e, passwort_hash = :h,
                        homescreen_name = NULL, zuletzt_aktiv_am = NULL
                  WHERE id = :b",
                [
                    'p' => self::PSEUDONYM_VORSILBE . $benutzerId,
                    // .invalid ist nach RFC 2606 nie zustellbar.
                    'e' => self::PSEUDONYM_VORSILBE . $benutzerId . '@geloescht.invalid',
                    // Kein gueltiger Hash: password_verify() scheitert an jeder Eingabe.
                    'h' => 'geloescht',
                    'b' => $benutzerId,
                ]
            );
        });

        foreach ($medienIds as $medienId) {
            try {
                $this->medien->entfernen($medienId, $benutzerId);
            } catch (\Throwable $fehler) {
                error_log('[MeinSlip/Kontoschliessung] Medium ' . $medienId . ': ' . $fehler->getMessage());
            }
        }

        // Das Profilbild liegt im Medienverzeichnis; Medien::datei() gibt nur
        // heraus, was wirklich darunter liegt.
        foreach ($profilbilder as $relativ) {
            $bilddatei = $this->medien->datei(is_string($relativ) ? $relativ : null);

            if ($bilddatei !== null) {
                @unlink($bilddatei);
            }
        }

        try {
            $this->belege->faelligeLoeschen($jetzt);
        } catch (\Throwable $fehler) {
            // bin/pflege holt es nach; das Loeschdatum steht bereits.
            error_log('[MeinSlip/Kontoschliessung] Belege: ' . $fehler->getMessage());
        }
    }

    private function hatOffeneBestellung(int $benutzerId): bool
    {
        $endzustaende = array_map(
            static fn (Bestellzustand $z): string => $z->value,
            array_filter(Bestellzustand::cases(), static fn (Bestellzustand $z): bool => $z->istEndzustand())
        );

        $zeilen = $this->db->alle(
            'SELECT zustand FROM bestellungen WHERE kaeufer_id = :a OR verkaeufer_id = :b',
            ['a' => $benutzerId, 'b' => $benutzerId]
        );

        foreach ($zeilen as $zeile) {
            if (!in_array((string) $zeile['zustand'], $endzustaende, true)) {
                return true;
            }
        }

        return false;
    }
}
