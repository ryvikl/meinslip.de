# Offene Fragen

> **Stand:** 25.07.2026
>
> Dies ist das wichtigste Dokument im Repository. Es sammelt, was geklärt sein muss, bevor MeinSlip online gehen kann —
> und was so schwer wiegt, dass es die Entwicklungsreihenfolge bestimmt.
>
> **Entwicklung kann und soll parallel laufen.** Diese Fragen blockieren den Livegang, nicht den Code. Zwei Ausnahmen
> sind markiert: Dort ist der Code selbst betroffen und bereits gesperrt.

---

## Rangliste nach Schadenshöhe

| # | Frage | Adressat | Blockiert |
| :-- | :--- | :--- | :--- |
| 1 | Ist das Guthaben erlaubnispflichtig? | Fachanwalt Bank- und Aufsichtsrecht | **Code — bereits gesperrt** |
| 2 | Ist § 25a UStG anwendbar? | Steuerberatung mit Umsatzsteuerschwerpunkt | Wirtschaftlichkeit |
| 3 | Gibt es überhaupt einen passenden Zahlungsdienstleister? | Vertrieb, parallele Anfragen | Livegang |
| 4 | Welches Altersverifikationsverfahren, und welche Bewertung gilt dafür? | Anbieter plus Fachanwalt | Livegang |
| 5 | Trägt die Trennung von Ware und Treffen? | Fachanwalt Straf- und Gewerberecht | **Safe-Meet — nicht in V1 bauen** |
| 6 | Was verlangen die Kartennetzwerke? | Acquirer | Livegang |
| 7 | Funktioniert Safe-Ship technisch und vertraglich? | Logistikpartner | Warenstrecke |

---

## 1. Ist das Guthaben erlaubnispflichtig?

**Adressat:** Fachanwalt für Bank- und Kapitalmarktrecht · **Status: Code gesperrt**

Ein aufladbares, jederzeit rückforderbares Guthaben kann ein **erlaubnispflichtiges Einlagengeschäft nach § 1 Abs. 1 S. 2 Nr. 1 KWG** sein. Daneben stehen E-Geld-Geschäft und Finanztransfergeschäft nach dem ZAG.

> **Das Kommissionsmodell beantwortet diese Frage nicht.** Es klärt, wer Verkäufer ist — nicht, was die Annahme rückzahlbarer Gelder des Publikums ist. Das Ausgangskonzept hat beides zusammengeworfen.

**Konkret zu fragen:**
- Ist ein Prepaid-Guthaben mit Rückzahlungsanspruch Einlagengeschäft?
- Greift eine Ausnahme, wenn das Guthaben nur für Käufe auf der eigenen Plattform verwendbar ist? Was, wenn es auch auszahlbar ist?
- Ändert sich die Einordnung, wenn ein lizenzierter Partner Kontoinhaber ist und MeinSlip nur technisch vermittelt?
- Was gilt für die Umbuchung von Verkaufserlösen in ausgebbares Guthaben?

**Bereits umgesetzt:** `MeinSlip\Domain\Ledger\Guthaben` wirft `AufsichtsrechtGesperrt`, solange `ZAHLUNG_GUTHABEN_AKTIV` nicht gesetzt ist. Das Hauptbuch bleibt unberührt — es ist unabhängig davon die richtige Struktur.

**Ausweg bei negativem Ergebnis:** Direktzahlung je Bestellung. Der Betrag geht unmittelbar in die Treuhandbindung, ohne dass je ein rückforderbarer Saldo entsteht. Kostet Bequemlichkeit und einen Teil der Diskretionswirkung — aber keine Lizenz.

---

## 2. Ist § 25a UStG anwendbar?

**Adressat:** Steuerberatung mit Umsatzsteuerschwerpunkt · **Diese eine Frage entscheidet über die Wirtschaftlichkeit.**

