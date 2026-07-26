<?php

declare(strict_types=1);

/**
 * Arbeitsliste der manuellen Identitätsprüfung.
 *
 * DIESE LISTE HAT EINE FRIST, DIE ANDEREN NICHT. Ein liegengebliebenes
 * gemeldetes Angebot bleibt liegen. Ein liegengebliebener Prüfbeleg
 * verschwindet — spätestens :tage Tage nach dem Start des Vorgangs löscht ihn
 * bin/pflege ungesehen, und die Person hat ihr Foto umsonst hochgeladen.
 * Deshalb steht das Löschdatum in einer eigenen Spalte und nicht im
 * Kleingedruckten.
 *
 * Absichtlich KEINE Bildvorschau in der Liste. Wer die Übersicht öffnet, will
 * wissen, wie viel Arbeit ansteht — nicht ein Dutzend Gesichter auf einem
 * Bildschirm sehen, der in einem Großraumbüro stehen kann. Das Bild gibt es
 * eine Ebene tiefer, einzeln und bewusst aufgerufen.
 *
 * @var array{zeilen: list<array<string,mixed>>, anzahl: int, seite: int, seiten: int, pro_seite: int} $liste
 * @var int $tage
 * @var int $entscheidungstage
 * @var string|null $erfolg
 * @var string|null $fehler
 */

$blaettern = static fn (int $seite): string => '/verwaltung/verifizierung?seite=' . $seite;
?>
<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('verwaltung.belege_kicker') ?></p>
        <h1 style="font-size:clamp(1.5rem,4vw,2.25rem)"><?= te('verwaltung.belege_titel') ?></h1>
        <p class="ms-hero__unterzeile" style="margin-top:var(--space-4)"><?= te('verwaltung.belege_erklaerung', ['tage' => (int) $tage, 'entscheidungstage' => (int) $entscheidungstage]) ?></p>
    </div>

    <?php if ($fehler !== null): ?>
        <p class="ms-fehler" role="alert"><?= te('verwaltung.fehler.' . $fehler) ?></p>
    <?php endif; ?>
    <?php if ($erfolg !== null): ?>
        <p class="card" role="status"><?= te('verwaltung.erfolg.' . $erfolg) ?></p>
    <?php endif; ?>

    <article class="card">
        <p class="card-body"><?= te('verwaltung.belege_kein_ausweis') ?></p>
    </article>

    <?php if ($liste['zeilen'] === []): ?>
        <article class="card">
            <p class="card-body"><?= te('verwaltung.belege_leer') ?></p>
        </article>
    <?php else: ?>
        <div class="ms-breit" role="region" tabindex="0" aria-label="<?= te('verwaltung.tabelle_belege') ?>" style="overflow-x:auto">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col"><?= te('verwaltung.spalte.kennung') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.pseudonym') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.code') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.eingereicht') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.loeschen_ab') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.aktion') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($liste['zeilen'] as $beleg): ?>
                    <tr>
                        <td><?= e((string) $beleg['id']) ?></td>
                        <td><?= e((string) $beleg['pseudonym']) ?></td>
                        <td style="letter-spacing:0.08em"><?= e((string) $beleg['code']) ?></td>
                        <td><?= e((string) ($beleg['eingereicht_am'] ?? '')) ?></td>
                        <td><?= e((string) $beleg['loeschen_ab']) ?></td>
                        <td>
                            <a class="btn btn-ghost" href="/verwaltung/verifizierung/<?= (int) $beleg['id'] ?>"><?= te('verwaltung.beleg_ansehen') ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="text-muted"><?= te('verwaltung.anzahl_gesamt', ['anzahl' => (int) $liste['anzahl']]) ?></p>

        <?php if ($liste['seiten'] > 1): ?>
            <p>
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
