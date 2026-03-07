<?php


// Include the database connection file
// This file is expected to create a $pdo object
require_once '../php/database_connection.php';

try {

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
        $year =  date('Y');
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


    /* ==== THEME VARIABLES ==== */
    :root {
      /* Light mode defaults */
      --bg-primary: #f4f4f6;
      --bg-surface: #ffffff;
      --bg-subtle: #eef0f3;
      --text-primary: #0f172a;
      --text-secondary: #334155;
      --border: #d5dae1;
      --shadow: rgba(2, 8, 23, 0.08);

      --card-bg: #ffffff;
      --card-text: #1f2937;
      --card-header: rgba(255,255,255,0.95);

      --accent-blue: #1e73be; /* not used as fill, glow only */
      --accent-red:  #be1e2d;

      --prediction-blue: #cfe8ff;
      --prediction-red:  #fde2e2;
      --prediction-base: #e5e7eb;

      --button-bg: #e5e7eb;
      --button-hover: #dbe1e8;

      --logo-filter: invert(1); /* white logo -> dark for light bg */
    }
    body.dark {
      --bg-primary: #0b1020;
      --bg-surface: #141a2a;
      --bg-subtle: #101626;


      --bg-primary: #111;
      --bg-surface: #333;
      --bg-subtle: #000;




      --text-primary: #e5e7eb;
      --text-secondary: #cbd5e1;
      --border: #273043;
      --shadow: rgba(0,0,0,0.35);

      --card-bg: #ffffff;     /* robot cards stay white for readability */
      --card-text: #1f2937;
      --card-header: rgba(255,255,255,0.95);

      --prediction-blue: #5DADE2;
      --prediction-red:  #EC7063;
      --prediction-base: #e2e3e5;

      --button-bg: #222;
      --button-hover: #111;

      --logo-filter: invert(0); /* keep logo white on dark bg */
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
            background-color: #333; 
            color: white;
            text-align: center;
            padding: 20px;
            font-size: 1.5rem;
        }

        #startMatch {
  width: 100vw;
  background-color: var(--bg-surface);
  color: var(--text-primary);
  text-align: center;
  padding: 20px;
  font-size: 1.5rem;
  transition: background 0.3s ease, color 0.3s ease;
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
  filter: var(--logo-filter);
  transition: filter 0.3s ease;
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
    #themeToggle {
      width:40px; height:40px; display:grid; place-items:center;
      border-radius:50%; font-size:1.1rem;
    }
