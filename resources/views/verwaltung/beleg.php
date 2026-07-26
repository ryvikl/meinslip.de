<?php

declare(strict_types=1);

/**
 * Ein einzelner Prüfbeleg — der eingriffsintensivste Bildschirm des Bereichs.
 *
 * Hier liegt das Gesicht einer Nutzerin auf dem Schirm. Drei Dinge stehen
 * deshalb neben dem Bild und nicht in einer Anleitung:
 *
 *  1. WAS ZU VERGLEICHEN IST. Code und Datum auf dem Zettel gegen Code und
 *     Ausgabezeitpunkt in der Tabelle. Nur diese beiden Angaben binden das
 *     Foto an diesen Vorgang und diesen Zeitraum — alles andere ist Eindruck.
 *
 *  2. WAS NICHT GEPRÜFT WIRD. Nicht das Alter. Wer hier freigibt, bestätigt
 *     einen Menschen mit einem Zettel und nichts weiter. Es gibt auf dieser
 *     Seite kein Feld für ein Geburtsdatum, und pruefungen.volljaehrig bleibt
 *     bei dieser Prüfung immer 0.
 *
 *  3. WAS DANACH GESCHIEHT. Mit der Entscheidung beginnt die kurze Frist: Das
 *     Foto ist :entscheidungstage Tage später gelöscht. Wer eine Beschwerde
 *     erwartet, muss innerhalb dieser Frist entscheiden — danach gibt es den
 *     Gegenstand nicht mehr, nur noch das Ergebnis.
 *
 * @var array<string,mixed> $beleg
 * @var bool $eigenesKonto
 * @var string $statusEingereicht
 * @var int $entscheidungstage
 * @var string|null $erfolg
 * @var string|null $fehler
 */

$kennung = (int) $beleg['id'];
$offen = (string) $beleg['status'] === $statusEingereicht;
$hatBild = ($beleg['pfad'] ?? null) !== null;
?>
<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('verwaltung.beleg_kicker') ?></p>
        <h1 style="font-size:clamp(1.5rem,4vw,2.25rem)"><?= e((string) $beleg['pseudonym']) ?></h1>
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
                    <th scope="row"><?= te('verwaltung.spalte.kennung') ?></th>
                    <td><?= e((string) $kennung) ?></td>
                </tr>
                <tr>
                    <th scope="row"><?= te('verwaltung.spalte.code') ?></th>
                    <td style="letter-spacing:0.12em;font-size:1.25rem"><?= e((string) $beleg['code']) ?></td>
                </tr>
                <tr>
                    <th scope="row"><?= te('verwaltung.spalte.code_ausgegeben') ?></th>
                    <td><?= e((string) $beleg['code_ausgegeben_am']) ?></td>
                </tr>
                <tr>
                    <th scope="row"><?= te('verwaltung.spalte.status') ?></th>
                    <td><?= te('verwaltung.beleg_status.' . $beleg['status']) ?></td>
                </tr>
                <tr>
                    <th scope="row"><?= te('verwaltung.spalte.eingereicht') ?></th>
                    <td><?= $beleg['eingereicht_am'] === null ? te('verwaltung.ohne') : e((string) $beleg['eingereicht_am']) ?></td>
                </tr>
                <tr>
                    <th scope="row"><?= te('verwaltung.spalte.loeschen_ab') ?></th>
                    <td><?= e((string) $beleg['loeschen_ab']) ?></td>
                </tr>
                </tbody>
            </table>
        </div>
    </article>

    <article class="card">
        <p class="card-body"><?= te('verwaltung.beleg_vergleich') ?></p>
        <p class="card-meta"><?= te('verwaltung.beleg_kein_alter') ?></p>
    </article>
</section>

