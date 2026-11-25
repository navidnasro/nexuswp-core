<?php

namespace nexuswp\core\database\migration;

use nexuswp\core\Config;
use Throwable;
use wpdb;

class MigrationManager
{
    private wpdb $wpdb;
    private string $migrationsTable;

    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->migrationsTable = $wpdb->prefix.Config::tablePrefix().'migrations';
    }

    /**
     * the ledger table must exist before any migration can be tracked,
     * otherwise the first run fails with a database error
     */
    private function ensureMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->migrationsTable}` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(255) NOT NULL,
            `batch` INT NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `migration` (`migration`)
        ) {$this->wpdb->get_charset_collate()};";

        $this->wpdb->query($sql);
    }

    private function getExecutedMigrations(): array
    {
        $migrations = $this->wpdb->get_col("SELECT migration FROM {$this->migrationsTable}");

        return is_array($migrations) ? $migrations : [];
    }

    private function getNextBatchNumber(): int|string
    {
        $batch = $this->wpdb->get_var("SELECT MAX(batch) FROM {$this->migrationsTable}");
        return $batch ? $batch + 1 : 1;
    }

    public function runMigrations(): void
    {
        $this->ensureMigrationsTable();

        $executedMigrations = $this->getExecutedMigrations();
        $batch = $this->getNextBatchNumber();

        $migrationFiles = glob(Config::pluginRoot() . '/database/migrations/*.php');
        sort($migrationFiles); // Ensure execution order

        foreach ($migrationFiles as $file)
        {
            $migrationName = basename($file, ".php");

            if (in_array($migrationName, $executedMigrations, true))
                continue;

            $migrationInstance = require $file; // Get the returned class

            // must be of type Migration
            if (!$migrationInstance instanceof Migration)
                continue;

            try
            {
                $migrationInstance->up();
            }
            catch (Throwable $exception)
            {
                // stop on the first failure so later migrations never run on a broken schema
                error_log('NexusWP migration failed: '.$migrationName.' - '.$exception->getMessage());
                break;
            }

            $this->wpdb->insert($this->migrationsTable,
                [
                    'migration' => $migrationName,
                    'batch' => $batch,
                    'created_at' => current_time('mysql')
                ]
            );
        }
    }

    public function rollbackLastBatch(): void
    {
        $this->ensureMigrationsTable();

        $lastBatch = $this->wpdb->get_var("SELECT MAX(batch) FROM {$this->migrationsTable}");
        if (!$lastBatch) return;

        $migrationsToRollback = $this->wpdb->get_col(
            $this->wpdb->prepare("SELECT migration FROM {$this->migrationsTable} WHERE batch = %d", $lastBatch)
        );

        foreach (array_reverse($migrationsToRollback) as $migrationName)
            $this->rollbackMigration($migrationName);
    }

    public function rollbackAllMigrations(): void
    {
        $this->ensureMigrationsTable();

        $migrationsToRollback = $this->wpdb->get_col("SELECT migration FROM {$this->migrationsTable}");

        foreach (array_reverse($migrationsToRollback) as $migrationName)
        {
            // never drop the ledger itself
            if (str_contains($migrationName,'create_migrations_table'))
                continue;

            $this->rollbackMigration($migrationName);
        }
    }

    private function rollbackMigration(string $migrationName): void
    {
        $file = Config::pluginRoot() . '/database/migrations/'.$migrationName.'.php';

        if (!file_exists($file))
            return;

        $migrationInstance = require $file;

        // must be of type Migration
        if (!$migrationInstance instanceof Migration)
            return;

        try
        {
            $migrationInstance->down();
        }
        catch (Throwable $exception)
        {
            error_log('NexusWP rollback failed: '.$migrationName.' - '.$exception->getMessage());
            return;
        }

        $this->wpdb->delete($this->migrationsTable, ['migration' => $migrationName]);
    }
}
