# Design-System

> **Stand:** 25.07.2026 · Grundlage ist das Designkonzept in
> [`../konzepte-eingang/chatgpt-designkonzept.md`](../konzepte-eingang/chatgpt-designkonzept.md). Die Designrichtung
> wurde übernommen, drei Punkte wurden korrigiert. Dieses Dokument ist maßgeblich, das Eingangsdokument nicht.

## Die Designidee

Die Plattform sieht aus wie Revolut und Vinted, nicht wie eine Erotikseite. Das ist keine Geschmacksfrage: Es macht Diskretion vom Marketingversprechen zur sichtbaren Produkteigenschaft. Die App darf auf dem Homescreen liegen, in der Bahn geöffnet und im Büro benutzt werden, ohne dass ein Blick über die Schulter etwas verrät.

**Sichtbare Identität:** Diskrete Verbindungen. Sichere Transaktionen. Volle Selbstbestimmung.

---

## Korrektur 1: Drei Farben verfehlen die Barrierefreiheit

Das Barrierefreiheitsstärkungsgesetz gilt seit dem 28.06.2025 für den elektronischen Geschäftsverkehr und verlangt WCAG 2.1 AA. Die vorgeschlagene Palette wurde nachgerechnet — das Skript liegt bei und ist Teil der Prüfung.

| Farbe | auf `#080B14` | auf `#111827` | Befund |
| :--- | ---: | ---: | :--- |
| White `#F8FAFC` | 18,79:1 | 16,96:1 | besteht |
| Light Gray `#CBD5E1` | 13,24:1 | 11,95:1 | besteht |
| **Muted Gray `#64748B`** | **4,13:1** | **3,73:1** | **fällt durch** für Fließtext |
| Electric Blue `#3B82F6` | 5,34:1 | 4,82:1 | besteht |
| **Digital Violet `#8B5CF6`** | 4,64:1 | **4,19:1** | **fällt durch** auf Karten |
| Soft Pink `#EC4899` | 5,57:1 | 5,03:1 | besteht |
| Success `#22C55E` | 8,63:1 | 7,79:1 | besteht |
| Warning `#F59E0B` | 9,15:1 | 8,26:1 | besteht |
| Alarm `#EF4444` | 5,22:1 | 4,71:1 | besteht |
| **Border Gray `#1E293B`** | **1,34:1** | **1,21:1** | **fällt durch** für Rahmen von Eingabefeldern (1.4.11 verlangt 3:1) |

### Die Korrekturen

Beim Nachrechnen fiel eine zweite Ebene auf, die im Eingangskonzept gar nicht betrachtet wurde: **Der Primary-Button mit Blau-Violett-Verlauf und weißer Schrift erreicht mit den Originalfarben nur 3,68:1** — weiße Schrift auf mittelhellem Blau ist der klassische Stolperstein. Deshalb werden Füll- und Textrollen getrennt.

| Verwendung | vorher | **jetzt** | Nachweis |
| :--- | :--- | :--- | ---: |
| Sekundärtext | `#64748B` | **`#94A3B8`** | 7,67 / 6,92 / 6,20 |
| Akzentblau als Schrift | `#3B82F6` | **`#4C8DF7`** | 6,05 / 5,46 / 4,89 |
| Violett als Schrift | `#8B5CF6` | **`#A78BFA`** | 7,22 / 6,52 / 5,84 |
| Alarmrot als Schrift | `#EF4444` | **`#F35B5B`** | 6,05 / 5,46 / 4,89 |
| Rahmen interaktiver Elemente | `#1E293B` | **`#687A96`** | 4,51 / 4,07 / 3,64 |
| Button-Füllung Blau | `#3B82F6` | **`#2563EB`** | weiße Schrift 5,17 |
| Button-Füllung Violett | `#8B5CF6` | **`#7C3AED`** | weiße Schrift 5,70 |
| Button-Füllung Rot | `#EF4444` | **`#DC2626`** | weiße Schrift 4,83 |

*(drei Werte = auf `#080B14` / `#111827` / `#182236`)*

Die ursprünglichen Markentöne `#3B82F6`, `#8B5CF6` und `#EF4444` bleiben erhalten — als **Symbol-, Fokus- und Aktivfarbe**, wo keine Schrift darauf liegt. Dort erreichen sie 5,34, 4,64 und 5,22 gegen den Hintergrund und bestehen die 3:1-Schwelle für Bedienelemente deutlich. Die Marke sieht also unverändert aus; nur Schrift und Füllungen sind lesbar geworden.

`#64748B` und `#1E293B` bleiben für rein dekorative Trennlinien ohne Bedeutung zulässig.

