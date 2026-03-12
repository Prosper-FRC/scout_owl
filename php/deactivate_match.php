// old stuff <?php
// old stuff header('Content-Type: application/json');
// old stuff require_once 'database_connection.php'; // Include your database connection // old stuff logic
// old stuff 
// old stuff try {
// old stuff     // Read and decode the JSON input
// old stuff     $input = json_decode(file_get_contents('php://input'), true);
// old stuff     $matchId = $input['match_id'] ?? null;
// old stuff 
// old stuff     if ($matchId) {
// old stuff 
// old stuff 
// old stuff         // Deactivate the match by setting active to 0
// old stuff         $sql = "UPDATE matches SET active = 0 WHERE id = :id";
// old stuff         $stmt = $pdo->prepare($sql);
// old stuff         $stmt->execute([':id' => $matchId]);
// old stuff 
// old stuff         echo json_encode(['success' => true]);
// old stuff     } else {
// old stuff         echo json_encode(['success' => false, 'error' => 'Invalid match ID']);
// old stuff     }
// old stuff } catch (Exception $e) {
// old stuff     echo json_encode(['success' => false, 'error' => $e->getMessage()]);
// old stuff }
// old stuff ?>


<?php
require_once 'database_connection.php';
header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);

    $match_id = isset($data['match_id']) ? (int)$data['match_id'] : 0;
    $field_id = isset($data['field_id']) ? (int)$data['field_id'] : 0;

    if ($match_id <= 0 || $field_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'match_id and field_id required']);
        exit;
    }

    // Only deactivate if this match belongs to the same field_id
    $stmt = $pdo->prepare("
        UPDATE matches
        SET active = 0,
            pause = 0,
            paused_at = NULL
        WHERE id = :id
          AND field_id = :field_id
          AND active = 1
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => $match_id,
        ':field_id' => $field_id
    ]);

    if ($stmt->rowCount() === 0) {
        // Nothing changed: wrong field_id, already inactive, or no such match
        echo json_encode(['success' => false, 'message' => 'No active match updated for that field']);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}