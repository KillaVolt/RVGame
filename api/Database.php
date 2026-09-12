<?php
declare(strict_types=1);

final class Database
{
    public static function settings(): array
    {
        $privateConfig = __DIR__ . '/.database.php';
        if (is_file($privateConfig)) {
            $settings = require $privateConfig;
            foreach (['host', 'port', 'name', 'user', 'password'] as $key) {
                if (!is_array($settings) || !isset($settings[$key]) || !is_string($settings[$key])) {
                    throw new RuntimeException('Private database configuration is invalid.');
                }
            }
            $port = filter_var($settings['port'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
            if ($port === false || strpbrk($settings['host'] . $settings['name'], ";\r\n") !== false) {
                throw new RuntimeException('Private database configuration contains invalid identifiers.');
            }
            return $settings;
        }

        $name = getenv('RVGAME_DB_NAME') ?: 'rv_game';
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            throw new RuntimeException('RVGAME_DB_NAME contains invalid characters.');
        }

        $webHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if (PHP_SAPI !== 'cli'
            && !str_starts_with($webHost, 'localhost')
            && !str_starts_with($webHost, '127.0.0.1')
            && getenv('RVGAME_DB_HOST') === false) {
            throw new RuntimeException('Private production database configuration is missing.');
        }

        return [
            'host' => getenv('RVGAME_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('RVGAME_DB_PORT') ?: '3306',
            'name' => $name,
            'user' => getenv('RVGAME_DB_USER') ?: 'root',
            'password' => getenv('RVGAME_DB_PASSWORD') ?: '',
        ];
    }

    public static function connect(bool $withDatabase = true): PDO
    {
        $settings = self::settings();
        $database = $withDatabase ? ';dbname=' . $settings['name'] : '';
        $dsn = 'mysql:host=' . $settings['host'] . ';port=' . $settings['port'] . $database . ';charset=utf8mb4';

        return new PDO($dsn, $settings['user'], $settings['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function channel(): string
    {
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        return preg_match('#/RVGame/OT(?:/|$)#i', $scriptName) === 1 ? 'ot' : 'main';
    }

    public static function sessionId(PDO $pdo): int
    {
        $channel = self::channel();
        $cookieName = $channel === 'ot' ? 'rvgame_ot_session' : 'rvgame_session';
        $cookiePath = $channel === 'ot' ? '/RVGame/OT' : '/RVGame';
        $token = $_COOKIE[$cookieName] ?? '';
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            $token = bin2hex(random_bytes(32));
            setcookie($cookieName, $token, [
                'expires' => time() + 31536000,
                'path' => $cookiePath,
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }

        $hash = hash('sha256', $token, true);
        $select = $pdo->prepare('SELECT id FROM rvgame_sessions WHERE channel = ? AND token_hash = ?');
        $select->execute([$channel, $hash]);
        $id = $select->fetchColumn();

        if ($id === false) {
            $insert = $pdo->prepare('INSERT INTO rvgame_sessions (channel, token_hash) VALUES (?, ?)');
            $insert->execute([$channel, $hash]);
            return (int) $pdo->lastInsertId();
        }

        $touch = $pdo->prepare('UPDATE rvgame_sessions SET last_seen_at = CURRENT_TIMESTAMP WHERE id = ?');
        $touch->execute([(int) $id]);
        return (int) $id;
    }
}
