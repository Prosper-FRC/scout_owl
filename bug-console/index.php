<?php
// Include the database connection file
// This file is expected to create a $pdo object
require_once '../php/database_connection.php';

try {
    // --- NEW: Load Game Files ---
    $gamesDir = __DIR__ . '/../scouter/games'; // Path from admin-console to scouter/games
    $gameFiles = [];
    if (is_dir($gamesDir)) {
        $files = glob($gamesDir . DIRECTORY_SEPARATOR . '*.json'); 
        if ($files !== false && count($files) > 0) { 
            sort($files, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($files as $f) {
                $basename = basename($f, '.json'); // Get name without .json
                if (strlen($basename) > 0) { 
                    $gameFiles[] = $basename;
                }
            }
        }
    }
    // --- END NEW ---

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
        // --- FIX: Use UTC year ---
        $year =  gmdate('Y');
        $event = $_POST['event'];
        $match_number = $_POST['match_number'];
        $game = $_POST['game']; // <-- Get game from form

        if (!empty($year) && !empty($event) && !empty($match_number) && !empty($game)) {
            
            // 1. Delete old scouting data for this match
            $sql_delete_scouting = "DELETE FROM scouting_submissions WHERE event_name = :event AND match_no = :match_number AND game = :game";
            $stmt_delete_scouting = $pdo->prepare($sql_delete_scouting);
            $stmt_delete_scouting->execute([
                ':event' => $event,
                ':match_number' => $match_number,
                ':game' => $game
            ]);

            // 2. Deactivate all other active matches
            $sql_deactivate = "UPDATE matches SET active = 1 WHERE active = 1";
            $pdo->prepare($sql_deactivate)->execute();

            // 3. --- NEW: Delete any old match entries for this exact match ---
            $sql_delete_match = "DELETE FROM matches 
                                 WHERE year = :year AND event = :event AND match_number = :match_number AND game = :game";
            $stmt_delete_match = $pdo->prepare($sql_delete_match);
            $stmt_delete_match->execute([
                ':year' => $year,
                ':event' => $event,
                ':match_number' => $match_number,
                ':game' => $game
            ]);

            // 4. Create the new, active match
            $sql_insert = "INSERT INTO matches (year, event, game, match_number, start_time, pause, total_pause_duration, active)
                           VALUES (:year, :event, :game, :match_number, UTC_TIMESTAMP(), 0, 0, 1)";
            $stmt_insert = $pdo->prepare($sql_insert);
            $stmt_insert->execute([
                ':year' => $year,
                ':event' => $event,
                ':game' => $game,
                ':match_number' => $match_number,
            ]);
        }
    }
    
    
    
    
    
    
    

    // Pause/Unpause Match
    if (isset($_POST['toggle_pause'])) {
        $sql = "SELECT * FROM matches WHERE active = 1 LIMIT 1";
        $activeMatch = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

        if ($activeMatch) {
            if ($activeMatch['pause'] == 0) {
                // Pause the match: Store the current UTC timestamp
                // --- FIX: Use UTC time ---
                $currentTime = gmdate('Y-m-d H:i:s');
                $sql = "UPDATE matches SET pause = 1, paused_at = :current_time WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':current_time' => $currentTime, ':id' => $activeMatch['id']]);
            } else {
                // Unpause the match: Calculate duration using UTC
                // --- FIX: Make calculation UTC-aware ---
                $pausedAtTimestamp = strtotime($activeMatch['paused_at'] . ' UTC');
                $currentUtcTimestamp = (new DateTime("now", new DateTimeZone("UTC")))->getTimestamp();
                $pausedDuration = $currentUtcTimestamp - $pausedAtTimestamp;
                // --- End Fix ---

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

    // --- FIX: Get all active details for HTML form ---
    $isMatchActive = ($activeMatch != null);
    $current_event_name = null;
    $current_match_number = null;
    $current_game_name = null; // <-- NEW variable

    if ($isMatchActive) {
        $current_event_name = $activeMatch['event'];
        $current_match_number = $activeMatch['match_number'];
        
        // --- NEW: Get the active game name from active_event table ---
        // (This assumes your 'active_event' table has a 'game' column)
        try {
            $sql_game = "SELECT game FROM active_event WHERE event_name = :event AND match_number = :match LIMIT 1";
            $stmt_game = $pdo->prepare($sql_game);
            $stmt_game->execute([
                ':event' => $current_event_name,
                ':match' => $current_match_number
            ]);
            $current_game_name = $stmt_game->fetchColumn();
        } catch (PDOException $e) {
            // Fails gracefully if 'game' column doesn't exist
            $current_game_name = null; 
        }
        // --- END NEW ---
    }
    // --- END FIX ---


// *** FIX: Convert the UTC time from DB to a proper UTC millisecond timestamp ***
    if ($activeMatch) {
        // Tell strtotime() that the string it's reading is already UTC
        $activeMatch['start_time_utc_ms'] = strtotime($activeMatch['start_time'] . ' UTC') * 1000;
    }
    // *** END OF FIX ***

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}








?>





















<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owl Admin</title>
<script src="../js/jquery-3.7.1.min.js"></script> 
    <style>
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
       
            body, html {
            font-family: 'Comfortaa', sans-serif;
      margin: 0;
      padding: 0;
      background: #222;
      color: #eee;
      line-height: 1.5;
      text-align: center;
    }
            /* General Reset */
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            /* Full-width top section */
            #startMatch {
                width: 100vw;
                background-color: #333; /* Example background */
                color: white;
                text-align: center;
                padding: 20px;
                font-size: 1.5rem;
            }

            /* Grid container for lower sections */
            #lowerContainer {
                display: grid;
                gap: 10px;
                padding: 10px;
                grid-template-columns: repeat(auto-fit, minmax(395px, 1fr));
                justify-content: center;
            }

            /* Individual square items */
            #red1, #red2, #red3, #blue1, #blue2, #blue3 {
                width: 395px;
                height: 395px;
                background-color: #222;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 1.2rem;
            }

            /* Mobile: Convert to collapsible layout */
            @media (max-width: 768px) {
                #lowerContainer {
                    display: flex;
                    flex-wrap: wrap;
                    justify-content: center;
                }

                #red1, #red2, #red3, #blue1, #blue2, #blue3 {
                    width: 100%;
                    max-width: 395px;
                }
            }

            .logo {
                width: 100%;
                max-width: 400px;
                display: block;
                margin: 0 auto 1rem auto;
            }
            select{min-width: 200px;}
            input{
                min-width: 200px;
font-size: 1.1rem; /* Increases font size for better readability */
                padding: 12px; /* Adds padding for touch-friendly areas */
                border: 1px solid #fff; /* Adds a white border */
                background-color: #222; /* Sets background color to match the theme */
                color: #fff; /* Sets text color to white */
                border-radius: 5px; /* Rounds the corners */}

