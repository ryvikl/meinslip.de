# Zahlungsverkehr und Aufsichtsrecht

> **Stand:** 25.07.2026 · **Belegstatus: UNBELEGTE STRUKTURANALYSE.** Die Recherche zu Zahlungsdienstleistern lief
> vollständig ohne Suchbudget und ohne Seitenabrufe. **Kein Provisionssatz, keine Gebühr, keine Schwelle und keine
> AGB-Klausel in diesem Dokument ist verifiziert.** Es enthält bewusst keine erfundenen Zahlen — wo eine Zahl fehlt,
> steht, dass sie fehlt.
>
> **Keine Rechtsberatung.** Dieses Dokument bereitet Gespräche mit Fachanwalt und Zahlungsdienstleistern vor.

---

## Die drei Sätze, auf die es ankommt

1. **Das aufladbare Guthaben ist möglicherweise erlaubnispflichtig — und das Kommissionsmodell beantwortet diese Frage nicht.**
2. **Es gibt möglicherweise keinen Zahlungsdienstleister, der alles gleichzeitig kann, was MeinSlip braucht.**
3. **Die Regeln der Kartennetzwerke sind strenger und werden schneller durchgesetzt als das Jugendschutzrecht.**

Alle drei sind vor weiterer Zahlungsentwicklung zu klären. Sie stehen deshalb in [`11-offene-fragen.md`](11-offene-fragen.md) ganz oben.

---

## 1. Das Guthaben ist die gefährlichste Stelle im Konzept

Das Ausgangskonzept nennt korrekt die Gefahr, als Zahlungsdienst eingestuft zu werden, und zieht daraus die richtige Schlussfolgerung: Nutzergelder gehören nicht auf das eigene Firmenkonto. **Die kritische Prüfung hat aber eine zweite, schwerere Frage aufgeworfen, die im Konzept fehlt.**

> **Ein aufladbares Guthaben, das jederzeit rückforderbar ist, kann ein erlaubnispflichtiges Einlagengeschäft nach § 1 Abs. 1 S. 2 Nr. 1 KWG sein.**

Das Kommissionsmodell hilft hier **nicht**. Es löst die Frage, wer Verkäufer ist — nicht die Frage, was die Annahme rückzahlbarer Gelder des Publikums ist. Die beiden Fragen sind unabhängig voneinander, und das Konzept hat sie bisher zusammengeworfen.

Daneben stehen die ohnehin zu klärenden Einordnungen als E-Geld-Geschäft oder Finanztransfergeschäft nach dem Zahlungsdiensteaufsichtsgesetz. Die Ausnahme für begrenzte Netze greift bei einem offenen Marktplatz mit beliebig vielen unabhängigen Anbieterinnen und heterogenem Sortiment vermutlich nicht.

### Was daraus für den Bau folgt

**Die Guthaben-Aufladung bleibt hinter einem Schalter, der ohne anwaltliche Freigabe nicht aktiviert werden kann.** Das ist im Code umgesetzt: `ZAHLUNG_GUTHABEN_AKTIV` ist standardmäßig aus, und der Aufladepfad wirft, solange keine Freigabe dokumentiert ist.

Das Hauptbuch selbst bleibt bestehen — es ist die richtige Struktur unabhängig davon, wie das Geld hereinkommt. Nur der Weg „Nutzer lädt auf und hat ein Guthaben" ist gesperrt.

**Der Ausweg, falls die Prüfung negativ ausfällt:** Direktzahlung je Bestellung statt Guthabenkonto. Der Betrag geht unmittelbar in die Treuhandbindung, ohne dass jemals ein rückforderbarer Saldo entsteht. Das kostet Bequemlichkeit und die Diskretionswirkung einer einzigen unauffälligen Sammelbuchung — aber es kostet keine Banklizenz.

---

## 2. Es gibt möglicherweise keinen passenden Anbieter

MeinSlip braucht von einem Zahlungsdienstleister vier Dinge gleichzeitig:

1. Akzeptanz von Erwachsenenangeboten, ausdrücklich einschließlich getragener Intimwäsche
2. Marktplatzabwicklung mit Treuhand und Auszahlung an Dritte
3. SEPA in Euro auf deutsche Konten, in beide Richtungen
4. Auskömmliche Konditionen

**Die Strukturhypothese der Recherche lautet: Diese Kombination existiert nicht als ein Produkt.** Die beiden Anbieterfamilien sind weitgehend disjunkt:

| Familie | Kann Marktplatz und Treuhand | Akzeptiert Erwachsenenangebote |
| :--- | :--- | :--- |
| EU-Marktplatzinfrastruktur (Mangopay, LemonWay, Online Payment Platform, Stripe Connect, Adyen, Mollie) | ja | **ungeklärt, überwiegend vermutlich nein** |
| Auf Erwachsenenangebote spezialisierte Anbieter (CCBill, Segpay, Epoch, Verotel, RocketGate, Vendo) | **überwiegend nein** | ja |

