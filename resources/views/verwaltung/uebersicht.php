<?php

declare(strict_types=1);

/**
 * Übersicht des Verwaltungsbereichs.
 *
 * Die Hauptbuchabweichung steht ganz oben und nimmt Platz ein, sobald sie
 * ungleich null ist. Als eine Zahl unter zwölf anderen würde sie übersehen —
 * und sie ist die einzige, bei der Geld entstanden oder verschwunden ist.
 *
 * @var array<string,mixed> $kennzahlen
 */

$abweichung = (int) $kennzahlen['hauptbuch_abweichung_cent'];
$inOrdnung = (bool) $kennzahlen['hauptbuch_in_ordnung'];

/** @var list<array{0:string, 1:int}> $zahlen */
$zahlen = [
    ['verwaltung.kennzahl_konten_gesamt', (int) $kennzahlen['konten_gesamt']],
    ['verwaltung.kennzahl_konten_neu', (int) $kennzahlen['konten_neu_7_tage']],
    ['verwaltung.kennzahl_konten_gesperrt', (int) $kennzahlen['konten_gesperrt']],
    ['verwaltung.kennzahl_meldungen_offen', (int) $kennzahlen['meldungen_offen']],
    ['verwaltung.kennzahl_meldungen_frist', (int) $kennzahlen['meldungen_frist_ueberschritten']],
    ['verwaltung.kennzahl_pruefungen_offen', (int) $kennzahlen['pruefungen_offen']],
];
?>
<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('verwaltung.uebersicht_kicker') ?></p>
        <h1 style="font-size:clamp(1.5rem,4vw,2.25rem)"><?= te('verwaltung.uebersicht_titel') ?></h1>
    </div>

    <?php if (!$inOrdnung): ?>
        <div class="ms-fehler" role="alert" style="display:flex;flex-direction:column;gap:var(--space-3)">
            <h2 style="font-size:1.25rem"><?= te('verwaltung.abweichung_titel') ?></h2>
            <p style="font-size:1.5rem"><?= te('verwaltung.abweichung_betrag', ['betrag' => geld($abweichung)]) ?></p>
            <p><?= te('verwaltung.abweichung_text') ?></p>
            <p><?= te('verwaltung.abweichung_vorgaenge', ['anzahl' => (int) $kennzahlen['hauptbuch_unausgeglichene_vorgaenge']]) ?></p>
            <p><a class="btn btn-primary" href="/verwaltung/hauptbuch"><?= te('verwaltung.abweichung_pruefen') ?></a></p>
        </div>
    <?php else: ?>
        <article class="card">
            <p class="card-title"><?= te('verwaltung.hauptbuch_in_ordnung') ?></p>
            <p class="card-meta"><?= te('verwaltung.hauptbuch_in_ordnung_text') ?></p>
        </article>
    <?php endif; ?>

    <div class="ms-raster">
        <?php foreach ($zahlen as [$schluessel, $wert]): ?>
            <article class="card elev-sm">
                <p class="card-kicker"><?= te($schluessel) ?></p>
                <p class="card-title" style="font-size:1.75rem"><?= e((string) $wert) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="ms-abschnitt">
    <h2><?= te('verwaltung.uebersicht_angebote') ?></h2>
    <div style="overflow-x:auto">
        <table class="table">
            <thead>
            <tr>
                <th scope="col"><?= te('verwaltung.spalte.status') ?></th>
                <th scope="col"><?= te('verwaltung.spalte.anzahl') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($kennzahlen['angebote_je_status'] as $status => $anzahl): ?>
                <tr>
                    <td><?= te('verwaltung.angebot_status.' . $status) ?></td>
                    <td><?= e((string) (int) $anzahl) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="ms-abschnitt">
    <h2><?= te('verwaltung.uebersicht_bestellungen') ?></h2>
    <div style="overflow-x:auto">
        <table class="table">
            <thead>
            <tr>
                <th scope="col"><?= te('verwaltung.spalte.zustand') ?></th>
                <th scope="col"><?= te('verwaltung.spalte.anzahl') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($kennzahlen['bestellungen_je_zustand'] as $zustand => $anzahl): ?>
                <tr>
                    <td><?= te('verwaltung.bestellzustand.' . $zustand) ?></td>
                    <td><?= e((string) (int) $anzahl) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
