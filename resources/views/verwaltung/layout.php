<?php

declare(strict_types=1);

/**
 * Grundgerüst des Verwaltungsbereichs.
 *
 * Bewusst schmaler als das Layout vorne: keine untere App-Navigation, kein
 * Schnellverbergen, kein Manifest, kein Service Worker. Der Bereich ist
 * Werkzeug, kein Schaufenster — und was hier nicht geladen wird, kann auch
 * nichts an fremde Geräte weiterreichen.
 *
 * @var string $inhalt
 * @var string $titel
 * @var string $sprache
 * @var string|null $aktiv
 * @var array<string,mixed>|null $sitzung
 */

$aktiv ??= null;
$sitzung ??= null;

$bereiche = [
    ['/verwaltung', 'verwaltung.nav.uebersicht'],
    ['/verwaltung/konten', 'verwaltung.nav.konten'],
    ['/verwaltung/angebote', 'verwaltung.nav.angebote'],
    ['/verwaltung/meldungen', 'verwaltung.nav.meldungen'],
    ['/verwaltung/protokoll', 'verwaltung.nav.protokoll'],
    ['/verwaltung/hauptbuch', 'verwaltung.nav.hauptbuch'],
];
?>
<!DOCTYPE html>
<html lang="<?= e($sprache) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($titel) ?></title>

    <?php // Diskretion: Der Bereich gehört in keinen Index und in keine Vorschau. ?>
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#161826">

    <link rel="icon" href="/assets/symbole/app-icon.png" type="image/png">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<a class="ms-sprungmarke" href="#hauptinhalt"><?= te('allgemein.zum_inhalt') ?></a>

<header>
    <nav class="nav ms-huelle" aria-label="<?= te('verwaltung.nav_bereiche') ?>">
        <a class="nav-brand" href="/verwaltung">MeinSlip · <?= te('verwaltung.bereich') ?></a>

        <?php foreach ($bereiche as [$ziel, $schluessel]): ?>
            <a href="<?= e($ziel) ?>"<?= $aktiv === $ziel ? ' aria-current="page"' : '' ?>><?= te($schluessel) ?></a>
        <?php endforeach; ?>

        <a href="/"><?= te('verwaltung.nav.website') ?></a>

        <?php if ($sitzung !== null): ?>
            <span class="tag tag-outline"><?= e((string) $sitzung['pseudonym']) ?></span>
        <?php endif; ?>
    </nav>
</header>

<main id="hauptinhalt" class="ms-huelle ms-inhalt">
    <?= $inhalt ?>
</main>
</body>
</html>
