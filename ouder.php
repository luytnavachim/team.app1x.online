<?php
declare(strict_types=1);

require __DIR__ . '/boot.php';

header('Referrer-Policy: no-referrer');

$token = strtolower(trim((string) ($_GET['t'] ?? '')));
$player = findPlayerByParentToken($mysqli, $token);
if (!$player) {
    http_response_code(404);
}

$types = loadTypes($mysqli);
$need = $player ? ((($player['position'] ?? '') === 'goalkeeper') ? [9, 4, 10] : [1, 4, 3, 7]) : [];
$extra = [11, 12];
$typeOrder = array_values(array_unique(array_merge($need, $extra)));

if ($player) {
    $player['items'] = [];
    $st = $mysqli->prepare('SELECT * FROM player_clothing WHERE player_id=?');
    $pid = (int) $player['id'];
    $st->bind_param('i', $pid);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $player['items'][(int) $row['clothing_type_id']][] = $row;
    }
}

$name = $player ? fullName($player) : '';
$posLabel = [
    'attacker' => 'aanval',
    'midfielder' => 'middenveld',
    'defender' => 'verdediging',
    'goalkeeper' => 'keeper',
];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $player ? 'Maten · ' . h($name) : 'Link ongeldig' ?> · Kitroom</title>
<meta name="theme-color" content="#0F1216">
<meta name="robots" content="noindex,nofollow">
<meta name="referrer" content="no-referrer">
<script>
(function(){
  var t='dark';
  try { t=localStorage.getItem('kitroom-theme')||'dark'; } catch(e) {}
  if(t!=='light') t='dark';
  document.documentElement.setAttribute('data-theme', t);
  document.documentElement.style.colorScheme=t;
})();
</script>
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
}
*{box-sizing:border-box}
html{color-scheme:dark;-webkit-text-size-adjust:100%}
html[data-theme="light"]{color-scheme:light}
body{
  margin:0;color:var(--ink);
  font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
  font-size:16px;line-height:1.45;
  background:var(--bg);
  background-image:
    radial-gradient(900px 420px at 78% -12%,var(--glow-a),transparent 62%),
    radial-gradient(700px 380px at 8% -6%,var(--glow-b),transparent 60%);
  background-attachment:fixed;
}
.wrap{max-width:560px;margin:auto;padding:18px 14px 80px}
.top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px}
.club b{display:block;font-size:19px;font-weight:800;letter-spacing:-.3px}
.club small{display:block;color:var(--muted);font-size:12px;font-weight:600}
.theme-switch{display:flex;border:1px solid var(--line);border-radius:999px;background:var(--surface);overflow:hidden}
.theme-switch button{
  border:0;background:transparent;color:var(--muted);padding:7px 11px;
  font-weight:800;font-size:11px;cursor:pointer;font-family:inherit;
}
.theme-switch button[aria-pressed="true"]{background:var(--accent);color:var(--on-accent)}
.note{
  background:var(--surface);border:1px solid var(--line);border-left:3px solid var(--accent);
  border-radius:var(--r);padding:12px 14px;font-size:13.5px;color:var(--muted);
  font-weight:500;margin-bottom:16px;
}
.note b{color:var(--ink)}
.section{background:var(--surface);border:1px solid var(--line);border-radius:var(--r-lg);padding:16px}
.section h2{margin:0 0 4px;font-size:22px;font-weight:800;letter-spacing:-.4px}
.section .sub{margin:0 0 16px;font-size:13px;color:var(--muted);font-weight:500}
.kit{display:grid;gap:8px}
.row{
  display:flex;justify-content:space-between;gap:10px;align-items:center;
  font-size:14px;font-weight:600;padding:11px 12px;border-radius:12px;
  background:var(--nabg);color:var(--muted);
}
.row.ok{background:var(--greenbg);color:var(--green)}
.row.no{background:var(--missbg);color:var(--miss)}
.row.wait{background:var(--warnbg);color:var(--warn)}
.row.extra{background:var(--nabg);color:var(--muted)}
.row>span:first-child{min-width:0}
.size-select{
  max-width:140px;border:1px solid var(--line2);border-radius:10px;padding:8px 10px;
  font-weight:700;font-size:15px;background:var(--raise);color:var(--ink);
  font-family:inherit;cursor:pointer;
}
.btn{
  border:1px solid var(--line);background:var(--surface2);border-radius:999px;
  padding:12px 16px;font-weight:800;font-size:14px;color:var(--ink);
  font-family:inherit;cursor:pointer;
}
.btn.dark{background:var(--accent);color:var(--on-accent);border-color:var(--accent)}
.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}
.toast{
  position:fixed;bottom:18px;left:50%;transform:translateX(-50%);
  background:var(--accent);color:var(--on-accent);padding:11px 18px;border-radius:999px;
  font-weight:800;font-size:13px;z-index:50;opacity:0;pointer-events:none;transition:opacity .2s;
}
.toast.show{opacity:1}
.line{margin:16px 0 8px;font-size:11px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:var(--dim)}
.err{color:var(--miss);font-size:13px;font-weight:700;min-height:18px;margin:8px 0 0}
</style>
</head>
<body>
<div class="wrap">
  <header class="top">
    <div class="club">
      <b>Kitroom</b>
      <small>14-2 · maten invullen</small>
    </div>
    <div class="theme-switch" role="group" aria-label="Thema">
      <button type="button" data-theme-set="dark" aria-pressed="true">Donker</button>
      <button type="button" data-theme-set="light" aria-pressed="false">Licht</button>
    </div>
  </header>

  <?php if (!$player): ?>
    <div class="section">
      <h2>Link ongeldig</h2>
      <p class="sub">Deze ouderlink werkt niet meer. Vraag de trainer of manager om een nieuwe link.</p>
    </div>
  <?php else: ?>
    <p class="note">Vul de kledingmaten van <b><?= h($name) ?></b> in en druk op opslaan. Shirt en broek in 14-2 zijn nu vooral <b>164</b> of <b>176</b>, sokken <b>36-40</b> of <b>41-44</b>.</p>
    <div class="section">
      <h2><?= h($name) ?></h2>
      <p class="sub"><?= h($posLabel[$player['position'] ?? ''] ?? 'speler') ?><?= !empty($player['jersey_number']) ? ' · #' . h((string) $player['jersey_number']) : '' ?></p>
      <form id="parentForm">
        <div class="kit">
          <?php foreach ($typeOrder as $tid):
            if (!isset($types[$tid])) continue;
            $t = $types[$tid];
            $it = itemFor($player, $tid);
            $isReq = in_array($tid, $need, true);
            $pending = isPendingItem($it) && $isReq;
            $cls = $it ? ($pending ? 'wait' : ($isReq ? 'ok' : 'extra')) : ($isReq ? 'no' : 'extra');
            if ($tid === 11 || $tid === 12):
          ?>
            <?php if ($tid === 11): ?><div class="line">Extra</div><?php endif; ?>
          <?php endif; ?>
          <div class="row <?= $cls ?>">
            <span><?= h($t['display_name']) ?><?= $isReq ? '' : ' · extra' ?><?= $pending ? ' · bestellen' : '' ?></span>
            <?= sizeSelect($tid, (string) ($it['size'] ?? ''), 'player', (int) $player['id']) ?>
          </div>
          <?php endforeach; ?>
        </div>
        <p class="err" id="formErr"></p>
        <div class="actions">
          <button class="btn dark" type="submit" id="saveBtn">Maten opslaan</button>
        </div>
      </form>
    </div>
  <?php endif; ?>
