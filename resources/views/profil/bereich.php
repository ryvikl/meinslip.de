<?php

declare(strict_types=1);

/**
 * Der eigene Profilbereich. Eine Vorlage, vier Reiter — der Kopf mit Bild,
 * Name und Reiterleiste ist auf allen derselbe.
 *
 * @var string $reiter uebersicht | bearbeiten | links | einstellungen
 * @var array<string,mixed>|null $profil
 * @var list<array{id:int,titel:string,adresse:string,rechner:string}> $links
 * @var list<array<string,mixed>> $benachrichtigungen
 * @var int $angebote
 * @var bool $abzeichen
 * @var string|null $oeffentlicheAdresse
 * @var array{art:string,schluessel:string,werte:array<string,int>}|null $meldung
 * @var bool $gestoert
 * @var int $bildGrenzeBytes
 * @var string $bildFormate
 * @var int $linksHoechstens
 * @var array<string,mixed>|null $sitzung
 */

$feld = static fn (): string => \MeinSlip\Http\Formularschutz::feld();
$pseudonym = (string) ($profil['pseudonym'] ?? $sitzung['pseudonym'] ?? '');
$name = (string) ($profil['name'] ?? $pseudonym);
$oeffentlich = ($profil['oeffentlich'] ?? false) === true;
$hatBild = is_string($profil['bild_pfad'] ?? null) && $profil['bild_pfad'] !== '';
$vorstellung = (string) ($profil['vorstellung'] ?? '');

$ungelesen = 0;
foreach ($benachrichtigungen as $eintrag) {
    if (($eintrag['gelesen'] ?? false) === false) {
        $ungelesen++;
    }
}

// Was dem Profil noch fehlt. Reihenfolge = Reihenfolge, in der es sich lohnt.
$schritte = [
    ['bild', $hatBild, '/profil/bearbeiten#bild'],
    ['vorstellung', $vorstellung !== '', '/profil/bearbeiten'],
    ['links', $links !== [], '/profil/links'],
    ['oeffentlich', $oeffentlich, '/profil/einstellungen'],
];
$erledigt = count(array_filter($schritte, static fn (array $s): bool => $s[1]));

$reiterliste = [
    'uebersicht' => '/profil',
    'bearbeiten' => '/profil/bearbeiten',
    'links' => '/profil/links',
    'einstellungen' => '/profil/einstellungen',
];
?>
<section class="ms-profilkopf">
    <span class="ms-profilbild ms-profilbild--mittel">
        <?php if ($hatBild): ?>
            <img src="/p/<?= e(rawurlencode($pseudonym)) ?>/bild" width="72" height="72" alt="">
        <?php else: ?>
            <?= e(mb_substr($name, 0, 1)) ?>
        <?php endif; ?>
    </span>
    <div class="ms-profilkopf__text">
        <p class="ms-kicker"><?= te('profil.kicker') ?></p>
        <h1><?= e($name) ?></h1>
        <p class="ms-profilkopf__zeile">
            <span>@<?= e($pseudonym) ?></span>
            <span class="ms-status ms-status--klein<?= $oeffentlich ? '' : ' ms-status--neutral' ?>">
                <span class="ms-status__punkt"></span>
                <?= te($oeffentlich ? 'profil.status_oeffentlich' : 'profil.status_privat') ?>
            </span>
            <?php if ($abzeichen): ?>
                <span class="tag tag-accent"><?= te('verifizierung.abzeichen') ?></span>
            <?php endif; ?>
        </p>
    </div>
</section>

<nav class="ms-reiter" aria-label="<?= te('profil.reiter_label') ?>">
    <?php foreach ($reiterliste as $schluessel => $pfad): ?>
        <a href="<?= $pfad ?>"<?= $schluessel === $reiter ? ' aria-current="page"' : '' ?>>
            <?= te('profil.reiter.' . $schluessel) ?>
            <?php if ($schluessel === 'uebersicht' && $ungelesen > 0): ?>
                <span class="ms-reiter__zahl"><?= $ungelesen ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($gestoert): ?>
    <p class="ms-fehler" role="alert"><?= te('profil.gestoert') ?></p>
<?php endif; ?>

<?php if ($meldung !== null): ?>
    <p class="<?= $meldung['art'] === 'erfolg' ? 'ms-hinweisbox ms-hinweisbox--ok' : 'ms-fehler' ?>"
       role="<?= $meldung['art'] === 'erfolg' ? 'status' : 'alert' ?>"><?= te($meldung['schluessel'], $meldung['werte']) ?></p>
<?php endif; ?>

