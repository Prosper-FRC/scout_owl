<?php
header('Content-Type: application/json');
require_once 'database_connection.php'; // Include your database connection logic

try {
    // Read and decode the JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $matchId = $input['match_id'] ?? null;

    if ($matchId) {


        // Deactivate the match by setting active to 0
        $sql = "UPDATE matches SET active = 0 WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $matchId]);

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid match ID']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
