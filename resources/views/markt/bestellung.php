<?php

declare(strict_types=1);

/**
 * Eine Bestellung ansehen — nur fuer die beiden Beteiligten.
 *
 * Die Aufteilung in Vermittlungsgebuehr und Auszahlung sieht nur die
 * Verkaeuferin: Fuer die Kaeuferin ist der Gesamtbetrag mit der enthaltenen
 * Umsatzsteuer die vollstaendige Auskunft, alles weitere ist das
 * Innenverhaeltnis zwischen Plattform und Verkaeuferin.
 *
 * @var array<string,mixed>|null $bestellung
 * @var list<array<string,mixed>> $positionen
 * @var string|null $rolle   kaeufer | verkaeufer
 * @var array<string,mixed>|null $gegenueber
 * @var bool $neu
 * @var bool $zugang
 */

$bestellung ??= null;
$positionen ??= [];
$rolle ??= null;
$gegenueber ??= null;
$neu ??= false;
$zugang ??= false;
?>
<?php if ($bestellung === null): ?>
    <section class="ms-abschnitt">
        <article class="card elev-sm">
            <h1 class="card-title"><?= te('markt.bestellung_unbekannt_titel') ?></h1>
            <p class="card-body"><?= te('markt.bestellung_unbekannt_text') ?></p>
        </article>
    </section>
<?php elseif (!$zugang): ?>
    <section class="ms-abschnitt">
        <article class="card elev-sm">
            <h1 class="card-title"><?= te('markt.bestellung_kein_zugang_titel') ?></h1>
            <p class="card-body"><?= te('markt.bestellung_kein_zugang_text') ?></p>
        </article>
    </section>
