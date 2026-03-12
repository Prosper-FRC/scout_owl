<?php
// match_tables.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../php/database_connection.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    die("Database connection failed: The \$pdo object was not created in database_connection.php.");
}

// --- Initialize ---
$games = [];
$events = [];
$robots = [];
$matches = [];
$matches_data = [];
$selected_game = $_GET['game'] ?? '';
$selected_event = $_GET['event'] ?? '';
$selected_robot = $_GET['robot'] ?? '';
$game_config = null;

// --- NEW: Action lists ---
$defense_action_codes = [];
$offense_action_names = [];
// $misc_action_names = []; // --- REMOVED MISC ---
$all_actions_list = []; // All columns we will need

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");

    // --- Scan for available games (JSON configs) ---
    $games_dir = '../scouter/games';
    $game_files = glob($games_dir . '/*.json');
    foreach ($game_files as $file) {
        $games[] = basename($file, '.json');
    }
    sort($games);

    // --- Load Game Config (Only for Defense) ---
    if ($selected_game !== '') {
        $config_path = $games_dir . '/' . basename($selected_game) . '.json';
        if (!file_exists($config_path)) {
            throw new Exception("Game config file not found: $config_path");
        }
        $game_config_json = file_get_contents($config_path);
        if ($game_config_json === false) {
            throw new Exception("Failed to read game config file: $config_path");
        }
        $game_config = json_decode($game_config_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error decoding JSON from $config_path: " . json_last_error_msg());
        }
        
        // --- MODIFIED: Get ONLY defense codes ---
        if (isset($game_config['buttons'])) {
            foreach ($game_config['buttons'] as $button) {
                if (!empty($button['code']) && isset($button['type']) && $button['type'] === 'defense') {
                    $defense_action_codes[] = $button['code'];
                }
            }
        }
    }

    // --- Get Events for selected game (from active_event) ---
    if ($selected_game !== '') {
        $eventStmt = $pdo->prepare("SELECT DISTINCT event_name FROM active_event WHERE game = ? ORDER BY event_name ASC");
        $eventStmt->execute([$selected_game]);
        $events = $eventStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- Get Robots for selected event/game (from active_event) ---
    if ($selected_game !== '' && $selected_event !== '') {
        $robotStmt = $pdo->prepare("SELECT DISTINCT robot FROM active_event WHERE event_name = :event AND game = :game ORDER BY robot ASC");
        $robotStmt->execute([':event' => $selected_event, ':game' => $selected_game]);
        $robots = $robotStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- MODIFIED: Build Action Lists (Offense & Misc) ---
    if ($selected_game !== '' && $selected_event !== '') {
        $baseParams = [':game' => $selected_game, ':event' => $selected_event];

        // 1. Get OFFENSE actions (Points > 0)
        $offenseStmt = $pdo->prepare("
            SELECT DISTINCT action 
            FROM scouting_submissions 
            WHERE game = :game AND event_name = :event AND points > 0
            ORDER BY action
        ");
        $offenseStmt->execute($baseParams);
        $offense_action_names = $offenseStmt->fetchAll(PDO::FETCH_COLUMN, 0);

        // --- REMOVED MISC QUERY BLOCK ---
        
        // 3. Combine all actions into one list for initializing the data array
        // --- UPDATED TO REMOVE MISC ---
        $all_actions_list = array_merge($offense_action_names, $defense_action_codes);
    }
    
    // --- Get match data (scheduled + scouted) for selected robot ---
    $rows = [];
    if ($selected_game !== '' && $selected_event !== '' && $selected_robot !== '') {
        $sql = "
            SELECT 
                ae.match_number AS match_no,
                ae.robot,
                ae.alliance,
                COALESCE(ss.action, '') AS action,
                COALESCE(ss.points, 0) AS points,
                ss.result
            FROM active_event ae
            LEFT JOIN scouting_submissions ss
                ON ss.event_name = ae.event_name
                AND ss.game = ae.game
                AND ss.match_no = ae.match_number
                AND ss.robot = ae.robot
            WHERE ae.event_name = :event
              AND ae.game = :game
              AND ae.match_number IN (
                  SELECT DISTINCT match_number 
                  FROM active_event 
                  WHERE event_name = :event 
                    AND game = :game 
                    AND robot = :robot
              )
            ORDER BY ae.match_number ASC, ae.alliance ASC, ae.robot ASC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':event' => $selected_event,
            ':game'  => $selected_game,
            ':robot' => $selected_robot
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- If no scouting rows yet, still show matches from active_event ---
    if (empty($rows) && $selected_robot !== '') {
        $stmt = $pdo->prepare("
            SELECT 
                match_number AS match_no,
                robot,
                alliance,
                '' AS action,
                0 AS points,
                NULL AS result
            FROM active_event
            WHERE event_name = :event
              AND game = :game
              AND match_number IN (
                  SELECT DISTINCT match_number 
                  FROM active_event 
                  WHERE event_name = :event 
                    AND game = :game 
                    AND robot = :robot
              )
            ORDER BY match_number ASC, alliance ASC, robot ASC
        ");
        $stmt->execute([
            ':event' => $selected_event,
            ':game'  => $selected_game,
            ':robot' => $selected_robot
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- Build match data structure ---
    $matches_data = [];
    $matches = [];

    foreach ($rows as $r) {
        $match_no = $r['match_no'];
        $robot = $r['robot'];
        $alliance = $r['alliance'];
        $action = $r['action'];

        // Initialize the robot's data array with all possible actions set to 0
        if (!isset($matches_data[$match_no][$robot])) {
            $matches_data[$match_no][$robot] = ['robot' => $robot, 'alliance' => $alliance];
            foreach ($all_actions_list as $action_name) {
                $matches_data[$match_no][$robot][$action_name] = 0;
            }
        }

        // We just need to check if the action is in our master list and count it.
        if ($action !== '' && in_array($action, $all_actions_list, true)) {
            $matches_data[$match_no][$robot][$action]++;
        }

        if (!in_array($match_no, array_column($matches, 'match_no'), true)) {
            $matches[] = ['match_no' => $match_no];
        }
    }

    // --- Round values for display (No change here) ---
    foreach ($matches_data as &$robotsInMatch) {
        foreach ($robotsInMatch as &$robotData) {
            foreach ($all_actions_list as $code) {
                if (isset($robotData[$code])) {
                    $robotData[$code] = round($robotData[$code], 2);
                }
            }
        }
    }
    unset($robotsInMatch, $robotData);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
} catch (Exception $e) {
    die("General Error: ". $e->getMessage());
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Match Analysis Dashboard</title>
  <style>
    /* --- Your CSS --- */
    body, html { font-family: 'Comfortaa', sans-serif; margin: 0; padding: 0; background: #222; color: #eee; line-height: 1.5; }
    h1, h2, h3, h4, h5 { text-align: center; color: #fff; }
    h5 { color: #aaa; text-transform: uppercase; letter-spacing: 1px; margin-top: 25px; margin-bottom: 5px; border-bottom: 1px solid #444; display: inline-block; padding-bottom: 3px; }
    select, input[type="submit"] { background-color: #333; color: #fff; border: 1px solid #fff; padding: 8px; margin: 10px 5px; width:220px; border-radius: 4px; cursor: pointer;}
    select:hover { background-color: #555; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 20px; color: #eee; } 
    table, th, td { border: 1px solid #555; }
    th, td { padding: 8px; text-align: left; }
    
    /* --- THIS IS THE CSS CHANGE --- */
    th { 
        background-color: #444; 
        cursor: pointer; 
        user-select: none; 
        font-weight: bold; 
        white-space: normal; /* This tells the text to wrap */
        word-wrap: break-word; /* This helps break long words if needed */
    } 
    
    form { text-align: center; margin-bottom: 20px; }
    label { font-weight: bold; margin-right: 5px; }
    .match-section { margin: 20px auto; background: #333; border: 1px solid #555; border-radius: 5px; padding: 15px; max-width: 900px; } 
    .match-section h3 { margin-top: 0; color: #fff;}
    .logo { width: 100%; max-width: 300px; display: block; margin: 0 auto 1rem auto; } 
    .alliance-section { text-align: center; margin-bottom: 20px; }
    .alliance-section h4 { border-bottom: 1px solid #555; padding-bottom: 5px; margin-bottom: 10px;}
    .alliance-section table { background-color: #282828; } 
    .alliance-section th { background-color: #383838; }
  </style>
  <link rel="stylesheet" href="../css/select.css">
  <script>
    // --- Pass ALL data from PHP ---
    const G_SELECTED_GAME = <?php echo json_encode($selected_game); ?>;
    const G_SELECTED_EVENT = <?php echo json_encode($selected_event); ?>;
    const G_GAME_CONFIG = <?php echo json_encode($game_config); // Pass decoded config object, still needed for headers ?>;
    const G_MATCHES_DATA = <?php echo json_encode($matches_data); ?>;

    // --- NEW: Pass action lists from PHP ---
    const G_OFFENSE_ACTIONS = <?php echo json_encode($offense_action_names); ?>;
    const G_DEFENSE_ACTIONS = <?php echo json_encode($defense_action_codes); ?>;
    // --- REMOVED G_MISC_ACTIONS ---


    function displayMatchMetrics(match_no) {
      var container = document.getElementById("match_" + match_no);
      if (!container) {
          console.error("Container 'match_" + match_no + "' not found.");
          return;
      }

      // Check if we have data for this match
      const robotDataObj = G_MATCHES_DATA[match_no];
      if (!robotDataObj || Object.keys(robotDataObj).length === 0) {
          container.innerHTML = "<h3>Match " + match_no + "</h3><p>No scouting data recorded for this match.</p>";
          return;
      }

      // Convert object of robots → array
      const robotData = Object.values(robotDataObj);

      // Separate robots by alliance
      var blueAlliance = robotData.filter(r => r.alliance && r.alliance.toLowerCase() === "blue");
      var redAlliance = robotData.filter(r => r.alliance && r.alliance.toLowerCase() === "red");

      var html = "<h3>Match " + match_no + "</h3>";

      // --- MODIFIED: Build Alliance Sections ---
      function buildAllianceHtml(allianceName, allianceData) {
          let allianceHtml = `<div class='alliance-section'><h4>${allianceName} Alliance</h4>`;
          
          if (allianceData.length > 0) {
              // Build tables based on our new lists
              if (G_OFFENSE_ACTIONS.length > 0) {
                  allianceHtml += "<h5>Offense</h5>" + buildTable(allianceData, G_OFFENSE_ACTIONS);
              }
              if (G_DEFENSE_ACTIONS.length > 0) {
                  // We can get fancy and pull names from the config for headers
                  let defense_headers = G_DEFENSE_ACTIONS.map(code => {
                      let btn = (G_GAME_CONFIG.buttons || []).find(b => b.code === code);
                      return btn ? btn.name : code; // Fallback to code if name not found
                  });
                  allianceHtml += "<h5>Defense</h5>" + buildTable(allianceData, G_DEFENSE_ACTIONS, defense_headers);
              }
              // --- REMOVED MISC TABLE BLOCK ---
          } else {
              allianceHtml += `<p>No ${allianceName} Alliance data available for this match.</p>`;
          }
          allianceHtml += "</div>";
          return allianceHtml;
      }

      html += buildAllianceHtml("Blue", blueAlliance);
      html += buildAllianceHtml("Red", redAlliance);

      container.innerHTML = html;
    }

    // --- MODIFIED: Build Table Function ---
    // Now takes an array of action_codes and an optional array of headers
    function buildTable(robots, action_codes, headers = null) {
        if (!action_codes || action_codes.length === 0) return "";
        
        // --- THIS IS THE JAVASCRIPT CHANGE ---
        if (!headers) {
            // If no custom headers are provided (like for Offense),
            // use the action codes but replace underscores with spaces.
            headers = action_codes.map(code => code.replace(/_/g, ' '));
        }

        let tableHtml = "<table><thead><tr><th>Robot</th>";
        
        // Store codes corresponding to headers for data lookup
        const headerCodes = ['robot']; // Start with robot
        
        headers.forEach((header, index) => {
            let code = action_codes[index]; // Get the data key
            tableHtml += `<th>${htmlspecialchars(header)}</th>`; // header is now formatted
            headerCodes.push(code); // Store the code
        });
        tableHtml += "</tr></thead><tbody>";

        robots.forEach(r => {
            tableHtml += "<tr>";
            // Loop through headerCodes to ensure correct order and handle missing data
            headerCodes.forEach(code => {
                let value = "0"; // Default value
                if (code === 'robot') {
                    value = htmlspecialchars(r.robot);
                } else if (r.hasOwnProperty(code)) { // Check if the robot data HAS this code
                    value = htmlspecialchars(r[code]);
                }
                // Handle potential null/undefined by defaulting to "0"
                value = (value === null || value === undefined) ? "0" : value;
                tableHtml += `<td>${value}</td>`;
            });
            tableHtml += "</tr>";
        });
        tableHtml += "</tbody></table>";
        return tableHtml;
    }

    // Simple HTML escaping function for JS
    function htmlspecialchars(str) {
        if (typeof str !== 'string') str = String(str); // Ensure it's a string
        return str.replace(/&/g, '&amp;')
                  .replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;')
                  .replace(/"/g, '&quot;')
                  .replace(/'/g, '&#039;');
    }


    // --- window.onload (No changes here) ---
    window.onload = function() {
      // Check if G_MATCHES_DATA is populated before looping
      if (G_MATCHES_DATA && Object.keys(G_MATCHES_DATA).length > 0) {
          for (const match_no in G_MATCHES_DATA) {
              // Ensure it's a property of the object itself
              if (Object.prototype.hasOwnProperty.call(G_MATCHES_DATA, match_no)) {
                  displayMatchMetrics(match_no);
              }
          }
      } else {
          // If G_MATCHES_DATA is empty but matches were expected (PHP found matches),
          // find the container and display a message.
          const matchesContainer = document.getElementById('matchesContainer');
          const selectedRobot = <?php echo json_encode($selected_robot); ?>;
          if (selectedRobot && matchesContainer && matchesContainer.children.length > 0 && matchesContainer.children[0].classList.contains('match-container-item')) {
              // If there were match divs generated by PHP but no data, update them
              Array.from(matchesContainer.children).forEach(div => {
                  const matchNum = div.dataset.match;
                  if (matchNum) {
                      div.innerHTML = `<h3>Match ${matchNum}</h3><p>Could not load detailed data for this match.</p>`;
                  }
              });
          } else if (!selectedRobot) {
              // No robot selected, do nothing special, PHP already handles this
          } else {
              // No matches found by PHP, PHP already shows "No matches found..."
          }
      }
    };
  </script>
</head>
<body>
    <a href="."><img src="../images/owlAnalytics.png" class="logo" alt="Logo"></a>
  <h1>Match Analysis Dashboard</h1>

  <form method="GET" action="">
    <label for="gameDropdown">Game:</label>
    <select id="gameDropdown" name="game" onchange="this.form.submit()">
      <option value=""> Select Game </option>
      <?php foreach ($games as $g): ?>
        <option value="<?php echo htmlspecialchars($g); ?>"
          <?php if ($selected_game === $g) echo "selected"; ?>>
          <?php echo htmlspecialchars($g); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <?php if (!empty($selected_game)): ?>
      <label for="eventDropdown">Event:</label>
      <select id="eventDropdown" name="event" onchange="this.form.submit()">
        <option value=""> Select Event </option>
        <?php foreach ($events as $e): ?>
          <?php // $e is now an array like ['event_name' => 'Name'] ?>
          <option value="<?php echo htmlspecialchars($e['event_name']); ?>"
            <?php if ($selected_event === $e['event_name']) echo "selected"; ?>>
            <?php echo htmlspecialchars($e['event_name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>

    <?php if (!empty($selected_event)): ?>
      <label for="robotDropdown">Robot:</label>
      <select id="robotDropdown" name="robot" onchange="this.form.submit()">
        <option value=""> Select Robot </option>
        <?php foreach ($robots as $r): ?>
          <?php // $r is now an array like ['robot' => '1234'] ?>
          <option value="<?php echo htmlspecialchars($r['robot']); ?>"
            <?php if ($selected_robot === $r['robot']) echo "selected"; ?>>
            <?php echo htmlspecialchars($r['robot']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
  </form>

  <div id="matchesContainer">
    <?php if (!empty($selected_game) && !empty($selected_event) && !empty($selected_robot) && !empty($matches)): ?>
      <h2>Matches for Robot <?php echo htmlspecialchars($selected_robot); ?> in "<?php echo htmlspecialchars($selected_event); ?>"</h2>
      <?php foreach ($matches as $m): ?>
          <?php $match_no = $m['match_no']; ?>
          <div id="match_<?php echo htmlspecialchars($match_no); ?>" class="match-section match-container-item" data-match="<?php echo htmlspecialchars($match_no); ?>">
              <p>Loading metrics for match <?php echo htmlspecialchars($match_no); ?>...</p>
          </div>
      <?php endforeach; ?>
    <?php elseif (!empty($selected_game) && !empty($selected_event) && !empty($selected_robot)): ?>
      <p>No matches found for Robot <?php echo htmlspecialchars($selected_robot); ?> in this event.</p>
      <?php elseif (!empty($selected_game) && !empty($selected_event)): ?>
      <p>Please select a Robot to view match details.</p>
      <?php elseif (!empty($selected_game)): ?>
      <p>Please select an Event.</p>
      <?php else: ?>
      <p>Please select a Game.</p>
    <?php endif; ?>
  </div>
</body>
</html>