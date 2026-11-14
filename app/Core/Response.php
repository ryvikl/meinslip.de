<?php

declare(strict_types=1);

namespace MeinSlip\Core;

final class Response
{
    /** @param array<string,string> $kopfzeilen */
    private function __construct(
        public readonly int $status,
        public readonly string $inhalt,
        public readonly array $kopfzeilen = [],
        /**
         * Ist $inhalt ein Dateipfad statt des Rumpfes?
         *
         * Nur datei() setzt das. Der Merker ist noetig, weil senden() sonst
         * den Pfad als Text ausgeben wuerde — und das waere ein Abfluss des
         * Serverdateisystems im Rumpf einer 200er-Antwort.
         */
        public readonly bool $istDatei = false,
    ) {
    }

    /** @param array<string,string> $kopfzeilen */
    public static function html(string $inhalt, int $status = 200, array $kopfzeilen = []): self
    {
        return new self($status, $inhalt, ['Content-Type' => 'text/html; charset=utf-8'] + $kopfzeilen);
    }

    /** @param mixed $daten */
    public static function json(mixed $daten, int $status = 200): self
    {
        return new self(
            $status,
            json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public static function weiterleitung(string $ziel, int $status = 302): self
    {
        return new self($status, '', ['Location' => $ziel]);
    }

    public static function text(string $inhalt, int $status = 200): self
    {
        return new self($status, $inhalt, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /**
     * Liefert eine Datei aus, die NICHT im oeffentlichen Verzeichnis liegt.
     *
     * Der Inhaltstyp wird uebergeben und nie aus dem Dateinamen geraten: Ein
     * geratener Typ ist die halbe Miete jeder Dateiablage-Luecke. Zusammen mit
     * dem X-Content-Type-Options: nosniff aus senden() entscheidet damit
     * ausschliesslich der Aufrufer, als was der Browser die Bytes behandelt.
     *
     * Content-Length steht hier und nicht in senden(), weil nur hier bekannt
     * ist, dass es ueberhaupt eine Datei gibt. Fehlt die Groesse — die Datei
     * verschwindet zwischen Pruefung und Auslieferung —, wird die Kopfzeile
     * weggelassen statt eine falsche Zahl zu senden.
     *
     * Aufrufer muessen den Pfad VORHER gegen ihr eigenes Verzeichnis
     * abgesichert haben. Diese Methode prueft ihn nicht; sie kann es auch
     * nicht, weil sie kein zulaessiges Verzeichnis kennt.
     *
     * $kopfzeilen ist optional und traegt genau einen Fall: die
     * Zwischenspeicherregel. Sie kann nicht hier entschieden werden — ob eine
     * Datei oeffentlich zwischengespeichert werden darf, weiss allein der
     * Aufrufer, der die Berechtigung geprueft hat. Und sie kann auch nicht
     * nachtraeglich gesetzt werden, weil eine Antwort unveraenderlich ist.
     *
     * @param array<string,string> $kopfzeilen
     */
    public static function datei(string $pfad, string $typ, array $kopfzeilen = []): self
    {
        $groesse = @filesize($pfad);
        $eigene = ['Content-Type' => $typ];

        if ($groesse !== false) {
            $eigene['Content-Length'] = (string) $groesse;
        }

        // Union statt array_merge: Content-Type und Content-Length gewinnen,
        // damit ein Aufrufer den Typ nicht versehentlich ueberschreibt.
        return new self(200, $pfad, $eigene + $kopfzeilen, true);
    }

    public function senden(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->kopfzeilen as $name => $wert) {
                header($name . ': ' . $wert, true);
            }

            // Sicherheitskopfzeilen fuer jede Antwort.
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: no-referrer');
            header('X-Frame-Options: DENY');
            // Diskretion: Die Seite soll nicht in fremden Vorschauen auftauchen.
            header('Permissions-Policy: geolocation=(self), camera=(self), microphone=()');
            // Zweite Linie hinter der Maskierung in den Vorlagen: Selbst wenn
            // einmal ein Wert ungefiltert in eine Seite geriete, laedt und
            // startet der Browser nichts, was nicht von hier kommt.
            // 'unsafe-inline' nur fuer Stile — die Vorlagen setzen style="".
            // Skripte gibt es ausschliesslich als Datei.
            header(
                "Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; "
                . "style-src 'self' 'unsafe-inline'; script-src 'self'; object-src 'none'; "
                . "frame-ancestors 'none'; base-uri 'self'; form-action 'self'"
            );
            // Kennzeichnung "nur fuer Erwachsene" fuer Filterprogramme.
            header('Rating: RTA-5042-1996-1400-1577-RTA');

            // Seiten nie zwischenspeichern: Chat, Profil und Verwaltung blieben
            // sonst im Verlauf und im Zurueck-Speicher eines geteilten Geraets
            // lesbar, nachdem sich jemand abgemeldet hat. Dateien (Bilder)
            // bringen ihre eigene Angabe mit.
            if (!$this->istDatei && !isset($this->kopfzeilen['Cache-Control'])) {
                header('Cache-Control: no-store');
            }
        }

        // Eine Datei wird gestreamt statt in den Arbeitsspeicher gelesen: Ein
        // Bild von mehreren Megabyte ueber file_get_contents zu echoen kostet
        // dieselben Megabyte im memory_limit, und zwar zusaetzlich zu allem,
        // was die Anfrage sonst schon belegt.
        if ($this->istDatei) {
            @readfile($this->inhalt);

            return;
        }

        echo $this->inhalt;
    }
}
