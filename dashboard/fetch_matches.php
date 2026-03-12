<?php
require_once '../php/database_connection.php';

if (!isset($_GET['event_name']) || empty($_GET['event_name'])) {
    die(json_encode(["error" => "No event selected."]));
}

$event_name = $_GET['event_name'];

$pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->prepare("SELECT DISTINCT match_number FROM active_event WHERE event_name = ? ORDER BY match_number ASC");
$stmt->execute([$event_name]);
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// DEBUG: Show output
if (empty($matches)) {
    die(json_encode(["error" => "No matches found for event: $event_name"]));
}

header('Content-Type: application/json');
echo json_encode($matches);
?>