</div>
<div id="toast" class="toast"></div>
<script>
(function(){
  const KEY='kitroom-theme';
  const meta=document.querySelector('meta[name="theme-color"]');
  function theme(){ return document.documentElement.getAttribute('data-theme')==='light' ? 'light' : 'dark'; }
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
<?php if ($player): ?>
const PARENT = { csrf: <?= json_encode($csrf) ?>, token: <?= json_encode($token) ?> };
function toast(msg){
  const el=document.getElementById('toast');
  el.textContent=msg;
  el.classList.add('show');
  clearTimeout(toast._t);
  toast._t=setTimeout(()=>el.classList.remove('show'), 2400);
}
document.addEventListener('change', e=>{
  const sel=e.target.closest?.('.size-select');
  if(!sel) return;
  const tid=sel.dataset.tid, who=sel.dataset.who, id=sel.dataset.id;
  document.querySelectorAll(`.size-select[data-who="${who}"][data-id="${id}"][data-copy-from="${tid}"]`).forEach(t=>{
    if(!t.value) t.value=sel.value;
  });
});
document.getElementById('parentForm')?.addEventListener('submit', async e=>{
  e.preventDefault();
  const err=document.getElementById('formErr');
  const btn=document.getElementById('saveBtn');
  err.textContent='';
  const items={};
  document.querySelectorAll('.size-select').forEach(s=>{ items[s.dataset.tid]=s.value; });
  btn.disabled=true;
  try{
    const res=await fetch('save.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({action:'parent_save', csrf:PARENT.csrf, token:PARENT.token, items}),
      credentials:'same-origin'
    });
    let data={};
    try{ data=await res.json(); }catch(ex){ data={ok:false,error:'Geen antwoord'}; }
    if(!data.ok){ err.textContent=data.error||'Opslaan mislukt'; return; }
    toast('Opgeslagen, bedankt');
    setTimeout(()=>location.reload(), 700);
  } finally {
    btn.disabled=false;
  }
});
<?php endif; ?>
</script>
</body>
</html>
