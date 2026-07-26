<?php

declare(strict_types=1);

/**
 * Angebotsseite: Ware, Kontakt, Meldeweg — und, wenn er offen ist, der
 * Konfigurator.
 *
 * DIE HAUPTAKTION IST "NACHRICHT SCHREIBEN", NICHT "BESTELLEN". Der
 * Bestellvorgang ist aufsichtsrechtlich verriegelt (§ 1 Abs. 1 S. 2 Nr. 1
 * KWG), solange kein Zahlungsdienstleister angebunden ist. Solange
 * $bestellvorgangAktiv falsch ist, wird der Konfigurator deshalb GAR NICHT
 * gerendert — nicht ausgegraut, nicht versteckt, sondern nicht gebaut. Ein
 * Formular, das niemand absenden kann, ist ein Versprechen, das die Seite
 * nicht haelt.
 *
 * DER KONFIGURATOR BLEIBT DER KERN, SOBALD ER WIEDER DA IST. Ohne eine echte,
 * vom Kaeufer gesetzte Spezifikation ist die Ware nicht "nach
 * Kundenspezifikation angefertigt" im Sinne von § 312g Abs. 2 Nr. 1 BGB — dann
 * traegt der Widerrufsausschluss nicht, und getragene Waesche kaeme zurueck.
 * Deshalb tragen Pflichtfelder 'required', und app/Http/MarktRouten.php prueft
 * dieselbe Bedingung serverseitig noch einmal.
 *
 * Die Unterrichtung ueber den Ausschluss steht VOR dem Absendeknopf, nicht
 * darunter und nicht im Kleingedruckten: § 312d Abs. 1 BGB i. V. m.
 * Art. 246a § 1 Abs. 3 Nr. 1 EGBGB verlangt sie vor Abgabe der
 * Vertragserklaerung.
 *
 * DER MELDEWEG STEHT AUCH ABGEMELDET OFFEN. Art. 16 DSA verlangt ein
 * Melde- und Abhilfeverfahren, das jeder Person offensteht — ein Konto darf
 * keine Voraussetzung sein. Der Verweis traegt deshalb keinen Anmeldezwang.
 *
 * @var array<string,mixed>|null $angebot
 * @var list<array<string,mixed>> $optionen
 * @var array<string,mixed>|null $verkaeufer
 * @var array<string,mixed>|null $kategorie
 * @var bool $darfKaufen
 * @var bool $angemeldet
 * @var bool $bestellvorgangAktiv  Schalter BESTELLVORGANG_AKTIV
 * @var bool $bestellbar           Angebote::istBestellbar()
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
$bestellvorgangAktiv ??= false;
$bestellbar ??= false;
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

    $angebotId = (int) $angebot['id'];

    /*
     * Wann der Konfigurator ueberhaupt die Hauptaktion ist.
     *
     * Fuenf Bedingungen, und keine davon ist ersetzbar: der Schalter, die
     * Bestellbarkeit nach § 312g Abs. 2 Nr. 1 BGB (kommt aus
     * Angebote::istBestellbar(), nicht aus einer Zaehlung hier), der Status,
     * die Anmeldung und die Kauffaehigkeit. Faellt eine weg, tritt "Nachricht
     * schreiben" an seine Stelle — es gibt nie einen Bildschirm ohne
     * Hauptaktion.
     */
    $konfigurator = $bestellvorgangAktiv && $bestellbar && $istAktiv && $angemeldet && $darfKaufen;

    // Der Chat entsteht erst spaeter (Paket P2); bis dahin ist /nachrichten
    // eine ehrliche Platzhalterseite. Bewusst ein gewoehnlicher Verweis und
    // kein Zwischenzustand: Wenn der Chat steht, aendert sich hier nur das
    // Ziel. Wer abgemeldet ist, wird zur Anmeldung gefuehrt — schreiben kann
    // nur, wer ein Konto hat.
    $nachrichtZiel = $angemeldet ? '/nachrichten' : '/anmelden';
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
            <?php // § 6 Abs. 1 PAngV verlangt zum Preis die Angabe, dass die
                  // Umsatzsteuer enthalten ist und ob Liefer- oder
                  // Versandkosten hinzukommen. Der Hinweis steht deshalb
                  // unmittelbar unter dem Preisschild und nicht im Seitenfuss —
                  // die Rechtsprechung verlangt den raeumlichen Zusammenhang. ?>
            <p class="card-meta text-muted"><?= te('markt.preis_hinweis') ?></p>
            <?php if ($kategorie !== null): ?>
                <p class="card-meta">
                    <a href="/kategorie/<?= e((string) $kategorie['pfad']) ?>"><?= te('kategorie.' . (string) $kategorie['schluessel']) ?></a>
                </p>
            <?php endif; ?>
        </article>
    </section>

    <?php if (!$istAktiv): ?>
        <?php /*
               * DIESER HINWEIS HAENGT NICHT AM BESTELLVORGANG. Diese Seite
               * bekommt ein nicht oeffentliches Angebot nur zu sehen, wer es
               * sehen darf: die Eigentuemerin in der Vorschau und die
               * Verwaltung (siehe MarktRouten::angebotSeite()). Genau diese
               * beiden muessen erfahren, dass hier nichts oeffentlich ist —
               * unabhaengig davon, ob gerade jemand bestellen koennte. Stand
               * der Satz nur im Konfiguratorabschnitt, verschwaende er mit
               * dem geschlossenen Bestellvorgang, und die Vorschau saehe aus
               * wie die Katalogseite.
               */ ?>
        <section class="ms-abschnitt">
            <article class="card elev-sm" role="status">
                <h2 class="card-title"><?= te('markt.angebot_nicht_aktiv_titel') ?></h2>
                <p class="card-body"><?= te('markt.angebot_nicht_aktiv_text') ?></p>
            </article>
        </section>
    <?php endif; ?>

    <?php if ($bestellvorgangAktiv && $istAktiv): ?>
        <?php // Nur wenn der Bestellvorgang offen ist, gibt es diesen Abschnitt
              // ueberhaupt. Ruht er, hat ein Konfigurator nichts zu sagen. ?>
        <section class="ms-abschnitt" aria-labelledby="konfigurator">
            <div>
                <p class="ms-kicker"><?= te('markt.konfigurator_kicker') ?></p>
                <h2 id="konfigurator"><?= te('markt.konfigurator_titel') ?></h2>
                <p class="ms-hero__unterzeile" style="margin-top:var(--space-4)"><?= te('markt.konfigurator_unterzeile') ?></p>
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

    <section class="ms-abschnitt" aria-labelledby="kontakt">
        <div>
            <p class="ms-kicker"><?= te('markt.kontakt_kicker') ?></p>
            <h2 id="kontakt"><?= te('markt.kontakt_titel') ?></h2>
        </div>

        <article class="card elev-sm">
            <?php // Zwei Erklaerungen, ein Knopf: Ob der Konfigurator daneben steht,
                  // aendert nur, was "Nachricht schreiben" bedeutet — Hauptweg oder
                  // Nebenweg. Der Knopf selbst ist in beiden Faellen derselbe. ?>
            <p class="card-body"><?= $konfigurator ? te('markt.kontakt_neben_bestellung') : te('markt.kontakt_hauptweg') ?></p>
            <p>
                <a class="btn <?= $konfigurator ? 'btn-secondary' : 'btn-primary' ?>" href="<?= e($nachrichtZiel) ?>"><?= te('markt.nachricht_schreiben') ?></a>
            </p>
            <?php if (!$angemeldet): ?>
                <p class="card-meta text-muted"><?= te('markt.nachricht_anmeldung') ?></p>
            <?php endif; ?>
        </article>

        <?php /*
               * MELDEN OHNE KONTO. Art. 16 DSA verlangt ein Verfahren, mit dem
               * "Personen oder Einrichtungen" rechtswidrige Inhalte melden
               * koennen — ohne Anmeldeschranke. Der Verweis steht deshalb
               * ausserhalb jeder Abfrage von $angemeldet, und die Meldestrecke
               * nimmt eine Meldung auch ohne Melder-Kennung entgegen.
               *
               * Das kaufmaennische Und muss im Markup maskiert stehen: '&id'
               * waere sonst eine unvollstaendige Zeichenreferenz.
               */ ?>
        <article class="card elev-sm">
            <p class="card-kicker"><?= te('markt.melden_kicker') ?></p>
            <p class="card-body"><?= te('markt.melden_text') ?></p>
            <p><a class="btn btn-ghost" href="/melden?art=angebot&amp;id=<?= $angebotId ?>"><?= te('markt.melden_knopf') ?></a></p>
        </article>
    </section>
<?php endif; ?>
