<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';

$mysqli = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
if ($mysqli->connect_error) {
    http_response_code(500);
    exit('Database niet bereikbaar');
}
$mysqli->set_charset('utf8mb4');

function h(?string $v): string {
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function euro(?float $n): string {
    if ($n === null) {
        return '—';
    }
    return '€ ' . number_format($n, 2, ',', '.');
}

function normName(string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8');
    $map = ['à'=>'a','á'=>'a','ä'=>'a','â'=>'a','è'=>'e','é'=>'e','ë'=>'e','ê'=>'e','ì'=>'i','í'=>'i','ï'=>'i','ò'=>'o','ó'=>'o','ö'=>'o','ù'=>'u','ú'=>'u','ü'=>'u','ñ'=>'n','ç'=>'c','ÿ'=>'y'];
    $s = strtr($s, $map);
    return preg_replace('/[^a-z]/', '', $s) ?? $s;
}

function playerKey(array $p): string {
    return normName(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
}

function fullName(array $p): string {
    return trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
}

function priceFor(array $t, ?string $size): ?float {
    $pick = static function ($v): ?float {
        if ($v === null || $v === '') {
            return null;
        }
        return (float) $v;
    };
    $standard = $pick($t['price'] ?? null);
    $small = $pick($t['price_small'] ?? null) ?? $standard;
    $large = $pick($t['price_large'] ?? null) ?? $standard;
    $size = strtoupper(trim((string) $size));
    if ($size === '') {
        return $small ?? $standard ?? $large;
    }
    $smallSizes = ['XS','XXS','XXXS','32','33','34','35','36','37','38','39','40','41','42','140','152','164','36-40'];
    if (in_array($size, $smallSizes, true) || preg_match('/^(1[2-6]4|140|152|176)$/', $size)) {
        return $small;
    }
    return $large;
}

function scoutLineFromPos(?string $pos): string {
    $p = strtoupper(trim((string) $pos));
    if ($p === '' ) {
        return '';
    }
    if ($p === 'K' || $p === 'GK') {
        return 'goalkeeper';
    }
    if (in_array($p, ['ST', 'LV', 'RV', 'LW', 'RW', 'CF'], true)) {
        return 'attacker';
    }
    if (in_array($p, ['CAM', 'CDM', 'CM', 'LM', 'RM'], true)) {
        return 'midfielder';
    }
    if (in_array($p, ['CV', 'LA', 'RA', 'CB', 'LB', 'RB'], true)) {
        return 'defender';
    }
    return 'midfielder';
}

function loadScoutPortal(): array {
    $file = '/var/www/knvb-scouting-portal/.data/portal.json';
    $byId = [];
    $byName14 = [];
    if (!is_readable($file)) {
        return ['byId' => [], 'byName14' => []];
    }
    $raw = json_decode((string) file_get_contents($file), true);
    foreach (($raw['knvb_team'] ?? []) as $sp) {
        $id = (int) ($sp['id'] ?? 0);
        $naam = trim((string) ($sp['naam'] ?? ''));
        if ($id < 1 || $naam === '') {
            continue;
        }
        $rec = [
            'id' => $id,
            'naam' => $naam,
            'pos' => (string) ($sp['pos'] ?? ''),
            'type' => (string) ($sp['typeSpeler'] ?? ''),
            'voet' => (string) ($sp['voet'] ?? ''),
            'jaar' => (string) ($sp['jaar'] ?? ''),
            'huidig' => trim((string) ($sp['huidig'] ?? '')),
        ];
        $byId[$id] = $rec;
        if ($rec['huidig'] !== '14-2') {
            continue;
        }
        $key = normName($naam);
        if (isset($byName14[$key]) && (int) $byName14[$key]['id'] < $id) {
            continue;
        }
        $byName14[$key] = $rec;
    }
    return ['byId' => $byId, 'byName14' => $byName14];
}

function findScoutForPlayer(array $p, array $portal): ?array {
    $sid = (int) ($p['scout_id'] ?? 0);
    if ($sid > 0 && isset($portal['byId'][$sid])) {
        return $portal['byId'][$sid];
    }
    $key = playerKey($p);
    if (isset($portal['byName14'][$key])) {
        return $portal['byName14'][$key];
    }
    $last = normName((string) ($p['last_name'] ?? ''));
    $first = normName((string) ($p['first_name'] ?? ''));
    $cands = [];
    foreach ($portal['byName14'] as $rec) {
        $parts = preg_split('/\s+/', trim($rec['naam'])) ?: [];
        $rf = normName((string) array_shift($parts));
        $rl = normName(implode(' ', $parts));
        if ($rf === $first && ($rl === $last || str_starts_with($rl, $last) || str_starts_with($last, $rl))) {
            $cands[] = $rec;
        }
    }
    return count($cands) === 1 ? $cands[0] : null;
}

function ensureScoutIdColumn(mysqli $db): void {
    $r = $db->query("SHOW COLUMNS FROM players LIKE 'scout_id'");
    if ($r && $r->num_rows > 0) {
        return;
    }
    $db->query('ALTER TABLE players ADD COLUMN scout_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER id');
    $db->query('ALTER TABLE players ADD UNIQUE KEY players_scout_id (scout_id)');
}

function syncPlayersFromScout(mysqli $db, array &$players, array $portal): int {
    ensureScoutIdColumn($db);
    $changed = 0;
    $upd = $db->prepare('UPDATE players SET scout_id=?, position=?, updated_at=NOW() WHERE id=?');
    foreach ($players as $id => &$p) {
        if (($p['status'] ?? '') !== 'active' || (int) ($p['is_guest'] ?? 0) === 1) {
            continue;
        }
        $info = findScoutForPlayer($p, $portal);
        if (!$info) {
            continue;
        }
        $line = scoutLineFromPos($info['pos']);
        $scoutId = (int) $info['id'];
        $curSid = (int) ($p['scout_id'] ?? 0);
        $curPos = (string) ($p['position'] ?? '');
        if ($curSid === $scoutId && ($line === '' || $curPos === $line)) {
            $p['scout_id'] = $scoutId;
            if ($line !== '') {
                $p['position'] = $line;
            }
            continue;
        }
        $pos = $line !== '' ? $line : $curPos;
        $upd->bind_param('isi', $scoutId, $pos, $id);
        $upd->execute();
        $p['scout_id'] = $scoutId;
        $p['position'] = $pos;
        $changed++;
    }
    unset($p);
    return $changed;
}

function sizeOptions(int $tid): array {
    $youth = ['140', '152', '164', '176'];
    $adult = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
    $socks = ['31-35', '36-40', '41-44', '45-47'];
    return match ($tid) {
        1, 4 => array_merge($youth, $adult),
        3, 7, 10 => $socks,
        9, 11, 12 => $adult,
        default => array_values(array_unique(array_merge($youth, $adult, $socks))),
    };
}

function ensureParentTokenColumn(mysqli $db): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $r = $db->query("SHOW COLUMNS FROM players LIKE 'parent_token'");
    if ($r && $r->num_rows > 0) {
        return;
    }
    $db->query('ALTER TABLE players ADD COLUMN parent_token CHAR(48) NULL DEFAULT NULL AFTER scout_id');
    $db->query('ALTER TABLE players ADD UNIQUE KEY players_parent_token (parent_token)');
}

function newParentToken(): string {
    return bin2hex(random_bytes(24));
}

function parentLinkUrl(string $token): string {
    return 'https://team.app1x.online/ouder.php?t=' . $token;
}

function parentMessage(string $name, string $url): string {
    return "Hoi, voor de kleding van 14-2 kun je de maten van {$name} invullen via deze link:\n{$url}";
}

function parentWhatsAppUrl(string $name, string $url): string {
    return 'https://wa.me/?text=' . rawurlencode(parentMessage($name, $url));
}

function parentFormPath(): string {
    return __DIR__ . '/.data/parent-form.json';
}

function parentTypeChoices(string $kind): array {
    return $kind === 'keeper' ? [9, 4, 10, 11, 12] : [1, 4, 3, 7, 11, 12];
}

function allParentTypeIds(): array {
    return [1, 4, 3, 7, 9, 10, 11, 12];
}

function shortTypeName(int $tid, array $types = []): string {
    return match ($tid) {
        1 => 'Shirt',
        4 => 'Broek',
        3 => 'Sokken',
        7 => 'Grip',
        9 => 'K-shirt',
        10 => 'K-sokken',
        11 => 'Polo',
        12 => 'Zip',
        default => (string) ($types[$tid]['display_name'] ?? $tid),
    };
}

function normalizeParentTypeIds(array $ids, array $allowed): array {
    $out = [];
    foreach ($ids as $id) {
        $id = (int) $id;
        if (in_array($id, $allowed, true)) {
            $out[$id] = $id;
        }
    }
    return array_values($out);
}

function defaultParentFormSettings(): array {
    return [
        'note' => '',
        'field' => [1, 4, 3, 7],
        'keeper' => [9, 4, 10],
        'players' => [],
    ];
}

function loadParentFormSettings(bool $reload = false): array {
    static $cached = null;
    if ($reload) {
        $cached = null;
    }
    if ($cached !== null) {
        return $cached;
    }
    $def = defaultParentFormSettings();
    $file = parentFormPath();
    if (!is_readable($file)) {
        return $cached = $def;
    }
    $raw = json_decode((string) file_get_contents($file), true);
    if (!is_array($raw)) {
        return $cached = $def;
    }
    $settings = $def;
    $settings['note'] = mb_substr(trim((string) ($raw['note'] ?? '')), 0, 280);
    $field = normalizeParentTypeIds($raw['field'] ?? $def['field'], parentTypeChoices('field'));
    $keeper = normalizeParentTypeIds($raw['keeper'] ?? $def['keeper'], parentTypeChoices('keeper'));
    $settings['field'] = $field !== [] ? $field : $def['field'];
    $settings['keeper'] = $keeper !== [] ? $keeper : $def['keeper'];
    $players = [];
    foreach (($raw['players'] ?? []) as $pid => $ids) {
        if (!is_array($ids)) {
            continue;
        }
        $pid = (int) $pid;
        if ($pid < 1) {
            continue;
        }
        $norm = normalizeParentTypeIds($ids, allParentTypeIds());
        if ($norm !== []) {
            $players[$pid] = $norm;
        }
    }
    $settings['players'] = $players;
    return $cached = $settings;
}

function saveParentFormSettings(array $settings): void {
    $dir = dirname(parentFormPath());
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $field = normalizeParentTypeIds($settings['field'] ?? [], parentTypeChoices('field'));
    $keeper = normalizeParentTypeIds($settings['keeper'] ?? [], parentTypeChoices('keeper'));
    $def = defaultParentFormSettings();
    $players = [];
    foreach (($settings['players'] ?? []) as $pid => $ids) {
        $pid = (int) $pid;
        if ($pid < 1 || !is_array($ids)) {
            continue;
        }
        $norm = normalizeParentTypeIds($ids, allParentTypeIds());
        if ($norm !== []) {
            $players[$pid] = $norm;
        }
    }
    $clean = [
        'note' => mb_substr(trim((string) ($settings['note'] ?? '')), 0, 280),
        'field' => $field !== [] ? $field : $def['field'],
        'keeper' => $keeper !== [] ? $keeper : $def['keeper'],
        'players' => $players,
    ];
    file_put_contents(parentFormPath(), json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    loadParentFormSettings(true);
}

function parentDefaultTypeIds(array $p): array {
    $settings = loadParentFormSettings();
    return (($p['position'] ?? '') === 'goalkeeper') ? $settings['keeper'] : $settings['field'];
}

function parentAllowedTypeIds(array $p): array {
    $settings = loadParentFormSettings();
    $pid = (int) ($p['id'] ?? 0);
    if ($pid > 0 && isset($settings['players'][$pid])) {
        return $settings['players'][$pid];
    }
    return parentDefaultTypeIds($p);
}

function parentUsesCustomTypes(array $p): bool {
    $settings = loadParentFormSettings();
    return isset($settings['players'][(int) ($p['id'] ?? 0)]);
}

function ensureParentSavedAtColumn(mysqli $db): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $r = $db->query("SHOW COLUMNS FROM players LIKE 'parent_saved_at'");
    if ($r && $r->num_rows > 0) {
        return;
    }
    $db->query('ALTER TABLE players ADD COLUMN parent_saved_at DATETIME NULL DEFAULT NULL AFTER parent_token');
}

function findPlayerByParentToken(mysqli $db, string $token): ?array {
    $token = strtolower(trim($token));
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
        return null;
    }
    ensureParentTokenColumn($db);
    $st = $db->prepare("SELECT * FROM players WHERE parent_token=? AND status='active' LIMIT 1");
    $st->bind_param('s', $token);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if (!$row) {
        return null;
    }
    $row['id'] = (int) $row['id'];
    return $row;
}

