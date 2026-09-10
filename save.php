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
    $who = (string) ($body['who'] ?? 'player');
    try {
        if ($who === 'staff') {
            $token = staffFillToken($mysqli, $id, $action === 'parent_rotate');
            $st = $mysqli->prepare('SELECT first_name, last_name FROM staff_members WHERE id=? LIMIT 1');
        } else {
            $token = playerParentToken($mysqli, $id, $action === 'parent_rotate');
            $st = $mysqli->prepare('SELECT first_name, last_name FROM players WHERE id=? LIMIT 1');
        }
    } catch (Throwable $e) {
        jsonOut(['ok' => false, 'error' => $e->getMessage()], 400);
    }
    $st->bind_param('i', $id);
    $st->execute();
    $p = $st->get_result()->fetch_assoc() ?: ['first_name' => '', 'last_name' => ''];
    $name = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
    $url = parentLinkUrl($token);
    $message = $who === 'staff' ? staffFillMessage($name, $url) : parentMessage($name, $url);
    jsonOut([
        'ok' => true,
        'url' => $url,
        'name' => $name,
        'wa' => fillWhatsAppUrl($message),
        'message' => $message,
    ]);
}

if ($action === 'parent_save') {
    $until = null;
    if (parentSaveBlocked($until)) {
        jsonOut(['ok' => false, 'error' => 'Te veel pogingen. Probeer na ' . $until . ' opnieuw.'], 429);
    }
    $parentToken = (string) ($body['token'] ?? '');
    $found = findFillPersonByToken($mysqli, $parentToken);
    if (!$found) {
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
    $who = (string) $found['who'];
    $person = $found['person'];
    $allowedIds = $who === 'staff' ? staffAllowedTypeIds($person) : parentAllowedTypeIds($person);
    $allowed = array_fill_keys($allowedIds, true);
    if ($allowed === []) {
        jsonOut(['ok' => false, 'error' => 'Er staat niets klaar om in te vullen. Vraag de trainer of manager.'], 400);
    }
    $types = loadTypes($mysqli);
    $saved = 0;
    $jerseySaved = null;
    $mysqli->begin_transaction();
    try {
        if ($who === 'player') {
            $jerseySaved = setPlayerJerseyNumber($mysqli, (int) $person['id'], $body['jersey_number'] ?? '');
        }
        foreach ($allowed as $tid => $_) {
            if (!array_key_exists((string) $tid, $items) && !array_key_exists($tid, $items)) {
                throw new RuntimeException('Vul alle maten in of kies n.v.t.');
            }
            $raw = $items[$tid] ?? $items[(string) $tid] ?? '';
            $parsed = parseItemInput($raw);
            $size = (string) $parsed['size'];
            if ($size === skipSizeToken()) {
                applyPersonItemChoice($mysqli, $types, $who, (int) $person['id'], (int) $tid, ['want' => false, 'size' => skipSizeToken()], 'pending');
                continue;
            }
            if (sanitizeSize($size) === '') {
                throw new RuntimeException('Vul alle maten in of kies n.v.t.');
            }
            if ($who === 'player' && !typeAllowedForPlayer($person, (int) $tid)) {
                continue;
            }
            upsertPersonItem($mysqli, $types, $who, (int) $person['id'], (int) $tid, $size, 'pending');
            $saved++;
        }
        $pid = (int) $person['id'];
        if ($who === 'staff') {
            $stamp = $mysqli->prepare('UPDATE staff_members SET parent_saved_at=NOW() WHERE id=?');
        } else {
            $stamp = $mysqli->prepare('UPDATE players SET parent_saved_at=NOW() WHERE id=?');
        }
        $stamp->bind_param('i', $pid);
        $stamp->execute();
        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        jsonOut(['ok' => false, 'error' => $e->getMessage()], 400);
    }
    registerParentSave();
    jsonOut(['ok' => true, 'saved' => $saved, 'jersey' => $jerseySaved]);
}

if ($action === 'parent_form') {
    if (!canEdit()) {
        jsonOut(['ok' => false, 'error' => 'Niet ingelogd.'], 401);
    }
    $tokenCsrf = (string) ($body['csrf'] ?? '');
    if ($tokenCsrf === '' || !hash_equals(csrfToken(), $tokenCsrf)) {
        jsonOut(['ok' => false, 'error' => 'Sessie verlopen. Vernieuw de pagina.'], 403);
    }
    loadTypes($mysqli);
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
        if (isset($body['staff']) && is_array($body['staff'])) {
            $settings['staff'] = $body['staff'];
        }
        saveParentFormSettings($settings);
        jsonOut(['ok' => true, 'settings' => loadParentFormSettings()]);
    }
    if ($scope === 'staff') {
        $id = (int) ($body['id'] ?? 0);
        if ($id < 1) {
            jsonOut(['ok' => false, 'error' => 'Staf ontbreekt.'], 400);
        }
        $st = $mysqli->prepare("SELECT id FROM staff_members WHERE id=? AND status='active' LIMIT 1");
        $st->bind_param('i', $id);
        $st->execute();
        if (!$st->get_result()->fetch_assoc()) {
            jsonOut(['ok' => false, 'error' => 'Staf niet gevonden.'], 400);
        }
        $reset = !empty($body['reset']);
        $typesIn = is_array($body['types'] ?? null) ? $body['types'] : [];
        if ($reset || $typesIn === []) {
            unset($settings['staff_members'][$id]);
        } else {
            $settings['staff_members'][$id] = $typesIn;
        }
        saveParentFormSettings($settings);
        $s = ['id' => $id];
        jsonOut(['ok' => true, 'types' => staffAllowedTypeIds($s), 'custom' => staffUsesCustomTypes($s)]);
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
    $display = array_key_exists('display_name', $body)
        ? substr(trim((string) $body['display_name']), 0, 255)
        : (string) ($types[$id]['display_name'] ?? '');
    if ($display === '') {
        jsonOut(['ok' => false, 'error' => 'Naam mag niet leeg zijn.'], 400);
    }
    $color = array_key_exists('color', $body)
        ? substr(trim((string) $body['color']), 0, 255)
        : (string) ($types[$id]['color'] ?? '');
    $brand = array_key_exists('brand', $body)
        ? substr(trim((string) $body['brand']), 0, 80)
        : (string) ($types[$id]['brand'] ?? '');
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
    $sizeKind = array_key_exists('size_kind', $body)
        ? strtolower(trim((string) $body['size_kind']))
        : (string) ($types[$id]['size_kind'] ?? 'body');
    if (!in_array($sizeKind, ['body', 'socks', 'onesize'], true)) {
        $sizeKind = 'body';
    }
    $orderGroup = array_key_exists('order_group', $body)
        ? strtolower(trim((string) $body['order_group']))
        : (string) ($types[$id]['order_group'] ?? 'extra');
    if (!in_array($orderGroup, ['match', 'package', 'extra'], true)) {
        $orderGroup = 'extra';
    }
    $printRohda = array_key_exists('print_rohda', $body) ? ((int) $body['print_rohda'] ? 1 : 0) : (int) ($types[$id]['print_rohda'] ?? 0);
    $printIni = array_key_exists('print_initials', $body) ? ((int) $body['print_initials'] ? 1 : 0) : (int) ($types[$id]['print_initials'] ?? 0);
    $printSp = array_key_exists('print_sponsor', $body) ? ((int) $body['print_sponsor'] ? 1 : 0) : (int) ($types[$id]['print_sponsor'] ?? 0);
    $printName = array_key_exists('print_name_back', $body) ? ((int) $body['print_name_back'] ? 1 : 0) : (int) ($types[$id]['print_name_back'] ?? 0);
    $printPlace = array_key_exists('print_place', $body)
        ? substr(trim((string) $body['print_place']), 0, 255)
        : (string) ($types[$id]['print_place'] ?? '');
    $upd = $mysqli->prepare("UPDATE clothing_types SET display_name=?, article_number=?, color=?, brand=?, price_small=NULLIF(?, ''), price_large=NULLIF(?, ''), price=NULLIF(?, ''), size_kind=?, order_group=?, print_rohda=?, print_initials=?, print_sponsor=?, print_name_back=?, print_place=?, updated_at=NOW() WHERE id=?");
    $upd->bind_param('sssssssssiiiisi', $display, $article, $color, $brand, $smallS, $largeS, $stdS, $sizeKind, $orderGroup, $printRohda, $printIni, $printSp, $printName, $printPlace, $id);
    if (!$upd->execute()) {
        jsonOut(['ok' => false, 'error' => 'Kon artikel niet opslaan.'], 400);
    }
    jsonOut([
        'ok' => true,
        'saved' => 1,
        'price_small' => $small,
        'price_large' => $large,
        'article_number' => $article,
        'color' => $color,
        'display_name' => $display,
    ]);
}

if ($action === 'delete_type') {
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
    if (!isset($types[$id]) || !typeIsActive($types[$id])) {
        jsonOut(['ok' => false, 'error' => 'Onbekend item.'], 400);
    }
    $pc = 0;
    $sc = 0;
    $st = $mysqli->prepare('SELECT COUNT(*) AS c FROM player_clothing WHERE clothing_type_id=?');
    $st->bind_param('i', $id);
    $st->execute();
    $pc = (int) ($st->get_result()->fetch_assoc()['c'] ?? 0);
    $st = $mysqli->prepare('SELECT COUNT(*) AS c FROM staff_clothing WHERE clothing_type_id=?');
    $st->bind_param('i', $id);
    $st->execute();
    $sc = (int) ($st->get_result()->fetch_assoc()['c'] ?? 0);

    $mysqli->begin_transaction();
    try {
        $upd = $mysqli->prepare('UPDATE clothing_types SET active=0, updated_at=NOW() WHERE id=?');
        $upd->bind_param('i', $id);
        $upd->execute();
        $kit = loadKitSettings();
        $kit['package'] = array_values(array_filter(
            array_map('intval', $kit['package'] ?? []),
            static fn(int $tid): bool => $tid !== $id
        ));
        saveKitSettings($kit);
        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        jsonOut(['ok' => false, 'error' => $e->getMessage()], 400);
    }
    jsonOut(['ok' => true, 'player_items' => $pc, 'staff_items' => $sc]);
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
    $res = $mysqli->query("SELECT * FROM players WHERE status='active' AND IFNULL(is_guest,0)=0 ORDER BY last_name, first_name");
    $portal = loadScoutPortal();
    $mysqli->begin_transaction();
    try {
        while ($row = $res->fetch_assoc()) {
            if (!playerOnScoutTeam14($row, $portal)) {
                continue;
            }
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

if ($action === 'save_jersey') {
    if (!canEdit()) {
        jsonOut(['ok' => false, 'error' => 'Niet ingelogd.'], 401);
    }
    $token = (string) ($body['csrf'] ?? '');
    if ($token === '' || !hash_equals(csrfToken(), $token)) {
        jsonOut(['ok' => false, 'error' => 'Sessie verlopen. Vernieuw de pagina.'], 403);
    }
    $id = (int) ($body['id'] ?? 0);
    if ($id < 1) {
        jsonOut(['ok' => false, 'error' => 'Kies een speler.'], 400);
    }
    $mysqli->begin_transaction();
    try {
        $jersey = setPlayerJerseyNumber($mysqli, $id, $body['jersey_number'] ?? '', true);
        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        jsonOut(['ok' => false, 'error' => $e->getMessage()], 400);
    }
    jsonOut(['ok' => true, 'jersey' => $jersey]);
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
                if (applyPersonItemChoice($mysqli, $types, $who, $id, (int) $tid, $size, $mode)) {
                    $saved++;
                }
            }
        }
        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        jsonOut(['ok' => false, 'error' => $e->getMessage()], 400);
    }
    jsonOut(['ok' => true, 'saved' => $saved]);
}

if ($action === 'remove_item') {
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
    if ($id < 1 || $tid < 1 || !in_array($who, ['player', 'staff'], true)) {
        jsonOut(['ok' => false, 'error' => 'Kies een item om te verwijderen.'], 400);
    }
    removePersonItem($mysqli, $who, $id, $tid);
    jsonOut(['ok' => true, 'saved' => 1]);
}

if ($action === 'add_type') {
    if (!canEdit()) {
        jsonOut(['ok' => false, 'error' => 'Niet ingelogd.'], 401);
    }
    $token = (string) ($body['csrf'] ?? '');
    if ($token === '' || !hash_equals(csrfToken(), $token)) {
        jsonOut(['ok' => false, 'error' => 'Sessie verlopen. Vernieuw de pagina.'], 403);
    }
    $display = substr(trim((string) ($body['display_name'] ?? '')), 0, 255);
    if ($display === '') {
        jsonOut(['ok' => false, 'error' => 'Vul een naam in.'], 400);
    }
    $name = slugTypeName($display);
    $article = substr(trim((string) ($body['article_number'] ?? '')), 0, 50);
    $color = substr(trim((string) ($body['color'] ?? '')), 0, 255);
    $brand = substr(trim((string) ($body['brand'] ?? 'Stanno')), 0, 80);
    if ($brand === '') {
        $brand = 'Stanno';
    }
    $small = parseMoney($body['price_small'] ?? null);
    $large = parseMoney($body['price_large'] ?? null) ?? $small;
    $std = $large ?? $small;
    $smallS = $small === null ? '' : number_format($small, 2, '.', '');
    $largeS = $large === null ? '' : number_format($large, 2, '.', '');
    $stdS = $std === null ? '' : number_format($std, 2, '.', '');
    $sizeKind = strtolower(trim((string) ($body['size_kind'] ?? 'body')));
    if (!in_array($sizeKind, ['body', 'socks', 'onesize'], true)) {
        $sizeKind = 'body';
    }
    $orderGroup = strtolower(trim((string) ($body['order_group'] ?? 'extra')));
    if (!in_array($orderGroup, ['match', 'package', 'extra'], true)) {
        $orderGroup = 'extra';
    }
    $printRohda = !empty($body['print_rohda']) ? 1 : 0;
    $printIni = !empty($body['print_initials']) ? 1 : 0;
    $printSp = !empty($body['print_sponsor']) ? 1 : 0;
    $printName = !empty($body['print_name_back']) ? 1 : 0;
    $printPlace = substr(trim((string) ($body['print_place'] ?? '')), 0, 255);
    $chk = $mysqli->prepare('SELECT id FROM clothing_types WHERE name=? LIMIT 1');
    $base = $name;
    for ($i = 0; $i < 8; $i++) {
        $try = $i === 0 ? $base : $base . $i;
        $chk->bind_param('s', $try);
        $chk->execute();
        if (!$chk->get_result()->fetch_row()) {
            $name = $try;
            break;
        }
    }
    $ins = $mysqli->prepare('INSERT INTO clothing_types (name, display_name, article_number, color, brand, price_small, price_large, price, size_kind, order_group, print_rohda, print_initials, print_sponsor, print_name_back, print_place, active, created_at, updated_at) VALUES (?,?,?,?,?,NULLIF(?,\'\'),NULLIF(?,\'\'),NULLIF(?,\'\'),?,?,?,?,?,?,?,1,NOW(),NOW())');
    $ins->bind_param('ssssssssssiiiis', $name, $display, $article, $color, $brand, $smallS, $largeS, $stdS, $sizeKind, $orderGroup, $printRohda, $printIni, $printSp, $printName, $printPlace);
    if (!$ins->execute()) {
        jsonOut(['ok' => false, 'error' => 'Kon artikel niet toevoegen.'], 400);
    }
    $newId = (int) $mysqli->insert_id;
    if ($orderGroup === 'package' && $newId > 0) {
        $kit = loadKitSettings();
        $kit['package'][] = $newId;
        saveKitSettings($kit);
    }
    jsonOut(['ok' => true, 'id' => $newId]);
}

if ($action === 'save_kit') {
    if (!canEdit()) {
        jsonOut(['ok' => false, 'error' => 'Niet ingelogd.'], 401);
    }
    $token = (string) ($body['csrf'] ?? '');
    if ($token === '' || !hash_equals(csrfToken(), $token)) {
        jsonOut(['ok' => false, 'error' => 'Sessie verlopen. Vernieuw de pagina.'], 403);
    }
    $kit = loadKitSettings();
    if (isset($body['package']) && is_array($body['package'])) {
        $kit['package'] = $body['package'];
    }
    if (isset($body['print']) && is_array($body['print'])) {
        $kit['print'] = array_merge($kit['print'], $body['print']);
    }
    if (array_key_exists('season', $body)) {
        $kit['season'] = $body['season'];
    }
    saveKitSettings($kit);
    jsonOut(['ok' => true, 'settings' => loadKitSettings()]);
}

function requireEditor(array $body): void {
    if (!canEdit()) {
        jsonOut(['ok' => false, 'error' => 'Niet ingelogd.'], 401);
    }
    $token = (string) ($body['csrf'] ?? '');
    if ($token === '' || !hash_equals(csrfToken(), $token)) {
        jsonOut(['ok' => false, 'error' => 'Sessie verlopen. Vernieuw de pagina.'], 403);
    }
}

if ($action === 'save_player') {
    requireEditor($body);
    $id = (int) ($body['id'] ?? 0);
    $first = substr(trim((string) ($body['first_name'] ?? '')), 0, 255);
    $last = substr(trim((string) ($body['last_name'] ?? '')), 0, 255);
    if ($first === '' || $last === '') {
        jsonOut(['ok' => false, 'error' => 'Voor- en achternaam zijn verplicht.'], 400);
    }
    $position = normalizePlayerPosition((string) ($body['position'] ?? 'midfielder'));
    $guest = !empty($body['is_guest']) ? 1 : 0;
    $jerseyRaw = $body['jersey_number'] ?? '';
    if ($id > 0) {
        $chk = $mysqli->prepare('SELECT id FROM players WHERE id=? LIMIT 1');
        $chk->bind_param('i', $id);
        $chk->execute();
        if (!$chk->get_result()->fetch_row()) {
            jsonOut(['ok' => false, 'error' => 'Onbekende speler.'], 400);
        }
        if (array_key_exists('status', $body)) {
            $status = (($body['status'] ?? '') === 'inactive') ? 'inactive' : 'active';
            $upd = $mysqli->prepare('UPDATE players SET first_name=?, last_name=?, position=?, is_guest=?, status=?, updated_at=NOW() WHERE id=?');
            $upd->bind_param('sssisi', $first, $last, $position, $guest, $status, $id);
        } else {
            $upd = $mysqli->prepare('UPDATE players SET first_name=?, last_name=?, position=?, is_guest=?, updated_at=NOW() WHERE id=?');
            $upd->bind_param('sssii', $first, $last, $position, $guest, $id);
        }
        $upd->execute();
    } else {
        $status = (($body['status'] ?? 'active') === 'inactive') ? 'inactive' : 'active';
        $ins = $mysqli->prepare("INSERT INTO players (first_name, last_name, position, is_guest, status, created_at, updated_at) VALUES (?,?,?,?,?,NOW(),NOW())");
        $ins->bind_param('sssis', $first, $last, $position, $guest, $status);
        if (!$ins->execute()) {
            jsonOut(['ok' => false, 'error' => 'Kon speler niet toevoegen.'], 400);
        }
        $id = (int) $mysqli->insert_id;
    }
    if ($id > 0 && array_key_exists('jersey_number', $body)) {
        try {
            $mysqli->begin_transaction();
            setPlayerJerseyNumber($mysqli, $id, $jerseyRaw, true);
            $mysqli->commit();
        } catch (Throwable $e) {
            $mysqli->rollback();
            jsonOut(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }
    jsonOut(['ok' => true, 'id' => $id]);
}

if ($action === 'set_player_status') {
    requireEditor($body);
    $id = (int) ($body['id'] ?? 0);
    $status = (($body['status'] ?? '') === 'inactive') ? 'inactive' : 'active';
    if ($id < 1) {
        jsonOut(['ok' => false, 'error' => 'Kies een speler.'], 400);
    }
    $upd = $mysqli->prepare('UPDATE players SET status=?, updated_at=NOW() WHERE id=?');
    $upd->bind_param('si', $status, $id);
    $upd->execute();
    jsonOut(['ok' => true, 'status' => $status]);
}

if ($action === 'save_staff') {
    requireEditor($body);
    $id = (int) ($body['id'] ?? 0);
    $first = substr(trim((string) ($body['first_name'] ?? '')), 0, 255);
    $last = substr(trim((string) ($body['last_name'] ?? '')), 0, 255);
    $role = substr(trim((string) ($body['role'] ?? 'staf')), 0, 255);
    if ($first === '' || $last === '') {
        jsonOut(['ok' => false, 'error' => 'Voor- en achternaam zijn verplicht.'], 400);
    }
    if ($role === '') {
        $role = 'staf';
    }
    if ($id > 0) {
        $chk = $mysqli->prepare('SELECT id FROM staff_members WHERE id=? LIMIT 1');
        $chk->bind_param('i', $id);
        $chk->execute();
        if (!$chk->get_result()->fetch_row()) {
            jsonOut(['ok' => false, 'error' => 'Onbekende staf.'], 400);
        }
        if (array_key_exists('status', $body)) {
            $status = normalizeStaffStatus((string) $body['status']);
            $upd = $mysqli->prepare('UPDATE staff_members SET first_name=?, last_name=?, role=?, status=?, updated_at=NOW() WHERE id=?');
            $upd->bind_param('ssssi', $first, $last, $role, $status, $id);
        } else {
            $upd = $mysqli->prepare('UPDATE staff_members SET first_name=?, last_name=?, role=?, updated_at=NOW() WHERE id=?');
            $upd->bind_param('sssi', $first, $last, $role, $id);
        }
        $upd->execute();
    } else {
        $status = normalizeStaffStatus((string) ($body['status'] ?? 'active'));
        $ins = $mysqli->prepare("INSERT INTO staff_members (first_name, last_name, role, status, created_at, updated_at) VALUES (?,?,?,?,NOW(),NOW())");
        $ins->bind_param('ssss', $first, $last, $role, $status);
        if (!$ins->execute()) {
            jsonOut(['ok' => false, 'error' => 'Kon staf niet toevoegen.'], 400);
        }
        $id = (int) $mysqli->insert_id;
    }
    jsonOut(['ok' => true, 'id' => $id]);
}

if ($action === 'set_staff_status') {
    requireEditor($body);
    $id = (int) ($body['id'] ?? 0);
    $status = normalizeStaffStatus((string) ($body['status'] ?? 'inactive'));
    if ($id < 1) {
        jsonOut(['ok' => false, 'error' => 'Kies een staflid.'], 400);
    }
    $upd = $mysqli->prepare('UPDATE staff_members SET status=?, updated_at=NOW() WHERE id=?');
    $upd->bind_param('si', $status, $id);
    $upd->execute();
    jsonOut(['ok' => true, 'status' => $status]);
}

if ($action === 'restore_type') {
    requireEditor($body);
    $id = (int) ($body['id'] ?? 0);
    if ($id < 1) {
        jsonOut(['ok' => false, 'error' => 'Onbekend item.'], 400);
    }
    $upd = $mysqli->prepare('UPDATE clothing_types SET active=1, updated_at=NOW() WHERE id=?');
    $upd->bind_param('i', $id);
    $upd->execute();
    jsonOut(['ok' => true]);
}

jsonOut(['ok' => false, 'error' => 'Onbekende actie.'], 400);
