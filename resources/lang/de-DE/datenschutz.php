<?php

declare(strict_types=1);

/**
 * Datenschutzerklärung nach Art. 13 DSGVO.
 *
 * JEDE AUSSAGE HIER BESCHREIBT, WAS DER CODE HEUTE TUT. Wer eine Verarbeitung
 * hinzufügt — ein neues Cookie, einen Dienstleister, einen Mailversand —,
 * ändert diese Datei im selben Paket. Belegstellen:
 *
 *  - Sitzung: app/Domain/Account/Sitzungen.php (Hashes, 720 Stunden)
 *  - Versuchszaehler: app/Domain/Trust/Drossel.php (HMAC, hoechstens 1 Stunde)
 *  - Kontoloeschung: app/Domain/Account/Kontoschliessung.php
 *  - Cookies: ms_alter, ms_sitzung, ms_formular, ms_preisstand, ms_melde_takt
 *  - Bilder: app/Domain/Media/Bilder.php (Neuschreiben entfernt EXIF)
 *  - Prüfbeleg: app/Domain/Verification/Pruefbelege.php (30 / 7 Tage)
 *  - Kasse und Guthaben: gesperrt, siehe .env.example
 *
 * NICHT anwaltlich geprüft. OFFEN vor dem öffentlichen Betrieb:
 *  - Datenschutz-Folgenabschätzung (Art. 35 DSGVO) wegen Art.-9-Daten
 *  - Speicherdauer der Server-Protokolle beim Hoster eintragen
 *  - Auftragsverarbeitungsvertrag mit dem Hoster abschließen und ablegen
 */
