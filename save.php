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
    if ($allowed === []) {
        jsonOut(['ok' => false, 'error' => 'Er staat niets klaar om in te vullen. Vraag de staf.'], 400);
    }
    $types = loadTypes($mysqli);
    $saved = 0;
    $mysqli->begin_transaction();
    try {
        foreach ($allowed as $tid => $_) {
            if (!array_key_exists((string) $tid, $items) && !array_key_exists($tid, $items)) {
                throw new RuntimeException('Vul alle maten in.');
            }
            $size = (string) ($items[$tid] ?? $items[(string) $tid] ?? '');
            if (sanitizeSize($size) === '') {
                throw new RuntimeException('Vul alle maten in.');
            }
            upsertPersonItem($mysqli, $types, 'player', (int) $player['id'], (int) $tid, $size, 'pending');
            $saved++;
        }
        $pid = (int) $player['id'];
        $stamp = $mysqli->prepare('UPDATE players SET parent_saved_at=NOW() WHERE id=?');
        $stamp->bind_param('i', $pid);
        $stamp->execute();
        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        jsonOut(['ok' => false, 'error' => $e->getMessage()], 400);
    }
    registerParentSave();
    jsonOut(['ok' => true, 'saved' => $saved]);
}

if ($action === 'parent_form') {
    if (!canEdit()) {
        jsonOut(['ok' => false, 'error' => 'Niet ingelogd.'], 401);
    }
    $tokenCsrf = (string) ($body['csrf'] ?? '');
    if ($tokenCsrf === '' || !hash_equals(csrfToken(), $tokenCsrf)) {
        jsonOut(['ok' => false, 'error' => 'Sessie verlopen. Vernieuw de pagina.'], 403);
    }
    $settings = loadParentFormSettings();
    $scope = (string) ($body['scope'] ?? '');
    if ($scope === 'defaults') {
        if (isset($body['note'])) {
            $settings['note'] = (string) $body['note'];
        }
        if (isset($body['field']) && is_array($body['field'])) {
            $settings['field'] = $body['field'];
        }
        if (isset($body['keeper']) && is_array($body['keeper'])) {
            $settings['keeper'] = $body['keeper'];
        }
        saveParentFormSettings($settings);
        jsonOut(['ok' => true, 'settings' => loadParentFormSettings()]);
    }
    if ($scope === 'player') {
        $id = (int) ($body['id'] ?? 0);
        if ($id < 1) {
            jsonOut(['ok' => false, 'error' => 'Speler ontbreekt.'], 400);
        }
        $st = $mysqli->prepare("SELECT id, position FROM players WHERE id=? AND status='active' LIMIT 1");
        $st->bind_param('i', $id);
        $st->execute();
        $p = $st->get_result()->fetch_assoc();
        if (!$p) {
            jsonOut(['ok' => false, 'error' => 'Speler niet gevonden.'], 400);
        }
        $reset = !empty($body['reset']);
        $typesIn = is_array($body['types'] ?? null) ? $body['types'] : [];
        if ($reset || $typesIn === []) {
            unset($settings['players'][$id]);
        } else {
            $settings['players'][$id] = $typesIn;
        }
        saveParentFormSettings($settings);
        $p['id'] = $id;
        jsonOut(['ok' => true, 'types' => parentAllowedTypeIds($p), 'custom' => parentUsesCustomTypes($p)]);
    }
    jsonOut(['ok' => false, 'error' => 'Onbekende instelling.'], 400);
}

if ($action === 'add_item') {
    if (!canEdit()) {
        jsonOut(['ok' => false, 'error' => 'Niet ingelogd.'], 401);
    }
    $token = (string) ($body['csrf'] ?? '');
    if ($token === '' || !hash_equals(csrfToken(), $token)) {
        jsonOut(['ok' => false, 'error' => 'Sessie verlopen. Vernieuw de pagina.'], 403);
    }
    $who = (string) ($body['who'] ?? '');
    $id = (int) ($body['id'] ?? 0);
    $tid = (int) ($body['tid'] ?? 0);
    $size = (string) ($body['size'] ?? '');
    $mode = (($body['mode'] ?? '') === 'active') ? 'active' : 'pending';
    if ($id < 1 || $tid < 1 || !in_array($who, ['player', 'staff'], true)) {
        jsonOut(['ok' => false, 'error' => 'Kies een speler en een item.'], 400);
    }
    if (sanitizeSize($size) === '') {
        jsonOut(['ok' => false, 'error' => 'Kies een maat.'], 400);
    }
    $types = loadTypes($mysqli);
    try {
        upsertPersonItem($mysqli, $types, $who, $id, $tid, $size, $mode);
    } catch (Throwable $e) {
        jsonOut(['ok' => false, 'error' => $e->getMessage()], 400);
    }
    jsonOut(['ok' => true, 'saved' => 1]);
}

