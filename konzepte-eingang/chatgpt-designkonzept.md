# Designkonzept für MeinSlip.de

> **Eingangsdokument — unverändert übernommen.**
> Erstellt mit ChatGPT, eingebracht am 25.07.2026. Dieses Dokument ist die Ausgangslage, nicht der aktuelle Stand.
> Die Designrichtung trägt und wurde übernommen. Drei Punkte wurden korrigiert: drei Farbwerte verfehlen WCAG 2.1 AA
> (Abschnitt 3), die Screenshot-Sperre und verschwindende Medien sind in einer PWA technisch nicht umsetzbar
> (Abschnitt 12), und es fehlt ein heller Modus. Was stattdessen gilt, steht in
> [`docs/09-design-system.md`](../docs/09-design-system.md). Dieses Dokument wird bewusst **nicht** nachträglich
> korrigiert, damit nachvollziehbar bleibt, was warum geändert wurde.

## 1. Designrichtung

MeinSlip.de soll auf den ersten Blick wie eine moderne Plattform für Creator, Community und digitale Services wirken.
Die Seite darf nicht sofort verraten, dass es um Fetischprodukte oder Adult-Content geht. Dadurch kann die PWA problemlos auf dem Homescreen liegen, unterwegs geöffnet oder in der Öffentlichkeit genutzt werden.

Die visuelle Richtung verbindet:

* Premium-Fintech
* Creator-Plattform
* moderner Marktplatz
* sichere Dating- und Meetup-App
* dezente Social-Media-Elemente

Das Ergebnis soll hochwertig, technisch, vertrauenswürdig und diskret wirken.

## 2. Markenwirkung

Die Marke soll folgende Gefühle vermitteln:

**Diskretion** — Keine eindeutigen Symbole, keine Dessous-Illustrationen und keine auffällige Erotik-Optik.

**Sicherheit** — Wallet, Verifizierung, Escrow und Safe-Meet stehen visuell im Mittelpunkt.

**Premium** — Dunkle Flächen, hochwertige Typografie, großzügige Abstände und dezente Animationen.

**Modernität** — Gradienten, leichte Glow-Effekte, klare Karten und eine Mobile-First-Navigation.

**Selbstbestimmung** — Creator bestimmen Preise, Grenzen, Kommunikation und Sichtbarkeit selbst.

## 3. Farbwelt

### Hauptfarben

| Name | Wert | Verwendung |
| :--- | :--- | :--- |
| Midnight Black | `#080B14` | Hintergrund der App und Hauptfarbe des Dark Modes |
| Deep Navy | `#111827` | Für Karten, Navigation und große Inhaltsflächen |
| Electric Blue | `#3B82F6` | Für Sicherheit, Verifizierung, Buttons und aktive Elemente |
| Digital Violet | `#8B5CF6` | Für Creator-Funktionen, Premium-Features und Highlights |
| Soft Pink | `#EC4899` | Nur sehr sparsam für exklusive Inhalte, Likes oder besondere Creator-Elemente |

### Neutrale Farben

| Name | Wert |
| :--- | :--- |
| White | `#F8FAFC` |
| Light Gray | `#CBD5E1` |
| Muted Gray | `#64748B` |
| Border Gray | `#1E293B` |

### Statusfarben

| Name | Wert |
| :--- | :--- |
| Erfolgreich / Verifiziert | `#22C55E` |
| Warnung | `#F59E0B` |
| Alarm / SOS | `#EF4444` |

Die Farbwelt sollte hauptsächlich aus Schwarz, Dunkelblau, Blau und Violett bestehen. Pink wird nur als dezenter Akzent eingesetzt, damit die Plattform nicht zu verspielt oder eindeutig erotisch wirkt.

## 4. Typografie

**Hauptschrift:** Inter, Manrope oder Plus Jakarta Sans. Diese Schriften wirken modern, digital und sehr gut lesbar.

**Alternative für Überschriften:** Sora oder Space Grotesk. Überschriften dürfen etwas futuristischer wirken, sollten aber nicht technisch-kalt werden.

### Typografie-System

* Große Überschrift: 44–56 px
* Bereichsüberschrift: 30–36 px
* Kartenüberschrift: 18–22 px
* Fließtext: 15–17 px
* Navigation: 13–15 px
* Labels: 11–13 px

