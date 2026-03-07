<?php
include '../php/database_connection.php';

$activeventQuery = "
    SELECT 
        event_name,
        match_no + 1 AS match_number
    FROM scouting_submissions
    WHERE event_name = (
        SELECT event_name 
        FROM scouting_submissions 
        ORDER BY id DESC 
        LIMIT 1
    )
    ORDER BY match_no DESC 
    LIMIT 1
";
$activeventStmt = $pdo->prepare($activeventQuery);
$activeventStmt->execute();
$row = $activeventStmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    $response = array(
        'activeEventName'   => $row['event_name'],
        'activeMatchNumber' => $row['match_number']
    );
} else {
    $response = array(
        'activeEventName'   => null,
        'activeMatchNumber' => null
    );
}

header('Content-Type: application/json');
echo json_encode($response);
?>
