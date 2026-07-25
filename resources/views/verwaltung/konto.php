<?php

declare(strict_types=1);

/**
 * Einzelnes Konto: alles, was für eine Entscheidung nötig ist, auf einer Seite.
 *
 * Jede Entscheidung braucht hier eine Begründung — das Feld ist nicht
 * Höflichkeit, sondern Art. 17 DSA. Ohne sie weist die Fachklasse ab.
 *
 * @var array<string,mixed> $daten
 * @var list<string> $alleFaehigkeiten
 * @var string $nichtVergebbar
 * @var bool $eigenesKonto
 * @var string|null $erfolg
 * @var string|null $fehler
 */

$konto = $daten['konto'];
$kennung = (int) $konto['id'];
$gesperrt = (string) $konto['status'] === 'gesperrt';
?>
<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('verwaltung.konto_kicker') ?></p>
        <h1 style="font-size:clamp(1.5rem,4vw,2.25rem)"><?= e((string) $konto['pseudonym']) ?></h1>
    </div>

    <?php if ($fehler !== null): ?>
        <p class="ms-fehler" role="alert"><?= te('verwaltung.fehler.' . $fehler) ?></p>
    <?php endif; ?>
    <?php if ($erfolg !== null): ?>
        <p class="card" role="status"><?= te('verwaltung.erfolg.' . $erfolg) ?></p>
    <?php endif; ?>

    <article class="card">
        <div style="overflow-x:auto">
            <table class="table">
                <tbody>
                <tr>
                    <th scope="row"><?= te('verwaltung.konto_kennung') ?></th>
                    <td><?= e((string) $kennung) ?></td>
                </tr>
                <tr>
                    <th scope="row"><?= te('verwaltung.spalte.email') ?></th>
                    <td><?= e((string) $konto['email']) ?></td>
                </tr>
                <tr>
                    <th scope="row"><?= te('verwaltung.spalte.status') ?></th>
                    <td><?= te('verwaltung.konto_status.' . $konto['status']) ?></td>
                </tr>
                <tr>
                    <th scope="row"><?= te('verwaltung.konto_guthaben') ?></th>
                    <td><?= e(geld((int) $daten['guthaben_cent'])) ?></td>
                </tr>
                <tr>
                    <th scope="row"><?= te('verwaltung.konto_einnahmen') ?></th>
                    <td><?= e(geld((int) $daten['einnahmen_cent'])) ?></td>
                </tr>
                <tr>
                    <th scope="row"><?= te('verwaltung.konto_angelegt') ?></th>
                    <td><?= e((string) $konto['angelegt_am']) ?></td>
                </tr>
                <tr>
                    <th scope="row"><?= te('verwaltung.konto_zuletzt_aktiv') ?></th>
                    <td><?= $konto['zuletzt_aktiv_am'] === null ? te('verwaltung.nie') : e((string) $konto['zuletzt_aktiv_am']) ?></td>
                </tr>
                </tbody>
            </table>
        </div>
    </article>
</section>

<section class="ms-abschnitt">
    <h2><?= te('verwaltung.faehigkeiten_titel') ?></h2>

    <p>
        <?php if ($daten['faehigkeiten'] === []): ?>
            <span class="text-muted"><?= te('verwaltung.ohne_faehigkeit') ?></span>
        <?php else: ?>
            <?php foreach ($daten['faehigkeiten'] as $faehigkeit): ?>
                <span class="tag tag-accent"><?= te('verwaltung.faehigkeit.' . $faehigkeit) ?></span>
            <?php endforeach; ?>
        <?php endif; ?>
    </p>

    <form class="ms-formular" method="post" action="/verwaltung/konten/<?= e((string) $kennung) ?>/faehigkeit">
        <?= \MeinSlip\Http\Formularschutz::feld() ?>
        <div class="field">
            <label for="faehigkeit"><?= te('verwaltung.faehigkeit_waehlen') ?></label>
            <select class="input" id="faehigkeit" name="faehigkeit" required>
                <?php foreach ($alleFaehigkeiten as $faehigkeit): ?>
                    <option value="<?= e($faehigkeit) ?>"><?= te('verwaltung.faehigkeit.' . $faehigkeit) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="hinweis"><?= te('verwaltung.faehigkeit_verwalten_hinweis') ?></span>
        </div>

        <div class="field">
            <label for="begruendung_faehigkeit"><?= te('verwaltung.begruendung') ?></label>
            <textarea class="input" id="begruendung_faehigkeit" name="begruendung" rows="3" required></textarea>
            <span class="hinweis"><?= te('verwaltung.begruendung_hinweis') ?></span>
        </div>

        <div style="display:flex;gap:var(--space-3);flex-wrap:wrap">
            <button class="btn btn-primary" type="submit" name="handlung" value="freischalten"><?= te('verwaltung.faehigkeit_freischalten') ?></button>
            <button class="btn btn-ghost" type="submit" name="handlung" value="entziehen"><?= te('verwaltung.faehigkeit_entziehen') ?></button>
        </div>
    </form>
