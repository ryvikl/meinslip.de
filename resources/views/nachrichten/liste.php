<?php

declare(strict_types=1);

/**
 * Die Liste der eigenen Unterhaltungen.
 *
 * Karten statt Tabelle. Eine Tabelle waere hier der falsche Baustein: Die
 * wichtigste Zeile ist der Auszug der letzten Nachricht, und der ist
 * unterschiedlich lang. In einer Tabellenzelle auf 360 px braeuchte er einen
 * eigenen Bildlauf (.ms-breit), und der Weg ins Gespraech — die einzige Aktion
 * dieser Seite — stuende ausserhalb des Bildes. Karten brechen um.
 *
 * DER AUSZUG WIRD GEKUERZT, NICHT ABGESCHNITTEN. Ohne Kuerzung stuenden bis zu
 * 4000 Zeichen einer fremden Nachricht in der Uebersicht, und die Liste waere
 * bei drei Gespraechen eine Bildschirmlaenge lang. Gekuerzt wird hier und nicht
 * per CSS: Was nicht gebraucht wird, soll den Browser gar nicht erst erreichen.
 *
 * @var array{zeilen:list<array<string,mixed>>, anzahl:int, seite:int, seiten:int, pro_seite:int}|null $blatt
 * @var array<int,string>  $angebote           Titel je Angebotskennung
 * @var string|null        $eigeneDeklaration
 * @var string|null        $erfolg
 * @var string|null        $fehler
 * @var bool               $gestoert
 */

$blatt ??= null;
$angebote ??= [];
$eigeneDeklaration ??= null;
$erfolg ??= null;
$fehler ??= null;
$gestoert ??= false;

$zeilen = $blatt['zeilen'] ?? [];
$seite = (int) ($blatt['seite'] ?? 1);
$seiten = (int) ($blatt['seiten'] ?? 1);

/** Kuerzt den Auszug auf eine Zeile Uebersicht. */
$auszug = static function (?string $text): string {
    $text = trim((string) $text);

    if (mb_strlen($text) <= 120) {
        return $text;
    }

    // Das Auslassungszeichen ist Interpunktion, kein uebersetzbarer Text —
    // es steht in jeder Sprache gleich.
    return mb_substr($text, 0, 120) . '…';
};
?>
<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('chat.kicker') ?></p>
        <h1 style="font-size:clamp(1.75rem,4vw,2.5rem)"><?= te('chat.titel') ?></h1>
        <p class="ms-hero__unterzeile" style="margin-top:var(--space-4)"><?= te('chat.unterzeile') ?></p>
    </div>

    <?php if ($erfolg !== null): ?>
        <p class="hinweis text-muted" role="status"><?= te('chat.erfolg.' . $erfolg) ?></p>
    <?php endif; ?>

    <?php if ($fehler !== null): ?>
        <p class="ms-fehler" role="alert"><?= te('chat.fehler.' . $fehler) ?></p>
    <?php endif; ?>

    <?php if ($gestoert): ?>
        <article class="card elev-sm">
            <h2 class="card-title"><?= te('chat.gestoert_titel') ?></h2>
            <p class="card-body"><?= te('chat.gestoert_text') ?></p>
        </article>
    <?php elseif ($zeilen === []): ?>
        <article class="card elev-sm">
            <h2 class="card-title"><?= te('chat.leer_titel') ?></h2>
            <p class="card-body"><?= te('chat.leer_text') ?></p>
            <p><a class="btn btn-primary" href="/entdecken"><?= te('chat.leer_knopf') ?></a></p>
        </article>
    <?php else: ?>
        <?php // Die eigene Angabe steht auch hier, nicht nur im Fenster: Wer sie
              // aendern will, soll dafuer kein Gespraech oeffnen muessen. ?>
        <p class="card-meta">
            <span class="tag tag-neutral"><?= te('chat.kopf_eigene') ?>
                <?= $eigeneDeklaration === null ? te('chat.kopf_ohne_angabe') : te('chat.deklaration.' . $eigeneDeklaration) ?></span>
            <a class="btn btn-ghost" href="/nachrichten/deklaration"><?= te('chat.kopf_deklaration_aendern') ?></a>
        </p>

        <?php foreach ($zeilen as $zeile): ?>
            <?php
            $kennung = (int) $zeile['id'];
            $ungelesen = (int) $zeile['ungelesen'];
            $angebotId = $zeile['angebot_id'] === null ? null : (int) $zeile['angebot_id'];
            $text = $auszug(is_string($zeile['letzter_text']) ? $zeile['letzter_text'] : null);
            ?>
            <article class="card elev-sm">
                <p class="card-kicker"><?= e((string) $zeile['partner_pseudonym']) ?></p>
                <h2 class="card-title"><?= e((string) $zeile['partner_name']) ?></h2>

                <p class="card-meta">
                    <?php // Die Deklaration des Gegenuebers steht schon in der Liste —
                          // sie soll nicht erst nach dem Oeffnen sichtbar werden. ?>
                    <span class="tag tag-accent"><?= $zeile['partner_deklaration'] === null
                        ? te('chat.kopf_ohne_angabe')
                        : te('chat.deklaration.' . (string) $zeile['partner_deklaration']) ?></span>
                    <?php if ($ungelesen > 0): ?>
                        <span class="tag tag-accent-2"><?= te('chat.liste_ungelesen', ['anzahl' => $ungelesen]) ?></span>
                    <?php endif; ?>
                    <?php if ($zeile['letzte_nachricht_am'] !== null): ?>
                        <span class="tag tag-neutral"><?= e((string) $zeile['letzte_nachricht_am']) ?></span>
                    <?php endif; ?>
                </p>

                <?php if ($text === ''): ?>
                    <p class="card-body text-muted"><?= te('chat.liste_ohne_nachricht') ?></p>
                <?php else: ?>
                    <p class="card-body" style="overflow-wrap:anywhere"><?= e($text) ?></p>
                <?php endif; ?>

                <?php if ($angebotId !== null): ?>
                    <p class="card-meta">
                        <?php if (isset($angebote[$angebotId])): ?>
                            <a href="/angebot/<?= $angebotId ?>"><?= te('chat.liste_bezug', ['titel' => $angebote[$angebotId]]) ?></a>
                        <?php else: ?>
                            <span class="text-muted"><?= te('chat.liste_bezug_entfallen') ?></span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <p><a class="btn btn-primary" href="/nachrichten/<?= $kennung ?>"><?= te('chat.liste_oeffnen') ?></a></p>
            </article>
        <?php endforeach; ?>

        <?php if ($seiten > 1): ?>
            <p class="card-meta">
                <span class="text-muted"><?= te('chat.liste_seite', ['nummer' => $seite, 'seiten' => $seiten]) ?></span>
                <?php if ($seite > 1): ?>
                    <a class="btn btn-ghost" href="/nachrichten?seite=<?= $seite - 1 ?>"><?= te('chat.liste_zurueck') ?></a>
                <?php endif; ?>
                <?php if ($seite < $seiten): ?>
                    <a class="btn btn-ghost" href="/nachrichten?seite=<?= $seite + 1 ?>"><?= te('chat.liste_weiter') ?></a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</section>