button{font-size: 1.1rem; /* Increases font size for better readability */
                padding: 12px; /* Adds padding for touch-friendly areas */
                border: 1px solid #fff; /* Adds a white border */
                background-color: #222; /* Sets background color to match the theme */
                color: #fff; /* Sets text color to white */
                border-radius: 5px; /* Rounds the corners */
cursor:pointer;
            }


.flash {
    animation: flashEffect 1s linear;
}

@keyframes flashEffect {
    0% { background-color: yellow; }
    50% { background-color: red; }
    100% { background-color: yellow; }
}
.robot-card {
width: 395px;
                height: 395px;
    color: white;
    padding: 10px;
    border-radius: 10px;
    text-align: center;
    box-shadow: 2px 2px 10px rgba(255, 255, 255, 0.2);
    transition: transform 0.3s ease-in-out;
}

.robot-card:hover {
    transform: scale(1.05);
}

.robot-number {
    font-size: 1.8rem;
    font-weight: bold;
    color: #CCC; 
}

.total-points {
    font-size: 1.3rem;
    font-weight: bold;
    margin: 10px 0;
}

.total-points span {
    color: #fff; /* Bright green for points */
    font-size: 1.5rem;
}

.activities-title {
    font-size: 1.2rem;
    margin-top: 15px;
    border-bottom: 2px solid #CCC;
    padding-bottom: 5px;
}

