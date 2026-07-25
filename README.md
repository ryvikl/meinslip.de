# MeinSlip.de

Werbefreie Progressive Web App, die drei bisher getrennte Märkte in einem Produkt verbindet:

1. **Content-Monetarisierung** — Abos, Pay-per-View, bezahlte Chats
2. **Physischer Marktplatz** — konfigurierbare Fetisch-Produkte mit anonymem Versand
3. **Treuhandgesicherte persönliche Übergabe** — das einzige Feature, das im gesamten untersuchten Markt niemand anbietet

Finanziert ausschließlich über Transaktionsprovision. Keine Werbung.

---

## Projektstand

**Konzeptphase.** In diesem Repository liegt noch kein Code, sondern die Wissensbasis, aus der gebaut wird: Marktanalyse, Wettbewerb, belegte Marktlücken, Feature-Konzepte, Rechts- und Steuerrahmen, Architektur und ein priorisierter Backlog.

Stand: 25. Juli 2026

## Lesereihenfolge

Wer neu dazukommt, liest in dieser Reihenfolge:

| # | Dokument | Worum es geht |
| :-- | :--- | :--- |
| 1 | [`docs/00-vision.md`](docs/00-vision.md) | Positionierung, Zielbild, bewusste Nicht-Ziele |
| 2 | [`docs/03-luecken-differenzierer.md`](docs/03-luecken-differenzierer.md) | Warum es dieses Produkt geben sollte — 15 belegte Marktlücken |
| 3 | [`docs/01-markt-wettbewerb.md`](docs/01-markt-wettbewerb.md) | Wer schon da ist, was er nimmt, was er nicht kann |
| 4 | [`docs/02-pains.md`](docs/02-pains.md) | Die Schmerzpunkte beider Seiten, mit Schwere und Häufigkeit |
| 5 | [`docs/04-features/`](docs/04-features/) | Die Features im Detail |
| 6 | [`docs/05-recht-compliance.md`](docs/05-recht-compliance.md) | Jugendschutz, DSA, Datenschutz, Verbraucherrecht |
| 7 | [`docs/06-steuern-kommissionsmodell.md`](docs/06-steuern-kommissionsmodell.md) | Warum die GmbH selbst Verkäuferin wird |
| 8 | [`docs/07-zahlungsverkehr.md`](docs/07-zahlungsverkehr.md) | Wallet, Treuhand, Zahlungsdienstleister |
| 9 | [`docs/08-architektur.md`](docs/08-architektur.md) | Datenmodell, Rollen, Ledger, Zustandsautomaten |
| 10 | [`docs/09-design-system.md`](docs/09-design-system.md) | Farben, Typografie, Komponenten, Tonalität |
| 11 | [`docs/10-backlog.md`](docs/10-backlog.md) | User Stories mit Akzeptanzkriterien, priorisiert |
| 12 | [`docs/11-offene-fragen.md`](docs/11-offene-fragen.md) | Was Fachanwalt, Steuerberater und Logistiker klären müssen |

Wer es eilig hat, liest **00**, **03** und **11**.

## Die fünf Entscheidungen, die alles andere bestimmen

| Entscheidung | Kurz |
| :--- | :--- |
| **Ein Konto, alle Rollen** | Wer kauft, kann auch verkaufen. Verkaufen wird durch Identitätsprüfung freigeschaltet, nicht durch ein zweites Konto. |
| **Die GmbH ist die Verkäuferin** | Kommissionsmodell nach §§ 383 ff. HGB. Das ist die einzige Konstruktion, unter der Creator pseudonym bleiben dürfen. |
| **Provision nur auf Ware** | Treffen ohne Warenbezug erzeugen nie eine Zahlung über die Plattform. Rechtliche Brandmauer, kein Kulanzversprechen. |
| **Deutschland zuerst, EU-fähig gebaut** | Ein Land zum Start, aber Sprache, Steuer, Altersprüfung und Versand von Anfang an als austauschbare Länder-Adapter. |
| **PWA statt App** | Apple und Google dulden keine Adult-Apps. Im gesamten untersuchten Markt hat kein Anbieter eine Store-App — die PWA ist Standard, kein Kompromiss. |

## Woher die Aussagen stammen

Die Marktanalyse beruht auf einer Recherche mit 26 parallelen Agenten. **Das Suchbudget war ab der Hälfte erschöpft, danach blockierte der Egress-Proxy jeden Seitenabruf.** Die Trennlinie verläuft dadurch scharf:

**Belegt — 413 abrufbare Fundstellen:**
- Marktplätze für getragene Wäsche, international und DACH (72)
- DACH-Content- und Abo-Plattformen (109)
- DACH-Community, Verifizierung und Treffen (34)
- Globale Abo- und Clip-Plattformen (62)
- Altersverifikation in EU und UK (75)
- Umsatzsteuer und Verbraucherrecht (61)

**Unbelegt — Strukturanalyse ohne eine einzige Quelle, in den Dokumenten durchgängig gekennzeichnet:**
- Schmerzpunkte beider Marktseiten
- Treuhandmechaniken außerhalb des Erotikbereichs
- Zahlungsaufsichtsrecht und Konditionen der Zahlungsdienstleister
- Safe-Ship (Versandbedingungen, Schnittstellen, Kosten)
- Safe-Meet (Vergleichszahlen; die Angriffsanalyse selbst trägt aus sich heraus)
- Der Konflikt zwischen DSA-Händlertransparenz und Creator-Pseudonymität
- Rechtsprechung zur Trennung von Ware und Treffen

Auch bei den belegten Teilen gilt: Die Angaben stammen aus Suchergebnis-Zusammenfassungen, nicht aus gelesenem Primärtext.

Jede Zahl in diesen Dokumenten ist entweder mit einer Quelle in [`docs/quellen/`](docs/quellen/) hinterlegt oder im Text als unbelegt gekennzeichnet. Es gibt keine dritte Kategorie.

> **Kein Dokument in diesem Repository ist Rechtsberatung.** Die Rechtsteile sind Rechercheergebnisse, die eine anwaltliche Prüfung vorbereiten, nicht ersetzen. Die zu klärenden Fragen stehen gesammelt in [`docs/11-offene-fragen.md`](docs/11-offene-fragen.md).

## Ausgangsdokumente

In [`konzepte-eingang/`](konzepte-eingang/) liegen die beiden Konzepte, mit denen das Projekt startete — unverändert, inklusive der Annahmen, die die Recherche später widerlegt hat. Sie werden bewusst nicht nachträglich korrigiert, damit nachvollziehbar bleibt, was sich warum geändert hat.
