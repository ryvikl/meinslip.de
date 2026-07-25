<?php

declare(strict_types=1);

namespace MeinSlip\Core;

/**
 * Fuehrt Migrationen aus database/migrations aus.
 *
 * Jede Migration ist eine PHP-Datei, die ein Objekt mit den Methoden
 * bezeichnung() und hoch(Database, Ddl) zurueckgibt. Ausgefuehrte Migrationen
 * werden in der Tabelle migrationen vermerkt und nie erneut ausgefuehrt.
 */
final class Migrator
{
    public function __construct(
        private readonly Database $db,
        private readonly string $verzeichnis
    ) {
    }

    /** @return list<string> Bezeichnungen der ausgefuehrten Migrationen */
    public function hoch(): array
    {
        $this->tabelleAnlegen();

        $bereitsAusgefuehrt = $this->bereitsAusgefuehrt();
        $ausgefuehrt = [];
        $ddl = new Ddl($this->db);

        foreach ($this->dateien() as $datei) {
            $name = basename($datei, '.php');
            if (in_array($name, $bereitsAusgefuehrt, true)) {
                continue;
            }

            $migration = require $datei;

            if (!is_object($migration) || !method_exists($migration, 'hoch')) {
                throw new \RuntimeException("Migration {$name} liefert kein gueltiges Objekt.");
            }

            // Kein Transaktionsklammer um DDL: MySQL committet DDL ohnehin
            // implizit, eine Transaktion wuerde hier falsche Sicherheit vorspiegeln.
            $migration->hoch($this->db, $ddl);

            $this->db->einfuegen('migrationen', [
                'bezeichnung' => $name,
                'ausgefuehrt_am' => gmdate('Y-m-d H:i:s'),
            ]);

            $ausgefuehrt[] = $name;
        }

        return $ausgefuehrt;
    }

    /** @return list<string> */
    private function dateien(): array
    {
        $muster = rtrim($this->verzeichnis, '/') . '/*.php';
        $dateien = glob($muster) ?: [];
        sort($dateien, SORT_STRING);

        return array_values($dateien);
    }

    /** @return list<string> */
    private function bereitsAusgefuehrt(): array
    {
        $zeilen = $this->db->alle('SELECT bezeichnung FROM migrationen');

        return array_map(static fn (array $z): string => (string) $z['bezeichnung'], $zeilen);
    }

    private function tabelleAnlegen(): void
    {
        $ddl = new Ddl($this->db);

        $this->db->ddl($ddl->tabelle('migrationen', [
            $ddl->id(),
            $ddl->text('bezeichnung'),
            $ddl->zeitpunkt('ausgefuehrt_am', false),
        ]));

        // Diese Methode laeuft bei JEDEM Aufruf, nicht einmalig als Migration.
        // CREATE TABLE IF NOT EXISTS ist damit unkritisch, CREATE INDEX auf
        // MySQL nicht: Dort gibt es kein IF NOT EXISTS, und der zweite Lauf
        // scheiterte mit "1061 Duplicate key name". Das erste Deployment lief
        // deshalb durch und jedes weitere nicht.
        if (!$ddl->indexVorhanden('migrationen', $ddl->indexName('migrationen', ['bezeichnung']))) {
            $this->db->ddl($ddl->index('migrationen', ['bezeichnung'], true));
        }
    }
}
