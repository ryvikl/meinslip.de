<?php

declare(strict_types=1);

/**
 * Bereiche, die in der Navigation stehen, aber noch kein Backend haben.
 * Eine ehrliche Auskunft ist besser als eine Fehlerseite — und besser als
 * eine Attrappe, die so tut, als gäbe es die Funktion schon.
 */
return [
    'kicker' => 'In Arbeit',
    'titel' => 'Noch nicht verfügbar',

    // Die 'nachrichten_*'-Texte sind entfallen: Der Chat ist gebaut. Die Seite
    // steht in resources/views/nachrichten/, die Texte in
    // resources/lang/de-DE/chat.php — darunter auch das Versprechen, das hier
    // stand: Jedes Konto gibt an, ob selbst, im Team oder KI-unterstützt
    // geschrieben wird, und das Label steht dauerhaft im Chatfenster.

    'guthaben_titel' => 'Guthaben',
    'guthaben' => 'Das Guthabenkonto ist im Code angelegt, aber bewusst gesperrt.',
    'guthaben_warum_titel' => 'Warum es gesperrt ist',
    'guthaben_warum' => 'Ein aufladbares, jederzeit rückforderbares Guthaben kann aufsichtsrechtlich '
        . 'ein Einlagengeschäft sein. Diese Frage ist offen, und bevor sie ein Fachanwalt beantwortet '
        . 'hat, nehmen wir kein Geld entgegen. Lieber später starten als mit einer Banklizenz-Frage im Rücken.',

    'profil_titel' => 'Profil',
    'profil' => 'Die Profilverwaltung folgt, sobald die Altersprüfung angebunden ist.',
    'profil_warum_titel' => 'Ein Konto, alle Rollen',
    'profil_warum' => 'Es wird kein zweites Konto zum Verkaufen geben. Verkaufen ist eine Berechtigung, '
        . 'die zu deinem bestehenden Konto hinzukommt, sobald die Identitätsprüfung besteht — '
        . 'dieselbe Anmeldung, dieselbe Historie.',
];
