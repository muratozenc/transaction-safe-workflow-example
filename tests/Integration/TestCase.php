<?php

declare(strict_types=1);

namespace Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Predis\Client as RedisClient;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected Connection $connection;
    protected RedisClient $redis;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup test database connection
        $this->connection = DriverManager::getConnection([
            'dbname' => $_ENV['DB_NAME'] ?? 'order_workflow_test',
            'user' => $_ENV['DB_USER'] ?? 'app_user',
            'password' => $_ENV['DB_PASSWORD'] ?? 'app_password',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
            'driver' => 'pdo_mysql',
        ]);

        // Setup Redis
        $this->redis = new RedisClient([
            'host' => $_ENV['REDIS_HOST'] ?? 'localhost',
            'port' => (int) ($_ENV['REDIS_PORT'] ?? 6379),
        ]);

        $this->cleanDatabase();
        $this->cleanRedis();
        $this->runMigrations();
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        $this->cleanRedis();
        parent::tearDown();
    }

    private function cleanDatabase(): void
    {
        $tables = ['order_notifications', 'outbox_events', 'orders', 'schema_migrations'];
        foreach ($tables as $table) {
            try {
                $this->connection->executeStatement("TRUNCATE TABLE {$table}");
            } catch (\Exception $e) {
                // Table might not exist yet
            }
        }
    }

    private function cleanRedis(): void
    {
        try {
            $this->redis->flushdb();
        } catch (\Exception $e) {
            // Redis might not be available
        }
    }

    private function runMigrations(): void
    {
        $migrationsPath = __DIR__ . '/../../migrations';
        $migrationFiles = glob($migrationsPath . '/*.sql');
        if ($migrationFiles === false) {
            return;
        }
        sort($migrationFiles);

        foreach ($migrationFiles as $migrationFile) {
            $sql = file_get_contents($migrationFile);
            if ($sql === false) {
                continue;
            }
            try {
                $this->connection->executeStatement($sql);
            } catch (\Exception $e) {
                // Migration might already be applied
            }
        }
    }
}

