<?php

declare(strict_types=1);

/**
 * Entdecken — der Kategorienbaum als Discovery-Motor.
 *
 * Nicht Namenssuche, sondern Attributsuche: Der Markt zeigt, dass eine tiefe
 * Taxonomie allein Kaufabsicht trägt, während der größte Anbieter mit
 * Millionen Creator praktisch keine Suchfunktion hat. Siehe
 * docs/04-features/discovery-taxonomie.md.
 *
 * Diese Zone bleibt nicht-pornografisch und damit indexierbar.
 *
 * @var list<array<string,mixed>> $kategorien
 * @var int $angebote
 */
?>
<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('entdecken.kicker') ?></p>
        <h1 style="font-size:clamp(1.75rem,4vw,2.5rem)"><?= te('entdecken.titel') ?></h1>
        <p class="ms-hero__unterzeile" style="margin-top:var(--space-4)"><?= te('entdecken.unterzeile') ?></p>
    </div>

    <?php if ($kategorien === []): ?>
        <article class="card">
            <h3 class="card-title"><?= te('entdecken.leer_titel') ?></h3>
            <p class="card-body"><?= te('entdecken.leer_text') ?></p>
        </article>
    <?php else: ?>
        <div class="ms-raster">
            <?php foreach ($kategorien as $kategorie): ?>
                <article class="card elev-sm">
                    <h3 class="card-title"><?= te('kategorie.' . $kategorie['schluessel']) ?></h3>
                    <p class="card-meta"><?= e((string) $kategorie['pfad']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('entdecken.angebote_kicker') ?></p>
        <h2><?= te('entdecken.angebote_titel') ?></h2>
    </div>

    <article class="card">
        <p class="card-body">
            <?= $angebote === 0
                ? te('entdecken.angebote_leer')
                : te('entdecken.angebote_anzahl', ['anzahl' => $angebote]) ?>
        </p>
    </article>
</section>