#pause{dispay:none;}
#themeToggle {
    position: fixed;
    top: 10px;
    right: 10px;
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    font-size: 1.1rem;
    background: var(--button-bg);
    color: var(--text-primary);
    border: 1px solid var(--border);
    cursor: pointer;
    z-index: 9999;
}
body.dark { background:#111; color:#eee; }
/* ==== THEME VARIABLES ==== */
:root {
  /* Light mode defaults */
  --bg-primary: #f4f4f6;
  --text-primary: #0f172a;
  --button-bg: #e5e7eb;
  --button-hover: #dbe1e8;
}

body {
  background: var(--bg-primary);
  color: var(--text-primary);
  transition: background 0.3s ease, color 0.3s ease;
}

button {
  background: var(--button-bg);
  color: var(--text-primary);
}
button:hover {
  background: var(--button-hover);
}

/* Dark mode overrides */
body.dark {
  --bg-primary: #111;
  --text-primary: #eee;
  --button-bg: #222;
  --button-hover: #111;
}

    </style>



<link rel="stylesheet" href="../css/select.css">
</head>
<body>

<div id="startMatch">
    <a href="..">
        <img src="../images/owladmin.png" class="logo" alt="Logo">
    </a>

    <form method="POST">
      
       <!-- <label for="year"></label>
        <select name="year" id="year" required>
            <option value="">Year</option>
            <option value="2025">2025</option>
            <option value="2026">2026</option>
            <option value="2027">2027</option>
            <option value="2028">2028</option>
            <option value="2029">2029</option>
        </select>
-->
<select name="event" id="delay">
    <option value="">Delay</option>
            <option value="0">0</option>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
            <option value="4">4</option>
</select>



        <label for="event"></label>
        <select name="event" id="event" required>
            <option value="">Event</option>
            <?php foreach ($activeEvents as $event): ?>
                <option value="<?= htmlspecialchars($event['event_name']) ?>">
                    <?= htmlspecialchars($event['event_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="match_number"></label>
        <input type="number" name="match_number" id="match_number" required min="1" placeholder="Enter Match Number">
        <!-- Initially hidden via inline style -->
<!--<button type="submit" name="next_match" id="nextMatch" style="visibility: hidden; width: 0px;">Next Match</button>
-->
        <button type="submit" name="begin_match">Begin Match</button>
    </form>

    <!-- Match Timer -->
    <h2>Match Timer</h2>
    <div>
        <?php if ($activeMatch): ?>
            <p id="activeMatchinfo">Match <strong><?= htmlspecialchars($activeMatch['match_number']) ?></strong> for
                <strong><?= htmlspecialchars($activeMatch['event']) ?></strong>(<?= date("Y") ?>) is active.
            </p>
            <p id="timer">Loading...</p>
            

           <!--
            <form method="POST"  id="pause">
                <button type="submit" name="toggle_pause">
                    <?= $activeMatch['pause'] == 0 ? 'Pause' : 'Unpause' ?>
                </button>
            </form>
-->
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





<!-- Button to trigger the popup -->
<button onclick="openForm()">Open Scouting Form</button>

<!-- Modal container -->
<div id="scoutingModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(0,0,0,0.7); z-index:1000;">
    <div style="position:relative; width:90%; max-width:700px; margin:5% auto; background:#222; padding:20px; border-radius:8px;">
        <span onclick="closeForm()" style="position:absolute; top:10px; right:15px; font-size:20px; color:white; cursor:pointer;">&times;</span>
        <iframe src="scouting_form.php" style="width:100%; height:600px; border:none;"></iframe>
    </div>
</div>

<button id="themeToggle" title="Toggle Light/Dark">🌙</button>
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
    let startTime = <?= json_encode($activeMatch['start_time'] ?? null) ?>;
    let totalPause = <?= json_encode($activeMatch['total_pause_duration'] ?? 0) ?>;
    // Cast pause to a boolean for client-side usage.
    let isPaused = <?= json_encode((bool)($activeMatch['pause'] ?? 0)) ?>;
    const matchId = <?= json_encode($activeMatch['id'] ?? null) ?>;
    
    // Flags to prevent repeated toggles.
    let autoPauseTriggered = false;
    let autoUnpauseTriggered = false;
    
    // Set your desired delay (in seconds) that the match should remain paused.
    let delay = document.getElementById('delay').value;

    // Function to poll the server for the latest pause status.
    function updatePauseStatus() {
        fetch('../php/get_pause.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            // Optionally, you can send current pause state, but it's not required.
            body: JSON.stringify({ pause: isPaused })
        })
        .then(response => response.json())
        .then(data => {
            // Expect the server to return an object with "pause" and "total_pause_duration".
            isPaused = Boolean(data.pause);
            totalPause = data.total_pause_duration;
            console.log("Pause value from server:", data.pause);
        })
        .catch(error => console.error("Error fetching pause:", error));
    }
    // Poll the server for pause status every second.
    setInterval(updatePauseStatus, 1000);

    // Timer update function.
    function updateTimer() {
        const timerElement = document.getElementById('timer');
        if (!startTime) {
            timerElement.textContent = "No active match.";
            return;
        }

        const startTimeMs = new Date(startTime).getTime();
        // Calculate effective elapsed time (in seconds), subtracting any total paused duration.
        let elapsedSeconds = (Date.now() - startTimeMs) / 1000 - totalPause;
        let remainingSeconds = Math.max(150 - elapsedSeconds, 0);

        // AUTO-TOGGLE LOGIC:
        // 1. Trigger pause when elapsed time is between 15 and 16 seconds.
        if (!autoPauseTriggered && elapsedSeconds >= 15 && elapsedSeconds <= 16) {
            console.log("Auton ended: triggering pause at elapsed =", elapsedSeconds);
            fetch('../php/toggle_pause.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ match_id: matchId })
            })
            .then(response => response.text())
            .then(data => console.log("Toggle pause response:", data))
            .catch(error => console.error("Error toggling pause:", error));
            autoPauseTriggered = true; // ensure this fires only once
        }

        // 2. Trigger unpause when elapsed time is between (delay+15) and (delay+16) seconds.
        if (!autoUnpauseTriggered && elapsedSeconds >= delay + 15 && elapsedSeconds <= delay + 16) {
            console.log("Teleop started: triggering unpause at elapsed =", elapsedSeconds);
            fetch('../php/toggle_pause.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ match_id: matchId })
            })
            .then(response => response.text())
            .then(data => console.log("Toggle unpause response:", data))
            .catch(error => console.error("Error toggling pause:", error));
            autoUnpauseTriggered = true; // ensure this fires only once
        }

        // If the match is currently paused, show "Paused" and exit.
        if (isPaused) {
            timerElement.textContent = "Paused";
            return;
        }

        // If time is up, display "Match Over" and deactivate match.
        if (remainingSeconds === 0) {
            timerElement.textContent = "Match Over";
            clearInterval(timerInterval);
  

            // Optionally deactivate the match on the server:
            if (matchId) {
                fetch('../php/deactivate_match.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ match_id: matchId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log("Match deactivated successfully.");
                    } else {
                        console.error("Failed to deactivate match.");
                    }
                })
                .catch(error => console.error("Error deactivating match:", error));
            }


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
      
    }, 1000); // third wait: 1 second
    
  }, 2000); // second wait: 2 seconds
  
}, 3000); // first wait: 3 seconds





            return;
        }

        // Otherwise, update the timer display.
        const minutes = Math.floor(remainingSeconds / 60);
        const seconds = Math.floor(remainingSeconds % 60);
        timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')} remaining`;
    }

    // Update the timer every second.
    const timerInterval = setInterval(updateTimer, 250);
    updateTimer();