---

## Korrektur 2: Screenshot-Sperre und verschwindende Medien gibt es nicht

Das Designkonzept nennt „gesperrte Screenshots, soweit technisch möglich" und „verschwindende Medien". **In einer Progressive Web App ist beides nicht umsetzbar.** Es gibt im Browser kein Gegenstück zu Androids `FLAG_SECURE`, und iOS Safari bietet gar nichts. Jeder angezeigte Inhalt ist abgreifbar, spätestens über die Entwicklerwerkzeuge.

Das als Sicherheitsversprechen zu kommunizieren wäre ein Vertrauensbruch, sobald der erste Leak auftaucht — und der taucht auf.

**Was stattdessen gebaut wird und tatsächlich wirkt:**

- **Forensisches Wasserzeichen pro Käufer** in jedem ausgelieferten Bild und Video — unsichtbar. Bei einem Leak ist die Quelle identifizierbar und sperrbar.
- **Kurzlebige, signierte Medienadressen** gegen Weiterverlinkung.
- **Leak-Suche und Takedown als Plattformleistung**, enthalten in der Provision — Creator zahlen dafür heute separat bei Drittanbietern.

> **Kein sichtbares Wasserzeichen im gekauften Inhalt.** Ein früherer Entwurf sah das als Abschreckung vor. Die kritische Prüfung hat es verworfen, und zu Recht: Es beschädigt genau das Produkt, für das bezahlt wurde, und ist damit der kürzeste Weg zu Rückbuchungen — die auf einer Adult-Plattform ohnehin das teuerste Einzelrisiko sind. Sichtbare Kennzeichnung bleibt auf **Vorschauen** beschränkt, wo sie nichts zerstört.

**Formulierung im Produkt:** nicht „Screenshots gesperrt", sondern **„jeder Screenshot ist rückverfolgbar"**. Das ist wahr, wirkt abschreckender und hält.

---

## Korrektur 3: Farben als Tokens, heller Modus möglich halten

Das Konzept ist ausschließlich dunkel. Als Markenentscheidung vertretbar — aber die Farben werden als Tokens angelegt, sodass ein heller Modus später ohne Neubau möglich ist. `prefers-color-scheme` wird ausgewertet.

---

## Tokens

Maßgeblich ist `public/assets/css/tokens.css`. Die Rollentrennung ist dort bewusst im Namen sichtbar: `-fill` trägt Schrift, `-text` **ist** Schrift, ohne Endung ist dekorativ.

```css
:root {
  /* Flächen */
  --ms-bg:             #080B14;
  --ms-surface:        #111827;
  --ms-surface-raised: #182236;

  /* Schrift */
  --ms-text:           #F8FAFC;
  --ms-text-muted:     #CBD5E1;
  --ms-text-subtle:    #94A3B8;   /* korrigiert von #64748B */

  /* Marke — dekorativ: Symbole, Fokusring, Aktivzustand. Nie Schrift darauf. */
  --ms-accent:         #3B82F6;
  --ms-accent-2:       #8B5CF6;
  --ms-accent-3:       #EC4899;

  /* Marke — als Schrift auf dunklem Grund */
  --ms-accent-text:    #4C8DF7;   /* korrigiert von #3B82F6 */
  --ms-accent-2-text:  #A78BFA;   /* korrigiert von #8B5CF6 */
  --ms-accent-3-text:  #EC4899;

  /* Marke — als Schaltflächenfüllung, trägt weiße Schrift */
  --ms-accent-fill:    #2563EB;   /* korrigiert von #3B82F6 */
  --ms-accent-2-fill:  #7C3AED;   /* korrigiert von #8B5CF6 */
  --ms-on-fill:        #FFFFFF;

  /* Rahmen */
  --ms-border:             #1E293B;  /* nur dekorative Trennlinien */
  --ms-border-interactive: #687A96;  /* korrigiert, Eingabefelder und Bedienelemente */

  /* Status */
  --ms-success:      #22C55E;
  --ms-success-fill: #22C55E;
  --ms-warning:      #F59E0B;
  --ms-warning-fill: #F59E0B;
  --ms-danger:       #EF4444;   /* dekorativ */
  --ms-danger-text:  #F35B5B;   /* korrigiert von #EF4444 */
  --ms-danger-fill:  #DC2626;   /* korrigiert, trägt weiße Schrift */

  /* Form */
  --ms-radius:    14px;
  --ms-radius-sm:  8px;
  --ms-font: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
}
```

