<?php

declare(strict_types=1);

/**
 * Ein Angebot — die Seite, auf der sich jemand entscheidet.
 *
 * AUFBAU, VON OBEN NACH UNTEN: Bild, Titel und Preis, die eine Hauptaktion,
 * wer anbietet, die Eckdaten, die Beschreibung. Vorher begann die Seite mit
 * einer Karte "Beschreibung", in der Preis, Etiketten und Rechtshinweis
 * steckten, und die Bilder kamen erst danach unter einer eigenen Überschrift.
 *
 * DIE HAUPTAKTION IST "NACHRICHT SCHREIBEN", solange die Kasse aus ist
 * (BESTELLVORGANG_AKTIV). Ist sie an, kommt darunter der Konfigurator aus
 * bestellen.php, und die Nachricht wird zur Nebenaktion. Wer nach dem
 * Bestellknopf sucht: Er steht in bestellen.php und trägt den Wortlaut aus
 * § 312j Abs. 3 BGB.
 *
 * EIN ALS NICHT JUGENDFREI GEKENNZEICHNETES BILD wird nur gezeigt, wenn
 * Medien::explizitSichtbar() das für diese Person erlaubt ($medienExplizitSichtbar).
 * Sonst steht an seiner Stelle ein Schloss — kein weichgezeichnetes Bild. Die
 * Route liefert es ohnehin nicht aus; die Vorlage fragt es deshalb gar nicht
 * erst an.
 *
 * 'preis_hinweis' erfüllt § 6 Abs. 1 PAngV und steht direkt am Preis.
 *
 * @var array<string,mixed>|null $angebot
 * @var list<array<string,mixed>> $optionen
 * @var array<string,mixed>|null $verkaeufer
 * @var array<string,mixed>|null $verkaeuferProfil öffentliches Profil oder null
 * @var bool $verkaeuferGeprueft
 * @var bool $eigenes
 * @var bool $chatBereit
 * @var array<string,mixed>|null $kategorie
 * @var bool $darfKaufen
 * @var bool $angemeldet
 * @var bool $bestellvorgangAktiv
 * @var bool $bestellbar
 * @var string|null $fehler
 * @var array<string,mixed> $eingaben
 * @var bool $gestoert
 * @var list<array<string,mixed>> $medien
 * @var bool $medienExplizitSichtbar
 * @var bool $darfAnschreiben
 */

$angebot ??= null;
$optionen ??= [];
$verkaeufer ??= null;
$verkaeuferProfil ??= null;
$verkaeuferGeprueft ??= false;
$eigenes ??= false;
$kategorie ??= null;
$darfKaufen ??= false;
$angemeldet ??= false;
$bestellvorgangAktiv ??= false;
$bestellbar ??= false;
$fehler ??= null;
$eingaben ??= [];
$gestoert ??= false;
$medien ??= [];
$medienExplizitSichtbar ??= false;
$darfAnschreiben ??= false;

$schloss = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><rect x="5" y="10.5" width="14" height="9.5" rx="2.2"/><path d="M8.4 10.5V8a3.6 3.6 0 0 1 7.2 0v2.5"/></svg>';
?>
<?php if ($gestoert): ?>
    <section class="ms-abschnitt">
        <article class="card elev-sm">
            <h1 class="card-title"><?= te('markt.gestoert_titel') ?></h1>
            <p class="card-body"><?= te('markt.gestoert_text') ?></p>
        </article>
    </section>
<?php elseif ($angebot === null): ?>
    <section class="ms-abschnitt">
        <article class="card elev-sm">
            <h1 class="card-title"><?= te('markt.angebot_unbekannt_titel') ?></h1>
            <p class="card-body"><?= te('markt.angebot_unbekannt_text') ?></p>
            <p><a class="btn btn-primary" href="/entdecken"><?= te('markt.kategorie_zurueck') ?></a></p>
        </article>
    </section>
