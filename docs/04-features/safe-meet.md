# Safe-Meet — treuhandgesicherte persönliche Übergabe

> **Stand:** 25.07.2026 · **Belegstatus:** Die Angriffsanalyse trägt aus sich heraus — sie ist Logik, keine
> Quellenfrage. Alle Vergleichszahlen zu anderen Plattformen sind **unbelegt**.
>
> **Status: nicht in der ersten Fassung bauen.** Begründung am Ende des Dokuments.

## Was es sein soll

Eine persönliche Warenübergabe, bei der das Geld hinterlegt ist und beide Seiten geschützt sind. Im gesamten untersuchten Markt bietet das **niemand** — auch außerhalb des Erotikbereichs nicht, weil alle Käuferschutzsysteme an einem Logistik-Trackingstatus hängen, den es bei einer Übergabe von Hand zu Hand nicht gibt.

## Der Entwurf, mit dem das Projekt startete

Aus dem Ausgangskonzept: Der Käufer bucht, der Betrag wird eingefroren. Beim Treffen scannt die Verkäuferin den QR-Code des Käufers — **das Geld wird unwiderruflich auf sie überschrieben**. Ein serverseitiger Countdown startet mit temporärer Standortverfolgung. Läuft er ohne Bestätigung ab, geht ein Notruf an eine Vertrauensperson.

Der Sicherheitsgedanke dahinter ist richtig und wichtig. Die Zahlungsmechanik hat einen strukturellen Kernfehler.

## Warum der Entwurf so nicht funktioniert

**Der Scan beweist das Falsche.** Er belegt, dass zwei Geräte zu einem Zeitpunkt nebeneinander waren. Er belegt nicht, dass eine Ware existiert, übergeben wurde oder der Beschreibung entspricht.

**Die Richtung ist invertiert.** Bei Lieferdiensten gibt der *Empfänger* den Code heraus, um den Erhalt zu quittieren. Hier gibt der Käufer sein Freigabe-Token heraus, **bevor** er etwas hat — und kann es praktisch nicht zurückhalten, weil die Verkäuferin es vor der Übergabe verlangt: „Erst scannen, dann bekommst du."

**Endgültigkeit im Übergabemoment ist genau das, was Betrüger suchen.** Kein Rückkanal, kein Fenster, keine Nachprüfung — funktional dasselbe, was Bargeldtransfers und Geschenkkarten für Betrüger attraktiv macht.

**Ein statischer QR-Code ist ein Bild, kein Anwesenheitsbeweis.** Er lässt sich als Bildschirmfoto verschicken und aus der Ferne scannen.

**Geldwäsche ist hier strukturell schlimmer als bei anderen Marktplätzen.** Getragene Wäsche hat keinen objektiven Preisanker. Ein Kleidermarktplatz kann eine Jeans für 2.000 € als auffällig markieren; „getragene Wäsche, drei Tage, beim Sport" für 800 € ist per Definition nicht als überteuert erkennbar.

**Reverse-Gesuch plus Übergabe ist ein Lockvogel-Werkzeug.** Ein Gesuch mit hohem, sofort eingefrorenem Budget ist ein Instrument, um eine bestimmte Person glaubwürdig an einen bestimmten Ort zu bestellen — das hinterlegte Geld erhöht genau die Glaubwürdigkeit, die ein Täter braucht.

**Der Totmannschalter wird durch seine Fehlalarmquote entwertet.** Leerer Akku, kein Netz, Einschlafen, längeres Treffen. Und die Orte ohne Netz — Keller, Hotel, Haus am Stadtrand — sind genau die relevanten. Nach wenigen Fehlalarmen nimmt eine Vertrauensperson den Anruf um drei Uhr nachts nicht mehr ernst.

## Der überarbeitete Ablauf

