<?php
header('Content-Type: application/json');

require_once 'database_connection.php';

$event = $_GET['event'] ?? null;
$match = $_GET['match'] ?? null;
$field_id = isset($_GET['field_id']) ? (int)$_GET['field_id'] : 0;
$currentYear = date("Y");

if (!$event || !$match) {
    echo json_encode(['error' => 'Missing event or match parameters.']);
    exit;
}

try {
    if ($field_id > 0) {
        $stmt = $pdo->prepare("
            SELECT start_time, total_pause_duration, paused_at, active, pause, field_id
            FROM matches
            WHERE event = :event
              AND match_number = :match
              AND field_id = :field_id
              AND YEAR(start_time) = :year
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'event' => $event,
            'match' => $match,
            'field_id' => $field_id,
            'year' => $currentYear
        ]);
    } else {
        $stmt = $pdo->prepare("
            SELECT start_time, total_pause_duration, paused_at, active, pause, field_id
            FROM matches
            WHERE event = :event
              AND match_number = :match
              AND YEAR(start_time) = :year
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'event' => $event,
            'match' => $match,
            'year' => $currentYear
        ]);
    }

    $activeMatch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($activeMatch) {
        $matchData = [
            'start_time' => $activeMatch['start_time'],
            'total_pause_duration' => (int)$activeMatch['total_pause_duration'],
            'paused_at' => $activeMatch['paused_at'],
            'active' => (int)$activeMatch['active'],
            'pause' => (int)$activeMatch['pause'],
            'field_id' => (int)$activeMatch['field_id'],
            'year' => $currentYear,
            'server_time' => gmdate('Y-m-d\TH:i:s\Z')
        ];
    } else {
        $matchData = [
            'start_time' => null,
            'total_pause_duration' => 0,
            'paused_at' => null,
            'active' => 0,
            'pause' => 0,
            'field_id' => $field_id,
            'year' => $currentYear,
            'server_time' => gmdate('Y-m-d\TH:i:s\Z'),
            'error' => 'Match not found for the current year.'
        ];
    }
} catch (PDOException $e) {
    $matchData = [
        'start_time' => null,
        'total_pause_duration' => 0,
        'paused_at' => null,
        'active' => 0,
        'pause' => 0,
        'field_id' => $field_id,
        'year' => $currentYear,
        'server_time' => gmdate('Y-m-d\TH:i:s\Z'),
        'error' => 'Database query failed: ' . $e->getMessage()
    ];
}

echo json_encode($matchData);
?>