.activities-list {
    list-style: none;
    padding: 0;
    text-align: left;
    font-size: 1rem;
    margin-top: 10px;
}

.activities-list li {
    display: flex;
    justify-content: space-between;
    background: #333;
    padding: 8px;
    border-radius: 5px;
    margin-bottom: 5px;
    transition: background 0.3s ease-in-out;
}

.activities-list li:hover {
    background: #444;
}

.timestamp {
    color: #FF4500; /* Orange-red */
    font-weight: bold;
}

.action {
    font-weight: bold;
    color: #00BFFF; /* Light blue */
}

.result {
    color: #FF69B4; /* Pink */
    font-style: italic;
}
.redRobots{background-color:#C0392B; }
.blueRobots{background-color:#2C3E50; }
#logoOuter{display: inline-block;} */
    /*auto*/
    /*style="width:400px";*/
    
    
}
#pause{dispay:none;}

    </style>



<link rel="stylesheet" href="../css/select.css">
</head>
<body>

<div id="logoOuter">
       <a href="..">
  
      <img src="../images/owladmin.png" class="logo" alt="Logo">
    </a>   
       
   </div>

<div id="startMatch">
   

    <form method="POST">
       
        <select name="event" id="delay">
    <option value="">Delay</option>
            <option value="0">0</option>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
            <option value="4">4</option>
            
            <option value="5">5</option>
            <option value="6">6</option>
            <option value="7">7</option>
            <option value="8">8</option>
            <option value="9">9</option>   
            
            <option value="10">10</option>
            <option value="11">11</option>
            <option value="12">12</option>
            <option value="13">13</option>
            <option value="14">14</option>             
            
</select>

        <select name="game" id="game" required <?= $isMatchActive ? 'disabled' : '' ?>>
            <option value="">Game</option>
            <?php foreach ($gameFiles as $game): 
                $gameName = htmlspecialchars($game);
            ?>
                <option value="<?= $gameName ?>" <?= ($gameName == $current_game_name) ? 'selected' : '' ?>>
                    <?= $gameName ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label for="event"></label>
        <select name="event" id="event" required <?= $isMatchActive ? 'disabled' : '' ?>>
            <option value="">Event</option>
            <?php foreach ($activeEvents as $event): 
                $eventName = htmlspecialchars($event['event_name']);
            ?>
                <option value="<?= $eventName ?>" <?= ($eventName == $current_event_name) ? 'selected' : '' ?>>
                    <?= $eventName ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="match_number"></label>
        <input type="number" name="match_number" id="match_number" required min="1" placeholder="Enter Match Number" 
               value="<?= htmlspecialchars($current_match_number) ?>" <?= $isMatchActive ? 'disabled' : '' ?>>
       
        <button type="submit" name="begin_match" <?= $isMatchActive ? 'disabled' : '' ?>>
            <?= $isMatchActive ? 'Match in Progress' : 'Begin Match' ?>
        </button>
    </form>

    <h2>Match Timer</h2>
    <div>
        <?php if ($activeMatch): ?>
            <p id="activeMatchinfo">Match <strong><?= htmlspecialchars($activeMatch['match_number']) ?></strong> for
                <strong><?= htmlspecialchars($activeMatch['event']) ?></strong>(<?= gmdate("Y") ?>) is active.
            </p>
            <p id="timer">Loading...</p>
           

        <?php else: ?>
            <p>No active match.</p>
        <?php endif; ?>
    </div>

</div>

<div id="lowerContainer">
    <div id="red1" class="redRobots">Red 1</div>
    <div id="red2" class="redRobots">Red 2</div>
    <div id="red3" class="redRobots">Red 3</div>
    <div id="blue1">Blue 1</div>
    <div id="blue2">Blue 2</div>
    <div id="blue3">Blue 3</div>
</div>





<button onclick="openForm()">Open Scouting Form</button>

<div id="scoutingModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(0,0,0,0.7); z-index:1000;">
    <div style="position:relative; width:90%; max-width:700px; margin:5% auto; background:#222; padding:20px; border-radius:8px;">
        <span onclick="closeForm()" style="position:absolute; top:10px; right:15px; font-size:20px; color:white; cursor:pointer;">&times;</span>
        <iframe src="scouting_form.php" style="width:100%; height:600px; border:none;"></iframe>
    </div>
</div>


<script>
function openForm() {
    document.getElementById("scoutingModal").style.display = "block";
}
function closeForm() {
    document.getElementById("scoutingModal").style.display = "none";
}
</script>


<script>
    
// These variables are initialized from your server data.
    // Use the new UTC millisecond timestamp from PHP
    let startTimeMs = <?= json_encode($activeMatch['start_time_utc_ms'] ?? null) ?>; 
    let totalPause = <?= json_encode($activeMatch['total_pause_duration'] ?? 0) ?>;
    
    // This is the new variable from PHP to check if a match is active
    const isMatchActive = <?= json_encode($isMatchActive) ?>;
    
    // Cast pause to a boolean for client-side usage.
    let isPaused = <?= json_encode((bool)($activeMatch['pause'] ?? 0)) ?>;
    const matchId = <?= json_encode($activeMatch['id'] ?? null) ?>;
    
    // Flags to prevent repeated toggles.
    let autoPauseTriggered = false;
    let autoUnpauseTriggered = false;
    
    // Set your desired delay (in seconds) that the match should remain paused.
    // This is just a default; the timer will read the live value.
    let delay = document.getElementById('delay').value;

    // Function to poll the server for the latest pause status.
    function updatePauseStatus() {
        // Only poll if a match is actually active
        if (!matchId) return; 

        // --- FIX: Add cache-buster for Bluehost ---
        fetch('../php/get_pause.php?cacheBust=' + Date.now(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            // --- FIX: Send matchId to get correct status ---
            body: JSON.stringify({ match_id: matchId })
        })
        .then(response => response.json())
        .then(data => {
            // Expect the server to return an object with "pause" and "total_pause_duration".
            isPaused = Boolean(data.pause);
            totalPause = data.total_pause_duration;
            // console.log("Pause value from server:", data.pause);
        })
        .catch(error => console.error("Error fetching pause:", error));
    }
    // Poll the server for pause status every second.
    setInterval(updatePauseStatus, 1000);







    // --- THIS IS THE CORRECTED TIMER FUNCTION ---
    // Timer update function.
    function updateTimer() {
        const timerElement = document.getElementById('timer');
        if (!startTimeMs) {
            timerElement.textContent = "No active match.";
            return;
        }

        // --- FIX: Use two "clocks" ---
        // 1. "Real Clock": This clock *ignores* pauses. 
        // It's used to trigger the auton pause and teleop un-pause.
        const realElapsedSeconds = (Date.now() - startTimeMs) / 1000;
        const delay = parseInt(document.getElementById('delay').value) || 0;

        // 2. "Game Clock": This clock *respects* pauses (using totalPause).
        // It's used for the 150-second countdown display.
        const elapsedGameTime = (Date.now() - startTimeMs) / 1000 - totalPause;
        // --- End Fix ---


        // AUTO-TOGGLE LOGIC (using the "Real Clock")
        // 1. Trigger pause (fires ONCE)
        if (!autoPauseTriggered && realElapsedSeconds >= 15) {
            console.log("Auton ended: triggering pause at real elapsed =", realElapsedSeconds);
            // Add cache-buster for Bluehost
            fetch('../php/toggle_pause.php?cacheBust=' + Date.now(), { 
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ match_id: matchId })
            })
            .then(response => response.text())
            .then(data => console.log("Toggle pause response:", data))
            .catch(error => console.error("Error toggling pause:", error));
            
            autoPauseTriggered = true; // Prevents this from ever firing again
        }
        // 2. Trigger unpause (fires ONCE after pause)
        else if (autoPauseTriggered && !autoUnpauseTriggered && realElapsedSeconds >= (15 + delay)) {
            console.log("Teleop started: triggering unpause at real elapsed =", realElapsedSeconds);
            // Add cache-buster for Bluehost
            fetch('../php/toggle_pause.php?cacheBust=' + Date.now(), { 
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ match_id: matchId })
            })
            .then(response => response.text())
            .then(data => console.log("Toggle unpause response:", data))
            .catch(error => console.error("Error toggling pause:", error));
            
            autoUnpauseTriggered = true; // Prevents this from ever firing again
        }

        // --- DISPLAY LOGIC ---
        // If the server says we're paused, show "Paused" and stop.
        // (isPaused is updated by the updatePauseStatus function)
        if (isPaused) {
            timerElement.textContent = "Paused";
            return;
        }
        
        // If not paused, show the "Game Clock" countdown
        let remainingSeconds = Math.max(150 - elapsedGameTime, 0);

        if (remainingSeconds === 0) {
            timerElement.textContent = "Match Over";
            clearInterval(timerInterval);
    
            // --- FIX: RESET FLAGS FOR NEXT MATCH ---
            autoPauseTriggered = false;
            autoUnpauseTriggered = false;
            // --- END FIX ---

            // Your 'Match Over' logic with setTimeouts
            setTimeout(() => {
                console.log("First wait (3 seconds) complete");
                setTimeout(() => {
                    document.getElementById("activeMatchinfo").textContent = "Getting Next Match";
                    timerElement.textContent = ""
                    console.log("Second wait (2 seconds) complete");
                    setTimeout(() => {
                        setNextMatch();
                        console.log("Third wait (1 second) complete");
                        document.getElementById("activeMatchinfo").textContent = "Ready to play!";
                    }, 1000); 
                }, 2000);
            }, 3000);

            return;
        }

        // Otherwise, update the timer display.
        const minutes = Math.floor(remainingSeconds / 60);
        const seconds = Math.floor(remainingSeconds % 60);
        timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')} remaining`;
    }
    // --- END OF CORRECTED TIMER FUNCTION ---

    // Update the timer every 250ms for a smooth display
    const timerInterval = setInterval(updateTimer, 250);
    // Run it once immediately on page load
    if (startTimeMs) {
        updateTimer();
    }

