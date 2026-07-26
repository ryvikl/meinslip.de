<?php

declare(strict_types=1);

/**
 * Der Chat — Liste, Fenster und die Pflicht-Deklaration.
 *
 * DIE DEKLARATIONSTEXTE SIND KEINE BESCHRIFTUNG, SIE SIND DAS PRODUKT.
 * „Rede ich mit ihr oder mit einem Chatter?" ist laut
 * docs/04-features/chat-monetarisierung.md der größte Vertrauensbruch der
 * Branche und zugleich der einzige Unterschied, den ein Wettbewerber nicht
 * kopieren kann, ohne sein eigenes Geschäft zu beschädigen. Drei Regeln halten
 * diese Texte deshalb ein:
 *
 *  - Sie behaupten nichts über die Person, sondern nur über die Urheberschaft
 *    der Nachrichten. „Sie schreibt selbst" ist eine Aussage über das
 *    Schreiben, kein Echtheitssiegel.
 *  - Sie sagen VOR der Wahl, dass eine falsche Angabe ein Sperrgrund ist.
 *    Danach liest das niemand mehr.
 *  - Sie verharmlosen „KI-unterstützt" nicht. Der Wert steht gleichberechtigt
 *    neben den anderen beiden, ohne entschuldigenden Zusatz — sonst wählt ihn
 *    niemand, und die Deklaration wird zur Dekoration.
 *
 * FLACHE SCHLÜSSEL. Lang::t() zerlegt nur am ERSTEN Punkt: 'deklaration.person'
 * ist ein Schlüssel mit Punkt, kein verschachteltes Array. Ein Unterarray
 * ergäbe sichtbar '[[chat.deklaration.person]]'.
 *
 * PLATZHALTER heißen ':name' und werden per str_replace ersetzt. Kein Name darf
 * Präfix eines anderen sein — deshalb ':gegenueber', ':anzahl', ':zeichen' und
 * ':titel', die einander nicht schlucken können.
 *
 * Zusammengesetzte Schlüssel (te('chat.deklaration.' . $wert),
 * te('chat.fehler.' . $fehler)) prüft UebersetzungenTest NICHT — dafür gibt es
 * tests/ChatTexteTest.php. Wer hier einen Schlüssel entfernt, macht ihn rot.
 */
