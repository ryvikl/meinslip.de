<?php

declare(strict_types=1);

/**
 * Textseite für Impressum, Datenschutz, AGB, Widerruf, Kündigung,
 * Sicherheit und Für-Creator.
 *
 * Der Inhalt kommt aus den Übersetzungsdateien, damit auch Rechtstexte
 * mehrsprachig werden können, ohne dass Vorlagen dupliziert werden.
 *
 * @var string $bereich Schlüsselpräfix in resources/lang, z. B. 'impressum'
 * @var list<string> $abschnitte Schlüssel der Absätze
 * @var bool $hinweisEntwurf Ob der Warnkasten gezeigt wird
 */

$hinweisEntwurf ??= false;
?>
<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te($bereich . '.kicker') ?></p>
        <h1 style="font-size:clamp(1.75rem,4vw,2.5rem)"><?= te($bereich . '.titel') ?></h1>
    </div>

    <?php if ($hinweisEntwurf): ?>
        <p class="ms-fehler" role="note"><?= te('allgemein.rechtstext_entwurf') ?></p>
    <?php endif; ?>

    <div style="display:flex;flex-direction:column;gap:var(--space-6);max-width:70ch">
        <?php foreach ($abschnitte as $abschnitt): ?>
            <?php $ueberschrift = $bereich . '.' . $abschnitt . '_titel'; ?>
            <?php if (!str_starts_with(t($ueberschrift), '[[')): ?>
                <h2 style="font-size:1.25rem"><?= te($ueberschrift) ?></h2>
            <?php endif; ?>
            <p style="color:var(--color-neutral-300)"><?= te($bereich . '.' . $abschnitt) ?></p>
        <?php endforeach; ?>
    </div>
</section>