function playerParentToken(mysqli $db, int $playerId, bool $rotate = false): string {
    ensureParentTokenColumn($db);
    $chk = $db->prepare('SELECT id, parent_token FROM players WHERE id=? AND status=? LIMIT 1');
    $status = 'active';
    $chk->bind_param('is', $playerId, $status);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    if (!$row) {
        throw new RuntimeException('Speler niet gevonden');
    }
    if (!$rotate && !empty($row['parent_token'])) {
        return (string) $row['parent_token'];
    }
    for ($i = 0; $i < 6; $i++) {
        $token = newParentToken();
        $upd = $db->prepare('UPDATE players SET parent_token=?, updated_at=NOW() WHERE id=?');
        $upd->bind_param('si', $token, $playerId);
        if ($upd->execute()) {
            return $token;
        }
        if ((int) $db->errno !== 1062) {
            throw new RuntimeException('Kon geen ouderlink maken');
        }
    }
    throw new RuntimeException('Kon geen ouderlink maken');
}

function parentSaveBlocked(?string &$untilHuman = null): bool {
    $file = __DIR__ . '/.data/parent-save-lock.json';
    if (!is_readable($file)) {
        return false;
    }
    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data)) {
        return false;
    }
    $row = $data[clientIp()] ?? null;
    if (!is_array($row)) {
        return false;
    }
    $until = (int) ($row['until'] ?? 0);
    if ($until > time()) {
        $untilHuman = date('H:i', $until);
        return true;
    }
    return false;
}

