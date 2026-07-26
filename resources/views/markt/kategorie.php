<?php

declare(strict_types=1);

/**
 * Katalog einer Kategorie.
 *
 * Der Kategorieschluessel steht in der Adresse und aendert sich nie; die
 * Bezeichnung kommt aus resources/lang/de-DE/kategorie.php und darf sich
 * jederzeit aendern, ohne dass eine Adresse bricht.
 *
 * Gestaltung nach design/MeinSlip App.dc.html, Screen 04: bildgefuehrte
 * Kacheln, zwei Spalten schon auf dem Telefon, der Preis als groesste Zahl
 * der Karte, das Pseudonym mit dem Siegel der Identitaetspruefung daneben.
 * Die GANZE Kachel ist der Verweis — auf dem Telefon ist die Flaeche das
 * Bedienelement.
 *
 * Jede Zeile in $angebote darf zusaetzlich den Schluessel 'vorschau' tragen:
 * das Ergebnis von Medien::ersteVorschau() oder null. Fehlt er, erscheint die
 * Kachel ohne Bild — die Liste bleibt also auch dann vollstaendig, wenn die
 * Medienschicht ausfaellt.
 *
 * @var array<string,mixed>|null $kategorie
 * @var list<array<string,mixed>> $angebote
 * @var int $anzahl
 * @var int $seite
 * @var int $seiten
 * @var string $pfad
 * @var bool $gestoert
 */

$kategorie ??= null;
$angebote ??= [];
$anzahl ??= 0;
$seite ??= 1;
$seiten ??= 1;
$pfad ??= '';
$gestoert ??= false;

$schloss = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
    . 'stroke-width="1.7" stroke-linecap="round" aria-hidden="true">'
    . '<rect x="5" y="10.5" width="14" height="9.5" rx="2.2"/><path d="M8.4 10.5V8a3.6 3.6 0 0 1 7.2 0v2.5"/></svg>';

$siegel = '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">'
    . '<path d="M12 2l2.4 1.6 2.9-.3 1 2.7 2.4 1.7-.7 2.8.7 2.8-2.4 1.7-1 2.7-2.9-.3L12 22l-2.4-1.6-2.9.3-1-2.7-2.4-1.7.7-2.8-.7-2.8 2.4-1.7 1-2.7 2.9.3z"/>'
    . '<path d="M8.6 12.2l2.3 2.3 4.4-4.6" fill="none" stroke="var(--color-surface)" stroke-width="2.2" '
    . 'stroke-linecap="round" stroke-linejoin="round"/></svg>';
?>
<?php if ($gestoert): ?>
    <section class="ms-abschnitt">
        <article class="card elev-sm">
            <h1 class="card-title"><?= te('markt.gestoert_titel') ?></h1>
            <p class="card-body"><?= te('markt.gestoert_text') ?></p>
        </article>
    </section>
<?php elseif ($kategorie === null): ?>
    <section class="ms-abschnitt">
        <article class="card elev-sm">
            <h1 class="card-title"><?= te('markt.kategorie_unbekannt_titel') ?></h1>
            <p class="card-body"><?= te('markt.kategorie_unbekannt_text') ?></p>
            <p><a class="btn btn-primary" href="/entdecken"><?= te('markt.kategorie_zurueck') ?></a></p>
        </article>
    </section>
