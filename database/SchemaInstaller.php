<?php
declare(strict_types=1);

final class SchemaInstaller
{
    public static function install(PDO $pdo, string $schemaPath): void
    {
        $schema = file_get_contents($schemaPath);
        if ($schema === false) {
            throw new RuntimeException('Database schema could not be read.');
        }

        foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
            $pdo->exec($statement);
        }

        $channel = $pdo->query("SHOW COLUMNS FROM rvgame_sessions LIKE 'channel'")->fetchColumn();
        if ($channel === false) {
            $pdo->exec("ALTER TABLE rvgame_sessions ADD channel VARCHAR(10) NOT NULL DEFAULT 'main' AFTER token_hash");
        }
        if ($pdo->query("SHOW COLUMNS FROM rvgame_sessions LIKE 'analytics_excluded'")->fetchColumn() === false) {
            $pdo->exec('ALTER TABLE rvgame_sessions ADD analytics_excluded TINYINT(1) NOT NULL DEFAULT 0 AFTER channel');
        }
    }
}
