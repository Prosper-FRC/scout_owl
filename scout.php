<?php
// scouter.php — The Scout Owl (auto-select latest game, dropdown override, banner logo, phase-aware points)

$gamesDir = __DIR__ . '/games';
$gameFiles = [];
if (is_dir($gamesDir)) {
  $files = glob($gamesDir . '/*.json');
  sort($files, SORT_NATURAL | SORT_FLAG_CASE);
  foreach ($files as $f) $gameFiles[] = basename($f);
}
$latestGame = end($gameFiles) ?: '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>the Scout Owl</title>

<style>
/* ==== your theme, inlined ==== */
@font-face{font-family:'Roboto';src:url('/../stat_goblin/fonts/roboto/Roboto-Regular.ttf') format('ttf')}
@font-face{font-family:'Griffy';src:url('/../stat_goblin/fonts/Griffy/Griffy-Regular.ttf') format('ttf')}
@font-face{font-family:'Comfortaa';src:url('/../stat_goblin/fonts/Comfortaa/Comfortaa-VariableFont_wght.ttf') format('ttf')}
body,html{font-family:'Comfortaa',sans-serif;margin:0;padding:0;display:flex;justify-content:center;background:#111;color:#fff}
h1{text-align:left;font-size:.75rem;margin-left:12px;margin-top:-24px;color:#fff}
h2,h3,h4{text-align:center;margin:0;color:#ccc}
h2{font-size:1rem;margin-top:-10px;color:#fff}
h3{font-size:1rem}
h4{font-size:.8rem}
#block{position:absolute;background:#222;top:0;margin:auto;padding:12px;max-width:800px;max-height:1280px;scrollbar-width:thin;scrollbar-color:#888 #333}
#block::-webkit-scrollbar{width:12px;height:12px}
#block::-webkit-scrollbar-track{background:#111;border-radius:6px}
#block::-webkit-scrollbar-thumb{background:#333;border-radius:6px;border:3px solid #111}
#block::-webkit-scrollbar-thumb:hover{background:#555}
#top{width:95%;margin:auto;display:flex;flex-direction:column}
#logoAndScoreboard{display:flex;width:100%;height:80%}
#logoOuter,#scoreboardOuter{width:50%}
.logo{width:100%}
#gameBanner{width:100%;max-width:800px;height:auto;object-fit:contain;border-radius:10px;margin:8px 0}
#scoreboard{width:94%;height:80%;margin:5% auto;border:1px solid #fff;background:#111;display:flex;flex-direction:column}
#timer,#score{width:100%;height:50%;display:flex;align-items:center;justify-content:center;font-size:3.5rem;cursor:pointer}
#timer-inner,#score-inner{margin:auto}
#matchInfoOuter{width:100%}
.info{display:flex;justify-content:space-between}
.infoBlock{flex:1;padding:10px;margin:5px}
#mid{display:flex;align-items:stretch;height:100%}
.threeBox{display:flex;align-items:stretch}
.buttons{flex:1;display:grid;grid-template-columns:repeat(2,1fr);gap:10px;width:80%;margin:auto;position:relative}
.button{font-size:1rem;text-align:center;background:#222;color:#fff;border-radius:5px;border:1px solid #fff;padding:.5rem;cursor:pointer;transition:background-color .2s}
.button:hover{background:#fff;color:#111}
.button.selected{background:#fff;color:#111}
.long-button{grid-column:span 2}
.PUC{grid-row:span 2;padding-top:10%}
.circle{border-radius:50%;margin:auto}
.coral{border-color:#CF4FB2}
.algae{border-color:#8AE8E0}
.opponent,.aliance{border-color:palevioletred}
#validator{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;background:rgba(0,0,0,.7);padding:40px;border-radius:10px}
.input-container{display:flex;gap:10px;margin-top:20px}
.input-box{width:70px;height:70px;text-align:center;border:2px solid #fff;background:#111;color:#fff;border-radius:10px}
.input-box:focus{border-color:#0f0}
#bottom{width:95%;margin:auto;display:flex;flex-direction:column;justify-content:flex-end;padding-bottom:20px}
#locationDisplay,#statusDisplay{text-align:center;margin-bottom:10px}
#coda{width:100%;display:flex}
#loc{width:100%;display:flex;flex-direction:column;justify-content:space-between;align-items:center}
#bottomDisplays,#stat{width:100%;text-align:center;margin-top:10px}
@keyframes flashFailure{0%,20%,40%,60%,80%,100%{background:#C0392B}10%,30%,50%,70%,90%{background:#000}}
.flashFailure{animation:flashFailure .5s ease-in-out}
@keyframes flashSuccess{0%,20%,40%,60%,80%,100%{background:#006632}10%,30%,50%,70%,90%{background:#000}}
.flashSuccess{animation:flashSuccess .5s ease-in-out}
.hex{background:#CF4FB2;margin:auto;clip-path:polygon(25% 0%,75% 0%,100% 50%,75% 100%,25% 100%,0% 50%)}
.griffy{font-family:'Griffy',sans-serif}
.auton{background:#444}.auton:hover{background:#fff}.auton.selected{background:#fff}
#offlineSubmissions{display:none}
select{background:#111;color:#fff;border:1px solid #555;border-radius:6px;padding:6px 10px}
</style>
</head>
<body>
<div id="block">
  <div id="top">
    <div id="logoAndScoreboard">
      <div id="logoOuter">
        <img src="images/thescoutowl.png" class="logo" alt="Logo" onerror="this.src='../images/thescoutowl.png'">
        <img id="gameBanner" alt="" style="display:none">
        <h1 class="griffy" id="gameEditionTitle">Loading…</h1>
      </div>
      <div id="scoreboardOuter">
        <div id="scoreboard" class="alliance">
          <div id="timer"><div id="timer-inner">150</div></div>
          <div id="score"><div id="score-inner">0</div></div>
        </div>
        <h2 id="eventName" style="margin-top:8px;display:none"></h2>
      </div>
    </div>

    <div id="matchInfoOuter">
      <div class="content">
        <div class="info">
          <div class="infoBlock"><h3 id="matchNumber"></h3></div>
          <div class="infoBlock"><h3 id="robotName"></h3></div>
        </div>
      </div>
    </div>

    <div style="margin-top:10px">
      <label><strong>Select Game:</strong></label>
      <select id="gameSelect">
        <option value="">-- Choose Game --</option>
        <?php foreach ($gameFiles as $g): ?>
          <option value="<?= htmlspecialchars($g) ?>" <?= $g === $latestGame ? 'selected' : '' ?>>
            <?= htmlspecialchars(pathinfo($g, PATHINFO_FILENAME)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div id="mid">
    <div class="buttons" id="dynamicButtons"></div>
  </div>

  <div id="coda">
    <div id="bottomDisplays">
      <div id="loc"><h3 id="locationDisplay">Location:</h3></div>
      <div id="stat"><h3 id="statusDisplay">Swipe to Submit</h3></div>
    </div>
  </div>

  <table id="offlineSubmissions" border="1">
    <thead>
      <tr><th>Event</th><th>Match</th><th>Time</th><th>Robot</th><th>Alliance</th><th>Action</th><th>Location</th><th>Result</th><th>Points</th></tr>
    </thead>
    <tbody></tbody>
  </table>
</div>

<script>
const gameSelect = document.getElementById('gameSelect');
const banner = document.getElementById('gameBanner');
const titleEl = document.getElementById('gameEditionTitle');
const buttonsDiv = document.getElementById('dynamicButtons');
const statusEl = document.getElementById('statusDisplay');
const timerEl = document.getElementById('timer-inner');
const scoreEl = document.getElementById('score-inner');

let selectedBtn = null;
let score = 0;

function isAutonPhase(){
  const t = Number(timerEl.textContent || 0);
  return t >= 135; // 15 sec auto at start
}

async function loadBanner(baseName){
  const base = `games/logos/${baseName}`;
  for (const ext of ['.png','.svg','.webp']) {
    try {
      const r = await fetch(base + ext, { method:'HEAD' });
      if (r.ok) {
        banner.src = base + ext;
        banner.style.display = 'block';
        return;
      }
    } catch(e){}
  }
  banner.style.display = 'none';
}

async function loadGame(file){
  if (!file) return;
  const baseName = file.replace(/\.json$/,'');
  titleEl.textContent = baseName + ' Edition';
  loadBanner(baseName);

  const res = await fetch('games/' + file);
  if (!res.ok) { console.error('JSON not found', file); return; }
  const data = await res.json();
  buildButtons(data.buttons || []);
}

function buildButtons(list){
  buttonsDiv.innerHTML = '';
  selectedBtn = null;
  statusEl.textContent = 'Swipe to Submit';

  list.sort((a,b)=>b.name.localeCompare(a.name));
  for (const b of list){
    const el = document.createElement('div');
    el.className = 'button fontBig';
    if (b.type === 'offense') el.classList.add('alliance');
    if (b.type === 'defense') el.classList.add('opponent');
    el.dataset.action = b.code || '';
    el.dataset.autonPoints = Number(b.autonPoints ?? 0);
    el.dataset.teleopPoints = Number(b.teleopPoints ?? 0);
    el.textContent = b.name || b.code || 'Action';
    el.onclick = () => selectButton(el);
    buttonsDiv.appendChild(el);
  }
}

function selectButton(el){
  document.querySelectorAll('.button').forEach(x=>x.classList.remove('selected'));
  el.classList.add('selected');
  selectedBtn = el;
  statusEl.textContent = 'Selected: ' + el.textContent;
}

function addOfflineSubmission(row){
  const tbody = document.querySelector('#offlineSubmissions tbody');
  const tr = document.createElement('tr');
  row.forEach(v=>{ const td=document.createElement('td'); td.textContent=v; tr.appendChild(td); });
  tbody.appendChild(tr);
}

// swipe handling
let startX=null;
document.body.addEventListener('touchstart', e => startX = e.touches[0].clientX);
document.body.addEventListener('touchend', e => {
  if (!selectedBtn || startX === null) return;
  const dx = e.changedTouches[0].clientX - startX;
  startX = null;
  if (Math.abs(dx) < 50) return;

  const success = dx > 0;
  const pts = success
    ? (isAutonPhase() ? Number(selectedBtn.dataset.autonPoints) : Number(selectedBtn.dataset.teleopPoints))
    : 0;

  if (success) {
    score += pts;
    scoreEl.textContent = String(score);
  }

  const row = ['Event', 'Match', new Date().toLocaleTimeString(), 'Robot',
               selectedBtn.classList.contains('alliance') ? 'Red' : 'Blue',
               selectedBtn.dataset.action, 'TBD', success ? 'success' : 'fail', pts];
  addOfflineSubmission(row);

  statusEl.textContent = `${selectedBtn.textContent} → ${success ? 'SUCCESS' : 'FAIL'} (${pts} pts)`;
  document.body.classList.add(success ? 'flashSuccess' : 'flashFailure');
  setTimeout(()=>document.body.classList.remove('flashSuccess','flashFailure'), 500);
});

// dropdown change
gameSelect.addEventListener('change', ()=> loadGame(gameSelect.value));

// auto-select latest
document.addEventListener('DOMContentLoaded', ()=>{
  if (gameSelect.value) loadGame(gameSelect.value);
});
</script>
</body>
</html>