return [
    'kicker' => 'Datenschutz',
    'titel' => 'Datenschutzerklärung',

    'verantwortlich_titel' => 'Wer verantwortlich ist',
    'verantwortlich' => <<<'TEXT'
        Verantwortlich im Sinne von Art. 4 Nr. 7 DSGVO:

        Chic & Chill GmbH
        Leipziger Straße 90
        04552 Borna
        Deutschland

        Vertreten durch die Geschäftsführung: Robby Klier, Linda Enzmann
        E-Mail: info@chicandchill.de
        Telefon: 03433 859 94 60

        Alle Anliegen zum Datenschutz gehen an diese Adresse. Schreib „Datenschutz MeinSlip“ in den Betreff, dann landet es an der richtigen Stelle.
        TEXT,

    'grundsatz_titel' => 'Das Wichtigste vorweg',
    'grundsatz' => <<<'TEXT'
        Diese Plattform ist nur für Erwachsene. Du nutzt sie unter einem Pseudonym. Deinen bürgerlichen Namen und deine Anschrift fragen wir bei der Registrierung nicht ab.

        - Keine Werbung, keine Werbenetzwerke, keine Profile für Dritte.
        - Keine Analyse- oder Statistikdienste, kein Tracking.
        - Keine eingebundenen Inhalte fremder Server: Schriften, Symbole und Skripte liefern wir selbst aus.
        - Keine Indexierung durch Suchmaschinen und kein Referrer an fremde Seiten.
        TEXT,

    'besondere_titel' => 'Warum hier mehr auf dem Spiel steht',
    'besondere' => <<<'TEXT'
        Schon die Tatsache, dass jemand hier ein Konto hat, ein bestimmtes Angebot einstellt oder eine Kategorie durchsucht, kann etwas über das Sexualleben verraten. Das sind besondere Kategorien personenbezogener Daten nach Art. 9 DSGVO.

        Wir verarbeiten sie nur, soweit du sie selbst einträgst oder veröffentlichst, und nur, um die Plattform zu betreiben. Grundlage ist deine ausdrückliche Einwilligung (Art. 9 Abs. 2 lit. a DSGVO), die du mit der Registrierung und dem Einstellen eigener Inhalte erteilst, und bei allem, was du selbst öffentlich sichtbar machst, zusätzlich Art. 9 Abs. 2 lit. e DSGVO. Die Einwilligung kannst du jederzeit widerrufen, indem du Inhalte löschst oder dein Konto schließen lässt.
        TEXT,

    'hosting_titel' => 'Hosting und Server-Protokolle',
    'hosting' => <<<'TEXT'
        Die Plattform läuft auf Servern in Deutschland bei ALL-INKL.COM – Neue Medien Münnich, Inhaber René Münnich, Hauptstraße 68, 02742 Friedersdorf. Der Hoster verarbeitet die Daten in unserem Auftrag und nach unserer Weisung (Art. 28 DSGVO).

        Bei jedem Abruf übermittelt dein Browser technische Angaben: IP-Adresse, Zeitpunkt, aufgerufene Adresse, übertragene Datenmenge, Statuscode und Browserkennung. Der Server braucht sie, um die Seite auszuliefern, und protokolliert sie kurzzeitig, um Angriffe und Störungen erkennen zu können. Grundlage ist unser berechtigtes Interesse an einem sicheren Betrieb (Art. 6 Abs. 1 lit. f DSGVO). Diese Protokolle führen wir nicht mit deinem Konto zusammen.
        TEXT,

    'konto_titel' => 'Dein Konto',
    'konto' => <<<'TEXT'
        Für die Registrierung brauchen wir ein Pseudonym, eine E-Mail-Adresse und ein Passwort. Das Passwort speichern wir ausschließlich als Hash — wir können es nicht lesen. Dazu kommen deine Sprache, dein Land, der Zeitpunkt der Registrierung und der letzten Aktivität sowie die Einstellungen, die du selbst triffst.

        Wir verarbeiten diese Daten, um den Nutzungsvertrag mit dir zu erfüllen (Art. 6 Abs. 1 lit. b DSGVO). Deine E-Mail-Adresse ist für andere Nutzer:innen nicht sichtbar. Newsletter oder Werbung verschicken wir nicht.
        TEXT,

    'sitzung_titel' => 'Anmeldung',
    'sitzung' => 'Meldest du dich an, legen wir eine Sitzung an. Gespeichert werden dabei nur '
        . 'Prüfsummen (Hashes) deiner Sitzungskennung, deiner Browserkennung und deiner IP-Adresse — '
        . 'nicht die Werte selbst. Sie dienen dazu, eine übernommene Sitzung erkennen zu können '
        . '(Art. 6 Abs. 1 lit. f DSGVO). Eine Sitzung läuft nach 30 Tagen ab oder endet, sobald du '
        . 'dich abmeldest. Damit niemand Passwörter durchprobieren oder massenhaft Konten anlegen '
        . 'kann, zählen wir Fehlversuche bei der Anmeldung, neue Registrierungen und Meldungen ohne '
        . 'Konto — ebenfalls nur als Prüfsumme von E-Mail-Adresse oder IP-Adresse, höchstens eine '
        . 'Stunde lang.',

    'cookies_titel' => 'Cookies und Gerätespeicher',
    'cookies' => <<<'TEXT'
        Wir setzen ausschließlich Cookies, ohne die die Plattform nicht funktioniert. Eine Einwilligung ist dafür nicht nötig (§ 25 Abs. 2 Nr. 2 TDDDG), deshalb siehst du auch kein Cookie-Banner.

        - ms_alter: merkt sich, dass du die Altersfrage mit Ja beantwortet hast, 30 Tage.
        - ms_sitzung: hält dich angemeldet, 30 Tage.
        - ms_formular: schützt Formulare davor, von fremden Seiten abgeschickt zu werden, 30 Tage.
        - ms_preisstand: merkt sich den Preis, der dir vor einer Bestellung angezeigt wurde, bis du den Browser schließt.
        - ms_melde_takt: begrenzt, wie viele Meldungen in kurzer Zeit abgeschickt werden können, für die Dauer des Zeitfensters.

        Installierst du die Plattform als App auf deinem Gerät, legt dein Browser Gestaltung, Schriften und Symbole in einem lokalen Zwischenspeicher ab, damit sie schneller und auch ohne Verbindung startet. Persönliche Inhalte landen dort nicht. Du leerst diesen Speicher über die Einstellungen deines Browsers.
        TEXT,

    'inhalte_titel' => 'Profil, Angebote und Bilder',
    'inhalte' => <<<'TEXT'
        Was du als Angebot einstellst, ist für andere sichtbar — dafür ist es da. Grundlage ist der Nutzungsvertrag (Art. 6 Abs. 1 lit. b DSGVO).

        Dein Profil ist zunächst privat. Schaltest du es öffentlich, bekommt es eine eigene Adresse, die du weitergeben kannst. Wer sie kennt, sieht dort Anzeigenamen, Profilbild, Vorstellung, deine Verweise und deine aktuellen Angebote. Du kannst das jederzeit wieder abschalten. Verweise auf fremde Seiten öffnen sich in einem neuen Fenster; die Zielseite erfährt dabei nicht, dass du von hier kommst, verarbeitet deine Daten aber nach ihren eigenen Regeln.

        Jedes hochgeladene Bild wird von uns neu geschrieben, bevor es gespeichert wird. Dabei fallen alle eingebetteten Zusatzangaben weg: Aufnahmeort, Gerät, Zeitpunkt. Das Original mit diesen Angaben bewahren wir nicht auf.

        Du kannst Angebote, Bilder, Profilbild und Verweise jederzeit entfernen. Sie verschwinden dann sofort.
        TEXT,

    'nachrichten_titel' => 'Nachrichten',
    'nachrichten' => <<<'TEXT'
        Nachrichten speichern wir, damit beide Beteiligten die Unterhaltung nachlesen können (Art. 6 Abs. 1 lit. b DSGVO). Wir lesen nicht mit und werten Unterhaltungen nicht aus.

        Unser Team sieht eine Nachricht nur dann, wenn sie gemeldet wird — und dann nur, soweit es zur Prüfung der Meldung nötig ist. Sperrst du eine Person, kann sie dir nicht mehr schreiben; die Sperre speichern wir, solange sie besteht.
        TEXT,

    'termine_titel' => 'Termine und Übergaben',
    'termine' => 'Verabredest du über die Plattform eine persönliche Übergabe, speichern wir Ort, '
        . 'Zeit, den Stand des Termins und die Bestätigungen beider Seiten. Sichtbar ist das nur für '
        . 'euch beide. Die Angaben dienen der Verabredung selbst und dem Nachweis, falls es später '
        . 'Streit gibt (Art. 6 Abs. 1 lit. b und f DSGVO).',

    'identitaet_titel' => 'Identitätsprüfung',
    'identitaet' => <<<'TEXT'
        Die Prüfung ist freiwillig. Du lädst ein Foto von dir hoch, auf dem du einen Zettel mit einem Code hältst, den wir dir vorher vergeben. Ein Mensch aus unserem Team sieht sich das Foto an und entscheidet.

        Das Foto löschen wir spätestens 30 Tage nach dem Hochladen und spätestens 7 Tage nach der Entscheidung — je nachdem, was früher eintritt. Auf Dauer bleibt nur das Ergebnis: bestanden oder nicht, und wann. Grundlage ist deine Einwilligung (Art. 6 Abs. 1 lit. a, Art. 9 Abs. 2 lit. a DSGVO), die du bis zur Entscheidung widerrufen kannst.

        Ausweisdokumente verlangen und speichern wir nicht.
        TEXT,

    'meldungen_titel' => 'Meldungen und Moderation',
    'meldungen' => <<<'TEXT'
        Meldest du ein Angebot, ein Profil oder eine Nachricht, speichern wir den Inhalt deiner Meldung, den gemeldeten Gegenstand und — wenn du angemeldet bist — dein Konto. Die gemeldete Person erfährt nicht, wer gemeldet hat.

        Greifen wir ein, etwa indem wir ein Angebot sperren oder eine Funktion entziehen, halten wir Maßnahme und Begründung fest und stellen beides der betroffenen Person in ihrem Profil zu. Dazu sind wir nach Art. 16 und 17 des Digital Services Act verpflichtet (Art. 6 Abs. 1 lit. c DSGVO).
        TEXT,

    'zahlung_titel' => 'Bestellungen und Zahlungen',
    'zahlung' => 'Kasse und Guthaben sind derzeit abgeschaltet. Wir verarbeiten deshalb weder '
        . 'Zahlungsdaten noch Lieferanschriften, und es ist kein Zahlungs- oder Versanddienstleister '
        . 'angebunden. Bevor sich das ändert, ergänzen wir diese Erklärung um die beteiligten '
        . 'Dienstleister und die dann verarbeiteten Daten.',

    'empfaenger_titel' => 'Wer deine Daten bekommt',
    'empfaenger' => <<<'TEXT'
        Außer dem Hoster niemand. Wir verkaufen keine Daten und geben sie nicht zu Werbezwecken weiter. Eine Übermittlung in Länder außerhalb der Europäischen Union findet nicht statt.

        Behörden erhalten Auskunft nur, wenn ein Gesetz uns dazu verpflichtet, und nur im verlangten Umfang.

        Entscheidungen, die ausschließlich automatisiert getroffen werden und dich rechtlich betreffen, gibt es bei uns nicht. Über Sperren und Prüfungen entscheidet immer ein Mensch.
        TEXT,

    'dauer_titel' => 'Wie lange wir speichern',
    'dauer' => <<<'TEXT'
        - Kontodaten: solange dein Konto besteht. Du löschst es selbst in deinem Profil. Dabei entfernen wir Profil, Angebote, Bilder, das Foto der Identitätsprüfung und alle Anmeldungen sofort; Pseudonym und E-Mail-Adresse werden überschrieben.
        - Sitzungen: 30 Tage oder bis zur Abmeldung.
        - Fotos der Identitätsprüfung: höchstens 30 Tage, nach der Entscheidung höchstens 7 Tage.
        - Nachrichten und Termine: solange die Unterhaltung besteht. Löschst du dein Konto, bleiben deine Nachrichten für die andere Person lesbar, aber ohne deinen Namen — sie sind auch Teil ihrer Unterhaltung und ihr Beleg, falls sie etwas melden muss.
        - Meldungen und Moderationsentscheidungen: drei Jahre zum Ende des Kalenderjahres, damit wir Entscheidungen belegen und Wiederholungen erkennen können.
        - Geschäftsunterlagen: sobald Bestellungen möglich sind, gelten die handels- und steuerrechtlichen Fristen von bis zu zehn Jahren.
        TEXT,

    'rechte_titel' => 'Deine Rechte',
    'rechte' => <<<'TEXT'
        Du kannst von uns verlangen:

        - Auskunft über die Daten, die wir zu dir gespeichert haben (Art. 15 DSGVO),
        - Berichtigung falscher Daten (Art. 16 DSGVO),
        - Löschung (Art. 17 DSGVO),
        - Einschränkung der Verarbeitung (Art. 18 DSGVO),
        - Herausgabe deiner Daten in einem gängigen Format (Art. 20 DSGVO).

        Eine Einwilligung kannst du jederzeit mit Wirkung für die Zukunft widerrufen. Stützen wir eine Verarbeitung auf unser berechtigtes Interesse, kannst du ihr widersprechen (Art. 21 DSGVO).

        Dein Konto löschst du selbst im Profil unter „Konto löschen“. Für alles andere genügt eine formlose E-Mail an die oben genannte Adresse. Wir antworten innerhalb eines Monats.
        TEXT,

    'beschwerde_titel' => 'Beschwerde bei der Aufsichtsbehörde',
    'beschwerde' => <<<'TEXT'
        Du kannst dich bei jeder Datenschutz-Aufsichtsbehörde beschweren. Für uns zuständig ist:

        Sächsische Datenschutz- und Transparenzbeauftragte
        Maternistraße 17
        01067 Dresden
        TEXT,

    'pflicht_titel' => 'Musst du die Daten angeben?',
    'pflicht' => 'Pseudonym, E-Mail-Adresse und Passwort brauchst du für ein Konto; ohne sie '
        . 'kannst du die Plattform nur ansehen. Alles andere ist freiwillig.',

    'stand' => 'Stand: 14. November 2026. Ändert sich, was wir verarbeiten, passen wir diese '
        . 'Erklärung an.',
];
