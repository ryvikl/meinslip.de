<?php

declare(strict_types=1);

namespace MeinSlip\Tests;

use MeinSlip\Core\Database;
use MeinSlip\Core\Ddl;
use MeinSlip\Core\Migrator;
use PHPUnit\Framework\TestCase;

/**
 * Migrationen muessen beliebig oft aufrufbar sein.
 *
 * Jedes Deployment ruft `php bin/migrate` auf. Ist der Aufruf nicht folgenlos,
 * wenn es nichts zu tun gibt, schlaegt ab dem zweiten Deployment jedes fehl.
 *
 * Genau das ist passiert: Die Anlage der Migrationstabelle laeuft bei jedem
 * Aufruf, und ihr CREATE INDEX kannte auf MySQL kein IF NOT EXISTS. Das erste
 * Deployment lief durch, das zweite brach mit "1061 Duplicate key name" ab.
 *
 * Dieser Test allein haette den Fehler NICHT gefunden — er laeuft gegen
 * SQLite, wo IF NOT EXISTS unterstuetzt wird. Deshalb gibt es zusaetzlich den
 * Job "Migrationen gegen echtes MySQL" in .github/workflows/pruefen.yml. Was
 * hier steht, sichert die Absicht; was dort laeuft, sichert den Treiber.
 */
final class MigratorTest extends TestCase
{
    private const VERZEICHNIS = __DIR__ . '/../database/migrations';

    public function testZweiterLaufIstFolgenlos(): void
    {
        $db = Database::imArbeitsspeicher();
        $migrator = new Migrator($db, self::VERZEICHNIS);

        $erster = $migrator->hoch();
        self::assertNotEmpty($erster, 'Der erste Lauf haette Migrationen ausfuehren muessen.');

        $zweiter = $migrator->hoch();
        self::assertSame([], $zweiter, 'Der zweite Lauf darf nichts mehr ausfuehren.');
    }

    public function testAuchEinNeuerMigratorAufDerselbenDatenbankLaeuftDurch(): void
    {
        // Das ist der Fall aus dem Betrieb: Jedes Deployment startet einen
        // neuen Prozess gegen dieselbe, bereits migrierte Datenbank.
        $db = Database::imArbeitsspeicher();

        (new Migrator($db, self::VERZEICHNIS))->hoch();

        self::assertSame([], (new Migrator($db, self::VERZEICHNIS))->hoch());
    }

    public function testDieAnlageDerMigrationstabelleFragtVorherNachDemIndex(): void
    {
        $db = Database::imArbeitsspeicher();
        $ddl = new Ddl($db);
        $name = $ddl->indexName('migrationen', ['bezeichnung']);

        self::assertFalse(
            $ddl->indexVorhanden('migrationen', $name),
            'Vor der ersten Migration darf es den Index nicht geben.'
        );

        (new Migrator($db, self::VERZEICHNIS))->hoch();

        self::assertTrue(
            $ddl->indexVorhanden('migrationen', $name),
            'Nach der ersten Migration muss der Index bestehen — sonst greift die '
            . 'Abfrage ins Leere und CREATE INDEX laeuft doch jedes Mal.'
        );
    }

    public function testIndexAnweisungTraegtNurAufSqliteEinIfNotExists(): void
    {
        // Der Unterschied, der den Fehler ausgeloest hat, festgehalten: Auf
        // MySQL gibt es die Absicherung in der Anweisung nicht, deshalb MUSS
        // der Aufrufer vorher fragen.
        $db = Database::imArbeitsspeicher();
        $anweisung = (new Ddl($db))->index('migrationen', ['bezeichnung'], true);

        self::assertTrue($db->istSqlite());
        self::assertStringContainsString('IF NOT EXISTS', $anweisung);
        self::assertStringContainsString('UNIQUE INDEX', $anweisung);
    }
}
