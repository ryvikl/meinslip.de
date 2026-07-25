# Recht und Compliance

> **Stand:** 25.07.2026
>
> **Belegstatus gemischt — je Abschnitt ausgewiesen.** Jugendschutz und Altersverifikation beruhen auf 75 abrufbaren
> Fundstellen; DSA-Händlertransparenz, Datenschutz-Folgenabschätzung und die Abgrenzung von Ware und Treffen sind
> **unbelegte Strukturanalyse**. Alle Angaben stammen aus Suchergebnis-Zusammenfassungen, nicht aus gelesenem
> Primärtext.
>
> **Dieses Dokument ist keine Rechtsberatung.** Es bereitet die anwaltliche Prüfung vor. Die Fragen dafür stehen
> gesammelt in [`11-offene-fragen.md`](11-offene-fragen.md).

---

## 1. Jugendschutz und Altersverifikation — der harte Blocker

*Belegstatus: belegt, Quellen in [`quellen/05-recht.md`](quellen/05-recht.md)*

### Was gilt

Für „einfache" Pornografie verlangt **§ 4 Abs. 2 S. 1 Nr. 1 i. V. m. S. 2 JMStV** zwingend eine **geschlossene Benutzergruppe**. Die KJM konkretisiert das im AVS-Raster (gültig seit 12.05.2022) **zweistufig**:

1. **Identifizierung** — mindestens einmalige Volljährigkeitsprüfung, die grundsätzlich über persönlichen Kontakt oder ein gleichwertiges Verfahren erfolgen muss
2. **Authentifizierung** — bei **jedem einzelnen Nutzungsvorgang**, der nicht unmittelbar auf die Identifizierung folgt

Ein Testzugang ist ausgeschlossen. Der novellierte JMStV (6. Medienänderungsstaatsvertrag) ist zum **01.12.2025** in Kraft getreten.

### Was das für das Ausgangskonzept bedeutet

> **Die im Produktkonzept vorgesehene Variante — „Altersverifikation läuft nahtlos im Hintergrund bei der ersten Wallet-Aufladung über Bank-/SCHUFA-Daten" — ist nicht zulässig.**

Zwei unabhängige Gründe:

