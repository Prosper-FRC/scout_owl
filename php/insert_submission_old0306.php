

<?php
// Include the database connection file (creates $pdo)
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
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset(
        $data['game'],
        $data['event_name'],
        $data['match_no'],
        $data['time_sec'],
        $data['robot'],
        $data['alliance'],
        $data['action'],
        $data['location'],
        $data['result'],
        $data['points']
    )) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required data']);
        exit;
    }

    $ip_address = $_SERVER['REMOTE_ADDR'];

    $game = $data['game'];
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
        // Optional improvement: include game + time_sec + action + location to avoid deleting the wrong row.
        // For now, keeping your original behavior (latest by id for robot+event+match).
        $sql = "DELETE FROM scouting_submissions
                WHERE id = (
                    SELECT id FROM (
                        SELECT MAX(id) AS id
                        FROM scouting_submissions
                        WHERE robot = :robot
                          AND event_name = :event_name
                          AND match_no = :match_no
                    ) AS subquery
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':event_name', $event_name);
        $stmt->bindParam(':match_no', $match_no);
        $stmt->bindParam(':robot', $robot);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Row deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete row']);
        }
        exit;
    }

    // Generate UUID server-side so it exists for perfect syncing
    $uuid = uuidv4();

    // Insert (include uuid). timestamp and id remain table-generated.
    $sql = "INSERT INTO scouting_submissions
            (uuid, game, ip_address, event_name, match_no, time_sec, robot, alliance, action, location, result, points)
            VALUES
            (:uuid, :game, :ip_address, :event_name, :match_no, :time_sec, :robot, :alliance, :action, :location, :result, :points)";

    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':uuid', $uuid);
    $stmt->bindParam(':game', $game);
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

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Data inserted successfully',
            'uuid' => $uuid
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to insert data']);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
?>