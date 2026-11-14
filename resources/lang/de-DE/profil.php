<?php

declare(strict_types=1);

/**
 * Texte des Profils.
 *
 * Der Schwerpunkt liegt auf den Entscheidungen der Verwaltung. Art. 17 Abs. 1
 * DSA verlangt, dass die betroffene Person die Begruendung erhaelt — die Texte
 * hier muessen deshalb erklaeren, was passiert ist und was sie dagegen tun
 * kann, ohne zu beschoenigen und ohne zu drohen.
 *
 * Die Schluessel unter 'art' entsprechen den Handlungskonstanten in
 * MeinSlip\Domain\Admin\Verwaltung. Kommt dort eine hinzu, gehoert sie auch
 * hierher — sonst steht auf der Seite '[[profil.art.x]]'. ProfilTest haelt das
 * fest.
 */

return [
    'titel' => 'Dein Profil',
    'kicker' => 'Dein Konto',

    'entscheidungen_kicker' => 'Entscheidungen über dein Konto',
    'entscheidungen_titel' => 'Was wir entschieden haben',
    'entscheidungen_erklaerung' => 'Jede Einschränkung deines Kontos steht hier mit ihrer '
        . 'Begründung. Wir sind verpflichtet, sie dir mitzuteilen, und wir halten fest, '
        . 'wann du sie gelesen hast.',

    'keine_titel' => 'Nichts eingeschränkt',
    'keine_text' => 'Es gibt keine Entscheidung über dein Konto. Sollte sich das ändern, '
        . 'steht die Begründung hier.',

    'gestoert' => 'Die Entscheidungen lassen sich gerade nicht laden. Bitte versuche es später noch einmal.',

    'begruendung_ueberschrift' => 'Begründung',
    'entschieden_am' => 'Entschieden am',
    'gelesen_am' => 'Von dir gelesen am',
    'als_gelesen' => 'Gelesen',

    'widerspruch' => 'Du hältst das für falsch? Wende dich an uns —',
    'widerspruch_weg' => 'Kontaktdaten im Impressum',

    // Flach mit Punkt im Schlüssel, nicht als verschachteltes Array:
    // Lang::ausDatei() schlägt genau einen Schlüssel nach und steigt nicht in
    // Unterarrays ab. Ein 'art' => [...] hier hieße '[[profil.art.x]]' auf der
    // Seite — genau dort, wo eine Begründung stehen soll.
    'art.faehigkeit_entzogen' => 'Eine Berechtigung wurde entzogen',
    'art.faehigkeit_freigeschaltet' => 'Eine Berechtigung wurde freigeschaltet',
    'art.konto_gesperrt' => 'Dein Konto wurde gesperrt',
    'art.konto_entsperrt' => 'Dein Konto wurde entsperrt',
    'art.angebot_abgelehnt' => 'Ein Angebot wurde abgelehnt',
    'art.meldung_bearbeitet' => 'Eine Meldung wurde bearbeitet',

    // Die Nachmoderation. 'angebot_gesperrt' ist eine Beschraenkung und wird
    // zugestellt — dieser Text steht dann als Ueberschrift ueber der
    // Begruendung nach Art. 17 Abs. 1 DSA.
    'art.angebot_gesperrt' => 'Ein Angebot wurde gesperrt',
    // 'angebot_entsperrt' wird ABSICHTLICH nicht zugestellt: Das Aufheben
    // einer Sperre beschraenkt niemanden. Der Text steht trotzdem hier, weil
    // ProfilTest jede HANDLUNG_-Konstante aus Verwaltung prueft — und weil ein
    // fehlender Text sonst erst dann auffiele, wenn doch einmal zugestellt
    // wird und die betroffene Person '[[profil.art.angebot_entsperrt]]' liest.
    'art.angebot_entsperrt' => 'Die Sperre eines Angebots wurde aufgehoben',

    // Die manuelle Identitätsprüfung. BEIDE Richtungen werden zugestellt, und
    // zwar aus verschiedenen Gründen: Die Ablehnung ist eine Beschränkung, die
    // Freigabe eine Auskunft über die eigenen Daten — mit der Entscheidung
    // beginnt die Frist, nach der das eingereichte Foto gelöscht wird. Wer
    // davon nichts erfährt, erfährt nie, dass sein Bild einmal da war.
    //
    // Kein Wort über das Alter: Anerkannt wird ein Identitätsbeleg. Wer hier
    // „altersgeprüft" hinschreibt, macht aus einem handgeschriebenen Zettel
    // eine Behauptung nach § 4 Abs. 2 JMStV, die er nicht trägt.
    'art.beleg_freigegeben' => 'Dein Identitätsbeleg wurde anerkannt',
    'art.beleg_abgelehnt' => 'Dein Identitätsbeleg wurde abgelehnt',

    // Kein Verwaltungsvorgang, sondern die Empfangsbestaetigung nach Art. 16
    // Abs. 4 DSA: Wer meldet, bekommt eine Zeile, die belegt, dass die Meldung
    // angekommen ist. Sie steht in derselben Liste, weil sie ueber denselben
    // Weg zugestellt wird.
    'art.meldung_eingegangen' => 'Deine Meldung ist eingegangen',

    'weg_entdecken' => 'Zum Katalog',

    // --- Kopf und Reiter ---------------------------------------------------
    'status_oeffentlich' => 'Öffentlich',
    'status_privat' => 'Privat',
    'reiter_label' => 'Profilbereich',
    'reiter.uebersicht' => 'Übersicht',
    'reiter.bearbeiten' => 'Profil',
    'reiter.links' => 'Links',
    'reiter.einstellungen' => 'Einstellungen',

    // --- Übersicht ---------------------------------------------------------
    'teilen_titel' => 'Deine Seite zum Teilen',
    'teilen_text' => 'Unter dieser Adresse findet man dein Profil, deine Links und deine Angebote. '
        . 'Gib sie weiter, wo du magst.',
    'teilen_text_privat' => 'Dein Profil ist privat. Schalte es öffentlich, dann bekommt es eine '
        . 'eigene Adresse, die du teilen kannst.',
    'teilen_ansehen' => 'Seite ansehen',
    'teilen_knopf' => 'Teilen',
    'teilen_kopiert' => 'Adresse kopiert',
    'teilen_einschalten' => 'Öffentlich schalten',
    'zahl_angebote' => 'aktive Angebote',
    'zahl_links' => 'Links',
    'zahl_nachrichten' => 'Nachrichten',
    'schritte_titel' => 'Mach dein Profil fertig',
    'schritte_stand' => ':erledigt von :gesamt erledigt',
    'schritt.bild' => 'Profilbild hochladen',
    'schritt.vorstellung' => 'Ein paar Sätze über dich schreiben',
    'schritt.links' => 'Den ersten Link hinzufügen',
    'schritt.oeffentlich' => 'Profil öffentlich schalten',

    // --- Profil bearbeiten -------------------------------------------------
    'bild_titel' => 'Profilbild',
    'bild_regel' => 'Jeder kann es sehen. Deshalb jugendfrei: keine nackte Haut, nichts Sexuelles. '
        . 'JPEG, PNG oder WebP.',
    'bild_feld' => 'Bilddatei',
    'bild_hochladen' => 'Hochladen',
    'bild_entfernen' => 'Bild entfernen',
    'bild_ausschnitt' => 'Ausschnitt ändern',

    // --- Ausschnitt wählen -------------------------------------------------
    'ausschnitt_titel' => 'Ausschnitt wählen',
    'ausschnitt_kicker' => 'Profilbild',
    'ausschnitt_text' => 'Schieb das Bild mit dem Finger zurecht und stell ein, wie nah es sein '
        . 'soll. Nur der Kreis ist später zu sehen — der Rest des Fotos bleibt bei dir.',
    'ausschnitt_vorschau' => 'Vorschau des Ausschnitts',
    'ausschnitt_zoom' => 'Größe',
    'ausschnitt_x' => 'Links – rechts',
    'ausschnitt_y' => 'Oben – unten',
    'ausschnitt_speichern' => 'Ausschnitt übernehmen',
    'ausschnitt_abbrechen' => 'So lassen',
    'bild_alt' => 'Profilbild von :name',
    'feld_anzeigename' => 'Anzeigename',
    'hinweis_anzeigename' => 'So heißt du auf deiner Seite. Leer gelassen, steht dort dein Pseudonym.',
    'feld_vorstellung' => 'Über dich',
    'hinweis_vorstellung' => 'Bis zu 2000 Zeichen. Zeilenumbrüche bleiben erhalten.',
    'speichern' => 'Speichern',

    // --- Links -------------------------------------------------------------
    'links_titel' => 'Deine Links',
    'links_erklaerung' => 'Bis zu :anzahl Links erscheinen als Knöpfe auf deiner Seite — etwa zu '
        . 'deinen anderen Profilen oder deiner Wunschliste. Die Reihenfolge bestimmst du.',
    'links_leer' => 'Noch kein Link. Füg unten den ersten hinzu.',
    'link_neu_titel' => 'Link hinzufügen',
    'link_feld_titel' => 'Beschriftung',
    'link_feld_adresse' => 'Adresse',
    'link_hinweis_adresse' => 'Nur verschlüsselte Adressen (https). Unter der Beschriftung zeigen '
        . 'wir an, wohin der Link führt.',
    'link_hinzufuegen' => 'Hinzufügen',
    'link_entfernen' => 'Entfernen',
    'link_hoch' => 'Nach oben',
    'link_runter' => 'Nach unten',

    // --- Einstellungen -----------------------------------------------------
    'sichtbar_titel' => 'Sichtbarkeit',
    'sichtbar_feld' => 'Profil öffentlich zeigen',
    'sichtbar_erklaerung' => 'Dann hat dein Profil eine eigene Adresse. Wer sie kennt, sieht Bild, '
        . 'Vorstellung, Links und Angebote. Suchmaschinen nehmen die Seite nicht auf.',
    'chat_titel' => 'Wer deine Nachrichten schreibt',
    'chat_offen' => 'Noch nicht angegeben. Ohne diese Angabe kannst du nicht schreiben.',
    'chat_aendern' => 'Ändern',
    'pruefung_weg' => 'Zur Prüfung',
    'abmelden_titel' => 'Abmelden',
    'abmelden_text' => 'Beendet die Anmeldung auf diesem Gerät.',

    // --- Rückmeldungen (ProfilRouten::MELDUNGEN) ---------------------------
    'meldung.gespeichert' => 'Gespeichert.',
    'meldung.bild_gespeichert' => 'Dein Profilbild ist da.',
    'meldung.bild_entfernt' => 'Das Profilbild ist entfernt.',
    'meldung.ausschnitt_gespeichert' => 'Der Ausschnitt ist übernommen.',
    'meldung.kein_original' => 'Zu diesem Profilbild gibt es kein ganzes Foto mehr. Lade es noch '
        . 'einmal hoch, dann kannst du den Ausschnitt wählen.',
    'meldung.link_gespeichert' => 'Der Link steht auf deiner Seite.',
    'meldung.link_entfernt' => 'Der Link ist entfernt.',
    'meldung.anzeigename_zu_lang' => 'Der Anzeigename darf höchstens 60 Zeichen haben.',
    'meldung.vorstellung_zu_lang' => 'Der Text über dich darf höchstens 2000 Zeichen haben.',
    'meldung.link_titel_fehlt' => 'Der Link braucht eine Beschriftung.',
    'meldung.link_titel_zu_lang' => 'Die Beschriftung darf höchstens 60 Zeichen haben.',
    'meldung.link_adresse_ungueltig' => 'Diese Adresse nehmen wir nicht an. Sie muss mit https:// '
        . 'beginnen und auf eine normale Webseite zeigen.',
    'meldung.link_zu_viele' => 'Mehr als :anzahl Links gehen nicht. Entferne erst einen.',
    'meldung.keine_datei' => 'Es ist keine Datei angekommen. Vielleicht war sie zu groß.',
    'meldung.gestoert' => 'Das hat gerade nicht geklappt. Bitte versuche es später noch einmal.',
    'meldung.unbestaetigt' => 'Bitte bestätige mit dem Häkchen, dass du dein Konto löschen willst.',
    'meldung.passwort_falsch' => 'Das Passwort stimmt nicht. Dein Konto besteht unverändert.',
    'meldung.bestellung_offen' => 'Du hast noch eine laufende Bestellung. Sobald sie abgeschlossen '
        . 'ist, kannst du dein Konto löschen.',
    'meldung.verwalter_bleibt' => 'Dieses Konto hat Zugang zur Verwaltung. Der muss erst entzogen werden.',
    'meldung.zu_viele_versuche' => 'Das waren zu viele falsche Passwörter. Bitte warte eine Viertelstunde.',

    // --- Öffentliche Seite -------------------------------------------------
    'oeffentlich_unbekannt_titel' => 'Dieses Profil gibt es nicht',
    'oeffentlich_unbekannt_text' => 'Entweder stimmt die Adresse nicht, oder das Profil ist nicht '
        . 'öffentlich.',
    'oeffentlich_bearbeiten' => 'Profil bearbeiten',
    'oeffentlich_nachricht' => 'Nachricht schreiben',
    'oeffentlich_nachricht_anmelden' => 'Anmelden und schreiben',
    'oeffentlich_angebote_kicker' => 'Aktuell',
    'oeffentlich_angebote_titel' => 'Neueste Angebote',
    'oeffentlich_melden' => 'Profil melden',

    // --- Konto schliessen (Art. 17 DSGVO) ----------------------------------
    // Beide Saetze beschreiben, was Kontoschliessung::schliessen() tut. Wer
    // dort etwas aendert, aendert es hier und in datenschutz.php.
    'loeschen_kicker' => 'Endgültig',
    'loeschen_titel' => 'Konto löschen',
    'loeschen_was_geht' => 'Wir entfernen dein Profil, deine Angebote und Bilder, das Foto deiner '
        . 'Identitätsprüfung, deine Sperrliste und alle Anmeldungen. Pseudonym und E-Mail-Adresse '
        . 'werden überschrieben. Das lässt sich nicht rückgängig machen.',
    'loeschen_was_bleibt' => 'Nachrichten, die du anderen geschrieben hast, bleiben bei ihnen '
        . 'lesbar — ohne deinen Namen. Meldungen und Entscheidungen der Moderation bewahren wir '
        . 'auf, weil wir dazu verpflichtet sind.',
    'loeschen_passwort' => 'Dein Passwort zur Bestätigung',
    'loeschen_bestaetigen' => 'Ich will mein Konto endgültig löschen.',
    'loeschen_knopf' => 'Konto löschen',
];
