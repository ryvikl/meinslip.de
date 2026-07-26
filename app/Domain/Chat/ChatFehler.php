<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Chat;

/**
 * Fehler des Chats.
 *
 * Zweigeteilt wie MeldungsFehler und VerwaltungsFehler: Der Schluessel ist
 * stabil und uebersetzbar — die Oberflaeche baut daraus 'chat.fehler.' .
 * $fehler->schluessel() und zeigt NIE getMessage(). Die Meldung darf
 * zusaetzlich Kontext tragen (welche Unterhaltung, welches Konto), damit eine
 * Fehlersuche ohne Nachschlagen auskommt.
 *
 * Warum die Meldung hier nicht selbst der Schluessel sein darf (wie bei
 * KontoFehler): Sie nennt Kennungen. 'nicht_teilnehmer' mit der Kennung im
 * Text wuerde einer fremden Person verraten, dass es die Unterhaltung gibt —
 * und wer daran beteiligt ist. Der Chat ist die Stelle mit den empfindlichsten
 * Daten der ganzen Plattform; hier waere ein durchgereichter Ausnahmetext ein
 * Datenabfluss.
 *
 * Die vollstaendige Liste der Schluessel, die diese Klasse tragen kann:
 * nicht_teilnehmer, gesperrt, text_leer, text_zu_lang, deklaration_fehlt,
 * selbstgespraech, unterhaltung_unbekannt, zu_schnell, empfaenger_unbekannt.
 * Jeder davon braucht einen Text unter 'chat.fehler.<schluessel>'.
 *
 * Nicht hier, sondern als VerwaltungsFehler: alles, was aus /verwaltung kommt.
 * verbergen() ist ein Verwaltungsvorgang und meldet sich deshalb in der
 * Sprache der Verwaltung — die schreibende Person ist dort nicht die
 * betroffene, und die Fehlertexte gehoeren in einen Bereich, den nur
 * Verwalterinnen sehen.
 */
final class ChatFehler extends \RuntimeException
{
    public function __construct(private readonly string $schluessel, string $meldung = '')
    {
        parent::__construct($meldung === '' ? $schluessel : $meldung);
    }

    /** Der uebersetzbare Grund, z. B. 'gesperrt'. */
    public function schluessel(): string
    {
        return $this->schluessel;
    }
}
