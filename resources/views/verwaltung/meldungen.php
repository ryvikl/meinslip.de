<?php

declare(strict_types=1);

/**
 * Meldungen nach Art. 16 DSA.
 *
 * Eine überschrittene Frist ist ein eigener Missstand und wird deshalb
 * ausgewiesen, nicht nur als Datum daneben gestellt.
 *
 * @var array{zeilen: list<array<string,mixed>>, anzahl: int, seite: int, seiten: int, pro_seite: int} $liste
 * @var string|null $status
 * @var list<string> $statuswerte
 * @var string|null $erfolg
 * @var string|null $fehler
 */

$blaettern = static fn (int $seite): string => '/verwaltung/meldungen?seite=' . $seite
    . ($status === null ? '' : '&status=' . rawurlencode($status));
?>
<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('verwaltung.meldungen_kicker') ?></p>
        <h1 style="font-size:clamp(1.5rem,4vw,2.25rem)"><?= te('verwaltung.meldungen_titel') ?></h1>
    </div>

    <?php if ($fehler !== null): ?>
        <p class="ms-fehler" role="alert"><?= te('verwaltung.fehler.' . $fehler) ?></p>
    <?php endif; ?>
    <?php if ($erfolg !== null): ?>
        <p class="card" role="status"><?= te('verwaltung.erfolg.' . $erfolg) ?></p>
    <?php endif; ?>

    <?php // Filter über GET, deshalb ohne Formularschutz — er ändert nichts. ?>
    <form class="ms-formular" method="get" action="/verwaltung/meldungen">
        <div class="field">
            <label for="status"><?= te('verwaltung.filter_status') ?></label>
            <select class="input" id="status" name="status">
                <option value=""><?= te('verwaltung.filter_alle') ?></option>
                <?php foreach ($statuswerte as $wert): ?>
                    <option value="<?= e($wert) ?>"<?= $status === $wert ? ' selected' : '' ?>><?= te('verwaltung.meldung_status.' . $wert) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit"><?= te('verwaltung.filter_anwenden') ?></button>
    </form>

    <?php if ($liste['zeilen'] === []): ?>
        <article class="card">
            <p class="card-body"><?= te('verwaltung.meldungen_leer') ?></p>
        </article>
    <?php else: ?>
        <p class="text-muted"><?= te('verwaltung.anzahl_gesamt', ['anzahl' => $liste['anzahl']]) ?></p>

        <?php foreach ($liste['zeilen'] as $meldung): ?>
            <?php $kennung = (int) $meldung['id']; ?>
            <article class="card elev-sm">
                <p class="card-kicker"><?= te('verwaltung.spalte.eingegangen') ?>: <?= e((string) $meldung['angelegt_am']) ?></p>
                <h2 class="card-title"><?= e((string) $meldung['grund']) ?></h2>
                <p class="card-meta">
                    <?= te('verwaltung.spalte.gegenstand') ?>: <?= te('verwaltung.gegenstand.' . $meldung['gegenstand_art']) ?>
                    <?= e('#' . (int) $meldung['gegenstand_id']) ?>
                    · <?= te('verwaltung.spalte.status') ?>: <?= te('verwaltung.meldung_status.' . $meldung['status']) ?>
                    · <?= te('verwaltung.spalte.melder') ?>:
                    <?= $meldung['melder_pseudonym'] === null ? te('verwaltung.ohne') : e((string) $meldung['melder_pseudonym']) ?>
                </p>

                <?php if ($meldung['frist_ueberschritten']): ?>
                    <p><span class="tag tag-accent"><?= te('verwaltung.frist_ueberschritten') ?></span></p>
                <?php elseif ($meldung['zugesagt_bis'] !== null): ?>
                    <p class="card-meta"><?= te('verwaltung.spalte.frist') ?>: <?= e((string) $meldung['zugesagt_bis']) ?></p>
                <?php endif; ?>

                <?php if ($meldung['beschreibung'] !== null): ?>
                    <p class="card-body"><?= e((string) $meldung['beschreibung']) ?></p>
                <?php endif; ?>

                <?php if ($meldung['entscheidung'] !== null): ?>
                    <p class="card-meta"><?= te('verwaltung.spalte.entscheidung') ?>: <?= e((string) $meldung['entscheidung']) ?></p>
                <?php endif; ?>

                <form class="ms-formular" method="post" action="/verwaltung/meldungen/<?= e((string) $kennung) ?>">
                    <?= \MeinSlip\Http\Formularschutz::feld() ?>
                    <div class="field">
                        <label for="status-<?= e((string) $kennung) ?>"><?= te('verwaltung.meldung_neuer_status') ?></label>
                        <select class="input" id="status-<?= e((string) $kennung) ?>" name="status" required>
                            <?php foreach ($statuswerte as $wert): ?>
                                <option value="<?= e($wert) ?>"<?= (string) $meldung['status'] === $wert ? ' selected' : '' ?>><?= te('verwaltung.meldung_status.' . $wert) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="entscheidung-<?= e((string) $kennung) ?>"><?= te('verwaltung.meldung_entscheidung') ?></label>
                        <textarea class="input" id="entscheidung-<?= e((string) $kennung) ?>" name="entscheidung" rows="2" required></textarea>
                        <span class="hinweis"><?= te('verwaltung.meldung_entscheidung_hinweis') ?></span>
                    </div>
                    <button class="btn btn-primary" type="submit"><?= te('verwaltung.meldung_uebernehmen') ?></button>
                </form>
            </article>
        <?php endforeach; ?>

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