<?php if ($reiter === 'uebersicht'): ?>

    <section class="ms-abschnitt">
        <?php // Die Teilen-Karte: der eine Verweis, um den sich der Bereich dreht. ?>
        <div class="card elev-sm ms-teilen">
            <div>
                <h2 class="card-title"><?= te('profil.teilen_titel') ?></h2>
                <p class="card-body"><?= te($oeffentlich ? 'profil.teilen_text' : 'profil.teilen_text_privat') ?></p>
            </div>
            <?php if ($oeffentlich && $oeffentlicheAdresse !== null): ?>
                <p class="ms-teilen__adresse"><?= e($oeffentlicheAdresse) ?></p>
                <div class="ms-teilen__knoepfe">
                    <a class="btn btn-primary" href="/p/<?= e(rawurlencode($pseudonym)) ?>"><?= te('profil.teilen_ansehen') ?></a>
                    <?php // Ohne JavaScript bleibt der Knopf verborgen; die Adresse steht daneben und lässt sich markieren. ?>
                    <button class="btn btn-ghost" type="button" hidden
                            data-teilen="<?= e($oeffentlicheAdresse) ?>"
                            data-teilen-titel="<?= e($name) ?>"
                            data-teilen-kopiert="<?= te('profil.teilen_kopiert') ?>"><?= te('profil.teilen_knopf') ?></button>
                </div>
            <?php else: ?>
                <div class="ms-teilen__knoepfe">
                    <a class="btn btn-primary" href="/profil/einstellungen"><?= te('profil.teilen_einschalten') ?></a>
                </div>
            <?php endif; ?>
        </div>

        <div class="ms-kennzahlen">
            <a class="card elev-sm" href="/verkaufen">
                <span class="ms-zahl ms-zahl--mittel"><?= $angebote ?></span>
                <span class="card-meta"><?= te('profil.zahl_angebote') ?></span>
            </a>
            <a class="card elev-sm" href="/profil/links">
                <span class="ms-zahl ms-zahl--mittel"><?= count($links) ?></span>
                <span class="card-meta"><?= te('profil.zahl_links') ?></span>
            </a>
            <a class="card elev-sm" href="/nachrichten">
                <span class="ms-zahl ms-zahl--mittel">→</span>
                <span class="card-meta"><?= te('profil.zahl_nachrichten') ?></span>
            </a>
        </div>

        <?php if ($erledigt < count($schritte)): ?>
            <div class="card elev-sm">
                <h2 class="card-title"><?= te('profil.schritte_titel') ?></h2>
                <p class="card-meta"><?= te('profil.schritte_stand', ['erledigt' => $erledigt, 'gesamt' => count($schritte)]) ?></p>
                <ul class="ms-schritte">
                    <?php foreach ($schritte as [$schluessel, $fertig, $ziel]): ?>
                        <li class="<?= $fertig ? 'ms-schritte__fertig' : '' ?>">
                            <?php if ($fertig): ?>
                                <span><?= te('profil.schritt.' . $schluessel) ?></span>
                            <?php else: ?>
                                <a href="<?= $ziel ?>"><?= te('profil.schritt.' . $schluessel) ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </section>

    <section class="ms-abschnitt" id="entscheidungen">
        <div>
            <p class="ms-kicker"><?= te('profil.entscheidungen_kicker') ?></p>
            <h2><?= te('profil.entscheidungen_titel') ?>
                <?php if ($ungelesen > 0): ?>
                    <span class="tag tag-accent"><?= $ungelesen ?></span>
                <?php endif; ?>
            </h2>
            <p class="hinweis"><?= te('profil.entscheidungen_erklaerung') ?></p>
        </div>

        <?php if ($benachrichtigungen === []): ?>
            <article class="card elev-sm">
                <h3 class="card-title"><?= te('profil.keine_titel') ?></h3>
                <p class="card-body"><?= te('profil.keine_text') ?></p>
            </article>
        <?php else: ?>
            <?php foreach ($benachrichtigungen as $eintrag): ?>
                <article class="card elev-sm">
                    <p class="card-kicker"><?= te('profil.art.' . (string) $eintrag['art']) ?></p>
                    <h3 class="card-title"><?= te('profil.begruendung_ueberschrift') ?></h3>

                    <?php // Die Begründung steht ungekürzt da. Eine Zusammenfassung
                          // wäre eine zweite Entscheidung darüber, was die Person
                          // erfahren darf — genau das verbietet Art. 17 DSA. ?>
                    <p class="card-body" style="white-space:pre-line;overflow-wrap:anywhere"><?= e((string) ($eintrag['begruendung'] ?? '')) ?></p>

                    <p class="card-meta">
                        <?= te('profil.entschieden_am') ?> <?= e((string) $eintrag['angelegt_am']) ?>
                    </p>

                    <?php if (($eintrag['gelesen'] ?? false) === false): ?>
                        <form method="post" action="/profil/gelesen" style="margin:0">
                            <?= $feld() ?>
                            <input type="hidden" name="id" value="<?= (int) $eintrag['id'] ?>">
                            <button class="btn btn-ghost" type="submit"><?= te('profil.als_gelesen') ?></button>
                        </form>
                    <?php else: ?>
                        <p class="card-meta"><?= te('profil.gelesen_am') ?> <?= e((string) $eintrag['gelesen_am']) ?></p>
                    <?php endif; ?>

                    <p class="hinweis"><?= te('profil.widerspruch') ?> <a href="/impressum"><?= te('profil.widerspruch_weg') ?></a></p>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

