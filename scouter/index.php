<?php
// scouter/index.php — The Scout Owl (Loads game from URL parameter)

// Show all errors to help debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

$gamesDir = __DIR__ . '/games';
$gameFiles = []; // Initialize as empty array

// --- Improved File Loading ---
if (is_dir($gamesDir)) {
    // Use DIRECTORY_SEPARATOR for better cross-platform compatibility
    $files = glob($gamesDir . DIRECTORY_SEPARATOR . '*.json'); 
    
    // Check if glob found any files before sorting
    if ($files !== false && count($files) > 0) { 
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);
        
        // Filter out invalid filenames just in case
        foreach ($files as $f) {
            $basename = basename($f);
            // Add a basic check: ensure it ends with .json and has a valid name part
            if (str_ends_with($basename, '.json') && strlen($basename) > 5) { 
                $gameFiles[] = $basename;
            }
        }
    } else {
        // Handle case where no JSON files are found
        error_log("No JSON files found in " . $gamesDir); 
    }
} else {
    // Handle case where /games directory doesn't exist
    error_log("Games directory not found: " . $gamesDir);
}

// Determine the latest game *only* if we found valid files
$latestGame = !empty($gameFiles) ? end($gameFiles) : ''; 

// Optional: Log the results for debugging
// error_log("Found game files: " . print_r($gameFiles, true));
// error_log("Latest game determined: " . $latestGame);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>the Scout Owl</title>

<style>
/* ==== Fonts ==== */
@font-face{font-family:'Roboto';src:url('/../stat_goblin/fonts/roboto/Roboto-Regular.ttf') format('ttf')}
@font-face{font-family:'Griffy';src:url('/../stat_goblin/fonts/Griffy/Griffy-Regular.ttf') format('ttf')}
@font-face{font-family:'Comfortaa';src:url('/../stat_goblin/fonts/Comfortaa/Comfortaa-VariableFont_wght.ttf') format('ttf')}

/* ==== THEME VARIABLES ==== */
:root {
    /* Light Mode (Default) */
    --bg-primary: #f4f4f4;       /* Light background */
    --bg-secondary: #ffffff;     /* Card/Container background */
    --text-primary: #111111;     /* Dark text */
    --text-secondary: #555555;   /* Lighter text */
    --border-color: #dddddd;     /* Subtle borders */
    --accent-color: #007bff;     /* A general accent */
    --red-alliance: #C0392B;
    --blue-alliance: #2C3E50;
    --neutral-border: #333333; /* Darker neutral border for light mode */
    --button-bg: #eeeeee;
    --button-bg-hover: #dddddd;
    --button-selected-bg: var(--accent-color);
    --button-selected-text: #ffffff;
    --scoreboard-bg: #ffffff;
    --scoreboard-border: #cccccc;
    --scoreboard-text: #111111;
}

body.dark-mode {
    /* Dark Mode Overrides */
    --bg-primary: #111111;
    --bg-secondary: #222222;
    --text-primary: #eeeeee;
    --text-secondary: #bbbbbb;
    --border-color: #444444;
    --accent-color: #0d6efd;
    --neutral-border: #cccccc; /* Lighter neutral border for dark mode */
    --button-bg: #333333;
    --button-bg-hover: #444444;
    --button-selected-bg: var(--accent-color);
    --button-selected-text: #ffffff;
    --scoreboard-bg: #1a1a1a;
    --scoreboard-border: #555555;
    --scoreboard-text: #eeeeee;
}

/* ==== Base Styles ==== */
body,html{
    font-family:'Comfortaa', sans-serif;
    margin:0; padding:0; display:flex; justify-content:center;
    background-color: var(--bg-primary); /* Apply theme background */
    color: var(--text-primary);
    transition: background-color 0.3s, color 0.3s;
    min-height: 100vh; /* Ensure body covers full viewport height */
}
h1, h2, h3, h4 { text-align:center; margin:0; color: var(--text-secondary); }

