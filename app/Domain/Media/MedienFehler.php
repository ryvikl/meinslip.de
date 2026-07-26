<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Media;

/**
 * Fehler der Bildaufnahme.
 *
 * Zweigeteilt wie AngebotFehler und MeldungsFehler: Der Schluessel ist stabil
 * und uebersetzbar — die Oberflaeche baut daraus 'medien.fehler.' .
 * $fehler->schluessel() und zeigt NIE getMessage(). Die Meldung darf
 * zusaetzlich Kontext tragen (welcher MIME-Typ, welche Kantenlaenge), damit
 * ein Protokolleintrag ohne Nachschlagen verstaendlich ist.
 *
 * WARUM DIE MELDUNG HIER NICHT DER SCHLUESSEL SEIN DARF (anders als bei
 * KontoFehler): Die Meldungen dieser Klasse nennen fast immer einen Wert aus
 * der hochgeladenen Datei — den gemeldeten MIME-Typ, die Bildmasse, den
 * Dateinamen. Das ist Angreiferwissen ueber die eigene Datei, also harmlos,
 * aber es ist auch Serverwissen ueber Grenzwerte und Speicherausstattung.
 * Beides gehoert ins Protokoll und nicht auf die Seite.
 *
 * ZWEI GRENZFAELLE HABEN ABSICHTLICH EIGENE SCHLUESSEL, obwohl sie technisch
 * derselbe Vorgang sind:
 *
 *  - 'upload_zu_gross' (UPLOAD_ERR_INI_SIZE / UPLOAD_ERR_FORM_SIZE) und
 *    'datei_zu_gross' (unsere eigene Byte-Grenze) sind ein BEDIENFEHLER. Wer
 *    ein Handyfoto mit 12 MB hochlaedt, hat nichts falsch gemacht ausser das
 *    Bild nicht verkleinert zu haben — die Seite muss das freundlich und mit
 *    einer Zahl beantworten, nicht wie einen Angriffsversuch.
 *  - 'nicht_hochgeladen', 'typ_widerspruch' und 'zu_viele_pixel' sind das
 *    Gegenteil: Sie entstehen bei normaler Bedienung nie. Sie duerfen knapp
 *    und ohne Hilfestellung beantwortet werden.
 *
 * Eine dritte Gruppe fehlt hier bewusst: Ueberschreitet die Anfrage
 * post_max_size, ist $_POST LEER — es gibt dann weder eine Datei noch das
 * CSRF-Feld, und die Anfrage sieht fuer den Server wie ein CSRF-Verstoss aus.
 * Dieser Fall erreicht Bilder::annehmen() nie und muss in der Route erkannt
 * werden (leeres $_POST bei CONTENT_LENGTH > 0). Er passiert beim ersten
 * Handyfoto und braucht dort einen eigenen Schluessel.
 */
final class MedienFehler extends \RuntimeException
{
    public function __construct(private readonly string $schluessel, string $meldung = '')
    {
        parent::__construct($meldung === '' ? $schluessel : $meldung);
    }

    /** Der uebersetzbare Grund, z. B. 'zu_viele_pixel'. */
    public function schluessel(): string
    {
        return $this->schluessel;
    }
}
