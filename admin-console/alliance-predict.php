<?php
// 1) Include DB connection.
include '../php/database_connection.php';

// 2) Get distinct event names from active_event (if needed for another dropdown).
$eventQuery = "SELECT DISTINCT event_name FROM active_event"; 
$eventStmt = $pdo->prepare($eventQuery);
$eventStmt->execute();
$events = $eventStmt->fetchAll(PDO::FETCH_ASSOC);

// 3) Get all distinct robots for the last event.
$activeEventQuery = "
    SELECT DISTINCT robot
    FROM scouting_submissions
    WHERE event_name = (
        SELECT event_name 
        FROM scouting_submissions 
        ORDER BY id DESC 
        LIMIT 1
    )
    ORDER BY robot ASC
";
$activeEventStmt = $pdo->prepare($activeEventQuery);
$activeEventStmt->execute();
$robots = $activeEventStmt->fetchAll(PDO::FETCH_ASSOC);

// Get the active event name from the most recent submission.
$activeEventName = "";
if (!empty($robots)) {
    // You could also query this separately. For now, we assume it's from the most recent submission.
    $eventNameQuery = "SELECT event_name FROM scouting_submissions ORDER BY id DESC LIMIT 1";
    $eventNameStmt = $pdo->prepare($eventNameQuery);
    $eventNameStmt->execute();
    $row = $eventNameStmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $activeEventName = $row['event_name'];
    }
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
    .logo {
      width: 100%;
      max-width: 400px;
      display: block;
      margin: 0 auto 1rem auto;
    }
    select {
      min-width: 180px;
      margin: 10px;
      padding: 8px;
    }
    input {
      min-width: 100px;
      font-size: 1.1rem;
      padding: 12px;
      border: 1px solid #fff;
      background-color: #222;
      color: #fff;
      border-radius: 5px;
    }
    button {
      font-size: 1.1rem;
      padding: 12px;
      border: 1px solid #fff;
      background-color: #222;
      color: #fff;
      border-radius: 5px;
      cursor: pointer;
      margin: 10px;
    }
    .flash {
      animation: flashEffect 1s linear;
    }
    @keyframes flashEffect {
      0% { background-color: yellow; }
      50% { background-color: red; }
      100% { background-color: yellow; }
    }
    /* Table styling */
    table {
      width: 80%;
      margin: 20px auto;
      border-collapse: collapse;
      background: #333;
    }
    table, th, td {
      border: 1px solid #fff;
    }
    th, td {
      padding: 10px;
      text-align: center;
    }
    mid{
overflow: hidden; /* Prevent content from spilling out */
    transition: height 1.5s ease-in-out; /* Animate height change */
    }

  .hide {
    height: 0;
    overflow: hidden;
    opacity: 0;
    transition: height 1.5s ease, opacity 1.5s ease;
}
#hide{width:98%;}
    .vsImg {
      width: 4rem;
    }
#top{background-color: #333;}

#bottom {
    display: flex;
    flex-wrap: wrap;  /* Allows collapsing on smaller screens */
    align-items: center;
    justify-content: center;
    gap: 10px; /* Space between items */
    margin-top: 10px;
    padding: 10px;
}

#blue, #vs, #red {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 1; /* Distribute equal space */
    min-width: 120px; /* Prevents elements from shrinking too much */
}

/* VS image */
.vsImg {
    width: 50px; /* Adjust size as needed */
    cursor: pointer;
}

/* Responsive collapse */
@media (max-width: 600px) {
    #bottom {
        flex-direction: column; /* Stack items on smaller screens */
    }

    #blue, #vs, #red {
        width: 100%; /* Make each element take full width */
        justify-content: center;
    }

    .vsImg {
        width: 40px; /* Reduce image size on small screens */
    }
}



  </style>
  <link rel="stylesheet" href="../css/select.css">
