<?php

declare(strict_types=1);

/**
 * Startseite.
 *
 * Bewusst ohne explizite Inhalte: Für nicht verifizierte Gäste ist der
 * gesamte pornografische Bereich gesperrt (§ 4 Abs. 2 JMStV). Was hier zu
 * sehen ist, erklärt das Versprechen — nicht das Produkt.
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
        <li class="ms-abzeichen ms-abzeichen--geprueft">Geprüfte Identitäten</li>
        <li class="ms-abzeichen ms-abzeichen--treuhand">Geld erst nach Erhalt</li>
        <li class="ms-abzeichen">Anonymer Versand</li>
        <li class="ms-abzeichen">Werbefrei</li>
    </ul>
</section>

<section aria-labelledby="vertrauen">
    <h2 id="vertrauen" class="ms-nur-vorlesen">Unsere Versprechen</h2>
    <div class="ms-raster">
        <?php
        $versprechen = [
            ['diskret', 'ms-abzeichen'],
            ['sicher', 'ms-abzeichen--geprueft'],
            ['direkt', 'ms-abzeichen--treuhand'],
        ];
        foreach ($versprechen as [$schluessel, $klasse]):
            ?>
            <article class="ms-karte">
                <h3 class="ms-karte__titel"><?= te('allgemein.vertrauen_' . $schluessel . '_titel') ?></h3>
                <p class="ms-karte__text"><?= te('allgemein.vertrauen_' . $schluessel . '_text') ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section aria-labelledby="bereiche" style="margin-top:var(--ms-raum-8)">
    <h2 id="bereiche">Drei Wege, eine Plattform</h2>
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
    <h2 id="sicherheit">Sicherheit ist kein Zusatz. Sie ist das System.</h2>
    <div class="ms-raster">
        <article class="ms-karte">
            <h3 class="ms-karte__titel">Geprüfte Identität</h3>
            <p class="ms-karte__text">
                Hinter jedem Verkaufsprofil steht eine geprüfte reale Person. Ohne bestandene
                Prüfung gibt es keine Auszahlung.
            </p>
        </article>
        <article class="ms-karte">
            <h3 class="ms-karte__titel">Geld erst nach Erhalt</h3>
            <p class="ms-karte__text">
                Dein Betrag wird hinterlegt und erst freigegeben, wenn du die Ware hast und
                deine Prüfzeit abgelaufen ist.
            </p>
        </article>
        <article class="ms-karte">
            <h3 class="ms-karte__titel">Anonymer Versand</h3>
            <p class="ms-karte__text">
                Weder Käufer noch Verkäuferin sehen die Adresse der anderen Seite. Das Etikett
                entsteht bei uns.
            </p>
        </article>
        <article class="ms-karte">
            <h3 class="ms-karte__titel">Rückverfolgbare Inhalte</h3>
            <p class="ms-karte__text">
                Jedes ausgelieferte Bild trägt ein unsichtbares Merkmal. Taucht es woanders auf,
                lässt sich die Quelle bestimmen.
            </p>
        </article>
    </div>
</section>
