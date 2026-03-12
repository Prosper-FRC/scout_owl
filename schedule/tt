<?php
require_once '../php/database_connection.php';

// --- CONFIG ---
$TBA_AUTH_KEY = 'iPU2nNv1lDHD3m03JwnaqsCxsJhHLmXuRU0Te4xNoyBvOjxq5nYvsWlpd3bJH0kc';

// --- Load available games ---
$gamesDir = __DIR__ . '/../scouter/games';
$gameFiles = [];
if (is_dir($gamesDir)) {
    $files = glob($gamesDir . '/*.json');
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($files as $f) {
        $gameFiles[] = basename($f, '.json');
    }
}
$latestGame   = end($gameFiles) ?: '';
$selectedGame = $_POST['game'] ?? $latestGame;

// --- Helper for TBA API ---
function tba_api($endpoint, $auth_key) {
    $ch = curl_init("https://www.thebluealliance.com/api/v3/$endpoint");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ["X-TBA-Auth-Key: $auth_key"],
        CURLOPT_RETURNTRANSFER => true
    ]);
    $response = curl_exec($ch);
    if (!$response) die("TBA API request failed: " . curl_error($ch));
    curl_close($ch);
    return json_decode($response, true);
    
}

$year = $_POST['year'] ?? date('Y');
$event_key = $_POST['event'] ?? '';
$event_name = '';
if (!empty($event_key)) {
    $eventData = tba_api("event/$event_key", $TBA_AUTH_KEY) ?? [];
    $event_name = $eventData['name'] ?? $event_key;
}
$importPressed = isset($_POST['import_schedule']);
$message = '';
$matches = [];
if ($event_key && $selectedGame && isset($_POST['preview_schedule'])) {
    $matches = tba_api("event/$event_key/matches", $TBA_AUTH_KEY);

// Sort by match number numerically
usort($matches, function($a, $b) {
    $aStr = (string)($a['match_number'] ?? '');
    $bStr = (string)($b['match_number'] ?? '');
    $lenDiff = strlen($aStr) <=> strlen($bStr);
    return $lenDiff !== 0 ? $lenDiff : ((int)$aStr <=> (int)$bStr);
});
}

