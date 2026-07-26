<?php

declare(strict_types=1);

/**
 * Das Anlegeformular fuer einen Termin.
 *
 * WARUM DIESE SEITE NICHT IM CHATFENSTER STEHT. Die Termine selbst stehen dort
 * — das Anlegen nicht. Auf dieser Seite steht die Erklaerung zu jeder
 * Treffpunktart und der Hinweis, dass hier keine Anschrift hingehoert. Beides
 * muss lesen, wer noch waehlt; zwischen Verlauf und Eingabefeld geklemmt liest
 * es niemand. Dieselbe Ueberlegung wie bei der Deklarationsseite des Chats.
 *
 * KEINE VORBELEGUNG DER TREFFPUNKTART. Kein Feld traegt 'checked'. Eine
 * vorausgewaehlte „Uebergabe an der Haustuer" waere die folgenreichste Wahl als
 * Standard — und wer nur weiterklickt, haette sie getroffen, ohne die Erklaerung
 * gelesen zu haben. Die Erklaerung steht deshalb unter jeder Wahl und nicht
 * gesammelt darunter.
 *
 * DIE HAUSTUER STEHT ZULETZT UND OHNE HERVORHEBUNG. Sie ist nicht verboten —
 * viele Uebergaben laufen so, und ein Verbot verlagerte sie nur aus der
 * Plattform heraus. Ihre Erklaerung sagt aber unverbluemt, was sie bedeutet:
 * Eine Seite erfaehrt, wo die andere wohnt, und das laesst sich nicht
 * zuruecknehmen.
 *
 * DIE FESTE LISTE IST DER GANZE SCHUTZ. Es gibt auf dieser Seite kein Feld
 * „Treffpunkt". Ein solches Feld waere binnen Wochen ein Adressfeld — und eine
 * Anschrift in der Datenbank dieser Plattform ist der Schaden, gegen den die
 * ganze rechtliche Brandmauer gebaut ist. Was bleibt, ist die grobe Region mit
 * 40 Zeichen, genau wie bei einem Angebot.
 *
 * KEIN STANDORT. Diese Seite fragt den Browser nicht nach einer Position, zeigt
 * keine Karte und nimmt keine Koordinaten entgegen. Der Hinweis dazu steht im
 * Klartext im Formular, weil er auch fuer das gilt, was AUSSERHALB der
 * Plattform passiert: Wer nach einem Standort fragt, tut das nicht hier.
 *
 * @var array<string,mixed>|null $unterhaltung  aus Unterhaltungen::laden()
 * @var list<string>             $arten         Termine::TREFFPUNKTARTEN
 * @var array<string,string>     $eingaben
 * @var string|null              $fehler
 * @var int                      $regionGrenze
 * @var int                      $vorlaufTage
 * @var string                   $frueheste     fuer das min-Attribut
 * @var string                   $spaeteste     fuer das max-Attribut
 * @var bool                     $gestoert
 */

$unterhaltung ??= null;
$arten ??= [];
$eingaben ??= [];
$fehler ??= null;
$regionGrenze ??= 40;
$vorlaufTage ??= 90;
$frueheste ??= '';
$spaeteste ??= '';
$gestoert ??= false;

// Das Formular liefert 'Y-m-dTH:i' zurueck; ein Fehlerweg gibt genau das
// wieder her. Ein gespeicherter Wert mit Sekunden wuerde vom Browser ebenfalls
// angenommen, kommt hier aber nicht vor.
$zeitpunktWert = $eingaben['zeitpunkt'] ?? '';
?>
<?php if ($gestoert): ?>
    <section class="ms-abschnitt">
        <article class="card elev-sm">
            <h1 class="card-title"><?= te('termin.gestoert_titel') ?></h1>
            <p class="card-body"><?= te('termin.gestoert_text') ?></p>
            <p><a class="btn btn-primary" href="/nachrichten"><?= te('termin.neu_zurueck') ?></a></p>
        </article>
    </section>
<?php elseif ($unterhaltung === null): ?>
    <section class="ms-abschnitt">
        <article class="card elev-sm">
            <h1 class="card-title"><?= te('termin.neu_unbekannt_titel') ?></h1>
            <p class="card-body"><?= te('termin.neu_unbekannt_text') ?></p>
            <p><a class="btn btn-primary" href="/nachrichten"><?= te('termin.neu_zurueck') ?></a></p>
        </article>
    </section>
