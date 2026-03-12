<?php
// Include the database connection file
require_once '../php/database_connection.php';

try {
    // --- NEW: Load Game Files ---
    $gamesDir = __DIR__ . '/../scouter/games';
    $gameFiles = [];
    if (is_dir($gamesDir)) {
        $files = glob($gamesDir . DIRECTORY_SEPARATOR . '*.json');
        if ($files !== false && count($files) > 0) {
            sort($files, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($files as $f) {
                $basename = basename($f, '.json');
                if (strlen($basename) > 0) $gameFiles[] = $basename;
            }
        }
    }

    // Generate a new 4-digit code
    if (isset($_POST['generate_code'])) {
        $new_code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $pdo->prepare("UPDATE codes SET is_active = 0")->execute();
        $stmt = $pdo->prepare("INSERT INTO codes (code, is_active) VALUES (:new_code, 1)");
        $stmt->bindParam(':new_code', $new_code);
        $stmt->execute();
    }

    // Begin Match: Insert or update match
    if (isset($_POST['begin_match'])) {
        $year = gmdate('Y');
        $event = $_POST['event'];
        $match_number = $_POST['match_number'];
        $game = $_POST['game'];

        if (!empty($year) && !empty($event) && !empty($match_number) && !empty($game)) {
            $sql_delete_scouting = "DELETE FROM scouting_submissions
                                    WHERE event_name = :event AND match_no = :match_number AND game = :game";
            $stmt_delete_scouting = $pdo->prepare($sql_delete_scouting);
            $stmt_delete_scouting->execute([
                ':event' => $event,
                ':match_number' => $match_number,
                ':game' => $game
            ]);

            $pdo->prepare("UPDATE matches SET active = 0 WHERE active = 1")->execute();

            $sql_delete_match = "DELETE FROM matches
                                 WHERE year = :year AND event = :event AND match_number = :match_number AND game = :game";
            $stmt_delete_match = $pdo->prepare($sql_delete_match);
            $stmt_delete_match->execute([
                ':year' => $year,
                ':event' => $event,
                ':match_number' => $match_number,
                ':game' => $game
            ]);

            $sql_insert = "INSERT INTO matches (year, event, game, match_number, start_time, pause, total_pause_duration, active)
                           VALUES (:year, :event, :game, :match_number, UTC_TIMESTAMP(), 0, 0, 1)";
            $stmt_insert = $pdo->prepare($sql_insert);
            $stmt_insert->execute([
                ':year' => $year,
                ':event' => $event,
                ':game' => $game,
                ':match_number' => $match_number,
            ]);
        }
    }

    // Pause/Unpause Match
    if (isset($_POST['toggle_pause'])) {
        $activeMatch = $pdo->query("SELECT * FROM matches WHERE active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);

        if ($activeMatch) {
            if ($activeMatch['pause'] == 0) {
                $currentTime = gmdate('Y-m-d H:i:s');
                $stmt = $pdo->prepare("UPDATE matches SET pause = 1, paused_at = :current_time WHERE id = :id");
                $stmt->execute([':current_time' => $currentTime, ':id' => $activeMatch['id']]);
            } else {
                $pausedAtTimestamp = strtotime($activeMatch['paused_at'] . ' UTC');
                $currentUtcTimestamp = (new DateTime("now", new DateTimeZone("UTC")))->getTimestamp();
                $pausedDuration = $currentUtcTimestamp - $pausedAtTimestamp;

                $sql = "UPDATE matches
                        SET pause = 0,
                            total_pause_duration = total_pause_duration + :paused_duration,
                            paused_at = NULL
                        WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':paused_duration' => $pausedDuration, ':id' => $activeMatch['id']]);
            }
        }
    }

    // Fetch only active events
    $activeEvents = $pdo->query("SELECT distinct event_name FROM active_event")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch the current active code
    $active_code = $pdo->query("SELECT * FROM codes WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    // Fetch the current active match
    $activeMatch = $pdo->query("SELECT * FROM matches WHERE active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    // Form state helpers
    $isMatchActive = ($activeMatch != null);
    $current_event_name = null;
    $current_match_number = null;
    $current_game_name = null;

    if ($isMatchActive) {
        $current_event_name = $activeMatch['event'];
        $current_match_number = $activeMatch['match_number'];

        // Try to get game from active_event table (optional)
        try {
            $stmt_game = $pdo->prepare("SELECT game FROM active_event WHERE event_name = :event AND match_number = :match LIMIT 1");
            $stmt_game->execute([
                ':event' => $current_event_name,
                ':match' => $current_match_number
            ]);
            $current_game_name = $stmt_game->fetchColumn();
        } catch (PDOException $e) {
            $current_game_name = null;
        }
    }

    // Convert UTC start_time to UTC milliseconds for JS
    if ($activeMatch) {
        $activeMatch['start_time_utc_ms'] = strtotime($activeMatch['start_time'] . ' UTC') * 1000;
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Owl Admin</title>
  <script src="../js/jquery-3.7.1.min.js"></script>
  <link rel="stylesheet" href="../css/select.css">

  <style>
    body, html { font-family: 'Comfortaa', sans-serif; margin:0; padding:0; background:#222; color:#eee; text-align:center; }
    * { box-sizing: border-box; }
    #startMatch { width: 100vw; background:#333; color:#fff; padding:20px; font-size:1.5rem; }
    #lowerContainer { display:grid; gap:10px; padding:10px; grid-template-columns: repeat(auto-fit, minmax(395px, 1fr)); justify-content:center; }
    #red1, #red2, #red3, #blue1, #blue2, #blue3 { width:395px; height:395px; background:#222; display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.2rem; }
    @media (max-width: 768px) {
      #lowerContainer { display:flex; flex-wrap:wrap; justify-content:center; }
      #red1, #red2, #red3, #blue1, #blue2, #blue3 { width:100%; max-width:395px; }
    }
    .logo { width:100%; max-width:400px; display:block; margin:0 auto 1rem auto; }
    select{ min-width:200px; }
    input, button{
      min-width:200px; font-size:1.1rem; padding:12px; border:1px solid #fff; background:#222; color:#fff; border-radius:5px;
    }
    button{ cursor:pointer; }
    .flash { animation: flashEffect 1s linear; }
    @keyframes flashEffect { 0%{background:yellow;} 50%{background:red;} 100%{background:yellow;} }

    .robot-card { width:395px; height:395px; color:#fff; padding:10px; border-radius:10px; text-align:center; box-shadow:2px 2px 10px rgba(255,255,255,0.2); transition:transform 0.3s; }
    .robot-card:hover { transform:scale(1.05); }
    .robot-number { font-size:1.8rem; font-weight:bold; color:#CCC; }
    .total-points { font-size:1.3rem; font-weight:bold; margin:10px 0; }
    .total-points span { color:#fff; font-size:1.5rem; }
    .activities-title { font-size:1.2rem; margin-top:15px; border-bottom:2px solid #CCC; padding-bottom:5px; }
    .activities-list { list-style:none; padding:0; text-align:left; font-size:1rem; margin-top:10px; }
    .activities-list li { display:flex; justify-content:space-between; background:#333; padding:8px; border-radius:5px; margin-bottom:5px; }
    .timestamp { color:#FF4500; font-weight:bold; }
    .action { font-weight:bold; color:#00BFFF; }
    .result { color:#FF69B4; font-style:italic; }
    .redRobots{ background:#C0392B; }
    .blueRobots{ background:#2C3E50; }
    #logoOuter{ display:inline-block; }

    /* Auto sync UI */
    #autoSyncWrap{
      margin-top: 10px;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      font-size: 1rem;
      background:#222;
      padding: 10px 12px;
      border: 1px solid #555;
      border-radius: 8px;
    }
    #syncStatus{
      font-size: 0.95rem;
      opacity: 0.9;
      min-width: 220px;
      text-align: left;
    }
    #syncNowBtn{ min-width: 140px; }
  </style>
</head>
<body>

<div id="logoOuter">
  <a href=".."><img src="../images/owladmin.png" class="logo" alt="Logo"></a>
</div>

<div id="startMatch">
  <form method="POST" id="matchForm">
    <select name="event" id="delay">
      <option value="">Delay</option>
      <?php for($i=0;$i<=14;$i++): ?>
        <option value="<?= $i ?>"><?= $i ?></option>
      <?php endfor; ?>
    </select>

    <select name="game" id="game" required <?= $isMatchActive ? 'disabled' : '' ?>>
      <option value="">Game</option>
      <?php foreach ($gameFiles as $g): $gameName = htmlspecialchars($g); ?>
        <option value="<?= $gameName ?>" <?= ($gameName == $current_game_name) ? 'selected' : '' ?>>
          <?= $gameName ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="event" id="event" required <?= $isMatchActive ? 'disabled' : '' ?>>
      <option value="">Event</option>
      <?php foreach ($activeEvents as $ev): $eventName = htmlspecialchars($ev['event_name']); ?>
        <option value="<?= $eventName ?>" <?= ($eventName == $current_event_name) ? 'selected' : '' ?>>
          <?= $eventName ?>
        </option>
      <?php endforeach; ?>
    </select>

    <input type="number" name="match_number" id="match_number" required min="1" placeholder="Enter Match Number"
           value="<?= htmlspecialchars($current_match_number) ?>" <?= $isMatchActive ? 'disabled' : '' ?>>

    <button type="submit" name="begin_match" <?= $isMatchActive ? 'disabled' : '' ?>>
      <?= $isMatchActive ? 'Match in Progress' : 'Begin Match' ?>
    </button>
  </form>

  <!-- ===========================
       AUTO SYNC CONTROLS (NEW)
       ===========================
       - Checkbox defaults unchecked
       - Disabled unless game/event/match_number are filled
       - “Sync Now” button to test manually
       - Interval dropdown controls how often we sync
  -->
  <div id="autoSyncWrap">
    <label style="display:inline-flex; align-items:center; gap:8px;">
      <input type="checkbox" id="autoSyncCheck">
      Auto Sync
    </label>

    <label style="display:inline-flex; align-items:center; gap:8px;">
      Every
      <select id="autoSyncSeconds" style="min-width:90px;">
        <option value="3">3s</option>
        <option value="5" selected>5s</option>
        <option value="10">10s</option>
        <option value="15">15s</option>
        <option value="30">30s</option>
      </select>
    </label>

    <button id="syncNowBtn" type="button">Sync Now</button>
    <div id="syncStatus">Auto sync is off.</div>
  </div>

  <h2>Match Timer</h2>
  <div>
    <?php if ($activeMatch): ?>
      <p id="activeMatchinfo">
        Match <strong><?= htmlspecialchars($activeMatch['match_number']) ?></strong> for
        <strong><?= htmlspecialchars($activeMatch['event']) ?></strong>(<?= gmdate("Y") ?>) is active.
      </p>
      <p id="timer">Loading...</p>
    <?php else: ?>
      <p>No active match.</p>
    <?php endif; ?>
  </div>
</div>

<div id="lowerContainer">
  <div id="red1" class="redRobots">Red 1</div>
  <div id="red2" class="redRobots">Red 2</div>
  <div id="red3" class="redRobots">Red 3</div>
  <div id="blue1">Blue 1</div>
  <div id="blue2">Blue 2</div>
  <div id="blue3">Blue 3</div>
</div>

<button onclick="openForm()">Open Scouting Form</button>

<div id="scoutingModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(0,0,0,0.7); z-index:1000;">
  <div style="position:relative; width:90%; max-width:700px; margin:5% auto; background:#222; padding:20px; border-radius:8px;">
    <span onclick="closeForm()" style="position:absolute; top:10px; right:15px; font-size:20px; color:white; cursor:pointer;">&times;</span>
    <iframe src="scouting_form.php" style="width:100%; height:600px; border:none;"></iframe>
  </div>
</div>

<script>
function openForm(){ document.getElementById("scoutingModal").style.display = "block"; }
function closeForm(){ document.getElementById("scoutingModal").style.display = "none"; }
</script>

<script>
/* =========================================================
   AUTO SYNC (NEW)
   =========================================================
   What it does:
   - Calls your sync endpoint on a timer:
       sync_scouting_submissions_uuid.php
   - Sends POST: event, match_number, game
   - If you only send event, it syncs everything for that event
   - We always send all 3 when they exist, so it stays tight

   IMPORTANT:
   - This is “best effort”. If it fails, it keeps trying.
   - It only turns on if dropdowns/fields are not blank.
*/

const autoCheck = document.getElementById('autoSyncCheck');
const autoSecs  = document.getElementById('autoSyncSeconds');
const syncNowBtn = document.getElementById('syncNowBtn');
const statusEl  = document.getElementById('syncStatus');

let autoTimer = null;
let syncInFlight = false;

// Helper: are required form fields filled?
function canEnableAutoSync() {
  const eventVal = document.getElementById('event').value.trim();
  const matchVal = document.getElementById('match_number').value.trim();
  const gameVal  = document.getElementById('game').value.trim();
  return eventVal !== '' && matchVal !== '' && gameVal !== '';
}

// Helper: enable/disable the checkbox based on dropdowns
function refreshAutoSyncUI() {
  const ok = canEnableAutoSync();

  // If not ok, force auto sync OFF and disable checkbox
  if (!ok) {
    stopAutoSync();
    autoCheck.checked = false;
    autoCheck.disabled = true;
    statusEl.textContent = 'Auto sync disabled (select Game, Event, Match).';
    syncNowBtn.disabled = true;
    return;
  }

  // If ok, allow checkbox usage
  autoCheck.disabled = false;
  syncNowBtn.disabled = false;

  if (!autoCheck.checked) {
    statusEl.textContent = 'Auto sync is off.';
  }
}

// Call sync endpoint once
async function runSyncOnce() {
  if (syncInFlight) return;
  syncInFlight = true;

  const eventVal = document.getElementById('event').value.trim();
  const matchVal = document.getElementById('match_number').value.trim();
  const gameVal  = document.getElementById('game').value.trim();

  statusEl.style.color = '#fff';
  statusEl.textContent = 'Syncing...';

  try {
    const form = new FormData();
    form.append('event', eventVal);
    form.append('match_number', matchVal);
    form.append('game', gameVal);

    const res = await fetch('../php/sync_scouting_submissions_uuid.php?cacheBust=' + Date.now(), {
      method: 'POST',
      body: form
    });

    const data = await res.json().catch(async () => {
      // If the server returned non-JSON, show the raw text
      const t = await res.text();
      throw new Error('Sync endpoint did not return JSON. Raw: ' + t);
    });

    if (!res.ok || !data.success) {
      statusEl.style.color = '#ff6b6b'; // red
      statusEl.textContent = 'Sync FAILED: ' + (data.message || ('HTTP ' + res.status));
      console.error('Sync failed:', data);
      return;
    }

    // success
    const now = new Date();
    statusEl.style.color = '#6bff95'; // green
    statusEl.textContent =
      `Sync OK @ ${now.toLocaleTimeString()} | local_rows=${data.local_rows} | hosted_affected=${data.hosted_affected}`;

  } catch (err) {
    statusEl.style.color = '#ff6b6b';
    statusEl.textContent = 'Sync FAILED: ' + err.message;
    console.error(err);
  } finally {
    syncInFlight = false;
  }
}

// Start/stop timer
function startAutoSync() {
  stopAutoSync(); // clear any existing timer

  const seconds = parseInt(autoSecs.value, 10) || 5;
  statusEl.textContent = 'Auto sync on (every ' + seconds + 's).';

  // Run once immediately, then on an interval
  runSyncOnce();
  autoTimer = setInterval(runSyncOnce, seconds * 1000);
}

function stopAutoSync() {
  if (autoTimer) {
    clearInterval(autoTimer);
    autoTimer = null;
  }
}

// Events
autoCheck.addEventListener('change', () => {
  if (!canEnableAutoSync()) {
    autoCheck.checked = false;
    refreshAutoSyncUI();
    return;
  }
  if (autoCheck.checked) startAutoSync();
  else {
    stopAutoSync();
    statusEl.textContent = 'Auto sync is off.';
  }
});

// If they change the interval while auto sync is on, restart the timer
autoSecs.addEventListener('change', () => {
  if (autoCheck.checked) startAutoSync();
});

// Manual sync button
syncNowBtn.addEventListener('click', () => {
  if (!canEnableAutoSync()) {
    statusEl.textContent = 'Select Game, Event, Match first.';
    return;
  }
  runSyncOnce();
});

// When dropdowns change, reevaluate if autosync can be enabled
['event','match_number','game'].forEach(id => {
  const el = document.getElementById(id);
  el.addEventListener('change', refreshAutoSyncUI);
  el.addEventListener('keyup', refreshAutoSyncUI);
});

// Run on page load
refreshAutoSyncUI();
</script>

<!-- ======================
     YOUR EXISTING SCRIPTS
     ====================== -->
<script>
  // These variables are initialized from your server data.
  let startTimeMs = <?= json_encode($activeMatch['start_time_utc_ms'] ?? null) ?>;
  let totalPause = <?= json_encode($activeMatch['total_pause_duration'] ?? 0) ?>;
  const isMatchActive = <?= json_encode($isMatchActive) ?>;
  let isPaused = <?= json_encode((bool)($activeMatch['pause'] ?? 0)) ?>;
  const matchId = <?= json_encode($activeMatch['id'] ?? null) ?>;

  let autoPauseTriggered = false;
  let autoUnpauseTriggered = false;

  function updatePauseStatus() {
    if (!matchId) return;
    fetch('../php/get_pause.php?cacheBust=' + Date.now(), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ match_id: matchId })
    })
    .then(r => r.json())
    .then(data => {
      isPaused = Boolean(data.pause);
      totalPause = data.total_pause_duration;
    })
    .catch(err => console.error("Error fetching pause:", err));
  }
  setInterval(updatePauseStatus, 1000);

  function updateTimer() {
    const timerElement = document.getElementById('timer');
    if (!startTimeMs) { timerElement.textContent = "No active match."; return; }

    const realElapsedSeconds = (Date.now() - startTimeMs) / 1000;
    const delay = parseInt(document.getElementById('delay').value) || 0;
    const elapsedGameTime = (Date.now() - startTimeMs) / 1000 - totalPause;

    if (!autoPauseTriggered && realElapsedSeconds >= 15) {
      fetch('../php/toggle_pause.php?cacheBust=' + Date.now(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ match_id: matchId })
      }).catch(console.error);
      autoPauseTriggered = true;
    }
    else if (autoPauseTriggered && !autoUnpauseTriggered && realElapsedSeconds >= (15 + delay)) {
      fetch('../php/toggle_pause.php?cacheBust=' + Date.now(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ match_id: matchId })
      }).catch(console.error);
      autoUnpauseTriggered = true;
    }

    if (isPaused) { timerElement.textContent = "Paused"; return; }

    let remainingSeconds = Math.max(150 - elapsedGameTime, 0);
    if (remainingSeconds === 0) {
      timerElement.textContent = "Match Over";
      clearInterval(timerInterval);
      autoPauseTriggered = false;
      autoUnpauseTriggered = false;
      return;
    }

    const minutes = Math.floor(remainingSeconds / 60);
    const seconds = Math.floor(remainingSeconds % 60);
    timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')} remaining`;
  }

  const timerInterval = setInterval(updateTimer, 250);
  if (startTimeMs) updateTimer();
</script>

<script>
  function fetchMatchData() {
    fetch("get_match_data.php?cacheBust=" + Date.now())
      .then(r => r.json())
      .then(data => {
        if (data.error) return;

        ["red1","red2","red3","blue1","blue2","blue3"].forEach(id => {
          const div = document.getElementById(id);
          if (!data[id]) { div.innerHTML = `<p>No data</p>`; return; }

          const { robot_number, total_points, activities, flash } = data[id];
          const allianceClass = id.includes("red") ? "redRobots" : "blueRobots";

          div.innerHTML = `
            <div class="robot-card ${allianceClass}">
              <h2 class="robot-number">🤖 Robot #${robot_number}</h2>
              <p class="total-points">Total Points: <span>${total_points}</span></p>
              <h3 class="activities-title">Last 5 Activities</h3>
              <ul class="activities-list">
                ${activities.map(act => `
                  <li>
                    <span class="timestamp">${new Date(act.timestamp).toLocaleTimeString()}</span>
                    <span class="action">${act.action}:</span>
                    <span class="result">${act.result}</span>
                  </li>
                `).join("")}
              </ul>
            </div>
          `;

          if (flash) {
            div.classList.add("flash");
            setTimeout(() => div.classList.remove("flash"), 1000);
          }
        });
      })
      .catch(err => console.error("Error fetching match data:", err));
  }

  setInterval(fetchMatchData, 1000);
  fetchMatchData();

  function setNextMatch(){
    fetch('get_active_event.php?cacheBust=' + Date.now())
      .then(r => r.json())
      .then(data => {
        const eventSelect = document.getElementById('event');
        const matchInput  = document.getElementById('match_number');
        const gameSelect  = document.getElementById('game');
        const beginButton = document.querySelector('button[name="begin_match"]');

        eventSelect.value = data.activeEventName;
        matchInput.value  = data.activeMatchNumber;
        gameSelect.value  = data.activeGameName;

        eventSelect.disabled = false;
        matchInput.disabled  = false;
        gameSelect.disabled  = false;
        beginButton.disabled = false;
        beginButton.textContent = 'Begin Match';

        // Re-check if auto sync can be enabled after we set values
        refreshAutoSyncUI();
      })
      .catch(err => console.error('Error fetching active event data:', err));
  }

  if (!isMatchActive) setNextMatch();

  document.getElementById('delay').addEventListener('change', function() {
    let delayValue = this.value;
    if (delayValue !== "") {
      fetch('../php/insert_delay.php?cacheBust=' + Date.now(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ delay: delayValue })
      }).catch(console.error);
    }
  });

  function fetchDelay() {
    $.ajax({
      type: 'POST',
      url: '../php/get_delay.php',
      dataType: 'json',
      cache: false,
      success: function(response) {
        if (response.delay !== null) {
          document.getElementById('delay').value = response.delay;
        }
      },
      error: function(xhr, status, error) {
        console.error("Error fetching delay:", error);
      }
    });
  }
  fetchDelay();
</script>

</body>
</html>