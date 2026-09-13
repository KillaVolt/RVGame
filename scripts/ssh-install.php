<?php
declare(strict_types=1);

function deployFail(string $message): never {
    fwrite(STDERR, "FAIL $message\n");
    exit(1);
}

function removeTree(string $path): void {
    if (!file_exists($path)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

function copyTree(string $source, string $target, bool $skipDist = false): void {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $item) {
        if (!$item->isFile()) continue;
        $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($source) + 1));
        if ($skipDist && ($relative === 'dist' || str_starts_with($relative, 'dist/'))) continue;
        $destination = $target . '/' . $relative;
        is_dir(dirname($destination)) || mkdir(dirname($destination), 0755, true) || deployFail("cannot create " . dirname($destination));
        copy($item->getPathname(), $destination) || deployFail("cannot copy $relative");
    }
}

if (PHP_SAPI !== 'cli' || count($argv) !== 5) deployFail('invalid invocation');
[$script, $remoteRootArg, $targetArg, $channel, $reset] = $argv;
$remoteRoot = realpath($remoteRootArg) ?: deployFail('remote root missing');
$target = rtrim($targetArg, '/');
$allowed = [$remoteRoot . '/RVGame', $remoteRoot . '/RVGame/OT'];
if (!in_array($target, $allowed, true)) deployFail('unsafe deployment target');
if (!in_array($channel, ['main', 'ot'], true) || !in_array($reset, ['0', '1'], true)) deployFail('invalid deployment options');

$package = dirname(__DIR__);
$public = $package . '/public';
$private = $package . '/private';
if (!is_dir($public . '/dist') || !is_file($private . '/owner.access.php')) deployFail('release incomplete');

defined('ROOT_PATH') || define('ROOT_PATH', $remoteRoot);
$config = require $remoteRoot . '/config/config.php';
$database = is_array($config) ? ($config['database'] ?? null) : null;
if (!is_array($database)) deployFail('database configuration missing');
foreach (['host', 'port', 'name', 'user', 'password'] as $field) {
    if (!isset($database[$field]) || (!is_int($database[$field]) && !is_string($database[$field]))) deployFail("database field $field missing");
}

require $private . '/SchemaInstaller.php';
$pdo = new PDO(
    'mysql:host=' . $database['host'] . ';port=' . $database['port'] . ';dbname=' . $database['name'] . ';charset=utf8mb4',
    (string) $database['user'],
    (string) $database['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
SchemaInstaller::install($pdo, $private . '/schema.sql');

is_dir($target) || mkdir($target, 0755, true) || deployFail('cannot create deployment target');
copyTree($public, $target, true);

$databaseFile = "<?php\ndeclare(strict_types=1);\nreturn " . var_export($database, true) . ";\n";
file_put_contents($target . '/api/.database.php', $databaseFile, LOCK_EX) !== false || deployFail('cannot write database config');
copy($private . '/owner.access.php', $target . '/owner/.access.php') || deployFail('cannot write owner access');
chmod($target . '/api/.database.php', 0600);
chmod($target . '/owner/.access.php', 0600);

$nextDist = $target . '/.dist-next';
$oldDist = $target . '/.dist-old';
removeTree($nextDist);
removeTree($oldDist);
copyTree($public . '/dist', $nextDist);
if (is_dir($target . '/dist') && !rename($target . '/dist', $oldDist)) deployFail('cannot preserve current dist');
if (!rename($nextDist, $target . '/dist')) {
    is_dir($oldDist) && rename($oldDist, $target . '/dist');
    deployFail('cannot activate new dist');
}
removeTree($oldDist);

if ($reset === '1') {
    $delete = $pdo->prepare('DELETE FROM rvgame_sessions WHERE channel = ?');
    $delete->execute([$channel]);
    echo "OK reset $channel playtest saves\n";
} else {
    echo "OK preserved $channel playtest saves\n";
}
echo "OK database and protected configuration\n";
echo "OK activated verified public release\n";