Im Kommissionsmodell schuldet die GmbH Umsatzsteuer auf den vollen Verkaufspreis. Ist die Creatorin Kleinunternehmerin, weist sie keine Umsatzsteuer aus — es entsteht eine Lücke, die die Provision auffressen kann.

Die überschlägige Rechnung aus der kritischen Prüfung: Bei 40 € Warenpreis, Kleinunternehmerin als Creator und einem auf Erwachsenenangebote spezialisierten Zahlungsdienstleister liegt der Deckungsbeitrag **ohne** Differenzbesteuerung bei etwa 0,21 € und **mit** bei etwa 4,68 €. Der Break-even verschiebt sich damit um mehr als das Zwanzigfache.

*(Die Zahlen sind eine Modellrechnung mit angenommenen Sätzen, keine belegten Werte — genau deshalb muss die Frage beantwortet werden.)*

**Konkret zu fragen:**
- Ist § 25a UStG auf die nach § 3 Abs. 3 UStG fingierte Lieferung vom Kommittenten zum Kommissionär anwendbar, wenn der Kommittent Kleinunternehmer ist?
- Gilt getragene Wäsche als Gebrauchtgegenstand im Sinne der Norm?
- Falls nein: Welche Gestaltung trägt stattdessen?

### Ein Zielkonflikt, der dabei aufzulösen ist

> **Der Trage-Konfigurator und die Differenzbesteuerung könnten einander widersprechen.**

Das Konzept braucht den Konfigurator als rechtliches Fundament, weil er jede Bestellung zur Anfertigung nach Kundenspezifikation macht und damit den Widerruf ausschließt (§ 312g Abs. 2 Nr. 1 BGB). Die Differenzbesteuerung setzt dagegen den Wiederverkauf eines **gebrauchten** Gegenstands voraus. Beides zugleich zu behaupten — individuell angefertigt **und** gebraucht wiederverkauft — könnte sich ausschließen.

**Die Alternative, falls sich das nicht auflösen lässt:** Widerrufsausschluss über § 312g Abs. 2 Nr. 3 BGB (versiegelte Hygieneware) mit tatsächlicher Versiegelung und aufgedrucktem Hinweis, und der Konfigurator wird vom Rechtsfundament zum reinen Produktmerkmal zurückgestuft. Das ist der schwächere Rechtsgrund — der EuGH legt die Hygieneausnahme in `slewo` eng aus —, aber er kollidiert nicht mit der Steuergestaltung.

**Diese Frage muss zusammen mit Frage 1 beantwortet werden. Sie ändert im Zweifel das Datenmodell.**

---

## 3. Gibt es einen passenden Zahlungsdienstleister?

**Adressat:** parallele Anfragen bei mehreren Anbietern · **Erster Meilenstein des Projekts, nicht der fünfte.**

Gebraucht werden vier Dinge gleichzeitig: Akzeptanz von Erwachsenenangeboten einschließlich getragener Intimwäsche, Marktplatzabwicklung mit Treuhand und Auszahlung an Dritte, SEPA in Euro auf deutsche Konten, auskömmliche Konditionen.

**Die Strukturhypothese lautet: Diese Kombination existiert nicht als ein Produkt.** Sie ist unverifiziert und muss geprüft werden.

**Schriftlich zu bestätigen — nicht aus den AGB lesen, sondern im Gespräch klären:**
- Ist getragene Intimwäsche ausdrücklich zulässig?
- Treuhand mit Auszahlung an Dritte möglich?
- SEPA in beide Richtungen?
- Provision, Rückstellungen, Auszahlungsfristen?
- Kündigungsfristen und Gründe — was passiert bei einer Beschwerde?
- Wer übernimmt die Registrierung bei den Kartennetzwerken?

Parallel anfragen, nicht nacheinander. Eine Absage kostet sonst Monate.

---

## 4. Altersverifikation

**Adressat:** Anbieter, bestätigt durch Fachanwalt

