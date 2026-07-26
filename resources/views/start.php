<?php

declare(strict_types=1);

/**
 * Startseite — nach der Landing-Vorlage aus design/MeinSlip Landing.dc.html.
 *
 * Aufbau wie gezeichnet: geteilter Hero mit Telefonvorschau vor treibenden
 * Indigo-Flächen, das Versprechen-Band mit Hairline-Fugen, die drei
 * Bereichskarten mit Hebe-Zustand, der Creator-Abschnitt, das
 * Sicherheits-Band mit Ring-Symbolen und der zentrierte Abschluss über dem
 * Indigo-Schein.
 *
 * Die Telefonvorschau ist eine reine Illustration (aria-hidden) und zeigt
 * ausschliesslich Bausteine, die es gibt: Treuhand, Empfehlungen,
 * Nachrichten, beidseitig quittierte Übergaben. Kein gezeichnetes
 * Versprechen ohne gebaute Strecke dahinter.
 *
 * Gehört zur öffentlichen Zone: nicht pornografisch, damit sie indexierbar
 * bleibt. Suchmaschinen sind der einzige Wachstumskanal, der offensteht, weil
 * Meta, Google und TikTok keine Werbung für Erwachsenenangebote zulassen.
 * Siehe docs/04-features/discovery-taxonomie.md.
 *
 * @var array<string,mixed>|null $sitzung
 */

/** Strichzeichen im Stil des Systems: 1,7er-Strich, runde Kappen. */
$zeichen = static function (string $name, int $groesse = 22): string {
    $pfade = [
        'haken' => '<path d="M5.5 12.5 10 17l8.5-9"/>',
        'content' => '<path d="M4 6.5h16v11H4z"/><path d="M10 9.8l4.5 2.7-4.5 2.7z"/>',
        'paket' => '<path d="M4 8.2 12 4l8 4.2v7.6L12 20l-8-4.2z"/><path d="M4 8.2 12 12.4l8-4.2"/><path d="M12 12.4V20"/>',
        'ort' => '<path d="M12 21s6.5-6 6.5-11a6.5 6.5 0 1 0-13 0C5.5 15 12 21 12 21z"/><circle cx="12" cy="10" r="2.2"/>',
        'schild' => '<path d="M12 3l7 3v5c0 4.3-2.9 8-7 10-4.1-2-7-5.7-7-10V6z"/><path d="M8.8 12.2l2.3 2.3 4.1-4.4"/>',
        'wallet' => '<rect x="3" y="6" width="18" height="12.5" rx="2.5"/><path d="M3 10h18"/><circle cx="16.6" cy="14.2" r="1.1" fill="currentColor" stroke="none"/>',
        'spur' => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.4"/><circle cx="12" cy="12" r="0.8" fill="currentColor" stroke="none"/>',
        'plus' => '<path d="M12 6v12M6 12h12"/>',
    ];

    return '<svg width="' . $groesse . '" height="' . $groesse . '" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" '
        . 'aria-hidden="true">' . ($pfade[$name] ?? '') . '</svg>';
};
?>
<section class="ms-vollbreit ms-glowfeld">
    <div class="ms-glow ms-glow--indigo" aria-hidden="true"></div>
    <div class="ms-glow ms-glow--akzent" aria-hidden="true"></div>

    <div class="ms-hero--geteilt">
        <div class="ms-hero__text">
            <span class="ms-hero__marke">
                <span class="ms-hero__punkt"></span>
                <?= te('allgemein.hero_marke') ?>
            </span>

            <h1><?= te('allgemein.hero_titel_1') ?><br><?= te('allgemein.hero_titel_2') ?></h1>

            <p class="ms-hero__unterzeile"><?= te('allgemein.hero_unterzeile') ?></p>

            <div class="ms-hero__knoepfe">
                <a class="btn btn-primary" href="/entdecken"><?= te('allgemein.hero_entdecken') ?></a>
                <a class="btn btn-secondary" href="/fuer-creator"><?= te('allgemein.hero_creator') ?></a>
            </div>

            <ul class="ms-merkmale">
                <?php foreach (['geprueft', 'treuhand', 'versand', 'werbefrei'] as $merkmal): ?>
                    <li><?= $zeichen('haken', 16) ?><?= te('allgemein.merkmal_' . $merkmal) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php /*
               * DIE TELEFONVORSCHAU. Dekorativ und deshalb komplett aus dem
               * Zugänglichkeitsbaum genommen: Sie wiederholt nur, was links
               * schon steht. Wer sie sieht, sieht die App — wer sie nicht
               * sieht, verpasst nichts.
               */ ?>
        <div class="ms-telefon__buehne" aria-hidden="true">
            <div class="ms-telefon__schein"></div>
            <div class="ms-telefon">
                <div class="ms-telefon__schirm">
                    <div class="ms-telefon__gruss">
                        <span class="ms-avatar" style="width:34px;height:34px;font-size:0.75rem">A</span>
                        <div>
                            <small><?= te('allgemein.vorschau_gruss') ?></small>
                            <strong><?= te('allgemein.vorschau_name') ?></strong>
                        </div>
                        <span class="tag tag-outline" style="font-size:0.5625rem"><?= te('allgemein.vorschau_marke') ?></span>
                    </div>

                    <div class="ms-kontokarte">
                        <span class="ms-kicker"><?= te('allgemein.vorschau_treuhand_kicker') ?></span>
                        <span class="ms-zahl"><?= te('allgemein.vorschau_treuhand_betrag') ?></span>
                        <small style="font-size:0.625rem;color:var(--color-neutral-400)"><?= te('allgemein.vorschau_treuhand_text') ?></small>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:var(--space-2)">
                        <span class="ms-kicker" style="font-size:0.625rem"><?= te('allgemein.vorschau_empfohlen') ?></span>
                        <div class="ms-telefon__kacheln">
                            <?php foreach ([1, 2] as $nummer): ?>
                                <div class="ms-telefon__kachel">
                                    <div class="ms-telefon__bildflaeche"></div>
                                    <div style="display:flex;align-items:center;gap:3px">
                                        <?= te('allgemein.vorschau_creator_' . $nummer) ?>
                                        <span class="ms-siegel"><?= $zeichen('schild', 10) ?></span>
                                    </div>
                                    <small style="font-size:0.5625rem;color:var(--color-neutral-600)"><?= te('allgemein.vorschau_creator_' . $nummer . '_region') ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:var(--space-2)">
                        <span class="ms-kicker" style="font-size:0.625rem"><?= te('allgemein.vorschau_nachrichten') ?></span>
                        <div class="ms-telefon__zeile">
                            <span class="ms-avatar" style="width:30px;height:30px;font-size:0.6875rem">L</span>
                            <div>
                                <?= te('allgemein.vorschau_creator_1') ?>
                                <small><?= te('allgemein.vorschau_nachricht_text') ?></small>
                            </div>
                            <span class="ms-hero__punkt" style="animation:none"></span>
                        </div>
                        <div class="ms-telefon__zeile ms-telefon__zeile--ruhig">
                            <span class="ms-symbolchip ms-symbolchip--klein ms-symbolchip--indigo" style="width:30px;height:30px"><?= $zeichen('schild', 15) ?></span>
                            <div>
                                <?= te('allgemein.vorschau_termin') ?>
                                <small><?= te('allgemein.vorschau_termin_status') ?></small>
                            </div>
                            <span class="ms-status ms-status--klein"><span class="ms-status__punkt"></span></span>
                        </div>
                    </div>

                    <nav class="ms-telefon__leiste">
                        <span><?= te('allgemein.nav_start') ?></span>
                        <span><?= te('allgemein.nav_entdecken') ?></span>
                        <span><?= te('allgemein.nav_nachrichten') ?></span>
                        <span><?= te('allgemein.nav_verkaufen') ?></span>
                        <span><?= te('allgemein.nav_profil') ?></span>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</section>

