<?php
    require_once '../php/database_connection.php';
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Fetch unique events from scouting_submissions
    $event_query = $pdo->query("SELECT DISTINCT event_name FROM scouting_submissions ORDER BY event_name ASC");
    $events = $event_query->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Owl Analytics</title>

  <style>
    /* --- Font Faces --- */
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
    }
    .containerOuter {
      background-color: #333;
      border-bottom: 1px solid #444;
      width: 100%;
      padding: 1rem;
      box-sizing: border-box;
    }
    .container {
      max-width: 800px;
      margin: auto;
    }
    
    /* Grid layout for dropdowns */
    .grid-container {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      grid-gap: 1rem;
      margin-bottom: 1rem;
    }
    .grid-item {
      display: flex;
      flex-direction: column;
    }
    .grid-item label {
      margin-bottom: 0.3rem;
      font-weight: bold;
      font-size: 1rem;
    }
    .grid-item ,
    .grid-item input {
      padding: 0.5rem;
      border-radius: 4px;
      border: 1px solid #555;
      background: #444;
      color: #eee;
      font-size: 1rem;
      width: 100%;
      box-sizing: border-box;
    }
    
  .grid-container_stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    /* Optional: add some padding to the container */
    padding: 1rem;
  }
  .grid-item_stats {
    border: 1px solid #ccc;
    padding: 1rem;
    text-align: center;
    /* Optional: ensure all boxes are the same height */
    display: flex;
    align-items: center;
    justify-content: center;
  }






    /* Card styling */
    .card {
      background: #fff;
      color: #333;
      border-radius: 8px;
 
      width: 100%;
      max-width: 800px;
      margin: 2rem auto;
      padding: 1rem;
      overflow-x: auto;
    }
    .prediction-card {
      /* Same as .card */
    }
    
    /* Robot Cards Layout */
    .robot-cards {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      padding: 1rem;
      justify-content: center;
    }
    
    /* Scoring breakdown table (for robot cards) */
    .scoring-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 0.5rem;
      font-size: 0.9rem;
    }
    .scoring-table th, .scoring-table td {
      border: 1px solid #ccc;
      padding: 0.5rem;
      text-align: center;
    }
    .scoring-table th {
      background: #f0f0f0;
      color: #333;
    }
    
    /* Prediction Card styling */
    .prediction-card {
      background: #fff;
      color: #333;
      border-radius: 8px;
 
      width: 90%;
      max-width: 800px;
      margin: 1rem auto;
     
      overflow-x: auto;
    }
    
    /* Prediction display table */
    .predictTable {
      font-size: 0.8rem;
      border-collapse: collapse;
      margin: 25px auto;
      font-family: sans-serif;
     
      box-shadow: 0 0 20px rgba(0,0,0,0.15);
      width: 100%;
    }
    .predictTable thead tr {
      background-color: #9A7E6F;
      color: #fff;
      text-align: center;
    }
    .predictTable th, .predictTable td {
      padding: 6px 8px;
      text-align: center;
    }
    .predictTable tbody tr {
      border-bottom: 1px solid #dddddd;
    }
    .predictTable tbody tr:nth-of-type(even) {
      background-color: #f3f3f3;
    }
    .predictTable tbody tr:last-of-type {
      border-bottom: 2px solid #9A7E6F;
    }
    
    /* Grouped Chart container (if needed) */
    .grouped-chart {
      width: 100%;
      height: 400px;
      margin: 25px auto;
    }
    
    /* Hide filter list display */
    #robotFilterList {
      display: none;
    }
    .logo {
      width: 400px;
      display: block;
      margin: 0 auto 1rem auto;
    }



