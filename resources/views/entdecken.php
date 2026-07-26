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
 * Gestaltung nach design/MeinSlip App.dc.html, Screen 02: kompakter Kopf mit
 * Zähler rechts, darunter das Kachelraster. Die Suchleiste der Vorlage fehlt
 * absichtlich — es gibt noch keine Suchroute, und ein Feld, das nirgends
 * hinführt, ist ein Versprechen, das die Seite nicht hält.
 *
 * Diese Zone bleibt nicht-pornografisch und damit indexierbar.
 *
 * @var list<array<string,mixed>> $kategorien
 * @var int $angebote
 */

$pfeil = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
    . 'stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
    . '<path d="M9.5 5.5 16 12l-6.5 6.5"/></svg>';
?>
<section class="ms-abschnitt">
    <div class="ms-kopfzeile">
        <div>
            <p class="ms-kicker"><?= te('entdecken.kicker') ?></p>
            <h1 style="font-size:clamp(1.75rem,4vw,2.5rem)"><?= te('entdecken.titel') ?></h1>
            <p class="ms-hero__unterzeile" style="margin-top:var(--space-4);font-size:1rem"><?= te('entdecken.unterzeile') ?></p>
        </div>
        <p class="ms-kopfzeile__neben">
            <?= $angebote === 0
                ? te('entdecken.angebote_leer')
                : te('entdecken.angebote_anzahl', ['anzahl' => $angebote]) ?>
        </p>
    </div>

    <?php if ($kategorien === []): ?>
        <article class="card elev-sm">
            <h3 class="card-title"><?= te('entdecken.leer_titel') ?></h3>
            <p class="card-body"><?= te('entdecken.leer_text') ?></p>
        </article>
    <?php else: ?>
        <div class="ms-raster">
            <?php // Die ganze Karte ist der Verweis, nicht nur die Überschrift:
                  // auf dem Telefon ist die Fläche das Bedienelement. ?>
            <?php foreach ($kategorien as $kategorie): ?>
                <a class="ms-kachel ms-kategoriekarte" style="flex-direction:row;align-items:center;gap:var(--space-4);padding:var(--space-4)"
                   href="/kategorie/<?= e((string) $kategorie['pfad']) ?>">
                    <span class="ms-symbolchip"><?= $pfeil ?></span>
                    <span style="flex:1;display:flex;flex-direction:column;gap:2px;min-width:0">
                        <span class="ms-kachel__titel" style="font-size:0.9375rem"><?= te('kategorie.' . $kategorie['schluessel']) ?></span>
                        <span class="ms-kachel__meta"><?= e((string) $kategorie['pfad']) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
