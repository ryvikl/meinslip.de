<?php

declare(strict_types=1);

/**
 * Das Bestellformular — der Konfigurator.
 *
 * Wird aus resources/views/markt/angebot.php eingebunden und erbt dessen
 * Variablen. Eigenstaendig, weil hier der rechtlich empfindlichste Teil der
 * Oberflaeche steht:
 *
 *  1. PFLICHTFELDER TRAGEN 'required'. Das ist Bequemlichkeit, keine
 *     Sicherung — app/Http/MarktRouten.php prueft dasselbe noch einmal.
 *
 *  2. VOR DEM ABSENDEN STEHT DIE RECHNUNG, UND SIE STEHT UNMITTELBAR DAVOR.
 *     § 312j Abs. 2 BGB verlangt Gegenstand (Art. 246a § 1 Abs. 1 S. 1 Nr. 1
 *     EGBGB), Gesamtpreis und Lieferkosten "unmittelbar bevor der Verbraucher
 *     seine Bestellung abgibt". Deshalb steht die Uebersichtskarte als
 *     letzter Block vor dem Knopf, und deshalb traegt sie Titel und
 *     Beschreibung der Ware, nicht nur Zahlen.
 *
 *     Der Betrag darf dabei nie mehr behaupten, als er ohne JavaScript
 *     einloest. public/assets/js/app.js rechnet die Aufpreise live mit; faellt
 *     das Skript aus, bleibt die Zahl auf dem Stand des Seitenaufbaus stehen.
 *     Deshalb heisst sie nur dann "Gesamtbetrag", wenn das Angebot keine
 *     einzige Option mit Aufpreis hat — dann kann sie sich gar nicht aendern.
 *     Sonst heisst sie "Vorlaeufiger Gesamtbetrag", und alle Aufpreise, die
 *     noch hinzukommen koennen, stehen einzeln und vollstaendig daneben.
 *     Eine serverseitig gerechnete Uebersichtsseite waere der sauberere Weg;
 *     sie braucht eine zweite Route in app/Http/MarktRouten.php.
 *
 *  3. VOR DEM ABSENDEN STEHT DIE UNTERRICHTUNG. § 312d Abs. 1 BGB i. V. m.
 *     Art. 246a § 1 Abs. 3 Nr. 1 EGBGB verlangt die Information ueber den
 *     Ausschluss des Widerrufsrechts VOR Abgabe der Vertragserklaerung —
 *     nicht in den AGB und nicht auf der Bestaetigungsseite.
 *
 *  4. DER ABSENDEKNOPF TRAEGT DEN WORTLAUT DES § 312j Abs. 3 S. 2 BGB. Er ist
 *     nicht frei waehlbar und vertraegt keinen Zusatz. Fehlt die
 *     Zahlungspflicht in der Beschriftung, kommt nach § 312j Abs. 4 BGB kein
 *     Vertrag zustande. Begruendung im Kopf von resources/lang/de-DE/markt.php.
 *
 * @var array<string,mixed> $angebot
 * @var list<array<string,mixed>> $optionen
 * @var array<string,string> $eingaben
 * @var string $waehrung
 * @var int $grundpreis
 * @var list<string> $lieferwege
 * @var string $gewaehlteLieferart
 */
