<?php

declare(strict_types=1);

/**
 * Ein eigenes Angebot bearbeiten.
 *
 * DIE SEITE FÜHRT VON OBEN NACH UNTEN. Vorher standen Statuskasten, Stammdaten,
 * Bilder und Konfigurator gleichrangig untereinander, und niemand wusste, was
 * als Nächstes zu tun ist. Jetzt:
 *
 *   1. Oben steht GENAU EINE Karte mit dem nächsten Schritt — abhängig davon,
 *      was dem Angebot noch fehlt und in welchem Status es ist.
 *   2. Darunter drei nummerierte Schritte: Angaben, Bilder, Wünsche.
 *   3. Ganz unten, abgesetzt, das endgültige Zurückziehen.
 *
 * DER DRITTE SCHRITT IST EINGEKLAPPT. Wünsche der Käufer (Optionen) braucht ein
 * Angebot erst, wenn über die Kasse bestellt wird, und die ist abgeschaltet.
 * Wer sie nicht braucht, soll sie nicht ausfüllen müssen, um zu verstehen, dass
 * er sie nicht braucht.
 *
 * Schlüssel und Reihenfolge einer Option fragt das Formular nicht mehr ab:
 * MarktRouten vergibt beides. Ob eine Angabe die Ware zur Anfertigung nach
 * Kundenwunsch macht (§ 312g Abs. 2 Nr. 1 BGB), folgt aus der Art der Eingabe
 * und nicht aus einem Kästchen, das niemand versteht.
 *
 * enctype="multipart/form-data" und MAX_FILE_SIZE VOR dem Dateifeld sind beim
 * Bild-Upload Pflicht: Ohne das erste bleibt $_FILES leer, ohne das zweite
 * bricht ein zu grosser Upload nicht früh ab. Die echte Grenze zieht
 * Bilder::MAX_BYTES.
 *
 * @var array<string,mixed>|null $angebot
 * @var list<array<string,mixed>> $kategorien
 * @var string|null $fehler
 * @var string|null $erfolg
 * @var bool $bestellbar
 * @var bool $bestellvorgangAktiv
 * @var list<array<string,mixed>> $medien
 * @var string|null $medienfehler
 * @var string|null $medienerfolg
 */

use MeinSlip\Domain\Catalog\Angebote;
use MeinSlip\Domain\Media\Bilder;
use MeinSlip\Domain\Media\Medien;

$angebot ??= null;
$kategorien ??= [];
$fehler ??= null;
$erfolg ??= null;
$bestellbar ??= false;
$bestellvorgangAktiv ??= false;
$medien ??= [];
$medienfehler ??= null;
$medienerfolg ??= null;

$grenzeBilder = Medien::JE_ANGEBOT;
$grenzeBytes = Bilder::MAX_BYTES;
$grenzeKante = Bilder::MAX_KANTE;

$medienZahlen = [
    'anzahl' => $grenzeBilder,
    'mb' => intdiv($grenzeBytes, 1024 * 1024),
    'kante' => $grenzeKante,
];

// Punkt als Dezimaltrenner: Das ist die Schreibweise, die <input type="number">
// verlangt — unabhängig davon, was der Browser dem Menschen anzeigt.
$euro = static function (int $cent): string {
    return intdiv($cent, 100) . '.' . str_pad((string) ($cent % 100), 2, '0', STR_PAD_LEFT);
};

