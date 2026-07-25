<?php

declare(strict_types=1);

/**
 * Startseite.
 *
 * Bewusst ohne explizite Inhalte: Für nicht verifizierte Gäste ist der
 * pornografische Bereich gesperrt (§ 4 Abs. 2 JMStV). Was hier zu sehen ist,
 * erklärt das Versprechen — nicht das Produkt.
 *
 * Diese Seite gehört zur öffentlichen Zone und muss indexierbar bleiben.
 * Suchmaschinen sind der einzige Wachstumskanal, der offensteht, weil Meta,
 * Google und TikTok keine Werbung für Erwachsenenangebote zulassen.
 */
?>
<section class="ms-hero">
    <h1><?= te('allgemein.hero_titel') ?></h1>
    <p class="ms-hero__unterzeile"><?= te('allgemein.hero_unterzeile') ?></p>

    <div class="ms-hero__knoepfe">
        <a class="ms-knopf ms-knopf--haupt" href="/entdecken"><?= te('allgemein.hero_entdecken') ?></a>
        <a class="ms-knopf ms-knopf--zweit" href="/fuer-creator"><?= te('allgemein.hero_creator') ?></a>
    </div>

    <ul class="ms-merkmale">
        <li class="ms-abzeichen ms-abzeichen--geprueft"><?= te('allgemein.merkmal_geprueft') ?></li>
        <li class="ms-abzeichen ms-abzeichen--treuhand"><?= te('allgemein.merkmal_treuhand') ?></li>
        <li class="ms-abzeichen"><?= te('allgemein.merkmal_versand') ?></li>
        <li class="ms-abzeichen"><?= te('allgemein.merkmal_werbefrei') ?></li>
    </ul>
</section>

<section aria-labelledby="vertrauen">
    <h2 id="vertrauen" class="ms-nur-vorlesen"><?= te('allgemein.abschnitt_versprechen') ?></h2>
    <div class="ms-raster">
        <?php foreach (['diskret', 'sicher', 'direkt'] as $versprechen): ?>
            <article class="ms-karte">
                <h3 class="ms-karte__titel"><?= te('allgemein.vertrauen_' . $versprechen . '_titel') ?></h3>
                <p class="ms-karte__text"><?= te('allgemein.vertrauen_' . $versprechen . '_text') ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section aria-labelledby="bereiche" style="margin-top:var(--ms-raum-8)">
    <h2 id="bereiche"><?= te('allgemein.abschnitt_wege') ?></h2>
    <div class="ms-raster">
        <?php foreach (['content', 'markt', 'uebergabe'] as $bereich): ?>
            <article class="ms-karte ms-karte--hebend">
                <h3 class="ms-karte__titel"><?= te('allgemein.bereich_' . $bereich . '_titel') ?></h3>
                <p class="ms-karte__text"><?= te('allgemein.bereich_' . $bereich . '_text') ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section aria-labelledby="sicherheit" style="margin-top:var(--ms-raum-8)">
    <h2 id="sicherheit"><?= te('allgemein.abschnitt_sicherheit') ?></h2>
    <div class="ms-raster">
        <?php foreach (['identitaet', 'treuhand', 'versand', 'leak'] as $modul): ?>
            <article class="ms-karte">
                <h3 class="ms-karte__titel"><?= te('allgemein.sicher_' . $modul . '_titel') ?></h3>
                <p class="ms-karte__text"><?= te('allgemein.sicher_' . $modul . '_text') ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>
