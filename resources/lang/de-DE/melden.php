<?php

declare(strict_types=1);

/**
 * Die Meldestrecke nach Art. 16 DSA.
 *
 * Diese Texte tragen die Rechtsgrundlage dafuer, dass Angebote ohne
 * Vorabpruefung erscheinen. Sie duerfen deshalb an drei Stellen nicht
 * "freundlicher" werden:
 *
 *  - Sie versprechen keine Bestaetigung, wo keine zugestellt werden kann.
 *    Ohne Konto gibt es niemanden, dem etwas zuzustellen waere.
 *  - Sie nennen die zugesagte Frist als Zahl (:stunden aus
 *    Meldungen::FRIST_STUNDEN), nicht als "bald".
 *  - Sie sagen vor dem Absenden, dass wissentlich falsche Meldungen Folgen
 *    haben (Art. 23 Abs. 2 DSA). Danach liest das niemand mehr.
 *
 * FLACHE SCHLUESSEL. Lang::t() zerlegt nur am ERSTEN Punkt: 'grund.betrug'
 * ist ein Schluessel mit Punkt, kein verschachteltes Array. Ein Unterarray
 * ergaebe sichtbar '[[melden.grund.betrug]]'.
 *
 * PLATZHALTER heissen ':name' und werden per str_replace ersetzt. Kein Name
 * darf Praefix eines anderen sein — ':stunden' steht deshalb allein, und in
 * 'gegenstand_zeile' stehen ':gegenstand' und ':kennung' nebeneinander,
 * die einander nicht schlucken koennen.
 */
