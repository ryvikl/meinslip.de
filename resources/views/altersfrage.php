<?php

declare(strict_types=1);

/**
 * Die Altersfrage. Eine eigene, vollständige Seite ohne Layout: Vor der
 * Antwort wird nichts von der Plattform gezeigt, auch keine Navigation.
 *
 * Die Rechtsseiten sind verlinkt — sie müssen ohne Antwort erreichbar sein.
 *
 * @var string $sprache
 * @var string $ziel Wohin es nach dem Ja geht (bereits geprüft)
 * @var bool $abgelehnt Ob gerade mit Nein geantwortet wurde
 */
?>
<!DOCTYPE html>
<html lang="<?= e($sprache) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= te('alter.titel') ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <meta name="RATING" content="RTA-5042-1996-1400-1577-RTA">
    <meta name="theme-color" content="#161826">
    <link rel="icon" href="/assets/symbole/app-icon.png" type="image/png">
    <link rel="stylesheet" href="<?= e(asset('/assets/css/nocturne.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
</head>
<body>
<main class="ms-huelle ms-inhalt" style="min-height:100vh;display:flex;align-items:center;justify-content:center">
    <section class="card elev-sm" style="max-width:30rem;display:flex;flex-direction:column;gap:var(--space-6);text-align:center">
        <img src="/assets/symbole/app-icon.png" alt="" width="56" height="56" style="margin:0 auto">

        <?php if ($abgelehnt): ?>
            <h1 style="font-size:1.5rem"><?= te('alter.nein_titel') ?></h1>
            <p class="card-body"><?= te('alter.nein_text') ?></p>
        <?php else: ?>
            <p class="ms-kicker"><?= te('alter.kicker') ?></p>
            <h1 style="font-size:1.5rem"><?= te('alter.frage') ?></h1>
            <p class="card-body"><?= te('alter.erklaerung') ?></p>

            <div style="display:flex;gap:var(--space-3);flex-wrap:wrap;justify-content:center">
                <form method="post" action="/alter" style="margin:0">
                    <input type="hidden" name="ziel" value="<?= e($ziel) ?>">
                    <button class="btn btn-primary" type="submit" name="antwort" value="ja"><?= te('alter.ja') ?></button>
                </form>
                <form method="post" action="/alter" style="margin:0">
                    <button class="btn btn-ghost" type="submit" name="antwort" value="nein"><?= te('alter.nein') ?></button>
                </form>
            </div>

            <p class="hinweis"><?= te('alter.cookie') ?></p>
        <?php endif; ?>

        <p class="hinweis">
            <a href="/impressum"><?= te('allgemein.fuss_impressum') ?></a> ·
            <a href="/datenschutz"><?= te('allgemein.fuss_datenschutz') ?></a> ·
            <a href="/agb"><?= te('allgemein.fuss_agb') ?></a>
        </p>
    </section>
</main>
</body>
</html>
