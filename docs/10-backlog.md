# Backlog

> **Stand:** 25.07.2026 · Priorisiert nach dem, was später nicht nachrüstbar ist und was das Versprechen trägt.
>
> Der Vollausbau ist nach der Aufwandsschätzung aus der Recherche realistisch **14 bis 18 Monate**. Die
> Projektentscheidung lautet „breit starten"; dieser Backlog setzt das um, benennt aber offen, wo mehrere unabhängige
> Prüfungen davon abgeraten haben.

---

## Reihenfolge und Begründung

**Zuerst kommt, was sich später nicht nachrüsten lässt.** Ein Kontenmodell, ein Hauptbuch und eine Vertragskonstruktion im Nachhinein zu ändern bedeutet, bestehende Konten, Buchungen und Rechnungen zu migrieren — teuer und fehleranfällig. Eine Chatfunktion später zu ergänzen kostet nur die Chatfunktion.

**Danach kommt die Warenstrecke.** Sie ist der KI-immune Teil, sie trägt das Alleinstellungsmerkmal, und sie ist der einzige Bereich, in dem die Treuhand juristisch unstrittig ist.

**Zuletzt kommt, was Nutzerdichte braucht.** Persönliche Übergabe und Chat entfalten ohne lokale Dichte beziehungsweise ohne Publikum keinen Wert.

---

## Stufe 1 — Fundament · **erledigt**

| Story | Status |
| :--- | :--- |
| Ein Konto, alle Rollen; Verkaufen als freischaltbare Fähigkeit | ✅ Schema |
| Hauptbuch mit doppelter Buchführung, zwei Töpfe je Konto | ✅ mit Tests |
| Kommissionsmodell im Datenmodell: Einkauf und Verkauf getrennt | ✅ Schema |
| Preiszerlegung ohne Centverlust | ✅ über 220 Fälle geprüft |
| Zustandsautomat mit erzwungenem Einspruchsfenster | ✅ mit Tests |
| Erzwungene Kundenspezifikation je Warenbestellung | ✅ mit Tests |
| Erkennung verbundener Konten | ✅ mit Tests |
| Länder-Adapter mit Funktionsschaltern | ✅ Schema |
| Mehrsprachigkeit ab Zeile 1 | ✅ |
| Guthaben hinter Aufsichtsrechts-Sperre | ✅ mit Tests |
| PWA-Gerüst, korrigierte Designtokens, Kontrastprüfung | ✅ |
| Deployment über SFTP mit Prüfschritten | ✅ |

---

## Stufe 2 — Warenstrecke bis zum ersten echten Verkauf

Das Ziel dieser Stufe: Eine Verkäuferin kann ein Angebot einstellen, ein Käufer kann es bestellen, das Geld liegt sicher, die Ware kommt an, die Auszahlung erfolgt.

### Konto und Zugang

- **Registrierung und Anmeldung** mit Pseudonym, Passwort nach aktuellem Stand gehasht
- **Altersverifikation Stufe 1** über einen lizenzierten Anbieter — *abhängig von [`11-offene-fragen.md`](11-offene-fragen.md) Punkt 4*
- **Zugangs-Gate je Nutzungsvorgang** (Adult-PIN oder Passkey). *Das AVS-Raster verlangt es ausdrücklich; angemeldet sein genügt nicht.*
- **Verkäufer-Freischaltung** nach Identitätsprüfung, gekoppelt an die erste Auszahlung
- **Zwei Zonen:** alles ohne Anmeldung Sichtbare bleibt nicht-pornografisch und damit indexierbar. *Ohne das stirbt der einzige verbleibende Wachstumskanal.*

### Katalog

- **Kategorienbaum** als Discovery-Motor, deutschsprachig, tief genug für Nischen
- **Angebot anlegen** mit Bildern und serverseitig erzeugter unscharfer Vorschau
- **Konfigurator-Optionen** mit Kennzeichnung, welche als Spezifikation zählen
- **Attributsuche** — filtern nach Art, Material, Tragedauer, Größe, Lieferart, Preis. *Nicht Namenssuche: Der Markt zeigt, dass Taxonomie allein Kaufabsicht trägt.*

### Bestellung

- **Konfigurator im Bestellvorgang** mit Live-Preis und erzwungener Spezifikation ✅ *Frontend vorhanden, Anbindung offen*
- **Bezahlung** — *abhängig von [`11-offene-fragen.md`](11-offene-fragen.md) Punkt 1 und 3*
- **Auftragsverwaltung** für die Verkäuferin: annehmen, ablehnen, vorbereiten
- **Einspruch und Streitfall** mit Moderationsoberfläche
- **Auszahlung** per SEPA ohne Mindestbetrag, mit Karenzzeit bei geänderter Bankverbindung

### Versand

- **Safe-Ship** mit Einlieferungscode statt fertigem Etikett — *abhängig von Punkt 7.* Die Variante mit Etikettdruck durch die Verkäuferin ist **keine** Rückfalllösung: Sie anonymisiert nur eine Richtung.
- **Zustellstatus** und automatische Freigabe nach Ablauf des Einspruchsfensters

### Vertrauen

- **Bewertungen**, getrennt nach Richtung
- **Meldeweg** mit zugesagter Reaktionszeit und Widerspruchsmöglichkeit
- **Sperren**, geräteübergreifend
- **Einwilligungsnachweis im Hochladevorgang** — Material bleibt gesperrt, bis für jede erkennbare Person der Nachweis vorliegt. *Anforderung der Kartennetzwerke, nachträglich ungleich teurer.*

