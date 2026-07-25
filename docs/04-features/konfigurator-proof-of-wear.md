# Trage-Konfigurator und Echtheitsnachweis

> **Stand:** 25.07.2026 · **Belegstatus:** Die umsatzsteuerliche Seite ist belegt, die verbraucherrechtliche Einordnung
> ist Strukturanalyse. **Achtung: Dieses Feature steht in einem ungelösten Zielkonflikt** — siehe unten.

## Was es ist

Der Käufer kauft kein fertiges Produkt, sondern konfiguriert es: Grundartikel plus Optionen wie längere Tragedauer, beim Sport getragen, bestimmter Wunsch. Jede Option hat einen Aufpreis.

Als Umsatzidee ist das naheliegend. **Tatsächlich ist es der Grund, warum das Warengeschäft ohne Rücknahmerisiko funktioniert.**

## Warum es rechtlich trägt

Der naheliegende Widerrufsausschluss für **versiegelte Hygieneartikel** (§ 312g Abs. 2 Nr. 3 BGB) trägt bei getragener Wäsche **nicht verlässlich** — der EuGH legt die Ausnahme in `slewo` (C-681/17) eng aus.

Deshalb stützt sich der Ausschluss auf **§ 312g Abs. 2 Nr. 1 BGB**: Waren, die nach Kundenspezifikation angefertigt oder eindeutig auf persönliche Bedürfnisse zugeschnitten sind.

> **Daraus folgt eine harte Produktanforderung:** Jede Warenbestellung braucht mindestens eine echte, vom Käufer gesetzte Spezifikation, protokolliert mit Zeitstempel.

*Im Code durchgesetzt:* `Bestellungen::anlegen()` weist eine Position ohne Spezifikation ab, und die Tabelle `bestellung_spezifikationen` führt `festgelegt_am` als Pflichtfeld. Ein Test hält es fest.

Damit ist der Konfigurator keine Bequemlichkeit, sondern Pflichtbestandteil.

## Der ungelöste Zielkonflikt

> **Der Konfigurator und die Differenzbesteuerung nach § 25a UStG könnten einander ausschließen.**

Das Kommissionsmodell macht die GmbH umsatzsteuerlich zur Verkäuferin des vollen Betrags. Ist die Creatorin Kleinunternehmerin, entsteht eine Lücke, die die Provision auffressen kann — die Modellrechnung zeigt einen Unterschied um mehr als das Zwanzigfache beim Break-even. Der Ausweg wäre die Differenzbesteuerung.

Nur setzt die den Wiederverkauf eines **gebrauchten** Gegenstands voraus. Der Konfigurator behauptet eine **Individualanfertigung**. Beides zugleich könnte sich ausschließen.

**Die Alternative, falls sich das nicht auflösen lässt:** Widerrufsausschluss über § 312g Abs. 2 Nr. 3 BGB mit tatsächlicher Versiegelung und aufgedrucktem Hinweis; der Konfigurator wird zum reinen Produktmerkmal zurückgestuft. Das ist der schwächere Rechtsgrund, kollidiert aber nicht mit der Steuergestaltung.

**Diese Frage ist offen und ändert im Zweifel das Datenmodell.** [`../11-offene-fragen.md`](../11-offene-fragen.md) Punkt 2.

## Als Produkt

Der Konfigurator ist zugleich das, was diese Ware von Massenware unterscheidet — und der Grund, warum sie nicht durch KI ersetzbar ist. Ein Produkt, das jemand tatsächlich drei Tage lang beim Sport getragen haben muss, lässt sich nicht synthetisieren.

Er löst außerdem einen der größten Schmerzpunkte der Verkäuferseite: **Sonderwünsche laufen über strukturierte, bepreiste Optionen statt über endlose unbezahlte Chatverhandlungen.** Zeitverschwender sind die häufigste Beschwerde im Segment — häufiger als die Gebühren.

Vorschläge im Bestellvorgang, jeweils mit Preis und kurzer Erklärung: zusätzlicher Tragetag, beim Sport getragen, persönlicher Wunsch, Foto-Aktualisierung während der Tragezeit, Eilbearbeitung.

## Echtheitsnachweis

Ein häufiger Betrug im Markt: Das teuer bezahlte „exklusive" Material ist in Wahrheit alt und wurde an Dutzende verkauft. Bei physischer Ware das Gegenstück: nicht wirklich getragen.

**Die Antwort ist eine Auftragsnummer, die im gelieferten Material sichtbar sein muss** — etwa ein handgeschriebener Zettel mit Nummer und Datum im Bild. Technisch trivial, aber es macht Wiederverwendung nachweisbar statt bloß bestreitbar.

Das setzt einen strukturierten Auftragsablauf voraus statt Freitext-Chat. Genau den haben die Abo-Plattformen nicht — und wollen ihn nicht, weil ihr Geschäft an der Wiederverkäuflichkeit desselben Materials hängt.

## Kein Zeitbezug im Konfigurator

Eine Regel aus der rechtlichen Brandmauer: **Der Konfigurator kennt keine Zeiteinheit als Preisbestandteil.** Keine Stundensätze, keine Dauerangaben, keine Zeitfenster. Die Tragedauer eines Kleidungsstücks ist eine Produkteigenschaft; die Dauer eines Treffens wäre eine Dienstleistung.

Der Unterschied klingt spitzfindig und ist er nicht — er entscheidet darüber, ob die Plattform Waren verkauft oder Zeit vermittelt. Siehe [`../05-recht-compliance.md`](../05-recht-compliance.md) Abschnitt 5.
