<?php
// Database connection details
// Include the database connection file
// This file is expected to create a $pdo object
require_once '../php/database_connection.php';
try {
    // Create a PDO instance to connect to the database


    // Prepare the SQL query to fetch all event names from the frc_events table
    $sql = "SELECT event_name FROM frc_events WHERE active = 1";  // Assuming you only want active events

    // Execute the query
    $stmt = $pdo->query($sql);

    // Fetch the results as an associative array
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return the events as JSON
    echo json_encode($events);
} catch (PDOException $e) {
    // Catch any database connection errors
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
}
?>