</script>







<script>
    function fetchMatchData() {
        // --- FIX: Add cache-buster for Bluehost ---
        fetch("get_match_data.php?cacheBust=" + Date.now())
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.warn(data.error);
                    return;
                }

                // Loop through red1, red2, red3, blue1, blue2, blue3
                ["red1", "red2", "red3", "blue1", "blue2", "blue3"].forEach(id => {
                    const div = document.getElementById(id);
                    if (!data[id]) {
                        div.innerHTML = `<p>No data</p>`;
                        return;
                    }

                    const { robot_number, total_points, activities, flash } = data[id];

                    // Format activities
                    let activitiesHTML = activities.map(act => 
                        `<li>${new Date(act.timestamp).toLocaleTimeString()} - ${act.action}: ${act.result}</li>`
                    ).join("");

const allianceClass = id.includes("red") ? "redRobots" : "blueRobots";

div.innerHTML = `
    <div class="robot-card ${allianceClass}">
        <h2 class="robot-number">🤖 Robot #${robot_number}</h2>
        <p class="total-points">Total Points: <span>${total_points}</span></p>
       
        <h3 class="activities-title">Last 5 Activities</h3>
        <ul class="activities-list">
            ${activities.map(act => `
                <li>
                    <span class="timestamp">${new Date(act.timestamp).toLocaleTimeString()}</span>
                    <span class="action">${act.action}:</span>
                    <span class="result">${act.result}</span>
                </li>
            `).join("")}
        </ul>
    </div>
`;


                    // Flash if last action was a score in the last 3 seconds
                    if (flash) {
                        div.classList.add("flash");
                        setTimeout(() => div.classList.remove("flash"), 1000);
                    }
                });
            })
            .catch(error => console.error("Error fetching match data:", error));
    }

    // Refresh data every second
    setInterval(fetchMatchData, 1000);
    fetchMatchData(); // Run immediately on page load




