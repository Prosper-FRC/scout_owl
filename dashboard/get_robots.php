<?php
// get_robots.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../php/database_connection.php';

if (!isset($_GET['event']) || empty($_GET['event'])) {
    echo json_encode([]);
    exit;
}

$event = $_GET['event'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo json_encode(['error' => "Database connection failed: " . $e->getMessage()]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT DISTINCT robot FROM scouting_submissions WHERE event_name = :event");
    $stmt->execute(['event' => $event]);
    $robots = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($robots);
    exit;
} catch (Exception $e) {
    echo json_encode(['error' => "Error fetching robots: " . $e->getMessage()]);
    exit;
}
?>
