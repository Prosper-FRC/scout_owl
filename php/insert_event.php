<?php
// Include the database connection file
// This file is expected to create a $pdo object
require_once '../php/database_connection.php';
try {
    // Enable error reporting for better debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Create a PDO instance to connect to the database

    // Get the JSON data from the client
    $data = json_decode(file_get_contents('php://input'), true);

    // Check if all necessary data is present
    if (!isset($data['event_name'], $data['year'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required data']);
        exit;
    }

    // Extract values from the data
    $event_name = $data['event_name'];
    $year = $data['year'];

    // Prepare the SQL query to insert data into the frc_events table
    $sql = "INSERT INTO frc_events (event_name, year, active) VALUES (:event_name, :year, 1)"; // Default active = 1

    // Prepare the statement
    $stmt = $pdo->prepare($sql);

    // Bind parameters
    $stmt->bindParam(':event_name', $event_name);
    $stmt->bindParam(':year', $year);

    // Execute the query
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Event added successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to insert event']);
    }

} catch (PDOException $e) {
    // Catch any database connection errors
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
}
?>