function setNextMatch(){

    // --- FIX: Add cache-buster for Bluehost ---
    fetch('get_active_event.php?cacheBust=' + Date.now())
        .then(response => response.json())
        .then(data => {
            // data is expected to return: { activeEventName, activeMatchNumber, activeGameName }
            console.log("Active Event:", data.activeEventName);
            console.log("Next Match:", data.activeMatchNumber);
            console.log("Active Game:", data.activeGameName); // <-- NEW

            // Get form elements
            const eventSelect = document.getElementById('event');
            const matchInput = document.getElementById('match_number');
            const gameSelect = document.getElementById('game'); // <-- NEW
            const beginButton = document.querySelector('button[name="begin_match"]');

            // Set values
            eventSelect.value = data.activeEventName;
            matchInput.value = data.activeMatchNumber;
            gameSelect.value = data.activeGameName; // <-- NEW

            // --- FIX: Re-enable the form ---
            eventSelect.disabled = false;
            matchInput.disabled = false;
            gameSelect.disabled = false; // <-- NEW
            beginButton.disabled = false;
            beginButton.textContent = 'Begin Match'; // Reset button text

        })
        .catch(error => console.error('Error fetching active event data:', error));

    // The #year select is commented out in your HTML, so this code isn't needed
    // const currentYear = new Date().getFullYear();
    // console.log(currentYear);
    // $('#year').val(currentYear);
}