1. **Kein Tap bewegt jemals Geld.** Ein Tap ist ausschließlich ein Ereignis, das eine Frist startet. *Im Code bereits so umgesetzt — `uebergabeBestaetigen()` bucht nichts, und ein Test hält das fest.*
2. **NFC-Tap statt statischem QR-Code.** Erzwingt Nähe im Zentimeterbereich und ist per Bildschirmfoto nicht reproduzierbar. QR nur als Rückfall, dann rotierend und an Transaktion und Gerät gebunden.
3. **Der Beweiswert verlagert sich auf ein beidseitig quittiertes, zeitgestempeltes Foto** der übergebenen Ware in der App, gegen den im Angebot dokumentierten Zustand. Das ist der eigentliche Streitfallbeweis; der Tap ist nur der Zeitanker.
4. **Gestaffelte Freigabe mit Einspruchsfenster** statt Sofortfreigabe. Das Prinzip ist von Airbnb geborgt: Zeit-Trigger, Widerspruch statt Bestätigung.
5. **Betragsobergrenze, Geschwindigkeitsgrenzen je Konto und je Paarung, Graphanalyse** auf wiederkehrende Paare und geteilte Geräte, Haltefristen für junge Konten.
6. **Kopplung von Reverse-Gesuch und Übergabe gesperrt.** Reverse-Gesuche laufen über den Versand.
7. **Eskalationskette vor jedem Alarm:** stiller Hinweis → lauter Hinweis mit Vibration → automatisierter Anruf → erst dann die Vertrauensperson. Ein-Tap-Verlängerung, großzügige Puffer, Offline-Fall mit lokalem Gerätealarm zuerst.
8. **Nötigungsresistente Bestätigung.** Überall im Markt gilt: aktives Bestätigen bedeutet, dass alles in Ordnung ist. Das versagt genau dann, wenn jemand unter Druck steht — und der Übergabemoment ist der Moment, in dem Druck entstehen kann. Es braucht einen stillen Notfall-Code, der nach außen wie eine normale Bestätigung aussieht.

## Datenschutz

Standortdaten im Kontext einer Fetisch-Plattform sind Daten besonderer Kategorie nach Art. 9 DSGVO. Die Datenschutz-Folgenabschätzung ist zwingend, und ihre Ergebnisse bestimmen die Ausgestaltung — sie muss deshalb **vor** dem Bau beginnen.

Bauvorgaben, die jetzt schon feststehen: kein Bewegungsprofil, nur der letzte bekannte Punkt, kurze Aufbewahrung, ausdrückliche Einwilligung je Termin, Zweckbindung. Ein Leck dieser Daten wäre für die Betroffenen existenziell.

## Warum es trotzdem nicht in die erste Fassung gehört

Drei unabhängige Prüfungen kommen zum selben Ergebnis:

**Ohne lokale Nutzerdichte erzeugt es keine einzige zusätzliche Transaktion.** Eine Übergabefunktion braucht zwei Menschen in derselben Stadt, die einander gefunden haben. Am Anfang gibt es die nicht.

**Es eröffnet die gesamte Haftungs- und Presseangriffsfläche.** Ein einziger Vorfall bei einem über die Plattform vereinbarten Treffen definiert die Marke dauerhaft.

**Und der schärfste Einwand, der hier stehen bleiben soll:** Die Plattform verkauft Anonymität und Schutz — und stellt mit der persönlichen Übergabe ausgerechnet das Feature ins Schaufenster, das beides in einem einzigen Vorgang und unumkehrbar aufhebt. Eine Treuhand sichert eine *Zahlung* ab. Sie suggeriert aber die Absicherung eines *Menschen*. Zwischen diesen beiden Dingen liegt die gesamte Haftung.

**Das Versprechen darf in Marke und Kommunikation ab Tag 1 stehen** — es ist der beste Pressehaken, den das Projekt hat. Der Code nicht.

Die rechtliche Voraussetzung steht in [`../11-offene-fragen.md`](../11-offene-fragen.md) Punkt 5.
