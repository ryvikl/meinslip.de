#!/usr/bin/env python3
"""Prueft die Farbtokens von MeinSlip gegen WCAG 2.1 AA.

Aufruf:  python3 docs/kontrast-pruefung.py
Rueckgabe: 0 wenn alle Werte bestehen, 1 wenn mindestens einer durchfaellt.

Hintergrund: Das Barrierefreiheitsstaerkungsgesetz gilt seit dem 28.06.2025 fuer den
elektronischen Geschaeftsverkehr und verlangt WCAG 2.1 AA. Text braucht 4.5:1,
Bedienelemente und ihre Rahmen brauchen nach 1.4.11 mindestens 3:1.

Die Werte muessen mit den Tokens in public/assets/css/tokens.css uebereinstimmen.
"""


def lin(c):
    c = c / 255
    return c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4


def luminanz(hexwert):
    h = hexwert.lstrip('#')
    r, g, b = (int(h[i:i + 2], 16) for i in (0, 2, 4))
    return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b)


def kontrast(vordergrund, hintergrund):
    a, b = luminanz(vordergrund), luminanz(hintergrund)
    hell, dunkel = max(a, b), min(a, b)
    return (hell + 0.05) / (dunkel + 0.05)


HINTERGRUENDE = {
    'bg': '#080B14',
    'surface': '#111827',
    'surface-raised': '#182236',
}

# Geprueft werden nur Tokens, die als SCHRIFT oder als Rahmen auf den Flaechen liegen.
# Die reinen Fuellfarben (--ms-accent, --ms-danger, --ms-accent-2) stehen weiter unten und
# werden nicht gegen den Hintergrund geprueft, weil auf ihnen Text liegt, nicht neben ihnen.
VORDERGRUENDE = {
    'text': '#F8FAFC',
    'text-muted': '#CBD5E1',
    'text-subtle': '#94A3B8',          # korrigiert von #64748B
    'accent-text': '#4C8DF7',          # korrigiert von #3B82F6
    'accent-2-text': '#A78BFA',        # korrigiert von #8B5CF6
    'accent-3-text': '#EC4899',
    'success-text': '#22C55E',
    'warning-text': '#F59E0B',
    'danger-text': '#F35B5B',          # korrigiert von #EF4444
    'border-interactive': '#687A96',   # korrigiert von #1E293B
}

# Diese Tokens sind Bedienelemente, kein Text: Schwelle 3:1 statt 4.5:1
NUR_UI = {'border-interactive'}

# Fuellfarben fuer Schaltflaechen: geprueft wird, ob die Schrift AUF der Flaeche lesbar ist
# (>= 4.5:1) UND ob sich die Flaeche selbst vom Seitenhintergrund abhebt (>= 3:1 nach 1.4.11).
# Die helleren Marken-Blau- und -Rottoene tragen keine weisse Schrift und sind deshalb hier
# durch dunklere Fuellvarianten ersetzt.
FUELLUNGEN = {
    '--ms-accent-fill': ('#2563EB', '#FFFFFF'),    # korrigiert von #3B82F6
    '--ms-accent-2-fill': ('#7C3AED', '#FFFFFF'),  # korrigiert von #8B5CF6
    '--ms-danger-fill': ('#DC2626', '#FFFFFF'),    # korrigiert von #EF4444
    '--ms-success-fill': ('#22C55E', '#08130B'),
    '--ms-warning-fill': ('#F59E0B', '#1A1103'),
}

# Diese Tokens werden nur als Symbol-, Fokus- oder Aktivfarbe verwendet, nie mit Schrift darauf.
# Sie muessen sich lediglich vom Hintergrund abheben (>= 3:1).
DEKORATIV = {
    '--ms-accent': '#3B82F6',
    '--ms-accent-2': '#8B5CF6',
    '--ms-danger': '#EF4444',
}

# Diese Kombinationen sind bewusst nicht vorgesehen und werden nicht geprueft
AUSGENOMMEN = set()


def main():
    fehler = []
    for hname, hwert in HINTERGRUENDE.items():
        print(f'=== auf {hname} ({hwert}) ===')
        for vname, vwert in VORDERGRUENDE.items():
            if (vname, hname) in AUSGENOMMEN:
                continue
            wert = kontrast(vwert, hwert)
            soll = 3.0 if vname in NUR_UI else 4.5
            ok = wert >= soll
            if not ok:
                fehler.append(f'{vname} auf {hname}: {wert:.2f}:1, noetig {soll}:1')
            marke = 'ok    ' if ok else 'FEHLER'
            print(f'  {marke}  {vname:20s} {wert:5.2f}:1  (soll >= {soll})')
        print()

    print('=== Schaltflaechen: Schrift auf der Fuellung, Fuellung gegen den Hintergrund ===')
    for name, (fuellung, schrift) in FUELLUNGEN.items():
        auf_fuellung = kontrast(schrift, fuellung)
        gegen_bg = kontrast(fuellung, HINTERGRUENDE['bg'])
        ok_s = auf_fuellung >= 4.5
        ok_f = gegen_bg >= 3.0
        if not ok_s:
            fehler.append(f'Schrift {schrift} auf {name} ({fuellung}): {auf_fuellung:.2f}:1')
        if not ok_f:
            fehler.append(f'{name} ({fuellung}) hebt sich nicht vom Hintergrund ab: {gegen_bg:.2f}:1')
        marke = 'ok    ' if (ok_s and ok_f) else 'FEHLER'
        print(f'  {marke}  {name:20s} Schrift {auf_fuellung:5.2f}:1  Flaeche {gegen_bg:5.2f}:1')
    print()

    print('=== Dekorative Farben (Symbole, Fokus, Aktivzustand) gegen den Hintergrund ===')
    for name, wertfarbe in DEKORATIV.items():
        gegen_bg = kontrast(wertfarbe, HINTERGRUENDE['bg'])
        ok = gegen_bg >= 3.0
        if not ok:
            fehler.append(f'{name} ({wertfarbe}) gegen Hintergrund: {gegen_bg:.2f}:1')
        marke = 'ok    ' if ok else 'FEHLER'
        print(f'  {marke}  {name:20s} {gegen_bg:5.2f}:1  (soll >= 3.0)')
    print()

    if fehler:
        print(f'{len(fehler)} Kontrastfehler:')
        for f in fehler:
            print('  - ' + f)
        return 1

    print('Alle Farbkombinationen erfuellen WCAG 2.1 AA.')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
