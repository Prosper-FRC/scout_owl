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

// Check if `$pdo` exists (since we are using PDO, not MySQLi)
if (!isset($pdo)) {
    die(json_encode(["error" => "Database connection not established."]));
}

// Query to get the most recent delay value using PDO
try {
    $stmt = $pdo->prepare("SELECT delay FROM time_delay ORDER BY timestamp DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Prepare JSON response
    $response = ["delay" => $result ? (int)$result["delay"] : null];

    // Ensure JSON response format
    header('Content-Type: application/json');
    echo json_encode($response);
} catch (PDOException $e) {
    die(json_encode(["error" => "Query failed: " . $e->getMessage()]));
}
?>