</section>

<section class="ms-abschnitt">
    <h2><?= te('verwaltung.sperre_titel') ?></h2>
    <p class="text-muted"><?= te('verwaltung.sperre_wirkung') ?></p>

    <?php if ($eigenesKonto): ?>
        <?php // Die Fachklasse weist das ohnehin ab; hier steht der Grund, statt in einen Fehler zu laufen. ?>
        <p class="ms-fehler"><?= te('verwaltung.eigenes_konto') ?></p>
    <?php else: ?>
        <form class="ms-formular" method="post" action="/verwaltung/konten/<?= e((string) $kennung) ?>/sperren">
            <?= \MeinSlip\Http\Formularschutz::feld() ?>
            <div class="field">
                <label for="begruendung_sperre"><?= te('verwaltung.begruendung') ?></label>
                <textarea class="input" id="begruendung_sperre" name="begruendung" rows="3" required></textarea>
                <span class="hinweis"><?= te('verwaltung.begruendung_hinweis') ?></span>
            </div>

            <?php if ($gesperrt): ?>
                <button class="btn btn-primary" type="submit" name="handlung" value="entsperren"><?= te('verwaltung.konto_entsperren') ?></button>
            <?php else: ?>
                <button class="btn btn-primary" type="submit" name="handlung" value="sperren"><?= te('verwaltung.konto_sperren') ?></button>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</section>

<section class="ms-abschnitt">
    <h2><?= te('verwaltung.pruefungen_titel') ?></h2>

    <?php if ($daten['pruefungen'] === []): ?>
        <p class="text-muted"><?= te('verwaltung.pruefungen_leer') ?></p>
    <?php else: ?>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col"><?= te('verwaltung.spalte.art') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.anbieter') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.status') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.volljaehrig') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.geprueft') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.gueltig_bis') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($daten['pruefungen'] as $pruefung): ?>
                    <tr>
                        <td><?= e((string) $pruefung['art']) ?></td>
                        <td><?= e((string) $pruefung['anbieter']) ?></td>
                        <td><?= te('verwaltung.pruefung_status.' . $pruefung['status']) ?></td>
                        <td><?= ((int) $pruefung['volljaehrig']) === 1 ? te('verwaltung.ja') : te('verwaltung.nein') ?></td>
                        <td><?= $pruefung['geprueft_am'] === null ? te('verwaltung.ohne') : e((string) $pruefung['geprueft_am']) ?></td>
                        <td><?= $pruefung['gueltig_bis'] === null ? te('verwaltung.ohne') : e((string) $pruefung['gueltig_bis']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="ms-abschnitt">
    <h2><?= te('verwaltung.bestellungen_titel') ?></h2>

    <?php if ($daten['bestellungen'] === []): ?>
        <p class="text-muted"><?= te('verwaltung.bestellungen_leer') ?></p>
    <?php else: ?>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col"><?= te('verwaltung.spalte.nummer') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.rolle') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.zustand') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.summe') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.angelegt') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($daten['bestellungen'] as $bestellung): ?>
                    <tr>
                        <td><?= e((string) $bestellung['nummer']) ?></td>
                        <td><?= te('verwaltung.rolle.' . $bestellung['rolle']) ?></td>
                        <td><?= te('verwaltung.bestellzustand.' . $bestellung['zustand']) ?></td>
                        <td><?= e(geld((int) $bestellung['summe_verkauf_cent'], (string) $bestellung['waehrung'])) ?></td>
                        <td><?= e((string) $bestellung['angelegt_am']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="ms-abschnitt">
    <h2><?= te('verwaltung.meldungen_gegen_titel') ?></h2>

    <?php if ($daten['meldungen'] === []): ?>
        <p class="text-muted"><?= te('verwaltung.meldungen_gegen_leer') ?></p>
    <?php else: ?>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col"><?= te('verwaltung.spalte.kennung') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.grund') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.status') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.melder') ?></th>
                    <th scope="col"><?= te('verwaltung.spalte.eingegangen') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($daten['meldungen'] as $meldung): ?>
                    <tr>
                        <td><?= e((string) $meldung['id']) ?></td>
                        <td><?= e((string) $meldung['grund']) ?></td>
                        <td><?= te('verwaltung.meldung_status.' . $meldung['status']) ?></td>
                        <td><?= $meldung['melder_pseudonym'] === null ? te('verwaltung.ohne') : e((string) $meldung['melder_pseudonym']) ?></td>
                        <td><?= e((string) $meldung['angelegt_am']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
