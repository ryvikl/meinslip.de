<?php

declare(strict_types=1);

/**
 * Neues Angebot anlegen.
 *
 * Das Angebot entsteht als Entwurf. Sichtbar wird es erst nach der Pruefung —
 * und einreichen laesst es sich erst, wenn mindestens eine Option als
 * Spezifikation angelegt ist. Beides steht als Hinweis am Formular, damit die
 * Reihenfolge niemanden ueberrascht.
 *
 * @var list<array<string,mixed>> $kategorien
 * @var string|null $fehler
 * @var array<string,string> $eingaben
 */

$kategorien ??= [];
$fehler ??= null;
$eingaben ??= [];
?>
<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('markt.anlegen_kicker') ?></p>
        <h1 style="font-size:clamp(1.75rem,4vw,2.5rem)"><?= te('markt.anlegen_titel') ?></h1>
        <p class="ms-hero__unterzeile" style="margin-top:var(--space-4)"><?= te('markt.anlegen_unterzeile') ?></p>
    </div>

    <?php if ($fehler !== null): ?>
        <p class="ms-fehler" role="alert"><?= te('markt.angebotsfehler.' . $fehler) ?></p>
    <?php endif; ?>

    <form class="ms-formular" method="post" action="/verkaufen/neu">
        <?= \MeinSlip\Http\Formularschutz::feld() ?>

        <div class="field">
            <label for="titel"><?= te('markt.feld_titel') ?></label>
            <input class="input" type="text" id="titel" name="titel" required maxlength="190"
                   value="<?= e($eingaben['titel'] ?? '') ?>">
            <span class="hinweis"><?= te('markt.hinweis_titel') ?></span>
        </div>

        <div class="field">
            <label for="kategorie_id"><?= te('markt.feld_kategorie') ?></label>
            <select class="input" id="kategorie_id" name="kategorie_id" required>
                <option value=""><?= te('markt.auswahl_bitte_waehlen') ?></option>
                <?php foreach ($kategorien as $kategorie): ?>
                    <option value="<?= (int) $kategorie['id'] ?>"
                        <?= ($eingaben['kategorie_id'] ?? '') === (string) $kategorie['id'] ? 'selected' : '' ?>><?= te('kategorie.' . (string) $kategorie['schluessel']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="beschreibung"><?= te('markt.feld_beschreibung') ?></label>
            <textarea class="input" id="beschreibung" name="beschreibung" rows="6"><?= e($eingaben['beschreibung'] ?? '') ?></textarea>
            <span class="hinweis"><?= te('markt.hinweis_beschreibung') ?></span>
        </div>

        <div class="field">
            <label for="grundpreis"><?= te('markt.feld_grundpreis') ?></label>
            <input class="input" type="number" id="grundpreis" name="grundpreis" required
                   min="0.01" step="0.01" inputmode="decimal"
                   value="<?= e($eingaben['grundpreis'] ?? '') ?>">
            <span class="hinweis"><?= te('markt.hinweis_grundpreis') ?></span>
        </div>

        <div class="field">
            <label for="bearbeitungstage"><?= te('markt.feld_bearbeitungstage') ?></label>
            <input class="input" type="number" id="bearbeitungstage" name="bearbeitungstage" required
                   min="1" max="90" step="1" inputmode="numeric"
                   value="<?= e($eingaben['bearbeitungstage'] ?? '3') ?>">
            <span class="hinweis"><?= te('markt.hinweis_bearbeitungstage') ?></span>
        </div>

        <div class="field">
            <label class="radio">
                <input type="checkbox" name="versand_moeglich" value="ja"
                    <?= ($eingaben['versand_moeglich'] ?? 'ja') === 'ja' ? 'checked' : '' ?>>
                <span class="dot"></span>
                <span><?= te('markt.feld_versand') ?></span>
            </label>
            <label class="radio">
                <input type="checkbox" name="uebergabe_moeglich" value="ja"
                    <?= ($eingaben['uebergabe_moeglich'] ?? '') === 'ja' ? 'checked' : '' ?>>
                <span class="dot"></span>
                <span><?= te('markt.feld_uebergabe') ?></span>
            </label>
        </div>

        <div class="field">
            <label for="uebergabe_region"><?= te('markt.feld_uebergabe_region') ?></label>
            <input class="input" type="text" id="uebergabe_region" name="uebergabe_region" maxlength="40"
                   value="<?= e($eingaben['uebergabe_region'] ?? '') ?>">
            <span class="hinweis"><?= te('markt.hinweis_uebergabe_region') ?></span>
        </div>

        <button class="btn btn-primary btn-block" type="submit"><?= te('markt.anlegen_absenden') ?></button>

        <p class="hinweis"><?= te('markt.anlegen_naechster_schritt') ?></p>
    </form>
</section>
