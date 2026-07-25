# 🚀 Projekt-Akte: MeinSlip.de (Knowledge Base)

> **Eingangsdokument — unverändert übernommen.**
> Erstellt mit Gemini, eingebracht am 25.07.2026. Dieses Dokument ist die Ausgangslage, nicht der aktuelle Stand.
> Mehrere Annahmen darin haben die Recherche nicht überstanden — insbesondere die Altersverifikation (Abschnitt 4 C),
> der Absender bei Safe-Ship (Abschnitt 3, USP 2) und der Ablauf von Safe-Meet (Abschnitt 3, USP 1).
> Was stattdessen gilt, steht in [`docs/`](../docs/). Dieses Dokument wird bewusst **nicht** nachträglich korrigiert,
> damit nachvollziehbar bleibt, was warum geändert wurde.

**Dokument-Typ:** Master-Konzept & Projekt-Wiki
**Plattform:** Progressive Web App (PWA)
**Domain:** meinslip.de
**Markt-Segment:** Creator Economy / E-Commerce / Meetup (Adult / High-Risk)
**Monetarisierung:** Transaktionsgebühren (werbefrei)

---

## 1. 💡 Executive Summary & Vision

MeinSlip.de ist eine hybride PWA, die drei bisher getrennte Milliardenmärkte in einer einheitlichen, werbefreien Plattform vereint:

1. **Content-Monetarisierung** (OnlyFans-Modell: Abos, Pay-per-View, Pay-to-Chat).
2. **Physischer Fetisch-Marktplatz** (Verkauf von z. B. getragener Wäsche).
3. **Sicheres Meetup** (Persönliche Warenübergaben & Treffen).

Das Herzstück bildet ein **Prepaid-Wallet-System** für Käufer, kombiniert mit einer strengen **Treuhand- und KYC-Logik**, die 100% Sicherheit für Verkäufer:innen (Creator) und absolute Diskretion für Käufer garantiert.

---

## 2. 🎯 Zielgruppen, Pain Points & Lösungen

### 🙋‍♀️ Verkäufer:innen (Creators)

| Pain Point (Schmerz) | MeinSlip Lösung |
| :--- | :--- |
| **"Time-Wasters" & Bildersammler** | **Pay-to-Chat:** Creator bestimmen den Preis pro Nachricht. Filtert Nutzer, die nicht zahlen wollen, sofort aus. |
| **Gefahr bei Treffen & No-Shows** | **Safe-Meet 2.0:** QR-Code Treuhand (Ausfallgebühr bei Nichterscheinen) + serverseitiger SOS-Timer (Totmannschalter). |
| **Stalking / Doxing beim Versand** | **Safe-Ship:** Automatisierte, anonymisierte Versandlabels (Plattform-Postfach fungiert als Absender). |
| **Chargebacks (Kreditkarten-Stornos)** | Das Risiko wird durch das Prepaid-Wallet-System für Creator komplett gepuffert. |

### 🙋‍♂️ Käufer (Konsumenten)

| Pain Point (Schmerz) | MeinSlip Lösung |
| :--- | :--- |
| **Fakes, Catfishing & Scammer** | **Strenges KYC:** Ausweis-Check für Creator vor Verkaufs-Freigabe -> "Verified"-Badge (Blauer Haken für 100% Echtheit). |
| **Peinliche Bankabbuchungen** | **Diskretion:** Neutrale Abbuchung beim Wallet-Aufladen (z.B. "Web Services MS" statt "MeinSlip"). |
| **Vorkasse-Betrug (Ware kommt nie)** | **Treuhand (Escrow):** Geld fließt final erst bei digitalem Versandnachweis (Tracking) oder QR-Scan vor Ort. |

---

## 3. 🌟 Core Features & USPs (Alleinstellungsmerkmale)

### 🛡️ USP 1: "Safe-Meet 2.0" (QR-Treuhand & Dead Man's Switch)

Ein kugelsicheres System für persönliche Treffen, das die Sicherheit der Creatorin in den Mittelpunkt stellt:

1. **Buchung (Escrow):** Käufer bucht ein Treffen. Der Betrag wird in seinem Wallet eingefroren.
2. **Check-In (Start):** Verkäuferin scannt beim Treffen den QR-Code des Käufers. Das Geld wird unwiderruflich auf sie überschrieben.
3. **Der Timer:** Ein serverseitiger Countdown (z.B. für 60 Min) startet. Temporäres GPS-Tracking der Creatorin wird aktiv.
4. **Vorwarnung (Silent Check):** 10 Minuten vor Ablauf erhält die Creatorin einen diskreten In-App Ping/Vibration.
5. **Checkout:** Sie beendet das Treffen sicher per PIN / Face-ID auf *ihrem* Gerät (oder verlängert die Zeit). *Kein Scan des Käufers nötig (Sicherheitsrisiko)!*
6. **Eskalation:** Erfolgt kein Checkout, sendet der Server automatisch einen SOS-Alarm (SMS) inkl. letztem GPS-Standort & Käufer-ID an ihren Vertrauenskontakt.

### 📦 USP 2: "Safe-Ship" (Anonymisierter Versand)

Löst die Angst vor Stalking durch die echte Absenderadresse. Bei Kauf wird (finanziert aus dem Käufer-Wallet) ein fertiges DHL/Hermes Label generiert. **Absender ist ein Postfach/Adresse der Plattform-GmbH.** Die Creatorin druckt es nur aus und bleibt zu 100% anonym.

### ⚙️ USP 3: Trage-Konfigurator & "Proof of Wear"

Käufer kaufen nicht nur ein Produkt, sie konfigurieren es (Upselling):

* **Basis:** Slip (20€)
* **Add-Ons:** + 2 Tage extra tragen (+10€), + beim Sport tragen (+15€).
* **Proof of Wear:** Käufer können ein Kurzvideo dazubuchen, das zeigt, wie die Creatorin exakt dieses Produkt auszieht und einpackt (wird nach dem Kauf im Chat freigeschaltet).

### 🔄 USP 4: Reverse-Marketplace (Wunschzettel)

Käufer stellen Gesuche ein (z.B. *"Suche Sportsocken, 3 Tage im Gym getragen, Größe 39, Budget 50€"*). Das Budget wird zur Sicherheit sofort im Wallet eingefroren, um Spaßanfragen zu blockieren. Verifizierte Creator können das Gesuch mit einem Klick annehmen.

---

## 4. 🏛️ Technische Architektur & Compliance ("Die Elefanten im Raum")

Um nicht von App-Stores, Gesetzen oder Banken gesperrt zu werden, müssen folgende Regeln in der Architektur zwingend beachtet werden:

### A. PWA (Progressive Web App)

Native Apps mit Erotik/Adult-Inhalten werden von Apple/Google rigoros aus den Stores gelöscht. Die Lösung ist eine PWA: Sie lässt sich über den mobilen Browser (Safari/Chrome) direkt als App auf dem Homescreen installieren (inkl. Push-Benachrichtigungen & Kamera-Zugriff für QR/Selfies).

### B. High-Risk Payment & BaFin (Treuhand)

* **Verboten:** Normale Anbieter wie PayPal, Stripe, Shopify Payments oder Klarna frieren Konten in dieser Nische sofort ein (AGB-Verstoß).
* **Lösung:** Anbindung spezieller **High-Risk-Payment-Gateways** (z.B. CCBill, Epoch, SecurionPay) für Wallet-Aufladungen (Kreditkarte/SEPA).
* **Escrow-Architektur (Split-Payments):** Nutzergelder dürfen rechtlich nicht direkt auf dem eigenen Firmenkonto der Betreiber liegen, um nicht als lizenzpflichtiges Bank/Zahlungsgeschäft (BaFin) eingestuft zu werden. Der Provider muss Marktplatz-Treuhand unterstützen.

### C. Jugendschutz (JMStV) & KYC (Know Your Customer)