Diese Aussage ist **unverifiziert**. Sie zu prüfen ist der erste Meilenstein des Projekts, nicht der fünfte.

**Wichtig für die Prüfung:** Die Liste verbotener Branchen in den AGB ist nur der erste Filter. Die belastbare Antwort kommt aus einem direkten Gespräch mit schriftlicher Bestätigung — nicht aus der Lektüre einer Webseite. Anzufragen ist bei mehreren Anbietern parallel, weil eine Absage sonst Monate kostet.

**Zwei Anbieter parallel ab Tag 1**, mit automatischem Ausweichen. Eine abgelehnte Zahlung ist im deutschen Markt ein häufiger Abbruchgrund, und ein einzelner Anbieter ist zugleich ein einzelner Ausfallpunkt für das gesamte Geschäft.

---

## 3. Die Kartennetzwerke sind die schärfere Aufsicht

Ein Befund der kritischen Prüfung, der im gesamten Ausgangskonzept fehlte:

> **Die Auflagen von Visa und Mastercard für Erwachsenenangebote sind strenger und werden schneller durchgesetzt als das Jugendschutzrecht.** Die KJM leitet ein Verfahren ein; ein Kartennetzwerk kündigt die Akzeptanz.

Beide Netzwerke führen Registrierungsprogramme für Händler mit Erwachsenenangeboten. Die Auflagen umfassen typischerweise Inhaltsmoderation, Alters- und Einwilligungsnachweise für **jede erkennbare Person** in hochgeladenem Material sowie eine Beschwerdebearbeitung innerhalb kurzer Frist. Kosten und genaue Anforderungen sind **nicht verifiziert**.

### Was daraus für den Bau folgt

**Der Einwilligungsnachweis gehört in den Hochladevorgang, nicht in die AGB.** Konkret: Material bleibt technisch gesperrt, bis für jede erkennbare Person ein Alters- und Einwilligungsnachweis vorliegt. Das nachträglich einzuführen ist ungleich teurer, als es von Anfang an so zu bauen.

Das passt zur Positionierung „100 % echte Menschen" — es ist dieselbe Anforderung aus zwei Richtungen.

### Der neutrale Kontoauszug hat eine Grenze

Beide Netzwerke verlangen, dass die Bezeichnung auf dem Kontoauszug den Händler **erkennbar** identifiziert. Eine bewusst irreführende Bezeichnung verstößt gegen die Regeln und ist zugleich der stärkste Auslöser für Rückbuchungen — der Karteninhaber erkennt die Buchung nicht und widerspricht.

**Der zulässige Weg:** eine abrechnende Gesellschaft mit unauffälligem, aber **echtem** Namen, konsistent verwendet in Kontoauszug, Impressum, Support und E-Mail. Und die Bezeichnung wird **vor dem Kauf im Klartext angezeigt**: „So erscheint es auf deinem Kontoauszug: …". Das erfüllt das Diskretionsversprechen, ohne die Regeln zu brechen, und senkt die Rückbuchungsquote.

---

## 4. Das Guthaben ist ein Margenhebel, nicht nur Diskretion

Ein Befund aus der Wirtschaftlichkeitsprüfung, der die Sicht auf das Guthabenkonto verändert:

Auf Erwachsenenangebote spezialisierte Anbieter liegen bei den Kartengebühren deutlich über dem allgemeinen Marktniveau. Bei kleinen Warenkörben — und die sind in diesem Segment die Regel — frisst das einen erheblichen Teil der Provision.

**Eine Aufladung per Überweisung kostet einen Bruchteil einer Kartenzahlung.** Wenn Nutzer einmal einen größeren Betrag per SEPA aufladen und daraus mehrere kleine Käufe bestreiten, sinken die Zahlungskosten je Bestellung erheblich.

Damit ist das Guthaben nicht Beiwerk zur Diskretion, sondern der **primäre Zahlweg**, und die Karte die teure Rückfallebene. Das gilt allerdings nur, falls die aufsichtsrechtliche Prüfung das Guthaben überhaupt erlaubt — siehe Abschnitt 1. Beide Fragen hängen zusammen und müssen gemeinsam entschieden werden.

---

## 5. Rückbuchungen

Branchenüblich trägt der Creator das volle Rückbuchungsrisiko — bei OnlyFans ausdrücklich einschließlich Gebühr und ohne Einspruchsrecht.

**MeinSlip übernimmt es.** Das ist ein starkes Verkaufsargument und zugleich eine reale Kostenposition, die in die Provision eingepreist werden muss.