<?php else: ?>
    <?php
    $waehrung = (string) $angebot['waehrung'];
    $grundpreis = (int) $angebot['grundpreis_cent'];
    $istAktiv = (string) $angebot['status'] === \MeinSlip\Domain\Catalog\Angebote::STATUS_AKTIV;
    $angebotId = (int) $angebot['id'];
    $beschreibung = trim((string) ($angebot['beschreibung'] ?? ''));

    // Wird von bestellen.php gebraucht, das unten eingebunden wird.
    $lieferwege = [];
    if ((int) $angebot['versand_moeglich'] === 1) {
        $lieferwege[] = 'versand';
    }
    if ((int) $angebot['uebergabe_moeglich'] === 1) {
        $lieferwege[] = 'uebergabe';
    }
    $gewaehlteLieferart = (string) ($eingaben['lieferart'] ?? '');
    if (!in_array($gewaehlteLieferart, $lieferwege, true)) {
        $gewaehlteLieferart = $lieferwege[0] ?? '';
    }

    $konfigurator = $bestellvorgangAktiv && $bestellbar && $istAktiv && $angemeldet && $darfKaufen;
    $anschreibenMoeglich = $angemeldet && $darfAnschreiben && $verkaeufer !== null;
    $pseudonym = $verkaeufer !== null ? (string) $verkaeufer['pseudonym'] : '';
    $name = $verkaeuferProfil !== null ? (string) $verkaeuferProfil['name'] : $pseudonym;
    $hatProfilbild = $verkaeuferProfil !== null && is_string($verkaeuferProfil['bild_pfad']) && $verkaeuferProfil['bild_pfad'] !== '';
    ?>
    <article class="ms-angebot">
        <?php // --- Bilder: eine Reihe zum Wischen, jedes Bild in voller Breite. ?>
        <div class="ms-angebot__bilder">
            <?php if ($medien === []): ?>
                <div class="ms-galerie ms-galerie--leer">
                    <span><?= te('markt.seite_kein_bild') ?></span>
                </div>
            <?php else: ?>
                <div class="ms-galerie" tabindex="0" role="region" aria-label="<?= te('medien.titel') ?>">
                    <?php foreach ($medien as $nummer => $bild): ?>
                        <?php $zeigbar = $bild['explizit'] !== true || $medienExplizitSichtbar; ?>
                        <figure class="ms-galerie__bild">
                            <?php if ($zeigbar): ?>
                                <img src="/medien/angebot/<?= (int) $bild['id'] ?>" alt="<?= te('medien.bild_alt') ?>"
                                     <?= (int) $nummer === 0 ? '' : 'loading="lazy"' ?>>
                            <?php else: ?>
                                <span class="ms-galerie__schloss">
                                    <?= $schloss ?>
                                    <strong><?= te('medien.gesperrt_schloss') ?></strong>
                                    <span><?= te('medien.gesperrt_fremd') ?></span>
                                </span>
                            <?php endif; ?>
                            <?php if (count($medien) > 1): ?>
                                <figcaption><?= (int) $nummer + 1 ?> / <?= count($medien) ?></figcaption>
                            <?php endif; ?>
                        </figure>
                    <?php endforeach; ?>
                </div>
                <?php if (count($medien) > 1): ?>
                    <p class="ms-galerie__hinweis"><?= te('markt.seite_wischen') ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="ms-angebot__inhalt">
            <?php if ($kategorie !== null): ?>
                <p class="ms-kicker">
                    <a href="/kategorie/<?= e((string) $kategorie['pfad']) ?>">← <?= te('kategorie.' . (string) $kategorie['schluessel']) ?></a>
                </p>
            <?php endif; ?>

            <h1><?= e((string) $angebot['titel']) ?></h1>

            <div class="ms-angebot__preis">
                <span class="ms-zahl"><?= e(geld($grundpreis, $waehrung)) ?></span>
                <span class="hinweis"><?= te('markt.seite_preis_zusatz') ?></span>
            </div>

            <?php if ($fehler === 'kaufen_gesperrt'): ?>
                <div class="ms-fehler" role="alert">
                    <strong><?= te('markt.kaufen_gesperrt_titel') ?></strong>
                    <?= te('markt.kaufen_gesperrt_text') ?>
                </div>
            <?php elseif ($fehler !== null): ?>
                <div class="ms-fehler" role="alert">
                    <?= te('markt.bestellfehler.' . $fehler) ?>
                    <?php if ($fehler === 'guthaben'): ?>
                        <?= te('markt.bestellfehler.guthaben_erklaerung') ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!$istAktiv): ?>
                <p class="ms-hinweisbox" role="status">
                    <strong><?= te('markt.angebot_nicht_aktiv_titel') ?>.</strong>
                    <?= te('markt.angebot_nicht_aktiv_text') ?>
                </p>
            <?php endif; ?>

            <?php // --- Die eine Hauptaktion. ?>
            <div class="ms-angebot__aktion">
                <?php if ($chatBereit ?? false): ?>
                    <p class="ms-hinweisbox ms-hinweisbox--ok" role="status"><?= te('markt.seite_chat_bereit') ?></p>
                <?php endif; ?>
                <?php if ($eigenes): ?>
                    <a class="btn btn-primary btn-block" href="/verkaufen/<?= $angebotId ?>"><?= te('markt.seite_bearbeiten') ?></a>
                    <a class="btn btn-ghost btn-block" href="/nachrichten"><?= te('markt.nachricht_eigene') ?></a>
                <?php elseif ($anschreibenMoeglich): ?>
                    <form method="post" action="/nachrichten/neu" style="margin:0">
                        <?= \MeinSlip\Http\Formularschutz::feld() ?>
                        <input type="hidden" name="empfaenger_id" value="<?= (int) $verkaeufer['id'] ?>">
                        <input type="hidden" name="angebot_id" value="<?= $angebotId ?>">
                        <input type="hidden" name="zurueck" value="/angebot/<?= $angebotId ?>">
                        <button class="btn <?= $konfigurator ? 'btn-secondary' : 'btn-primary' ?> btn-block" type="submit"><?= te('markt.nachricht_schreiben') ?></button>
                    </form>
                    <p class="hinweis"><?= $konfigurator ? te('markt.kontakt_neben_bestellung') : te('markt.seite_nachricht_text') ?></p>
                <?php elseif (!$angemeldet): ?>
                    <a class="btn btn-primary btn-block" href="/anmelden"><?= te('markt.seite_anmelden_schreiben') ?></a>
                    <p class="hinweis"><?= te('markt.nachricht_anmeldung') ?> <a href="/registrieren"><?= te('markt.registrieren_knopf') ?></a></p>
                <?php endif; ?>
            </div>

            <?php // --- Wer anbietet. ?>
            <?php if ($verkaeufer !== null): ?>
                <?php $huelle = $verkaeuferProfil !== null ? 'a' : 'div'; ?>
                <<?= $huelle ?> class="ms-anbieter"<?= $verkaeuferProfil !== null ? ' href="/p/' . e(rawurlencode($pseudonym)) . '"' : '' ?>>
                    <span class="ms-profilbild ms-profilbild--klein">
                        <?php if ($hatProfilbild): ?>
                            <img src="/p/<?= e(rawurlencode($pseudonym)) ?>/bild" width="48" height="48" alt="">
                        <?php else: ?>
                            <?= e(mb_substr($name, 0, 1)) ?>
                        <?php endif; ?>
                    </span>
                    <span class="ms-anbieter__text">
                        <span class="hinweis"><?= te('markt.angebot_von') ?></span>
                        <strong><?= e($name) ?></strong>
                        <?php if ($verkaeuferGeprueft): ?>
                            <span class="tag tag-accent" title="<?= te('verifizierung.abzeichen_was_nicht') ?>"><?= te('verifizierung.abzeichen') ?></span>
                        <?php endif; ?>
                    </span>
                    <?php if ($verkaeuferProfil !== null): ?>
                        <span class="ms-anbieter__pfeil" aria-hidden="true">→</span>
                    <?php endif; ?>
                </<?= $huelle ?>>
            <?php endif; ?>

            <?php // --- Eckdaten als Liste statt als Etikettenwolke. ?>
            <ul class="ms-eckdaten">
                <li>
                    <span class="hinweis"><?= te('markt.seite_fertig') ?></span>
                    <strong><?= te('markt.seite_fertig_wert', ['tage' => (int) $angebot['bearbeitungstage']]) ?></strong>
                </li>
                <?php if ((int) $angebot['versand_moeglich'] === 1): ?>
                    <li>
                        <span class="hinweis"><?= te('markt.seite_versand') ?></span>
                        <strong><?= te('markt.seite_versand_wert') ?></strong>
                    </li>
                <?php endif; ?>
                <?php if ((int) $angebot['uebergabe_moeglich'] === 1): ?>
                    <li>
                        <span class="hinweis"><?= te('markt.seite_uebergabe') ?></span>
                        <strong><?= ($angebot['uebergabe_region'] ?? null) !== null && (string) $angebot['uebergabe_region'] !== ''
                            ? e((string) $angebot['uebergabe_region'])
                            : te('markt.seite_uebergabe_wert') ?></strong>
                    </li>
                <?php endif; ?>
            </ul>

            <?php if ($beschreibung !== ''): ?>
                <section class="ms-angebot__block">
                    <h2><?= te('markt.angebot_beschreibung') ?></h2>
                    <p class="ms-angebot__text"><?= e($beschreibung) ?></p>
                </section>
            <?php endif; ?>

            <?php // Solange die Kasse aus ist, sind die Wünsche eine Auskunft:
                  // Das kannst du bei ihr bestimmen — klär es im Chat. ?>
            <?php if (!$konfigurator && $optionen !== []): ?>
                <section class="ms-angebot__block">
                    <h2><?= te('markt.seite_wuensche') ?></h2>
                    <ul class="ms-wunschliste">
                        <?php foreach ($optionen as $option): ?>
                            <li>
                                <span><?= e((string) $option['bezeichnung']) ?></span>
                                <?php if ((int) $option['aufpreis_cent'] > 0): ?>
                                    <span class="hinweis">+ <?= e(geld((int) $option['aufpreis_cent'], $waehrung)) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="hinweis"><?= te('markt.seite_wuensche_text') ?></p>
                </section>
            <?php endif; ?>

            <p class="hinweis"><?= te('markt.preis_hinweis') ?></p>
        </div>
    </article>

    <?php // --- Bestellen über die Kasse, sobald sie freigeschaltet ist. ?>
    <?php if ($bestellvorgangAktiv && $istAktiv): ?>
        <section class="ms-abschnitt" aria-labelledby="konfigurator">
            <div>
                <p class="ms-kicker"><?= te('markt.konfigurator_kicker') ?></p>
                <h2 id="konfigurator"><?= te('markt.konfigurator_titel') ?></h2>
                <p class="hinweis"><?= te('markt.konfigurator_unterzeile') ?></p>
            </div>

            <?php if (!$bestellbar): ?>
                <article class="card elev-sm">
                    <h3 class="card-title"><?= te('markt.konfigurator_keine_optionen_titel') ?></h3>
                    <p class="card-body"><?= te('markt.konfigurator_keine_optionen_text') ?></p>
                </article>
            <?php elseif (!$angemeldet): ?>
                <article class="card elev-sm">
                    <h3 class="card-title"><?= te('markt.anmeldung_noetig_titel') ?></h3>
                    <p class="card-body"><?= te('markt.anmeldung_noetig_text') ?></p>
                    <div class="ms-zeile">
                        <a class="btn btn-primary" href="/anmelden"><?= te('markt.anmelden_knopf') ?></a>
                        <a class="btn btn-secondary" href="/registrieren"><?= te('markt.registrieren_knopf') ?></a>
                    </div>
                </article>
            <?php elseif (!$darfKaufen): ?>
                <article class="card elev-sm">
                    <h3 class="card-title"><?= te('markt.kaufen_gesperrt_titel') ?></h3>
                    <p class="card-body"><?= te('markt.kaufen_gesperrt_text') ?></p>
                </article>
            <?php else: ?>
                <?php include __DIR__ . '/bestellen.php'; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php // Der Meldeweg nach Art. 16 DSA: an jedem Angebot, ohne Anmeldung
          // erreichbar — aber als leiser Verweis am Seitenende, nicht als Karte
          // zwischen Bild und Preis. ?>
    <?php if (!$eigenes): ?>
        <p style="text-align:center">
            <a class="hinweis" href="/melden?art=angebot&amp;id=<?= $angebotId ?>"><?= te('markt.melden_knopf') ?></a>
        </p>
    <?php endif; ?>
<?php endif; ?>
