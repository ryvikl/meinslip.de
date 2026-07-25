<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Order;

/**
 * Zustandsautomat einer Warenbestellung.
 *
 * Die wichtigste Eigenschaft steht nicht in den Zustaenden, sondern in dem,
 * was fehlt: Es gibt keinen Uebergang, der Geld unmittelbar freigibt. Zwischen
 * Zustellung und Freigabe liegt immer ein Einspruchsfenster.
 *
 * Der urspruengliche Entwurf sah vor, dass der QR-Scan bei der Uebergabe das
 * Geld unwiderruflich uebertraegt. Genau diese Endgueltigkeit im
 * Uebergabemoment ist das, was Betrueger suchen — und der Scan beweist
 * ohnehin nur, dass zwei Geraete nebeneinander waren, nie dass eine Ware den
 * Besitzer gewechselt hat.
 */
enum Bestellzustand: string
{
    case Entwurf = 'entwurf';
    case ZahlungOffen = 'zahlung_offen';
    case TreuhandGebunden = 'treuhand_gebunden';
    case Angenommen = 'angenommen';
    case InVorbereitung = 'in_vorbereitung';
    case Versendet = 'versendet';
    case UebergabeGeplant = 'uebergabe_geplant';
    case Uebergeben = 'uebergeben';
    case Zugestellt = 'zugestellt';
    case Einspruchsfenster = 'einspruchsfenster';
    case Streitfall = 'streitfall';
    case Freigegeben = 'freigegeben';
    case Erstattet = 'erstattet';
    case Abgebrochen = 'abgebrochen';

    /**
     * Erlaubte Folgezustaende.
     *
     * @return list<self>
     */
    public function erlaubteFolgen(): array
    {
        return match ($this) {
            self::Entwurf => [self::ZahlungOffen, self::Abgebrochen],
            self::ZahlungOffen => [self::TreuhandGebunden, self::Abgebrochen],
            // Die Verkaeuferin kann ablehnen oder die Annahmefrist verstreichen
            // lassen — beides fuehrt zur vollstaendigen Erstattung.
            self::TreuhandGebunden => [self::Angenommen, self::Erstattet],
            self::Angenommen => [self::InVorbereitung, self::Erstattet],
            self::InVorbereitung => [self::Versendet, self::UebergabeGeplant, self::Erstattet],
            self::Versendet => [self::Zugestellt, self::Streitfall],
            self::UebergabeGeplant => [self::Uebergeben, self::Erstattet, self::Streitfall],
            // Die Uebergabe selbst bewegt kein Geld. Sie startet nur die Frist.
            self::Uebergeben => [self::Zugestellt],
            self::Zugestellt => [self::Einspruchsfenster],
            self::Einspruchsfenster => [self::Freigegeben, self::Streitfall],
            self::Streitfall => [self::Freigegeben, self::Erstattet],
            self::Freigegeben, self::Erstattet, self::Abgebrochen => [],
        };
    }

    public function darfWechselnZu(self $ziel): bool
    {
        return in_array($ziel, $this->erlaubteFolgen(), true);
    }

    /** Endzustand: keine weiteren Wechsel moeglich. */
    public function istEndzustand(): bool
    {
        return $this->erlaubteFolgen() === [];
    }

    /** Zustaende, in denen Geld in der Treuhand liegt. */
    public function haeltTreuhand(): bool
    {
        return in_array($this, [
            self::TreuhandGebunden,
            self::Angenommen,
            self::InVorbereitung,
            self::Versendet,
            self::UebergabeGeplant,
            self::Uebergeben,
            self::Zugestellt,
            self::Einspruchsfenster,
            self::Streitfall,
        ], true);
    }

    /** Kann der Kaeufer hier noch Einspruch erheben? */
    public function erlaubtEinspruch(): bool
    {
        return in_array($this, [
            self::Versendet,
            self::UebergabeGeplant,
            self::Uebergeben,
            self::Zugestellt,
            self::Einspruchsfenster,
        ], true);
    }

    public function bezeichnungsSchluessel(): string
    {
        return 'bestellung.zustand.' . $this->value;
    }
}