**Der BGH hat genau das bereits verworfen.** Urteil vom 18.10.2007, **I ZR 102/05 („ueber18.de")**: Die Abfrage von Ausweisnummer, Postleitzahl und Name/Adresse zusammen mit einer Kontoüberweisung ist **keine ausreichende Barriere** im Sinne des § 4 Abs. 2 JMStV. Eine Zahlung ist kein Altersnachweis. Bemerkenswert für die Risikoeinschätzung: Das war ein **Wettbewerbsverfahren**. Der wahrscheinlichste Angriffsweg ist also nicht die Aufsicht, sondern die Abmahnung durch einen Konkurrenten.

**Die zweite Stufe fehlt komplett.** Selbst ein funktionierender Bankdatenabgleich deckt nur die Identifizierung ab. Ein Zugangs-Gate vor jedem Nutzungsvorgang kommt im Ausgangskonzept nicht vor.

### Was stattdessen gebaut wird

**Wallet-Aufladung und Altersverifikation sind getrennte Abläufe.** Kein Verfahren wird selbst entwickelt — es wird eine von der KJM positiv bewertete Bauform lizenziert und **exakt so implementiert, wie sie bewertet wurde**.

**Stufe 1 — Identifizierung.** Mehrere Verfahren zur Auswahl, um die Abbruchquote zu senken:

| Verfahren | Einordnung |
| :--- | :--- |
| **finAPI GiroIdent Jugendschutz** | KJM-positiv seit Mai 2022. Funktioniert, weil nach Online-Banking-Login und Namensabgleich das SCHUFA-Q-Bit abgefragt wird — und dieses bestätigt eine **frühere** persönliche Ausweisprüfung bei der Kontoeröffnung. Die Bankdaten sind Träger einer früheren Identifizierung, nicht selbst der Altersnachweis. |
| **Biometrische Alterschätzung** | Drei Systeme von der KJM positiv bewertet. Für 18+-Inhalte muss die Person als **mindestens 23 Jahre** erkannt werden (Sicherheitspuffer). Biometrische Daten verbleiben auf dem Gerät. Die KJM stuft dies ausdrücklich als **Teillösung auf Identifizierungsebene** ein. |
| **eID / Online-Ausweisfunktion** | Datenschutzfreundlich, weil nur das Merkmal „über 18" übertragen wird. |
| **Video-Ident** | Aufwendig, aber etabliert. |

> **Die Falle bei der Anbieterauswahl:** „Yoti Age Scan" ist von der KJM **nur** als technisches Mittel nach § 5 Abs. 3 Nr. 1 JMStV positiv bewertet — also für entwicklungsbeeinträchtigende Inhalte, **nicht** als geschlossene Benutzergruppe nach § 4 Abs. 2. Wer das vor Pornografie schaltet, baut ein rechtswidriges Gate. Bei **jedem** Anbieter muss geprüft werden, für welche Norm und für welche Stufe die Bewertung gilt.

**Konsequenz für die Produktplanung:** Die biometrische Schätzung mit 23-Jahre-Schwelle erzeugt eine spürbare Fehlablehnungsquote bei 20- bis 24-Jährigen. Es braucht zwingend einen zweiten Weg als Auffanglösung.

**Stufe 2 — Authentifizierung pro Nutzungsvorgang.** Ein eigenes Zugangs-Gate: Adult-PIN, Passkey oder Gerätebiometrie. Technisch umgesetzt als kurzlebiges Zugriffsrecht, das bei jeder neuen Sitzung neu erworben werden muss.

### Stufenmodell

| Stufe | Wer | Was sichtbar ist | Prüfung |
| :--- | :--- | :--- | :--- |
| **0 — Gast** | jede:r | Startseite, Kategorienbaum, Profilrahmen. Explizites unscharf und gesperrt | Kein Zugang zu pornografischen Inhalten |
| **1 — Käufer** | verifiziert | vollständiger Zugang | Identifizierung **plus** Gate pro Sitzung |
| **2 — Verkäufer:in** | zusätzlich freigeschaltet | Verkaufen, Auszahlung | Ausweis plus Lebendnachweis, **vor der ersten Auszahlung** |

**Zur Unschärfe für Gäste:** Die Vorschau muss serverseitig erzeugt werden. Ein CSS-Weichzeichner über dem Originalbild ist wirkungslos — das Original liegt dann im Browser. Das ist ein Umsetzungsdetail mit rechtlicher Wirkung.

### Für die spätere Expansion

**EuGH, Urteil vom 16.06.2026, verb. Rs. C-188/24 und C-190/24:** Ein Mitgliedstaat darf von Anbietern pornografischer Dienste aus anderen Mitgliedstaaten eine wirksame Altersverifikation verlangen. Das Herkunftslandprinzip steht dem unter den Voraussetzungen des Art. 3 Abs. 4 E-Commerce-Richtlinie nicht entgegen.

> Die naheliegende Sparstrategie „wir sind eine deutsche GmbH, also reicht ein deutsches Verfahren EU-weit" trägt nach diesem Urteil nicht. Umgekehrt schützt der deutsche Sitz auch nicht gegen deutsches Recht — **Deutschland ist für MeinSlip der strengste und unausweichliche Maßstab.** Das ist beim DE-Start ein Vorteil: Wer den härtesten Maßstab zuerst erfüllt, hat es später leichter.

### Warum das existenziell ist

Auslöser für Netzsperren und Aufsichtsverfahren war in allen bekannten Fällen die **fehlende Altersverifikation**, nicht der Inhalt. Eine saubere Prüfung von Anfang an macht ein KJM-Verfahren weitgehend gegenstandslos — und ist zugleich Voraussetzung dafür, überhaupt einen Zahlungsdienstleister zu bekommen.

---

## 2. Pseudonymität gegen Händlertransparenz

*Belegstatus: **unbelegte Strukturanalyse**, keine Quellen — vor Umsetzung anwaltlich prüfen lassen*

Das Konzept verspricht Verkäufer:innen vollständige Pseudonymität. Dem stehen nach der Analyse **vier unabhängige Normen** entgegen, die Klarname und ladungsfähige Anschrift **öffentlich** verlangen:

- **DSA Art. 30 Abs. 7** — Rückverfolgbarkeit von Unternehmern; der Marktplatz muss Name, Anschrift, Telefon, E-Mail und Registernummer auf der Oberfläche verfügbar machen
- **Art. 246a EGBGB** — vorvertragliche Informationspflichten im Fernabsatz
- **§ 5 DDG** — Anbieterkennzeichnung
- **GPSR Art. 19** — Produktsicherheit

Auf die Ausnahme für Privatverkäufe kann man sich nicht stützen: Durch Planmäßigkeit, Wiederholung, gleichartige Ware und den Konfigurator sind MeinSlip-Verkäufer:innen praktisch immer Unternehmer.

**Die Auflösung ist das Kommissionsmodell** — die GmbH wird selbst Verkäuferin, sodass neben ihr kein zweiter Unternehmer existiert. Ausführlich in [`06-steuern-kommissionsmodell.md`](06-steuern-kommissionsmodell.md).

**Was daraus für die Kommunikation folgt:** Pseudonym nach außen, Klardaten intern. Nach § 23 PStTG muss die Plattform Anbieter sperren, die die erforderlichen Daten nicht vorlegen — nach zweimaliger Erinnerung. Das gehört in Klartext ins Onboarding: *„Dein Klarname ist für Käufer nie sichtbar. Gesetzlich müssen wir ihn kennen."* Wer das verschweigt, erzeugt den Vertrauensbruch beim ersten Datenabruf.

---

## 3. Datenschutz

*Belegstatus: **unbelegte Strukturanalyse***

### Die Folgenabschätzung ist Pflicht, nicht Kür

Eine Datenschutz-Folgenabschätzung nach **Art. 35 DSGVO** ist hier gleich mehrfach ausgelöst:

- **Art. 35 Abs. 3 lit. b** — umfangreiche Verarbeitung besonderer Kategorien. Daten über das **Sexualleben** sind ausdrücklich Art.-9-Daten, und eine Fetisch-Plattform verarbeitet sie zwangsläufig: Kategorien, Vorlieben, Kaufhistorie, Chatinhalte
- Standortdaten im Kontext von Safe-Meet
- Biometrie bei der Alterschätzung und beim Lebendnachweis

> **Zeitliche Konsequenz:** Die Folgenabschätzung bestimmt GPS-Granularität, Speicherfristen und Aufbewahrung. Sie muss deshalb **vor** der Architekturentscheidung beginnen, nicht danach. Sie ist ein Dokumentations-, kein Genehmigungsverfahren — mit einigen Wochen Vorlauf und externer Unterstützung machbar.

### Bauentscheidungen, die daraus folgen

**Die Plattform soll Ausweisdaten nie sehen.** Die Identitätsprüfung läuft so, dass nur das Ergebnis zurückkommt — geprüft ja/nein, volljährig ja/nein, ein Referenzschlüssel — nicht das Ausweisbild. Wer die Daten nicht hat, kann sie nicht verlieren.

**Datensparsamkeit bei Standortdaten.** Kein Bewegungsprofil, nur der letzte bekannte Punkt, kurze Aufbewahrung, Zweckbindung, ausdrückliche Einwilligung. Ein Leck dieser Daten wäre für die Betroffenen existenziell.

**Hosting in der EU**, Auftragsverarbeitungsverträge, Löschkonzept, Auskunfts- und Exportfunktion im Produkt statt per E-Mail.

**Einkaufsdaten sind auch intern besonders zu schützen** — wer selbst verkauft und woanders einkauft, darf dabei niemals sichtbar werden. Siehe [`08-architektur.md`](08-architektur.md).

---

## 4. Verbraucherrecht

*Belegstatus: Umsatzsteuerteil belegt, verbraucherrechtlicher Teil Strukturanalyse*

### Widerrufsrecht — der Punkt, an dem der Konfigurator zur Pflicht wird

Der naheliegende Ausschluss für **versiegelte Hygieneartikel (§ 312g Abs. 2 Nr. 3 BGB)** trägt bei getragener Wäsche **nicht verlässlich**. Der EuGH legt die Ausnahme in `slewo` (C-681/17) eng aus.

**Deshalb wird der Ausschluss auf § 312g Abs. 2 Nr. 1 BGB gestützt** — Waren, die nach Kundenspezifikation angefertigt oder eindeutig auf persönliche Bedürfnisse zugeschnitten sind.

> **Daraus folgt eine harte Produktanforderung:** Jede Warenbestellung braucht **mindestens eine echte, vom Käufer gesetzte Spezifikation**, protokolliert mit Zeitstempel. Der Trage-Konfigurator ist damit keine Umsatzidee, sondern das Fundament, auf dem das Warengeschäft ohne Rücknahmerisiko funktioniert. Siehe [`04-features/konfigurator-proof-of-wear.md`](04-features/konfigurator-proof-of-wear.md).

**Digitale Inhalte:** Ausschluss nach § 356 Abs. 5 BGB — setzt eine ausdrückliche Zustimmung und die Kenntnisnahme des Verlusts des Widerrufsrechts voraus. Beides muss protokolliert werden.

### Weitere Pflichten

| Pflicht | Umsetzung |
| :--- | :--- |
| **Kündigungsbutton, § 312k BGB** | Prominent, ohne Login-Zwang erreichbar |
| **Automatische Verlängerung** | Als Voreinstellung **aus**. Bewusster Umsatzverzicht, stärkster Vertrauensbeweis gegenüber der Konkurrenz |
| **Preisangabenverordnung** | Endpreise inklusive Umsatzsteuer, Versandkosten vor dem Bestellabschluss |
| **Omnibus-Richtlinie** | Transparenz über Ranking-Kriterien und über die Echtheitsprüfung von Bewertungen — passt zur Entscheidung, Ranking über Verlässlichkeit statt über bezahlte Platzierung zu steuern |
| **Barrierefreiheitsstärkungsgesetz** | Gilt seit 28.06.2025 für den elektronischen Geschäftsverkehr, Standard WCAG 2.1 AA. Die Kleinstunternehmen-Ausnahme ist zu prüfen. Konsequenzen für die Farbwelt in [`09-design-system.md`](09-design-system.md) |

---

## 5. Die Trennung von Ware und Treffen

*Belegstatus: **unbelegte Strukturanalyse** — die einschlägige Rechtsprechung konnte nicht recherchiert werden. Höchste Priorität für die anwaltliche Prüfung.*

Die Projektentscheidung lautet: **Warenübergabe mit Provision, Treffen ohne Warenbezug provisionsfrei als reine Kontaktanbahnung.** Ein Rechercheagent hat ausdrücklich empfohlen, Treffen **gar nicht** anzubieten — wegen der Erlaubnispflicht nach § 12 ProstSchG, der Risiken aus §§ 180a, 181a StGB und der Gefahr, den Zahlungsdienstleister zu verlieren. Diese abweichende Empfehlung bleibt hier stehen, damit sie bei der anwaltlichen Prüfung auf dem Tisch liegt.

**Die Trennung trägt nur, wenn das Produkt sie erzwingt.** Was in den AGB steht, ist wirkungslos, wenn die Oberfläche etwas anderes ermöglicht. Als harte Anforderungen:

1. **Provision ausschließlich auf Warengeschäfte.** Kein Treffen ohne Warenbezug erzeugt jemals eine Zahlung über die Plattform. Technisch: Es existiert kein Codepfad, der das könnte.
2. **Betragsdeckel für Warenübergaben**, damit „Slipübergabe für 300 €" nicht zur Chiffre wird.
3. **Keine Zeitangebote, keine Stundensätze, keine Dauerangaben.** Der Konfigurator kennt keine Zeiteinheit als Preisbestandteil.
4. **Moderation auf entsprechende Formulierungen**, mit gepflegter Wortliste und menschlicher Nachprüfung.
5. **Bei späterer Expansion Feature-Flag aus** in Ländern mit Sexkaufverbot.
6. **Kein US-Bezug ohne Prüfung** — Hosting, Zahlungsdienstleister und CDN. FOSTA/SESTA wären sonst zu betrachten.

---

## 6. Digital Services Act

*Belegstatus: **unbelegte Strukturanalyse***

Als Online-Plattform mit Marktplatzfunktion greifen unter anderem: Melde- und Abhilfeverfahren (Art. 16), internes Beschwerdemanagement (Art. 20), Transparenzberichte (Art. 24), Rückverfolgbarkeit von Unternehmern (Art. 30 — durch das Kommissionsmodell gegenstandslos), Schutz Minderjähriger (Art. 28).

Praktisch heißt das: ein funktionierender Meldeweg mit zugesagter Reaktionszeit, ein Widerspruchsverfahren gegen eigene Entscheidungen, und die Bereitschaft, Moderationsentscheidungen zu begründen. Die Pflichten für sehr große Plattformen greifen erst weit oberhalb der zu erwartenden Größe.

---

## 7. Was vor der ersten Zeile Produktivbetrieb geklärt sein muss

Nicht vor der ersten Zeile *Code* — Entwicklung kann und soll parallel laufen. Aber **vor dem Livegang**:

1. Vertrag mit einem KJM-positiv bewerteten Anbieter für die Altersverifikation
2. Anwaltliche Bestätigung des Kommissionsmodells und der Trennung von Ware und Treffen
3. Datenschutz-Folgenabschätzung abgeschlossen
4. Zahlungsdienstleister mit schriftlicher Bestätigung der Branchenakzeptanz
5. Steuerliche Registrierung, OSS-Verfahren, Meldeprozess nach PStTG
6. Impressum, AGB, Datenschutzerklärung, Widerrufsbelehrung — anwaltlich erstellt
7. Jugendschutzbeauftragte:r benannt und erreichbar

Details und Zuständigkeiten in [`11-offene-fragen.md`](11-offene-fragen.md).