<?php else: ?>
    <section class="ms-abschnitt">
        <div class="ms-kopfzeile">
            <div>
                <p class="ms-kicker"><?= te('markt.kategorie_kicker') ?></p>
                <h1 style="font-size:clamp(1.75rem,4vw,2.5rem)"><?= te('kategorie.' . (string) $kategorie['schluessel']) ?></h1>
            </div>
            <p class="ms-kopfzeile__neben"><?= te('markt.kategorie_anzahl', ['anzahl' => $anzahl]) ?></p>
        </div>

        <?php if ($angebote === []): ?>
            <article class="card elev-sm">
                <h2 class="card-title"><?= te('markt.kategorie_leer_titel') ?></h2>
                <p class="card-body"><?= te('markt.kategorie_leer_text') ?></p>
            </article>
        <?php else: ?>
            <div class="ms-raster--kacheln">
                <?php foreach ($angebote as $angebot): ?>
                    <?php $vorschau = is_array($angebot['vorschau'] ?? null) ? $angebot['vorschau'] : null; ?>
                    <a class="ms-kachel" href="/angebot/<?= (int) $angebot['id'] ?>">
                        <span class="ms-kachel__bild">
                            <?php /*
                                   * DIE KACHEL ZEIGT IM KATALOG NIE EIN ALS NICHT
                                   * JUGENDFREI GEKENNZEICHNETES BILD — auch der
                                   * Eigentuemerin nicht.
                                   *
                                   * Auf der Angebotsseite entscheidet
                                   * Medien::explizitSichtbar() je Person; hier
                                   * nicht, und das ist Absicht statt
                                   * Bequemlichkeit: Der Katalog ist die eine
                                   * Seite, die man mit offenem Bildschirm in der
                                   * Bahn durchblaettert. Was die Eigentuemerin
                                   * hier saehe, saehe der Sitznachbar mit. Sie
                                   * sieht ihr Bild auf ihrer Angebotsseite und
                                   * beim Bearbeiten — beides Seiten, die man
                                   * absichtlich aufruft.
                                   *
                                   * Zugleich ist das Schloss eine ehrliche
                                   * Auskunft: Das Angebot HAT ein Bild, es ist
                                   * nur nichts fuer diese Ansicht.
                                   */ ?>
                            <?php if ($vorschau !== null && $vorschau['explizit'] === true): ?>
                                <span class="ms-gesperrt__schloss">
                                    <?= $schloss ?>
                                    <?= te('medien.gesperrt_kachel') ?>
                                </span>
                            <?php elseif ($vorschau !== null): ?>
                                <img src="/medien/angebot/<?= (int) $vorschau['id'] ?>"
                                     alt="<?= te('medien.bild_alt') ?>"
                                     loading="lazy">
                            <?php endif; ?>
                        </span>
                        <span class="ms-kachel__inhalt">
                            <span class="ms-kachel__titel"><?= e((string) $angebot['titel']) ?></span>
                            <span class="ms-kachel__meta">
                                <?= e((string) $angebot['verkaeufer_pseudonym']) ?>
                                <span class="ms-siegel"><?= $siegel ?></span>
                            </span>
                            <span class="ms-kachel__preiszeile">
                                <span class="ms-kachel__preis"><?= e(geld((int) $angebot['grundpreis_cent'], (string) $angebot['waehrung'])) ?></span>
                                <span class="ms-kachel__neben"><?= te('markt.angebot_bearbeitungstage', ['tage' => (int) $angebot['bearbeitungstage']]) ?></span>
                            </span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php // § 6 Abs. 1 PAngV: Der Hinweis zu Umsatzsteuer und
                  // Versandkosten steht im raeumlichen Zusammenhang mit den
                  // Preisen — direkt unter dem Raster, nicht im Seitenfuss. ?>
            <p class="ms-kachel__neben" style="font-size:0.6875rem"><?= te('markt.preis_hinweis') ?></p>
        <?php endif; ?>

        <?php if ($seiten > 1): ?>
            <nav class="ms-abschnitt" aria-label="<?= te('markt.blaettern') ?>" style="gap:var(--space-4)">
                <p class="text-muted"><?= te('markt.blaettern_stand', ['seite' => $seite, 'seiten' => $seiten]) ?></p>
                <p>
                    <?php if ($seite > 1): ?>
                        <a class="btn btn-secondary" href="/kategorie/<?= e($pfad) ?>?seite=<?= $seite - 1 ?>"><?= te('markt.blaettern_zurueck') ?></a>
                    <?php endif; ?>
                    <?php if ($seite < $seiten): ?>
                        <a class="btn btn-secondary" href="/kategorie/<?= e($pfad) ?>?seite=<?= $seite + 1 ?>"><?= te('markt.blaettern_weiter') ?></a>
                    <?php endif; ?>
                </p>
            </nav>
        <?php endif; ?>
    </section>
<?php endif; ?>
