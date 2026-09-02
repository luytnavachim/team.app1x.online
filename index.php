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
    header('Content-Disposition: attachment; filename="rohda-14-2-bestellijst.csv"');
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
<title>ROHDA 14-2 · Kleding</title>
<style>
:root{
  --red:#E31D1A; --yellow:#FFD600; --black:#17130E; --cream:#FFF9E8;
  --ink:#201B16; --muted:#7B7067; --line:#EEE6D5; --green:#188553; --okbg:#e6f8ef;
  --miss:#c44c46; --missbg:#ffeded; --na:#9a9288; --nabg:#f4f1ea; --blue:#2b6cb0;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;font-family:ui-rounded,"SF Pro Rounded","Nunito","Segoe UI",sans-serif;background:
  radial-gradient(circle at 90% -5%,rgba(255,214,0,.22),transparent 28%),
  linear-gradient(180deg,#fffdf7,#fffaf0);color:var(--ink)}
.wrap{max-width:1100px;margin:auto;padding:16px 14px 64px}
.top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:12px}
.club{display:flex;gap:10px;align-items:center}
.logo{width:46px;height:46px;border-radius:50%;border:5px solid var(--black);background:conic-gradient(from 90deg,var(--red) 0 50%,var(--yellow) 50% 100%);flex:0 0 46px}
.club b{display:block;font-size:18px}
.club small{color:var(--muted);font-weight:750}
.badge{background:var(--black);color:var(--yellow);padding:8px 11px;border-radius:999px;font-size:11px;font-weight:900}
.nav{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;position:sticky;top:0;z-index:5;padding:8px 0;background:linear-gradient(#fffdf7,#fffdf7 70%,transparent)}
.nav a,.btn{border:1px solid var(--line);background:#fff;border-radius:999px;padding:8px 12px;font-weight:850;font-size:12px;color:var(--ink);text-decoration:none}
.nav a:hover,.btn:hover{border-color:var(--black)}
.btn.dark{background:var(--black);color:var(--yellow);border-color:var(--black)}
.note{background:#fff;border:1px solid var(--line);border-radius:18px;padding:12px 14px;font-size:13px;color:var(--muted);font-weight:650;margin-bottom:14px}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:9px;margin-bottom:16px}
@media(max-width:700px){.stats{grid-template-columns:repeat(2,1fr)}}
.stat{background:#fff;border:1px solid var(--line);border-radius:18px;padding:12px}
.stat b{display:block;font-size:24px;line-height:1}
.stat span{font-size:11px;color:var(--muted);font-weight:800}
.featured{background:linear-gradient(135deg,#15110d,#8c140f);color:#fff;border-radius:24px;padding:16px;margin-bottom:16px}
.featured h2{margin:0 0 4px;font-size:20px}
.featured p{margin:0 0 12px;color:#f3e6d6;font-size:13px}
.pills{display:flex;flex-wrap:wrap;gap:7px}
.pill{background:rgba(255,255,255,.12);border-radius:999px;padding:7px 10px;font-size:12px;font-weight:800}
.pill.ok{background:#1f8a4c}
.pill.no{background:#c44c46}
.section{background:#fff;border:1px solid var(--line);border-radius:24px;padding:14px;margin-bottom:14px}
.section h3{margin:0 0 4px;font-size:17px}
.section .sub{margin:0 0 12px;font-size:12px;color:var(--muted);font-weight:650}
.legend{display:flex;gap:10px;flex-wrap:wrap;font-size:11px;font-weight:800;color:var(--muted);margin-bottom:10px}
.dot{display:inline-block;width:10px;height:10px;border-radius:3px;margin-right:4px;vertical-align:middle}
.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:10px}
.card{border:1px solid var(--line);border-radius:18px;padding:11px;background:linear-gradient(#fff,#fffdf8)}
.card.moos{outline:3px solid var(--yellow)}
.card.gap{outline:2px solid #f0b4b0}
.who{display:flex;justify-content:space-between;gap:8px;align-items:baseline}
.who b{font-size:15px}
.nr{font-size:11px;font-weight:900;color:var(--muted)}
.meta{font-size:11px;color:var(--muted);font-weight:750;margin:3px 0 8px}
.kit{display:grid;gap:5px}
.row{display:flex;justify-content:space-between;gap:8px;align-items:center;font-size:12px;font-weight:750;padding:6px 8px;border-radius:10px}
.row.ok{background:var(--okbg);color:var(--green)}
.row.no{background:var(--missbg);color:var(--miss)}
.row.na{background:var(--nabg);color:var(--na)}
.row.extra{background:#f7f2e9;color:var(--ink)}
.tablewrap{overflow:auto}
table{width:100%;border-collapse:separate;border-spacing:0;font-size:12px}
th,td{padding:8px 7px;border-bottom:1px solid var(--line);text-align:center;white-space:nowrap}
th{font-size:10px;text-transform:uppercase;letter-spacing:.3px;color:var(--muted);position:sticky;top:0;background:#fff}
td.name,th.name{text-align:left;font-weight:800}
td.left{text-align:left;font-weight:650;white-space:normal}
td.ok{background:var(--okbg);color:var(--green);font-weight:850}
td.no{background:var(--missbg);color:var(--miss);font-weight:850}
td.na{background:var(--nabg);color:var(--na)}
.filters{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:10px}
.filters button{border:1px solid var(--line);background:#fff;border-radius:999px;padding:8px 12px;font-weight:850;font-size:12px;cursor:pointer}
.filters button.on{background:var(--black);color:var(--yellow);border-color:var(--black)}
.hidden{display:none !important}
.line{margin:16px 0 8px;font-size:12px;font-weight:900;letter-spacing:.4px;text-transform:uppercase;color:var(--muted)}
.barwrap{display:grid;gap:8px}
.barrow{display:grid;grid-template-columns:110px 1fr 36px;gap:8px;align-items:center;font-size:12px;font-weight:750}
.bar{height:12px;background:#f4eee0;border-radius:99px;overflow:hidden}
.bar i{display:block;height:100%;background:linear-gradient(90deg,var(--red),#c41614);border-radius:99px}
.chip{display:inline-flex;gap:6px;align-items:center;background:#f7f2e9;border-radius:999px;padding:4px 8px;font-size:11px;font-weight:800;margin:0 6px 6px 0}
.actions{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0 0}
.row.wait{background:#fff6d6;color:#8a6408}
.muted{color:var(--muted);font-size:12px}
.size-select{max-width:108px;border:1px solid #ddd2bb;border-radius:8px;padding:4px 6px;font-weight:800;font-size:12px;background:#fff}
.editbar{background:#fff6d6;border-color:#ead48a;color:#6a5208}
button.btn{font-family:inherit;cursor:pointer}
.modal{position:fixed;inset:0;background:rgba(23,19,14,.45);display:flex;align-items:center;justify-content:center;z-index:20;padding:16px}
.modal.hidden{display:none}
.modalbox{background:#fff;border-radius:22px;padding:18px;width:min(360px,100%);box-shadow:0 20px 50px rgba(0,0,0,.2)}
.modalbox h3{margin:0 0 6px}
.modalbox p{margin:0 0 10px;color:var(--muted);font-size:13px;font-weight:650}
.modalbox input{width:100%;padding:12px;border-radius:12px;border:1px solid var(--line);font-size:20px;letter-spacing:4px;text-align:center;margin-bottom:10px}
.modalbox .err{color:var(--miss);min-height:18px}
.toast{position:fixed;bottom:16px;left:50%;transform:translateX(-50%);background:var(--black);color:var(--yellow);padding:10px 14px;border-radius:999px;font-weight:850;font-size:13px;z-index:30;opacity:0;pointer-events:none;transition:opacity .2s}
.toast.show{opacity:1}
@media print{
  .nav,.filters,.actions,.note{display:none !important}
  body{background:#fff}
  .section,.featured,.card{break-inside:avoid}
  .wrap{max-width:none;padding:0}
}
</style>
</head>
<body class="<?= $canEdit ? 'editing' : '' ?>">
<div class="wrap">
  <div class="top">
    <div class="club">
      <div class="logo"></div>
      <div><b>ROHDA 14-2</b><small>team.app1x.online · kleding &amp; selectie</small></div>
    </div>
    <div class="badge">Seizoen 26/27</div>
  </div>

  <nav class="nav">
    <a href="#bestel">Bestellijst</a>
    <a href="#opmeten">Opmeten</a>
    <a href="#spelers">Spelers</a>
    <a href="#maten">Maatverdeling</a>
    <a href="#matrix">Matrix</a>
    <a href="#staf">Staf</a>
    <a href="#catalogus">Catalogus</a>
    <a class="dark" href="?csv=bestel">CSV</a>
    <?php if ($canEdit): ?>
      <a class="dark" href="#opmeten">Invullen</a>
      <button type="button" class="btn" id="saveAllBtn">Alles opslaan</button>
      <button type="button" class="btn" id="logoutBtn">Klaar</button>
    <?php else: ?>
      <button type="button" class="btn dark" id="editBtn">Maten invullen</button>
    <?php endif; ?>
  </nav>

  <?php if ($canEdit): ?>
  <div class="note editbar">Bewerkmodus aan. Seizoen 26/27: iedereen krijgt een nieuwe basisset. Pas maten aan en druk op <b>Opslaan</b>. Pas als de set binnen is: <b>In bezit</b>.</div>
  <?php else: ?>
  <div class="note">Seizoen 26/27: voor iedereen een nieuwe set (shirt, broek, sokken, grip; keepers: keepershirt, broek, keepersokken). Posities komen live uit scout.app1x.online. <b>Maten invullen</b> met pincode als iemand is gegroeid.</div>
  <?php endif; ?>

  <div class="stats">
    <div class="stat"><b><?= count($active) ?></b><span>spelers in 14-2</span></div>
    <div class="stat"><b><?= $complete ?>/<?= count($active) ?></b><span>set ontvangen</span></div>
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
      <span><i class="dot" style="background:#ccc"></i>N.v.t. / extra</span>
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
</script>
</body>
</html>