// --- FIX: Only call setNextMatch() on page load if NO match is active ---
// We use the 'isMatchActive' variable from the first script block
if (!isMatchActive) {
    setNextMatch();
}



//document.getElementById('nextMatch').addEventListener('click', function(e) {
//    // Optionally, prevent the default form submission if needed:
//    e.preventDefault();
//    // Call your JavaScript function to process the next match, if applicable:
//    setNextMatch(); 
//    // Hide the button:
//     this.style.visibility = 'hidden';
//     this.style.width = '0px';
//});








document.getElementById('delay').addEventListener('change', function() {
    let delayValue = this.value;
    if (delayValue !== "") {
        // --- FIX: Add cache-buster for Bluehost ---
        fetch('../php/insert_delay.php?cacheBust=' + Date.now(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ delay: delayValue })
        })
        .then(response => response.json())
        .then(data => {
            if(data.success){
                console.log("Delay inserted:", data.delay);
            } else {
                console.error("Error:", data.error);
            }
        })
        .catch(error => console.error("Error inserting delay:", error));
    }
});





function fetchDelay() {
    $.ajax({
        type: 'POST',
        url: '../php/get_delay.php',  // Make sure this path is correct
        dataType: 'json',  // Expect JSON response
        // --- FIX: Add cache-buster for Bluehost (jQuery) ---
        cache: false, 
        success: function(response) {
            if (response.delay !== null) {
                // We just update the 'delay' select. The timer reads from it directly.
                console.log("Fetched delay:", response.delay);
                document.getElementById('delay').value = response.delay;
            } else {
                console.error("No delay data available.");
            }
        },
        error: function(xhr, status, error) {
            console.error("Error fetching delay:", error);
        }
    });
}
// Call fetchDelay when the page loads
fetchDelay();




</script>





</body>
</html>