Das AVS-Raster der KJM verlangt zwei Stufen: qualifizierte Identifizierung **und** Authentifizierung bei jedem Nutzungsvorgang. Die im Ausgangskonzept geplante Variante ist nicht zulässig — siehe [`05-recht-compliance.md`](05-recht-compliance.md).

**Konkret zu klären:**
- Welches Verfahren wird lizenziert? Für **welche Norm** gilt dessen KJM-Bewertung — § 4 Abs. 2 JMStV oder nur § 5 Abs. 3 Nr. 1?
- Deckt es die Identifizierung, die Authentifizierung oder beides?
- Kosten je Verifizierung, Abbruchquote, Rückfallverfahren für Ablehnungen?
- Ist die geplante Umsetzung genau die bewertete Bauform? Abweichungen können die Bewertung entwerten.

**Falle, die bereits gefunden wurde:** „Yoti Age Scan" ist nur nach § 5 Abs. 3 Nr. 1 bewertet, nicht für geschlossene Benutzergruppen. Bei jedem Anbieter einzeln prüfen.

### Damit verbunden: die Zonen-Entscheidung

Die Entscheidung „breit starten" bedeutet, dass § 4 Abs. 2 JMStV ab Tag 1 greift. Eine geschlossene Benutzergruppe ist für Suchmaschinen eine Wand — und organische Suche ist der einzige Wachstumskanal, der bleibt, wenn Meta, Google und TikTok keine Werbung für Erwachsenenangebote zulassen.

**Empfehlung aus der Prüfung, als harte Bauvorgabe:** Zwei Zonen. Alles ohne Anmeldung Sichtbare bleibt nicht-pornografisch — Kategorienbaum, Produktseiten ohne Nacktheit, Preise, Ratgeber — und damit indexierbar. Die Altersschranke liegt vor dem expliziten Bereich, nicht vor dem Katalog.

Das ist keine juristische Frage, sondern eine Architekturentscheidung, die jetzt getroffen werden muss.

---

## 5. Trägt die Trennung von Ware und Treffen?

**Adressat:** Fachanwalt für Straf- und Gewerberecht · **Status: Safe-Meet nicht in V1 bauen**

Die Projektentscheidung lautet: Warenübergabe mit Provision, Treffen ohne Warenbezug provisionsfrei. Die einschlägige Rechtsprechung konnte **nicht recherchiert werden**.

**Konkret zu fragen:**
- Wo verläuft die Grenze zwischen Warenübergabe und Vermittlung sexueller Dienstleistungen?
- Ab wann greift die Erlaubnispflicht nach § 12 ProstSchG?
- Welches Risiko entsteht aus §§ 180a, 181a StGB, wenn die Plattform an einer Übergabe verdient, die faktisch ein Treffen ist?
- Reicht die Trennung „Provision nur auf Ware", wenn die Übergabe erkennbar der eigentliche Zweck ist?
- Welche Betragsobergrenze ist verteidigbar?

**Empfehlung aus drei unabhängigen Prüfungen:** Safe-Meet nicht in der ersten Fassung bauen. Nicht wegen des Rechts allein — sondern weil das Feature ohne lokale Nutzerdichte ohnehin keine einzige zusätzliche Transaktion erzeugt, während es die gesamte Haftungs- und Presseangriffsfläche eröffnet.

Das Versprechen darf in Marke und Kommunikation ab Tag 1 stehen. Der Code nicht.

Eine schärfere Fassung des Einwands, die im Dokument bleiben soll: *Die Plattform verkauft Anonymität und Schutz — und stellt mit der persönlichen Übergabe ausgerechnet das Feature ins Schaufenster, das beides in einem einzigen Vorgang und unumkehrbar aufhebt. Eine Treuhand sichert eine Zahlung ab. Sie suggeriert aber die Absicherung eines Menschen. Zwischen diesen beiden Dingen liegt die gesamte Haftung.*

