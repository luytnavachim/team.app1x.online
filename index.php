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

$types = [];
$res = $mysqli->query('SELECT * FROM clothing_types ORDER BY id');
while ($row = $res->fetch_assoc()) {
    $types[(int) $row['id']] = $row;
}

$FIELD_CORE = [1, 4, 3, 7];
$KEEPER_CORE = [9, 4, 10];
$EXTRA = [11, 12];
$KEEPER_ONLY = [9, 10];
$STAFF_CORE = [11, 12];
$typeOrder = [1, 4, 3, 7, 11, 12, 9, 10];

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

function requiredIds(array $p, array $FIELD_CORE, array $KEEPER_CORE): array {
    return ($p['position'] ?? '') === 'goalkeeper' ? $KEEPER_CORE : $FIELD_CORE;
}

function parentChecksHtml(string $scope, array $choices, array $selected, int $playerId = 0, array $defaultIds = []): string {
    $html = '<div class="checks" data-parent-scope="'.h($scope).'" data-id="'.$playerId.'" data-default="'.h(implode(',', $defaultIds)).'">';
    foreach ($choices as $tid) {
        $on = in_array($tid, $selected, true) ? ' checked' : '';
        $html .= '<label><input type="checkbox" value="'.$tid.'"'.$on.'> '.h(shortTypeName($tid)).'</label>';
    }
    $html .= '</div>';
    return $html;
}

$portal = loadScoutPortal();
syncPlayersFromScout($mysqli, $players, $portal);

$active = array_values(array_filter($players, fn($p) => ($p['status'] ?? '') === 'active' && !(int) $p['is_guest']));

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
    $need = requiredIds($p, $FIELD_CORE, $KEEPER_CORE);
    $p['need'] = $need;
    $p['missing'] = [];
    $p['to_order'] = [];
    foreach ($need as $tid) {
        $it = itemFor($p, $tid);
        if (isIssued($it)) {
            continue;
        }
        if (isPendingItem($it)) {
            $p['to_order'][] = $tid;
        } else {
            $p['missing'][] = $tid;
        }
    }
    $p['miss'] = count($p['missing']) + count($p['to_order']);
    $p['complete'] = $p['miss'] === 0;
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

$complete = count(array_filter($active, fn($p) => $p['complete']));
$newPlayers = array_values(array_filter($active, fn($p) => count($p['missing']) > 0));
$orderPlayers = array_values(array_filter($active, fn($p) => count($p['to_order']) > 0));
$guestPlayers = array_values(array_filter($players, fn($p) => (int) ($p['is_guest'] ?? 0) === 1 && ($p['status'] ?? '') === 'active'));

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

$moos = null;
foreach ($active as $cand) {
    if (strcasecmp((string) $cand['first_name'], 'Moos') === 0) {
        $moos = $cand;
        break;
    }
}

$gaps = [];
foreach ($active as $p) {
    foreach ($p['to_order'] as $tid) {
        $t = $types[$tid] ?? null;
        $it = itemFor($p, $tid);
        if (!$t || !$it) {
            continue;
        }
        $sz = (string) ($it['size'] ?? '');
        $gaps[] = [
            'who' => fullName($p),
            'player' => $p,
            'tid' => $tid,
            'type' => $t,
            'size' => $sz,
            'price' => priceFor($t, $sz !== '' ? $sz : '164'),
        ];
    }
}
foreach ($staff as $s) {
    foreach ($STAFF_CORE as $tid) {
        $it = itemFor($s, $tid);
        if (isIssued($it)) {
            continue;
        }
        if (!isPendingItem($it)) {
            continue;
        }
        $t = $types[$tid] ?? null;
        if (!$t) {
            continue;
        }
        $sz = (string) ($it['size'] ?? '');
        $gaps[] = [
            'who' => fullName($s) . ' (staf)',
            'player' => $s,
            'tid' => $tid,
            'type' => $t,
            'size' => $sz,
            'price' => priceFor($t, $sz !== '' ? $sz : 'M'),
            'staff' => true,
        ];
    }
}

$orderGroups = [];
foreach ($gaps as $g) {
    $key = $g['tid'] . '|' . ($g['size'] !== '' ? $g['size'] : 'onbekend');
    if (!isset($orderGroups[$key])) {
        $orderGroups[$key] = [
            'tid' => $g['tid'],
            'type' => $g['type']['display_name'],
            'article' => $g['type']['article_number'] ?? '',
            'color' => $g['type']['color'] ?? '',
            'brand' => $g['type']['brand'] ?? '',
            'size' => $g['size'] !== '' ? $g['size'] : 'maat onbekend',
            'count' => 0,
            'names' => [],
            'price' => $g['price'],
        ];
    }
    $orderGroups[$key]['count']++;
    $orderGroups[$key]['names'][] = $g['who'];
}
usort($orderGroups, static fn($a, $b) => [$a['tid'], $a['size']] <=> [$b['tid'], $b['size']]);

$orderPieces = array_sum(array_column($orderGroups, 'count'));
$orderCost = 0.0;
$orderCostKnown = true;
foreach ($orderGroups as $g) {
    if ($g['price'] === null) {
        $orderCostKnown = false;
        continue;
    }
    $orderCost += $g['price'] * $g['count'];
}

