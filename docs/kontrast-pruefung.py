#!/usr/bin/env python3
"""Prueft die Farbtokens des Nocturne-Designsystems gegen WCAG 2.1 AA.

Aufruf:  python3 docs/kontrast-pruefung.py
Rueckgabe: 0 wenn alle Werte bestehen, 1 wenn mindestens einer durchfaellt.

Das Barrierefreiheitsstaerkungsgesetz gilt seit dem 28.06.2025 fuer den
elektronischen Geschaeftsverkehr und verlangt WCAG 2.1 AA: Text braucht 4.5:1,
Bedienelemente und ihre Rahmen nach 1.4.11 mindestens 3:1.

Die Werte muessen mit public/assets/css/nocturne.css uebereinstimmen. Das
Skript liest das CSS NICHT ein, es haelt die Hexwerte als Literale — wer eine
Farbrolle ergaenzt oder einen Wert dreht, muss hier von Hand nachziehen, sonst
meldet die CI weiter gruen fuer Farben, die es nicht mehr gibt.

Beim Uebernehmen des Designsystems fand diese Pruefung einen echten Mangel:
Der Rahmen von Eingabefeldern erreichte im Ruhezustand nur 1.58:1. Deshalb
gibt es dort jetzt --color-feld-rahmen mit 40 % Textfarbe statt der 16 % des
allgemeinen Trenners.
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


def mischen(vordergrund, hintergrund, anteil):
    """Bildet color-mix(in srgb, VG anteil%, HG) nach."""
    v = [int(vordergrund.lstrip('#')[i:i + 2], 16) for i in (0, 2, 4)]
    h = [int(hintergrund.lstrip('#')[i:i + 2], 16) for i in (0, 2, 4)]
    return '#%02x%02x%02x' % tuple(round(anteil * v[i] + (1 - anteil) * h[i]) for i in range(3))


BG = '#161826'        # --color-bg
SURFACE = '#232532'   # --color-surface
TEXT = '#e9e9ed'      # --color-text

HINTERGRUENDE = {'bg': BG, 'surface': SURFACE}

# Schrift auf den Flaechen — Schwelle 4.5:1
SCHRIFT = {
    'text': TEXT,
    'accent': '#9184d9',
    'accent-300': '#d2cefd',
    'accent-400': '#b5abfc',
    'accent-500': '#968ae0',
    # Die zweite Akzentrampe wird genauso geprueft wie die erste: als Schrift
    # kommen nur die Stufen 300 bis 500 in Frage, darunter wird es zu dunkel.
    'accent-2': '#a7a1db',
    'accent-2-300': '#d2cefd',
    'accent-2-400': '#b5afe8',
    'accent-2-500': '#9690c9',
    'neutral-300': '#cfd3e5',
    'neutral-400': '#b2b6ca',
    'neutral-500': '#9397ab',
    # Statusfarben — laut Design-Vorlage nur fuer Verifiziert/Erfolg, Warnung
    # und SOS. Sie stehen auch als kleine Schrift ("online", "bestaetigt"),
    # deshalb gilt fuer sie die Textschwelle, nicht die 3:1 der
    # Bedienelemente. Das Rot ist NICHT das #ef4444 der Comps: das erreicht
    # auf der Kartenflaeche nur 4.04:1. Herleitung des Ersatzes in
    # docs/09-design-system.md, Korrektur 1.
    'ok': '#22c55e',
    'warnung': '#f59e0b',
    'gefahr': '#f35b5b',
}

# Diese Ramp-Stufen sind fuer Flaechen und Tints gedacht, nicht fuer Schrift.
# Sie werden bewusst NICHT als Schrift geprueft — wer sie doch als Schrift
# einsetzt, verletzt die Vorgabe des Designsystems.
NUR_FLAECHE = {
    'neutral-600': '#75798c',
    'neutral-700': '#595d6c',
    'neutral-800': '#3f424d',
    'neutral-900': '#292b31',
    'accent-2-600': '#7972a9',
    'accent-2-700': '#5c5783',
    'accent-2-800': '#423e5d',
    'accent-2-900': '#2b293a',
}

# Bedienelemente — Schwelle 3:1 nach WCAG 1.4.11
BEDIENELEMENTE = {
    'Rahmen Eingabefeld (--color-feld-rahmen, 40 % Text)': mischen(TEXT, SURFACE, 0.40),
    'Rahmen Eingabefeld bei Hover (45 % Text)': mischen(TEXT, SURFACE, 0.45),
    'Rahmen Eingabefeld bei Fokus (Akzent)': '#9184d9',
    'Rahmen .btn-primary (Akzent)': '#9184d9',
}

# Tints mit Text darauf — Schwelle 4.5:1
#
# Hier stehen auch --color-section, --color-section-glow und
# --color-section-ghost. Sie sind laut nocturne.css ausdruecklich Deck-Flaechen
# und keine Interface-Farben, also nie Schrift — geprueft wird deshalb die
# Schrift, die auf ihnen liegt, gegen die Schwelle fuer Text.
TINTS = {
    '.tag-neutral: neutral-100 auf neutral-800': ('#f3f5fe', '#3f424d'),
    '.tag-accent: accent-100 auf accent-800': ('#f5f4ff', '#423a6a'),
    '.tag-accent-2: accent-2-100 auf accent-2-800': ('#f5f4ff', '#423e5d'),
    'Deck: Text auf --color-section': (TEXT, '#262a60'),
    'Deck: Text auf --color-section-glow': (TEXT, '#353b80'),
    'Deck: Text auf --color-section-ghost': (TEXT, '#4c5397'),
}


def main():
    fehler = []

    for hname, hwert in HINTERGRUENDE.items():
        print(f'=== Schrift auf {hname} ({hwert}) ===')
        for name, farbe in SCHRIFT.items():
            wert = kontrast(farbe, hwert)
            ok = wert >= 4.5
            if not ok:
                fehler.append(f'{name} auf {hname}: {wert:.2f}:1, noetig 4.5:1')
            print(f'  {"ok    " if ok else "FEHLER"}  {name:14s} {wert:5.2f}:1')
        print()

    print('=== Bedienelemente gegen die Kartenflaeche (1.4.11, 3:1) ===')
    for name, farbe in BEDIENELEMENTE.items():
        wert = kontrast(farbe, SURFACE)
        ok = wert >= 3.0
        if not ok:
            fehler.append(f'{name}: {wert:.2f}:1, noetig 3.0:1')
        print(f'  {"ok    " if ok else "FEHLER"}  {name:46s} {wert:5.2f}:1')
    print()

    print('=== Schrift auf getoenten Flaechen ===')
    for name, (schrift, flaeche) in TINTS.items():
        wert = kontrast(schrift, flaeche)
        ok = wert >= 4.5
        if not ok:
            fehler.append(f'{name}: {wert:.2f}:1, noetig 4.5:1')
        print(f'  {"ok    " if ok else "FEHLER"}  {name:46s} {wert:5.2f}:1')
    print()

    print('=== Nur fuer Flaechen, nicht als Schrift verwenden ===')
    for name, farbe in NUR_FLAECHE.items():
        wert = kontrast(farbe, BG)
        print(f'          {name:14s} {wert:5.2f}:1  (als Schrift unzulaessig)')
    print()

    if fehler:
        print(f'{len(fehler)} Kontrastfehler:')
        for f in fehler:
            print('  - ' + f)
        return 1

    print('Alle geprueften Farbkombinationen erfuellen WCAG 2.1 AA.')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
