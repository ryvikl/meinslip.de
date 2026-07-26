# Design-System

> **Stand:** 25.07.2026 · Maßgeblich ist **Nocturne**, geliefert als Export aus Claude Design und umgesetzt in
> [`../public/assets/css/nocturne.css`](../public/assets/css/nocturne.css). Nocturne hat die zuvor hier
> beschriebene Palette **vollständig ersetzt**. Die alte Palette ist unten als
> [Vorgeschichte](#vorgeschichte-die-ersetzte-palette) dokumentiert, weil ihre drei Korrekturen erklären, worauf
> bei jeder Farbänderung zu achten ist — sie ist aber **nicht mehr in Kraft**, und `tokens.css` wurde gelöscht.

## Die Designidee

Die Plattform sieht aus wie Revolut und Vinted, nicht wie eine Erotikseite. Das ist keine Geschmacksfrage: Es macht Diskretion vom Marketingversprechen zur sichtbaren Produkteigenschaft. Die App darf auf dem Homescreen liegen, in der Bahn geöffnet und im Büro benutzt werden, ohne dass ein Blick über die Schulter etwas verrät.

**Sichtbare Identität:** Diskrete Verbindungen. Sichere Transaktionen. Volle Selbstbestimmung.

---

## Nocturne

Nocturne ist dunkel, ruhig und farbarm — genau die Richtung, die die Designidee verlangt. Zwei Eigenschaften unterscheiden es von der ersetzten Palette:

**Ein Akzent statt dreier Markentöne.** `#9184d9` trägt allein. Blau, Violett und Pink nebeneinander waren dekorativ, aber sie machten jede Hervorhebung beliebig: Wenn drei Farben „wichtig" bedeuten, bedeutet keine es. Der zweite Ton `#a7a1db` ist ein Vertreter derselben Familie, keine zweite Aussage.

**Umrissene statt gefüllter Schaltflächen.** Damit entfällt der Stolperstein, der die alte Palette überhaupt erst zur Korrektur zwang — weiße Schrift auf mittelhellem Grund. Ein Umriss trägt seine Schrift auf dem Seitenhintergrund, wo sie 14,54:1 erreicht; der Umriss selbst muss nur 3:1 schaffen und schafft 4,71:1.

**Dichte 0,70×.** Die Abstandstokens sind auf 70 % gestaucht (`--space-6: 16.8px` statt 24 px). Das ist der Grund, warum Nocturne mehr auf einen Handybildschirm bekommt, ohne gedrängt zu wirken.

### Zwei Fehler im Export, beide behoben

| Befund | Warum das nicht bleiben durfte | Behebung |
| :--- | :--- | :--- |
| **Rahmen der Eingabefelder bei 1,58:1** | WCAG 1.4.11 verlangt 3:1 für Bedienelemente. Ein Feld, dessen Rand man nicht sieht, ist kein Feld. | Neues Token `--color-feld-rahmen` mit 40 % Textfarbe → **3,23:1** |
| **`@import` von Google Fonts** | Überträgt die IP-Adresse jedes Besuchers in die USA, bevor er zustimmen konnte. Auf einer Plattform, die mit Diskretion wirbt, ist das der denkbar schlechteste erste Netzwerkaufruf. | Acht Inter-Schnitte als `woff2` selbst gehostet unter `public/assets/fonts/`, eingebunden per `@font-face` |

Beide sind durch Tests abgesichert: `HuelleTest::testKeineSchriftVonFremdenServern` und die Kontrastprüfung.

### Statusfarben — Ergänzung aus der Vorlage

Die Design-Vorlage (`design/MeinSlip App.dc.html`, Kopfnotiz) legt fest: **„Statusfarben nur für
Verifiziert, Warnung, SOS"**. Die Comps setzen sie als Punkt, Linie und kleine Schrift ein — nie als
Fläche. Seit der Design-Überarbeitung stehen sie als Tokens in `nocturne.css`:

| Token | Wert | auf `#161826` | auf `#232532` | Rolle |
| :--- | :--- | ---: | ---: | :--- |
| `--color-ok` | `#22c55e` | 7,73:1 | 6,67:1 | online, bestätigt, quittiert |
| `--color-warnung` | `#f59e0b` | 8,20:1 | 7,07:1 | Bewertung, Hinweis |
| `--color-gefahr` | `#f35b5b` | 5,41:1 | 4,67:1 | Fehler, SOS |

Das Rot der Comps (`#ef4444`) erreicht auf der Kartenfläche nur **4,04:1** und fällt als Schrift
durch; es wurde durch den in Korrektur 1 hergeleiteten schrifttauglichen Alarmton `#f35b5b`
ersetzt. Alle drei Werte prüft `kontrast-pruefung.py` gegen die Textschwelle 4,5:1, weil sie auch
als kleine Schrift („online", „bestätigt") stehen.

### Die Bausteine der Vorlage in der Anwendung

Die Comps sind maßgeblich, `app.css` setzt ihre wiederkehrenden Bausteine tokenbasiert um —
kein Hexwert außerhalb von `nocturne.css`:

- **Hairline-Band** (`.ms-band`): Zellen auf Seitengrund, getrennt durch 1-px-Fugen — das
  Versprechen- und Sicherheitsband der Landing.
- **Kontokarte** (`.ms-kontokarte`): der Indigo-Verlauf (`--color-section` → Fläche) mit
  Akzent-Radialschein — der eine Ort, an dem Sättigung Fläche werden darf.
- **Bereichskarte** (`.ms-bereichskarte`): Symbolchip, Marken, Hebe-Zustand mit Akzentring
  und weichem Schein beim Zeigen.
- **Kacheln** (`.ms-raster--kacheln`, `.ms-kachel`): bildgeführte Katalogkarten, zwei Spalten
  schon auf dem Telefon, der Preis als größte Zahl der Karte.
- **Chips** (`.ms-chip`), **Statuspunkte** (`.ms-status`), **Ring-Symbole** (`.ms-ringsymbol`),
  **Fortschritt** (`.ms-fortschritt`), **Zeilenliste** (`.ms-zeilen`), **Hinweisbox**
  (`.ms-hinweisbox`).
- **Konfigurator** (`.ms-option`, `.ms-summenleiste`): Optionskarten mit Aufpreis rechts und
  eingelegtem Akzentring; die Summenleiste hält Betrag und Bestellknopf beim Rollen zusammen.
- **Chat** (`.ms-verlauf`, `.ms-blase`): eigene Blasen rechts auf `--color-accent-800` mit
  `--color-accent-100`, fremde links auf der Kartenfläche, Schwanz an der Ecke zur eigenen Seite.
- **Telefonvorschau** (`.ms-telefon`): die Hero-Illustration der Landing, rein HTML/CSS und
  dekorativ (`aria-hidden`).

Zwei Zwischenflächen der Comps (`#1c1f2e`, `#1b1d2b`) sind keine Tokens, sondern werden in
`app.css` als `color-mix()` aus `--color-bg` und `--color-surface` gebildet — sie wandern bei
jeder Token-Änderung mit.

### Gemessene Werte

Alle Angaben aus `docs/kontrast-pruefung.py`, nicht abgeschrieben.

| Rolle | Wert | auf `#161826` | auf `#232532` |
| :--- | :--- | ---: | ---: |
| Fließtext `--color-text` | `#e9e9ed` | 14,54:1 | 12,55:1 |
| Akzent als Schrift | `#9184d9` | 5,45:1 | 4,71:1 |
| Sekundärtext `neutral-400` | `#b2b6ca` | 8,75:1 | 7,55:1 |
| Schwächster zulässiger Text `neutral-500` | `#9397ab` | 6,08:1 | 5,25:1 |
| Rahmen Eingabefeld | 40 % Text | — | 3,23:1 |
| Rahmen bei Fokus / `.btn-primary` | Akzent | — | 4,71:1 |

`neutral-600` bis `neutral-900` liegen zwischen 4,08:1 und 1,24:1 und sind **ausschließlich Flächen- und Rahmenfarben** — niemals Schrift. Das Prüfskript führt sie getrennt auf, damit die Grenze im Bericht sichtbar bleibt.

---

## Vorgeschichte: die ersetzte Palette

> Alles ab hier beschreibt den Stand **vor** Nocturne. Die Farbwerte sind nicht mehr in Kraft. Der Abschnitt bleibt, weil die drei Korrekturen die Prüfschritte begründen, die weiterhin bei jeder Farbänderung laufen — und weil Korrektur 2 eine Produktentscheidung ist, die unabhängig vom Design gilt.

### Korrektur 1: Drei Farben verfehlen die Barrierefreiheit

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

#### Die Korrekturen

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

### Korrektur 2: Screenshot-Sperre und verschwindende Medien gibt es nicht

Das Designkonzept nennt „gesperrte Screenshots, soweit technisch möglich" und „verschwindende Medien". **In einer Progressive Web App ist beides nicht umsetzbar.** Es gibt im Browser kein Gegenstück zu Androids `FLAG_SECURE`, und iOS Safari bietet gar nichts. Jeder angezeigte Inhalt ist abgreifbar, spätestens über die Entwicklerwerkzeuge.

Das als Sicherheitsversprechen zu kommunizieren wäre ein Vertrauensbruch, sobald der erste Leak auftaucht — und der taucht auf.

**Was stattdessen gebaut wird und tatsächlich wirkt:**

- **Forensisches Wasserzeichen pro Käufer** in jedem ausgelieferten Bild und Video — unsichtbar. Bei einem Leak ist die Quelle identifizierbar und sperrbar.
- **Kurzlebige, signierte Medienadressen** gegen Weiterverlinkung.
- **Leak-Suche und Takedown als Plattformleistung**, enthalten in der Provision — Creator zahlen dafür heute separat bei Drittanbietern.

> **Kein sichtbares Wasserzeichen im gekauften Inhalt.** Ein früherer Entwurf sah das als Abschreckung vor. Die kritische Prüfung hat es verworfen, und zu Recht: Es beschädigt genau das Produkt, für das bezahlt wurde, und ist damit der kürzeste Weg zu Rückbuchungen — die auf einer Adult-Plattform ohnehin das teuerste Einzelrisiko sind. Sichtbare Kennzeichnung bleibt auf **Vorschauen** beschränkt, wo sie nichts zerstört.

**Formulierung im Produkt:** nicht „Screenshots gesperrt", sondern **„jeder Screenshot ist rückverfolgbar"**. Das ist wahr, wirkt abschreckender und hält.

---

### Korrektur 3: Farben als Tokens, heller Modus möglich halten

Das Konzept ist ausschließlich dunkel. Als Markenentscheidung vertretbar — aber die Farben werden als Tokens angelegt, sodass ein heller Modus später ohne Neubau möglich ist. `prefers-color-scheme` wird ausgewertet.

---

## Tokens

Maßgeblich ist [`../public/assets/css/nocturne.css`](../public/assets/css/nocturne.css). Ergänzungen der Anwendung
stehen in `app.css` und dürfen **keinen einzigen Hexwert** enthalten — Farben, Abstände, Radien und Schatten kommen
ausschließlich aus den Tokens. Nur so bleibt ein heller Modus später eine Frage von `prefers-color-scheme` und nicht
ein Umbau.

```css
:root {
  /* Flächen */
  --color-bg:      #161826;
  --color-surface: #232532;

  /* Schrift */
  --color-text:    #e9e9ed;

  /* Akzent — trägt allein. Als Schrift zulässig: 5,45:1 bzw. 4,71:1. */
  --color-accent:   #9184d9;
  --color-accent-2: #a7a1db;

  /* Linien */
  --color-divider:     color-mix(in srgb, #e9e9ed 16%, transparent);
  --color-feld-rahmen: color-mix(in srgb, #e9e9ed 40%, transparent);  /* ergänzt, 3,23:1 */

  /* Tonleitern, in OKLCH auf einer gemeinsamen Helligkeitsskala erzeugt:
     100–500 sind als Schrift zulässig, 600–900 ausschließlich Flächen. */
  --color-neutral-100: #f3f5fe;  /* … */  --color-neutral-900: #292b31;
  --color-accent-100:  #f5f4ff;  /* … */  --color-accent-900:  #2b2741;

  /* Schrift — selbst gehostet, siehe public/assets/fonts/ */
  --font-heading: "Inter", system-ui, sans-serif;
  --font-body:    "Inter", system-ui, sans-serif;

  /* Abstände, Dichte 0,70× */
  --space-1: 2.8px;  --space-2: 5.6px;  --space-3: 8.4px;
  --space-4: 11.2px; --space-6: 16.8px; --space-8: 22.4px;

  /* Form */
  --radius-sm: 4px;  --radius-md: 8px;  --radius-lg: 14px;
}
```

*(Gekürzt — die vollständigen Tonleitern stehen in der Datei.)*

Schriftgrößen: große Überschrift `clamp(1.75rem, 4vw, 2.5rem)`, darunter die Größen des Systems. Die Kopfzeile ist
der einzige Ort mit einer eigenen Größe (`1.0625rem` für die Wortmarke).

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

Die Werte oben sind nicht abgeschrieben, sondern berechnet. Das Skript [`kontrast-pruefung.py`](kontrast-pruefung.py) prüft vier Dinge und muss nach **jeder** Farbänderung ohne Fehler durchlaufen. Es läuft in `.github/workflows/pruefen.yml` bei jedem Push:

1. **Jede Textfarbe auf beiden Flächen** (`--color-bg` und `--color-surface`) — mindestens 4,5:1
2. **Rahmen von Bedienelementen** gegen die Kartenfläche — mindestens 3:1 nach WCAG 1.4.11. Das betrifft Eingabefelder in Ruhe, bei Hover und bei Fokus sowie den Umriss von `.btn-primary`
3. **Schrift auf getönten Flächen** (`.tag-accent`, `.tag-neutral`) — mindestens 4,5:1 gegen die eigene Füllung, nicht gegen den Seitenhintergrund
4. **Die Stufen 600–900 der Tonleitern** werden getrennt ausgewiesen und als *„als Schrift unzulaessig"* markiert, damit die Grenze im Bericht steht und nicht im Gedächtnis

```
$ python3 docs/kontrast-pruefung.py
...
Alle geprueften Farbkombinationen erfuellen WCAG 2.1 AA.
$ echo $?
0
```

Die Prüfung ist nicht dekorativ: Sie hat in beiden Designs je einen echten Fehler gefunden, der sonst bis in die fertige Oberfläche durchgelaufen wäre — in der alten Palette die weiße Schrift auf Schaltflächenfüllungen (3,68:1), in Nocturne den Rahmen der Eingabefelder (1,58:1).

---

## Was die Oberfläche selbst absichert

Zwei Dinge prüft kein Farbskript, beide haben in dieser Oberfläche bereits zugeschlagen:

**Die Kopfzeile muss auf ein Telefon passen.** Marke, Anmelde- und Registrierknopf, Pseudonym und Schnellverbergen zusammen brauchten gemessene **447 px**. Verbreitete Geräte haben 360–390 px. Unterhalb von 560 px trägt deshalb die untere Navigation die Wege — sie führt abgemeldet zur Anmeldung —, die Wortmarke wird auf das Zeichen reduziert (der Text bleibt für Vorlesegeräte erhalten, sonst hätte der Verweis zur Startseite keinen lesbaren Namen), und das Pseudonym wird bei 12 Zeichen abgeschnitten. Danach: **258 px** abgemeldet, **301 px** angemeldet mit dem längstmöglichen Pseudonym.

**Ein Pseudonym darf 30 Zeichen haben.** Ungebremst schiebt es jede Zeile auseinander, in die es gesetzt wird. Gekappt wird immer per CSS, nie im PHP — so bleibt der volle Name im Markup und Vorlesegeräte geben ihn vollständig aus.
