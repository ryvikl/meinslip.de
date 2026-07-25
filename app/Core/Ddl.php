<?php

declare(strict_types=1);

namespace MeinSlip\Core;

/**
 * Kleine Uebersetzungshilfe fuer die Stellen, an denen sich MySQL und SQLite
 * unterscheiden. Produktiv laeuft MySQL, die Tests laufen gegen SQLite — beide
 * muessen dasselbe Schema erzeugen.
 *
 * Bewusst klein gehalten: Sobald mehr als diese wenigen Faelle noetig waeren,
 * waere ein echter Schema-Aufbau die bessere Antwort als eine wachsende
 * Sammlung von Sonderfaellen.
 */
final class Ddl
{
    public function __construct(private readonly Database $db)
    {
    }

    /** Primaerschluessel mit automatischer Nummerierung. */
    public function id(string $name = 'id'): string
    {
        return $this->db->istSqlite()
            ? "{$name} INTEGER PRIMARY KEY AUTOINCREMENT"
            : "{$name} BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY";
    }

    /** Fremdschluesselspalte, passend zum Typ von id(). */
    public function fremdschluessel(string $name, bool $optional = false): string
    {
        $typ = $this->db->istSqlite() ? 'INTEGER' : 'BIGINT UNSIGNED';
        $null = $optional ? 'NULL' : 'NOT NULL';

        return "{$name} {$typ} {$null}";
    }

    /**
     * Geldbetraege werden immer als ganzzahlige Cent gefuehrt, nie als
     * Gleitkommazahl. Vorzeichenbehaftet, weil das Hauptbuch Soll und Haben
     * als negative und positive Betraege fuehrt.
     */
    public function betrag(string $name): string
    {
        return "{$name} BIGINT NOT NULL";
    }

    public function text(string $name, int $laenge = 255, bool $optional = false): string
    {
        $null = $optional ? 'NULL' : 'NOT NULL';

        return "{$name} VARCHAR({$laenge}) {$null}";
    }

    public function langtext(string $name, bool $optional = true): string
    {
        $null = $optional ? 'NULL' : 'NOT NULL';

        return "{$name} TEXT {$null}";
    }

    public function ganzzahl(string $name, bool $optional = false, ?int $standard = null): string
    {
        $null = $optional ? 'NULL' : 'NOT NULL';
        $vorgabe = $standard === null ? '' : " DEFAULT {$standard}";

        return "{$name} INTEGER {$null}{$vorgabe}";
    }

    public function jaNein(string $name, bool $standard = false): string
    {
        $vorgabe = $standard ? '1' : '0';

        return $this->db->istSqlite()
            ? "{$name} INTEGER NOT NULL DEFAULT {$vorgabe}"
            : "{$name} TINYINT(1) NOT NULL DEFAULT {$vorgabe}";
    }

    public function zeitpunkt(string $name, bool $optional = true): string
    {
        $null = $optional ? 'NULL' : 'NOT NULL';

        return $this->db->istSqlite()
            ? "{$name} TEXT {$null}"
            : "{$name} DATETIME {$null}";
    }

    /** Anlagezeitpunkt mit Vorgabewert. */
    public function angelegtAm(string $name = 'angelegt_am'): string
    {
        return $this->db->istSqlite()
            ? "{$name} TEXT NOT NULL DEFAULT (datetime('now'))"
            : "{$name} DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP";
    }

    /**
     * Zustaende und Arten werden als Text gespeichert, nicht als ENUM.
     * ENUM kennt SQLite nicht, und eine Zustandserweiterung waere in MySQL
     * eine Schemaaenderung statt einer Codeaenderung.
     */
    public function schluesselwort(string $name, int $laenge = 40): string
    {
        return "{$name} VARCHAR({$laenge}) NOT NULL";
    }

    /** Tabellenzusatz: Zeichensatz und Speicher-Engine nur fuer MySQL. */
    public function tabellenzusatz(): string
    {
        return $this->db->istSqlite() ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }

    /**
     * Erzeugt eine Tabelle aus Spalten- und Bedingungszeilen.
     *
     * @param list<string> $zeilen
     */
    public function tabelle(string $name, array $zeilen): string
    {
        return sprintf(
            "CREATE TABLE IF NOT EXISTS %s (\n  %s\n)%s",
            $name,
            implode(",\n  ", $zeilen),
            $this->tabellenzusatz()
        );
    }

    /** @param list<string> $spalten */
    public function index(string $tabelle, array $spalten, bool $eindeutig = false): string
    {
        $name = 'idx_' . $tabelle . '_' . implode('_', $spalten);

        // Eigenstaendiger Befehl statt Inline-Definition: SQLite kennt kein
        // INDEX innerhalb von CREATE TABLE, MySQL akzeptiert beide Formen.
        // IF NOT EXISTS gibt es bei CREATE INDEX nur in SQLite und MariaDB,
        // nicht in MySQL — dort schuetzt die Migrationstabelle vor Doppellaeufen.
        return sprintf(
            'CREATE %s %s%s ON %s (%s)',
            $eindeutig ? 'UNIQUE INDEX' : 'INDEX',
            $this->db->istSqlite() ? 'IF NOT EXISTS ' : '',
            $name,
            $tabelle,
            implode(', ', $spalten)
        );
    }

    public function fremdschluesselBedingung(
        string $spalte,
        string $zielTabelle,
        string $zielSpalte = 'id',
        string $beimLoeschen = 'CASCADE'
    ): string {
        return sprintf(
            'FOREIGN KEY (%s) REFERENCES %s(%s) ON DELETE %s',
            $spalte,
            $zielTabelle,
            $zielSpalte,
            $beimLoeschen
        );
    }
}
