<?php
// Set the Content-Type header to JSON
header('Content-Type: application/json');

// Include your database connection logic
require_once 'database_connection.php';

// Retrieve query parameters
$event = $_GET['event'] ?? null;
$match = $_GET['match'] ?? null;

// Validate required parameters
if (!$event || !$match) {
    echo json_encode(['error' => 'Missing event or match parameters']);
    exit;
}

try {
    // Query the database for the match data
    $sql = "SELECT start_time, pause, total_pause_duration, active, paused_at
            FROM matches
            WHERE event_name = :event AND match_number = :match
            LIMIT 1";

    $stmt = $pdo->prepare($sql); // `$pdo` is expected from database_connection.php
    $stmt->execute([
        'event' => $event,
        'match' => $match
    ]);

    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if the match was found
    if ($data) {
        echo json_encode(['matchFound' => true, 'data' => $data]);
    } else {
        echo json_encode(['matchFound' => false, 'message' => 'No match found']);
    }
} catch (Exception $e) {
    // Return an error message if something goes wrong
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
