<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require __DIR__ . '/boot.php';

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '', true);
if (!is_array($body)) {
    $body = $_POST;
}
$action = (string) ($body['action'] ?? $_GET['action'] ?? '');

function jsonOut(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'login') {
    $hash = (string) ($config['edit_pin_hash'] ?? '');
    $res = tryLogin((string) ($body['pin'] ?? ''), $hash);
    jsonOut($res, $res['ok'] ? 200 : 401);
}

if ($action === 'logout') {
    logoutEdit();
    jsonOut(['ok' => true]);
}

if ($action === 'parent_link' || $action === 'parent_rotate') {
    if (!canEdit()) {
        jsonOut(['ok' => false, 'error' => 'Niet ingelogd.'], 401);
    }
    $tokenCsrf = (string) ($body['csrf'] ?? '');
    if ($tokenCsrf === '' || !hash_equals(csrfToken(), $tokenCsrf)) {
        jsonOut(['ok' => false, 'error' => 'Sessie verlopen. Vernieuw de pagina.'], 403);
    }
    $id = (int) ($body['id'] ?? 0);
    try {
        $token = playerParentToken($mysqli, $id, $action === 'parent_rotate');
    } catch (Throwable $e) {
        jsonOut(['ok' => false, 'error' => $e->getMessage()], 400);
    }
    $st = $mysqli->prepare('SELECT first_name, last_name FROM players WHERE id=? LIMIT 1');
    $st->bind_param('i', $id);
    $st->execute();
    $p = $st->get_result()->fetch_assoc() ?: ['first_name' => '', 'last_name' => ''];
    $name = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
    $url = parentLinkUrl($token);
    jsonOut([
        'ok' => true,
        'url' => $url,
        'name' => $name,
        'wa' => parentWhatsAppUrl($name, $url),
        'message' => parentMessage($name, $url),
    ]);
}

if ($action === 'parent_save') {
    $until = null;
    if (parentSaveBlocked($until)) {
        jsonOut(['ok' => false, 'error' => 'Te veel pogingen. Probeer na ' . $until . ' opnieuw.'], 429);
    }
    $parentToken = (string) ($body['token'] ?? '');
    $player = findPlayerByParentToken($mysqli, $parentToken);
    if (!$player) {
        registerParentSave();
        jsonOut(['ok' => false, 'error' => 'Deze link is ongeldig of verlopen.'], 403);
    }
    $tokenCsrf = (string) ($body['csrf'] ?? '');
    if ($tokenCsrf === '' || !hash_equals(csrfToken(), $tokenCsrf)) {
        jsonOut(['ok' => false, 'error' => 'Sessie verlopen. Vernieuw de pagina.'], 403);
    }
    $items = $body['items'] ?? [];
    if (!is_array($items) || $items === []) {
        jsonOut(['ok' => false, 'error' => 'Niets om op te slaan.'], 400);
    }
    $allowed = array_fill_keys(parentAllowedTypeIds($player), true);
    $types = loadTypes($mysqli);
    $saved = 0;
    $mysqli->begin_transaction();
    try {
        foreach ($items as $tid => $size) {
            $tid = (int) $tid;
            if (!isset($allowed[$tid])) {
                continue;
            }
            upsertPersonItem($mysqli, $types, 'player', (int) $player['id'], $tid, (string) $size, 'pending');
            $saved++;
        }
        if ($saved < 1) {
            throw new RuntimeException('Niets om op te slaan.');
        }
        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        jsonOut(['ok' => false, 'error' => $e->getMessage()], 400);
    }
    registerParentSave();
    jsonOut(['ok' => true, 'saved' => $saved]);
}

if ($action === 'save' || $action === 'save_all') {
    if (!canEdit()) {
        jsonOut(['ok' => false, 'error' => 'Niet ingelogd.'], 401);
    }
    $token = (string) ($body['csrf'] ?? '');
    if ($token === '' || !hash_equals(csrfToken(), $token)) {
        jsonOut(['ok' => false, 'error' => 'Sessie verlopen. Vernieuw de pagina.'], 403);
    }
    $mode = (($body['mode'] ?? '') === 'active') ? 'active' : 'pending';
    $rows = $action === 'save_all' ? ($body['rows'] ?? []) : [$body];
    if (!is_array($rows) || $rows === []) {
        jsonOut(['ok' => false, 'error' => 'Niets om op te slaan.'], 400);
    }
    $types = loadTypes($mysqli);
    $saved = 0;
    $mysqli->begin_transaction();
    try {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $who = (string) ($row['who'] ?? '');
            $id = (int) ($row['id'] ?? 0);
            $items = $row['items'] ?? [];
            if ($id < 1 || !in_array($who, ['player', 'staff'], true) || !is_array($items)) {
                throw new RuntimeException('Ongeldige rij');
            }
            foreach ($items as $tid => $size) {
                upsertPersonItem($mysqli, $types, $who, $id, (int) $tid, (string) $size, $mode);
                $saved++;
            }
        }
        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        jsonOut(['ok' => false, 'error' => $e->getMessage()], 400);
    }
    jsonOut(['ok' => true, 'saved' => $saved]);
}

jsonOut(['ok' => false, 'error' => 'Onbekende actie.'], 400);
