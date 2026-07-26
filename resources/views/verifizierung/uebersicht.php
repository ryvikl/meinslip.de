<?php

declare(strict_types=1);

/**
 * Die eigene Seite der manuellen Identitätsprüfung.
 *
 * DREI DINGE STEHEN HIER, BEVOR IRGENDWO EIN DATEIFELD AUFTAUCHT: was das
 * Abzeichen bedeutet und was es nicht bedeutet, was auf den Zettel gehört, und
 * wann das Foto wieder gelöscht ist. Wer ein Bild von seinem Gesicht hochlädt,
 * soll vorher gelesen haben, wie lange es liegt — nicht hinterher in der
 * Datenschutzerklärung nachschlagen müssen.
 *
 * Die Seite hat vier Zustände, und genau einer ist zu jedem Zeitpunkt sichtbar:
 * kein Vorgang, Code vergeben (gültig oder abgelaufen), Foto eingereicht,
 * entschieden. Die Verzweigung liegt hier und nicht in der Route, weil sie
 * ausschließlich Darstellung ist — was erlaubt ist, entscheidet Pruefbelege
 * noch einmal selbst.
 *
 * @var array<string,mixed>|null $vorgang           Pruefbelege::letzterVorgang()
 * @var bool                     $abzeichen         Pruefbelege::abzeichenVorhanden()
 * @var bool                     $gestoert
 * @var string|null              $erfolg
 * @var string|null              $fehler
 * @var array<string,int>        $zahlen            Fristen und Bildgrenzen
 * @var string                   $accept            accept-Attribut des Dateifelds
 * @var int                      $maxBytes          Bilder::MAX_BYTES
 * @var string                   $statusOffen
 * @var string                   $statusEingereicht
 * @var string                   $statusFreigegeben
 * @var string                   $statusAbgelehnt
 * @var string                   $jetzt             'Y-m-d H:i:s' in UTC
 */

$vorgang ??= null;
$abzeichen ??= false;
$gestoert ??= false;
$erfolg ??= null;
$fehler ??= null;

$status = $vorgang === null ? null : (string) $vorgang['status'];
$laeuft = $status === $statusOffen || $status === $statusEingereicht;

// Zeichenkettenvergleich: Alle Zeitangaben stehen als 'Y-m-d H:i:s' in UTC,
// damit ordnet ein einfacher Vergleich sie richtig. Ein strtotime() in der
// Vorlage nähme die lokale Zeitzone an und verschöbe die Frist je nach Server.
$codeGilt = $status === $statusOffen && (string) $vorgang['code_gueltig_bis'] >= $jetzt;
$entschieden = $status === $statusFreigegeben || $status === $statusAbgelehnt;
?>
<section class="ms-abschnitt">
    <div>
        <p class="ms-kicker"><?= te('verifizierung.kicker') ?></p>
        <h1 style="font-size:clamp(1.75rem,4vw,2.5rem)"><?= te('verifizierung.titel') ?></h1>
        <p class="ms-hero__unterzeile" style="margin-top:var(--space-4)"><?= te('verifizierung.unterzeile', $zahlen) ?></p>
    </div>

    <?php if ($erfolg !== null): ?>
        <p class="card" role="status"><?= te('verifizierung.erfolg.' . $erfolg) ?></p>
    <?php endif; ?>

    <?php if ($fehler !== null): ?>
        <p class="ms-fehler" role="alert"><?= te('verifizierung.fehler.' . $fehler, $zahlen) ?></p>
    <?php endif; ?>

    <?php if ($gestoert): ?>
        <p class="ms-fehler" role="alert"><?= te('verifizierung.gestoert') ?></p>
    <?php endif; ?>

    <?php /*
           * DAS ABZEICHEN UND SEIN KLEINGEDRUCKTES STEHEN IN DERSELBEN KARTE.
           * Sie lassen sich nicht getrennt kopieren, nicht getrennt anzeigen
           * und nicht versehentlich einzeln übernehmen. Ein Abzeichen ohne
           * diesen Satz wäre eine Behauptung über eine Altersprüfung, die hier
           * nicht stattfindet.
           */ ?>
    <article class="card elev-sm">
        <p class="card-kicker">
            <span class="tag <?= $abzeichen ? 'tag-accent' : 'tag-outline' ?>"><?= te('verifizierung.abzeichen') ?></span>
        </p>
        <p class="card-title" style="font-size:1.125rem">
            <?= $abzeichen ? te('verifizierung.abzeichen_vorhanden') : te('verifizierung.abzeichen_fehlt') ?>
        </p>
        <p class="card-body"><?= te('verifizierung.abzeichen_was_nicht') ?></p>
    </article>
