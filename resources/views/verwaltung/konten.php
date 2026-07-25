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
 * KEINE E-MAIL-SPALTE. Zum Finden eines Kontos genügen Pseudonym und Kennung;
 * gesucht wird weiterhin auch über die E-Mail (WHERE in Verwaltung::konten()),
 * angezeigt wird sie erst auf der Einzelseite, wo tatsächlich entschieden wird.
 * Eine Spalte hier machte aus jeder Seite dieser durchblätterbaren Liste einen
 * vollständigen Adressexport — auf einer Plattform für getragene Wäsche ist die
 * E-Mail das Bindeglied zur bürgerlichen Identität. Art. 5 Abs. 1 lit. c DSGVO
 * verlangt die Beschränkung auf das für den Zweck notwendige Maß.
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

        <?php
        /*
         * tabindex="0" ist keine Zierde: Der Behaelter scrollt waagerecht, ist
         * ohne ihn aber selbst nicht fokussierbar, und die mittleren Spalten
         * tragen keinen fokussierbaren Inhalt, ueber den man sie ins Bild holen
         * koennte. Chrome macht Bildlaufbehaelter seit 127 von sich aus
         * fokussierbar, Safari nicht — und iOS ist hier die Hauptplattform.
         * WCAG 2.1.1: Was die Maus erreicht, muss die Tastatur auch erreichen.
         * role="region" braucht dazu zwingend einen Namen, sonst steht der
         * Bereich namenlos in der Landmarkenliste.
         */
        ?>
        <div class="ms-breit" tabindex="0" role="region" aria-label="<?= te('verwaltung.tabelle_konten') ?>">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col"><?= te('verwaltung.spalte.kennung') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.pseudonym') ?></th>
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
