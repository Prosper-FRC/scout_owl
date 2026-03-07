<?php
// **********  Stuff We Need to add!!!!!!!!!!! **************
// 1. Make the field number persist on load/ refresh when game ends
// 2. Make the game persist
// 3. Test 2 matches at the same time
// 
// 
// 
// 
// 
// Add to all matches tables
// ALTER TABLE matches DROP INDEX match_event_year;
// ALTER TABLE matches ADD UNIQUE KEY match_event_year (year, event, match_number, field_id, game);



require_once '../php/database_connection.php';

// Safe defaults so page still renders if DB action fails
$selected_field_id = 1;
$gameFiles = [];
$activeEvents = [];
$active_code = null;
$activeMatch = null;
$isMatchActive = false;
$current_event_name = null;
$current_match_number = null;
$current_game_name = null;
$pageError = null;

try {
    // --- Determine selected field_id (GET -> POST -> default 1) ---
    if (isset($_GET['field_id']) && $_GET['field_id'] !== '') {
        $selected_field_id = (int)$_GET['field_id'];
    } elseif (isset($_POST['field_id']) && $_POST['field_id'] !== '') {
        $selected_field_id = (int)$_POST['field_id'];
    }
    if ($selected_field_id <= 0) $selected_field_id = 1;

    // --- Load Game Files ---
    $gamesDir = __DIR__ . '/../scouter/games';
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
        $new_code = str_pad((string)rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $pdo->prepare("UPDATE codes SET is_active = 0")->execute();
        $stmt = $pdo->prepare("INSERT INTO codes (code, is_active) VALUES (:new_code, 1)");
        $stmt->bindParam(':new_code', $new_code);
        $stmt->execute();
    }

    // Begin Match: Insert or update match (FIELD-SCOPED)
    if (isset($_POST['begin_match'])) {
        $year = gmdate('Y');
        $event = trim($_POST['event'] ?? '');
        $match_number = trim((string)($_POST['match_number'] ?? ''));
        $game = trim($_POST['game'] ?? '');
        $field_id = isset($_POST['field_id']) ? (int)$_POST['field_id'] : $selected_field_id;
        if ($field_id <= 0) $field_id = 1;

        // Keep selected field in sync after submit
        $selected_field_id = $field_id;

        if ($year !== '' && $event !== '' && $match_number !== '' && $game !== '') {
            $pdo->beginTransaction();

            try {
                // If scouting submissions are field-specific, delete only for this field
                $sql_delete_scouting = "DELETE FROM scouting_submissions
                                        WHERE event_name = :event
                                          AND match_no = :match_number
                                          AND game = :game
                                          AND field_id = :field_id";
                $stmt_delete_scouting = $pdo->prepare($sql_delete_scouting);
                $stmt_delete_scouting->execute([
                    ':event' => $event,
                    ':match_number' => $match_number,
                    ':game' => $game,
                    ':field_id' => $field_id
                ]);

                // Only deactivate matches on THIS field
                $stmt = $pdo->prepare("UPDATE matches SET active = 0 WHERE active = 1 AND field_id = :field_id");
                $stmt->execute([':field_id' => $field_id]);

                // Delete existing match row for same identity + field
                // IMPORTANT: your DB unique index should include field_id if you want concurrent same match numbers on different fields
                $sql_delete_match = "DELETE FROM matches
                                     WHERE year = :year
                                       AND event = :event
                                       AND match_number = :match_number
                                       AND game = :game
                                       AND field_id = :field_id";
                $stmt_delete_match = $pdo->prepare($sql_delete_match);
                $stmt_delete_match->execute([
                    ':year' => $year,
                    ':event' => $event,
                    ':match_number' => $match_number,
                    ':game' => $game,
                    ':field_id' => $field_id
                ]);

                // Insert new active match on this field
                $sql_insert = "INSERT INTO matches
                                (year, event, game, match_number, field_id, start_time, pause, total_pause_duration, active)
                               VALUES
                                (:year, :event, :game, :match_number, :field_id, UTC_TIMESTAMP(), 0, 0, 1)";
                $stmt_insert = $pdo->prepare($sql_insert);
                $stmt_insert->execute([
                    ':year' => $year,
                    ':event' => $event,
                    ':game' => $game,
                    ':match_number' => $match_number,
                    ':field_id' => $field_id
                ]);

                $pdo->commit();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
        }
    }

    // Pause/Unpause Match (FIELD-SCOPED if used via POST on this page)
    if (isset($_POST['toggle_pause'])) {
        $field_id = isset($_POST['field_id']) ? (int)$_POST['field_id'] : $selected_field_id;
        if ($field_id <= 0) $field_id = 1;

        $stmt = $pdo->prepare("SELECT * FROM matches WHERE active = 1 AND field_id = :field_id LIMIT 1");
        $stmt->execute([':field_id' => $field_id]);
        $activeMatch = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($activeMatch) {
            if ((int)$activeMatch['pause'] === 0) {
                $currentTime = gmdate('Y-m-d H:i:s');
                $stmt2 = $pdo->prepare("UPDATE matches SET pause = 1, paused_at = :current_time WHERE id = :id");
                $stmt2->execute([':current_time' => $currentTime, ':id' => $activeMatch['id']]);
            } else {
                $pausedAtTimestamp = strtotime($activeMatch['paused_at'] . ' UTC');
                $currentUtcTimestamp = (new DateTime("now", new DateTimeZone("UTC")))->getTimestamp();
                $pausedDuration = $currentUtcTimestamp - $pausedAtTimestamp;

                $sql = "UPDATE matches
                        SET pause = 0,
                            total_pause_duration = total_pause_duration + :paused_duration,
                            paused_at = NULL
                        WHERE id = :id";
                $stmt2 = $pdo->prepare($sql);
                $stmt2->execute([':paused_duration' => $pausedDuration, ':id' => $activeMatch['id']]);
            }
        }
    }

    // Fetch only active events
    $activeEvents = $pdo->query("SELECT DISTINCT event_name FROM active_event")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch the current active code
    $active_code = $pdo->query("SELECT * FROM codes WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    // Fetch the current active match FOR THIS FIELD
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE active = 1 AND field_id = :field_id LIMIT 1");
    $stmt->execute([':field_id' => $selected_field_id]);
    $activeMatch = $stmt->fetch(PDO::FETCH_ASSOC);

    // Form state helpers (field-specific)
    $isMatchActive = ($activeMatch != null);

    if ($isMatchActive) {
        $current_event_name = $activeMatch['event'] ?? null;
        $current_match_number = $activeMatch['match_number'] ?? null;
        $current_game_name = $activeMatch['game'] ?? null;

        // Try to get game from active_event table (optional)
        try {
            $stmt_game = $pdo->prepare("SELECT game FROM active_event WHERE event_name = :event AND match_number = :match AND field_id = :field_id LIMIT 1");
            $stmt_game->execute([
                ':event' => $current_event_name,
                ':match' => $current_match_number,
                ':field_id' => $selected_field_id
            ]);
            $gameFromActiveEvent = $stmt_game->fetchColumn();

            if ($gameFromActiveEvent !== false && $gameFromActiveEvent !== null && $gameFromActiveEvent !== '') {
                $current_game_name = $gameFromActiveEvent;
            } else {
                $stmt_game2 = $pdo->prepare("SELECT game FROM active_event WHERE event_name = :event AND match_number = :match LIMIT 1");
                $stmt_game2->execute([
                    ':event' => $current_event_name,
                    ':match' => $current_match_number
                ]);
                $gameFromActiveEvent2 = $stmt_game2->fetchColumn();
                if ($gameFromActiveEvent2 !== false && $gameFromActiveEvent2 !== null && $gameFromActiveEvent2 !== '') {
                    $current_game_name = $gameFromActiveEvent2;
                }
            }
        } catch (PDOException $e) {
            // keep current_game_name from matches row
        }
    }

    // Convert UTC start_time to UTC milliseconds for JS
    if ($activeMatch && !empty($activeMatch['start_time'])) {
        $activeMatch['start_time_utc_ms'] = strtotime($activeMatch['start_time'] . ' UTC') * 1000;
    }

} catch (PDOException $e) {
    $pageError = $e->getMessage();
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
    select { min-width:200px; }
    input, button {
      min-width:200px; font-size:1.1rem; padding:12px; border:1px solid #fff; background:#222; color:#fff; border-radius:5px;
    }
    button { cursor:pointer; }
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
    .redRobots { background:#C0392B; }
    .blueRobots { background:#2C3E50; }
    #logoOuter { display:inline-block; }
    #autoSyncWrap {
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
    #syncStatus {
      font-size: 0.95rem;
      opacity: 0.9;
      min-width: 220px;
      text-align: left;
    }
    #syncNowBtn { min-width: 140px; }
    .page-error {
      margin: 10px auto 0 auto;
      padding: 12px 14px;
      max-width: 900px;
      background: #5b1d1d;
      border: 1px solid #ff7d7d;
      color: #fff;
      border-radius: 8px;
      font-size: 0.95rem;
      text-align: left;
      word-break: break-word;
    }
  </style>
</head>
<body>

<div id="logoOuter">
  <a href=".."><img src="../images/owladmin.png" class="logo" alt="Logo"></a>
</div>

<?php if ($pageError): ?>
  <div class="page-error">
    Error: <?= htmlspecialchars($pageError) ?>
  </div>
<?php endif; ?>

<div id="startMatch">
  <form method="POST" id="matchForm">
    <select name="field_id" id="field_id" required <?= $isMatchActive ? 'disabled' : '' ?>>
      <option value="">Field</option>
      <?php for ($f = 1; $f <= 6; $f++): ?>
        <option value="<?= $f ?>" <?= ((int)$selected_field_id === $f) ? 'selected' : '' ?>>Field <?= $f ?></option>
      <?php endfor; ?>
    </select>

    <select name="delay" id="delay">
      <option value="">Delay</option>
      <?php for ($i = 0; $i <= 14; $i++): ?>
        <option value="<?= $i ?>"><?= $i ?></option>
      <?php endfor; ?>
    </select>

    <select name="game" id="game" required <?= $isMatchActive ? 'disabled' : '' ?>>
      <option value="">Game</option>
      <?php foreach ($gameFiles as $g): $gameName = htmlspecialchars($g); ?>
        <option value="<?= $gameName ?>" <?= ($gameName === (string)$current_game_name) ? 'selected' : '' ?>>
          <?= $gameName ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="event" id="event" required <?= $isMatchActive ? 'disabled' : '' ?>>
      <option value="">Event</option>
      <?php foreach ($activeEvents as $ev): $eventName = htmlspecialchars($ev['event_name']); ?>
        <option value="<?= $eventName ?>" <?= ($eventName === (string)$current_event_name) ? 'selected' : '' ?>>
          <?= $eventName ?>
        </option>
      <?php endforeach; ?>
    </select>

    <input type="number" name="match_number" id="match_number" required min="1" placeholder="Enter Match Number"
           value="<?= htmlspecialchars((string)$current_match_number) ?>" <?= $isMatchActive ? 'disabled' : '' ?>>

    <button type="submit" name="begin_match" <?= $isMatchActive ? 'disabled' : '' ?>>
      <?= $isMatchActive ? 'Match in Progress' : 'Begin Match' ?>
    </button>
  </form>

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
        Field <strong><?= (int)$selected_field_id ?></strong>:
        Match <strong><?= htmlspecialchars((string)$activeMatch['match_number']) ?></strong> for
        <strong><?= htmlspecialchars((string)$activeMatch['event']) ?></strong> (<?= gmdate("Y") ?>) is active.
      </p>
      <p id="timer">Loading...</p>
    <?php else: ?>
      <p>No active match on Field <?= (int)$selected_field_id ?>.</p>
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
   <iframe src="scouting_form.php?cacheBust=<?= time() ?>" style="width:100%; height:600px; border:none;"></iframe>
  </div>
</div>

<script>
function openForm(){ document.getElementById("scoutingModal").style.display = "block"; }
function closeForm(){ document.getElementById("scoutingModal").style.display = "none"; }
</script>

<script>
let autoTimer = null;
let syncInFlight = false;

const autoCheck = document.getElementById('autoSyncCheck');
const autoSecs  = document.getElementById('autoSyncSeconds');
const syncNowBtn = document.getElementById('syncNowBtn');
const statusEl  = document.getElementById('syncStatus');

function getFieldId() {
  const fieldEl = document.getElementById('field_id');
  const v = fieldEl ? fieldEl.value : '';
  return v ? v.trim() : '';
}

function canEnableAutoSync() {
  const fieldVal = getFieldId();
  const eventVal = document.getElementById('event')?.value?.trim() || '';
  const matchVal = document.getElementById('match_number')?.value?.trim() || '';
  const gameVal  = document.getElementById('game')?.value?.trim() || '';
  return fieldVal !== '' && eventVal !== '' && matchVal !== '' && gameVal !== '';
}

function stopAutoSync() {
  if (autoTimer) {
    clearInterval(autoTimer);
    autoTimer = null;
  }
}

function refreshAutoSyncUI() {
  const ok = canEnableAutoSync();

  if (!ok) {
    stopAutoSync();
    autoCheck.checked = false;
    autoCheck.disabled = true;
    syncNowBtn.disabled = true;
    statusEl.style.color = '#fff';
    statusEl.textContent = 'Auto sync disabled (select Field, Game, Event, Match).';
    return;
  }

  autoCheck.disabled = false;
  syncNowBtn.disabled = false;

  if (!autoCheck.checked) {
    statusEl.style.color = '#fff';
    statusEl.textContent = 'Auto sync is off.';
  }
}

async function fetchJsonOnce(url, options) {
  const res = await fetch(url, options);
  const text = await res.text();

  let data;
  try {
    data = JSON.parse(text);
  } catch {
    const snippet = text ? text.slice(0, 500) : '';
    throw new Error(`Sync endpoint returned non-JSON (HTTP ${res.status}). ${snippet}`);
  }

  data.__httpStatus = res.status;
  data.__ok = res.ok;
  return data;
}

async function runSyncOnce() {
  if (syncInFlight) return;
  syncInFlight = true;

  const fieldVal = getFieldId();
  const eventVal = document.getElementById('event')?.value?.trim() || '';
  const matchVal = document.getElementById('match_number')?.value?.trim() || '';
  const gameVal  = document.getElementById('game')?.value?.trim() || '';

  statusEl.style.color = '#fff';
  statusEl.textContent = 'Syncing...';

  try {
    const form = new FormData();
    form.append('field_id', fieldVal);
    form.append('event', eventVal);
    form.append('match_number', matchVal);
    form.append('game', gameVal);

    const data = await fetchJsonOnce('../php/sync_scouting_submissions_uuid.php?cacheBust=' + Date.now(), {
      method: 'POST',
      body: form,
      cache: 'no-store'
    });

    if (!data.__ok || !data.success) {
      statusEl.style.color = '#ff6b6b';
      statusEl.textContent = 'Sync FAILED: ' + (data.message || ('HTTP ' + data.__httpStatus));
      console.error('Sync failed:', data);
      return;
    }

    const now = new Date();
    statusEl.style.color = '#6bff95';
    statusEl.textContent =
      `Sync OK @ ${now.toLocaleTimeString()} | local_rows=${data.local_rows} | hosted_affected=${data.hosted_affected}`;
  } catch (err) {
    statusEl.style.color = '#ff6b6b';
    statusEl.textContent = 'Sync FAILED: ' + (err?.message || String(err));
    console.error(err);
  } finally {
    syncInFlight = false;
  }
}

function startAutoSync() {
  stopAutoSync();
  const seconds = parseInt(autoSecs.value, 10) || 5;
  statusEl.style.color = '#fff';
  statusEl.textContent = 'Auto sync on (every ' + seconds + 's).';
  runSyncOnce();
  autoTimer = setInterval(runSyncOnce, seconds * 1000);
}

autoCheck.addEventListener('change', () => {
  if (!canEnableAutoSync()) {
    autoCheck.checked = false;
    refreshAutoSyncUI();
    return;
  }
  if (autoCheck.checked) startAutoSync();
  else {
    stopAutoSync();
    statusEl.style.color = '#fff';
    statusEl.textContent = 'Auto sync is off.';
  }
});

autoSecs.addEventListener('change', () => {
  if (autoCheck.checked) startAutoSync();
});

syncNowBtn.addEventListener('click', () => {
  if (!canEnableAutoSync()) {
    statusEl.style.color = '#fff';
    statusEl.textContent = 'Select Field, Game, Event, Match first.';
    return;
  }
  runSyncOnce();
});

['field_id','event','match_number','game'].forEach(id => {
  const el = document.getElementById(id);
  if (!el) return;
  el.addEventListener('change', refreshAutoSyncUI);
  el.addEventListener('keyup', refreshAutoSyncUI);
});

refreshAutoSyncUI();
</script>

<script>
let startTimeMs = <?= json_encode($activeMatch['start_time_utc_ms'] ?? null) ?>;
let totalPause = <?= json_encode($activeMatch['total_pause_duration'] ?? 0) ?>;
let isPaused   = <?= json_encode((bool)($activeMatch['pause'] ?? 0)) ?>;
const matchId  = <?= json_encode($activeMatch['id'] ?? null) ?>;

const FIELD_ID_FALLBACK = <?= (int)$selected_field_id ?>;

let autoPauseTriggered = false;
let autoUnpauseTriggered = false;
let endMatchInFlight = false;
let pausePollInterval = null;

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
    totalPause = Number(data.total_pause_duration) || 0;
  })
  .catch(err => console.error("Error fetching pause:", err));
}

if (matchId) pausePollInterval = setInterval(updatePauseStatus, 1000);

function updateTimer() {
  const timerElement = document.getElementById('timer');
  if (!timerElement) return;

  if (!startTimeMs) {
    timerElement.textContent = "No active match.";
    return;
  }

  const realElapsedSeconds = (Date.now() - startTimeMs) / 1000;
  const delay = parseInt(document.getElementById('delay')?.value, 10) || 0;
  const elapsedGameTime = realElapsedSeconds - totalPause;

  if (!autoPauseTriggered && realElapsedSeconds >= 15 && matchId) {
    fetch('../php/toggle_pause.php?cacheBust=' + Date.now(), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ match_id: matchId })
    }).catch(console.error);
    autoPauseTriggered = true;
  } else if (autoPauseTriggered && !autoUnpauseTriggered && realElapsedSeconds >= (15 + delay) && matchId) {
    fetch('../php/toggle_pause.php?cacheBust=' + Date.now(), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ match_id: matchId })
    }).catch(console.error);
    autoUnpauseTriggered = true;
  }

  if (isPaused) {
    timerElement.textContent = "Paused";
    return;
  }

  const MATCH_LEN = 150;
  const remainingSeconds = Math.max(MATCH_LEN - elapsedGameTime, 0);

  if (elapsedGameTime >= MATCH_LEN) {
    timerElement.textContent = "Match Over";
    clearInterval(timerInterval);
    autoPauseTriggered = false;
    autoUnpauseTriggered = false;

    if (endMatchInFlight) return;
    endMatchInFlight = true;

    const fieldId = parseInt(document.getElementById('field_id')?.value, 10) || FIELD_ID_FALLBACK;

    fetch('../php/end_match.php?cacheBust=' + Date.now(), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ match_id: matchId, field_id: fieldId })
    })
    .then(r => r.json())
    .then(data => {
      console.log('end_match response:', data);

      const gameVal  = (document.getElementById('game')?.value || '').trim();
      const eventVal = (document.getElementById('event')?.value || '').trim();

      const url = new URL(window.location.href);
      url.pathname = window.location.pathname;
      url.searchParams.set('field_id', fieldId);
      if (gameVal)  url.searchParams.set('game', gameVal);
      if (eventVal) url.searchParams.set('event', eventVal);
      url.searchParams.set('cacheBust', Date.now());

      window.location.href = url.toString();
    })
    .catch(err => {
      console.error('end_match failed', err);

      const gameVal  = (document.getElementById('game')?.value || '').trim();
      const eventVal = (document.getElementById('event')?.value || '').trim();

      const url = new URL(window.location.href);
      url.pathname = window.location.pathname;
      url.searchParams.set('field_id', fieldId);
      if (gameVal)  url.searchParams.set('game', gameVal);
      if (eventVal) url.searchParams.set('event', eventVal);
      url.searchParams.set('cacheBust', Date.now());

      window.location.href = url.toString();
    });

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
  const fieldId = document.getElementById('field_id')?.value || <?= (int)$selected_field_id ?>;

  fetch("get_match_data.php?cacheBust=" + Date.now() + "&field_id=" + encodeURIComponent(fieldId))
    .then(r => r.json())
    .then(data => {
      if (data.error) return;

      ["red1","red2","red3","blue1","blue2","blue3"].forEach(id => {
        const div = document.getElementById(id);
        if (!div) return;

        if (!data[id]) {
          div.innerHTML = `<p>No data</p>`;
          return;
        }

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

function setNextMatch() {
  const fieldId = document.getElementById('field_id')?.value || <?= (int)$selected_field_id ?>;

  fetch('get_active_event.php?cacheBust=' + Date.now() + '&field_id=' + encodeURIComponent(fieldId))
    .then(r => r.json())
    .then(data => {
      const fieldSelect = document.getElementById('field_id');
      const eventSelect = document.getElementById('event');
      const matchInput  = document.getElementById('match_number');
      const gameSelect  = document.getElementById('game');
      const beginButton = document.querySelector('button[name="begin_match"]');

      const savedGame = getCookie("owl_game");

      eventSelect.value = data.activeEventName || '';
      matchInput.value  = data.activeMatchNumber || '';

      if (data.activeGameName) {
        gameSelect.value = data.activeGameName;
      } else if (savedGame) {
        gameSelect.value = savedGame;
      } else {
        gameSelect.value = '';
      }

      fieldSelect.disabled = false;
      eventSelect.disabled = false;
      matchInput.disabled  = false;
      gameSelect.disabled  = false;
      beginButton.disabled = false;
      beginButton.textContent = 'Begin Match';

      refreshAutoSyncUI();
    })
    .catch(err => console.error('Error fetching active event data:', err));
}

if (!<?= json_encode($isMatchActive) ?>) setNextMatch();

document.getElementById('delay')?.addEventListener('change', function() {
  const delayValue = this.value;
  const fieldId = document.getElementById('field_id')?.value || <?= (int)$selected_field_id ?>;

  if (delayValue !== "") {
    fetch('../php/insert_delay.php?cacheBust=' + Date.now(), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ delay: delayValue, field_id: fieldId })
    }).catch(console.error);
  }
});

function fetchDelay() {
  const fieldId = document.getElementById('field_id')?.value || <?= (int)$selected_field_id ?>;

  $.ajax({
    type: 'POST',
    url: '../php/get_delay.php?cacheBust=' + Date.now(),
    dataType: 'json',
    cache: false,
    data: { field_id: fieldId },
    success: function(response) {
      if (response.delay !== null && response.delay !== undefined) {
        document.getElementById('delay').value = response.delay;
      }
    },
    error: function(xhr, status, error) {
      console.error("Error fetching delay:", error);
    }
  });
}

document.getElementById('field_id')?.addEventListener('change', function() {
  fetchDelay();
  refreshAutoSyncUI();
});

fetchDelay();
</script>

<script>
const gameSelectCookie = document.getElementById('game');
const fieldSelectCookie = document.getElementById('field_id');

if (gameSelectCookie) {
  gameSelectCookie.addEventListener('change', function () {
    document.cookie = "owl_game=" + encodeURIComponent(this.value) + "; path=/; max-age=" + (60*60*24*30);
  });
}

if (fieldSelectCookie) {
  fieldSelectCookie.addEventListener('change', function () {
    document.cookie = "owl_field_id=" + encodeURIComponent(this.value) + "; path=/; max-age=" + (60*60*24*30);
  });
}

function getCookie(name) {
  const value = "; " + document.cookie;
  const parts = value.split("; " + name + "=");
  if (parts.length === 2) return decodeURIComponent(parts.pop().split(";").shift());
  return null;
}

document.addEventListener("DOMContentLoaded", function () {
  const gameSelect = document.getElementById('game');
  const fieldSelect = document.getElementById('field_id');

  const savedGame = getCookie("owl_game");
  const savedFieldId = getCookie("owl_field_id");

  if (fieldSelect && savedFieldId && !<?= json_encode($isMatchActive) ?>) {
    fieldSelect.value = savedFieldId;
  }

  if (gameSelect && savedGame && !<?= json_encode($isMatchActive) ?>) {
    gameSelect.value = savedGame;
  }

  refreshAutoSyncUI();
});
</script>

</body>
</html>