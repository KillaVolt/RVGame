<?php
declare(strict_types=1);

require dirname(__DIR__) . '/api/Database.php';

try {
    $settings = Database::settings();
    $pdo = Database::connect(false);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . $settings['name'] . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE ' . $settings['name']);
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('database/schema.sql could not be read.');
    }
    foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
        $pdo->exec($statement);
    }
    echo "PASS database ready: " . $settings['name'] . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL database setup: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}

