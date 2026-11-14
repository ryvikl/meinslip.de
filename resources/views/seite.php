<?php

declare(strict_types=1);

/**
 * Textseite für Impressum, Datenschutz, AGB, Widerruf, Kündigung,
 * Sicherheit und Für-Creator.
 *
 * Der Inhalt kommt aus den Übersetzungsdateien, damit auch Rechtstexte
 * mehrsprachig werden können, ohne dass Vorlagen dupliziert werden.
 *
 * Ein Abschnitt ist reiner Text mit drei Auszeichnungen, mehr nicht:
 * eine Leerzeile trennt Absätze, ein einfacher Zeilenumbruch bleibt ein
 * Zeilenumbruch (Anschriften), und ein Block, in dem jede Zeile mit "- "
 * beginnt, wird eine Liste. HTML in Sprachdateien gibt es weiterhin nicht —
 * alles läuft durch e().
 *
 * @var string $bereich Schlüsselpräfix in resources/lang, z. B. 'impressum'
 * @var list<string> $abschnitte Schlüssel der Absätze
 * @var bool $rechtsseite Ob die Seite die Betreibergesellschaft nennt
 */

$rechtsseite ??= false;

$bloecke = static function (string $text): array {
    $ergebnis = [];

    foreach (preg_split('/\R{2,}/u', trim($text)) ?: [] as $block) {
        $zeilen = array_map('trim', preg_split('/\R/u', $block) ?: []);
        $liste = $zeilen !== [] && array_filter($zeilen, static fn (string $z): bool => !str_starts_with($z, '- ')) === [];

        $ergebnis[] = $liste
            ? ['liste', array_map(static fn (string $z): string => substr($z, 2), $zeilen)]
            : ['absatz', $zeilen];
    }

    return $ergebnis;
};
?>
<?php // data-nosnippet: Auch falls die Seite je in einem Index landet, zeigt der Treffer keinen Auszug. ?>
<section class="ms-abschnitt"<?= $rechtsseite ? ' data-nosnippet' : '' ?>>
    <div>
        <p class="ms-kicker"><?= te($bereich . '.kicker') ?></p>
        <h1 style="font-size:clamp(1.75rem,4vw,2.5rem)"><?= te($bereich . '.titel') ?></h1>
    </div>

    <div style="display:flex;flex-direction:column;gap:var(--space-6);max-width:70ch">
        <?php foreach ($abschnitte as $abschnitt): ?>
            <?php $ueberschrift = $bereich . '.' . $abschnitt . '_titel'; ?>
            <?php if (!str_starts_with(t($ueberschrift), '[[')): ?>
                <h2 style="font-size:1.25rem"><?= te($ueberschrift) ?></h2>
            <?php endif; ?>
            <?php foreach ($bloecke(t($bereich . '.' . $abschnitt)) as [$art, $zeilen]): ?>
                <?php if ($art === 'liste'): ?>
                    <ul style="color:var(--color-neutral-300);padding-left:1.25rem;display:flex;flex-direction:column;gap:var(--space-2)">
                        <?php foreach ($zeilen as $zeile): ?>
                            <li><?= e($zeile) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="color:var(--color-neutral-300)"><?= implode('<br>', array_map('e', $zeilen)) ?></p>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</section>
