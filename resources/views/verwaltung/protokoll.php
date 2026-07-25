<?php

declare(strict_types=1);

/**
 * Protokoll der Verwaltungshandlungen, neueste zuerst.
 *
 * Diese Liste ist der Nachweis nach Art. 17 DSA: Sie zeigt, wer wann was
 * entschieden hat und warum. Sie lässt sich nirgends ändern.
 *
 * @var array{zeilen: list<array<string,mixed>>, anzahl: int, seite: int, seiten: int, pro_seite: int} $liste
 */

$blaettern = static fn (int $seite): string => '/verwaltung/protokoll?seite=' . $seite;
?>
<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('verwaltung.protokoll_kicker') ?></p>
        <h1 style="font-size:clamp(1.5rem,4vw,2.25rem)"><?= te('verwaltung.protokoll_titel') ?></h1>
    </div>

    <?php if ($liste['zeilen'] === []): ?>
        <article class="card">
            <p class="card-body"><?= te('verwaltung.protokoll_leer') ?></p>
        </article>
    <?php else: ?>
        <p class="text-muted"><?= te('verwaltung.anzahl_gesamt', ['anzahl' => $liste['anzahl']]) ?></p>

        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col"><?= te('verwaltung.spalte.zeit') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.verwalter') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.handlung') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.gegenstand') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.begruendung') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($liste['zeilen'] as $ereignis): ?>
                    <tr>
                        <td><?= e((string) $ereignis['angelegt_am']) ?></td>
                        <td><?= $ereignis['verwalter_pseudonym'] === null ? te('verwaltung.ohne') : e((string) $ereignis['verwalter_pseudonym']) ?></td>
                        <td><?= te('verwaltung.handlung.' . $ereignis['handlung']) ?></td>
                        <td>
                            <?= te('verwaltung.gegenstand.' . $ereignis['gegenstand_art']) ?>
                            <?= $ereignis['gegenstand_id'] === null ? '' : e('#' . (int) $ereignis['gegenstand_id']) ?>
                        </td>
                        <td><?= e((string) ($ereignis['begruendung'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($liste['seiten'] > 1): ?>
            <p class="text-muted">
                <?php if ($liste['seite'] > 1): ?>
                    <a href="<?= e($blaettern($liste['seite'] - 1)) ?>"><?= te('verwaltung.blaettern_zurueck') ?></a>
                <?php endif; ?>
                <?= te('verwaltung.blaettern_seite', ['seite' => $liste['seite'], 'gesamt' => $liste['seiten']]) ?>
                <?php if ($liste['seite'] < $liste['seiten']): ?>
                    <a href="<?= e($blaettern($liste['seite'] + 1)) ?>"><?= te('verwaltung.blaettern_weiter') ?></a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</section>