Der Primary-Button behält damit seinen Blau-Violett-Verlauf — `#2563EB` nach `#7C3AED` — und weiße Schrift bleibt an beiden Enden lesbar (5,17:1 und 5,70:1).

Schriftgrößen wie im Eingangskonzept: große Überschrift 44–56 px, Bereichsüberschrift 30–36 px, Kartenüberschrift 18–22 px, Fließtext 15–17 px, Navigation 13–15 px, Labels 11–13 px.

---

## Diskretion als Funktion

Über das Eingangskonzept hinaus:

**Schnellverbergen.** Eine Geste oder Taste blendet sofort einen unverfänglichen Bildschirm ein. Für eine Plattform, die mit Diskretion wirbt, ist das keine Spielerei, sondern die naheliegendste Erwartung.

**Wählbarer Name auf dem Homescreen.** Technisch der `short_name` im Manifest. Die Vorschläge aus dem Designkonzept — „MS Connect", „MS Space", „M Platform" — werden zur Auswahl gestellt, statt sie vorzugeben.

**Push-Vorschau standardmäßig aus.** Die neutralen Beispieltexte des Eingangskonzepts sind richtig und werden Voreinstellung, nicht Option: „Du hast eine neue Nachricht", „Deine Bestellung wurde aktualisiert", „Dein Termin beginnt bald". Nie ein Absendername, nie ein Vorschaubild, nie ein Produktname.

**Neutraler Kontoauszug — mit einer Einschränkung.** Visa und Mastercard verlangen, dass die Bezeichnung den Händler erkennbar identifiziert. Eine bewusst irreführende Bezeichnung verstößt gegen die Regeln und ist der stärkste Auslöser für Rückbuchungen. Der Weg ist eine abrechnende Gesellschaft mit **unauffälligem, aber echtem** Namen, konsistent in Kontoauszug, Impressum, Support und E-Mail. Vor dem Kauf wird im Klartext angezeigt, wie die Buchung erscheinen wird.

---

## Progressive Web App

**Push-Benachrichtigungen** über die Web-Push-Schnittstelle mit Service Worker. Zwei Einschränkungen, die das Produkt berühren:

- **Auf iOS gibt es Push nur, wenn die App auf dem Homescreen installiert ist** (ab iOS 16.4), und die Erlaubnis muss aus einer echten Nutzerinteraktion heraus abgefragt werden. Ohne Installation kein Push. Es braucht deshalb einen aktiven Installationshinweis und einen Posteingang in der App als Rückfallebene.
- **Push ist nicht zustellgarantiert.** Deshalb darf keine Sicherheitsfunktion allein daran hängen — das betrifft unmittelbar die Vorwarnung bei Safe-Meet. Siehe [`04-features/safe-meet.md`](04-features/safe-meet.md).

**Mobile zuerst:** Navigation am unteren Rand, große Berührungsflächen, einhändige Bedienung, kurze Wege im Bestellvorgang, jederzeit sichtbarer Guthabenstand.

---

## Tonalität

Selbstbewusst, respektvoll, klar. Nicht anzüglich, nicht verschämt.

> Entdecke Creator, die zu dir passen.
> Bestimme selbst, wer deine Inhalte sieht.
> Sichere Zahlungen. Klare Regeln. Volle Kontrolle.
> Diskretion beginnt beim Design.

Alle Texte liegen in `resources/lang/de-DE/`. Keine deutschen Zeichenketten im Code — sonst ist die Mehrsprachigkeit später ein Umbau.

---

## Prüfung der Kontrastwerte

Die Werte oben sind nicht abgeschrieben, sondern berechnet. Das Skript [`kontrast-pruefung.py`](kontrast-pruefung.py) prüft drei Dinge und muss nach **jeder** Farbänderung ohne Fehler durchlaufen:

1. **Schrift auf jeder der drei Flächen** — mindestens 4,5:1
2. **Weiße Schrift auf jeder Schaltflächenfüllung** — mindestens 4,5:1, und die Füllung selbst mindestens 3:1 gegen den Seitenhintergrund, damit ihr Rand erkennbar bleibt
3. **Dekorative Farben** (Symbole, Fokusring, Aktivzustand) — mindestens 3:1 gegen den Hintergrund

```
$ python3 docs/kontrast-pruefung.py
...
Alle Farbkombinationen erfuellen WCAG 2.1 AA.
$ echo $?
0
```

Der zweite Punkt hat die Korrektur der Füllfarben überhaupt erst ausgelöst — die Prüfung ist also nicht dekorativ, sie hat bereits einen echten Fehler gefunden, der sonst bis in die fertige Oberfläche durchgelaufen wäre.
