<?php
declare(strict_types=1);

require __DIR__ . '/Database.php';
require __DIR__ . '/Game.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $pdo = Database::connect();
    $sessionId = Database::sessionId($pdo);
    $game = new Game(dirname(__DIR__) . '/content/simple-mode.json');

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
        $query = $pdo->prepare('SELECT state_json FROM rvgame_campaigns WHERE session_id = ?');
        $query->execute([$sessionId]);
        $raw = $query->fetchColumn();
        respond([
            'ok' => true,
            'state' => $raw === false ? null : $game->publicView(json_decode((string) $raw, true, flags: JSON_THROW_ON_ERROR)),
            'setup' => $game->startOptions(),
        ]);
    }

    if ($method !== 'POST'
        || ($_SERVER['HTTP_X_RVGAME'] ?? '') !== '1'
        || !str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        respond(['ok' => false, 'error' => ['code' => 'BAD_REQUEST', 'message' => 'Expected an authorized JSON game request.']], 400);
    }

    $rawBody = file_get_contents('php://input');
    $request = json_decode($rawBody === false ? '' : $rawBody, true, flags: JSON_THROW_ON_ERROR);
    $action = $request['action'] ?? '';

    if ($action === 'new') {
        $query = $pdo->prepare('SELECT state_json FROM rvgame_campaigns WHERE session_id = ?');
        $query->execute([$sessionId]);
        $existing = $query->fetchColumn();
        if ($existing !== false) {
            $state = json_decode((string) $existing, true, flags: JSON_THROW_ON_ERROR);
            respond(['ok' => true, 'state' => $game->publicView($state)]);
        }
        $state = $game->initialState((string) ($request['loadoutId'] ?? ''));
        $insert = $pdo->prepare('INSERT INTO rvgame_campaigns (id, session_id, ruleset_id, revision, status, state_json) VALUES (?, ?, ?, ?, ?, ?)');
        $insert->execute([
            $state['campaignId'],
            $sessionId,
            $state['rulesetId'],
            $state['revision'],
            $state['status'],
            json_encode($state, JSON_THROW_ON_ERROR),
        ]);
        respond(['ok' => true, 'state' => $game->publicView($state)]);
    }

    if ($action === 'reset') {
        $delete = $pdo->prepare('DELETE FROM rvgame_campaigns WHERE session_id = ?');
        $delete->execute([$sessionId]);
        respond(['ok' => true, 'state' => null]);
    }

    if ($action !== 'command') {
        respond(['ok' => false, 'error' => ['code' => 'UNKNOWN_ACTION', 'message' => 'Unknown API action.']], 400);
    }

    $commandId = (string) ($request['commandId'] ?? '');
    $expectedRevision = $request['expectedRevision'] ?? null;
    $type = (string) ($request['type'] ?? '');
    $payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];
    if (!preg_match('/^[a-f0-9-]{36}$/i', $commandId) || !is_int($expectedRevision) || $type === '') {
        respond(['ok' => false, 'error' => ['code' => 'INVALID_COMMAND', 'message' => 'Command ID, revision, or type is invalid.']], 422);
    }

    $requestHash = hash('sha256', json_encode([$type, $payload], JSON_THROW_ON_ERROR), true);
    $pdo->beginTransaction();
    $campaignQuery = $pdo->prepare('SELECT id, revision, state_json FROM rvgame_campaigns WHERE session_id = ? FOR UPDATE');
    $campaignQuery->execute([$sessionId]);
    $campaign = $campaignQuery->fetch();
    if ($campaign === false) {
        $pdo->rollBack();
        respond(['ok' => false, 'error' => ['code' => 'NO_CAMPAIGN', 'message' => 'Start a campaign first.']], 409);
    }

    $receiptQuery = $pdo->prepare('SELECT campaign_id, request_hash, response_json FROM rvgame_command_receipts WHERE command_id = ?');
    $receiptQuery->execute([$commandId]);
    $receipt = $receiptQuery->fetch();
    if ($receipt !== false) {
        if ($receipt['campaign_id'] !== $campaign['id'] || !hash_equals($receipt['request_hash'], $requestHash)) {
            $pdo->rollBack();
            respond(['ok' => false, 'error' => ['code' => 'COMMAND_ID_CONFLICT', 'message' => 'Command ID was reused for a different request.']], 409);
        }
        $pdo->commit();
        $saved = json_decode($receipt['response_json'], true, flags: JSON_THROW_ON_ERROR);
        $saved['duplicate'] = true;
        $saved['state'] = $game->publicView(json_decode($campaign['state_json'], true, flags: JSON_THROW_ON_ERROR));
        respond($saved);
    }

    if ((int) $campaign['revision'] !== $expectedRevision) {
        $pdo->rollBack();
        respond([
            'ok' => false,
            'error' => [
                'code' => 'STALE_REVISION',
                'message' => 'Campaign changed. Reload before retrying.',
                'currentRevision' => (int) $campaign['revision'],
            ],
        ], 409);
    }

    $state = json_decode($campaign['state_json'], true, flags: JSON_THROW_ON_ERROR);
    [$state, $result] = $game->apply($state, $type, $payload);
    $response = ['ok' => true, 'duplicate' => false, 'state' => $game->publicView($state), 'result' => $result];
    $update = $pdo->prepare('UPDATE rvgame_campaigns SET revision = ?, status = ?, state_json = ? WHERE id = ?');
    $update->execute([$state['revision'], $state['status'], json_encode($state, JSON_THROW_ON_ERROR), $campaign['id']]);
    $receiptInsert = $pdo->prepare('INSERT INTO rvgame_command_receipts (command_id, campaign_id, request_hash, response_json) VALUES (?, ?, ?, ?)');
    $receiptInsert->execute([$commandId, $campaign['id'], $requestHash, json_encode($response, JSON_THROW_ON_ERROR)]);
    $pdo->commit();
    respond($response);
} catch (GameRuleException $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respond(['ok' => false, 'error' => ['code' => $error->ruleCode, 'message' => $error->getMessage()]], 422);
} catch (JsonException $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respond(['ok' => false, 'error' => ['code' => 'INVALID_JSON', 'message' => 'JSON input or stored state is invalid.']], 400);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('RVGame API failure: ' . $error->getMessage());
    respond(['ok' => false, 'error' => ['code' => 'SERVER_FAILURE', 'message' => 'The game server or database failed. No fallback save was used.']], 500);
}
