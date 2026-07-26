<?php

declare(strict_types=1);

/**
 * Bestaetigungsseite nach einer Meldung.
 *
 * SIE IST NICHT DIE EMPFANGSBESTAETIGUNG. Art. 16 Abs. 4 DSA verlangt eine
 * unverzuegliche Bestaetigung des Eingangs — eine Seite, die man wegklickt,
 * belegt nichts. Die Zustellung macht Meldungen::melden() ueber die Tabelle
 * 'benachrichtigungen', in derselben Transaktion wie die Meldung selbst. Ohne
 * Konto gibt es niemanden, dem zuzustellen waere; genau das steht hier auch,
 * statt eine Bestaetigung zu versprechen, die nie kommt.
 *
 * Die zugesagte Frist steht im Klartext auf der Seite und nicht nur im
 * Datensatz: Eine Zusage, die die meldende Person nie liest, ist keine.
 *
 * @var int  $stunden
 * @var bool $angemeldet
 */

$stunden ??= 0;
$angemeldet ??= false;
?>
<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('melden.danke_kicker') ?></p>
        <h1 style="font-size:clamp(1.75rem,4vw,2.5rem)"><?= te('melden.danke_titel') ?></h1>
        <p class="ms-hero__unterzeile" style="margin-top:var(--space-4)"><?= te('melden.danke_unterzeile') ?></p>
    </div>

    <article class="card elev-sm">
        <p class="card-kicker"><?= te('melden.danke_frist_kicker') ?></p>
        <h2 class="card-title"><?= te('melden.danke_frist_titel', ['stunden' => $stunden]) ?></h2>
        <p class="card-body"><?= te('melden.danke_frist_text', ['stunden' => $stunden]) ?></p>
    </article>

    <?php if ($angemeldet): ?>
        <article class="card elev-sm">
            <p class="card-kicker"><?= te('melden.danke_bestaetigung_kicker') ?></p>
            <h2 class="card-title"><?= te('melden.danke_bestaetigung_titel') ?></h2>
            <p class="card-body"><?= te('melden.danke_bestaetigung_text') ?></p>
            <p><a class="btn btn-secondary" href="/profil"><?= te('melden.danke_profil_knopf') ?></a></p>
        </article>
    <?php else: ?>
        <article class="card elev-sm">
            <p class="card-kicker"><?= te('melden.danke_anonym_kicker') ?></p>
            <h2 class="card-title"><?= te('melden.danke_anonym_titel') ?></h2>
            <p class="card-body"><?= te('melden.danke_anonym_text') ?></p>
        </article>
    <?php endif; ?>

    <p><a class="btn btn-ghost" href="/entdecken"><?= te('melden.danke_weiter_knopf') ?></a></p>
</section>