if ($action === 'save_type') {
    if (!canEdit()) {
        jsonOut(['ok' => false, 'error' => 'Niet ingelogd.'], 401);
    }
    $token = (string) ($body['csrf'] ?? '');
    if ($token === '' || !hash_equals(csrfToken(), $token)) {
        jsonOut(['ok' => false, 'error' => 'Sessie verlopen. Vernieuw de pagina.'], 403);
    }
    $id = (int) ($body['id'] ?? 0);
    if ($id < 1) {
        jsonOut(['ok' => false, 'error' => 'Onbekend item.'], 400);
    }
    $types = loadTypes($mysqli);
    if (!isset($types[$id])) {
        jsonOut(['ok' => false, 'error' => 'Onbekend item.'], 400);
    }
    $article = array_key_exists('article_number', $body)
        ? substr(trim((string) $body['article_number']), 0, 50)
        : (string) ($types[$id]['article_number'] ?? '');
    $color = array_key_exists('color', $body)
        ? substr(trim((string) $body['color']), 0, 255)
        : (string) ($types[$id]['color'] ?? '');
    $small = array_key_exists('price_small', $body)
        ? parseMoney($body['price_small'])
        : (isset($types[$id]['price_small']) && $types[$id]['price_small'] !== '' && $types[$id]['price_small'] !== null ? (float) $types[$id]['price_small'] : null);
    $large = array_key_exists('price_large', $body)
        ? parseMoney($body['price_large'])
        : (isset($types[$id]['price_large']) && $types[$id]['price_large'] !== '' && $types[$id]['price_large'] !== null ? (float) $types[$id]['price_large'] : null);
    $std = $large ?? $small;
    $smallS = $small === null ? '' : number_format($small, 2, '.', '');
    $largeS = $large === null ? '' : number_format($large, 2, '.', '');
    $stdS = $std === null ? '' : number_format($std, 2, '.', '');
    $upd = $mysqli->prepare("UPDATE clothing_types SET article_number=?, color=?, price_small=NULLIF(?, ''), price_large=NULLIF(?, ''), price=NULLIF(?, ''), updated_at=NOW() WHERE id=?");
    $upd->bind_param('sssssi', $article, $color, $smallS, $largeS, $stdS, $id);
    $upd->execute();
    jsonOut([
        'ok' => true,
        'saved' => 1,
        'price_small' => $small,
        'price_large' => $large,
        'article_number' => $article,
        'color' => $color,
    ]);
}

if ($action === 'add_package') {
    if (!canEdit()) {
        jsonOut(['ok' => false, 'error' => 'Niet ingelogd.'], 401);
    }
    $token = (string) ($body['csrf'] ?? '');
    if ($token === '' || !hash_equals(csrfToken(), $token)) {
        jsonOut(['ok' => false, 'error' => 'Sessie verlopen. Vernieuw de pagina.'], 403);
    }
    $who = (string) ($body['who'] ?? 'player');
    $id = (int) ($body['id'] ?? 0);
    $mode = (($body['mode'] ?? '') === 'active') ? 'active' : 'pending';
    if ($id < 1 || !in_array($who, ['player', 'staff'], true)) {
        jsonOut(['ok' => false, 'error' => 'Kies een speler.'], 400);
    }
    $types = loadTypes($mysqli);
    try {
        $out = assignPackageToPerson($mysqli, $types, $who, $id, $mode, true);
    } catch (Throwable $e) {
        jsonOut(['ok' => false, 'error' => $e->getMessage()], 400);
    }
    if ($out['saved'] < 1 && $out['skipped'] !== []) {
        jsonOut(['ok' => false, 'error' => 'Geen shirtmaat. Vul eerst het shirt in.'], 400);
    }
    jsonOut(['ok' => true, 'saved' => $out['saved'], 'skipped' => $out['skipped']]);
}

if ($action === 'add_package_all') {
    if (!canEdit()) {
        jsonOut(['ok' => false, 'error' => 'Niet ingelogd.'], 401);
    }
    $token = (string) ($body['csrf'] ?? '');
    if ($token === '' || !hash_equals(csrfToken(), $token)) {
        jsonOut(['ok' => false, 'error' => 'Sessie verlopen. Vernieuw de pagina.'], 403);
    }
    $mode = (($body['mode'] ?? '') === 'active') ? 'active' : 'pending';
    $types = loadTypes($mysqli);
    $saved = 0;
    $people = 0;
    $res = $mysqli->query("SELECT id FROM players WHERE status='active' AND IFNULL(is_guest,0)=0 ORDER BY last_name, first_name");
    $mysqli->begin_transaction();
    try {
        while ($row = $res->fetch_assoc()) {
            $out = assignPackageToPerson($mysqli, $types, 'player', (int) $row['id'], $mode, true);
            if ($out['saved'] > 0) {
                $people++;
                $saved += $out['saved'];
            }
        }
        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        jsonOut(['ok' => false, 'error' => $e->getMessage()], 400);
    }
    jsonOut(['ok' => true, 'saved' => $saved, 'people' => $people]);
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