return [
    // --- Formular ---------------------------------------------------------
    'kicker' => 'Etwas stimmt nicht?',
    'titel' => 'Inhalt melden',
    'unterzeile' => 'Sag uns, was an diesem Inhalt nicht stimmt. Ein Mensch sieht sich das an und '
        . 'entscheidet. Ein Konto brauchst du dafür nicht — melden darf jede und jeder.',

    'gegenstand_zeile' => 'Du meldest: :gegenstand Nr. :kennung',

    // Fuer jede Art in MeldeRouten::ARTEN braucht es hier einen Text, sonst
    // steht '[[melden.art.x]]' im Formular. 'bestellung' liegt bereit, ist in
    // ARTEN aber bewusst noch nicht freigegeben — dort steht, warum.
    'art.angebot' => 'Angebot',
    'art.benutzer' => 'Profil',
    'art.bestellung' => 'Bestellung',

    'feld_grund' => 'Worum geht es?',
    'auswahl_bitte_waehlen' => 'Bitte wählen',
    'hinweis_grund' => 'Wähle den Punkt, der am besten passt. Er entscheidet mit darüber, wie schnell '
        . 'wir uns die Sache ansehen.',

    'feld_beschreibung' => 'Was ist passiert?',
    'hinweis_beschreibung' => 'Beschreibe möglichst genau, was an dem Inhalt nicht stimmt — je klarer, '
        . 'desto schneller können wir entscheiden. Ohne diese Erläuterung dürfen wir nicht tätig werden '
        . '(Art. 16 Abs. 2 DSA).',

    'warnung_missbrauch' => 'Bitte melde nur, was wirklich gegen Gesetze oder unsere Regeln verstößt. '
        . 'Wer wiederholt offensichtlich unbegründet meldet, verliert die Möglichkeit zu melden '
        . '(Art. 23 Abs. 2 DSA) — und eine erfundene Meldung kann für die gemeldete Person ernste '
        . 'Folgen haben.',

    'absenden' => 'Meldung absenden',

    'hinweis_bestaetigung' => 'Du bist angemeldet: Die Eingangsbestätigung erscheint gleich in deinem '
        . 'Profil, und dort siehst du später auch, wie wir entschieden haben.',
    'hinweis_anonym' => 'Du bist nicht angemeldet. Deine Meldung nehmen wir trotzdem entgegen — sie '
        . 'kommt dann ohne Absender an, und wir können dir die Entscheidung nicht mitteilen. Melde dich '
        . 'an, wenn du eine Antwort möchtest.',

    // --- Ohne Gegenstand ---------------------------------------------------
    // Ein Formular, in das sich eine beliebige Kennung tippen liesse, waere ein
    // Fernausloeser fuer fremde Inhalte. Deshalb gibt es hier keinen Ersatzweg,
    // sondern den Hinweis auf den Knopf am Inhalt selbst.
    'ohne_gegenstand_titel' => 'Was möchtest du melden?',
    'ohne_gegenstand_text' => 'Diese Seite braucht den Inhalt, um den es geht. Öffne das Angebot oder '
        . 'das Profil und benutze dort den Meldeknopf — dann liegt uns sofort vor, worum es geht.',
    'ohne_gegenstand_knopf' => 'Zu den Angeboten',

    // --- Meldegruende ------------------------------------------------------
    // Die Schluessel sind die Konstanten aus Meldungen::GRUENDE. Kommt dort
    // einer dazu, muss hier ein Text dazu — sonst steht im Auswahlfeld
    // '[[melden.grund.x]]'.
    'grund.minderjaehrig' => 'Hier ist jemand minderjährig',
    'grund.gestohlene_identitaet' => 'Bilder oder Identität einer anderen Person',
    'grund.verbotene_ware' => 'Verbotene oder nicht verkehrsfähige Ware',
    'grund.betrug' => 'Betrug',
    'grund.belaestigung' => 'Belästigung oder Bedrohung',
    'grund.urheberrecht' => 'Urheberrecht verletzt',
    'grund.sonstiges' => 'Etwas anderes',

    // --- Fehler ------------------------------------------------------------
    // Woertlich die Schluessel aus MeldungsFehler::schluessel(), dazu die vier
    // aus MeldeRouten. Die Texte nennen NIE eine fremde Kennung: Ob es Angebot
    // 4711 gibt, geht die meldende Person nichts an.
    'fehler.gegenstand_art_unbekannt' => 'Es steht nicht fest, was gemeldet werden soll. Öffne das '
        . 'Angebot oder das Profil, um das es geht, und benutze dort den Meldeknopf.',
    'fehler.grund_unbekannt' => 'Bitte wähle einen Grund aus der Liste.',
    'fehler.grund_zu_lang' => 'Dieser Grund ist ungültig. Bitte wähle einen aus der Liste.',
    'fehler.beschreibung_fehlt' => 'Bitte erkläre kurz, was an dem Inhalt nicht stimmt. Ohne '
        . 'Erläuterung können wir nichts prüfen — Art. 16 Abs. 2 DSA verlangt sie ausdrücklich.',
    'fehler.beschreibung_zu_lang' => 'Deine Erläuterung ist zu lang. Bitte fasse dich kürzer: Was '
        . 'niemand zu Ende liest, wird nicht sorgfältiger geprüft.',
    'fehler.gegenstand_unbekannt' => 'Diesen Inhalt gibt es nicht mehr. Möglicherweise wurde er '
        . 'bereits entfernt.',
    'fehler.melder_unbekannt' => 'Dein Konto ließ sich nicht zuordnen. Melde dich bitte neu an und '
        . 'versuche es noch einmal.',
    'fehler.bereits_gemeldet' => 'Du hast diesen Inhalt schon gemeldet, und wir haben noch nicht '
        . 'entschieden. Sobald das geschehen ist, steht die Entscheidung in deinem Profil — und du '
        . 'kannst erneut melden, falls sich etwas geändert hat.',

    // Die beiden Drosselungen aus MeldeRouten. Beide Texte sagen, was gilt und
    // was der Weg daran vorbei ist — eine Grenze ohne Ausweg wäre eine Absage
    // an das Melderecht.
    'fehler.zu_viele_meldungen' => 'Aus diesem Browser sind gerade schon mehrere Meldungen gekommen. '
        . 'Damit sich niemand mit Meldungen überziehen lässt, nehmen wir sie nur in Abständen '
        . 'entgegen — versuche es in einer Weile noch einmal. Ist die Sache dringend, erreichst du '
        . 'uns über die Angaben im Impressum.',
    'fehler.bereits_in_pruefung' => 'Zu diesem Inhalt liegen bereits mehrere unbearbeitete Meldungen '
        . 'vor. Er steht damit in unserer Prüfliste, eine weitere anonyme Meldung ändert daran nichts. '
        . 'Melde dich an, wenn deine Erläuterung trotzdem ankommen soll: Meldungen mit Konto nehmen '
        . 'wir immer entgegen, und du bekommst die Entscheidung mitgeteilt.',

    'fehler.gestoert' => 'Wir konnten deine Meldung gerade nicht speichern. Das liegt an uns, nicht '
        . 'an dir — dein Text steht noch da, bitte sende ihn gleich noch einmal ab.',
    'fehler.unbekannt' => 'Deine Meldung konnte nicht entgegengenommen werden. Bitte versuche es '
        . 'noch einmal.',

    // --- Bestätigungsseite -------------------------------------------------
    'danke_kicker' => 'Angekommen',
    'danke_titel' => 'Danke für deine Meldung',
    'danke_unterzeile' => 'Sie liegt jetzt bei uns. Was daraus wird, entscheidet ein Mensch — '
        . 'nichts wird automatisch gesperrt, und nichts verschwindet unbemerkt.',

    'danke_frist_kicker' => 'Unsere Zusage',
    'danke_frist_titel' => 'Wir sehen uns das binnen :stunden Stunden an',
    'danke_frist_text' => 'Diese Frist ist eine Selbstverpflichtung: Art. 16 Abs. 6 DSA verlangt eine '
        . 'zeitnahe Bearbeitung, ohne eine Zahl zu nennen. Wir haben :stunden Stunden zugesagt und '
        . 'messen uns daran — die Frist steht bei deiner Meldung und läuft mit.',

    'danke_bestaetigung_kicker' => 'Dein Beleg',
    'danke_bestaetigung_titel' => 'Die Eingangsbestätigung steht in deinem Profil',
    'danke_bestaetigung_text' => 'Art. 16 Abs. 4 DSA verlangt eine unverzügliche Bestätigung — diese '
        . 'Seite allein wäre keine, denn sie ist beim nächsten Klick weg. Deine Bestätigung liegt '
        . 'deshalb in deinem Profil, samt deiner Erläuterung im Wortlaut. Dort erscheint später auch, '
        . 'wie wir entschieden haben.',
    'danke_profil_knopf' => 'Zum Profil',

    'danke_anonym_kicker' => 'Ohne Absender',
    'danke_anonym_titel' => 'Deine Meldung ist anonym bei uns',
    'danke_anonym_text' => 'Du warst nicht angemeldet, deshalb steht bei deiner Meldung kein Absender. '
        . 'Das ist so gewollt und ändert nichts daran, dass wir sie prüfen. Es heißt aber auch: Wir '
        . 'haben keinen Weg, dir die Entscheidung mitzuteilen. Mit einem Konto bekämest du beides — '
        . 'die Eingangsbestätigung und die Antwort.',

    'danke_weiter_knopf' => 'Weiter stöbern',
];
