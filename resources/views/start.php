<?php

declare(strict_types=1);

/**
 * Startseite — nach der Landing-Vorlage aus dem Nocturne-Export.
 *
 * Gehört zur öffentlichen Zone: nicht pornografisch, damit sie indexierbar
 * bleibt. Suchmaschinen sind der einzige Wachstumskanal, der offensteht, weil
 * Meta, Google und TikTok keine Werbung für Erwachsenenangebote zulassen.
 * Siehe docs/04-features/discovery-taxonomie.md.
 *
 * @var array<string,mixed>|null $sitzung
 */

$haken = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
    . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
    . '<path d="m4.5 12.5 5 5 10-11"/></svg>';
?>
<section class="ms-hero">
    <span class="ms-hero__marke">
        <span class="ms-hero__punkt"></span>
        <?= te('allgemein.hero_marke') ?>
    </span>

    <h1><?= te('allgemein.hero_titel_1') ?><br><?= te('allgemein.hero_titel_2') ?></h1>

    <p class="ms-hero__unterzeile"><?= te('allgemein.hero_unterzeile') ?></p>

    <div class="ms-hero__knoepfe">
        <a class="btn btn-primary" href="/entdecken"><?= te('allgemein.hero_entdecken') ?></a>
        <a class="btn btn-secondary" href="/fuer-creator"><?= te('allgemein.hero_creator') ?></a>
    </div>

    <ul class="ms-merkmale">
        <?php foreach (['geprueft', 'treuhand', 'versand', 'werbefrei'] as $merkmal): ?>
            <li><?= $haken ?><?= te('allgemein.merkmal_' . $merkmal) ?></li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="ms-abschnitt" aria-labelledby="bereiche">
    <div>
        <p class="ms-kicker"><?= te('allgemein.abschnitt_wege_kicker') ?></p>
        <h2 id="bereiche"><?= te('allgemein.abschnitt_wege') ?></h2>
    </div>

    <div class="ms-raster">
        <?php foreach (['content', 'markt', 'uebergabe'] as $bereich): ?>
            <article class="card elev-sm">
                <p class="card-kicker"><?= te('allgemein.bereich_' . $bereich . '_kicker') ?></p>
                <h3 class="card-title"><?= te('allgemein.bereich_' . $bereich . '_titel') ?></h3>
                <p class="card-body"><?= te('allgemein.bereich_' . $bereich . '_text') ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="ms-abschnitt" aria-labelledby="vertrauen">
    <div>
        <p class="ms-kicker"><?= te('allgemein.abschnitt_versprechen_kicker') ?></p>
        <h2 id="vertrauen"><?= te('allgemein.abschnitt_versprechen') ?></h2>
    </div>

    <div class="ms-raster">
        <?php foreach (['diskret', 'sicher', 'direkt'] as $versprechen): ?>
            <article class="card">
                <h3 class="card-title"><?= te('allgemein.vertrauen_' . $versprechen . '_titel') ?></h3>
                <p class="card-body"><?= te('allgemein.vertrauen_' . $versprechen . '_text') ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="ms-abschnitt" aria-labelledby="sicherheit">
    <div>
        <p class="ms-kicker"><?= te('allgemein.abschnitt_sicherheit_kicker') ?></p>
        <h2 id="sicherheit"><?= te('allgemein.abschnitt_sicherheit') ?></h2>
    </div>

    <div class="ms-raster">
        <?php foreach (['identitaet', 'treuhand', 'versand', 'leak'] as $modul): ?>
            <article class="card elev-sm">
                <h3 class="card-title"><?= te('allgemein.sicher_' . $modul . '_titel') ?></h3>
                <p class="card-body"><?= te('allgemein.sicher_' . $modul . '_text') ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($sitzung === null): ?>
    <section class="ms-abschnitt" aria-labelledby="abschluss">
        <h2 id="abschluss"><?= te('allgemein.abschluss_titel') ?></h2>
        <div class="ms-hero__knoepfe">
            <a class="btn btn-primary" href="/registrieren"><?= te('allgemein.abschluss_registrieren') ?></a>
            <a class="btn btn-secondary" href="/fuer-creator"><?= te('allgemein.abschluss_creator') ?></a>
        </div>
    </section>
<?php endif; ?>
