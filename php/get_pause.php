<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the database connection file
$database_file = 'database_connection.php';
if (file_exists($database_file)) {
    include $database_file;
} else {
    die(json_encode(["error" => "Database connection file not found."]));
}

// Check if `$pdo` exists A
if (!isset($pdo)) {
    die(json_encode(["error" => "Database connection not established."]));
}


try {
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE active = 1 LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Prepare JSON response
    $response = [
    "pause" => $result ? (int)$result["pause"] : null,
    "total_pause_duration" => $result ? (int)$result["total_pause_duration"] : null
];


    // Ensure JSON response format
    header('Content-Type: application/json');
    echo json_encode($response);
} catch (PDOException $e) {
    die(json_encode(["error" => "Query failed: " . $e->getMessage()]));
}
?>