### Pflichten

- Impressum, AGB, Datenschutzerklärung, Widerrufsbelehrung — anwaltlich erstellt
- Kündigungsbutton nach § 312k BGB, ohne Anmeldung erreichbar
- Steuerübersicht mit Export; Meldeschwellen laufend sichtbar
- Barrierefreiheit nach WCAG 2.1 AA ✅ *Prüfung läuft bereits mit*

---

## Stufe 3 — Inhalte

- Abonnements, Einzelkäufe, Feed
- **Bezahlter Chat mit Pflicht-Deklaration, wer schreibt** — Person, autorisiertes Team oder KI-unterstützt, als dauerhaftes Label. *Der stärkste Differenzierungshebel im ganzen Katalog, weil Wettbewerber ihn nicht kopieren können, ohne ihr eigenes Modell zu beschädigen.*
- Forensisches Wasserzeichen je Käufer; Leak-Suche als Plattformleistung
- **Auftragsnummer im gelieferten Material** als Echtheitsnachweis gegen recycelten Inhalt
- Auto-Verlängerung standardmäßig **aus**

---

## Stufe 4 — Persönliche Übergabe

> **Drei unabhängige Prüfungen empfehlen, Safe-Meet nicht in der ersten Fassung zu bauen.** Nicht wegen des Rechts allein, sondern weil das Feature ohne lokale Nutzerdichte keine einzige zusätzliche Transaktion erzeugt, während es die gesamte Haftungs- und Presseangriffsfläche eröffnet.
>
> Das Versprechen darf in Marke und Kommunikation ab Tag 1 stehen. Der Code nicht.

Wenn gebaut wird, dann so:

- Kein Ereignis bewegt Geld; jeder Tap startet nur eine Frist
- NFC-Tap statt statischem QR-Code; QR nur als Rückfall, rotierend und gerätegebunden
- Beidseitig quittiertes, zeitgestempeltes Foto als Streitfallbeweis
- Betragsobergrenze, Geschwindigkeitsgrenzen, Graphanalyse
- Kopplung von Reverse-Gesuch und Übergabe **gesperrt** — sonst ist das eingefrorene Budget ein Werkzeug, um eine bestimmte Person glaubwürdig an einen bestimmten Ort zu bestellen
- Eskalationskette vor jedem Alarm, mit großzügigen Puffern und ausgestaltetem Offline-Fall
- Nötigungsresistente Bestätigung: ein stiller Notfall-Code, der nach außen wie eine normale Bestätigung aussieht

---

## Später

Reverse-Marketplace, Live-Übertragungen, weitere Länder, öffentliche Schnittstelle, heller Modus, Empfehlungen.

---

## Bewusst nicht

| Nicht | Warum |
| :--- | :--- |
| Verkäuferinnen-Abo, Vorabgebühr, „Pro-Account" | Genau das Modell, das der Markt schlecht macht und das angreifbar ist |
| Kaufbare Sichtbarkeit, Ranking-Verkauf | Verschärft den Preisverfall; Ranking läuft über Verlässlichkeit |
| Eigene Kunstwährung mit Auszahlungsgebühr | Verdeckte Provision; genau der Vorwurf gegen einen Wettbewerber |
| Umkreissuche im ersten Jahr | Ort nicht feiner als Bundesland. Umkreissuche plus Erotikprofil ist ein Werkzeug zum Auffinden von Personen |
| Sichtbares Wasserzeichen im gekauften Inhalt | Beschädigt das bezahlte Produkt, kürzester Weg zu Rückbuchungen |
| Öffentlicher Vertrauens-Score für Käufer | Wer öffentlich bewertet wird, kauft nicht mehr |
| KI-Detektor als sichtbares Merkmal | Wettrüsten mit garantierten Falschtreffern; jede falsch markierte echte Creatorin ist ein Vertrauensschaden |
| Treffen als bepreistes Produkt | Rechtliche Brandmauer. Keine Ausnahme |
| Katalog hinter der Altersschranke | Für Suchmaschinen eine Wand — und Suche ist der einzige verbleibende Kanal |
| Bezahlte Werbung bei Meta, Google, TikTok | Erwachsenenangebote sind dort ausgeschlossen; Widerspruchsverfahren sind verlorene Zeit |

---

## Ein Einwand, der im Dokument bleiben soll

Die kritische Prüfung hält **„breit starten" für die riskanteste Variante** und empfiehlt, mit getragener Wäsche zu beginnen — aus einem Grund, der über Marketing hinausgeht: Dieser Bereich ist nicht pornografisch und muss deshalb nicht hinter die Altersschranke. Er bliebe indexierbar und damit bewerbbar, während der Content-Bereich es nicht ist.

Ein zweiter Befund dreht die übliche Annahme um: **Die knappe Marktseite ist der zahlende Käufer, nicht die Verkäuferin.** Das gesamte bisherige Konzept ist auf die Gewinnung von Creatorn ausgerichtet. Wenn das stimmt, ist die Reihenfolge im Kaltstart falsch herum gedacht.

Beides ist eine Entscheidung des Auftraggebers, keine des Backlogs. Die Reihenfolge oben ist so gewählt, dass sie in beide Richtungen offen bleibt: Die Warenstrecke kommt vor den Inhalten, und die Zwei-Zonen-Architektur hält den indexierbaren Bereich frei — unabhängig davon, wie breit am Ende gestartet wird.
