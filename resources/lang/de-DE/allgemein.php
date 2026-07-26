<?php

declare(strict_types=1);

/**
 * Allgemeine Texte.
 *
 * Tonalitaet nach docs/09-design-system.md: selbstbewusst, respektvoll, klar.
 * Nicht anzueglich, nicht verschaemt.
 */
return [
    'marke' => 'MeinSlip',
    'marke_lang' => 'MeinSlip.de',

    // Startseite
    'hero_marke' => 'Diskretion beginnt beim Design',
    'hero_titel_1' => 'Dein Raum.',
    'hero_titel_2' => 'Deine Regeln.',
    'hero_unterzeile' => 'Eine diskrete Plattform für exklusive Inhalte, persönliche Wünsche und sichere Verbindungen.',
    'hero_entdecken' => 'Profile entdecken',
    'hero_creator' => 'Als Creator starten',

    // Navigation
    'nav_entdecken' => 'Entdecken',
    'nav_sicherheit' => 'Sicherheit',
    'nav_fuer_creator' => 'Für Creator',
    'nav_anmelden' => 'Anmelden',
    'nav_abmelden' => 'Abmelden',
    'nav_registrieren' => 'Konto erstellen',
    'nav_start' => 'Start',
    'nav_nachrichten' => 'Nachrichten',
    'nav_wallet' => 'Guthaben',
    'nav_profil' => 'Profil',
    'nav_haupt' => 'Hauptnavigation',
    'nav_bereiche' => 'Hauptbereiche',
    'nav_verkaufen' => 'Verkaufen',
    'nav_verwaltung' => 'Verwaltung',
    // Eigener, kürzerer Text nur für die untere Leiste. Dort hat jeder der
    // fünf Plätze auf einem 360-px-Telefon rund 68 px; 'Konto erstellen' aus
    // nav_registrieren bräuchte etwa das Anderthalbfache und bräche um.
    'nav_registrieren_kurz' => 'Konto',
    // Nur für Sprachausgaben. Sie liest die Ziffer im Abzeichen nicht mit,
    // weil '3' neben 'Nachrichten' als zwei zusammenhanglose Wörter ankäme.
    'nav_ungelesen' => ':anzahl ungelesen',

    // Merkmale
    'merkmal_geprueft' => 'Geprüfte Identitäten',
    'merkmal_treuhand' => 'Geld erst nach Erhalt',
    'merkmal_versand' => 'Anonymer Versand',
    'merkmal_werbefrei' => 'Werbefrei',

    // Telefonvorschau im Hero — eine gezeichnete Beispielansicht der App.
    // Sie ist als Illustration gekennzeichnet und zeigt nur Bausteine, die es
    // gibt: Treuhand, Empfehlungen, Nachrichten, quittierte Übergaben.
    'vorschau_marke' => 'Beispielansicht',
    'vorschau_gruss' => 'Guten Abend',
    'vorschau_name' => 'Alex',
    'vorschau_treuhand_kicker' => 'Treuhand',
    'vorschau_treuhand_betrag' => '42,00 €',
    'vorschau_treuhand_text' => 'hinterlegt bis zur Bestätigung',
    'vorschau_empfohlen' => 'Empfehlungen',
    'vorschau_creator_1' => 'Lina',
    'vorschau_creator_1_region' => 'Region Köln',
    'vorschau_creator_2' => 'Mira',
    'vorschau_creator_2_region' => 'Region Hamburg',
    'vorschau_nachrichten' => 'Nachrichten',
    'vorschau_nachricht_text' => 'Übergabe bestätigt für Mittwoch.',
    'vorschau_termin' => 'Übergabe · Mi 18:30',
    'vorschau_termin_status' => 'beidseitig quittiert',

    // Abschnitte
    'abschnitt_wege_kicker' => 'Drei Bereiche. Ein Konto.',
    'abschnitt_wege' => 'Drei Wege, eine Plattform',
    'abschnitt_wege_titel_1' => 'Drei Bereiche.',
    'abschnitt_wege_titel_2' => 'Ein Konto.',
    'abschnitt_wege_neben' => 'Alles läuft über dasselbe Konto, dieselbe Identitätsprüfung und dieselben Regeln.',
    'abschnitt_versprechen_kicker' => 'Wofür wir stehen',
    'abschnitt_versprechen' => 'Unsere Versprechen',
    'abschnitt_sicherheit_kicker' => 'Sicherheit',
    'abschnitt_sicherheit' => 'Sicherheit ist kein Zusatz. Sie ist das System.',
    'abschnitt_sicherheit_titel_1' => 'Sicherheit ist kein Zusatz.',
    'abschnitt_sicherheit_titel_2' => 'Sie ist das System.',
    'abschnitt_sicherheit_status' => 'Fest eingebaut, nicht zuschaltbar',

    // Bereiche
    'bereich_content_kicker' => 'Inhalte',
    'bereich_content_titel' => 'Content',
    'bereich_content_text' => 'Exklusive Beiträge, private Inhalte und direkte Nachrichten — mit klarer Angabe, wer schreibt.',
    'bereich_content_tag_1' => 'Angebote',
    'bereich_content_tag_2' => 'Private Inhalte',
    'bereich_content_tag_3' => 'Direkter Kontakt',
    'bereich_markt_kicker' => 'Marktplatz',
    'bereich_markt_titel' => 'Marketplace',
    'bereich_markt_text' => 'Produkte, die eigens für dich angefertigt werden. Anonym versendet, in beide Richtungen.',
    'bereich_markt_tag_1' => 'Konfigurator',
    'bereich_markt_tag_2' => 'Anonymer Versand',
    'bereich_markt_tag_3' => 'Personalisiert',
    'bereich_uebergabe_kicker' => 'Übergabe',
    'bereich_uebergabe_titel' => 'Sichere Übergabe',
    'bereich_uebergabe_text' => 'Persönliche Warenübergabe, beidseitig quittiert — mit hinterlegtem Betrag und klaren Regeln für beide Seiten.',
    'bereich_uebergabe_tag_1' => 'Beidseitige Quittung',
    'bereich_uebergabe_tag_2' => 'Region statt Adresse',
    'bereich_uebergabe_tag_3' => 'Treuhand',

    // Vertrauensversprechen
    'vertrauen_diskret_titel' => 'Diskret',
    'vertrauen_diskret_text' => 'Neutrale Darstellung, geschützte Daten, unauffällige Nutzung — bis hin zum Namen auf dem Homescreen.',
    'vertrauen_sicher_titel' => 'Sicher',
    'vertrauen_sicher_text' => 'Geprüfte Identitäten, hinterlegte Zahlungen und kontrollierte Übergaben.',
    'vertrauen_direkt_titel' => 'Direkt',
    'vertrauen_direkt_text' => 'Ohne Werbung, ohne Agentur dazwischen, ohne Umwege.',

    // Creator-Abschnitt
    'creator_kicker' => 'Für Creator',
    'creator_titel' => 'Baue deine eigene Community auf.',
    'creator_punkt_1' => 'Eigene Preise festlegen',
    'creator_punkt_2' => 'Angebote selbst gestalten',
    'creator_punkt_3' => 'Anfragen persönlich beantworten',
    'creator_punkt_4' => 'Übergaben sicher vereinbaren',
    'creator_punkt_5' => 'Pseudonym bleiben',
    'creator_punkt_6' => 'Sichtbarkeit selbst bestimmen',
    'creator_knopf' => 'Creator werden',
    'creator_mehr' => 'Konditionen ansehen',
    'creator_karte_kicker' => 'So startest du',
    'creator_karte_titel' => 'Drei Schritte bis zum ersten Angebot',
    'creator_schritt_1_titel' => 'Identität prüfen lassen',
    'creator_schritt_1_text' => 'Einmalig, bevor das erste Angebot sichtbar wird.',
    'creator_schritt_2_titel' => 'Angebot anlegen',
    'creator_schritt_2_text' => 'Preis, Optionen und Lieferweg bestimmst du.',
    'creator_schritt_3_titel' => 'Verkaufen über Treuhand',
    'creator_schritt_3_text' => 'Der Betrag ist hinterlegt, bevor du lieferst.',
    'creator_karte_marke' => 'Pseudonym nach außen',

    // Sicherheitsmodule
    'sicher_identitaet_titel' => 'Geprüfte Identität',
    'sicher_identitaet_text' => 'Hinter jedem Verkaufsprofil steht eine geprüfte reale Person. Ohne bestandene Prüfung gibt es keine Auszahlung.',
    'sicher_identitaet_status' => 'vor jedem Verkauf',
    'sicher_treuhand_titel' => 'Geld erst nach Erhalt',
    'sicher_treuhand_text' => 'Dein Betrag wird hinterlegt und erst freigegeben, wenn du die Ware hast und deine Prüfzeit abgelaufen ist.',
    'sicher_treuhand_status' => 'bei jeder Bestellung',
    'sicher_versand_titel' => 'Anonymer Versand',
    'sicher_versand_text' => 'Weder Käufer noch Verkäuferin sehen die Adresse der anderen Seite. Das Etikett entsteht bei uns.',
    'sicher_versand_status' => 'in beide Richtungen',
    'sicher_leak_titel' => 'Rückverfolgbare Inhalte',
    'sicher_leak_text' => 'Jedes ausgelieferte Bild trägt ein unsichtbares Merkmal. Taucht es woanders auf, lässt sich die Quelle bestimmen.',
    'sicher_leak_status' => 'in jedem Bild',

    // Abschluss
    'abschluss_titel' => 'Bereit für eine Plattform, die zu deinen Regeln passt?',
    'abschluss_unterzeile' => 'Sichere Zahlungen. Klare Regeln. Volle Kontrolle.',
    'abschluss_registrieren' => 'Kostenlos registrieren',
    'abschluss_creator' => 'Creator werden',

    // Bedienelemente
    'weiter' => 'Weiter',
    'zurueck' => 'Zurück',
    'abbrechen' => 'Abbrechen',
    'speichern' => 'Speichern',
    'schliessen' => 'Schließen',
    'zum_inhalt' => 'Zum Inhalt springen',

    // Diskretion
    'schnellverbergen' => 'Schnell verbergen',
    'vorhang_titel' => 'Bildschirm gesperrt',
    'vorhang_text' => 'Zum Fortfahren tippen',
    'vorhang_ersatztitel' => 'Neuer Tab',

    // Fußzeile
    'fuss_hinweis_alter' => '18+',
    'fuss_hinweis_diskret' => 'Diskrete Nutzung',
    'fuss_hinweis_verifiziert' => 'Geprüfte Konten',
    'fuss_hinweis_werbefrei' => 'Werbefrei',
    'fuss_impressum' => 'Impressum',
    'fuss_datenschutz' => 'Datenschutz',
    'fuss_agb' => 'AGB',
    'fuss_widerruf' => 'Widerruf',
    'fuss_kuendigen' => 'Verträge kündigen',

    // Rechtstexte
    'rechtstext_entwurf' => 'Dieser Text ist ein Platzhalter und noch nicht anwaltlich geprüft. '
        . 'Vor einem öffentlichen Betrieb muss er durch eine geprüfte Fassung ersetzt werden.',

    // Offline
    'offline_titel' => 'Keine Verbindung',
    'offline_text' => 'Sobald du wieder online bist, geht es hier weiter.',

    // Fehler
    'fehler_nicht_gefunden' => 'Diese Seite gibt es nicht.',
    'fehler_allgemein' => 'Da ist etwas schiefgelaufen.',
    'fehler_keine_berechtigung' => 'Dafür fehlt dir die Berechtigung.',
];