?>
<form class="ms-formular" method="post" action="/bestellen/<?= (int) $angebot['id'] ?>"
      data-konfigurator data-grundpreis="<?= $grundpreis ?>">
    <?= \MeinSlip\Http\Formularschutz::feld() ?>

    <?php foreach ($optionen as $option): ?>
        <?php
        $schluessel = (string) $option['schluessel'];
        $feldId = 'option_' . $schluessel;
        $wert = (string) ($eingaben[$feldId] ?? '');
        $aufpreis = (int) $option['aufpreis_cent'];
        $istPflicht = (int) $option['pflicht'] === 1;
        $istSpezifikation = (int) $option['ist_spezifikation'] === 1;
        $merkmale = 'data-option data-aufpreis="' . $aufpreis . '" data-spezifikation="'
            . ($istSpezifikation ? 'ja' : 'nein') . '"';
        ?>
        <div class="field">
            <?php if ((string) $option['art'] === \MeinSlip\Domain\Catalog\Angebote::ART_AUSWAHL): ?>
                <?php // Ankreuzen statt Auswahlliste: app.js zaehlt ein Kaestchen ueber
                      // .checked, eine Liste dagegen ueber value !== "" — dort wuerde
                      // auch ein abwaehlendes "nein" den Aufpreis mitrechnen. ?>
                <label class="radio">
                    <input type="checkbox" id="<?= e($feldId) ?>" name="<?= e($feldId) ?>" value="ja"
                           <?= $merkmale ?> <?= $wert === 'ja' ? 'checked' : '' ?> <?= $istPflicht ? 'required' : '' ?>>
                    <span class="dot"></span>
                    <span><?= e((string) $option['bezeichnung']) ?></span>
                </label>
            <?php elseif ((string) $option['art'] === \MeinSlip\Domain\Catalog\Angebote::ART_ZAHL): ?>
                <label for="<?= e($feldId) ?>"><?= e((string) $option['bezeichnung']) ?></label>
                <input class="input" type="number" id="<?= e($feldId) ?>" name="<?= e($feldId) ?>"
                       value="<?= e($wert) ?>" min="0" step="1" inputmode="numeric"
                       <?= $merkmale ?> <?= $istPflicht ? 'required' : '' ?>>
            <?php else: ?>
                <label for="<?= e($feldId) ?>"><?= e((string) $option['bezeichnung']) ?></label>
                <input class="input" type="text" id="<?= e($feldId) ?>" name="<?= e($feldId) ?>"
                       value="<?= e($wert) ?>" maxlength="190"
                       <?= $merkmale ?> <?= $istPflicht ? 'required' : '' ?>>
            <?php endif; ?>

            <span class="hinweis">
                <?= $aufpreis > 0
                    ? te('markt.konfigurator_aufpreis', ['betrag' => geld($aufpreis, $waehrung)])
                    : te('markt.konfigurator_ohne_aufpreis') ?>
                <?php if ($istSpezifikation): ?>
                    <span class="tag tag-accent-2"><?= te('markt.konfigurator_spezifikation') ?></span>
                <?php endif; ?>
                <?php if ($istPflicht): ?>
                    <span class="tag tag-accent"><?= te('markt.konfigurator_pflicht') ?></span>
                <?php endif; ?>
            </span>

            <?php if (($option['erlaeuterung'] ?? null) !== null): ?>
                <span class="hinweis"><?= e((string) $option['erlaeuterung']) ?></span>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if (count($lieferwege) > 1): ?>
        <?php // Eine Gruppe aus Optionsfeldern laesst sich nicht mit einem
              // for-Attribut beschriften: for zeigte hier auf ein div und lief
              // ins Leere — die Gruppe hatte keinen Namen, obwohl an ihr das
              // Diskretionsversprechen haengt (WCAG 1.3.1, ueber das BFSG
              // verbindlich). role="radiogroup" plus aria-labelledby gibt ihr
              // den Namen. Das label bleibt ein label, damit die Regel
              // ".field > label" in nocturne.css weiter greift; ein fieldset
              // mit legend waere nativer, braucht aber einen CSS-Reset. ?>
        <div class="field">
            <label id="lieferart-beschriftung"><?= te('markt.konfigurator_lieferart') ?></label>
            <div class="seg" role="radiogroup" aria-labelledby="lieferart-beschriftung">
                <?php foreach ($lieferwege as $weg): ?>
                    <label class="seg-opt">
                        <input type="radio" name="lieferart" value="<?= e($weg) ?>"
                               <?= $gewaehlteLieferart === $weg ? 'checked' : '' ?>>
                        <span><?= te('markt.konfigurator_lieferart_' . $weg) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <input type="hidden" name="lieferart" value="<?= e($gewaehlteLieferart) ?>">
        <p class="hinweis"><?= te('markt.konfigurator_lieferart') ?>:
            <?= te('markt.konfigurator_lieferart_' . ($gewaehlteLieferart === '' ? 'versand' : $gewaehlteLieferart)) ?></p>
    <?php endif; ?>

    <article class="card elev-sm">
        <h3 class="card-title"><?= te('markt.widerruf_titel') ?></h3>
        <p class="card-body"><?= te('markt.widerruf_text') ?></p>
        <label class="radio">
            <input type="checkbox" name="widerruf_verstanden" value="ja" required
                   <?= ($eingaben['widerruf_verstanden'] ?? '') === 'ja' ? 'checked' : '' ?>>
            <span class="dot"></span>
            <span><?= te('markt.widerruf_bestaetigung') ?></span>
        </label>
    </article>

    <?php
    // Die Uebersicht steht bewusst als LETZTER Block vor dem Knopf: § 312j
    // Abs. 2 BGB verlangt die Angaben "unmittelbar bevor der Verbraucher seine
    // Bestellung abgibt". Vorher stand sie vor der Widerrufskarte, also durch
    // einen ganzen Block vom Knopf getrennt.
    //
    // Der Betrag wird hier genauso gebildet wie beim Absenden in
    // app/Http/MarktRouten.php: Jede Option, deren Feld einen Wert traegt,
    // steuert ihren Aufpreis bei. Damit stimmt die Zahl auch dann, wenn die
    // Seite nach einem abgewiesenen Versuch mit den alten Eingaben neu
    // aufgebaut wird — vorher stand dort in diesem Fall stets der blosse
    // Grundpreis.
    $aufpreisOptionen = [];
    $vorlaeufigeSumme = $grundpreis;

    foreach ($optionen as $moegliche) {
        if ((int) $moegliche['aufpreis_cent'] > 0) {
            $aufpreisOptionen[] = $moegliche;
        }

        if (trim((string) ($eingaben['option_' . (string) $moegliche['schluessel']] ?? '')) !== '') {
            $vorlaeufigeSumme += (int) $moegliche['aufpreis_cent'];
        }
    }

    // Hat das Angebot keine einzige Option mit Aufpreis, kann sich der Betrag
    // durch nichts mehr aendern, was die Kaeuferin hier tut — dann und nur
    // dann ist er auch ohne JavaScript der Gesamtpreis und darf so heissen.
    $summeIstEndgueltig = $aufpreisOptionen === [];
    ?>
    <article class="card elev-sm">
        <p class="card-kicker"><?= te('markt.konfigurator_summe_titel') ?></p>

        <?php // Gegenstand der Bestellung: Art. 246a § 1 Abs. 1 S. 1 Nr. 1
              // EGBGB ueber § 312j Abs. 2 BGB. Ohne Titel und Beschreibung
              // benennt die Uebersicht nicht, worueber der Vertrag geschlossen
              // wird — auf langer Konfiguratorseite ist der Kopf der Seite
              // beim Klick auf den Knopf laengst ausserhalb des Sichtfelds. ?>
        <h3 class="card-title"><?= e((string) $angebot['titel']) ?></h3>
        <p class="card-body"><?= nl2br(e((string) ($angebot['beschreibung'] ?? ''))) ?></p>

        <p class="card-body"><?= te('markt.konfigurator_summe_grundpreis') ?>: <?= e(geld($grundpreis, $waehrung)) ?></p>

        <?php if (!$summeIstEndgueltig): ?>
            <?php // Vollstaendige Liste dessen, was den Preis noch bewegen
                  // kann. Sie steht hier ein zweites Mal, obwohl jeder Aufpreis
                  // auch am Feld steht: Wer unten auf den Knopf schaut, soll
                  // nicht nach oben scrollen muessen, um den Preis zu pruefen. ?>
            <p class="card-meta"><?= te('markt.konfigurator_summe_aufpreise') ?></p>
            <?php foreach ($aufpreisOptionen as $mitAufpreis): ?>
                <p class="card-body"><?= te('markt.konfigurator_summe_aufpreis_zeile', [
                    'bezeichnung' => (string) $mitAufpreis['bezeichnung'],
                    'betrag' => geld((int) $mitAufpreis['aufpreis_cent'], $waehrung),
                ]) ?></p>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php // data-gesamt ist die Naht fuer public/assets/js/app.js: Sobald
              // das Skript laeuft, ist der Betrag exakt und die Beschriftung
              // darf auf "Gesamtbetrag" wechseln. Der Text kommt aus dem
              // Attribut, damit er in der Sprachdatei bleibt und nicht ins
              // Skript wandert. Die zwei Zeilen dafuer gehoeren in app.js. ?>
        <p class="card-title">
            <span data-summe-beschriftung data-gesamt="<?= te('markt.konfigurator_summe_gesamt') ?>"><?=
                $summeIstEndgueltig
                    ? te('markt.konfigurator_summe_gesamt')
                    : te('markt.konfigurator_summe_vorlaeufig')
            ?></span>:
            <span data-summe><?= e(geld($vorlaeufigeSumme, $waehrung)) ?></span>
        </p>

        <?php // § 6 Abs. 1 PAngV: Am Gesamtpreis muss stehen, dass die
              // Umsatzsteuer enthalten ist und ob Versandkosten hinzukommen. ?>
        <p class="card-meta"><?= te('markt.preis_hinweis') ?></p>

        <?php if (!$summeIstEndgueltig): ?>
            <p class="card-meta"><?= te('markt.konfigurator_summe_offen') ?></p>
            <noscript><p class="card-meta"><?= te('markt.konfigurator_summe_ohne_js') ?></p></noscript>
        <?php endif; ?>

        <p class="card-meta"><?= te('markt.konfigurator_summe_hinweis') ?></p>
    </article>

    <p class="ms-fehler" data-spezifikation-warnung role="alert" hidden><?= te('markt.konfigurator_warnung') ?></p>

    <button class="btn btn-primary btn-block" type="submit" data-absenden><?= te('markt.konfigurator_absenden') ?></button>
</form>