<?php else: ?>
    <?php
    $waehrung = (string) $bestellung['waehrung'];
    $istVerkaeufer = $rolle === 'verkaeufer';
    ?>
    <section class="ms-abschnitt">
        <div>
            <p class="ms-kicker"><?= te('markt.bestellung_kicker') ?></p>
            <?php // Die Bestellnummer ist eine Zeichenkette am Stueck (MS-JJJJMMTT-XXXXXX).
                  // Sie bricht zwar an den Bindestrichen, aber nicht darin — auf 360 px
                  // bleibt es knapp, deshalb der Umbruch an beliebiger Stelle. ?>
            <h1 style="font-size:clamp(1.75rem,4vw,2.5rem);overflow-wrap:anywhere"><?= e((string) $bestellung['nummer']) ?></h1>
        </div>

        <?php if ($neu): ?>
            <p class="card" role="status"><?= te('markt.bestellung_erfolg') ?></p>
        <?php endif; ?>

        <article class="card elev-sm">
            <p class="card-meta">
                <span class="tag tag-accent"><?= te('bestellung.zustand.' . (string) $bestellung['zustand']) ?></span>
                <span class="tag tag-neutral"><?= te('markt.konfigurator_lieferart_' . (string) $bestellung['lieferart']) ?></span>
                <span class="tag tag-neutral"><?= te('markt.bestellung_rolle') ?>:
                    <?= $istVerkaeufer ? te('markt.bestellung_rolle_verkaeufer') : te('markt.bestellung_rolle_kaeufer') ?></span>
            </p>
            <p class="card-body"><?= te('markt.bestellung_nummer') ?>: <?= e((string) $bestellung['nummer']) ?></p>
            <p class="card-body"><?= te('markt.bestellung_angelegt') ?>: <?= e((string) $bestellung['angelegt_am']) ?></p>
            <?php if ($gegenueber !== null): ?>
                <p class="card-body"><?= $istVerkaeufer ? te('markt.bestellung_rolle_kaeufer') : te('markt.bestellung_rolle_verkaeufer') ?>:
                    <span class="tag tag-outline"><?= e((string) $gegenueber['pseudonym']) ?></span>
                </p>
            <?php endif; ?>
            <p class="card-meta"><?= te('markt.bestellung_treuhand') ?></p>
        </article>
    </section>

    <section class="ms-abschnitt" aria-labelledby="positionen">
        <div>
            <p class="ms-kicker"><?= te('markt.bestellung_kicker') ?></p>
            <h2 id="positionen"><?= te('markt.bestellung_positionen') ?></h2>
        </div>

        <?php foreach ($positionen as $position): ?>
            <article class="card elev-sm">
                <?php // Die Bezeichnung ist der uebernommene Angebotstitel, bis 190 Zeichen
                      // und moeglicherweise ohne jede Leerstelle. ?>
                <h3 class="card-title" style="overflow-wrap:anywhere"><?= e((string) $position['bezeichnung']) ?></h3>
                <p class="card-meta">
                    <span class="tag tag-accent"><?= e(geld((int) $position['verkaufspreis_cent'], $waehrung)) ?></span>
                </p>

                <?php $spezifikationen = is_array($position['spezifikationen'] ?? null) ? $position['spezifikationen'] : []; ?>
                <?php if ($spezifikationen !== []): ?>
                    <?php
                    /*
                     * Die Zeile "Deine Angaben" benennt die Tabelle ohnehin schon sichtbar.
                     * Ueber eine Kennung wird daraus der Name des Bildlaufbereichs — das ist
                     * belastbarer als ein zweiter, nur vorgelesener Text und haelt beides
                     * zusammen. Die Kennung traegt die Positionsnummer, weil eine Bestellung
                     * mehrere Positionen mit je eigener Tabelle haben kann und eine doppelte
                     * Kennung im Dokument die Zuordnung zerstoerte.
                     */
                    $angabenKennung = 'angaben-' . (int) $position['id'];
                    ?>
                    <p class="card-kicker" id="<?= e($angabenKennung) ?>"><?= te('markt.bestellung_spezifikationen') ?></p>
                    <?php
                    /*
                     * Drei bedeutungstragende Spalten — Angabe, gewaehlter Wert, Aufpreis.
                     * Ohne Kopfzellen meldet ein Vorleseprogramm im Tabellenmodus nur
                     * "Spalte 3: 5,00 Euro" und sagt nie, dass Spalte 3 der Aufpreis ist
                     * (WCAG 1.3.1). Auf einem Beleg, bei dem das Widerrufsrecht nach
                     * § 312g Abs. 2 Nr. 1 BGB ausgeschlossen ist, muss nachvollziehbar
                     * bleiben, welcher Aufpreis zu welcher Angabe gehoert. Die Bezeichnung
                     * ist der Kopf ihrer Zeile, deshalb <th scope="row"> — dasselbe Muster
                     * wie in verwaltung/konto.php.
                     *
                     * Die Huelle scrollt statt der Seite: der Wert in Spalte 2 ist Freitext
                     * der Kaeuferin und in der Laenge nicht begrenzt. tabindex="0", weil in
                     * der Tabelle nichts Fokussierbares steht, ueber das sich der Behaelter
                     * per Tastatur hinscrollen liesse (WCAG 2.1.1).
                     */
                    ?>
                    <div class="ms-breit" tabindex="0" role="region" aria-labelledby="<?= e($angabenKennung) ?>">
                        <table class="table">
                            <thead>
                            <tr>
                                <th scope="col"><?= te('markt.bestellung_spalte_angabe') ?></th>
                                <th scope="col"><?= te('markt.bestellung_spalte_wert') ?></th>
                                <th scope="col"><?= te('markt.bestellung_spalte_aufpreis') ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($spezifikationen as $spezifikation): ?>
                                <tr>
                                    <th scope="row" style="overflow-wrap:anywhere"><?= e((string) $spezifikation['bezeichnung']) ?></th>
                                    <td style="overflow-wrap:anywhere"><?= e((string) ($spezifikation['wert'] ?? '')) ?></td>
                                    <td><?= e(geld((int) $spezifikation['aufpreis_cent'], $waehrung)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

        <article class="card elev-sm">
            <p class="card-title"><?= te('markt.bestellung_summe') ?>:
                <?= e(geld((int) $bestellung['summe_verkauf_cent'], $waehrung)) ?></p>
            <p class="card-body"><?= te('markt.bestellung_ust') ?>:
                <?= e(geld((int) $bestellung['summe_ust_cent'], $waehrung)) ?></p>
            <?php if ($istVerkaeufer): ?>
                <p class="card-body"><?= te('markt.bestellung_provision') ?>:
                    <?= e(geld((int) $bestellung['summe_provision_cent'], $waehrung)) ?></p>
                <p class="card-body"><?= te('markt.bestellung_auszahlung') ?>:
                    <?= e(geld((int) $bestellung['summe_einkauf_cent'], $waehrung)) ?></p>
            <?php endif; ?>
            <p class="card-meta"><?= te('markt.bestellung_widerruf') ?></p>
        </article>
    </section>
<?php endif; ?>
