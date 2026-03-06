<?php
// Database connection details

// Include the database connection file
// This file is expected to create a $pdo object
require_once '../php/database_connection.php';

function uuidv4(): string {
    $data = random_bytes(16);
    // version 4
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    // variant
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
try {
    // Get the JSON data from the client
    $data = json_decode(file_get_contents('php://input'), true);

    // Check if all necessary data is present
    // *** UPDATED: Added 'game' to the check ***
    if (!isset($data['game'], $data['event_name'], $data['match_no'], $data['time_sec'], 
              $data['robot'], $data['alliance'], $data['action'], $data['location'], $data['result'], $data['points'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required data']);
        exit;
    }

    // Get the client's IP address
    $ip_address = $_SERVER['REMOTE_ADDR']; 

    // Extract values from the data
    // *** UPDATED: Added 'game' ***
    $game = $data['game'];
    $field_id = $data['field_id'];
    $event_name = $data['event_name'];
    $match_no = $data['match_no'];
    $time_sec = $data['time_sec'];
    $robot = $data['robot'];
    $alliance = $data['alliance'];
    $action = $data['action'];
    $location = $data['location'];
    $result = $data['result'];
    $points = $data['points'];

    
    if ($action === 'delete_action') { 
        // Prepare SQL query to delete the latest matching entry
        // *** UPDATED: Added 'AND game = :game' for safety ***
        $sql = "DELETE FROM scouting_submissions 
                WHERE id = (SELECT id FROM (SELECT MAX(id) AS id FROM scouting_submissions 
                                          WHERE robot = :robot 
                                          AND event_name = :event_name 
                                          AND match_no = :match_no
                                          AND game = :game) AS subquery)"; 


        // Prepare the statement
        $stmt = $pdo->prepare($sql);

        // Bind parameters
        // *** UPDATED: Added 'game' ***
        $stmt->bindParam(':game', $game);
        $stmt->bindParam(':event_name', $event_name);
        $stmt->bindParam(':match_no', $match_no);
        $stmt->bindParam(':robot', $robot);

    } else { 
        // Prepare SQL query to insert data
        // *** UPDATED: Added 'game' column and ':game' parameter ***
    // Generate UUID server-side so it exists for perfect syncing
    $uuid = uuidv4();

        $sql = "INSERT INTO scouting_submissions (uuid, game, field_id, ip_address, event_name, match_no, time_sec, robot, alliance, action, location, result, points)
                VALUES (:uuid, :game, :field_id, :ip_address, :event_name, :match_no, :time_sec, :robot, :alliance, :action, :location, :result, :points)";

        // Prepare the statement
        $stmt = $pdo->prepare($sql);

        // Bind parameters
        // *** UPDATED: Added 'game' ***
        $stmt->bindParam(':uuid', $uuid);
        $stmt->bindParam(':game', $game);
        $stmt->bindParam(':field_id', $field_id);
        $stmt->bindParam(':ip_address', $ip_address);
        $stmt->bindParam(':event_name', $event_name);
        $stmt->bindParam(':match_no', $match_no);
        $stmt->bindParam(':time_sec', $time_sec);
        $stmt->bindParam(':robot', $robot);
        $stmt->bindParam(':alliance', $alliance);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':location', $location);
        $stmt->bindParam(':result', $result);
        $stmt->bindParam(':points', $points);
    }
    
    // Execute the query
    if ($stmt->execute()) {
        $action_desc = ($action === 'delete_action') ? 'deleted' : 'inserted';
        echo json_encode(['status' => 'success', 'message' => "Data $action_desc successfully"]);
    } else {
        $action_desc = ($action === 'delete_action') ? 'delete' : 'insert';
        echo json_encode(['status' => 'error', 'message' => "Failed to $action_desc data"]);
    }

} catch (PDOException $e) {
    // Catch any database connection errors
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
}
?>