<?php

declare(strict_types=1);

/**
 * Profil — Zustellung der Entscheidungen nach Art. 17 DSA.
 *
 * Wenn die Verwaltung etwas einschränkt, muss die betroffene Person die
 * Begründung bekommen. Deshalb steht sie hier ungekürzt und im Klartext, nicht
 * als Verweis auf ein Aktenzeichen. Der Zeitpunkt des Lesens wird festgehalten
 * — er ist der Nachweis, dass die Begründung angekommen ist.
 *
 * @var list<array<string,mixed>> $benachrichtigungen
 * @var bool $gestoert
 * @var array<string,mixed>|null $sitzung
 */

$gestoert ??= false;
$benachrichtigungen ??= [];
$ungelesen = 0;

foreach ($benachrichtigungen as $eintrag) {
    if (($eintrag['gelesen'] ?? false) === false) {
        $ungelesen++;
    }
}
?>
<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('profil.kicker') ?></p>
        <h1 style="font-size:clamp(1.75rem,4vw,2.5rem)"><?= te('profil.titel') ?></h1>
        <?php if ($sitzung !== null): ?>
            <p class="ms-hero__unterzeile" style="margin-top:var(--space-4)"><?= e((string) $sitzung['pseudonym']) ?></p>
        <?php endif; ?>
    </div>
</section>

<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('profil.entscheidungen_kicker') ?></p>
        <h2><?= te('profil.entscheidungen_titel') ?>
            <?php if ($ungelesen > 0): ?>
                <span class="tag tag-accent"><?= e((string) $ungelesen) ?></span>
            <?php endif; ?>
        </h2>
        <p class="hinweis"><?= te('profil.entscheidungen_erklaerung') ?></p>
    </div>

    <?php if ($gestoert): ?>
        <p class="ms-fehler" role="alert"><?= te('profil.gestoert') ?></p>
    <?php elseif ($benachrichtigungen === []): ?>
        <article class="card">
            <h3 class="card-title"><?= te('profil.keine_titel') ?></h3>
            <p class="card-body"><?= te('profil.keine_text') ?></p>
        </article>
    <?php else: ?>
        <?php foreach ($benachrichtigungen as $eintrag): ?>
            <article class="card elev-sm">
                <p class="card-kicker"><?= te('profil.art.' . (string) $eintrag['art']) ?></p>
                <h3 class="card-title"><?= te('profil.begruendung_ueberschrift') ?></h3>

                <?php // Die Begründung steht ungekürzt da. Eine Zusammenfassung
                      // wäre eine zweite Entscheidung darüber, was die Person
                      // erfahren darf — genau das verbietet Art. 17. ?>
                <p class="card-body" style="white-space:pre-line;overflow-wrap:anywhere"><?= e((string) ($eintrag['begruendung'] ?? '')) ?></p>

                <p class="card-meta">
                    <?= te('profil.entschieden_am') ?> <?= e((string) $eintrag['angelegt_am']) ?>
                </p>

                <?php if (($eintrag['gelesen'] ?? false) === false): ?>
                    <form method="post" action="/profil/gelesen" style="margin:0">
                        <?= \MeinSlip\Http\Formularschutz::feld() ?>
                        <input type="hidden" name="id" value="<?= (int) $eintrag['id'] ?>">
                        <button class="btn btn-ghost" type="submit"><?= te('profil.als_gelesen') ?></button>
                    </form>
                <?php else: ?>
                    <p class="card-meta"><?= te('profil.gelesen_am') ?> <?= e((string) $eintrag['gelesen_am']) ?></p>
                <?php endif; ?>

                <p class="hinweis"><?= te('profil.widerspruch') ?> <a href="/impressum"><?= te('profil.widerspruch_weg') ?></a></p>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('profil.konto_kicker') ?></p>
        <h2><?= te('profil.konto_titel') ?></h2>
    </div>

    <div class="ms-raster">
        <a class="card elev-sm ms-kategoriekarte" href="/verkaufen">
            <h3 class="card-title"><?= te('profil.weg_verkaufen') ?></h3>
            <p class="card-body"><?= te('profil.weg_verkaufen_text') ?></p>
        </a>
        <a class="card elev-sm ms-kategoriekarte" href="/entdecken">
            <h3 class="card-title"><?= te('profil.weg_entdecken') ?></h3>
            <p class="card-body"><?= te('profil.weg_entdecken_text') ?></p>
        </a>
    </div>
</section>
