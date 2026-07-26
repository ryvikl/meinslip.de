# Architektur

> **Stand:** 25.07.2026 · Dieses Dokument beschreibt, was gebaut wird. Es ist die Vorlage für den Code in `app/` und
> `database/`, nicht eine nachträgliche Beschreibung.

## Technische Grundentscheidung

**PHP 8 und MySQL**, ausgeliefert über das vorhandene All-Inkl-Webhosting. Das Frontend ist eine Progressive Web App ohne Build-Schritt-Zwang.

Begründung: Auf Standard-Webhosting laufen keine dauerhaften Node-Prozesse. PHP und MySQL sind dort garantiert verfügbar, das Deployment ist ein Dateiabgleich, und es entstehen keine zusätzlichen Betriebskosten. Der Preis dieser Entscheidung ist, dass langlaufende Hintergrundprozesse über Cronjobs statt über einen Worker-Prozess laufen müssen — für die geplanten Aufgaben (Fristen, Freigaben, Benachrichtigungen) reicht das.

**Kein Framework mit schwerem Unterbau.** Ein schlanker eigener Kern: Router, Container, Datenbankschicht auf PDO, Migrationen, Validierung. Das hält die Anwendung auf Shared Hosting schnell und macht sie ohne fremde Konventionen lesbar.

---

## Die fünf Prinzipien, die im Code sichtbar sein müssen

### 1. Rolle ist eine Fähigkeit, kein Kontotyp

Es gibt **eine** Nutzertabelle. Niemand ist „Käufer" oder „Verkäuferin" — jedes Konto besitzt Fähigkeiten, die freigeschaltet werden.

```
users ──< user_capabilities (buy | sell | ...)
```

Registrierung schaltet `buy` frei, sobald die Altersprüfung besteht. `sell` kommt hinzu, wenn die Identitätsprüfung besteht — **dieselbe Person, dasselbe Konto, eine zusätzliche Berechtigung**. Kein zweites Konto, kein Wechsel, kein Datenverlust.

Die Oberfläche wechselt den Kontext, nicht das Konto.

### 2. Geld wird doppelt gebucht und nie überschrieben

Kein `UPDATE` auf einen Kontostand. Stattdessen ein Hauptbuch mit doppelter Buchführung:

```
ledger_transactions ──< ledger_entries >── ledger_accounts
```

Jede Bewegung erzeugt mindestens zwei Einträge, deren Summe null ergibt. Ein Kontostand ist immer die Summe der Einträge, nie ein gespeicherter Wert. Das macht jede Abweichung sofort sichtbar und die Belegkette prüfbar — was das Kommissionsmodell ohnehin verlangt.

**Jedes Konto führt getrennte Töpfe:**

| Konto | Bedeutung |
| :--- | :--- |
| `user_balance` | Aufgeladenes Guthaben zum Ausgeben |
| `user_earnings` | Einnahmen aus Verkäufen, auszahlbar |
| `escrow` | Treuhandbestand je Bestellung, gehört noch niemandem |
| `platform_revenue` | Provision |
| `platform_vat` | Umsatzsteueranteil, getrennt geführt |

Ob Einnahmen direkt wieder ausgegeben werden dürfen, ist ein Schalter mit Betragsgrenze — bequem, aber ein Geldwäschepfad über zwei abgestimmte Konten. Voreinstellung: erlaubt erst nach vollständiger Identitätsprüfung, mit Obergrenze.

### 3. Die GmbH ist die Verkäuferin

Jede Warenbestellung erzeugt **zwei** Geschäftsvorfälle, nicht einen mit Provisionsabzug:

1. Die GmbH kauft beim Creator ein (Einkauf, Gutschriftsverfahren)
2. Die GmbH verkauft an den Käufer (Verkauf, Rechnung)

Das Datenmodell bildet beide ab. Umsatzsteuersatz und Land werden **pro Bestellposition** gespeichert, nicht global konfiguriert — sonst ist die Expansion ein Umbau statt einer Konfiguration.

### 4. Einkäufe sind strukturell privat

Keine Abfrage im gesamten System darf die Einkäufe eines Kontos einer anderen Person zugänglich machen — auch nicht aggregiert, auch nicht in Bestenlisten, auch nicht als „kürzlich gekauft".

Das wird nicht über eine Einstellung gelöst, sondern über die Datenzugriffsschicht: Einkaufsdaten sind nur über den eigenen Kontext abrufbar. Bewertungen sind nach Richtung getrennt, damit das Verhalten als Käufer die Verkäufer-Reputation nicht berührt und umgekehrt.

### 5. Land ist ein Adapter, nicht eine Bedingung im Code

```php
// So nicht:
if ($country === 'DE') { $vat = 0.19; }

// Sondern:
$rules = CountryRules::for($order->country_code);
$vat   = $rules->vatRate($item->type);
```

Ein Land umfasst: Umsatzsteuersätze, zugelassene Altersprüfverfahren, Versanddienstleister, Zahlarten und **Funktionsschalter** — die Treffen-Funktion muss länderweise abschaltbar sein, ohne den Rest anzufassen. Zum Start ist nur `DE` aktiv.

