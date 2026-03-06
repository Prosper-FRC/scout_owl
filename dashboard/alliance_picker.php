<?php
// alliance_picker.php

// Enable error reporting for debugging.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Use external database connection.
require_once '../php/database_connection.php';

try {
    // Create PDO connection using external configuration.
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Retrieve distinct event names from scouting_submissions.
try {
    $stmt = $pdo->query("SELECT DISTINCT event_name FROM scouting_submissions ORDER BY event_name");
    $events = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    die("Error fetching events: " . $e->getMessage());
}

// Get selected parameters from GET.
$selected_event = isset($_GET['event_name']) ? $_GET['event_name'] : "";
$selected_robot = isset($_GET['robot']) ? $_GET['robot'] : "";
$tba_event_key  = isset($_GET['event_key']) ? $_GET['event_key'] : "";
$analysisData   = null;

// If an event is selected, get unique robots for that event.
$robots = array();
if (!empty($selected_event)) {
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT robot FROM scouting_submissions WHERE event_name = :event");
        $stmt->execute(['event' => $selected_event]);
        $robots = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        die("Error fetching robots: " . $e->getMessage());
    }
}

// --- Fill the Event Key Dropdown via TBA API ---
// Use a default year.
$defaultYear = "2025";
function get_event_keys_by_event_name($event_name, $year, $TBA_AUTH_KEY) {
    $url = "https://www.thebluealliance.com/api/v3/events/" . $year;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-TBA-Auth-Key: " . $TBA_AUTH_KEY]);
    $response = curl_exec($ch);
    if ($response === false) {
        curl_close($ch);
        return [];
    }
    curl_close($ch);
    
    $eventsTBA = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }
    
    $keys = [];
    // Try to match based on the event's "name" field.
    foreach ($eventsTBA as $event) {
        if (isset($event['name']) && stripos($event['name'], $event_name) !== false) {
            if (isset($event['key'])) {
                $keys[] = $event['key'];
            }
        }
    }
    // If no keys match, return all keys.
    if (empty($keys)) {
        foreach ($eventsTBA as $event) {
            if (isset($event['key'])) {
                $keys[] = $event['key'];
            }
        }
    }
    return $keys;
}

// Your TBA API key.
$TBA_AUTH_KEY = "iPU2nNv1lDHD3m03JwnaqsCxsJhHLmXuRU0Te4xNoyBvOjxq5nYvsWlpd3bJH0kc";

// Get event keys using the helper function.
$event_keys = array();
if (!empty($selected_event)) {
    $event_keys = get_event_keys_by_event_name($selected_event, $defaultYear, $TBA_AUTH_KEY);
}

// --- Helper Function: get_team_nickname using /tmp/cache ---
function get_team_nickname($teamNumber, $TBA_AUTH_KEY) {
    $cacheDir = '/tmp/cache';
    if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0777, true);
    }
    $cacheFile = $cacheDir . "/team_" . $teamNumber . ".json";
    $cacheTime = 12 * 3600; // 12 hours

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if ($data && isset($data["nickname"])) {
            return $data["nickname"];
        }
    }
    $teamKey = "frc" . $teamNumber;
    $url = "https://www.thebluealliance.com/api/v3/team/" . urlencode($teamKey);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers = [ "X-TBA-Auth-Key: " . $TBA_AUTH_KEY ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    if ($response === false) {
        curl_close($ch);
        return "Unknown";
    }
    curl_close($ch);

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return "Unknown";
    }
    if (isset($data["nickname"])) {
        file_put_contents($cacheFile, $response);
        return $data["nickname"];
    }
    return "Unknown";
}

// --- Determine "Scores Algae" flag ---
// Instead of basing it on "most_common_action", we now check a field "algae_scored"
// from the analysis data (set to true if the robot has ever scored algae in processor or net).
function compute_scores_algae($robotData) {
    // If the analysis data already provides an "algae_scored" flag, use it.
    if (isset($robotData['algae_scored'])) {
        return ($robotData['algae_scored'] == true) ? "Yes" : "No";
    }
    // Fallback: check the most common action.
    if (isset($robotData['most_common_action']) && ((stripos($robotData['most_common_action'], "processor") !== false) || (stripos($robotData['most_common_action'], "net") !== false))) {
        return "Yes";
    }
    return "No";
}

