<?php
// Force error reporting AT THE VERY TOP
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../php/database_connection.php'; // This MUST create $pdo

// --- Basic Connection Check ---
if (!isset($pdo) || !$pdo instanceof PDO) {
    die("Database connection failed: The \$pdo object was not created in database_connection.php.");
}

// Initialize variables
$games = [];
$events = [];
$results = [];
$selected_game = isset($_GET['game']) ? $_GET['game'] : '';
$selected_event = isset($_GET['event_name']) ? $_GET['event_name'] : '';
$game_config = null;
$all_buttons = [];
$offense_actions_in = "''";
$scoring_offense_actions_in = "''";

// --- MAIN LOGIC WRAPPED IN TRY...CATCH ---
try {
    // Set PDO attributes
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");

    // --- Scan for available games ---
    $games_dir = '../scouter/games';
    $game_files = glob($games_dir . '/*.json');
    if ($game_files === false) throw new Exception("Failed to scan games directory: " . $games_dir);
    if ($game_files) {
        foreach ($game_files as $file) $games[] = basename($file, '.json');
    }
    sort($games);

    // --- Load events ONLY if a game is selected ---
    if ($selected_game) {
        $stmt = $pdo->prepare("SELECT DISTINCT event_name FROM scouting_submissions WHERE game = ? ORDER BY event_name");
        $stmt->execute([$selected_game]);
        $events = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

// --- Load game config to identify offense and defense actions ---
if ($selected_game) {
    $config_path = $games_dir . '/' . basename($selected_game) . '.json';
    if (file_exists($config_path)) {
        $game_config_json = file_get_contents($config_path);
        if ($game_config_json === false)
            throw new Exception("Failed to read game config file: " . $config_path);

        $game_config = json_decode($game_config_json, true);
        if (json_last_error() !== JSON_ERROR_NONE)
            throw new Exception("Error decoding JSON from " . $config_path . ": " . json_last_error_msg());

        $offense_codes = [];
        $scoring_offense_codes = [];
        $defense_codes = [];

        if (isset($game_config['buttons']) && is_array($game_config['buttons'])) {
            foreach ($game_config['buttons'] as $btn) {
                $type = strtolower($btn['type'] ?? '');
                $code = $btn['code'] ?? '';
                if (!$code) continue;

                if ($type === 'offense') {
                    $offense_codes[] = $pdo->quote($code);
                    $autonPoints  = (int)($btn['autonPoints'] ?? 0);
                    $teleopPoints = (int)($btn['teleopPoints'] ?? 0);
                    if ($autonPoints > 0 || $teleopPoints > 0)
                        $scoring_offense_codes[] = $pdo->quote($code);
                } elseif ($type === 'defense') {
                    $defense_codes[] = $pdo->quote($code);
                }
            }
        }

        $offense_actions_in         = count($offense_codes) ? implode(',', $offense_codes) : "''";
        $scoring_offense_actions_in = count($scoring_offense_codes) ? implode(',', $scoring_offense_codes) : "''";
        $defense_actions_in         = count($defense_codes) ? implode(',', $defense_codes) : "'plays_defense','block'";
    } else {
        throw new Exception("Game config file not found: " . $config_path);
    }
}


    // --- Run analysis logic ONLY if BOTH game/event are selected ---
    if ($selected_game && $selected_event) {

        // Start Transaction
        $pdo->beginTransaction();

        // Set session variables
        $stmt = $pdo->prepare("SET @game_name := ?");
        $stmt->execute([$selected_game]);
        $stmt = $pdo->prepare("SET @event_name := ?");
        $stmt->execute([$selected_event]);

        // Step 1: Create Temporary Table
        $pdo->exec("DROP TEMPORARY TABLE IF EXISTS temp_robot_summary");
        $pdo->exec("
            CREATE TEMPORARY TABLE temp_robot_summary (
                robot INT PRIMARY KEY,
                match_count INT DEFAULT 0,
                auton_score DECIMAL(5,2) DEFAULT 0,
                defense_score DECIMAL(5,2) DEFAULT 0,
                offense_actions_score DECIMAL(5,2) DEFAULT 0,
                offense_points_score DECIMAL(5,2) DEFAULT 0,
                scoring_rate DECIMAL(5,1) DEFAULT 0,
                top_scoring_location VARCHAR(255),
                high_score INT DEFAULT 0,
                high_score_match VARCHAR(255) DEFAULT 'N/A',
                avg_cycle_time DECIMAL(5,2) DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Step 2: Insert unique robots
        $pdo->exec("
            INSERT INTO temp_robot_summary (robot)
            SELECT DISTINCT robot FROM scouting_submissions WHERE game = @game_name AND event_name = @event_name
        ");

        // Step 3: Calculate basic counts and averages
        $pdo->exec("
            UPDATE temp_robot_summary rs
            LEFT JOIN (
                SELECT
                    robot,
                    COUNT(DISTINCT match_no) AS match_count,
                    
                    
                    
                    
                    
                    COALESCE(SUM(CASE WHEN time_sec <= 15 AND result = 'success' AND points > 0
                    THEN 1 ELSE 0 END) / COUNT(DISTINCT match_no), 0) AS avg_auton_actions,
                    
                    
                    COALESCE(SUM(CASE WHEN action IN ($defense_actions_in) THEN 1 ELSE 0 END) / COUNT(DISTINCT match_no), 0) AS avg_defense_actions,

                    
                    
                    
                    
                    COALESCE(SUM(CASE WHEN action IN ($offense_actions_in) THEN 1 ELSE 0 END) / COUNT(DISTINCT match_no), 0) AS avg_offense_actions,
                    COALESCE(SUM(points) / COUNT(DISTINCT match_no), 0) AS avg_offense_points,
                    COALESCE(SUM(CASE WHEN action IN ($offense_actions_in) AND result = 'success' THEN 1 ELSE 0 END) * 100.0 / NULLIF(SUM(CASE WHEN action IN ($offense_actions_in) THEN 1 ELSE 0 END), 0), 0) AS offense_success_rate
                FROM scouting_submissions
                WHERE game = @game_name AND event_name = @event_name
                GROUP BY robot
            ) AS summary_data ON rs.robot = summary_data.robot
            SET
                rs.match_count = IFNULL(summary_data.match_count, 0),
                rs.auton_score = IFNULL(summary_data.avg_auton_actions, 0),
                rs.defense_score = IFNULL(summary_data.avg_defense_actions, 0),
                rs.offense_actions_score = IFNULL(summary_data.avg_offense_actions, 0),
                rs.offense_points_score = IFNULL(summary_data.avg_offense_points, 0),
                rs.scoring_rate = IFNULL(summary_data.offense_success_rate, 0);
        ");


// Step 4: Top Scoring Location (points > 0 based)
$pdo->exec("DROP TEMPORARY TABLE IF EXISTS temp_top_action_counts;");
$pdo->exec("
CREATE TEMPORARY TABLE temp_top_action_counts AS
SELECT robot, action, COUNT(*) AS action_count
FROM scouting_submissions
WHERE game = @game_name
  AND event_name = @event_name
  AND result = 'success'
  AND points > 0
GROUP BY robot, action;
");

$pdo->exec("DROP TEMPORARY TABLE IF EXISTS temp_top_max_counts;");
$pdo->exec("
CREATE TEMPORARY TABLE temp_top_max_counts AS
SELECT robot, MAX(action_count) AS max_count
FROM temp_top_action_counts
GROUP BY robot;
");

$pdo->exec("
UPDATE temp_robot_summary rs
LEFT JOIN (
  SELECT t1.robot,
         GROUP_CONCAT(t1.action ORDER BY t1.action SEPARATOR ', ') AS top_locations
  FROM temp_top_action_counts t1
  JOIN temp_top_max_counts t2
    ON t1.robot = t2.robot AND t1.action_count = t2.max_count
  GROUP BY t1.robot
) AS top_score_data
  ON rs.robot = top_score_data.robot
SET rs.top_scoring_location = top_score_data.top_locations;
");







        // Step 5: High Score
        $pdo->exec("DROP TEMPORARY TABLE IF EXISTS robot_match_scores;");
        $pdo->exec("
            CREATE TEMPORARY TABLE robot_match_scores AS
                SELECT robot, match_no, SUM(points) AS points
                FROM scouting_submissions
                WHERE game = @game_name AND event_name = @event_name
                GROUP BY robot, match_no;
        ");
        $pdo->exec("DROP TEMPORARY TABLE IF EXISTS temp_robot_max_points;");
        $pdo->exec("
            CREATE TEMPORARY TABLE temp_robot_max_points AS
                SELECT robot, MAX(points) AS max_points
                FROM robot_match_scores
                GROUP BY robot;
        ");
        $pdo->exec("
            UPDATE temp_robot_summary rs
            LEFT JOIN (
                SELECT t1.robot, GROUP_CONCAT(t1.match_no ORDER BY t1.match_no SEPARATOR ', ') AS high_score_matches, t2.max_points AS high_score
                FROM robot_match_scores t1
                JOIN temp_robot_max_points t2 ON t1.robot = t2.robot AND t1.points = t2.max_points
                GROUP BY t1.robot, t2.max_points
            ) AS subquery ON rs.robot = subquery.robot
            SET rs.high_score = IFNULL(subquery.high_score, 0),
                rs.high_score_match = IFNULL(subquery.high_score_matches, 'N/A');
        ");






// Step 6: Average Cycle Time (points > 0 and success only)
$pdo->exec("DROP TEMPORARY TABLE IF EXISTS scoring_events;");
$pdo->exec("
CREATE TEMPORARY TABLE scoring_events AS
SELECT robot, match_no, time_sec
FROM scouting_submissions
WHERE game = @game_name
  AND event_name = @event_name
  AND result = 'success'
  AND points > 0
  AND time_sec BETWEEN 15 AND 135
ORDER BY robot, match_no, time_sec;
");

$pdo->exec("SET @prev_time := NULL, @prev_robot := NULL, @prev_match := NULL;");
$pdo->exec("DROP TEMPORARY TABLE IF EXISTS time_diffs;");
$pdo->exec("
CREATE TEMPORARY TABLE time_diffs AS
SELECT
  robot,
  match_no,
  time_sec,
  IF(@prev_robot = robot AND @prev_match = match_no,
     time_sec - @prev_time, NULL) AS time_diff,
  @prev_time := time_sec,
  @prev_robot := robot,
  @prev_match := match_no
FROM scoring_events;
");

$pdo->exec("DROP TEMPORARY TABLE IF EXISTS cycle_time_data;");
$pdo->exec("
CREATE TEMPORARY TABLE cycle_time_data AS
SELECT robot,
       AVG(avg_match_time_diff) AS avg_cycle_time
FROM (
  SELECT robot, match_no, AVG(time_diff) AS avg_match_time_diff
  FROM time_diffs
  WHERE time_diff IS NOT NULL
    AND time_diff > 0
    AND time_diff < 60
  GROUP BY robot, match_no
) AS per_match
GROUP BY robot;
");

$pdo->exec("
UPDATE temp_robot_summary rs
LEFT JOIN cycle_time_data ctd ON rs.robot = ctd.robot
SET rs.avg_cycle_time = IFNULL(ctd.avg_cycle_time, 0);
");

        
        
        
        
        
        
        
        

        // Commit Transaction
        $pdo->commit();








        // --- Fetch Final Results ---
        $final_sql = "
            SELECT
                rs.robot,
                rs.match_count,
                ROUND(rs.avg_cycle_time, 2) AS `avg_cycle_time`,
                ROUND(rs.auton_score, 2) AS `auton_score (avg actions)`,
                ROUND(rs.defense_score, 2) AS `defense_score (avg actions)`,
                ROUND(rs.offense_actions_score, 2) AS `offense_score (avg actions)`,
                ROUND(rs.offense_points_score, 2) AS `offense_score (avg points)`,
                ROUND(rs.scoring_rate, 1) AS `offense_success_rate (%)`,
                rs.top_scoring_location,
                rs.high_score,
                rs.high_score_match
            FROM temp_robot_summary rs
            ORDER BY rs.robot ASC
        ";

        $stmt = $pdo->query($final_sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Sanitize column names/aliases for display
        if($results) {
             $sanitized_results = [];
             $first_row_keys = !empty($results[0]) ? array_keys($results[0]) : [];
            foreach($results as $row_index => $row) {
                 $new_row = [];
                 foreach($first_row_keys as $key) {
                      $new_key = htmlspecialchars(str_replace('`', '', $key)); // Remove backticks
                      $new_row[$new_key] = $row[$key] ?? null;
                 }
                 $sanitized_results[] = $new_row;
            }
             $results = $sanitized_results;
        }


    } // End if ($selected_game && $selected_event)

} catch (PDOException $e) {
    // --- Error Handling ---
    $error_message = "Database Error: " . $e->getMessage() . " (Code: " . $e->getCode() . ")";
    if (isset($pdo) && $pdo->inTransaction()) {
        try { $pdo->rollBack(); $error_message .= " (Transaction rolled back.)"; }
        catch (PDOException $rollback_e) { error_log("Rollback failed:".$rollback_e->getMessage()); $error_message .= " (Rollback failed - check logs)"; }
    }
    error_log($error_message . " | Trace: " . $e->getTraceAsString());
    die($error_message);

} catch (Throwable $t) { // Catch any other error
    $error_message = "General Script Error: " . $t->getMessage();
    error_log($error_message . " | File: " . $t->getFile() . " | Line: " . $t->getLine() . " | Trace: " . $t->getTraceAsString());
    die($error_message);
}

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>The Stat Owl - Event Analysis</title>
  <link rel="stylesheet" href="../css/select.css">
   <style>
    /* ==== Fonts ==== */
    @font-face{font-family:'Roboto';src:url('/../stat_goblin/fonts/roboto/Roboto-Regular.ttf') format('ttf')}
    @font-face{font-family:'Griffy';src:url('/../stat_goblin/fonts/Griffy/Griffy-Regular.ttf') format('ttf')}
    @font-face{font-family:'Comfortaa';src:url('/../stat_goblin/fonts/Comfortaa/Comfortaa-VariableFont_wght.ttf') format('ttf')}

    /* ==== THEME VARIABLES ==== */
    :root {
        /* Light Mode (Default) */
        --bg-primary: #f4f4f4;
        --bg-secondary: #ffffff;
        --text-primary: #111111;
        --text-secondary: #555555;
        --border-color: #dddddd;
        --accent-color: #007bff;
        --button-bg: #eeeeee;
        --button-bg-hover: #dddddd;
        --table-header-bg: #eee;
        --table-border-color: #ccc;
        --table-row-even-bg: #f8f8f8;
        --table-row-hover-bg: #e8e8e8;
        --table-sticky-bg: #ddd;
        --table-sticky-header-bg: #ccc;
        --table-highlight-bg: #cceeff;
        --modal-bg: rgba(0,0,0,0.5);
        --modal-content-bg: #fefefe;
        --modal-text-color: #333;
        --link-color: #007bff;
        --table-link-color: #0056b3;
    }

    body.dark-mode {
        /* Dark Mode Overrides */
        --bg-primary: #111111;
        --bg-secondary: #222222;
        --text-primary: #eeeeee;
        --text-secondary: #bbbbbb;
        --border-color: #444444;
        --accent-color: #0d6efd; /* Slightly different blue for dark mode */
        --button-bg: #333333;
        --button-bg-hover: #555555;
        --table-header-bg: #444; /* Darker header */
        --table-border-color: #555; /* Darker border */
        --table-row-even-bg: #282828; /* Darker zebra */
        --table-row-hover-bg: #383838; /* Darker hover */
        --table-sticky-bg: #333; /* Darker sticky */
        --table-sticky-header-bg: #444; /* Darker sticky header */
        --table-highlight-bg: #004466; /* Dark blue highlight */
        --modal-bg: rgba(0,0,0,0.7);
        --modal-content-bg: #2b2b2b; /* Dark modal content */
        --modal-text-color: #eee; /* Light modal text */
        --link-color: #8ab4f8; /* Lighter blue for dark */
        --table-link-color: #90caf9; /* Lighter blue for dark table */
    }


/* ==== Base Styles ==== */
    body, html {
      font-family: 'Comfortaa', sans-serif;
      margin: 0; padding: 0; /* <--- Padding removed */
      background-color: var(--bg-primary);
      color: var(--text-primary);
      line-height: 1.5; text-align: center;
      transition: background-color 0.3s, color 0.3s;
      min-height: 100vh; /* Ensure body covers full viewport height */
    }
    h1, h2 { color: var(--text-primary); }
    a { color: var(--link-color); }
    .logo { width: 100%; max-width: 250px; display: block; margin: 0 auto 1rem auto; }

    /* ==== Form Styles ==== */
    form { margin-bottom: 20px; }
    form label { margin-right: 5px; color: var(--text-secondary); }
    select, button {
        background-color: var(--button-bg);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
        padding: 10px 15px; margin: 10px 5px;
        cursor: pointer; border-radius: 4px; font-size: 1em;
        transition: background-color 0.2s, border-color 0.2s;
    }
    button:hover, select:hover { background-color: var(--button-bg-hover); }
    select { min-width: 280px; }
    form .grid-item { display: inline-block; vertical-align: middle; }
    .icon { width: 30px; vertical-align: middle; margin-left: 8px; filter: var(--logo-filter, invert(0)); /* Invert icon in dark mode */ }
    body.dark-mode .icon { --logo-filter: invert(1); }

    /* ==== Table Styles ==== */
    .table-container {
        max-height: 70vh; overflow-y: auto; overflow-x: auto;
        margin-top: 20px; background-color: var(--bg-secondary);
        padding: 0; border-radius: 5px; border: 1px solid var(--table-border-color);
    }
    table { width: 100%; border-collapse: collapse; color: var(--text-primary); }
    table, th, td { border: 1px solid var(--table-border-color); }
    th, td { padding: 12px; text-align: left; white-space: nowrap; }
    th {
        background-color: var(--table-header-bg);
        cursor: pointer; user-select: none; font-weight: bold;
        position: sticky; top: 0; z-index: 10;
    }
    #resultsTable th:first-child,
    #resultsTable td:first-child {
        position: sticky; left: 0;
        background-color: var(--table-sticky-bg); z-index: 1;
    }
    #resultsTable th:first-child {
        z-index: 11; /* Above other sticky cells */
        background-color: var(--table-sticky-header-bg);
    }
    #resultsTable tr.highlight td { background-color: var(--table-highlight-bg) !important; color: var(--text-primary); /* Ensure text is readable */}
     #resultsTable tbody tr:nth-child(even) td { background-color: var(--table-row-even-bg); }
     #resultsTable tbody tr:hover td { background-color: var(--table-row-hover-bg); }
    table a { color: var(--table-link-color); text-decoration: none; font-weight: bold;}
    table a:hover { text-decoration: underline; }

    /* ==== Modal Styles ==== */
    .modal {
        display: none; position: fixed; z-index: 1000;
        left: 0; top: 0; width: 100%; height: 100%;
        overflow: auto; background-color: var(--modal-bg);
    }
    .modal-content {
        background-color: var(--modal-content-bg);
        color: var(--modal-text-color);
        margin: 5% auto; padding: 25px; border: 1px solid var(--border-color);
        width: 95%; max-width: 1400px; border-radius: 8px;
        position: relative; box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    }
    .close {
        color: #aaa; position: absolute; right: 20px; top: 15px;
        font-size: 32px; font-weight: bold; cursor: pointer; line-height: 1;
    }
    .close:hover, .close:focus { color: var(--text-primary); text-decoration: none; }
    body.dark-mode .close:hover, body.dark-mode .close:focus { color: #fff; } /* Ensure close button is visible */

    /* ==== Export Button ==== */
     #exportButton { background-color: #4CAF50; border: none; color: white; padding: 10px 20px;}
     #exportButton:hover { background-color: #45a049; }

     /* ==== Theme Toggle Button ==== */
     #themeToggle {
        position: fixed; bottom: 15px; right: 15px;
        background-color: var(--button-bg); color: var(--text-primary);
        border: 1px solid var(--border-color); border-radius: 50%;
        width: 40px; height: 40px; font-size: 1.2rem;
        cursor: pointer; z-index: 1000;
        display: flex; align-items: center; justify-content: center;
        transition: background-color 0.3s, color 0.3s, border-color 0.3s;
     }
     #themeToggle:hover { background-color: var(--button-bg-hover); }

  </style>
</head>
<body>

      <a href=".">
        <img src="../images/theStatOwl.png" class="logo" alt="Logo" id="mainLogo">
    </a>

  <form method="get" action="">
    <label for="game">Game:</label>
    <select name="game" id="game" onchange="this.form.submit()">
      <option value="">Choose a game</option>
      <?php foreach ($games as $game): ?>
        <option value="<?= htmlspecialchars($game) ?>" <?= ($game === $selected_game) ? 'selected' : '' ?>>
          <?= htmlspecialchars($game) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <?php if ($selected_game): ?>
    <label for="event_name">Event:</label>
    <select name="event_name" id="event_name" onchange="this.form.submit()">
      <option value="">Choose an event</option>
      <?php foreach ($events as $event): ?>
        <option value="<?= htmlspecialchars($event) ?>" <?= ($event === $selected_event) ? 'selected' : '' ?>>
          <?= htmlspecialchars($event) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>

    <?php if ($selected_event && !empty($results)): ?>
    <div class="grid-item" style="display: inline-block;">
      <a href="#" id="openChart" title="Open Charts">
        <img class="icon" src="../icons/heyitsachart.png" alt="Open Charts">
      </a>
    </div>
    <?php endif; ?>
  </form>

<div id="chartModal" class="modal">
  <div class="modal-content">
    <span class="close" title="Close">&times;</span>
    <iframe src="" id="chartIframe" frameborder="0" style="width:100%; height:80vh;"></iframe>
  </div>
</div>


  <?php if ($selected_event && !empty($results)): ?>
    <h2>Results for <?= htmlspecialchars($selected_event) ?> (<?= htmlspecialchars($selected_game) ?>)</h2>
    <div class="table-container">
      <table id="resultsTable">
        <thead>
          <tr>
             <?php foreach (array_keys($results[0]) as $col_key): ?>
               <th data-sort="asc"><?= htmlspecialchars($col_key) // Display sanitized key ?></th>
             <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($results as $row): ?>
              <tr>
                 <?php foreach ($row as $col_key => $cell): ?>
                    <td>
                        <?php if ($col_key === 'robot'): ?>
                            <a href="https://www.thebluealliance.com/team/<?= htmlspecialchars($cell) ?>" target="_blank">
                                <?= htmlspecialchars($cell) ?>
                            </a>
                        <?php else: ?>
                            <?= htmlspecialchars($cell ?? 'N/A') // Display N/A for null values ?>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
              </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php elseif ($selected_event): ?>
    <p>No scouting data available for this event and game combination.</p>
   <?php elseif ($selected_game): ?>
    <p>Please select an event to view analysis.</p>
   <?php else: ?>
     <p>Please select a game to view available events.</p>
  <?php endif; ?>

  <?php if (!empty($results)): ?>
    <button id="exportButton" style="margin-top: 20px;">Export to CSV</button>
  <?php endif; ?>

  <button id="themeToggle" title="Toggle Light/Dark Mode">☀️</button>

  <script>
    // === THEME TOGGLE LOGIC ===
    const themeToggle = document.getElementById('themeToggle');
    const bodyElement = document.body;
    const mainLogo = document.getElementById('mainLogo'); // Get the logo element

    // Function to apply the theme and save preference
    function applyTheme(theme) {
        if (theme === 'dark') {
            bodyElement.classList.add('dark-mode');
            themeToggle.textContent = '🌙'; // Moon icon for dark
             if (mainLogo) mainLogo.src = '../images/theStatOwl.png'; // Dark mode logo (assuming this is correct)
            localStorage.setItem('statOwlTheme', 'dark'); // Use a unique key
        } else { // Light mode
            bodyElement.classList.remove('dark-mode');
            themeToggle.textContent = '☀️'; // Sun icon for light
             if (mainLogo) mainLogo.src = '../images/theStatOwlb.png'; // Light mode logo (assuming this is correct)
            localStorage.setItem('statOwlTheme', 'light'); // Use a unique key
        }
    }

    // Check localStorage on page load
    // Use a different localStorage key than the scouter page if needed, e.g., 'statOwlTheme'
    const savedTheme = localStorage.getItem('statOwlTheme') || 'light'; // Default to light
    applyTheme(savedTheme); // Apply theme immediately

    // Add click listener to the toggle button
    themeToggle.addEventListener('click', () => {
        const isDarkMode = bodyElement.classList.contains('dark-mode');
        applyTheme(isDarkMode ? 'light' : 'dark');
    });
    // === END THEME TOGGLE LOGIC ===

    // --- Vanilla JS table sorting ---
    document.addEventListener("DOMContentLoaded", function() {
      const table = document.getElementById("resultsTable");
      if (!table) return;
      const headers = table.querySelectorAll("th");
      headers.forEach((header, columnIndex) => {
         if(!header) return;
        header.addEventListener("click", function() {
           if(!this) return;
          const currentAsc = this.getAttribute("data-sort") === "asc";
          sortTableByColumn(table, columnIndex, currentAsc);
          headers.forEach(h => { if(h && h !== this) h.setAttribute('data-sort', 'asc'); });
          this.setAttribute("data-sort", currentAsc ? "desc" : "asc");
        });
      });
    });

   function sortTableByColumn(table, columnIndex, asc = true) {
      const tbody = table.tBodies[0];
      if (!tbody) return;
      const rowsArray = Array.from(tbody.querySelectorAll("tr"));
       const headerCell = table.tHead?.rows[0]?.cells[columnIndex];
       if(!headerCell) return;
      const headerText = headerCell.textContent.trim().toLowerCase() ?? '';

      rowsArray.sort((a, b) => {
           const aCell = a.cells[columnIndex];
           const bCell = b.cells[columnIndex];
           if(!aCell || !bCell) return 0;

          const aText = aCell.textContent.trim() ?? '';
          const bText = bCell.textContent.trim() ?? '';

          // Determine if column should be numeric
          const numA = parseFloat(aText);
const numB = parseFloat(bText);
const isNumeric = !isNaN(numA) && !isNaN(numB);


if (isNumeric) {
  const valA = parseFloat(aText) || 0;
  const valB = parseFloat(bText) || 0;
  return asc ? valA - valB : valB - valA;
} else {
  return asc ? aText.localeCompare(bText) : bText.localeCompare(aText);
}

          // Fallback for string columns
          return asc ? aText.localeCompare(bText) : bText.localeCompare(aText);
      });
      rowsArray.forEach(row => tbody.appendChild(row));
    }


    // Row highlighting
    document.addEventListener("DOMContentLoaded", function() {
      const table = document.getElementById("resultsTable");
      if (!table) return;
      const tbody = table.querySelector("tbody");
       if (!tbody) return;

      tbody.addEventListener("click", function(event) {
          const clickedRow = event.target.closest("tr");
          if (!clickedRow) return;

           tbody.querySelectorAll("tr").forEach(r => r.classList.remove("highlight"));
           clickedRow.classList.add("highlight");
      });
    });


    // CSV Export
    document.addEventListener("DOMContentLoaded", function () {
      const exportBtn = document.getElementById("exportButton");
      if (exportBtn) {
        exportBtn.addEventListener("click", function () {
            const gameName = document.getElementById('game')?.value || 'UnknownGame';
            const eventName = document.getElementById('event_name')?.value || 'UnknownEvent';
            const safeGameName = gameName.replace(/[^a-z0-9]/gi, '_').toLowerCase();
            const safeEventName = eventName.replace(/[^a-z0-9]/gi, '_').toLowerCase();
            exportTableToCSV(`${safeGameName}_${safeEventName}_results.csv`);
        });
      }
    });

    function exportTableToCSV(filename) {
      const table = document.getElementById("resultsTable");
       if (!table) return;
      const rows = table.querySelectorAll("tr");
      let csv = [];

      rows.forEach(row => {
        let cols = row.querySelectorAll("th, td");
        let rowData = Array.from(cols).map(col => {
            let data = col.textContent.replace(/"/g, '""');
            const link = col.querySelector('a');
            if(link) data = link.textContent.replace(/"/g, '""'); // Use link text
            return `"${data}"`;
        });
        csv.push(rowData.join(","));
      });

      if (csv.length === 0) return;

      const csvFile = new Blob(["\uFEFF" + csv.join("\n")], { type: "text/csv;charset=utf-8;" }); // Add BOM
      const tempLink = document.createElement("a");

       if (navigator.msSaveBlob) { // IE/Edge
           navigator.msSaveBlob(csvFile, filename);
       } else { // Modern browsers
          tempLink.download = filename;
          tempLink.href = URL.createObjectURL(csvFile);
          tempLink.style.display = "none";
          document.body.appendChild(tempLink);
          tempLink.click();
          document.body.removeChild(tempLink);
          URL.revokeObjectURL(tempLink.href);
       }
    }


    // Modal logic
    const openChartLink = document.getElementById('openChart');
    if (openChartLink) {
        openChartLink.addEventListener('click', function(e) {
            e.preventDefault();
            const gameSelect = document.getElementById('game');
            const eventSelect = document.getElementById('event_name');
             const game = gameSelect?.value;
             const event = eventSelect?.value;

            if(game && event) {
              const iframe = document.getElementById('chartIframe');
              const modal = document.getElementById('chartModal');
               if(iframe) iframe.src = `charts.php?game=${encodeURIComponent(game)}&event_name=${encodeURIComponent(event)}`;
               if(modal) modal.style.display = 'block';
            } else {
              alert("Please select both a game and an event to view charts.");
            }
        });
    }

    const closeBtn = document.querySelector('.close');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            const modal = document.getElementById('chartModal');
            const iframe = document.getElementById('chartIframe');
            if(modal) modal.style.display = 'none';
            if(iframe) iframe.src = 'about:blank'; // Clear src
        });
    }

    window.addEventListener('click', function(e) {
      const modal = document.getElementById('chartModal');
      // Close if click is directly on the modal background
      if (modal && e.target === modal) {
          modal.style.display = 'none';
          const iframe = document.getElementById('chartIframe');
          if(iframe) iframe.src = 'about:blank'; // Clear src
      }
    });
  </script>
</body>
</html>