<?php
require_once '../php/database_connection.php';

$event_name = isset($_GET['event_name']) ? $_GET['event_name'] : null;
if (!$event_name) {
    echo json_encode(["error" => "No event specified"]);
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    header('Content-Type: application/json');
    ob_clean();

    // Step 1: Create Temporary Table with additional columns for attempts
    $pdo->exec("
        CREATE TEMPORARY TABLE IF NOT EXISTS temp_robot_categories (
           robot INT PRIMARY KEY,
            starting_position VARCHAR(30),
            auton_path VARCHAR(30),
            offense_score DECIMAL(5,2) DEFAULT 0,
            defense_score DECIMAL(5,2) DEFAULT 0,
            auton_score DECIMAL(5,2) DEFAULT 0,
            cooperative_score DECIMAL(5,2) DEFAULT 0,
            top_scoring_location VARCHAR(30),
            match_count INT DEFAULT 0,
            count_level_1 INT DEFAULT 0,
            count_level_2 INT DEFAULT 0,
            count_level_3 INT DEFAULT 0,
            count_level_4 INT DEFAULT 0,
            level1_attempts INT DEFAULT 0,
            level2_attempts INT DEFAULT 0,
            level3_attempts INT DEFAULT 0,
            level4_attempts INT DEFAULT 0,

            level1_avg_attempts  DECIMAL(5,2) DEFAULT 0,
            level2_avg_attempts  DECIMAL(5,2) DEFAULT 0,
            level3_avg_attempts  DECIMAL(5,2) DEFAULT 0,
            level4_avg_attempts  DECIMAL(5,2) DEFAULT 0,
            
            algae_net_attempts INT DEFAULT 0,
            algae_net_success INT DEFAULT 0,
            algae_net_avg_attempts  DECIMAL(5,2) DEFAULT 0,

            algae_processor_attempts INT DEFAULT 0,
            algae_processor_success INT DEFAULT 0,
            algae_processor_avg_attempts  DECIMAL(5,2) DEFAULT 0,
            high_score  INT DEFAULT 0,
            high_score_match  INT DEFAULT 0,

            deep_climb_success INT DEFAULT 0,
            deep_climb_attempts INT DEFAULT 0,
          
            
            shallow_climb_success INT DEFAULT 0,
            shallow_climb_attempts INT DEFAULT 0
        )
    ");

    // Step 2: Insert All Robots for the Event
    $pdo->exec("
        INSERT INTO temp_robot_categories (robot)
        SELECT DISTINCT robot 
        FROM scouting_submissions
        WHERE event_name = '$event_name'
    ");

    // Step 3: Calculate Offense, Defense, and Autonomous Scores
    $pdo->exec("
        UPDATE temp_robot_categories rc
        JOIN (



 SELECT robot, AVG(offense_score) AS offense_score
         FROM (
             SELECT robot, match_no, SUM(points) AS offense_score
             FROM scouting_submissions
             WHERE event_name = '$event_name'
             GROUP BY robot, match_no
         ) AS ff
         GROUP BY robot




        ) AS offense_data ON rc.robot = offense_data.robot
        SET rc.offense_score = offense_data.offense_score
    ");

    $pdo->exec("
        UPDATE temp_robot_categories rc
        JOIN (
            SELECT robot, COUNT(*) / count(distinct match_no) AS defense_score
            FROM scouting_submissions
            WHERE event_name = '$event_name'
              AND action IN ('plays_defense', 'block')
            GROUP BY robot
        ) AS defense_data ON rc.robot = defense_data.robot
        SET rc.defense_score = defense_data.defense_score
    ");

    $pdo->exec("
        UPDATE temp_robot_categories rc
        JOIN (
select robot, max(auton_score) as auton_score
from
(SELECT robot,match_no,  count(action)  AS auton_score
            FROM scouting_submissions
            WHERE time_sec <= 15 AND action like '%score%' and result = 'success' and event_name = '$event_name'
            GROUP BY robot, match_no 
) AS auton_scores
group by robot
        ) AS auton_data ON rc.robot = auton_data.robot
        SET rc.auton_score = auton_data.auton_score
    ");

    // Step 4: Update Coral Scoring Counts (Successes) for Levels 1-4
    for ($level = 1; $level <= 4; $level++) {
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN (
                SELECT robot, COUNT(*) AS count_level_$level
                FROM scouting_submissions
                WHERE event_name = '$event_name'
                  AND action = 'scores_coral_level_$level'
                  AND result = 'success'
                GROUP BY robot
            ) AS level_data ON rc.robot = level_data.robot
            SET rc.count_level_$level = level_data.count_level_$level
        ");
    }

    // Step 5: Update Coral Scoring Attempts for Levels 1-4
    for ($level = 1; $level <= 4; $level++) {
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN (
                SELECT robot, COUNT(*) AS level{$level}_attempts
                FROM scouting_submissions
                WHERE event_name = '$event_name'
                  AND action = 'scores_coral_level_$level'
                GROUP BY robot
            ) AS attempts_data ON rc.robot = attempts_data.robot
            SET rc.level{$level}_attempts = attempts_data.level{$level}_attempts
        ");
    }

    // Step 6: Update Top Scoring Location
    $pdo->exec("
        UPDATE temp_robot_categories rc
        JOIN (
            SELECT robot, action AS top_scoring_location
            FROM (
                SELECT robot, action, COUNT(*) AS score_count,
                       ROW_NUMBER() OVER (PARTITION BY robot ORDER BY COUNT(*) DESC) AS rn
                FROM scouting_submissions
                WHERE event_name = '$event_name'
                  AND action IN ('scores_coral_level_1', 'scores_coral_level_2', 'scores_coral_level_3', 'scores_coral_level_4', 'scores_algae_net', 'scores_algae_processor')
                  AND result = 'success'
                GROUP BY robot, action
            ) ranked
            WHERE rn = 1
        ) AS top_score_data ON rc.robot = top_score_data.robot
        SET rc.top_scoring_location = top_score_data.top_scoring_location
    ");

    // Step 7: Update Algae Scoring for Net and Processor Attempts/Successes
    // Algae Net Attempts
    $pdo->exec("
        UPDATE temp_robot_categories rc
        JOIN (
            SELECT robot, COUNT(*) AS algae_net_attempts
            FROM scouting_submissions
            WHERE event_name = '$event_name'
              AND action = 'scores_algae_net'
            GROUP BY robot
        ) AS net_attempts ON rc.robot = net_attempts.robot
        SET rc.algae_net_attempts = net_attempts.algae_net_attempts
    ");

    // Algae Net Success
    $pdo->exec("
        UPDATE temp_robot_categories rc
        JOIN (
            SELECT robot, COUNT(*) AS algae_net_success
            FROM scouting_submissions
            WHERE event_name = '$event_name'
              AND action = 'scores_algae_net'
              AND result = 'success'
            GROUP BY robot
        ) AS net_success ON rc.robot = net_success.robot
        SET rc.algae_net_success = net_success.algae_net_success
    ");

    // Algae Processor Attempts
    $pdo->exec("
        UPDATE temp_robot_categories rc
        JOIN (
            SELECT robot, COUNT(*) AS algae_processor_attempts
            FROM scouting_submissions
            WHERE event_name = '$event_name'
              AND action = 'scores_algae_processor'
            GROUP BY robot
        ) AS proc_attempts ON rc.robot = proc_attempts.robot
        SET rc.algae_processor_attempts = proc_attempts.algae_processor_attempts
    ");

    // Algae Processor Success
    $pdo->exec("
        UPDATE temp_robot_categories rc
        JOIN (
            SELECT robot, COUNT(*) AS algae_processor_success
            FROM scouting_submissions
            WHERE event_name = '$event_name'
              AND action = 'scores_algae_processor'
              AND result = 'success'
            GROUP BY robot
        ) AS proc_success ON rc.robot = proc_success.robot
        SET rc.algae_processor_success = proc_success.algae_processor_success
    ");



       // deep climb Attempts
    $stmt = $pdo->prepare("
        UPDATE temp_robot_categories rc
        JOIN (
            SELECT robot, COUNT(*) AS deep_climb_attempts
            FROM scouting_submissions
            WHERE event_name = ? 
              AND action = 'attempts_deep_climb'
            GROUP BY robot
        ) AS deep_climb_attempts ON rc.robot = deep_climb_attempts.robot
        SET rc.deep_climb_attempts =deep_climb_attempts.deep_climb_attempts
    ");
    $stmt->execute([$event_name]);

    // Deep Climb Success
    $stmt = $pdo->prepare("
        UPDATE temp_robot_categories rc
        JOIN (
            SELECT robot, COUNT(*) AS deep_climb_success
            FROM scouting_submissions
            WHERE event_name = ? 
              AND action = 'attempts_deep_climb'
              AND result = 'success'
            GROUP BY robot
        ) AS deep_climb_success ON rc.robot = deep_climb_success.robot
        SET rc.deep_climb_success = deep_climb_success.deep_climb_success
    ");
    $stmt->execute([$event_name]);



        // shallow climb Attempts
    $stmt = $pdo->prepare("
        UPDATE temp_robot_categories rc
        JOIN (
            SELECT robot, COUNT(*) AS shallow_climb_attempts
            FROM scouting_submissions
            WHERE event_name = ? 
              AND action = 'attempts_shallow_climb'
            GROUP BY robot
        ) AS shallow_climb_attempts ON rc.robot = shallow_climb_attempts.robot
        SET rc.shallow_climb_attempts = shallow_climb_attempts.shallow_climb_attempts
    ");
    $stmt->execute([$event_name]);

    // shallow Climb Success
    $stmt = $pdo->prepare("
        UPDATE temp_robot_categories rc
        JOIN (
            SELECT robot, COUNT(*) AS shallow_climb_success
            FROM scouting_submissions
            WHERE event_name = ? 
              AND action = 'attempts_shallow_climb'
              AND result = 'success'
            GROUP BY robot
        ) AS shallow_climb_success ON rc.robot = shallow_climb_success.robot
        SET rc.shallow_climb_success = shallow_climb_success.shallow_climb_success
    ");
    $stmt->execute([$event_name]);






    // Step 8: Compute Cooperative Score (existing logic)
    $pdo->exec("
        CREATE TEMPORARY TABLE IF NOT EXISTS match_scores AS
        SELECT match_no, SUM(points) AS total_score
        FROM scouting_submissions
        WHERE event_name = '$event_name'
        GROUP BY match_no
    ");

    $pdo->exec("
        CREATE TEMPORARY TABLE IF NOT EXISTS robot_alliance_scores AS
        SELECT ss.robot, AVG(ms.total_score) AS avg_alliance_score
        FROM scouting_submissions ss
        JOIN match_scores ms ON ss.match_no = ms.match_no
        WHERE ss.event_name = '$event_name'
        GROUP BY ss.robot
    ");

    $pdo->query("SELECT AVG(total_score) INTO @avg_alliance_score_overall FROM match_scores");

    $pdo->exec("
        UPDATE temp_robot_categories rc
        JOIN robot_alliance_scores ras ON rc.robot = ras.robot
        SET rc.cooperative_score = ras.avg_alliance_score - @avg_alliance_score_overall
    ");

    // Step 9: Update Match Count (for all matches)
    $pdo->exec("
        UPDATE temp_robot_categories rc
        JOIN (
            SELECT robot, COUNT(DISTINCT match_no) AS match_count
            FROM scouting_submissions
            WHERE event_name = '$event_name'
            GROUP BY robot
        ) AS match_data ON rc.robot = match_data.robot
        SET rc.match_count = match_data.match_count
    ");


 // Step 10: Fetch auton


$pdo->exec("
    UPDATE temp_robot_categories rc
    JOIN (
        SELECT 
            robot,
            MAX(starting_position) AS starting_position,
            MAX(auton_path) AS auton_path
        FROM (
            SELECT 
                robot,
                CASE WHEN action LIKE 's%' THEN action ELSE NULL END AS starting_position,
                CASE WHEN action LIKE 'a%' THEN action ELSE NULL END AS auton_path,
                rnk
            FROM (
                SELECT 
                    robot,
                    action,
                    volume,
                    max_match,
                    RANK() OVER (
                        PARTITION BY robot, CASE WHEN action LIKE 's%' THEN 's' ELSE 'a' END
                        ORDER BY volume DESC, max_match DESC
                    ) AS rnk
                FROM (
                    SELECT 
                        ss.robot,
                        ss.action,
                        COUNT(ss.result) AS volume,
                        MAX(ss.match_no) AS max_match
                    FROM scouting_submissions ss
                    INNER JOIN (
                        SELECT event_name 
                        FROM scouting_submissions 
                        ORDER BY id DESC 
                        LIMIT 1
                    ) ev ON ss.event_name = ev.event_name
                    AND ss.action IN (
                        'starting_position_1',
                        'starting_position_2',
                        'starting_position_3',
                        'auton_left',
                        'auton_center',
                        'auton_right'
                    )
                    GROUP BY ss.robot, ss.action
                ) AS t
            ) AS b
            WHERE rnk = 1
        ) AS c
        GROUP BY robot
    ) AS subquery ON rc.robot = subquery.robot
    SET 
        rc.starting_position = subquery.starting_position,
        rc.auton_path = subquery.auton_path
");

// Step 11: Fetch average attemtps


$pdo->exec("
    UPDATE temp_robot_categories rc
    JOIN (
       
SELECT
   robot,
   count(
   case
      when
         result = 'success' 
         and action = 'scores_algae_net' 
      then
         action 
      else
         null 
   end
) / count(distinct match_no) as scores_algae_net, count(
   case
      when
         result = 'success' 
         and action = 'scores_algae_processor' 
      then
         action 
      else
         null 
   end
) / count(distinct match_no) as scores_algae_processor, count(
   case
      when
         result = 'success' 
         and action = 'scores_coral_level_1' 
      then
         action 
      else
         null 
   end
) / count(distinct match_no) as scores_coral_level_1, count(
   case
      when
         result = 'success' 
         and action = 'scores_coral_level_2' 
      then
         action 
      else
         null 
   end
) / count(distinct match_no) as scores_coral_level_2, count(
   case
      when
         result = 'success' 
         and action = 'scores_coral_level_3' 
      then
         action 
      else
         null 
   end
) / count(distinct match_no) as scores_coral_level_3, count(
   case
      when
         result = 'success' 
         and action = 'scores_coral_level_4' 
      then
         action 
      else
         null 
   end
) / count(distinct match_no) as scores_coral_level_4 
FROM
   `scouting_submissions` 
group by
   robot


    ) AS subquery ON rc.robot = subquery.robot
    SET 


rc.level1_avg_attempts=subquery.scores_coral_level_1,
rc.level2_avg_attempts=subquery.scores_coral_level_2,
rc.level3_avg_attempts=subquery.scores_coral_level_3,
rc.level4_avg_attempts=subquery.scores_coral_level_4,


rc.algae_net_avg_attempts=subquery.scores_algae_net,
rc.algae_processor_avg_attempts=subquery.scores_algae_processor

");



// Step 12: Fetch best match_no using a prepared statement
$pdo->exec("
    UPDATE temp_robot_categories rc
    INNER JOIN (
        SELECT robot, match_no, points
        FROM (
            SELECT robot, match_no, SUM(points) AS points,
                   RANK() OVER (PARTITION BY robot ORDER BY SUM(points) DESC) AS rnk
            FROM scouting_submissions
            WHERE event_name = '$event_name'
            GROUP BY robot, match_no
        ) ranked
        WHERE rnk = 1
    ) AS subquery ON rc.robot = subquery.robot
    SET 
        rc.high_score = subquery.match_no,
        rc.high_score_match = subquery.points
");










    // Step 13: Fetch Final Data
    $robot_query = $pdo->query("
        SELECT rc.*, 'n/a' as alliance
        FROM temp_robot_categories rc
        
        ORDER BY rc.offense_score desc
    ");
    $robots = $robot_query->fetchAll(PDO::FETCH_ASSOC);

    if (empty($robots)) {
        echo json_encode(["error" => "No robots available for this event"]);
        exit;
    }

    // Convert cooperative_score to float for proper JSON formatting
    foreach ($robots as &$robot) {
        $robot['cooperative_score'] = (float)$robot['cooperative_score'];
    }

    echo json_encode(["robots" => $robots]);
    exit;

} catch (PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    exit;
}
?>
