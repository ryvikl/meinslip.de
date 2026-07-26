<?php

declare(strict_types=1);

/**
 * Das Chatfenster: Kopf mit den Deklarationen, Verlauf, Eingabefeld.
 *
 * DAS LABEL IM KOPF IST DER GRUND, WARUM ES DIESE SEITE GIBT.
 * docs/04-features/chat-monetarisierung.md verlangt die Angabe als
 * „dauerhaftes Label im Chatfenster, nicht als Fußnote im Profil" — und zwar
 * fuer beide Seiten. Deshalb steht sie hier oben, vor dem Verlauf, und nicht
 * hinter einem Aufklappen: Wer sie sucht, hat schon geschrieben.
 *
 * JEDE BLASE ZEIGT IHRE EIGENE DEKLARATION, NICHT DIE DES KONTOS.
 * Unterhaltungen::senden() schreibt die Angabe als Momentaufnahme in die Zeile.
 * Wuerde hier stattdessen der aktuelle Kontostand stehen, waere diese
 * Momentaufnahme wertlos — ein Konto koennte nach zwei Monaten Teamchat auf
 * „Sie schreibt selbst" umstellen, und der ganze Verlauf behauptete
 * rueckwirkend etwas anderes. Genau das soll die Spalte verhindern.
 *
 * VERBORGENE NACHRICHTEN SIND EIN PLATZHALTER, KEINE LUECKE. Wer eine
 * beanstandete Nachricht spurlos entfernt, laesst den Verlauf manipuliert
 * aussehen — und nimmt der betroffenen Person die Moeglichkeit, die
 * Entscheidung nachzuvollziehen. Der Text ist bereits serverseitig entfernt
 * (Unterhaltungen::nachrichten() liefert ihn gar nicht erst); hier steht nur
 * noch, DASS dort etwas stand.
 *
 * KEIN fetch(), KEIN AUTOMATISCHES NACHLADEN. Begruendung im Klassenkopf von
 * app/Http/NachrichtenRouten.php. Der Verlauf ist eine gewoehnliche Seite mit
 * einem gewoehnlichen Formular; die Sprungmarke am Ende ersetzt das
 * Herunterrollen per Skript.
 *
 * @var array<string,mixed>|null      $unterhaltung
 * @var list<array<string,mixed>>     $nachrichten
 * @var string|null                   $angebot        Titel des bezogenen Angebots
 * @var bool                          $selbstGesperrt Diese Person hat das Gegenueber gesperrt
 * @var array<string,string>          $eingaben
 * @var string|null                   $fehler
 * @var string|null                   $erfolg
 * @var int                           $textGrenze
 * @var bool                          $gestoert
 */

$unterhaltung ??= null;
$nachrichten ??= [];
$angebot ??= null;
$selbstGesperrt ??= false;
$eingaben ??= [];
$fehler ??= null;
$erfolg ??= null;
$textGrenze ??= 4000;
$gestoert ??= false;
?>
<?php if ($gestoert): ?>
    <section class="ms-abschnitt">
        <article class="card elev-sm">
            <h1 class="card-title"><?= te('chat.gestoert_titel') ?></h1>
            <p class="card-body"><?= te('chat.gestoert_text') ?></p>
            <p><a class="btn btn-primary" href="/nachrichten"><?= te('chat.fenster_zurueck') ?></a></p>
        </article>
    </section>
<?php elseif ($unterhaltung === null): ?>
    <section class="ms-abschnitt">
        <article class="card elev-sm">
            <h1 class="card-title"><?= te('chat.unbekannt_titel') ?></h1>
            <p class="card-body"><?= te('chat.unbekannt_text') ?></p>
            <p><a class="btn btn-primary" href="/nachrichten"><?= te('chat.fenster_zurueck') ?></a></p>
        </article>
    </section>
