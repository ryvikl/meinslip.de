<?php

declare(strict_types=1);

/**
 * Katalog einer Kategorie.
 *
 * Der Kategorieschluessel steht in der Adresse und aendert sich nie; die
 * Bezeichnung kommt aus resources/lang/de-DE/kategorie.php und darf sich
 * jederzeit aendern, ohne dass eine Adresse bricht.
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
        <div>
            <p class="ms-kicker"><?= te('markt.kategorie_kicker') ?></p>
            <h1 style="font-size:clamp(1.75rem,4vw,2.5rem)"><?= te('kategorie.' . (string) $kategorie['schluessel']) ?></h1>
            <p class="text-muted" style="margin-top:var(--space-3)"><?= te('markt.kategorie_anzahl', ['anzahl' => $anzahl]) ?></p>
        </div>

        <?php if ($angebote === []): ?>
            <article class="card elev-sm">
                <h2 class="card-title"><?= te('markt.kategorie_leer_titel') ?></h2>
                <p class="card-body"><?= te('markt.kategorie_leer_text') ?></p>
            </article>
        <?php else: ?>
            <div class="ms-raster">
                <?php foreach ($angebote as $angebot): ?>
                    <?php $vorschau = is_array($angebot['vorschau'] ?? null) ? $angebot['vorschau'] : null; ?>
                    <article class="card elev-sm">
                        <?php if ($vorschau !== null): ?>
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
                            <?php if ($vorschau['explizit'] === true): ?>
                                <div class="ms-gesperrt" style="min-height:9rem;background:var(--color-neutral-900)">
                                    <span class="ms-gesperrt__schloss"><?= te('medien.gesperrt_kachel') ?></span>
                                </div>
                            <?php else: ?>
                                <a href="/angebot/<?= (int) $angebot['id'] ?>">
                                    <img src="/medien/angebot/<?= (int) $vorschau['id'] ?>"
                                         alt="<?= te('medien.bild_alt') ?>"
                                         loading="lazy"
                                         style="width:100%;height:auto;display:block;border-radius:var(--radius-md)">
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <p class="card-kicker"><?= e((string) $angebot['verkaeufer_pseudonym']) ?></p>
                        <h2 class="card-title">
                            <a href="/angebot/<?= (int) $angebot['id'] ?>"><?= e((string) $angebot['titel']) ?></a>
                        </h2>
                        <p class="card-body"><?= e(mb_substr((string) ($angebot['beschreibung'] ?? ''), 0, 180)) ?></p>
                        <p class="card-meta">
                            <span class="tag tag-accent"><?= e(geld((int) $angebot['grundpreis_cent'], (string) $angebot['waehrung'])) ?></span>
                            <span><?= te('markt.angebot_bearbeitungstage', ['tage' => (int) $angebot['bearbeitungstage']]) ?></span>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($seiten > 1): ?>
            <nav class="ms-abschnitt" aria-label="<?= te('markt.blaettern') ?>">
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