<?php elseif ($reiter === 'bearbeiten'): ?>

    <section class="ms-abschnitt ms-schmal" id="bild">
        <div class="card elev-sm ms-bildkarte">
            <span class="ms-profilbild ms-profilbild--gross">
                <?php if ($hatBild): ?>
                    <img src="/p/<?= e(rawurlencode($pseudonym)) ?>/bild" width="112" height="112" alt="">
                <?php else: ?>
                    <?= e(mb_substr($name, 0, 1)) ?>
                <?php endif; ?>
            </span>
            <div class="ms-bildkarte__formular">
                <h2 class="card-title"><?= te('profil.bild_titel') ?></h2>
                <p class="hinweis"><?= te('profil.bild_regel') ?></p>
                <?php // enctype und MAX_FILE_SIZE vor dem Dateifeld: siehe markt/angebot_bearbeiten.php. ?>
                <form method="post" action="/profil/bild" enctype="multipart/form-data" class="ms-zeile">
                    <?= $feld() ?>
                    <input type="hidden" name="MAX_FILE_SIZE" value="<?= $bildGrenzeBytes ?>">
                    <label class="ms-nur-vorlesen" for="bild-datei"><?= te('profil.bild_feld') ?></label>
                    <input class="input" type="file" id="bild-datei" name="bild" required accept="<?= e($bildFormate) ?>">
                    <button class="btn btn-primary" type="submit"><?= te('profil.bild_hochladen') ?></button>
                </form>
                <?php if ($hatBild): ?>
                    <form method="post" action="/profil/bild/entfernen" style="margin:0">
                        <?= $feld() ?>
                        <button class="btn btn-ghost" type="submit"><?= te('profil.bild_entfernen') ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <form method="post" action="/profil/bearbeiten" class="card elev-sm ms-formular">
            <?= $feld() ?>
            <div class="field">
                <label for="anzeigename"><?= te('profil.feld_anzeigename') ?></label>
                <input class="input" type="text" id="anzeigename" name="anzeigename" maxlength="60"
                       value="<?= e((string) ($profil['anzeigename'] ?? '')) ?>" placeholder="<?= e($pseudonym) ?>">
                <span class="hinweis"><?= te('profil.hinweis_anzeigename') ?></span>
            </div>
            <div class="field">
                <label for="vorstellung"><?= te('profil.feld_vorstellung') ?></label>
                <textarea class="input" id="vorstellung" name="vorstellung" rows="6" maxlength="2000"><?= e($vorstellung) ?></textarea>
                <span class="hinweis"><?= te('profil.hinweis_vorstellung') ?></span>
            </div>
            <button class="btn btn-primary" type="submit"><?= te('profil.speichern') ?></button>
        </form>
    </section>