<section class="ms-abschnitt" aria-labelledby="bild">
    <h2 id="bild"><?= te('verwaltung.beleg_bild_titel') ?></h2>

    <?php if (!$hatBild): ?>
        <p class="card"><?= te('verwaltung.beleg_ohne_bild') ?></p>
    <?php else: ?>
        <?php /*
               * Kein loading="lazy" und kein data:-URI. Das Bild wird über eine
               * eigene Route geholt, damit es nicht im HTML steht — HTML landet
               * im Verlauf, im Ausdruck und in jeder Bildschirmaufnahme. Die
               * Route setzt 'private, no-store'.
               */ ?>
        <article class="card elev-sm">
            <img src="/verwaltung/verifizierung/<?= $kennung ?>/bild"
                 alt="<?= te('verwaltung.beleg_bild_alt') ?>"
                 style="width:100%;height:auto;display:block;border-radius:var(--radius-md)">
        </article>
    <?php endif; ?>
</section>

<section class="ms-abschnitt">
    <h2><?= te('verwaltung.beleg_entscheiden_titel') ?></h2>

    <?php if ($eigenesKonto): ?>
        <?php // Die Route weist das ohnehin ab; hier steht der Grund, statt in
              // einen Fehler zu laufen. ?>
        <p class="ms-fehler"><?= te('verwaltung.eigenes_konto') ?></p>
    <?php elseif (!$offen): ?>
        <p class="card"><?= te('verwaltung.beleg_schon_entschieden') ?></p>

        <?php if (($beleg['entscheidung'] ?? null) !== null): ?>
            <article class="card">
                <p class="card-title" style="font-size:1rem"><?= te('verwaltung.spalte.begruendung') ?></p>
                <p class="card-body" style="white-space:pre-line;overflow-wrap:anywhere"><?= e((string) $beleg['entscheidung']) ?></p>
                <p class="card-meta"><?= e((string) ($beleg['entschieden_am'] ?? '')) ?></p>
            </article>
        <?php endif; ?>
    <?php else: ?>
        <p class="text-muted"><?= te('verwaltung.beleg_frist_hinweis', ['entscheidungstage' => (int) $entscheidungstage]) ?></p>

        <?php /*
               * Zwei Formulare statt eines mit zwei Knöpfen: Freigabe und
               * Ablehnung gehen an verschiedene Adressen, damit sich einer
               * Anfrage ansehen lässt, was sie bewirkt. Ein gemeinsames
               * Formular mit name="handlung" wäre die Stelle, an der ein
               * verrutschter oder weggelassener Wert die falsche Entscheidung
               * auslöst — VerwaltungsRouten hat genau diesen Fehler bei Sperre
               * und Fähigkeit schon einmal gehabt.
               */ ?>
        <form class="ms-formular" method="post" action="/verwaltung/verifizierung/<?= $kennung ?>/freigeben">
            <?= \MeinSlip\Http\Formularschutz::feld() ?>
            <div class="field">
                <label for="grund_freigabe"><?= te('verwaltung.beleg_grund_freigabe') ?></label>
                <textarea class="input" id="grund_freigabe" name="grund" rows="3" required></textarea>
                <span class="hinweis"><?= te('verwaltung.beleg_grund_freigabe_hinweis') ?></span>
            </div>
            <button class="btn btn-primary" type="submit"><?= te('verwaltung.beleg_freigeben') ?></button>
        </form>

        <form class="ms-formular" method="post" action="/verwaltung/verifizierung/<?= $kennung ?>/ablehnen">
            <?= \MeinSlip\Http\Formularschutz::feld() ?>
            <div class="field">
                <label for="grund_ablehnung"><?= te('verwaltung.beleg_grund_ablehnung') ?></label>
                <textarea class="input" id="grund_ablehnung" name="grund" rows="3" required></textarea>
                <span class="hinweis"><?= te('verwaltung.beleg_grund_ablehnung_hinweis') ?></span>
            </div>
            <button class="btn btn-ghost" type="submit"><?= te('verwaltung.beleg_ablehnen') ?></button>
        </form>
    <?php endif; ?>

    <p class="hinweis"><a href="/verwaltung/verifizierung"><?= te('verwaltung.beleg_zurueck') ?></a></p>
</section>
