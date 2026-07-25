<?php

declare(strict_types=1);

/**
 * Angebotsseite mit Konfigurator.
 *
 * DER KONFIGURATOR IST DER KERN, NICHT DAS BEIWERK. Ohne eine echte, vom
 * Kaeufer gesetzte Spezifikation ist die Ware nicht "nach Kundenspezifikation
 * angefertigt" im Sinne von § 312g Abs. 2 Nr. 1 BGB — dann traegt der
 * Widerrufsausschluss nicht, und getragene Waesche kaeme zurueck. Deshalb
 * tragen Pflichtfelder 'required', und app/Http/MarktRouten.php prueft
 * dieselbe Bedingung serverseitig noch einmal.
 *
 * Die Unterrichtung ueber den Ausschluss steht VOR dem Absendeknopf, nicht
 * darunter und nicht im Kleingedruckten: § 312d Abs. 1 BGB i. V. m.
 * Art. 246a § 1 Abs. 3 Nr. 1 EGBGB verlangt sie vor Abgabe der
 * Vertragserklaerung.
 *
 * @var array<string,mixed>|null $angebot
 * @var list<array<string,mixed>> $optionen
 * @var array<string,mixed>|null $verkaeufer
 * @var array<string,mixed>|null $kategorie
 * @var bool $darfKaufen
 * @var bool $angemeldet
 * @var string|null $fehler
 * @var array<string,string> $eingaben
 * @var bool $gestoert
 */

$angebot ??= null;
$optionen ??= [];
$verkaeufer ??= null;
$kategorie ??= null;
$darfKaufen ??= false;
$angemeldet ??= false;
$fehler ??= null;
$eingaben ??= [];
$gestoert ??= false;
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

    $hatSpezifikation = false;
    foreach ($optionen as $moeglich) {
        if ((int) $moeglich['ist_spezifikation'] === 1) {
            $hatSpezifikation = true;
        }
    }
    ?>
    <section class="ms-abschnitt">
        <div>
            <p class="ms-kicker"><?= te('markt.angebot_kicker') ?></p>
            <h1 style="font-size:clamp(1.75rem,4vw,2.5rem)"><?= e((string) $angebot['titel']) ?></h1>
            <?php if ($verkaeufer !== null): ?>
                <p class="text-muted" style="margin-top:var(--space-3)"><?= te('markt.angebot_von') ?>
                    <span class="tag tag-outline"><?= e((string) $verkaeufer['pseudonym']) ?></span>
                </p>
            <?php endif; ?>
        </div>

        <?php if ($fehler === 'kaufen_gesperrt'): ?>
            <article class="card elev-sm" role="alert">
                <h2 class="card-title"><?= te('markt.kaufen_gesperrt_titel') ?></h2>
                <p class="card-body"><?= te('markt.kaufen_gesperrt_text') ?></p>
            </article>
        <?php elseif ($fehler !== null): ?>
            <article class="card elev-sm" role="alert">
                <p class="ms-fehler"><?= te('markt.bestellfehler.' . $fehler) ?></p>
                <?php if ($fehler === 'guthaben'): ?>
                    <p class="card-body"><?= te('markt.bestellfehler.guthaben_erklaerung') ?></p>
                <?php endif; ?>
            </article>
        <?php endif; ?>

        <article class="card elev-sm">
            <p class="card-kicker"><?= te('markt.angebot_beschreibung') ?></p>
            <p class="card-body"><?= nl2br(e((string) ($angebot['beschreibung'] ?? ''))) ?></p>
            <p class="card-meta">
                <span class="tag tag-accent"><?= te('markt.angebot_grundpreis') ?>: <?= e(geld($grundpreis, $waehrung)) ?></span>
                <span class="tag tag-neutral"><?= te('markt.angebot_bearbeitungstage', ['tage' => (int) $angebot['bearbeitungstage']]) ?></span>
                <?php if ((int) $angebot['versand_moeglich'] === 1): ?>
                    <span class="tag tag-neutral"><?= te('markt.angebot_versand') ?></span>
                <?php endif; ?>
                <?php if ((int) $angebot['uebergabe_moeglich'] === 1): ?>
                    <span class="tag tag-neutral"><?= te('markt.angebot_uebergabe') ?></span>
                <?php endif; ?>
                <?php if (($angebot['uebergabe_region'] ?? null) !== null): ?>
                    <span class="tag tag-neutral"><?= te('markt.angebot_region', ['region' => (string) $angebot['uebergabe_region']]) ?></span>
                <?php endif; ?>
            </p>
            <?php if ($kategorie !== null): ?>
                <p class="card-meta">
                    <a href="/kategorie/<?= e((string) $kategorie['pfad']) ?>"><?= te('kategorie.' . (string) $kategorie['schluessel']) ?></a>
                </p>
            <?php endif; ?>
        </article>
    </section>

    <section class="ms-abschnitt" aria-labelledby="konfigurator">
        <div>
            <p class="ms-kicker"><?= te('markt.konfigurator_kicker') ?></p>
            <h2 id="konfigurator"><?= te('markt.konfigurator_titel') ?></h2>
            <p class="ms-hero__unterzeile" style="margin-top:var(--space-4)"><?= te('markt.konfigurator_unterzeile') ?></p>
        </div>

        <?php if (!$istAktiv): ?>
            <article class="card elev-sm">
                <h3 class="card-title"><?= te('markt.angebot_nicht_aktiv_titel') ?></h3>
                <p class="card-body"><?= te('markt.angebot_nicht_aktiv_text') ?></p>
            </article>
        <?php elseif (!$hatSpezifikation): ?>
            <article class="card elev-sm">
                <h3 class="card-title"><?= te('markt.konfigurator_keine_optionen_titel') ?></h3>
                <p class="card-body"><?= te('markt.konfigurator_keine_optionen_text') ?></p>
            </article>
        <?php elseif (!$angemeldet): ?>
            <article class="card elev-sm">
                <h3 class="card-title"><?= te('markt.anmeldung_noetig_titel') ?></h3>
                <p class="card-body"><?= te('markt.anmeldung_noetig_text') ?></p>
                <p>
                    <a class="btn btn-primary" href="/anmelden"><?= te('markt.anmelden_knopf') ?></a>
                    <a class="btn btn-secondary" href="/registrieren"><?= te('markt.registrieren_knopf') ?></a>
                </p>
            </article>
        <?php elseif (!$darfKaufen): ?>
            <article class="card elev-sm">
                <h3 class="card-title"><?= te('markt.kaufen_gesperrt_titel') ?></h3>
                <p class="card-body"><?= te('markt.kaufen_gesperrt_text') ?></p>
            </article>
        <?php else: ?>
            <?php // Das Bestellformular liegt in einer eigenen Vorlage — es ist der
                  // rechtlich empfindlichste Teil der Seite und soll einzeln lesbar
                  // bleiben. Die Variablen dieser Vorlage gelten dort weiter. ?>
            <?php include __DIR__ . '/bestellen.php'; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>
