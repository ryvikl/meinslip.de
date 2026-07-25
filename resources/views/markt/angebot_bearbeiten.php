<?php

declare(strict_types=1);

/**
 * Angebot bearbeiten — Stammdaten, Optionen des Konfigurators, Weg durch die
 * Statusmaschine.
 *
 * Veraendert wird nur im Entwurf oder in der Pause. Ein aktives Angebot laesst
 * sich weder umschreiben noch in seinen Optionen aendern — sonst wechselte die
 * Ware unter den Augen der Kaeuferin ihre Beschaffenheit, und die
 * Spezifikation, auf die sich der Widerrufsausschluss stuetzt, waere nicht
 * mehr die gezeigte. Die Oberflaeche bildet das ab, statt es beim Speichern
 * abzuweisen.
 *
 * @var array<string,mixed>|null $angebot
 * @var list<array<string,mixed>> $kategorien
 * @var string|null $fehler
 * @var string|null $erfolg
 */

use MeinSlip\Domain\Catalog\Angebote;

$angebot ??= null;
$kategorien ??= [];
$fehler ??= null;
$erfolg ??= null;

/** Ganzzahlige Cent als Euro-Wert fuer ein Zahlenfeld — ohne Gleitkomma. */
$euro = static function (int $cent): string {
    return intdiv($cent, 100) . '.' . str_pad((string) ($cent % 100), 2, '0', STR_PAD_LEFT);
};
?>
<?php if ($angebot === null): ?>
    <section class="ms-abschnitt">
        <article class="card elev-sm">
            <h1 class="card-title"><?= te('markt.angebotsfehler.angebot_unbekannt') ?></h1>
            <p><a class="btn btn-primary" href="/verkaufen"><?= te('markt.verkaufen_titel') ?></a></p>
        </article>
    </section>
