<?php

declare(strict_types=1);

/**
 * Die Schrankenseite — das Einzige, was ohne Passwort zu sehen ist.
 *
 * Eigenstaendiges Dokument ohne das Anwendungslayout: kein Kopf, keine
 * Navigation, kein Fussbereich. Absicht, nicht Faulheit — die Seite soll
 * nichts von dem zeigen, was sie schuetzt, und sie soll kein Telemedium mit
 * Inhalt sein, sondern eine Tuer (Begruendung im Kopf von
 * app/Http/Vorschauschranke.php).
 *
 * Das Formular sendet an den ANGEFRAGTEN Pfad zurueck: Wer mit einem tiefen
 * Verweis ankommt, landet nach dem Einlass genau dort.
 *
 * @var string $pfad
 * @var string|null $fehler Sprachschluessel unter vorschau.* oder null
 */

$fehler ??= null;
?>
<!DOCTYPE html>
<html lang="de-DE">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#161826">
    <title><?= te('vorschau.titel') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<main class="ms-huelle ms-inhalt" style="max-width:26rem;justify-content:center;min-height:100dvh;padding-block:var(--space-8)">
    <div class="ms-abschnitt" style="gap:var(--space-6)">
        <span class="ms-hero__marke">
            <span class="ms-hero__punkt" style="animation:none"></span>
            <?= te('vorschau.marke') ?>
        </span>

        <h1 style="font-size:clamp(1.75rem,6vw,2.25rem)"><?= te('vorschau.titel') ?></h1>

        <p class="ms-hero__unterzeile" style="font-size:0.9375rem"><?= te('vorschau.erklaerung') ?></p>

        <?php if ($fehler !== null): ?>
            <p class="ms-fehler" role="alert"><?= te('vorschau.' . $fehler) ?></p>
        <?php endif; ?>

        <form class="ms-formular" method="post" action="<?= e($pfad) ?>" style="max-width:none">
            <?= \MeinSlip\Http\Formularschutz::feld() ?>
            <div class="field">
                <label for="vorschau_passwort"><?= te('vorschau.feld') ?></label>
                <?php // autofocus ist hier vertretbar: Die Seite hat genau ein
                      // Bedienelement, es gibt nichts, woran der Fokus vorbeispraenge. ?>
                <input class="input" type="password" id="vorschau_passwort"
                       name="<?= e(\MeinSlip\Http\Vorschauschranke::FELD) ?>"
                       autocomplete="current-password" required autofocus>
            </div>
            <button class="btn btn-primary btn-block" type="submit"><?= te('vorschau.knopf') ?></button>
        </form>

        <p class="hinweis text-muted" style="font-size:0.75rem"><?= te('vorschau.hinweis') ?></p>
    </div>
</main>
</body>
</html>