h1#gameEditionTitle {
    display: block;
    font-size: 1.25rem;
    margin-top: 8px; /* Space below scoreboard */
    margin-left: 0; /* No specific left margin needed now */
    text-align: center; /* Center under scoreboard */
    color: var(--text-primary);
    font-family:'Griffy',sans-serif;
    width: 100%; /* Take full width of scoreboardOuter */
}
h2 { font-size:1rem; margin-top: -10px; color: var(--text-primary); }
h3 { font-size:1rem; }
h4 { font-size:.8rem; }

/* ==== Layout Containers ==== */
#block {
    position: absolute;
    background-color: var(--bg-secondary);
    top: 0;
    width: 100vw;       /* Make it full viewport width */
    max-width: 800px;   /* Cap the width */
    margin: 0 auto;     /* Center horizontally */
    padding: 15px;
    max-height: 1280px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: background-color 0.3s;
    scrollbar-width: thin;
    scrollbar-color: #888 var(--bg-secondary);
    box-sizing: border-box;
}
#block::-webkit-scrollbar{width:10px;height:10px}
#block::-webkit-scrollbar-track{background:var(--bg-secondary);border-radius:5px}
#block::-webkit-scrollbar-thumb{background:#555;border-radius:5px;border:2px solid var(--bg-secondary)}
#block::-webkit-scrollbar-thumb:hover{background:#777}

#top{width:95%; margin:auto; display:flex; flex-direction:column; gap: 10px;}
#logoAndScoreboard{display:flex; width:100%; align-items: flex-start;}
#logoOuter,#scoreboardOuter{width:50%}
.logo{width:100%; }

#scoreboard{
    width:94%; height: auto; margin: 0 auto;
    border: 2px solid var(--scoreboard-border);
    background-color: var(--scoreboard-bg); color: var(--scoreboard-text);
    display:flex; flex-direction:column; border-radius: 6px;
    transition: background-color 0.3s, border-color 0.3s, color 0.3s;
}
#timer,#score{ width:100%; height:50%; display:flex; align-items:center; justify-content:center; font-size:3rem; cursor:pointer; padding: 5px 0; }
#timer-inner,#score-inner{margin:auto}

#matchInfoOuter{width:100%; margin-top: 10px;}
.info{display:flex; justify-content:space-between}
.infoBlock{flex:1; padding: 5px; margin: 0 5px;}

#mid{display:flex; align-items:stretch; height:100%; margin-top: 20px;}

/* --- Button & Layout CSS --- */
.threeBox { display: flex; align-items: stretch; justify-content: center; gap: 10px; }
.threeBox .button { flex: 1; }
.button.circle { border-radius: 50%; aspect-ratio: 1 / 1; height: 4.5rem; width: 4.5rem; padding: 0; margin-left: auto; margin-right: auto; display: flex; align-items: center; justify-content: center; flex-grow: 0; flex-shrink: 0; flex-basis: auto; }
.threeBox .button.circle { flex-grow: 0; flex-basis: auto; }