Im Gegensatz zu "Schwarzen Brettern" (z.B. markt.de), betreibt MeinSlip eine Paywall mit Payment-Providern (Visa/Mastercard BRAM Compliance). Daher greift ein **3-Schichten-Modell**:

* **Level 0 (Gast):** Startseite/Profile sind jugendfrei (Explizites ist geblurt, Softe Erotik erlaubt). Zugang per einfachem *18+ Klick*.
* **Level 1 (Käufer):** Altersverifikation (AVS) passiert nahtlos im Hintergrund bei der ersten Wallet-Aufladung (Abgleich der Bank-/Schufa-Daten).
* **Level 2 (Creator / Verkäufer):** Strenger KYC-Prozess via Drittanbieter-API (z.B. *Yoti*, *IDnow*). Ein Ausweis-Scan + Live-Selfie-Match ist *zwingende Pflicht*, bevor ein Profil freigeschaltet wird (Schutz vor Geldwäsche & Minderjährigen).

---

## 5. 🤖 KI-Prompts für die Weiterentwicklung

*(Diese Bausteine können genutzt werden, um KIs wie ChatGPT/Claude oder Entwickler-Teams punktgenau für den nächsten Schritt zu briefen)*

### 📝 Prompt 1: System-Architektur & Backend

> "Agiere als Senior Software-Architekt. Entwirf das Backend- und Datenbank-Konzept für die PWA 'MeinSlip.de'. Es ist ein hybrider Adult-Marktplatz (Content-Abos + Physische Produkte + Escort/Meetup).
> **Anforderungen:**
> 1. Prepaid-Wallet-System mit Escrow-Logik (Treuhand) via High-Risk Payment Gateway (Split-Payments).
> 2. Integration einer 3rd-Party KYC-API (z.B. IDnow/Yoti) für das 3-stufige Jugendschutzmodell (Gast = geblurt, Käufer = AVS, Creator = Full ID-Scan).
> 3. API-Anbindung an Versanddienstleister (DHL/Hermes) zur Generierung anonymisierter Versandlabels (Safe-Ship), bei denen die Absenderadresse serverseitig auf ein Firmenpostfach überschrieben wird. Erstelle ein ER-Diagramm für die Datenbank."

### 📝 Prompt 2: Safe-Meet Logik (Dead Man's Switch)

> "Agiere als Backend-Entwickler. Schreibe den Logik-Flow (Backend & Cronjobs) für unser 'Safe-Meet' Feature, einen serverseitigen Totmannschalter.
> 1. Trigger: QR-Scan durch Client A (Creator) transferiert Escrow-Guthaben und startet einen serverseitigen Countdown inkl. temporärem GPS-Tracking.
> 2. Vorwarnung: 10 Min vor Ablauf des Timers geht ein lautloser Push-Ping an Client A.
> 3. Safe-Checkout: Client A stoppt Timer durch PIN/Biometrie-Eingabe (ohne Client B).
> 4. Fail-Trigger: Läuft der Timer ohne PIN-Eingabe ab, feuert das Backend autark einen SOS-Alert (z.B. API zu SMS-Gateway wie Twilio) an einen Notfallkontakt, inklusive dem letzten bekannten GPS-Payload und der ID von Client B."

### 📝 Prompt 3: UI / UX Design

> "Agiere als UI/UX Designer. Erstelle ein Mobile-First Konzept für die PWA 'MeinSlip.de'. Das Design muss Premium, sicher und modern wirken (Mischung aus Vinted und Patreon/OnlyFans), ohne 'schmuddelig' zu sein.
> Skizziere den User-Flow für folgende Screens:
> 1. Creator-Profil (mit geblurtem Content für Gäste).
> 2. Trage-Konfigurator im Checkout (Basis-Item + Upsell-Checkboxes + 'Proof of Wear' Video-Zubuchung).
> 3. Das Dashboard für das 'Safe-Meet' Feature (QR-Scanner und der laufende Sicherheits-Timer mit unauffälligem PIN-Eingabefeld)."
