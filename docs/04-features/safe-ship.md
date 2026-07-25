# Safe-Ship — anonymer Versand in beide Richtungen

> **Stand:** 25.07.2026 · **Belegstatus: unbelegt.** Carrier-Bedingungen, Schnittstellen und Kosten konnten nicht
> recherchiert werden. Die logische Analyse trägt aus sich heraus, die Umsetzbarkeit ist zu prüfen —
> [`../11-offene-fragen.md`](../11-offene-fragen.md) Punkt 7.

## Das Problem

Auf jedem Paket steht ein Absender. Das ist der **meistgenannte und existenziellste Schmerzpunkt der gesamten Verkäuferseite**: Klarname und Wohnanschrift gehen an fremde Käufer. Dazu kommen Rückläufer, Nachbarn, die annehmen, und eine Sendungsverfolgung, die den Herkunftsort zeigt.

Die heutigen Behelfe — Abgabe in einer fremden Filiale, Packstation, erfundene Absender — sind unzuverlässig und teilweise vertragswidrig.

**Kein einziger untersuchter Anbieter löst das.** Alle werben mit „diskretem Versand" und „neutraler Verpackung" — das schützt aber ausschließlich den Käufer. Neutrale Verpackung ist im Erotik-Versandhandel seit Jahren Standard und damit kein Unterscheidungsmerkmal mehr; echter Adressschutz wäre eines.

## Zwei Fehler im Ausgangskonzept

### Ein Postfach kann kein Absender sein

Das Konzept sieht vor: „Absender ist ein Postfach/Adresse der Plattform-GmbH."

Ein Postfach ist eine reine Empfangseinrichtung für Briefpost und nimmt keine Pakete an. Damit fehlt dem Absenderfeld seine eigentliche Funktion: das Ziel für Rückläufer. Es braucht eine **echte Straßenanschrift** der GmbH oder eines Dienstleisters — und einen physischen Prozess für zurücklaufende getragene Wäsche.

### Das Etikett anonymisiert nur eine Richtung

Der gravierendere Fehler ist rein logisch und unabhängig von jedem Carrier-Detail:

> **Wer das Etikett druckt und aufklebt, sieht die Adresse des Empfängers.**

In der Etikett-Variante bleibt die Verkäuferin anonym — der Käufer nicht. Damit ist genau die Hälfte des Versprechens nicht eingelöst, und zwar die Hälfte, die niemand erwähnt hat.

Der Käufer will seine Privatadresse ebenso wenig an eine fremde Verkäuferin geben. Dieser Schmerzpunkt steht unabhängig davon in der Käuferliste.

## Die Lösung

**Einlieferung per Code, Etikett wird erst vor Ort gedruckt.** Die Verkäuferin erhält einen Einlieferungscode für den Paketshop oder die Packstation. Das Etikett entsteht dort — sie sieht die Käuferadresse nie.

*Im Code bereits so angelegt:* Die Tabelle `versendungen` führt `einlieferungscode`, und `Bestellungen::versenden()` nimmt einen Code entgegen, kein Etikett. Die Etikett-Variante ist bewusst **keine** Rückfalllösung, weil sie das Versprechen halbiert.

**Falls sich das Verfahren nicht umsetzen lässt**, bleibt als Alternative ein Umschlagpunkt: Die Verkäuferin schickt an ein Lager der Plattform, dort wird neu verpackt und weiterversendet. Das anonymisiert vollständig, kostet aber Laufzeit, Lagerfläche und wirft Fragen zu Hygiene- und Gewerberecht bei getragener Wäsche auf.

## Warum das nur mit dem Kommissionsmodell geht

Ein Absender, der nicht der Versender ist, wäre eine Behauptung. Im Kommissionsmodell **ist** die GmbH tatsächlich Verkäuferin und Versenderin — die Anschrift auf dem Paket ist dann keine Verschleierung, sondern richtig.

Das ist einer von vier Gründen für das Kommissionsmodell, siehe [`../06-steuern-kommissionsmodell.md`](../06-steuern-kommissionsmodell.md).

## Warum es an der Rückbuchungsgarantie hängt

MeinSlip übernimmt das Rückbuchungsrisiko — branchenüblich trägt es der Creator. Finanzierbar ist das nur, weil die Plattform den Zustellnachweis besitzt. Und den besitzt sie nur, wenn das Etikett über die Plattform läuft.

**Beides funktioniert nur zusammen.** Das ist das Argument dafür, Safe-Ship als Regelweg zu bauen und nicht als optionalen Komfort.

## Zu klären

- Kann die GmbH als Absender auftreten, obwohl das Paket von anderer Stelle eingeliefert wird?
- Gibt es die Einlieferung per Code mit Etikettdruck vor Ort? **Die entscheidende Frage.**
- Wohin gehen Rückläufer, und wer nimmt sie an?
- Kosten je Sendung, und wer trägt sie?
- Sind Erwachsenenangebote in den Geschäftsbedingungen der Dienstleister zulässig?
- Wen trifft die Registrierungspflicht nach dem Verpackungsgesetz?