$dist = [];
foreach ($typeOrder as $tid) {
    if (!isset($types[$tid])) {
        continue;
    }
    $dist[$tid] = [];
}
foreach ($active as $p) {
    foreach ($p['items'] as $tid => $list) {
        foreach ($list as $it) {
            $sz = trim((string) ($it['size'] ?? '')) ?: '?';
            $dist[$tid][$sz] = ($dist[$tid][$sz] ?? 0) + 1;
        }
    }
}
foreach ($dist as &$sizes) {
    ksort($sizes, SORT_NATURAL);
}
unset($sizes);

$heldValue = 0.0;
foreach ($active as $p) {
    foreach ($p['items'] as $tid => $list) {
        $t = $types[$tid] ?? null;
        if (!$t) {
            continue;
        }
        foreach ($list as $it) {
            $pr = priceFor($t, (string) ($it['size'] ?? ''));
            if ($pr !== null) {
                $heldValue += $pr;
            }
        }
    }
}

if (isset($_GET['csv']) && $_GET['csv'] === 'bestel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="kitroom-14-2-bestellijst.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Type', 'Maat', 'Artikel', 'Kleur', 'Merk', 'Aantal', 'Nodig voor', 'Richtprijs/stuk', 'Subtotaal'], ';');
    foreach ($orderGroups as $g) {
        $sub = $g['price'] !== null ? number_format($g['price'] * $g['count'], 2, ',', '.') : '';
        fputcsv($out, [
            $g['type'],
            $g['size'],
            $g['article'],
            $g['color'],
            $g['brand'],
            $g['count'],
            implode(', ', $g['names']),
            $g['price'] !== null ? number_format($g['price'], 2, ',', '.') : '',
            $sub,
        ], ';');
    }
    fputcsv($out, [], ';');
    fputcsv($out, ['Totaal stuks', $orderPieces, '', '', '', '', '', '', number_format($orderCost, 2, ',', '.')], ';');
    fclose($out);
    exit;
}

