<?php
session_start();

// Check if the user is logged in, if not redirect to the login page
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: /../stat_goblin/php/login.php');
    exit;
}

// Database connection details
$host = 'localhost';
$dbname = 'frc_scouting';
$username = 'root';
$password = 'pw123456';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Generate a new 4-digit code
    if (isset($_POST['generate_code'])) {
        $new_code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $sql = "UPDATE codes SET is_active = 0";
        $pdo->prepare($sql)->execute();
        $sql = "INSERT INTO codes (code, is_active) VALUES (:new_code, 1)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':new_code', $new_code);
        $stmt->execute();
    }

    // Begin Match: Insert or update match
    if (isset($_POST['begin_match'])) {
        $year = $_POST['year'];
        $event = $_POST['event'];
        $match_number = $_POST['match_number'];

        if (!empty($year) && !empty($event) && !empty($match_number)) {
            // Deactivate all previously active matches
            $sql = "UPDATE matches SET active = 0";
            $pdo->prepare($sql)->execute();

            // Check if there's already a match with the same year, event, and match number
            $sql = "SELECT COUNT(*) FROM matches WHERE year = :year AND event = :event AND match_number = :match_number";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':year' => $year,
                ':event' => $event,
                ':match_number' => $match_number,
            ]);
            $exists = $stmt->fetchColumn();

            // If match exists, reset it
            if ($exists) {
                $sql = "UPDATE matches 
                        SET start_time = NOW(), 
                            pause = 0, 
                            paused_at = NULL, 
                            total_pause_duration = 0, 
                            active = 1
                        WHERE year = :year AND event = :event AND match_number = :match_number";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':year' => $year,
                    ':event' => $event,
                    ':match_number' => $match_number,
                ]);
            } else {
                // Otherwise, create a new match
                $sql = "INSERT INTO matches (year, event, match_number, start_time, pause, total_pause_duration, active)
                        VALUES (:year, :event, :match_number, NOW(), 0, 0, 1)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':year' => $year,
                    ':event' => $event,
                    ':match_number' => $match_number,
                ]);
            }
        }
    }

    // Pause/Unpause Match
    if (isset($_POST['toggle_pause'])) {
        $sql = "SELECT * FROM matches WHERE active = 1 LIMIT 1";
        $activeMatch = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

        if ($activeMatch) {
            if ($activeMatch['pause'] == 0) {
                // Pause the match: Store the current timestamp in `paused_at`
                $currentTime = date('Y-m-d H:i:s');
                $sql = "UPDATE matches SET pause = 1, paused_at = :current_time WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':current_time' => $currentTime, ':id' => $activeMatch['id']]);
            } else {
                // Unpause the match: Calculate the duration paused (in seconds)
                $pausedAtTimestamp = strtotime($activeMatch['paused_at']);
                $pausedDuration = time() - $pausedAtTimestamp;

                // Add the paused duration to `total_pause_duration` and reset `paused_at`
                $sql = "UPDATE matches 
                        SET pause = 0, 
                            total_pause_duration = total_pause_duration + :paused_duration, 
                            paused_at = NULL 
                        WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':paused_duration' => $pausedDuration, ':id' => $activeMatch['id']]);
            }
        }
    }

    // Fetch only active events 
    $sql = "SELECT distinct event_name FROM active_event";
    $activeEvents = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    // Fetch the current active code
    $sql = "SELECT * FROM codes WHERE is_active = 1 LIMIT 1";
    $active_code = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

    // Fetch the current active match
    $sql = "SELECT * FROM matches WHERE active = 1 LIMIT 1";
    $activeMatch = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <!-- Ensures responsive rendering on mobile devices -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owl Admin Console</title>
     
    <style>
        /* Include custom fonts if you have them; otherwise, use fallbacks */
         @font-face {
            font-family: 'Roboto';
            src: url('/../stat_goblin/fonts/roboto/Roboto-Regular.ttf') format('ttf'),
            url('/../stat_goblin/fonts/roboto/Roboto-Regular.ttf') format('ttf');
            font-weight: normal;
            font-style: normal;
            }
            @font-face {
            font-family: 'Griffy';
            src: url('/../stat_goblin/fonts/Griffy/Griffy-Regular.ttf') format('ttf'),
            url('/../stat_goblin/fonts/Griffy/Griffy-Regular.ttf') format('ttf');
            font-weight: normal;
            font-style: normal;
            }
            @font-face {
            font-family: 'Comfortaa';
            src: url('/../stat_goblin/fonts/Comfortaa/Comfortaa-VariableFont_wght.ttf') format('ttf'),
            url('/../stat_goblin/fonts/Comfortaa/Comfortaa-VariableFont_wght.ttf') format('ttf');
            font-weight: normal;
            font-style: normal;
            }

        /* Global Styles */
        * {
            box-sizing: border-box;
        }

        body, html {
            font-family: 'Comfortaa', sans-serif;
            margin: 0;
            padding: 0;
            background: #222;
            color: #eee;
            line-height: 1.5;
            text-align: center;
        }

        .containerOuter {
            background-color: #333;
            width: 100%;
            padding: 1rem 0;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .logo {
            width: 100%;
            max-width: 400px;
            display: block;
            margin: 0 auto 1rem auto;
        }

        h1, h2, h3 {
            margin-top: 1rem;
            margin-bottom: 0.5rem;
        }

        p {
            margin: 0.5rem 0;
        }

        /* Buttons and Forms */
        button {
            padding: 0.75rem 1rem;
            font-size: 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 0.5rem;
            background-color: #555;
            color: #fff;
        }

        button:hover {
            background-color: #666;
        }

        form {
            margin: 1rem 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        label {
            display: block;
            margin: 0.5rem 0 0.25rem;
            text-align: left;
            width: 100%;
            max-width: 300px;
        }

        select, input[type="number"] {
            width: 100%;
            max-width: 300px;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            border-radius: 5px;
            border: 1px solid #777;
            background-color: #444;
            color: #eee;
        }

        /* Table (if you decide to use one somewhere) */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        th, td {
            padding: 0.75rem;
            text-align: left;
            border: 1px solid #444;
        }

        tr:nth-child(even) {
            background-color: #2b2b2b;
        }

        .scrollable-table {
            max-height: 300px;
            overflow-y: auto;
        }

        /* Responsive Styles: Adjust as needed */
        @media (max-width: 768px) {
            h1 {
                font-size: 1.5rem;
            }

            .container {
                padding: 0 0.5rem;
            }

            button {
                width: 100%;
                margin-top: 0.75rem;
            }
        }




         

    </style>

    <link rel="stylesheet" href="../css/select.css">
</head>
<body>

<div class="containerOuter">
    <div class="container">
        <a href="..">
            <img src="../images/owladmin.png" class="logo" alt="Logo">
        </a>

        <!--
        <h1>Admin Console</h1>

 
        <h2>Generate New Code</h2>
        <form method="POST">
            <button type="submit" name="generate_code">Generate New Code</button>
        </form>
        <?php if ($active_code): ?>
            <p>Current Active Code: <strong><?= htmlspecialchars($active_code['code']) ?></strong></p>
        <?php else: ?>
            <p>No active code found.</p>
        <?php endif; ?>

-->


        <!-- Start a New Match -->
        <h2>Start a New Match</h2>
        <form method="POST">
            <label for="year">Year:</label>
            <select name="year" id="year" required>
                <option value="">Select Year</option>
                <!-- Adjust or generate years as needed -->
                <option value="2025">2025</option>
                <option value="2026">2026</option>
                <option value="2027">2027</option>
                <option value="2028">2028</option>
                <option value="2029">2029</option>
            </select>

            <label for="event">Event:</label>
            <select name="event" id="event" required>
                <option value="">Select Event</option>
                <?php foreach ($activeEvents as $event): ?>
                    <option value="<?= htmlspecialchars($event['event_name']) ?>">
                        <?= htmlspecialchars($event['event_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="match_number">Match Number:</label>
            <input type="number" name="match_number" id="match_number" required min="1" placeholder="Enter Match Number">

            <button type="submit" name="begin_match">Begin Match</button>
        </form>

        <!-- Match Timer -->
        <h2>Match Timer</h2>
        <div>
            <?php if ($activeMatch): ?>
                <p>Match <strong><?= htmlspecialchars($activeMatch['match_number']) ?></strong> for
                    <strong><?= htmlspecialchars($activeMatch['event']) ?></strong> (<?= htmlspecialchars($activeMatch['year']) ?>) is active.
                </p>
                <p id="timer">Loading...</p>
                <form method="POST">
                    <button type="submit" name="toggle_pause">
                        <?= $activeMatch['pause'] == 0 ? 'Pause' : 'Unpause' ?>
                    </button>
                </form>
            <?php else: ?>
                <p>No active match.</p>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
    let startTime = <?= json_encode($activeMatch['start_time'] ?? null) ?>;
    let totalPause = <?= json_encode($activeMatch['total_pause_duration'] ?? 0) ?>;
    let isPaused = <?= json_encode($activeMatch['pause'] ?? 0) ?>;
    const matchId = <?= json_encode($activeMatch['id'] ?? null) ?>;

    function updateTimer() {
        const timerElement = document.getElementById('timer');
        if (!startTime) {
            timerElement.textContent = "No active match.";
            return;
        }

        const startTimeMs = new Date(startTime).getTime();
        let elapsedSeconds = (Date.now() - startTimeMs) / 1000 - totalPause;

        if (isPaused) {
            timerElement.textContent = "Paused";
            return;
        }

        const remainingSeconds = Math.max(150 - elapsedSeconds, 0);

        if (remainingSeconds === 0) {
            timerElement.textContent = "Match Over";
            clearInterval(timerInterval);

            // Deactivate the match when the timer reaches 0
            if (matchId) {
                fetch('../php/deactivate_match.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ match_id: matchId }),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log("Match deactivated successfully.");
                    } else {
                        console.error("Failed to deactivate match.");
                    }
                })
                .catch(error => console.error("Error:", error));
            }
            return;
        }

        const minutes = Math.floor(remainingSeconds / 60);
        const seconds = Math.floor(remainingSeconds % 60);
        timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')} remaining`;
    }

    const timerInterval = setInterval(updateTimer, 1000);
    updateTimer();
</script>

</body>
</html>