if ($importPressed && $event_key && $selectedGame) {
    $matches = tba_api("event/$event_key/matches", $TBA_AUTH_KEY);
    if (!is_array($matches) || empty($matches)) {
        $message = "No matches found for this event.";
    } else {
        usort($matches, function($a, $b) {
    $order = ['qm' => 1, 'ef' => 2, 'qf' => 3, 'sf' => 4, 'f' => 5];
    $a_lvl = $order[$a['comp_level']] ?? 99;
    $b_lvl = $order[$b['comp_level']] ?? 99;

    if ($a_lvl !== $b_lvl) return $a_lvl <=> $b_lvl;
    if (($a['set_number'] ?? 0) !== ($b['set_number'] ?? 0))
        return ($a['set_number'] ?? 0) <=> ($b['set_number'] ?? 0);
    return ($a['match_number'] ?? 0) <=> ($b['match_number'] ?? 0);
});
        // Delete only rows for same game + event
        $del = $pdo->prepare("DELETE FROM active_event WHERE event_name = :event_name AND game = :game");
        $del->execute([':event_name' => $event_name, ':game' => $selectedGame]);

        $insert = $pdo->prepare("
            INSERT INTO active_event (event_name, game, match_number, alliance, robot)
            VALUES (:event_name, :game, :match_number, :alliance, :robot)
        ");

        $count = 0;
        foreach ($matches as $m) {
            if (($m['comp_level'] ?? '') !== 'qm') continue;
            $match_number = $m['match_number'];
            foreach (['red', 'blue'] as $alliance) {
                foreach ($m['alliances'][$alliance]['team_keys'] as $team) {
                    $insert->execute([
                        ':event_name'   => $event_name,
                        ':game'         => $selectedGame,
                        ':match_number' => $match_number,
                        ':alliance'     => ucfirst($alliance),
                        ':robot'        => str_replace('frc', '', $team)
                    ]);
                    $count++;
                }
            }
        }
$message = "Inserted {$count} rows for “{$event_name}” ({$selectedGame}).";
    }
}

// Fetch all events for dropdown
$events = tba_api("events/$year", $TBA_AUTH_KEY);
usort($events, fn($a, $b) => strcmp($a['name'], $b['name']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Import FRC Schedule</title>
<style>
body, html {
  font-family: 'Comfortaa', sans-serif;
  margin: 0; padding: 0; text-align: center;
  background: #222; color: #eee;
  transition: background 0.3s ease, color 0.3s ease;
}
body.light { background: #f5f5f5; color: #111; }
.logo {
  width: 400px; display: block; margin: 20px auto;
}
form { margin: 20px auto; text-align: center; }
select, button {
  font-size: 1rem; padding: 10px 14px; margin: 8px;
  border-radius: 5px; border: 1px solid #ccc;
  background: #333; color: #fff;
}
body.light select, body.light button {
  background: #fff; color: #000; border: 1px solid #999;
}
button:hover { cursor: pointer; opacity: 0.8; }
table { margin: 20px auto; border-collapse: collapse; width: 80%; }
th, td { padding: 8px 12px; border: 1px solid #555; }
th { background: #444; }
.red { color: #E74C3C; }
.blue { color: #3498DB; }
#themeToggle {
  position: fixed; top: 10px; right: 10px;
  width: 40px; height: 40px;
  border-radius: 50%;
  font-size: 1.2rem; cursor: pointer;
  border: 1px solid #777;
  background: #222; color: #fff;
}
body.light #themeToggle {
  background: #fff; color: #000; border: 1px solid #ccc;
}
</style>
</head>
<body>

<a href=".."><img src="../images/owladmin.png" class="logo" alt="Logo"></a>
<h2>Import Schedule from The Blue Alliance</h2>

<form method="POST">
  <label for="year">Year:</label>
  <select name="year" id="year" onchange="this.form.submit()">
    <?php for ($y = 2023; $y <= date('Y'); $y++): ?>
      <option value="<?= $y ?>" <?= $y==$year?'selected':'' ?>><?= $y ?></option>
    <?php endfor; ?>
  </select>

  <label for="event">Event:</label>
  <select name="event" id="event">
    <option value="">-- Select Event --</option>
    <?php foreach ($events as $e): ?>
      <option value="<?= htmlspecialchars($e['key']) ?>"
        data-name="<?= htmlspecialchars($e['name']) ?>"
        <?= ($event_key===$e['key']?'selected':'') ?>>
        <?= htmlspecialchars($e['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <input type="hidden" name="event_name" id="event_name_hidden" value="<?= htmlspecialchars($event_name) ?>">

  <label for="game">Game:</label>
  <select name="game" id="game">
    <?php foreach ($gameFiles as $g): ?>
      <option value="<?= htmlspecialchars($g) ?>" <?= $g===$selectedGame?'selected':'' ?>>
        <?= htmlspecialchars($g) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <button type="submit" name="preview_schedule">Preview Schedule</button>
  <button type="submit" name="import_schedule" style="background:#16a34a;">Import Schedule</button>
</form>

<?php if ($message): ?>
  <p><strong><?= htmlspecialchars($message) ?></strong></p>
<?php endif; ?>

<?php if (!empty($matches)): ?>
  <table>
    <tr><th>Match</th><th>Red Alliance</th><th>Blue Alliance</th></tr>
    <?php foreach ($matches as $m): if (($m['comp_level'] ?? '') !== 'qm') continue;
      $num = $m['match_number'];
      $red = implode(', ', array_map(fn($t)=>str_replace('frc','',$t), $m['alliances']['red']['team_keys']));
      $blue = implode(', ', array_map(fn($t)=>str_replace('frc','',$t), $m['alliances']['blue']['team_keys']));
    ?>
      <tr><td><?= $num ?></td><td class="red"><?= $red ?></td><td class="blue"><?= $blue ?></td></tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>

<button id="themeToggle" title="Toggle Theme">🌙</button>
<script>
// store selected event name for POST
document.getElementById('event').addEventListener('change', function() {
  const selected = this.options[this.selectedIndex];
  document.getElementById('event_name_hidden').value = selected.getAttribute('data-name') || '';
});

document.addEventListener('DOMContentLoaded', () => {
  const bodyEl = document.body;
  const btn = document.getElementById('themeToggle');
  const saved = localStorage.getItem('owlTheme') || 'dark';
  applyTheme(saved);
  btn.addEventListener('click', () => {
    const next = bodyEl.classList.contains('light') ? 'dark' : 'light';
    applyTheme(next);
  });
  function applyTheme(theme) {
    bodyEl.classList.toggle('light', theme === 'light');
    btn.textContent = theme === 'light' ? '☀️' : '🌙';
    localStorage.setItem('owlTheme', theme);
  }
});
</script>

</body>
</html>