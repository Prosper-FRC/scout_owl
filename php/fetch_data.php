<?php
include 'database_connection.php';

if (isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'fetchMatches' && isset($_POST['event'])) {
        $event = $_POST['event'];
        $stmt = $pdo->prepare("SELECT DISTINCT match_number FROM active_event WHERE event_name = :event_name ORDER BY match_number ASC");
        $stmt->execute(['event_name' => $event]);
        echo '<option value="">Select Match Number</option>';
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo '<option value="' . htmlspecialchars($row['match_number']) . '">' . htmlspecialchars($row['match_number']) . '</option>';
        }

    } elseif ($action === 'fetchRobots' && isset($_POST['event'], $_POST['match_number'])) {
        $event = $_POST['event'];
        $matchNumber = $_POST['match_number'];
        $stmt = $pdo->prepare("SELECT DISTINCT robot, alliance FROM active_event WHERE event_name = :event_name AND match_number = :match_number  ");
        $stmt->execute(['event_name' => $event, 'match_number' => $matchNumber]);
        echo '<option value="">Select Robot</option>';
        
        $runningNumber = 0;
        $allianceNumber = 0;
        $allianceName = '';
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $runningNumber +=1;
            
            if ($runningNumber>3) {
                $allianceNumber = $runningNumber - 3;
                $allianceName = "Blue - " . $allianceNumber;
            }
            
            else {

                $allianceName = "Red - " . $runningNumber;
            }

            echo '<option value="' . htmlspecialchars($row['robot']) . '">(' . $allianceName . ") - " . htmlspecialchars($row['robot']) . '</option>';
        }
    } elseif ($action === 'fetchAlliance' && isset($_POST['event'], $_POST['match_number'], $_POST['robot'])) {
        $event = $_POST['event'];
        $matchNumber = $_POST['match_number'];
        $robot = $_POST['robot'];
        $stmt = $pdo->prepare("SELECT alliance FROM active_event WHERE event_name = :event_name AND match_number = :match_number AND robot = :robot order by alliance LIMIT 1");
        $stmt->execute(['event_name' => $event, 'match_number' => $matchNumber, 'robot' => $robot]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo htmlspecialchars($row['alliance']);
        } else {
            echo '';
        }
    }
}
?>
