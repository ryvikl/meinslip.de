<?php

declare(strict_types=1);

/**
 * Meine Angebote.
 *
 * Verkaufen ist eine Faehigkeit, kein zweites Konto — deshalb liegt diese
 * Seite in derselben Anmeldung. Ist die Faehigkeit nicht freigeschaltet,
 * erklaert die Seite den Grund, statt eine Fehlerseite zu zeigen.
 *
 * @var string|null $gesperrt  anmeldung | faehigkeit | gestoert | null
 * @var list<array<string,mixed>> $angebote
 */

$gesperrt ??= null;
$angebote ??= [];
?>
<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('markt.verkaufen_kicker') ?></p>
        <h1 style="font-size:clamp(1.75rem,4vw,2.5rem)"><?= te('markt.verkaufen_titel') ?></h1>
        <p class="ms-hero__unterzeile" style="margin-top:var(--space-4)"><?= te('markt.verkaufen_unterzeile') ?></p>
    </div>

    <?php if ($gesperrt === 'gestoert'): ?>
        <article class="card elev-sm">
            <h2 class="card-title"><?= te('markt.gestoert_titel') ?></h2>
            <p class="card-body"><?= te('markt.gestoert_text') ?></p>
        </article>
    <?php elseif ($gesperrt === 'anmeldung'): ?>
        <article class="card elev-sm">
            <h2 class="card-title"><?= te('markt.anmeldung_noetig_titel') ?></h2>
            <p class="card-body"><?= te('markt.anmeldung_noetig_text') ?></p>
            <p>
                <a class="btn btn-primary" href="/anmelden"><?= te('markt.anmelden_knopf') ?></a>
                <a class="btn btn-secondary" href="/registrieren"><?= te('markt.registrieren_knopf') ?></a>
            </p>
        </article>
    <?php elseif ($gesperrt === 'faehigkeit'): ?>
        <article class="card elev-sm">
            <h2 class="card-title"><?= te('markt.verkaufen_gesperrt_titel') ?></h2>
            <p class="card-body"><?= te('markt.verkaufen_gesperrt_text') ?></p>
        </article>
    <?php elseif ($angebote === []): ?>
        <article class="card elev-sm">
            <h2 class="card-title"><?= te('markt.verkaufen_leer_titel') ?></h2>
            <p class="card-body"><?= te('markt.verkaufen_leer_text') ?></p>
            <p><a class="btn btn-primary" href="/verkaufen/neu"><?= te('markt.verkaufen_neu') ?></a></p>
        </article>
    <?php else: ?>
        <p><a class="btn btn-primary" href="/verkaufen/neu"><?= te('markt.verkaufen_neu') ?></a></p>

        <table class="table">
            <thead>
            <tr>
                <th scope="col"><?= te('markt.spalte_titel') ?></th>
                <th scope="col"><?= te('markt.spalte_status') ?></th>
                <th scope="col"><?= te('markt.spalte_preis') ?></th>
                <th scope="col"><?= te('markt.spalte_angelegt') ?></th>
                <th scope="col"><?= te('markt.spalte_aktion') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($angebote as $angebot): ?>
                <tr>
                    <td><?= e((string) $angebot['titel']) ?></td>
                    <td><span class="tag tag-neutral"><?= te('markt.status.' . (string) $angebot['status']) ?></span></td>
                    <td><?= e(geld((int) $angebot['grundpreis_cent'], (string) $angebot['waehrung'])) ?></td>
                    <td><?= e((string) $angebot['angelegt_am']) ?></td>
                    <td>
                        <a class="btn btn-ghost" href="/verkaufen/<?= (int) $angebot['id'] ?>"><?= te('markt.verkaufen_bearbeiten') ?></a>
                        <?php if ((string) $angebot['status'] === \MeinSlip\Domain\Catalog\Angebote::STATUS_AKTIV): ?>
                            <a class="btn btn-ghost" href="/angebot/<?= (int) $angebot['id'] ?>"><?= te('markt.verkaufen_ansehen') ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
