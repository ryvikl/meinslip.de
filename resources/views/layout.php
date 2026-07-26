<?php

declare(strict_types=1);

/**
 * Grundgerüst jeder Seite — Nocturne-Designsystem.
 *
 * @var string $inhalt
 * @var string $titel
 * @var string $sprache
 * @var string|null $aktiv
 * @var array<string,mixed>|null $sitzung
 * @var bool|null $verwalter
 */

$aktiv ??= null;
$sitzung ??= null;
$angemeldet = $sitzung !== null;

// Der Verwaltungsbereich weist sich nicht selbst aus: Wer die Fähigkeit nicht
// hat, bekommt dort 404 statt 403, damit die Existenz des Bereichs nicht
// bestätigt wird. Der Verweis hier ist die Gegenseite davon — er erscheint
// nur, wenn die Route ihn ausdrücklich freigibt. Fehlt die Angabe, ist er weg.
$verwalter ??= false;

/** Strichsymbole im Stil des Designsystems. */
$symbol = static function (string $name): string {
    $pfade = [
        'home' => '<path d="M3.5 10.2 12 3.6l8.5 6.6V20a1 1 0 0 1-1 1h-15a1 1 0 0 1-1-1z"/><path d="M9.2 21v-6.4h5.6V21"/>',
        'entdecken' => '<circle cx="12" cy="12" r="9"/><path d="M14.9 9.1 13.4 13.4 9.1 14.9l1.5-4.3z"/>',
        'nachrichten' => '<path d="M4 5.5h16v11H12l-5 3.5v-3.5H4z"/>',
        'guthaben' => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/>',
        'profil' => '<circle cx="12" cy="8.5" r="3.5"/><path d="M5 20c0-3.3 3.1-5.5 7-5.5s7 2.2 7 5.5"/>',
        'verkaufen' => '<path d="M3.5 8.5h17l-1.4 11a1 1 0 0 1-1 .9H5.9a1 1 0 0 1-1-.9z"/>'
            . '<path d="M8.7 8.5V6.8a3.3 3.3 0 0 1 6.6 0v1.7"/>',
        'haken' => '<path d="m4.5 12.5 5 5 10-11"/>',
        'verbergen' => '<circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 0 0 18z" fill="currentColor" stroke="none"/>',
    ];

    return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($pfade[$name] ?? '') . '</svg>';
};
?>
<!DOCTYPE html>
<html lang="<?= e($sprache) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($titel) ?></title>

    <?php // Diskretion: keine Indexierung, keine Vorschau in fremden Diensten. ?>
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#161826">

    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/assets/symbole/app-icon.png" type="image/png">
    <link rel="apple-touch-icon" href="/assets/symbole/app-icon.png">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<a class="ms-sprungmarke" href="#hauptinhalt"><?= te('allgemein.zum_inhalt') ?></a>

<header class="ms-kopf">
    <div class="ms-huelle ms-kopf__reihe">
        <a class="ms-marke" href="/">
            <img src="/assets/symbole/app-icon.png" alt="">
            <span class="ms-marke__text">MeinSlip<span class="ms-marke__endung">.de</span></span>
        </a>

        <nav class="ms-kopf__nav" aria-label="<?= te('allgemein.nav_haupt') ?>">
            <a href="/entdecken"><?= te('allgemein.nav_entdecken') ?></a>
            <?php if ($angemeldet): ?>
                <a href="/verkaufen"><?= te('allgemein.nav_verkaufen') ?></a>
            <?php endif; ?>
            <a href="/sicherheit"><?= te('allgemein.nav_sicherheit') ?></a>
            <a href="/fuer-creator"><?= te('allgemein.nav_fuer_creator') ?></a>
            <?php if ($verwalter): ?>
                <a href="/verwaltung"><?= te('allgemein.nav_verwaltung') ?></a>
            <?php endif; ?>
        </nav>

        <div class="ms-kopf__aktionen">
            <?php // Auf schmalen Geräten trägt die untere Navigation die Wege;
                  // die Kopfzeile behält Marke, Hauptaktion und Schnellverbergen. ?>
            <?php if ($angemeldet): ?>
                <span class="tag tag-outline ms-kopf__pseudonym"><?= e((string) $sitzung['pseudonym']) ?></span>
                <form method="post" action="/abmelden" style="margin:0">
                    <button class="btn btn-ghost" type="submit"><?= te('allgemein.nav_abmelden') ?></button>
                </form>
            <?php else: ?>
                <a class="btn btn-ghost ms-kopf__nebenaktion" href="/anmelden"><?= te('allgemein.nav_anmelden') ?></a>
                <a class="btn btn-primary" href="/registrieren"><?= te('allgemein.nav_registrieren') ?></a>
            <?php endif; ?>
            <button class="btn btn-icon" type="button" data-verbergen
                    aria-label="<?= te('allgemein.schnellverbergen') ?>">
                <?= $symbol('verbergen') ?>
            </button>
        </div>
    </div>
</header>

<main id="hauptinhalt" class="ms-huelle ms-inhalt">
    <?= $inhalt ?>
</main>

