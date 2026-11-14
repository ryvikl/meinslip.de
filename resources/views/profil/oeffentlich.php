<?php

declare(strict_types=1);

/**
 * Die öffentliche Profilseite — eine schmale Spalte zum Teilen: Bild, Name,
 * Vorstellung, Verweise, die letzten Angebote.
 *
 * $profil ist null, wenn es die Seite nicht gibt, das Konto nicht aktiv ist
 * oder das Profil nicht öffentlich geschaltet wurde. Die Seite sagt nicht,
 * welcher der drei Fälle es ist.
 *
 * @var array<string,mixed>|null $profil
 * @var list<array{id:int,titel:string,adresse:string,rechner:string}> $links
 * @var list<array<string,mixed>> $angebote
 * @var bool $abzeichen
 * @var bool $eigenes
 * @var string $oeffentlicheAdresse
 * @var array<string,mixed>|null $sitzung
 */

if ($profil === null): ?>
    <section class="ms-abschnitt ms-profilseite">
        <span class="ms-profilbild ms-profilbild--gross">?</span>
        <h1><?= te('profil.oeffentlich_unbekannt_titel') ?></h1>
        <p class="card-body"><?= te('profil.oeffentlich_unbekannt_text') ?></p>
        <a class="btn btn-primary" href="/entdecken"><?= te('profil.weg_entdecken') ?></a>
    </section>
    <?php return; ?>
<?php endif;

$pseudonym = (string) $profil['pseudonym'];
$name = (string) $profil['name'];
$hatBild = is_string($profil['bild_pfad']) && $profil['bild_pfad'] !== '';
$vorstellung = is_string($profil['vorstellung']) ? $profil['vorstellung'] : '';
?>
<section class="ms-profilseite">
    <span class="ms-profilbild ms-profilbild--gross">
        <?php if ($hatBild): ?>
            <img src="/p/<?= e(rawurlencode($pseudonym)) ?>/bild" width="112" height="112" alt="<?= te('profil.bild_alt', ['name' => $name]) ?>">
        <?php else: ?>
            <?= e(mb_substr($name, 0, 1)) ?>
        <?php endif; ?>
    </span>

    <div>
        <h1><?= e($name) ?></h1>
        <p class="ms-profilseite__pseudonym">@<?= e($pseudonym) ?></p>
    </div>

    <p class="ms-tagzeile" style="justify-content:center">
        <?php if ($abzeichen): ?>
            <span class="tag tag-accent" title="<?= te('verifizierung.abzeichen_was_nicht') ?>"><?= te('verifizierung.abzeichen') ?></span>
        <?php endif; ?>
        <?php if (is_string($profil['chat_deklaration'])): ?>
            <span class="tag tag-outline"><?= te('chat.deklaration.' . $profil['chat_deklaration']) ?></span>
        <?php endif; ?>
    </p>

    <?php if ($vorstellung !== ''): ?>
        <p class="ms-profilseite__vorstellung"><?= e($vorstellung) ?></p>
    <?php endif; ?>

    <div class="ms-profilseite__aktionen">
        <?php if ($eigenes): ?>
            <a class="btn btn-primary" href="/profil/bearbeiten"><?= te('profil.oeffentlich_bearbeiten') ?></a>
        <?php elseif ($sitzung !== null): ?>
            <form method="post" action="/nachrichten/neu" style="margin:0">
                <?= \MeinSlip\Http\Formularschutz::feld() ?>
                <input type="hidden" name="empfaenger_id" value="<?= (int) $profil['benutzer_id'] ?>">
                <button class="btn btn-primary" type="submit"><?= te('profil.oeffentlich_nachricht') ?></button>
            </form>
        <?php else: ?>
            <a class="btn btn-primary" href="/anmelden"><?= te('profil.oeffentlich_nachricht_anmelden') ?></a>
        <?php endif; ?>
        <button class="btn btn-ghost" type="button" hidden
                data-teilen="<?= e($oeffentlicheAdresse) ?>"
                data-teilen-titel="<?= e($name) ?>"
                data-teilen-kopiert="<?= te('profil.teilen_kopiert') ?>"><?= te('profil.teilen_knopf') ?></button>
    </div>

    <?php if ($links !== []): ?>
        <ul class="ms-linkliste">
            <?php foreach ($links as $link): ?>
                <li>
                    <?php // noopener/noreferrer: Die Zielseite erfährt nicht, woher der Besuch kommt,
                          // und bekommt keinen Zugriff auf dieses Fenster. ugc/nofollow: Der
                          // Verweis stammt von einer Nutzerin, nicht von uns. ?>
                    <a class="ms-linkliste__eintrag" href="<?= e($link['adresse']) ?>"
                       target="_blank" rel="noopener noreferrer nofollow ugc">
                        <span class="ms-linkliste__titel"><?= e($link['titel']) ?></span>
                        <span class="ms-linkliste__ziel"><?= e($link['rechner']) ?> ↗</span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php if ($angebote !== []): ?>
    <section class="ms-abschnitt">
        <div style="text-align:center">
            <p class="ms-kicker"><?= te('profil.oeffentlich_angebote_kicker') ?></p>
            <h2><?= te('profil.oeffentlich_angebote_titel') ?></h2>
        </div>
        <div class="ms-raster--kacheln">
            <?php foreach ($angebote as $angebot): ?>
                <?php $vorschau = is_array($angebot['vorschau'] ?? null) ? $angebot['vorschau'] : null; ?>
                <a class="ms-kachel" href="/angebot/<?= (int) $angebot['id'] ?>">
                    <span class="ms-kachel__bild">
                        <?php // Wie im Katalog: ein als nicht jugendfrei markiertes Bild erscheint hier nie. ?>
                        <?php if ($vorschau !== null && $vorschau['explizit'] === true): ?>
                            <span class="ms-gesperrt__schloss"><?= te('medien.gesperrt_kachel') ?></span>
                        <?php elseif ($vorschau !== null): ?>
                            <img src="/medien/angebot/<?= (int) $vorschau['id'] ?>" alt="<?= te('medien.bild_alt') ?>" loading="lazy">
                        <?php endif; ?>
                    </span>
                    <span class="ms-kachel__inhalt">
                        <span class="ms-kachel__titel"><?= e((string) $angebot['titel']) ?></span>
                        <span class="ms-kachel__preiszeile">
                            <span class="ms-kachel__preis"><?= e(geld((int) $angebot['grundpreis_cent'], (string) $angebot['waehrung'])) ?></span>
                        </span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if (!$eigenes): ?>
    <p style="text-align:center">
        <a class="hinweis" href="/melden?art=benutzer&amp;id=<?= (int) $profil['benutzer_id'] ?>"><?= te('profil.oeffentlich_melden') ?></a>
    </p>
<?php endif; ?>