return [
    // --- Liste --------------------------------------------------------------
    'kicker' => 'Deine Gespräche',
    'titel' => 'Nachrichten',
    'unterzeile' => 'Hier stehen alle Unterhaltungen, die du führst — neueste zuerst.',

    'leer_titel' => 'Noch keine Unterhaltung',
    'leer_text' => 'Sobald du jemanden anschreibst oder angeschrieben wirst, steht das Gespräch hier. '
        . 'Den Anfang machst du auf einer Angebotsseite über „Nachricht schreiben".',
    'leer_knopf' => 'Angebote entdecken',

    'liste_oeffnen' => 'Gespräch öffnen',
    'liste_ungelesen' => ':anzahl ungelesen',
    'liste_ohne_nachricht' => 'Noch keine Nachricht geschrieben.',
    'liste_bezug' => 'Zum Angebot: :titel',
    'liste_bezug_entfallen' => 'Das Angebot dazu gibt es nicht mehr.',
    'liste_seite' => 'Seite :nummer von :seiten',
    'liste_weiter' => 'Weitere Gespräche',
    'liste_zurueck' => 'Vorherige Gespräche',

    // --- Fenster ------------------------------------------------------------
    'fenster_titel' => 'Gespräch mit :gegenueber',
    'fenster_zurueck' => 'Zurück zur Übersicht',

    'unbekannt_titel' => 'Gespräch nicht gefunden',
    'unbekannt_text' => 'Dieses Gespräch gibt es nicht, oder es gehört nicht zu deinem Konto.',

    'verlauf_leer' => 'Hier steht noch nichts. Schreib die erste Nachricht.',
    'verlauf_ueberschrift' => 'Verlauf',

    // Der Kopf des Fensters. Das Label steht dauerhaft hier und nicht als
    // Fußnote im Profil — so verlangt es das Konzeptdokument.
    'kopf_gegenueber' => 'Schreibt dort:',
    'kopf_eigene' => 'Du schreibst als:',
    'kopf_ohne_angabe' => 'Keine Angabe',
    'kopf_erklaerung' => 'Diese Angabe macht jedes Konto selbst. Sie steht bei jeder einzelnen Nachricht '
        . 'so, wie sie beim Schreiben galt — eine spätere Änderung wirkt nicht rückwirkend.',
    'kopf_deklaration_aendern' => 'Eigene Angabe ändern',

    'blase_eigene' => 'Du',
    'blase_gelesen' => 'Gelesen',
    'blase_verborgen' => 'Diese Nachricht wurde nach einer Meldung ausgeblendet.',
    'blase_melden' => 'Melden',

    'senden_feld' => 'Deine Nachricht',
    'senden_hinweis' => 'Höchstens :zeichen Zeichen.',
    'senden_knopf' => 'Senden',

    'sperre_eigene_titel' => 'Du hast diese Person gesperrt',
    'sperre_eigene_text' => 'Solange die Sperre besteht, kann keine von euch beiden der anderen '
        . 'schreiben. Der Verlauf bleibt lesbar — er ist das Beweismittel, wenn du meldest.',
    'sperre_setzen' => 'Person sperren',
    'sperre_aufheben' => 'Sperre aufheben',
    'sperre_erklaerung' => 'Eine Sperre wirkt in beide Richtungen und lässt sich jederzeit wieder aufheben.',

    'bezug_titel' => 'Worum es geht',
    'bezug_angebot' => 'Angebot ansehen',

    // --- Deklaration --------------------------------------------------------
    'deklaration_kicker' => 'Einmalige Angabe',
    'deklaration_titel' => 'Wer schreibt hier?',
    'deklaration_unterzeile' => 'Bevor du den Chat benutzt, sag einmal, wer deine Nachrichten schreibt. '
        . 'Die Angabe steht danach dauerhaft im Chatfenster — für dein Gegenüber sichtbar, so wie '
        . 'dessen Angabe für dich.',

    // Die drei Werte aus Profile::DEKLARATIONEN. Fehlt einer, wird
    // tests/ChatTexteTest.php rot.
    'deklaration.person' => 'Sie schreibt selbst',
    'deklaration.team' => 'Team',
    'deklaration.ki' => 'KI-unterstützt',

    'deklaration_erklaerung.person' => 'Ausschließlich du selbst schreibst. Niemand sonst hat Zugriff '
        . 'auf dieses Postfach.',
    'deklaration_erklaerung.team' => 'Autorisierte Mitarbeitende schreiben mit. Auch dann bist du für '
        . 'jede Nachricht verantwortlich.',
    'deklaration_erklaerung.ki' => 'Antworten werden maschinell erzeugt oder vorgeschlagen — auch dann, '
        . 'wenn du sie vor dem Absenden noch einmal liest.',

    'deklaration_warnung' => 'Bitte gib das ehrlich an. Eine falsche Angabe ist ein Sperrgrund: Wer '
        . 'behauptet, selbst zu schreiben, und es nicht tut, täuscht die Person am anderen Ende über '
        . 'genau das, wofür sie hier ist.',
    'deklaration_spaeter' => 'Du kannst die Angabe später ändern. Bereits gesendete Nachrichten behalten '
        . 'die Angabe, die beim Schreiben galt.',
    'deklaration_speichern' => 'Angabe übernehmen',

    // --- Störung ------------------------------------------------------------
    'gestoert_titel' => 'Gerade nicht erreichbar',
    'gestoert_text' => 'Die Nachrichten lassen sich im Moment nicht laden. Das liegt an uns, nicht an '
        . 'dir — versuch es gleich noch einmal.',

    // --- Rückmeldungen ------------------------------------------------------
    'erfolg.deklaration_gesetzt' => 'Deine Angabe ist gespeichert.',
    'erfolg.sperre_gesetzt' => 'Die Person ist gesperrt. Ihr könnt einander nicht mehr schreiben.',
    'erfolg.sperre_aufgehoben' => 'Die Sperre ist aufgehoben.',

    // --- Fehler -------------------------------------------------------------
    // Die ersten neun sind wörtlich die Schlüssel von ChatFehler, danach zwei
    // aus KontoFehler und vier aus NachrichtenRouten. Kein Text nennt eine
    // Kennung: Der Chat ist die Stelle mit den empfindlichsten Daten der
    // Plattform, und 'nicht_teilnehmer' mit Nummer wäre die Bestätigung, dass
    // es die fremde Unterhaltung gibt.
    'fehler.nicht_teilnehmer' => 'Dieses Gespräch gehört nicht zu deinem Konto.',
    'fehler.gesperrt' => 'In diesem Gespräch kann gerade nicht geschrieben werden. Zwischen euch besteht '
        . 'eine Sperre.',
    'fehler.text_leer' => 'Die Nachricht ist leer.',
    'fehler.text_zu_lang' => 'Die Nachricht ist zu lang. Kürze sie und schick sie noch einmal ab.',
    'fehler.deklaration_fehlt' => 'Sag zuerst, wer deine Nachrichten schreibt. Ohne diese Angabe geht '
        . 'im Chat nichts.',
    'fehler.selbstgespraech' => 'Mit dir selbst kannst du kein Gespräch führen.',
    'fehler.unterhaltung_unbekannt' => 'Dieses Gespräch gibt es nicht.',
    'fehler.zu_schnell' => 'Das war zu schnell hintereinander. Warte einen Moment und versuch es noch '
        . 'einmal — dein Text bleibt stehen.',
    'fehler.empfaenger_unbekannt' => 'Dieses Konto gibt es nicht.',

    'fehler.deklaration_unbekannt' => 'Bitte wähle einen der drei angebotenen Punkte.',
    'fehler.benutzer_unbekannt' => 'Dieses Konto gibt es nicht.',

    'fehler.empfaenger_ungueltig' => 'Es ist nicht erkennbar, wem du schreiben willst.',
    'fehler.nachricht_unbekannt' => 'Diese Nachricht gehört nicht zu diesem Gespräch.',
    'fehler.gestoert' => 'Das hat gerade nicht geklappt. Bitte versuch es in einem Moment noch einmal.',
    'fehler.unbekannt' => 'Das hat nicht geklappt. Bitte versuch es noch einmal.',
];