Alle Texte liegen in Übersetzungsdateien unter `resources/lang/`, `de-DE` als erste Sprache. Keine deutschen Zeichenketten im Code.

---

## Datenmodell im Überblick

### Konten und Prüfungen

| Tabelle | Zweck |
| :--- | :--- |
| `users` | Pseudonym, E-Mail, Passwort-Hash, Sprache, Land, Status |
| `user_capabilities` | `buy`, `sell` — mit Zeitpunkt und Grundlage der Freischaltung |
| `user_identities` | Klardaten für Meldepflichten — getrennt von `users`, eng zugriffsbeschränkt |
| `verifications` | Ergebnis einer Prüfung: Art, Anbieter, Referenz, Status, Gültigkeit |
| `sessions` | inklusive `adult_gate_passed_at` für die Authentifizierung pro Nutzungsvorgang |

> **Die Plattform speichert niemals Ausweisdokumente.** Von der Identitätsprüfung kommt nur das Ergebnis zurück — bestanden ja/nein, volljährig ja/nein, ein Referenzschlüssel beim Anbieter. Wer die Daten nicht hat, kann sie nicht verlieren. Das ist zugleich eine Anforderung aus der Datenschutz-Folgenabschätzung.

**Das Zugangs-Gate ist ein eigenes Feld, kein Nebeneffekt der Anmeldung.** § 4 Abs. 2 JMStV verlangt eine Authentifizierung bei jedem Nutzungsvorgang, der nicht unmittelbar auf die Identifizierung folgt. Angemeldet sein genügt nicht.

### Katalog

| Tabelle | Zweck |
| :--- | :--- |
| `categories` | Fetisch-Taxonomie, hierarchisch — der Discovery-Motor |
| `products` | Angebot einer Verkäuferin, Grundpreis, Lieferarten |
| `product_options` | Konfigurator-Optionen mit Aufpreis |
| `product_media` | Bilder, mit serverseitig erzeugter unscharfer Vorschau |

**Zur unscharfen Vorschau:** Sie wird serverseitig als eigene Datei erzeugt. Ein Weichzeichner per CSS ist wirkungslos — das Original läge dann im Browser. Ein Umsetzungsdetail mit rechtlicher Wirkung.

### Bestellung und Treuhand

| Tabelle | Zweck |
| :--- | :--- |
| `orders` | Zustand, Käufer, Verkäuferin, Beträge, Land, Lieferart |
| `order_items` | Position mit Umsatzsteuersatz, Einkaufs- und Verkaufspreis, Provision |
| `order_specifications` | **Die vom Käufer gesetzten Spezifikationen mit Zeitstempel** |
| `order_events` | Vollständiges Protokoll aller Zustandswechsel |
| `escrow_holds` | Treuhandbindung mit Frist |

> **`order_specifications` ist keine Bequemlichkeitstabelle.** Sie ist der Nachweis, dass die Ware nach Kundenspezifikation angefertigt wurde — die Grundlage des Widerrufsausschlusses nach § 312g Abs. 2 Nr. 1 BGB. Eine Bestellung ohne mindestens eine Spezifikation darf technisch nicht entstehen können.

### Zustandsautomat einer Warenbestellung

```
entwurf
   └─> zahlung_offen ──(Guthaben reicht nicht)──> abgebrochen
          └─> treuhand_gebunden ──(Verkäuferin lehnt ab / Frist)──> erstattet
                 └─> angenommen
                        └─> in_vorbereitung
                               ├─> versendet ────────┐
                               └─> uebergabe_geplant ┤
                                      └─> uebergeben ┴─> zugestellt
                                                            └─> einspruchsfenster
                                                                   ├─(Frist)──> freigegeben
                                                                   └─(Einspruch)─> streitfall
                                                                                      ├─> freigegeben
                                                                                      └─> erstattet
```

Drei Regeln, die im Code erzwungen werden:

1. **Kein Zustandswechsel bewegt Geld direkt.** Geld bewegt sich ausschließlich über Hauptbuch-Buchungen, die an ein protokolliertes Ereignis gebunden sind.
2. **Freigabe erfolgt nie sofort**, sondern immer nach Ablauf eines Einspruchsfensters. Das ist der Kernunterschied zum ursprünglichen Entwurf und der Grund, warum Betrug nicht endgültig wird.
3. **Jeder Wechsel schreibt nach `order_events`.** Kein stiller Zustandswechsel.

### Hauptbuch

| Tabelle | Zweck |
| :--- | :--- |
| `ledger_accounts` | Konto je Nutzer und Topf, plus Plattformkonten |
| `ledger_transactions` | Geschäftsvorfall mit Art und Bezug |
| `ledger_entries` | Einzelbuchung, unveränderlich, Summe je Vorgang ist null |

### Vertrauen und Missbrauchsabwehr

| Tabelle | Zweck |
| :--- | :--- |
| `ratings` | nach Richtung getrennt, an eine Bestellung gebunden |
| `blocks` | Sperrungen, geräteübergreifend |
| `reports` | Meldungen mit Bearbeitungsstand und zugesagter Reaktionszeit |
| `account_signals` | Gerät, Netzadresse, Zahlungsmittel — für die Erkennung verbundener Konten |