// If all parameters (selected event, selected robot, and selected event key) are provided, call the Flask analysis service.
if (!empty($selected_event) && !empty($selected_robot) && !empty($tba_event_key)) {
    $analyzeUrl = "http://heconspiracyshirtcompany.com/aliance-picker?event=" . urlencode($selected_event) .
                  "&robot=" . urlencode($selected_robot) .
                  "&event_key=" . urlencode($tba_event_key);
    $analysisResponse = file_get_contents($analyzeUrl);
    $analysisData = json_decode($analysisResponse, true);
    
    // Sort full_candidate_analysis by numeric ranking (ascending).
    if (isset($analysisData['full_candidate_analysis']) && is_array($analysisData['full_candidate_analysis'])) {
        usort($analysisData['full_candidate_analysis'], function($a, $b) {
            $rankA = (isset($a['ranking']) && is_numeric($a['ranking'])) ? (int)$a['ranking'] : 9999;
            $rankB = (isset($b['ranking']) && is_numeric($b['ranking'])) ? (int)$b['ranking'] : 9999;
            return $rankA - $rankB;
        });
    }
}

// Extract the year from the selected event key (first 4 characters).
$year = "";
if (!empty($tba_event_key)) {
    $year = substr($tba_event_key, 0, 4);
}
$avatarBase = "https://www.thebluealliance.com/avatar/" . $year . "/frc";
$teamPageBase = "https://www.thebluealliance.com/team/";
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Alliance Analysis</title>
  <link rel="stylesheet" href="../css/select.css">
  <style>
    /* --- Font Faces --- */
    @font-face {
      font-family: 'Roboto';
      src: url('/../stat_goblin/fonts/roboto/Roboto-Regular.ttf') format('truetype');
      font-weight: normal;
      font-style: normal;
    }
    @font-face {
      font-family: 'Griffy';
      src: url('/../stat_goblin/fonts/Griffy/Griffy-Regular.ttf') format('truetype');
      font-weight: normal;
      font-style: normal;
    }
    @font-face {
      font-family: 'Comfortaa';
      src: url('/../stat_goblin/fonts/Comfortaa/Comfortaa-VariableFont_wght.ttf') format('truetype');
      font-weight: normal;
      font-style: normal;
    }
    /* Global Styles */
    body, html {
      font-family: 'Comfortaa', sans-serif;
      margin: 0;
      padding: 0;
      background: #222;
      color: #eee;
      line-height: 1.5;
    }
    .logo {
      width: 400px;
      display: block;
      margin: 0 auto 1rem auto;
    }
    .containerOuter {
      background-color: #333;
      border-bottom: 1px solid #444;
      width: 100%;
      padding: 1rem;
      box-sizing: border-box;
    }
    .container {
      max-width: 800px;
      margin: auto;
    }
    h1, h2 { color: #fff; }
    input[type="submit"], input[type="text"] {
      font-size: 1.1rem;
      padding: 12px;
      border: 1px solid #fff;
      background-color: #222;
      color: #fff;
      border-radius: 5px;
      margin: 10px 0;
      width: 180px;
    }
    select { width: 200px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th, td { padding: 8px; text-align: center; border: 1px solid #ddd; }
    th { background-color: #333; }
    img { border-radius: 4px; }
    /* Styles for logo container and robot number */
    .logo-container {
        width: 50px;
        text-align: center;
    }
    .robot-number {
        width: 50px;
        font-size: 0.8rem;
        margin-top: 2px;
    }


    /* Modal Styles */
.modal {
  display: none; /* Hidden by default */
  position: fixed;
  z-index: 100;
  left: 0;
  top: 0;
  width: 100%; 
  height: 100%; 
  overflow: auto;
  background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
}

.modal-content {
  background-color: #fff;
  margin: 15% auto;
  padding: 20px;
  border: 1px solid #888;
  width: 80%;
  color: #000;
  border-radius: 5px;
  position: relative;
}

.close {
  color: #aaa;
  position: absolute;
  top: 10px;
  right: 20px;
  font-size: 28px;
  font-weight: bold;
  cursor: pointer;
}

.close:hover,
.close:focus {
  color: #000;
  text-decoration: none;
}

  </style>
</head>
<body>
  <div class="containerOuter">
    <div class="container">



      <a href="."><img src="../images/owlAnalytics.png" class="logo" alt="Logo"></a>

      <form method="GET" action="alliance_picker.php">
          <!-- Event Name Dropdown -->
          <select name="event_name" id="event_name" onchange="this.form.submit()">
              <option value="">-- Select an Event --</option>
              <?php foreach ($events as $ev): ?>
                  <option value="<?php echo htmlspecialchars($ev); ?>" <?php if ($ev == $selected_event) echo "selected"; ?>>
                      <?php echo htmlspecialchars($ev); ?>
                  </option>
              <?php endforeach; ?>
          </select>
          
          <?php if (!empty($selected_event)): ?>
              <!-- Event Key Dropdown (populated from TBA API) -->
              <select name="event_key" id="event_key">
                  <option value="">Select Event Key</option>
                  <?php foreach ($event_keys as $key): ?>
                      <option value="<?php echo htmlspecialchars($key); ?>" <?php if ($key == $tba_event_key) echo "selected"; ?>>
                          <?php echo htmlspecialchars($key); ?>
                      </option>
                  <?php endforeach; ?>
              </select>
              
              <!-- Robot Dropdown -->
              <select name="robot" id="robot">
                  <option value="">Select Your Robot</option>
                  <?php foreach ($robots as $r): ?>
                      <option value="<?php echo htmlspecialchars($r); ?>" <?php if ($r == $selected_robot) echo "selected"; ?>>
                          <?php echo htmlspecialchars($r); ?>
                      </option>
                  <?php endforeach; ?>
              </select>
              <input type="submit" value="Analyze">
          <?php endif; ?>
      </form>
      
    
      

<!-- Modal Popup -->
<div id="popupModal" class="modal">
  <div class="modal-content">
    <span id="closeBtn" class="close">&times;</span>
    <p id="modalText"></p>
  </div>
</div>





      <?php if ($analysisData): ?>
          <!-- First Pick Options -->
          <h2>First Pick Options</h2>
          <table>
              <thead>
    <tr>
        <th>Logo</th>
        <th>Nickname</th>
        <th>Rank</th>
        <th>
            Predicted Pts/Match
            <span class="info-icon" data-definition="Summary

Predicted Points - Data Aggregation:
The code processes historical match data to compute both a straightforward historical average of points per match and multiple performance metrics for each robot.
Model Training:
A RandomForestRegressor is trained on robot features (like historical averages, success rates, and event counts) to predict the total points scored by each robot across matches.
Prediction Calculation:
The trained model predicts the total points, which is then divided by the number of matches to obtain a per-match average. Finally, this model-based prediction is blended with the simple historical average to produce a balanced and more robust estimate of predicted points per match.
This multi-step approach leverages both the historical performance data and the model’s capacity to capture nonlinear relationships among multiple factors, giving teams a more nuanced prediction for their scoring performance in upcoming matches." style="cursor: pointer;">[i]</span>
        </th>
        <th>
            Cycle Time (sec)
            <span class="info-icon" data-definition="Cycle time: estimated as 150 / (scoring_events) for a match, assuming a fixed match duration (150 seconds). This acts as a rough proxy for how quickly a robot scores." style="cursor: pointer;">[i]</span>
        </th>

<th>
    Auton Score
    <span class="info-icon" data-definition="Autonomous Score: The maximum count of successful scoring actions during the autonomous period (<=15 sec) per match. It indicates the robot's early-game performance." style="cursor: pointer;">[i]</span>
</th>



        <th>
            Favorite Scoring
            <span class="info-icon" data-definition="The scoring action most frequently performed by the team." style="cursor: pointer;">[i]</span>
        </th>
    </tr>
</thead>

              <tbody>
                  <?php foreach ($analysisData['first_pick_options'] as $opt): 
                        $nickname = get_team_nickname($opt['robot'], $TBA_AUTH_KEY);
                        $scoresAlgae = (isset($opt['algae_scored']) && $opt['algae_scored'] == true) ? "Yes" : "No";
                  ?>
                  <tr>
                      <td>
                          <div class="logo-container">
                              <a href="<?php echo $teamPageBase . htmlspecialchars($opt['robot']); ?>">
                                  <img src="<?php echo $avatarBase . htmlspecialchars($opt['robot']); ?>.png" width="50" height="50" alt="Logo"
                                  onerror="this.onerror=null; this.src='../images/first_logo.png';">
                              </a>
                              <div class="robot-number"><?php echo htmlspecialchars($opt['robot']); ?></div>
                          </div>
                      </td>
                      <td><?php echo htmlspecialchars($nickname); ?></td>
                      <td><?php echo htmlspecialchars($opt['ranking']); ?></td>
                      <td><?php echo number_format($opt['predicted_avg_pts_per_match'], 2); ?></td>
                      <td><?php echo number_format($opt['baseline_cycle'], 2); ?></td>
                      <td><?php echo number_format($opt['auton_score'], 2); ?></td>

                      <td><?php echo htmlspecialchars($opt['most_common_action']); ?></td>
                  
                  </tr>
                  <?php endforeach; ?>
              </tbody>
          </table>
          
          <!-- Second Pick Options Offense -->
          <h2>Second Pick Options Offense</h2>
          <table>
 <thead>
    <tr>
        <th>Logo</th>
        <th>Nickname</th>
        <th>Rank</th>
        <th>
            Predicted Pts/Match
            <span class="info-icon" data-definition="Summary

Predicted Points - Data Aggregation:
The code processes historical match data to compute both a straightforward historical average of points per match and multiple performance metrics for each robot.
Model Training:
A RandomForestRegressor is trained on robot features (like historical averages, success rates, and event counts) to predict the total points scored by each robot across matches.
Prediction Calculation:
The trained model predicts the total points, which is then divided by the number of matches to obtain a per-match average. Finally, this model-based prediction is blended with the simple historical average to produce a balanced and more robust estimate of predicted points per match.
This multi-step approach leverages both the historical performance data and the model’s capacity to capture nonlinear relationships among multiple factors, giving teams a more nuanced prediction for their scoring performance in upcoming matches." style="cursor: pointer;">[i]</span>
        </th>
        <th>
            Cycle Time (sec)
            <span class="info-icon" data-definition="Cycle time: estimated as 150 / (scoring_events) for a match, assuming a fixed match duration (150 seconds). This acts as a rough proxy for how quickly a robot scores." style="cursor: pointer;">[i]</span>
        </th>

<th>
    Auton Score
    <span class="info-icon" data-definition="Autonomous Score: The maximum count of successful scoring actions during the autonomous period (<=15 sec) per match. It indicates the robot's early-game performance." style="cursor: pointer;">[i]</span>
</th>



        <th>
            Favorite Scoring
            <span class="info-icon" data-definition="The scoring action most frequently performed by the team." style="cursor: pointer;">[i]</span>
        </th>
    </tr>
</thead>
              <tbody>
                  <?php foreach ($analysisData['second_pick_options_offense'] as $opt): 
                        $nickname = get_team_nickname($opt['robot'], $TBA_AUTH_KEY);
                        $scoresAlgae = (isset($opt['algae_scored']) && $opt['algae_scored'] == true) ? "Yes" : "No";
                  ?>
                  <tr>
                      <td>
                          <div class="logo-container">
                              <a href="<?php echo $teamPageBase . htmlspecialchars($opt['robot']); ?>">
                                  <img src="<?php echo $avatarBase . htmlspecialchars($opt['robot']); ?>.png" width="50" height="50" alt="Logo"
                                  onerror="this.onerror=null; this.src='../images/first_logo.png';">
                              </a>
                              <div class="robot-number"><?php echo htmlspecialchars($opt['robot']); ?></div>
                          </div>
                      </td>
                      <td><?php echo htmlspecialchars($nickname); ?></td>
                      <td><?php echo htmlspecialchars($opt['ranking']); ?></td>
                      <td><?php echo number_format($opt['predicted_avg_pts_per_match'], 2); ?></td>
                      <td><?php echo number_format($opt['baseline_cycle'], 2); ?></td>
                      <td><?php echo number_format($opt['auton_score'], 2); ?></td>
                      <td><?php echo htmlspecialchars($opt['most_common_action']); ?></td>
           
                  </tr>
                  <?php endforeach; ?>
              </tbody>
          </table>
          
          <!-- Defensive Options  -->
          <h2>Defensive Options</h2>
          <table>
<thead>
    <tr>
        <th>Logo</th>
        <th>Nickname</th>
        <th>Rank</th>
           <th>
            Predicted Pts/Match
            <span class="info-icon" data-definition="Summary

Predicted Points - Data Aggregation:
The code processes historical match data to compute both a straightforward historical average of points per match and multiple performance metrics for each robot.
Model Training:
A RandomForestRegressor is trained on robot features (like historical averages, success rates, and event counts) to predict the total points scored by each robot across matches.
Prediction Calculation:
The trained model predicts the total points, which is then divided by the number of matches to obtain a per-match average. Finally, this model-based prediction is blended with the simple historical average to produce a balanced and more robust estimate of predicted points per match.
This multi-step approach leverages both the historical performance data and the model’s capacity to capture nonlinear relationships among multiple factors, giving teams a more nuanced prediction for their scoring performance in upcoming matches." style="cursor: pointer;">[i]</span>
        </th>
 
        <th>
    Auton Score
    <span class="info-icon" data-definition="Autonomous Score: The maximum count of successful scoring actions during the autonomous period (<=15 sec) per match. It indicates the robot's early-game performance." style="cursor: pointer;">[i]</span>
</th>
        <th>
            Defensive Impact (sec)
            <span class="info-icon" data-definition="Defensive Impact (sec)
Calculation:
For each match, the code computes a “cycle time” – the effective time taken by a robot to score, calculated as
match_cycle_time
=
150/
scoring_events


 
It also calculates a “baseline cycle time” based on the robot’s overall scoring frequency (i.e., the expected cycle time without defensive interference). Then, for matches where the opponent’s defense is flagged, the difference

def_delta
=
match_cycle_time
−
baseline_cycle
def_delta=match_cycle_time−baseline_cycle
is computed and averaged over such matches to yield the Defensive Impact, representing in seconds how much longer a robot’s cycle is when facing effective defense." style="cursor: pointer;">[i]</span>
        </th>
        <th>
            Defensive Effect: Slowdown (sec)
            <span class="info-icon" data-definition="Defensive Effect: Slowdown (sec)
Calculation:
For each match where a robot performs a defensive action, the code looks at the opposing alliance’s performance. It calculates:
The opponents’ average cycle time from that match.
The opponents’ baseline cycle time (an average expected cycle time based on historical performance).
The difference

delta_cycle
=
opponents’ average cycle time
−
baseline cycle time
delta_cycle=opponents’ average cycle time−baseline cycle time
is computed for each match and then averaged across all relevant matches. This average difference is reported as Defensive Effect: Slowdown (sec), which estimates how many extra seconds the defense adds to opponents’ cycle times." style="cursor: pointer;">[i]</span>
        </th>
        <th>
            Defensive Effect: Points Reduction
            <span class="info-icon" data-definition="Defensive Effect: Points Reduction
Calculation:
Similarly, for each match where there is a defensive action:
The opponents’ actual average points scored in that match are computed.
The opponents’ expected (or baseline) average points per match are determined from historical data.
Their difference

delta_points
=
opponents’ average points
−
baseline average points
delta_points=opponents’ average points−baseline average points
is then averaged over the matches. This yields the Defensive Effect: Points Reduction, which quantifies on average how many points the defense is expected to take away from the opponents’ scoring." style="cursor: pointer;">[i]</span>
        </th>
    </tr>
</thead>

              <tbody>
                  <?php foreach ($analysisData['defensive_options'] as $opt): 
                        $nickname = get_team_nickname($opt['robot'], $TBA_AUTH_KEY);
                        $scoresAlgae = (isset($opt['algae_scored']) && $opt['algae_scored'] == true) ? "Yes" : "No";
                  ?>
                  <tr>
                      <td>
                          <div class="logo-container">
                              <a href="<?php echo $teamPageBase . htmlspecialchars($opt['robot']); ?>">
                                  <img src="<?php echo $avatarBase . htmlspecialchars($opt['robot']); ?>.png" width="50" height="50" alt="Logo"
                                  onerror="this.onerror=null; this.src='../images/first_logo.png';">
                              </a>
                              <div class="robot-number"><?php echo htmlspecialchars($opt['robot']); ?></div>
                          </div>
                      </td>
                      <td><?php echo htmlspecialchars($nickname); ?></td>
                      <td><?php echo htmlspecialchars($opt['ranking']); ?></td>
                      <td><?php echo number_format($opt['predicted_avg_pts_per_match'], 2); ?></td>

                      <td><?php echo number_format($opt['auton_score'], 2); ?></td>
<td><?php echo number_format($opt['defensive_impact_delta'] ?? 0, 2); ?></td>
<td><?php echo number_format($opt['def_effect_cycle'] ?? 0, 2); ?></td>
<td><?php echo number_format($opt['def_effect_points'] ?? 0, 2); ?></td>

                  </tr>
                  <?php endforeach; ?>
              </tbody>
          </table>
          
          <!-- Full Candidate Analysis -->
          <h2>Full Candidate Analysis</h2>
          <table>
              <thead>
                  <tr>
                      <th>Logo</th>
                      <th>Nickname</th>
                      <th>Rank</th>
                      <th>Explanation</th>
                  </tr>
              </thead>
              <tbody>
                  <?php foreach ($analysisData['full_candidate_analysis'] as $detail): 
                        $nickname = get_team_nickname($detail['robot'], $TBA_AUTH_KEY);
                        $scoresAlgae = (isset($detail['algae_scored']) && $detail['algae_scored'] == true) ? "Yes" : "No";
                  ?>
                  <tr>
                      <td>
                          <div class="logo-container">
                              <a href="<?php echo $teamPageBase . htmlspecialchars($detail['robot']); ?>">
                                  <img src="<?php echo $avatarBase . htmlspecialchars($detail['robot']); ?>.png" width="50" height="50" alt="Logo"
                                  onerror="this.onerror=null; this.src='../images/first_logo.png';">
                              </a>
                              <div class="robot-number"><?php echo htmlspecialchars($detail['robot']); ?></div>
                          </div>
                      </td>
                      <td><?php echo htmlspecialchars($nickname); ?></td>
                        <td><?php echo htmlspecialchars($detail['ranking']); ?></td>

                      <td><?php echo htmlspecialchars($detail['explanation']); ?></td>
                  </tr>
                  <?php endforeach; ?>
              </tbody>
          </table>
          
          <!-- Defensive Effects Summary -->

          <!--
          <h2>Defensive Effects Summary</h2>
          <table>
              <thead>
                  <tr>
                      <th>Team (Defending)</th>
                      <th>Opponents Avg Cycle Slowdown (sec)</th>
                      <th>Opponents Avg Points Reduction</th>
                  </tr>
              </thead>
              <tbody>
                  <?php foreach ($analysisData['defensive_effects_summary'] as $d): ?>
                  <tr>
                      <td><?php echo htmlspecialchars($d['robot']); ?></td>
                      <td><?php echo number_format($d['def_effect_cycle'], 2); ?></td>
                      <td><?php echo number_format($d['def_effect_points'], 2); ?></td>
                  </tr>
                  <?php endforeach; ?>
              </tbody>
          </table>
-->


      <?php endif; ?>
    </div>
  </div>



<script>
// Select the modal elements
var modal = document.getElementById("popupModal");
var modalText = document.getElementById("modalText");
var closeBtn = document.getElementById("closeBtn");

// Attach click event listeners to all info icons
document.querySelectorAll(".info-icon").forEach(function(icon) {
    icon.addEventListener("click", function() {
        var definition = this.getAttribute("data-definition");
        modalText.textContent = definition;
        modal.style.display = "block";
    });
});

// Close the modal when the "x" is clicked
closeBtn.addEventListener("click", function() {
    modal.style.display = "none";
});

// Close the modal when clicking outside the modal content area
window.addEventListener("click", function(event) {
    if (event.target == modal) {
        modal.style.display = "none";
    }
});
</script>



</body>
</html>
