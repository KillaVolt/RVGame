<?php
declare(strict_types=1);
require dirname(__DIR__) . '/api/Database.php';
require dirname(__DIR__) . '/api/Analytics.php';
require dirname(__DIR__) . '/api/Game.php';

function verify(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$pdo = Database::connect();
$sessionIds = [];
try {
    $game = new Game(dirname(__DIR__) . '/content/simple-mode.json');
    foreach (['owner', 'player'] as $kind) {
        $token = bin2hex(random_bytes(32));
        $insert = $pdo->prepare('INSERT INTO rvgame_sessions (channel, token_hash) VALUES (?, ?)');
        $insert->execute(['ot', hash('sha256', $token, true)]);
        $id = (int) $pdo->lastInsertId();
        $sessionIds[$kind] = $id;
        $_COOKIE = [];
        Analytics::recordPageView($pdo, $id, 'ot');
        $visit = (int) $pdo->lastInsertId();
        $event = $pdo->prepare('INSERT INTO rvgame_analytics_events (visit_id, session_id, channel, event_name) VALUES (?, ?, ?, ?)');
        $event->execute([$visit, $id, 'ot', 'campaign_start']);
        $rating = $pdo->prepare('INSERT INTO rvgame_ratings (session_id, rating) VALUES (?, ?)');
        $rating->execute([$id, $kind === 'owner' ? 1 : 5]);
        $feedback = $pdo->prepare('INSERT INTO rvgame_feedback (session_id, category, message) VALUES (?, ?, ?)');
        $feedback->execute([$id, 'bug', 'Disposable owner-exclusion test']);
        $state = $game->initialState('hands-on');
        $campaign = $pdo->prepare('INSERT INTO rvgame_campaigns (id, session_id, ruleset_id, revision, status, state_json) VALUES (?, ?, ?, ?, ?, ?)');
        $campaign->execute([$state['campaignId'], $id, $state['rulesetId'], 0, 'active', json_encode($state, JSON_THROW_ON_ERROR)]);
        if ($kind === 'owner') {
            $ownerToken = $token;
            $savedState = json_encode($state, JSON_THROW_ON_ERROR);
        }
    }
    $_COOKIE = ['rvgame_ot_session' => $ownerToken];
    Database::excludeOwnerBrowser($pdo);
    verify($_COOKIE['rvgame_owner_testing'] === '1', 'Owner browser marker missing');
    verify(Database::analyticsExcluded($pdo, $sessionIds['owner']), 'Owner session not excluded');
    verify(!Database::analyticsExcluded($pdo, $sessionIds['player']), 'Other player was excluded');
    Analytics::recordPageView($pdo, $sessionIds['owner'], 'ot');
    Analytics::touch($pdo, $sessionIds['owner'], 'ot');
    Analytics::recordEvent($pdo, $sessionIds['owner'], 'ot', 'command_work');
    foreach (['rvgame_analytics_events', 'rvgame_analytics_visits', 'rvgame_feedback', 'rvgame_ratings'] as $table) {
        $query = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE session_id = ?");
        $query->execute([$sessionIds['owner']]);
        verify((int) $query->fetchColumn() === 0, "Owner records remain in $table");
        $query->execute([$sessionIds['player']]);
        verify((int) $query->fetchColumn() === 1, "Other player records changed in $table");
    }
    $query = $pdo->prepare('SELECT state_json FROM rvgame_campaigns WHERE session_id = ?');
    $query->execute([$sessionIds['owner']]);
    verify($query->fetchColumn() === $savedState, 'Owner game save changed');
    $query = $pdo->prepare('SELECT COUNT(*) FROM rvgame_campaigns c JOIN rvgame_sessions s ON s.id=c.session_id WHERE s.analytics_excluded=0 AND s.id IN (?, ?)');
    $query->execute(array_values($sessionIds));
    verify((int) $query->fetchColumn() === 1, 'Owner campaign entered the metric');
    $_COOKIE = ['rvgame_owner_testing' => '1'];
    $sessionIds['fresh'] = Database::sessionId($pdo);
    verify(Database::analyticsExcluded($pdo, $sessionIds['fresh']), 'Fresh campaign session lost browser exclusion');
    Database::excludeSession($pdo, $sessionIds['owner']);
    echo "PASS owner exclusion: historical cleanup, future analytics suppressed, new sessions excluded, other player and game saves preserved\n";
} finally {
    foreach ($sessionIds as $id) {
        $delete = $pdo->prepare('DELETE FROM rvgame_sessions WHERE id = ?');
        $delete->execute([$id]);
    }
}
