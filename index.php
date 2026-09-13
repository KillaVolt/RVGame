<?php
declare(strict_types=1);

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$isOwnerTest = str_contains($scriptName, '/RVGame/OT/');
$basePath = $isOwnerTest ? '/RVGame/OT/' : '/RVGame/';
$sessionCookie = $isOwnerTest ? 'rvgame_ot_session' : 'rvgame_session';
$visitCookie = $isOwnerTest ? 'rvgame_ot_visit' : 'rvgame_visit';
$secureCookie = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$isPreviewCrawler = preg_match('/facebookexternalhit|Facebot|Twitterbot|LinkedInBot|Slackbot|Discordbot|WhatsApp/i', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')) === 1;
$isFreshEntry = ($_GET['new'] ?? '') === '1' || isset($_GET['fbclid']);

if ($isFreshEntry && !$isPreviewCrawler) {
    foreach ([$sessionCookie, $visitCookie] as $cookieName) {
        setcookie($cookieName, '', [
            'expires' => 1,
            'path' => rtrim($basePath, '/'),
            'secure' => $secureCookie,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
    header('Cache-Control: no-store');
    header('Location: ' . $basePath, true, 303);
    exit;
}

$built = __DIR__ . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'index.html';
if (!is_file($built)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "RVGame has not been built. Run npm install and npm run build in this folder.";
    exit;
}

require __DIR__ . '/api/Database.php';
require __DIR__ . '/api/Analytics.php';

try {
    $pdo = Database::connect();
    $sessionId = Database::sessionId($pdo);
    Analytics::recordPageView($pdo, $sessionId, Database::channel());
} catch (Throwable $error) {
    error_log('RVGame page-view initialization failed: ' . $error->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'RVGame could not connect to its required game service.';
    exit;
}

$html = file_get_contents($built);
if ($html === false) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "RVGame's built entry point could not be read.";
    exit;
}

$html = preg_replace('/<head>/', '<head><base href="dist/">', $html, 1, $replacements);
if ($html === null || $replacements !== 1) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "RVGame's built entry point is invalid.";
    exit;
}

header('Cache-Control: no-store');
header('Content-Type: text/html; charset=utf-8');
echo $html;
