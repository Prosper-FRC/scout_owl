<?php
require_once '../php/database_connection.php';

$pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

header('Content-Type: application/json');
// Clean (flush) any previously buffered output
ob_clean();

if (!isset($_GET['robotList']) || empty($_GET['robotList'])) {
    echo json_encode(["error" => "Missing robot list number."]);
    exit;
}

// Get the robot list as a comma-separated string and convert it to an array
$robotListStr = $_GET['robotList'];  // e.g. "r1,r2,r3"
$robotListArray = array_map('trim', explode(',', $robotListStr));

// Build a string of placeholders for each robot in the list (e.g. "?,?,?")
$placeholders = implode(',', array_fill(0, count($robotListArray), '?'));

try {
    // Use the placeholders in the IN clause of your query.
    $query = "
SELECT 
    robot,

    points,
    offense_scores,
    success_rate,
    defense_score,
    RANK() OVER (PARTITION BY robot ORDER BY match_no ASC) AS match_rank
from
(SELECT *
FROM (
  SELECT 
    robot,
    match_no,
    points,
    offense_scores,
    success_rate,
    defense_score,
    RANK() OVER (PARTITION BY robot ORDER BY match_no DESC) AS match_rank
  FROM (
    SELECT 
      robot,
      match_no,
      SUM(points) AS points,
      COUNT(CASE 
              WHEN action IN ('scores_coral_level_1', 'scores_coral_level_2', 'scores_coral_level_3', 'scores_coral_level_4', 'scores_algae_net', 'scores_algae_processor')
                AND result = 'success' THEN 1 
              ELSE NULL 
            END) AS offense_scores,
      COUNT(CASE 
              WHEN action IN ('plays_defense', 'attempts_to_descore')
                THEN 1 
              ELSE NULL 
            END) AS defense_score,
      COUNT(CASE 
              WHEN action IN ('scores_coral_level_1', 'scores_coral_level_2', 'scores_coral_level_3', 'scores_coral_level_4', 'scores_algae_net', 'scores_algae_processor')
                AND result = 'success' THEN 1 
              ELSE NULL 
            END) / COUNT(CASE 
                          WHEN action IN ('scores_coral_level_1', 'scores_coral_level_2', 'scores_coral_level_3', 'scores_coral_level_4', 'scores_algae_net', 'scores_algae_processor')
                            THEN 1 
                          ELSE NULL 
                        END) AS success_rate
    FROM scouting_submissions
    WHERE event_name = (SELECT event_name 
                        FROM scouting_submissions 
                        ORDER BY id DESC LIMIT 1 )
    AND robot IN ($placeholders) 
 
    GROUP BY robot, match_no
  ) AS t
) AS final

WHERE match_rank <= 12
) aa;
    ";

    // Prepare the statement and bind the robot list values
    $stmt = $pdo->prepare($query);
    $stmt->execute($robotListArray);

    // Fetch the results as an associative array and return them as JSON
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>