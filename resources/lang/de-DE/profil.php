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

    // Kein Verwaltungsvorgang, sondern die Empfangsbestaetigung nach Art. 16
    // Abs. 4 DSA: Wer meldet, bekommt eine Zeile, die belegt, dass die Meldung
    // angekommen ist. Sie steht in derselben Liste, weil sie ueber denselben
    // Weg zugestellt wird.
    'art.meldung_eingegangen' => 'Deine Meldung ist eingegangen',

    'konto_kicker' => 'Wege',
    'konto_titel' => 'Weiter zu',
    'weg_verkaufen' => 'Meine Angebote',
    'weg_verkaufen_text' => 'Angebote anlegen, bearbeiten und zur Prüfung einreichen.',
    'weg_entdecken' => 'Entdecken',
    'weg_entdecken_text' => 'Der Katalog nach Art, Material und Tragedauer.',
];
