<?php
    require_once '../php/database_connection.php';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Fetch unique events
    $event_query = $pdo->query("SELECT DISTINCT event_name FROM scouting_submissions ORDER BY event_name ASC");
    $events = $event_query->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FRC Match Viewer with Robot Filter</title>
  <style>
    /* Base Styling */
    body {
      font-family: "Helvetica Neue", Arial, sans-serif;
      margin: 0;
      padding: 0;
      background: #222;
      color: #eee;
      line-height: 1.5;
    }
    .container {
      padding: 1rem;
      text-align: center;
      background-color: #333;
      border-bottom: 1px solid #444;
    }
    .container label,
    .container select,
    .container input {
      margin: 0.5rem;
      font-size: 1rem;
    }
    .container select,
    .container input {
      padding: 0.5rem;
      border-radius: 4px;
      border: 1px solid #555;
      background: #444;
      color: #eee;
    }
    
    /* Robot Cards Layout */
    .robot-cards {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      padding: 1rem;
      justify-content: center;
    }
    .card {
      background: #fff;
      color: #333;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.2);
      width: 100%;
      max-width: 700px;
      display: flex;
      flex-direction: column; /* default column layout */
      overflow: hidden;
    }
    @media (min-width: 800px) {
      .card {
        flex-direction: row;  /* row layout on larger screens */
      }
    }
    .robot-details {
      padding: 1rem;
      flex: 1;
    }
    .robot-details h3 {
      margin-top: 0;
    }
    .robot-details p {
      margin: 0.25rem 0;
    }
    .robot-chart {
      flex: 0 0 300px; /* fixed width on larger screens */
      height: 300px;
      padding: 1rem;
    }
    .robot-chart canvas {
      width: 100%;
      height: 100%;
    }
    /* Hide filter list display */
    #robotFilterList {
      display: none;
    }
  </style>
  <!-- Include Chart.js -->
  <script src="../js/Chart.bundle.js"></script>
  <script>
    // Global variables
    let fetchedRobots = [];  // Raw robot data from server
    let filterRobots = [];   // Array of robot numbers to exclude

    // Fetch matches based on event selection
    function fetchMatches() {
      const eventName = document.getElementById("eventDropdown").value;
      const matchDropdown = document.getElementById("matchDropdown");
      const robotContainer = document.getElementById("robotContainer");

      // Always show the dropdown; clear old matches
      matchDropdown.innerHTML = "<option value=''>-- Select Match --</option><option value='all'>All Matches</option>";
      robotContainer.innerHTML = "";

      if (!eventName) return;

      let xhr = new XMLHttpRequest();
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

    // Fetch robots based on match selection
    function fetchRobots() {
      const eventName = document.getElementById("eventDropdown").value;
      const matchNumber = document.getElementById("matchDropdown").value;
      const robotContainer = document.getElementById("robotContainer");

      robotContainer.innerHTML = "";
      if (!eventName || !matchNumber) return;

      let xhr = new XMLHttpRequest();
      console.log("Event:", eventName, "Match Number:", matchNumber);

      if (matchNumber !== 'all') {
        xhr.open("GET", "fetch_robot_data.php?event_name=" + encodeURIComponent(eventName) + "&match_number=" + encodeURIComponent(matchNumber), true);
      } else {
        xhr.open("GET", "fetch_robot_data2.php?event_name=" + encodeURIComponent(eventName) + "&match_number=" + encodeURIComponent(matchNumber), true);
      }
      xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
          console.log("Fetch Robots Raw Response:", xhr.responseText);
          try {
            let data = JSON.parse(xhr.responseText);
            if (data.error) {
              console.error("Error:", data.error);
              robotContainer.innerHTML = `<p style="color:red;">${data.error}</p>`;
              return;
            }
            let robotsArray;
            if (Array.isArray(data)) {
              robotsArray = data;
            } else if (data.robots && Array.isArray(data.robots)) {
              robotsArray = data.robots;
            } else {
              robotsArray = [data];
            }
            fetchedRobots = robotsArray;
            // Reset the filter list when new data is fetched.
            filterRobots = [];
            updateRobotCards();
            // Populate the robot toggle dropdown with unique robot numbers.
            populateRobotToggleDropdown();
          } catch (error) {
            console.error("JSON Parsing Error:", error);
            console.log("Response received:", xhr.responseText);
            robotContainer.innerHTML = `<p style="color:red;">Error processing data. Check console.</p>`;
          }
        }
      };
      xhr.send();
    }

    // Populate the "Toggle Robot" dropdown with unique robot numbers.
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

    // Toggle a robot number in the filter list.
    function toggleRobotFilter() {
      const dropdown = document.getElementById("robotToggleDropdown");
      const selected = dropdown.value;
      if (!selected) return;
      const selectedNum = parseInt(selected, 10);
      const index = filterRobots.indexOf(selectedNum);
      if (index === -1) {
        filterRobots.push(selectedNum);
      } else {
        filterRobots.splice(index, 1);
      }
      updateRobotCards();
      dropdown.value = "";
    }

    // Update displayed robot cards based on exclusion filter and sorting.
    function updateRobotCards() {
      const sortBy = document.getElementById("sortOption").value;
      let sorted = [...fetchedRobots];
      if (sortBy === "alliance") {
        sorted.sort((a, b) => a.alliance.localeCompare(b.alliance));
      } else {
        sorted.sort((a, b) => b[sortBy] - a[sortBy]);
      }
      const finalList = sorted.filter(robot => !filterRobots.includes(parseInt(robot.robot, 10)));
      displayRobotCards(finalList);
    }

    // Render robot cards with details and a simple bar chart.
    function displayRobotCards(robots) {
      const container = document.getElementById("robotContainer");
      if (!container) {
        console.error("robotContainer element not found.");
        return;
      }
      container.innerHTML = "";
      let html = "";
      robots.forEach((robot, index) => {
        html += `
          <div class="robot-card card">
            <div class="robot-details">
              <h3>Robot ${robot.robot}</h3>
              <p><strong>Alliance:</strong> ${robot.alliance}</p>
              <p>Matches Played: ${robot.match_count}</p>
              <p>Top Scoring Location: ${robot.top_scoring_location || "N/A"}</p>
              <p>Offense Score: ${robot.offense_score}</p>
              <p>Defense Score: ${robot.defense_score}</p>
              <p>Auton Score: ${robot.auton_score}</p>
              <p>Cooperative Score: ${parseFloat(robot.cooperative_score).toFixed(2)}</p>
              <p>Scoring Breakdown:</p>
              <ul>
                <li>Level 1: ${robot.count_level_1}</li>
                <li>Level 2: ${robot.count_level_2}</li>
                <li>Level 3: ${robot.count_level_3}</li>
                <li>Level 4: ${robot.count_level_4}</li>
              </ul>
            </div>
            <div class="robot-chart">
              <canvas id="chart-${index}"></canvas>
            </div>
          </div>
        `;
      });
      container.innerHTML = html;

      robots.forEach((robot, index) => {
        const canvas = document.getElementById(`chart-${index}`);
        if (!canvas) {
          console.error(`Canvas chart-${index} not found.`);
          return;
        }
        const ctx = canvas.getContext("2d");

        const offense = Number(robot.offense_score) || 0;
        const defense = Number(robot.defense_score) || 0;
        const auton = Number(robot.auton_score) || 0;
        const coop = Number(robot.cooperative_score) || 0;

        const coopColor = coop >= 0 ? "rgba(0, 128, 0, 0.6)" : "rgba(255, 0, 0, 0.6)";
        const coopBorderColor = coop >= 0 ? "rgba(0, 128, 0, 1)" : "rgba(255, 0, 0, 1)";

        const data = {
          labels: ["Offense", "Defense", "Auton", "Co-op"],
          datasets: [{
            label: "Performance",
            data: [offense, defense, auton, coop],
            backgroundColor: [
              "rgba(75, 192, 192, 0.6)",
              "rgba(153, 102, 255, 0.6)",
              "rgba(255, 205, 86, 0.6)",
              coopColor
            ],
            borderColor: [
              "rgba(75, 192, 192, 1)",
              "rgba(153, 102, 255, 1)",
              "rgba(255, 205, 86, 1)",
              coopBorderColor
            ],
            borderWidth: 1
          }]
        };

        const config = {
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
        };

        new Chart(ctx, config);
      });
    }
  </script>