function registerParentSave(): void {
    $file = __DIR__ . '/.data/parent-save-lock.json';
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $data = [];
    if (is_readable($file)) {
        $parsed = json_decode((string) file_get_contents($file), true);
        $data = is_array($parsed) ? $parsed : [];
    }
    $ip = clientIp();
    $row = $data[$ip] ?? ['n' => 0, 'window' => time(), 'until' => 0];
    $window = (int) ($row['window'] ?? 0);
    $n = (int) ($row['n'] ?? 0);
    if (time() - $window > 600) {
        $window = time();
        $n = 0;
    }
    $n++;
    $until = (int) ($row['until'] ?? 0);
    if ($n >= 25) {
        $until = time() + 15 * 60;
        $n = 0;
        $window = time();
    }
    $data[$ip] = ['n' => $n, 'window' => $window, 'until' => $until, 't' => time()];
    file_put_contents($file, json_encode($data), LOCK_EX);
}

function startTeamSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
    session_name('team142');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 12,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrfToken(): string {
    startTeamSession();
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function canEdit(): bool {
    startTeamSession();
    return !empty($_SESSION['edit']) && $_SESSION['edit'] === true;
}

function lockPath(): string {
    return __DIR__ . '/.data/pin-lock.json';
}

function clientIp(): string {
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0');
    return preg_replace('/[^0-9a-fA-F:.]/', '', $ip) ?: '0';
}

function readLock(): array {
    $file = lockPath();
    if (!is_readable($file)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function writeLock(array $data): void {
    $dir = dirname(lockPath());
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    file_put_contents(lockPath(), json_encode($data), LOCK_EX);
}

function pinBlocked(?string &$untilHuman = null): bool {
    $lock = readLock();
    $row = $lock[clientIp()] ?? null;
    if (!is_array($row)) {
        return false;
    }
    $until = (int) ($row['until'] ?? 0);
    if ($until > time()) {
        $untilHuman = date('H:i', $until);
        return true;
    }
    return false;
}

function registerPinFail(): void {
    $lock = readLock();
    $ip = clientIp();
    $row = $lock[$ip] ?? ['fails' => 0, 'until' => 0];
    $fails = (int) ($row['fails'] ?? 0) + 1;
    $until = 0;
    if ($fails >= 8) {
        $until = time() + 15 * 60;
        $fails = 0;
    }
    $lock[$ip] = ['fails' => $fails, 'until' => $until, 't' => time()];
    writeLock($lock);
}

function clearPinFail(): void {
    $lock = readLock();
    unset($lock[clientIp()]);
    writeLock($lock);
}

function tryLogin(string $pin, string $hash): array {
    $until = null;
    if (pinBlocked($until)) {
        return ['ok' => false, 'error' => 'Te veel pogingen. Probeer na ' . $until . ' opnieuw.'];
    }
    $pin = trim($pin);
    if ($pin === '' || $hash === '' || !password_verify($pin, $hash)) {
        registerPinFail();
        return ['ok' => false, 'error' => 'Pincode onjuist.'];
    }
    clearPinFail();
    startTeamSession();
    session_regenerate_id(true);
    $_SESSION['edit'] = true;
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return ['ok' => true, 'csrf' => $_SESSION['csrf']];
}

function logoutEdit(): void {
    startTeamSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'] ?? '/', $p['domain'] ?? '', (bool) ($p['secure'] ?? false), (bool) ($p['httponly'] ?? true));
    }
    session_destroy();
}

function sanitizeSize(string $size): string {
    $size = trim($size);
    if (strlen($size) > 20) {
        $size = substr($size, 0, 20);
    }
    if ($size !== '' && !preg_match('/^[A-Za-z0-9][A-Za-z0-9 +\-\/]*$/', $size)) {
        return '';
    }
    return $size;
}

function loadTypes(mysqli $db): array {
    $types = [];
    $res = $db->query('SELECT * FROM clothing_types ORDER BY id');
    while ($row = $res->fetch_assoc()) {
        $types[(int) $row['id']] = $row;
    }
    return $types;
}

function upsertPersonItem(
    mysqli $db,
    array $types,
    string $who,
    int $personId,
    int $typeId,
    string $size,
    string $mode
): void {
    if (!isset($types[$typeId])) {
        throw new RuntimeException('Onbekend kledingtype');
    }
    if ($who === 'player') {
        $chk = $db->prepare('SELECT id FROM players WHERE id=? LIMIT 1');
        $chk->bind_param('i', $personId);
        $table = 'player_clothing';
        $fk = 'player_id';
    } elseif ($who === 'staff') {
        $chk = $db->prepare('SELECT id FROM staff_members WHERE id=? LIMIT 1');
        $chk->bind_param('i', $personId);
        $table = 'staff_clothing';
        $fk = 'staff_member_id';
    } else {
        throw new RuntimeException('Ongeldig type');
    }
    $chk->execute();
    if (!$chk->get_result()->fetch_row()) {
        throw new RuntimeException('Persoon niet gevonden');
    }

    $size = sanitizeSize($size);
    if ($size === '') {
        $del = $db->prepare("DELETE FROM {$table} WHERE {$fk}=? AND clothing_type_id=?");
        $del->bind_param('ii', $personId, $typeId);
        $del->execute();
        return;
    }

    $t = $types[$typeId];
    $price = priceFor($t, $size) ?? 0.0;
    $color = (string) ($t['color'] ?? '');
    $brand = (string) ($t['brand'] ?? '');

    $sel = $db->prepare("SELECT id, status FROM {$table} WHERE {$fk}=? AND clothing_type_id=? ORDER BY id ASC");
    $sel->bind_param('ii', $personId, $typeId);
    $sel->execute();
    $rows = $sel->get_result()->fetch_all(MYSQLI_ASSOC);
    $existing = $rows[0] ?? null;
    $keepId = $existing ? (int) $existing['id'] : 0;
    $currentStatus = (string) ($existing['status'] ?? '');

    if ($mode === 'active') {
        $status = 'active';
    } elseif ($currentStatus === 'active') {
        $status = 'active';
    } else {
        $status = 'pending';
    }

    if ($keepId) {
        if ($table === 'player_clothing') {
            $upd = $db->prepare('UPDATE player_clothing SET size=?, color=?, brand=?, price=?, status=?, updated_at=NOW() WHERE id=?');
            $upd->bind_param('sssdsi', $size, $color, $brand, $price, $status, $keepId);
        } else {
            $upd = $db->prepare('UPDATE staff_clothing SET size=?, color=?, brand=?, status=?, updated_at=NOW() WHERE id=?');
            $upd->bind_param('ssssi', $size, $color, $brand, $status, $keepId);
        }
        $upd->execute();
        return;
    }

    if ($table === 'player_clothing') {
        $ins = $db->prepare('INSERT INTO player_clothing (player_id, clothing_type_id, size, color, brand, price, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())');
        $ins->bind_param('iisssds', $personId, $typeId, $size, $color, $brand, $price, $status);
    } else {
        $ins = $db->prepare('INSERT INTO staff_clothing (staff_member_id, clothing_type_id, size, brand, color, status, created_at, updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW())');
        $ins->bind_param('iissss', $personId, $typeId, $size, $brand, $color, $status);
    }
    $ins->execute();
}

function itemFor(array $p, int $tid): ?array {
    $list = $p['items'][$tid] ?? [];
    return $list[0] ?? null;
}

function itemStatus(?array $it): string {
    return strtolower(trim((string) ($it['status'] ?? '')));
}

function isIssued(?array $it): bool {
    return $it !== null && itemStatus($it) === 'active';
}

function isPendingItem(?array $it): bool {
    return $it !== null && itemStatus($it) === 'pending';
}

function sizeSelect(int $tid, string $current, string $who, int $id, bool $na = false): string {
    if ($na) {
        return '<span class="muted">n.v.t.</span>';
    }
    $opts = sizeOptions($tid);
    if ($current !== '' && !in_array($current, $opts, true)) {
        array_unshift($opts, $current);
    }
    $copy = '';
    if ($tid === 4) {
        $copy = ' data-copy-from="1"';
    } elseif ($tid === 7) {
        $copy = ' data-copy-from="3"';
    }
    $html = '<select class="size-select" data-who="'.h($who).'" data-id="'.$id.'" data-tid="'.$tid.'"'.$copy.'>';
    $html .= '<option value="">—</option>';
    foreach ($opts as $o) {
        $sel = $o === $current ? ' selected' : '';
        $html .= '<option value="'.h($o).'"'.$sel.'>'.h($o).'</option>';
    }
    $html .= '</select>';
    return $html;
}

ensureParentTokenColumn($mysqli);
ensureParentSavedAtColumn($mysqli);
startTeamSession();
$canEdit = canEdit();
$csrf = csrfToken();
