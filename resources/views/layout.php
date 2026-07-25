<?php

declare(strict_types=1);

/**
 * Grundgerüst jeder Seite.
 *
 * @var string $inhalt
 * @var string $titel
 * @var string $sprache
 * @var string|null $aktiv
 */

$aktiv ??= null;
?>
<!DOCTYPE html>
<html lang="<?= e($sprache) ?>" data-thema="dunkel">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($titel) ?></title>

    <?php // Diskretion: keine Vorschau in fremden Diensten, keine Indexierung. ?>
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#080B14">

    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/assets/symbole/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/assets/symbole/icon-180.png">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<a class="ms-sprungmarke" href="#hauptinhalt"><?= te('allgemein.zum_inhalt') ?></a>

<header class="ms-kopf">
    <div class="ms-huelle ms-kopf__reihe">
        <a class="ms-marke" href="/">
            <span class="ms-marke__zeichen" aria-hidden="true">M</span>
            <span><?= te('allgemein.marke_lang') ?></span>
        </a>

        <nav class="ms-kopf__nav" aria-label="<?= te('allgemein.nav_haupt') ?>">
            <a href="/entdecken"><?= te('allgemein.nav_entdecken') ?></a>
            <a href="/sicherheit"><?= te('allgemein.nav_sicherheit') ?></a>
            <a href="/fuer-creator"><?= te('allgemein.nav_fuer_creator') ?></a>
            <a href="/anmelden"><?= te('allgemein.nav_anmelden') ?></a>
            <a class="ms-knopf ms-knopf--haupt" href="/registrieren"><?= te('allgemein.nav_registrieren') ?></a>
        </nav>

        <button class="ms-knopf ms-knopf--leer" type="button" data-verbergen
                aria-label="<?= te('allgemein.schnellverbergen') ?>">
            <span aria-hidden="true">◐</span>
        </button>
    </div>
</header>

<main id="hauptinhalt" class="ms-huelle ms-inhalt">
    <?= $inhalt ?>
</main>

<footer class="ms-fuss">
    <div class="ms-huelle">
        <ul class="ms-fuss__links">
            <li><a href="/impressum"><?= te('allgemein.fuss_impressum') ?></a></li>
            <li><a href="/datenschutz"><?= te('allgemein.fuss_datenschutz') ?></a></li>
            <li><a href="/agb"><?= te('allgemein.fuss_agb') ?></a></li>
            <li><a href="/widerruf"><?= te('allgemein.fuss_widerruf') ?></a></li>
            <?php // § 312k BGB: erreichbar ohne Anmeldung. ?>
            <li><a href="/kuendigen"><?= te('allgemein.fuss_kuendigen') ?></a></li>
        </ul>
        <p>
            <?= te('allgemein.fuss_hinweis_alter') ?> ·
            <?= te('allgemein.fuss_hinweis_diskret') ?> ·
            <?= te('allgemein.fuss_hinweis_verifiziert') ?> ·
            <?= te('allgemein.fuss_hinweis_werbefrei') ?>
        </p>
    </div>
</footer>

<nav class="ms-untennav" aria-label="<?= te('allgemein.nav_bereiche') ?>">
    <?php
    $bereiche = [
        ['/', 'allgemein.nav_start', '⌂'],
        ['/entdecken', 'allgemein.nav_entdecken', '◎'],
        ['/nachrichten', 'allgemein.nav_nachrichten', '✉'],
        ['/guthaben', 'allgemein.nav_wallet', '◈'],
        ['/profil', 'allgemein.nav_profil', '○'],
    ];
    foreach ($bereiche as [$ziel, $schluessel, $zeichen]):
        ?>
        <a class="ms-untennav__eintrag" href="<?= e($ziel) ?>"
           <?= $aktiv === $ziel ? 'aria-current="page"' : '' ?>>
            <span aria-hidden="true"><?= e($zeichen) ?></span>
            <span><?= te($schluessel) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<?php // Schnellverbergen: Esc oder Doppeltipp legt diesen Vorhang darüber. ?>
<div class="ms-vorhang" data-vorhang role="dialog" aria-modal="true" aria-label="<?= te('allgemein.vorhang_titel') ?>" hidden>
    <div>
        <p style="font-size:1.25rem;margin-bottom:8px"><?= te('allgemein.vorhang_titel') ?></p>
        <p style="color:#94a3b8;margin:0"><?= te('allgemein.vorhang_text') ?></p>
    </div>
</div>

<script src="/assets/js/app.js" defer></script>
</body>
</html>
