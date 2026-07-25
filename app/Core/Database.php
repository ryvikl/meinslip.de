<?php

declare(strict_types=1);

namespace MeinSlip\Core;

use PDO;
use PDOStatement;

/**
 * Datenbankzugriff ueber PDO.
 *
 * Produktiv laeuft MySQL auf dem All-Inkl-Webhosting, die Tests laufen gegen
 * SQLite im Arbeitsspeicher. Deshalb kennt diese Klasse ihren Treiber und die
 * Migrationen fragen ihn ab, wo sich die Dialekte unterscheiden.
 *
 * Alle Abfragen laufen ueber vorbereitete Anweisungen. Es gibt in dieser Klasse
 * bewusst keine Methode, die rohes SQL mit eingesetzten Werten ausfuehrt.
 */
final class Database
{
    private PDO $pdo;

    private string $treiber;

    private int $transaktionstiefe = 0;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->treiber = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($this->treiber === 'sqlite') {
            // Ohne diese Zeile ignoriert SQLite Fremdschluessel stillschweigend.
            $this->pdo->exec('PRAGMA foreign_keys = ON');
        }
    }

    public static function ausEnv(): self
    {
        $treiber = Env::get('DB_TREIBER', 'mysql');

        if ($treiber === 'sqlite') {
            $pfad = Env::get('DB_PFAD', ':memory:');

            return new self(new PDO('sqlite:' . $pfad));
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            Env::pflicht('DB_HOST'),
            Env::get('DB_PORT', '3306'),
            Env::pflicht('DB_NAME')
        );

        return new self(new PDO($dsn, Env::pflicht('DB_BENUTZER'), Env::pflicht('DB_PASSWORT')));
    }

    public static function imArbeitsspeicher(): self
    {
        return new self(new PDO('sqlite::memory:'));
    }

    public function treiber(): string
    {
        return $this->treiber;
    }

    public function istSqlite(): bool
    {
        return $this->treiber === 'sqlite';
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** @param array<string,mixed> $werte */
    public function ausfuehren(string $sql, array $werte = []): PDOStatement
    {
        $anweisung = $this->pdo->prepare($sql);
        $anweisung->execute($werte);

        return $anweisung;
    }

    /**
     * Fuehrt reines DDL aus. Nur fuer Migrationen — enthaelt nie Nutzereingaben.
     */
    public function ddl(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    /**
     * @param array<string,mixed> $werte
     * @return array<string,mixed>|null
     */
    public function eine(string $sql, array $werte = []): ?array
    {
        $zeile = $this->ausfuehren($sql, $werte)->fetch();

        return $zeile === false ? null : $zeile;
    }

    /**
     * @param array<string,mixed> $werte
     * @return list<array<string,mixed>>
     */
    public function alle(string $sql, array $werte = []): array
    {
        return $this->ausfuehren($sql, $werte)->fetchAll();
    }

    /** @param array<string,mixed> $werte */
    public function wert(string $sql, array $werte = []): mixed
    {
        $ergebnis = $this->ausfuehren($sql, $werte)->fetchColumn();

        return $ergebnis === false ? null : $ergebnis;
    }

    /** @param array<string,mixed> $daten */
    public function einfuegen(string $tabelle, array $daten): int
    {
        $spalten = array_keys($daten);
        $platzhalter = array_map(static fn (string $s): string => ':' . $s, $spalten);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $tabelle,
            implode(', ', $spalten),
            implode(', ', $platzhalter)
        );

        $this->ausfuehren($sql, $daten);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Verschachtelbare Transaktion. Innere Aufrufe zaehlen nur mit, damit
     * zusammengesetzte Vorgaenge — etwa eine Bestellung samt Buchungen —
     * als eine Einheit committen.
     *
     * @template T
     * @param callable():T $arbeit
     * @return T
     */
    public function transaktion(callable $arbeit): mixed
    {
        if ($this->transaktionstiefe === 0) {
            $this->pdo->beginTransaction();
        }
        $this->transaktionstiefe++;

        try {
            $ergebnis = $arbeit();
        } catch (\Throwable $fehler) {
            $this->transaktionstiefe--;
            if ($this->transaktionstiefe === 0 && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $fehler;
        }

        $this->transaktionstiefe--;
        if ($this->transaktionstiefe === 0) {
            $this->pdo->commit();
        }

        return $ergebnis;
    }
}
