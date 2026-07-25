# Verifizierung und Altersstufen

> **Stand:** 25.07.2026 · **Belegstatus:** belegt, 75 Fundstellen. Angaben stammen aus Suchergebnis-Zusammenfassungen,
> nicht aus gelesenem Primärtext. Rechtliche Herleitung in [`../05-recht-compliance.md`](../05-recht-compliance.md).

## Drei Stufen

| Stufe | Wer | Sichtbar | Prüfung |
| :--- | :--- | :--- | :--- |
| **0 — Gast** | jede:r | Kategorienbaum, Produktseiten ohne Nacktheit, Preise, Ratgeber | keine |
| **1 — Käufer** | verifiziert | vollständig | Identifizierung **und** Zugang je Nutzungsvorgang |
| **2 — Verkäufer:in** | freigeschaltet | zusätzlich verkaufen und auszahlen | Ausweis plus Lebendnachweis, **vor der ersten Auszahlung** |

**Rolle ist kein Kontotyp, sondern eine freigeschaltete Fähigkeit.** Dieselbe Person, dasselbe Konto — Stufe 2 kommt zu Stufe 1 hinzu. *Im Code als `benutzer_faehigkeiten` umgesetzt.*

## Die zwei Stufen der Altersprüfung

Das AVS-Raster der KJM verlangt beides, nicht eines von beiden:

1. **Identifizierung** — einmalig, über persönlichen Kontakt oder ein gleichwertiges Verfahren
2. **Authentifizierung bei jedem Nutzungsvorgang**, der nicht unmittelbar auf die Identifizierung folgt

> **Angemeldet zu sein genügt ausdrücklich nicht.** Das Zugangs-Gate ist deshalb ein eigenes Feld — `sitzungen.adult_gate_bestanden_am` — und kein Nebeneffekt der Anmeldung.

Die im Ausgangskonzept vorgesehene Variante („läuft nahtlos bei der ersten Wallet-Aufladung") ist **nicht zulässig**: Der BGH hat Datenabfrage plus Kontoüberweisung 2007 als unzureichend verworfen, und die zweite Stufe fehlte ganz.

## Verfahren

Mehrere zur Auswahl, weil die Abbruchquote bei der Verifizierung der teuerste Punkt im Trichter ist:

| Verfahren | Anmerkung |
| :--- | :--- |
| **Bankbasiert mit Auskunftsmerkmal** | Funktioniert, weil das Merkmal eine frühere persönliche Ausweisprüfung bei der Kontoeröffnung bestätigt |
| **Biometrische Alterschätzung** | Für 18+ muss die Person als **mindestens 23** erkannt werden. Daten bleiben auf dem Gerät. Nur **Teillösung** auf Identifizierungsebene — erzeugt spürbare Fehlablehnungen bei 20- bis 24-Jährigen, deshalb Rückfallweg zwingend |
| **eID** | Datenschutzfreundlich, überträgt nur „über 18" |
| **Video-Ident** | Aufwendig, aber etabliert |

> **Falle bei der Anbieterauswahl:** Ein Verfahren kann positiv bewertet sein — aber nur nach § 5 Abs. 3 Nr. 1 JMStV für entwicklungsbeeinträchtigende Inhalte, **nicht** als geschlossene Benutzergruppe nach § 4 Abs. 2. Wer das vor Pornografie schaltet, baut ein rechtswidriges Gate. Bei jedem Produkt einzeln prüfen, für welche Norm und welche Stufe die Bewertung gilt.

## Was die Plattform nicht speichert

**Niemals Ausweisdokumente.** Von der Prüfung kommt nur das Ergebnis zurück: bestanden ja/nein, volljährig ja/nein, ein Referenzschlüssel beim Anbieter. Wer die Daten nicht hat, kann sie nicht verlieren.

*Im Code so angelegt:* Die Tabelle `pruefungen` hat kein Feld für Dokumente.

## Verifizierung als Dauerzustand, nicht als Ereignis

Ein Befund aus der Marktanalyse: Überall im Segment ist Verifizierung ein **einmaliges Ereignis**. Geprüft wird die Person zum Zeitpunkt der Anmeldung; danach hält nur noch ein Passwort das Konto zusammen. Ein übernommenes Konto bleibt „verifiziert".

Was daraus folgt:

- **Passkey-Bindung** statt reinem Passwort
- **Wiederkehrender Lebendnachweis** in Abständen
- **Auszahlungssperre bei geänderter Bankverbindung** mit Karenzzeit und Benachrichtigung. *Die Recherche fand nirgends im Segment einen solchen Schutz — dabei ist die Kontoübernahme mit Auszahlungsumleitung der naheliegendste Angriff auf eine Plattform, die Geld hält.*

## Auch Käufer werden verifiziert

Ungewöhnlich im Markt und aus zwei Gründen richtig:

**Es löst den meistgenannten Zeitschmerz der Verkäuferseite.** Die häufigste Beschwerde ist nicht die Gebühr, sondern: „not one ACTUAL buyer". Wer verifiziert und zahlungsfähig sein muss, bevor er schreiben kann, ist kein Zeitverschwender.

**Es ist der wirksamste Schutz gegen Belästigung.** Allein die Nicht-Anonymität der Käuferseite wirkt stark abschreckend — Blockieren allein ist wirkungslos, weil Zweitkonten trivial sind.

## Was das Abzeichen bedeutet

Sichtbar, gestuft und ehrlich beschriftet. Beim Marktführer im Wäsche-Segment berichten Nutzer:innen von **verifizierten Betrugskonten** — ein Abzeichen, das nichts garantiert, ist schlimmer als keines.

Deshalb: **Kein Abzeichen ohne bestandene Prüfung, und keine Auszahlung ohne Abzeichen.** Die Kopplung an die Auszahlung ist das, was das Verfahren unaushebelbar macht — und genau sie fehlt im gesamten untersuchten Markt.
