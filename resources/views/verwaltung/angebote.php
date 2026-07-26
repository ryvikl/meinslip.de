<?php

declare(strict_types=1);

/**
 * Die Arbeitsliste der Nachmoderation.
 *
 * ZUERST DIE GEMELDETEN ANGEBOTE. Seit dem Modellwechsel gibt es keine
 * Vorabprüfung mehr: Ein Angebot ist sofort sichtbar, die Verwaltung greift
 * erst auf Meldung hin ein. Diese Seite ist deshalb keine Prüfliste mehr,
 * sondern eine Meldeliste — und ihr wichtigstes Merkmal ist die zugesagte
 * Frist. Art. 16 Abs. 6 DSA verlangt eine zeitnahe Entscheidung; wird die
 * eigene Zusage gerissen, muss das ins Auge fallen und nicht als Datum
 * zwischen anderen Daten stehen.
 *
 * Die alte Liste steht darunter und nur dann, wenn sie nicht leer ist.
 * Migration 010 räumt sie einmalig aus; in einer frisch migrierten Datenbank
 * ist sie gar nicht sichtbar.
 *
 * @var array{zeilen: list<array<string,mixed>>, anzahl: int, seite: int, seiten: int, pro_seite: int} $gemeldet
 * @var array{zeilen: list<array<string,mixed>>, anzahl: int, seite: int, seiten: int, pro_seite: int} $liste
 * @var int $verwalterId
 * @var string $jetzt
 * @var string $statusAktiv
 * @var string $statusGesperrt
 * @var string $statusEntfernt
 * @var string|null $erfolg
 * @var string|null $fehler
 */

// Beide Listen blättern getrennt, tragen den Stand der jeweils anderen aber
// mit: Ein Sprung auf Seite 2 der Meldeliste darf die untere Liste nicht auf
// Seite 1 zurückwerfen.
$blaettern = static fn (int $seite): string => '/verwaltung/angebote?seite=' . $seite
    . '&altseite=' . $liste['seite'];
$blaetternAlt = static fn (int $seite): string => '/verwaltung/angebote?seite=' . $gemeldet['seite']
    . '&altseite=' . $seite;