@keyframes blueWinAnim {
  0% { background-color: #fff; }
  50% { background-color: #cce5ff; }
  100% { background-color: #fff; }
}
@keyframes redWinAnim {
  0% { background-color: #fff; }
  50% { background-color: #f8d7da; }
  100% { background-color: #fff; }
}
@keyframes tieWinAnim {
  0% { background-color: #fff; }
  50% { background-color: #e2e3e5; }
  100% { background-color: #fff; }
}

.prediction-card #predictionHeader.blue-win {
  animation: blueWinAnim 2s;
}
.prediction-card #predictionHeader.red-win {
  animation: redWinAnim 2s;
}
.prediction-card #predictionHeader.tie-win {
  animation: tieWinAnim 2s;
}















/* HTML: <div class="loader"></div> */



.loader2 {
color:#fff;
  font-weight: bold;
  font-family: monospace;
  display: inline-grid;
  font-size: 1.5rem;
  margin:auto;
  text-align: center;
}
.loader2:before,
.loader2:after {
  content:"Processing Random Forest Regression Prediction...";
  grid-area: 1/1;
  -webkit-mask-size: 2ch 100%,100% 100%;
  -webkit-mask-repeat: no-repeat;
  -webkit-mask-composite: xor;
          mask-composite:exclude;
  animation: l37 1s infinite;
}
.loader2:before {
  -webkit-mask-image:
    linear-gradient(#000 0 0),
    linear-gradient(#000 0 0);
}
.loader2:after {
  -webkit-mask-image:linear-gradient(#000 0 0);
  transform: scaleY(0.5);
}

@keyframes l37{
  0%    {-webkit-mask-position:1ch  0,0 0}
  12.5% {-webkit-mask-position:100% 0,0 0}
  25%   {-webkit-mask-position:4ch  0,0 0}
  37.5% {-webkit-mask-position:8ch  0,0 0}
  50%   {-webkit-mask-position:2ch  0,0 0}
  62.5% {-webkit-mask-position:100% 0,0 0}
  75%   {-webkit-mask-position:0ch  0,0 0}
  87.5% {-webkit-mask-position:6ch  0,0 0}
  100%  {-webkit-mask-position:3ch  0,0 0}
}



.loader {
  width: 85px;
  height: 50px;
  --g1:conic-gradient(from  90deg at left   3px top   3px,#0000 90deg,#fff 0);
  --g2:conic-gradient(from -90deg at bottom 3px right 3px,#0000 90deg,#fff 0);
  background: var(--g1),var(--g1),var(--g1), var(--g2),var(--g2),var(--g2);
  background-position: left,center,right;
  background-repeat: no-repeat;
  animation: l10 1s infinite alternate;
}
@keyframes l10 {
  0%,
  2%   {background-size:25px 50% ,25px 50% ,25px 50%}
  20%  {background-size:25px 25% ,25px 50% ,25px 50%}
  40%  {background-size:25px 100%,25px 25% ,25px 50%}
  60%  {background-size:25px 50% ,25px 100%,25px 25%}
  80%  {background-size:25px 50% ,25px 50% ,25px 100%}
  98%,
  100% {background-size:25px 50% ,25px 50% ,25px 50%}
}




.loader{
margin:auto;
}
#predictionCard{

border:none;
  background-color: #222;}














#newChart{height:18rem; }


#newChartContainer {
  min-height: 400px;
 width:90% !important;
  display:none;
}


  </style>
  <link rel="stylesheet" href="../css/select.css">
  <!-- Include Chart.js -->
  <script src="../js/Chart.bundle.js"></script>
  <script>
    /******************************************
     * Global Variables
     ******************************************/
    let fetchedRobots = [];    // Robot data from fetch_robot_data.php
    let aggregatedData = {};     // Aggregated data from predict.php
    let filterRobots = [];       // Array to hold robot IDs to exclude
    
    /******************************************
     * Fetch Functions
     ******************************************/
    // Fetch matches when event is selected.
    function fetchMatches() {
      const eventName = document.getElementById("eventDropdown").value;
      const matchDropdown = document.getElementById("matchDropdown");
      const robotContainer = document.getElementById("robotContainer");
      matchDropdown.innerHTML = "<option value=''>-- Select Match --</option><option value='all'>All Matches</option>";
      robotContainer.innerHTML = "";
      if (!eventName) return;
      const xhr = new XMLHttpRequest();
      xhr.open("GET", "fetch_matches.php?event_name=" + encodeURIComponent(eventName), true);
      xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
          let response = JSON.parse(xhr.responseText);
          if (response.error) {
            console.error(response.error);
            return;
          }
          if (response.length > 0) {
            response.forEach(match => {
              let option = document.createElement("option");
              option.value = match.match_number;
              option.textContent = "Match " + match.match_number;
              matchDropdown.appendChild(option);
            });
          } else {
            console.log("No matches found for this event.");
          }
        }
      };
      xhr.send();
    }
    
    // Fetch robot card data from fetch_robot_data.php (or fetch_robot_data2.php).
   function fetchRobotCards() {
  const eventName = document.getElementById("eventDropdown").value;
  const matchNumber = document.getElementById("matchDropdown").value;
  const robotContainer = document.getElementById("robotContainer");
  robotContainer.innerHTML = "";
  if (!eventName || !matchNumber) return;
  
  const xhr = new XMLHttpRequest();
  console.log("Fetching robot cards for:", eventName, matchNumber);
  if (matchNumber !== 'all') {
    xhr.open("GET", "fetch_robot_data.php?event_name=" + encodeURIComponent(eventName) + "&match_number=" + encodeURIComponent(matchNumber), true);
  } else {
    xhr.open("GET", "fetch_robot_data2.php?event_name=" + encodeURIComponent(eventName) + "&match_number=" + encodeURIComponent(matchNumber), true);
  }
  
  xhr.onreadystatechange = function() {
    if (xhr.readyState === 4) {
      console.log("Robot Cards Raw Response:", xhr.responseText);
      try {
        let data = JSON.parse(xhr.responseText);
        if (data.error) {
          console.error("Error:", data.error);
          robotContainer.innerHTML = `<p style="color:red;">${data.error}</p>`;
          return;
        }
        // Normalize data to an array.
        let robotsArray = Array.isArray(data)
          ? data
          : (data.robots && Array.isArray(data.robots))
          ? data.robots
          : [data];
        fetchedRobots = robotsArray;
        filterRobots = [];
        updateRobotCards();
        populateRobotToggleDropdown();
        // Now that robot data is fully received, call fetchPrediction()
        fetchMatchCharts();
        fetchPrediction();
      } catch (error) {
        console.error("JSON Parsing Error:", error);
        console.log("Response:", xhr.responseText);
        robotContainer.innerHTML = `<p style="color:red;">Error processing robot data. Check console.</p>`;
      }
    }
  };
  xhr.send();
}



    
    // Fetch aggregated prediction data from predict.php.
    function fetchPrediction() {
      const eventName = document.getElementById("eventDropdown").value;
      const matchNumber = document.getElementById("matchDropdown").value;
      
      
if(matchNumber !=="all"){


      // Build alliance arrays from fetchedRobots.
      let blueAlliance = [];
      let redAlliance = [];
      fetchedRobots.forEach(robot => {
        if (robot.alliance && robot.alliance.toLowerCase() === "blue") {
          blueAlliance.push(robot.robot.toString().trim());
        } else if (robot.alliance && robot.alliance.toLowerCase() === "red") {
          redAlliance.push(robot.robot.toString().trim());
        }
      });
      
      let hist_weight = 0.5;
      const apiUrl = `predict.php?event_name=${encodeURIComponent(eventName)}&match_no=${encodeURIComponent(matchNumber)}&blue_alliance=${encodeURIComponent(blueAlliance.join(','))}&red_alliance=${encodeURIComponent(redAlliance.join(','))}&hist_weight=${encodeURIComponent(hist_weight)}`;
      console.log("Prediction API URL:", apiUrl);
      const xhr = new XMLHttpRequest();
      xhr.open("GET", apiUrl, true);
      xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
          console.log("Prediction Raw Response:", xhr.responseText);
          try {
            let data = JSON.parse(xhr.responseText);
            if (data.error) {
              console.error("Prediction Error:", data.error);
              document.getElementById("predictionResult").innerHTML = `<span style="color:red;">${data.error}</span>`;
              return;
            }
            aggregatedData = data;
            updatePredictionDisplay();
          } catch (error) {
            console.error("Prediction JSON Parsing Error:", error);
            console.log("Response:", xhr.responseText);
            document.getElementById("predictionResult").innerHTML = `<span style="color:red;">Error processing prediction data. Check console.</span>`;
          }
        }
      };
      xhr.send();
}else{

  updatePredictionDisplay();
}




    }
    
    /******************************************
     * Utility Functions for Sorting & Exclusion
     ******************************************/
    // Populate the robot exclusion dropdown.
    function populateRobotToggleDropdown() {
      const dropdown = document.getElementById("robotToggleDropdown");
      dropdown.innerHTML = "<option value=''>-- Select Robot --</option>";
      const uniqueRobots = [...new Set(fetchedRobots.map(r => r.robot))];
      uniqueRobots.forEach(robotNum => {
        const option = document.createElement("option");
        option.value = robotNum;
        option.textContent = robotNum;
        dropdown.appendChild(option);
      });
    }
    
    // Toggle robot exclusion.
//    function toggleRobotFilter() {
//      const dropdown = document.getElementById("robotToggleDropdown");
//      const selectedNum = parseInt(dropdown.value, 10);
//      if (!selectedNum) return;
//      const idx = filterRobots.indexOf(selectedNum);
//      if (idx === -1) {
//        filterRobots.push(selectedNum);
//      } else {
//        filterRobots.splice(idx, 1);
//      }
//      updateRobotCards();
//      dropdown.value = "";
//    }

console.log(document.querySelectorAll('.robot-card').length);

function toggleRobotFilter(cardId) {
  // If no cardId is provided, use the dropdown's value.
  const targetId = cardId || document.getElementById("robotToggleDropdown").value.trim();
  if (!targetId) {
    console.error("No target id provided and dropdown value is empty.");
    return;
  }

  const targetDiv = document.getElementById(targetId);
  if (!targetDiv) {
    console.error(`No element found with id "${targetId}"`);
    return;
  }

  // Get the actual computed background color
  let currentColor = window.getComputedStyle(targetDiv).backgroundColor;

  // Convert RGB format to hex
  const rgbToHex = (rgb) => {
    let [r, g, b] = rgb.match(/\d+/g).map(Number);
    return `#${((1 << 24) | (r << 16) | (g << 8) | b).toString(16).slice(1)}`;
  };

  let currentHex = currentColor.startsWith("rgb") ? rgbToHex(currentColor) : currentColor;
  console.log(`Current Color of ${targetId}: ${currentHex}`);

  // Toggle between #666 and default (empty string)
  if (currentHex === "#666666" || currentHex === "#333333") {
    targetDiv.style.backgroundColor = "";
  } else {
    targetDiv.style.backgroundColor = "#666";
  }

  // If triggered by the dropdown, clear its value.
  if (!cardId) {
    document.getElementById("robotToggleDropdown").value = "";
  }
}



    
   

    // Update robot cards display with sorting and filtering.
    function updateRobotCards() {
      const sortBy = document.getElementById("sortOption").value;
      let sorted = [...fetchedRobots];
      if (sortBy === "alliance") {
        sorted.sort((a, b) => (a.alliance || "Unknown").localeCompare(b.alliance || "Unknown"));
      } else {
        sorted.sort((a, b) => (b[sortBy] || 0) - (a[sortBy] || 0));
      }
      // Exclude robots that are in filterRobots.
      const finalList = sorted.filter(robot => !filterRobots.includes(parseInt(robot.robot, 10)));
      displayRobotCards(finalList);
    }
    
    /******************************************
     * Display Functions
     ******************************************/
    // Display individual robot cards.
    function displayRobotCards(robots) {
      const container = document.getElementById("robotContainer");
      if (!container) {
        console.error("robotContainer element not found.");
        return;
      }
      let html = "";
      robots.forEach((robot, index) => {
        html += `
          <div class="robot-card card" id="${robot.robot}">
            <div class="top-row">
              <div class="robot-details">
                <h3>Robot ${robot.robot}</h3>
                <p><strong>Alliance:</strong> ${robot.alliance}</p>
                <div class="grid-container_stats">
  <div class="grid-item_stats">
    <p>Matches Played: ${robot.match_count || "N/A"}</p>
  </div>
  <div class="grid-item_stats">
    <p>Favorite Starting Position: ${formatString(robot.starting_position) || "N/A"}</p>
  </div>
  <div class="grid-item_stats">
    <p>Auton Path: ${robot.auton_path || "N/A"}</p>
  </div>
  <div class="grid-item_stats">
    <p>Top Scoring Location: ${robot.top_scoring_location || "N/A"}</p>
  </div>
  <div class="grid-item_stats">
    <p>Average Offense: ${robot.offense_score || 0}</p>
  </div>
  <div class="grid-item_stats">
    <p>Defense Score: ${robot.defense_score || 0}</p>
  </div>
  <div class="grid-item_stats">
    <p>Auton Score: ${robot.auton_score || 0}</p>
  </div>
  <div class="grid-item_stats">
    <p>Cooperative Score: ${parseFloat(robot.cooperative_score || 0).toFixed(2)}</p>
  </div>
</div>
              </div>
              <div class="robot-chart">
                <canvas id="chart_${robot.robot}"></canvas>

              </div>
            </div>
            <div class="scoring-breakdown">
              <h4>Scoring Breakdown</h4>
              <table class="scoring-table">
                <thead>
                  <tr>
                    <th>Scoring Location</th>
                    <th>Successes</th>
                    <th>Attempts</th>
          <th>Successes Per Match</th>
                    <th>Rate</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>Level 1</td>
                    <td>${robot.count_level_1 || 0}</td>
                    <td>${robot.level1_attempts || 0}</td>
          <td>${robot.level1_avg_attempts || 0}</td>
                    <td>${(robot.level1_attempts > 0) ? ((robot.count_level_1 / robot.level1_attempts) * 100).toFixed(1) + "%" : "0%"}</td>
                  </tr>
                  <tr>
                    <td>Level 2</td>
                    <td>${robot.count_level_2 || 0}</td>
                    <td>${robot.level2_attempts || 0}</td>
          <td>${robot.level2_avg_attempts || 0}</td>
                    <td>${(robot.level2_attempts > 0) ? ((robot.count_level_2 / robot.level2_attempts) * 100).toFixed(1) + "%" : "0%"}</td>
                  </tr>
                  <tr>
                    <td>Level 3</td>
                    <td>${robot.count_level_3 || 0}</td>
                    <td>${robot.level3_attempts || 0}</td>
          <td>${robot.level3_avg_attempts || 0}</td>
                    <td>${(robot.level3_attempts > 0) ? ((robot.count_level_3 / robot.level3_attempts) * 100).toFixed(1) + "%" : "0%"}</td>
                  </tr>
                  <tr>
                    <td>Level 4</td>
                    <td>${robot.count_level_4 || 0}</td>
                    <td>${robot.level4_attempts || 0}</td>
          <td>${robot.level4_avg_attempts || 0}</td>
                    <td>${(robot.level4_attempts > 0) ? ((robot.count_level_4 / robot.level4_attempts) * 100).toFixed(1) + "%" : "0%"}</td>
                  </tr>
                  <tr>
                    <td>Algae Net</td>
                    <td>${robot.algae_net_success || 0}</td>
                    <td>${robot.algae_net_attempts || 0}</td>
          <td>${robot.algae_net_avg_attempts || 0}</td>
                    <td>${(robot.algae_net_attempts > 0) ? ((robot.algae_net_success / robot.algae_net_attempts) * 100).toFixed(1) + "%" : "0%"}</td>
                  </tr>
                  <tr>
                    <td>Algae Proc</td>
                    <td>${robot.algae_processor_success || 0}</td>
                    <td>${robot.algae_processor_attempts || 0}</td>
          <td>${robot.algae_processor_avg_attempts || 0}</td>
                    <td>${(robot.algae_processor_attempts > 0) ? ((robot.algae_processor_success / robot.algae_processor_attempts) * 100).toFixed(1) + "%" : "0%"}</td>
                  </tr>

                <!-- New Rows for Deep & Shallow Climb -->
      <tr>
        <td>Deep Climb</td>
        <td>${robot.deep_climb_success || 0}</td>
        <td>${robot.deep_climb_attempts || 0}</td>
        <td>N/A</td>
        <td>${(robot.deep_climb_attempts > 0) ? ((robot.deep_climb_success / robot.deep_climb_attempts) * 100).toFixed(1) + "%" : "0%"}</td>
      </tr>
      <tr>
        <td>Shallow Climb</td>
        <td>${robot.shallow_climb_success || 0}</td>
        <td>${robot.shallow_climb_attempts || 0}</td>
        <td>${robot.shallow_climb_avg_attempts || 0}</td>
        <td>${(robot.shallow_climb_attempts > 0) ? ((robot.shallow_climb_success / robot.shallow_climb_attempts) * 100).toFixed(1) + "%" : "0%"}</td>
      </tr>




                </tbody>
              </table>
            </div>
          <h4>Highest Score: <span>${robot.high_score || 0} </span></h4> <h4>In match:<span> ${robot.high_score_match || 0} </span></h4>
          </div>
        `;
      });
      container.innerHTML = html;
      
      // Create individual charts for each robot (if desired)
 // Create individual charts for each robot (using the robot's unique ID)
fetchedRobots.forEach(robot => {
  // Use the robot identifier to get the correct canvas element.
  const canvas = document.getElementById("chart_" + robot.robot);
  if (!canvas) return;
  const ctx = canvas.getContext("2d");

  // Extract the performance values (ensuring numeric conversion)
  const offense = Number(robot.offense_score) || 0;
  const defense = Number(robot.defense_score) || 0;
  const auton = Number(robot.auton_score) || 0;
  const coop = Number(robot.cooperative_score) || 0;
  const coopColor = coop >= 0 ? "rgba(0, 128, 0, 0.6)" : "rgba(243,53,53,0.6)";
  const coopBorderColor = coop >= 0 ? "rgba(0, 128, 0, 1)" : "rgba(243,53,53,1)";

  const data = {
    labels: ["Offense", "Defense", "Auton", "Co-op"],
    datasets: [{
      label: "Performance",
      data: [offense, defense, auton, coop],
      backgroundColor: [
        "rgba(20, 61, 96, 0.6)",
        "rgba(39, 102, 123, 0.6)",
        "rgba(160, 200, 120, 0.6)",
        coopColor
      ],
      borderColor: [
        "rgb(20, 61, 96)",
        "rgb(39, 102, 123)",
        "rgb(160, 200, 120)",
        coopBorderColor
      ],
      borderWidth: 1
    }]
  };

  new Chart(ctx, {
    type: "bar",
    data: data,
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        x: { beginAtZero: true },
        y: { beginAtZero: true }
      },
      plugins: { legend: { display: false } }
    }
  });
});

    }



        function formatString(str) {
      if (!str) return "N/A";
      str = str.replace(/_/g, " ");
      return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    // Update prediction display using aggregated data from predict.php.
 function updatePredictionDisplay() {
  // Combine blue_stats and red_stats if needed (or use them separately)
  let blueStats = [];
  let redStats = [];
  if (aggregatedData.blue_stats && Array.isArray(aggregatedData.blue_stats)) {
    blueStats = aggregatedData.blue_stats;
  }
  if (aggregatedData.red_stats && Array.isArray(aggregatedData.red_stats)) {
    redStats = aggregatedData.red_stats;
  }
  
  // Update header info with your original snippet
  let headerHtml = `<strong>Event:</strong> ${aggregatedData.event_name}<br>`;
  headerHtml += `<strong>Match No:</strong> ${aggregatedData.match_no}<br>`;
  headerHtml += `<strong>Blue Alliance Score:</strong> ${aggregatedData.blue_score}<br>`;
  headerHtml += `<strong>Red Alliance Score:</strong> ${aggregatedData.red_score}<br>`;
  headerHtml += `<strong>Predicted Winner:</strong> ${aggregatedData.predicted_winner}<br><br>`;
  
  const headerEl = document.getElementById("predictionHeader");
  headerEl.innerHTML = headerHtml;
  
  // Remove previous animation classes
  headerEl.classList.remove("blue-win", "red-win", "tie-win");
  // Apply the appropriate animation based on predicted winner
let predictedWinner = aggregatedData.predicted_winner || "";
if (predictedWinner.toLowerCase().includes("blue")) {
  headerEl.classList.add("blue-win");
} else if (predictedWinner.toLowerCase().includes("red")) {
  headerEl.classList.add("red-win");
} else {
  headerEl.classList.add("tie-win");
}
  
  // Build tables for Blue and Red Alliance basic stats.
  let chartsHtml = "";
  
  chartsHtml += `<h2>Blue Alliance Stats</h2>`;
  chartsHtml += `<table class="predictTable">
                   <thead>
                     <tr>
                       <th>Robot</th>
                       <th>Matches</th>
                       <th>Avg PPM</th>
                       <th>Next Points</th>
                     </tr>
                   </thead>
                   <tbody>`;
  blueStats.forEach(stat => {
    chartsHtml += `<tr>
                     <td>${stat.robot}</td>
                     <td>${stat.matches}</td>
                     <td>${stat.avg_points_per_match}</td>
                     <td>${stat.predicted_next_points}</td>
                   </tr>`;
  });
  chartsHtml += `</tbody></table>`;
  
  chartsHtml += `<h2>Red Alliance Stats</h2>`;
  chartsHtml += `<table class="predictTable">
                   <thead>
                     <tr>
                       <th>Robot</th>
                       <th>Matches</th>
                       <th>Avg PPM</th>
                       <th>Next Points</th>
                     </tr>
                   </thead>
                   <tbody>`;
  redStats.forEach(stat => {
    chartsHtml += `<tr>
                     <td>${stat.robot}</td>
                     <td>${stat.matches}</td>
                     <td>${stat.avg_points_per_match}</td>
                     <td>${stat.predicted_next_points}</td>
                   </tr>`;
  });
  chartsHtml += `</tbody></table>`;
  
  // Create 3 charts per alliance (same as before)
  chartsHtml += `<div style="margin-bottom:1rem;"><h3>Blue Alliance: Success Rate Slope</h3><canvas id="blueSuccessChart" style="width:100%; height:300px;"></canvas></div>`;
  chartsHtml += `<div style="margin-bottom:1rem;"><h3>Blue Alliance: Total Events Slope</h3><canvas id="blueEventsChart" style="width:100%; height:300px;"></canvas></div>`;
  chartsHtml += `<div style="margin-bottom:1rem;"><h3>Blue Alliance: Points Slope</h3><canvas id="bluePointsChart" style="width:100%; height:300px;"></canvas></div>`;
  
  chartsHtml += `<div style="margin-bottom:1rem;"><h3>Red Alliance: Success Rate Slope</h3><canvas id="redSuccessChart" style="width:100%; height:300px;"></canvas></div>`;
  chartsHtml += `<div style="margin-bottom:1rem;"><h3>Red Alliance: Total Events Slope</h3><canvas id="redEventsChart" style="width:100%; height:300px;"></canvas></div>`;
  chartsHtml += `<div style="margin-bottom:1rem;"><h3>Red Alliance: Points Slope</h3><canvas id="redPointsChart" style="width:100%; height:300px;"></canvas></div>`;
  document.getElementById("predictionCard").style.backgroundColor = '#fff';
  // Update the charts container (assumed to have id "predictionCharts")
  document.getElementById("predictionCharts").innerHTML = chartsHtml;
  
  // Build Blue Alliance charts.
  const blueLabels = blueStats.map(r => r.robot);
  const blueSuccessData = blueStats.map(r => r.success_rate_slope || 0);
  const blueEventsData = blueStats.map(r => r.total_events_slope || 0);
  const bluePointsData = blueStats.map(r => r.points_slope || 0);
  
  const blueSuccessCtx = document.getElementById("blueSuccessChart").getContext("2d");
  new Chart(blueSuccessCtx, {
    type: 'bar',
    data: {
      labels: blueLabels,
      datasets: [{
        label: "Success Rate Slope",
        data: blueSuccessData,
        backgroundColor: "rgb(41, 37, 44)"
      }]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      scales: { x: { beginAtZero: true } },
      plugins: { legend: { position: 'bottom' } }
    }
  });
  
  const blueEventsCtx = document.getElementById("blueEventsChart").getContext("2d");
  new Chart(blueEventsCtx, {
    type: 'bar',
    data: {
      labels: blueLabels,
      datasets: [{
        label: "Total Events Slope",
        data: blueEventsData,
        backgroundColor: "rgb(216, 233, 240)"
      }]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      scales: { x: { beginAtZero: true } },
      plugins: { legend: { position: 'bottom' } }
    }
  });
  
  const bluePointsCtx = document.getElementById("bluePointsChart").getContext("2d");
  new Chart(bluePointsCtx, {
    type: 'bar',
    data: {
      labels: blueLabels,
      datasets: [{
        label: "Points Slope",
        data: bluePointsData,
        backgroundColor: "rgb(51, 66, 91)"
      }]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      scales: { x: { beginAtZero: true } },
      plugins: { legend: { position: 'bottom' } }
    }
  });
  
  // Build Red Alliance charts.
  const redLabels = redStats.map(r => r.robot);
  const redSuccessData = redStats.map(r => r.success_rate_slope || 0);
  const redEventsData = redStats.map(r => r.total_events_slope || 0);
  const redPointsData = redStats.map(r => r.points_slope || 0);
  
  const redSuccessCtx = document.getElementById("redSuccessChart").getContext("2d");
  new Chart(redSuccessCtx, {
    type: 'bar',
    data: {
      labels: redLabels,
      datasets: [{
        label: "Success Rate Slope",
        data: redSuccessData,
        backgroundColor: "rgb(135, 35, 65)"
      }]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      scales: { x: { beginAtZero: true } },
      plugins: { legend: { position: 'bottom' } }
    }
  });
  
  const redEventsCtx = document.getElementById("redEventsChart").getContext("2d");
  new Chart(redEventsCtx, {
    type: 'bar',
    data: {
      labels: redLabels,
      datasets: [{
        label: "Total Events Slope",
        data: redEventsData,
        backgroundColor: "rgb(190, 49, 68)"
      }]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      scales: { x: { beginAtZero: true } },
      plugins: { legend: { position: 'bottom' } }
    }
  });
  
  const redPointsCtx = document.getElementById("redPointsChart").getContext("2d");
  new Chart(redPointsCtx, {
    type: 'bar',
    data: {
      labels: redLabels,
      datasets: [{
        label: "Points Slope",
        data: redPointsData,
        backgroundColor: "rgb(225, 117, 100)"
      }]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      scales: { x: { beginAtZero: true } },
      plugins: { legend: { position: 'bottom' } }
    }
  });
}





    
    /******************************************
     * Event Listeners
     ******************************************/
   
  </script>
</head>
<body>
  <div class="containerOuter">
    <div class="container">
      <a href="."><img src="../images/owlAnalytics.png" class="logo" alt="Logo"></a>
      <!-- Grid for Dropdowns -->
      <div class="grid-container">
        <div class="grid-item">
          <label for="eventDropdown"><strong>Select an Event:</strong></label>
          <select id="eventDropdown" onchange="fetchMatches()">
            <option value="">-- Select Event --</option>
            <?php foreach ($events as $event) { ?>
              <option value="<?php echo htmlspecialchars($event['event_name']); ?>">
                <?php echo htmlspecialchars($event['event_name']); ?>
              </option>
            <?php } ?>
          </select>
        </div>
        <div class="grid-item">
          <label for="matchDropdown"><strong>Select a Match:</strong></label>
          <select id="matchDropdown">
            <option value="">-- Select Match --</option>
            <option value="all">All Matches</option>
          </select>
        </div>
        <div class="grid-item">
          <label for="sortOption"><strong>Sort by:</strong></label>
          <select id="sortOption" onchange="updateRobotCards()">
            <option value="alliance" selected>Alliance</option>
            <option value="offense_score">Offense Score</option>
            <option value="defense_score">Defense Score</option>
            <option value="cooperative_score">Cooperative Score</option>
          </select>
        </div>
        <div class="grid-item">
          <label for="robotToggleDropdown"><strong>Robot (Exclude):</strong></label>
          <select id="robotToggleDropdown" onchange="toggleRobotFilter()">
            <option value="">-- Select Robot --</option>
          </select>
        </div>
      </div>
      <!-- Hidden Filter List -->
      <input type="text" id="robotFilterList" readonly placeholder="Filter list" />
    </div>
  </div>
<div id="newChartContainer" class="card">
  <canvas id="newChart" style="width:100%; height:400px;"></canvas>
</div>

  <!-- Prediction Card -->
<div id="predictionCard" class="card prediction-card ">
  <div id="predictionHeader">
    <!-- updatePredictionDisplay() will update header info here -->
  </div>
  <div id="predictionResult">
    <!-- updatePredictionDisplay() will insert header + tables here -->
  </div>
  <div id="predictionCharts">
    <!-- This container is updated by updatePredictionDisplay() with your charts -->
  </div>
</div>

  
  <!-- Robot Cards Container -->
  <div id="robotContainer" class="robot-cards"></div>

  <script type="text/javascript">
    

 // When the match dropdown changes, fetch robot cards and then prediction data.
    document.getElementById('matchDropdown').addEventListener('change', function() {
      const eventName = document.getElementById("eventDropdown").value;
      const matchNumber = document.getElementById("matchDropdown").value;
      if (!eventName || !matchNumber || matchNumber === "") {
        console.warn("Missing required parameters.");
        return;
      }
      // First, fetch robot cards. After a short delay, fetch prediction data.
       showPredictionLoading(matchNumber);
      fetchRobotCards();
     
    });
function showPredictionLoading(matchNumber) {
  const predictionContainer = document.getElementById("predictionCard");
  const predictionCharts = document.getElementById("predictionCharts");
  if(matchNumber == 'all'){
predictionContainer.style.display = 'none'
  }else{
  predictionContainer.style.display = 'block'
  predictionContainer.style.backgroundColor = '#222'

  predictionCharts.innerHTML = '';
  document.getElementById("predictionHeader").innerHTML = '';
  predictionCharts.innerHTML = `<div class="loader"> </div> <div class="loader2"> </div>`;
}
}




// Global variables for the chart and cycling
var matchChart; 
var currentMetricIndex = 0;
var metrics = ['offense_scores', 'points', 'success_rate', 'defense_score'];
var groupedData = {}; // Will hold data grouped by robot

// Global variables (ensure these are declared at the top of your script)
var groupedData = {}; // will be filled by fetchMatchCharts
var metrics = ['offense_scores', 'points', 'success_rate', 'defense_score'];
var currentMetricIndex = 0;
var matchChart = null;

function fetchMatchCharts() {
  // Build the robot list from fetchedRobots (each object must have a "robot" property)
  var robotList = fetchedRobots.map(function(r) { return r.robot; }).join(',');
  var apiUrl = "matchcharts.php?robotList=" + encodeURIComponent(robotList);
  console.log("Match Charts API URL:", apiUrl);

  fetch(apiUrl)
    .then(function(response) { return response.json(); })
    .then(function(data) {
      // Group rows by robot and convert numeric fields.
      groupedData = {};
      data.forEach(function(row) {
        var matchRank = Number(row.match_rank);
        var offense = Number(row.offense_scores);
        var pts = Number(row.points);
        var success = Number(row.success_rate);
        var defense = Number(row.defense_score);
        if (isNaN(matchRank) || isNaN(offense)) return;
        var robot = row.robot;
        if (!groupedData[robot]) {
          groupedData[robot] = [];
        }
        groupedData[robot].push({
          x: matchRank,
          offense_scores: offense,
          points: pts,
          success_rate: success,
          defense_score: defense
        });
        console.log("Data row:", row, "X:", matchRank, "Offense:", offense);
      });
      
      // Sort each robot's data by match_rank.
      for (var robot in groupedData) {
        groupedData[robot].sort(function(a, b) { return a.x - b.x; });
      }
      
      // Build datasets using the current metric.
      var currentMetric = metrics[currentMetricIndex];
      var datasets = [];
      var colors = [
        "rgba(20,96,61,.6)",
        "rgba(20,55,96,.6)",
        "rgba(96,20,55,.6)",
        "rgba(96,61,20,.6)",
        "rgba(96,23,20,.6)",
        "rgba(33,91,159, .6)"
      ];
      var colorIndex = 0;
      for (var robot in groupedData) {
        if (Array.isArray(groupedData[robot]) && groupedData[robot].length > 0) {
          var datasetData = groupedData[robot].map(function(pt) {
            return { x: pt.x, y: pt[currentMetric] };
          });
          datasets.push({
            label: robot,
            data: datasetData,
            backgroundColor: colors[colorIndex % colors.length],
            borderColor: colors[colorIndex % colors.length],
            showLine: true,   // Connect points
            fill: false,
            tension: 0.1
          });
          colorIndex++;
        }
      }
      
      if (datasets.length === 0) {
        console.error("No valid datasets to render.");
        return;
      }
      
      // Determine x-axis labels (maximum match rank).
      var maxRank = 0;
      for (var robot in groupedData) {
        groupedData[robot].forEach(function(pt) {
          if (pt.x > maxRank) maxRank = pt.x;
        });
      }
      var labels = [];
      for (var i = 1; i <= maxRank; i++) {
        labels.push(i.toString());
      }
      
      // Get the canvas element.
      var canvas = document.getElementById("newChart");
      if (!canvas || typeof canvas.getContext !== 'function') {
        console.error("Canvas element with id 'newChart' is missing or invalid.");
        return;
      }
      var ctx = canvas.getContext("2d");
      document.getElementById("newChartContainer").style.display = 'block';
      
      // Create the scatter chart using Chart.js v2 syntax.
      matchChart = new Chart(ctx, {
        type: 'scatter',
        data: {
          labels: labels, // not really used for scatter charts but required
          datasets: datasets
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            xAxes: [{
              type: 'linear',
              position: 'bottom',
              scaleLabel: {
                display: true,
                labelString: 'Match Rank'
              },
              ticks: {
                stepSize: 1,
                beginAtZero: false
              }
            }],
            yAxes: [{
              scaleLabel: {
                display: true,
                labelString: getYAxisTitle(currentMetric)
              },
              ticks: {
                beginAtZero: true
              }
            }]
          },
          title: {
            display: true,
            text: 'Metrics by Match'//getChartTitle(currentMetric)
          },
          legend: {
            position: 'bottom'
          }
        }
      });
      
      // Add a click event listener to the canvas to cycle metrics.
      canvas.onclick = function() {
        cycleMetrics();
      };
      
      // Start cycling metrics every 8 seconds.
      setInterval(cycleMetrics, 800000);
    })
    .catch(function(error) {
      console.error("Error fetching match charts:", error);
    });
}

function getYAxisTitle(metric) {
  switch(metric) {
    case 'offense_scores': return 'Offense Score';
    case 'points': return 'Total Points';
    case 'success_rate': return 'Success Rate';
    case 'defense_score': return 'Defense Score';
    default: return metric;
  }
}

function getChartTitle(metric) {
  switch(metric) {
    case 'offense_scores': return 'Offense Scoring by Match';
    case 'points': return 'Total Points by Match';
    case 'success_rate': return 'Success Rate by Match';
    case 'defense_score': return 'Defense Scoring by Match';
    default: return metric;
  }
}

function cycleMetrics() {
  if (!matchChart) return;
  // Cycle to the next metric.
  currentMetricIndex = (currentMetricIndex + 1) % metrics.length;
  var currentMetric = metrics[currentMetricIndex];
  
  // Update each dataset's data using the new metric.
  matchChart.data.datasets.forEach(function(dataset) {
    var robotLabel = dataset.label;
    if (groupedData[robotLabel]) {
      dataset.data = groupedData[robotLabel].map(function(pt) {
        return { x: pt.x, y: pt[currentMetric] };
      });
    }
  });
  
  // Update the y-axis label and chart title.
  matchChart.options.scales.yAxes[0].scaleLabel.labelString = getYAxisTitle(currentMetric);
  matchChart.options.title.text = getChartTitle(currentMetric);
  
  // Update the chart.
  matchChart.update();
  console.log("Cycled to metric:", currentMetric);
}

document.addEventListener("DOMContentLoaded", function () {
  let lastTapTime = 0;
  let touchStartX = 0;
  let touchEndX = 0;

  document.body.addEventListener("touchstart", function (e) {
    const card = e.target.closest(".robot-card");
    if (!card) return;

    touchStartX = e.touches[0].clientX;
    console.log(`TOUCH START on ${card.id} at X: ${touchStartX}`);
  }, { passive: true });

  document.body.addEventListener("touchend", function (e) {
    const card = e.target.closest(".robot-card");
    if (!card) return;

    touchEndX = e.changedTouches[0].clientX;
    console.log(`TOUCH END on ${card.id} at X: ${touchEndX}`);

    const swipeDistance = touchEndX - touchStartX;
    console.log(`Swipe Distance: ${swipeDistance}`);

    // Get the actual computed background color
    let currentColor = window.getComputedStyle(card).backgroundColor;

    // Convert RGB to HEX
    const rgbToHex = (rgb) => {
      let [r, g, b] = rgb.match(/\d+/g).map(Number);
      return `#${((1 << 24) | (r << 16) | (g << 8) | b).toString(16).slice(1)}`;
    };

    let currentHex = currentColor.startsWith("rgb") ? rgbToHex(currentColor) : currentColor;
    console.log(`Current Color of ${card.id}: ${currentHex}`);

    // Detect swipe left or right and toggle between #666 and default
    if (Math.abs(swipeDistance) > 150) {
      if (swipeDistance < -150) { // Swipe left → Toggle to default (empty)
        if (currentHex === "#666666" || currentHex === "#333333") {
          card.style.backgroundColor = "";
          console.log(`Swiped LEFT on ${card.id} - Reset to Default`);
        }
      } else if (swipeDistance > 150) { // Swipe right → Set to #666
        if (currentHex !== "#666666") {
          card.style.backgroundColor = "#666";
          console.log(`Swiped RIGHT on ${card.id} - Changed to #666`);
        }
      }
    } else {
      // Handle Double Tap
      const currentTime = new Date().getTime();
      if (currentTime - lastTapTime < 300) {
        toggleRobotFilter(card.id);
        console.log(`Double TAP on ${card.id}`);
        e.preventDefault();
      }
      lastTapTime = currentTime;
    }
  }, { passive: false });
});


  </script>
</body>
</html>
