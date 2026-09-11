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

function assetUrl(string $file): string {
    $path = __DIR__ . '/' . ltrim($file, '/');
    $v = is_file($path) ? (string) filemtime($path) : (string) time();
    return h($file) . '?v=' . $v;
}

function euro(?float $n): string {
    if ($n === null) {
        return '—';
    }
    return '€ ' . number_format($n, 2, ',', '.');
}

function vatRate(): float {
    return 0.21;
}

function withVat(?float $n): ?float {
    if ($n === null) {
        return null;
    }
    return round($n * (1 + vatRate()), 2);
}

function euroIncl(?float $n): string {
    $v = withVat($n);
    return $v === null ? '—' : euro($v) . ' incl. btw';
}

function euroPair(?float $n): string {
    if ($n === null) {
        return '—';
    }
    return euro($n) . ' · ' . euro(withVat($n)) . ' incl.';
}

function withoutVat(?float $n): ?float {
    if ($n === null) {
        return null;
    }
    return round($n / (1 + vatRate()), 2);
}

/**
 * Webshopprijzen zoals op Stanno.com en Teamswear.nl (incl. 21% btw), sept. 2026.
 * stanno.nl en teamwears.nl bestaan niet; dit zijn de live shops.
 *
 * @return array<string, array{stanno_jr:?float,stanno_sr:?float,teamwear_jr:?float,teamwear_sr:?float,stanno_url:string,teamwear_url:string}>
 */
function shopComparePrices(): array {
    return [
        '410014' => [
            'stanno_jr' => 23.99, 'stanno_sr' => 25.99,
            'teamwear_jr' => 16.77, 'teamwear_sr' => 18.16,
            'stanno_url' => 'https://www.stanno.com/nl/410014-bolt-t-shirt/',
            'teamwear_url' => 'https://www.teamswear.nl/stanno-bolt-shirt-korte-mouw-heren-zwart',
        ],
        '440001' => [
            'stanno_jr' => 12.99, 'stanno_sr' => 12.99,
            'teamwear_jr' => 9.06, 'teamwear_sr' => 9.06,
            'stanno_url' => 'https://www.stanno.com/nl/440001-uni-ii-sock/',
            'teamwear_url' => 'https://www.teamswear.nl/stanno-uni-sock-ii-voetbalkousen-wit',
        ],
        '420004' => [
            'stanno_jr' => 19.99, 'stanno_sr' => 19.99,
            'teamwear_jr' => 13.96, 'teamwear_sr' => 13.96,
            'stanno_url' => 'https://www.stanno.com/nl/420004-focus-shorts-ii/',
            'teamwear_url' => 'https://www.teamswear.nl/stanno-focus-ii-short-heren-zwart',
        ],
        '444007' => [
            'stanno_jr' => 14.99, 'stanno_sr' => 14.99,
            'teamwear_jr' => 10.46, 'teamwear_sr' => 10.46,
            'stanno_url' => 'https://www.stanno.com/nl/444007-raw-crew-socks/',
            'teamwear_url' => 'https://www.teamswear.nl/stanno-raw-crew-gripsokken-zwart-wit',
        ],
        '440125' => [
            'stanno_jr' => 13.99, 'stanno_sr' => 13.99,
            'teamwear_jr' => 9.77, 'teamwear_sr' => 9.77,
            'stanno_url' => 'https://www.stanno.com/nl/440125-uni-pro-sock/',
            'teamwear_url' => 'https://www.teamswear.nl/stanno-uni-pro-voetbalkousen-zwart',
        ],
        '463003' => [
            'stanno_jr' => 23.50, 'stanno_sr' => 25.50,
            'teamwear_jr' => 16.45, 'teamwear_sr' => 17.85,
            'stanno_url' => 'https://www.stanno.com/nl/463003-field-polo/',
            'teamwear_url' => 'https://www.teamswear.nl/stanno-field-polo-heren-zwart',
        ],
        '408038' => [
            'stanno_jr' => 41.99, 'stanno_sr' => 44.99,
            'teamwear_jr' => 29.37, 'teamwear_sr' => 31.47,
            'stanno_url' => 'https://www.stanno.com/nl/408038-bolt-quarter-zip-top/',
            'teamwear_url' => 'https://www.teamswear.nl/stanno-bolt-ziptop-heren-zwart',
        ],
        '454002' => [
            'stanno_jr' => 35.50, 'stanno_sr' => 37.50,
            'teamwear_jr' => 24.85, 'teamwear_sr' => 26.25,
            'stanno_url' => 'https://www.stanno.com/nl/454002-field-jack/',
            'teamwear_url' => 'https://www.teamswear.nl/stanno-field-regenjas-heren-zwart',
        ],
        '456004' => [
            'stanno_jr' => 89.99, 'stanno_sr' => 94.99,
            'teamwear_jr' => 59.47, 'teamwear_sr' => 66.47,
            'stanno_url' => 'https://www.stanno.com/nl/456004-prime-padded-jacket/',
            'teamwear_url' => 'https://www.teamswear.nl/stanno-prime-padded-coach-jacket-heren-marine',
        ],
        '484837' => [
            'stanno_jr' => 43.99, 'stanno_sr' => 43.99,
            'teamwear_jr' => 30.77, 'teamwear_sr' => 30.77,
            'stanno_url' => 'https://www.stanno.com/nl/484837-pro-bag-prime/',
            'teamwear_url' => 'https://www.teamswear.nl/stanno-pro-prime-sporttas-met-bodemvak-zwart',
        ],
        '484838' => [
            'stanno_jr' => 41.99, 'stanno_sr' => 41.99,
            'teamwear_jr' => 29.37, 'teamwear_sr' => 29.37,
            'stanno_url' => 'https://www.stanno.com/nl/484838-pro-prime-backpack/',
            'teamwear_url' => 'https://www.teamswear.nl/stanno-pro-prime-multifunctionele-rugzak-met-bodemvak-zwart',
        ],
        '415007' => [
            'stanno_jr' => 54.99, 'stanno_sr' => 59.99,
            'teamwear_jr' => 38.47, 'teamwear_sr' => 41.97,
            'stanno_url' => 'https://www.stanno.com/nl/415007-trick-long-sleeve-goalkeeper-set/',
            'teamwear_url' => 'https://www.teamswear.nl/stanno-trick-keeperstenue-heren-geel',
        ],
        '444004' => [
            'stanno_jr' => 9.99, 'stanno_sr' => 9.99,
            'teamwear_jr' => 6.96, 'teamwear_sr' => 6.96,
            'stanno_url' => 'https://www.stanno.com/nl/444004-move-footless-socks/',
            'teamwear_url' => 'https://www.teamswear.nl/stanno-move-voetbalkousen-voetloos-wit',
        ],
        '425105' => [
            'stanno_jr' => 36.99, 'stanno_sr' => 41.99,
            'teamwear_jr' => 25.87, 'teamwear_sr' => 29.37,
            'stanno_url' => 'https://www.stanno.com/nl/425105-bounce-goalkeeper-pants/',
            'teamwear_url' => 'https://www.teamswear.nl/voetbal/keeperskleding/keepersbroeken/stanno',
        ],
    ];
}

function shopArticleKey(?string $article): string {
    if (!preg_match('/(\d{6})/', (string) $article, $m)) {
        return '';
    }
    return $m[1];
}

function shopCompareForArticle(?string $article): ?array {
    $key = shopArticleKey($article);
    if ($key === '') {
        return null;
    }
    return shopComparePrices()[$key] ?? null;
}

