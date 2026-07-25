#!/usr/bin/env python3
"""Prueft interne Verweise in allen Markdown-Dateien des Projekts.

Aufruf:  python3 deployment/verweise-pruefen.py
Rueckgabe: 0 wenn alle Verweise aufloesen, 1 sonst.

Hintergrund: Die Dokumentation verweist stark aufeinander — docs/00-vision.md
zeigt auf docs/06, die Feature-Dokumente zeigen auf docs/11, und der README
zeigt auf alles. Ein umbenanntes Dokument bricht diese Kette still. Der Test
faengt das ab, bevor jemand einem toten Verweis folgt.

Externe Verweise (http, https, mailto) werden bewusst NICHT geprueft: Ein
fremder Server, der gerade nicht antwortet, darf keine Pruefung rot faerben.
"""

import os
import re
import sys

# Verzeichnisse, die keine Projektdokumentation enthalten.
UEBERSPRINGEN = {'.git', 'vendor', 'node_modules', '.phpunit.cache', 'upload'}

VERWEIS = re.compile(r'\[[^\]]*\]\(([^)]+)\)')


def markdown_dateien(wurzel: str):
    for verzeichnis, unterverzeichnisse, dateien in os.walk(wurzel):
        unterverzeichnisse[:] = [
            u for u in unterverzeichnisse
            if u not in UEBERSPRINGEN and not u.startswith('.')
        ]
        for datei in dateien:
            if datei.endswith('.md'):
                yield os.path.join(verzeichnis, datei)


def pruefen(wurzel: str) -> list[str]:
    fehler = []

    for pfad in markdown_dateien(wurzel):
        with open(pfad, encoding='utf-8') as f:
            inhalt = f.read()

        for ziel in VERWEIS.findall(inhalt):
            ziel = ziel.strip()

            # Externe Verweise und reine Sprungmarken auslassen.
            if ziel.startswith(('http://', 'https://', 'mailto:', '#')):
                continue

            # Sprungmarke am Ende abschneiden: datei.md#abschnitt
            datei = ziel.split('#')[0]
            if datei == '':
                continue

            aufgeloest = os.path.normpath(os.path.join(os.path.dirname(pfad), datei))

            if not os.path.exists(aufgeloest):
                fehler.append(f'{os.path.relpath(pfad, wurzel)} -> {ziel}')

    return fehler


def main() -> int:
    wurzel = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    fehler = pruefen(wurzel)

    if fehler:
        print(f'{len(fehler)} tote Verweise:')
        for f in sorted(fehler):
            print('  - ' + f)
        return 1

    print('Alle internen Verweise loesen auf.')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
