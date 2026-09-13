<?php
declare(strict_types=1);

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header("Content-Security-Policy: default-src 'self'; style-src 'unsafe-inline'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$ownerPath = str_contains($scriptName, '/RVGame/OT/') ? '/RVGame/OT/owner/' : '/RVGame/owner/';
session_name(str_contains($scriptName, '/RVGame/OT/') ? 'rvgame_ot_owner' : 'rvgame_owner');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => rtrim($ownerPath, '/'),
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

$configFile = __DIR__ . '/.access.php';
if (!is_file($configFile)) {
    http_response_code(503);
    exit('Owner access is not configured.');
}
$access = require $configFile;
if (!is_array($access)
    || !is_string($access['username'] ?? null)
    || !is_string($access['passwordHash'] ?? null)) {
    http_response_code(503);
    exit('Owner access configuration is invalid.');
}

$error = '';
if (($_POST['action'] ?? '') === 'login') {
    $username = (string) ($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    if (hash_equals($access['username'], $username) && password_verify($password, $access['passwordHash'])) {
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['lastActivity'] = time();
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
        header('Location: ' . $ownerPath, true, 303);
        exit;
    }
    usleep(600000);
    $error = 'The owner credentials were not accepted.';
}

$authenticated = ($_SESSION['authenticated'] ?? false) === true
    && is_int($_SESSION['lastActivity'] ?? null)
    && (time() - $_SESSION['lastActivity']) < 28800;

if ($authenticated && ($_POST['action'] ?? '') === 'logout') {
    $csrf = (string) ($_POST['csrf'] ?? '');
    if (!is_string($_SESSION['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $csrf)) {
        http_response_code(400);
        exit('Invalid logout request.');
    }
    $_SESSION = [];
    session_destroy();
    header('Location: ' . $ownerPath, true, 303);
    exit;
}

if (!$authenticated) {
    $_SESSION = [];
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>RVGame Owner Login</title>
  <style>
    :root{color-scheme:light;--pine:#17382c;--paper:#f7efd9;--amber:#e4a62a;--rust:#a84d32;--ink:#17251f}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:repeating-linear-gradient(135deg,#dce7da 0 24px,#d4dfd1 24px 48px);color:var(--ink);font-family:"Arial Narrow",sans-serif}.card{width:min(92vw,430px);padding:28px;background:var(--paper);border:1px solid #8f8065;box-shadow:10px 10px 0 rgb(23 56 44 / 20%)}h1{margin:0 0 6px;font-family:Rockwell,serif;color:var(--pine)}p{line-height:1.45}.error{padding:10px;border-left:5px solid var(--rust);background:#f4d8cf}label{display:block;margin:14px 0 5px;font-weight:800}input{width:100%;padding:11px;border:1px solid #766a55;background:#fffdf6;font:inherit}button{width:100%;margin-top:18px;padding:12px;border:0;background:var(--pine);color:white;font-weight:900;cursor:pointer}button:hover,button:focus-visible{background:#285845;outline:3px solid var(--amber)}</style>
</head>
<body><main class="card"><h1>RVGame Owner</h1><p>Private analytics dashboard.</p>
<?php if ($error !== ''): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="action" value="login"><label for="username">Owner username</label><input id="username" name="username" autocomplete="username" required autofocus><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required><button type="submit">Open dashboard</button></form></main></body></html>
<?php
    exit;
}

$_SESSION['lastActivity'] = time();
$csrf = (string) $_SESSION['csrf'];
require dirname(__DIR__) . '/api/Database.php';

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function allRows(PDO $pdo, string $sql, array $parameters = []): array
{
    $query = $pdo->prepare($sql);
    $query->execute($parameters);
    return $query->fetchAll();
}
function oneRow(PDO $pdo, string $sql, array $parameters = []): array
{
    $query = $pdo->prepare($sql);
    $query->execute($parameters);
    return $query->fetch() ?: [];
}
function durationLabel(int $seconds): string
{
    if ($seconds < 60) return $seconds . ' sec';
    if ($seconds < 3600) return intdiv($seconds, 60) . 'm ' . ($seconds % 60) . 's';
    return intdiv($seconds, 3600) . 'h ' . intdiv($seconds % 3600, 60) . 'm';
}
function barChart(array $rows, string $empty = 'No data yet.'): string
{
    if ($rows === []) return '<p class="empty">' . h($empty) . '</p>';
    $maximum = max(array_map(static fn(array $row): int => (int) $row['value'], $rows));
    $output = '<div class="bars">';
    foreach ($rows as $row) {
        $value = (int) $row['value'];
        $width = $maximum > 0 ? max(2, (int) round(($value / $maximum) * 100)) : 0;
        $output .= '<div class="bar-row"><div class="bar-label"><span>' . h((string) $row['label']) . '</span><b>' . number_format($value) . '</b></div><div class="bar-track"><i style="width:' . $width . '%"></i></div></div>';
    }
    return $output . '</div>';
}
function trendChart(array $rows): string
{
    if ($rows === []) return '<p class="empty">No traffic data yet.</p>';
    $width = 900;
    $height = 250;
    $left = 42;
    $top = 20;
    $plotWidth = 830;
    $plotHeight = 180;
    $maximum = max(1, ...array_map(static fn(array $row): int => max((int) $row['views'], (int) $row['visitors'], (int) $row['starts']), $rows));
    $count = count($rows);
    $series = ['views' => '#a84d32', 'visitors' => '#17382c', 'starts' => '#d69012'];
    $polylines = '';
    foreach ($series as $key => $color) {
        $points = [];
        foreach ($rows as $index => $row) {
            $x = $left + ($count === 1 ? $plotWidth / 2 : ($index * $plotWidth / ($count - 1)));
            $y = $top + $plotHeight - (((int) $row[$key] / $maximum) * $plotHeight);
            $points[] = round($x, 1) . ',' . round($y, 1);
        }
        $polylines .= '<polyline points="' . implode(' ', $points) . '" fill="none" stroke="' . $color . '" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>';
    }
    $first = h((string) $rows[0]['day']);
    $last = h((string) $rows[$count - 1]['day']);
    return '<svg class="trend" viewBox="0 0 900 250" role="img" aria-label="Daily page views, visitors, and campaign starts"><line x1="42" y1="200" x2="872" y2="200" stroke="#b4a78d"/><line x1="42" y1="20" x2="42" y2="200" stroke="#b4a78d"/>' . $polylines . '<text x="42" y="225">' . $first . '</text><text x="872" y="225" text-anchor="end">' . $last . '</text><text x="8" y="27">' . $maximum . '</text></svg><div class="legend"><span class="views">Page views</span><span class="visitors">Visitors</span><span class="starts">Campaign starts</span></div>';
}

try {
    $pdo = Database::connect();
    Database::excludeOwnerBrowser($pdo);
    $channel = Database::channel();
    $days = filter_input(INPUT_GET, 'days', FILTER_VALIDATE_INT);
    $days = in_array($days, [7, 30, 90, 365], true) ? $days : 30;
    $since = (new DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days')->format('Y-m-d 00:00:00');

    $traffic = oneRow($pdo,
        'SELECT COUNT(*) AS visits, COALESCE(SUM(page_views), 0) AS views,
                COUNT(DISTINCT session_id) AS visitors,
                COALESCE(AVG(TIMESTAMPDIFF(SECOND, started_at, last_activity_at)), 0) AS avg_seconds,
                COALESCE(100 * SUM(CASE WHEN page_views = 1 AND event_count = 0 THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0), 0) AS bounce_rate
         FROM rvgame_analytics_visits WHERE session_id IN (SELECT id FROM rvgame_sessions WHERE analytics_excluded = 0) AND channel = ? AND is_bot = 0 AND started_at >= ?',
        [$channel, $since]
    );
    $activeNow = oneRow($pdo,
        'SELECT COUNT(DISTINCT session_id) AS total FROM rvgame_analytics_visits
         WHERE session_id IN (SELECT id FROM rvgame_sessions WHERE analytics_excluded = 0) AND channel = ? AND is_bot = 0 AND last_activity_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE)',
        [$channel]
    );
    $campaigns = oneRow($pdo,
        'SELECT COUNT(*) AS total FROM rvgame_campaigns c INNER JOIN rvgame_sessions s ON s.id = c.session_id
         WHERE s.analytics_excluded = 0 AND s.channel = ? AND c.created_at >= ?',
        [$channel, $since]
    );
    $ratings = oneRow($pdo,
        'SELECT COUNT(*) AS total, ROUND(AVG(r.rating), 1) AS average FROM rvgame_ratings r
         INNER JOIN rvgame_sessions s ON s.id = r.session_id WHERE s.analytics_excluded = 0 AND s.channel = ?',
        [$channel]
    );
    $feedbackCount = oneRow($pdo,
        'SELECT COUNT(*) AS total FROM rvgame_feedback f INNER JOIN rvgame_sessions s ON s.id = f.session_id
         WHERE s.analytics_excluded = 0 AND s.channel = ? AND f.created_at >= ?',
        [$channel, $since]
    );

    $daily = [];
    for ($offset = 0; $offset < $days; $offset++) {
        $day = (new DateTimeImmutable($since))->modify('+' . $offset . ' days')->format('Y-m-d');
        $daily[$day] = ['day' => $day, 'views' => 0, 'visitors' => 0, 'starts' => 0];
    }
    foreach (allRows($pdo,
        'SELECT DATE(started_at) AS day, SUM(page_views) AS views, COUNT(DISTINCT session_id) AS visitors
         FROM rvgame_analytics_visits WHERE session_id IN (SELECT id FROM rvgame_sessions WHERE analytics_excluded = 0) AND channel = ? AND is_bot = 0 AND started_at >= ? GROUP BY DATE(started_at)',
        [$channel, $since]
    ) as $row) {
        if (isset($daily[$row['day']])) {
            $daily[$row['day']]['views'] = (int) $row['views'];
            $daily[$row['day']]['visitors'] = (int) $row['visitors'];
        }
    }
    foreach (allRows($pdo,
        'SELECT DATE(c.created_at) AS day, COUNT(*) AS starts FROM rvgame_campaigns c
         INNER JOIN rvgame_sessions s ON s.id = c.session_id
         WHERE s.analytics_excluded = 0 AND s.channel = ? AND c.created_at >= ? GROUP BY DATE(c.created_at)',
        [$channel, $since]
    ) as $row) {
        if (isset($daily[$row['day']])) $daily[$row['day']]['starts'] = (int) $row['starts'];
    }
    $daily = array_values($daily);

    $sources = allRows($pdo,
        'SELECT source AS label, COUNT(*) AS value FROM rvgame_analytics_visits
         WHERE session_id IN (SELECT id FROM rvgame_sessions WHERE analytics_excluded = 0) AND channel = ? AND is_bot = 0 AND started_at >= ? GROUP BY source ORDER BY value DESC LIMIT 12',
        [$channel, $since]
    );
    $devices = allRows($pdo,
        'SELECT device AS label, COUNT(*) AS value FROM rvgame_analytics_visits
         WHERE session_id IN (SELECT id FROM rvgame_sessions WHERE analytics_excluded = 0) AND channel = ? AND is_bot = 0 AND started_at >= ? GROUP BY device ORDER BY value DESC',
        [$channel, $since]
    );
    $browsers = allRows($pdo,
        'SELECT browser AS label, COUNT(*) AS value FROM rvgame_analytics_visits
         WHERE session_id IN (SELECT id FROM rvgame_sessions WHERE analytics_excluded = 0) AND channel = ? AND is_bot = 0 AND started_at >= ? GROUP BY browser ORDER BY value DESC LIMIT 10',
        [$channel, $since]
    );
    $systems = allRows($pdo,
        'SELECT operating_system AS label, COUNT(*) AS value FROM rvgame_analytics_visits
         WHERE session_id IN (SELECT id FROM rvgame_sessions WHERE analytics_excluded = 0) AND channel = ? AND is_bot = 0 AND started_at >= ? GROUP BY operating_system ORDER BY value DESC LIMIT 10',
        [$channel, $since]
    );
    $languages = allRows($pdo,
        'SELECT language AS label, COUNT(*) AS value FROM rvgame_analytics_visits
         WHERE session_id IN (SELECT id FROM rvgame_sessions WHERE analytics_excluded = 0) AND channel = ? AND is_bot = 0 AND started_at >= ? GROUP BY language ORDER BY value DESC LIMIT 10',
        [$channel, $since]
    );
    $actions = allRows($pdo,
        'SELECT REPLACE(event_name, "_", " ") AS label, COUNT(*) AS value FROM rvgame_analytics_events
         WHERE session_id IN (SELECT id FROM rvgame_sessions WHERE analytics_excluded = 0) AND channel = ? AND created_at >= ? GROUP BY event_name ORDER BY value DESC LIMIT 15',
        [$channel, $since]
    );
    $hours = array_map(static fn(array $row): array => ['label' => str_pad((string) $row['hour'], 2, '0', STR_PAD_LEFT) . ':00', 'value' => (int) $row['value']], allRows($pdo,
        'SELECT HOUR(started_at) AS hour, COUNT(*) AS value FROM rvgame_analytics_visits
         WHERE session_id IN (SELECT id FROM rvgame_sessions WHERE analytics_excluded = 0) AND channel = ? AND is_bot = 0 AND started_at >= ? GROUP BY HOUR(started_at) ORDER BY hour',
        [$channel, $since]
    ));
    $ratingBreakdown = allRows($pdo,
        'SELECT CONCAT(r.rating, " stars") AS label, COUNT(*) AS value FROM rvgame_ratings r
         INNER JOIN rvgame_sessions s ON s.id = r.session_id WHERE s.analytics_excluded = 0 AND s.channel = ? GROUP BY r.rating ORDER BY r.rating DESC',
        [$channel]
    );
    $feedback = allRows($pdo,
        'SELECT f.category, f.message, f.created_at FROM rvgame_feedback f
         INNER JOIN rvgame_sessions s ON s.id = f.session_id WHERE s.analytics_excluded = 0 AND s.channel = ? ORDER BY f.id DESC LIMIT 30',
        [$channel]
    );
    $funnelEvents = allRows($pdo,
        'SELECT event_name, COUNT(DISTINCT session_id) AS value FROM rvgame_analytics_events
         WHERE session_id IN (SELECT id FROM rvgame_sessions WHERE analytics_excluded = 0) AND channel = ? AND created_at >= ? AND event_name IN ("campaign_start", "command_travel", "command_sell", "command_complete")
         GROUP BY event_name',
        [$channel, $since]
    );
    $funnelMap = array_column($funnelEvents, 'value', 'event_name');
    $funnel = [
        ['label' => 'Visitors', 'value' => (int) ($traffic['visitors'] ?? 0)],
        ['label' => 'Campaign starts', 'value' => (int) ($funnelMap['campaign_start'] ?? 0)],
        ['label' => 'Travelers', 'value' => (int) ($funnelMap['command_travel'] ?? 0)],
        ['label' => 'Sellers', 'value' => (int) ($funnelMap['command_sell'] ?? 0)],
        ['label' => 'Completed', 'value' => (int) ($funnelMap['command_complete'] ?? 0)],
    ];
} catch (Throwable $exception) {
    error_log('RVGame owner dashboard failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Analytics could not be loaded.');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>RVGame Owner Dashboard</title>
  <style>
    :root{--ink:#17251f;--pine:#17382c;--green:#2f684f;--rust:#a84d32;--amber:#e4a62a;--paper:#fff9e9;--sand:#eadfc6;--line:#b3a488;--muted:#647269}*{box-sizing:border-box}body{margin:0;color:var(--ink);background:repeating-linear-gradient(135deg,#dce7da 0 24px,#d5e1d2 24px 48px);font-family:"Arial Narrow","Aptos Narrow",sans-serif}header{position:sticky;top:0;z-index:5;display:flex;align-items:center;gap:18px;flex-wrap:wrap;padding:14px 22px;color:white;background:var(--pine);border-bottom:5px solid var(--amber)}h1,h2{font-family:Rockwell,Georgia,serif}h1{margin:0;font-size:1.6rem}header p{margin:0;color:#caddd3}header form{margin-left:auto}.logout{padding:8px 12px;border:1px solid #d9e4dc;background:transparent;color:white;cursor:pointer}.ranges{display:flex;gap:6px}.ranges a{padding:7px 10px;color:white;text-decoration:none;border:1px solid #6e9180}.ranges a.active{background:var(--amber);color:var(--ink);font-weight:900}main{width:min(1480px,calc(100% - 24px));margin:18px auto 40px}.kpis{display:grid;grid-template-columns:repeat(8,minmax(120px,1fr));gap:10px}.kpi,.panel{background:rgba(255,249,233,.97);border:1px solid var(--line);box-shadow:5px 5px 0 rgb(23 37 31 / 11%)}.kpi{padding:13px}.kpi span{display:block;color:var(--muted);font-weight:800;text-transform:uppercase;font-size:.72rem;letter-spacing:.07em}.kpi b{display:block;margin-top:6px;font:900 1.7rem/1 Rockwell,serif}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:14px;margin-top:14px}.panel{grid-column:span 4}.panel.wide{grid-column:span 8}.panel.full{grid-column:1/-1}.panel h2{margin:0;padding:11px 14px;color:white;background:var(--green);font-size:1rem}.inside{padding:14px}.trend{display:block;width:100%;height:auto;background:#f5ecd8;border:1px solid #cabc9f}.trend text{font:12px sans-serif;fill:#56645b}.legend{display:flex;gap:16px;margin-top:8px;font-weight:800}.legend span:before{content:"";display:inline-block;width:18px;height:4px;margin:0 6px 3px 0}.legend .views:before{background:var(--rust)}.legend .visitors:before{background:var(--pine)}.legend .starts:before{background:#d69012}.bars{display:grid;gap:10px}.bar-label{display:flex;justify-content:space-between;gap:10px}.bar-track{height:10px;background:#dfd4bd}.bar-track i{display:block;height:100%;background:linear-gradient(90deg,var(--rust),var(--amber))}.empty{color:var(--muted)}table{width:100%;border-collapse:collapse}th,td{padding:9px;border-bottom:1px solid #d9ccb2;text-align:left;vertical-align:top}th{color:var(--muted);font-size:.75rem;text-transform:uppercase}.feedback-message{white-space:pre-wrap;max-width:850px}.privacy{padding:12px;border-left:6px solid var(--amber);background:#f4e6c5}.channel{color:var(--amber);text-transform:uppercase;font-weight:900}@media(max-width:1100px){.kpis{grid-template-columns:repeat(4,1fr)}.panel,.panel.wide{grid-column:span 6}}@media(max-width:700px){header{position:static}.kpis{grid-template-columns:repeat(2,1fr)}.panel,.panel.wide{grid-column:1/-1}.ranges{width:100%;overflow:auto}}
  </style>
</head>
<body>
<header><div><h1>RVGame Owner Dashboard</h1><p><span class="channel"><?= h($channel) ?></span> analytics starting with this deployment</p></div><nav class="ranges" aria-label="Date range"><?php foreach ([7,30,90,365] as $range): ?><a class="<?= $days === $range ? 'active' : '' ?>" href="?days=<?= $range ?>"><?= $range === 365 ? '1 year' : $range . ' days' ?></a><?php endforeach; ?></nav><form method="post"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><button class="logout" type="submit">Sign out</button></form></header>
<main>
  <p class="privacy">Owner testing in this browser is excluded from all metrics, ratings and feedback. Game saves are kept. Sign in to this dashboard once on each browser you use for testing.</p>
  <section class="kpis">
    <div class="kpi"><span>Active now</span><b><?= number_format((int) ($activeNow['total'] ?? 0)) ?></b></div>
    <div class="kpi"><span>Visitors</span><b><?= number_format((int) ($traffic['visitors'] ?? 0)) ?></b></div>
    <div class="kpi"><span>Visits</span><b><?= number_format((int) ($traffic['visits'] ?? 0)) ?></b></div>
    <div class="kpi"><span>Page views</span><b><?= number_format((int) ($traffic['views'] ?? 0)) ?></b></div>
    <div class="kpi"><span>Avg engagement</span><b><?= h(durationLabel((int) round((float) ($traffic['avg_seconds'] ?? 0)))) ?></b></div>
    <div class="kpi"><span>Bounce estimate</span><b><?= number_format((float) ($traffic['bounce_rate'] ?? 0), 1) ?>%</b></div>
    <div class="kpi"><span>Campaign starts</span><b><?= number_format((int) ($campaigns['total'] ?? 0)) ?></b></div>
    <div class="kpi"><span>Rating</span><b><?= $ratings['average'] === null ? '-' : h((string) $ratings['average']) ?> / 5</b></div>
  </section>
  <section class="grid">
    <article class="panel wide"><h2>Daily traffic and campaign starts</h2><div class="inside"><?= trendChart($daily) ?></div></article>
    <article class="panel"><h2>Campaign funnel</h2><div class="inside"><?= barChart($funnel) ?></div></article>
    <article class="panel"><h2>Traffic sources</h2><div class="inside"><?= barChart($sources) ?></div></article>
    <article class="panel"><h2>Devices</h2><div class="inside"><?= barChart($devices) ?></div></article>
    <article class="panel"><h2>Browsers</h2><div class="inside"><?= barChart($browsers) ?></div></article>
    <article class="panel"><h2>Operating systems</h2><div class="inside"><?= barChart($systems) ?></div></article>
    <article class="panel"><h2>Languages</h2><div class="inside"><?= barChart($languages) ?></div></article>
    <article class="panel"><h2>Visit start hours</h2><div class="inside"><?= barChart($hours) ?></div></article>
    <article class="panel"><h2>Game actions</h2><div class="inside"><?= barChart($actions) ?></div></article>
    <article class="panel"><h2>Ratings</h2><div class="inside"><p><b><?= number_format((int) ($ratings['total'] ?? 0)) ?></b> total ratings</p><?= barChart($ratingBreakdown) ?></div></article>
    <article class="panel"><h2>Feedback</h2><div class="inside"><p><b><?= number_format((int) ($feedbackCount['total'] ?? 0)) ?></b> messages in this range</p><p class="privacy">No raw IP addresses, raw user agents, or full referring URLs are stored.</p></div></article>
    <article class="panel full"><h2>Latest feedback</h2><div class="inside"><table><thead><tr><th>Time</th><th>Type</th><th>Message</th></tr></thead><tbody><?php if ($feedback === []): ?><tr><td colspan="3">No feedback yet.</td></tr><?php else: foreach ($feedback as $item): ?><tr><td><?= h((string) $item['created_at']) ?></td><td><?= h(ucfirst((string) $item['category'])) ?></td><td class="feedback-message"><?= h((string) $item['message']) ?></td></tr><?php endforeach; endif; ?></tbody></table></div></article>
  </section>
</main>
</body>
</html>