</section>

<?php if ($entschieden): ?>
    <section class="ms-abschnitt">
        <article class="card elev-sm">
            <p class="card-kicker">
                <?= $status === $statusFreigegeben
                    ? te('verifizierung.freigegeben_titel')
                    : te('verifizierung.abgelehnt_titel') ?>
            </p>
            <p class="card-body">
                <?= $status === $statusFreigegeben
                    ? te('verifizierung.freigegeben_text')
                    : te('verifizierung.abgelehnt_text') ?>
            </p>

            <?php if (($vorgang['entscheidung'] ?? null) !== null): ?>
                <p class="card-title" style="font-size:1rem"><?= te('verifizierung.entscheidung_ueberschrift') ?></p>
                <?php // Ungekürzt. Eine Zusammenfassung wäre eine zweite
                      // Entscheidung darüber, was die Person erfahren darf. ?>
                <p class="card-body" style="white-space:pre-line;overflow-wrap:anywhere"><?= e((string) $vorgang['entscheidung']) ?></p>
            <?php endif; ?>

            <p class="card-meta">
                <?= te('verifizierung.entschieden_am') ?> <?= e((string) ($vorgang['entschieden_am'] ?? '')) ?>
            </p>
            <p class="card-meta"><?= te('verifizierung.frist_loeschung_am', ['zeitpunkt' => (string) $vorgang['loeschen_ab']]) ?></p>
        </article>
    </section>
<?php endif; ?>

<?php if ($status === $statusEingereicht): ?>
    <section class="ms-abschnitt">
        <article class="card elev-sm">
            <p class="card-kicker"><?= te('verifizierung.eingereicht_titel') ?></p>
            <p class="card-body"><?= te('verifizierung.eingereicht_text') ?></p>
            <p class="card-meta"><?= te('verifizierung.frist_loeschung_am', ['zeitpunkt' => (string) $vorgang['loeschen_ab']]) ?></p>
        </article>
    </section>
<?php endif; ?>

<?php if ($status === $statusOffen && !$codeGilt): ?>
    <section class="ms-abschnitt">
        <article class="card">
            <p class="card-kicker"><?= te('verifizierung.code_abgelaufen_titel') ?></p>
            <p class="card-body"><?= te('verifizierung.code_abgelaufen_text') ?></p>
        </article>
    </section>
<?php endif; ?>

