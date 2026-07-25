<?php

declare(strict_types=1);

/**
 * Anmeldung.
 *
 * @var string|null $fehler
 * @var string $email
 */

$fehler ??= null;
$email ??= '';
?>
<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('konto.anmelden_kicker') ?></p>
        <h1 style="font-size:clamp(1.75rem,4vw,2.5rem)"><?= te('konto.anmelden_titel') ?></h1>
    </div>

    <?php if ($fehler !== null): ?>
        <p class="ms-fehler" role="alert"><?= te('konto.fehler.' . $fehler) ?></p>
    <?php endif; ?>

    <form class="ms-formular" method="post" action="/anmelden">
        <?= \MeinSlip\Http\Formularschutz::feld() ?>
        <div class="field">
            <label for="email"><?= te('konto.feld_email') ?></label>
            <input class="input" type="email" id="email" name="email"
                   value="<?= e($email) ?>" required autocomplete="email">
        </div>

        <div class="field">
            <label for="passwort"><?= te('konto.feld_passwort') ?></label>
            <input class="input" type="password" id="passwort" name="passwort"
                   required autocomplete="current-password">
        </div>

        <button class="btn btn-primary btn-block" type="submit"><?= te('konto.anmelden_absenden') ?></button>

        <p class="hinweis"><?= te('konto.noch_kein_konto') ?> <a href="/registrieren"><?= te('konto.zur_registrierung') ?></a></p>
    </form>
</section>
