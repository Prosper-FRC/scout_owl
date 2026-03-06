<?php
// get_events.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../php/database_connection.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo json_encode(['error' => "Database connection failed: " . $e->getMessage()]);
    exit;
}

try {
    $stmt = $pdo->query("SELECT DISTINCT event_name FROM scouting_submissions ORDER BY event_name");
    $events = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($events);
    exit;
} catch (Exception $e) {
    echo json_encode(['error' => "Error fetching events: " . $e->getMessage()]);
    exit;
}
?>