.buttons{flex:1; display:grid; grid-template-columns:repeat(2,1fr); gap:12px; width:90%; margin:auto; position:relative}
.button{
    font-size:0.9rem; text-align:center; background-color: var(--button-bg); color: var(--text-primary);
    border-radius:6px; border: 1px solid var(--border-color); padding: .8rem .5rem; cursor:pointer;
    transition: background-color 0.2s, border-color 0.2s, color 0.2s, box-shadow 0.2s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05); line-height: 1.3;
}
.button:hover{ background-color: var(--button-bg-hover); box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
.button.selected{ background-color: var(--button-selected-bg); color: var(--button-selected-text); border-color: var(--button-selected-bg); box-shadow: inset 0 1px 3px rgba(0,0,0,0.2); }
.long-button{grid-column:span 2}
.PUC{grid-row:span 2; padding-top: 10%;}

.button.alliance { border-color: var(--red-alliance); border-width: 2px; }
.button.opponent { border-color: var(--blue-alliance); border-width: 2px; }
.button.cooperative { border-color: var(--neutral-border); border-width: 2px; }
.button.selected.alliance, .button.selected.opponent, .button.selected.cooperative { border-color: var(--button-selected-bg); }

.spacer { visibility: hidden; min-height: 3rem; grid-row: auto / span 1; grid-column: auto / span 1; }

#coda{width:100%; display:flex; flex-direction: column; align-items: center; margin-top: 20px;}
#loc{width:100%; display:flex; flex-direction:column; justify-content:space-between; align-items:center}
#bottomDisplays,#stat{width:100%; text-align:center; margin-top:10px}

/* --- Banner CSS --- */
#gameBanner { width: 80%; max-width: 300px; height: auto; object-fit: contain; margin-top: 15px; border-radius: 6px; display: none; }

/* --- Animations --- */
@keyframes shake{0%,100%{transform:translate(0)}15%,65%{transform:translateY(-3px)}25%,75%{transform:translateX(-3px)}35%,85%{transform:translateY(3px)}50%{transform:translateX(3px)}}
.shake{animation:shake .5s ease-in-out}
@keyframes flashFailure{0%,20%,40%,60%,80%,100%{background-color:var(--red-alliance)}10%,30%,50%,70%,90%{background-color:inherit}}
.flashFailure{animation:flashFailure .5s ease-in-out}
@keyframes flashSuccess{0%,20%,40%,60%,80%,100%{background-color:#006632}10%,30%,50%,70%,90%{background-color:inherit}}
.flashSuccess{animation:flashSuccess .5s ease-in-out}

.griffy{font-family:'Griffy',sans-serif}
#offlineSubmissions{display:none}

/* --- Theme Toggle Button --- */
#themeToggle {
    position: fixed; bottom: 15px; right: 15px; background-color: var(--button-bg); color: var(--text-primary);
    border: 1px solid var(--border-color); border-radius: 50%; width: 40px; height: 40px; font-size: 1.2rem;
    cursor: pointer; z-index: 1000; display: flex; align-items: center; justify-content: center;
    transition: background-color 0.3s, color 0.3s, border-color 0.3s;
}
#themeToggle:hover { background-color: var(--button-bg-hover); }
</style>
</head>
<body>
<div id="block">
  <div id="top">
    <div id="logoAndScoreboard">
      <div id="logoOuter">
       <a href="../scouter.php"> <img src="../images/thescoutowl.png" class="logo" alt="Logo" id="mainLogo"></a>
      </div>
      <div id="scoreboardOuter">
        <div id="scoreboard" class="alliance">
          <div id="timer"><div id="timer-inner">150</div></div>
          <div id="score"><div id="score-inner">0</div></div>

        </div>
         <h1 class="griffy" id="gameEditionTitle">Loading…</h1>
        <h2 id="eventName" style="margin-top:8px; display:none"></h2>
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
    </div>

  <div id="mid">
    <div class="buttons" id="dynamicButtons"></div>
  </div>

  <div id="coda">
    <div id="bottomDisplays">
      <div id="loc"><h3 id="locationDisplay">Location:</h3></div>
      <div id="stat"><h3 id="statusDisplay">Swipe to Submit</h3></div>
    </div>
    <img id="gameBanner" alt="Game Banner">
  </div>

  <table id="offlineSubmissions" border="1">
    <thead>
      <tr>
        <th>Game</th> <th>Event</th>
        <th>Match</th>
        <th>Time</th>
        <th>Robot</th>
        <th>Alliance</th>
        <th>Action</th>
        <th>Location</th>
        <th>Result</th>
        <th>Points</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>
</div>
<button id="themeToggle" title="Toggle Light/Dark Mode">☀️</button>
<script src="../js/canvas-confetti.js" type="text/javascript"></script>

<script>
// === THEME TOGGLE LOGIC ===
const themeToggle = document.getElementById('themeToggle');
const bodyElement = document.body;
const mainLogo = document.getElementById('mainLogo'); // Make sure this line is here

// Function to apply the theme and save preference
function applyTheme(theme) {
    if (theme === 'dark') {
        bodyElement.classList.add('dark-mode');
        themeToggle.textContent = '🌙'; // Moon icon for dark
        if (mainLogo) { // Check if logo element exists
             mainLogo.src = '../images/thescoutowl.png'; // Dark mode logo
        }
        localStorage.setItem('scoutrTheme', 'dark');
    } else { // Light mode
        bodyElement.classList.remove('dark-mode');
        themeToggle.textContent = '☀️'; // Sun icon for light
         if (mainLogo) { // Check if logo element exists
             mainLogo.src = '../images/thescoutowlb.png'; // Light mode logo
        }
        localStorage.setItem('scouterTheme', 'light');
    }
}

// Check localStorage on page load
const savedTheme = localStorage.getItem('scouterTheme') || 'light'; // Default to light
applyTheme(savedTheme);

// Add click listener to the toggle button
themeToggle.addEventListener('click', () => {
    const isDarkMode = bodyElement.classList.contains('dark-mode');
    applyTheme(isDarkMode ? 'light' : 'dark');
});
// === END THEME TOGGLE LOGIC ===

// === GLOBAL VARIABLES ===
const banner = document.getElementById('gameBanner');
const titleEl = document.getElementById('gameEditionTitle');
const buttonsDiv = document.getElementById('dynamicButtons');
const statusEl = document.getElementById('statusDisplay');
const locationEl = document.getElementById('locationDisplay');
const timerEl = document.getElementById('timer-inner');
const scoreEl = document.getElementById('score-inner');
const blockElement = document.getElementById('block');
const scoreboard = document.getElementById('scoreboard');

let selectedBtn = null;
let score = 0;
let G_CURRENT_GAME_NAME = ""; // <<< ADDED: To store the game name

// === URL PARAMS & MATCH INFO ===
const params = new URLSearchParams(window.location.search);
const event = params.get('event');
const match = params.get('match');
const robot = params.get('robot');
const alliance = params.get('alliance');
const gameFileFromUrl = params.get('game');
const field_id = params.get('field_id');

document.getElementById('eventName').textContent = event ? ` ${event.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}` : 'Unknown Event';
document.getElementById('matchNumber').textContent = match ? `Match #: ${match}` : 'Match #: N/A';
document.getElementById('robotName').textContent = robot ? `Robot #: ${robot}` : 'Robot #: N/A';

// === SERVER-SYNCED TIMER LOGIC ===
let startTime = null; let totalPause = 0; let pausedAt = null; let isActive = 0;
let isPaused = 0; let year = null; let timerTime = 150; let confettiThrown = false;
let playedSong = false; let timeDrift = 0;


function parseUtc(dt) {
  // handles "YYYY-MM-DD HH:MM:SS" or "YYYY-MM-DDTHH:MM:SS"
  if (!dt) return NaN;
  const s = dt.replace(' ', 'T');
  // If no timezone suffix, force UTC
  return Date.parse(s.endsWith('Z') || /[+-]\d\d:\d\d$/.test(s) ? s : (s + 'Z'));
}

async function fetchMatchData() {
  try {
    const url = `../php/getMatchTimer.php?event=${encodeURIComponent(event)}&match=${encodeURIComponent(match)}`;
    const response = await fetch(url, { cache: 'no-store' });
    if (!response.ok) throw new Error(`Status: ${response.status}`);
    const data = await response.json();

    const serverTimeMs = parseUtc(data.server_time);
    const clientTimeMs = Date.now();
    timeDrift = serverTimeMs - clientTimeMs;

    startTime = parseUtc(data.start_time);
    totalPause = Number(data.total_pause_duration) || 0;
    pausedAt = data.paused_at || null;
    isActive = Number(data.active) || 0;
    isPaused = Number(data.pause) || 0;
    year = data.year || null;

  } catch (error) {
    console.error("Error fetching match data:", error);
  }
}


function updateMatchTimer() { /* ... unchanged ... */ if (!startTime || isActive === 0) { timerEl.textContent = "Inactive"; return; } const correctedTimeMs = Date.now() + timeDrift; let elapsedSeconds = (correctedTimeMs - startTime) / 1000 - totalPause; if (isPaused) { timerEl.textContent = "Paused"; return; } const remainingSeconds = Math.max(150 - elapsedSeconds, 0); timerTime = remainingSeconds; if (elapsedSeconds >= 150) { timerEl.textContent = "Finished"; if (!confettiThrown) { throwCrazyConfetti(); confettiThrown = true; } if(!playedSong) { playRandomSong(); playedSong = true; } return; } const minutes = Math.floor(remainingSeconds / 60); const seconds = Math.floor(remainingSeconds % 60); timerEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`; }
setInterval(async () => { fetchMatchData(); updateMatchTimer(); }, 250);

// === DYNAMIC GAME LOADING ===
async function loadBanner(baseName){ /* ... unchanged ... */ const base = `games/logos/${baseName}`; let found = false; for (const ext of ['.png','.svg','.webp']) { try { const r = await fetch(base + ext, { method:'HEAD' }); if (r.ok) { banner.src = base + ext; banner.style.display = 'block'; found = true; return; } } catch(e){} } if (!found) { banner.src = 'games/logos/default.svg'; banner.onerror = () => { banner.style.display = 'none'; }; banner.style.display = 'block'; } }
async function loadGame(file){
  if (!file) {
      console.error("No game file specified in URL.");
      titleEl.textContent = "Error: No Game Specified";
      return; // Stop if no game file
  }
  const baseName = file.replace(/\.json$/,'');
  G_CURRENT_GAME_NAME = baseName; // <<< ADDED: Set the global game name
  titleEl.textContent = baseName + ' Edition'; // Update title text
  loadBanner(baseName); // Load banner

  // Fetch using encodeURIComponent
  const res = await fetch('games/' + encodeURIComponent(file) + '?t=' + new Date().getTime()); 
  if (!res.ok) { console.error('JSON not found:', file); titleEl.textContent = `Error: ${file} not found`; return; }
  const data = await res.json();
  buildButtons(data.buttons || []);
  /* Alliance styling removed, handled by CSS */
}

// --- buildButtons FUNCTION ---
function buildButtons(list){ /* ... unchanged ... */ buttonsDiv.innerHTML = ''; selectedBtn = null; statusEl.textContent = 'Swipe to Submit'; locationEl.textContent = 'Location:'; const createButton = (buttonData, layout) => { if (!buttonData.name || buttonData.name.trim() === '') { const spacer = document.createElement('div'); spacer.className = 'spacer'; return spacer; } const el = document.createElement('div'); el.className = 'button fontBig'; if (buttonData.type === 'offense') el.classList.add('alliance'); if (buttonData.type === 'defense') el.classList.add('opponent'); if (buttonData.type === 'cooperative') el.classList.add('cooperative'); if (layout === 'square-1') el.classList.add('PUC'); if (layout === 'circle-2') el.classList.add('circle'); el.dataset.action = buttonData.code || ''; el.dataset.location = buttonData.location || 'anywhere'; el.dataset.autonPoints = Number(buttonData.autonPoints ?? 0); el.dataset.teleopPoints = Number(buttonData.teleopPoints ?? 0); el.textContent = buttonData.name; el.onclick = () => selectButton(el); return el; }; for (let i = 0; i < list.length; i++) { const b = list[i]; const layout = b.layout || 'rect-1'; if (layout === 'rect-1' || layout === 'square-1') { const btnEl = createButton(b, layout); buttonsDiv.appendChild(btnEl); } else if (layout === 'rect-2' || layout === 'circle-2') { const wrapper = document.createElement('div'); if (!b.name || b.name.trim() === '') { wrapper.className = 'spacer'; const spacerEl1 = createButton(b, layout); wrapper.appendChild(spacerEl1); } else { wrapper.className = 'threeBox'; const btnEl1 = createButton(b, layout); wrapper.appendChild(btnEl1); } const next_b = list[i + 1]; if (next_b && next_b.layout === layout) { i++; const btnEl2 = createButton(list[i], layout); wrapper.appendChild(btnEl2); } else { const explicitSpacer = document.createElement('div'); explicitSpacer.className = 'spacer'; explicitSpacer.style.flex = '1'; wrapper.appendChild(explicitSpacer); } buttonsDiv.appendChild(wrapper); } } }

function selectButton(el){ /* ... unchanged ... */ document.querySelectorAll('.button:not(.spacer), .threeBox:not(.spacer) > .button').forEach(x=>x.classList.remove('selected')); if(el && !el.classList.contains('spacer')) { el.classList.add('selected'); selectedBtn = el; statusEl.textContent = 'Selected: ' + el.textContent; locationEl.textContent = 'Location: ' + el.dataset.location; } else { selectedBtn = null; statusEl.textContent = 'Swipe to Submit'; locationEl.textContent = 'Location:'; } }

// === SUBMISSION & SWIPE LOGIC ===
function showAlert(points) { /* ... unchanged ... */ score += points; scoreEl.textContent = score; }
function handleSwipe(success) {
    if (!selectedBtn) return;
    const action = selectedBtn.dataset.action;
    const location = selectedBtn.dataset.location;
    const timeIntoMatch = 150 - timerTime;
    const isAuton = (150 - timeIntoMatch) >= 135;
    let points = 0;
    if (success) {
        points = isAuton ? Number(selectedBtn.dataset.autonPoints) : Number(selectedBtn.dataset.teleopPoints);
    }
    const result = success ? 'Success' : 'Failure';
    statusEl.textContent = `${selectedBtn.textContent} → ${result} (${points} pts)`;
    if (result === 'Failure') {
        blockElement.classList.add('flashFailure');
        scoreboard.classList.add('shake');
        setTimeout(() => {
            blockElement.classList.remove('flashFailure');
            scoreboard.classList.remove('shake');
        }, 250);
    } else {
        blockElement.classList.add('flashSuccess');
        scoreboard.classList.add('shake');
        setTimeout(() => {
            blockElement.classList.remove('flashSuccess');
            scoreboard.classList.remove('shake');
        }, 250);
    }
    if (timerTime > 0 && timerTime < 150) {
        if ("vibrate" in navigator) {
            navigator.vibrate(200);
        }
        showAlert(points);
        
        // *** UPDATED SUBMISSION DATA ***
        const data = {
            game: G_CURRENT_GAME_NAME, // <<< ADDED
            ip_address: 'user-ip-address',
            field_id: field_id,
            event_name: event,
            match_no: match,
            time_sec: timeIntoMatch,
            robot: robot,
            alliance: alliance,
            action: action,
            location: location,
            result: result,
            points: points
        };
        fetch('../php/insert_submission.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data),
        }).then(response => response.json()).then(responseData => {
            console.log(responseData);
            if (responseData.status !== 'success') {
                saveSubmissionOffline(data);
            }
        }).catch(error => {
            console.error('Error:', error);
            saveSubmissionOffline(data);
        });
    }
}

// === OFFLINE & CONNECTIVITY LOGIC ===
function saveSubmissionOffline(data) {
    const tableBody = document.querySelector('#offlineSubmissions tbody');
    const row = document.createElement('tr');
    
    // *** UPDATED FIELDS ARRAY ***
    const fields = ['game', 'event_name', 'match_no', 'time_sec', 'robot', 'alliance', 'action', 'location', 'result', 'points'];
    fields.forEach(field => {
        const cell = document.createElement('td');
        cell.textContent = data[field];
        row.appendChild(cell);
    });
    tableBody.appendChild(row);
}
function sendOfflineSubmissions() {
    const tableBody = document.querySelector('#offlineSubmissions tbody');
    const rows = Array.from(tableBody.querySelectorAll('tr'));
    rows.forEach((row) => {
        const cells = row.querySelectorAll('td');
        
        // *** UPDATED OFFLINE SUBMISSION DATA ***
        const submissionData = {
            game: cells[0].textContent,       // <<< ADDED
            event_name: cells[1].textContent,
            match_no: cells[2].textContent,
            time_sec: cells[3].textContent,
            robot: cells[4].textContent,
            alliance: cells[5].textContent,
            action: cells[6].textContent,
            location: cells[7].textContent,
            result: cells[8].textContent,
            points: cells[9].textContent,
        };
        fetch('../php/insert_submission.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(submissionData),
        }).then(response => response.json()).then(responseData => {
            if (responseData.status === 'success') {
                row.remove();
            }
        }).catch(error => {
            console.error("Failed to resend offline submission:", error);
        });
    });
}
function checkServerConnectivity() { /* ... unchanged ... */ fetch('../php/server_status.php', { cache: 'no-store' }).then(response => { if (response.ok) { sendOfflineSubmissions(); } }).catch(error => { console.log('Server not reachable.'); }); }
setInterval(checkServerConnectivity, 5000);

// === ADVANCED SWIPE LISTENERS ===
let startX = 0; let startY = 0; let preventSwipeNav = false; /* ... unchanged ... */
blockElement.addEventListener('touchstart', (e) => { startX = e.touches[0].clientX; startY = e.touches[0].clientY; preventSwipeNav = false; }, { passive: false }); blockElement.addEventListener('touchmove', (e) => { const moveX = e.touches[0].clientX - startX; const moveY = e.touches[0].clientY - startY; if (Math.abs(moveX) > Math.abs(moveY)) { preventSwipeNav = true; e.preventDefault(); } }, { passive: false }); document.addEventListener('touchend', (e) => { if (!preventSwipeNav) return; const endX = e.changedTouches[0].clientX; const diff = endX - startX; if (diff > 200) { handleSwipe(true); } else if (diff < -200) { handleSwipe(false); } else { statusEl.textContent = 'Swipe to Submit'; } });

// === HELPER FUNCTIONS ===
function throwCrazyConfetti() { /* ... unchanged ... */ let count = 800; let defaults = { origin: { y: 0.7 } }; function fire(particleRatio, opts) { confetti(Object.assign({}, defaults, opts, { particleCount: Math.floor(count * particleRatio) })); } fire(0.25, { spread: 26, startVelocity: 55 }); fire(0.2, { spread: 60 }); fire(0.35, { spread: 100, decay: 0.91 }); fire(0.1, { spread: 120, startVelocity: 25, decay: 0.92 }); fire(0.1, { spread: 140, startVelocity: 45 }); }
function playRandomSong() { /* ... unchanged ... */ const songs = [ { name: "Imperial March", pattern: [500, 200, 500, 200, 1000] }, { name: "Jaws", pattern: [500, 1000, 500, 1000] }, { name: "Mahna Mahna", pattern: [300, 150, 300, 150, 300, 500, 100, 100, 100, 100, 100, 100, 100, 100, 100, 100] } ]; const randomSong = songs[Math.floor(Math.random() * songs.length)]; console.log("Playing song:", randomSong.name); if ("vibrate" in navigator) { navigator.vibrate(randomSong.pattern); } }

// === PAGE LOAD LISTENER ===
document.addEventListener('DOMContentLoaded', ()=>{
  if (gameFileFromUrl) {
    loadGame(gameFileFromUrl);
  } else {
    console.error("Game parameter ('game=...') is missing from the URL.");
    titleEl.textContent = "Error: Game parameter missing";
  }
});
</script>
</body>
</html>