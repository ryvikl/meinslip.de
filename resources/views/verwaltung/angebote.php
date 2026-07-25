<?php

declare(strict_types=1);

/**
 * Angebote, die auf eine Entscheidung warten — älteste zuerst.
 *
 * Als Karten und nicht als Tabelle: Zu jeder Zeile gehören eine Beschreibung
 * und zwei Formulare, und eine Ablehnung ohne Grund gibt es nicht.
 *
 * @var array{zeilen: list<array<string,mixed>>, anzahl: int, seite: int, seiten: int, pro_seite: int} $liste
 * @var int $verwalterId
 * @var string|null $erfolg
 * @var string|null $fehler
 */

$blaettern = static fn (int $seite): string => '/verwaltung/angebote?seite=' . $seite;
?>
<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('verwaltung.angebote_kicker') ?></p>
        <h1 style="font-size:clamp(1.5rem,4vw,2.25rem)"><?= te('verwaltung.angebote_titel') ?></h1>
    </div>

    <?php if ($fehler !== null): ?>
        <p class="ms-fehler" role="alert"><?= te('verwaltung.fehler.' . $fehler) ?></p>
    <?php endif; ?>
    <?php if ($erfolg !== null): ?>
        <p class="card" role="status"><?= te('verwaltung.erfolg.' . $erfolg) ?></p>
    <?php endif; ?>

    <?php if ($liste['zeilen'] === []): ?>
        <article class="card">
            <p class="card-body"><?= te('verwaltung.angebote_leer') ?></p>
        </article>
    <?php else: ?>
        <p class="text-muted"><?= te('verwaltung.anzahl_gesamt', ['anzahl' => $liste['anzahl']]) ?></p>

        <?php foreach ($liste['zeilen'] as $angebot): ?>
            <?php $kennung = (int) $angebot['id']; ?>
            <article class="card elev-sm">
                <p class="card-kicker"><?= te('verwaltung.spalte.eingereicht') ?>: <?= e((string) $angebot['angelegt_am']) ?></p>
                <h2 class="card-title"><?= e((string) $angebot['titel']) ?></h2>
                <p class="card-meta">
                    <?= te('verwaltung.spalte.verkaeufer') ?>: <?= e((string) $angebot['verkaeufer_pseudonym']) ?>
                    · <?= te('verwaltung.spalte.kategorie') ?>: <?= te('kategorie.' . $angebot['kategorie_schluessel']) ?>
                    · <?= te('verwaltung.spalte.preis') ?>: <?= e(geld((int) $angebot['grundpreis_cent'], (string) $angebot['waehrung'])) ?>
                </p>
                <p class="card-body"><?= e((string) $angebot['beschreibung']) ?></p>

                <?php if ((int) $angebot['verkaeufer_id'] === $verwalterId): ?>
                    <?php // Vier-Augen-Prinzip: Angebote::freigeben() weist das ohnehin ab. ?>
                    <p class="ms-fehler"><?= te('verwaltung.angebot_vier_augen') ?></p>
                <?php else: ?>
                    <form method="post" action="/verwaltung/angebote/<?= e((string) $kennung) ?>/freigeben">
                        <?= \MeinSlip\Http\Formularschutz::feld() ?>
                        <button class="btn btn-primary" type="submit"><?= te('verwaltung.angebot_freigeben') ?></button>
                    </form>

                    <form class="ms-formular" method="post" action="/verwaltung/angebote/<?= e((string) $kennung) ?>/ablehnen">
                        <?= \MeinSlip\Http\Formularschutz::feld() ?>
                        <div class="field">
                            <label for="grund-<?= e((string) $kennung) ?>"><?= te('verwaltung.angebot_grund') ?></label>
                            <textarea class="input" id="grund-<?= e((string) $kennung) ?>" name="grund" rows="2" required></textarea>
                            <span class="hinweis"><?= te('verwaltung.angebot_grund_hinweis') ?></span>
                        </div>
                        <button class="btn btn-ghost" type="submit"><?= te('verwaltung.angebot_ablehnen') ?></button>
                        <p class="hinweis"><?= te('verwaltung.angebot_abgelehnt_ziel') ?></p>
                    </form>
                <?php endif; ?>
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
