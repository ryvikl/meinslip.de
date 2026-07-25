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

    <?php
    /*
     * Kopfleiste des Verwaltungsbereichs.
     *
     * Nocturnes .nav ist eine Zeile ohne Umbruch (nocturne.css). Diese Leiste
     * hat neun Kinder — Marke, sechs Bereiche, "Zur Website", Pseudonym —, die
     * zusammen rund 600 px brauchen. Auf einem 360-px-Telefon bleiben nach
     * .ms-huelle und den Innenabstaenden 304 px. Flex-Kinder schrumpfen wegen
     * min-width:auto nicht unter ihr min-content, also ragte die Leiste ueber
     * den Rand hinaus und schob das ganze Dokument seitwaerts. Damit liefen
     * zugleich die .ms-breit-Huellen um die Tabellen ins Leere: die Seite
     * wanderte schon, bevor die Tabelle ueberhaupt scrollte.
     *
     * Die Regeln stehen hier und nicht in app.css, weil .nav ausschliesslich
     * von diesem Layout benutzt wird und der Bereich bewusst nichts laedt, was
     * er nicht braucht (siehe Kopfkommentar). Ausschliesslich Tokens, keine
     * harten Werte — --tippziel und --space-* kommen aus nocturne.css.
     */
    ?>
    <style>
        .nav { flex-wrap: wrap; row-gap: var(--space-2); }

        /* Unter 560 px nimmt die Marke eine eigene Zeile. Ihr margin-right:auto
           loest bei negativem Freiraum auf 0 auf und drueckt die Verweise sonst
           an den Rand, statt sie umbrechen zu lassen. */
        @media (max-width: 559px) {
            .nav-brand { flex: 1 0 100%; margin-right: 0; }
        }

        /* Die Verweise sind die einzigen Bedienelemente der Leiste. Als reine
           14-px-Textzeilen waren sie rund 17 px hoch — sie muessen dasselbe
           Fingerziel treffen wie .btn und .input. */
        .nav a {
            display: inline-flex;
            align-items: center;
            min-height: var(--tippziel);
            touch-action: manipulation;
        }
    </style>
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
