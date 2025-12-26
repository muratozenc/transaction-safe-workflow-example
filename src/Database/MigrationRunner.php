<?php

declare(strict_types=1);

namespace App\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;

final class MigrationRunner
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly string $migrationsPath
    ) {
    }

    public function run(): void
    {
        $this->ensureMigrationsTable();
        $appliedMigrations = $this->getAppliedMigrations();
        $migrationFiles = $this->getMigrationFiles();

        foreach ($migrationFiles as $migrationFile) {
            $version = basename($migrationFile, '.sql');
            if (in_array($version, $appliedMigrations, true)) {
                continue;
            }

            $this->logger->info("Running migration: {$version}");
            $sql = file_get_contents($migrationFile);
            if ($sql === false) {
                throw new \RuntimeException("Failed to read migration file: {$migrationFile}");
            }

            $this->connection->executeStatement($sql);
            $this->connection->executeStatement(
                'INSERT INTO schema_migrations (version) VALUES (?)',
                [$version]
            );

            $this->logger->info("Migration {$version} applied successfully");
        }
    }

    private function ensureMigrationsTable(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(255) PRIMARY KEY,
                applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function getAppliedMigrations(): array
    {
        $result = $this->connection->fetchFirstColumn('SELECT version FROM schema_migrations ORDER BY version');
        return $result;
    }

    private function getMigrationFiles(): array
    {
        $files = glob($this->migrationsPath . '/*.sql');
        if ($files === false) {
            return [];
        }
        sort($files);
        return $files;
    }
}

