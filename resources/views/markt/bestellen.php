<?php

declare(strict_types=1);

/**
 * Das Bestellformular — der Konfigurator.
 *
 * Wird aus resources/views/markt/angebot.php eingebunden und erbt dessen
 * Variablen. Eigenstaendig, weil hier der rechtlich empfindlichste Teil der
 * Oberflaeche steht:
 *
 *  1. PFLICHTFELDER TRAGEN 'required'. Das ist Bequemlichkeit, keine
 *     Sicherung — app/Http/MarktRouten.php prueft dasselbe noch einmal.
 *
 *  2. VOR DEM ABSENDEN STEHT DIE RECHNUNG. Grundpreis, jede Option mit ihrem
 *     Aufpreis und die Summe sind sichtbar, bevor der Knopf erreichbar ist.
 *     Ohne JavaScript bleibt die Summe der Grundpreis; jede Option nennt
 *     ihren Aufpreis dann an Ort und Stelle.
 *
 *  3. VOR DEM ABSENDEN STEHT DIE UNTERRICHTUNG. § 312d Abs. 1 BGB i. V. m.
 *     Art. 246a § 1 Abs. 3 Nr. 1 EGBGB verlangt die Information ueber den
 *     Ausschluss des Widerrufsrechts VOR Abgabe der Vertragserklaerung —
 *     nicht in den AGB und nicht auf der Bestaetigungsseite.
 *
 * @var array<string,mixed> $angebot
 * @var list<array<string,mixed>> $optionen
 * @var array<string,string> $eingaben
 * @var string $waehrung
 * @var int $grundpreis
 * @var list<string> $lieferwege
 * @var string $gewaehlteLieferart
 */
?>
<form class="ms-formular" method="post" action="/bestellen/<?= (int) $angebot['id'] ?>"
      data-konfigurator data-grundpreis="<?= $grundpreis ?>">
    <?= \MeinSlip\Http\Formularschutz::feld() ?>

    <?php foreach ($optionen as $option): ?>
        <?php
        $schluessel = (string) $option['schluessel'];
        $feldId = 'option_' . $schluessel;
        $wert = (string) ($eingaben[$feldId] ?? '');
        $aufpreis = (int) $option['aufpreis_cent'];
        $istPflicht = (int) $option['pflicht'] === 1;
        $istSpezifikation = (int) $option['ist_spezifikation'] === 1;
        $merkmale = 'data-option data-aufpreis="' . $aufpreis . '" data-spezifikation="'
            . ($istSpezifikation ? 'ja' : 'nein') . '"';
        ?>
        <div class="field">
            <?php if ((string) $option['art'] === \MeinSlip\Domain\Catalog\Angebote::ART_AUSWAHL): ?>
                <?php // Ankreuzen statt Auswahlliste: app.js zaehlt ein Kaestchen ueber
                      // .checked, eine Liste dagegen ueber value !== "" — dort wuerde
                      // auch ein abwaehlendes "nein" den Aufpreis mitrechnen. ?>
                <label class="radio">
                    <input type="checkbox" id="<?= e($feldId) ?>" name="<?= e($feldId) ?>" value="ja"
                           <?= $merkmale ?> <?= $wert === 'ja' ? 'checked' : '' ?> <?= $istPflicht ? 'required' : '' ?>>
                    <span class="dot"></span>
                    <span><?= e((string) $option['bezeichnung']) ?></span>
                </label>
            <?php elseif ((string) $option['art'] === \MeinSlip\Domain\Catalog\Angebote::ART_ZAHL): ?>
                <label for="<?= e($feldId) ?>"><?= e((string) $option['bezeichnung']) ?></label>
                <input class="input" type="number" id="<?= e($feldId) ?>" name="<?= e($feldId) ?>"
                       value="<?= e($wert) ?>" min="0" step="1" inputmode="numeric"
                       <?= $merkmale ?> <?= $istPflicht ? 'required' : '' ?>>
            <?php else: ?>
                <label for="<?= e($feldId) ?>"><?= e((string) $option['bezeichnung']) ?></label>
                <input class="input" type="text" id="<?= e($feldId) ?>" name="<?= e($feldId) ?>"
                       value="<?= e($wert) ?>" maxlength="190"
                       <?= $merkmale ?> <?= $istPflicht ? 'required' : '' ?>>
            <?php endif; ?>

            <span class="hinweis">
                <?= $aufpreis > 0
                    ? te('markt.konfigurator_aufpreis', ['betrag' => geld($aufpreis, $waehrung)])
                    : te('markt.konfigurator_ohne_aufpreis') ?>
                <?php if ($istSpezifikation): ?>
                    <span class="tag tag-accent-2"><?= te('markt.konfigurator_spezifikation') ?></span>
                <?php endif; ?>
                <?php if ($istPflicht): ?>
                    <span class="tag tag-accent"><?= te('markt.konfigurator_pflicht') ?></span>
                <?php endif; ?>
            </span>

            <?php if (($option['erlaeuterung'] ?? null) !== null): ?>
                <span class="hinweis"><?= e((string) $option['erlaeuterung']) ?></span>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if (count($lieferwege) > 1): ?>
        <div class="field">
            <label for="lieferart"><?= te('markt.konfigurator_lieferart') ?></label>
            <div class="seg" id="lieferart">
                <?php foreach ($lieferwege as $weg): ?>
                    <label class="seg-opt">
                        <input type="radio" name="lieferart" value="<?= e($weg) ?>"
                               <?= $gewaehlteLieferart === $weg ? 'checked' : '' ?>>
                        <span><?= te('markt.konfigurator_lieferart_' . $weg) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <input type="hidden" name="lieferart" value="<?= e($gewaehlteLieferart) ?>">
        <p class="hinweis"><?= te('markt.konfigurator_lieferart') ?>:
            <?= te('markt.konfigurator_lieferart_' . ($gewaehlteLieferart === '' ? 'versand' : $gewaehlteLieferart)) ?></p>
    <?php endif; ?>

    <article class="card elev-sm">
        <p class="card-kicker"><?= te('markt.konfigurator_summe_titel') ?></p>
        <p class="card-body"><?= te('markt.konfigurator_summe_grundpreis') ?>: <?= e(geld($grundpreis, $waehrung)) ?></p>
        <p class="card-title"><?= te('markt.konfigurator_summe_gesamt') ?>:
            <span data-summe><?= e(geld($grundpreis, $waehrung)) ?></span>
        </p>
        <p class="card-meta"><?= te('markt.konfigurator_summe_hinweis') ?></p>
    </article>

    <article class="card elev-sm">
        <h3 class="card-title"><?= te('markt.widerruf_titel') ?></h3>
        <p class="card-body"><?= te('markt.widerruf_text') ?></p>
        <label class="radio">
            <input type="checkbox" name="widerruf_verstanden" value="ja" required
                   <?= ($eingaben['widerruf_verstanden'] ?? '') === 'ja' ? 'checked' : '' ?>>
            <span class="dot"></span>
            <span><?= te('markt.widerruf_bestaetigung') ?></span>
        </label>
    </article>

    <p class="ms-fehler" data-spezifikation-warnung role="alert" hidden><?= te('markt.konfigurator_warnung') ?></p>

    <button class="btn btn-primary btn-block" type="submit" data-absenden><?= te('markt.konfigurator_absenden') ?></button>
</form>