$byLine = [];
foreach ($posOrder as $pos) {
    $byLine[$pos] = array_values(array_filter($active, fn($p) => ($p['position'] ?? '') === $pos));
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
<meta name="theme-color" content="#0F1216">
<script>
(function(){
  var t='dark';
  try { t=localStorage.getItem('kitroom-theme')||'dark'; } catch(e) {}
  if(t!=='light') t='dark';
  document.documentElement.setAttribute('data-theme', t);
  document.documentElement.style.colorScheme=t;
})();
</script>
<link rel="icon" href="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%22478.658%20474.658%201090.5839999999998%201090.5839999999998%22%3E%3Crect%20x%3D%22478.658%22%20y%3D%22474.658%22%20width%3D%221090.5839999999998%22%20height%3D%221090.5839999999998%22%20rx%3D%22239.92847999999995%22%20fill%3D%22%2312151A%22%2F%3E%3Cpath%20transform%3D%22translate(0%2C0)%22%20fill%3D%22%23C8FF3D%22%20d%3D%22M%20921.182%20786.705%20C%20921.088%20748.191%20924.254%20721.832%20952.882%20692.548%20C%201015.98%20628.006%201122.48%20672.735%201124.65%20761.167%20C%201125.5%20795.816%201102.43%20832.056%201081.81%20859.102%20C%201173.63%20909.241%201268.41%20963.174%201360.93%201011.13%20L%201361%201131.03%20C%201319.03%201131.1%201275.61%201130.63%201233.75%201131.29%20L%201233.19%201375.08%20L%201049.35%201375.98%20C%201012.25%201376.33%20975.285%201377.55%20938.134%201377.03%20C%20917.37%201362.43%20883.662%201343.49%20865.937%201326.65%20C%20904.772%201326.82%20943.608%201326.66%20982.44%201326.19%20L%201181.84%201325.99%20L%201181.77%201081.01%20C%201223.2%201081.15%201266.55%201081.59%201307.88%201080.71%20L%201307.78%201041.6%20C%201291.08%201031.4%201272.57%201022.84%201255.7%201013.2%20C%201209.38%20986.726%201158.4%20962.557%201112.55%20935.731%20L%201022.94%20987.354%20C%20993.336%20970.865%20960.046%20953.378%20929.916%20937.92%20C%20920.677%20942.578%20898.06%20955.722%20889.447%20958.678%20C%20884.065%20957.576%20873.468%20958.363%20868.546%20958.12%20C%20855.177%20957.462%20795.501%20959.764%20787.18%20956.615%20C%20823.019%20935.682%20860.332%20916.939%20896.692%20896.892%20C%20908.282%20890.501%20919.978%20884.554%20932.037%20879.089%20C%20947.323%20886.595%20963.46%20896.026%20978.428%20904.412%20C%20992.956%20912.551%201009.08%20921.18%201023.02%20930.015%20C%201031.8%20923.173%201052.8%20912.06%201063.31%20905.352%20C%201044.94%20894.018%201024.84%20883.287%201005.8%20873.072%20C%201027.7%20847.761%201051.43%20821.947%201065.94%20791.627%20C%201076.9%20768.732%201076.85%20743.614%201055.63%20726.522%20C%201019.55%20697.466%20974.242%20726.414%20971.812%20768.411%20C%20971.469%20774.337%20971.873%20780.788%20972.045%20786.715%20C%20955.299%20786.729%20937.87%20786.998%20921.182%20786.705%20z%22%2F%3E%3Cpath%20transform%3D%22translate(0%2C0)%22%20fill%3D%22%23C8FF3D%22%20d%3D%22M%20687.097%201011.14%20L%20787.18%20956.615%20C%20795.501%20959.764%20855.177%20957.462%20868.546%20958.12%20C%20873.468%20958.363%20884.065%20957.576%20889.447%20958.678%20C%20873.458%20968.871%20851.83%20980.353%20835.021%20989.785%20C%20803.509%201007.51%20771.807%201024.9%20739.921%201041.95%20L%20739.755%201081.04%20L%20865.102%201081.01%20C%20865.082%201106.56%20864.201%201324.24%20865.937%201326.65%20C%20883.662%201343.49%20917.37%201362.43%20938.134%201377.03%20L%20812.355%201377.16%20L%20812.749%201132.92%20L%20687.027%201133.03%20L%20687.097%201011.14%20z%22%2F%3E%3C%2Fsvg%3E">
<meta name="description" content="Kleding- en selectieoverzicht voor 14-2.">
<style>
:root,html[data-theme="dark"]{
  --bg:#0F1216; --surface:#171B21; --surface2:#1D222A; --raise:#232A33;
  --line:#272E38; --line2:#333C48;
  --ink:#F2F4F7; --muted:#8B95A4; --dim:#5D6773;
  --accent:#C8FF3D; --accent-dim:#A9DC2A; --on-accent:#12151A; --accent-text:#C8FF3D;
  --green:#3DDC91; --greenbg:rgba(61,220,145,.13);
  --miss:#FF6B6B; --missbg:rgba(255,107,107,.13);
  --warn:#FFD166; --warnbg:rgba(255,197,61,.10);
  --na:#5D6773; --nabg:rgba(255,255,255,.035);
  --glow-a:rgba(200,255,61,.10); --glow-b:rgba(61,220,145,.05);
  --nav-fade:rgba(15,18,22,0);
  --featured:linear-gradient(140deg,#1B2129,#12161B 62%);
  --mark-bg:linear-gradient(155deg,var(--surface2),#12161B);
  --overlay:rgba(8,10,13,.72);
  --hover:rgba(255,255,255,.02);
  --editbar-bg:rgba(200,255,61,.06); --editbar-ink:#D6DEE8;
  --pill-ink:#C3CCD8;
  --modal-shadow:0 24px 60px rgba(0,0,0,.55);
  --r:14px; --r-lg:20px;
}
html[data-theme="light"]{
  --bg:#F3F5F0; --surface:#FFFFFF; --surface2:#F7F8F4; --raise:#EEF1E8;
  --line:#E1E6D8; --line2:#C9D0BC;
  --ink:#14181D; --muted:#5A6470; --dim:#7A8490;
  --accent:#C8FF3D; --accent-dim:#9ACC20; --on-accent:#12151A; --accent-text:#4F6C00;
  --green:#0F8F5C; --greenbg:rgba(15,143,92,.12);
  --miss:#C62828; --missbg:rgba(198,40,40,.10);
  --warn:#A15C00; --warnbg:rgba(161,92,0,.12);
  --na:#7A8490; --nabg:rgba(20,24,29,.04);
  --glow-a:rgba(200,255,61,.22); --glow-b:rgba(15,143,92,.07);
  --nav-fade:rgba(243,245,240,0);
  --featured:linear-gradient(140deg,#FFFFFF,#F3F5F0 62%);
  --mark-bg:linear-gradient(155deg,#FFFFFF,#EEF1E8);
  --overlay:rgba(20,24,29,.42);
  --hover:rgba(20,24,29,.03);
  --editbar-bg:rgba(200,255,61,.28); --editbar-ink:#14181D;
  --pill-ink:#3D454E;
  --modal-shadow:0 24px 60px rgba(20,24,29,.16);
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
.wrap{max-width:1120px;margin:auto;padding:18px 14px 72px}
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
.mark svg{width:100%;height:100%;display:block}
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
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px}
@media(max-width:760px){.stats{grid-template-columns:repeat(2,1fr)}}
.stat{background:var(--surface);border:1px solid var(--line);border-radius:var(--r);padding:13px 14px}
.stat b{display:block;font-size:27px;line-height:1.05;font-weight:800;letter-spacing:-1px}
.stat span{display:block;margin-top:4px;font-size:11px;color:var(--muted);font-weight:600;letter-spacing:.2px}
.stat.accent b{color:var(--accent-text)}
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
.section .sub{margin:0 0 14px;font-size:12.5px;color:var(--muted);font-weight:500}
.section .sub b{color:var(--ink);font-weight:700}
.legend{display:flex;gap:14px;flex-wrap:wrap;font-size:11px;font-weight:700;color:var(--muted);margin-bottom:12px}
.dot{display:inline-block;width:9px;height:9px;border-radius:3px;margin-right:5px;vertical-align:middle}
.line{margin:18px 0 9px;font-size:11px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:var(--dim)}
.line:first-child{margin-top:0}

/* ---------- cards ---------- */
.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:11px}
.card{
  border:1px solid var(--line);border-radius:var(--r);padding:13px;
  background:var(--surface2);transition:border-color .15s;
}
.card:hover{border-color:var(--line2)}
.card.moos{border-color:rgba(200,255,61,.42);box-shadow:0 0 0 1px rgba(200,255,61,.12)}
.card.gap{border-left:3px solid rgba(255,107,107,.45)}
.who{display:flex;justify-content:space-between;gap:8px;align-items:baseline}
.who b{font-size:15.5px;font-weight:700;letter-spacing:-.2px}
.nr{font-size:11.5px;font-weight:800;color:var(--dim)}
.meta{font-size:11.5px;color:var(--muted);font-weight:600;margin:4px 0 10px}
.kit{display:grid;gap:5px}
.row{display:flex;justify-content:space-between;gap:8px;align-items:center;font-size:12.5px;font-weight:600;padding:7px 10px;border-radius:9px;background:var(--nabg);color:var(--muted)}
.row.ok{background:var(--greenbg);color:var(--green)}
.row.no{background:var(--missbg);color:var(--miss)}
.row.wait{background:var(--warnbg);color:var(--warn)}
.row.na{background:var(--nabg);color:var(--na)}
.row.extra{background:var(--nabg);color:var(--muted)}
.row>span:last-child,.row>select{flex:0 0 auto;white-space:nowrap}
.row>span:first-child{min-width:0}

/* ---------- tables ---------- */
.tablewrap{overflow-x:auto;border:1px solid var(--line);border-radius:var(--r);background:var(--surface2)}
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

/* ---------- filters ---------- */
.filters{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:12px}
.filters button{
  border:1px solid var(--line);background:var(--surface2);color:var(--ink);
  border-radius:999px;padding:8px 13px;font-weight:700;font-size:12.5px;cursor:pointer;
  font-family:inherit;transition:background .15s,border-color .15s,color .15s;
}
.filters button:hover{border-color:var(--line2)}
.filters button.on{background:var(--accent);color:var(--on-accent);border-color:var(--accent);font-weight:800}
.hidden{display:none !important}

/* ---------- bars ---------- */
.barwrap{display:grid;gap:7px}
.barrow{display:grid;grid-template-columns:96px 1fr 34px;gap:10px;align-items:center;font-size:12.5px;font-weight:600;color:var(--muted)}
.barrow span:last-child{text-align:right;color:var(--ink);font-weight:700}
.bar{height:9px;background:var(--raise);border-radius:99px;overflow:hidden}
.bar i{display:block;height:100%;background:linear-gradient(90deg,var(--accent-dim),var(--accent));border-radius:99px}

.chip{display:inline-flex;gap:6px;align-items:center;background:var(--raise);border-radius:999px;padding:5px 10px;font-size:11px;font-weight:700;margin:0 6px 6px 0}
.actions{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 0}
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

@media(max-width:520px){
  .wrap{padding:14px 12px 64px}
  .stat b{font-size:24px}
  .cards{grid-template-columns:1fr}
  .barrow{grid-template-columns:80px 1fr 30px}
  .top{flex-wrap:wrap}
}

/* ---------- print: terug naar licht ---------- */
@media print{
  :root{--bg:#fff;--surface:#fff;--surface2:#fff;--raise:#f4f4f5;--line:#d4d4d8;--line2:#a1a1aa;
        --ink:#111;--muted:#52525b;--dim:#71717a;--accent:#111;--on-accent:#fff;
        --green:#15803d;--greenbg:#dcfce7;--miss:#b91c1c;--missbg:#fee2e2;
        --warn:#a16207;--warnbg:#fef3c7;--na:#71717a;--nabg:#fafafa}
  html{color-scheme:light}
  body{background:#fff;color:#111}
  .navwrap,.filters,.actions,.note,.toast,.modal,.theme-switch{display:none !important}
  .section,.featured,.card{break-inside:avoid;border:1px solid #d4d4d8}
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
      <div class="mark" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="623.0 619.0 801.9 801.9" ><path transform="translate(0,0)" fill="#C8FF3D" d="M 921.182 786.705 C 921.088 748.191 924.254 721.832 952.882 692.548 C 1015.98 628.006 1122.48 672.735 1124.65 761.167 C 1125.5 795.816 1102.43 832.056 1081.81 859.102 C 1173.63 909.241 1268.41 963.174 1360.93 1011.13 L 1361 1131.03 C 1319.03 1131.1 1275.61 1130.63 1233.75 1131.29 L 1233.19 1375.08 L 1049.35 1375.98 C 1012.25 1376.33 975.285 1377.55 938.134 1377.03 C 917.37 1362.43 883.662 1343.49 865.937 1326.65 C 904.772 1326.82 943.608 1326.66 982.44 1326.19 L 1181.84 1325.99 L 1181.77 1081.01 C 1223.2 1081.15 1266.55 1081.59 1307.88 1080.71 L 1307.78 1041.6 C 1291.08 1031.4 1272.57 1022.84 1255.7 1013.2 C 1209.38 986.726 1158.4 962.557 1112.55 935.731 L 1022.94 987.354 C 993.336 970.865 960.046 953.378 929.916 937.92 C 920.677 942.578 898.06 955.722 889.447 958.678 C 884.065 957.576 873.468 958.363 868.546 958.12 C 855.177 957.462 795.501 959.764 787.18 956.615 C 823.019 935.682 860.332 916.939 896.692 896.892 C 908.282 890.501 919.978 884.554 932.037 879.089 C 947.323 886.595 963.46 896.026 978.428 904.412 C 992.956 912.551 1009.08 921.18 1023.02 930.015 C 1031.8 923.173 1052.8 912.06 1063.31 905.352 C 1044.94 894.018 1024.84 883.287 1005.8 873.072 C 1027.7 847.761 1051.43 821.947 1065.94 791.627 C 1076.9 768.732 1076.85 743.614 1055.63 726.522 C 1019.55 697.466 974.242 726.414 971.812 768.411 C 971.469 774.337 971.873 780.788 972.045 786.715 C 955.299 786.729 937.87 786.998 921.182 786.705 z"/><path transform="translate(0,0)" fill="#C8FF3D" d="M 687.097 1011.14 L 787.18 956.615 C 795.501 959.764 855.177 957.462 868.546 958.12 C 873.468 958.363 884.065 957.576 889.447 958.678 C 873.458 968.871 851.83 980.353 835.021 989.785 C 803.509 1007.51 771.807 1024.9 739.921 1041.95 L 739.755 1081.04 L 865.102 1081.01 C 865.082 1106.56 864.201 1324.24 865.937 1326.65 C 883.662 1343.49 917.37 1362.43 938.134 1377.03 L 812.355 1377.16 L 812.749 1132.92 L 687.027 1133.03 L 687.097 1011.14 z"/></svg></div>
      <div><b>Kitroom</b><small>14-2 · kleding &amp; selectie</small></div>
    </div>
    <div class="top-right">
      <div class="theme-switch" role="group" aria-label="Thema">
        <button type="button" data-theme-set="dark" aria-pressed="true">Donker</button>
        <button type="button" data-theme-set="light" aria-pressed="false">Licht</button>
      </div>
      <div class="badge">26/27</div>
    </div>
  </header>

  <div class="navwrap">
  <nav class="nav">
    <a href="#bestel">Bestellijst</a>
    <a href="#opmeten">Opmeten</a>
    <a href="#ouders">Ouderlinks</a>
    <a href="#spelers">Spelers</a>
    <a href="#maten">Maatverdeling</a>
    <a href="#matrix">Matrix</a>
    <a href="#staf">Staf</a>
    <a href="#catalogus">Catalogus</a>
    <a href="?csv=bestel">CSV</a>
    <?php if ($canEdit): ?>
      <a class="dark" href="#opmeten">Invullen</a>
      <button type="button" class="btn" id="saveAllBtn">Alles opslaan</button>
      <button type="button" class="btn" id="logoutBtn">Klaar</button>
    <?php else: ?>
      <button type="button" class="btn dark" id="editBtn">Maten invullen</button>
    <?php endif; ?>
  </nav>
  </div>

  <?php if ($canEdit): ?>
  <div class="note editbar">Bewerkmodus aan. Seizoen 26/27: iedereen krijgt een nieuwe basisset. Pas maten aan en druk op <b>Opslaan</b>. Pas als de set binnen is: <b>In bezit</b>.</div>
  <?php else: ?>
  <div class="note">Seizoen 26/27: voor iedereen een nieuwe set (shirt, broek, sokken, grip; keepers: keepershirt, broek, keepersokken). Posities komen live uit scout.app1x.online. <b>Maten invullen</b> met pincode. Daar kies je ook wat ouders te zien krijgen.</div>
  <?php endif; ?>

  <div class="stats">
    <div class="stat"><b><?= count($active) ?></b><span>spelers in 14-2</span></div>
    <div class="stat accent">
      <b><?= $complete ?>/<?= count($active) ?></b><span>set ontvangen</span>
      <div class="progress"><i style="width:<?= count($active) ? round(100 * $complete / count($active)) : 0 ?>%"></i></div>
    </div>
    <div class="stat"><b><?= count($newPlayers) ?></b><span>nog opmeten</span></div>
    <div class="stat"><b><?= count($orderPlayers) ?></b><span>nieuwe set bestellen</span></div>
  </div>

  <?php if ($moos): ?>
  <div class="featured">
    <h2>Moos van Tuyl<?= $moos['jersey_number'] ? ' · #'.h($moos['jersey_number']) : '' ?></h2>
    <p><?= h($moos['scout_type'] ?: ($posLabel[$moos['position']] ?? '')) ?><?= $moos['scout_pos'] ? ' · '.$moos['scout_pos'] : '' ?><?= $moos['voet'] ? ' · '.$voetLabel($moos['voet']) : '' ?> · shirt/broek 164 · sokken 36-40</p>
    <div class="pills">
      <?php foreach ($moos['need'] as $tid):
        $it = itemFor($moos, $tid);
        $t = $types[$tid] ?? ['display_name' => (string) $tid];
      ?>
        <?php if (isIssued($it)): ?>
          <span class="pill ok"><?= h($t['display_name']) ?> · <?= h($it['size']) ?></span>
        <?php elseif ($it): ?>
          <span class="pill"><?= h($t['display_name']) ?> · <?= h($it['size']) ?> · bestellen</span>
        <?php else: ?>
          <span class="pill no"><?= h($t['display_name']) ?> ontbreekt</span>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php foreach ($EXTRA as $tid):
        $it = itemFor($moos, $tid);
        $t = $types[$tid] ?? ['display_name' => (string) $tid];
      ?>
        <span class="pill"><?= h($t['display_name']) ?>: <?= $it ? h($it['size']) : 'niet uitgegeven' ?></span>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="section" id="bestel">
    <h3>Bestellijst</h3>
    <p class="sub"><?= $orderGroups ? ((int) $orderPieces.' stuks · nieuwe set voor het hele team · richtprijs '.euro($orderCost)) : 'Nog niets om te bestellen.' ?></p>
    <div class="actions">
      <a class="btn dark" href="?csv=bestel">Download CSV</a>
      <a class="btn" href="javascript:window.print()">Print</a>
    </div>
    <?php if (!$orderGroups): ?>
      <p class="sub">Niets open op de bestellijst.</p>
    <?php else: ?>
    <div class="tablewrap" style="margin-top:10px">
      <table>
        <thead>
          <tr>
            <th class="name">Type</th>
            <th>Maat</th>
            <th>Artikel</th>
            <th>Kleur</th>
            <th>Aantal</th>
            <th>Stuk</th>
            <th>Subtotaal</th>
            <th class="name">Voor</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orderGroups as $g): ?>
          <tr>
            <td class="name"><?= h($g['type']) ?></td>
            <td class="<?= $g['size'] === 'maat onbekend' ? 'no' : 'ok' ?>"><?= h($g['size']) ?></td>
            <td><?= h($g['article']) ?></td>
            <td><?= h($g['color']) ?></td>
            <td><b><?= (int) $g['count'] ?></b></td>
            <td><?= euro($g['price']) ?></td>
            <td><?= $g['price'] !== null ? euro($g['price'] * $g['count']) : '—' ?></td>
            <td class="left"><?= h(implode(', ', $g['names'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <tr>
            <td class="name">Totaal</td>
            <td></td><td></td><td></td>
            <td><b><?= (int) $orderPieces ?></b></td>
            <td></td>
            <td><b><?= euro($orderCost) ?></b></td>
            <td class="left"></td>
          </tr>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <div class="section" id="opmeten">
    <h3>Opmeetlijst</h3>
    <p class="sub"><?php if (!$newPlayers): ?>Alle spelers hebben een maat; die staat op de bestellijst voor de nieuwe set.<?php else: ?>Deze <?= count($newPlayers) ?> spelers hebben nog geen maat. Shirt/broek in 14-2 is nu vooral <b>164</b> en <b>176</b>, sokken 36-40 of 41-44.<?= $canEdit ? ' Vul de maten in bij hun kaart hieronder en druk op Opslaan.' : '' ?><?php endif; ?></p>
    <?php if ($newPlayers): ?>
    <div class="tablewrap">
      <table>
        <thead>
          <tr>
            <th class="name">Speler</th>
            <th>Lijn</th>
            <th>Scout</th>
            <th>Voet</th>
            <th>Open</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($newPlayers as $p): ?>
          <tr>
            <td class="name"><?= h(fullName($p)) ?></td>
            <td><?= h($posLabel[$p['position']] ?? $p['position']) ?></td>
            <td><?= h(trim(($p['scout_pos'] ? $p['scout_pos'].' · ' : '').($p['scout_type'] ?? '')) ?: '—') ?></td>
            <td><?= h($voetLabel((string) $p['voet']) ?: '—') ?></td>
            <td class="left"><?= h(implode(', ', array_map(fn($tid) => $types[$tid]['display_name'] ?? (string) $tid, $p['missing']))) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <div class="section" id="ouders">
    <h3>Ouderlinks</h3>
    <?php if (!$canEdit): ?>
    <p class="sub">Met pincode kun je kiezen <b>wat</b> ouders invullen (shirt, broek, sokken, …) en per speler een link sturen. Open <b>Maten invullen</b> om dat in te stellen.</p>
    <?php else:
      $parentForm = loadParentFormSettings();
    ?>
    <p class="sub">Kies eerst wat ouders te zien krijgen. Daarna kopieer je de link of stuur je hem via WhatsApp. Een nieuwe link maakt de oude ongeldig.</p>
    <div class="parent-defaults" id="parentDefaults">
      <h4>Wat ouders invullen</h4>
      <p class="hint">Dit is de standaard. Per speler kun je hieronder afwijken. Polo en zip staan uit, tenzij je ze aanzet.</p>
      <div class="line">Veldspelers</div>
      <?= parentChecksHtml('field', parentTypeChoices('field'), $parentForm['field']) ?>
      <div class="line">Keepers</div>
      <?= parentChecksHtml('keeper', parentTypeChoices('keeper'), $parentForm['keeper']) ?>
      <label class="hint" for="parentNote">Tekst bovenaan het ouderformulier (optioneel)</label>
      <textarea class="note-input" id="parentNote" maxlength="280" placeholder="Bijvoorbeeld: alleen de nieuwe set voor 26/27, geen polo."><?= h($parentForm['note']) ?></textarea>
    </div>
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
          <tr>
            <td class="name"><?= h($pl['name']) ?><?php if ($pl['custom']): ?><div class="tiny">aangepast</div><?php endif; ?></td>
            <td><?= $savedAt !== '' ? 'ingevuld '.$savedAt : ((int) $pl['missing'] > 0 ? (int) $pl['missing'].' open' : 'nog niet') ?></td>
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
    <?php endif; ?>
  </div>

  <div class="section" id="spelers">
    <h3>Spelers</h3>
    <p class="sub">Gegroepeerd op scoutingpositie (CAM, CDM, K, …). Wijziging in scout.app1x.online komt hier terug.</p>
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
      <span><i class="dot" style="background:var(--green)"></i>Geregeld</span>
      <span><i class="dot" style="background:var(--miss)"></i>Ontbreekt</span>
      <span><i class="dot" style="background:var(--warn)"></i>Besteld</span>
      <span><i class="dot" style="background:var(--na)"></i>N.v.t. / extra</span>
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
          $meta[] = $p['complete'] ? 'set ontvangen' : ($p['to_order'] ? 'nieuwe set bestellen' : $p['miss'].' open');
        ?>
        <article class="card<?= $isMoos ? ' moos' : '' ?><?= $p['complete'] ? '' : ' gap' ?>"
                 data-pos="<?= h($p['position'] ?? '') ?>">
          <div class="who">
            <b><?= h(fullName($p)) ?></b>
            <span class="nr"><?= $p['jersey_number'] ? '#'.h($p['jersey_number']) : '' ?></span>
          </div>
          <div class="meta"><?= h(implode(' · ', array_filter($meta))) ?></div>
          <div class="kit">
            <?php foreach ($typeOrder as $tid):
              if (!isset($types[$tid])) continue;
              $t = $types[$tid];
              $it = itemFor($p, $tid);
              $isReq = in_array($tid, $p['need'], true);
              $isKeeperOnly = in_array($tid, $KEEPER_ONLY, true) && ($p['position'] ?? '') !== 'goalkeeper';
              $pending = isPendingItem($it) && $isReq;
              $cls = $it ? ($pending ? 'wait' : ($isReq ? 'ok' : 'extra')) : ($isKeeperOnly ? 'na' : ($isReq ? 'no' : 'extra'));
              $val = $it ? $it['size'] : ($isKeeperOnly ? 'n.v.t.' : ($isReq ? 'ontbreekt' : '—'));
            ?>
            <div class="row <?= $cls ?>">
              <span><?= h($t['display_name']) ?><?= $pending ? ' · bestellen' : '' ?></span>
              <?php if ($canEdit && !$isKeeperOnly): ?>
                <?= sizeSelect($tid, (string) ($it['size'] ?? ''), 'player', (int) $p['id']) ?>
              <?php else: ?>
                <span><?= h($val) ?></span>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if ($canEdit): ?>
              <div class="actions">
                <button type="button" class="btn dark save-one" data-who="player" data-id="<?= (int) $p['id'] ?>">Opslaan</button>
                <button type="button" class="btn save-one" data-who="player" data-id="<?= (int) $p['id'] ?>" data-mode="active">In bezit</button>
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
  </div>

  <div class="section" id="maten">
    <h3>Maatverdeling</h3>
    <p class="sub">Huidige maten in de selectie (ijkpunt of iemand is gegroeid). Richtprijs van de bestelling staat bovenaan.</p>
    <?php foreach ($typeOrder as $tid):
      if (!isset($types[$tid]) || empty($dist[$tid])) continue;
      $max = max($dist[$tid]);
    ?>
      <div class="line"><?= h($types[$tid]['display_name']) ?></div>
      <div class="barwrap">
        <?php foreach ($dist[$tid] as $sz => $n): ?>
          <div class="barrow">
            <span><?= h((string) $sz) ?></span>
            <div class="bar"><i style="width:<?= $max ? round(100 * $n / $max) : 0 ?>%"></i></div>
            <span><?= (int) $n ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="section" id="matrix">
    <h3>Matrix · maat per type</h3>
    <p class="sub">Rood/oranje = nog bestellen. Groen = set al ontvangen.</p>
    <div class="tablewrap">
      <table>
        <thead>
          <tr>
            <th class="name">Speler</th>
            <th>Scout</th>
            <?php foreach ($typeOrder as $tid): if (!isset($types[$tid])) continue; ?>
              <th><?= h($types[$tid]['display_name']) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($active as $p): ?>
          <tr>
            <td class="name"><?= h(fullName($p)) ?></td>
            <td><?= h($p['scout_pos'] ?: '—') ?></td>
            <?php foreach ($typeOrder as $tid):
              if (!isset($types[$tid])) continue;
              $it = itemFor($p, $tid);
              $isReq = in_array($tid, $p['need'], true);
              $isKeeperOnly = in_array($tid, $KEEPER_ONLY, true) && ($p['position'] ?? '') !== 'goalkeeper';
              if ($it) {
                $pending = isPendingItem($it);
                $cls = $pending ? 'wait' : 'ok';
                $val = $it['size'].($pending ? '*' : '');
              }
              elseif ($isKeeperOnly) { $cls='na'; $val='·'; }
              elseif ($isReq) { $cls='no'; $val='—'; }
              else { $cls='na'; $val=''; }
            ?>
              <td class="<?= $cls ?>"><?= h((string) $val) ?></td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="section" id="staf">
    <h3>Staf</h3>
    <p class="sub">Polo en quarter zip uit de 13-2 administratie.<?= $canEdit ? ' Vul ontbrekende maten in en sla op.' : '' ?></p>
    <div class="cards">
      <?php foreach ($staff as $s): ?>
      <article class="card">
        <div class="who"><b><?= h(fullName($s)) ?></b><span class="nr"><?= h($s['role']) ?></span></div>
        <div class="kit">
          <?php foreach ($STAFF_CORE as $tid):
              $it = itemFor($s, $tid);
              $pending = isPendingItem($it);
              $cls = isIssued($it) ? 'ok' : ($pending ? 'wait' : 'no');
          ?>
            <div class="row <?= $cls ?>">
              <span><?= h($types[$tid]['display_name'] ?? '') ?><?= $pending ? ' · bestellen' : '' ?></span>
              <?php if ($canEdit): ?>
                <?= sizeSelect($tid, (string) ($it['size'] ?? ''), 'staff', (int) $s['id']) ?>
              <?php else: ?>
                <span><?= h($it['size'] ?? 'ontbreekt') ?></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          <?php if ($canEdit): ?>
            <div class="actions">
              <button type="button" class="btn dark save-one" data-who="staff" data-id="<?= (int) $s['id'] ?>">Opslaan</button>
              <button type="button" class="btn save-one" data-who="staff" data-id="<?= (int) $s['id'] ?>" data-mode="active">In bezit</button>
            </div>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="section" id="catalogus">
    <h3>Catalogus · Stanno</h3>
    <p class="sub">Artikelnummers en prijzen uit de oude 13-2 kledingadministratie. Jeugdmaten (152/164/36-40) gebruiken de kleine prijs.</p>
    <div class="tablewrap">
      <table>
        <thead>
          <tr>
            <th class="name">Type</th>
            <th>Artikel</th>
            <th>Kleur</th>
            <th>Merk</th>
            <th>Klein</th>
            <th>Groot / standaard</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($types as $t): ?>
          <tr>
            <td class="name"><?= h($t['display_name']) ?></td>
            <td><?= h($t['article_number']) ?></td>
            <td><?= h($t['color']) ?></td>
            <td><?= h($t['brand']) ?></td>
            <td><?= euro(isset($t['price_small']) && $t['price_small'] !== '' && $t['price_small'] !== null ? (float) $t['price_small'] : null) ?></td>
            <td><?= euro(isset($t['price_large']) && $t['price_large'] !== '' && $t['price_large'] !== null ? (float) $t['price_large'] : (isset($t['price']) && $t['price'] !== '' && $t['price'] !== null ? (float) $t['price'] : null)) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<div id="pinModal" class="modal hidden">
  <form class="modalbox" id="pinForm">
    <h3>Maten invullen</h3>
    <p>Pincode van de 14-2 teamapp</p>
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
    if(meta) meta.setAttribute('content', next==='light' ? '#F3F5F0' : '#0F1216');
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
const TEAM = { csrf: <?= json_encode($csrf) ?>, editing: <?= $canEdit ? 'true' : 'false' ?> };
function toast(msg){
  const el=document.getElementById('toast');
  el.textContent=msg;
  el.classList.add('show');
  clearTimeout(toast._t);
  toast._t=setTimeout(()=>el.classList.remove('show'), 2200);
}
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
document.addEventListener('change', e=>{
  const sel=e.target.closest?.('.size-select');
  if(!sel) return;
  const tid=sel.dataset.tid, who=sel.dataset.who, id=sel.dataset.id;
  document.querySelectorAll(`.size-select[data-who="${who}"][data-id="${id}"][data-copy-from="${tid}"]`).forEach(t=>{
    if(!t.value) t.value=sel.value;
  });
});
function itemsFor(who, id, root){
  const items={};
  (root||document).querySelectorAll(`.size-select[data-who="${who}"][data-id="${id}"]`).forEach(s=>{
    items[s.dataset.tid]=s.value;
  });
  return items;
}
async function saveRow(who, id, mode, root){
  const out=await api({action:'save', csrf:TEAM.csrf, who, id, mode: mode||'pending', items: itemsFor(who, id, root)});
  if(!out.ok){ toast(out.error||'Opslaan mislukt'); return; }
  toast('Opgeslagen');
  location.reload();
}
document.querySelectorAll('.save-one').forEach(btn=>{
  btn.onclick=()=>saveRow(btn.dataset.who, +btn.dataset.id, btn.dataset.mode||'pending', btn.closest('article, tr'));
});
document.getElementById('saveAllBtn')?.addEventListener('click', async ()=>{
  const map=new Map();
  document.querySelectorAll('article.card .size-select').forEach(s=>{
    const key=s.dataset.who+':'+s.dataset.id;
    if(!map.has(key)) map.set(key,{who:s.dataset.who,id:+s.dataset.id,items:{}});
    map.get(key).items[s.dataset.tid]=s.value;
  });
  const out=await api({action:'save_all', csrf:TEAM.csrf, mode:'pending', rows:[...map.values()]});
  if(!out.ok){ toast(out.error||'Opslaan mislukt'); return; }
  toast('Alles opgeslagen');
  location.reload();
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
    if(!confirm('De oude ouderlink stopt dan met werken. Nieuwe link maken?')) return;
    const out=await api({action:'parent_rotate', csrf:TEAM.csrf, id:+btn.dataset.id});
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
  if(!fieldEl||!keeperEl) return;
  const field=checkedTypes(fieldEl);
  const keeper=checkedTypes(keeperEl);
  if(field.length<1||keeper.length<1){ toast('Kies minstens één item'); return; }
  const note=document.getElementById('parentNote')?.value||'';
  const out=await api({action:'parent_form', csrf:TEAM.csrf, scope:'defaults', field, keeper, note});
  if(!out.ok){ toast(out.error||'Mislukt'); return; }
  toast('Ouderformulier opgeslagen');
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
document.querySelectorAll('.parent-reset').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    const out=await api({action:'parent_form', csrf:TEAM.csrf, scope:'player', id:+btn.dataset.id, reset:true});
    if(!out.ok){ toast(out.error||'Mislukt'); return; }
    location.reload();
  });
});
</script>
</body>
</html>
