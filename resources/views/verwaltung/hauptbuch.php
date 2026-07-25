<?php

declare(strict_types=1);

/**
 * Die letzten Vorgänge des Hauptbuchs — nur lesend.
 *
 * Es gibt hier keine Schaltfläche, die etwas ändert, und das ist Absicht: Eine
 * falsche Buchung wird mit einer Gegenbuchung berichtigt, nie durch Ändern
 * einer bestehenden Zeile. Ein Vorgang, der nicht ausgeglichen ist, ist die
 * Fundstelle einer Abweichung.
 *
 * @var array{zeilen: list<array<string,mixed>>, anzahl: int, seite: int, seiten: int, pro_seite: int} $liste
 * @var int $abweichung
 */

$blaettern = static fn (int $seite): string => '/verwaltung/hauptbuch?seite=' . $seite;
?>
<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('verwaltung.hauptbuch_kicker') ?></p>
        <h1 style="font-size:clamp(1.5rem,4vw,2.25rem)"><?= te('verwaltung.hauptbuch_titel') ?></h1>
    </div>

    <?php if ($abweichung !== 0): ?>
        <div class="ms-fehler" role="alert" style="display:flex;flex-direction:column;gap:var(--space-3)">
            <h2 style="font-size:1.25rem"><?= te('verwaltung.abweichung_titel') ?></h2>
            <p style="font-size:1.5rem"><?= te('verwaltung.abweichung_betrag', ['betrag' => geld($abweichung)]) ?></p>
            <p><?= te('verwaltung.abweichung_text') ?></p>
        </div>
    <?php else: ?>
        <article class="card">
            <p class="card-title"><?= te('verwaltung.hauptbuch_in_ordnung') ?></p>
            <p class="card-meta"><?= te('verwaltung.hauptbuch_in_ordnung_text') ?></p>
        </article>
    <?php endif; ?>

    <p class="text-muted"><?= te('verwaltung.hauptbuch_nur_lesend') ?></p>

    <?php if ($liste['zeilen'] === []): ?>
        <article class="card">
            <p class="card-body"><?= te('verwaltung.hauptbuch_leer') ?></p>
        </article>
    <?php else: ?>
        <p class="text-muted"><?= te('verwaltung.anzahl_gesamt', ['anzahl' => $liste['anzahl']]) ?></p>

        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col"><?= te('verwaltung.spalte.vorgang') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.zeit') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.art') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.bezug') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.beschreibung') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.summe') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.buchungen') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.ausgeglichen') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($liste['zeilen'] as $vorgang): ?>
                    <tr>
                        <td><?= e((string) (int) $vorgang['id']) ?></td>
                        <td><?= e((string) $vorgang['angelegt_am']) ?></td>
                        <td><?= e((string) $vorgang['art']) ?></td>
                        <td>
                            <?= $vorgang['bezug_art'] === null
                                ? te('verwaltung.ohne')
                                : e((string) $vorgang['bezug_art'] . ' #' . (int) $vorgang['bezug_id']) ?>
                        </td>
                        <td><?= e((string) ($vorgang['beschreibung'] ?? '')) ?></td>
                        <td><?= e(geld((int) $vorgang['summe_cent'])) ?></td>
                        <td><?= e((string) (int) $vorgang['buchungen']) ?></td>
                        <td>
                            <?php if ($vorgang['ausgeglichen']): ?>
                                <span class="tag tag-neutral"><?= te('verwaltung.vorgang_ausgeglichen') ?></span>
                            <?php else: ?>
                                <span class="tag tag-accent"><?= te('verwaltung.vorgang_unausgeglichen') ?></span>
                            <?php endif; ?>
                        </td>
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
