<?php

declare(strict_types=1);

namespace MeinSlip\Domain\Account;

/**
 * Fehler bei Registrierung oder Anmeldung.
 *
 * Die Nachricht ist bewusst ein Schluessel wie 'email_vergeben' und kein
 * fertiger Satz: Uebersetzt wird in der Oberflaeche, damit die
 * Mehrsprachigkeit spaeter kein Umbau wird.
 */
final class KontoFehler extends \RuntimeException
{
    public function schluessel(): string
    {
        return $this->getMessage();
    }
}