// Alle Zeitangaben stehen als 'Y-m-d H:i:s' in UTC. In diesem Format ordnet
// ein Zeichenkettenvergleich chronologisch — dieselbe Annahme trifft
// Verwaltung::meldungen() für ihr eigenes Fristkennzeichen.
$ueberfaellig = static fn (mixed $frist): bool => is_string($frist) && $frist !== '' && $frist < $jetzt;
?>
<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('verwaltung.angebote_kicker') ?></p>
        <h1 style="font-size:clamp(1.5rem,4vw,2.25rem)"><?= te('verwaltung.angebote_titel') ?></h1>
        <p class="text-muted"><?= te('verwaltung.angebote_erklaerung') ?></p>
    </div>

    <?php if ($fehler !== null): ?>
        <p class="ms-fehler" role="alert"><?= te('verwaltung.fehler.' . $fehler) ?></p>
    <?php endif; ?>
    <?php if ($erfolg !== null): ?>
        <p class="card" role="status"><?= te('verwaltung.erfolg.' . $erfolg) ?></p>
    <?php endif; ?>

    <?php if ($gemeldet['zeilen'] === []): ?>
        <article class="card">
            <p class="card-body"><?= te('verwaltung.gemeldet_leer') ?></p>
        </article>
    <?php else: ?>
        <p class="text-muted"><?= te('verwaltung.anzahl_gesamt', ['anzahl' => $gemeldet['anzahl']]) ?></p>

        <?php foreach ($gemeldet['zeilen'] as $angebot): ?>
            <?php
            $kennung = (int) $angebot['id'];
            $status = (string) $angebot['status'];
            $gerissen = $ueberfaellig($angebot['zugesagt_bis']);

            // Die Gründe werden hier zusammengesetzt und nicht im HTML: Eine
            // Schleife zwischen zwei Tags liefert eine Aufzählung ohne
            // Trennzeichen, und ein Trennzeichen im Markup wäre sichtbarer
            // Text ohne Übersetzung.
            $grundliste = [];

            foreach ($angebot['gruende'] as $einGrund => $wieOft) {
                $grundliste[] = t('verwaltung.meldegrund_zaehler', [
                    'grund' => t('verwaltung.meldegrund.' . $einGrund),
                    'wieoft' => (int) $wieOft,
                ]);
            }
            ?>
            <article class="card elev-sm">
                <p class="card-kicker"><?= te('verwaltung.spalte.erste_meldung') ?>: <?= e((string) $angebot['erste_meldung_am']) ?></p>
                <h2 class="card-title"><?= e((string) $angebot['titel']) ?></h2>

                <p>
                    <?php if ($gerissen): ?>
                        <?php // Die gerissene Frist zuerst und als eigenes Merkmal — sie ist ein eigener Missstand. ?>
                        <span class="tag tag-accent"><?= te('verwaltung.frist_ueberschritten') ?></span>
                    <?php endif; ?>
                    <span class="tag tag-neutral"><?= te('verwaltung.meldegrund.' . (string) $angebot['grund']) ?></span>
                    <span class="tag tag-neutral"><?= (int) $angebot['meldungen_anzahl'] === 1 ? te('verwaltung.meldung_eine') : te('verwaltung.meldungen_anzahl', ['anzahl' => (int) $angebot['meldungen_anzahl']]) ?></span>
                    <span class="tag tag-outline"><?= te('verwaltung.angebot_status.' . $status) ?></span>
                </p>

                <p class="card-meta">
                    <?= te('verwaltung.spalte.verkaeufer') ?>:
                    <a href="/verwaltung/konten/<?= e((string) (int) $angebot['verkaeufer_id']) ?>"><?= e((string) $angebot['verkaeufer_pseudonym']) ?></a>
                    (<?= te('verwaltung.konto_status.' . (string) $angebot['verkaeufer_status']) ?>)
                    · <?= te('verwaltung.spalte.preis') ?>: <?= e(geld((int) $angebot['grundpreis_cent'], (string) $angebot['waehrung'])) ?>
                </p>

                <?php if ($angebot['zugesagt_bis'] !== null): ?>
                    <p class="card-meta"><?= te('verwaltung.spalte.frist') ?>: <?= e((string) $angebot['zugesagt_bis']) ?></p>
                <?php else: ?>
                    <?php // Altbestand aus der Zeit vor dem Meldeweg trägt keine Zusage. ?>
                    <p class="card-meta"><?= te('verwaltung.spalte.frist') ?>: <?= te('verwaltung.ohne') ?></p>
                <?php endif; ?>

                <?php if (count($grundliste) > 1): ?>
                    <p class="card-meta"><?= te('verwaltung.alle_meldegruende') ?>: <?= e(implode(' · ', $grundliste)) ?></p>
                <?php endif; ?>

                <?php if ($status === $statusAktiv): ?>
                    <p class="card-body"><a href="/angebot/<?= e((string) $kennung) ?>"><?= te('verwaltung.angebot_ansehen') ?></a></p>
                <?php else: ?>
                    <?php // Kein Verweis ins Leere: /angebot/{id} liefert alles ausser 'aktiv' als unbekannt aus. ?>
                    <p class="card-body"><?= te('verwaltung.angebot_nicht_oeffentlich') ?></p>
                <?php endif; ?>

                <?php if ((int) $angebot['verkaeufer_id'] === $verwalterId): ?>
                    <?php // Vier-Augen-Prinzip: Angebote::sperren() weist das ohnehin ab. ?>
                    <p class="ms-fehler"><?= te('verwaltung.angebot_vier_augen') ?></p>
                <?php elseif ($status === $statusEntfernt): ?>
                    <?php // 'entfernt' ist der Endzustand — von dort führt kein Übergang mehr weg. ?>
                    <p class="ms-fehler"><?= te('verwaltung.angebot_ist_entfernt') ?></p>
                <?php elseif ($status === $statusGesperrt): ?>
                    <form class="ms-formular" method="post" action="/verwaltung/angebote/<?= e((string) $kennung) ?>/entsperren">
                        <?= \MeinSlip\Http\Formularschutz::feld() ?>
                        <div class="field">
                            <label for="entsperrgrund-<?= e((string) $kennung) ?>"><?= te('verwaltung.angebot_entsperrgrund') ?></label>
                            <textarea class="input" id="entsperrgrund-<?= e((string) $kennung) ?>" name="grund" rows="2" required></textarea>
                            <span class="hinweis"><?= te('verwaltung.angebot_entsperrgrund_hinweis') ?></span>
                        </div>
                        <button class="btn btn-primary" type="submit"><?= te('verwaltung.angebot_entsperren') ?></button>
                    </form>
                <?php else: ?>
                    <form class="ms-formular" method="post" action="/verwaltung/angebote/<?= e((string) $kennung) ?>/sperren">
                        <?= \MeinSlip\Http\Formularschutz::feld() ?>
                        <div class="field">
                            <label for="sperrgrund-<?= e((string) $kennung) ?>"><?= te('verwaltung.angebot_sperrgrund') ?></label>
                            <textarea class="input" id="sperrgrund-<?= e((string) $kennung) ?>" name="grund" rows="2" required></textarea>
                            <span class="hinweis"><?= te('verwaltung.angebot_sperrgrund_hinweis') ?></span>
                        </div>
                        <button class="btn btn-primary" type="submit"><?= te('verwaltung.angebot_sperren') ?></button>
                        <p class="hinweis"><?= te('verwaltung.angebot_sperren_ziel') ?></p>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

        <?php if ($gemeldet['seiten'] > 1): ?>
            <p class="text-muted">
                <?php if ($gemeldet['seite'] > 1): ?>
                    <a href="<?= e($blaettern($gemeldet['seite'] - 1)) ?>"><?= te('verwaltung.blaettern_zurueck') ?></a>
                <?php endif; ?>
                <?= te('verwaltung.blaettern_seite', ['seite' => $gemeldet['seite'], 'gesamt' => $gemeldet['seiten']]) ?>
                <?php if ($gemeldet['seite'] < $gemeldet['seiten']): ?>
                    <a href="<?= e($blaettern($gemeldet['seite'] + 1)) ?>"><?= te('verwaltung.blaettern_weiter') ?></a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if ($liste['zeilen'] !== []): ?>
    <?php
    /*
     * Die auslaufende Vorabprüfung.
     *
     * Sie steht nur da, solange sie belegt ist. Migration 010 hat den Bestand
     * einmalig nach 'aktiv' gehoben; nachfüllen kann sie nur noch, wer ein
     * Angebot ausdrücklich zur Prüfung einreicht. Der Satz darüber sagt das,
     * damit niemand hier eine dauerhafte Pflicht vermutet und wartet.
     */
    ?>
    <section class="ms-abschnitt">
        <div>
            <h2 style="font-size:1.25rem"><?= te('verwaltung.altliste_titel') ?></h2>
            <p class="text-muted"><?= te('verwaltung.altliste_hinweis') ?></p>
        </div>

        <p class="text-muted"><?= te('verwaltung.anzahl_gesamt', ['anzahl' => $liste['anzahl']]) ?></p>

        <?php foreach ($liste['zeilen'] as $angebot): ?>
            <?php $kennung = (int) $angebot['id']; ?>
            <article class="card elev-sm">
                <p class="card-kicker"><?= te('verwaltung.spalte.eingereicht') ?>: <?= e((string) $angebot['angelegt_am']) ?></p>
                <h3 class="card-title"><?= e((string) $angebot['titel']) ?></h3>
                <p class="card-meta">
                    <?= te('verwaltung.spalte.verkaeufer') ?>: <?= e((string) $angebot['verkaeufer_pseudonym']) ?>
                    · <?= te('verwaltung.spalte.kategorie') ?>: <?= te('kategorie.' . $angebot['kategorie_schluessel']) ?>
                    · <?= te('verwaltung.spalte.preis') ?>: <?= e(geld((int) $angebot['grundpreis_cent'], (string) $angebot['waehrung'])) ?>
                </p>
                <p class="card-body"><?= e((string) $angebot['beschreibung']) ?></p>

                <?php if ((int) $angebot['verkaeufer_id'] === $verwalterId): ?>
                    <?php // Vier-Augen-Prinzip: Angebote::freigeben() weist das ohnehin ab. ?>
                    <p class="ms-fehler"><?= te('verwaltung.angebot_vier_augen') ?></p>
                <?php else: ?>
                    <form method="post" action="/verwaltung/angebote/<?= e((string) $kennung) ?>/freigeben">
                        <?= \MeinSlip\Http\Formularschutz::feld() ?>
                        <button class="btn btn-primary" type="submit"><?= te('verwaltung.angebot_freigeben') ?></button>
                    </form>

                    <form class="ms-formular" method="post" action="/verwaltung/angebote/<?= e((string) $kennung) ?>/ablehnen">
                        <?= \MeinSlip\Http\Formularschutz::feld() ?>
                        <div class="field">
                            <label for="grund-<?= e((string) $kennung) ?>"><?= te('verwaltung.angebot_grund') ?></label>
                            <textarea class="input" id="grund-<?= e((string) $kennung) ?>" name="grund" rows="2" required></textarea>
                            <span class="hinweis"><?= te('verwaltung.angebot_grund_hinweis') ?></span>
                        </div>
                        <button class="btn btn-ghost" type="submit"><?= te('verwaltung.angebot_ablehnen') ?></button>
                        <p class="hinweis"><?= te('verwaltung.angebot_abgelehnt_ziel') ?></p>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

        <?php if ($liste['seiten'] > 1): ?>
            <p class="text-muted">
                <?php if ($liste['seite'] > 1): ?>
                    <a href="<?= e($blaetternAlt($liste['seite'] - 1)) ?>"><?= te('verwaltung.blaettern_zurueck') ?></a>
                <?php endif; ?>
                <?= te('verwaltung.blaettern_seite', ['seite' => $liste['seite'], 'gesamt' => $liste['seiten']]) ?>
                <?php if ($liste['seite'] < $liste['seiten']): ?>
                    <a href="<?= e($blaetternAlt($liste['seite'] + 1)) ?>"><?= te('verwaltung.blaettern_weiter') ?></a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </section>
<?php endif; ?>