<?php else: ?>
    <?php
    $angebotId = (int) $angebot['id'];
    $status = (string) $angebot['status'];
    $waehrung = (string) $angebot['waehrung'];
    $veraenderbar = in_array($status, [Angebote::STATUS_ENTWURF, Angebote::STATUS_PAUSIERT], true);
    $optionen = is_array($angebot['optionen'] ?? null) ? $angebot['optionen'] : [];
    ?>
    <section class="ms-abschnitt">
        <div>
            <p class="ms-kicker"><?= te('markt.bearbeiten_kicker') ?></p>
            <?php // Der Titel stammt von der Verkaeuferin und darf 190 Zeichen ohne eine
                  // einzige Leerstelle enthalten. Ohne Umbruch an beliebiger Stelle
                  // schoebe eine solche Zeichenkette auf 360 px die ganze Seite auseinander. ?>
            <h1 style="font-size:clamp(1.75rem,4vw,2.5rem);overflow-wrap:anywhere"><?= e((string) $angebot['titel']) ?></h1>
            <p style="margin-top:var(--space-3)">
                <span class="tag tag-neutral"><?= te('markt.status.' . $status) ?></span>
            </p>
        </div>

        <?php if ($erfolg !== null): ?>
            <p class="card" role="status"><?= te('markt.erfolg.' . $erfolg) ?></p>
        <?php endif; ?>

        <?php if ($fehler !== null): ?>
            <p class="ms-fehler" role="alert"><?= te('markt.angebotsfehler.' . $fehler) ?></p>
        <?php endif; ?>

        <article class="card elev-sm">
            <p class="card-kicker"><?= te('markt.ablauf_titel') ?></p>

            <?php if ($status === Angebote::STATUS_ENTWURF): ?>
                <p class="card-body"><?= te('markt.einreichen_hinweis') ?></p>
                <form method="post" action="/verkaufen/<?= $angebotId ?>/einreichen" style="margin:0">
                    <?= \MeinSlip\Http\Formularschutz::feld() ?>
                    <button class="btn btn-primary" type="submit"><?= te('markt.einreichen') ?></button>
                </form>
            <?php elseif ($status === Angebote::STATUS_AKTIV): ?>
                <form method="post" action="/verkaufen/<?= $angebotId ?>" style="margin:0">
                    <?= \MeinSlip\Http\Formularschutz::feld() ?>
                    <input type="hidden" name="aktion" value="pausieren">
                    <button class="btn btn-secondary" type="submit"><?= te('markt.aktion_pausieren') ?></button>
                </form>
            <?php elseif ($status === Angebote::STATUS_PAUSIERT): ?>
                <form method="post" action="/verkaufen/<?= $angebotId ?>" style="margin:0">
                    <?= \MeinSlip\Http\Formularschutz::feld() ?>
                    <input type="hidden" name="aktion" value="fortsetzen">
                    <button class="btn btn-primary" type="submit"><?= te('markt.aktion_fortsetzen') ?></button>
                </form>
            <?php endif; ?>

            <?php if ($status !== Angebote::STATUS_ENTFERNT): ?>
                <form method="post" action="/verkaufen/<?= $angebotId ?>" style="margin:0">
                    <?= \MeinSlip\Http\Formularschutz::feld() ?>
                    <input type="hidden" name="aktion" value="entfernen">
                    <button class="btn btn-ghost" type="submit"><?= te('markt.aktion_entfernen') ?></button>
                </form>
                <p class="card-meta"><?= te('markt.aktion_entfernen_hinweis') ?></p>
            <?php endif; ?>
        </article>
    </section>

    <section class="ms-abschnitt" aria-labelledby="stammdaten">
        <div>
            <p class="ms-kicker"><?= te('markt.stammdaten_kicker') ?></p>
            <h2 id="stammdaten"><?= te('markt.bearbeiten_stammdaten') ?></h2>
        </div>

        <?php if (!$veraenderbar): ?>
            <p class="card"><?= te('markt.bearbeiten_gesperrt') ?></p>
        <?php else: ?>
            <form class="ms-formular" method="post" action="/verkaufen/<?= $angebotId ?>">
                <?= \MeinSlip\Http\Formularschutz::feld() ?>
                <input type="hidden" name="aktion" value="stammdaten">
                <?php // Kaestchen melden sich nur angekreuzt. Dieses Steuerfeld sagt der
                      // Route, dass die beiden Lieferwege ueberhaupt im Formular standen. ?>
                <input type="hidden" name="lieferwege" value="ja">

                <div class="field">
                    <label for="titel"><?= te('markt.feld_titel') ?></label>
                    <input class="input" type="text" id="titel" name="titel" required maxlength="190"
                           value="<?= e((string) $angebot['titel']) ?>">
                </div>

                <div class="field">
                    <label for="kategorie_id"><?= te('markt.feld_kategorie') ?></label>
                    <select class="input" id="kategorie_id" name="kategorie_id" required>
                        <?php foreach ($kategorien as $kategorie): ?>
                            <option value="<?= (int) $kategorie['id'] ?>"
                                <?= (int) $kategorie['id'] === (int) $angebot['kategorie_id'] ? 'selected' : '' ?>><?= te('kategorie.' . (string) $kategorie['schluessel']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="beschreibung"><?= te('markt.feld_beschreibung') ?></label>
                    <textarea class="input" id="beschreibung" name="beschreibung" rows="6"><?= e((string) ($angebot['beschreibung'] ?? '')) ?></textarea>
                </div>

                <div class="field">
                    <label for="grundpreis"><?= te('markt.feld_grundpreis') ?></label>
                    <input class="input" type="number" id="grundpreis" name="grundpreis" required
                           min="0.01" step="0.01" inputmode="decimal"
                           value="<?= e($euro((int) $angebot['grundpreis_cent'])) ?>">
                    <span class="hinweis"><?= te('markt.hinweis_grundpreis') ?></span>
                </div>

                <div class="field">
                    <label for="bearbeitungstage"><?= te('markt.feld_bearbeitungstage') ?></label>
                    <input class="input" type="number" id="bearbeitungstage" name="bearbeitungstage" required
                           min="1" max="90" step="1" inputmode="numeric"
                           value="<?= (int) $angebot['bearbeitungstage'] ?>">
                </div>

                <div class="field">
                    <label class="radio">
                        <input type="checkbox" name="versand_moeglich" value="ja"
                            <?= (int) $angebot['versand_moeglich'] === 1 ? 'checked' : '' ?>>
                        <span class="dot"></span>
                        <span><?= te('markt.feld_versand') ?></span>
                    </label>
                    <label class="radio">
                        <input type="checkbox" name="uebergabe_moeglich" value="ja"
                            <?= (int) $angebot['uebergabe_moeglich'] === 1 ? 'checked' : '' ?>>
                        <span class="dot"></span>
                        <span><?= te('markt.feld_uebergabe') ?></span>
                    </label>
                </div>

                <div class="field">
                    <label for="uebergabe_region"><?= te('markt.feld_uebergabe_region') ?></label>
                    <input class="input" type="text" id="uebergabe_region" name="uebergabe_region" maxlength="40"
                           value="<?= e((string) ($angebot['uebergabe_region'] ?? '')) ?>">
                    <span class="hinweis"><?= te('markt.hinweis_uebergabe_region') ?></span>
                </div>

                <button class="btn btn-primary btn-block" type="submit"><?= te('markt.bearbeiten_speichern') ?></button>
            </form>
        <?php endif; ?>
    </section>

    <section class="ms-abschnitt" aria-labelledby="optionen">
        <div>
            <p class="ms-kicker"><?= te('markt.konfigurator_kicker') ?></p>
            <h2 id="optionen"><?= te('markt.optionen_titel') ?></h2>
            <p class="ms-hero__unterzeile" style="margin-top:var(--space-4)"><?= te('markt.optionen_unterzeile') ?></p>
        </div>

        <?php if ($optionen === []): ?>
            <p class="card"><?= te('markt.optionen_leer') ?></p>
        <?php else: ?>
            <?php
            /*
             * Sechs Spalten passen auf 360 px nicht nebeneinander: die Kopfzeilen
             * allein, dazu die Tags "Spezifikation" und "Pflichtangabe" und der
             * Knopf "Entfernen" brauchen mehr als die 326 px, die die Huelle uebrig
             * laesst. Ohne eigenen Bildlauf scrollt das Dokument statt der Tabelle —
             * die Spalte "Aktion" mit dem Entfernen-Knopf steht dann ausserhalb des
             * Bildes, und beim Zurueckschieben geht die Zeilenzuordnung verloren.
             *
             * tabindex="0" ist Pflicht, nicht Zierde: In den Spalten Schluessel,
             * Bezeichnung, Art und Aufpreis steht nichts Fokussierbares, ueber das
             * sich der Behaelter per Tastatur hinscrollen liesse. Chrome macht
             * Bildlaufbehaelter seit 127 von sich aus fokussierbar, Safari nicht.
             * WCAG 2.1.1: Was die Maus erreicht, muss die Tastatur auch erreichen.
             * role="region" braucht dazu einen Namen, sonst steht der Bereich
             * namenlos in der Landmarkenliste.
             */
            ?>
            <div class="ms-breit" tabindex="0" role="region" aria-label="<?= te('markt.optionen_titel') ?>">
                <table class="table">
                    <thead>
                    <tr>
                        <th scope="col"><?= te('markt.option_schluessel') ?></th>
                        <th scope="col"><?= te('markt.option_bezeichnung') ?></th>
                        <th scope="col"><?= te('markt.option_art') ?></th>
                        <th scope="col"><?= te('markt.option_aufpreis') ?></th>
                        <th scope="col"><?= te('markt.konfigurator_spezifikation') ?></th>
                        <?php if ($veraenderbar): ?>
                            <th scope="col"><?= te('markt.spalte_aktion') ?></th>
                        <?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($optionen as $option): ?>
                        <tr>
                            <?php // Der Schluessel erlaubt 80 Zeichen aus [a-z0-9_] — eine
                                  // Zeichenkette voellig ohne Umbruchstelle. Ohne Umbruch an
                                  // beliebiger Stelle belegte allein diese Spalte rund 440 px
                                  // und schoebe die uebrigen Spalten weit nach rechts. ?>
                            <td style="overflow-wrap:anywhere"><?= e((string) $option['schluessel']) ?></td>
                            <td style="overflow-wrap:anywhere"><?= e((string) $option['bezeichnung']) ?></td>
                            <td><?= te('markt.art.' . (string) $option['art']) ?></td>
                            <td><?= e(geld((int) $option['aufpreis_cent'], $waehrung)) ?></td>
                            <td>
                                <?php if ((int) $option['ist_spezifikation'] === 1): ?>
                                    <span class="tag tag-accent-2"><?= te('markt.konfigurator_spezifikation') ?></span>
                                <?php endif; ?>
                                <?php if ((int) $option['pflicht'] === 1): ?>
                                    <span class="tag tag-accent"><?= te('markt.konfigurator_pflicht') ?></span>
                                <?php endif; ?>
                            </td>
                            <?php if ($veraenderbar): ?>
                                <td>
                                    <form method="post" action="/verkaufen/<?= $angebotId ?>" style="margin:0">
                                        <?= \MeinSlip\Http\Formularschutz::feld() ?>
                                        <input type="hidden" name="aktion" value="option_entfernen">
                                        <input type="hidden" name="schluessel" value="<?= e((string) $option['schluessel']) ?>">
                                        <button class="btn btn-ghost" type="submit"><?= te('markt.option_entfernen') ?></button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($veraenderbar): ?>
            <form class="ms-formular" method="post" action="/verkaufen/<?= $angebotId ?>">
                <?= \MeinSlip\Http\Formularschutz::feld() ?>
                <input type="hidden" name="aktion" value="option_setzen">

                <h3><?= te('markt.optionen_neu') ?></h3>

                <div class="field">
                    <label for="schluessel"><?= te('markt.option_schluessel') ?></label>
                    <input class="input" type="text" id="schluessel" name="schluessel" required
                           maxlength="80" pattern="[a-z0-9_]{2,80}" autocapitalize="none">
                    <span class="hinweis"><?= te('markt.option_hinweis_schluessel') ?></span>
                </div>

                <div class="field">
                    <label for="bezeichnung"><?= te('markt.option_bezeichnung') ?></label>
                    <input class="input" type="text" id="bezeichnung" name="bezeichnung" required maxlength="190">
                </div>

                <div class="field">
                    <label for="art"><?= te('markt.option_art') ?></label>
                    <select class="input" id="art" name="art">
                        <?php foreach (Angebote::OPTIONSARTEN as $art): ?>
                            <option value="<?= e($art) ?>"><?= te('markt.art.' . $art) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="aufpreis"><?= te('markt.option_aufpreis') ?></label>
                    <input class="input" type="number" id="aufpreis" name="aufpreis"
                           min="0" step="0.01" inputmode="decimal" value="0.00">
                    <span class="hinweis"><?= te('markt.option_hinweis_aufpreis') ?></span>
                </div>

                <div class="field">
                    <label for="erlaeuterung"><?= te('markt.option_erlaeuterung') ?></label>
                    <textarea class="input" id="erlaeuterung" name="erlaeuterung" rows="3"></textarea>
                </div>

                <div class="field">
                    <label for="reihenfolge"><?= te('markt.option_reihenfolge') ?></label>
                    <input class="input" type="number" id="reihenfolge" name="reihenfolge"
                           min="0" step="1" inputmode="numeric" value="0">
                </div>

                <div class="field">
                    <label class="radio">
                        <input type="checkbox" name="ist_spezifikation" value="ja" checked>
                        <span class="dot"></span>
                        <span><?= te('markt.option_ist_spezifikation') ?></span>
                    </label>
                    <span class="hinweis"><?= te('markt.option_hinweis_spezifikation') ?></span>
                    <label class="radio">
                        <input type="checkbox" name="pflicht" value="ja">
                        <span class="dot"></span>
                        <span><?= te('markt.option_pflicht') ?></span>
                    </label>
                </div>

                <button class="btn btn-primary btn-block" type="submit"><?= te('markt.option_speichern') ?></button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>
