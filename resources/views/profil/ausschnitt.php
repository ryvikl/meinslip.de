<?php

declare(strict_types=1);

/**
 * Ausschnitt des Profilbilds wählen.
 *
 * Die drei Schieberegler SIND das Formular — sie funktionieren ohne
 * JavaScript. Mit JavaScript kommt die Vorschau dazu und das Bild lässt sich
 * mit dem Finger verschieben; beides stellt nur dieselben drei Regler.
 *
 * Gerechnet wird der Ausschnitt auf dem Server (Ausschnitt::rechteck). Die
 * Vorschau im Browser rechnet dieselbe Formel nach, damit das, was man sieht,
 * das ist, was gespeichert wird.
 *
 * @var int $zoomMin
 * @var int $zoomMax
 */
?>
<section class="ms-abschnitt ms-schmal">
    <div>
        <p class="ms-kicker"><?= te('profil.ausschnitt_kicker') ?></p>
        <h1><?= te('profil.ausschnitt_titel') ?></h1>
        <p class="hinweis"><?= te('profil.ausschnitt_text') ?></p>
    </div>

    <form method="post" action="/profil/bild/ausschnitt" class="card elev-sm ms-formular" data-ausschnitt>
        <?= \MeinSlip\Http\Formularschutz::feld() ?>

        <?php // Ohne JavaScript bleibt die Bühne verborgen: Sie zeigte sonst ein
              // Bild, das auf die Regler nicht reagiert. ?>
        <div class="ms-ausschnitt" hidden data-ausschnitt-buehne
             role="img" aria-label="<?= te('profil.ausschnitt_vorschau') ?>">
            <img src="/profil/bild/original" alt="" draggable="false" data-ausschnitt-bild>
        </div>

        <div class="field">
            <label for="ausschnitt-zoom"><?= te('profil.ausschnitt_zoom') ?></label>
            <input type="range" id="ausschnitt-zoom" name="zoom" min="<?= $zoomMin ?>" max="<?= $zoomMax ?>" value="<?= $zoomMin ?>" step="1">
        </div>
        <div class="field" data-ausschnitt-fein>
            <label for="ausschnitt-x"><?= te('profil.ausschnitt_x') ?></label>
            <input type="range" id="ausschnitt-x" name="x" min="0" max="100" value="50" step="1">
        </div>
        <div class="field" data-ausschnitt-fein>
            <label for="ausschnitt-y"><?= te('profil.ausschnitt_y') ?></label>
            <input type="range" id="ausschnitt-y" name="y" min="0" max="100" value="50" step="1">
        </div>

        <div class="ms-zeile">
            <button class="btn btn-primary" type="submit"><?= te('profil.ausschnitt_speichern') ?></button>
            <a class="btn btn-ghost" href="/profil/bearbeiten"><?= te('profil.ausschnitt_abbrechen') ?></a>
        </div>
    </form>
</section>
