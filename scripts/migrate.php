<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$container = \App\Application\ContainerFactory::create();
$runner = $container->get(\App\Database\MigrationRunner::class);

try {
    $runner->run();
    echo "Migrations completed successfully.\n";
} catch (\Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

