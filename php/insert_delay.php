<?php
session_start();
header('Content-Type: application/json');

// Include your database connection file
$database_file = 'database_connection.php';
if (!file_exists($database_file)) {
    die(json_encode(["error" => "Database connection file not found."]));
}
include $database_file;

try {
    // Create PDO instance if not already set by connection file
    if (!isset($pdo)) {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['delay'])) {
        // Convert delay to an integer
        $delay = (int)$data['delay'];
        
        // Insert into the table. Adjust the column names as per your table.
        $sql = "INSERT INTO time_delay (delay, timestamp) VALUES (:delay, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':delay' => $delay]);
        
        echo json_encode(["success" => true, "delay" => $delay]);
    } else {
        echo json_encode(["error" => "Delay value not provided."]);
    }
} catch (PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>