---

## 6. Auflagen der Kartennetzwerke

**Adressat:** Acquirer beziehungsweise Zahlungsdienstleister

Die Auflagen von Visa und Mastercard für Erwachsenenangebote werden **schneller durchgesetzt als das Jugendschutzrecht**. Die Aufsicht leitet ein Verfahren ein; ein Kartennetzwerk kündigt die Akzeptanz.

**Zu klären:**
- Kosten und Auflagen der Registrierungsprogramme
- Welche Alters- und Einwilligungsnachweise sind für jede erkennbare Person im Material zu führen?
- Welche Fristen gelten für die Bearbeitung von Beschwerden?
- Ab welcher Rückbuchungsquote drohen Programme oder Kündigung?
- Ist eine Vorabprüfung von Uploads verlangt oder genügt eine nachgelagerte?

**Bauvorgabe, die sich daraus jetzt schon ergibt:** Der Einwilligungsnachweis gehört in den Hochladevorgang, nicht in die AGB. Material bleibt gesperrt, bis für jede erkennbare Person der Nachweis vorliegt. Nachträglich einzuführen ist das ungleich teurer.

---

## 7. Safe-Ship

**Adressat:** Logistikpartner

**Zu klären:**
- Kann die GmbH als Absender auftreten, obwohl das Paket von einer anderen Adresse eingeliefert wird?
- Gibt es eine Einlieferung per Code im Paketshop, bei der das Etikett erst vor Ort gedruckt wird? **Das ist die entscheidende Frage** — nur so sieht die Verkäuferin die Käuferadresse nie.
- Wohin gehen Rückläufer, und wer nimmt sie an?
- Kosten je Sendung?
- Sind Erwachsenenangebote in den Geschäftsbedingungen zulässig?
- Wen trifft die Registrierungspflicht nach dem Verpackungsgesetz, wenn die Plattform Etikett und Verpackung stellt?

**Bereits geklärt:** Ein Postfach funktioniert **nicht** — es nimmt keine Pakete an und fällt damit als Rückläuferziel aus. Die Annahme im Ausgangskonzept ist falsch.

---

## Weitere Fragen

**Datenschutz.** Die Folgenabschätzung nach Art. 35 DSGVO ist zwingend und muss **vor** der Architekturentscheidung beginnen, weil ihre Ergebnisse Speicherfristen und GPS-Granularität bestimmen. Adressat: Datenschutzbeauftragte:r.

**Steuer.** Exakte Meldeschwellen nach PStTG. Umsatzsteuerliche Behandlung von Creatorn ohne Umsatzsteuerausweis. Auswirkungen des ViDA-Pakets und ab wann.

**Gewährleistung.** In welchem Umfang ist der Regress gegenüber Creatorn vertraglich durchsetzbar, wenn die GmbH gegenüber dem Käufer haftet?

**Barrierefreiheit.** Gilt das BFSG, oder greift die Ausnahme für Kleinstunternehmen? Unabhängig davon wird nach WCAG 2.1 AA gebaut — die Kontrastprüfung läuft bereits mit.

---

## Was die Recherche selbst noch schuldig ist

Das Suchbudget war ab der Hälfte erschöpft. Ein Folgelauf mit funktionierendem Zugang sollte in dieser Reihenfolge nachholen:

1. Konditionen und Branchenakzeptanz der Zahlungsdienstleister
2. Versandbedingungen und Schnittstellen der Logistiker
3. Rechtsprechung zur Abgrenzung von Ware und Treffen
4. Belege für die Schmerzpunkte — Reddit, Trustpilot, Verbraucherschutz
5. Primärtext der Rechtsquellen; bisher liegen nur Suchergebnis-Zusammenfassungen vor

Die vollständige Prüfliste steht in [`quellen/02-pains.md`](quellen/02-pains.md) und [`quellen/05-recht.md`](quellen/05-recht.md).
