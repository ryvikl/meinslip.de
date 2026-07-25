<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Ledger;

use MeinSlip\Core\Env;

/**
 * Aufladung des Kaeufer-Guthabens.
 *
 * ================================================================
 *  GESPERRT BIS ZUR AUFSICHTSRECHTLICHEN FREIGABE
 * ================================================================
 *
 * Ein aufladbares, jederzeit rueckforderbares Guthaben kann ein
 * erlaubnispflichtiges EINLAGENGESCHAEFT nach § 1 Abs. 1 S. 2 Nr. 1 KWG sein.
 * Daneben stehen die Einordnungen als E-Geld-Geschaeft oder
 * Finanztransfergeschaeft nach dem ZAG.
 *
 * Das Kommissionsmodell loest diese Frage NICHT. Es klaert, wer Verkaeufer
 * ist — nicht, was die Annahme rueckzahlbarer Gelder des Publikums ist. Die
 * beiden Fragen sind unabhaengig, und das Ausgangskonzept hat sie
 * zusammengeworfen.
 *
 * Deshalb ist der Aufladepfad hinter einem Schalter, der ohne dokumentierte
 * anwaltliche Freigabe nicht aktiviert werden kann. Das Hauptbuch selbst
 * bleibt davon unberuehrt — es ist unabhaengig davon die richtige Struktur.
 *
 * Faellt die Pruefung negativ aus, ist der Ausweg die Direktzahlung je
 * Bestellung: Der Betrag geht unmittelbar in die Treuhandbindung, ohne dass
 * jemals ein rueckforderbarer Saldo entsteht. Siehe docs/07-zahlungsverkehr.md.
 */
final class Guthaben
{
    public function __construct(private readonly Hauptbuch $hauptbuch)
    {
    }

    /**
     * Laedt Guthaben auf.
     *
     * @param string $zahlungsreferenz Referenz des Zahlungsdienstleisters,
     *        dient zugleich als Idempotenzschluessel gegen Doppelbuchung.
     */
    public function aufladen(
        int $benutzerId,
        int $betragCent,
        string $zahlungsreferenz,
        string $waehrung = 'EUR'
    ): int {
        $this->pruefeFreigabe();

        if ($betragCent <= 0) {
            throw new BuchungsFehler('Aufladebetraege muessen groesser als null sein.');
        }

        $obergrenze = (int) (Env::get('GUTHABEN_OBERGRENZE_CENT', '50000') ?? '50000');
        $bestand = $this->hauptbuch->guthaben($benutzerId, $waehrung);

        if ($bestand + $betragCent > $obergrenze) {
            throw new BuchungsFehler(sprintf(
                'Obergrenze fuer das Guthaben erreicht: %d Cent erlaubt, %d Cent waeren es danach.',
                $obergrenze,
                $bestand + $betragCent
            ));
        }

        return $this->hauptbuch->buchen(
            art: 'aufladung',
            buchungen: [
                ['konto_id' => $this->hauptbuch->plattformkonto(Hauptbuch::KONTO_ZAHLUNGSEINGANG, $waehrung), 'betrag_cent' => -$betragCent],
                ['konto_id' => $this->hauptbuch->benutzerkonto($benutzerId, Hauptbuch::KONTO_GUTHABEN, $waehrung), 'betrag_cent' => $betragCent],
            ],
            bezugArt: 'aufladung',
            bezugId: $benutzerId,
            beschreibung: 'Guthabenaufladung',
            idempotenzSchluessel: 'aufladung-' . $zahlungsreferenz,
            waehrung: $waehrung
        );
    }

    /**
     * Gibt Einnahmen zum erneuten Ausgeben frei.
     *
     * Bequem und spart Auszahlungsgebuehren — aber ein Geldwaeschepfad ueber
     * zwei abgestimmte Konten. Getragene Waesche hat keinen objektiven
     * Preisanker, an dem ein auffaelliger Betrag erkennbar waere. Deshalb nur
     * nach vollstaendiger Identitaetspruefung und mit Obergrenze.
     */
    public function einnahmenUmbuchen(
        int $benutzerId,
        int $betragCent,
        bool $identitaetVollstaendigGeprueft,
        string $waehrung = 'EUR'
    ): int {
        $this->pruefeFreigabe();

        if (!$identitaetVollstaendigGeprueft) {
            throw new BuchungsFehler(
                'Einnahmen koennen erst nach vollstaendiger Identitaetspruefung wieder ausgegeben werden.'
            );
        }

        $obergrenze = (int) (Env::get('EINNAHMEN_UMBUCHUNG_OBERGRENZE_CENT', '20000') ?? '20000');

        if ($betragCent <= 0 || $betragCent > $obergrenze) {
            throw new BuchungsFehler(sprintf(
                'Umbuchung muss zwischen 1 und %d Cent liegen.',
                $obergrenze
            ));
        }

        if ($this->hauptbuch->einnahmen($benutzerId, $waehrung) < $betragCent) {
            throw new BuchungsFehler('Nicht genug Einnahmen vorhanden.');
        }

        return $this->hauptbuch->buchen(
            art: 'einnahmen_umbuchung',
            buchungen: [
                ['konto_id' => $this->hauptbuch->benutzerkonto($benutzerId, Hauptbuch::KONTO_EINNAHMEN, $waehrung), 'betrag_cent' => -$betragCent],
                ['konto_id' => $this->hauptbuch->benutzerkonto($benutzerId, Hauptbuch::KONTO_GUTHABEN, $waehrung), 'betrag_cent' => $betragCent],
            ],
            bezugArt: 'umbuchung',
            bezugId: $benutzerId,
            beschreibung: 'Einnahmen als Guthaben verfuegbar gemacht',
            waehrung: $waehrung
        );
    }

    /**
     * Der Schalter.
     *
     * Bewusst als Ausnahme mit vollstaendiger Begruendung: Wer diesen Fehler
     * sieht, soll sofort wissen, warum — und nicht anfangen, ihn wegzuklicken.
     */
    private function pruefeFreigabe(): void
    {
        if (Env::bool('ZAHLUNG_GUTHABEN_AKTIV', false)) {
            return;
        }

        throw new AufsichtsrechtGesperrt(
            'Die Guthabenfunktion ist gesperrt. Ein aufladbares, jederzeit rueckforderbares Guthaben '
            . 'kann ein erlaubnispflichtiges Einlagengeschaeft nach § 1 Abs. 1 S. 2 Nr. 1 KWG sein; '
            . 'daneben stehen E-Geld- und Zahlungsdiensteeinordnung nach dem ZAG. '
            . 'Diese Frage ist offen und wird vom Kommissionsmodell NICHT beantwortet. '
            . 'Freigabe erst nach anwaltlicher Klaerung ueber ZAHLUNG_GUTHABEN_AKTIV=true. '
            . 'Siehe docs/07-zahlungsverkehr.md und docs/11-offene-fragen.md.'
        );
    }
}
