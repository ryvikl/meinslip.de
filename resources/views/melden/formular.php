<?php

declare(strict_types=1);

/**
 * Das Meldeformular nach Art. 16 DSA.
 *
 * OHNE ANMELDESCHRANKE. Es gibt in dieser Vorlage keine Abfrage, die das
 * Formular hinter $angemeldet versteckt — $angemeldet entscheidet allein
 * darueber, welcher Hinweis unter dem Knopf steht. Art. 16 Abs. 1 DSA kennt
 * keine Kontopflicht; wer hier eine einbaut, nimmt der Plattform die
 * Rechtsgrundlage dafuer, dass Angebote ohne Vorabpruefung erscheinen.
 *
 * Gegenstandsart und Kennung stehen versteckt im Formular. Sie kommen aus der
 * Adresszeile, sind aber in MeldeRouten gegen die Weissliste geprueft, bevor
 * sie hier ankommen: $gegenstand ist entweder null oder ein gueltiges Paar.
 * Ist es null, gibt es kein Formular — ein Feld, in das sich eine beliebige
 * Kennung tippen liesse, waere ein Fernausloeser fuer fremde Inhalte.
 *
 * @var array{art: string, id: int}|null $gegenstand
 * @var list<string>                     $gruende
 * @var string|null                      $fehler
 * @var array<string,string>             $eingaben
 * @var bool                             $angemeldet
 */

$gegenstand ??= null;
$gruende ??= [];
$fehler ??= null;
$eingaben ??= [];
$angemeldet ??= false;
?>
<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('melden.kicker') ?></p>
        <h1 style="font-size:clamp(1.75rem,4vw,2.5rem)"><?= te('melden.titel') ?></h1>
        <p class="ms-hero__unterzeile" style="margin-top:var(--space-4)"><?= te('melden.unterzeile') ?></p>
    </div>

    <?php if ($fehler !== null): ?>
        <p class="ms-fehler" role="alert"><?= te('melden.fehler.' . $fehler) ?></p>
    <?php endif; ?>

    <?php if ($gegenstand === null): ?>
        <article class="card elev-sm">
            <h2 class="card-title"><?= te('melden.ohne_gegenstand_titel') ?></h2>
            <p class="card-body"><?= te('melden.ohne_gegenstand_text') ?></p>
            <p><a class="btn btn-primary" href="/entdecken"><?= te('melden.ohne_gegenstand_knopf') ?></a></p>
        </article>
    <?php else: ?>
        <form class="ms-formular" method="post" action="/melden">
            <?= \MeinSlip\Http\Formularschutz::feld() ?>
            <input type="hidden" name="art" value="<?= e($gegenstand['art']) ?>">
            <input type="hidden" name="id" value="<?= (int) $gegenstand['id'] ?>">

            <?php // Was gemeldet wird, steht sichtbar da. Bewusst nur Art und
                  // Kennung und nicht der Titel: Den zu laden hiesse, ueber die
                  // blosse Adresse /melden?art=angebot&id=N bestaetigen zu
                  // lassen, welche Kennungen es gibt. ?>
            <p class="text-muted"><?= te('melden.gegenstand_zeile', [
                'gegenstand' => t('melden.art.' . $gegenstand['art']),
                'kennung' => $gegenstand['id'],
            ]) ?></p>

            <div class="field">
                <label for="grund"><?= te('melden.feld_grund') ?></label>
                <select class="input" id="grund" name="grund" required>
                    <option value=""><?= te('melden.auswahl_bitte_waehlen') ?></option>
                    <?php foreach ($gruende as $grund): ?>
                        <option value="<?= e($grund) ?>"
                            <?= ($eingaben['grund'] ?? '') === $grund ? 'selected' : '' ?>><?= te('melden.grund.' . $grund) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="hinweis"><?= te('melden.hinweis_grund') ?></span>
            </div>

            <?php // Pflichtfeld, und zwar aus Rechtsgruenden: Art. 16 Abs. 2
                  // lit. a DSA verlangt eine hinreichend begruendete
                  // Erlaeuterung. 'required' ist dabei nur die Bequemlichkeit —
                  // abgewiesen wird eine leere Erlaeuterung serverseitig.
                  // maxlength spiegelt die Grenze aus Meldungen; massgeblich
                  // ist dort die Fachklasse, nicht diese Zahl. ?>
            <div class="field">
                <label for="beschreibung"><?= te('melden.feld_beschreibung') ?></label>
                <textarea class="input" id="beschreibung" name="beschreibung" rows="6" required
                          maxlength="2000"><?= e($eingaben['beschreibung'] ?? '') ?></textarea>
                <span class="hinweis"><?= te('melden.hinweis_beschreibung') ?></span>
            </div>

            <?php // Art. 23 Abs. 2 DSA: Wer haeufig offensichtlich unbegruendet
                  // meldet, kann von der Meldemoeglichkeit ausgeschlossen
                  // werden. Der Hinweis steht VOR dem Knopf, weil er danach
                  // niemanden mehr erreicht. ?>
            <p class="hinweis" role="note"><?= te('melden.warnung_missbrauch') ?></p>

            <button class="btn btn-primary btn-block" type="submit"><?= te('melden.absenden') ?></button>

            <?php // 'text-muted' zusaetzlich zu 'hinweis': '.hinweis' ist nur als
                  // '.field .hinweis' gestylt (app.css), ein freistehender
                  // Hinweis bliebe sonst in voller Groesse stehen. Das ist beim
                  // Missbrauchshinweis darueber gewollt und hier nicht. ?>
            <?php if ($angemeldet): ?>
                <p class="hinweis text-muted"><?= te('melden.hinweis_bestaetigung') ?></p>
            <?php else: ?>
                <p class="hinweis text-muted"><?= te('melden.hinweis_anonym') ?></p>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</section>
