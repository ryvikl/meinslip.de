<?php

declare(strict_types=1);

/**
 * Registrierung.
 *
 * Erhoben wird nur, was gebraucht wird: Pseudonym, E-Mail, Passwort. Kein
 * Klarname, keine Anschrift — die verlangt erst die Identitätsprüfung, und
 * dann liegen sie in einer eigenen Tabelle mit engem Zugriff.
 *
 * @var string|null $fehler
 * @var string $pseudonym
 * @var string $email
 */

$fehler ??= null;
$pseudonym ??= '';
$email ??= '';
?>
<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('konto.registrieren_kicker') ?></p>
        <h1 style="font-size:clamp(1.75rem,4vw,2.5rem)"><?= te('konto.registrieren_titel') ?></h1>
    </div>

    <?php if ($fehler !== null): ?>
        <p class="ms-fehler" role="alert"><?= te('konto.fehler.' . $fehler) ?></p>
    <?php endif; ?>

    <form class="ms-formular" method="post" action="/registrieren">
        <?= \MeinSlip\Http\Formularschutz::feld() ?>
        <div class="field">
            <label for="pseudonym"><?= te('konto.feld_pseudonym') ?></label>
            <input class="input" type="text" id="pseudonym" name="pseudonym"
                   value="<?= e($pseudonym) ?>" required minlength="3" maxlength="30"
                   autocomplete="username" autocapitalize="none">
            <span class="hinweis"><?= te('konto.hinweis_pseudonym') ?></span>
        </div>

        <div class="field">
            <label for="email"><?= te('konto.feld_email') ?></label>
            <input class="input" type="email" id="email" name="email"
                   value="<?= e($email) ?>" required autocomplete="email">
            <span class="hinweis"><?= te('konto.hinweis_email') ?></span>
        </div>

        <div class="field">
            <label for="passwort"><?= te('konto.feld_passwort') ?></label>
            <input class="input" type="password" id="passwort" name="passwort"
                   required minlength="12" autocomplete="new-password">
            <span class="hinweis"><?= te('konto.hinweis_passwort') ?></span>
        </div>

        <?php // Das Kästchen trägt drei Erklärungen, auf die sich AGB und
              // Datenschutzerklärung berufen: volljährig, Bedingungen
              // angenommen, in die Verarbeitung eingewilligt. Ohne es wären
              // das Behauptungen über die Person statt Erklärungen von ihr. ?>
        <label style="display:flex;gap:var(--space-2);align-items:flex-start">
            <input type="checkbox" name="volljaehrig" value="1" required>
            <span>
                <?= te('konto.erklaerung_1') ?>
                <a href="/agb"><?= te('konto.erklaerung_agb') ?></a>
                <?= te('konto.erklaerung_2') ?>
                <a href="/datenschutz"><?= te('konto.erklaerung_datenschutz') ?></a><?= te('konto.erklaerung_3') ?>
            </span>
        </label>

        <button class="btn btn-primary btn-block" type="submit"><?= te('konto.registrieren_absenden') ?></button>

        <p class="hinweis"><?= te('konto.registrieren_naechster_schritt') ?></p>
        <p class="hinweis"><?= te('konto.bereits_konto') ?> <a href="/anmelden"><?= te('konto.zur_anmeldung') ?></a></p>
    </form>
</section>