Die Texte sollten kurz, klar und ruhig formuliert sein.

## 5. Logo und App-Icon

Das App-Icon besteht ausschließlich aus dem abstrakten M-Monogramm.
Es enthält keine Unterwäsche, Herzen oder erotischen Symbole.

Das Logo funktioniert dadurch auch:

* auf dem Smartphone-Homescreen
* als Browser-Favicon
* als Profilbild
* in neutralen Zahlungsinformationen
* auf Versandmaterialien
* in Push-Benachrichtigungen

Das Icon erhält einen dunklen Hintergrund mit einem Blau-Violett-Pink-Verlauf im Monogramm.

Der sichtbare App-Name könnte auf dem Homescreen verkürzt werden: **MeinSlip**

Alternativ besonders diskret: **MS Connect**, **MS Space**, **M Platform**

Innerhalb der Webseite bleibt der vollständige Name MeinSlip.de sichtbar.

## 6. Aufbau der Startseite

### Header

Der Header ist minimal und leicht transparent.

Links: Logo und MeinSlip.de

Rechts: Entdecken · Sicherheit · Für Creator · Anmelden · Konto erstellen

Der wichtigste Button lautet: **Jetzt entdecken**

Auf dem Smartphone wird eine kompakte Navigation mit Menü-Icon verwendet.

### Hero-Bereich

Große Überschrift: **Dein Raum. Deine Regeln.**

Unterzeile: *Eine diskrete Plattform für exklusive Inhalte, persönliche Wünsche und sichere Verbindungen.*

Buttons: **Profile entdecken** · **Als Creator starten**

Daneben oder darunter befindet sich ein hochwertiges Smartphone-Mockup der PWA.

Im Mockup sieht man keine expliziten Inhalte, sondern:

* Creator-Karten
* Verifizierungsbadge
* Wallet-Balance
* sichere Nachrichten
* Marketplace-Produkte
* Safe-Meet-Status

Zusätzliche Vertrauenselemente:

* Identitätsgeprüfte Creator
* Sichere Wallet-Zahlungen
* Diskrete Nutzung
* Geschützte Kommunikation

### Vertrauensbereich

Eine horizontale Sektion mit drei Kernversprechen:

**Diskret** — Neutrale Darstellung, geschützte Daten und unauffällige Nutzung.

**Sicher** — Verifizierung, Escrow-Zahlungen und kontrollierte Übergaben.

**Direkt** — Creator und Käufer kommunizieren ohne Werbung und ohne unnötige Zwischenwege.

### Plattform-Bereiche

Drei große Karten zeigen die wichtigsten Funktionen.

**Content** — Exklusive Beiträge, Abonnements, Pay-per-View und private Inhalte.

**Marketplace** — Personalisierte Produkte, individuelle Konfigurationen und diskreter Versand.

**Safe-Meet** — Sicher gebuchte persönliche Übergaben mit QR-Check-in und Sicherheits-Timer.

Die Karten sollten beim Überfahren leicht leuchten und sich minimal nach oben bewegen.

### Creator-Bereich

Überschrift: **Baue deine eigene Community auf.**

Inhalte:

* Eigene Preise festlegen
* Nachrichten monetarisieren
* Produkte anbieten
* Wunschaufträge annehmen
* Auszahlungen verwalten
* Sichtbarkeit selbst bestimmen

Dazu ein Creator-Dashboard als Mockup. Das Dashboard zeigt:

* Einnahmen
* neue Bestellungen
* Nachrichten
* aktive Abonnements
* Safe-Meet-Buchungen
* Auszahlbarer Betrag

### Sicherheitsbereich

Dieser Bereich sollte besonders hochwertig und technisch wirken.

Überschrift: **Sicherheit ist kein Zusatz. Sie ist das System.**

Vier Sicherheitsmodule:

**Verified Identity** — Creator werden vor der Freischaltung geprüft.

**Protected Wallet** — Zahlungen werden sicher verwaltet und erst bei erfüllten Bedingungen freigegeben.

**Safe-Ship** — Versandlabels schützen private Absenderdaten.

**Safe-Meet** — QR-Check-in, Sicherheits-Timer und optionaler Notfallkontakt.

Visuell können feine Linien, Sicherheitsringe und animierte Statuspunkte verwendet werden.

