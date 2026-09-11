<?php
declare(strict_types=1);

require __DIR__ . '/boot.php';

$posLabel = [
    'attacker' => 'Aanval',
    'midfielder' => 'Middenveld',
    'defender' => 'Verdediging',
    'goalkeeper' => 'Keeper',
];
$posOrder = ['goalkeeper', 'defender', 'midfielder', 'attacker'];

$types = loadTypes($mysqli);
cleanupMismatchedPlayerKit($mysqli);
$typeAssigned = clothingTypeAssignmentCounts($mysqli);

$FIELD_CORE = [1, 4, 24, 7];
$KEEPER_CORE = keeperCoreTypeIds($types);
$PACKAGE_CORE = packageTypeIds();
$KEEPER_ONLY = keeperOnlyTypeIds($types);
$STAFF_CORE = [11, 12];
$kitSettings = loadKitSettings();
$printPrices = $kitSettings['print'];
$catalogPrint = catalogPrintPrices($types);
foreach ($catalogPrint as $key => $unit) {
    if ($unit !== null) {
        $printPrices[$key] = $unit;
    }
}

function cardTypeIds(array $p, array $types, array $packageIds, array $keeperOnly): array {
    $out = [];
    foreach (array_keys($p['items'] ?? []) as $tid) {
        $tid = (int) $tid;
        if ($tid < 1 || !isset($types[$tid]) || isset($out[$tid])) {
            continue;
        }
        if (!itemFor($p, $tid)) {
            continue;
        }
        if (!typeAllowedForPlayer($p, $tid)) {
            continue;
        }
        $out[$tid] = $tid;
    }
    return array_values($out);
}

$players = [];
$res = $mysqli->query('SELECT * FROM players ORDER BY last_name, first_name');
while ($row = $res->fetch_assoc()) {
    $row['id'] = (int) $row['id'];
    $row['items'] = [];
    $players[$row['id']] = $row;
}
$res = $mysqli->query('SELECT * FROM player_clothing');
while ($row = $res->fetch_assoc()) {
    $pid = (int) $row['player_id'];
    if (!isset($players[$pid])) {
        continue;
    }
    $players[$pid]['items'][(int) $row['clothing_type_id']][] = $row;
}

$staff = [];
$res = $mysqli->query('SELECT * FROM staff_members ORDER BY id');
while ($row = $res->fetch_assoc()) {
    $row['id'] = (int) $row['id'];
    $row['items'] = [];
    $staff[$row['id']] = $row;
}
$res = $mysqli->query('SELECT * FROM staff_clothing');
while ($row = $res->fetch_assoc()) {
    $sid = (int) $row['staff_member_id'];
    if (!isset($staff[$sid])) {
        continue;
    }
    $staff[$sid]['items'][(int) $row['clothing_type_id']][] = $row;
}

$staffAll = array_values($staff);
$staff = array_values(array_filter($staffAll, static fn($s) => ($s['status'] ?? '') === 'active'));
$season = trim((string) ($kitSettings['season'] ?? '26/27')) ?: '26/27';

function parentChecksHtml(string $scope, array $choices, array $selected, int $playerId = 0, array $defaultIds = []): string {
    $html = '<div class="checks" data-parent-scope="'.h($scope).'" data-id="'.$playerId.'" data-default="'.h(implode(',', $defaultIds)).'">';
    foreach ($choices as $tid) {
        $on = in_array($tid, $selected, true) ? ' checked' : '';
        $html .= '<label><input type="checkbox" value="'.$tid.'"'.$on.'> '.h(shortTypeName($tid, rememberTypes())).'</label>';
    }
    $html .= '</div>';
    return $html;
}

$portal = loadScoutPortal();
syncPlayersFromScout($mysqli, $players, $portal);
archivePlayersNotOnScoutTeam14($mysqli, $players, $portal);

$active = array_values(array_filter($players, static fn($p) => ($p['status'] ?? '') === 'active' && !(int) $p['is_guest'] && playerOnScoutTeam14($p, $portal)));

foreach ($active as &$p) {
    $info = findScoutForPlayer($p, $portal);
    $p['scout_pos'] = $info['pos'] ?? '';
    $p['scout_type'] = $info['type'] ?? '';
    $p['voet'] = $info['voet'] ?? '';
    $p['jaar'] = $info['jaar'] ?? '';
    if ($info) {
        $p['scout_id'] = (int) $info['id'];
        $line = scoutLineFromPos($info['pos']);
        if ($line !== '') {
            $p['position'] = $line;
        }
    }
    $p['need'] = ($p['position'] ?? '') === 'goalkeeper' ? $KEEPER_CORE : $FIELD_CORE;
    $p['card_types'] = cardTypeIds($p, $types, $PACKAGE_CORE, $KEEPER_ONLY);
    $p['missing'] = [];
    $p['to_order'] = [];
    $p['owned'] = [];
    foreach ($p['items'] as $tid => $list) {
        $tid = (int) $tid;
        $it = itemFor($p, $tid);
        if (isPendingItem($it)) {
            $p['to_order'][] = $tid;
        } elseif (isIssued($it)) {
            $p['owned'][] = $tid;
        }
    }
    $p['miss'] = count($p['to_order']);
    $p['complete'] = $p['miss'] === 0 && $p['owned'] !== [];
}
unset($p);

usort($active, static function ($a, $b) use ($posOrder) {
    $pa = array_search($a['position'] ?? '', $posOrder, true);
    $pb = array_search($b['position'] ?? '', $posOrder, true);
    $pa = $pa === false ? 99 : $pa;
    $pb = $pb === false ? 99 : $pb;
    if ($pa !== $pb) {
        return $pa <=> $pb;
    }
    return strcasecmp(fullName($a), fullName($b));
});

$guestPlayers = [];
foreach ($guestPlayers as &$p) {
    $p['scout_pos'] = '';
    $p['scout_type'] = '';
    $p['voet'] = '';
    $p['jaar'] = '';
    $p['need'] = ($p['position'] ?? '') === 'goalkeeper' ? $KEEPER_CORE : $FIELD_CORE;
    $p['card_types'] = cardTypeIds($p, $types, $PACKAGE_CORE, $KEEPER_ONLY);
    $p['missing'] = [];
    $p['to_order'] = [];
    $p['owned'] = [];
    foreach ($p['items'] as $tid => $list) {
        $tid = (int) $tid;
        $it = itemFor($p, $tid);
        if (isPendingItem($it)) {
            $p['to_order'][] = $tid;
        } elseif (isIssued($it)) {
            $p['owned'][] = $tid;
        }
    }
    $p['miss'] = count($p['to_order']);
    $p['complete'] = $p['miss'] === 0 && $p['owned'] !== [];
}
unset($p);

$parentFilled = array_values(array_filter($active, static fn($p) => !empty($p['parent_saved_at'])));
usort($parentFilled, static fn($a, $b) => strcmp((string) ($b['parent_saved_at'] ?? ''), (string) ($a['parent_saved_at'] ?? '')));
$parentFillJs = array_map(static function ($p) {
    $ts = strtotime((string) $p['parent_saved_at']);
    return [
        'id' => (int) $p['id'],
        'name' => fullName($p),
        'at' => $ts ? $ts * 1000 : 0,
    ];
}, $parentFilled);

$parentLinks = [];
if ($canEdit) {
    $linkPlayers = [];
    foreach (array_merge($active, $guestPlayers) as $lp) {
        $linkPlayers[(int) $lp['id']] = $lp;
    }
    foreach ($linkPlayers as $lp) {
        $tok = playerParentToken($mysqli, (int) $lp['id']);
        $url = parentLinkUrl($tok);
        $nm = fullName($lp);
        $parentLinks[(int) $lp['id']] = [
            'name' => $nm,
            'url' => $url,
            'wa' => parentWhatsAppUrl($nm, $url),
            'missing' => (int) ($lp['miss'] ?? 0),
            'types' => parentAllowedTypeIds($lp),
            'custom' => parentUsesCustomTypes($lp),
            'saved' => $lp['parent_saved_at'] ?? null,
            'position' => (string) ($lp['position'] ?? ''),
            'player' => $lp,
        ];
    }
}

$staffLinks = [];
if ($canEdit) {
    foreach ($staff as $ls) {
        $tok = staffFillToken($mysqli, (int) $ls['id']);
        $url = parentLinkUrl($tok);
        $nm = fullName($ls);
        $msg = staffFillMessage($nm, $url);
        $staffLinks[(int) $ls['id']] = [
            'name' => $nm,
            'role' => (string) ($ls['role'] ?? 'staf'),
            'url' => $url,
            'wa' => fillWhatsAppUrl($msg),
            'types' => staffAllowedTypeIds($ls),
            'custom' => staffUsesCustomTypes($ls),
            'saved' => $ls['parent_saved_at'] ?? null,
            'staff' => $ls,
        ];
    }
}

$gaps = [];
$addGap = static function (array $person, int $tid, string $whoLabel) use (&$gaps, $types): void {
    $t = $types[$tid] ?? null;
    $it = itemFor($person, $tid);
    if (!$t || !typeIsActive($t) || isPrintCatalogType($t) || !isPendingItem($it)) {
        return;
    }
    $sz = trim((string) ($it['size'] ?? ''));
    $gaps[] = [
        'who' => $whoLabel,
        'first' => trim((string) ($person['first_name'] ?? '')),
        'ini' => playerInitials($person),
        'jersey' => trim((string) ($person['jersey_number'] ?? '')),
        'tid' => $tid,
        'type' => $t,
        'size' => $sz,
        'price' => priceFor($t, $sz),
    ];
};
foreach ($active as $p) {
    foreach (array_keys($p['items']) as $tid) {
        $tid = (int) $tid;
        if (!typeAllowedForPlayer($p, $tid)) {
            continue;
        }
        $addGap($p, $tid, fullName($p));
    }
}
foreach ($staff as $s) {
    foreach (array_keys($s['items']) as $tid) {
        $addGap($s, (int) $tid, fullName($s) . ' (staf)');
    }
}
$orderGroups = [];
$orderBrand = ['rohda' => 0, 'initials' => 0, 'sponsor' => 0, 'sponsor_back' => 0, 'sponsor_padded' => 0, 'sponsor_jacket' => 0, 'sponsor_bag' => 0, 'name_back' => 0, 'staff_text' => 0];
foreach ($gaps as $g) {
    $key = $g['tid'] . '|' . ($g['size'] !== '' ? $g['size'] : 'onbekend');
    $t = $g['type'];
    if (!isset($orderGroups[$key])) {
        $orderGroups[$key] = [
            'tid' => $g['tid'],
            'type' => $t['display_name'],
            'article' => $t['article_number'] ?? '',
            'color' => $t['color'] ?? '',
            'brand' => $t['brand'] ?? '',
            'place' => (string) ($t['print_place'] ?? ''),
            'size' => $g['size'] !== '' ? $g['size'] : 'maat onbekend',
            'count' => 0,
            'names' => [],
            'price' => $g['price'],
            'band' => isYouthPriceSize($g['size']) ? 'small' : 'large',
            'rohda' => typePrints($t, 'print_rohda'),
            'initials' => typePrints($t, 'print_initials'),
            'sponsor' => typePrints($t, 'print_sponsor'),
            'sponsor_back' => typePrints($t, 'print_sponsor_back'),
            'sponsor_padded' => typePrints($t, 'print_sponsor_padded'),
            'sponsor_jacket' => typePrints($t, 'print_sponsor_jacket'),
            'sponsor_bag' => typePrints($t, 'print_sponsor_bag'),
            'name_back' => typePrints($t, 'print_name_back'),
            'staff_text' => typePrints($t, 'print_staff_text'),
        ];
    }
    $orderGroups[$key]['count']++;
    $label = $g['who'] . ($g['ini'] !== '' ? ' · ' . $g['ini'] : '');
    $orderGroups[$key]['names'][] = $label;
    if ($orderGroups[$key]['rohda']) {
        $orderBrand['rohda']++;
    }
    if ($orderGroups[$key]['initials']) {
        $orderBrand['initials']++;
    }
    if ($orderGroups[$key]['sponsor']) {
        $orderBrand['sponsor']++;
    }
    if ($orderGroups[$key]['sponsor_back']) {
        $orderBrand['sponsor_back']++;
    }
    if ($orderGroups[$key]['sponsor_padded']) {
        $orderBrand['sponsor_padded']++;
    }
    if ($orderGroups[$key]['sponsor_jacket']) {
        $orderBrand['sponsor_jacket']++;
    }
    if ($orderGroups[$key]['sponsor_bag']) {
        $orderBrand['sponsor_bag']++;
    }
    if ($orderGroups[$key]['name_back']) {
        $orderBrand['name_back']++;
    }
    if ($orderGroups[$key]['staff_text']) {
        $orderBrand['staff_text']++;
    }
}
usort($orderGroups, static fn($a, $b) => [$a['tid'], $a['size']] <=> [$b['tid'], $b['size']]);
usort($gaps, static fn($a, $b) => [$a['tid'], $a['who'], $a['size']] <=> [$b['tid'], $b['who'], $b['size']]);

$orderPieces = array_sum(array_column($orderGroups, 'count'));
$orderCost = 0.0;
foreach ($orderGroups as $g) {
    if ($g['price'] === null) {
        continue;
    }
    $orderCost += $g['price'] * $g['count'];
}
$printCost = 0.0;
$printLines = [
    'rohda' => 'Rohda Raalte logo',
    'initials' => 'Initialen',
    'sponsor' => 'Sponsor shirts voorkant',
    'sponsor_back' => 'Sponsor shirts achterkant',
    'sponsor_padded' => 'Sponsor padded (gezamenlijk)',
    'sponsor_jacket' => 'Sponsor field jack achterkant',
    'sponsor_bag' => 'Sponsor tas',
    'name_back' => 'Nummer achterop',
    'staff_text' => 'Tekst staf',
];
$printRows = [];
foreach ($printLines as $key => $label) {
    $unit = $printPrices[$key] ?? null;
    $n = (int) $orderBrand[$key];
    $sum = ($unit !== null && $n > 0) ? $unit * $n : null;
    if ($unit !== null) {
        $printCost += $unit * $n;
    }
    $printRows[$key] = ['label' => $label, 'count' => $n, 'unit' => $unit, 'sum' => $sum];
}
$orderTotal = $orderCost + $printCost;

// Overzicht voor de drukker: per product maten + bedrukking-aantallen
$sizeRank = static function (string $size): array {
    $order = ['116', '128', '140', '152', '164', 'S', 'M', 'L', 'XL', 'XXL', '2XL', 'XXXL', '3XL', 'JR', 'SR', '25/29', '30/35', '36/40', '41/44', '45/48', '31-35', '36-40', '41-44', '45-47', 'één maat', 'maat onbekend', 'onbekend'];
    $i = array_search($size, $order, true);
    return [$i === false ? 999 : $i, $size];
};
$shopByType = [];
foreach ($orderGroups as $g) {
    $tid = (int) $g['tid'];
    if (!isset($shopByType[$tid])) {
        $shopByType[$tid] = [
            'tid' => $tid,
            'label' => shortTypeName($tid, $types),
            'article' => (string) $g['article'],
            'color' => (string) $g['color'],
            'brand' => (string) ($g['brand'] ?? ''),
            'place' => (string) $g['place'],
            'sizes' => [],
            'count' => 0,
            'rohda' => 0,
            'initials' => 0,
            'sponsor' => 0,
            'sponsor_back' => 0,
            'sponsor_padded' => 0,
            'sponsor_jacket' => 0,
            'sponsor_bag' => 0,
            'name_back' => 0,
            'staff_text' => 0,
            'numbers' => [],
            'letters' => [],
            'size_lines' => [],
            'cost' => 0.0,
        ];
    }
    $sz = (string) $g['size'];
    $shopByType[$tid]['sizes'][$sz] = ($shopByType[$tid]['sizes'][$sz] ?? 0) + (int) $g['count'];
    $shopByType[$tid]['count'] += (int) $g['count'];
    if ($g['rohda']) {
        $shopByType[$tid]['rohda'] += (int) $g['count'];
    }
    if ($g['initials']) {
        $shopByType[$tid]['initials'] += (int) $g['count'];
    }
    if ($g['sponsor']) {
        $shopByType[$tid]['sponsor'] += (int) $g['count'];
    }
    if ($g['sponsor_back']) {
        $shopByType[$tid]['sponsor_back'] += (int) $g['count'];
    }
    if (!empty($g['sponsor_padded'])) {
        $shopByType[$tid]['sponsor_padded'] += (int) $g['count'];
    }
    if (!empty($g['sponsor_jacket'])) {
        $shopByType[$tid]['sponsor_jacket'] += (int) $g['count'];
    }
    if (!empty($g['sponsor_bag'])) {
        $shopByType[$tid]['sponsor_bag'] += (int) $g['count'];
    }
    if ($g['name_back']) {
        $shopByType[$tid]['name_back'] += (int) $g['count'];
    }
    if (!empty($g['staff_text'])) {
        $shopByType[$tid]['staff_text'] += (int) $g['count'];
    }
    if ($g['price'] !== null) {
        $shopByType[$tid]['cost'] += $g['price'] * $g['count'];
    }
}
foreach ($gaps as $g) {
    $tid = (int) $g['tid'];
    if (!isset($shopByType[$tid])) {
        continue;
    }
    $sz = (string) (($g['size'] ?? '') !== '' ? $g['size'] : 'maat onbekend');
    if (!isset($shopByType[$tid]['size_lines'][$sz])) {
        $shopByType[$tid]['size_lines'][$sz] = ['letters' => [], 'numbers' => []];
    }
    if (typePrints($g['type'], 'print_name_back') && ($g['jersey'] ?? '') !== '') {
        $shopByType[$tid]['numbers'][] = (string) $g['jersey'];
        $shopByType[$tid]['size_lines'][$sz]['numbers'][] = (string) $g['jersey'];
    }
    if (typePrints($g['type'], 'print_initials') && ($g['ini'] ?? '') !== '') {
        $shopByType[$tid]['letters'][] = (string) $g['ini'];
        $shopByType[$tid]['size_lines'][$sz]['letters'][] = (string) $g['ini'];
    }
}
foreach ($shopByType as &$shopRow) {
    uksort($shopRow['sizes'], static fn($a, $b) => $sizeRank((string) $a) <=> $sizeRank((string) $b));
    uksort($shopRow['size_lines'], static fn($a, $b) => $sizeRank((string) $a) <=> $sizeRank((string) $b));
    $nums = array_values(array_unique($shopRow['numbers']));
    usort($nums, static fn($a, $b) => ((int) $a) <=> ((int) $b));
    $shopRow['numbers'] = $nums;
    $letters = $shopRow['letters'] ?? [];
    usort($letters, static fn($a, $b) => strcasecmp($a, $b));
    $shopRow['letters'] = $letters;
    foreach ($shopRow['size_lines'] as &$line) {
        usort($line['letters'], static fn($a, $b) => strcasecmp($a, $b));
        usort($line['numbers'], static fn($a, $b) => ((int) $a) <=> ((int) $b));
    }
    unset($line);
}
unset($shopRow);
uksort($shopByType, static function ($a, $b) use ($types) {
    $rank = static function (int $tid) use ($types): int {
        $g = typeOrderGroup($types[$tid] ?? []);
        return ['match' => 0, 'package' => 1, 'extra' => 2][$g] ?? 3;
    };
    return [$rank((int) $a), (int) $a] <=> [$rank((int) $b), (int) $b];
});

