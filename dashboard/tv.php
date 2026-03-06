<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_exception_handler(function($e){
    echo "<pre>Uncaught Exception: ".$e->getMessage()."\nFile: ".$e->getFile().":".$e->getLine()."</pre>";
});
set_error_handler(function($errno,$errstr,$errfile,$errline){
    echo "<pre>PHP ERROR [$errno] $errstr in $errfile:$errline</pre>";
});


require_once '../php/database_connection.php'; // This MUST create $pdo

// --- Basic Connection Check ---
if (!isset($pdo) || !$pdo instanceof PDO) {
    die("Database connection failed: The \$pdo object was not created in database_connection.php.");
}

// Initialize variables
$games = [];
$events = [];
$event_analysis_results = []; // Holds the final analysis data for ALL robots (keyed by robot_id)
$event_analysis_results_indexed = []; // Holds data as simple array for card display loop
$unique_robots_at_event = []; // List of robot numbers for dropdowns
$selected_game = isset($_GET['game']) ? $_GET['game'] : '';
$selected_event = isset($_GET['event_name']) ? $_GET['event_name'] : '';
$game_config = null;
$all_buttons = [];
$offense_actions_in = "''";
$scoring_offense_actions_in = "''";

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
        if ($game_config_json === false) {
            throw new Exception("Failed to read game config file: " . $config_path);
        }

        $game_config = json_decode($game_config_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error decoding JSON from " . $config_path . ": " . json_last_error_msg());
        }

        if (isset($game_config['buttons']) && is_array($game_config['buttons'])) {
            $all_buttons = $game_config['buttons'];

            $offense_codes = [];
            $scoring_offense_codes = [];
            $defense_codes = [];

            foreach ($all_buttons as $btn) {
                $type = strtolower(trim($btn['type'] ?? ''));
                $code = $btn['code'] ?? '';
                if ($code === '') continue;

                // Offensive actions
                if ($type === 'offense') {
                    $offense_codes[] = $pdo->quote($code);
                    $autonPoints  = isset($btn['autonPoints']) ? (int)$btn['autonPoints'] : 0;
                    $teleopPoints = isset($btn['teleopPoints']) ? (int)$btn['teleopPoints'] : 0;
                    if ($autonPoints > 0 || $teleopPoints > 0) {
                        $scoring_offense_codes[] = $pdo->quote($code);
                    }
                }

                // Defensive actions
                elseif ($type === 'defense') {
                    $defense_codes[] = $pdo->quote($code);
                }
            }

            $offense_actions_in         = count($offense_codes)         ? implode(',', $offense_codes)         : "''";
            $scoring_offense_actions_in = count($scoring_offense_codes) ? implode(',', $scoring_offense_codes) : "''";
            $defense_actions_in         = count($defense_codes)         ? implode(',', $defense_codes)         : "''";

        } else {
            $all_buttons = [];
            $offense_actions_in = $scoring_offense_actions_in = $defense_actions_in = "''";
        }
    } else {
        throw new Exception("Game config file not found: " . $config_path);
    }
}











    // --- Run analysis logic ONLY if BOTH game/event are selected ---
    if ($selected_game && $selected_event) {
        // --- Get List of ALL Unique Robots at the Event ---
        $stmt = $pdo->prepare("SELECT DISTINCT robot FROM scouting_submissions WHERE game = ? AND event_name = ? ORDER BY CAST(robot AS UNSIGNED)");
        $stmt->execute([$selected_game, $selected_event]);
        $unique_robots_at_event = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($unique_robots_at_event)) {
            $pdo->beginTransaction();

            // Set session variables
            $stmt = $pdo->prepare("SET @game_name := ?"); $stmt->execute([$selected_game]);
            $stmt = $pdo->prepare("SET @event_name := ?"); $stmt->execute([$selected_event]);

            // Step 1: Create Temporary Table
            $pdo->exec("DROP TEMPORARY TABLE IF EXISTS temp_robot_summary");
            $pdo->exec("
                CREATE TEMPORARY TABLE temp_robot_summary (
                    robot INT PRIMARY KEY, match_count INT DEFAULT 0, auton_score DECIMAL(5,2) DEFAULT 0,
                    defense_score DECIMAL(5,2) DEFAULT 0, offense_actions_score DECIMAL(5,2) DEFAULT 0,
                    offense_points_score DECIMAL(5,2) DEFAULT 0, scoring_rate DECIMAL(5,1) DEFAULT 0,
                    top_scoring_location VARCHAR(255), high_score INT DEFAULT 0,
                    high_score_match VARCHAR(255) DEFAULT 'N/A', avg_cycle_time DECIMAL(5,2) DEFAULT 0
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Step 2: Insert unique robots
            $pdo->exec("
                INSERT IGNORE INTO temp_robot_summary (robot)
                SELECT DISTINCT robot FROM scouting_submissions WHERE game = @game_name AND event_name = @event_name
            ");

            // Step 3: Calculate basic counts and averages
            $pdo->exec("
                UPDATE temp_robot_summary rs
                LEFT JOIN (
                    SELECT
                        robot,
                        COUNT(DISTINCT match_no) AS match_count,
                        
                        
                        COALESCE(SUM(CASE WHEN 
                        time_sec <= 15 AND result = 'success' 
                        AND points > 0
                        THEN 1 ELSE 0 END) / NULLIF(COUNT(DISTINCT match_no), 0), 0) AS avg_auton_actions,
                        
                        
                        
                        
                        COALESCE(SUM(CASE WHEN action IN ($defense_actions_in)
 THEN 1 ELSE 0 END) / NULLIF(COUNT(DISTINCT match_no), 0), 0) AS avg_defense_actions,
                        COALESCE(SUM(CASE WHEN action IN ($offense_actions_in) THEN 1 ELSE 0 END) / NULLIF(COUNT(DISTINCT match_no), 0), 0) AS avg_offense_actions,
                        COALESCE(SUM(points) / NULLIF(COUNT(DISTINCT match_no), 0), 0) AS avg_offense_points,
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





// Step 4: Top Scoring Location (data-driven)
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
            $pdo->exec("CREATE TEMPORARY TABLE robot_match_scores AS SELECT robot, match_no, SUM(points) AS points FROM scouting_submissions WHERE game = @game_name AND event_name = @event_name GROUP BY robot, match_no;");
            $pdo->exec("DROP TEMPORARY TABLE IF EXISTS temp_robot_max_points;");
            $pdo->exec("CREATE TEMPORARY TABLE temp_robot_max_points AS SELECT robot, MAX(points) AS max_points FROM robot_match_scores GROUP BY robot;");
            $pdo->exec("UPDATE temp_robot_summary rs LEFT JOIN (SELECT t1.robot, GROUP_CONCAT(t1.match_no ORDER BY t1.match_no SEPARATOR ', ') AS high_score_matches, t2.max_points AS high_score FROM robot_match_scores t1 JOIN temp_robot_max_points t2 ON t1.robot = t2.robot AND t1.points = t2.max_points GROUP BY t1.robot, t2.max_points) AS subquery ON rs.robot = subquery.robot SET rs.high_score = IFNULL(subquery.high_score, 0), rs.high_score_match = IFNULL(subquery.high_score_matches, 'N/A');");






// Step 6: Average Cycle Time (MySQL 5.7 compatible)

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
) x
GROUP BY robot;
");

$pdo->exec("
UPDATE temp_robot_summary rs
LEFT JOIN cycle_time_data ctd ON rs.robot = ctd.robot
SET rs.avg_cycle_time = IFNULL(ctd.avg_cycle_time, 0);
");







// Step 7: Build robot summary text (avg auton actions, max auton points)
$pdo->exec("DROP TEMPORARY TABLE IF EXISTS temp_robot_summary_text;");
$pdo->exec("
  CREATE TEMPORARY TABLE temp_robot_summary_text AS
  SELECT 
    rs.robot,
    CONCAT(
      'Robot <strong>', rs.robot, '</strong> has competed in <strong>', rs.match_count, '</strong> matches. ',
      'It averages a cycle time of <strong>', ROUND(rs.avg_cycle_time, 1), '</strong> seconds ',
      'with a total offensive score of <strong>', ROUND(SUM(ss.points), 0), '</strong> and ',
      '<strong>', ROUND(rs.offense_points_score, 1), '</strong> points per match. ',
      'During autonomous, it performs about <strong>', ROUND(rs.auton_score, 1), '</strong> actions ',
      'and has achieved a maximum of <strong>', 
         COALESCE((
           SELECT ROUND(MAX(total_auton_points),1)
           FROM (
             SELECT robot, match_no, SUM(points) AS total_auton_points
             FROM scouting_submissions
             WHERE game = @game_name 
               AND event_name = @event_name 
               AND time_sec <= 15
             GROUP BY robot, match_no
           ) AS sub
           WHERE sub.robot = rs.robot
         ), 0),
      '</strong> auton points in a single match. ',
      'Defensively, it averages <strong>', ROUND(rs.defense_score, 1), '</strong> actions per match.'
    ) AS summary_text
  FROM temp_robot_summary rs
  LEFT JOIN scouting_submissions ss 
    ON ss.robot = rs.robot AND ss.event_name = @event_name AND ss.game = @game_name
  GROUP BY rs.robot, rs.match_count, rs.avg_cycle_time, rs.offense_points_score, rs.auton_score, rs.defense_score;
");














            // --- Fetch Final Results for ALL robots ---
$final_sql = "
    SELECT
        rs.robot,
        (SELECT alliance FROM scouting_submissions ssub
         WHERE ssub.robot = rs.robot AND ssub.game = @game_name AND ssub.event_name = @event_name
         ORDER BY ssub.id DESC LIMIT 1) AS alliance,
        rs.match_count,
        ROUND(rs.avg_cycle_time, 2) AS avg_cycle_time,
        ROUND(rs.auton_score, 2) AS auton_score,
        ROUND(rs.defense_score, 2) AS defense_score,
        ROUND(rs.offense_actions_score, 2) AS offense_actions_score,
        ROUND(rs.offense_points_score, 2) AS offense_points_score,
        ROUND(rs.scoring_rate, 1) AS scoring_rate,
        rs.top_scoring_location,
        rs.high_score,
        rs.high_score_match,
        tx.summary_text
    FROM temp_robot_summary rs
    LEFT JOIN temp_robot_summary_text tx ON rs.robot = tx.robot
    ORDER BY rs.robot ASC
";


            $stmt = $pdo->query($final_sql);
            $event_analysis_results_indexed = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Create the keyed version for JS prediction lookup
            $event_analysis_results = [];
            foreach ($event_analysis_results_indexed as $row) {
                 if (isset($row['robot'])) {
                    $event_analysis_results[$row['robot']] = $row;
                 }
            }

            $pdo->commit();
        }
    }
} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage() . " (Code: " . $e->getCode() . ")";
    if (isset($pdo) && $pdo->inTransaction()) {
        try { $pdo->rollBack(); $error_message .= " (Transaction rolled back.)"; }
        catch (PDOException $rollback_e) { error_log("Rollback failed:".$rollback_e->getMessage()); $error_message .= " (Rollback failed - check logs)"; }
    }
    error_log($error_message . " | Trace: " . $e->getTraceAsString());
    die($error_message);
} catch (Throwable $t) {
    $error_message = "General Script Error: " . $t->getMessage();
    error_log($error_message . " | File: " . $t->getFile() . " | Line: " . $t->getLine() . " | Trace: " . $t->getTraceAsString());
    die($error_message);
}

















// --- Determine active or next match robots (final fixed column names) ---
$dropdown_robots = [
  'blue1' => '', 'blue2' => '', 'blue3' => '',
  'red1'  => '', 'red2'  => '', 'red3'  => ''
];

$active_match = null;
$next_match = null;
$fallback_match = null;
$last_scouted_match = null;

if ($selected_game && $selected_event) {

    // 1. Check for active match in matches table
    $stmt = $pdo->prepare("
        SELECT match_number
        FROM matches
        WHERE game = ? AND event = ? AND active = 1
        ORDER BY match_number DESC
        LIMIT 1
    ");
    $stmt->execute([$selected_game, $selected_event]);
    $active_match_from_table = $stmt->fetchColumn();

    // 2. If no active match in table, compute next match based on scouting_submissions
    if (!$active_match_from_table) {

        // last scouted match number
        $stmt = $pdo->prepare("
            SELECT COALESCE(MAX(match_no), 0)
            FROM scouting_submissions
            WHERE game = ? AND event_name = ?
        ");
        $stmt->execute([$selected_game, $selected_event]);
        $last_scouted_match = (int)$stmt->fetchColumn();

        // possible next match number
        $next_match = $last_scouted_match + 1;

        // verify next match exists in active_event
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM active_event
            WHERE game = ? AND event_name = ? AND match_number = ?
        ");
        $stmt->execute([$selected_game, $selected_event, $next_match]);
        $exists_next = (int)$stmt->fetchColumn();

        if ($exists_next > 0) {
            $active_match = $next_match;
        } else {
            // fallback to next_match - 1 if next doesn’t exist
            $fallback_match = max($next_match - 1, 1);
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM active_event
                WHERE game = ? AND event_name = ? AND match_number = ?
            ");
            $stmt->execute([$selected_game, $selected_event, $fallback_match]);
            $exists_fallback = (int)$stmt->fetchColumn();
            $active_match = $exists_fallback > 0 ? $fallback_match : null;
        }

    } else {
        // active match exists in matches table
        $active_match = (int)$active_match_from_table;
    }

    // 3. Load robots for the selected match
    if ($active_match) {
        $stmt = $pdo->prepare("
            SELECT match_number, robot, alliance
            FROM active_event
            WHERE game = ? AND event_name = ? AND match_number = ?
            ORDER BY alliance, robot
        ");
        $stmt->execute([$selected_game, $selected_event, $active_match]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$b = 1;
$r = 1;
foreach ($rows as $row) {
    $a = strtolower(trim($row['alliance']));
    if ($a === 'blue' && $b <= 3) {
        $dropdown_robots["blue{$b}"] = $row['robot'];
        $b++;
    } elseif ($a === 'red' && $r <= 3) {
        $dropdown_robots["red{$r}"] = $row['robot'];
        $r++;
    }
}

    }
}







// --- DEBUG SAFE OUTPUT ---
    $dbg1 = isset($active_match_from_table) ? $active_match_from_table : 'NULL';
//$dbg2 = isset($last_scouted_match) ? $last_scouted_match : 'NULL';
//$dbg3 = isset($next_match) ? $next_match : 'NULL';
//$dbg4 = isset($fallback_match) ? $fallback_match : 'NULL';
//$dbg5 = isset($active_match) ? $active_match : 'NULL';
//echo "<script>
//alert('Active (matches table): {$dbg1}\\nLast Scouted: {$dbg2}\\nNext Match: //{$dbg3}\\nFallback (next-1): {$dbg4}\\nFinal Active Match Used: {$dbg5}');
//</script>";




?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta http-equiv="refresh" content="30000">
  <title>Owl TV - <?= htmlspecialchars($selected_event ? $selected_event : 'Select Event') ?></title>

  <link rel="stylesheet" href="../css/select.css">
  <script src="../js/canvas-confetti.js" type="text/javascript"></script>
  <script src="../js/Chart.bundle.js"></script>

  <style>
    /* ==== Fonts ==== */
    @font-face{font-family:'Roboto';src:url('/../stat_goblin/fonts/roboto/Roboto-Regular.ttf') format('ttf')}
    @font-face{font-family:'Griffy';src:url('/../stat_goblin/fonts/Griffy/Griffy-Regular.ttf') format('ttf')}
    @font-face{font-family:'Comfortaa';src:url('/../stat_goblin/fonts/Comfortaa/Comfortaa-VariableFont_wght.ttf') format('ttf')}

    /* ==== THEME VARIABLES ==== */
    :root {
      /* Light mode defaults */
      --bg-primary: #f4f4f6;
      --bg-surface: #ffffff;
      --bg-subtle: #eef0f3;
      --text-primary: #0f172a;
      --text-secondary: #334155;
      --border: #d5dae1;
      --shadow: rgba(2, 8, 23, 0.08);

      --card-bg: #ffffff;
      --card-text: #1f2937;
      --card-header: rgba(255,255,255,0.95);

      --accent-blue: #1e73be; /* not used as fill, glow only */
      --accent-red:  #be1e2d;

      --prediction-blue: #cfe8ff;
      --prediction-red:  #fde2e2;
      --prediction-base: #e5e7eb;

      --button-bg: #e5e7eb;
      --button-hover: #dbe1e8;

      --logo-filter: invert(1); /* white logo -> dark for light bg */
    }
    body.dark {
      --bg-primary: #0b1020;
      --bg-surface: #141a2a;
      --bg-subtle: #101626;


      --bg-primary: #111;
      --bg-surface: #333;
      --bg-subtle: #000;




      --text-primary: #e5e7eb;
      --text-secondary: #cbd5e1;
      --border: #273043;
      --shadow: rgba(0,0,0,0.35);

      --card-bg: #ffffff;     /* robot cards stay white for readability */
      --card-text: #1f2937;
      --card-header: rgba(255,255,255,0.95);

      --prediction-blue: #5DADE2;
      --prediction-red:  #EC7063;
      --prediction-base: #e2e3e5;

      --button-bg: #222;
      --button-hover: #111;

      --logo-filter: invert(0); /* keep logo white on dark bg */
    }

    /* ==== Base ==== */
    html, body { margin:0; padding:0; font-family:'Comfortaa', system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background:var(--bg-primary); color:var(--text-primary); }
    .container { padding: 1rem; max-width: 1800px; margin: auto; }

    /* ==== Header Row: logo + game + event + sort + theme toggle ==== */
    .header-bar {
      display:flex; align-items:center; gap:.75rem; flex-wrap:wrap;
      background:var(--bg-surface); padding:.75rem 1rem; border-radius:10px;
      box-shadow:0 2px 8px var(--shadow);
      position:sticky; top:.5rem; z-index:100;
    }
    .brand {
      display:flex; align-items:center; gap:.5rem; margin-right:.5rem;
    }
    .logo {
      height: 44px; /* bigger logo */
      aspect-ratio: 1 / 1;
      filter: var(--logo-filter);
      transition: filter .25s ease;
    }
        .logo2 {
      width:100%;
    }
            .cardlogo{
      background-color: #333;
    }
    .title {
      font-weight:700; font-size:1.15rem; color:var(--text-primary);
      white-space:nowrap;
    }
    .controls-inline {
      display:flex; align-items:center; gap:.5rem; flex-wrap:wrap;
    }
    .controls-inline label { color:var(--text-secondary); font-size:.9rem; }
    .controls-inline select, .controls-inline button {
      appearance:none;
      background:var(--button-bg);
      color:var(--text-primary);
      border:1px solid var(--border);
      border-radius:8px;
      padding:.45rem .6rem;
      font-size:.95rem;
      line-height:1;
      cursor:pointer;
    }
    .controls-inline select:hover, .controls-inline button:hover { background:var(--button-hover); }

    #themeToggle {
      width:40px; height:40px; display:grid; place-items:center;
      border-radius:50%; font-size:1.1rem;
    }

    /* Prediction robot pickers live in their own bar below header */
    .prediction-controls {
      margin-top: .75rem;
      background: var(--bg-subtle);
      border:1px solid var(--border);
      padding:.6rem .8rem;
      border-radius:10px;
      display:flex; align-items:center; justify-content:center; gap:1rem; flex-wrap:wrap;
    }
    .dropdown-group { display:flex; align-items:center; gap:.4rem; }
    .dropdown-group label { color:var(--text-secondary); font-size:.9rem; }

    /* ==== Robot Cards ==== */
    .robot-cards {
      display:grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 1rem; justify-content:center; padding: 1rem 0;
    }
    .robot-card {
      background: var(--card-bg);
      color: var(--card-text);
      border-radius: 10px;
      position: relative; overflow: hidden;
      aspect-ratio: 1/1;
      display:flex; flex-direction:column;
      box-shadow: 0 2px 8px var(--shadow);
    }
    /* Colored glow by alliance — Blue/Red; Unknown=white */
    .robot-card.Blue { box-shadow: 0 0 10px 2px #1e73be, 0 2px 8px var(--shadow); }
    .robot-card.Red  { box-shadow: 0 0 10px 2px #be1e2d, 0 2px 8px var(--shadow); }
    .robot-card.Unknown { box-shadow: 0 0 10px 2px #ffffff, 0 2px 8px var(--shadow); }

    /* Card header */
    .card-header {
      background: var(--card-header);
      height: 2.6em; padding: 0 0.7rem;
      display:flex; align-items:center; justify-content:space-between;
      border-bottom: 1px solid #e5e7eb;
      font-weight:700;
      position:absolute; inset:0 0 auto 0; z-index:2;
    }
    .robot-id { font-size: 1.25em; }
.robot-card.Blue,
.robot-card.Red,
.robot-card.Unknown {
  box-shadow: none;
}

    /* Views */
.view {
  position: absolute;
  inset: 2.6em 0 0 0;
  display: grid;
  place-items: center;
  opacity: 0;
  transform: translateX(100%);
  transition: transform 0.6s ease, opacity 0.6s ease;
}
.view.active {
  opacity: 1;
  transform: translateX(0);
  z-index: 1;
}


    /* Stat grid */
    .box_container{ display:grid; place-items:center; width:100%; height:100%; padding:0; box-sizing:border-box; }
    .stat-cards{
      display:grid; grid-template-columns:repeat(3,1fr); grid-template-rows:repeat(3,1fr);
      gap:.35rem; width:90%; aspect-ratio:1/1;
    }
    .stat-card {
      display:flex; flex-direction:column; align-items:center; justify-content:center;
      border:2px solid; border-radius:6px; color:#fff; font-size:.78em; line-height:1.15; padding:.2em; overflow:hidden;
    }
    .stat-label{ font-size:.72em; color:rgba(255,255,255,.85); margin-bottom:.1em; text-transform:uppercase; }
    .stat-value{ font-size:1.08em; font-weight:700; color:#fff; }
    .stat-value.small{ font-size:.9em; line-height:1.25; white-space:nowrap; }

    /* Stat colors */
    .stat-card.match_count           { background-color: rgba(20,96,61,0.7);   border-color: rgb(20,96,61); }
    .stat-card.avg_cycle_time        { background-color: rgba(33,91,159,0.7);  border-color: rgb(33,91,159); }
    .stat-card.auton_score           { background-color: rgba(96,23,20,0.7);   border-color: rgb(96,23,20); }
    .stat-card.defense_score         { background-color: rgba(96,61,20,0.7);   border-color: rgb(96,61,20); }
    .stat-card.offense_actions_score { background-color: rgba(96,20,55,0.7);   border-color: rgb(96,20,55); }
    .stat-card.offense_points_score  { background-color: rgba(96,20,55,0.7);   border-color: rgb(96,20,55); }
    .stat-card.scoring_rate          { background-color: rgba(20,55,96,0.7);   border-color: rgb(20,55,96); }
    .stat-card.top_scoring_location  { background-color: rgba(96,23,20,0.7);   border-color: rgb(96,23,20); }
    .stat-card.high_score            { background-color: rgba(33,91,159,0.7);  border-color: rgb(33,91,159); }

    /* Prediction card */
    .prediction-card {
      background: var(--prediction-base);
      color: #111; padding:1rem; border:none; border-radius:10px;
      display:flex; flex-direction:column; align-items:center; text-align:center; overflow-y:auto;
      box-shadow:0 2px 8px var(--shadow);
    }
    .prediction-card.Blue { background: var(--prediction-blue); }
    .prediction-card.Red  { background: var(--prediction-red); }
    .prediction-card h3{ margin:.25rem 0 .5rem; font-size:1.2em; }

    /* Misc */
    .placeholder { color: var(--text-secondary); text-align:center; padding:2rem; grid-column:1/-1; }
    .loading { font-style: italic; color: var(--text-secondary); }
    #confetti-canvas { position: fixed; inset:0; width:100%; height:100%; z-index:1001; pointer-events: none; }
    .loader { border: 4px solid var(--border); border-top: 4px solid #3498db; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: 10px auto; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

    /* Fix oversized inner boxes inside robot cards */
.robot-card .stat-cards {
  width: 88%;
  height: 82%;
  max-width: 88%;
  max-height: 82%;
  aspect-ratio: 1 / 1;
  margin: auto;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  grid-template-rows: repeat(3, 1fr);
  gap: 0.35rem;
  place-items: center;
  box-sizing: border-box;
}

/* Make each stat-card respect its grid cell and not stretch */
.robot-card .stat-card {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  box-sizing: border-box;
}

/* Keep stat text perfectly centered */
.stat-card {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  text-align: center;
  border-radius: 6px;
  border: 2px solid;
  color: #fff;
  font-size: 0.78em;
  line-height: 1.15;
  padding: 0.25em;
  overflow-wrap: break-word;
  word-break: break-word;
  white-space: normal; /* allow text to wrap */
  box-sizing: border-box;
}

.stat-card .stat-label {
  font-size: 0.72em;
  margin-bottom: 0.2em;
  text-transform: uppercase;
  width: 100%;
  text-align: center;
}

.stat-card .stat-value {
  font-size: 1.05em;
  font-weight: 700;
  text-align: center;
  width: 100%;
}

#card_2{background-color: #333}
/* Prediction card text and table adjustments */
.prediction-card {
  max-height: 100%;
  overflow-y: auto;
  font-size: 0.9em;
  padding: 0.75rem;
  line-height: 1.2;
  word-wrap: break-word;
  text-align: center;
}

.prediction-card h3 {
  margin-bottom: 0.5rem;
  font-size: 1.1em;
}

.prediction-card p {
  margin: 0.3rem 0;
}

.prediction-card table {
  width: 95%;
  margin: 0.3rem auto;
  border-collapse: collapse;
  font-size: 0.9em;
}

.prediction-card th,
.prediction-card td {
  padding: 0.15rem 0.3rem;
  text-align: center;
  word-break: break-word;
}

.prediction-card th {
  font-weight: bold;
  border-bottom: 1px solid rgba(0,0,0,0.2);
}

.prediction-card td {
  border-bottom: 1px solid rgba(0,0,0,0.1);
}

@media (max-width: 600px) {
  .prediction-card {
    font-size: 0.8em;
    padding: 0.5rem;
  }
}
.summary {
  padding: 0.8rem 1rem;
  font-size: 0.9em;
  line-height: 1.45;
  color: var(--text-primary);
  text-align: left;
  /*background-color: rgba(255, 255, 255, 0.85);*/
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.1);
  width: 90%;
  margin: 0.5rem auto 1rem;
  overflow-wrap: break-word;
}

.dark .summary {
/*  background-color: rgba(30, 30, 30, 0.85);*/
  color: #111;
}
.summary strong {
  color: #1e73be;
  font-weight: 600;
}
.summary {
  padding: 0.8rem 1rem;
  font-size: 0.9em;
  line-height: 1.5;
  color: var(--text-primary);
  text-align: left;
  background-color: rgba(255, 255, 255, 0.85);
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.1);
  width: 90%;
  margin: 0.5rem auto 1rem;
  overflow-wrap: break-word;
}

.summary strong {
  color: #1e73be;
  font-weight: 600;
}

  </style>
</head>
<body>
  <canvas id="confetti-canvas"></canvas>
  <div class="container">
    <!-- Header row -->
    <div class="header-bar">
      <div class="brand">
        <img id="mainLogo" class="logo" src="../images/owlanalytics.png" alt="Owl TV Logo">
        <div class="title">Owl TV — <?= htmlspecialchars($selected_event ? $selected_event : 'Select Event') ?></div>
      </div>

      <form method="get" action="" class="controls-inline" style="flex:1; min-width:280px;">
        <label for="game">Game</label>
        <select name="game" id="game" onchange="this.form.submit()">
          <option value="">Choose a game</option>
          <?php foreach ($games as $game): ?>
            <option value="<?= htmlspecialchars($game) ?>" <?= ($game === $selected_game) ? 'selected' : '' ?>>
              <?= htmlspecialchars($game) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <?php if ($selected_game): ?>
          <label for="event_name">Event</label>
          <select name="event_name" id="event_name" onchange="this.form.submit()">
            <option value="">Choose an event</option>
            <?php foreach ($events as $event): ?>
              <option value="<?= htmlspecialchars($event) ?>" <?= ($event === $selected_event) ? 'selected' : '' ?>>
                <?= htmlspecialchars($event) ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>

        <?php if ($selected_game && $selected_event && !empty($unique_robots_at_event)): ?>
          <label for="sortOption">Sort</label>
          <select id="sortOption" onchange="updateRobotCards()">
            <option value="offense_points_score" selected>Offense Pts</option>
            <option value="avg_cycle_time">Cycle Time</option>
            <option value="auton_score">Auton Actions</option>
            <option value="defense_score">Defense Actions</option>
            <option value="scoring_rate">Scoring Rate</option>
          </select>
        <?php endif; ?>
      </form>

      <button id="themeToggle" title="Toggle Light/Dark">🌙</button>
    </div>

    <?php if ($selected_game && $selected_event && !empty($unique_robots_at_event)): ?>
      <div class="prediction-controls">
        <div class="dropdown-group">
          <label for="blue1">B1</label>
          <select id="blue1"><option value="">?</option></select>
          <label for="blue2">B2</label>
          <select id="blue2"><option value="">?</option></select>
          <label for="blue3">B3</label>
          <select id="blue3"><option value="">?</option></select>
        </div>
        <div class="dropdown-group">
          <label for="red1">R1</label>
          <select id="red1"><option value="">?</option></select>
          <label for="red2">R2</label>
          <select id="red2"><option value="">?</option></select>
          <label for="red3">R3</label>
          <select id="red3"><option value="">?</option></select>
        </div>
        <button type="button" onclick="cycleRobotCardViews('toggle')">Toggle Cycle</button>
      </div>
    <?php endif; ?>

    <div id="robotContainer" class="robot-cards">
      <?php if (!$selected_game || !$selected_event): ?>
        <p class="placeholder">Please select a game and event to view robot analysis.</p>
      <?php elseif (empty($unique_robots_at_event)): ?>
        <p class="placeholder">No robots found for this event in the scouting data.</p>
      <?php else: ?>
        <div id="predictionCard" class="card robot-card prediction-card">
          <h3>Match Prediction</h3>
          <p id="predictionResult"><span class="loading">Select all 6 robots...</span></p>
        </div>
        <p class="loading placeholder">Loading robot data...</p>
      <?php endif; ?>
    </div>
  </div>

  <script>
    /* --- Data from PHP --- */
    const G_ROBOT_DATA = <?php echo json_encode($event_analysis_results); ?>; // keyed by robot
    const G_UNIQUE_ROBOTS = <?php echo json_encode($unique_robots_at_event); ?>;
    const G_SELECTED_EVENT = <?php echo json_encode($selected_event); ?>;
const G_PRESELECT = <?php echo json_encode($dropdown_robots); ?>;

    let G_CYCLE_CARDS = true;
    let G_CYCLE_TIMEOUTS = {};
    let G_PREDICTION_REQUESTED = false;

    /* --- Confetti --- */
    function throwCrazyConfetti() {
      const canvas = document.getElementById('confetti-canvas');
      if (!canvas) return;
      const myConfetti = confetti.create(canvas, { resize: true });
      let count = 200; let defaults = { origin: { y: 0.7 }, scalar: 1.2, spread: 80 };
      function fire(particleRatio, opts) { myConfetti(Object.assign({}, defaults, opts, { particleCount: Math.floor(count * particleRatio) })); }
      fire(0.25, { spread: 26, startVelocity: 55 });
      fire(0.2,  { spread: 60 });
      fire(0.35, { spread: 100, decay: 0.91, scalar: 0.8 });
      fire(0.1,  { spread: 120, startVelocity: 25, decay: 0.92, scalar: 1.2 });
      fire(0.1,  { spread: 120, startVelocity: 45 });
      setTimeout(() => myConfetti.reset(), 5000);
    }

    /* --- Utils --- */
    function htmlspecialchars(str) {
      if (typeof str !== 'string' && typeof str !== 'number') str = String(str ?? '');
      return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    /* --- Render Cards --- */
    function displayRobotCards(robotsToDisplay) {
      const container = document.getElementById("robotContainer");
      if (!container) return;




      let html = `
         <div class="robot-card logoCard cardlogo" id="card_2">
        <a href=".">  <img class="logo2" src="../images/owlAnalytics.png" alt="Logo"></a>
        </div>
        <div id="predictionCard" class="card robot-card prediction-card">
          <h3>Match Prediction</h3>
          <p id="predictionResult"><span class="loading">Select all 6 robots...</span></p>
        </div>`;

      if (!robotsToDisplay || robotsToDisplay.length === 0) {
        container.innerHTML = html + `<p class="placeholder">No analysis data available to display.</p>`;
        return;
      }

      robotsToDisplay.forEach(robot => {
        const alliance = htmlspecialchars(robot.alliance || 'Unknown'); // only used for glow class
        const robotId = htmlspecialchars(robot.robot || 'N/A');

        const matchCount = htmlspecialchars(robot.match_count || 0);
        const avgCycleTime = parseFloat(robot.avg_cycle_time || 0).toFixed(1);
        const autonScore = parseFloat(robot.auton_score || 0).toFixed(1);
        const defenseScore = parseFloat(robot.defense_score || 0).toFixed(1);
        const offenseActions = parseFloat(robot.offense_actions_score || 0).toFixed(1);
        const offensePoints = parseFloat(robot.offense_points_score || 0).toFixed(1);
        const scoringRate = parseFloat(robot.scoring_rate || 0).toFixed(1);
        const topLocation = htmlspecialchars((robot.top_scoring_location || 'N/A').replace(/_/g, ' '));

        const highScore = htmlspecialchars(robot.high_score || 0);
        const highScoreMatch = htmlspecialchars(robot.high_score_match || 'N/A');
        const topLocationClass = topLocation.length > 25 ? 'small' : '';

        html += `
          <div class="robot-card ${alliance}" id="card-${robotId}">
            <div class="card-header">
              <span class="robot-id">${robotId}</span>
              <!-- Intentionally no alliance text -->
            </div>

            <div class="view">
              <div class="box_container">
                <div class="stat-cards">
                  <div class="stat-card match_count"><span class="stat-label">Matches</span><span class="stat-value">${matchCount}</span></div>
                  <div class="stat-card avg_cycle_time"><span class="stat-label">Cycle Time</span><span class="stat-value">${avgCycleTime}s</span></div>
                  <div class="stat-card auton_score"><span class="stat-label">Auton Actions</span><span class="stat-value">${autonScore}</span></div>
                  <div class="stat-card defense_score"><span class="stat-label">Defense Actions</span><span class="stat-value">${defenseScore}</span></div>
                  <div class="stat-card offense_actions_score"><span class="stat-label">Offense Actions</span><span class="stat-value">${offenseActions}</span></div>
                  <div class="stat-card offense_points_score"><span class="stat-label">Offense Points</span><span class="stat-value">${offensePoints}</span></div>
                  <div class="stat-card top_scoring_location"><span class="stat-label">Top Scoring</span><span class="stat-value ${topLocationClass}">${topLocation}</span></div>
                  <div class="stat-card scoring_rate"><span class="stat-label">Success Rate</span><span class="stat-value">${scoringRate}%</span></div>
                  <div class="stat-card high_score"><span class="stat-label">High Score</span><span class="stat-value small">${highScore} (M ${highScoreMatch})</span></div>
                </div>
              </div>
            </div>

    <!-- Chart View -->
    <div class="view chart-view">
      <canvas id="chart-${robotId}" width="250" height="200"></canvas>
    </div>
<div class="view">
<p class="summary">${robot.summary_text || 'No summary available'}</p>

</div>



          </div>`;
      });

    container.innerHTML = html;

// create chart in first view for each robot
robotsToDisplay.forEach(robot => {
  const ctx = document.getElementById(`chart-${robot.robot}`);
  if (!ctx) return;

  const data = [
   // robot.match_count,
   // robot.avg_cycle_time,
   // robot.auton_score,
  robot.defense_score,
   // robot.offense_actions_score,
   robot.offense_points_score,
   //robot.scoring_rate,
   robot.high_score
  ];

  // const labels = ["Matches","Cycle","Auton","Defense","Offense","Points","Rate","High"];
  
  
  const labels = ["Defense","Avg. Points","High Score"];

  new Chart(ctx, {
    type: "bar",
    data: {
      labels: labels,
      datasets: [{
        data: data,
        backgroundColor: [
          "rgba(20,96,61,0.7)",
          "rgba(33,91,159,0.7)",
          "rgba(96,23,20,0.7)",
          "rgba(96,61,20,0.7)",
          "rgba(96,20,55,0.7)",
          "rgba(96,20,55,0.7)",
          "rgba(20,55,96,0.7)",
          "rgba(33,91,159,0.7)"
        ],
        borderColor: [
          "rgb(20,96,61)",
          "rgb(33,91,159)",
          "rgb(96,23,20)",
          "rgb(96,61,20)",
          "rgb(96,20,55)",
          "rgb(96,20,55)",
          "rgb(20,55,96)",
          "rgb(33,91,159)"
        ],
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { color: "#fff" } }, x: { ticks: { color: "#fff" } } }
    }
  });
});



startCardCycling();



      // (Optional) per-card charts could be added here if desired

      if (G_CYCLE_CARDS) startCardCycling();
    }

    /* --- Sorting --- */
    function updateRobotCards() {
      const sortBy = document.getElementById("sortOption")?.value || 'offense_points_score';
      let dataArray = G_ROBOT_DATA ? Object.values(G_ROBOT_DATA) : [];

      dataArray.sort((a, b) => {
        const aValue = a?.[sortBy];
        const bValue = b?.[sortBy];
        const valA = (aValue==='N/A'||aValue===''||aValue===null||typeof aValue==='undefined') ? -Infinity : parseFloat(aValue);
        const valB = (bValue==='N/A'||bValue===''||bValue===null||typeof bValue==='undefined') ? -Infinity : parseFloat(bValue);
        const aIsNum = !isNaN(valA) && isFinite(valA);
        const bIsNum = !isNaN(valB) && isFinite(valB);
        if (aIsNum && bIsNum) return valB - valA; // Desc
        if (aIsNum) return -1;
        if (bIsNum) return 1;
        return 0;
      });

      stopCardCycling();
      displayRobotCards(dataArray);
    }

    /* --- Cycling --- */
/* --- Cycling --- */
function startCardCycling() {
  stopCardCycling();
  const robotCards = document.querySelectorAll(".robot-card");
  robotCards.forEach(card => {
    const views = card.querySelectorAll(".view");
    if (views.length < 2) return;
    let index = 0;
    function cycle() {
      views.forEach((v, i) => v.classList.toggle("active", i === index));
      index = (index + 1) % views.length;
      const delay = 4000 + Math.random() * 6000;
      G_CYCLE_TIMEOUTS[card.id] = setTimeout(cycle, delay);
    }
    const initialDelay = 2000 + Math.random() * 4000;
    G_CYCLE_TIMEOUTS[card.id] = setTimeout(cycle, initialDelay);
  });
}

function stopCardCycling() {
  for (const id in G_CYCLE_TIMEOUTS) clearTimeout(G_CYCLE_TIMEOUTS[id]);
  G_CYCLE_TIMEOUTS = {};
}

function cycleRobotCardViews(command) {
  if (command === "toggle") G_CYCLE_CARDS = !G_CYCLE_CARDS;
  else if (command === "start") G_CYCLE_CARDS = true;
  else if (command === "stop") G_CYCLE_CARDS = false;
  if (G_CYCLE_CARDS) startCardCycling();
  else stopCardCycling();
}

    function stopCardCycling() {
      for (const cardId in G_CYCLE_TIMEOUTS) clearTimeout(G_CYCLE_TIMEOUTS[cardId]);
      G_CYCLE_TIMEOUTS = {};
    }
    function cycleRobotCardViews(command) {
      if (command === 'toggle') G_CYCLE_CARDS = !G_CYCLE_CARDS;
      else if (command === 'start') G_CYCLE_CARDS = true;
      else if (command === 'stop') G_CYCLE_CARDS = false;
      if (G_CYCLE_CARDS) startCardCycling(); else stopCardCycling();
    }

   /* --- Prediction dropdowns --- */
function populateAllRobotDropdowns() {
  const ids = ["blue1","blue2","blue3","red1","red2","red3"];
  // combine robots seen before and robots for the active match
  let allRobots = Array.isArray(G_UNIQUE_ROBOTS) ? [...G_UNIQUE_ROBOTS] : [];
  for (const r of Object.values(G_PRESELECT || {})) {
    if (r && !allRobots.includes(r)) allRobots.push(r);
  }
  allRobots.sort((a,b)=>parseInt(a)-parseInt(b));

  ids.forEach(id => {
    const dd = document.getElementById(id);
    if (!dd) return;
    dd.innerHTML = "<option value=''>Robot?</option>";
    allRobots.forEach(robot => {
      const opt = document.createElement("option");
      opt.value = robot;
      opt.textContent = robot;
      dd.appendChild(opt);
    });
    // preselect from PHP data if available
    const selected = (G_PRESELECT && G_PRESELECT[id]) ? G_PRESELECT[id] : "";
    if (selected && allRobots.includes(selected)) dd.value = selected;
    dd.addEventListener("change", checkAllDropdownsFilled);
  });

  checkAllDropdownsFilled();
}

    function checkAllDropdownsFilled() {
      const ids = ["blue1", "blue2", "blue3", "red1", "red2", "red3"];
      const allFilled = ids.every(id => document.getElementById(id)?.value.trim() !== "");
      if (allFilled && !G_PREDICTION_REQUESTED) {
        G_PREDICTION_REQUESTED = true;
        sendPredictionRequest();
      } else if (!allFilled) {
        resetPredictionDisplay(); G_PREDICTION_REQUESTED = false;
      }
    }

    function resetPredictionDisplay() {
      const predictionResultEl = document.getElementById("predictionResult");
      const predictionCardEl = document.getElementById("predictionCard");
      if (predictionResultEl) predictionResultEl.innerHTML = '<span class="loading">Select all 6 robots...</span>';
      if (predictionCardEl) predictionCardEl.classList.remove('Blue','Red');
    }

    function showPredictionLoading() {
      const c = document.getElementById("predictionCard");
      if (!c) return;
      c.classList.remove('Blue','Red');
      c.innerHTML = '<h3>Match Prediction</h3><div class="loader"></div><p class="loading">Calculating prediction...</p>';
    }

    function sendPredictionRequest() {
      showPredictionLoading();
      const blueAllianceRobots = ["blue1","blue2","blue3"].map(id => document.getElementById(id)?.value).filter(Boolean);
      const redAllianceRobots  = ["red1","red2","red3"].map(id => document.getElementById(id)?.value).filter(Boolean);
      if (blueAllianceRobots.length !== 3 || redAllianceRobots.length !== 3) {
        resetPredictionDisplay(); G_PREDICTION_REQUESTED = false; return;
      }
      const eventName = G_SELECTED_EVENT;
      const matchNumber = 'predict';
      const hist_weight = 0.5;
  //const apiUrl = `predict.php?event_name=${encodeURIComponent(eventName)}&match_no=${encodeURIComponent(matchNumber)}&blue_alliance=${encodeURIComponent(blueAllianceRobots.join(','))}&red_alliance=${encodeURIComponent(redAllianceRobots.join(','))}&hist_weight=${encodeURIComponent(hist_weight)}`;


const apiUrl =
  `https://theconspiracyshirtcompany.com/predict/` +
  `?event_name=${encodeURIComponent(eventName)}` +
  `&match_no=1313` +
  `&blue_alliance=${encodeURIComponent(blueAllianceRobots.join(','))}` +
  `&red_alliance=${encodeURIComponent(redAllianceRobots.join(','))}` +
  `&hist_weight=${encodeURIComponent(hist_weight)}`;


//const apiUrl = `https://theconspiracyshirtcompany.com/predict?event_name=${encodeURIComponent(eventName)}&match_no=1313&blue_alliance=${encodeURIComponent(blueAllianceRobots.join(','))}&red_alliance=${encodeURIComponent(redAllianceRobots.join(','))}&hist_weight=${encodeURIComponent(hist_weight)}`;


//const apiUrl = `https://owl-prediction-api.onrender.com/predict?event_name//=${encodeURIComponent(eventName)}&match_no=${encodeURIComponent(matchNumber//)}&blue_alliance=${encodeURIComponent(blueAllianceRobots.join(','//))}&red_alliance=${encodeURIComponent(redAllianceRobots.join(','//))}&hist_weight=${encodeURIComponent(hist_weight)}`;
//
    
    
    
      fetch(apiUrl)
        .then(r => { if (!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
        .then(data => { displayPredictionResults(data); G_PREDICTION_REQUESTED = false; throwCrazyConfetti(); })
        .catch(err => {
          const el = document.getElementById("predictionResult");
          if (el) el.innerHTML = `<span style="color:red;">Prediction Error: ${err.message}</span>`;
          G_PREDICTION_REQUESTED = false;
        });
    }

    function buildPredictionTable(contributions) {
      if (!contributions || !Array.isArray(contributions) || contributions.length === 0) return '<p>No contribution data.</p>';
      let t = '<table><thead><tr><th>Robot</th><th>Predicted Pts</th></tr></thead><tbody>';
      contributions.forEach(item => {
        const robot = htmlspecialchars(item.robot || '?');
        const points = parseFloat(item.predicted_ppm || 0).toFixed(2);
        t += `<tr><td>${robot}</td><td>${points}</td></tr>`;
      });
      t += '</tbody></table>';
      return t;
    }

    function displayPredictionResults(data) {
      const card = document.querySelector(".prediction-card");

      if (!card) return;
      card.classList.remove('Blue','Red');

      if (data.error) {
        card.innerHTML = `<h3>Match Prediction</h3><p style="color:red;">Error: ${htmlspecialchars(data.error)}</p>`;
        return;
      }
      const blueScore = parseFloat(data.blue_score || 0).toFixed(1);
      const redScore  = parseFloat(data.red_score || 0).toFixed(1);
      const winner    = String(data.predicted_winner || '').toLowerCase();

      let html = `<h3>Prediction Results</h3>`;
      html += `<p><strong>Blue Alliance Score: ${blueScore}</strong></p>`;
      html += buildPredictionTable(data.blue_contributions);
      html += `<p><strong>Red Alliance Score: ${redScore}</strong></p>`;
      html += buildPredictionTable(data.red_contributions);
      html += `<p><strong>Predicted Winner: ${winner.includes('blue') ? 'Blue' : winner.includes('red') ? 'Red' : 'Unknown'}</strong></p>`;
      card.innerHTML = html;

      if (winner.includes('blue')) card.classList.add('Blue');
      else if (winner.includes('red')) card.classList.add('Red');
    }

    /* --- Initial load --- */
document.addEventListener("DOMContentLoaded", function() {
  populateAllRobotDropdowns();
  updateRobotCards();

  // ensure first .view is visible after rendering
  setTimeout(() => {
    document.querySelectorAll(".robot-card .view:first-child")
      .forEach(v => v.classList.add("active"));
  }, 500);
});

    /* --- Theme toggle (persistent) --- */
    const bodyEl = document.body;
    const themeBtn = document.getElementById('themeToggle');
    const savedTheme = localStorage.getItem('statOwlTheme') || 'dark';
    applyTheme(savedTheme);

    themeBtn.addEventListener('click', () => {
      const next = bodyEl.classList.contains('dark') ? 'light' : 'dark';
      applyTheme(next);
    });

    function applyTheme(theme) {
      if (theme === 'dark') {
        bodyEl.classList.add('dark');
        themeBtn.textContent = '🌙';
      } else {
        bodyEl.classList.remove('dark');
        themeBtn.textContent = '☀️';
      }
      localStorage.setItem('statOwlTheme', theme);
    }
  </script>
</body>
</html>
