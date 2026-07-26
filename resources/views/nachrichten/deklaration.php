<?php

declare(strict_types=1);

/**
 * Die Pflichtwahl: Wer schreibt hier?
 *
 * KEINE VORBELEGUNG, AUCH NICHT BEIM AENDERN. Kein Feld traegt 'checked'. Ein
 * vorausgewaehltes „Sie schreibt selbst" waere die staerkste Behauptung als
 * Standard — genau das, was profile.chat_deklaration mit „nullable ohne
 * Vorgabewert" ausschliesst (database/migrations/011_profile_und_chat.php).
 * Wer nur weiterklickt, haette dann die wertvollste Zusicherung der Plattform
 * abgegeben, ohne sie gelesen zu haben.
 *
 * DIE WARNUNG STEHT VOR DEM KNOPF. Dass eine Falschangabe ein Sperrgrund ist,
 * muss lesen, wer noch waehlt — darunter liest es niemand mehr. Dieselbe Regel
 * wie beim Missbrauchshinweis der Meldestrecke.
 *
 * DIE DREI WERTE KOMMEN AUS DER FACHKLASSE, NICHT AUS DIESER DATEI.
 * $werte ist Profile::DEKLARATIONEN. Eine hier abgeschriebene Liste waere beim
 * naechsten vierten Wert stumm veraltet; so erscheint er von selbst — und
 * tests/ChatTexteTest.php verlangt fuer jeden Wert einen Text, bevor jemand
 * '[[chat.deklaration.x]]' liest.
 *
 * @var list<string>  $werte
 * @var string        $weiter  Rueckweg, in NachrichtenRouten gegen ein Muster geprueft
 * @var string|null   $fehler
 */

$werte ??= [];
$weiter ??= '/nachrichten';
$fehler ??= null;
?>
<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('chat.deklaration_kicker') ?></p>
        <h1 style="font-size:clamp(1.75rem,4vw,2.5rem)"><?= te('chat.deklaration_titel') ?></h1>
        <p class="ms-hero__unterzeile" style="margin-top:var(--space-4)"><?= te('chat.deklaration_unterzeile') ?></p>
    </div>

    <?php if ($fehler !== null): ?>
        <p class="ms-fehler" role="alert"><?= te('chat.fehler.' . $fehler) ?></p>
    <?php endif; ?>

    <form class="ms-formular" method="post" action="/nachrichten/deklaration">
        <?= \MeinSlip\Http\Formularschutz::feld() ?>
        <input type="hidden" name="weiter" value="<?= e($weiter) ?>">

        <div class="field" role="radiogroup" aria-labelledby="deklaration-beschriftung">
            <span id="deklaration-beschriftung"><?= te('chat.deklaration_titel') ?></span>

            <?php foreach ($werte as $wert): ?>
                <?php // Kein 'checked': siehe Kopf dieser Datei. 'required' liegt
                      // auf jedem Feld der Gruppe — der Browser erzwingt damit
                      // eine Wahl, und Profile::deklarationSetzen() weist einen
                      // leeren Wert serverseitig noch einmal ab. ?>
                <label class="radio">
                    <input type="radio" name="deklaration" value="<?= e($wert) ?>" required>
                    <span class="dot"></span>
                    <span><?= te('chat.deklaration.' . $wert) ?></span>
                </label>
                <span class="hinweis"><?= te('chat.deklaration_erklaerung.' . $wert) ?></span>
            <?php endforeach; ?>
        </div>

        <?php // Art. 50 KI-VO laeuft in dieselbe Richtung: Wer mit einer Maschine
              // spricht, muss das erfahren. Hier ist es freiwillig und trotzdem
              // verbindlich — deshalb die Sperrandrohung im Klartext. ?>
        <p class="hinweis" role="note"><?= te('chat.deklaration_warnung') ?></p>

        <button class="btn btn-primary btn-block" type="submit"><?= te('chat.deklaration_speichern') ?></button>

        <?php // 'text-muted' zusaetzlich zu 'hinweis': '.hinweis' ist nur als
              // '.field .hinweis' gestylt, ein freistehender Hinweis bliebe sonst
              // in voller Groesse stehen. ?>
        <p class="hinweis text-muted"><?= te('chat.deklaration_spaeter') ?></p>
    </form>
</section>