Finanzierbar ist das nur, weil die Plattform den Zustellnachweis besitzt — und den besitzt sie nur, weil das Versandetikett über die Plattform läuft. **Das koppelt Safe-Ship an die Rückbuchungsgarantie:** Beides funktioniert nur zusammen, und das ist ein Argument dafür, Safe-Ship nicht als optionalen Komfort zu bauen, sondern als Regelweg.

Die Treuhand hilft zusätzlich, verschiebt das Risiko aber nur: Weniger Einzeltransaktionen bedeuten weniger Angriffsfläche, aber eine Rückbuchung nach erfolgter Auszahlung trifft die Plattform voll.

---

## 6. Diskrete Zahlwege

| Weg | Einschätzung |
| :--- | :--- |
| **SEPA-Überweisung** | Günstig, in Deutschland vertraut, kartenlos. Der naheliegende Hauptweg — vorbehaltlich Abschnitt 1. |
| **Sofortüberweisung** | Sofortige Gutschrift; Akzeptanz für Erwachsenenangebote **ungeklärt**. |
| **SEPA-Lastschrift** | Achtung: acht Wochen Widerspruchsfrist ohne Angabe von Gründen. Für diese Branche riskant. |
| **paysafecard, Barzahlung im Handel** | Deckt echte Nachfrage nach kartenloser Zahlung. Akzeptanz für Erwachsenenangebote **ungeklärt**, Gebühren typischerweise hoch. |
| **Kryptowährung** | Unter der EU-Verordnung über Märkte für Kryptowerte mit erheblichem Aufwand verbunden. Für den Start nicht empfohlen. |
| **PayPal, Klarna** | Schließen Erwachsenenangebote aus. Kein Weg. |

Die Recherche hat gezeigt, dass **keine der zwölf untersuchten globalen Abo-Plattformen** eine dieser deutschen Zahlarten anbietet — das ist eine der größten Lücken im Markt und für einen internationalen Anbieter strukturell schwer zu schließen.

---

## 7. Auszahlung an Creator

SEPA in Euro auf ein deutsches Konto. Die Marktanalyse zeigt, wie niedrig die Latte liegt: Die Wäsche-Marktplätze zahlen über Nischenanbieter oder in Kryptowährung aus, ein Anbieter zahlt nur montags von Hand, ein deutscher erst ab 25 € Guthaben.

**Kein Mindestauszahlungsbetrag, feste öffentlich kommunizierte Frist, keine versteckten Umrechnungsgebühren.** In Deutschland ist eine SEPA-Überweisung praktisch kostenlos — das ist ein sehr billiger, sehr starker Hebel.

**Eine Änderung der Bankverbindung sperrt Auszahlungen für eine Karenzzeit** und löst eine Benachrichtigung aus. Die Recherche fand nirgends im Segment einen solchen Schutz — dabei ist die Übernahme eines Kontos mit anschließender Auszahlungsumleitung der naheliegendste Angriff auf eine Plattform, die Geld hält.

---

## 8. Geldwäsche

Zwei Besonderheiten dieses Marktes:

**Getragene Wäsche hat keinen objektiven Preisanker.** Ein anderer Marktplatz kann eine Jeans für 2.000 € als auffällig markieren. „Getragene Wäsche, drei Tage, beim Sport" für 800 € ist per Definition nicht als überteuert erkennbar.

**Die Doppelrolle verschärft das.** Wer gleichzeitig kaufen und verkaufen kann, kann mit einem zweiten Konto einen geschlossenen Kreislauf bilden.

Gegenmaßnahmen, im Code angelegt: Erkennung verbundener Konten über Gerät, Netz und Zahlungsmittel; Betragsobergrenzen; Geschwindigkeitsgrenzen je Konto und je Paarung; Haltefristen für junge Konten; Freigabe von Einnahmen zum erneuten Ausgeben erst nach vollständiger Identitätsprüfung und mit Obergrenze.

---

## Was zu klären ist, bevor weiter an der Zahlung gebaut wird

1. Ist das Guthaben Einlagengeschäft nach § 1 Abs. 1 S. 2 Nr. 1 KWG? Ist es E-Geld oder ein Zahlungsdienst nach dem ZAG?
2. Gibt es einen Anbieter, der Erwachsenenangebote, Marktplatztreuhand und SEPA gleichzeitig kann — schriftlich bestätigt?
3. Was kosten die Registrierungsprogramme der Kartennetzwerke, und welche Auflagen bringen sie?
4. Welche Rückbuchungsquote ist realistisch, und trägt die Provision sie?

Alle vier mit Adressat und Priorität in [`11-offene-fragen.md`](11-offene-fragen.md).
