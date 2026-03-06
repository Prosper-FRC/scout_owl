<?php
// Set the header to return JSON content
header('Content-Type: application/json');

// Include your database connection
require_once 'database_connection.php'; // Ensure this file establishes the `$pdo` object

// Get query parameters from the URL
$event = $_GET['event'] ?? null;
$match= $_GET['match'] ?? null;
$currentYear = date("Y"); // Get the current year

// Validate input
if (!$event || !$match) {
    echo json_encode(['error' => 'Missing event or match parameters.']);
    exit;
}

try {
    // Query the database for the match data
    $stmt = $pdo->prepare("
        SELECT start_time, total_pause_duration, paused_at, active, pause
        FROM matches
        WHERE event = :event 
          AND match_number = :match
          AND YEAR(start_time) = :year
        LIMIT 1
    ");
    $stmt->execute(['event' => $event, 'match' => $match, 'year' => $currentYear]);
    $activeMatch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($activeMatch) {
        // Populate match data for response and add server_time
        $matchData = [
            'start_time' => $activeMatch['start_time'],
            'total_pause_duration' => $activeMatch['total_pause_duration'],
            'paused_at' => $activeMatch['paused_at'],
            'active' => $activeMatch['active'],
            'pause' => $activeMatch['pause'],
            'year' => $currentYear,
            'server_time' => gmdate('Y-m-d\TH:i:s\Z') // Current server time added here
        ];
    } else {
        // No matching row found
        $matchData = ['error' => 'Match not found for the current year.'];
    }
} catch (PDOException $e) {
    // Handle database errors
    $matchData = ['error' => 'Database query failed: ' . $e->getMessage()];
}

// Return the match data as JSON
echo json_encode($matchData);
?>
