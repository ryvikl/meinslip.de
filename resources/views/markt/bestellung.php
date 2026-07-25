<?php

declare(strict_types=1);

/**
 * Eine Bestellung ansehen — nur fuer die beiden Beteiligten.
 *
 * Die Aufteilung in Vermittlungsgebuehr und Auszahlung sieht nur die
 * Verkaeuferin: Fuer die Kaeuferin ist der Gesamtbetrag mit der enthaltenen
 * Umsatzsteuer die vollstaendige Auskunft, alles weitere ist das
 * Innenverhaeltnis zwischen Plattform und Verkaeuferin.
 *
 * @var array<string,mixed>|null $bestellung
 * @var list<array<string,mixed>> $positionen
 * @var string|null $rolle   kaeufer | verkaeufer
 * @var array<string,mixed>|null $gegenueber
 * @var bool $neu
 * @var bool $zugang
 */

$bestellung ??= null;
$positionen ??= [];
$rolle ??= null;
$gegenueber ??= null;
$neu ??= false;
$zugang ??= false;
?>
<?php if ($bestellung === null): ?>
    <section class="ms-abschnitt">
        <article class="card elev-sm">
            <h1 class="card-title"><?= te('markt.bestellung_unbekannt_titel') ?></h1>
            <p class="card-body"><?= te('markt.bestellung_unbekannt_text') ?></p>
        </article>
    </section>
<?php elseif (!$zugang): ?>
    <section class="ms-abschnitt">
        <article class="card elev-sm">
            <h1 class="card-title"><?= te('markt.bestellung_kein_zugang_titel') ?></h1>
            <p class="card-body"><?= te('markt.bestellung_kein_zugang_text') ?></p>
        </article>
    </section>
<?php else: ?>
    <?php
    $waehrung = (string) $bestellung['waehrung'];
    $istVerkaeufer = $rolle === 'verkaeufer';
    ?>
    <section class="ms-abschnitt">
        <div>
            <p class="ms-kicker"><?= te('markt.bestellung_kicker') ?></p>
            <h1 style="font-size:clamp(1.75rem,4vw,2.5rem)"><?= e((string) $bestellung['nummer']) ?></h1>
        </div>

        <?php if ($neu): ?>
            <p class="card" role="status"><?= te('markt.bestellung_erfolg') ?></p>
        <?php endif; ?>

        <article class="card elev-sm">
            <p class="card-meta">
                <span class="tag tag-accent"><?= te('bestellung.zustand.' . (string) $bestellung['zustand']) ?></span>
                <span class="tag tag-neutral"><?= te('markt.konfigurator_lieferart_' . (string) $bestellung['lieferart']) ?></span>
                <span class="tag tag-neutral"><?= te('markt.bestellung_rolle') ?>:
                    <?= $istVerkaeufer ? te('markt.bestellung_rolle_verkaeufer') : te('markt.bestellung_rolle_kaeufer') ?></span>
            </p>
            <p class="card-body"><?= te('markt.bestellung_nummer') ?>: <?= e((string) $bestellung['nummer']) ?></p>
            <p class="card-body"><?= te('markt.bestellung_angelegt') ?>: <?= e((string) $bestellung['angelegt_am']) ?></p>
            <?php if ($gegenueber !== null): ?>
                <p class="card-body"><?= $istVerkaeufer ? te('markt.bestellung_rolle_kaeufer') : te('markt.bestellung_rolle_verkaeufer') ?>:
                    <span class="tag tag-outline"><?= e((string) $gegenueber['pseudonym']) ?></span>
                </p>
            <?php endif; ?>
            <p class="card-meta"><?= te('markt.bestellung_treuhand') ?></p>
        </article>
    </section>

    <section class="ms-abschnitt" aria-labelledby="positionen">
        <div>
            <p class="ms-kicker"><?= te('markt.bestellung_kicker') ?></p>
            <h2 id="positionen"><?= te('markt.bestellung_positionen') ?></h2>
        </div>

        <?php foreach ($positionen as $position): ?>
            <article class="card elev-sm">
                <h3 class="card-title"><?= e((string) $position['bezeichnung']) ?></h3>
                <p class="card-meta">
                    <span class="tag tag-accent"><?= e(geld((int) $position['verkaufspreis_cent'], $waehrung)) ?></span>
                </p>

                <?php $spezifikationen = is_array($position['spezifikationen'] ?? null) ? $position['spezifikationen'] : []; ?>
                <?php if ($spezifikationen !== []): ?>
                    <p class="card-kicker"><?= te('markt.bestellung_spezifikationen') ?></p>
                    <table class="table">
                        <tbody>
                        <?php foreach ($spezifikationen as $spezifikation): ?>
                            <tr>
                                <td><?= e((string) $spezifikation['bezeichnung']) ?></td>
                                <td><?= e((string) ($spezifikation['wert'] ?? '')) ?></td>
                                <td><?= e(geld((int) $spezifikation['aufpreis_cent'], $waehrung)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

        <article class="card elev-sm">
            <p class="card-title"><?= te('markt.bestellung_summe') ?>:
                <?= e(geld((int) $bestellung['summe_verkauf_cent'], $waehrung)) ?></p>
            <p class="card-body"><?= te('markt.bestellung_ust') ?>:
                <?= e(geld((int) $bestellung['summe_ust_cent'], $waehrung)) ?></p>
            <?php if ($istVerkaeufer): ?>
                <p class="card-body"><?= te('markt.bestellung_provision') ?>:
                    <?= e(geld((int) $bestellung['summe_provision_cent'], $waehrung)) ?></p>
                <p class="card-body"><?= te('markt.bestellung_auszahlung') ?>:
                    <?= e(geld((int) $bestellung['summe_einkauf_cent'], $waehrung)) ?></p>
            <?php endif; ?>
            <p class="card-meta"><?= te('markt.bestellung_widerruf') ?></p>
        </article>
    </section>
<?php endif; ?>
