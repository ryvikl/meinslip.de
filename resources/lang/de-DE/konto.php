<?php

declare(strict_types=1);

/**
 * Registrierung, Anmeldung, Konto.
 *
 * Grundsatz: EINE Registrierung, alle Rollen. Deshalb heisst es nirgends
 * "Als Kaeufer registrieren" oder "Verkaeuferkonto anlegen" — es gibt nur
 * ein Konto, und Verkaufen kommt spaeter als Faehigkeit hinzu.
 */
return [
    'registrieren_kicker' => 'Ein Konto für alles',
    'registrieren_titel' => 'Konto erstellen',
    'registrieren_absenden' => 'Konto erstellen',
    'registrieren_naechster_schritt' => 'Nach der Registrierung folgt die Altersprüfung. '
        . 'Verkaufen schaltest du später mit derselben Anmeldung frei — du brauchst kein zweites Konto.',

    'anmelden_kicker' => 'Willkommen zurück',
    'anmelden_titel' => 'Anmelden',
    'anmelden_absenden' => 'Anmelden',

    'feld_pseudonym' => 'Pseudonym',
    'feld_email' => 'E-Mail-Adresse',
    'feld_passwort' => 'Passwort',

    'hinweis_pseudonym' => 'So sehen dich andere. Deinen echten Namen fragen wir nicht ab.',
    'hinweis_email' => 'Nur für Anmeldung und wichtige Benachrichtigungen. Nie sichtbar für andere.',
    'hinweis_passwort' => 'Mindestens zwölf Zeichen. Länge schützt besser als Sonderzeichen.',

    'bereits_konto' => 'Du hast schon ein Konto?',
    'zur_anmeldung' => 'Hier anmelden',
    'noch_kein_konto' => 'Noch kein Konto?',
    'zur_registrierung' => 'Jetzt erstellen',

    'abgemeldet' => 'Du bist abgemeldet.',

    // Fehlermeldungen. Die Schluessel entsprechen KontoFehler::schluessel().
    'fehler.pseudonym_ungueltig' => 'Das Pseudonym braucht drei bis dreißig Zeichen '
        . 'und darf nur Buchstaben, Ziffern, Bindestrich und Unterstrich enthalten.',
    'fehler.email_ungueltig' => 'Diese E-Mail-Adresse sieht nicht gültig aus.',
    'fehler.passwort_zu_kurz' => 'Das Passwort braucht mindestens zwölf Zeichen.',
    'fehler.email_vergeben' => 'Für diese E-Mail-Adresse gibt es bereits ein Konto.',
    'fehler.pseudonym_vergeben' => 'Dieses Pseudonym ist schon vergeben.',
    // Bewusst dieselbe Meldung für falsche Adresse und falsches Passwort:
    // sonst verrät die Anmeldung, welche Adressen registriert sind.
    'fehler.anmeldung_fehlgeschlagen' => 'E-Mail-Adresse oder Passwort stimmen nicht.',
];
