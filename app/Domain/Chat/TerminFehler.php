<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Chat;

/**
 * Fehler der Terminspur.
 *
 * Zweigeteilt wie ChatFehler, MeldungsFehler und VerwaltungsFehler: Der
 * Schluessel ist stabil und uebersetzbar — die Oberflaeche baut daraus
 * 'termin.fehler.' . $fehler->schluessel() und zeigt NIE getMessage(). Die
 * Meldung darf zusaetzlich Kontext tragen (welcher Termin, welches Konto),
 * damit eine Fehlersuche ohne Nachschlagen auskommt.
 *
 * Warum der durchgereichte Ausnahmetext hier verboten ist, gilt woertlich wie
 * beim Chat: Er nennt Kennungen. 'nicht_teilnehmer' mit der Kennung im Text
 * verriete einer fremden Person, dass es diese Unterhaltung gibt und wer daran
 * beteiligt ist. Ein Termin ist darueber hinaus die empfindlichste Zeile, die
 * diese Plattform ueberhaupt fuehrt — sie sagt, dass zwei bestimmte Menschen
 * sich zu einer bestimmten Zeit sehen wollen. Ein Ausnahmetext an der
 * Oberflaeche waere hier kein Schoenheitsfehler, sondern ein Datenabfluss mit
 * unmittelbarer koerperlicher Folge.
 *
 * Die vollstaendige Liste der Schluessel, die diese Klasse tragen kann:
 * unterhaltung_unbekannt, nicht_teilnehmer, gesperrt, termin_unbekannt,
 * zeitpunkt_ungueltig, zeitpunkt_vergangen, zeitpunkt_zu_fern,
 * treffpunkt_unbekannt, region_fehlt, region_zu_lang, grund_zu_lang,
 * eigener_vorschlag, nicht_offen, nicht_angenommen, zu_frueh,
 * bereits_quittiert, zu_schnell, zu_viele_offen, status_unbekannt,
 * unerlaubter_wechsel. Jeder davon braucht einen Text unter
 * 'termin.fehler.<schluessel>'; tests/TermineTest.php haelt das fest.
 */
final class TerminFehler extends \RuntimeException
{
    public function __construct(private readonly string $schluessel, string $meldung = '')
    {
        parent::__construct($meldung === '' ? $schluessel : $meldung);
    }

    /** Der uebersetzbare Grund, z. B. 'eigener_vorschlag'. */
    public function schluessel(): string
    {
        return $this->schluessel;
    }
}
