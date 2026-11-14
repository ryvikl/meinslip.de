<?php

declare(strict_types=1);

/**
 * Die Altersfrage vor dem ersten Seitenaufruf (app/Http/Alterstor.php).
 *
 * Kein Wort hier darf nach einer Prüfung klingen. Es ist eine Frage, und die
 * Antwort ist eine Erklärung der Person — nicht unsere Feststellung.
 */
return [
    'titel' => 'Nur für Erwachsene',
    'kicker' => 'Ab 18',
    'frage' => 'Bist du mindestens 18 Jahre alt?',
    'erklaerung' => 'Diese Seite richtet sich ausschließlich an Erwachsene. Bitte bestätige dein '
        . 'Alter, bevor es weitergeht.',
    'ja' => 'Ja, ich bin 18 oder älter',
    'nein' => 'Nein',
    'cookie' => 'Deine Antwort merken wir uns 30 Tage lang in einem Cookie auf diesem Gerät.',
    'nein_titel' => 'Hier geht es für dich nicht weiter',
    'nein_text' => 'Diese Seite ist Erwachsenen vorbehalten. Danke, dass du ehrlich geantwortet hast.',
];
