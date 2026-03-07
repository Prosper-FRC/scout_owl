<?php
require_once '../php/database_connection.php';

header('Content-Type: application/json');

// Ensure an active match exists
$sql = "SELECT * FROM matches WHERE active = 1 LIMIT 1";
$activeMatch = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

if (!$activeMatch) {
    echo json_encode(["error" => "No active match"]);
    exit;
}

$event_name = $activeMatch['event'];
$match_number = $activeMatch['match_number'];

// Get robots assigned to this match
$sql = "SELECT alliance, robot FROM active_event WHERE event_name = :event_name AND match_number = :match_number";
$stmt = $pdo->prepare($sql);
$stmt->execute([':event_name' => $event_name, ':match_number' => $match_number]);
$robots = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Structure data for each robot
$robotData = [];

foreach ($robots as $key => $robot) {
    $robot_number = $robot['robot'];
    $alliance = strtolower($robot['alliance']); // "red" or "blue"
    $position = $key % 3 + 1; // 1, 2, 3 (assign to red1, red2, etc.)

    // Get total points for this match
    $sql = "SELECT SUM(points) as total_points FROM scouting_submissions 
            WHERE event_name = :event_name AND match_no = :match_number AND robot = :robot_number";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':event_name' => $event_name, ':match_number' => $match_number, ':robot_number' => $robot_number]);
    $points = $stmt->fetch(PDO::FETCH_ASSOC)['total_points'] ?? 0;

    // Get last 5 activities
    $sql = "SELECT timestamp, action, result FROM scouting_submissions 
            WHERE event_name = :event_name AND match_no = :match_number AND robot = :robot_number 
            ORDER BY timestamp DESC LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':event_name' => $event_name, ':match_number' => $match_number, ':robot_number' => $robot_number]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Determine if a score happened in the last 3 seconds
    $flash = false;
    if (!empty($activities)) {
        $latestActivity = $activities[0]; // Most recent action
        $timeDifference = time() - strtotime($latestActivity['timestamp']);

        if (strpos(strtolower($latestActivity['action']), 'score') !== false && $timeDifference <= 3) {
            $flash = true;
        }
    }

    // Store data in an associative array
    $robotData["{$alliance}{$position}"] = [
        "robot_number" => $robot_number,
        "total_points" => $points,
        "activities" => $activities,
        "flash" => $flash
    ];
}

// Send JSON response
echo json_encode($robotData);
?>
