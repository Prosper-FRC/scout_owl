<?php
require_once '../php/database_connection.php';

try {
    // Create a new PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get the most recent event name
    $stmt = $pdo->query("SELECT event_name FROM scouting_submissions ORDER BY id DESC LIMIT 1");
    $event_name = $stmt->fetchColumn();

    // Handle form submission
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $match_no   = $_POST['match_no'];
        $time_sec   = $_POST['time_sec'];
        $robot      = $_POST['robot'];
        $alliance   = $_POST['alliance'];
        $action     = $_POST['action'];
        $location   = $_POST['location'];
        $result     = $_POST['result'];
        $points     = $_POST['points'];
        $timestamp  = date('Y-m-d H:i:s');
        $ip_address = $_SERVER['REMOTE_ADDR'];

        $insert = $pdo->prepare("
            INSERT INTO scouting_submissions 
            (ip_address, event_name, match_no, time_sec, robot, alliance, action, location, result, points, timestamp)
            VALUES 
            (:ip_address, :event_name, :match_no, :time_sec, :robot, :alliance, :action, :location, :result, :points, :timestamp)
        ");

        $insert->execute([
            ':ip_address' => $ip_address,
            ':event_name' => $event_name,
            ':match_no'   => $match_no,
            ':time_sec'   => $time_sec,
            ':robot'      => $robot,
            ':alliance'   => $alliance,
            ':action'     => $action,
            ':location'   => $location,
            ':result'     => $result,
            ':points'     => $points,
            ':timestamp'  => $timestamp
        ]);

        echo "<p style='color: lightgreen;'>Submission successful!</p>";
    }

} catch (PDOException $e) {
    echo "<p style='color: red;'>Database Error: " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Scouting Form</title>
    <style>
        body {
            background-color: #333;
            color: #fff;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        label {
            display: block;
            margin-top: 12px;
        }
        select, input[type="text"], input[type="number"], input[type="submit"] {
            padding: 8px;
            width: 100%;
            max-width: 320px;
            background-color: #444;
            color: #fff;
            border: 1px solid #666;
            border-radius: 4px;
        }
    </style>
     <link rel="stylesheet" href="../css/select.css">
</head>
<body>

<h2>Scouting Submission</h2>
<form method="post">

    <label>Event Name</label>
    <input type="text" value="<?= htmlspecialchars($event_name) ?>" disabled>

    <label for="match_no">Match No</label>
    <select name="match_no" required>
        <?php for ($i = 1; $i <= 199; $i++): ?>
            <option value="<?= $i ?>"><?= $i ?></option>
        <?php endfor; ?>
    </select>

    <label for="time_sec">Time (sec)</label>
    <input type="number" name="time_sec" value="149" required>

    <label for="robot">Robot</label>
    <input type="text" name="robot" required>

    <label for="alliance">Alliance</label>
    <select name="alliance" required>
        <option value="Red">Red</option>
        <option value="Blue">Blue</option>
    </select>

    <label for="action">Action</label>
    <select name="action" id="action" onchange="updateFields()" required></select>

    <label for="location">Location</label>
    <input type="text" name="location" id="location" readonly>

    <label for="result">Result</label>
    <select name="result" required>
        <option value="Success">Success</option>
        <option value="Failure">Failure</option>
    </select>

    <label for="points">Points</label>
    <input type="number" name="points" id="points" readonly>

    <br><br>
    <input type="submit" value="Submit">
</form>

<script>
const actions = {
    "crosses_starting_line": { "location": "starting_line", "points": 0 },
    "starting_position_1":   { "location": "starting_pad", "points": 0 },
    "starting_position_2":   { "location": "starting_pad", "points": 0 },
    "starting_position_3":   { "location": "starting_pad", "points": 0 },
    "auton_left":            { "location": "starting_pad", "points": 0 },
    "auton_center":          { "location": "starting_pad", "points": 0 },
    "auton_right":           { "location": "starting_pad", "points": 0 },
    "picks_up_coral":        { "location": "station", "points": 0 },
    "scores_coral_level_1":  { "location": "reef", "points": 1 },
    "scores_coral_level_2":  { "location": "reef", "points": 2 },
    "scores_coral_level_3":  { "location": "reef", "points": 3 },
    "scores_coral_level_4":  { "location": "reef", "points": 4 },
    "picks_up_algae":        { "location": "mid_field", "points": 0 },
    "scores_algae_net":      { "location": "net", "points": 4 },
    "scores_algae_processor":{ "location": "processor", "points": 6 },
    "attempts_shallow_climb":{ "location": "barge", "points": 6 },
    "attempts_deep_climb":   { "location": "barge", "points": 12 },
    "attempts_parked":       { "location": "barge", "points": 2 },
    "plays_defense":         { "location": "opponent_side", "points": 0 },
    "block":                 { "location": "opponent_side", "points": 0 },
    "disabled":              { "location": "anywhere", "points": 0 }
};

const actionSelect = document.getElementById("action");
for (let key in actions) {
    let opt = document.createElement("option");
    opt.value = key;
    opt.textContent = key;
    actionSelect.appendChild(opt);
}

function updateFields() {
    const selected = actionSelect.value;
    document.getElementById("location").value = actions[selected].location;
    document.getElementById("points").value = actions[selected].points;
}
</script>

</body>
</html>