**Geschäfte mit sich selbst und zwischen verbundenen Konten werden blockiert.** Ohne das wird die Provision zur Geldwäscheschleife — und getragene Wäsche hat, anders als Kleidung bei anderen Marktplätzen, keinen objektiven Preisanker, an dem sich ein auffälliger Betrag erkennen ließe.

### Länder und Sprache

| Tabelle | Zweck |
| :--- | :--- |
| `countries` | aktiv ja/nein, Umsatzsteuersätze, Prüfverfahren, Funktionsschalter |

---

## Verzeichnisstruktur

```
public/                  einziges über das Web erreichbares Verzeichnis
  index.php              Einstiegspunkt
  assets/                CSS, JavaScript, Symbole
  manifest.webmanifest
  sw.js                  Service Worker
app/
  Core/                  Router, Container, Request, Response, Datenbank, Migrationen
  Domain/                Fachlogik ohne Web- und Datenbankbezug
    Account/             Konten, Fähigkeiten, Prüfungen
    Ledger/              Hauptbuch, Buchungen, Kontostände
    Catalog/             Produkte, Kategorien, Optionen
    Order/               Bestellung, Zustandsautomat, Treuhand
    Country/             Länder-Adapter
  Http/                  Controller, Middleware, Formularprüfung
  Support/               Hilfsfunktionen
database/
  migrations/            nummerierte SQL-Migrationen
  seeds/
resources/
  lang/de-DE/            alle Texte
  views/                 Templates
storage/                 Logs, Cache, hochgeladene Dateien — nicht über das Web erreichbar
tests/
docs/
```

**Nur `public/` ist über das Web erreichbar.** Auf All-Inkl wird das Dokumentenstammverzeichnis der Domain darauf gezeigt; zusätzlich sichert eine `.htaccess` die übrigen Verzeichnisse ab, falls die Zuordnung einmal nicht greift.

---

## Sicherheit

**Zugangsdaten ausschließlich in `.env`**, niemals im Repository. Die Datei ist in `.gitignore` erfasst, `.env.example` dokumentiert die nötigen Werte ohne Inhalte.

**Passwörter** mit `password_hash()` und Argon2id, sofern verfügbar, sonst bcrypt.

**Alle Datenbankzugriffe über vorbereitete Anweisungen.** Keine zusammengesetzten Abfragen.

**Zugriffsschutz für Medien:** Bilder liegen in `storage/medien/`, außerhalb von `public/`, und werden ausschließlich über `MedienRouten` ausgeliefert.

Gebaut ist bewusst *keine* signierte Adresse, sondern etwas Strengeres: **Der Pfad kommt niemals aus der Anfrage.** Die Adresse trägt nur eine Ganzzahl, daraus wird eine Datenbankzeile, und erst die trägt den Pfad. Ein `..` kann damit gar nicht erst hineingeraten — Traversal ist strukturell unmöglich statt weggefiltert. Die Berechtigung entscheidet das *gebundene Angebot*, nicht die Medienzeile. Jede Ablehnung ist dieselbe `404` ohne Rumpf; ein `403` würde verraten, welche Kennungen existieren.

Eine kurzlebige Signatur kommt erst mit den bezahlten Inhalten dazu — sie löst ein anderes Problem (Weitergabe einer gültigen Adresse), das ohne Bezahlschranke noch keines ist.

**Noch nicht gebaut — forensisches Wasserzeichen pro Käufer** in jedem ausgelieferten Medium. Es ist der belastbare Ersatz für die im Designkonzept vorgesehene Screenshot-Sperre, die in einer PWA technisch nicht möglich ist. Es gehört zu den bezahlten Inhalten und damit hinter den gesperrten Bestellvorgang.

**Übertragung ausschließlich verschlüsselt**, HSTS, sichere Cookie-Attribute, Inhaltssicherheitsrichtlinie.

---

## Wiederkehrende Aufgaben

Über Cronjobs, weil auf Shared Hosting kein dauerhafter Prozess läuft:

| Aufgabe | Takt |
| :--- | :--- |
| Einspruchsfenster prüfen und fällige Treuhandbeträge freigeben | alle 5 Minuten |
| Abgelaufene Zahlungs- und Annahmefristen zurückabwickeln | alle 5 Minuten |
| Fristen bei Safe-Meet und Eskalationskette | jede Minute |
| Meldeschwellen fortschreiben | täglich |
| Hauptbuch auf Ausgeglichenheit prüfen und bei Abweichung alarmieren | stündlich |

Der letzte Punkt ist wichtiger, als er klingt: Eine Abweichung im Hauptbuch bedeutet einen Fehler in der Geldlogik und muss laut auffallen, nicht still bleiben.

---

## Was bewusst später kommt

Live-Übertragungen, Empfehlungen durch maschinelles Lernen, eine öffentliche Schnittstelle für Dritte, Mehrwährungsfähigkeit. Nichts davon ist durch die jetzigen Entscheidungen verbaut — aber alles davon würde den ersten lauffähigen Stand verzögern.