</head>
<body>
  <div id="container">
    <div id="top">
      <a href="..">
        <img src="../images/owlanalytics.png" class="logo" alt="Logo">
      </a>
    </div>
    <div id="mid">
      <h2>Active Event: <?= htmlspecialchars($activeEventName) ?></h2>
      
      <!-- Alliance Dropdown -->
      <select name="alliance" id="alliance">
        <option value="">Select Alliance</option>
        <?php for ($i = 1; $i <= 12; $i++): ?>
          <option value="<?= $i ?>"><?= $i ?></option>
        <?php endfor; ?>
      </select>
      
      <!-- Robot Dropdown -->
      <select name="robot" id="robot">
        <option value="">Select Robot</option>
        <?php foreach ($robots as $row): ?>
          <option value="<?= htmlspecialchars($row['robot']) ?>">
            <?= htmlspecialchars($row['robot']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      
      <!-- Button to add the selected robot -->
      <button id="addRobot">Add Robot</button>
      
      <!-- HTML5 Table to display selection -->
      <table id="selection">
        <thead>
          <tr>
            <th>Event Name</th>
            <th>Alliance #</th>
            <th>Robots</th>
          </tr>
        </thead>
        <tbody>
          <!-- Rows will be inserted here -->
        </tbody>
      </table>

    </div>
    <button id="hide">Hide</button>
<div id="bottom">
            
<div id="blue">

      <select name="bluealliance" id="bluealliance">
        <option value="">Blue Alliance</option>
        <?php for ($i = 1; $i <= 12; $i++): ?>
          <option value="<?= $i ?>"><?= $i ?></option>
        <?php endfor; ?>
      </select>
</div>
      
          <div id="vs">
            <!-- VS image between alliances -->
            <img class="vsImg" src="../images/vs.png" alt="vs" onclick="cycleRobotCardViews('startStop')">
          </div>
          <div id="red">
      <select name="redalliance" id="redalliance">
        <option value="">Red Alliance</option>
        <?php for ($i = 1; $i <= 12; $i++): ?>
          <option value="<?= $i ?>"><?= $i ?></option>
        <?php endfor; ?>
      </select>
</div>


  </div>
  </div>
  
  <script>
    // Wait for the DOM to fully load.
    document.addEventListener("DOMContentLoaded", function() {
      // Global variables for the selections.
      const activeEventName = <?= json_encode($activeEventName) ?>;
      let currentAlliance = "";
      let robotSelections = []; // Array to hold selected robots for the current alliance.
      
      const allianceSelect = document.getElementById("alliance");
      const robotSelect = document.getElementById("robot");
      const addRobotButton = document.getElementById("addRobot");
      const tableBody = document.querySelector("#selection tbody");
      const hideSelection = document.querySelector("#hide");

hideSelection.addEventListener("click", function() {
    let midSection = document.getElementById("mid");
    midSection.classList.toggle("hide");

    // Toggle button text
    this.innerHTML = midSection.classList.contains("hide") ? "Show" : "Hide";
});


      addRobotButton.addEventListener("click", function() {
        const allianceValue = allianceSelect.value;
        const robotValue = robotSelect.value;
        




        console.log("Alliance selected:", allianceValue, "Robot selected:", robotValue);
        
        // Validate that an alliance is selected.
        if (!allianceValue) {
          alert("Please select an alliance.");
          return;
        }
        // Validate that a robot is selected.
        if (!robotValue) {
          alert("Please select a robot.");
          return;
        }
        
        // If the alliance has changed, reset the robotSelections array.
        if (currentAlliance !== allianceValue) {
          currentAlliance = allianceValue;
          robotSelections = [];
          console.log("Alliance changed. Resetting robot selections.");
        }
        
        // Prevent duplicate robot selection.
        if (robotSelections.includes(robotValue)) {
          alert("This robot is already selected for alliance " + allianceValue + ".");
          return;
        }
        
        // Add the selected robot to the array.
        robotSelections.push(robotValue);
        console.log("Current selections for alliance", allianceValue + ":", robotSelections);
        
        // If three robots have been selected, add a row to the table.
        if (robotSelections.length === 3) {
          let newRow = tableBody.insertRow();
          
          // Create cells for Event Name, Alliance, and Robots.
          let cellEvent = newRow.insertCell();
          let cellAlliance = newRow.insertCell();
          let cellRobots = newRow.insertCell();
          
          cellEvent.textContent = activeEventName;
          cellAlliance.textContent = allianceValue;
          cellRobots.textContent = robotSelections.join(", ");
          
          console.log("Row added:", activeEventName, allianceValue, robotSelections.join(", "));
          
          // Reset robotSelections for the next entry.
          robotSelections = [];
          // Optionally reset the robot dropdown.
          robotSelect.value = "";
        }
      });
    });
  </script>
</body>
</html>
