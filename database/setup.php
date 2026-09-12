<?php
declare(strict_types=1);

require dirname(__DIR__) . '/api/Database.php';
require __DIR__ . '/SchemaInstaller.php';

try {
    $settings = Database::settings();
    $pdo = Database::connect(false);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . $settings['name'] . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE ' . $settings['name']);
    SchemaInstaller::install($pdo, __DIR__ . '/schema.sql');
    echo "PASS database ready: " . $settings['name'] . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL database setup: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
