<?php
header('Content-Type: application/json');
require_once __DIR__ . '/database_connection.php';

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }

    $match_id = isset($data['match_id']) ? (int)$data['match_id'] : 0;
    $field_id = isset($data['field_id']) ? (int)$data['field_id'] : 0;

    if ($match_id <= 0 || $field_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'match_id and field_id are required']);
        exit;
    }

    // Deactivate ONLY this match, and ONLY if it belongs to this field
    $stmt = $pdo->prepare("
        UPDATE matches
        SET active = 0
        WHERE id = :match_id
          AND field_id = :field_id
          AND active = 1
    ");
    $stmt->execute([
        ':match_id' => $match_id,
        ':field_id' => $field_id
    ]);

    echo json_encode([
        'success' => true,
        'deactivated_rows' => $stmt->rowCount()
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}