</script>







<script>
    function fetchMatchData() {
        fetch("get_match_data.php")
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



fetch('get_active_event.php')
        .then(response => response.json())
        .then(data => {
            console.log("Active Event:", data.activeEventName);
            console.log("Next Match:", data.activeMatchNumber);
            // You can update your dropdowns or inputs here
            document.getElementById('event').value = data.activeEventName;
            document.getElementById('match_number').value = data.activeMatchNumber;
        })
        .catch(error => console.error('Error fetching active event data:', error));






const currentYear = new Date().getFullYear();
console.log(currentYear);
$('#year').val(currentYear);


}
setNextMatch();



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
        fetch('../php/insert_delay.php', {
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
        success: function(response) {
            if (response.delay !== null) {
                delay = parseInt(response.delay); // Store the fetched delay globally
                console.log("Fetched delay:", delay);
                document.getElementById('delay').value = delay;
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

<script>
document.addEventListener('DOMContentLoaded', () => {
  const bodyEl = document.body;
  const themeBtn = document.getElementById('themeToggle');
  if (!themeBtn) return;

  const savedTheme = localStorage.getItem('statOwlTheme') || 'dark';
  applyTheme(savedTheme);

  themeBtn.addEventListener('click', () => {
    const next = bodyEl.classList.contains('dark') ? 'light' : 'dark';
    applyTheme(next);
  });

  function applyTheme(theme) {
    if (theme === 'dark') {
      bodyEl.classList.add('dark');
      themeBtn.textContent = '🌙';
    } else {
      bodyEl.classList.remove('dark');
      themeBtn.textContent = '☀️';
    }
    localStorage.setItem('statOwlTheme', theme);
  }
});
</script>



</body>
</html>