$csvKind = (string) ($_GET['csv'] ?? $_GET['xls'] ?? '');

if ($csvKind === 'bestel' || $csvKind === 'regels') {
    $stamp = new DateTimeImmutable('now', new DateTimeZone('Europe/Amsterdam'));
    sendXlsxDownload(
        'kitroom-14-2-bestelling-' . $stamp->format('d-m-Y-H.i') . '.xlsx',
        orderListRows($shopByType, (int) $orderPieces, $gaps, $stamp)
    );
}

$byLine = [];
$cardPlayers = array_merge($active, $guestPlayers);
foreach ($posOrder as $pos) {
    $byLine[$pos] = array_values(array_filter($cardPlayers, fn($p) => ($p['position'] ?? '') === $pos));
}

$voetLabel = static function (string $v): string {
    $v = strtolower(trim($v));
    return match ($v) {
        'links' => 'links',
        'rechts' => 'rechts',
        'tweebenig' => 'tweebenig',
        default => $v,
    };
};
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kitroom · 14-2</title>
<meta name="theme-color" content="#090A0C">
<script>
(function(){
  var t='dark';
  try { t=localStorage.getItem('kitroom-theme')||'dark'; } catch(e) {}
  if(t!=='light') t='dark';
  document.documentElement.setAttribute('data-theme', t);
  document.documentElement.style.colorScheme=t;
})();
</script>
<link rel="icon" href="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%22478.658%20474.658%201090.5839999999998%201090.5839999999998%22%3E%3Crect%20x%3D%22478.658%22%20y%3D%22474.658%22%20width%3D%221090.5839999999998%22%20height%3D%221090.5839999999998%22%20rx%3D%22239.92847999999995%22%20fill%3D%22%2312151A%22%2F%3E%3Cpath%20transform%3D%22translate(0%2C0)%22%20fill%3D%22%23E11D2E%22%20d%3D%22M%20921.182%20786.705%20C%20921.088%20748.191%20924.254%20721.832%20952.882%20692.548%20C%201015.98%20628.006%201122.48%20672.735%201124.65%20761.167%20C%201125.5%20795.816%201102.43%20832.056%201081.81%20859.102%20C%201173.63%20909.241%201268.41%20963.174%201360.93%201011.13%20L%201361%201131.03%20C%201319.03%201131.1%201275.61%201130.63%201233.75%201131.29%20L%201233.19%201375.08%20L%201049.35%201375.98%20C%201012.25%201376.33%20975.285%201377.55%20938.134%201377.03%20C%20917.37%201362.43%20883.662%201343.49%20865.937%201326.65%20C%20904.772%201326.82%20943.608%201326.66%20982.44%201326.19%20L%201181.84%201325.99%20L%201181.77%201081.01%20C%201223.2%201081.15%201266.55%201081.59%201307.88%201080.71%20L%201307.78%201041.6%20C%201291.08%201031.4%201272.57%201022.84%201255.7%201013.2%20C%201209.38%20986.726%201158.4%20962.557%201112.55%20935.731%20L%201022.94%20987.354%20C%20993.336%20970.865%20960.046%20953.378%20929.916%20937.92%20C%20920.677%20942.578%20898.06%20955.722%20889.447%20958.678%20C%20884.065%20957.576%20873.468%20958.363%20868.546%20958.12%20C%20855.177%20957.462%20795.501%20959.764%20787.18%20956.615%20C%20823.019%20935.682%20860.332%20916.939%20896.692%20896.892%20C%20908.282%20890.501%20919.978%20884.554%20932.037%20879.089%20C%20947.323%20886.595%20963.46%20896.026%20978.428%20904.412%20C%20992.956%20912.551%201009.08%20921.18%201023.02%20930.015%20C%201031.8%20923.173%201052.8%20912.06%201063.31%20905.352%20C%201044.94%20894.018%201024.84%20883.287%201005.8%20873.072%20C%201027.7%20847.761%201051.43%20821.947%201065.94%20791.627%20C%201076.9%20768.732%201076.85%20743.614%201055.63%20726.522%20C%201019.55%20697.466%20974.242%20726.414%20971.812%20768.411%20C%20971.469%20774.337%20971.873%20780.788%20972.045%20786.715%20C%20955.299%20786.729%20937.87%20786.998%20921.182%20786.705%20z%22%2F%3E%3Cpath%20transform%3D%22translate(0%2C0)%22%20fill%3D%22%23E11D2E%22%20d%3D%22M%20687.097%201011.14%20L%20787.18%20956.615%20C%20795.501%20959.764%20855.177%20957.462%20868.546%20958.12%20C%20873.468%20958.363%20884.065%20957.576%20889.447%20958.678%20C%20873.458%20968.871%20851.83%20980.353%20835.021%20989.785%20C%20803.509%201007.51%20771.807%201024.9%20739.921%201041.95%20L%20739.755%201081.04%20L%20865.102%201081.01%20C%20865.082%201106.56%20864.201%201324.24%20865.937%201326.65%20C%20883.662%201343.49%20917.37%201362.43%20938.134%201377.03%20L%20812.355%201377.16%20L%20812.749%201132.92%20L%20687.027%201133.03%20L%20687.097%201011.14%20z%22%2F%3E%3C%2Fsvg%3E">
<meta name="description" content="Kleding- en selectieoverzicht voor 14-2.">
<style>
:root,html[data-theme="dark"]{
  --bg:#090A0C; --surface:#121417; --surface2:#181B20; --raise:#22262C;
  --line:#2C323A; --line2:#3D454E;
  --ink:#F4F1EC; --muted:#9A9388; --dim:#6B655C;
  --accent:#E11D2E; --accent-dim:#C41424; --on-accent:#FFFFFF; --accent-text:#FF6B76;
  --green:#3DCC8A; --greenbg:rgba(61,204,138,.14);
  --miss:#FF6B6B; --missbg:rgba(255,107,107,.14);
  --warn:#E8B84A; --warnbg:rgba(232,184,74,.14);
  --na:#6B655C; --nabg:rgba(255,255,255,.04);
  --glow-a:rgba(225,29,46,.14); --glow-b:rgba(232,184,74,.07);
  --nav-fade:rgba(9,10,12,0);
  --featured:linear-gradient(140deg,#1A1516,#121417 62%);
  --mark-bg:linear-gradient(155deg,#1A1516,#0C0D10);
  --overlay:rgba(8,8,10,.76);
  --hover:rgba(255,255,255,.03);
  --editbar-bg:rgba(225,29,46,.10); --editbar-ink:#F4F1EC;
  --pill-ink:#D6CFC4;
  --modal-shadow:0 24px 60px rgba(0,0,0,.55);
  --r:14px; --r-lg:20px;
}
html[data-theme="light"]{
  --bg:#F4F1EC; --surface:#FFFFFF; --surface2:#F7F4EF; --raise:#EFEBE4;
  --line:#E4DED4; --line2:#D0C8BB;
  --ink:#14110F; --muted:#6A635A; --dim:#8A8378;
  --accent:#E11D2E; --accent-dim:#C41424; --on-accent:#FFFFFF; --accent-text:#B91C1C;
  --green:#0F7A4F; --greenbg:rgba(15,122,79,.12);
  --miss:#C62828; --missbg:rgba(198,40,40,.10);
  --warn:#A67C12; --warnbg:rgba(166,124,18,.12);
  --na:#8A8378; --nabg:rgba(20,17,15,.04);
  --glow-a:rgba(225,29,46,.10); --glow-b:rgba(232,184,74,.08);
  --nav-fade:rgba(244,241,236,0);
  --featured:linear-gradient(140deg,#FFFFFF,#F4F1EC 62%);
  --mark-bg:linear-gradient(155deg,#FFFFFF,#EFEBE4);
  --overlay:rgba(20,17,15,.42);
  --hover:rgba(20,17,15,.03);
  --editbar-bg:rgba(225,29,46,.10); --editbar-ink:#14110F;
  --pill-ink:#3D3832;
  --modal-shadow:0 24px 60px rgba(20,17,15,.14);
}
*{box-sizing:border-box}
html{scroll-behavior:smooth;color-scheme:dark;-webkit-text-size-adjust:100%}
html[data-theme="light"]{color-scheme:light}
body{
  margin:0;color:var(--ink);
  font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
  font-size:15px;line-height:1.45;
  background:var(--bg);
  background-image:
    radial-gradient(900px 420px at 78% -12%,var(--glow-a),transparent 62%),
    radial-gradient(700px 380px at 8% -6%,var(--glow-b),transparent 60%);
  background-attachment:fixed;
  font-variant-numeric:tabular-nums;
}
.wrap{max-width:1080px;margin:auto;padding:16px 16px 80px}
a{color:inherit}
:focus-visible{outline:2px solid var(--accent);outline-offset:2px;border-radius:8px}

/* ---------- header ---------- */
.top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px}
.club{display:flex;gap:11px;align-items:center;min-width:0}
.top-right{display:flex;align-items:center;gap:8px;flex:0 0 auto}
.theme-switch{
  display:flex;border:1px solid var(--line);border-radius:999px;background:var(--surface);overflow:hidden;
}
.theme-switch button{
  border:0;background:transparent;color:var(--muted);padding:7px 11px;
  font-weight:800;font-size:11px;letter-spacing:.1px;cursor:pointer;font-family:inherit;
}
.theme-switch button[aria-pressed="true"]{background:var(--accent);color:var(--on-accent)}
.mark{
  width:44px;height:44px;flex:0 0 44px;border-radius:13px;
  background:var(--mark-bg);
  border:1px solid var(--line2);
  display:grid;place-items:center;padding:8px;
}
.mark svg{width:100%;height:100%;display:block;color:var(--accent)}
.club b{display:block;font-size:19px;font-weight:800;letter-spacing:-.3px;line-height:1.15}
.club small{display:block;color:var(--muted);font-size:11.5px;font-weight:600;letter-spacing:.1px}
.badge{
  background:var(--accent);color:var(--on-accent);
  padding:7px 12px;border-radius:999px;font-size:11px;font-weight:800;
  letter-spacing:.2px;white-space:nowrap;flex:0 0 auto;
}

/* ---------- nav ---------- */
.navwrap{position:sticky;top:0;z-index:20;margin:0 -14px 16px;padding:8px 14px;
  background:linear-gradient(var(--bg) 62%,var(--nav-fade));backdrop-filter:blur(6px)}
.nav{display:flex;gap:7px;overflow-x:auto;scrollbar-width:none;padding-bottom:2px}
.nav::-webkit-scrollbar{display:none}
.nav a,.btn{
  border:1px solid var(--line);background:var(--surface);border-radius:999px;
  padding:9px 14px;font-weight:700;font-size:12.5px;color:var(--ink);
  text-decoration:none;white-space:nowrap;flex:0 0 auto;
  transition:border-color .15s,background .15s,color .15s;
}
.nav a:hover,.btn:hover{border-color:var(--line2);background:var(--surface2)}
.btn.dark,.nav a.dark{background:var(--accent);color:var(--on-accent);border-color:var(--accent);font-weight:800}
.btn.dark:hover,.nav a.dark:hover{background:var(--accent-dim);border-color:var(--accent-dim);color:var(--on-accent)}
button.btn{font-family:inherit;cursor:pointer}

/* ---------- notes ---------- */
.note{
  background:var(--surface);border:1px solid var(--line);border-left:3px solid var(--line2);
  border-radius:var(--r);padding:12px 14px;font-size:13px;color:var(--muted);
  font-weight:500;margin-bottom:16px;
}
.note b{color:var(--ink);font-weight:700}
.editbar{border-left-color:var(--accent);background:var(--editbar-bg);color:var(--editbar-ink)}
.editbar b{color:var(--accent-text)}

/* ---------- stats ---------- */
.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:16px}
body:not(.editing) .stats{grid-template-columns:repeat(3,minmax(0,1fr))}
@media(max-width:720px){.stats,body:not(.editing) .stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
.stat{background:var(--surface);border:1px solid var(--line);border-radius:var(--r);padding:12px 13px;min-width:0}
.stat b{display:block;font-size:clamp(18px,2.4vw,26px);line-height:1.1;font-weight:800;letter-spacing:-.8px;overflow-wrap:anywhere}
.stat span{display:block;margin-top:4px;font-size:11px;color:var(--muted);font-weight:600;letter-spacing:.2px;line-height:1.3}
.stat:hover{border-color:var(--line2)}
.stat.accent b{color:var(--accent-text)}
.stat a{text-decoration:none}
.nav .count{
  display:inline-block;min-width:1.3em;margin-left:5px;padding:1px 6px;border-radius:999px;
  background:var(--accent);color:var(--on-accent);font-size:10px;font-weight:800;line-height:1.4;text-align:center;
}
.nav .count.wait{background:var(--warn);color:#12151A}
.note.alert{
  border-left-color:var(--green);background:var(--greenbg);color:var(--ink);
}
.note.alert b{color:var(--green)}
.note.alert.hidden{display:none}
.note .okbtn{margin-left:8px}
.progress{height:5px;background:var(--raise);border-radius:99px;overflow:hidden;margin-top:9px}
.progress i{display:block;height:100%;background:linear-gradient(90deg,var(--accent-dim),var(--accent));border-radius:99px}

/* ---------- featured ---------- */
.featured{
  background:var(--featured);
  border:1px solid var(--line2);border-radius:var(--r-lg);padding:16px 17px;margin-bottom:16px;
  position:relative;overflow:hidden;
}
.featured::before{content:"";position:absolute;inset:0 auto 0 0;width:3px;background:var(--accent)}
.featured h2{margin:0 0 3px;font-size:20px;font-weight:800;letter-spacing:-.4px}
.featured p{margin:0 0 13px;color:var(--muted);font-size:12.5px;font-weight:500}
.pills{display:flex;flex-wrap:wrap;gap:7px}
.pill{background:var(--raise);border:1px solid var(--line);border-radius:999px;padding:6px 11px;font-size:11.5px;font-weight:700;color:var(--pill-ink)}
.pill.ok{background:var(--greenbg);border-color:rgba(61,220,145,.3);color:var(--green)}
.pill.no{background:var(--missbg);border-color:rgba(255,107,107,.3);color:var(--miss)}

/* ---------- sections ---------- */
.section{background:var(--surface);border:1px solid var(--line);border-radius:var(--r-lg);padding:16px;margin-bottom:14px}
.section h3{margin:0 0 4px;font-size:17px;font-weight:800;letter-spacing:-.3px}
details.fold > summary.fold-head{
  list-style:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:10px;
  padding:2px 0 8px;min-height:44px;
}
details.fold > summary.fold-head::-webkit-details-marker{display:none}
details.fold > summary.fold-head h3{margin:0;flex:1;min-width:0}
.fold-meta{flex:0 0 auto;font-size:12px;font-weight:800;color:var(--muted)}
details.fold > summary.fold-head::after{
  content:'▾';flex:0 0 auto;color:var(--dim);font-size:14px;font-weight:800;line-height:1;
}
details.fold:not([open]) > summary.fold-head{padding-bottom:0}
details.fold:not([open]) > summary.fold-head::after{content:'▸';transform:none}
details.fold:not([open]) > *:not(summary){display:none !important}
details.fold[open] > summary.fold-head{margin-bottom:2px;border-bottom:1px solid var(--line)}
.section .sub{margin:0 0 14px;font-size:12.5px;color:var(--muted);font-weight:500}
.section .sub b{color:var(--ink);font-weight:700}
.legend{display:flex;gap:14px;flex-wrap:wrap;font-size:11px;font-weight:700;color:var(--muted);margin-bottom:12px}
.dot{display:inline-block;width:9px;height:9px;border-radius:3px;margin-right:5px;vertical-align:middle}
.line{margin:18px 0 9px;font-size:11px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:var(--dim)}
.line:first-child{margin-top:0}

/* ---------- cards ---------- */
.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px}
.card{
  border:1px solid var(--line);border-radius:var(--r);padding:14px;
  background:var(--surface2);transition:border-color .15s;
  display:flex;flex-direction:column;min-width:0;
}
.card:hover{border-color:var(--line2)}
.card.moos{border-color:rgba(225,29,46,.45);box-shadow:0 0 0 1px rgba(225,29,46,.12)}
.card.gap{border-left:3px solid rgba(255,107,107,.45)}
.who{display:flex;justify-content:space-between;gap:10px;align-items:center;min-width:0}
.who-left{display:flex;align-items:baseline;gap:8px;min-width:0;overflow:hidden}
.who b{font-size:15.5px;font-weight:700;letter-spacing:-.2px;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ini{flex:0 0 auto;font-size:11px;font-weight:800;letter-spacing:.06em;color:var(--dim);border:1px solid var(--line);border-radius:6px;padding:1px 6px;line-height:1.35}
.nr{font-size:12px;font-weight:800;color:var(--dim);flex:0 0 auto}
.who .jersey-select{
  width:auto;min-width:5.75rem;max-width:7.25rem;flex:0 0 auto;font-weight:700;
  padding:5px 7px;font-size:12px;
}
.meta{font-size:11.5px;color:var(--muted);font-weight:600;margin:5px 0 11px;line-height:1.35}
.meta a{color:var(--accent-text);text-decoration:none;font-weight:800}
.meta a:hover{text-decoration:underline}
.kit{display:grid;gap:6px;flex:1}
.row{display:flex;justify-content:space-between;gap:8px;align-items:center;font-size:12.5px;font-weight:600;padding:8px 10px;border-radius:9px;background:var(--nabg);color:var(--muted);min-width:0}
.row.ok{background:var(--greenbg);color:var(--green)}
.row.no{background:var(--missbg);color:var(--miss)}
.row.wait{background:var(--warnbg);color:var(--warn)}
.row.na{background:var(--nabg);color:var(--na)}
.row.extra{background:var(--nabg);color:var(--muted)}
.row>span:last-child,.row>select{flex:0 0 auto;white-space:nowrap}
.row>span:first-child,.row .want{min-width:0}
.row .want{display:inline-flex;align-items:center;gap:7px;font-weight:700;min-width:0}
.row .want input{margin:0;flex:0 0 auto;accent-color:var(--accent)}
.kit-row .size-select{max-width:96px}
.row .del{
  border:0;background:transparent;color:var(--miss);font:inherit;
  font-size:11px;font-weight:800;cursor:pointer;padding:0 2px;white-space:nowrap;
}
.kit-row.off{opacity:.55}
.shop-card.off{opacity:.48}
.shop-include{
  display:inline-flex;align-items:center;gap:6px;margin:6px 0 0;
  font-size:11.5px;font-weight:800;color:var(--muted);cursor:pointer;
}
.shop-include input{margin:0;accent-color:var(--accent)}
.order-live{
  display:flex;flex-wrap:wrap;gap:12px 16px;align-items:center;justify-content:space-between;
  border:1px solid var(--line);border-radius:var(--r);padding:12px 14px;
  background:var(--surface2);margin:12px 0 4px;
}
.order-live-total{display:flex;flex-wrap:wrap;gap:8px 14px;align-items:baseline}
.order-live-total b{font-size:18px;font-weight:800}
.order-live-total .incl{font-size:13px;font-weight:700;color:var(--muted)}
.order-live .hint{margin:0;font-size:12px}
.card .actions{margin-top:auto;padding-top:4px}
.card .actions .btn{padding:8px 12px;font-size:12px}
.save-state{font-size:11px;font-weight:800;color:var(--muted);min-height:16px}
.save-state.on{color:var(--green)}
.save-state.err{color:var(--miss)}
.add-type{
  display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:12px;align-items:end;
}
.add-type label{display:grid;gap:4px;font-size:11px;font-weight:700;color:var(--dim)}
@media(max-width:760px){.add-type{grid-template-columns:1fr 1fr}}

/* ---------- tables ---------- */
.tablewrap{overflow-x:auto;-webkit-overflow-scrolling:touch;border:1px solid var(--line);border-radius:var(--r);background:var(--surface2)}
table{width:100%;border-collapse:separate;border-spacing:0;font-size:12.5px}
th,td{padding:9px 10px;border-bottom:1px solid var(--line);text-align:center;white-space:nowrap}
tr:last-child td{border-bottom:0}
th{font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--dim);font-weight:800;
   position:sticky;top:0;background:var(--raise);z-index:2}
td.name,th.name{text-align:left;font-weight:700}
th.name{left:0;z-index:3}
td.name{position:sticky;left:0;background:var(--surface2);z-index:1}
td.left{text-align:left;font-weight:500;white-space:normal;color:var(--muted);min-width:180px}
td.ok{background:var(--greenbg);color:var(--green);font-weight:700}
td.no{background:var(--missbg);color:var(--miss);font-weight:700}
td.wait{background:var(--warnbg);color:var(--warn);font-weight:700}
td.na{background:transparent;color:var(--na)}
tbody tr:hover td{background-color:var(--hover)}
tbody tr:hover td.name{background:var(--raise)}
tr.parent-done td.name{box-shadow:inset 3px 0 0 var(--green)}

/* ---------- filters ---------- */
.filters{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:12px}
.filters button,.filters a{
  border:1px solid var(--line);background:var(--surface2);color:var(--ink);
  border-radius:999px;padding:8px 13px;font-weight:700;font-size:12.5px;cursor:pointer;
  font-family:inherit;transition:background .15s,border-color .15s,color .15s;
  text-decoration:none;
}
.filters button:hover,.filters a:hover{border-color:var(--line2)}
.filters button.on,.filters a.on{background:var(--accent);color:var(--on-accent);border-color:var(--accent);font-weight:800}
.hidden{display:none !important}

/* ---------- bars ---------- */
.barwrap{display:grid;gap:7px}
.barrow{display:grid;grid-template-columns:96px 1fr 34px;gap:10px;align-items:center;font-size:12.5px;font-weight:600;color:var(--muted)}
.barrow span:last-child{text-align:right;color:var(--ink);font-weight:700}
.bar{height:9px;background:var(--raise);border-radius:99px;overflow:hidden}
.bar i{display:block;height:100%;background:linear-gradient(90deg,var(--accent-dim),var(--accent));border-radius:99px}

.chip{display:inline-flex;gap:6px;align-items:center;background:var(--raise);border-radius:999px;padding:5px 10px;font-size:11px;font-weight:700;margin:0 6px 6px 0}
.actions{display:flex;gap:7px;flex-wrap:wrap;margin:10px 0 0;align-items:center}
.muted{color:var(--muted);font-size:12px}
.checks{display:flex;flex-wrap:wrap;gap:7px;margin:8px 0 4px}
.checks label{
  display:inline-flex;align-items:center;gap:6px;cursor:pointer;
  border:1px solid var(--line);background:var(--surface2);color:var(--ink);
  border-radius:999px;padding:7px 11px;font-size:12.5px;font-weight:700;
}
.checks label:has(input:checked){background:var(--accent);color:var(--on-accent);border-color:var(--accent)}
.checks input{margin:0}
.note-input{
  width:100%;padding:10px 12px;border-radius:12px;border:1px solid var(--line2);
  background:var(--raise);color:var(--ink);font:inherit;font-size:13.5px;font-weight:500;
  margin:8px 0 12px;resize:vertical;min-height:64px;
}
.parent-defaults{border:1px solid var(--line);border-radius:var(--r);padding:12px;background:var(--surface2);margin-bottom:14px}
.parent-defaults h4{margin:0 0 4px;font-size:13px;font-weight:800}
.parent-defaults .hint{margin:0 0 6px;font-size:12px;color:var(--muted);font-weight:500}
.tiny{font-size:11px;font-weight:700;color:var(--dim)}
.size-select{
  max-width:118px;border:1px solid var(--line2);border-radius:8px;padding:5px 8px;
  font-weight:700;font-size:12.5px;background:var(--raise);color:var(--ink);
  font-family:inherit;cursor:pointer;
}
.size-select:hover{border-color:var(--accent)}
.money{
  width:88px;border:1px solid var(--line2);border-radius:8px;padding:5px 8px;
  font-weight:700;font-size:12.5px;background:var(--raise);color:var(--ink);
  font-family:inherit;text-align:right;
}
.money:hover,.money:focus{border-color:var(--accent)}
.money-wrap{display:flex;flex-direction:column;align-items:flex-end;gap:2px}
.vat-hint{display:block;font-size:10.5px;font-weight:700;color:var(--muted);letter-spacing:.1px;white-space:nowrap}
.shop-cmp{display:block;font-size:12.5px;font-weight:800;line-height:1.25;white-space:nowrap}
.shop-cmp-link{color:inherit;text-decoration:none}
.shop-cmp-link:hover{color:var(--accent)}
td .shop-cmp-link{display:flex;flex-direction:column;align-items:flex-end;gap:2px}
.stat .incl{color:var(--ink);font-weight:800;font-size:13px}
.cat-input{
  width:100%;min-width:88px;border:1px solid var(--line2);border-radius:8px;padding:5px 8px;
  font-weight:700;font-size:12.5px;background:var(--raise);color:var(--ink);font-family:inherit;
}
.print-tags{display:flex;flex-wrap:wrap;gap:4px;margin-top:3px}
.print-tags i{font-style:normal;font-size:10px;font-weight:800;letter-spacing:.3px;color:var(--dim);background:var(--raise);border-radius:999px;padding:2px 7px}
.cat-prints{display:flex;flex-wrap:wrap;gap:5px;margin-top:6px}
.cat-prints label{
  display:inline-flex;align-items:center;gap:5px;cursor:pointer;
  border:1px solid var(--line);background:var(--raise);color:var(--ink);
  border-radius:999px;padding:4px 9px;font-size:11px;font-weight:700;
}
.cat-prints label:has(input:checked){background:var(--accent);color:var(--on-accent);border-color:var(--accent)}
.cat-prints input{margin:0}
.cat-name{display:grid;gap:5px;min-width:160px}
.cat-name .cat-input{width:100%;min-width:140px}
.cat-del{border:0;background:transparent;color:var(--miss);font:inherit;font-size:11px;font-weight:800;cursor:pointer;padding:4px 2px;white-space:nowrap}
.cat-used{display:block;font-size:11px;font-weight:700;color:var(--muted);white-space:nowrap}
.cms-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(132px,1fr));gap:8px;align-items:end;margin:8px 0 14px}
.cms-grid .btn{justify-self:start}
.cms-grid label{display:grid;gap:4px;font-size:11px;font-weight:700;color:var(--dim)}
.cms-actions{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}
#beheer,[id^="cms-p-"],[id^="cms-s-"],[id^="card-p-"],[id^="card-s-"]{scroll-margin-top:72px}
tr.archived td{opacity:.55}
.assign{
  display:grid;grid-template-columns:minmax(140px,1.4fr) minmax(110px,1fr) 110px auto auto auto;
  gap:8px;align-items:center;
}
.assign select,.addrow select{
  width:100%;border:1px solid var(--line2);border-radius:8px;padding:8px 10px;
  font-weight:700;font-size:12.5px;background:var(--raise);color:var(--ink);
  font-family:inherit;cursor:pointer;
}
.assign select:hover,.addrow select:hover{border-color:var(--accent)}
.addrow{display:grid;grid-template-columns:1fr 86px auto;gap:6px;margin-top:8px;align-items:center}
@media(max-width:760px){
  .assign{grid-template-columns:1fr}
  .addrow{grid-template-columns:1fr 1fr}
  .addrow .btn{grid-column:1 / -1}
}

/* ---------- modal / toast ---------- */
.modal{position:fixed;inset:0;background:var(--overlay);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;z-index:40;padding:16px}
.modal.hidden{display:none}
.modalbox{background:var(--surface);border:1px solid var(--line2);border-radius:var(--r-lg);padding:20px;width:min(370px,100%);box-shadow:var(--modal-shadow)}
.modalbox h3{margin:0 0 5px;font-size:18px;font-weight:800}
.modalbox p{margin:0 0 12px;color:var(--muted);font-size:13px;font-weight:500}
.modalbox input{
  width:100%;padding:13px;border-radius:12px;border:1px solid var(--line2);
  background:var(--raise);color:var(--ink);font-size:21px;letter-spacing:6px;
  text-align:center;margin-bottom:10px;font-family:inherit;
}
.modalbox input:focus{border-color:var(--accent);outline:none}
.modalbox .err{color:var(--miss);min-height:18px;font-size:12.5px;font-weight:600;margin:0 0 8px}
.toast{
  position:fixed;bottom:18px;left:50%;transform:translateX(-50%);
  background:var(--accent);color:var(--on-accent);padding:11px 18px;border-radius:999px;
  font-weight:800;font-size:13px;z-index:50;opacity:0;pointer-events:none;transition:opacity .2s;
  box-shadow:0 8px 26px rgba(0,0,0,.4);
}
.toast.show{opacity:1}
.packshot{
  width:100%;max-width:420px;margin:8px auto 0;display:block;
  aspect-ratio:1 / 1;object-fit:contain;object-position:center;
  border:0;border-radius:0;background:transparent;padding:0;
}
.kit-design{
  display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:0 0 16px;align-items:start;
}
.kit-design figure{
  margin:0;background:#fff;border:1px solid var(--line);border-radius:var(--r-lg);
  padding:8px;min-width:0;
}
.kit-design img{
  width:100%;height:auto;max-height:640px;display:block;margin:0 auto;
  object-fit:contain;background:#fff;
}
@media(max-width:720px){
  .kit-design{grid-template-columns:1fr}
  .kit-design img{max-height:none}
}
.packfold{margin:0 0 14px;border:0;padding:0;background:transparent}
.packfold > summary{
  cursor:pointer;font-weight:800;font-size:13px;color:var(--muted);list-style:none;
  padding:8px 0;min-height:44px;display:flex;align-items:center;
}
.packfold > summary::-webkit-details-marker{display:none}
.packfold[open] > summary{color:var(--ink)}
.brandbits{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:0 0 14px}
@media(max-width:760px){.brandbits{grid-template-columns:repeat(2,1fr)}}
.shop-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:14px 0 18px}
@media(max-width:720px){.shop-grid{grid-template-columns:1fr}}
.shop-card{
  border:1px solid var(--line);border-radius:16px;background:var(--surface2);
  padding:14px 16px;box-shadow:0 1px 0 rgba(255,255,255,.04) inset,0 8px 22px -12px rgba(0,0,0,.35);
  min-width:0;
}
.shop-card-top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:10px}
.shop-card h4{margin:0;font-size:16px;font-weight:800;letter-spacing:-.02em}
.shop-card .art{
  margin:4px 0 0;font-size:13px;font-weight:800;color:var(--accent-text);letter-spacing:.2px;
  font-variant-numeric:tabular-nums;
}
.shop-card .meta{margin:3px 0 0;font-size:12px;color:var(--muted);font-weight:600}
.shop-card .total{font-size:22px;font-weight:800;color:var(--accent-text);line-height:1;text-align:right;flex:0 0 auto}
.shop-card .total span{display:block;font-size:11px;font-weight:700;color:var(--muted);margin-top:2px}
.shop-card .total .shop-eur{color:var(--ink);font-size:13px;font-weight:800;margin-top:4px}
.shop-card .total .shop-eur.vat-hint{color:var(--muted);font-size:11px;font-weight:700;margin-top:2px}
.size-grid{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 10px}
.size-pill{
  display:inline-flex;align-items:baseline;gap:8px;min-width:84px;
  padding:9px 11px;border-radius:12px;background:var(--raise);border:1px solid var(--line);
}
.size-pill .sz{font-size:13px;font-weight:800;color:var(--ink)}
.size-pill .n{font-size:18px;font-weight:800;color:var(--accent-text);line-height:1}
.size-pill .n small{font-size:11px;font-weight:700;color:var(--muted);margin-left:2px}
.print-row{display:flex;flex-wrap:wrap;gap:6px}
.print-row i{
  font-style:normal;font-size:11.5px;font-weight:700;padding:5px 9px;border-radius:999px;
  background:var(--raise);border:1px solid var(--line);color:var(--muted);
}
.print-row i b{color:var(--ink);font-weight:800}
.shop-nums{margin-top:8px;font-size:12px;color:var(--muted);font-weight:600;line-height:1.45}
.shop-nums b{color:var(--ink)}
.shop-prints{
  border:1px solid var(--line);border-radius:16px;background:var(--surface2);
  padding:14px 16px;margin:0 0 16px;
}
.shop-prints h4{margin:0 0 10px;font-size:14px;font-weight:800}
.shop-prints-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px}
.shop-prints-grid .stat{padding:11px 12px;background:var(--raise)}
.shop-prints-grid .stat b{font-size:clamp(18px,2.2vw,24px)}
@media(max-width:760px){.shop-prints-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
.shop-rule{margin:0 0 12px;font-size:12.5px;color:var(--muted);line-height:1.5}
.shop-rule b{color:var(--ink)}
details.shop-more{margin-top:16px;border:1px solid var(--line);border-radius:14px;padding:10px 14px;background:var(--surface2)}
details.shop-more > summary{cursor:pointer;font-weight:800;font-size:13px;color:var(--ink);list-style:none;min-height:44px;display:flex;align-items:center}
details.shop-more > summary::-webkit-details-marker{display:none}
details.shop-more[open] > summary{margin-bottom:10px;color:var(--accent-text)}
@media print{
  details.shop-more,#printPrices{display:none !important}
  details.fold{display:block}
  details.fold > summary.fold-head{display:flex;border:0;padding:0 0 8px}
  details.fold > summary.fold-head::after{display:none}
  .shop-card{break-inside:avoid;box-shadow:none}
}
.place{font-size:11px;color:var(--dim);font-weight:600;margin:2px 0 0}

@media(max-width:720px){
  .note,.shop-rule,.section .sub{font-size:14px;line-height:1.5}
  .nav a,.btn,button.btn,.filters button{
    min-height:42px;display:inline-flex;align-items:center;justify-content:center;
  }
  .checks label{min-height:40px}
  .shop-include{min-height:40px}
  .row .want{white-space:normal;line-height:1.35;overflow-wrap:anywhere}
  .shop-card h4{overflow-wrap:anywhere}
  .who b{white-space:normal}
  .tablewrap{margin-inline:-4px}
  .actions .btn{flex:1 1 calc(50% - 7px);text-align:center}
  .card .actions .btn{flex:1 1 auto}
}
@media(max-width:560px){
  .wrap{padding:14px 12px 64px}
  .navwrap{margin-left:-12px;margin-right:-12px}
  .cards{grid-template-columns:1fr}
  .barrow{grid-template-columns:80px 1fr 30px}
  .top{flex-wrap:wrap}
  .club b{font-size:17px}
  .kit-row{
    display:grid;grid-template-columns:1fr auto;grid-template-areas:"want size" "want del";
    gap:6px 8px;align-items:center;
  }
  .kit-row .want{grid-area:want;font-size:13.5px}
  .kit-row .size-select{grid-area:size;max-width:120px;min-height:42px;font-size:16px}
  .kit-row .del{grid-area:del;justify-self:end;min-height:36px}
  .who{flex-wrap:wrap}
  .who .jersey-select{max-width:none;width:100%;min-height:42px}
  .packshot{max-width:100%;padding:8px}
  .section{padding:14px 12px}
  .fold-meta{font-size:11px}
}

/* ---------- print: terug naar licht ---------- */
@media print{
  :root{--bg:#fff;--surface:#fff;--surface2:#fff;--raise:#f4f4f5;--line:#d4d4d8;--line2:#a1a1aa;
        --ink:#111;--muted:#52525b;--dim:#71717a;--accent:#111;--on-accent:#fff;
        --green:#15803d;--greenbg:#dcfce7;--miss:#b91c1c;--missbg:#fee2e2;
        --warn:#a16207;--warnbg:#fef3c7;--na:#71717a;--nabg:#fafafa}
  html{color-scheme:light}
  body{background:#fff;color:#111}
  .navwrap,.filters,.actions,.note,.toast,.modal,.theme-switch,#parentAlert,.assign,.addrow,.money,.cat-input,#printPrices,.parent-defaults,.packfold > summary,#beheer{display:none !important}
  .packfold{display:block}
  .packshot{max-width:360px}
  .kit-design{grid-template-columns:1fr 1fr;break-inside:avoid}
  .kit-design img{max-height:280px}
  .kit-design figure{border:1px solid #d4d4d8;background:#fff}
  .featured,.card{break-inside:avoid;border:1px solid #d4d4d8}
  .section{border:1px solid #d4d4d8}
  #bestel{break-inside:auto}
  .featured{background:#fff;color:#111}
  .featured p,.pill{color:#333}
  .wrap{max-width:none;padding:0}
  th{background:#f4f4f5}
  td.name{background:#fff}
  .mark{background:#fff;border-color:#d4d4d8}
  .mark svg path{fill:#111 !important}
  .badge{background:#111;color:#fff}
}
</style>
</head>
<body class="<?= $canEdit ? 'editing' : '' ?>">
<div class="wrap">
  <header class="top">
    <div class="club">
      <div class="mark" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="623.0 619.0 801.9 801.9" ><path transform="translate(0,0)" fill="currentColor" d="M 921.182 786.705 C 921.088 748.191 924.254 721.832 952.882 692.548 C 1015.98 628.006 1122.48 672.735 1124.65 761.167 C 1125.5 795.816 1102.43 832.056 1081.81 859.102 C 1173.63 909.241 1268.41 963.174 1360.93 1011.13 L 1361 1131.03 C 1319.03 1131.1 1275.61 1130.63 1233.75 1131.29 L 1233.19 1375.08 L 1049.35 1375.98 C 1012.25 1376.33 975.285 1377.55 938.134 1377.03 C 917.37 1362.43 883.662 1343.49 865.937 1326.65 C 904.772 1326.82 943.608 1326.66 982.44 1326.19 L 1181.84 1325.99 L 1181.77 1081.01 C 1223.2 1081.15 1266.55 1081.59 1307.88 1080.71 L 1307.78 1041.6 C 1291.08 1031.4 1272.57 1022.84 1255.7 1013.2 C 1209.38 986.726 1158.4 962.557 1112.55 935.731 L 1022.94 987.354 C 993.336 970.865 960.046 953.378 929.916 937.92 C 920.677 942.578 898.06 955.722 889.447 958.678 C 884.065 957.576 873.468 958.363 868.546 958.12 C 855.177 957.462 795.501 959.764 787.18 956.615 C 823.019 935.682 860.332 916.939 896.692 896.892 C 908.282 890.501 919.978 884.554 932.037 879.089 C 947.323 886.595 963.46 896.026 978.428 904.412 C 992.956 912.551 1009.08 921.18 1023.02 930.015 C 1031.8 923.173 1052.8 912.06 1063.31 905.352 C 1044.94 894.018 1024.84 883.287 1005.8 873.072 C 1027.7 847.761 1051.43 821.947 1065.94 791.627 C 1076.9 768.732 1076.85 743.614 1055.63 726.522 C 1019.55 697.466 974.242 726.414 971.812 768.411 C 971.469 774.337 971.873 780.788 972.045 786.715 C 955.299 786.729 937.87 786.998 921.182 786.705 z"/><path transform="translate(0,0)" fill="currentColor" d="M 687.097 1011.14 L 787.18 956.615 C 795.501 959.764 855.177 957.462 868.546 958.12 C 873.468 958.363 884.065 957.576 889.447 958.678 C 873.458 968.871 851.83 980.353 835.021 989.785 C 803.509 1007.51 771.807 1024.9 739.921 1041.95 L 739.755 1081.04 L 865.102 1081.01 C 865.082 1106.56 864.201 1324.24 865.937 1326.65 C 883.662 1343.49 917.37 1362.43 938.134 1377.03 L 812.355 1377.16 L 812.749 1132.92 L 687.027 1133.03 L 687.097 1011.14 z"/></svg></div>
      <div><b>Kitroom</b><small>14-2 · kleding &amp; selectie</small></div>
    </div>
    <div class="top-right">
      <div class="theme-switch" role="group" aria-label="Thema">
        <button type="button" data-theme-set="dark" aria-pressed="true">Donker</button>
        <button type="button" data-theme-set="light" aria-pressed="false">Licht</button>
      </div>
      <div class="badge"><?= h($season) ?></div>
    </div>
  </header>

  <div class="navwrap">
  <nav class="nav">
    <a href="#design">Design</a>
    <a href="#spelers">Spelers</a>
    <?php if ($canEdit): ?>
    <a href="#ouders">Ouders<?php if ($parentFilled): ?> <span class="count" id="ouderNavCount"><?= count($parentFilled) ?></span><?php endif; ?></a>
    <?php endif; ?>
    <a href="#bestel">Bestelling</a>
    <a href="#staf">Staf</a>
    <a href="#catalogus">Catalogus</a>
    <?php if ($canEdit): ?>
    <a href="#beheer">Beheer</a>
    <?php endif; ?>
    <?php if ($canEdit): ?>
      <button type="button" class="btn" id="saveAllBtn">Alles opslaan</button>
      <button type="button" class="btn" id="logoutBtn">Klaar</button>
    <?php else: ?>
      <button type="button" class="btn dark" id="editBtn">Beheer</button>
    <?php endif; ?>
  </nav>
  </div>

  <div class="note alert hidden" id="parentAlert">
    <b>Nieuw ingevuld:</b> <span id="parentAlertNames"></span>
    <a href="#ouders">Bekijken</a>
    <button type="button" class="btn okbtn" id="parentAlertOk">Gezien</button>
  </div>

  <div class="stats">
    <a class="stat" href="#spelers"><b><?= count($active) ?></b><span>spelers</span></a>
    <a class="stat accent" href="#bestel"><b id="statPieces"><?= (int) $orderPieces ?></b><span>stuks te bestellen</span></a>
    <?php if ($canEdit): ?>
    <div class="stat"><b id="statTotal"><?= euro($orderTotal) ?></b><span class="incl" id="statTotalIncl"><?= euroIncl($orderTotal) ?></span><span>richtprijs excl. btw<?= $printCost > 0 ? ' · kleding + print' : '' ?></span></div>
    <?php endif; ?>
    <?php if ($canEdit): ?>
    <a class="stat accent" href="#ouders">
      <b><?= count($parentFilled) ?>/<?= count($active) ?></b><span>ouders ingevuld</span>
      <div class="progress"><i style="width:<?= count($active) ? round(100 * count($parentFilled) / count($active)) : 0 ?>%"></i></div>
    </a>
    <?php else: ?>
    <div class="stat">
      <b><?= count($parentFilled) ?>/<?= count($active) ?></b><span>ouders ingevuld</span>
      <div class="progress"><i style="width:<?= count($active) ? round(100 * count($parentFilled) / count($active)) : 0 ?>%"></i></div>
    </div>
    <?php endif; ?>
  </div>

  <section class="kit-design" id="design">
    <figure>
      <img src="<?= assetUrl('speler-kit.png') ?>" width="1023" height="1024" alt="Spelerstenue 14-2: shirt, broekje, jassen, tas en sokken">
    </figure>
    <figure>
      <img src="<?= assetUrl('kader-kit.png') ?>" width="528" height="1024" alt="Kadertenue 14-2: polo, shirt, jas en broekje">
    </figure>
  </section>

  <details class="section fold" id="spelers">
    <summary class="fold-head"><h3>Spelers</h3><span class="fold-meta"><?= count($active) ?></span></summary>
    <p class="sub"><?= $canEdit ? 'Maat of vinkje wordt meteen opgeslagen. Uitvinken houdt het item op de kaart, buiten prijs en Excel. Weghalen alleen via <b>Verwijderen</b>. <b>Pakket</b> zet de set in één keer.' : 'Overzicht van maten en rugnummers.' ?></p>
    <div class="filters" id="playerFilters">
      <button class="on" data-f="all">Iedereen</button>
      <?php if ($guestPlayers): ?>
      <button data-f="guest">Gasten</button>
      <?php endif; ?>
      <button data-f="goalkeeper">Keepers</button>
      <button data-f="defender">Verdediging</button>
      <button data-f="midfielder">Middenveld</button>
      <button data-f="attacker">Aanval</button>
    </div>
    <div class="legend">
      <span><i class="dot" style="background:var(--green)"></i>In bezit</span>
      <span><i class="dot" style="background:var(--warn)"></i>Te bestellen</span>
      <span><i class="dot" style="background:var(--na)"></i>Niet toegewezen</span>
    </div>
    <div id="playerCards">
      <?php foreach ($byLine as $pos => $group): if (!$group) continue; ?>
        <div class="line"><?= h($posLabel[$pos] ?? $pos) ?> · <?= count($group) ?></div>
        <div class="cards">
        <?php foreach ($group as $p):
          $isMoos = strcasecmp((string) $p['first_name'], 'Moos') === 0;
          $meta = [];
          if ($p['scout_pos'] || $p['scout_type']) {
              $meta[] = trim($p['scout_pos'].' '.$p['scout_type']);
          } else {
              $meta[] = $posLabel[$p['position']] ?? 'Speler';
          }
          if ($p['voet']) {
              $meta[] = $voetLabel((string) $p['voet']);
          }
          $meta[] = $p['to_order'] ? count($p['to_order']).' te bestellen' : ($p['owned'] ? 'niets te bestellen' : 'nog niets toegewezen');
          if (!empty($p['parent_saved_at'])) {
              $meta[] = 'ouder ingevuld';
          }
        ?>
        <article class="card<?= $isMoos ? ' moos' : '' ?><?= $p['to_order'] ? ' gap' : '' ?>"
                 id="card-p-<?= (int) $p['id'] ?>"
                 data-pos="<?= h($p['position'] ?? '') ?>"
                 data-guest="<?= !empty($p['is_guest']) ? '1' : '0' ?>">
          <div class="who">
            <span class="who-left">
              <b><?= h(fullName($p)) ?></b>
              <?php $ini = playerInitials($p); if ($ini !== ''): ?><span class="ini"><?= h($ini) ?></span><?php endif; ?>
            </span>
            <?php if ($canEdit): ?>
            <?= jerseySelectHtml($mysqli, $p, ['class' => 'size-select jersey-select', 'allow_empty' => true]) ?>
            <?php else: ?>
            <span class="nr"><?= $p['jersey_number'] ? '#'.h($p['jersey_number']) : '' ?></span>
            <?php endif; ?>
          </div>
          <div class="meta"><?= h(implode(' · ', array_filter($meta))) ?><?php if ($canEdit): ?> · <a href="#cms-p-<?= (int) $p['id'] ?>">Wijzigen</a><?php endif; ?></div>
          <div class="kit">
            <?php
              $cardTypes = $p['card_types'] ?? [];
            ?>
            <?php foreach ($cardTypes as $tid):
              if (!isset($types[$tid])) continue;
              $t = $types[$tid];
              $it = itemFor($p, $tid);
              if (!$it) continue;
              $pending = isPendingItem($it);
              $held = isHeldItem($it);
              $owned = isIssued($it);
              $cls = $owned ? 'ok' : ($pending ? 'wait' : 'extra');
              $val = $it ? (string) $it['size'] : '—';
              $tag = $pending ? ' · bestellen' : ($owned ? ' · in bezit' : ($held ? ' · niet in bestelling' : ''));
              $unit = ($canEdit && ($pending || $held)) ? priceFor($t, (string) ($it['size'] ?? '')) : null;
            ?>
            <div class="row <?= $cls ?> kit-row<?= $pending ? '' : ' off' ?>" data-who="player" data-id="<?= (int) $p['id'] ?>" data-tid="<?= $tid ?>" data-status="<?= ($pending || $held) ? 'pending' : ($owned ? 'owned' : '') ?>">
              <label class="want">
                <?php if ($canEdit): ?>
                <input type="checkbox" class="want-check"<?= $pending ? ' checked' : '' ?><?= $owned ? ' disabled title="In bezit"' : '' ?>>
                <?php endif; ?>
                <?= h(shortTypeName($tid, $types)) ?><span class="kit-tag"><?= $tag ?></span><?php if ($unit !== null): ?><span class="kit-price"> · <?= euroPair($unit) ?></span><?php endif; ?>
              </label>
              <?php if ($canEdit): ?>
                <?= sizeSelect($tid, (string) ($it['size'] ?? ''), 'player', (int) $p['id']) ?>
                <?php if ($it): ?>
                <button type="button" class="del item-del" data-who="player" data-id="<?= (int) $p['id'] ?>" data-tid="<?= $tid ?>">Verwijderen</button>
                <?php endif; ?>
              <?php else: ?>
                <span><?= h($val) ?></span>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if ($canEdit): ?>
              <div class="addrow" data-who="player" data-id="<?= (int) $p['id'] ?>">
                <select class="assign-type"><?= typeOptionsHtml($types, 'Item toevoegen…') ?></select>
                <select class="assign-size" disabled><option value="">Maat</option></select>
                <button type="button" class="btn dark assign-add">Toevoegen</button>
              </div>
              <div class="actions">
                <span class="save-state" data-who="player" data-id="<?= (int) $p['id'] ?>"></span>
                <button type="button" class="btn save-one" data-who="player" data-id="<?= (int) $p['id'] ?>" data-mode="active">In bezit</button>
                <button type="button" class="btn assign-package" data-who="player" data-id="<?= (int) $p['id'] ?>">Pakket</button>
                <?php if (isset($parentLinks[(int) $p['id']])): $pl = $parentLinks[(int) $p['id']]; ?>
                <button type="button" class="btn parent-copy" data-url="<?= h($pl['url']) ?>">Link ouders</button>
                <a class="btn" href="<?= h($pl['wa']) ?>" target="_blank" rel="noopener">WhatsApp</a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </article>
        <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </details>

  <?php if ($canEdit):
    $parentForm = loadParentFormSettings();
  ?>
  <details class="section fold" id="ouders">
    <summary class="fold-head"><h3>Ouderlinks</h3><span class="fold-meta"><?= count($parentFilled) ?>/<?= count($active) ?></span></summary>
    <p class="sub">Kopieer de link of stuur hem via WhatsApp. Een nieuwe link maakt de oude ongeldig.</p>
    <details class="shop-more" id="parentDefaultsWrap">
      <summary>Wat ouders invullen</summary>
    <div class="parent-defaults" id="parentDefaults" style="margin:0;border:0;padding:4px 0 0;background:transparent">
      <p class="hint">Standaard voor veldspelers, keepers en staf. Elk catalogusartikel staat hier; een nieuw item komt automatisch bij. Per speler kun je hieronder afwijken. Ouders moeten elk aangevinkt item invullen: maat of n.v.t.</p>
      <div class="line">Veldspelers</div>
      <?= parentChecksHtml('field', parentTypeChoices('field'), $parentForm['field']) ?>
      <div class="line">Keepers</div>
      <?= parentChecksHtml('keeper', parentTypeChoices('keeper'), $parentForm['keeper']) ?>
      <div class="line">Staf</div>
      <?= parentChecksHtml('staff', parentTypeChoices('staff'), $parentForm['staff']) ?>
      <label class="hint" for="parentNote">Tekst bovenaan het ouderformulier (optioneel)</label>
      <textarea class="note-input" id="parentNote" maxlength="280" placeholder="Bijvoorbeeld: alleen de nieuwe set voor 26/27, geen polo."><?= h($parentForm['note']) ?></textarea>
    </div>
    </details>
    <div class="tablewrap">
      <table>
        <thead>
          <tr>
            <th class="name">Speler</th>
            <th>Status</th>
            <th class="name">Ziet</th>
            <th>Link</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($parentLinks as $pid => $pl):
              $kind = (($pl['position'] ?? '') === 'goalkeeper') ? 'keeper' : 'field';
              $choices = parentTypeChoices($kind);
              $defaults = parentDefaultTypeIds($pl['player']);
              $savedAt = $pl['saved'] ? date('d-m H:i', strtotime((string) $pl['saved'])) : '';
          ?>
          <tr<?= $savedAt !== '' ? ' class="parent-done"' : '' ?>>
            <td class="name"><?= h($pl['name']) ?><?php if ($pl['custom']): ?><div class="tiny">aangepast</div><?php endif; ?></td>
            <td class="<?= $savedAt !== '' ? 'ok' : 'no' ?>"><?= $savedAt !== '' ? 'ingevuld '.$savedAt : 'nog niet' ?></td>
            <td class="left">
              <?= parentChecksHtml('player', $choices, $pl['types'], (int) $pid, $defaults) ?>
              <?php if ($pl['custom']): ?>
                <button type="button" class="btn parent-reset" data-id="<?= (int) $pid ?>">Standaard</button>
              <?php endif; ?>
            </td>
            <td class="left">
              <div class="actions" style="margin:0">
                <button type="button" class="btn dark parent-copy" data-url="<?= h($pl['url']) ?>">Kopiëren</button>
                <a class="btn" href="<?= h($pl['wa']) ?>" target="_blank" rel="noopener">WhatsApp</a>
                <a class="btn" href="<?= h($pl['url']) ?>" target="_blank" rel="noopener">Bekijk</a>
                <button type="button" class="btn parent-rotate" data-id="<?= (int) $pid ?>">Nieuwe link</button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <details class="shop-more">
    <summary>Staflinks</summary>
    <h3 style="margin-top:8px">Staflinks</h3>
    <p class="sub">Zelfde soort link, zonder rugnummer. Standaard polo en zip; shirt, broekje, jassen en tas kun je extra aanvinken.</p>
    <div class="tablewrap">
      <table>
        <thead>
          <tr>
            <th class="name">Staf</th>
            <th>Status</th>
            <th class="name">Ziet</th>
            <th>Link</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($staffLinks as $sid => $sl):
              $savedAt = $sl['saved'] ? date('d-m H:i', strtotime((string) $sl['saved'])) : '';
          ?>
          <tr<?= $savedAt !== '' ? ' class="parent-done"' : '' ?>>
            <td class="name"><?= h($sl['name']) ?><div class="tiny"><?= h($sl['role']) ?><?= $sl['custom'] ? ' · aangepast' : '' ?></div></td>
            <td class="<?= $savedAt !== '' ? 'ok' : 'no' ?>"><?= $savedAt !== '' ? 'ingevuld '.$savedAt : 'nog niet' ?></td>
            <td class="left">
              <?= parentChecksHtml('staff-one', parentTypeChoices('staff'), $sl['types'], (int) $sid, $parentForm['staff']) ?>
              <?php if ($sl['custom']): ?>
                <button type="button" class="btn parent-reset" data-id="<?= (int) $sid ?>" data-who="staff">Standaard</button>
              <?php endif; ?>
            </td>
            <td class="left">
              <div class="actions" style="margin:0">
                <button type="button" class="btn dark parent-copy" data-url="<?= h($sl['url']) ?>">Kopiëren</button>
                <a class="btn" href="<?= h($sl['wa']) ?>" target="_blank" rel="noopener">WhatsApp</a>
                <a class="btn" href="<?= h($sl['url']) ?>" target="_blank" rel="noopener">Bekijk</a>
                <button type="button" class="btn parent-rotate" data-id="<?= (int) $sid ?>" data-who="staff">Nieuwe link</button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    </details>
  </details>
  <?php endif; ?>

  <details class="section fold" id="bestel">
    <summary class="fold-head"><h3>Bestelling</h3><span class="fold-meta" id="bestelMeta"><?= (int) $orderPieces ?> stuks</span></summary>
    <p class="sub" id="bestelSub"><?= (int) $orderPieces ?> stuks<?php if ($canEdit && $orderTotal > 0): ?> · <?= euro($orderTotal) ?> excl. · <?= euroIncl($orderTotal) ?><?php endif; ?> · artikelnummers, maten en print.<?= $canEdit ? ' Uitvinken haalt het item uit prijs en Excel, niet van de speler.' : '' ?></p>
    <p class="shop-rule"><b>Rohda-logo:</b> jassen, shirt, keeperstenue, tas · <b>Sponsor shirts:</b> voor- en achterkant op shirt/keeperstenue (niet de jassen) · <b>Sponsor padded:</b> gezamenlijk blok op de winterjas · <b>Sponsor field jack:</b> achterkant regenjas · <b>Sponsor tas:</b> 1 kleur · <b>Initialen:</b> jassen, shirt, broekje, keeperstenue, tas · <b>Nummer:</b> shirt</p>

    <div class="actions">
      <a class="btn dark" href="?csv=bestel">Excel-bestellijst</a>
      <a class="btn" href="javascript:window.print()">Print</a>
      <?php if ($canEdit): ?>
      <button type="button" class="btn" id="assignPackageAll">Pakket aan alle spelers</button>
      <?php endif; ?>
    </div>

    <?php if ($canEdit): ?>
    <div class="order-live" id="orderLive">
      <label class="shop-include"><input type="checkbox" id="orderSelectAll" checked> Alles meetellen</label>
      <div class="order-live-total">
        <span><b id="livePieces"><?= (int) $orderPieces ?></b> stuks</span>
        <span><b id="liveExcl"><?= euro($orderTotal) ?></b> excl.</span>
        <span class="incl" id="liveIncl"><?= euroIncl($orderTotal) ?></span>
      </div>
      <p class="hint">Uitvinken haalt het item uit de richtprijs en de Excel-lijst. Het blijft bij de speler staan. Weghalen alleen via Verwijderen.</p>
    </div>
    <?php endif; ?>

    <?php if (!$shopByType): ?>
      <p class="sub">Nog niets te bestellen. Zet per speler producten op bestellen met maat.</p>
    <?php else: ?>

    <div class="shop-prints">
      <h4>Totaal bedrukken</h4>
      <div class="shop-prints-grid">
        <?php foreach ($printRows as $key => $row): ?>
        <div class="stat<?= ($row['unit'] !== null && $row['count'] > 0) ? ' accent' : '' ?>" data-print-key="<?= h((string) $key) ?>" data-print-unit="<?= $row['unit'] !== null ? h((string) $row['unit']) : '' ?>">
          <b class="print-count"><?= (int) $row['count'] ?></b>
          <span><?= h($row['label']) ?><?php if ($canEdit && $row['unit'] !== null): ?> · <?= euro($row['unit']) ?> / <?= euro(withVat($row['unit'])) ?> incl. p.st.<?php endif; ?></span>
          <?php if ($canEdit): ?>
          <span class="print-sum"><?php if ($row['sum'] !== null): ?><?= euro($row['sum']) ?><?php endif; ?></span>
          <span class="vat-hint print-sum-incl"><?php if ($row['sum'] !== null): ?><?= euroIncl($row['sum']) ?><?php elseif ($row['count'] > 0 && $row['unit'] === null): ?>geen prijs in catalogus<?php endif; ?></span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="shop-grid">
      <?php foreach ($shopByType as $shop): ?>
      <div class="shop-card" data-tid="<?= (int) $shop['tid'] ?>">
        <div class="shop-card-top">
          <div>
            <h4><?= h($shop['label']) ?></h4>
            <?php if ($canEdit): ?>
            <label class="shop-include"><input type="checkbox" class="shop-include-check" checked> Meetellen</label>
            <?php endif; ?>
            <?php if ($shop['article'] !== ''): ?>
            <div class="art">Art. <?= h($shop['article']) ?></div>
            <?php else: ?>
            <div class="art" style="color:var(--miss)">Art. ontbreekt</div>
            <?php endif; ?>
            <div class="meta">
              <?php
                $bits = array_filter([
                  ($shop['brand'] ?? '') !== '' ? $shop['brand'] : 'Stanno',
                  $shop['color'] !== '' ? $shop['color'] : '',
                ]);
                echo h(implode(' · ', $bits));
              ?>
            </div>
          </div>
          <div class="total"><span class="shop-count"><?= (int) $shop['count'] ?></span><span>stuks</span><?php if ($canEdit && ($shop['cost'] ?? 0) > 0): ?><span class="shop-eur"><?= euro((float) $shop['cost']) ?></span><span class="shop-eur vat-hint"><?= euroIncl((float) $shop['cost']) ?></span><?php elseif ($canEdit): ?><span class="shop-eur"></span><span class="shop-eur vat-hint"></span><?php endif; ?></div>
        </div>
        <div class="size-grid">
          <?php foreach ($shop['sizes'] as $sz => $cnt): ?>
          <div class="size-pill">
            <span class="sz"><?= h((string) $sz) ?></span>
            <span class="n"><?= (int) $cnt ?><small>×</small></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if ($shop['rohda'] || $shop['initials'] || $shop['sponsor'] || $shop['sponsor_back'] || !empty($shop['sponsor_padded']) || !empty($shop['sponsor_jacket']) || !empty($shop['sponsor_bag']) || $shop['name_back'] || !empty($shop['staff_text'])): ?>
        <div class="print-row">
          <?php if ($shop['rohda']): ?><i>Rohda logo <b><?= (int) $shop['rohda'] ?></b></i><?php endif; ?>
          <?php if ($shop['initials']): ?><i>Initialen <b><?= (int) $shop['initials'] ?></b></i><?php endif; ?>
          <?php if ($shop['sponsor']): ?><i>Sponsor shirts voorkant <b><?= (int) $shop['sponsor'] ?></b></i><?php endif; ?>
          <?php if ($shop['sponsor_back']): ?><i>Sponsor shirts achterkant <b><?= (int) $shop['sponsor_back'] ?></b></i><?php endif; ?>
          <?php if (!empty($shop['sponsor_padded'])): ?><i>Sponsor padded <b><?= (int) $shop['sponsor_padded'] ?></b></i><?php endif; ?>
          <?php if (!empty($shop['sponsor_jacket'])): ?><i>Sponsor field jack achterkant <b><?= (int) $shop['sponsor_jacket'] ?></b></i><?php endif; ?>
          <?php if (!empty($shop['sponsor_bag'])): ?><i>Sponsor tas <b><?= (int) $shop['sponsor_bag'] ?></b></i><?php endif; ?>
          <?php if ($shop['name_back']): ?><i>Nummer achterop <b><?= (int) $shop['name_back'] ?></b></i><?php endif; ?>
          <?php if (!empty($shop['staff_text'])): ?><i>Tekst staf <b><?= (int) $shop['staff_text'] ?></b></i><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php foreach (($shop['size_lines'] ?? []) as $sz => $line):
          if (empty($line['letters']) && empty($line['numbers'])) {
              continue;
          }
          $bits = [];
          if (!empty($line['letters'])) {
              $bits[] = implode(', ', $line['letters']);
          }
          if (!empty($line['numbers'])) {
              $bits[] = implode(', ', array_map(static fn($n) => '#'.$n, $line['numbers']));
          }
        ?>
        <div class="shop-nums"><?= h((string) $sz) ?>: <b><?= h(implode(' · ', $bits)) ?></b></div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($canEdit): ?>
    <p class="shop-rule">Printprijzen komen uit de catalogus (Rohda Logo, sponsor shirts/padded/field jack/tas, Initialen<?= ($printPrices['name_back'] ?? null) === null ? '; nummer: nog geen catalogusprijs' : '' ?>).</p>
    <details class="shop-more">
      <summary>Prijsregels (intern)</summary>
      <div class="tablewrap">
        <table>
          <thead>
            <tr>
              <th class="name">Product</th>
              <th>Maat</th>
              <th>Aantal</th>
              <th>Stuk</th>
              <th>Subtotaal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orderGroups as $g):
              $tid = (int) $g['tid'];
              $t = $types[$tid] ?? [];
              $small = isset($t['price_small']) && $t['price_small'] !== '' && $t['price_small'] !== null ? (float) $t['price_small'] : null;
              $large = isset($t['price_large']) && $t['price_large'] !== '' && $t['price_large'] !== null ? (float) $t['price_large'] : (isset($t['price']) && $t['price'] !== '' && $t['price'] !== null ? (float) $t['price'] : null);
              $field = ($g['band'] ?? 'large') === 'small' ? 'price_small' : 'price_large';
              $shown = $field === 'price_small' ? $small : $large;
            ?>
            <tr>
              <td class="name"><?= h(shortTypeName($tid, $types)) ?></td>
              <td class="<?= $g['size'] === 'maat onbekend' ? 'no' : 'ok' ?>"><?= h($g['size']) ?></td>
              <td><b><?= (int) $g['count'] ?></b></td>
              <td><?= moneyInput($tid, $field, $shown) ?></td>
              <td><?= $g['price'] !== null ? euroPair($g['price'] * $g['count']) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            <tr>
              <td class="name">Kleding</td>
              <td></td>
              <td><b id="orderPiecesCell"><?= (int) $orderPieces ?></b></td>
              <td></td>
              <td><b id="orderCostCell"><?= euroPair($orderCost) ?></b></td>
            </tr>
            <?php foreach ($printRows as $row):
              if ($row['count'] < 1) continue;
            ?>
            <tr>
              <td class="name"><?= h($row['label']) ?></td>
              <td class="ok">applicatie</td>
              <td><b><?= (int) $row['count'] ?></b></td>
              <td><?= $row['unit'] !== null ? euroPair($row['unit']) : '—' ?></td>
              <td><?= $row['sum'] !== null ? euroPair($row['sum']) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            <tr>
              <td class="name">Totaal</td>
              <td></td>
              <td></td>
              <td></td>
              <td><b id="orderTotalCell"><?= euroPair($orderTotal) ?></b></td>
            </tr>
          </tbody>
        </table>
      </div>
    </details>
    <?php endif; ?>
    <?php endif; ?>
  </details>

  <details class="section fold" id="staf">
    <summary class="fold-head"><h3>Staf</h3><span class="fold-meta"><?= count($staff) ?></span></summary>
    <p class="sub">Polo en quarter zip.<?= $canEdit ? ' Maat of vinkje wordt meteen opgeslagen. Stuur een link zodat ze zelf hun maten invullen, of vul hier in.' : '' ?></p>
    <div class="cards">
      <?php foreach ($staff as $s): ?>
      <article class="card" id="card-s-<?= (int) $s['id'] ?>">
        <div class="who"><b><?= h(fullName($s)) ?></b><span class="nr"><?= h($s['role']) ?></span></div>
        <?php if ($canEdit): ?><div class="meta"><a href="#cms-s-<?= (int) $s['id'] ?>">Wijzigen</a></div><?php endif; ?>
        <div class="kit">
          <?php
            $staffTypes = [];
            foreach (array_keys($s['items']) as $extraTid) {
                $extraTid = (int) $extraTid;
                if ($extraTid > 0 && isset($types[$extraTid])) {
                    $staffTypes[] = $extraTid;
                }
            }
          ?>
          <?php foreach ($staffTypes as $tid):
              if (!isset($types[$tid])) continue;
              $it = itemFor($s, $tid);
              if (!$it) continue;
              $pending = isPendingItem($it);
              $held = isHeldItem($it);
              $owned = isIssued($it);
              $cls = $owned ? 'ok' : ($pending ? 'wait' : 'extra');
              $unit = ($canEdit && ($pending || $held)) ? priceFor($types[$tid], (string) ($it['size'] ?? '')) : null;
          ?>
            <div class="row <?= $cls ?> kit-row<?= $pending ? '' : ' off' ?>" data-who="staff" data-id="<?= (int) $s['id'] ?>" data-tid="<?= $tid ?>" data-status="<?= ($pending || $held) ? 'pending' : ($owned ? 'owned' : '') ?>">
              <label class="want">
                <?php if ($canEdit): ?>
                <input type="checkbox" class="want-check"<?= $pending ? ' checked' : '' ?><?= $owned ? ' disabled title="In bezit"' : '' ?>>
                <?php endif; ?>
                <?= h(shortTypeName($tid, $types)) ?><span class="kit-tag"><?= $pending ? ' · bestellen' : ($owned ? ' · in bezit' : ($held ? ' · niet in bestelling' : '')) ?></span><?php if ($unit !== null): ?><span class="kit-price"> · <?= euroPair($unit) ?></span><?php endif; ?>
              </label>
              <?php if ($canEdit): ?>
                <?= sizeSelect($tid, (string) ($it['size'] ?? ''), 'staff', (int) $s['id']) ?>
                <?php if ($it): ?>
                <button type="button" class="del item-del" data-who="staff" data-id="<?= (int) $s['id'] ?>" data-tid="<?= $tid ?>">Verwijderen</button>
                <?php endif; ?>
              <?php else: ?>
                <span><?= h($it['size'] ?? '—') ?></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          <?php if ($canEdit): ?>
            <div class="addrow" data-who="staff" data-id="<?= (int) $s['id'] ?>">
              <select class="assign-type"><?= typeOptionsHtml($types, 'Item toevoegen…') ?></select>
              <select class="assign-size" disabled><option value="">Maat</option></select>
              <button type="button" class="btn dark assign-add">Toevoegen</button>
            </div>
            <div class="actions">
              <span class="save-state" data-who="staff" data-id="<?= (int) $s['id'] ?>"></span>
              <button type="button" class="btn save-one" data-who="staff" data-id="<?= (int) $s['id'] ?>" data-mode="active">In bezit</button>
              <?php if (isset($staffLinks[(int) $s['id']])): $sl = $staffLinks[(int) $s['id']]; ?>
              <button type="button" class="btn parent-copy" data-url="<?= h($sl['url']) ?>">Link staf</button>
              <a class="btn" href="<?= h($sl['wa']) ?>" target="_blank" rel="noopener">WhatsApp</a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </details>

  <details class="section fold" id="catalogus">
    <summary class="fold-head"><h3>Catalogus · Stanno</h3></summary>
    <p class="sub">Artikelnummers<?= $canEdit ? ', offerteprijzen excl. btw (incl. 21% eronder)' : '' ?> en maten zoals Stanno die voert. <?= $canEdit ? 'Stanno.com en Teamswear.nl zijn webshopprijzen incl. btw (sept. 2026), met excl. eronder ter vergelijking. Pas een regel aan of verwijder hem. Nieuw artikel onderaan.' : '' ?></p>
    <?php if ($canEdit): ?>
    <details class="shop-more" id="packageDefaults">
      <summary>Pakket-sjabloon</summary>
      <p class="hint">Deze items zet <b>Pakket</b> in één keer op bestellen. Jacks nemen de shirtmaat over.</p>
      <div class="checks" id="packageChecks">
        <?php foreach ($types as $t):
          if (!typeIsActive($t) || isPrintCatalogType($t)) continue;
          $tid = (int) $t['id'];
          $on = in_array($tid, $PACKAGE_CORE, true) ? ' checked' : '';
        ?>
        <label><input type="checkbox" value="<?= $tid ?>"<?= $on ?>> <?= h(shortTypeName($tid, $types)) ?></label>
        <?php endforeach; ?>
      </div>
    </details>
    <?php endif; ?>
    <div class="tablewrap">
      <table>
        <thead>
          <tr>
            <th class="name">Type</th>
            <th>Artikel</th>
            <th>Kleur</th>
            <th>Merk</th>
            <?php if ($canEdit): ?>
            <th>Offerte 164 / JR</th>
            <th>Offerte S–XL / SR</th>
            <th>Stanno.com</th>
            <th>Teamswear</th>
            <?php endif; ?>
            <th class="name">Bedrukking</th>
            <?php if ($canEdit): ?><th></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($types as $t):
            if (!typeIsActive($t)) continue;
            $tid = (int) $t['id'];
            $small = isset($t['price_small']) && $t['price_small'] !== '' && $t['price_small'] !== null ? (float) $t['price_small'] : null;
            $large = isset($t['price_large']) && $t['price_large'] !== '' && $t['price_large'] !== null ? (float) $t['price_large'] : (isset($t['price']) && $t['price'] !== '' && $t['price'] !== null ? (float) $t['price'] : null);
            $tags = [];
            if (typePrints($t, 'print_rohda')) $tags[] = 'Rohda';
            if (typePrints($t, 'print_initials')) $tags[] = 'initialen';
            if (typePrints($t, 'print_sponsor')) $tags[] = 'sponsor shirts voorkant';
            if (typePrints($t, 'print_sponsor_back')) $tags[] = 'sponsor shirts achterkant';
            if (typePrints($t, 'print_sponsor_padded')) $tags[] = 'sponsor padded';
            if (typePrints($t, 'print_sponsor_jacket')) $tags[] = 'sponsor field jack';
            if (typePrints($t, 'print_sponsor_bag')) $tags[] = 'sponsor tas';
            if (typePrints($t, 'print_name_back')) $tags[] = 'nummer';
            if (typePrints($t, 'print_staff_text')) $tags[] = 'tekst staf';
          ?>
          <tr data-type-id="<?= $tid ?>">
            <td class="name">
              <?php if ($canEdit): ?>
              <div class="cat-name">
                <input class="cat-input" data-tid="<?= $tid ?>" data-field="display_name" value="<?= h((string) $t['display_name']) ?>" aria-label="Naam">
                <input class="cat-input" data-tid="<?= $tid ?>" data-field="print_place" value="<?= h((string) ($t['print_place'] ?? '')) ?>" placeholder="Plaats bedrukking…">
                <input class="cat-input" data-tid="<?= $tid ?>" data-field="sizes" value="<?= h(formatSizeList(sizeOptions($tid))) ?>" placeholder="Maten, kommagescheiden" aria-label="Maten">
              </div>
              <?php else: ?>
              <?= h($t['display_name']) ?><?php if (!empty($t['print_place'])): ?><div class="place"><?= h((string) $t['print_place']) ?></div><?php endif; ?>
              <div class="place"><?= h(formatSizeList(sizeOptions($tid))) ?></div>
              <?php endif; ?>
            </td>
            <td><?php if ($canEdit): ?><input class="cat-input" data-tid="<?= $tid ?>" data-field="article_number" value="<?= h((string) $t['article_number']) ?>"><?php else: ?><?= h((string) $t['article_number']) ?><?php endif; ?></td>
            <td><?php if ($canEdit): ?><input class="cat-input" data-tid="<?= $tid ?>" data-field="color" value="<?= h((string) $t['color']) ?>"><?php else: ?><?= h((string) $t['color']) ?><?php endif; ?></td>
            <td><?php if ($canEdit): ?><input class="cat-input" data-tid="<?= $tid ?>" data-field="brand" value="<?= h((string) $t['brand']) ?>"><?php else: ?><?= h((string) $t['brand']) ?><?php endif; ?></td>
            <?php if ($canEdit): ?>
            <td><?= moneyInput($tid, 'price_small', $small) ?></td>
            <td><?= moneyInput($tid, 'price_large', $large) ?></td>
            <?php
              $shop = isPrintCatalogType($t) ? null : shopCompareForArticle((string) ($t['article_number'] ?? ''));
            ?>
            <td><?= $shop ? shopPriceCell($shop['stanno_jr'], $shop['stanno_sr'], $shop['stanno_url']) : '<span class="muted">—</span>' ?></td>
            <td><?= $shop ? shopPriceCell($shop['teamwear_jr'], $shop['teamwear_sr'], $shop['teamwear_url']) : '<span class="muted">—</span>' ?></td>
            <?php endif; ?>
            <td class="left">
              <?php if ($canEdit): ?>
              <div class="cat-prints" data-tid="<?= $tid ?>">
                <label><input type="checkbox" data-print="print_rohda"<?= typePrints($t, 'print_rohda') ? ' checked' : '' ?>> Rohda</label>
                <label><input type="checkbox" data-print="print_initials"<?= typePrints($t, 'print_initials') ? ' checked' : '' ?>> Initialen</label>
                <label><input type="checkbox" data-print="print_sponsor"<?= typePrints($t, 'print_sponsor') ? ' checked' : '' ?>> Shirts voorkant</label>
                <label><input type="checkbox" data-print="print_sponsor_back"<?= typePrints($t, 'print_sponsor_back') ? ' checked' : '' ?>> Shirts achterkant</label>
                <label><input type="checkbox" data-print="print_sponsor_padded"<?= typePrints($t, 'print_sponsor_padded') ? ' checked' : '' ?>> Padded (gezamenlijk)</label>
                <label><input type="checkbox" data-print="print_sponsor_jacket"<?= typePrints($t, 'print_sponsor_jacket') ? ' checked' : '' ?>> Field jack achterkant</label>
                <label><input type="checkbox" data-print="print_sponsor_bag"<?= typePrints($t, 'print_sponsor_bag') ? ' checked' : '' ?>> Tas</label>
                <label><input type="checkbox" data-print="print_name_back"<?= typePrints($t, 'print_name_back') ? ' checked' : '' ?>> Nummer</label>
                <label><input type="checkbox" data-print="print_staff_text"<?= typePrints($t, 'print_staff_text') ? ' checked' : '' ?>> Tekst staf</label>
              </div>
              <?php else: ?>
              <div class="print-tags"><?php foreach ($tags as $tag): ?><i><?= h($tag) ?></i><?php endforeach; ?><?php if (!$tags): ?><span class="muted">geen</span><?php endif; ?></div>
              <?php endif; ?>
            </td>
            <?php if ($canEdit): ?>
            <td><?php
              $usedPlayers = (int) ($typeAssigned[$tid]['player'] ?? 0);
              $usedStaff = (int) ($typeAssigned[$tid]['staff'] ?? 0);
              $used = $usedPlayers + $usedStaff;
              if ($used > 0):
            ?><span class="cat-used" title="<?= h(assignedTypeDeleteError($usedPlayers, $usedStaff)) ?>">Toegekend · <?= (int) $used ?></span><?php else: ?>
            <button type="button" class="cat-del" data-tid="<?= $tid ?>" data-name="<?= h((string) $t['display_name']) ?>">Verwijderen</button>
            <?php endif; ?></td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($canEdit): ?>
    <details class="shop-more" style="margin-top:14px">
      <summary>Artikel toevoegen</summary>
      <p class="hint">Nieuwe jas, tas of shirt.</p>
      <div class="add-type" id="addTypeForm">
        <label>Naam <input class="cat-input" id="newDisplay" placeholder="Trainingsshirt"></label>
        <label>Artikel <input class="cat-input" id="newArticle" placeholder="410014"></label>
        <label>Kleur <input class="cat-input" id="newColor" placeholder="Zwart"></label>
        <label>Merk <input class="cat-input" id="newBrand" value="Stanno"></label>
        <label>164 / JR <input class="cat-input" id="newSmall" inputmode="decimal" placeholder="35,50"></label>
        <label>S–XL / SR <input class="cat-input" id="newLarge" inputmode="decimal" placeholder="37,50"></label>
        <label>Maten <input class="cat-input" id="newSizes" placeholder="leeg = Stanno-maten van het artikelnummer"></label>
        <label>Maatgroep
          <select class="cat-input" id="newKind">
            <option value="body">Shirt / jas</option>
            <option value="socks">Sokken</option>
            <option value="onesize">Eén maat</option>
          </select>
        </label>
        <label>Ronde
          <select class="cat-input" id="newGroup">
            <option value="extra">Overig</option>
            <option value="package">Pakket</option>
            <option value="match">Wedstrijd</option>
          </select>
        </label>
        <label>Bedrukking <input class="cat-input" id="newPlace" placeholder="Rohda borst · initialen"></label>
      </div>
      <div class="checks" id="newPrints">
        <label><input type="checkbox" value="print_rohda"> Rohda</label>
        <label><input type="checkbox" value="print_initials"> Initialen</label>
        <label><input type="checkbox" value="print_sponsor"> Shirts voorkant</label>
        <label><input type="checkbox" value="print_sponsor_back"> Shirts achterkant</label>
        <label><input type="checkbox" value="print_sponsor_padded"> Padded (gezamenlijk)</label>
        <label><input type="checkbox" value="print_sponsor_jacket"> Field jack achterkant</label>
        <label><input type="checkbox" value="print_sponsor_bag"> Tas</label>
        <label><input type="checkbox" value="print_name_back"> Nummer</label>
        <label><input type="checkbox" value="print_staff_text"> Tekst staf</label>
      </div>
      <div class="actions">
        <button type="button" class="btn dark" id="addTypeBtn">Artikel toevoegen</button>
      </div>
    </details>
    <?php endif; ?>
  </details>

  <?php if ($canEdit):
    $cmsPlayers = array_values(array_filter($players, static fn($p) => playerOnScoutTeam14($p, $portal)));
    usort($cmsPlayers, static fn($a, $b) => strcasecmp(fullName($a), fullName($b)));
    $inactiveTypes = array_values(array_filter($types, static fn($t) => is_array($t) && !typeIsActive($t)));
    $posSelect = static function (string $current) use ($posLabel): string {
        $html = '';
        foreach ($posLabel as $key => $lab) {
            $sel = ($current === $key) ? ' selected' : '';
            $html .= '<option value="'.h($key).'"'.$sel.'>'.h($lab).'</option>';
        }
        return $html;
    };
  ?>
  <details class="section fold" id="beheer">
    <summary class="fold-head"><h3>Beheer</h3></summary>
    <p class="sub">CMS: seizoen, staf, catalogus. Spelers zijn alleen de huidige 14-2 selectie uit de scout-app.</p>

    <div class="parent-defaults">
      <h4>Seizoen</h4>
      <div class="cms-grid">
        <label>Seizoen
          <input class="cat-input" id="seasonInput" value="<?= h($season) ?>" maxlength="16" placeholder="26/27">
        </label>
        <button type="button" class="btn dark" id="seasonSave">Opslaan</button>
      </div>
    </div>

    <div class="parent-defaults">
      <h4>Backup</h4>
      <p class="hint">Kopie van de database en instellingen. Wordt gedownload en blijft op de server (laatste 15).</p>
      <div class="actions" style="margin:0">
        <button type="button" class="btn dark" id="backupBtn">Backup maken</button>
      </div>
      <?php
        $recentBackups = listKitroomBackups(5);
        if ($recentBackups):
      ?>
      <p class="hint" id="backupList"><?php foreach ($recentBackups as $b): ?><?= h($b['at']) ?> · <?= h($b['file']) ?> (<?= (int) $b['kb'] ?> kB)<br><?php endforeach; ?></p>
      <?php else: ?>
      <p class="hint" id="backupList">Nog geen backup op de server.</p>
      <?php endif; ?>
    </div>

    <details class="shop-more">
    <summary>Spelers</summary>
    <h4 class="line">Spelers</h4>
    <div class="parent-defaults">
      <p class="hint">Alleen namen uit de scout-app 14-2. Wie daar niet in staat, verdwijnt uit dit overzicht.</p>
      <div class="cms-grid" id="newPlayerForm">
        <label>Voornaam <input class="cat-input" id="npFirst" placeholder="Voornaam"></label>
        <label>Achternaam <input class="cat-input" id="npLast" placeholder="Achternaam"></label>
        <label>Lijn <select class="cat-input" id="npPos"><?= $posSelect('midfielder') ?></select></label>
        <label>Rugnummer <input class="cat-input" id="npJersey" inputmode="numeric" placeholder="1–99"></label>
        <label>Gast <select class="cat-input" id="npGuest"><option value="0">Nee</option><option value="1">Ja</option></select></label>
        <button type="button" class="btn dark" id="npAdd">Speler toevoegen</button>
      </div>
    </div>
    <div class="tablewrap">
      <table>
        <thead>
          <tr>
            <th class="name">Naam</th>
            <th>Lijn</th>
            <th>Rugnr</th>
            <th>Gast</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cmsPlayers as $cp):
            $pid = (int) $cp['id'];
            $st = (($cp['status'] ?? '') === 'active') ? 'active' : 'inactive';
          ?>
          <tr class="<?= $st === 'inactive' ? 'archived' : '' ?>" id="cms-p-<?= $pid ?>">
            <td class="name">
              <div class="cat-name">
                <input class="cat-input cms-p-first" value="<?= h((string) $cp['first_name']) ?>" aria-label="Voornaam">
                <input class="cat-input cms-p-last" value="<?= h((string) $cp['last_name']) ?>" aria-label="Achternaam">
              </div>
            </td>
            <td><select class="cat-input cms-p-pos"><?= $posSelect((string) ($cp['position'] ?? 'midfielder')) ?></select></td>
            <td><input class="cat-input cms-p-jersey" value="<?= h((string) normalizeJerseyNumber($cp['jersey_number'] ?? '') ?? '') ?>" inputmode="numeric" style="width:4.5rem"></td>
            <td><select class="cat-input cms-p-guest"><option value="0"<?= empty($cp['is_guest']) ? ' selected' : '' ?>>Nee</option><option value="1"<?= !empty($cp['is_guest']) ? ' selected' : '' ?>>Ja</option></select></td>
            <td class="<?= $st === 'active' ? 'ok' : 'no' ?>"><?= $st === 'active' ? 'actief' : 'archief' ?></td>
            <td>
              <div class="cms-actions">
                <button type="button" class="btn dark cms-p-save" data-id="<?= $pid ?>">Opslaan</button>
                <?php if ($st === 'active'): ?>
                <a class="btn" href="#card-p-<?= $pid ?>">Kaart</a>
                <button type="button" class="cat-del cms-p-archive" data-id="<?= $pid ?>">Archiveren</button>
                <?php else: ?>
                <button type="button" class="btn cms-p-restore" data-id="<?= $pid ?>">Terugzetten</button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    </details>

    <details class="shop-more">
    <summary>Staf</summary>
    <h4 class="line">Staf</h4>
    <div class="parent-defaults">
      <div class="cms-grid" id="newStaffForm">
        <label>Voornaam <input class="cat-input" id="nsFirst" placeholder="Voornaam"></label>
        <label>Achternaam <input class="cat-input" id="nsLast" placeholder="Achternaam"></label>
        <label>Rol <input class="cat-input" id="nsRole" placeholder="trainer"></label>
        <button type="button" class="btn dark" id="nsAdd">Staflid toevoegen</button>
      </div>
    </div>
    <div class="tablewrap">
      <table>
        <thead>
          <tr>
            <th class="name">Naam</th>
            <th>Rol</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($staffAll as $cs):
            $sid = (int) $cs['id'];
            $sst = (string) ($cs['status'] ?? 'active');
            $sstLabel = ['active' => 'actief', 'inactive' => 'archief', 'former' => 'oud'][$sst] ?? $sst;
          ?>
          <tr class="<?= $sst === 'active' ? '' : 'archived' ?>" id="cms-s-<?= $sid ?>">
            <td class="name">
              <div class="cat-name">
                <input class="cat-input cms-s-first" value="<?= h((string) $cs['first_name']) ?>">
                <input class="cat-input cms-s-last" value="<?= h((string) $cs['last_name']) ?>">
              </div>
            </td>
            <td><input class="cat-input cms-s-role" value="<?= h((string) $cs['role']) ?>"></td>
            <td class="<?= $sst === 'active' ? 'ok' : 'no' ?>"><?= h($sstLabel) ?></td>
            <td>
              <div class="cms-actions">
                <button type="button" class="btn dark cms-s-save" data-id="<?= $sid ?>">Opslaan</button>
                <?php if ($sst === 'active'): ?>
                <a class="btn" href="#card-s-<?= $sid ?>">Kaart</a>
                <button type="button" class="cat-del cms-s-archive" data-id="<?= $sid ?>">Archiveren</button>
                <?php else: ?>
                <button type="button" class="btn cms-s-restore" data-id="<?= $sid ?>">Terugzetten</button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    </details>

    <?php if ($inactiveTypes): ?>
    <h4 class="line">Verwijderde artikelen</h4>
    <p class="sub">Terugzetten maakt het artikel weer zichtbaar in de catalogus. Bestaande toewijzingen blijven.</p>
    <div class="tablewrap">
      <table>
        <thead>
          <tr><th class="name">Type</th><th>Artikel</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($inactiveTypes as $it): ?>
          <tr class="archived">
            <td class="name"><?= h((string) $it['display_name']) ?></td>
            <td><?= h((string) ($it['article_number'] ?? '')) ?></td>
            <td><button type="button" class="btn cms-t-restore" data-id="<?= (int) $it['id'] ?>">Terugzetten</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <h4 class="line">Verwijderde artikelen</h4>
    <p class="sub">Geen verwijderde catalogusartikelen.</p>
    <?php endif; ?>
  </details>
  <?php endif; ?>
</div>
<div id="pinModal" class="modal hidden">
  <form class="modalbox" id="pinForm">
    <h3>Beheer</h3>
    <p>Pincode van de 14-2 teamapp. Prijzen en bedragen zijn alleen hier zichtbaar.</p>
    <input id="pinInput" type="password" inputmode="numeric" maxlength="8" autocomplete="off" autofocus>
    <p class="err" id="pinErr"></p>
    <div class="actions">
      <button class="btn dark" type="submit">Open</button>
      <button class="btn" type="button" id="pinCancel">Annuleren</button>
    </div>
  </form>
</div>
<div id="toast" class="toast"></div>
<script>
(function(){
  const KEY='kitroom-theme';
  const meta=document.querySelector('meta[name="theme-color"]');
  function theme(){
    return document.documentElement.getAttribute('data-theme')==='light' ? 'light' : 'dark';
  }
  function apply(t){
    const next=t==='light' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    document.documentElement.style.colorScheme=next;
    if(meta) meta.setAttribute('content', next==='light' ? '#F4F1EC' : '#090A0C');
    document.querySelectorAll('[data-theme-set]').forEach(btn=>{
      btn.setAttribute('aria-pressed', btn.getAttribute('data-theme-set')===next ? 'true' : 'false');
    });
    try { localStorage.setItem(KEY, next); } catch(e) {}
  }
  document.querySelectorAll('[data-theme-set]').forEach(btn=>{
    btn.addEventListener('click', ()=>apply(btn.getAttribute('data-theme-set')));
  });
  apply(theme());
})();
(function(){
  function openFor(el){
    let n=el;
    while(n){
      if(n.tagName==='DETAILS') n.open=true;
      n=n.parentElement;
    }
  }
  document.querySelectorAll('details').forEach(d=>{ d.open=false; });
  document.addEventListener('click', e=>{
    const a=e.target.closest?.('a[href^="#"]');
    if(!a) return;
    const id=a.getAttribute('href').slice(1);
    const el=id ? document.getElementById(id) : null;
    if(el) openFor(el);
  });
  window.addEventListener('beforeprint', ()=>{
    document.querySelectorAll('details.fold').forEach(d=>{ d.open=true; });
  });
})();
const TEAM = {
  csrf: <?= json_encode($csrf) ?>,
  editing: <?= $canEdit ? 'true' : 'false' ?>,
  vat: <?= json_encode(vatRate()) ?>,
  parentFills: <?= json_encode($parentFillJs, JSON_UNESCAPED_UNICODE) ?>,
  packageTypes: <?= json_encode(packageTypeIds()) ?>,
  printPrices: <?= json_encode($printPrices, JSON_UNESCAPED_UNICODE) ?>,
  suggest: <?= json_encode(array_reduce($active, static function ($acc, $p) use ($types) {
      $id = (int) $p['id'];
      $jacket = suggestedJacketSize($p);
      $acc[$id] = [];
      foreach (packageTypeIds() as $tid) {
          $kind = isset($types[$tid]) ? typeSizeKind($types[$tid]) : 'body';
          if ($kind === 'onesize') {
              $acc[$id][$tid] = 'één maat';
          } else {
              $acc[$id][$tid] = $jacket !== 'onbekend' ? $jacket : '';
          }
      }
      return $acc;
  }, []), JSON_UNESCAPED_UNICODE) ?>,
  types: <?= json_encode(array_values(array_map(static function ($t) use ($types) {
      $id = (int) $t['id'];
      $prints = [];
      foreach ([
          'rohda' => 'print_rohda',
          'initials' => 'print_initials',
          'sponsor' => 'print_sponsor',
          'sponsor_back' => 'print_sponsor_back',
          'sponsor_padded' => 'print_sponsor_padded',
          'sponsor_jacket' => 'print_sponsor_jacket',
          'sponsor_bag' => 'print_sponsor_bag',
          'name_back' => 'print_name_back',
          'staff_text' => 'print_staff_text',
      ] as $key => $flag) {
          if (typePrints($t, $flag)) {
              $prints[] = $key;
          }
      }
      $small = isset($t['price_small']) && $t['price_small'] !== '' && $t['price_small'] !== null ? (float) $t['price_small'] : null;
      $large = isset($t['price_large']) && $t['price_large'] !== '' && $t['price_large'] !== null ? (float) $t['price_large'] : (isset($t['price']) && $t['price'] !== '' && $t['price'] !== null ? (float) $t['price'] : null);
      return [
          'id' => $id,
          'name' => shortTypeName($id, $types),
          'sizes' => sizeOptions($id),
          'small' => $small,
          'large' => $large,
          'prints' => $prints,
      ];
  }, $types)), JSON_UNESCAPED_UNICODE) ?>
};
function toast(msg){
  const el=document.getElementById('toast');
  el.textContent=msg;
  el.classList.add('show');
  clearTimeout(toast._t);
  toast._t=setTimeout(()=>el.classList.remove('show'), 2200);
}
(function(){
  const KEY='kitroom-parent-seen';
  const fills=Array.isArray(TEAM.parentFills)?TEAM.parentFills:[];
  const seen=parseInt(localStorage.getItem(KEY)||'0',10)||0;
  const neu=fills.filter(f=>f.at>seen);
  const box=document.getElementById('parentAlert');
  const names=document.getElementById('parentAlertNames');
  const nav=document.getElementById('ouderNavCount');
  function markSeen(){
    const latest=fills.reduce((m,f)=>Math.max(m, f.at||0), Date.now());
    try { localStorage.setItem(KEY, String(latest)); } catch(e) {}
    box?.classList.add('hidden');
    if(nav){ nav.textContent=String(fills.length); nav.classList.remove('wait'); }
  }
  if(neu.length && box && names){
    names.textContent=neu.map(f=>f.name).join(', ');
    box.classList.remove('hidden');
    if(nav){ nav.textContent=String(neu.length); nav.classList.add('wait'); }
  }
  document.getElementById('parentAlertOk')?.addEventListener('click', markSeen);
})();
async function api(payload){
  const res=await fetch('save.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload), credentials:'same-origin'});
  let data={};
  try{ data=await res.json(); }catch(e){ data={ok:false,error:'Geen antwoord'}; }
  if(!res.ok && !data.error) data.error='Fout '+res.status;
  return data;
}
document.querySelectorAll('#playerFilters button').forEach(btn=>{
  btn.onclick=()=>{
    document.querySelectorAll('#playerFilters button').forEach(b=>b.classList.remove('on'));
    btn.classList.add('on');
    const f=btn.dataset.f;
    document.querySelectorAll('#playerCards .card').forEach(c=>{
      const show = f==='all'
        || (f==='guest' && c.dataset.guest==='1')
        || (c.dataset.pos===f);
      c.classList.toggle('hidden', !show);
    });
    document.querySelectorAll('#playerCards .line').forEach(line=>{
      const cards=[...line.nextElementSibling.querySelectorAll('.card')];
      const any=cards.some(c=>!c.classList.contains('hidden'));
      line.classList.toggle('hidden', !any);
      line.nextElementSibling.classList.toggle('hidden', !any);
    });
  };
});
const pinModal=document.getElementById('pinModal');
document.getElementById('editBtn')?.addEventListener('click', ()=>{
  pinModal.classList.remove('hidden');
  document.getElementById('pinInput').focus();
});
document.getElementById('pinCancel')?.addEventListener('click', ()=>pinModal.classList.add('hidden'));
document.getElementById('pinForm')?.addEventListener('submit', async e=>{
  e.preventDefault();
  const pin=document.getElementById('pinInput').value;
  const out=await api({action:'login', pin});
  if(out.ok){ location.reload(); return; }
  document.getElementById('pinErr').textContent=out.error||'Mislukt';
});
document.getElementById('logoutBtn')?.addEventListener('click', async ()=>{
  await api({action:'logout'});
  location.reload();
});
let clothingChain=Promise.resolve();
function queueClothing(fn){
  const run=clothingChain.then(fn, fn);
  clothingChain=run.then(()=>undefined, ()=>undefined);
  return run;
}
function liveKitRow(row){
  return !!row && row.dataset.gone!=='1' && row.isConnected;
}
function syncKitRow(row){
  if(!liveKitRow(row) || row.dataset.status==='owned') return;
  const box=row.querySelector('.want-check');
  const on=!!box?.checked;
  row.classList.toggle('off', !on);
  row.classList.toggle('wait', on);
  row.classList.toggle('extra', !on);
  const tag=row.querySelector('.kit-tag');
  if(tag) tag.textContent=on?' · bestellen':' · niet in bestelling';
  const price=priceForType(+row.dataset.tid, row.querySelector('.size-select')?.value||'');
  const priceEl=row.querySelector('.kit-price');
  if(priceEl) priceEl.textContent=price!=null?' · '+euroPairJs(price):'';
}
function rowChoice(row){
  const owned=row.dataset.status==='owned';
  return {
    want: owned || !!row.querySelector('.want-check')?.checked,
    size: row.querySelector('.size-select')?.value||''
  };
}
document.addEventListener('change', e=>{
  const box=e.target.closest?.('.want-check');
  if(box){
    const row=box.closest('.kit-row');
    if(box.checked){
      const sel=row?.querySelector('.size-select');
      if(sel && !sel.value){
        const tid=+row.dataset.tid, who=row.dataset.who, id=+row.dataset.id;
        const sizes=typeSizes(tid);
        let pick='';
        if(who==='player' && TEAM.suggest && TEAM.suggest[id]) pick=TEAM.suggest[id][tid]||'';
        if(!pick && sizes.length===1) pick=sizes[0];
        if(pick && sizes.includes(pick)) sel.value=pick;
      }
    }
    syncKitRow(row);
    recalcOrder();
    if(row) scheduleSavePerson(row.dataset.who, +row.dataset.id);
  }
  const include=e.target.closest?.('.shop-include-check');
  if(include){
    const card=include.closest('.shop-card');
    const tid=+card?.dataset.tid;
    if(tid){
      setPendingTypeWant(tid, include.checked);
      recalcOrder();
      scheduleSaveOrder();
    }
  }
  if(e.target?.id==='orderSelectAll'){
    const on=e.target.checked;
    document.querySelectorAll('.kit-row[data-status="pending"] .want-check').forEach(b=>{
      if(b.disabled) return;
      b.checked=on;
      syncKitRow(b.closest('.kit-row'));
    });
    recalcOrder();
    scheduleSaveOrder();
  }
  const sel=e.target.closest?.('.size-select');
  if(!sel || sel.dataset.jersey || !sel.closest('.kit-row')) return;
  const tid=sel.dataset.tid, who=sel.dataset.who, id=sel.dataset.id;
  document.querySelectorAll(`.size-select[data-who="${who}"][data-id="${id}"][data-copy-from="${tid}"]`).forEach(t=>{
    const val=sel.value;
    const row=t.closest('.kit-row');
    if(!liveKitRow(row)) return;
    if(!t.value && val && [...t.options].some(o=>o.value===val)) t.value=val;
    syncKitRow(row);
  });
  syncKitRow(sel.closest('.kit-row'));
  recalcOrder();
  scheduleSavePerson(who, +id);
});
function itemsFor(who, id, root){
  const items={};
  (root||document).querySelectorAll(`.kit-row[data-who="${who}"][data-id="${id}"]`).forEach(row=>{
    if(!liveKitRow(row)) return;
    items[row.dataset.tid]=rowChoice(row);
  });
  return items;
}
function cardRoot(who, id){
  return document.getElementById(who==='staff' ? 'card-s-'+id : 'card-p-'+id);
}
function setSaveState(who, id, text, cls){
  const el=document.querySelector(`.save-state[data-who="${who}"][data-id="${id}"]`);
  if(!el) return;
  el.textContent=text;
  el.classList.remove('on','err');
  if(cls) el.classList.add(cls);
}
const personSaveTimers={};
let saveOrderTimer=null;
function scheduleSavePerson(who, id){
  if(!TEAM.editing || !who || !id) return;
  const key=who+':'+id;
  setSaveState(who, id, 'Opslaan…', '');
  clearTimeout(personSaveTimers[key]);
  personSaveTimers[key]=setTimeout(()=>savePerson(who, id), 250);
}
async function savePerson(who, id){
  const key=who+':'+id;
  delete personSaveTimers[key];
  return queueClothing(async ()=>{
    const root=cardRoot(who, id);
    const out=await api({action:'save', csrf:TEAM.csrf, who, id, mode:'pending', items: itemsFor(who, id, root)});
    if(!out.ok){
      setSaveState(who, id, out.error||'Niet opgeslagen', 'err');
      toast(out.error||'Opslaan mislukt');
      return;
    }
    setSaveState(who, id, 'Opgeslagen', 'on');
  });
}
async function saveRow(who, id, mode, root){
  const key=who+':'+id;
  clearTimeout(personSaveTimers[key]);
  delete personSaveTimers[key];
  const out=await queueClothing(()=>api({action:'save', csrf:TEAM.csrf, who, id, mode: mode||'pending', items: itemsFor(who, id, root)}));
  if(!out.ok){ toast(out.error||'Opslaan mislukt'); return; }
  toast(mode==='active' ? 'Op in bezit gezet' : 'Opgeslagen');
  location.reload();
}
document.querySelectorAll('.save-one').forEach(btn=>{
  btn.onclick=()=>saveRow(btn.dataset.who, +btn.dataset.id, btn.dataset.mode||'pending', btn.closest('article, tr'));
});
document.querySelectorAll('.jersey-select').forEach(sel=>{
  sel.addEventListener('change', async ()=>{
    const id=+sel.dataset.id;
    if(!id) return;
    setSaveState('player', id, 'Opslaan…', '');
    const out=await api({action:'save_jersey', csrf:TEAM.csrf, id, jersey_number:sel.value||''});
    if(!out.ok){
      setSaveState('player', id, out.error||'Nummer mislukt', 'err');
      toast(out.error||'Nummer opslaan mislukt');
      return;
    }
    setSaveState('player', id, 'Opgeslagen', 'on');
  });
});
document.getElementById('saveAllBtn')?.addEventListener('click', async ()=>{
  Object.keys(personSaveTimers).forEach(k=>{
    clearTimeout(personSaveTimers[k]);
    delete personSaveTimers[k];
  });
  clearTimeout(saveOrderTimer);
  const out=await queueClothing(()=>api({action:'save_all', csrf:TEAM.csrf, mode:'pending', rows:collectAllKitRows()}));
  if(!out.ok){ toast(out.error||'Opslaan mislukt'); return; }
  toast('Alles opgeslagen');
});
async function copyText(text){
  try {
    await navigator.clipboard.writeText(text);
    return true;
  } catch(e) {
    return false;
  }
}
document.querySelectorAll('.parent-copy').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    const url=btn.dataset.url||'';
    if(!url) return;
    if(await copyText(url)) toast('Link gekopieerd');
    else window.prompt('Kopieer deze link', url);
  });
});
document.querySelectorAll('.parent-rotate').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    if(!confirm('De oude link stopt dan met werken. Nieuwe link maken?')) return;
    const out=await api({action:'parent_rotate', csrf:TEAM.csrf, id:+btn.dataset.id, who:btn.dataset.who||'player'});
    if(!out.ok){ toast(out.error||'Mislukt'); return; }
    if(await copyText(out.url||'')) toast('Nieuwe link gekopieerd');
    location.reload();
  });
});
function checkedTypes(el){
  return [...(el?.querySelectorAll('input[type="checkbox"]:checked')||[])].map(i=>+i.value);
}
function typesKey(list){
  return [...list].map(Number).sort((a,b)=>a-b).join(',');
}
async function saveParentDefaults(){
  const fieldEl=document.querySelector('[data-parent-scope="field"]');
  const keeperEl=document.querySelector('[data-parent-scope="keeper"]');
  const staffEl=document.querySelector('[data-parent-scope="staff"]');
  if(!fieldEl||!keeperEl) return;
  const field=checkedTypes(fieldEl);
  const keeper=checkedTypes(keeperEl);
  const staff=staffEl ? checkedTypes(staffEl) : [];
  if(field.length<1||keeper.length<1){ toast('Kies minstens één item'); return; }
  if(staffEl && staff.length<1){ toast('Kies minstens één staf-item'); return; }
  const note=document.getElementById('parentNote')?.value||'';
  const out=await api({action:'parent_form', csrf:TEAM.csrf, scope:'defaults', field, keeper, staff, note});
  if(!out.ok){ toast(out.error||'Mislukt'); return; }
  toast('Formulier opgeslagen');
}
document.getElementById('parentDefaults')?.addEventListener('change', e=>{
  if(e.target.id==='parentNote') return;
  saveParentDefaults();
});
let parentNoteTimer;
document.getElementById('parentNote')?.addEventListener('input', ()=>{
  clearTimeout(parentNoteTimer);
  parentNoteTimer=setTimeout(saveParentDefaults, 450);
});
document.querySelectorAll('[data-parent-scope="player"]').forEach(box=>{
  box.addEventListener('change', async ()=>{
    const types=checkedTypes(box);
    if(types.length<1){ toast('Kies minstens één item'); return; }
    const def=(box.dataset.default||'').split(',').filter(Boolean).map(Number);
    const reset=typesKey(types)===typesKey(def);
    const out=await api({action:'parent_form', csrf:TEAM.csrf, scope:'player', id:+box.dataset.id, types, reset:reset});
    if(!out.ok){ toast(out.error||'Mislukt'); return; }
    toast(out.custom ? 'Aangepast voor deze speler' : 'Standaard voor deze speler');
  });
});
document.querySelectorAll('[data-parent-scope="staff-one"]').forEach(box=>{
  box.addEventListener('change', async ()=>{
    const types=checkedTypes(box);
    if(types.length<1){ toast('Kies minstens één item'); return; }
    const def=(box.dataset.default||'').split(',').filter(Boolean).map(Number);
    const reset=typesKey(types)===typesKey(def);
    const out=await api({action:'parent_form', csrf:TEAM.csrf, scope:'staff', id:+box.dataset.id, types, reset:reset});
    if(!out.ok){ toast(out.error||'Mislukt'); return; }
    toast(out.custom ? 'Aangepast voor deze staf' : 'Standaard voor deze staf');
  });
});
document.querySelectorAll('.parent-reset').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    const who=btn.dataset.who||'player';
    const out=await api({action:'parent_form', csrf:TEAM.csrf, scope:who, id:+btn.dataset.id, reset:true});
    if(!out.ok){ toast(out.error||'Mislukt'); return; }
    location.reload();
  });
});
function typeSizes(tid){
  const t=(TEAM.types||[]).find(x=>x.id===tid);
  return t && Array.isArray(t.sizes) ? t.sizes : [];
}
function typeById(tid){
  return (TEAM.types||[]).find(x=>x.id===+tid)||null;
}
function euroJs(n){
  if(n==null || Number.isNaN(n)) return '—';
  const neg=n<0;
  const [a,b]=Math.abs(n).toFixed(2).split('.');
  return (neg?'-':'')+'€ '+a.replace(/\B(?=(\d{3})+(?!\d))/g,'.')+','+b;
}
function withVatJs(n){
  return Math.round(n*(1+(TEAM.vat||0.21))*100)/100;
}
function euroInclJs(n){
  return euroJs(withVatJs(n))+' incl. btw';
}
function euroPairJs(n){
  return euroJs(n)+' · '+euroJs(withVatJs(n))+' incl.';
}
function isYouthSize(size){
  const s=String(size||'').toUpperCase().trim();
  if(!s) return true;
  const small=['XS','XXS','XXXS','32','33','34','35','36','37','38','39','40','41','42','116','128','140','152','164','JR','25/29','30/35','36/40','25-29','30-35','31-35','36-40'];
  return small.includes(s) || /^(1[2-6]4|140|152|176)$/.test(s);
}
function priceForType(tid, size){
  const t=typeById(tid);
  if(!t) return null;
  const small=t.small!=null?+t.small:null;
  const large=t.large!=null?+t.large:small;
  const pick=isYouthSize(size)?(small??large):(large??small);
  return pick==null?null:pick;
}
function pendingWantRows(){
  return [...document.querySelectorAll('.kit-row[data-status="pending"]')].filter(row=>{
    const box=row.querySelector('.want-check');
    return box?box.checked:true;
  });
}
function setText(el, text){
  if(el) el.textContent=text;
}
function recalcOrder(){
  if(!TEAM.editing) return;
  const rows=pendingWantRows();
  let pieces=0, clothing=0;
  const byTid={};
  const prints={};
  (TEAM.types||[]).forEach(t=>{
    (t.prints||[]).forEach(k=>{ prints[k]=prints[k]||0; });
  });
  rows.forEach(row=>{
    const tid=+row.dataset.tid;
    const size=row.querySelector('.size-select')?.value||'';
    const price=priceForType(tid, size);
    pieces+=1;
    if(price!=null) clothing+=price;
    if(!byTid[tid]) byTid[tid]={count:0, cost:0};
    byTid[tid].count+=1;
    if(price!=null) byTid[tid].cost+=price;
    const t=typeById(tid);
    (t?.prints||[]).forEach(k=>{ prints[k]=(prints[k]||0)+1; });
  });
  let printCost=0;
  Object.entries(prints).forEach(([k,n])=>{
    const unit=TEAM.printPrices&&TEAM.printPrices[k]!=null?+TEAM.printPrices[k]:null;
    if(unit!=null) printCost+=unit*n;
  });
  const total=clothing+printCost;
  setText(document.getElementById('statPieces'), String(pieces));
  setText(document.getElementById('statTotal'), euroJs(total));
  setText(document.getElementById('statTotalIncl'), euroInclJs(total));
  setText(document.getElementById('livePieces'), String(pieces));
  setText(document.getElementById('liveExcl'), euroJs(total));
  setText(document.getElementById('liveIncl'), euroInclJs(total));
  setText(document.getElementById('bestelMeta'), pieces+' stuks');
  const sub=document.getElementById('bestelSub');
  if(sub){
    sub.textContent=pieces+' stuks · '+euroJs(total)+' excl. · '+euroInclJs(total)+' · artikelnummers, maten en print. Uitvinken haalt het item uit prijs en Excel, niet van de speler.';
  }
  setText(document.getElementById('orderPiecesCell'), String(pieces));
  setText(document.getElementById('orderCostCell'), euroPairJs(clothing));
  setText(document.getElementById('orderTotalCell'), euroPairJs(total));
  document.querySelectorAll('.shop-card[data-tid]').forEach(card=>{
    const tid=+card.dataset.tid;
    const info=byTid[tid]||{count:0, cost:0};
    const countEl=card.querySelector('.shop-count');
    if(countEl) countEl.textContent=String(info.count);
    const eur=card.querySelectorAll('.shop-eur');
    if(eur[0]) eur[0].textContent=info.cost>0?euroJs(info.cost):'';
    if(eur[1]) eur[1].textContent=info.cost>0?euroInclJs(info.cost):'';
    const box=card.querySelector('.shop-include-check');
    const pending=[...document.querySelectorAll(`.kit-row[data-status="pending"][data-tid="${tid}"] .want-check`)];
    const on=pending.filter(b=>b.checked).length;
    if(box){
      box.checked=pending.length>0 && on===pending.length;
      box.indeterminate=on>0 && on<pending.length;
    }
    card.classList.toggle('off', on<1);
  });
  const allBox=document.getElementById('orderSelectAll');
  if(allBox){
    const pending=[...document.querySelectorAll('.kit-row[data-status="pending"] .want-check')].filter(b=>!b.disabled);
    const on=pending.filter(b=>b.checked).length;
    allBox.checked=pending.length>0 && on===pending.length;
    allBox.indeterminate=on>0 && on<pending.length;
  }
  document.querySelectorAll('[data-print-key]').forEach(el=>{
    const key=el.dataset.printKey;
    const n=prints[key]||0;
    const unit=el.dataset.printUnit!=='' && el.dataset.printUnit!=null?+el.dataset.printUnit:(TEAM.printPrices&&TEAM.printPrices[key]!=null?+TEAM.printPrices[key]:null);
    setText(el.querySelector('.print-count'), String(n));
    const sum=unit!=null && n>0?unit*n:null;
    setText(el.querySelector('.print-sum'), sum!=null?euroJs(sum):'');
    setText(el.querySelector('.print-sum-incl'), sum!=null?euroInclJs(sum):(n>0 && unit==null?'geen prijs in catalogus':''));
    el.classList.toggle('accent', unit!=null && n>0);
  });
}
function collectAllKitRows(){
  const map=new Map();
  document.querySelectorAll('article.card .kit-row').forEach(row=>{
    if(!liveKitRow(row)) return;
    const key=row.dataset.who+':'+row.dataset.id;
    if(!map.has(key)) map.set(key,{who:row.dataset.who,id:+row.dataset.id,items:{}});
    map.get(key).items[row.dataset.tid]=rowChoice(row);
  });
  return [...map.values()];
}
function scheduleSaveOrder(){
  if(!TEAM.editing) return;
  clearTimeout(saveOrderTimer);
  saveOrderTimer=setTimeout(()=>{
    queueClothing(async ()=>{
      const out=await api({action:'save_all', csrf:TEAM.csrf, mode:'pending', rows:collectAllKitRows()});
      if(!out.ok) toast(out.error||'Opslaan mislukt');
    });
  }, 800);
}
function setPendingTypeWant(tid, on){
  document.querySelectorAll(`.kit-row[data-status="pending"][data-tid="${tid}"]`).forEach(row=>{
    const box=row.querySelector('.want-check');
    if(!box || box.disabled) return;
    box.checked=on;
    syncKitRow(row);
  });
}
function fillSizeSelect(sel, tid, current, who, id){
  const sizes=typeSizes(tid);
  sel.innerHTML='<option value="">Maat</option>'+sizes.map(s=>'<option value="'+String(s).replace(/"/g,'')+'">'+String(s)+'</option>').join('');
  sel.disabled=sizes.length<1;
  let pick=current||'';
  if(!pick && who==='player' && id && TEAM.suggest && TEAM.suggest[id]){
    pick=TEAM.suggest[id][tid]||'';
  }
  if(!pick && sizes.length===1) pick=sizes[0];
  if(pick && sizes.includes(pick)) sel.value=pick;
}
function assignPersonParts(){
  const person=(document.getElementById('assignPerson')?.value||'').split(':');
  return {who: person[0]||'', id: +person[1]||0};
}
function refreshAssignSize(){
  const {who,id}=assignPersonParts();
  fillSizeSelect(document.getElementById('assignSize'), +document.getElementById('assignType')?.value, '', who, id);
}
async function addItem(who, id, tid, size, mode){
  if(!who || !id || !tid){ toast('Kies een speler en een item'); return; }
  if(!size){ toast('Kies een maat'); return; }
  const out=await api({action:'add_item', csrf:TEAM.csrf, who, id, tid, size, mode: mode||'pending'});
  if(!out.ok){ toast(out.error||'Toevoegen mislukt'); return; }
  toast(mode==='active' ? 'In bezit gezet' : 'Item toegevoegd');
  location.reload();
}
async function addPackage(who, id, mode){
  if(!who || !id){ toast('Kies een speler'); return; }
  const out=await api({action:'add_package', csrf:TEAM.csrf, who, id, mode: mode||'pending'});
  if(!out.ok){ toast(out.error||'Pakket toewijzen mislukt'); return; }
  toast(out.saved ? ('Pakket toegevoegd ('+out.saved+')') : 'Niets nieuws toe te wijzen');
  location.reload();
}
document.getElementById('assignType')?.addEventListener('change', refreshAssignSize);
document.getElementById('assignPerson')?.addEventListener('change', refreshAssignSize);
async function submitAssign(mode){
  const {who,id}=assignPersonParts();
  await addItem(who, id, +document.getElementById('assignType')?.value, document.getElementById('assignSize')?.value, mode);
}
document.getElementById('assignPending')?.addEventListener('click', ()=>submitAssign('pending'));
document.getElementById('assignActive')?.addEventListener('click', ()=>submitAssign('active'));
document.getElementById('assignPackage')?.addEventListener('click', ()=>{
  const {who,id}=assignPersonParts();
  addPackage(who, id, 'pending');
});
document.getElementById('assignPackageAll')?.addEventListener('click', async ()=>{
  if(!confirm('Het huidige pakket op bestellen zetten voor alle spelers die die items nog niet hebben?')) return;
  const out=await api({action:'add_package_all', csrf:TEAM.csrf, mode:'pending'});
  if(!out.ok){ toast(out.error||'Mislukt'); return; }
  toast((out.people||0)+' spelers · '+(out.saved||0)+' items');
  location.reload();
});
document.querySelectorAll('.addrow .assign-type').forEach(sel=>{
  sel.addEventListener('change', ()=>{
    const row=sel.parentElement;
    const sizeSel=row.querySelector('.assign-size');
    fillSizeSelect(sizeSel, +sel.value, '', row.dataset.who, +row.dataset.id);
  });
});
document.querySelectorAll('.addrow .assign-add').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const row=btn.closest('.addrow');
    addItem(row.dataset.who, +row.dataset.id, +row.querySelector('.assign-type').value, row.querySelector('.assign-size').value, 'pending');
  });
});
document.querySelectorAll('.assign-package').forEach(btn=>{
  btn.addEventListener('click', ()=>addPackage(btn.dataset.who, +btn.dataset.id, 'pending'));
});
document.querySelectorAll('.item-del').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    if(!confirm('Dit item van de speler halen? Alleen Verwijderen wist het. Staat het in bezit, dan verdwijnt die regel ook.')) return;
    const who=btn.dataset.who;
    const id=+btn.dataset.id;
    const tid=+btn.dataset.tid;
    const row=btn.closest('.kit-row');
    if(row){
      row.dataset.gone='1';
      row.remove();
    }
    recalcOrder();
    setSaveState(who, id, 'Opslaan…', '');
    const out=await queueClothing(()=>api({action:'remove_item', csrf:TEAM.csrf, who, id, tid}));
    if(!out.ok){
      setSaveState(who, id, out.error||'Verwijderen mislukt', 'err');
      toast(out.error||'Verwijderen mislukt');
      location.reload();
      return;
    }
    setSaveState(who, id, 'Verwijderd', 'on');
    toast('Verwijderd');
  });
});
async function saveKit(payload){
  const out=await api(Object.assign({action:'save_kit', csrf:TEAM.csrf}, payload));
  if(!out.ok){ toast(out.error||'Niet opgeslagen'); return; }
  toast('Opgeslagen');
  location.reload();
}
document.querySelectorAll('.print-price').forEach(el=>{
  el.addEventListener('change', ()=>{
    const print={};
    document.querySelectorAll('.print-price').forEach(i=>{ print[i.dataset.print]=i.value; });
    saveKit({print});
  });
});
document.getElementById('packageChecks')?.addEventListener('change', ()=>{
  const pkg=checkedTypes(document.getElementById('packageChecks'));
  if(pkg.length<1){ toast('Kies minstens één pakket-item'); return; }
  saveKit({package: pkg});
});
document.getElementById('addTypeBtn')?.addEventListener('click', async ()=>{
  const prints={};
  document.querySelectorAll('#newPrints input').forEach(i=>{ prints[i.value]=i.checked?1:0; });
  const out=await api(Object.assign({
    action:'add_type', csrf:TEAM.csrf,
    display_name: document.getElementById('newDisplay')?.value||'',
    article_number: document.getElementById('newArticle')?.value||'',
    color: document.getElementById('newColor')?.value||'',
    brand: document.getElementById('newBrand')?.value||'Stanno',
    price_small: document.getElementById('newSmall')?.value||'',
    price_large: document.getElementById('newLarge')?.value||'',
    size_kind: document.getElementById('newKind')?.value||'body',
    sizes: document.getElementById('newSizes')?.value||'',
    order_group: document.getElementById('newGroup')?.value||'extra',
    print_place: document.getElementById('newPlace')?.value||''
  }, prints));
  if(!out.ok){ toast(out.error||'Toevoegen mislukt'); return; }
  toast('Artikel toegevoegd');
  location.reload();
});
async function saveTypeField(el){
  const id=+el.dataset.tid, field=el.dataset.field;
  if(!id || !field) return;
  const payload={action:'save_type', csrf:TEAM.csrf, id};
  payload[field]=el.value;
  const out=await api(payload);
  if(!out.ok){ toast(out.error||'Niet opgeslagen'); return; }
  toast('Opgeslagen');
  location.reload();
}
document.querySelectorAll('.money:not(.print-price), .cat-input[data-tid]').forEach(el=>{
  el.addEventListener('change', ()=>saveTypeField(el));
});
document.querySelectorAll('.cat-prints').forEach(box=>{
  box.addEventListener('change', async ()=>{
    const id=+box.dataset.tid;
    if(!id) return;
    const payload={action:'save_type', csrf:TEAM.csrf, id};
    box.querySelectorAll('input[data-print]').forEach(i=>{ payload[i.dataset.print]=i.checked?1:0; });
    const out=await api(payload);
    if(!out.ok){ toast(out.error||'Niet opgeslagen'); return; }
    toast('Bedrukking opgeslagen');
    location.reload();
  });
});
document.querySelectorAll('.cat-del[data-tid]').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    const id=+btn.dataset.tid;
    const name=btn.dataset.name||'dit artikel';
    if(!id) return;
    if(!confirm('“'+name+'” verwijderen uit de catalogus?')) return;
    const out=await api({action:'delete_type', csrf:TEAM.csrf, id});
    if(!out.ok){ toast(out.error||'Verwijderen mislukt'); return; }
    toast('Verwijderd');
    location.reload();
  });
});
async function cmsOk(out, okMsg){
  if(!out.ok){ toast(out.error||'Mislukt'); return false; }
  toast(okMsg||'Opgeslagen');
  location.reload();
  return true;
}
document.getElementById('seasonSave')?.addEventListener('click', async ()=>{
  const season=document.getElementById('seasonInput')?.value||'';
  await cmsOk(await api({action:'save_kit', csrf:TEAM.csrf, season}), 'Seizoen opgeslagen');
});
document.getElementById('backupBtn')?.addEventListener('click', ()=>{
  const btn=document.getElementById('backupBtn');
  if(btn) btn.disabled=true;
  location.href='save.php?action=backup&csrf='+encodeURIComponent(TEAM.csrf);
  setTimeout(()=>{ if(btn) btn.disabled=false; }, 2000);
});
document.getElementById('npAdd')?.addEventListener('click', async ()=>{
  await cmsOk(await api({
    action:'save_player', csrf:TEAM.csrf, id:0,
    first_name: document.getElementById('npFirst')?.value||'',
    last_name: document.getElementById('npLast')?.value||'',
    position: document.getElementById('npPos')?.value||'midfielder',
    jersey_number: document.getElementById('npJersey')?.value||'',
    is_guest: document.getElementById('npGuest')?.value==='1'?1:0,
    status:'active'
  }), 'Speler toegevoegd');
});
document.querySelectorAll('.cms-p-save').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    const row=btn.closest('tr');
    await cmsOk(await api({
      action:'save_player', csrf:TEAM.csrf, id:+btn.dataset.id,
      first_name: row.querySelector('.cms-p-first')?.value||'',
      last_name: row.querySelector('.cms-p-last')?.value||'',
      position: row.querySelector('.cms-p-pos')?.value||'midfielder',
      jersey_number: row.querySelector('.cms-p-jersey')?.value||'',
      is_guest: row.querySelector('.cms-p-guest')?.value==='1'?1:0
    }), 'Speler opgeslagen');
  });
});
document.querySelectorAll('.cms-p-archive').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    if(!confirm('Deze speler archiveren? Hij verdwijnt uit de actieve lijst.')) return;
    await cmsOk(await api({action:'set_player_status', csrf:TEAM.csrf, id:+btn.dataset.id, status:'inactive'}), 'Gearchiveerd');
  });
});
document.querySelectorAll('.cms-p-restore').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    await cmsOk(await api({action:'set_player_status', csrf:TEAM.csrf, id:+btn.dataset.id, status:'active'}), 'Teruggezet');
  });
});
document.getElementById('nsAdd')?.addEventListener('click', async ()=>{
  await cmsOk(await api({
    action:'save_staff', csrf:TEAM.csrf, id:0,
    first_name: document.getElementById('nsFirst')?.value||'',
    last_name: document.getElementById('nsLast')?.value||'',
    role: document.getElementById('nsRole')?.value||'staf',
    status:'active'
  }), 'Staflid toegevoegd');
});
document.querySelectorAll('.cms-s-save').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    const row=btn.closest('tr');
    await cmsOk(await api({
      action:'save_staff', csrf:TEAM.csrf, id:+btn.dataset.id,
      first_name: row.querySelector('.cms-s-first')?.value||'',
      last_name: row.querySelector('.cms-s-last')?.value||'',
      role: row.querySelector('.cms-s-role')?.value||'staf'
    }), 'Staf opgeslagen');
  });
});
document.querySelectorAll('.cms-s-archive').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    if(!confirm('Dit staflid archiveren?')) return;
    await cmsOk(await api({action:'set_staff_status', csrf:TEAM.csrf, id:+btn.dataset.id, status:'inactive'}), 'Gearchiveerd');
  });
});
document.querySelectorAll('.cms-s-restore').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    await cmsOk(await api({action:'set_staff_status', csrf:TEAM.csrf, id:+btn.dataset.id, status:'active'}), 'Teruggezet');
  });
});
document.querySelectorAll('.cms-t-restore').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    await cmsOk(await api({action:'restore_type', csrf:TEAM.csrf, id:+btn.dataset.id}), 'Artikel teruggezet');
  });
});
recalcOrder();
</script>
</body>
</html>