### Abschlussbereich

Überschrift: **Bereit für eine Plattform, die zu deinen Regeln passt?**

Buttons: **Kostenlos registrieren** · **Creator werden**

Kleine Hinweise: 18+ · Diskrete Nutzung · Verifizierte Accounts · Werbefreie Plattform

## 7. Dashboard für Käufer

### Navigation

Die mobile Navigation besitzt fünf Punkte:

* Home
* Entdecken
* Nachrichten
* Wallet
* Profil

Der zentrale Entdecken-Button kann optisch hervorgehoben werden.

### Start-Dashboard

Oben: Begrüßung und Wallet-Guthaben.

Darunter:

* empfohlene Creator
* neue Inhalte
* offene Bestellungen
* gespeicherte Profile
* aktive Chats
* Safe-Meet-Termine

### Creator-Karten

Eine Creator-Karte enthält:

* Profilbild
* Vorname oder Künstlername
* Verifizierungsbadge
* Standort nur grob
* Bewertung
* Kategorien
* Abopreis
* Online-Status

Inhalte für nicht verifizierte Gäste bleiben weich geblurt.

## 8. Creator-Profil

### Profilkopf

* großes Coverbild
* rundes Profilbild
* Künstlername
* blauer Verifizierungsbadge
* Bewertung
* Abonnentenzahl
* grobe Region
* Online-Status

Buttons: **Folgen** · **Abonnieren** · **Nachricht senden**

### Profilbereiche

* Feed
* Shop
* Wünsche
* Treffen
* Über mich
* Bewertungen

### Diskrete Darstellung

Öffentliche Inhalte wirken stilvoll und neutral.

Explizitere Inhalte werden durch folgende Elemente geschützt:

* Blur-Effekt
* Schloss-Symbol
* Preis
* Altersverifikation
* Freischaltungsbutton

## 9. Marketplace-Design

Der Marktplatz ähnelt optisch einer Mischung aus Vinted und einer modernen Creator-Plattform.

### Filter

* Kategorie
* Preis
* Größe
* Tragedauer
* Versand oder Übergabe
* Verifiziert
* Bewertung
* verfügbar ab

### Produktkarte

* neutrales Produktbild
* Titel
* Creator
* Verifizierungsbadge
* Basispreis
* Lieferart
* Personalisierbar
* Favoriten-Button

Die Produktbilder sollten niemals zu grafisch oder billig wirken. Die Bildsprache bleibt hochwertig, weich und ästhetisch.

## 10. Trage-Konfigurator

Der Konfigurator funktioniert wie ein moderner Checkout.

**Schritt 1** — Produkt auswählen

**Schritt 2** — Optionen hinzufügen. Beispiele:

* zusätzlicher Tragetag
* Sport
* persönlicher Wunsch
* Duftoption
* Foto-Update
* Proof-of-Wear
* Express-Bearbeitung

Jede Option befindet sich in einer klaren Karte mit Preis und kurzer Erklärung.

**Schritt 3** — Lieferung

* Safe-Ship
* persönliche Übergabe
* diskrete Verpackung

**Schritt 4** — Zahlung

Wallet-Guthaben, Gesamtpreis und Escrow-Hinweis.

Der Button lautet: **Sicher bestellen**

## 11. Safe-Meet-Ansicht

Diese Ansicht muss besonders ruhig und professionell wirken.

### Vor dem Treffen

* Datum
* Uhrzeit
* Treffpunkt
* Buchungswert
* Sicherheitskontakt
* QR-Code
* Hinweise

### Während des Treffens

Großer Timer: **54:32 Minuten verbleibend**

Darunter:

* Status: Sicher aktiv
* GPS-Verbindung
* Sicherheitskontakt hinterlegt
* Treffen verlängern
* Sicher beenden

Der SOS-Button ist nicht permanent dominant, aber jederzeit erreichbar.

### Nach dem Treffen

* Treffen abgeschlossen
* Zahlung freigegeben
* Bewertung abgeben
* Problem melden

## 12. Nachrichtenbereich

Der Chat ähnelt modernen Messenger-Apps.

Funktionen:

* Pay-to-Chat
* verschwindende Medien
* gesperrte Screenshots, soweit technisch möglich
* Medien mit Preis versehen
* Produkt direkt im Chat anbieten
* Safe-Meet-Anfrage senden
* Wunschauftrag erstellen
* Nutzer melden oder blockieren

Kostenpflichtige Nachrichten werden mit einem kleinen Wallet-Symbol gekennzeichnet.

## 13. Wallet

Der Wallet-Bereich wirkt bewusst wie eine Banking-App.

### Übersicht

* verfügbares Guthaben
* reserviertes Guthaben
* offene Escrow-Zahlungen
* letzte Transaktionen

### Aktionen

* Guthaben aufladen
* Auszahlung
* Transaktionen ansehen
* Zahlungsmethoden
* Sicherheitsprüfung

Zahlungen und Transaktionen erhalten neutrale Bezeichnungen.

## 14. Animationen und Interaktionen

Animationen sollten zurückhaltend eingesetzt werden.

Geeignet sind:

* weiches Einblenden von Karten
* dezenter Glow bei aktiven Buttons
* animierter Verifizierungsstatus
* langsame Gradient-Bewegung
* leichte Haptik bei Wallet-Aktionen
* Fortschrittsanimationen beim KYC
* pulsierender Sicherheitsstatus beim Safe-Meet

Keine übertriebenen Neon-Effekte oder schnellen Animationen.

## 15. Bildsprache

Die Plattform sollte keine typische Erotik-Werbung verwenden.

Stattdessen:

* hochwertige Detailaufnahmen
* dunkle Lifestyle-Fotografie
* Silhouetten
* Stoffe und Texturen
* moderne Wohnungen
* Smartphone-Nutzung
* Verpackung und Versand
* diskrete Porträts
* selbstbewusste Creator

Fotos sollten eher an Mode-, Beauty- oder Premium-Lifestyle-Kampagnen erinnern.

## 16. Mobile-First-Konzept

Da die Plattform hauptsächlich als PWA verwendet wird, wird zuerst für Smartphones gestaltet.

Wichtige Regeln:

* große Touch-Flächen
* Navigation am unteren Bildschirmrand
* schnelle Ladezeiten
* einhändige Bedienung
* klare Sicherheitsanzeigen
* keine langen Textblöcke
* einfache Checkout-Schritte
* jederzeit sichtbarer Wallet-Status
* diskrete Push-Nachrichten

Beispiele für neutrale Push-Nachrichten:

> „Du hast eine neue Nachricht."
> „Deine Bestellung wurde aktualisiert."
> „Dein Termin beginnt bald."

Nicht verwenden:

> „Dein Slip wurde bestellt."
> „Neue erotische Nachricht."

## 17. Komponenten-System

Die gesamte Webseite sollte aus wiederverwendbaren Komponenten bestehen.

### Buttons

* Primary: Blau-Violett-Verlauf
* Secondary: Dunkle Fläche mit Rand
* Danger: Rot
* Ghost: transparent

### Karten

* abgerundete Ecken
* dunkle Oberfläche
* feiner Rand
* leichter Schatten
* optionaler Glow

### Badges

* Verifiziert
* Neu
* Online
* Premium
* Safe-Meet
* Diskreter Versand

### Formulare

* dunkle Eingabefelder
* klare Labels
* sichtbare Validierung
* Passwortanzeige
* Sicherheitsstatus
* Fortschrittsanzeige

## 18. Tonalität der Plattform

Die Texte sollten selbstbewusst, respektvoll und klar wirken. Nicht billig oder anzüglich.

Beispiele:

> Entdecke Creator, die zu dir passen.
> Bestimme selbst, wer deine Inhalte sieht.
> Sichere Zahlungen. Klare Regeln. Volle Kontrolle.
> Persönliche Wünsche, professionell umgesetzt.
> Diskretion beginnt beim Design.

## 19. Zentrale Designbotschaft

MeinSlip.de sollte nicht wie eine Erotikseite aussehen.

Die Plattform sollte aussehen wie eine seriöse Mischung aus:

* Patreon
* Vinted
* Revolut
* Bumble
* einer modernen Creator-App

Der Adult-Bereich ist eine Funktion der Plattform, aber nicht ihre sichtbare Designidentität.

Die sichtbare Identität lautet:

**Diskrete Verbindungen. Sichere Transaktionen. Volle Selbstbestimmung.**