<?php else: ?>
    <?php $kennung = (int) $unterhaltung['id']; ?>
    <section class="ms-abschnitt">
        <div>
            <p class="ms-kicker"><?= te('termin.neu_kicker') ?></p>
            <h1 style="font-size:clamp(1.75rem,4vw,2.5rem)"><?= te('termin.neu_titel') ?></h1>
            <p class="ms-hero__unterzeile" style="margin-top:var(--space-4)"><?= te('termin.neu_unterzeile') ?></p>
            <p class="text-muted" style="margin-top:var(--space-3)">
                <span class="tag tag-outline"><?= te('termin.neu_gegenueber', [
                    'gegenueber' => (string) $unterhaltung['partner_name'],
                ]) ?></span>
            </p>
        </div>

        <?php if ($fehler !== null): ?>
            <p class="ms-fehler" role="alert"><?= te('termin.fehler.' . $fehler) ?></p>
        <?php endif; ?>

        <form class="ms-formular" method="post" action="/termine/neu">
            <?= \MeinSlip\Http\Formularschutz::feld() ?>
            <input type="hidden" name="unterhaltung" value="<?= $kennung ?>">

            <div class="field">
                <label for="zeitpunkt"><?= te('termin.feld_zeitpunkt') ?></label>
                <?php // min und max spiegeln Termine::VORLAUF_MAX_TAGE. Massgeblich
                      // ist die Fachklasse, nicht der Browser: Termine::
                      // gepruefterZeitpunkt() prueft dieselbe Grenze noch einmal. ?>
                <input class="input" type="datetime-local" id="zeitpunkt" name="zeitpunkt" required
                       min="<?= e($frueheste) ?>" max="<?= e($spaeteste) ?>"
                       value="<?= e($zeitpunktWert) ?>">
                <span class="hinweis"><?= te('termin.hinweis_zeitpunkt', ['tage' => $vorlaufTage]) ?></span>
                <?php // Das ganze Schema fuehrt Zeitstempel in UTC. Diese Seite
                      // rechnet nichts um und sagt das deshalb ausdruecklich —
                      // eine stille Umrechnung nur an dieser einen Stelle waere
                      // schlimmer als eine sichtbare Ansage. ?>
                <span class="hinweis"><?= te('termin.hinweis_utc') ?></span>
            </div>

            <div class="field" role="radiogroup" aria-labelledby="treffpunkt-beschriftung">
                <span id="treffpunkt-beschriftung"><?= te('termin.feld_treffpunkt') ?></span>
                <span class="hinweis"><?= te('termin.hinweis_treffpunkt') ?></span>

                <?php foreach ($arten as $art): ?>
                    <?php // Kein 'checked': siehe Kopf dieser Datei. 'required'
                          // liegt auf jedem Feld der Gruppe — der Browser
                          // erzwingt damit eine Wahl, und
                          // Termine::gepruefteTreffpunktart() weist einen
                          // unbekannten Wert serverseitig noch einmal ab. ?>
                    <label class="radio">
                        <input type="radio" name="treffpunkt_art" value="<?= e($art) ?>" required
                            <?= ($eingaben['treffpunkt_art'] ?? '') === $art ? 'checked' : '' ?>>
                        <span class="dot"></span>
                        <span><?= te('termin.treffpunkt.' . $art) ?></span>
                    </label>
                    <span class="hinweis"><?= te('termin.treffpunkt_erklaerung.' . $art) ?></span>
                <?php endforeach; ?>
            </div>

            <div class="field">
                <label for="region"><?= te('termin.feld_region') ?></label>
                <?php // maxlength spiegelt Termine::REGION_MAXLAENGE — dieselbe
                      // Grenze wie bei angebote.uebergabe_region. Sie ist der
                      // strukturelle Ersatz fuer ein Adressfeld, nicht seine
                      // Verkleinerung. ?>
                <input class="input" type="text" id="region" name="region" required
                       maxlength="<?= $regionGrenze ?>"
                       value="<?= e($eingaben['region'] ?? '') ?>">
                <span class="hinweis"><?= te('termin.hinweis_region', ['zeichen' => $regionGrenze]) ?></span>
            </div>

            <?php // Beide Warnungen stehen VOR dem Knopf. Darunter liest sie
                  // niemand mehr — dieselbe Regel wie beim Missbrauchshinweis
                  // der Meldestrecke und bei der Sperrandrohung der
                  // Deklarationsseite. ?>
            <p class="hinweis" role="note"><?= te('termin.warnung_kein_standort') ?></p>
            <p class="hinweis" role="note"><?= te('termin.warnung_frei_entscheiden') ?></p>

            <button class="btn btn-primary btn-block" type="submit"><?= te('termin.neu_speichern') ?></button>
        </form>

        <p><a class="btn btn-ghost" href="/nachrichten/<?= $kennung ?>#termine"><?= te('termin.neu_zurueck') ?></a></p>
    </section>
<?php endif; ?>
