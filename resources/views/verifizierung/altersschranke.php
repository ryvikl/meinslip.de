<?php

declare(strict_types=1);

/**
 * Die Altersschranke — und die Seite, die ausspricht, was sie nicht ist.
 *
 * DIE REIHENFOLGE AUF DIESER SEITE IST DIE GANZE ENTSCHEIDUNG. Zuerst kommt,
 * was die Erklärung nicht leistet, dann erst der Knopf. Umgekehrt läse
 * niemand den zweiten Teil: Wer eine Schaltfläche mit „Ich bin volljährig"
 * sieht, drückt sie und liest darunter nichts mehr.
 *
 * Sitzungen::gateBestanden() und gateGilt() hängen hier — sie lagen seit
 * 001_konten.php unbenutzt im Code. Angeschlossen sind sie damit; freigeschaltet
 * wird trotzdem nichts, und das steht auf der Seite. Medien::explizitSichtbar()
 * fragt die Erklärung erst, wenn Medien::altersschrankeGebunden() true meldet,
 * und das tut es nicht, solange kein von der KJM positiv bewertetes Verfahren
 * lizenziert und eingebunden ist.
 *
 * @var bool                     $gilt              Sitzungen::gateGilt()
 * @var int                      $minuten           Sitzungen::GATE_GUELTIG_MINUTEN
 * @var bool                     $schrankeGebunden  Medien::altersschrankeGebunden()
 * @var array<string,mixed>|null $sitzung
 * @var string|null              $erfolg
 * @var string|null              $fehler
 */

$gilt ??= false;
$sitzung ??= null;
$erfolg ??= null;
$fehler ??= null;
$schrankeGebunden ??= false;
?>
<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('verifizierung.schranke_kicker') ?></p>
        <h1 style="font-size:clamp(1.75rem,4vw,2.5rem)"><?= te('verifizierung.schranke_titel') ?></h1>
        <p class="ms-hero__unterzeile" style="margin-top:var(--space-4)"><?= te('verifizierung.schranke_was_das_ist', ['minuten' => (int) $minuten]) ?></p>
    </div>

    <?php if ($erfolg !== null): ?>
        <p class="card" role="status"><?= te('verifizierung.erfolg.' . $erfolg) ?></p>
    <?php endif; ?>

    <?php if ($fehler !== null): ?>
        <p class="ms-fehler" role="alert"><?= te('verifizierung.fehler.' . $fehler) ?></p>
    <?php endif; ?>
</section>

<section class="ms-abschnitt" aria-labelledby="ehrlich">
    <?php // Zuerst das Kleingedruckte, dann der Knopf. Siehe Kopfkommentar. ?>
    <article class="card elev-sm">
        <h2 id="ehrlich" class="card-title" style="font-size:1.125rem"><?= te('verifizierung.schranke_ehrlich_titel') ?></h2>
        <ul class="card-body">
            <li><?= te('verifizierung.schranke_ehrlich_1') ?></li>
            <li><?= te('verifizierung.schranke_ehrlich_2') ?></li>
            <li><?= te('verifizierung.schranke_ehrlich_3') ?></li>
            <li><?= te('verifizierung.schranke_ehrlich_4') ?></li>
        </ul>

        <?php if ($schrankeGebunden): ?>
            <?php /*
                   * Steht heute nie da. Der Zweig existiert, damit die Seite
                   * beim Anbinden eines Verfahrens nicht weiter behauptet, es
                   * gäbe keines — wer Medien::altersschrankeGebunden() umdreht,
                   * darf diesen Text nicht erst suchen müssen.
                   */ ?>
            <p class="card-meta"><?= te('verifizierung.schranke_gebunden') ?></p>
        <?php endif; ?>
    </article>
</section>

<section class="ms-abschnitt">
    <article class="card">
        <p class="card-title" style="font-size:1rem">
            <?= $gilt ? te('verifizierung.schranke_gilt') : te('verifizierung.schranke_gilt_nicht') ?>
        </p>
        <p class="card-body"><?= te('verifizierung.schranke_laeuft_ab', ['minuten' => (int) $minuten]) ?></p>
    </article>

    <?php if ($sitzung === null): ?>
        <p class="hinweis"><?= te('verifizierung.schranke_ohne_sitzung') ?></p>
        <p><a class="btn btn-secondary" href="/anmelden"><?= te('verifizierung.schranke_anmelden') ?></a></p>
    <?php else: ?>
        <form class="ms-formular" method="post" action="/altersschranke">
            <?= \MeinSlip\Http\Formularschutz::feld() ?>
            <button class="btn btn-primary btn-block" type="submit"><?= te('verifizierung.schranke_knopf') ?></button>
        </form>
    <?php endif; ?>
</section>
