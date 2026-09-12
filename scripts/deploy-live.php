<?php
declare(strict_types=1);

const LOCAL_ROOT = 'E:/RVGame/Deploy';
const SOURCE_ROOT = 'E:/RVGame';
define('REMOTE_FOLDER', in_array('--final', $argv, true) ? 'RVGame' : 'RVGame/OT');
define('DEPLOY_CHANNEL', REMOTE_FOLDER === 'RVGame/OT' ? 'ot' : 'main');
require SOURCE_ROOT . '/database/SchemaInstaller.php';

function fail(string $message): never { fwrite(STDERR, "FAIL $message\n"); exit(1); }
function unquote(string $value): string {
    $value = trim($value);
    if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) return substr($value, 1, -1);
    return $value;
}
function secrets(): array {
    $out = [];
    foreach (file('E:/.secrets.env', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('/^\s*([A-Z0-9_]+)=(.*)$/', $line, $m)) $out[$m[1]] = unquote($m[2]);
    }
    foreach (['STARCORE_FTP_HOST', 'STARCORE_FTP_PORT', 'STARCORE_FTP_USERNAME', 'STARCORE_FTP_PASSWORD', 'STARCORE_FTP_ROOT'] as $key) {
        if (($out[$key] ?? '') === '') fail("missing $key");
    }
    return $out;
}
function tokenString(array $token): string {
    $raw = $token[1];
    $value = substr($raw, 1, -1);
    return $raw[0] === "'" ? str_replace(["\\\\", "\\'"], ["\\", "'"], $value) : stripcslashes($value);
}
function nextMeaningful(array $tokens, int $index): int {
    $count = count($tokens);
    while ($index < $count && is_array($tokens[$index]) && in_array($tokens[$index][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) $index++;
    return $index;
}
function databaseSettings(string $php): array {
    $tokens = token_get_all($php);
    $count = count($tokens);
    $start = null;
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_CONSTANT_ENCAPSED_STRING || strtolower(tokenString($tokens[$i])) !== 'database') continue;
        $arrow = nextMeaningful($tokens, $i + 1);
        $open = nextMeaningful($tokens, $arrow + 1);
        if (is_array($tokens[$arrow]) && $tokens[$arrow][0] === T_DOUBLE_ARROW && $tokens[$open] === '[') { $start = $open; break; }
    }
    if ($start === null) fail('live database configuration shape not recognized');
    $settings = [];
    $depth = 1;
    for ($i = $start + 1; $i < $count && $depth > 0; $i++) {
        if ($tokens[$i] === '[') { $depth++; continue; }
        if ($tokens[$i] === ']') { $depth--; continue; }
        if ($depth !== 1 || !is_array($tokens[$i]) || $tokens[$i][0] !== T_CONSTANT_ENCAPSED_STRING) continue;
        $key = strtolower(tokenString($tokens[$i]));
        if (!in_array($key, ['host', 'port', 'name', 'user', 'password'], true)) continue;
        $arrow = nextMeaningful($tokens, $i + 1);
        $value = nextMeaningful($tokens, $arrow + 1);
        if (!is_array($tokens[$arrow]) || $tokens[$arrow][0] !== T_DOUBLE_ARROW || !is_array($tokens[$value]) || $tokens[$value][0] !== T_CONSTANT_ENCAPSED_STRING) fail("live database field $key is not a string");
        $settings[$key] = tokenString($tokens[$value]);
    }
    foreach (['host', 'port', 'name', 'user', 'password'] as $key) if (!isset($settings[$key])) fail("live database field $key missing");
    $port = filter_var($settings['port'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    if ($port === false || strpbrk($settings['host'] . $settings['name'], ";\r\n") !== false) fail('live database identifiers invalid');
    return $settings;
}
function remoteFile(FTP\Connection $ftp, string $path): string {
    $stream = fopen('php://temp', 'w+b') ?: fail('temp stream failed');
    ftp_fget($ftp, $stream, $path, FTP_BINARY) || fail("cannot read remote $path");
    rewind($stream);
    return stream_get_contents($stream) ?: '';
}
function ensureDir(FTP\Connection $ftp, string $root, string $path): void {
    ftp_chdir($ftp, $root) || fail('cannot restore remote root');
    foreach (array_filter(explode('/', trim($path, '/')), 'strlen') as $part) {
        if (!@ftp_chdir($ftp, $part)) {
            @ftp_mkdir($ftp, $part) || fail("cannot create remote directory $part");
            ftp_chdir($ftp, $part) || fail("cannot enter remote directory $part");
        }
    }
}
function remoteHash(FTP\Connection $ftp, string $file): string {
    $stream = fopen('php://temp', 'w+b') ?: fail('hash stream failed');
    ftp_fget($ftp, $stream, $file, FTP_BINARY) || fail("cannot verify $file");
    rewind($stream);
    return hash('sha256', stream_get_contents($stream) ?: '');
}
function upload(FTP\Connection $ftp, string $root, string $relative, $stream, string $hash): void {
    $directory = dirname($relative) === '.' ? REMOTE_FOLDER : REMOTE_FOLDER . '/' . dirname($relative);
    ensureDir($ftp, $root, $directory);
    $name = basename($relative);
    $temporary = $name . '.uploading';
    rewind($stream);
    ftp_fput($ftp, $temporary, $stream, FTP_BINARY) || fail("upload failed $relative");
    remoteHash($ftp, $temporary) === $hash || fail("pre-activation hash mismatch $relative");
    @ftp_delete($ftp, $name);
    ftp_rename($ftp, $temporary, $name) || fail("activation failed $relative");
    remoteHash($ftp, $name) === $hash || fail("post-activation hash mismatch $relative");
    echo "OK $relative\n";
}

$s = secrets();
$root = rtrim(str_replace('\\', '/', $s['STARCORE_FTP_ROOT']), '/');
$ftp = ftp_ssl_connect($s['STARCORE_FTP_HOST'], (int) $s['STARCORE_FTP_PORT'], 30) ?: fail('FTPS connect failed');
ftp_login($ftp, $s['STARCORE_FTP_USERNAME'], $s['STARCORE_FTP_PASSWORD']) || fail('FTPS login failed');
ftp_pasv($ftp, true) || fail('passive mode failed');
ftp_chdir($ftp, $root) || fail('remote root unavailable');
$db = databaseSettings(remoteFile($ftp, 'config/config.php'));

$pdo = new PDO('mysql:host=' . $db['host'] . ';port=' . $db['port'] . ';dbname=' . $db['name'] . ';charset=utf8mb4', $db['user'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
SchemaInstaller::install($pdo, SOURCE_ROOT . '/database/schema.sql');
echo "OK database rvgame_* tables\n";

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(LOCAL_ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) if ($file->isFile()) $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen(LOCAL_ROOT) + 1));
sort($files);
foreach ($files as $relative) {
    $stream = fopen(LOCAL_ROOT . '/' . $relative, 'rb') ?: fail("cannot read local $relative");
    upload($ftp, $root, $relative, $stream, hash_file('sha256', LOCAL_ROOT . '/' . $relative));
}

$expectedAssets = array_map('basename', array_filter($files, fn(string $file): bool => str_starts_with($file, 'dist/assets/')));
ensureDir($ftp, $root, REMOTE_FOLDER . '/dist/assets');
foreach (ftp_nlist($ftp, '.') ?: [] as $entry) {
    $name = basename($entry);
    if (!in_array($name, ['.', '..'], true) && !in_array($name, $expectedAssets, true) && ftp_size($ftp, $name) >= 0) {
        ftp_delete($ftp, $name) || fail("cannot remove obsolete asset $name");
        echo "OK removed obsolete asset $name\n";
    }
}

$private = "<?php\ndeclare(strict_types=1);\nreturn " . var_export($db, true) . ";\n";
$stream = fopen('php://temp', 'w+b') ?: fail('private config stream failed');
fwrite($stream, $private);
upload($ftp, $root, 'api/.database.php', $stream, hash('sha256', $private));
ensureDir($ftp, $root, REMOTE_FOLDER . '/api');
$mode = @ftp_chmod($ftp, 0600, '.database.php');
echo $mode === false ? "WARN private config chmod unsupported; Apache deny remains active\n" : "OK private config mode 0600\n";

if (in_array('--reset-saves', $argv, true)) {
    $pdo->beginTransaction();
    try {
        $reset = $pdo->prepare('DELETE FROM rvgame_sessions WHERE channel = ?');
        $reset->execute([DEPLOY_CHANNEL]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        fail('RVGame playtest reset failed');
    }
    echo 'OK reset ' . DEPLOY_CHANNEL . " playtest saves\n";
} else {
    echo 'OK preserved ' . DEPLOY_CHANNEL . " playtest saves\n";
}
@ftp_close($ftp);
echo 'DEPLOYED ' . count($files) . ' public runtime files plus protected database config to /' . REMOTE_FOLDER . "/\n";