<?php if ($codeGilt): ?>
    <section class="ms-abschnitt" aria-labelledby="code">
        <div>
            <h2 id="code"><?= te('verifizierung.code_titel') ?></h2>
        </div>

        <article class="card elev-sm">
            <?php /*
                   * Der Code in großer, weit gesperrter Schrift: Er wird von
                   * diesem Bildschirm abgelesen und mit der Hand abgeschrieben.
                   * Jedes Zeichen, das dabei verrutscht, kostet die Person ein
                   * zweites Foto — und ein zweites Foto gibt es je Vorgang
                   * nicht.
                   */ ?>
            <p class="card-title" style="font-size:clamp(1.5rem,7vw,2.5rem);letter-spacing:0.18em;overflow-wrap:anywhere"><?= e((string) $vorgang['code']) ?></p>
            <p class="card-meta"><?= te('verifizierung.code_gilt_bis', ['zeitpunkt' => (string) $vorgang['code_gueltig_bis']]) ?></p>
            <p class="card-body"><?= te('verifizierung.code_warum') ?></p>
        </article>

        <article class="card">
            <p class="card-title" style="font-size:1rem"><?= te('verifizierung.zettel_titel') ?></p>
            <ul class="card-body">
                <li><?= te('verifizierung.zettel_code') ?></li>
                <li><?= te('verifizierung.zettel_datum') ?></li>
                <li><?= te('verifizierung.zettel_hand') ?></li>
                <li><?= te('verifizierung.zettel_gesicht') ?></li>
            </ul>
        </article>
    </section>

    <section class="ms-abschnitt" aria-labelledby="beleg">
        <div>
            <h2 id="beleg"><?= te('verifizierung.beleg_titel') ?></h2>
            <p class="hinweis"><?= te('verifizierung.nur_eines') ?></p>
        </div>

        <?php /*
               * enctype="multipart/form-data" ist die eine Angabe, ohne die gar
               * nichts geht: Fehlt sie, sendet der Browser nur den Dateinamen
               * als Text, $_FILES bleibt leer, und der Fehler sieht aus wie
               * "keine Datei ausgewählt", obwohl eine ausgewählt war.
               *
               * MAX_FILE_SIZE MUSS VOR DEM DATEIFELD STEHEN — PHP wertet das
               * Feld nur aus, wenn es im Datenstrom vorher kommt. Es ist keine
               * Sicherung (der Browser kann es weglassen), sondern eine
               * Höflichkeit: Der Upload bricht dann früh ab und PHP meldet
               * UPLOAD_ERR_FORM_SIZE, was Bilder in 'upload_zu_gross'
               * übersetzt. Die echte Grenze zieht Bilder::MAX_BYTES am fertig
               * empfangenen Datenstrom.
               */ ?>
        <form class="ms-formular" method="post" action="/verifizierung/beleg" enctype="multipart/form-data">
            <?= \MeinSlip\Http\Formularschutz::feld() ?>
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int) $maxBytes ?>">

            <div class="field">
                <label for="beleg-datei"><?= te('verifizierung.feld_datei') ?></label>
                <input class="input" type="file" id="beleg-datei" name="beleg" required
                       accept="<?= e($accept) ?>">
                <span class="hinweis"><?= te('verifizierung.hinweis_datei', $zahlen) ?></span>
            </div>

            <p class="hinweis"><?= te('verifizierung.exif_hinweis') ?></p>

            <button class="btn btn-primary btn-block" type="submit"><?= te('verifizierung.hochladen') ?></button>
        </form>
    </section>
<?php endif; ?>

<?php if (!$laeuft || ($status === $statusOffen && !$codeGilt)): ?>
    <section class="ms-abschnitt">
        <div>
            <h2><?= te('verifizierung.start_titel') ?></h2>
            <p class="hinweis"><?= te('verifizierung.start_text') ?></p>
        </div>

        <form class="ms-formular" method="post" action="/verifizierung/code">
            <?= \MeinSlip\Http\Formularschutz::feld() ?>
            <button class="btn btn-primary btn-block" type="submit">
                <?php if ($status === $statusOffen): ?>
                    <?= te('verifizierung.code_neu') ?>
                <?php elseif ($entschieden): ?>
                    <?= te('verifizierung.neu_starten') ?>
                <?php else: ?>
                    <?= te('verifizierung.start_knopf') ?>
                <?php endif; ?>
            </button>
        </form>
    </section>
<?php endif; ?>

<section class="ms-abschnitt">
    <article class="card">
        <p class="card-title" style="font-size:1rem"><?= te('verifizierung.frist_titel') ?></p>
        <p class="card-body"><?= te('verifizierung.frist_text', $zahlen) ?></p>
    </article>

    <p class="hinweis">
        <a href="/altersschranke"><?= te('verifizierung.schranke_titel') ?></a>
    </p>
</section>
