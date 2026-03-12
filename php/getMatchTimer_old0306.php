<?php
// Set the header to return JSON content
header('Content-Type: application/json');

// Include your database connection
require_once 'database_connection.php'; // Ensure this file establishes the $pdo object

// Get query parameters from the URL
$event = $_GET['event'] ?? null;
$match = $_GET['match'] ?? null;

// --- FIX: Use UTC year to match the admin script ---
$currentYear = gmdate("Y"); 

// Validate input
if (!$event || !$match) {
    echo json_encode(['error' => 'Missing event or match parameters.']);
    exit;
}

try {
    // Get the new 'game' parameter from the URL
    $game = $_GET['game'] ?? null;
    
    // Validate new input
    if (!$game) {
        echo json_encode(['error' => 'Missing game parameter.']);
        exit;
    }

    // Query the database for the match data
    $stmt = $pdo->prepare("
        SELECT start_time, total_pause_duration, paused_at, active, pause
        FROM matches
        WHERE event = :event 
          AND match_number = :match
          AND year = :year
          AND game = :game
        LIMIT 1
    ");
    $stmt->execute(['event' => $event, 'match' => $match, 'year' => $currentYear, 'game' => $game]);
    $activeMatch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($activeMatch) {
        
        $toIso8601 = function($utcTimeString) {
            if (empty($utcTimeString)) {
                return null;
            }
            $utc_time = new DateTime($utcTimeString, new DateTimeZone('UTC'));
            return $utc_time->format('c'); 
        };

        $matchData = [
            'start_time' => $toIso8601($activeMatch['start_time']),
            'total_pause_duration' => $activeMatch['total_pause_duration'],
            'paused_at' => $toIso8601($activeMatch['paused_at']),
            'active' => $activeMatch['active'],
            'pause' => $activeMatch['pause'],
            'year' => $currentYear,
            'server_time' => gmdate('c') 
        ];

    } else {
        $matchData = ['error' => 'Match not found for the current year, event, game, and match number.'];
    }

} catch (Exception $e) { 
    $matchData = ['error' => 'Database query or Date conversion failed: ' . $e->getMessage()];
}

// Return the match data as JSON
echo json_encode($matchData);
?>