<?php else: ?>
    <?php
    $kennung = (int) $unterhaltung['id'];
    $angebotId = $unterhaltung['angebot_id'] === null ? null : (int) $unterhaltung['angebot_id'];
    $fremd = $unterhaltung['partner_deklaration'];
    $eigen = $unterhaltung['eigene_deklaration'];
    ?>
    <section class="ms-abschnitt">
        <div>
            <p class="ms-kicker"><?= te('chat.kicker') ?></p>
            <h1 style="font-size:clamp(1.5rem,4vw,2.25rem)"><?= e((string) $unterhaltung['partner_name']) ?></h1>
            <p class="text-muted" style="margin-top:var(--space-3)">
                <span class="tag tag-outline"><?= e((string) $unterhaltung['partner_pseudonym']) ?></span>
            </p>
        </div>

        <?php if ($erfolg !== null): ?>
            <p class="hinweis text-muted" role="status"><?= te('chat.erfolg.' . $erfolg) ?></p>
        <?php endif; ?>

        <?php if ($fehler !== null): ?>
            <p class="ms-fehler" role="alert"><?= te('chat.fehler.' . $fehler) ?></p>
        <?php endif; ?>

        <?php /*
               * DER KOPF. Beide Angaben nebeneinander, weil die Deklaration
               * eine gegenseitige Zusicherung ist: Wer wissen will, mit wem er
               * spricht, muss auch sehen, was er selbst behauptet hat.
               */ ?>
        <article class="card elev-sm">
            <p class="card-meta">
                <span class="tag tag-accent"><?= te('chat.kopf_gegenueber') ?>
                    <?= $fremd === null ? te('chat.kopf_ohne_angabe') : te('chat.deklaration.' . (string) $fremd) ?></span>
                <span class="tag tag-neutral"><?= te('chat.kopf_eigene') ?>
                    <?= $eigen === null ? te('chat.kopf_ohne_angabe') : te('chat.deklaration.' . (string) $eigen) ?></span>
            </p>
            <p class="card-meta text-muted"><?= te('chat.kopf_erklaerung') ?></p>
            <p><a class="btn btn-ghost" href="/nachrichten/deklaration?weiter=/nachrichten/<?= $kennung ?>"><?= te('chat.kopf_deklaration_aendern') ?></a></p>
        </article>

        <?php if ($angebotId !== null): ?>
            <article class="card elev-sm">
                <p class="card-kicker"><?= te('chat.bezug_titel') ?></p>
                <?php if ($angebot !== null): ?>
                    <p class="card-body"><?= e($angebot) ?></p>
                    <p><a class="btn btn-ghost" href="/angebot/<?= $angebotId ?>"><?= te('chat.bezug_angebot') ?></a></p>
                <?php else: ?>
                    <p class="card-body text-muted"><?= te('chat.liste_bezug_entfallen') ?></p>
                <?php endif; ?>
            </article>
        <?php endif; ?>
    </section>

    <section class="ms-abschnitt" aria-labelledby="verlauf">
        <h2 id="verlauf" class="ms-nur-vorlesen"><?= te('chat.verlauf_ueberschrift') ?></h2>

        <?php if ($nachrichten === []): ?>
            <p class="text-muted"><?= te('chat.verlauf_leer') ?></p>
        <?php endif; ?>

        <?php foreach ($nachrichten as $eintrag): ?>
            <?php
            $eigene = (bool) $eintrag['eigene'];
            $verborgen = (bool) $eintrag['verborgen'];
            ?>
            <?php // Eigene und fremde Blasen unterscheiden sich in Rahmen und
                  // Ausrichtung. Beides kommt aus vorhandenen Bausteinen —
                  // '.card' plus die Randregel des Designsystems, keine neue
                  // CSS-Klasse. ?>
            <article class="card elev-sm"
                     style="<?= $eigene ? 'margin-left:auto;max-width:min(100%,42rem);border-color:var(--color-accent)' : 'margin-right:auto;max-width:min(100%,42rem)' ?>">
                <p class="card-kicker">
                    <?= $eigene ? te('chat.blase_eigene') : e((string) $unterhaltung['partner_name']) ?>
                </p>

                <?php if ($verborgen): ?>
                    <p class="card-body text-muted"><?= te('chat.blase_verborgen') ?></p>
                <?php else: ?>
                    <p class="card-body" style="overflow-wrap:anywhere"><?= nl2br(e((string) $eintrag['text'])) ?></p>
                <?php endif; ?>

                <p class="card-meta">
                    <?php // Die Deklaration DIESER Nachricht — nicht die aktuelle
                          // des Kontos. Siehe Kopf dieser Datei. ?>
                    <span class="tag tag-neutral"><?= te('chat.deklaration.' . (string) $eintrag['deklaration']) ?></span>
                    <span class="tag tag-neutral"><?= e((string) $eintrag['angelegt_am']) ?></span>
                    <?php if ($eigene && $eintrag['gelesen_am'] !== null): ?>
                        <span class="tag tag-neutral"><?= te('chat.blase_gelesen') ?></span>
                    <?php endif; ?>
                </p>

                <?php if (!$eigene && !$verborgen): ?>
                    <?php // Melden laeuft ueber POST, damit die Nachrichtenkennung
                          // geprueft wird, bevor sie in der Adresse steht — siehe
                          // NachrichtenRouten::melden(). ?>
                    <form method="post" action="/nachrichten/<?= $kennung ?>/melden" style="margin:0">
                        <?= \MeinSlip\Http\Formularschutz::feld() ?>
                        <input type="hidden" name="nachricht_id" value="<?= (int) $eintrag['id'] ?>">
                        <button class="btn btn-ghost" type="submit"><?= te('chat.blase_melden') ?></button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

        <?php // Ziel der Weiterleitung nach dem Senden. Ersetzt das Herunterrollen
              // per Skript und funktioniert auch ohne JavaScript. ?>
        <span id="ende"></span>
    </section>

    <section class="ms-abschnitt" aria-labelledby="schreiben">
        <h2 id="schreiben" class="ms-nur-vorlesen"><?= te('chat.senden_feld') ?></h2>

        <?php if ($selbstGesperrt): ?>
            <?php /*
                   * Nur die EIGENE Sperre wird benannt. Dass die andere Person
                   * gesperrt hat, steht hier bewusst nicht: Diese Auskunft ist
                   * genau das, womit eine Belaestigung auf einem zweiten Konto
                   * weitergeht. Wer trotzdem sendet, bekommt die neutrale
                   * Meldung aus Unterhaltungen::senden().
                   */ ?>
            <article class="card elev-sm" role="status">
                <h3 class="card-title"><?= te('chat.sperre_eigene_titel') ?></h3>
                <p class="card-body"><?= te('chat.sperre_eigene_text') ?></p>
                <form method="post" action="/nachrichten/<?= $kennung ?>/sperren" style="margin:0">
                    <?= \MeinSlip\Http\Formularschutz::feld() ?>
                    <input type="hidden" name="handlung" value="aufheben">
                    <button class="btn btn-secondary" type="submit"><?= te('chat.sperre_aufheben') ?></button>
                </form>
            </article>
        <?php else: ?>
            <form class="ms-formular" method="post" action="/nachrichten/<?= $kennung ?>">
                <?= \MeinSlip\Http\Formularschutz::feld() ?>
                <div class="field">
                    <label for="text"><?= te('chat.senden_feld') ?></label>
                    <?php // maxlength spiegelt Unterhaltungen::TEXT_MAXLAENGE; massgeblich
                          // ist die Fachklasse, nicht diese Zahl. ?>
                    <textarea class="input" id="text" name="text" rows="5" required
                              maxlength="<?= $textGrenze ?>"><?= e($eingaben['text'] ?? '') ?></textarea>
                    <span class="hinweis"><?= te('chat.senden_hinweis', ['zeichen' => $textGrenze]) ?></span>
                </div>
                <button class="btn btn-primary btn-block" type="submit"><?= te('chat.senden_knopf') ?></button>
            </form>

            <article class="card elev-sm">
                <p class="card-body text-muted"><?= te('chat.sperre_erklaerung') ?></p>
                <form method="post" action="/nachrichten/<?= $kennung ?>/sperren" style="margin:0">
                    <?= \MeinSlip\Http\Formularschutz::feld() ?>
                    <input type="hidden" name="handlung" value="setzen">
                    <button class="btn btn-ghost" type="submit"><?= te('chat.sperre_setzen') ?></button>
                </form>
            </article>
        <?php endif; ?>

        <p><a class="btn btn-ghost" href="/nachrichten"><?= te('chat.fenster_zurueck') ?></a></p>
    </section>
<?php endif; ?>
