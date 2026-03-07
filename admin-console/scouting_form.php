<?php
require_once '../php/database_connection.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

function uuidv4(): string {
    $data = random_bytes(16);
    // version 4
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    // variant
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/*
Assumes scouting_submissions has at least:
- uuid
- game
- field_id
- event_name
- match_no
- time_sec
- robot
- alliance
- action
- location
- result
- points
*/

// ---------- AJAX endpoints ----------
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    $ajax = $_GET['ajax'];

    try {
        if ($ajax === 'matches') {
            $event = trim($_GET['event'] ?? '');
            $game = trim($_GET['game'] ?? '');
            $field_id = (int)($_GET['field_id'] ?? 0);

            if ($event === '' || $game === '' || $field_id <= 0) {
                echo json_encode(['ok' => true, 'matches' => []]);
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT DISTINCT match_number
                FROM active_event
                WHERE event_name = :event_name
                  AND game = :game
                  AND field_id = :field_id
                ORDER BY match_number ASC
            ");
            $stmt->execute([
                ':event_name' => $event,
                ':game' => $game,
                ':field_id' => $field_id
            ]);

            echo json_encode([
                'ok' => true,
                'matches' => $stmt->fetchAll(PDO::FETCH_COLUMN)
            ]);
            exit;
        }

        if ($ajax === 'robots') {
            $event = trim($_GET['event'] ?? '');
            $game = trim($_GET['game'] ?? '');
            $field_id = (int)($_GET['field_id'] ?? 0);
            $match_number = (int)($_GET['match_number'] ?? 0);

            if ($event === '' || $game === '' || $field_id <= 0 || $match_number <= 0) {
                echo json_encode(['ok' => true, 'robots' => []]);
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT robot, alliance
                FROM active_event
                WHERE event_name = :event_name
                  AND game = :game
                  AND field_id = :field_id
                  AND match_number = :match_number
                ORDER BY alliance ASC, robot ASC
            ");
            $stmt->execute([
                ':event_name' => $event,
                ':game' => $game,
                ':field_id' => $field_id,
                ':match_number' => $match_number
            ]);

            echo json_encode([
                'ok' => true,
                'robots' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
            exit;
        }

        echo json_encode(['ok' => false, 'message' => 'Unknown AJAX action.']);
        exit;
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ---------- Load page data ----------
$gamesDir = __DIR__ . '/../scouter/games';
$gameFiles = [];
if (is_dir($gamesDir)) {
    $files = glob($gamesDir . DIRECTORY_SEPARATOR . '*.json');
    if ($files !== false) {
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($files as $f) {
            $gameFiles[] = basename($f); // keep .json
        }
    }
}

$eventsStmt = $pdo->query("SELECT DISTINCT event_name FROM active_event ORDER BY event_name ASC");
$events = $eventsStmt->fetchAll(PDO::FETCH_COLUMN);

// ---------- Form handling ----------
$message = '';
$error = '';

$field_id = (int)($_POST['field_id'] ?? 1);
$event_name = trim($_POST['event_name'] ?? '');
$game = trim($_POST['game'] ?? '');
$match_no = (int)($_POST['match_no'] ?? 0);
$robot = trim($_POST['robot'] ?? '');
$alliance = trim($_POST['alliance'] ?? '');
$action_code = trim($_POST['action_code'] ?? '');
$result = trim($_POST['result'] ?? 'Success');
$phase = trim($_POST['phase'] ?? 'teleop'); // auton or teleop
$time_sec = (int)($_POST['time_sec'] ?? 150);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_missed_action'])) {
    try {
        if ($field_id <= 0) throw new Exception('Field is required.');
        if ($event_name === '') throw new Exception('Event is required.');
        if ($game === '') throw new Exception('Game is required.');
        if ($match_no <= 0) throw new Exception('Match number is required.');
        if ($robot === '') throw new Exception('Robot is required.');
        if ($alliance === '') throw new Exception('Alliance is required.');
        if ($action_code === '') throw new Exception('Action is required.');
        if (!in_array($result, ['Success', 'Failure'], true)) throw new Exception('Result is invalid.');
        if (!in_array($phase, ['auton', 'teleop'], true)) throw new Exception('Phase is invalid.');
        if ($time_sec < 0 || $time_sec > 150) throw new Exception('Time into match must be between 0 and 150.');

        $gamePath = $gamesDir . DIRECTORY_SEPARATOR . $game;
        if (!is_file($gamePath)) {
            throw new Exception('Game JSON file not found.');
        }

        $gameJson = json_decode(file_get_contents($gamePath), true);
        if (!is_array($gameJson)) {
            throw new Exception('Game JSON is invalid.');
        }

        $buttons = $gameJson['buttons'] ?? [];
        $actionDef = null;
        foreach ($buttons as $btn) {
            if (($btn['code'] ?? '') === $action_code) {
                $actionDef = $btn;
                break;
            }
        }

        if (!$actionDef) {
            throw new Exception('Selected action was not found in the game JSON.');
        }

        $location = $actionDef['location'] ?? 'anywhere';
        $autonPoints = (int)($actionDef['autonPoints'] ?? 0);
        $teleopPoints = (int)($actionDef['teleopPoints'] ?? 0);

        $points = 0;
        if ($result === 'Success') {
            $points = ($phase === 'auton') ? $autonPoints : $teleopPoints;
        }

        $uuid = uuidv4();

        $stmt = $pdo->prepare("
            INSERT INTO scouting_submissions
            (uuid, ip_address, game, field_id, event_name, match_no, time_sec, robot, alliance, action, location, result, points)
            VALUES
            (:uuid, :ip_address, :game, :field_id, :event_name, :match_no, :time_sec, :robot, :alliance, :action, :location, :result, :points)
        ");

        $stmt->execute([
            ':uuid' => $uuid,

            ':ip_address'=> 'admin',
            ':game' => pathinfo($game, PATHINFO_FILENAME), // stores base game name
            ':field_id' => $field_id,
            ':event_name' => $event_name,
            ':match_no' => $match_no,
            ':time_sec' => $time_sec,
            ':robot' => $robot,
            ':alliance' => $alliance,
            ':action' => $action_code,
            ':location' => $location,
            ':result' => $result,
            ':points' => $points
        ]);

        $message = "Missed action added for robot {$robot}, match {$match_no}. UUID: {$uuid}";
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Missed Action</title>
<link rel="stylesheet" href="../css/select.css">
<style>
body, html {
  font-family: 'Comfortaa', sans-serif;
  margin: 0;
  padding: 0;
  text-align: center;
  background: #222;
  color: #eee;
}
.logo {
  width: 400px;
  display: block;
  margin: 20px auto;
}
.container {
  max-width: 900px;
  margin: 0 auto;
  padding: 20px;
}
form {
  display: grid;
  grid-template-columns: repeat(2, minmax(220px, 1fr));
  gap: 14px;
  margin-top: 20px;
}
.full {
  grid-column: 1 / -1;
}
label {
  display: block;
  margin-bottom: 6px;
  text-align: left;
}
select, input, button {
  width: 100%;
  box-sizing: border-box;
  padding: 12px;
  font-size: 1rem;
  border-radius: 6px;
  border: 1px solid #555;
  background: #333;
  color: #fff;
}
button {
  cursor: pointer;
  background: #16a34a;
  border-color: #16a34a;
}
button:hover {
  opacity: 0.9;
}
.notice {
  max-width: 900px;
  margin: 12px auto;
  padding: 12px;
  border-radius: 8px;
}
.success { background: #123d22; }
.error { background: #5a1d1d; }
small {
  color: #bbb;
}
</style>
</head>
<body>
<a href=".."><img src="../images/owladmin.png" class="logo" alt="Logo"></a>

<div class="container">
  <h2>Add Missed Action</h2>

  <?php if ($message): ?>
    <div class="notice success"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="notice error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" id="missedActionForm">
    <div>
      <label for="field_id">Field</label>
      <select name="field_id" id="field_id" required>
        <?php for ($f = 1; $f <= 6; $f++): ?>
          <option value="<?= $f ?>" <?= $field_id === $f ? 'selected' : '' ?>>Field <?= $f ?></option>
        <?php endfor; ?>
      </select>
    </div>

    <div>
      <label for="event_name">Event</label>
      <select name="event_name" id="event_name" required>
        <option value="">Select Event</option>
        <?php foreach ($events as $ev): ?>
          <option value="<?= htmlspecialchars($ev) ?>" <?= $event_name === $ev ? 'selected' : '' ?>>
            <?= htmlspecialchars($ev) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label for="game">Game</label>
      <select name="game" id="game" required>
        <option value="">Select Game</option>
        <?php foreach ($gameFiles as $g): ?>
          <option value="<?= htmlspecialchars($g) ?>" <?= $game === $g ? 'selected' : '' ?>>
            <?= htmlspecialchars(pathinfo($g, PATHINFO_FILENAME)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label for="match_no">Match Number</label>
      <select name="match_no" id="match_no" required>
        <option value="">Select Match</option>
      </select>
    </div>

    <div>
      <label for="robot">Robot</label>
      <select name="robot" id="robot" required>
        <option value="">Select Robot</option>
      </select>
    </div>

    <div>
      <label for="alliance">Alliance</label>
      <input type="text" name="alliance" id="alliance" readonly value="<?= htmlspecialchars($alliance) ?>" required>
    </div>

    <div>
      <label for="action_code">Action</label>
      <select name="action_code" id="action_code" required>
        <option value="">Select Action</option>
      </select>
    </div>

    <div>
      <label for="result">Result</label>
      <select name="result" id="result" required>
        <option value="Success" <?= $result === 'Success' ? 'selected' : '' ?>>Success</option>
        <option value="Failure" <?= $result === 'Failure' ? 'selected' : '' ?>>Failure</option>
      </select>
    </div>

    <div>
      <label for="phase">Phase</label>
      <select name="phase" id="phase" required>
        <option value="auton" <?= $phase === 'auton' ? 'selected' : '' ?>>Auton</option>
        <option value="teleop" <?= $phase === 'teleop' ? 'selected' : '' ?>>Teleop</option>
      </select>
    </div>

    <div>
      <label for="time_sec">Time Into Match (0–150)</label>
      <input type="number" name="time_sec" id="time_sec" min="0" max="150" value="<?= htmlspecialchars((string)$time_sec) ?>" required>
    </div>

    <div class="full">
      <small>Points are calculated from the selected game JSON. Failures always insert 0 points.</small>
    </div>

    <div class="full">
      <button type="submit" name="save_missed_action">Add Missed Action</button>
    </div>
  </form>
</div>

<script>
const gameActions = {};
<?php
foreach ($gameFiles as $g) {
    $path = $gamesDir . DIRECTORY_SEPARATOR . $g;
    $json = is_file($path) ? json_decode(file_get_contents($path), true) : null;
    $buttons = is_array($json) ? ($json['buttons'] ?? []) : [];
    echo "gameActions[" . json_encode($g) . "] = " . json_encode($buttons) . ";\n";
}
?>

const savedMatch = <?= json_encode($match_no > 0 ? (string)$match_no : '') ?>;
const savedRobot = <?= json_encode($robot) ?>;
const savedAction = <?= json_encode($action_code) ?>;

function loadActions() {
  const game = document.getElementById('game').value;
  const actionSel = document.getElementById('action_code');
  actionSel.innerHTML = '<option value="">Select Action</option>';

  const actions = gameActions[game] || [];
  actions.forEach(btn => {
    if (!btn.code || !btn.name) return;
    const opt = document.createElement('option');
    opt.value = btn.code;
    opt.textContent = btn.name;
    if (savedAction && savedAction === btn.code) opt.selected = true;
    actionSel.appendChild(opt);
  });
}

async function loadMatches() {
  const field_id = document.getElementById('field_id').value;
  const event = document.getElementById('event_name').value;
  const game = document.getElementById('game').value;
  const matchSel = document.getElementById('match_no');
  const robotSel = document.getElementById('robot');
  const allianceInput = document.getElementById('alliance');

  matchSel.innerHTML = '<option value="">Select Match</option>';
  robotSel.innerHTML = '<option value="">Select Robot</option>';
  allianceInput.value = '';

  if (!field_id || !event || !game) return;

  const url = new URL(window.location.href);
  url.searchParams.set('ajax', 'matches');
  url.searchParams.set('field_id', field_id);
  url.searchParams.set('event', event);
  url.searchParams.set('game', game.replace(/\.json$/,''));

  const res = await fetch(url);
  const data = await res.json();
  if (!data.ok) return;

  data.matches.forEach(m => {
    const opt = document.createElement('option');
    opt.value = m;
    opt.textContent = m;
    if (savedMatch && String(savedMatch) === String(m)) opt.selected = true;
    matchSel.appendChild(opt);
  });

  if (matchSel.value) {
    await loadRobots();
  }
}

async function loadRobots() {
  const field_id = document.getElementById('field_id').value;
  const event = document.getElementById('event_name').value;
  const game = document.getElementById('game').value;
  const match_no = document.getElementById('match_no').value;
  const robotSel = document.getElementById('robot');
  const allianceInput = document.getElementById('alliance');

  robotSel.innerHTML = '<option value="">Select Robot</option>';
  allianceInput.value = '';

  if (!field_id || !event || !game || !match_no) return;

  const url = new URL(window.location.href);
  url.searchParams.set('ajax', 'robots');
  url.searchParams.set('field_id', field_id);
  url.searchParams.set('event', event);
  url.searchParams.set('game', game.replace(/\.json$/,''));
  url.searchParams.set('match_number', match_no);

  const res = await fetch(url);
  const data = await res.json();
  if (!data.ok) return;

  data.robots.forEach(r => {
    const opt = document.createElement('option');
    opt.value = r.robot;
    opt.textContent = `${r.robot} (${r.alliance})`;
    opt.dataset.alliance = r.alliance;
    if (savedRobot && String(savedRobot) === String(r.robot)) opt.selected = true;
    robotSel.appendChild(opt);
  });

  if (robotSel.value) {
    syncAlliance();
  }
}

function syncAlliance() {
  const robotSel = document.getElementById('robot');
  const selected = robotSel.options[robotSel.selectedIndex];
  document.getElementById('alliance').value = selected?.dataset?.alliance || '';
}

document.getElementById('field_id').addEventListener('change', loadMatches);
document.getElementById('event_name').addEventListener('change', loadMatches);
document.getElementById('game').addEventListener('change', () => {
  loadActions();
  loadMatches();
});
document.getElementById('match_no').addEventListener('change', loadRobots);
document.getElementById('robot').addEventListener('change', syncAlliance);

document.addEventListener('DOMContentLoaded', async () => {
  loadActions();
  await loadMatches();
});
</script>
</body>
</html>