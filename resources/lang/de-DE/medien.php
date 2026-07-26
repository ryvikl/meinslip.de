<?php

declare(strict_types=1);

/**
 * Texte rund um Bilder: Hochladen, Anzeigen, Sperren.
 *
 * Flach mit Punkt, wie alle Sprachdateien dieses Projekts. Die
 * Fehlerschluessel unter 'fehler.' sind woertlich die aus
 * MedienFehler::schluessel() plus die eigenen von MedienRouten — jeder einzelne
 * muss hier stehen, sonst liest die Verkaeuferin '[[medien.fehler.x]]' genau in
 * dem Moment, in dem ihr Upload gescheitert ist. tests/MedienZugriffTest.php
 * haelt das nach, weil der zusammengesetzte Schluessel te('medien.fehler.' . $x)
 * von UebersetzungenTest nicht erfasst wird.
 *
 * DIE FEHLERTEXTE SAGEN, WAS ZU TUN IST, nicht was schiefging. 'Die
 * Dekodierung ist fehlgeschlagen' ist fuer die Person, die gerade ein Foto
 * hochladen wollte, keine Auskunft, sondern eine Beleidigung. Der technische
 * Grund steht im Protokoll — hier steht der naechste Schritt.
 */

return [
    // --- Hochladeformular -------------------------------------------------
    'titel' => 'Bilder',
    'kicker' => 'Zeigen, was du anbietest',
    'unterzeile' => 'Höchstens :anzahl Bilder je Angebot. Jedes Bild wird beim Hochladen neu berechnet — Aufnahmeort, Kameradaten und Uhrzeit verschwinden dabei.',
    'feld_datei' => 'Bilddatei',
    'hinweis_datei' => 'JPEG, PNG oder WebP, höchstens :mb MB. Größere Bilder werden auf :kante Pixel verkleinert.',
    'hochladen' => 'Bild hinzufügen',
    'entfernen' => 'Bild entfernen',
    'leer' => 'Zu diesem Angebot gibt es noch kein Bild.',
    'voll' => 'Dieses Angebot hat die Höchstzahl von :anzahl Bildern erreicht. Entferne erst eines.',
    'liste_titel' => 'Vorhandene Bilder',
    // Eigener Kicker für die Angebotsseite: 'kicker' spricht die Verkäuferin
    // an ("Zeigen, was du anbietest") und wäre dort die falsche Stimme.
    'galerie_kicker' => 'Ansehen',
    'bild_alt' => 'Bild zum Angebot',
    'vorschau_alt' => 'Unscharfe Vorschau zum Angebot',
    'nummer' => 'Bild :nummer',

    // --- Die Altersfrage --------------------------------------------------
    // Ausgeschriebene Folge statt eines Fachworts: Wer hier "explizit"
    // ankreuzt, ohne zu wissen, was danach passiert, ist getäuscht worden.
    'explizit_frage' => 'Ist auf diesem Bild nackte Haut oder ein sexueller Zusammenhang zu sehen?',
    'explizit_nein' => 'Nein, jugendfrei — das Bild wird allen gezeigt.',
    'explizit_ja' => 'Ja, nicht jugendfrei — dann sehen es andere nicht, bis eine Altersprüfung angebunden ist. Nur du und die Verwaltung sehen es.',
    'explizit_pflicht' => 'Diese Frage musst du beantworten. Ohne Antwort behandeln wir das Bild als nicht jugendfrei.',
    'explizit_marke' => 'Nicht jugendfrei',

    // --- Gesperrte Darstellung -------------------------------------------
    'gesperrt_schloss' => 'Nicht sichtbar',
    'gesperrt_fremd' => 'Dieses Bild ist als nicht jugendfrei gekennzeichnet. Wir zeigen es nicht — auch nicht unscharf —, solange kein geprüftes Altersverfahren angebunden ist. Eine Selbstauskunft genügt dafür nicht.',
    'gesperrt_eigen' => 'Dieses Bild ist als nicht jugendfrei gekennzeichnet. Außer dir und der Verwaltung sieht es niemand, solange kein geprüftes Altersverfahren angebunden ist.',
    'gesperrt_kachel' => 'Bild nicht jugendfrei',

    // --- Rückmeldungen ----------------------------------------------------
    'erfolg.bild_hinzugefuegt' => 'Das Bild wurde hinzugefügt.',
    'erfolg.bild_entfernt' => 'Das Bild wurde entfernt.',

    // Aus Bilder::annehmen()
    'fehler.animation_nicht_erlaubt' => 'Bewegte Bilder gehen noch nicht. Nimm ein Einzelbild.',
    'fehler.bildverarbeitung_fehlt' => 'Auf diesem Server fehlt die Bildverarbeitung. Bitte melde dich bei uns — hochladen kann gerade niemand.',
    'fehler.datei_zu_gross' => 'Die Datei ist größer als :mb MB. Verkleinere sie oder nimm ein anderes Bild.',
    'fehler.dekodierung_fehlgeschlagen' => 'Diese Datei lässt sich nicht als Bild lesen. Speichere sie noch einmal als JPEG und versuche es erneut.',
    'fehler.format_nicht_erlaubt' => 'Dieses Format nehmen wir nicht an. Erlaubt sind JPEG, PNG und WebP.',
    'fehler.format_nicht_unterstuetzt' => 'Dieses Format kann der Server nicht lesen. Speichere das Bild als JPEG.',
    'fehler.kein_bild' => 'In der Datei steckt kein Bild. Hast du versehentlich etwas anderes ausgewählt?',
    'fehler.nicht_hochgeladen' => 'Der Upload ist unterwegs verloren gegangen. Bitte versuche es noch einmal.',
    'fehler.speicher_reicht_nicht' => 'Das Bild hat zu viele Bildpunkte für diesen Server. Verkleinere es und lade es erneut hoch.',
    'fehler.speichern_fehlgeschlagen' => 'Das Bild ließ sich nicht ablegen. Bitte versuche es noch einmal.',
    'fehler.typ_widerspruch' => 'Die Datei gibt sich als etwas aus, das sie nicht ist. Speichere das Bild neu und versuche es erneut.',
    'fehler.upload_fehlgeschlagen' => 'Der Upload ist fehlgeschlagen. Bitte versuche es noch einmal.',
    'fehler.upload_zu_gross' => 'Das Bild ist zu groß für den Server. Verkleinere es auf höchstens :mb MB.',
    'fehler.verarbeitung_fehlgeschlagen' => 'Beim Umrechnen des Bildes ist etwas schiefgegangen. Bitte versuche es noch einmal.',
    'fehler.ziel_unbrauchbar' => 'Der Ablageort für Bilder ist nicht beschreibbar. Bitte melde dich bei uns.',
    'fehler.zu_viele_pixel' => 'Das Bild hat zu viele Bildpunkte. Verkleinere es und lade es erneut hoch.',

    // Aus Medien
    'fehler.angebot_unbekannt' => 'Dieses Angebot gibt es nicht.',
    'fehler.medium_unbekannt' => 'Dieses Bild gibt es nicht.',
    'fehler.zu_viele_bilder' => 'Mehr als :anzahl Bilder gehen nicht. Entferne erst eines.',

    // Aus MedienRouten
    'fehler.keine_datei' => 'Du hast keine Datei ausgewählt.',
    // Der Satz, der die häufigste und rätselhafteste Panne erklärt: Bei
    // überschrittener post_max_size kommt vom Formular gar nichts an.
    'fehler.post_zu_gross' => 'Das Bild war zu groß, um überhaupt anzukommen — der Server hat die Übertragung abgebrochen. Verkleinere es auf höchstens :mb MB und versuche es noch einmal.',
    'fehler.gestoert' => 'Gerade geht das nicht. Bitte versuche es später noch einmal.',
    'fehler.unbekannt' => 'Das Bild wurde nicht hinzugefügt. Bitte versuche es noch einmal.',
];
