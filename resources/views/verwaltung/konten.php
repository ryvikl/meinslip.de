<?php

declare(strict_types=1);

/**
 * Kontenliste mit Suche.
 *
 * Das Suchformular schickt GET und trägt deshalb bewusst KEIN Formularschutz-
 * Feld: Ein Token in der Adresszeile landet in Verlauf, Lesezeichen und
 * Referrer. Der Schutz gehört an die Formulare, die etwas ändern — und die
 * stehen in konto.php.
 *
 * @var array{zeilen: list<array<string,mixed>>, anzahl: int, seite: int, seiten: int, pro_seite: int} $liste
 * @var string $suche
 */

$blaettern = static fn (int $seite): string => '/verwaltung/konten?seite=' . $seite
    . ($suche === '' ? '' : '&suche=' . rawurlencode($suche));
?>
<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('verwaltung.konten_kicker') ?></p>
        <h1 style="font-size:clamp(1.5rem,4vw,2.25rem)"><?= te('verwaltung.konten_titel') ?></h1>
    </div>

    <form method="get" action="/verwaltung/konten" class="ms-formular">
        <div class="field">
            <label for="suche"><?= te('verwaltung.konten_suche') ?></label>
            <input class="input" type="search" id="suche" name="suche" value="<?= e($suche) ?>"
                   autocapitalize="none" autocomplete="off">
        </div>
        <button class="btn btn-primary" type="submit"><?= te('verwaltung.konten_suche_absenden') ?></button>
    </form>

    <?php if ($liste['zeilen'] === []): ?>
        <article class="card">
            <p class="card-body"><?= te('verwaltung.konten_leer') ?></p>
        </article>
    <?php else: ?>
        <p class="text-muted"><?= te('verwaltung.anzahl_gesamt', ['anzahl' => $liste['anzahl']]) ?></p>

        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col"><?= te('verwaltung.spalte.kennung') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.pseudonym') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.email') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.status') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.faehigkeiten') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.guthaben') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.angelegt') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.zuletzt_aktiv') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.aktion') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($liste['zeilen'] as $zeile): ?>
                    <tr>
                        <td><?= e((string) $zeile['id']) ?></td>
                        <td><?= e((string) $zeile['pseudonym']) ?></td>
                        <td><?= e((string) $zeile['email']) ?></td>
                        <td><?= te('verwaltung.konto_status.' . $zeile['status']) ?></td>
                        <td>
                            <?php if ($zeile['faehigkeiten'] === []): ?>
                                <span class="text-muted"><?= te('verwaltung.ohne_faehigkeit') ?></span>
                            <?php else: ?>
                                <?php foreach ($zeile['faehigkeiten'] as $faehigkeit): ?>
                                    <span class="tag tag-accent"><?= te('verwaltung.faehigkeit.' . $faehigkeit) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td><?= e(geld((int) $zeile['guthaben_cent'])) ?></td>
                        <td><?= e((string) $zeile['angelegt_am']) ?></td>
                        <td>
                            <?php if ($zeile['zuletzt_aktiv_am'] === null): ?>
                                <span class="text-muted"><?= te('verwaltung.nie') ?></span>
                            <?php else: ?>
                                <?= e((string) $zeile['zuletzt_aktiv_am']) ?>
                            <?php endif; ?>
                        </td>
                        <td><a href="/verwaltung/konten/<?= e((string) (int) $zeile['id']) ?>"><?= te('verwaltung.konto_ansehen') ?></a></td>
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
