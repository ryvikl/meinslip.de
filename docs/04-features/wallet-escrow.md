# Guthaben und Treuhand

> **Stand:** 25.07.2026 · **Belegstatus:** Aufsichtsrechtliche Einordnung **unbelegt**. Vollständige Analyse in
> [`../07-zahlungsverkehr.md`](../07-zahlungsverkehr.md).
>
> **Status: Guthabenfunktion im Code gesperrt.** Siehe unten.

## Warum Treuhand die Daseinsberechtigung ist

Beide Marktseiten misstrauen einander, und das bremst den gesamten Markt. Die Verkäuferin fürchtet Rückbuchung und Nichtzahlung, der Käufer fürchtet Vorkasse-Betrug. Die heutige Behelfslösung — Vorkasse per Freundschaftsüberweisung — löst das Problem der einen Seite, indem sie die andere schutzlos stellt.

**Treuhand löst beide gleichzeitig.** Das ist der Grund, warum es die Plattform geben sollte.

## Die Mechanik

```
Kauf ──> Betrag gebunden ──> Annahme ──> Versand/Übergabe
                                              │
                                         Zustellung
                                              │
                                    Einspruchsfenster (72 h)
                                         ╱          ╲
                                  Frist ab        Einspruch
                                     │                │
                                Freigabe          Streitfall
                                                   ╱     ╲
                                             Freigabe   Erstattung
```

**Drei Regeln, die im Code durchgesetzt sind:**

1. **Kein Zustandswechsel bewegt Geld direkt.** Geld bewegt sich ausschließlich über Buchungen im Hauptbuch, gebunden an ein protokolliertes Ereignis.
2. **Freigabe erfolgt nie sofort**, sondern immer nach Ablauf des Einspruchsfensters. Der Zustandsautomat kennt keinen Übergang von „zugestellt" oder „übergeben" direkt nach „freigegeben" — ein Test hält das fest.
3. **Jeder Wechsel wird protokolliert.** Kein stiller Zustandswechsel.

Bei Freigabe wird der Betrag in einem Vorgang aufgeteilt: Einkaufspreis an die Verkäuferin, Provision und Umsatzsteuer an die Plattformkonten. Die Summe geht exakt auf — sonst weist das Hauptbuch die Buchung ab.

## Zwei Töpfe je Konto

| Topf | Bedeutung |
| :--- | :--- |
| **Guthaben** | Aufgeladenes Geld zum Ausgeben |
| **Einnahmen** | Erlöse aus Verkäufen, auszahlbar |

Getrennt geführt, weil sie rechtlich und steuerlich Verschiedenes sind. Wer beides kann — und das kann jeder, weil ein Konto alle Rollen trägt — sieht beides getrennt.

**Einnahmen direkt wieder auszugeben** ist bequem und spart Auszahlungsgebühren, ist aber ein Geldwäschepfad über zwei abgestimmte Konten. Deshalb: erst nach vollständiger Identitätsprüfung, mit Obergrenze. *Im Code so umgesetzt.*

## Doppelte Buchführung

Kein gespeicherter Kontostand. Ein Saldo ist immer die Summe der Buchungen, jede Bewegung erzeugt mindestens zwei Zeilen, deren Summe null ergibt.

Das klingt nach Buchhalter-Ästhetik und ist eine Sicherheitsmaßnahme: **Jede Abweichung ist sofort messbar.** Die Betriebsprüfung unter `/zustand` meldet sie, ein stündlicher Cronjob prüft sie. Eine Abweichung bedeutet einen Fehler in der Geldlogik und muss laut auffallen.

Buchungen werden nie geändert oder gelöscht. Eine Korrektur ist eine Gegenbuchung. Das verlangt das Kommissionsmodell ohnehin.

## Die Sperre

> **Ein aufladbares, jederzeit rückforderbares Guthaben kann ein erlaubnispflichtiges Einlagengeschäft nach § 1 Abs. 1 S. 2 Nr. 1 KWG sein.**

Das Ausgangskonzept erkennt die Gefahr, als Zahlungsdienst eingestuft zu werden, und zieht die richtige Schlussfolgerung — greift aber zu kurz. **Das Kommissionsmodell beantwortet diese Frage nicht.** Es klärt, wer Verkäufer ist, nicht, was die Annahme rückzahlbarer Gelder des Publikums ist.

Deshalb wirft `Guthaben::aufladen()` eine `AufsichtsrechtGesperrt`, solange `ZAHLUNG_GUTHABEN_AKTIV` nicht gesetzt ist. Die Fehlermeldung nennt den Grund vollständig — wer sie sieht, soll nicht anfangen, sie wegzuklicken.

**Das Hauptbuch bleibt davon unberührt.** Es ist unabhängig davon die richtige Struktur.

**Ausweg bei negativem Ergebnis:** Direktzahlung je Bestellung. Der Betrag geht unmittelbar in die Treuhandbindung, ohne dass je ein rückforderbarer Saldo entsteht. Kostet Bequemlichkeit und einen Teil der Diskretionswirkung — aber keine Lizenz.

## Das Guthaben ist auch ein Margenhebel

Falls es zulässig ist: Auf Erwachsenenangebote spezialisierte Zahlungsdienstleister liegen bei Kartengebühren deutlich über dem allgemeinen Niveau, und die Warenkörbe sind in diesem Segment klein. Eine Aufladung per Überweisung kostet einen Bruchteil einer Kartenzahlung.

Damit wäre das Guthaben nicht Beiwerk zur Diskretion, sondern der **primäre Zahlweg** — und die Karte die teure Rückfallebene.

## Ausgabenkontrolle

Ein Schmerzpunkt der Käuferseite, den im Markt niemand adressiert, weil er dem Umsatz entgegensteht: Das Abo ist der Köder, danach folgen Einzelkäufe und Trinkgeld-Druck.

**Selbst gesetztes Monatslimit**, jederzeit mit Sofortwirkung senkbar, Erhöhung erst nach 24 Stunden Bedenkzeit. Paradoxerweise umsatzstabilisierend, weil es Reue-Rückbuchungen und Abwanderung verhindert — und Rückbuchungen sind auf einer Adult-Plattform das teuerste Einzelrisiko.

## Auszahlung

SEPA in Euro, **kein Mindestbetrag**, feste öffentlich kommunizierte Frist. Die Latte im Markt liegt niedrig: Ein Anbieter zahlt nur montags von Hand aus, ein deutscher erst ab 25 € Guthaben, die internationalen über Nischenanbieter oder in Kryptowährung.

Eine Änderung der Bankverbindung sperrt Auszahlungen für eine Karenzzeit und löst eine Benachrichtigung aus.
