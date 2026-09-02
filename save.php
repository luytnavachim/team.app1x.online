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