function shopPriceCell(?float $jrIncl, ?float $srIncl, string $url = ''): string {
    if ($jrIncl === null && $srIncl === null) {
        return '<span class="muted">—</span>';
    }
    $same = $jrIncl !== null && $srIncl !== null && abs($jrIncl - $srIncl) < 0.005;
    if ($same) {
        $line = euro($jrIncl);
        $hint = euro(withoutVat($jrIncl)) . ' excl.';
    } else {
        $line = 'JR ' . euro($jrIncl) . '<br>SR ' . euro($srIncl);
        $hint = 'JR ' . euro(withoutVat($jrIncl)) . ' · SR ' . euro(withoutVat($srIncl)) . ' excl.';
    }
    $html = '<span class="shop-cmp">' . $line . '</span><small class="vat-hint">' . h($hint) . '</small>';
    if ($url === '') {
        return $html;
    }
    return '<a class="shop-cmp-link" href="' . h($url) . '" target="_blank" rel="noopener">' . $html . '</a>';
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
    if ($size === '' || isYouthPriceSize($size)) {
        return $small ?? $standard ?? $large;
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
    if ($sid > 0 && isset($portal['byId'][$sid]) && trim((string) ($portal['byId'][$sid]['huidig'] ?? '')) === '14-2') {
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

function playerOnScoutTeam14(array $p, array $portal): bool {
    return findScoutForPlayer($p, $portal) !== null;
}

function archivePlayersNotOnScoutTeam14(mysqli $db, array &$players, array $portal): int {
    $changed = 0;
    $status = 'inactive';
    $upd = $db->prepare('UPDATE players SET status=?, updated_at=NOW() WHERE id=? AND status=\'active\'');
    foreach ($players as $id => &$p) {
        if (($p['status'] ?? '') !== 'active' || (int) ($p['is_guest'] ?? 0) === 1) {
            continue;
        }
        if (playerOnScoutTeam14($p, $portal)) {
            continue;
        }
        $id = (int) $id;
        $upd->bind_param('si', $status, $id);
        $upd->execute();
        $p['status'] = 'inactive';
        $changed++;
    }
    unset($p);
    return $changed;
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

function kitSettingsPath(): string {
    return __DIR__ . '/.data/kit-settings.json';
}

function defaultPlayerPackageIds(): array {
    return [15, 13, 14, 1, 24, fieldShortTypeId(), 7];
}

function defaultKeeperPackageIds(): array {
    return [15, 13, 14, 19, 10, 25];
}

function defaultStaffPackageIds(): array {
    return [14, 23, 11, fieldShortTypeId()];
}

function sanitizeTypeIdList(mixed $raw, array $fallback): array {
    $ids = [];
    foreach (is_array($raw) ? $raw : [] as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    return $ids !== [] ? array_values($ids) : $fallback;
}

function defaultKitSettings(): array {
    return [
        'package' => defaultPlayerPackageIds(),
        'package_keeper' => defaultKeeperPackageIds(),
        'package_staff' => defaultStaffPackageIds(),
        'print' => [
            'rohda' => null,
            'initials' => null,
            'sponsor' => null,
            'sponsor_back' => null,
            'sponsor_padded' => null,
            'sponsor_jacket' => null,
            'sponsor_bag' => null,
            'name_back' => null,
            'staff_text' => null,
        ],
        'season' => '26/27',
    ];
}

function loadKitSettings(bool $reload = false): array {
    static $cached = null;
    if ($reload) {
        $cached = null;
    }
    if ($cached !== null) {
        return $cached;
    }
    $def = defaultKitSettings();
    $file = kitSettingsPath();
    if (!is_readable($file)) {
        return $cached = $def;
    }
    $raw = json_decode((string) file_get_contents($file), true);
    if (!is_array($raw)) {
        return $cached = $def;
    }
    $package = remapShortsInTypeList(sanitizeTypeIdList($raw['package'] ?? null, $def['package']));
    $packageKeeper = sanitizeTypeIdList($raw['package_keeper'] ?? null, $def['package_keeper']);
    $packageStaff = remapShortsInTypeList(sanitizeTypeIdList($raw['package_staff'] ?? null, $def['package_staff']));
    $print = $def['print'];
    foreach (array_keys($print) as $key) {
        $print[$key] = parseMoney($raw['print'][$key] ?? null);
    }
    $season = trim((string) ($raw['season'] ?? $def['season']));
    if ($season === '') {
        $season = $def['season'];
    }
    $cached = [
        'package' => $package,
        'package_keeper' => $packageKeeper,
        'package_staff' => $packageStaff,
        'print' => $print,
        'season' => substr($season, 0, 16),
    ];
    return $cached;
}

function saveKitSettings(array $settings): void {
    $dir = dirname(kitSettingsPath());
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $def = defaultKitSettings();
    $package = remapShortsInTypeList(sanitizeTypeIdList($settings['package'] ?? null, $def['package']));
    $packageKeeper = sanitizeTypeIdList($settings['package_keeper'] ?? null, $def['package_keeper']);
    $packageStaff = remapShortsInTypeList(sanitizeTypeIdList($settings['package_staff'] ?? null, $def['package_staff']));
    $print = [];
    foreach (array_keys($def['print']) as $key) {
        $print[$key] = parseMoney($settings['print'][$key] ?? null);
    }
    $season = trim((string) ($settings['season'] ?? $def['season']));
    if ($season === '') {
        $season = $def['season'];
    }
    $clean = [
        'package' => $package,
        'package_keeper' => $packageKeeper,
        'package_staff' => $packageStaff,
        'print' => $print,
        'season' => substr($season, 0, 16),
    ];
    file_put_contents(kitSettingsPath(), json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    loadKitSettings(true);
}

function packageTypeIds(): array {
    return sanitizeTypeIdList(loadKitSettings()['package'] ?? null, defaultPlayerPackageIds());
}

function staffPackageTypeIds(): array {
    return sanitizeTypeIdList(loadKitSettings()['package_staff'] ?? null, defaultStaffPackageIds());
}

function keeperPackageTypeIds(): array {
    return sanitizeTypeIdList(loadKitSettings()['package_keeper'] ?? null, defaultKeeperPackageIds());
}

function playerIsKeeper(?array $person): bool {
    return ($person['position'] ?? '') === 'goalkeeper';
}

function packageTypeIdsFor(string $who, ?array $person = null): array {
    if ($who === 'staff') {
        return staffPackageTypeIds();
    }
    if ($who === 'keeper' || playerIsKeeper($person)) {
        return keeperPackageTypeIds();
    }
    return packageTypeIds();
}

function fieldShortTypeId(): int {
    return 31;
}

/** Focus-short (4) en Field Short (31) zijn dezelfde broek-plek. */
function shortsSlotTypeIds(): array {
    $ids = [];
    foreach ([4, fieldShortTypeId()] as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

function isShortsType(int $tid): bool {
    return in_array($tid, shortsSlotTypeIds(), true);
}

function packageEquivalentTypeIds(int $tid): array {
    return isShortsType($tid) ? shortsSlotTypeIds() : [$tid];
}

function remapShortsInTypeList(array $ids): array {
    $out = [];
    foreach ($ids as $id) {
        $id = (int) $id;
        if (isShortsType($id)) {
            $id = fieldShortTypeId();
        }
        if ($id > 0) {
            $out[$id] = $id;
        }
    }
    return array_values($out);
}

function itemFillRank(?array $it): int {
    if (isIssued($it)) {
        return 3;
    }
    if (isPendingItem($it)) {
        return 2;
    }
    if (isHeldItem($it) || itemStatus($it) === 'nvt') {
        return 1;
    }
    return 0;
}

function packageColumnTid(array $person, int $tid, string $who): int {
    $cands = packageEquivalentTypeIds($tid);
    if (count($cands) < 2) {
        return $tid;
    }
    $prefer = fieldShortTypeId();
    $bestTid = in_array($prefer, $cands, true) ? $prefer : $tid;
    $bestRank = -1;
    foreach ($cands as $cid) {
        $rank = itemFillRank(itemFor($person, $cid));
        if ($rank > $bestRank || ($rank === $bestRank && $cid === $prefer)) {
            $bestRank = $rank;
            $bestTid = $cid;
        }
    }
    return $bestTid;
}

function personChoseSkip(array $person, int $tid, string $who): bool {
    $it = itemFor($person, $tid);
    return isHeldItem($it) || itemStatus($it) === 'nvt';
}

function personHasType(array $person, int $tid): bool {
    return itemFor($person, $tid) !== null;
}

function packageSlotIsFilled(array $person, int $tid, string $who): bool {
    $live = false;
    $skip = false;
    foreach (packageEquivalentTypeIds($tid) as $cid) {
        $it = itemFor($person, $cid);
        if (isIssued($it) || isPendingItem($it)) {
            $live = true;
        }
        if (isHeldItem($it) || itemStatus($it) === 'nvt') {
            $skip = true;
        }
    }
    return $live || $skip;
}

function packageMissingIds(array $person, string $who): array {
    $miss = [];
    $seen = [];
    $types = rememberTypes();
    foreach (personFormTypeIdSet($person, $who) as $tid) {
        $tid = (int) $tid;
        if ($tid < 1 || !isset($types[$tid])) {
            continue;
        }
        $key = implode(',', packageEquivalentTypeIds($tid));
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        if (packageSlotIsFilled($person, $tid, $who)) {
            continue;
        }
        $miss[] = packageColumnTid($person, $tid, $who);
    }
    return $miss;
}

function collapseCardTypeIds(array $ids, array $person): array {
    $keepShort = null;
    foreach ($ids as $tid) {
        $tid = (int) $tid;
        if (!isShortsType($tid)) {
            continue;
        }
        $keepShort = packageColumnTid($person, $tid, 'player');
        break;
    }
    if ($keepShort === null) {
        return array_values($ids);
    }
    $out = [];
    foreach ($ids as $tid) {
        $tid = (int) $tid;
        if (isShortsType($tid) && $tid !== $keepShort) {
            continue;
        }
        $out[] = $tid;
    }
    return $out;
}

/** Pakketkolommen plus extra items die iemand écht heeft (bestellen, in bezit of n.v.t.). */
function packOverviewTypeIds(array $packageIds, array $people, string $who = 'player'): array {
    $types = rememberTypes();
    $out = [];
    foreach ($packageIds as $tid) {
        $tid = (int) $tid;
        if ($tid > 0) {
            $out[$tid] = $tid;
        }
    }
    foreach ($people as $person) {
        $ids = [];
        foreach (array_keys($person['items'] ?? []) as $tid) {
            $tid = (int) $tid;
            if ($tid < 1 || !itemFor($person, $tid) || !personShowsType($person, $tid, $who)) {
                continue;
            }
            $t = $types[$tid] ?? null;
            if ($t && isPrintCatalogType($t)) {
                continue;
            }
            $ids[] = $tid;
        }
        foreach (collapseCardTypeIds($ids, $person) as $tid) {
            $out[(int) $tid] = (int) $tid;
        }
    }
    return array_values($out);
}

/** Haalt de oude Focus-broek weg als dezelfde persoon al Field Short krijgt. */
function cleanupRedundantShorts(mysqli $db): int {
    $short = fieldShortTypeId();
    $n = 0;
    $sql = "DELETE pc FROM player_clothing pc
            INNER JOIN player_clothing ps
              ON ps.player_id = pc.player_id
             AND ps.clothing_type_id = {$short}
             AND ps.status IN ('pending','active')
            WHERE pc.clothing_type_id = 4 AND pc.status IN ('hold','nvt')";
    if ($db->query($sql)) {
        $n += (int) $db->affected_rows;
    }
    $sql = "DELETE sc FROM staff_clothing sc
            INNER JOIN staff_clothing ss
              ON ss.staff_member_id = sc.staff_member_id
             AND ss.clothing_type_id = {$short}
             AND ss.status IN ('pending','active')
            WHERE sc.clothing_type_id = 4 AND sc.status IN ('hold','nvt')";
    if ($db->query($sql)) {
        $n += (int) $db->affected_rows;
    }
    return $n;
}

function staffShirtTypeId(): int {
    return 23;
}

function isKeeperKitType(array $t): bool {
    $hay = strtolower(trim(
        (string) ($t['display_name'] ?? '') . ' ' .
        (string) ($t['name'] ?? '') . ' ' .
        (string) ($t['description'] ?? '')
    ));
    if ($hay === '' || !preg_match('/keeper|goalkeeper|keepershirt|keepersok|keeperstenue|keepertenue|\bk-shirt\b|\bk-sok/u', $hay)) {
        return false;
    }
    if (trim((string) ($t['article_number'] ?? '')) === '' && (
        typePrints($t, 'print_rohda') || typePrints($t, 'print_initials')
        || typePrints($t, 'print_sponsor') || typePrints($t, 'print_sponsor_back')
        || typePrints($t, 'print_sponsor_padded') || typePrints($t, 'print_sponsor_jacket')
        || typePrints($t, 'print_sponsor_bag')
        || typePrints($t, 'print_name_back') || typePrints($t, 'print_staff_text')
    )) {
        return false;
    }
    return true;
}

function keeperKitTypeHasSet(array $t): bool {
    $hay = strtolower((string) ($t['display_name'] ?? '') . ' ' . (string) ($t['name'] ?? ''));
    return str_contains($hay, 'set') || str_contains($hay, 'tenue') || str_contains($hay, 'stenue');
}

function keeperOnlyTypeIds(?array $types = null): array {
    $types = $types ?? rememberTypes();
    $ids = [9 => 9, 10 => 10];
    foreach ($types as $tid => $t) {
        if (!is_array($t) || !isKeeperKitType($t)) {
            continue;
        }
        $id = (int) ($t['id'] ?? $tid);
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

function keeperCoreTypeIds(?array $types = null): array {
    $types = $types ?? rememberTypes();
    $ids = [];
    $hasSet = false;
    foreach ($types as $tid => $t) {
        if (!is_array($t) || !typeIsActive($t) || !isKeeperKitType($t)) {
            continue;
        }
        $id = (int) ($t['id'] ?? $tid);
        if ($id < 1) {
            continue;
        }
        $ids[$id] = $id;
        if (keeperKitTypeHasSet($t)) {
            $hasSet = true;
        }
    }
    if ($ids === []) {
        return [9, 4, 10];
    }
    if (!$hasSet) {
        $ids[4] = 4;
    }
    return array_values($ids);
}

function fieldOnlyTypeIds(): array {
    return [1, 3];
}

/** Veldspeler krijgt geen keepershirt/-sokken; keepers mogen wel veld- én keeperkleding. */
function typeAllowedForPlayer(array $player, int $tid): bool {
    $isKeeper = ($player['position'] ?? '') === 'goalkeeper';
    if (!$isKeeper && in_array($tid, keeperOnlyTypeIds(), true)) {
        return false;
    }
    return true;
}

/** Haalt keeperskleding weg bij veldspelers. */
function cleanupMismatchedPlayerKit(mysqli $db): int {
    $keeper = implode(',', array_map('intval', keeperOnlyTypeIds())) ?: '9,10';
    $sql = "DELETE pc FROM player_clothing pc
            INNER JOIN players p ON p.id = pc.player_id
            WHERE (p.position IS NULL OR p.position <> 'goalkeeper') AND pc.clothing_type_id IN ({$keeper})";
    if (!$db->query($sql)) {
        return 0;
    }
    return (int) $db->affected_rows;
}

function isPackageType(int $tid): bool {
    return in_array($tid, packageTypeIds(), true)
        || in_array($tid, keeperPackageTypeIds(), true)
        || in_array($tid, staffPackageTypeIds(), true);
}

function rememberTypes(?array $types = null): array {
    static $cached = [];
    if ($types !== null) {
        $cached = $types;
    }
    return $cached;
}

function typeSizeKind(array $t): string {
    $k = strtolower(trim((string) ($t['size_kind'] ?? '')));
    if (in_array($k, ['body', 'socks', 'onesize'], true)) {
        return $k;
    }
    $id = (int) ($t['id'] ?? 0);
    return match ($id) {
        3, 7, 10, 24 => 'socks',
        15 => 'onesize',
        default => 'body',
    };
}

function typeOrderGroup(array $t): string {
    $g = strtolower(trim((string) ($t['order_group'] ?? '')));
    if (in_array($g, ['match', 'package', 'extra'], true)) {
        return $g;
    }
    $id = (int) ($t['id'] ?? 0);
    if (isPackageType($id) || in_array($id, [13, 14, 15], true)) {
        return 'package';
    }
    if (in_array($id, [1, 3, 4, 7, 9, 10], true)) {
        return 'match';
    }
    return 'extra';
}

function sizeOptionsForKind(string $kind): array {
    return match ($kind) {
        'socks' => sockSizes(),
        'onesize' => ['één maat'],
        default => bodySizes(),
    };
}

function bodySizes(): array {
    return ['116', '128', '140', '152', '164', 'S', 'M', 'L', 'XL', '2XL', '3XL'];
}

function sockSizes(): array {
    return ['25/29', '30/35', '36/40', '41/44', '45/48'];
}

function stannoSizeChart(): array {
    $jr3xl = ['116', '128', '140', '152', '164', 'S', 'M', 'L', 'XL', '2XL', '3XL'];
    $jr2xl = ['116', '128', '140', '152', '164', 'S', 'M', 'L', 'XL', '2XL'];
    $socks = ['25/29', '30/35', '36/40', '41/44', '45/48'];
    return [
        '410014' => $jr3xl,
        '420000' => $jr3xl,
        '420004' => $jr3xl,
        '408038' => $jr3xl,
        '463003' => ['116', '128', '140', '152', '164', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'],
        '454002' => $jr2xl,
        '456004' => ['128', '140', '152', '164', 'S', 'M', 'L', 'XL', '2XL', '3XL'],
        '415007' => ['128', '140', '152', '164', 'S', 'M', 'L', 'XL', '2XL'],
        '425105' => $jr2xl,
        '440001' => $socks,
        '440125' => $socks,
        '444007' => ['36/40', '41/44', '45/48'],
        '444004' => ['JR', 'SR'],
        '484837' => ['één maat'],
        '484838' => ['één maat'],
    ];
}

function articleSizeKey(string $article): string {
    $article = trim($article);
    if (preg_match('/^(\d{6})/', $article, $m)) {
        return $m[1];
    }
    return $article;
}

function stannoSizesForArticle(string $article): array {
    $key = articleSizeKey($article);
    return stannoSizeChart()[$key] ?? [];
}

function parseSizeList(mixed $raw): array {
    if (is_array($raw)) {
        $parts = $raw;
    } else {
        $parts = preg_split('/\s*,\s*/', str_replace(['·', ';', '|'], ',', trim((string) $raw))) ?: [];
    }
    $out = [];
    foreach ($parts as $part) {
        $size = normalizeSizeLabel((string) $part);
        if ($size !== '' && !in_array($size, $out, true)) {
            $out[] = $size;
        }
    }
    return $out;
}

function formatSizeList(array $sizes): string {
    return implode(', ', $sizes);
}

function normalizeSizeLabel(string $size): string {
    $size = trim($size);
    if ($size === '') {
        return '';
    }
    $compact = mb_strtolower(str_replace([' ', '–', '—'], ['', '-', '-'], $size), 'UTF-8');
    $map = [
        '36-40' => '36/40', '36/40' => '36/40',
        '41-44' => '41/44', '41/44' => '41/44',
        '45-47' => '45/48', '45-48' => '45/48', '45/47' => '45/48', '45/48' => '45/48',
        '31-35' => '30/35', '30-35' => '30/35', '30/35' => '30/35', '31/35' => '30/35',
        '25-29' => '25/29', '25/29' => '25/29',
        '2xl' => '2XL', 'xxl' => 'XXL',
        '3xl' => '3XL', 'xxxl' => 'XXXL',
        'eenmaat' => 'één maat', 'éénmaat' => 'één maat', 'onesize' => 'één maat',
        'jr' => 'JR', 'sr' => 'SR',
    ];
    if (isset($map[$compact])) {
        return $map[$compact];
    }
    if (preg_match('/^(116|128|140|152|164)$/', $size)) {
        return $size;
    }
    $up = strtoupper($size);
    if (in_array($up, ['S', 'M', 'L', 'XL', 'XXL', 'XXXL', '2XL', '3XL', 'JR', 'SR'], true)) {
        return $up;
    }
    return $size;
}

function sizeOptions(int $tid): array {
    $types = rememberTypes();
    if (isset($types[$tid])) {
        $list = parseSizeList((string) ($types[$tid]['sizes'] ?? ''));
        if ($list !== []) {
            return $list;
        }
        $fromArt = stannoSizesForArticle((string) ($types[$tid]['article_number'] ?? ''));
        if ($fromArt !== []) {
            return $fromArt;
        }
        return sizeOptionsForKind(typeSizeKind($types[$tid]));
    }
    return match ($tid) {
        3, 7, 10 => sockSizes(),
        15 => ['één maat'],
        24 => ['JR', 'SR'],
        default => bodySizes(),
    };
}

function skipSizeToken(): string {
    return '__skip__';
}

function isSkipSize(string $size): bool {
    $size = trim($size);
    return $size === '' || $size === skipSizeToken();
}

function isYouthPriceSize(string $size): bool {
    $size = strtoupper(trim($size));
    if ($size === '') {
        return true;
    }
    $small = ['XS', 'XXS', 'XXXS', '32', '33', '34', '35', '36', '37', '38', '39', '40', '41', '42', '116', '128', '140', '152', '164', 'JR', '25/29', '30/35', '36/40', '25-29', '30-35', '31-35', '36-40'];
    return in_array($size, $small, true) || (bool) preg_match('/^(1[2-6]4|140|152|176)$/', $size);
}

function parseMoney(mixed $v): ?float {
    $s = trim(str_replace(['€', "\u{00A0}", ' '], '', (string) $v));
    if ($s === '') {
        return null;
    }
    $s = str_replace(',', '.', $s);
    if (!is_numeric($s)) {
        return null;
    }
    return round((float) $s, 2);
}

function typePrints(array $t, string $flag): bool {
    return (int) ($t[$flag] ?? 0) === 1;
}

/** Vinkjes in de catalogus: wat er écht op het item zit, in dezelfde woorden als op de foto. */
function printFlagEditorLabels(): array {
    return [
        'print_rohda' => 'Clublogo',
        'print_sponsor' => 'Logo sponsor voorkant',
        'print_sponsor_back' => 'Logo sponsor achterkant',
        'print_sponsor_padded' => 'Logo sponsor padded',
        'print_sponsor_jacket' => 'Logo sponsor regenjas',
        'print_sponsor_bag' => 'Logo sponsor tas',
        'print_initials' => 'Initialen',
        'print_name_back' => 'Nummer',
        'print_staff_text' => 'Tekst staf',
    ];
}

/** Leesbare tags per item, zoals op de pakketfoto. */
function printTagsForType(array $t): array {
    $map = [
        'print_rohda' => 'Clublogo',
        'print_sponsor' => 'Logo sponsor voorkant',
        'print_sponsor_padded' => 'Logo sponsor voorkant',
        'print_sponsor_back' => 'Logo sponsor achterkant',
        'print_sponsor_jacket' => 'Logo sponsor achterkant',
        'print_sponsor_bag' => 'Logo sponsor',
        'print_initials' => 'Initialen',
        'print_name_back' => 'Nummer',
        'print_staff_text' => 'Tekst staf',
    ];
    $tags = [];
    foreach ($map as $flag => $label) {
        if (typePrints($t, $flag)) {
            $tags[] = $label;
        }
    }
    return $tags;
}

function printPlaceFromFlags(array $t): string {
    $tags = printTagsForType($t);
    return $tags !== [] ? implode(' · ', $tags) : 'Geen bedrukking';
}

/** Zet bedrukking gelijk met de kader- en spelersfoto. */
function syncKitPrintFromPhotos(mysqli $db): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $mark = __DIR__ . '/.data/print-photo-v';
    if (is_file($mark) && trim((string) file_get_contents($mark)) === '2') {
        return;
    }
    ensureTypePrintColumns($db);
    ensureSponsorPrintSplit($db);
    ensureStaffTextPrint($db);
    ensureSponsorQuoteKinds($db);
    $rows = [
        1 => ['print_rohda' => 1, 'print_initials' => 1, 'print_sponsor' => 1, 'print_sponsor_back' => 1, 'print_sponsor_padded' => 0, 'print_sponsor_jacket' => 0, 'print_sponsor_bag' => 0, 'print_name_back' => 1, 'print_staff_text' => 0],
        3 => ['print_rohda' => 0, 'print_initials' => 0, 'print_sponsor' => 0, 'print_sponsor_back' => 0, 'print_sponsor_padded' => 0, 'print_sponsor_jacket' => 0, 'print_sponsor_bag' => 0, 'print_name_back' => 0, 'print_staff_text' => 0],
        4 => ['print_rohda' => 0, 'print_initials' => 1, 'print_sponsor' => 0, 'print_sponsor_back' => 0, 'print_sponsor_padded' => 0, 'print_sponsor_jacket' => 0, 'print_sponsor_bag' => 0, 'print_name_back' => 0, 'print_staff_text' => 0],
        7 => ['print_rohda' => 0, 'print_initials' => 0, 'print_sponsor' => 0, 'print_sponsor_back' => 0, 'print_sponsor_padded' => 0, 'print_sponsor_jacket' => 0, 'print_sponsor_bag' => 0, 'print_name_back' => 0, 'print_staff_text' => 0],
        10 => ['print_rohda' => 0, 'print_initials' => 0, 'print_sponsor' => 0, 'print_sponsor_back' => 0, 'print_sponsor_padded' => 0, 'print_sponsor_jacket' => 0, 'print_sponsor_bag' => 0, 'print_name_back' => 0, 'print_staff_text' => 0],
        11 => ['print_rohda' => 1, 'print_initials' => 1, 'print_sponsor' => 1, 'print_sponsor_back' => 1, 'print_sponsor_padded' => 0, 'print_sponsor_jacket' => 0, 'print_sponsor_bag' => 0, 'print_name_back' => 0, 'print_staff_text' => 1],
        12 => ['print_rohda' => 0, 'print_initials' => 0, 'print_sponsor' => 0, 'print_sponsor_back' => 0, 'print_sponsor_padded' => 0, 'print_sponsor_jacket' => 0, 'print_sponsor_bag' => 0, 'print_name_back' => 0, 'print_staff_text' => 0],
        13 => ['print_rohda' => 0, 'print_initials' => 1, 'print_sponsor' => 0, 'print_sponsor_back' => 0, 'print_sponsor_padded' => 0, 'print_sponsor_jacket' => 1, 'print_sponsor_bag' => 0, 'print_name_back' => 0, 'print_staff_text' => 0],
        14 => ['print_rohda' => 1, 'print_initials' => 1, 'print_sponsor' => 0, 'print_sponsor_back' => 0, 'print_sponsor_padded' => 1, 'print_sponsor_jacket' => 0, 'print_sponsor_bag' => 0, 'print_name_back' => 0, 'print_staff_text' => 0],
        15 => ['print_rohda' => 1, 'print_initials' => 1, 'print_sponsor' => 0, 'print_sponsor_back' => 0, 'print_sponsor_padded' => 0, 'print_sponsor_jacket' => 0, 'print_sponsor_bag' => 1, 'print_name_back' => 0, 'print_staff_text' => 0],
        19 => ['print_rohda' => 1, 'print_initials' => 1, 'print_sponsor' => 1, 'print_sponsor_back' => 1, 'print_sponsor_padded' => 0, 'print_sponsor_jacket' => 0, 'print_sponsor_bag' => 0, 'print_name_back' => 0, 'print_staff_text' => 0],
        23 => ['print_rohda' => 1, 'print_initials' => 1, 'print_sponsor' => 1, 'print_sponsor_back' => 1, 'print_sponsor_padded' => 0, 'print_sponsor_jacket' => 0, 'print_sponsor_bag' => 0, 'print_name_back' => 0, 'print_staff_text' => 1],
        24 => ['print_rohda' => 0, 'print_initials' => 0, 'print_sponsor' => 0, 'print_sponsor_back' => 0, 'print_sponsor_padded' => 0, 'print_sponsor_jacket' => 0, 'print_sponsor_bag' => 0, 'print_name_back' => 0, 'print_staff_text' => 0],
        25 => ['print_rohda' => 0, 'print_initials' => 1, 'print_sponsor' => 0, 'print_sponsor_back' => 0, 'print_sponsor_padded' => 0, 'print_sponsor_jacket' => 0, 'print_sponsor_bag' => 0, 'print_name_back' => 0, 'print_staff_text' => 0],
        31 => ['print_rohda' => 0, 'print_initials' => 1, 'print_sponsor' => 0, 'print_sponsor_back' => 0, 'print_sponsor_padded' => 0, 'print_sponsor_jacket' => 0, 'print_sponsor_bag' => 0, 'print_name_back' => 0, 'print_staff_text' => 0],
    ];
    $sql = 'UPDATE clothing_types SET print_rohda=?, print_initials=?, print_sponsor=?, print_sponsor_back=?, print_sponsor_padded=?, print_sponsor_jacket=?, print_sponsor_bag=?, print_name_back=?, print_staff_text=?, print_place=?, updated_at=NOW() WHERE id=?';
    $st = $db->prepare($sql);
    foreach ($rows as $id => $flags) {
        $place = printPlaceFromFlags($flags);
        $st->bind_param(
            'iiiiiiiiisi',
            $flags['print_rohda'],
            $flags['print_initials'],
            $flags['print_sponsor'],
            $flags['print_sponsor_back'],
            $flags['print_sponsor_padded'],
            $flags['print_sponsor_jacket'],
            $flags['print_sponsor_bag'],
            $flags['print_name_back'],
            $flags['print_staff_text'],
            $place,
            $id
        );
        $st->execute();
    }
    $db->query("UPDATE clothing_types SET display_name='Clublogo' WHERE id=16");
    $db->query("UPDATE clothing_types SET display_name='Logo sponsor voorkant' WHERE id=17");
    $db->query("UPDATE clothing_types SET display_name='Logo sponsor achterkant' WHERE id=26");
    $db->query("UPDATE clothing_types SET display_name='Logo sponsor padded' WHERE id=28");
    $db->query("UPDATE clothing_types SET display_name='Logo sponsor regenjas' WHERE id=29");
    $db->query("UPDATE clothing_types SET display_name='Logo sponsor tas' WHERE id=30");
    @mkdir(dirname($mark), 0750, true);
    file_put_contents($mark, "2\n");
}

function ensureTypeMetaColumns(mysqli $db): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $added = false;
    $cols = [
        'size_kind' => "VARCHAR(20) NOT NULL DEFAULT 'body'",
        'order_group' => "VARCHAR(20) NOT NULL DEFAULT 'extra'",
        'sizes' => 'VARCHAR(255) NULL DEFAULT NULL',
    ];
    foreach ($cols as $name => $ddl) {
        $r = $db->query("SHOW COLUMNS FROM clothing_types LIKE '{$name}'");
        if ($r && $r->num_rows > 0) {
            continue;
        }
        $db->query("ALTER TABLE clothing_types ADD COLUMN {$name} {$ddl}");
        $added = true;
    }
    $needSeed = $added;
    if (!$needSeed) {
        $r = $db->query("SELECT COUNT(*) AS n FROM clothing_types WHERE size_kind='socks'");
        $row = $r ? $r->fetch_assoc() : null;
        $needSeed = (int) ($row['n'] ?? 0) === 0;
    }
    if (!$needSeed) {
        return;
    }
    $rows = [
        1 => ['body', 'match'],
        3 => ['socks', 'match'],
        4 => ['body', 'match'],
        7 => ['socks', 'match'],
        9 => ['body', 'match'],
        10 => ['socks', 'match'],
        11 => ['body', 'extra'],
        12 => ['body', 'extra'],
        13 => ['body', 'package'],
        14 => ['body', 'package'],
        15 => ['onesize', 'package'],
    ];
    $st = $db->prepare('UPDATE clothing_types SET size_kind=?, order_group=? WHERE id=?');
    foreach ($rows as $id => $r) {
        $st->bind_param('ssi', $r[0], $r[1], $id);
        $st->execute();
    }
}

function ensureTypePrintColumns(mysqli $db): bool {
    static $done = false;
    static $added = false;
    if ($done) {
        return $added;
    }
    $done = true;
    $cols = [
        'print_rohda' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'print_initials' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'print_sponsor' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'print_name_back' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'print_place' => 'VARCHAR(255) NULL DEFAULT NULL',
    ];
    foreach ($cols as $name => $ddl) {
        $r = $db->query("SHOW COLUMNS FROM clothing_types LIKE '{$name}'");
        if ($r && $r->num_rows > 0) {
            continue;
        }
        $db->query("ALTER TABLE clothing_types ADD COLUMN {$name} {$ddl}");
        $added = true;
    }
    return $added;
}

function seedTypePrintDefaults(mysqli $db): void {
    $rows = [
        1 => [1, 1, 1, 1, 'Clublogo · bedrijfslogo · initialen · nummer achterop'],
        3 => [0, 0, 0, 0, 'Geen bedrukking'],
        4 => [0, 1, 0, 0, 'Alleen initialen'],
        7 => [0, 0, 0, 0, 'Geen bedrukking'],
        9 => [1, 1, 1, 1, 'Clublogo · bedrijfslogo · initialen · nummer achterop'],
        10 => [0, 0, 0, 0, 'Geen bedrukking'],
        11 => [0, 0, 0, 0, 'Geen bedrukking'],
        12 => [0, 0, 0, 0, 'Geen bedrukking'],
        13 => [1, 1, 1, 0, 'Clublogo · bedrijfslogo · initialen'],
        14 => [1, 1, 1, 0, 'Clublogo · bedrijfslogo · initialen'],
        15 => [1, 1, 1, 0, 'Clublogo · bedrijfslogo · initialen'],
    ];
    $st = $db->prepare('UPDATE clothing_types SET print_rohda=?, print_initials=?, print_sponsor=?, print_name_back=?, print_place=? WHERE id=?');
    foreach ($rows as $id => $r) {
        $st->bind_param('iiiisi', $r[0], $r[1], $r[2], $r[3], $r[4], $id);
        $st->execute();
    }
}

function seedStannoTypeSizes(mysqli $db): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $r = $db->query("SHOW COLUMNS FROM clothing_types LIKE 'sizes'");
    if (!$r || $r->num_rows === 0) {
        return;
    }
    $res = $db->query('SELECT id, article_number, sizes, size_kind FROM clothing_types');
    if (!$res) {
        return;
    }
    $upd = $db->prepare('UPDATE clothing_types SET sizes=?, size_kind=? WHERE id=?');
    while ($row = $res->fetch_assoc()) {
        $id = (int) ($row['id'] ?? 0);
        if ($id < 1) {
            continue;
        }
        $current = parseSizeList((string) ($row['sizes'] ?? ''));
        $fromArt = stannoSizesForArticle((string) ($row['article_number'] ?? ''));
        $sizes = $current !== [] ? $current : $fromArt;
        if ($sizes === []) {
            $sizes = sizeOptionsForKind((string) ($row['size_kind'] ?? 'body'));
        }
        $kind = (string) ($row['size_kind'] ?? 'body');
        if ($sizes === ['één maat']) {
            $kind = 'onesize';
        } elseif ($sizes === ['JR', 'SR'] || preg_match('/^\d+\/\d+$/', $sizes[0] ?? '')) {
            $kind = 'socks';
        } elseif (in_array($kind, ['body', 'socks', 'onesize'], true) === false) {
            $kind = 'body';
        } elseif ($fromArt !== [] && $kind === 'onesize' && $sizes !== ['één maat']) {
            $kind = ($sizes === ['JR', 'SR'] || preg_match('/^\d+\/\d+$/', $sizes[0] ?? '')) ? 'socks' : 'body';
        }
        $sizesStr = formatSizeList($sizes);
        if ($sizesStr === (string) ($row['sizes'] ?? '') && $kind === (string) ($row['size_kind'] ?? '')) {
            continue;
        }
        $upd->bind_param('ssi', $sizesStr, $kind, $id);
        $upd->execute();
    }
}

function migrateAssignedSizeAliases(mysqli $db): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $map = [
        '36-40' => '36/40',
        '41-44' => '41/44',
        '45-47' => '45/48',
        '45-48' => '45/48',
        '31-35' => '30/35',
        '30-35' => '30/35',
        '25-29' => '25/29',
    ];
    foreach (['player_clothing', 'staff_clothing'] as $table) {
        foreach ($map as $from => $to) {
            $st = $db->prepare("UPDATE {$table} SET size=?, updated_at=NOW() WHERE size=?");
            $st->bind_param('ss', $to, $from);
            $st->execute();
        }
    }
    $youth = "'116','128','140','152','164'";
    $db->query("UPDATE player_clothing pc
        LEFT JOIN player_clothing sh ON sh.player_id=pc.player_id AND sh.clothing_type_id=1
        SET pc.size=IF(sh.size IN ({$youth}), 'JR', 'SR'), pc.updated_at=NOW()
        WHERE pc.clothing_type_id=24 AND pc.size IN ('één maat','een maat','onesize')");
    $db->query("UPDATE staff_clothing sc
        SET sc.size='SR', sc.updated_at=NOW()
        WHERE sc.clothing_type_id=24 AND sc.size IN ('één maat','een maat','onesize')");
}

function seedPriceIfEmpty(mysqli $db, int $id, float $small, float $large): void {
    $st = $db->prepare('SELECT price, price_small, price_large FROM clothing_types WHERE id=? LIMIT 1');
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if (!$row) {
        return;
    }
    $empty = static fn($v) => $v === null || $v === '';
    if (!$empty($row['price']) || !$empty($row['price_small']) || !$empty($row['price_large'])) {
        return;
    }
    $upd = $db->prepare('UPDATE clothing_types SET price_small=?, price_large=?, price=?, updated_at=NOW() WHERE id=?');
    $upd->bind_param('dddi', $small, $large, $large, $id);
    $upd->execute();
}

function ensurePackageTypes(mysqli $db): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $printColsAdded = ensureTypePrintColumns($db);
    ensureTypeMetaColumns($db);
    $rows = [
        13 => ['field_jack', 'Field Jack (regenjas)', '454002', 'Regenjack pakket 14-2', 24.94, 26.34],
        14 => ['prime_padded_jacket', 'Prime Padded Jacket (Winterjas)', '456004', 'Winterjas pakket 14-2', 63.22, 66.73],
        15 => ['pro_bag_prime', 'Pro Bag Prime (multifunctionele tas)', '484837', 'Sporttas pakket 14-2', 29.50, 29.50],
    ];
    foreach ($rows as $id => $r) {
        [$name, $display, $article, $desc, $small, $large] = $r;
        $color = 'Zwart';
        $brand = 'Stanno';
        $found = null;
        $sel = $db->prepare('SELECT id FROM clothing_types WHERE id=? LIMIT 1');
        $sel->bind_param('i', $id);
        $sel->execute();
        $found = $sel->get_result()->fetch_assoc();
        if (!$found) {
            $sel = $db->prepare('SELECT id FROM clothing_types WHERE article_number=? OR name=? LIMIT 1');
            $sel->bind_param('ss', $article, $name);
            $sel->execute();
            $found = $sel->get_result()->fetch_assoc();
        }
        if ($found) {
            $fid = (int) $found['id'];
            seedPriceIfEmpty($db, $fid, $small, $large);
            continue;
        }
        $ins = $db->prepare('INSERT INTO clothing_types (id, name, display_name, article_number, description, color, brand, price_small, price_large, price, active, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,1,NOW(),NOW())');
        $ins->bind_param('issssssddd', $id, $name, $display, $article, $desc, $color, $brand, $small, $large, $large);
        $ins->execute();
    }
    if ($printColsAdded) {
        seedTypePrintDefaults($db);
    }
}

function ensureFieldShortType(mysqli $db): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ensureTypeMetaColumns($db);
    ensureTypePrintColumns($db);
    $id = fieldShortTypeId();
    $name = 'field_short';
    $display = 'Field Short';
    $article = '420000';
    $desc = 'Field short zwart';
    $color = 'Zwart';
    $brand = 'Stanno';
    $price = 9.45;
    $place = 'Alleen initialen';
    $kind = 'body';
    $group = 'match';
    $foundId = 0;
    $sel = $db->prepare('SELECT id FROM clothing_types WHERE id=? OR article_number=? OR name=? LIMIT 1');
    $sel->bind_param('iss', $id, $article, $name);
    $sel->execute();
    $found = $sel->get_result()->fetch_assoc();
    if ($found) {
        $foundId = (int) $found['id'];
    }
    if ($foundId < 1) {
        $ins = $db->prepare('INSERT INTO clothing_types (id, name, display_name, article_number, description, color, brand, price_small, price_large, price, size_kind, order_group, print_rohda, print_initials, print_sponsor, print_sponsor_back, print_name_back, print_place, active, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0,1,0,0,0,?,1,NOW(),NOW())');
        $ins->bind_param('issssssdddsss', $id, $name, $display, $article, $desc, $color, $brand, $price, $price, $price, $kind, $group, $place);
        $ins->execute();
        $foundId = $id > 0 ? $id : (int) $db->insert_id;
    }
}

function ensureSponsorPrintSplit(mysqli $db): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $added = false;
    $r = $db->query("SHOW COLUMNS FROM clothing_types LIKE 'print_sponsor_back'");
    if (!$r || $r->num_rows === 0) {
        $db->query('ALTER TABLE clothing_types ADD COLUMN print_sponsor_back TINYINT(1) NOT NULL DEFAULT 0 AFTER print_sponsor');
        $added = true;
    }
    $front = $db->query('SELECT id, name, display_name FROM clothing_types WHERE id=17 LIMIT 1');
    $row = $front ? $front->fetch_assoc() : null;
    if ($row) {
        $dn = trim((string) ($row['display_name'] ?? ''));
        if (preg_match('/^logo\s*sponser$/iu', $dn)) {
            $db->query("UPDATE clothing_types SET display_name='Logo Sponsor voorkant', name='logo_sponsor_voorkant', updated_at=NOW() WHERE id=17");
        }
    }
    $hasBack = false;
    $chk = $db->query("SELECT id FROM clothing_types WHERE print_sponsor_back=1 AND (article_number IS NULL OR TRIM(article_number)='') LIMIT 1");
    if ($chk && $chk->fetch_row()) {
        $hasBack = true;
    }
    if (!$hasBack) {
        $price = 9.95;
        $src = $db->query('SELECT price, price_small, price_large FROM clothing_types WHERE id=17 LIMIT 1');
        $p = $src ? $src->fetch_assoc() : null;
        if ($p) {
            foreach (['price_small', 'price_large', 'price'] as $k) {
                if (($p[$k] ?? null) !== null && $p[$k] !== '') {
                    $price = (float) $p[$k];
                    break;
                }
            }
        }
        $name = 'logo_sponsor_achterkant';
        $exist = $db->prepare('SELECT id FROM clothing_types WHERE name=? LIMIT 1');
        $exist->bind_param('s', $name);
        $exist->execute();
        $found = $exist->get_result()->fetch_assoc();
        $priceS = number_format($price, 2, '.', '');
        $display = 'Logo Sponsor achterkant';
        $article = '';
        $color = '';
        $brand = 'Stanno';
        $kind = 'onesize';
        $group = 'extra';
        $place = '';
        if ($found) {
            $fid = (int) $found['id'];
            $upd = $db->prepare("UPDATE clothing_types SET display_name=?, article_number=?, print_rohda=0, print_initials=0, print_sponsor=0, print_sponsor_back=1, print_name_back=0, price_small=?, price_large=?, price=?, active=1, updated_at=NOW() WHERE id=?");
            $upd->bind_param('sssssi', $display, $article, $priceS, $priceS, $priceS, $fid);
            $upd->execute();
        } else {
            $ins = $db->prepare('INSERT INTO clothing_types (name, display_name, article_number, color, brand, price_small, price_large, price, size_kind, order_group, print_rohda, print_initials, print_sponsor, print_sponsor_back, print_name_back, print_place, active, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,0,0,0,1,0,?,1,NOW(),NOW())');
            $ins->bind_param('sssssssssss', $name, $display, $article, $color, $brand, $priceS, $priceS, $priceS, $kind, $group, $place);
            $ins->execute();
        }
    }
    if (!$added) {
        return;
    }
    $db->query('UPDATE clothing_types SET print_sponsor_back=1 WHERE id IN (1, 19, 23)');
    $db->query("UPDATE clothing_types SET print_sponsor=0, print_sponsor_back=1, print_place='Clublogo · bedrijfslogo achterkant · initialen' WHERE id=13");
    $db->query("UPDATE clothing_types SET print_place='Clublogo · bedrijfslogo voor- en achterkant · initialen · nummer achterop' WHERE id IN (1, 19)");
    $db->query("UPDATE clothing_types SET print_place='Clublogo · bedrijfslogo voor- en achterkant · initialen' WHERE id=23");
}

function ensureStaffTextPrint(mysqli $db): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $added = false;
    $r = $db->query("SHOW COLUMNS FROM clothing_types LIKE 'print_staff_text'");
    if (!$r || $r->num_rows === 0) {
        $db->query('ALTER TABLE clothing_types ADD COLUMN print_staff_text TINYINT(1) NOT NULL DEFAULT 0 AFTER print_name_back');
        $added = true;
    }
    $has = false;
    $chk = $db->query("SELECT id FROM clothing_types WHERE print_staff_text=1 AND (article_number IS NULL OR TRIM(article_number)='') LIMIT 1");
    if ($chk && $chk->fetch_row()) {
        $has = true;
    }
    if (!$has) {
        $name = 'tekst_staf';
        $display = 'Tekst staf';
        $article = '';
        $color = '';
        $brand = 'Stanno';
        $priceS = '7.95';
        $kind = 'onesize';
        $group = 'extra';
        $place = '';
        $exist = $db->prepare('SELECT id FROM clothing_types WHERE name=? LIMIT 1');
        $exist->bind_param('s', $name);
        $exist->execute();
        $found = $exist->get_result()->fetch_assoc();
        if ($found) {
            $fid = (int) $found['id'];
            $upd = $db->prepare("UPDATE clothing_types SET display_name=?, article_number=?, print_rohda=0, print_initials=0, print_sponsor=0, print_sponsor_back=0, print_name_back=0, print_staff_text=1, price_small=?, price_large=?, price=?, active=1, updated_at=NOW() WHERE id=?");
            $upd->bind_param('sssssi', $display, $article, $priceS, $priceS, $priceS, $fid);
            $upd->execute();
        } else {
            $ins = $db->prepare('INSERT INTO clothing_types (name, display_name, article_number, color, brand, price_small, price_large, price, size_kind, order_group, print_rohda, print_initials, print_sponsor, print_sponsor_back, print_name_back, print_staff_text, print_place, active, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,0,0,0,0,0,1,?,1,NOW(),NOW())');
            $ins->bind_param('sssssssssss', $name, $display, $article, $color, $brand, $priceS, $priceS, $priceS, $kind, $group, $place);
            $ins->execute();
        }
    }
    if ($added) {
        $db->query('UPDATE clothing_types SET print_staff_text=1 WHERE id=23');
    }
}

function ensurePrintCatalogRow(mysqli $db, string $name, string $display, string $flag, string $priceS = '9.95'): void {
    $ok = [
        'print_sponsor' => true,
        'print_sponsor_back' => true,
        'print_sponsor_padded' => true,
        'print_sponsor_jacket' => true,
        'print_sponsor_bag' => true,
        'print_staff_text' => true,
        'print_rohda' => true,
        'print_initials' => true,
        'print_name_back' => true,
    ];
    if (!isset($ok[$flag])) {
        return;
    }
    $chk = $db->query("SELECT id FROM clothing_types WHERE {$flag}=1 AND (article_number IS NULL OR TRIM(article_number)='') LIMIT 1");
    if ($chk && $chk->fetch_row()) {
        return;
    }
    $exist = $db->prepare('SELECT id FROM clothing_types WHERE name=? LIMIT 1');
    $exist->bind_param('s', $name);
    $exist->execute();
    $found = $exist->get_result()->fetch_assoc();
    $article = '';
    $color = '';
    $brand = 'Stanno';
    $kind = 'onesize';
    $group = 'extra';
    $place = '';
    $flags = [
        'print_rohda' => 0,
        'print_initials' => 0,
        'print_sponsor' => 0,
        'print_sponsor_back' => 0,
        'print_sponsor_padded' => 0,
        'print_sponsor_jacket' => 0,
        'print_sponsor_bag' => 0,
        'print_name_back' => 0,
        'print_staff_text' => 0,
    ];
    $flags[$flag] = 1;
    if ($found) {
        $fid = (int) $found['id'];
        $upd = $db->prepare("UPDATE clothing_types SET display_name=?, article_number=?, print_rohda=?, print_initials=?, print_sponsor=?, print_sponsor_back=?, print_sponsor_padded=?, print_sponsor_jacket=?, print_sponsor_bag=?, print_name_back=?, print_staff_text=?, price_small=?, price_large=?, price=?, active=1, updated_at=NOW() WHERE id=?");
        $upd->bind_param(
            'ssiiiiiiiiisssi',
            $display,
            $article,
            $flags['print_rohda'],
            $flags['print_initials'],
            $flags['print_sponsor'],
            $flags['print_sponsor_back'],
            $flags['print_sponsor_padded'],
            $flags['print_sponsor_jacket'],
            $flags['print_sponsor_bag'],
            $flags['print_name_back'],
            $flags['print_staff_text'],
            $priceS,
            $priceS,
            $priceS,
            $fid
        );
        $upd->execute();
        return;
    }
    $ins = $db->prepare('INSERT INTO clothing_types (name, display_name, article_number, color, brand, price_small, price_large, price, size_kind, order_group, print_rohda, print_initials, print_sponsor, print_sponsor_back, print_sponsor_padded, print_sponsor_jacket, print_sponsor_bag, print_name_back, print_staff_text, print_place, active, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, ?,1,NOW(),NOW())');
    $ins->bind_param(
        'ssssssssssiiiiiiiiis',
        $name,
        $display,
        $article,
        $color,
        $brand,
        $priceS,
        $priceS,
        $priceS,
        $kind,
        $group,
        $flags['print_rohda'],
        $flags['print_initials'],
        $flags['print_sponsor'],
        $flags['print_sponsor_back'],
        $flags['print_sponsor_padded'],
        $flags['print_sponsor_jacket'],
        $flags['print_sponsor_bag'],
        $flags['print_name_back'],
        $flags['print_staff_text'],
        $place
    );
    $ins->execute();
}

function ensureSponsorQuoteKinds(mysqli $db): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $added = false;
    $cols = [
        'print_sponsor_padded' => 'print_sponsor_back',
        'print_sponsor_jacket' => 'print_sponsor_padded',
        'print_sponsor_bag' => 'print_sponsor_jacket',
    ];
    foreach ($cols as $name => $after) {
        $r = $db->query("SHOW COLUMNS FROM clothing_types LIKE '{$name}'");
        if ($r && $r->num_rows > 0) {
            continue;
        }
        $db->query("ALTER TABLE clothing_types ADD COLUMN {$name} TINYINT(1) NOT NULL DEFAULT 0 AFTER {$after}");
        $added = true;
    }
    $db->query("UPDATE clothing_types SET display_name='Logo Sponsor shirts voorkant' WHERE id=17 AND display_name IN ('Logo Sponsor voorkant','Logo Sponser')");
    $db->query("UPDATE clothing_types SET display_name='Logo Sponsor shirts achterkant' WHERE id=26 AND display_name='Logo Sponsor achterkant'");
    ensurePrintCatalogRow($db, 'logo_sponsor_padded', 'Logo Sponsor padded (gezamenlijk)', 'print_sponsor_padded');
    ensurePrintCatalogRow($db, 'logo_sponsor_fieldjack', 'Logo Sponsor field jack achterkant', 'print_sponsor_jacket');
    ensurePrintCatalogRow($db, 'logo_sponsor_tas', 'Logo Sponsor tas', 'print_sponsor_bag');
    if (!$added) {
        return;
    }
    $db->query("UPDATE clothing_types SET print_sponsor=0, print_sponsor_padded=1, print_place='Clublogo · gezamenlijk sponsorlogo borst · initialen' WHERE id=14");
    $db->query("UPDATE clothing_types SET print_sponsor_back=0, print_sponsor_jacket=1, print_place='Clublogo · sponsor achterkant · initialen' WHERE id=13");
    $db->query("UPDATE clothing_types SET print_sponsor=0, print_sponsor_bag=1, print_place='Clublogo · sponsor 1 kleur · initialen' WHERE id=15");
}

function remapStaffShirtTypeIds(array $ids, int $staffShirtId): array {
    $out = [];
    foreach ($ids as $tid) {
        $tid = (int) $tid;
        if ($tid === 1) {
            $tid = $staffShirtId;
        }
        if ($tid > 0) {
            $out[$tid] = $tid;
        }
    }
    return array_values($out);
}

function ensureStaffShirtType(mysqli $db): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ensureTypePrintColumns($db);
    ensureTypeMetaColumns($db);
    $id = staffShirtTypeId();
    $name = 'staff_shirt';
    $display = 'Staf shirt';
    $src = $db->query('SELECT article_number, color, brand, price, price_small, price_large FROM clothing_types WHERE id=1 LIMIT 1');
    $row = $src ? $src->fetch_assoc() : null;
    $article = (string) ($row['article_number'] ?? '410014');
    $color = (string) ($row['color'] ?? 'Zwart');
    $brand = (string) ($row['brand'] ?? 'Stanno');
    if ($brand === '') {
        $brand = 'Stanno';
    }
    $small = isset($row['price_small']) && $row['price_small'] !== null && $row['price_small'] !== '' ? (float) $row['price_small'] : 16.85;
    $large = isset($row['price_large']) && $row['price_large'] !== null && $row['price_large'] !== '' ? (float) $row['price_large'] : 18.26;
    $price = isset($row['price']) && $row['price'] !== null && $row['price'] !== '' ? (float) $row['price'] : $large;
    $desc = 'Shirt voor kader 14-2';
    $kind = 'body';
    $group = 'extra';
    $place = 'Clublogo · bedrijfslogo voor- en achterkant · initialen';
    $found = null;
    $sel = $db->prepare('SELECT id FROM clothing_types WHERE id=? OR name=? LIMIT 1');
    $sel->bind_param('is', $id, $name);
    $sel->execute();
    $found = $sel->get_result()->fetch_assoc();
    if ($found) {
        $fid = (int) $found['id'];
        seedPriceIfEmpty($db, $fid, $small, $large);
        $id = $fid;
    } else {
        $ins = $db->prepare('INSERT INTO clothing_types (id, name, display_name, article_number, description, color, brand, price_small, price_large, price, size_kind, order_group, print_rohda, print_initials, print_sponsor, print_sponsor_back, print_name_back, print_staff_text, print_place, active, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,1,1,1,0,1,?,1,NOW(),NOW())');
        $ins->bind_param('issssssdddsss', $id, $name, $display, $article, $desc, $color, $brand, $small, $large, $price, $kind, $group, $place);
        $ins->execute();
    }

    $mv = $db->prepare('UPDATE staff_clothing SET clothing_type_id=? WHERE clothing_type_id=1');
    $mv->bind_param('i', $id);
    $mv->execute();

    saveParentFormSettings(loadParentFormSettings(true));
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

function staffFillMessage(string $name, string $url): string {
    return "Hoi {$name}, voor je kleding van 14-2 kun je je maten invullen via deze link:\n{$url}";
}

function fillWhatsAppUrl(string $message): string {
    return 'https://wa.me/?text=' . rawurlencode($message);
}

function parentWhatsAppUrl(string $name, string $url): string {
    return 'https://wa.me/?text=' . rawurlencode(parentMessage($name, $url));
}

function parentFormPath(): string {
    return __DIR__ . '/.data/parent-form.json';
}

function parentFillableTypeIds(?array $types = null): array {
    $types = $types ?? rememberTypes();
    if ($types === []) {
        global $mysqli;
        if (isset($mysqli) && $mysqli instanceof mysqli) {
            $types = loadTypes($mysqli);
        }
    }
    $ids = [];
    foreach ($types as $tid => $t) {
        if (!is_array($t) || !typeIsActive($t) || isPrintCatalogType($t)) {
            continue;
        }
        $id = (int) ($t['id'] ?? $tid);
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    if (isset($ids[fieldShortTypeId()])) {
        unset($ids[4]);
    }
    if ($ids === []) {
        return [1, fieldShortTypeId(), 13, 14, 15, 3, 7, 11, 12, staffShirtTypeId(), 9, 10, 19];
    }
    $preferred = [1, 23, 9, 19, fieldShortTypeId(), 13, 14, 15, 3, 10, 7, 11, 12];
    $out = [];
    foreach ($preferred as $id) {
        if (isset($ids[$id])) {
            $out[] = $id;
            unset($ids[$id]);
        }
    }
    ksort($ids);
    foreach ($ids as $id) {
        $out[] = $id;
    }
    return $out;
}

function parentTypeChoices(string $kind = 'field'): array {
    $ids = parentFillableTypeIds();
    if ($kind === 'field') {
        return filterParentTypeIdsForPerson($ids, ['position' => 'midfielder']);
    }
    return $ids;
}

function allParentTypeIds(): array {
    return parentFillableTypeIds();
}

function catalogTypeName(int $tid, array $types = []): string {
    if ($types === []) {
        $types = rememberTypes();
    }
    $t = $types[$tid] ?? [];
    $label = trim((string) ($t['display_name'] ?? ''));
    if ($label === '') {
        $label = trim((string) ($t['name'] ?? ''));
    }
    return $label !== '' ? $label : (string) $tid;
}

function shortTypeName(int $tid, array $types = []): string {
    return catalogTypeName($tid, $types);
}

function cardTypeName(int $tid, array $types = []): string {
    return catalogTypeName($tid, $types);
}

function kitRowCopyHtml(int $tid, array $types, string $tag, ?float $unit): string {
    $html = '<span class="kit-copy">';
    $html .= '<span class="kit-name">'.h(cardTypeName($tid, $types)).'</span>';
    $html .= '<span class="kit-tag">'.h($tag).'</span>';
    $html .= '<span class="kit-price">';
    if ($unit !== null) {
        $html .= '<span class="kit-ex">'.h(euro($unit)).'</span>';
        $html .= '<span class="kit-inc">'.h(euro(withVat($unit))).' incl.</span>';
    }
    $html .= '</span></span>';
    return $html;
}

function typeIsActive(array $t): bool {
    return !array_key_exists('active', $t) || (int) $t['active'] === 1;
}

function clothingTypeAssignmentCounts(mysqli $db): array {
    $out = [];
    $queries = [
        'player' => 'SELECT clothing_type_id, COUNT(*) AS c FROM player_clothing GROUP BY clothing_type_id',
        'staff' => 'SELECT clothing_type_id, COUNT(*) AS c FROM staff_clothing GROUP BY clothing_type_id',
    ];
    foreach ($queries as $who => $sql) {
        $res = $db->query($sql);
        if (!$res) {
            continue;
        }
        while ($row = $res->fetch_assoc()) {
            $id = (int) ($row['clothing_type_id'] ?? 0);
            if ($id > 0) {
                $out[$id][$who] = (int) ($row['c'] ?? 0);
            }
        }
    }
    return $out;
}

function assignedTypeDeleteError(int $players, int $staff): string {
    $bits = [];
    if ($players === 1) {
        $bits[] = '1 speler';
    } elseif ($players > 1) {
        $bits[] = $players . ' spelers';
    }
    if ($staff === 1) {
        $bits[] = '1 staflid';
    } elseif ($staff > 1) {
        $bits[] = $staff . ' stafleden';
    }
    if ($bits === []) {
        return '';
    }
    return 'Dit artikel is nog toegekend aan ' . implode(' en ', $bits) . '. Haal het daar eerst weg.';
}

function normalizePlayerPosition(string $pos): string {
    $pos = strtolower(trim($pos));
    return in_array($pos, ['goalkeeper', 'defender', 'midfielder', 'attacker'], true) ? $pos : 'midfielder';
}

function normalizeStaffStatus(string $status): string {
    $status = strtolower(trim($status));
    return in_array($status, ['active', 'inactive', 'former'], true) ? $status : 'active';
}

/** Catalogusregel voor een bedrukking (logo/initialen), geen kledingstuk. */
function isPrintCatalogType(array $t): bool {
    if (!typeIsActive($t)) {
        return false;
    }
    if (trim((string) ($t['article_number'] ?? '')) !== '') {
        return false;
    }
    return typePrints($t, 'print_rohda')
        || typePrints($t, 'print_initials')
        || typePrints($t, 'print_sponsor')
        || typePrints($t, 'print_sponsor_back')
        || typePrints($t, 'print_sponsor_padded')
        || typePrints($t, 'print_sponsor_jacket')
        || typePrints($t, 'print_sponsor_bag')
        || typePrints($t, 'print_name_back')
        || typePrints($t, 'print_staff_text');
}

/** Stukprijs per bedrukking uit de catalogus. */
function catalogPrintPrices(array $types): array {
    $map = [
        'rohda' => 'print_rohda',
        'initials' => 'print_initials',
        'sponsor' => 'print_sponsor',
        'sponsor_back' => 'print_sponsor_back',
        'sponsor_padded' => 'print_sponsor_padded',
        'sponsor_jacket' => 'print_sponsor_jacket',
        'sponsor_bag' => 'print_sponsor_bag',
        'name_back' => 'print_name_back',
        'staff_text' => 'print_staff_text',
    ];
    $out = [
        'rohda' => null,
        'initials' => null,
        'sponsor' => null,
        'sponsor_back' => null,
        'sponsor_padded' => null,
        'sponsor_jacket' => null,
        'sponsor_bag' => null,
        'name_back' => null,
        'staff_text' => null,
    ];
    foreach ($map as $key => $flag) {
        foreach ($types as $t) {
            if (!is_array($t) || !isPrintCatalogType($t) || !typePrints($t, $flag)) {
                continue;
            }
            $out[$key] = priceFor($t, '');
            break;
        }
    }
    return $out;
}

/** Kleding + print voor items die iemand krijgt (bestellen of in bezit), niet n.v.t. */
function personKitCost(array $person, array $types, array $printPrices, string $who = 'player'): array {
    $map = [
        'rohda' => 'print_rohda',
        'initials' => 'print_initials',
        'sponsor' => 'print_sponsor',
        'sponsor_back' => 'print_sponsor_back',
        'sponsor_padded' => 'print_sponsor_padded',
        'sponsor_jacket' => 'print_sponsor_jacket',
        'sponsor_bag' => 'print_sponsor_bag',
        'name_back' => 'print_name_back',
        'staff_text' => 'print_staff_text',
    ];
    $clothing = 0.0;
    $print = 0.0;
    $count = 0;
    foreach (array_keys($person['items'] ?? []) as $tid) {
        $tid = (int) $tid;
        $t = $types[$tid] ?? null;
        $it = itemFor($person, $tid);
        if (!$t || !$it || isPrintCatalogType($t)) {
            continue;
        }
        if (!personShowsType($person, $tid, $who)) {
            continue;
        }
        if (!isPendingItem($it) && !isIssued($it)) {
            continue;
        }
        $count++;
        $unit = priceFor($t, (string) ($it['size'] ?? ''));
        if ($unit !== null) {
            $clothing += $unit;
        }
        foreach ($map as $key => $flag) {
            if (!typePrints($t, $flag)) {
                continue;
            }
            $pu = $printPrices[$key] ?? null;
            if ($pu !== null) {
                $print += (float) $pu;
            }
        }
    }
    return [
        'clothing' => round($clothing, 2),
        'print' => round($print, 2),
        'total' => round($clothing + $print, 2),
        'count' => $count,
    ];
}

function kitTotalHtml(array $cost): string {
    $html = '<div class="kit-total" data-kit-total>';
    $html .= '<span>Totaal kleding</span>';
    if ($cost['count'] < 1) {
        $html .= '<b>—</b>';
    } else {
        $html .= '<b>'.h(euro($cost['total'])).'</b>';
        $html .= '<small>'.h(euro(withVat($cost['total']))).' incl.</small>';
    }
    $html .= '</div>';
    return $html;
}

function typeOptionsHtml(array $types, string $placeholder = 'Type'): string {
    $html = '<option value="">'.h($placeholder).'</option>';
    foreach ($types as $tid => $t) {
        if (!is_array($t) || !typeIsActive($t) || isPrintCatalogType($t)) {
            continue;
        }
        $tid = (int) $tid;
        if ($tid === 4 && isset($types[fieldShortTypeId()])) {
            continue;
        }
        $html .= '<option value="'.$tid.'">'.h(shortTypeName($tid, $types)).'</option>';
    }
    return $html;
}

function playerInitials(array $p): string {
    $first = trim((string) ($p['first_name'] ?? ''));
    $last = trim((string) ($p['last_name'] ?? ''));
    $particles = ['van', 'de', 'der', 'den', 'het', 'ten', 'ter', 'te', 'op', 'aan', 'tot', "'t"];
    $head = '';
    if ($first !== '') {
        $head = mb_strtoupper(mb_substr($first, 0, 1, 'UTF-8'), 'UTF-8');
    }
    $mids = '';
    $tail = '';
    foreach (preg_split('/\s+/', $last) ?: [] as $part) {
        if ($part === '') {
            continue;
        }
        $low = mb_strtolower($part, 'UTF-8');
        $ch = mb_substr($part, 0, 1, 'UTF-8');
        if (in_array($low, $particles, true)) {
            $mids .= mb_strtolower($ch, 'UTF-8');
        } else {
            $tail .= mb_strtoupper($ch, 'UTF-8');
        }
    }
    $full = $head . $tail;
    if ($full === '' && $mids !== '') {
        $full = mb_strtoupper($mids, 'UTF-8');
    }
    return mb_substr($full, 0, 2, 'UTF-8');
}

function kitSize(array $p, int $tid): string {
    return trim((string) (itemFor($p, $tid)['size'] ?? ''));
}

function playerBodyKitSize(array $p): string {
    foreach ([1, 9, 4] as $tid) {
        $s = kitSize($p, $tid);
        if ($s !== '') {
            return $s;
        }
    }
    $types = rememberTypes();
    foreach (keeperOnlyTypeIds($types) as $tid) {
        $t = $types[$tid] ?? null;
        if (!$t || typeSizeKind($t) === 'socks') {
            continue;
        }
        $s = kitSize($p, $tid);
        if ($s !== '') {
            return $s;
        }
    }
    return '';
}

function suggestedJacketSize(array $p): string {
    $body = playerBodyKitSize($p);
    $opts = sizeOptions(13);
    return in_array($body, $opts, true) ? $body : '';
}

function assignedSize(array $p, int $tid): string {
    $it = itemFor($p, $tid);
    if ($it === null) {
        return '';
    }
    return trim((string) ($it['size'] ?? ''));
}

function normalizeParentTypeIds(array $ids, array $allowed): array {
    $out = [];
    foreach ($ids as $id) {
        $id = (int) $id;
        if (isShortsType($id)) {
            $id = fieldShortTypeId();
        }
        if (in_array($id, $allowed, true)) {
            $out[$id] = $id;
        }
    }
    return array_values($out);
}

function defaultParentFormSettings(): array {
    return [
        'note' => '',
        'field' => [1, fieldShortTypeId(), 13, 14, 24, 7],
        'keeper' => array_values(array_unique(array_merge(
            [1, fieldShortTypeId(), 13, 14, 24, 7],
            keeperCoreTypeIds()
        ))),
        'staff' => defaultStaffPackageIds(),
        'players' => [],
        'staff_members' => [],
        'v' => 2,
    ];
}

function withParentJacketTypes(array $ids, array $allowed): array {
    $ids = normalizeParentTypeIds($ids, $allowed);
    foreach ([13, 14] as $tid) {
        if (in_array($tid, $allowed, true) && !in_array($tid, $ids, true)) {
            $ids[] = $tid;
        }
    }
    return $ids;
}

function replaceRetiredKeeperParentTypes(array $ids): array {
    $types = rememberTypes();
    $out = [];
    $hadRetired = false;
    foreach ($ids as $id) {
        $id = (int) $id;
        $t = $types[$id] ?? null;
        if ($id === 9 || ($t && isKeeperKitType($t) && !typeIsActive($t))) {
            $hadRetired = true;
            continue;
        }
        if ($id > 0) {
            $out[$id] = $id;
        }
    }
    foreach (keeperCoreTypeIds($types) as $kid) {
        $t = $types[$kid] ?? null;
        if ($t && isKeeperKitType($t) && typeIsActive($t)) {
            $out[$kid] = $kid;
        }
    }
    return array_values($out);
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
    $keeper = normalizeParentTypeIds(
        replaceRetiredKeeperParentTypes($raw['keeper'] ?? $def['keeper']),
        parentTypeChoices('keeper')
    );
    $version = (int) ($raw['v'] ?? 1);
    if ($version < 2) {
        $field = withParentJacketTypes($field, parentTypeChoices('field'));
        $keeper = withParentJacketTypes($keeper, parentTypeChoices('keeper'));
    }
    $settings['field'] = $field !== [] ? $field : $def['field'];
    $settings['keeper'] = $keeper !== [] ? $keeper : $def['keeper'];
    $staff = normalizeParentTypeIds(
        remapStaffShirtTypeIds($raw['staff'] ?? $def['staff'], staffShirtTypeId()),
        parentTypeChoices('staff')
    );
    $settings['staff'] = $staff !== [] ? $staff : $def['staff'];
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
        if ($version < 2) {
            $norm = withParentJacketTypes($norm, allParentTypeIds());
        }
        if ($norm !== []) {
            $players[$pid] = $norm;
        }
    }
    $settings['players'] = $players;
    $staffMembers = [];
    foreach (($raw['staff_members'] ?? []) as $sid => $ids) {
        if (!is_array($ids)) {
            continue;
        }
        $sid = (int) $sid;
        if ($sid < 1) {
            continue;
        }
        $norm = normalizeParentTypeIds(remapStaffShirtTypeIds($ids, staffShirtTypeId()), parentTypeChoices('staff'));
        if ($norm !== []) {
            $staffMembers[$sid] = $norm;
        }
    }
    $settings['staff_members'] = $staffMembers;
    $settings['v'] = 2;
    return $cached = $settings;
}

function saveParentFormSettings(array $settings): void {
    $dir = dirname(parentFormPath());
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $field = normalizeParentTypeIds($settings['field'] ?? [], parentTypeChoices('field'));
    $keeper = normalizeParentTypeIds($settings['keeper'] ?? [], parentTypeChoices('keeper'));
    $staff = normalizeParentTypeIds(remapStaffShirtTypeIds($settings['staff'] ?? [], staffShirtTypeId()), parentTypeChoices('staff'));
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
    $staffMembers = [];
    foreach (($settings['staff_members'] ?? []) as $sid => $ids) {
        $sid = (int) $sid;
        if ($sid < 1 || !is_array($ids)) {
            continue;
        }
        $norm = normalizeParentTypeIds(remapStaffShirtTypeIds($ids, staffShirtTypeId()), parentTypeChoices('staff'));
        if ($norm !== []) {
            $staffMembers[$sid] = $norm;
        }
    }
    $clean = [
        'note' => mb_substr(trim((string) ($settings['note'] ?? '')), 0, 280),
        'field' => $field !== [] ? $field : $def['field'],
        'keeper' => $keeper !== [] ? $keeper : $def['keeper'],
        'staff' => $staff !== [] ? $staff : $def['staff'],
        'players' => $players,
        'staff_members' => $staffMembers,
        'v' => 2,
    ];
    file_put_contents(parentFormPath(), json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    loadParentFormSettings(true);
}

function parentDefaultTypeIds(array $p): array {
    $settings = loadParentFormSettings();
    return (($p['position'] ?? '') === 'goalkeeper') ? $settings['keeper'] : $settings['field'];
}

function filterParentTypeIdsForPerson(array $ids, array $person): array {
    $isKeeper = ($person['position'] ?? '') === 'goalkeeper';
    $out = [];
    foreach ($ids as $tid) {
        $tid = (int) $tid;
        if ($tid < 1) {
            continue;
        }
        if (!$isKeeper && in_array($tid, keeperOnlyTypeIds(), true)) {
            continue;
        }
        $out[$tid] = $tid;
    }
    return array_values($out);
}

function parentAllowedTypeIds(array $p): array {
    $settings = loadParentFormSettings();
    $pid = (int) ($p['id'] ?? 0);
    $ids = ($pid > 0 && isset($settings['players'][$pid]))
        ? $settings['players'][$pid]
        : parentDefaultTypeIds($p);
    return filterParentTypeIdsForPerson($ids, $p);
}

function parentUsesCustomTypes(array $p): bool {
    $settings = loadParentFormSettings();
    return isset($settings['players'][(int) ($p['id'] ?? 0)]);
}

function staffDefaultTypeIds(): array {
    return loadParentFormSettings()['staff'];
}

function staffAllowedTypeIds(array $s): array {
    $settings = loadParentFormSettings();
    $sid = (int) ($s['id'] ?? 0);
    if ($sid > 0 && isset($settings['staff_members'][$sid])) {
        return $settings['staff_members'][$sid];
    }
    return staffDefaultTypeIds();
}

function staffUsesCustomTypes(array $s): bool {
    $settings = loadParentFormSettings();
    return isset($settings['staff_members'][(int) ($s['id'] ?? 0)]);
}

/** Types die op het ouder-/stafformulier van deze persoon staan, inclusief broek-alias. */
function personFormTypeIdSet(array $person, string $who = 'player'): array {
    $ids = $who === 'staff' ? staffAllowedTypeIds($person) : parentAllowedTypeIds($person);
    $out = [];
    foreach ($ids as $tid) {
        foreach (packageEquivalentTypeIds((int) $tid) as $cid) {
            $cid = (int) $cid;
            if ($cid > 0) {
                $out[$cid] = $cid;
            }
        }
    }
    return $out;
}

function personShowsType(array $person, int $tid, string $who = 'player'): bool {
    return isset(personFormTypeIdSet($person, $who)[$tid]);
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

function ensureClothingHoldStatus(mysqli $db): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    foreach (['player_clothing', 'staff_clothing'] as $table) {
        $r = $db->query("SHOW COLUMNS FROM `{$table}` LIKE 'status'");
        $row = $r ? $r->fetch_assoc() : null;
        $type = (string) ($row['Type'] ?? '');
        if ($type === '' || stripos($type, "'hold'") !== false) {
            continue;
        }
        if (!preg_match('/^enum\((.*)\)$/i', $type, $m)) {
            continue;
        }
        $ok = $db->query("ALTER TABLE `{$table}` MODIFY status ENUM({$m[1]},'hold') NOT NULL DEFAULT 'active'");
        if (!$ok) {
            throw new RuntimeException('Kon kledingstatus hold niet toevoegen.');
        }
    }
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

function ensureStaffFillColumns(mysqli $db): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $r = $db->query("SHOW COLUMNS FROM staff_members LIKE 'parent_token'");
    if (!$r || $r->num_rows < 1) {
        $db->query('ALTER TABLE staff_members ADD COLUMN parent_token CHAR(48) NULL DEFAULT NULL AFTER email');
        $db->query('ALTER TABLE staff_members ADD UNIQUE KEY staff_parent_token (parent_token)');
    }
    $r = $db->query("SHOW COLUMNS FROM staff_members LIKE 'parent_saved_at'");
    if (!$r || $r->num_rows < 1) {
        $db->query('ALTER TABLE staff_members ADD COLUMN parent_saved_at DATETIME NULL DEFAULT NULL AFTER parent_token');
    }
}

function fillTokenInUse(mysqli $db, string $token): bool {
    $st = $db->prepare('SELECT 1 FROM players WHERE parent_token=? LIMIT 1');
    $st->bind_param('s', $token);
    $st->execute();
    if ($st->get_result()->fetch_row()) {
        return true;
    }
    $st = $db->prepare('SELECT 1 FROM staff_members WHERE parent_token=? LIMIT 1');
    $st->bind_param('s', $token);
    $st->execute();
    return (bool) $st->get_result()->fetch_row();
}

function findStaffByFillToken(mysqli $db, string $token): ?array {
    $token = strtolower(trim($token));
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
        return null;
    }
    ensureStaffFillColumns($db);
    $st = $db->prepare("SELECT * FROM staff_members WHERE parent_token=? AND status='active' LIMIT 1");
    $st->bind_param('s', $token);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if (!$row) {
        return null;
    }
    $row['id'] = (int) $row['id'];
    return $row;
}

function findFillPersonByToken(mysqli $db, string $token): ?array {
    $player = findPlayerByParentToken($db, $token);
    if ($player) {
        return ['who' => 'player', 'person' => $player];
    }
    $staff = findStaffByFillToken($db, $token);
    if ($staff) {
        return ['who' => 'staff', 'person' => $staff];
    }
    return null;
}

function staffFillToken(mysqli $db, int $staffId, bool $rotate = false): string {
    ensureStaffFillColumns($db);
    $chk = $db->prepare("SELECT id, parent_token FROM staff_members WHERE id=? AND status='active' LIMIT 1");
    $chk->bind_param('i', $staffId);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    if (!$row) {
        throw new RuntimeException('Staf niet gevonden');
    }
    if (!$rotate && !empty($row['parent_token'])) {
        return (string) $row['parent_token'];
    }
    for ($i = 0; $i < 8; $i++) {
        $token = newParentToken();
        if (fillTokenInUse($db, $token)) {
            continue;
        }
        $upd = $db->prepare('UPDATE staff_members SET parent_token=?, updated_at=NOW() WHERE id=?');
        $upd->bind_param('si', $token, $staffId);
        if ($upd->execute()) {
            return $token;
        }
        if ((int) $db->errno !== 1062) {
            throw new RuntimeException('Kon geen staflink maken');
        }
    }
    throw new RuntimeException('Kon geen staflink maken');
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
    if ($size !== '' && !preg_match('/^[\p{L}0-9][\p{L}0-9 +\-\/]*$/u', $size)) {
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
    return rememberTypes($types);
}

function slugTypeName(string $display): string {
    $s = normName($display);
    return $s !== '' ? substr($s, 0, 48) : 'item';
}

function parseItemInput(mixed $v): array {
    if (is_array($v)) {
        $size = (string) ($v['size'] ?? '');
        $want = array_key_exists('want', $v) ? (bool) $v['want'] : !isSkipSize($size);
        $remove = !empty($v['remove']);
        return ['want' => $want, 'remove' => $remove, 'size' => $size];
    }
    $size = (string) $v;
    return ['want' => !isSkipSize($size), 'remove' => false, 'size' => $size];
}

function currentItemSize(mysqli $db, string $who, int $personId, int $typeId): string {
    if ($who === 'player') {
        $st = $db->prepare('SELECT size FROM player_clothing WHERE player_id=? AND clothing_type_id=? ORDER BY id ASC LIMIT 1');
    } elseif ($who === 'staff') {
        $st = $db->prepare('SELECT size FROM staff_clothing WHERE staff_member_id=? AND clothing_type_id=? ORDER BY id ASC LIMIT 1');
    } else {
        return '';
    }
    $st->bind_param('ii', $personId, $typeId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    return trim((string) ($row['size'] ?? ''));
}

function assignPackageToPerson(
    mysqli $db,
    array $types,
    string $who,
    int $personId,
    string $mode,
    bool $onlyMissing = true
): array {
    $person = null;
    if ($who === 'player') {
        $st = $db->prepare('SELECT * FROM players WHERE id=? LIMIT 1');
        $st->bind_param('i', $personId);
        $st->execute();
        $person = $st->get_result()->fetch_assoc() ?: null;
    }
    $shirtTid = $who === 'staff' ? staffShirtTypeId() : 1;
    $shirt = currentItemSize($db, $who, $personId, $shirtTid);
    if ($shirt === '' && $who === 'staff') {
        foreach ([1, 11, 14, fieldShortTypeId(), 4] as $alt) {
            $shirt = currentItemSize($db, $who, $personId, $alt);
            if ($shirt !== '') {
                break;
            }
        }
    }
    if ($shirt === '' && $who === 'player') {
        foreach (array_values(array_unique(array_merge([9], keeperOnlyTypeIds($types)))) as $kid) {
            $t = $types[$kid] ?? null;
            if ($t && typeSizeKind($t) === 'socks') {
                continue;
            }
            $shirt = currentItemSize($db, $who, $personId, (int) $kid);
            if ($shirt !== '') {
                break;
            }
        }
    }
    $shorts = currentItemSize($db, $who, $personId, fieldShortTypeId());
    if ($shorts === '' || $shorts === skipSizeToken()) {
        $shorts = currentItemSize($db, $who, $personId, 4);
    }
    $socks = currentItemSize($db, $who, $personId, 3);
    if ($socks === '') {
        foreach (array_values(array_unique(array_merge([10, 24, 7], keeperOnlyTypeIds($types)))) as $kid) {
            $t = $types[$kid] ?? null;
            if (!$t || typeSizeKind($t) !== 'socks') {
                continue;
            }
            $socks = currentItemSize($db, $who, $personId, (int) $kid);
            if ($socks !== '') {
                break;
            }
        }
    }
    $body = $shirt !== '' ? $shirt : $shorts;
    $saved = 0;
    $skipped = [];
    foreach (packageTypeIdsFor($who, $person) as $tid) {
        if (!isset($types[$tid])) {
            continue;
        }
        if ($who === 'player' && $person && !typeAllowedForPlayer($person, $tid)) {
            continue;
        }
        $kind = typeSizeKind($types[$tid]);
        $opts = sizeOptions($tid);
        if ($opts === ['één maat'] || ($kind === 'onesize' && count($opts) === 1)) {
            $size = $opts[0] ?? 'één maat';
        } elseif (in_array('JR', $opts, true) && in_array('SR', $opts, true)) {
            $size = isYouthPriceSize($body) ? 'JR' : 'SR';
            if (!in_array($size, $opts, true)) {
                $size = '';
            }
        } elseif ($kind === 'socks' || preg_match('/^\d+\/\d+$/', $opts[0] ?? '')) {
            $mapped = normalizeSizeLabel($socks);
            $size = in_array($mapped, $opts, true) ? $mapped : (in_array($socks, $opts, true) ? $socks : '');
        } else {
            $mapped = normalizeSizeLabel($body);
            $size = in_array($mapped, $opts, true) ? $mapped : (in_array($body, $opts, true) ? $body : '');
        }
        if ($size === '' || $size === 'onbekend') {
            $skipped[] = shortTypeName($tid, $types);
            continue;
        }
        if ($onlyMissing && dbHasLiveSlot($db, $who, $personId, $tid)) {
            continue;
        }
        upsertPersonItem($db, $types, $who, $personId, $tid, $size, $mode);
        $saved++;
    }
    return ['saved' => $saved, 'skipped' => $skipped];
}

function personItemRow(mysqli $db, string $who, int $personId, int $typeId): ?array {
    if ($who === 'player') {
        $st = $db->prepare('SELECT id, size, status FROM player_clothing WHERE player_id=? AND clothing_type_id=? ORDER BY id ASC LIMIT 1');
    } elseif ($who === 'staff') {
        $st = $db->prepare('SELECT id, size, status FROM staff_clothing WHERE staff_member_id=? AND clothing_type_id=? ORDER BY id ASC LIMIT 1');
    } else {
        return null;
    }
    $st->bind_param('ii', $personId, $typeId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    return $row ?: null;
}

function dbHasLiveSlot(mysqli $db, string $who, int $personId, int $tid): bool {
    foreach (packageEquivalentTypeIds($tid) as $cid) {
        $row = personItemRow($db, $who, $personId, $cid);
        $status = strtolower(trim((string) ($row['status'] ?? '')));
        if (in_array($status, ['pending', 'active', 'hold', 'nvt'], true)) {
            return true;
        }
    }
    return false;
}

function removePersonItem(mysqli $db, string $who, int $personId, int $typeId): void {
    if ($who === 'player') {
        $sql = 'DELETE FROM player_clothing WHERE player_id=? AND clothing_type_id=?';
    } elseif ($who === 'staff') {
        $sql = 'DELETE FROM staff_clothing WHERE staff_member_id=? AND clothing_type_id=?';
    } else {
        throw new RuntimeException('Ongeldig type');
    }
    $del = $db->prepare($sql);
    foreach (packageEquivalentTypeIds($typeId) as $tid) {
        $tid = (int) $tid;
        if ($tid < 1) {
            continue;
        }
        $del->bind_param('ii', $personId, $tid);
        $del->execute();
    }
}

function applyPersonItemChoice(
    mysqli $db,
    array $types,
    string $who,
    int $personId,
    int $typeId,
    mixed $input,
    string $mode
): bool {
    if ($who === 'player') {
        $st = $db->prepare('SELECT id, position FROM players WHERE id=? LIMIT 1');
        $st->bind_param('i', $personId);
        $st->execute();
        $player = $st->get_result()->fetch_assoc();
        if ($player && !typeAllowedForPlayer($player, $typeId)) {
            return false;
        }
    }
    $parsed = parseItemInput($input);
    if ($parsed['remove']) {
        $existing = personItemRow($db, $who, $personId, $typeId);
        removePersonItem($db, $who, $personId, $typeId);
        return $existing !== null;
    }
    if (!$parsed['want'] || $parsed['size'] === skipSizeToken()) {
        return holdPersonItem($db, $who, $personId, $typeId, (string) $parsed['size']);
    }
    $size = sanitizeSize($parsed['size']);
    if ($size === '') {
        return false;
    }
    upsertPersonItem($db, $types, $who, $personId, $typeId, $size, $mode);
    return true;
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
    if ($size === '' || $size === skipSizeToken()) {
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

function isHeldItem(?array $it): bool {
    return $it !== null && in_array(itemStatus($it), ['hold', 'nvt'], true);
}

function holdPersonItem(mysqli $db, string $who, int $personId, int $typeId, string $size = ''): bool {
    $existing = personItemRow($db, $who, $personId, $typeId);
    if (itemStatus($existing) === 'active') {
        return false;
    }
    $keepSize = sanitizeSize($size);
    if ($keepSize === '' || $keepSize === skipSizeToken()) {
        $keepSize = $existing ? sanitizeSize((string) ($existing['size'] ?? '')) : '';
    }
    if ($keepSize === '' || $keepSize === skipSizeToken()) {
        $keepSize = $existing ? (string) ($existing['size'] ?? '') : 'nvt';
    }
    if ($keepSize === '' || $keepSize === skipSizeToken()) {
        $keepSize = 'nvt';
    }
    $status = 'hold';
    if ($existing) {
        $id = (int) $existing['id'];
        if ($who === 'player') {
            $upd = $db->prepare("UPDATE player_clothing SET size=?, status=?, updated_at=NOW() WHERE id=?");
            $upd->bind_param('ssi', $keepSize, $status, $id);
        } elseif ($who === 'staff') {
            $upd = $db->prepare("UPDATE staff_clothing SET size=?, status=?, updated_at=NOW() WHERE id=?");
            $upd->bind_param('ssi', $keepSize, $status, $id);
        } else {
            return false;
        }
        if (!$upd->execute()) {
            throw new RuntimeException($upd->error !== '' ? $upd->error : 'Kon item niet op hold zetten.');
        }
        return true;
    }
    if ($who === 'player') {
        $ins = $db->prepare('INSERT INTO player_clothing (player_id, clothing_type_id, size, status, created_at, updated_at) VALUES (?,?,?,?,NOW(),NOW())');
        $ins->bind_param('iiss', $personId, $typeId, $keepSize, $status);
    } elseif ($who === 'staff') {
        $ins = $db->prepare('INSERT INTO staff_clothing (staff_member_id, clothing_type_id, size, status, created_at, updated_at) VALUES (?,?,?,?,NOW(),NOW())');
        $ins->bind_param('iiss', $personId, $typeId, $keepSize, $status);
    } else {
        return false;
    }
    if (!$ins->execute()) {
        throw new RuntimeException($ins->error !== '' ? $ins->error : 'Kon item niet op hold zetten.');
    }
    return true;
}

function moneyInput(int $tid, string $field, ?float $value, string $extra = ''): string {
    $val = $value === null ? '' : number_format($value, 2, ',', '');
    $input = '<input class="money" inputmode="decimal" data-tid="'.$tid.'" data-field="'.h($field).'" value="'.h($val).'" placeholder="—"'.$extra.'>';
    if ($value === null) {
        return $input;
    }
    return '<span class="money-wrap">'.$input.'<small class="vat-hint">'.euro(withVat($value)).' incl.</small></span>';
}

function sizeSelect(int $tid, string $current, string $who, int $id, bool $na = false, bool $allowSkip = false, ?array $person = null): string {
    if ($na) {
        return '<span class="muted">n.v.t.</span>';
    }
    $opts = sizeOptions($tid);
    if ($current !== '' && $current !== skipSizeToken() && !in_array($current, $opts, true)) {
        array_unshift($opts, $current);
    }
    $copy = '';
    if ($tid === 4 || $tid === fieldShortTypeId()) {
        $copy = ' data-copy-from="1"';
    } elseif ($tid === 7) {
        $copy = ' data-copy-from="3"';
    } elseif ($tid === 13 || $tid === 14) {
        $copy = ' data-copy-from="1"';
    }
    $html = '<select class="size-select" data-who="'.h($who).'" data-id="'.$id.'" data-tid="'.$tid.'"'.$copy.'>';
    $html .= '<option value="">—</option>';
    if ($allowSkip) {
        $html .= '<option value="'.h(skipSizeToken()).'">n.v.t.</option>';
    }
    foreach ($opts as $o) {
        $sel = $o === $current ? ' selected' : '';
        $html .= '<option value="'.h($o).'"'.$sel.'>'.h($o).'</option>';
    }
    $html .= '</select>';
    return $html;
}

function normalizeJerseyNumber(mixed $raw): ?string {
    $s = trim((string) $raw);
    if ($s === '' || strtolower($s) === 'null') {
        return null;
    }
    if (!preg_match('/^\d{1,2}$/', $s)) {
        return null;
    }
    $n = (int) $s;
    if ($n < 1 || $n > 99) {
        return null;
    }
    return (string) $n;
}

/** Rugnummers in gebruik door andere actieve spelers. */
function takenJerseyNumbers(mysqli $db, int $excludePlayerId = 0): array {
    $taken = [];
    $st = $db->prepare(
        "SELECT id, jersey_number FROM players
         WHERE status='active' AND IFNULL(is_guest,0)=0
           AND jersey_number IS NOT NULL AND TRIM(jersey_number) <> ''"
    );
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $id = (int) ($row['id'] ?? 0);
        if ($excludePlayerId > 0 && $id === $excludePlayerId) {
            continue;
        }
        $num = normalizeJerseyNumber($row['jersey_number'] ?? '');
        if ($num !== null) {
            $taken[$num] = $id;
        }
    }
    return $taken;
}

function availableJerseyNumbers(mysqli $db, int $playerId = 0, ?string $current = null): array {
    $taken = takenJerseyNumbers($db, $playerId);
    $cur = normalizeJerseyNumber($current);
    $out = [];
    for ($i = 1; $i <= 99; $i++) {
        $n = (string) $i;
        if (isset($taken[$n]) && $n !== $cur) {
            continue;
        }
        $out[] = $n;
    }
    return $out;
}

/**
 * @param array{id?:string,required?:bool,allow_empty?:bool,class?:string} $opts
 */
function jerseySelectHtml(mysqli $db, array $player, array $opts = []): string {
    $pid = (int) ($player['id'] ?? 0);
    $current = normalizeJerseyNumber($player['jersey_number'] ?? '');
    $numbers = availableJerseyNumbers($db, $pid, $current);
    $required = !empty($opts['required']);
    $allowEmpty = array_key_exists('allow_empty', $opts) ? (bool) $opts['allow_empty'] : !$required;
    $class = h($opts['class'] ?? 'size-select jersey-select');
    $idAttr = isset($opts['id']) && $opts['id'] !== '' ? ' id="' . h((string) $opts['id']) . '"' : '';
    $html = '<select class="' . $class . '"' . $idAttr
        . ' name="jersey_number"'
        . ($required ? ' required' : '')
        . ' data-jersey="1" data-who="player" data-id="' . $pid . '"'
        . ' aria-label="Rugnummer">';
    $html .= '<option value="">' . ($allowEmpty ? 'Geen nummer' : 'Kies nummer…') . '</option>';
    foreach ($numbers as $o) {
        $sel = ($current !== null && $o === $current) ? ' selected' : '';
        $html .= '<option value="' . h($o) . '"' . $sel . '>#' . h($o) . '</option>';
    }
    $html .= '</select>';
    return $html;
}

/**
 * Zet rugnummer voor speler; faalt als nummer al bezet is.
 * @return string|null Gekozen nummer, of null als gewist (alleen met $allowEmpty)
 * @throws RuntimeException
 */
function setPlayerJerseyNumber(mysqli $db, int $playerId, mixed $raw, bool $allowEmpty = false): ?string {
    $trimmed = trim((string) ($raw ?? ''));
    $num = normalizeJerseyNumber($raw);
    if ($num === null) {
        if ($allowEmpty && $trimmed === '') {
            $upd = $db->prepare('UPDATE players SET jersey_number=NULL, updated_at=NOW() WHERE id=?');
            $upd->bind_param('i', $playerId);
            $upd->execute();
            return null;
        }
        throw new RuntimeException('Kies een rugnummer (1–99).');
    }
    $nInt = (int) $num;
    $st = $db->prepare(
        "SELECT id, first_name, last_name FROM players
         WHERE status='active' AND IFNULL(is_guest,0)=0
           AND id <> ?
           AND jersey_number IS NOT NULL AND TRIM(jersey_number) <> ''
           AND CAST(TRIM(jersey_number) AS UNSIGNED) = ?
         LIMIT 1 FOR UPDATE"
    );
    $st->bind_param('ii', $playerId, $nInt);
    $st->execute();
    $other = $st->get_result()->fetch_assoc();
    if ($other) {
        $who = trim(($other['first_name'] ?? '') . ' ' . ($other['last_name'] ?? ''));
        throw new RuntimeException('Nummer #' . $num . ' is al gekozen' . ($who !== '' ? ' door ' . $who : '') . '. Kies een ander nummer.');
    }
    $upd = $db->prepare('UPDATE players SET jersey_number=?, updated_at=NOW() WHERE id=?');
    $upd->bind_param('si', $num, $playerId);
    $upd->execute();
    return $num;
}

function xlsxColumnName(int $index): string {
    $name = '';
    $n = $index + 1;
    while ($n > 0) {
        $n--;
        $name = chr(65 + ($n % 26)) . $name;
        $n = intdiv($n, 26);
    }
    return $name;
}

function xlsxSheetXml(array $rows): string {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    $r = 1;
    foreach ($rows as $row) {
        $xml .= '<row r="' . $r . '">';
        $c = 0;
        foreach ($row as $val) {
            if ($val === null || $val === '') {
                $c++;
                continue;
            }
            $ref = xlsxColumnName($c) . $r;
            if (is_int($val) || is_float($val) || (is_string($val) && preg_match('/^-?\d+$/', $val))) {
                $xml .= '<c r="' . $ref . '"><v>' . $val . '</v></c>';
            } else {
                $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
                    . htmlspecialchars((string) $val, ENT_XML1 | ENT_QUOTES, 'UTF-8')
                    . '</t></is></c>';
            }
            $c++;
        }
        $xml .= '</row>';
        $r++;
    }
    $xml .= '</sheetData></worksheet>';
    return $xml;
}

function sendXlsxDownload(string $filename, array $rows): void {
    $tmp = tempnam(sys_get_temp_dir(), 'krx');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Kon Excel-bestand niet maken.');
    }
    $zip->addFromString(
        '[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>'
    );
    $zip->addFromString(
        '_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>'
    );
    $zip->addFromString(
        'xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Bestelling" sheetId="1" r:id="rId1"/></sheets></workbook>'
    );
    $zip->addFromString(
        'xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>'
    );
    $zip->addFromString('xl/worksheets/sheet1.xml', xlsxSheetXml($rows));
    $zip->close();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    header('Content-Length: ' . (string) filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

function kitSizeRank(string $size): array {
    $order = ['116', '128', '140', '152', '164', 'S', 'M', 'L', 'XL', 'XXL', '2XL', 'XXXL', '3XL', 'JR', 'SR', '25/29', '30/35', '36/40', '41/44', '45/48', '31-35', '36-40', '41-44', '45-47', 'één maat', 'maat onbekend', 'onbekend'];
    $i = array_search($size, $order, true);
    return [$i === false ? 999 : $i, $size];
}

function itemPrintMarks(array $g): array {
    $t = $g['type'] ?? [];
    $ini = trim((string) ($g['ini'] ?? ''));
    $jersey = trim((string) ($g['jersey'] ?? ''));
    $rohda = typePrints($t, 'print_rohda');
    $sponsor = typePrints($t, 'print_sponsor');
    $sponsorBack = typePrints($t, 'print_sponsor_back');
    $sponsorPadded = typePrints($t, 'print_sponsor_padded');
    $sponsorJacket = typePrints($t, 'print_sponsor_jacket');
    $sponsorBag = typePrints($t, 'print_sponsor_bag');
    $wantIni = typePrints($t, 'print_initials');
    $wantNum = typePrints($t, 'print_name_back');
    $staffText = typePrints($t, 'print_staff_text');
    $bits = [];
    if ($wantIni) {
        $bits[] = $ini !== '' ? $ini : 'initialen ontbreken';
    }
    if ($wantNum) {
        $bits[] = $jersey !== '' ? '#' . $jersey : 'nummer ontbreekt';
    }
    if ($staffText) {
        $bits[] = 'Tekst staf';
    }
    if ($rohda) {
        $bits[] = 'Clublogo';
    }
    if ($sponsor) {
        $bits[] = 'Sponsor shirts voorkant';
    }
    if ($sponsorBack) {
        $bits[] = 'Sponsor shirts achterkant';
    }
    if ($sponsorPadded) {
        $bits[] = 'Sponsor padded (gezamenlijk)';
    }
    if ($sponsorJacket) {
        $bits[] = 'Sponsor field jack achterkant';
    }
    if ($sponsorBag) {
        $bits[] = 'Sponsor tas';
    }
    return [
        'any' => $wantIni || $wantNum || $staffText || $rohda || $sponsor || $sponsorBack || $sponsorPadded || $sponsorJacket || $sponsorBag,
        'ini' => $wantIni ? ($ini !== '' ? $ini : 'ontbreekt') : '',
        'num' => $wantNum ? ($jersey !== '' ? '#' . $jersey : 'ontbreekt') : '',
        'rohda' => $rohda ? 'ja' : '',
        'sponsor' => $sponsor ? 'ja' : '',
        'sponsor_back' => $sponsorBack ? 'ja' : '',
        'on' => implode(' · ', $bits),
    ];
}

function orderListRows(array $shopByType, int $orderPieces, array $gaps = [], ?DateTimeInterface $stamp = null): array {
    $stamp ??= new DateTimeImmutable('now', new DateTimeZone('Europe/Amsterdam'));
    $when = $stamp->format('d-m-Y H:i');
    $rows = [];
    $rows[] = ['Bestelling 14-2 · versie ' . $when];
    $rows[] = ['Winkel: aantallen per maat. Drukker: per maat de initialen/nummers, en per stuk precies wat erop moet.'];
    $rows[] = ['Clublogo: jassen, shirt, keeperstenue, tas. Sponsor shirts voor/achter: shirt en keeperstenue. Sponsor padded: winterjas. Sponsor regenjas: achterkant. Sponsor tas. Initialen: jassen, shirt, broekje, keeperstenue, tas. Nummer achterop: shirt.'];
    $rows[] = [];
    $rows[] = ['BESTELLEN · AANTALLEN PER MAAT'];
    $rows[] = ['Product', 'Artikelnummer', 'Merk', 'Kleur', 'Maat', 'Aantal'];
    foreach ($shopByType as $shop) {
        $art = trim((string) ($shop['article'] ?? ''));
        $brand = trim((string) ($shop['brand'] ?? ''));
        if ($brand === '') {
            $brand = 'Stanno';
        }
        foreach ($shop['sizes'] as $sz => $cnt) {
            $rows[] = [$shop['label'], $art, $brand, $shop['color'], $sz, (int) $cnt];
        }
    }
    $rows[] = ['Alle producten · totaal', '', '', '', '', $orderPieces];
    $rows[] = [];
    $rows[] = ['BEDRUKKEN · PER MAAT'];
    $rows[] = ['Product', 'Artikelnummer', 'Maat', 'Aantal', 'Initialen op deze maat', 'Nummers op deze maat', 'Clublogo', 'Sponsor voorkant', 'Sponsor achterkant'];
    foreach ($shopByType as $shop) {
        $hasPrint = !empty($shop['rohda']) || !empty($shop['initials']) || !empty($shop['sponsor']) || !empty($shop['sponsor_back']) || !empty($shop['sponsor_padded']) || !empty($shop['sponsor_jacket']) || !empty($shop['sponsor_bag']) || !empty($shop['name_back']) || !empty($shop['staff_text']);
        if (!$hasPrint) {
            continue;
        }
        $art = trim((string) ($shop['article'] ?? ''));
        foreach ($shop['sizes'] as $sz => $cnt) {
            $line = $shop['size_lines'][$sz] ?? ['letters' => [], 'numbers' => []];
            $rows[] = [
                $shop['label'],
                $art,
                $sz,
                (int) $cnt,
                !empty($line['letters']) ? implode(', ', $line['letters']) : '',
                !empty($line['numbers']) ? implode(', ', array_map(static fn($n) => '#' . $n, $line['numbers'])) : '',
                !empty($shop['rohda']) ? 'ja' : '',
                !empty($shop['sponsor']) ? 'ja' : '',
                !empty($shop['sponsor_back']) ? 'ja' : '',
            ];
        }
    }

    $printGaps = [];
    foreach ($gaps as $g) {
        $marks = itemPrintMarks($g);
        if (!$marks['any']) {
            continue;
        }
        $g['marks'] = $marks;
        $printGaps[] = $g;
    }
    usort($printGaps, static function (array $a, array $b): int {
        $sa = (string) (($a['size'] ?? '') !== '' ? $a['size'] : 'maat onbekend');
        $sb = (string) (($b['size'] ?? '') !== '' ? $b['size'] : 'maat onbekend');
        return [(int) $a['tid'], kitSizeRank($sa), mb_strtolower((string) $a['who'], 'UTF-8')]
            <=> [(int) $b['tid'], kitSizeRank($sb), mb_strtolower((string) $b['who'], 'UTF-8')];
    });

    $rows[] = [];
    $rows[] = ['BEDRUKKEN · PER STUK'];
    $rows[] = ['Product', 'Artikelnummer', 'Maat', 'Speler', 'Initialen', 'Nummer', 'Clublogo', 'Sponsor voorkant', 'Sponsor achterkant', 'Op dit stuk'];
    foreach ($printGaps as $g) {
        $tid = (int) $g['tid'];
        $shop = $shopByType[$tid] ?? [];
        $size = (string) (($g['size'] ?? '') !== '' ? $g['size'] : 'maat onbekend');
        $m = $g['marks'];
        $rows[] = [
            $shop['label'] ?? shortTypeName($tid),
            trim((string) ($shop['article'] ?? ($g['type']['article_number'] ?? ''))),
            $size,
            (string) $g['who'],
            $m['ini'],
            $m['num'],
            $m['rohda'],
            $m['sponsor'],
            $m['sponsor_back'],
            $m['on'],
        ];
    }

    $byWho = [];
    foreach ($printGaps as $g) {
        $who = (string) $g['who'];
        $byWho[$who][] = $g;
    }
    uksort($byWho, 'strcasecmp');
    $rows[] = [];
    $rows[] = ['CONTROLE · PER SPELER'];
    $rows[] = ['Speler', 'Initialen', 'Nummer', 'Product', 'Maat', 'Op dit stuk'];
    foreach ($byWho as $who => $items) {
        usort($items, static function (array $a, array $b): int {
            $sa = (string) (($a['size'] ?? '') !== '' ? $a['size'] : 'maat onbekend');
            $sb = (string) (($b['size'] ?? '') !== '' ? $b['size'] : 'maat onbekend');
            return [(int) $a['tid'], kitSizeRank($sa)] <=> [(int) $b['tid'], kitSizeRank($sb)];
        });
        foreach ($items as $g) {
            $tid = (int) $g['tid'];
            $shop = $shopByType[$tid] ?? [];
            $size = (string) (($g['size'] ?? '') !== '' ? $g['size'] : 'maat onbekend');
            $m = $g['marks'];
            $ini = (string) ($g['ini'] ?? '');
            $jersey = trim((string) ($g['jersey'] ?? ''));
            $rows[] = [
                $who,
                $ini,
                $jersey !== '' ? '#' . $jersey : '',
                $shop['label'] ?? shortTypeName($tid),
                $size,
                $m['on'],
            ];
        }
    }

    return $rows;
}

function kitroomBackupDir(): string {
    return __DIR__ . '/.data/backups';
}

function sqlBackupIdent(string $name): string {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new RuntimeException('Ongeldige tabelnaam in backup.');
    }
    return '`' . $name . '`';
}

function sqlBackupDump(mysqli $db): string {
    $sql = "-- Kitroom 14-2 backup\n-- " . (new DateTimeImmutable('now', new DateTimeZone('Europe/Amsterdam')))->format('d-m-Y H:i') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    $tables = [];
    $res = $db->query('SHOW TABLES');
    if (!$res) {
        throw new RuntimeException('Kon tabellen niet lezen.');
    }
    while ($row = $res->fetch_row()) {
        $tables[] = (string) $row[0];
    }
    foreach ($tables as $table) {
        $ident = sqlBackupIdent($table);
        $createRes = $db->query('SHOW CREATE TABLE ' . $ident);
        $create = $createRes ? $createRes->fetch_assoc() : null;
        $createSql = (string) ($create['Create Table'] ?? '');
        if ($createSql === '') {
            throw new RuntimeException('Kon tabelstructuur niet lezen.');
        }
        $sql .= 'DROP TABLE IF EXISTS ' . $ident . ";\n" . $createSql . ";\n\n";
        $data = $db->query('SELECT * FROM ' . $ident);
        if (!$data) {
            continue;
        }
        while ($row = $data->fetch_assoc()) {
            $cols = [];
            $vals = [];
            foreach ($row as $col => $val) {
                $cols[] = sqlBackupIdent((string) $col);
                if ($val === null) {
                    $vals[] = 'NULL';
                } else {
                    $vals[] = "'" . $db->real_escape_string((string) $val) . "'";
                }
            }
            $sql .= 'INSERT INTO ' . $ident . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ");\n";
        }
        $sql .= "\n";
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $sql;
}

function pruneKitroomBackups(string $dir, int $keep = 15): void {
    $files = glob($dir . '/kitroom-14-2-*.zip') ?: [];
    usort($files, static fn($a, $b) => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
    foreach (array_slice($files, $keep) as $old) {
        @unlink($old);
    }
}

function listKitroomBackups(int $limit = 5): array {
    $dir = kitroomBackupDir();
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/kitroom-14-2-*.zip') ?: [];
    usort($files, static fn($a, $b) => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
    $out = [];
    foreach (array_slice($files, 0, $limit) as $path) {
        $out[] = [
            'file' => basename($path),
            'at' => date('d-m-Y H:i', filemtime($path) ?: time()),
            'kb' => max(1, (int) round((filesize($path) ?: 0) / 1024)),
        ];
    }
    return $out;
}

function makeKitroomBackup(mysqli $db): array {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Zip is niet beschikbaar op de server.');
    }
    $dir = kitroomBackupDir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $stamp = new DateTimeImmutable('now', new DateTimeZone('Europe/Amsterdam'));
    $filename = 'kitroom-14-2-' . $stamp->format('Y-m-d-H-i') . '.zip';
    $tmp = tempnam(sys_get_temp_dir(), 'krb');
    if ($tmp === false) {
        throw new RuntimeException('Kon tijdelijk backupbestand niet maken.');
    }
    @unlink($tmp);
    $zip = new ZipArchive();
    $opened = $zip->open($tmp, ZipArchive::CREATE);
    if ($opened !== true) {
        throw new RuntimeException('Kon backupbestand niet maken.');
    }
    $zip->addFromString('kitroom.sql', sqlBackupDump($db));
    $dataDir = __DIR__ . '/.data';
    foreach (['kit-settings.json', 'parent-form.json'] as $name) {
        $file = $dataDir . '/' . $name;
        if (is_readable($file)) {
            $zip->addFromString($name, (string) file_get_contents($file));
        }
    }
    $zip->addFromString(
        'leesmij.txt',
        "Kitroom 14-2 backup\n"
        . 'Datum: ' . $stamp->format('d-m-Y H:i') . "\n"
        . "Inhoud: kitroom.sql (database) plus kit-settings.json en parent-form.json.\n"
        . "Pincode en databasewachtwoord zitten niet in dit bestand.\n"
    );
    $zip->close();
    if (!is_file($tmp) || filesize($tmp) < 32) {
        @unlink($tmp);
        throw new RuntimeException('Backupbestand is leeg.');
    }
    $path = $tmp;
    if (is_dir($dir) && is_writable($dir)) {
        $dest = $dir . '/' . $filename;
        if (@rename($tmp, $dest) || @copy($tmp, $dest)) {
            if (is_file($dest)) {
                if (is_file($tmp) && realpath($tmp) !== realpath($dest)) {
                    @unlink($tmp);
                }
                $path = $dest;
                pruneKitroomBackups($dir);
            }
        }
    }
    return ['path' => $path, 'filename' => $filename];
}

ensureParentTokenColumn($mysqli);
ensureParentSavedAtColumn($mysqli);
ensureStaffFillColumns($mysqli);
ensureClothingHoldStatus($mysqli);
ensurePackageTypes($mysqli);
ensureFieldShortType($mysqli);
ensureSponsorPrintSplit($mysqli);
ensureStaffTextPrint($mysqli);
ensureSponsorQuoteKinds($mysqli);
ensureStaffShirtType($mysqli);
syncKitPrintFromPhotos($mysqli);
seedStannoTypeSizes($mysqli);
migrateAssignedSizeAliases($mysqli);
startTeamSession();
$canEdit = canEdit();
$csrf = csrfToken();