<footer class="ms-fuss ms-huelle">
    <ul class="ms-fuss__links">
        <li><a href="/impressum"><?= te('allgemein.fuss_impressum') ?></a></li>
        <li><a href="/datenschutz"><?= te('allgemein.fuss_datenschutz') ?></a></li>
        <li><a href="/agb"><?= te('allgemein.fuss_agb') ?></a></li>
        <li><a href="/widerruf"><?= te('allgemein.fuss_widerruf') ?></a></li>
        <?php // § 312k BGB: ohne Anmeldung erreichbar. ?>
        <li><a href="/kuendigen"><?= te('allgemein.fuss_kuendigen') ?></a></li>
    </ul>
    <p>
        <?= te('allgemein.fuss_hinweis_alter') ?> ·
        <?= te('allgemein.fuss_hinweis_diskret') ?> ·
        <?= te('allgemein.fuss_hinweis_verifiziert') ?> ·
        <?= te('allgemein.fuss_hinweis_werbefrei') ?>
    </p>
</footer>

<nav class="ms-untennav" aria-label="<?= te('allgemein.nav_bereiche') ?>">
    <?php
    /*
     * FÜNF PLÄTZE, UND JEDER MUSS SICH SEINEN VERDIENEN.
     *
     * Unterhalb von 860 px ist die Kopfnavigation ausgeblendet — diese Leiste
     * ist dann der einzige Weg durch die Anwendung. Ein Platz, der auf eine
     * gesperrte Funktion zeigt, ist deshalb nicht bloß unschön, er kostet ein
     * Fünftel der knappsten Fläche der ganzen Anwendung.
     *
     * Guthaben ist genau so ein Platz und verlässt die Leiste. Es ist aus
     * Aufsichtsgründen gesperrt (§ 1 Abs. 1 S. 2 Nr. 1 KWG, siehe
     * docs/11-offene-fragen.md) und bleibt es auf Monate. Route und Seite
     * bleiben bestehen, erreichbar über /profil — nur der Dauerplatz geht.
     *
     * Nachrichten bekommt den frei gewordenen Platz zurück. Der Kommentar, der
     * hier vorher stand, hatte ihn ausdrücklich nur geliehen: „ein arbeitendes
     * Ziel schlägt ein angekündigtes" — der Chat arbeitet jetzt.
     *
     * Abgemeldet zeigt die Leiste keinen einzigen Eintrag, der zur Anmeldung
     * zwingt, außer denen, die es sollen: Sicherheit steht dort, weil es die
     * eine Seite ist, die jemand in Eile finden muss, ohne vorher ein Konto
     * anzulegen.
     */
    $abzeichen = $angemeldet ? \MeinSlip\Http\Navigation::abzeichen($sitzung) : null;

    $bereiche = $angemeldet
        ? [
            ['/', 'allgemein.nav_start', 'home', null],
            ['/entdecken', 'allgemein.nav_entdecken', 'entdecken', null],
            ['/nachrichten', 'allgemein.nav_nachrichten', 'nachrichten', $abzeichen],
            ['/verkaufen', 'allgemein.nav_verkaufen', 'verkaufen', null],
            ['/profil', 'allgemein.nav_profil', 'profil', null],
        ]
        : [
            ['/', 'allgemein.nav_start', 'home', null],
            ['/entdecken', 'allgemein.nav_entdecken', 'entdecken', null],
            ['/anmelden', 'allgemein.nav_anmelden', 'profil', null],
            ['/registrieren', 'allgemein.nav_registrieren_kurz', 'verkaufen', null],
            ['/sicherheit', 'allgemein.nav_sicherheit', 'guthaben', null],
        ];

    foreach ($bereiche as [$ziel, $schluessel, $zeichen, $zahl]):
        ?>
        <a class="ms-untennav__eintrag" href="<?= e($ziel) ?>"
           <?= $aktiv === $ziel ? 'aria-current="page"' : '' ?>>
            <span class="ms-untennav__symbol">
                <?= $symbol($zeichen) ?>
                <?php if ($zahl !== null): ?>
                    <span class="ms-untennav__abzeichen" aria-hidden="true"><?= e($zahl) ?></span>
                <?php endif; ?>
            </span>

            <?php // Sichtbar bleibt das kurze Wort — die Leiste hat auf 360 px
                  // rund 68 px je Platz, ein längerer Text bricht sie um.
                  // Die Zahl steht daneben nur als Ziffer und ist für eine
                  // Sprachausgabe ausgeblendet; sie bekommt stattdessen den
                  // ausgeschriebenen Satz, sonst läse sie „3 Nachrichten“ als
                  // zwei zusammenhanglose Wörter vor. ?>
            <span><?= te($schluessel) ?></span>
            <?php if ($zahl !== null): ?>
                <span class="ms-nur-vorlesen"><?= te('allgemein.nav_ungelesen', ['anzahl' => $zahl]) ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php // Schnellverbergen: Esc oder die Schaltfläche legt diesen Vorhang darüber. ?>
<div class="ms-vorhang" data-vorhang role="dialog" aria-modal="true"
     aria-label="<?= te('allgemein.vorhang_titel') ?>" hidden>
    <div>
        <h2><?= te('allgemein.vorhang_titel') ?></h2>
        <p class="ms-kicker" style="margin-top:var(--space-3)"><?= te('allgemein.vorhang_text') ?></p>
    </div>
</div>

<script src="/assets/js/app.js" defer></script>
</body>
</html>