</head>
<body>
  <div class="container">
      <!-- Event Selection -->
      <label><strong>Select an Event:</strong></label>
      <select id="eventDropdown" onchange="fetchMatches()">
          <option value="">-- Select Event --</option>
          <?php foreach ($events as $event) { ?>
              <option value="<?php echo htmlspecialchars($event['event_name']); ?>">
                  <?php echo htmlspecialchars($event['event_name']); ?>
              </option>
          <?php } ?>
      </select>

      <!-- Match Selection (always visible) -->
      <label><strong>Select a Match:</strong></label>
      <select id="matchDropdown" onchange="fetchRobots()">
          <option value="">-- Select Match --</option>
          <option value="all">All Matches</option>
      </select>
<br>
      <!-- Sort Dropdown (default sort is Alliance) -->
      <label for="sortOption">Sort by:</label>
      <select id="sortOption" onchange="updateRobotCards()">
          <option value="alliance" selected>Alliance</option>
          <option value="offense_score">Offense Score</option>
          <option value="defense_score">Defense Score</option>
          <option value="cooperative_score">Cooperative Score</option>
      </select>

      <!-- Robot Toggle Dropdown (for exclusion filter) -->
      <label for="robotToggleDropdown">Toggle Robot (Exclude):</label>
      <select id="robotToggleDropdown" onchange="toggleRobotFilter()">
          <option value="">-- Select Robot --</option>
      </select>

      <!-- Hidden filter list -->
      <input type="text" id="robotFilterList" readonly placeholder="Filter list" />
  </div>

  <div id="robotContainer" class="robot-cards">
      <!-
