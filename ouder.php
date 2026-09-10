<?php
declare(strict_types=1);

require __DIR__ . '/boot.php';

header('Referrer-Policy: no-referrer');

$token = strtolower(trim((string) ($_GET['t'] ?? '')));
$found = findFillPersonByToken($mysqli, $token);
$who = (string) ($found['who'] ?? '');
$person = $found['person'] ?? null;
if (!$person) {
    http_response_code(404);
}

$types = loadTypes($mysqli);
$formSettings = loadParentFormSettings();
$typeOrder = [];
if ($person && $who === 'player') {
    $typeOrder = parentAllowedTypeIds($person);
} elseif ($person && $who === 'staff') {
    $typeOrder = staffAllowedTypeIds($person);
}

if ($person) {
    $person['items'] = [];
    if ($who === 'staff') {
        $st = $mysqli->prepare('SELECT * FROM staff_clothing WHERE staff_member_id=?');
    } else {
        $st = $mysqli->prepare('SELECT * FROM player_clothing WHERE player_id=?');
    }
    $pid = (int) $person['id'];
    $st->bind_param('i', $pid);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $person['items'][(int) $row['clothing_type_id']][] = $row;
    }
}

$player = $who === 'player' ? $person : null;
$name = $person ? fullName($person) : '';
$ini = $person ? playerInitials($person) : '';
$isStaff = $who === 'staff';
$roleLabel = $isStaff ? trim((string) ($person['role'] ?? 'staf')) : '';
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
<title><?= $person ? 'Maten · ' . h($name) : 'Link ongeldig' ?> · Kitroom</title>
<meta name="theme-color" content="#090A0C">
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
  --bg:#090A0C; --surface:#121417; --surface2:#181B20; --raise:#22262C;
  --line:#2C323A; --line2:#3D454E;
  --ink:#F4F1EC; --muted:#9A9388; --dim:#6B655C;
  --accent:#E11D2E; --accent-dim:#C41424; --on-accent:#FFFFFF; --accent-text:#FF6B76;
  --green:#3DCC8A; --greenbg:rgba(61,204,138,.14);
  --miss:#FF6B6B; --missbg:rgba(255,107,107,.14);
  --warn:#E8B84A; --warnbg:rgba(232,184,74,.14);
  --na:#6B655C; --nabg:rgba(255,255,255,.04);
  --glow-a:rgba(225,29,46,.14); --glow-b:rgba(232,184,74,.07);
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
.wrap{max-width:520px;margin:auto;padding:20px 16px 88px}
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
  border-radius:var(--r);padding:13px 14px;font-size:13.5px;color:var(--muted);
  font-weight:500;margin-bottom:16px;line-height:1.5;
}
.note b{color:var(--ink)}
.section{background:var(--surface);border:1px solid var(--line);border-radius:var(--r-lg);padding:18px 16px}
.section h2{margin:0 0 4px;font-size:clamp(20px,5vw,24px);font-weight:800;letter-spacing:-.4px;display:flex;align-items:baseline;gap:10px;flex-wrap:wrap}
.section h2 .ini{flex:0 0 auto;font-size:13px;font-weight:800;letter-spacing:.06em;color:var(--muted);border:1px solid var(--line);border-radius:8px;padding:2px 8px}
.section .sub{margin:0 0 16px;font-size:13px;color:var(--muted);font-weight:500}
.kit{display:grid;gap:8px}
.row{
  display:flex;justify-content:space-between;gap:12px;align-items:center;
  font-size:14px;font-weight:600;padding:12px 13px;border-radius:12px;
  background:var(--nabg);color:var(--muted);min-width:0;
}
.row.ok{background:var(--greenbg);color:var(--green)}
.row.no{background:var(--missbg);color:var(--miss)}
.row.wait{background:var(--warnbg);color:var(--warn)}
.row.extra{background:var(--nabg);color:var(--muted)}
.row>span:first-child{min-width:0;line-height:1.3}
.size-select{
  flex:0 0 auto;min-width:7.5rem;max-width:11rem;border:1px solid var(--line2);border-radius:10px;padding:9px 11px;
  font-weight:700;font-size:15px;background:var(--raise);color:var(--ink);
  font-family:inherit;cursor:pointer;
}
.btn{
  border:1px solid var(--line);background:var(--surface2);border-radius:999px;
  padding:13px 18px;font-weight:800;font-size:14px;color:var(--ink);
  font-family:inherit;cursor:pointer;width:100%;
}
.btn.dark{background:var(--accent);color:var(--on-accent);border-color:var(--accent)}
.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px}
.toast{
  position:fixed;bottom:18px;left:50%;transform:translateX(-50%);
  background:var(--accent);color:var(--on-accent);padding:11px 18px;border-radius:999px;
  font-weight:800;font-size:13px;z-index:50;opacity:0;pointer-events:none;transition:opacity .2s;
}
.toast.show{opacity:1}
.line{margin:16px 0 8px;font-size:11px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:var(--dim)}
.err{color:var(--miss);font-size:13px;font-weight:700;min-height:18px;margin:8px 0 0}
.packshot-wrap{margin:0 0 16px;text-align:center}
.packshot{
  display:block;margin:0 auto;width:100%;max-width:280px;height:auto;
  object-fit:contain;background:transparent;
}
.kit-design{
  display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:0 0 16px;
}
.kit-design figure{
  margin:0;background:var(--raise);border:1px solid var(--line);border-radius:var(--r);
  padding:10px 10px 8px;min-width:0;
}
.kit-design img{
  width:100%;height:auto;max-height:420px;display:block;margin:0 auto;
  object-fit:contain;background:transparent;
}
.kit-design figcaption{
  margin:8px 0 0;text-align:center;font-size:12px;font-weight:800;color:var(--muted);
}
@media(max-width:520px){
  .kit-design{grid-template-columns:1fr}
  .kit-design img{max-height:none}
}
.packcap{margin:6px 0 0;font-size:12px;font-weight:600;color:var(--muted)}
@media(max-width:520px){
  .row{flex-wrap:wrap}
  .row>span:first-child{width:100%}
  .size-select{width:100%;max-width:none;min-height:44px;font-size:16px}
  .section h2{font-size:22px}
  .note{font-size:14px;line-height:1.5}
}
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

  <?php if (!$person): ?>
    <div class="section">
      <h2>Link ongeldig</h2>
      <p class="sub">Deze link werkt niet meer. Vraag de trainer of manager om een nieuwe link.</p>
    </div>
  <?php else: ?>
    <p class="note">
      <?php if ($isStaff): ?>
      Vul bij <b><?= h($name) ?></b><?= $ini !== '' ? ' <b>(' . h($ini) . ')</b>' : '' ?> <b>elk item</b> in. Krijg je het: kies de maat zoals Stanno die bij het artikel toont. Krijg je het niet: kies <b>n.v.t.</b><?= $ini !== '' ? ' Initialen op de kleding: <b>' . h($ini) . '</b>.' : '' ?>
      <?php else: ?>
      Vul bij <b><?= h($name) ?></b><?= $ini !== '' ? ' <b>(' . h($ini) . ')</b>' : '' ?> <b>elk item</b> in, of hij het nu wel of niet krijgt. Krijgt hij het: kies de maat zoals Stanno die bij het artikel toont. Krijgt hij het niet: kies <b>n.v.t.</b> Kies ook een <b>rugnummer</b> en druk op opslaan. Een nummer dat al door een andere speler is gekozen, kun je niet meer kiezen.<?= $ini !== '' ? ' Initialen op de kleding: <b>' . h($ini) . '</b>.' : '' ?>
      <?php endif; ?>
      <?php if ($formSettings['note'] !== ''): ?> <?= h($formSettings['note']) ?><?php endif; ?>
    </p>
    <section class="kit-design">
      <figure>
        <img src="<?= assetUrl('speler-kit.png') ?>" width="1145" height="1037" alt="Spelerstenue 14-2: shirt, broekje, jassen, tas en sokken">
        <figcaption>Speler</figcaption>
      </figure>
      <figure>
        <img src="<?= assetUrl('kader-kit.png') ?>" width="837" height="1469" alt="Kadertenue 14-2: polo, shirt, jas en broekje">
        <figcaption>Kader</figcaption>
      </figure>
    </section>
    <?php if ($typeOrder === []): ?>
    <div class="section">
      <h2><?= h($name) ?><?php if ($ini !== ''): ?> <span class="ini"><?= h($ini) ?></span><?php endif; ?></h2>
      <p class="sub">Er staat nu niets klaar om in te vullen. Vraag de trainer of manager.</p>
    </div>
    <?php else: ?>
    <div class="section">
      <h2><?= h($name) ?><?php if ($ini !== ''): ?> <span class="ini"><?= h($ini) ?></span><?php endif; ?></h2>
      <p class="sub"><?php if ($isStaff): ?><?= h($roleLabel !== '' ? $roleLabel : 'staf') ?><?php else: ?><?= h($posLabel[$person['position'] ?? ''] ?? 'speler') ?><?= normalizeJerseyNumber($person['jersey_number'] ?? '') ? ' · #' . h((string) normalizeJerseyNumber($person['jersey_number'])) : '' ?><?php endif; ?><?= $ini !== '' ? ' · initialen ' . h($ini) : '' ?></p>
      <form id="parentForm">
        <div class="kit">
          <?php if (!$isStaff): ?>
          <div class="row wait">
            <span>Rugnummer <small style="font-weight:600;opacity:.8">(uniek)</small></span>
            <?= jerseySelectHtml($mysqli, $person, ['id' => 'jerseySelect', 'required' => true, 'class' => 'size-select']) ?>
          </div>
          <?php endif; ?>
          <?php foreach ($typeOrder as $tid):
            if (!isset($types[$tid])) continue;
            $t = $types[$tid];
            $it = itemFor($person, $tid);
            $pending = isPendingItem($it);
            $cls = $it ? ($pending ? 'wait' : 'ok') : 'no';
          ?>
          <div class="row <?= $cls ?>">
            <span><?= h($t['display_name']) ?><?= $pending ? ' · bestellen' : '' ?></span>
            <?= sizeSelect($tid, (string) ($it['size'] ?? ''), $who, (int) $person['id'], false, true) ?>
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
<?php if ($person): ?>
const PARENT = { csrf: <?= json_encode($csrf) ?>, token: <?= json_encode($token) ?>, staff: <?= $isStaff ? 'true' : 'false' ?> };
function toast(msg){
  const el=document.getElementById('toast');
  el.textContent=msg;
  el.classList.add('show');
  clearTimeout(toast._t);
  toast._t=setTimeout(()=>el.classList.remove('show'), 2400);
}
document.addEventListener('change', e=>{
  const sel=e.target.closest?.('.size-select');
  if(!sel || sel.id==='jerseySelect') return;
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
  const jersey=document.getElementById('jerseySelect')?.value || '';
  if(!PARENT.staff && !jersey){ err.textContent='Kies een rugnummer.'; return; }
  const items={};
  document.querySelectorAll('.size-select[data-tid]').forEach(s=>{ items[s.dataset.tid]=s.value; });
  btn.disabled=true;
  try{
    const res=await fetch('save.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({action:'parent_save', csrf:PARENT.csrf, token:PARENT.token, items, jersey_number:jersey}),
      credentials:'same-origin'
    });
    let data={};
    try{ data=await res.json(); }catch(ex){ data={ok:false,error:'Geen antwoord'}; }
    if(!data.ok){ err.textContent=data.error||'Opslaan mislukt'; return; }
    toast('Opgeslagen, bedankt'+(data.jersey ? ' · #'+data.jersey : ''));
    setTimeout(()=>location.reload(), 700);
  } finally {
    btn.disabled=false;
  }
});
<?php endif; ?>
</script>
</body>
</html>