$feld = static fn (): string => \MeinSlip\Http\Formularschutz::feld();
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
    $hatBild = $medien !== [];
    $hatText = trim((string) ($angebot['beschreibung'] ?? '')) !== '';
    $aktion = '/verkaufen/' . $angebotId;
    ?>
    <section class="ms-abschnitt">
        <div>
            <p class="ms-kicker"><a href="/verkaufen"><?= te('markt.bearb_zurueck') ?></a></p>
            <h1 style="overflow-wrap:anywhere"><?= e((string) $angebot['titel']) ?></h1>
            <p>
                <span class="tag <?= $status === Angebote::STATUS_AKTIV || $status === Angebote::STATUS_GESPERRT ? 'tag-accent' : 'tag-neutral' ?>"><?= te('markt.status.' . $status) ?></span>
            </p>
        </div>

        <?php if ($erfolg !== null): ?>
            <p class="ms-hinweisbox ms-hinweisbox--ok" role="status"><?= te('markt.erfolg.' . $erfolg) ?></p>
        <?php endif; ?>

        <?php if ($fehler !== null): ?>
            <p class="ms-fehler" role="alert"><?= te('markt.angebotsfehler.' . $fehler) ?></p>
        <?php endif; ?>

        <?php // --- Der nächste Schritt: genau eine Karte, genau eine Hauptaktion. ?>
        <article class="card elev-sm ms-naechster">
            <?php if ($status === Angebote::STATUS_GESPERRT): ?>
                <h2 class="card-title"><?= te('markt.gesperrt_titel') ?></h2>
                <p class="card-body"><?= te('markt.gesperrt_text') ?></p>
                <p class="card-body"><?= te('markt.gesperrt_begruendung') ?></p>
                <div class="ms-zeile"><a class="btn btn-primary" href="/profil"><?= te('markt.gesperrt_zum_profil') ?></a></div>

            <?php elseif ($status === Angebote::STATUS_ENTFERNT): ?>
                <h2 class="card-title"><?= te('markt.bearb_entfernt_titel') ?></h2>
                <p class="card-body"><?= te('markt.aktion_entfernen_hinweis') ?></p>
                <div class="ms-zeile"><a class="btn btn-primary" href="/verkaufen/neu"><?= te('markt.bearb_neues') ?></a></div>

            <?php elseif ($status === Angebote::STATUS_AKTIV): ?>
                <h2 class="card-title"><?= te('markt.bearb_aktiv_titel') ?></h2>
                <p class="card-body"><?= te('markt.bearb_aktiv_text') ?></p>
                <div class="ms-zeile">
                    <a class="btn btn-primary" href="/angebot/<?= $angebotId ?>"><?= te('markt.verkaufen_ansehen') ?></a>
                    <form method="post" action="<?= $aktion ?>" style="margin:0">
                        <?= $feld() ?>
                        <input type="hidden" name="aktion" value="pausieren">
                        <button class="btn btn-secondary" type="submit"><?= te('markt.bearb_pausieren') ?></button>
                    </form>
                </div>

            <?php elseif ($status === Angebote::STATUS_PAUSIERT): ?>
                <h2 class="card-title"><?= te('markt.bearb_pausiert_titel') ?></h2>
                <p class="card-body"><?= te('markt.bearb_pausiert_text') ?></p>
                <form method="post" action="<?= $aktion ?>" class="ms-zeile">
                    <?= $feld() ?>
                    <input type="hidden" name="aktion" value="fortsetzen">
                    <button class="btn btn-primary" type="submit"><?= te('markt.aktion_fortsetzen') ?></button>
                </form>

            <?php elseif (!$hatBild): ?>
                <?php // Entwurf ohne Bild: Das Bild ist der Schritt, der am meisten bringt. ?>
                <p class="card-kicker"><?= te('markt.bearb_naechster') ?></p>
                <h2 class="card-title"><?= te('markt.bearb_bild_fehlt_titel') ?></h2>
                <p class="card-body"><?= te('markt.bearb_bild_fehlt_text') ?></p>
                <div class="ms-zeile">
                    <a class="btn btn-primary" href="#bilder"><?= te('markt.bearb_bild_fehlt_knopf') ?></a>
                    <form method="post" action="<?= $aktion ?>/veroeffentlichen" style="margin:0">
                        <?= $feld() ?>
                        <button class="btn btn-ghost" type="submit"><?= te('markt.bearb_ohne_bild') ?></button>
                    </form>
                </div>

            <?php else: ?>
                <p class="card-kicker"><?= te('markt.bearb_naechster') ?></p>
                <h2 class="card-title"><?= te('markt.bearb_fertig_titel') ?></h2>
                <p class="card-body"><?= te('markt.veroeffentlichen_hinweis') ?></p>
                <form method="post" action="<?= $aktion ?>/veroeffentlichen" class="ms-zeile">
                    <?= $feld() ?>
                    <button class="btn btn-primary" type="submit"><?= te('markt.veroeffentlichen') ?></button>
                </form>
            <?php endif; ?>
        </article>

        <?php if ($veraenderbar): ?>
            <?php // Die Übersicht der Schritte, zum Anspringen. ?>
            <ol class="ms-schrittleiste" aria-label="<?= te('markt.bearb_schritte') ?>">
                <li class="ms-schrittleiste__fertig"><a href="#angaben"><?= te('markt.bearb_schritt_angaben') ?></a></li>
                <li class="<?= $hatBild ? 'ms-schrittleiste__fertig' : '' ?>"><a href="#bilder"><?= te('markt.bearb_schritt_bilder') ?></a></li>
                <li class="<?= $optionen !== [] ? 'ms-schrittleiste__fertig' : '' ?>"><a href="#wuensche"><?= te('markt.bearb_schritt_wuensche') ?></a></li>
            </ol>
        <?php endif; ?>

        <?php if ($bestellvorgangAktiv && !$bestellbar && $status !== Angebote::STATUS_ENTFERNT): ?>
            <article class="card elev-sm">
                <h2 class="card-title"><?= te('markt.nicht_bestellbar_titel') ?></h2>
                <p class="card-body"><?= te('markt.nicht_bestellbar_text') ?></p>
            </article>
        <?php endif; ?>
    </section>

    <?php if ($status !== Angebote::STATUS_ENTFERNT && $status !== Angebote::STATUS_GESPERRT): ?>

    <?php // ================= Schritt 1: Angaben ================= ?>
    <section class="ms-abschnitt ms-schritt" id="angaben" aria-labelledby="angaben-titel">
        <div>
            <p class="ms-schritt__nummer">1</p>
            <h2 id="angaben-titel"><?= te('markt.bearb_angaben_titel') ?></h2>
            <p class="hinweis"><?= te('markt.bearb_angaben_text') ?></p>
        </div>

        <?php if (!$veraenderbar): ?>
            <p class="ms-hinweisbox"><?= te('markt.bearb_erst_pausieren') ?></p>
        <?php else: ?>
            <form class="card elev-sm ms-formular" method="post" action="<?= $aktion ?>">
                <?= $feld() ?>
                <input type="hidden" name="aktion" value="stammdaten">
                <?php // Marke dafür, dass die Lieferwege mitgeschickt werden: Ein
                      // nicht angekreuztes Kästchen schickt gar nichts. ?>
                <input type="hidden" name="lieferwege" value="ja">

                <div class="field">
                    <label for="titel"><?= te('markt.feld_titel') ?></label>
                    <input class="input" type="text" id="titel" name="titel" required maxlength="190"
                           value="<?= e((string) $angebot['titel']) ?>">
                    <span class="hinweis"><?= te('markt.bearb_hinweis_titel') ?></span>
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
                    <textarea class="input" id="beschreibung" name="beschreibung" rows="5"
                              placeholder="<?= te('markt.bearb_beschreibung_beispiel') ?>"><?= e((string) ($angebot['beschreibung'] ?? '')) ?></textarea>
                    <span class="hinweis"><?= te('markt.hinweis_beschreibung') ?></span>
                </div>

                <div class="ms-zweispaltig">
                    <div class="field">
                        <label for="grundpreis"><?= te('markt.bearb_feld_preis') ?></label>
                        <input class="input" type="number" id="grundpreis" name="grundpreis" required
                               min="0.01" step="0.01" inputmode="decimal"
                               value="<?= e($euro((int) $angebot['grundpreis_cent'])) ?>">
                    </div>
                    <div class="field">
                        <label for="bearbeitungstage"><?= te('markt.bearb_feld_tage') ?></label>
                        <input class="input" type="number" id="bearbeitungstage" name="bearbeitungstage" required
                               min="1" max="90" step="1" inputmode="numeric"
                               value="<?= (int) $angebot['bearbeitungstage'] ?>">
                    </div>
                </div>
                <span class="hinweis"><?= te('markt.bearb_hinweis_tage') ?></span>

                <div class="field">
                    <span><?= te('markt.bearb_lieferung') ?></span>
                    <label class="radio">
                        <input type="checkbox" name="versand_moeglich" value="ja"
                            <?= (int) $angebot['versand_moeglich'] === 1 ? 'checked' : '' ?>>
                        <span class="dot"></span>
                        <span><?= te('markt.bearb_versand') ?></span>
                    </label>
                    <label class="radio">
                        <input type="checkbox" name="uebergabe_moeglich" value="ja"
                            <?= (int) $angebot['uebergabe_moeglich'] === 1 ? 'checked' : '' ?>>
                        <span class="dot"></span>
                        <span><?= te('markt.bearb_uebergabe') ?></span>
                    </label>
                </div>

                <div class="field">
                    <label for="uebergabe_region"><?= te('markt.bearb_feld_region') ?></label>
                    <input class="input" type="text" id="uebergabe_region" name="uebergabe_region" maxlength="40"
                           placeholder="<?= te('markt.bearb_region_beispiel') ?>"
                           value="<?= e((string) ($angebot['uebergabe_region'] ?? '')) ?>">
                    <span class="hinweis"><?= te('markt.bearb_hinweis_region') ?></span>
                </div>

                <button class="btn btn-primary btn-block" type="submit"><?= te('markt.bearb_angaben_speichern') ?></button>
            </form>
        <?php endif; ?>
    </section>

    <?php // ================= Schritt 2: Bilder ================= ?>
    <section class="ms-abschnitt ms-schritt" id="bilder" aria-labelledby="bilder-titel">
        <div>
            <p class="ms-schritt__nummer">2</p>
            <h2 id="bilder-titel"><?= te('markt.bearb_bilder_titel') ?></h2>
            <p class="hinweis"><?= te('markt.bearb_bilder_text', $medienZahlen) ?></p>
        </div>

        <?php if ($medienerfolg !== null): ?>
            <p class="ms-hinweisbox ms-hinweisbox--ok" role="status"><?= te('medien.erfolg.' . $medienerfolg) ?></p>
        <?php endif; ?>

        <?php // Das Fehlerbild steht direkt über dem Formular, damit der Grund
              // neben dem Feld steht, an dem er sich beheben lässt. ?>
        <?php if ($medienfehler !== null): ?>
            <p class="ms-fehler" role="alert"><?= te('medien.fehler.' . $medienfehler, $medienZahlen) ?></p>
        <?php endif; ?>

        <?php if ($medien !== []): ?>
            <div class="ms-bildraster">
                <?php foreach ($medien as $nummer => $bild): ?>
                    <?php // Diese Seite sieht nur die Eigentümerin, und sie darf ihr eigenes
                          // Bild immer scharf sehen — auch ein nicht jugendfreies. ?>
                    <article class="ms-bildraster__eintrag">
                        <img src="/medien/angebot/<?= (int) $bild['id'] ?>" alt="<?= te('medien.bild_alt') ?>" loading="lazy">
                        <p class="card-meta">
                            <?php if ((int) $nummer === 0): ?>
                                <span class="tag tag-accent"><?= te('markt.bearb_titelbild') ?></span>
                            <?php endif; ?>
                            <?php if ($bild['explizit'] === true): ?>
                                <span class="tag tag-neutral" title="<?= te('medien.gesperrt_eigen') ?>"><?= te('medien.explizit_marke') ?></span>
                            <?php endif; ?>
                        </p>
                        <form method="post" action="/medien/entfernen/<?= (int) $bild['id'] ?>" style="margin:0">
                            <?= $feld() ?>
                            <button class="btn btn-ghost" type="submit"><?= te('medien.entfernen') ?></button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (count($medien) >= $grenzeBilder): ?>
            <p class="ms-hinweisbox" role="status"><?= te('medien.voll', $medienZahlen) ?></p>
        <?php else: ?>
            <form class="card elev-sm ms-formular" method="post" action="/medien/hinzufuegen/<?= $angebotId ?>"
                  enctype="multipart/form-data">
                <?= $feld() ?>
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= $grenzeBytes ?>">

                <div class="field">
                    <label for="bild"><?= te($hatBild ? 'markt.bearb_bild_weiteres' : 'markt.bearb_bild_erstes') ?></label>
                    <input class="input" type="file" id="bild" name="bild" required
                           accept="<?= e(\MeinSlip\Http\MedienRouten::accept()) ?>">
                    <span class="hinweis"><?= te('medien.hinweis_datei', $medienZahlen) ?></span>
                </div>

                <?php // DIE FRAGE HAT KEINE VORBELEGUNG. Ein 'checked' auf "nein"
                      // wäre die bequeme Antwort und damit die, die ungelesen
                      // durchgeht. Bleibt die Wahl aus, behandelt
                      // MedienRouten::hinzufuegen() das Bild als nicht jugendfrei. ?>
                <div class="field" role="radiogroup" aria-labelledby="explizit-frage">
                    <span id="explizit-frage"><?= te('medien.explizit_frage') ?></span>
                    <label class="radio">
                        <input type="radio" name="explizit" value="nein" required>
                        <span class="dot"></span>
                        <span><?= te('medien.explizit_nein') ?></span>
                    </label>
                    <label class="radio">
                        <input type="radio" name="explizit" value="ja" required>
                        <span class="dot"></span>
                        <span><?= te('medien.explizit_ja') ?></span>
                    </label>
                </div>

                <button class="btn btn-primary btn-block" type="submit"><?= te('medien.hochladen') ?></button>
            </form>
        <?php endif; ?>
    </section>

    <?php // ================= Schritt 3: Wünsche der Käufer ================= ?>
    <section class="ms-abschnitt ms-schritt" id="wuensche" aria-labelledby="wuensche-titel">
        <div>
            <p class="ms-schritt__nummer">3</p>
            <h2 id="wuensche-titel"><?= te('markt.bearb_wuensche_titel') ?> <span class="tag tag-neutral"><?= te('markt.bearb_freiwillig') ?></span></h2>
            <p class="hinweis"><?= te('markt.bearb_wuensche_text') ?></p>
        </div>

        <?php if ($optionen !== []): ?>
            <ul class="ms-linkverwaltung">
                <?php foreach ($optionen as $option): ?>
                    <li class="card elev-sm">
                        <div class="ms-linkverwaltung__text">
                            <strong><?= e((string) $option['bezeichnung']) ?></strong>
                            <span class="card-meta">
                                <?= te('markt.bearb_art.' . (string) $option['art']) ?>
                                <?php if ((int) $option['aufpreis_cent'] > 0): ?>
                                    · + <?= e(geld((int) $option['aufpreis_cent'], $waehrung)) ?>
                                <?php endif; ?>
                                <?php if ((int) $option['pflicht'] === 1): ?>
                                    · <?= te('markt.konfigurator_pflicht') ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if ($veraenderbar): ?>
                            <form method="post" action="<?= $aktion ?>" style="margin:0">
                                <?= $feld() ?>
                                <input type="hidden" name="aktion" value="option_entfernen">
                                <input type="hidden" name="schluessel" value="<?= e((string) $option['schluessel']) ?>">
                                <button class="btn btn-ghost" type="submit"><?= te('markt.option_entfernen') ?></button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($veraenderbar): ?>
            <details class="card elev-sm ms-aufklappen"<?= $fehler !== null && str_starts_with($fehler, 'option') ? ' open' : '' ?>>
                <summary><?= te($optionen === [] ? 'markt.bearb_wunsch_erster' : 'markt.bearb_wunsch_weiterer') ?></summary>

                <form class="ms-formular" method="post" action="<?= $aktion ?>">
                    <?= $feld() ?>
                    <input type="hidden" name="aktion" value="option_setzen">
                    <?php // Kein Schlüssel, keine Reihenfolge, kein Spezifikations-Kästchen:
                          // 'einfach' sagt MarktRouten, alle drei selbst zu bestimmen. ?>
                    <input type="hidden" name="einfach" value="ja">

                    <div class="field">
                        <label for="bezeichnung"><?= te('markt.bearb_wunsch_frage') ?></label>
                        <input class="input" type="text" id="bezeichnung" name="bezeichnung" required maxlength="190"
                               placeholder="<?= te('markt.bearb_wunsch_beispiel') ?>">
                    </div>

                    <div class="field" role="radiogroup" aria-labelledby="art-frage">
                        <span id="art-frage"><?= te('markt.bearb_wunsch_art') ?></span>
                        <?php foreach ([Angebote::ART_ZAHL, Angebote::ART_FREITEXT, Angebote::ART_AUSWAHL] as $nummer => $art): ?>
                            <label class="radio">
                                <input type="radio" name="art" value="<?= e($art) ?>" required <?= $nummer === 0 ? 'checked' : '' ?>>
                                <span class="dot"></span>
                                <span><?= te('markt.bearb_art_lang.' . $art) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="field">
                        <label for="aufpreis"><?= te('markt.bearb_wunsch_aufpreis') ?></label>
                        <input class="input" type="number" id="aufpreis" name="aufpreis"
                               min="0" step="0.01" inputmode="decimal" value="0.00">
                        <span class="hinweis"><?= te('markt.bearb_wunsch_aufpreis_hinweis') ?></span>
                    </div>

                    <div class="field">
                        <label class="radio">
                            <input type="checkbox" name="pflicht" value="ja">
                            <span class="dot"></span>
                            <span><?= te('markt.bearb_wunsch_pflicht') ?></span>
                        </label>
                    </div>

                    <button class="btn btn-primary btn-block" type="submit"><?= te('markt.bearb_wunsch_speichern') ?></button>
                </form>
            </details>
        <?php endif; ?>
    </section>

    <?php // ================= Ganz unten: zurückziehen ================= ?>
    <section class="ms-abschnitt">
        <details class="ms-aufklappen ms-aufklappen--leise">
            <summary><?= te('markt.bearb_entfernen_frage') ?></summary>
            <p class="hinweis"><?= te('markt.aktion_entfernen_hinweis') ?></p>
            <form method="post" action="<?= $aktion ?>" style="margin:0">
                <?= $feld() ?>
                <input type="hidden" name="aktion" value="entfernen">
                <button class="btn btn-secondary" type="submit"><?= te('markt.aktion_entfernen') ?></button>
            </form>
        </details>
    </section>

    <?php endif; ?>
<?php endif; ?>