<?php elseif ($reiter === 'links'): ?>

    <section class="ms-abschnitt ms-schmal">
        <div>
            <h2><?= te('profil.links_titel') ?></h2>
            <p class="hinweis"><?= te('profil.links_erklaerung', ['anzahl' => $linksHoechstens]) ?></p>
        </div>

        <?php if ($links === []): ?>
            <p class="card elev-sm card-body"><?= te('profil.links_leer') ?></p>
        <?php else: ?>
            <ul class="ms-linkverwaltung">
                <?php foreach ($links as $nummer => $link): ?>
                    <li class="card elev-sm">
                        <div class="ms-linkverwaltung__text">
                            <strong><?= e($link['titel']) ?></strong>
                            <span class="card-meta"><?= e($link['adresse']) ?></span>
                        </div>
                        <div class="ms-linkverwaltung__knoepfe">
                            <?php foreach ([['hoch', '↑', $nummer > 0], ['runter', '↓', $nummer < count($links) - 1]] as [$aktion, $zeichen, $moeglich]): ?>
                                <form method="post" action="/profil/links/<?= $link['id'] ?>/<?= $aktion ?>" style="margin:0">
                                    <?= $feld() ?>
                                    <button class="btn btn-icon" type="submit" <?= $moeglich ? '' : 'disabled' ?>
                                            aria-label="<?= te('profil.link_' . $aktion) ?>"><?= $zeichen ?></button>
                                </form>
                            <?php endforeach; ?>
                            <form method="post" action="/profil/links/<?= $link['id'] ?>/entfernen" style="margin:0">
                                <?= $feld() ?>
                                <button class="btn btn-ghost" type="submit"><?= te('profil.link_entfernen') ?></button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (count($links) < $linksHoechstens): ?>
            <form method="post" action="/profil/links" class="card elev-sm ms-formular">
                <?= $feld() ?>
                <h3 class="card-title"><?= te('profil.link_neu_titel') ?></h3>
                <div class="field">
                    <label for="link-titel"><?= te('profil.link_feld_titel') ?></label>
                    <input class="input" type="text" id="link-titel" name="titel" maxlength="60" required>
                </div>
                <div class="field">
                    <label for="link-adresse"><?= te('profil.link_feld_adresse') ?></label>
                    <input class="input" type="text" id="link-adresse" name="adresse" maxlength="500" required
                           inputmode="url" autocapitalize="none" placeholder="https://">
                    <span class="hinweis"><?= te('profil.link_hinweis_adresse') ?></span>
                </div>
                <button class="btn btn-primary" type="submit"><?= te('profil.link_hinzufuegen') ?></button>
            </form>
        <?php endif; ?>
    </section>

<?php else: ?>

    <section class="ms-abschnitt ms-schmal">
        <form method="post" action="/profil/sichtbarkeit" class="card elev-sm ms-formular">
            <?= $feld() ?>
            <h2 class="card-title"><?= te('profil.sichtbar_titel') ?></h2>
            <label class="ms-schalter">
                <input type="checkbox" name="oeffentlich" value="1" <?= $oeffentlich ? 'checked' : '' ?>>
                <span>
                    <strong><?= te('profil.sichtbar_feld') ?></strong>
                    <span class="hinweis"><?= te('profil.sichtbar_erklaerung') ?></span>
                </span>
            </label>
            <button class="btn btn-primary" type="submit"><?= te('profil.speichern') ?></button>
        </form>

        <div class="card elev-sm ms-zeilenkarte">
            <div>
                <h2 class="card-title"><?= te('profil.chat_titel') ?></h2>
                <p class="card-body">
                    <?= $profil !== null && is_string($profil['chat_deklaration'])
                        ? te('chat.deklaration.' . $profil['chat_deklaration'])
                        : te('profil.chat_offen') ?>
                </p>
            </div>
            <a class="btn btn-ghost" href="/nachrichten/deklaration"><?= te('profil.chat_aendern') ?></a>
        </div>

        <div class="card elev-sm ms-zeilenkarte">
            <div>
                <h2 class="card-title"><?= te('verifizierung.abzeichen') ?></h2>
                <p class="card-body"><?= te($abzeichen ? 'verifizierung.abzeichen_vorhanden' : 'verifizierung.abzeichen_fehlt') ?></p>
            </div>
            <a class="btn btn-ghost" href="/verifizierung"><?= te('profil.pruefung_weg') ?></a>
        </div>

        <form method="post" action="/abmelden" class="card elev-sm ms-zeilenkarte">
            <?= $feld() ?>
            <div>
                <h2 class="card-title"><?= te('profil.abmelden_titel') ?></h2>
                <p class="card-body"><?= te('profil.abmelden_text') ?></p>
            </div>
            <button class="btn btn-ghost" type="submit"><?= te('allgemein.nav_abmelden') ?></button>
        </form>
    </section>

    <section class="ms-abschnitt ms-schmal" id="konto-loeschen">
        <div>
            <p class="ms-kicker"><?= te('profil.loeschen_kicker') ?></p>
            <h2><?= te('profil.loeschen_titel') ?></h2>
            <p class="hinweis"><?= te('profil.loeschen_was_geht') ?></p>
            <p class="hinweis"><?= te('profil.loeschen_was_bleibt') ?></p>
        </div>

        <form method="post" action="/profil/loeschen" class="card elev-sm ms-formular">
            <?= $feld() ?>
            <div class="field">
                <label for="loeschen-passwort"><?= te('profil.loeschen_passwort') ?></label>
                <input class="input" id="loeschen-passwort" name="passwort" type="password" autocomplete="current-password" required>
            </div>
            <label class="ms-schalter">
                <input type="checkbox" name="bestaetigt" value="1" required>
                <span><?= te('profil.loeschen_bestaetigen') ?></span>
            </label>
            <button class="btn btn-ghost" type="submit"><?= te('profil.loeschen_knopf') ?></button>
        </form>
    </section>

<?php endif; ?>