<section aria-labelledby="versprechen">
    <h2 id="versprechen" class="ms-nur-vorlesen"><?= te('allgemein.abschnitt_versprechen') ?></h2>
    <div class="ms-band ms-band--drei">
        <?php foreach (['diskret', 'sicher', 'direkt'] as $versprechen): ?>
            <div class="ms-band__zelle">
                <span class="ms-band__titel"><?= te('allgemein.vertrauen_' . $versprechen . '_titel') ?></span>
                <span class="ms-band__text"><?= te('allgemein.vertrauen_' . $versprechen . '_text') ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="ms-abschnitt" aria-labelledby="bereiche">
    <div class="ms-kopfzeile">
        <h2 id="bereiche"><?= te('allgemein.abschnitt_wege_titel_1') ?><br><?= te('allgemein.abschnitt_wege_titel_2') ?></h2>
        <p class="ms-kopfzeile__neben"><?= te('allgemein.abschnitt_wege_neben') ?></p>
    </div>

    <div class="ms-raster">
        <?php foreach ([
            'content' => 'content',
            'markt' => 'paket',
            'uebergabe' => 'ort',
        ] as $bereich => $bild): ?>
            <article class="ms-bereichskarte">
                <span class="ms-symbolchip"><?= $zeichen($bild) ?></span>
                <h3 style="font-size:1.25rem"><?= te('allgemein.bereich_' . $bereich . '_titel') ?></h3>
                <p class="card-body ms-band__text"><?= te('allgemein.bereich_' . $bereich . '_text') ?></p>
                <div class="ms-tagzeile">
                    <?php foreach ([1, 2, 3] as $marke): ?>
                        <span class="tag tag-neutral" style="font-size:0.625rem"><?= te('allgemein.bereich_' . $bereich . '_tag_' . $marke) ?></span>
                    <?php endforeach; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="ms-abschnitt" aria-labelledby="creator">
    <div style="display:grid;gap:calc(var(--space-8)*2);align-items:center" class="ms-creatorduett">
        <div style="display:flex;flex-direction:column;gap:var(--space-8)">
            <p class="ms-kicker ms-kicker--akzent"><?= te('allgemein.creator_kicker') ?></p>
            <h2 id="creator"><?= te('allgemein.creator_titel') ?></h2>

            <ul class="ms-merkmale" style="max-width:none;font-size:0.9375rem">
                <?php foreach ([1, 2, 3, 4, 5, 6] as $punkt): ?>
                    <li>
                        <span class="ms-symbolchip ms-symbolchip--klein" style="width:22px;height:22px;border-radius:7px"><?= $zeichen('haken', 12) ?></span>
                        <?= te('allgemein.creator_punkt_' . $punkt) ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="ms-hero__knoepfe">
                <a class="btn btn-primary" href="/registrieren" style="min-height:48px"><?= te('allgemein.creator_knopf') ?></a>
                <a class="btn btn-ghost" href="/fuer-creator" style="min-height:48px"><?= te('allgemein.creator_mehr') ?></a>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:var(--space-6);padding:var(--space-8);border-radius:16px;background:var(--ms-zwischenflaeche);box-shadow:var(--shadow-md)">
            <div style="display:flex;align-items:center;gap:var(--space-4)">
                <div style="flex:1;display:flex;flex-direction:column">
                    <span style="font-size:0.875rem;font-weight:var(--font-heading-weight)"><?= te('allgemein.creator_karte_kicker') ?></span>
                    <span style="font-size:0.6875rem;color:var(--color-neutral-600)"><?= te('allgemein.creator_karte_titel') ?></span>
                </div>
                <span class="tag tag-accent" style="font-size:0.625rem"><?= te('allgemein.creator_karte_marke') ?></span>
            </div>

            <div class="ms-zeilen">
                <?php foreach ([
                    1 => 'schild',
                    2 => 'plus',
                    3 => 'wallet',
                ] as $schritt => $bild): ?>
                    <div>
                        <div style="display:flex;align-items:center;gap:var(--space-4);min-width:0">
                            <span class="ms-symbolchip ms-symbolchip--klein"><?= $zeichen($bild, 17) ?></span>
                            <div style="display:flex;flex-direction:column;min-width:0">
                                <span style="font-size:0.8125rem"><?= te('allgemein.creator_schritt_' . $schritt . '_titel') ?></span>
                                <span style="font-size:0.6875rem;color:var(--color-neutral-600)"><?= te('allgemein.creator_schritt_' . $schritt . '_text') ?></span>
                            </div>
                        </div>
                        <span class="ms-zahl ms-zahl--mittel" style="font-size:1rem;color:var(--color-neutral-500)"><?= $schritt ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<section class="ms-abschnitt" aria-labelledby="sicherheit">
    <div class="ms-kopfzeile">
        <h2 id="sicherheit"><?= te('allgemein.abschnitt_sicherheit_titel_1') ?><br><?= te('allgemein.abschnitt_sicherheit_titel_2') ?></h2>
        <span class="ms-status" style="padding-bottom:var(--space-2)">
            <span class="ms-status__punkt"></span>
            <?= te('allgemein.abschnitt_sicherheit_status') ?>
        </span>
    </div>

    <div class="ms-band ms-band--vier">
        <?php foreach ([
            'identitaet' => 'schild',
            'treuhand' => 'wallet',
            'versand' => 'paket',
            'leak' => 'spur',
        ] as $modul => $bild): ?>
            <div class="ms-band__zelle">
                <span class="ms-ringsymbol"><?= $zeichen($bild) ?></span>
                <h3><?= te('allgemein.sicher_' . $modul . '_titel') ?></h3>
                <span class="ms-band__text" style="font-size:0.8125rem;flex:1"><?= te('allgemein.sicher_' . $modul . '_text') ?></span>
                <span class="ms-status ms-status--klein" style="margin-top:var(--space-2)">
                    <span class="ms-status__punkt" style="animation:none"></span>
                    <?= te('allgemein.sicher_' . $modul . '_status') ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($sitzung === null): ?>
    <section class="ms-vollbreit ms-abschluss" aria-labelledby="abschluss">
        <h2 id="abschluss"><?= te('allgemein.abschluss_titel') ?></h2>
        <p class="ms-hero__unterzeile" style="max-width:none;text-align:center"><?= te('allgemein.abschluss_unterzeile') ?></p>
        <div class="ms-hero__knoepfe" style="justify-content:center">
            <a class="btn btn-primary" href="/registrieren"><?= te('allgemein.abschluss_registrieren') ?></a>
            <a class="btn btn-secondary" href="/fuer-creator"><?= te('allgemein.abschluss_creator') ?></a>
        </div>
        <div class="ms-abschluss__zusagen">
            <?php foreach (['alter', 'diskret', 'verifiziert', 'werbefrei'] as $zusage): ?>
                <span class="tag tag-outline" style="font-size:0.6875rem;color:var(--color-neutral-500)"><?= te('allgemein.fuss_hinweis_' . $zusage) ?></span>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
