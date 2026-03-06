<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manual Robot Chart with Legend (Fixed Size)</title>
  <!-- Include Chart.js v2 -->
  <script src="../js/Chart.bundle.js"></script>
  <style>
    body {
      font-family: sans-serif;
      margin: 2rem;
      background: #222;
      color:#fff;
    }
    .input-container {
      margin-bottom: 1rem;
    }
    label {
      font-weight: bold;
    }
    input[type="text"] {
      padding: 0.5rem;
      width: 300px;
    }
    button {
      padding: 0.5rem 1rem;
    }
    /* Set fixed dimensions for the canvas */
    #manualChart {
      width: 95vw !important;
      height: 75vh !important;
      background: #ccc;
      border: 1px solid #666;
      margin-top: 1rem;
    }
    .toggle-container {
      margin-top: 1rem;
    }
    .toggle-button {
      display: inline-block;
      margin-right: 0.5rem;
      padding: 0.5rem 1rem;
      background-color: #eee;
      border: 1px solid #ccc;
      border-radius: 4px;
      cursor: pointer;
    }
    .toggle-button.active {
      background-color: #666;
      color: #fff;
    }
  </style>
</head>
<body>
  <div class="input-container">
    <label for="robotInput">Enter Robot Numbers (comma separated):</label>
    <input type="text" id="robotInput" placeholder="e.g., 101,202,303">
    <button id="generateChartBtn">Generate Chart</button>
  </div>
  
  <canvas id="manualChart" width="800" height="400"></canvas>
  
  <div class="toggle-container" id="toggleContainer"></div>
  
  <script>
    // Global variables
    let manualChart; // Chart.js instance
    let chartDatasets = {}; // Maps robot number to dataset index
    let metrics = ['points', 'offense', 'successRate', 'defense'];
    let currentMetricIndex = 0;
    let groupedDataGlobal = {}; // Stores grouped data keyed by robot

    // Fetch data using the manually entered robot list and create the chart.
    function fetchManualChart() {
      const robotList = document.getElementById('robotInput').value;
      if (!robotList.trim()){
        alert("Please enter some robot numbers");
        return;
      }
      const url = "matchcharts.php?robotList=" + encodeURIComponent(robotList);
      fetch(url)
        .then(response => response.json())
        .then(data => {
          console.log("API Response:", data);
          if (data.error) {
            alert("Error: " + data.error);
            return;
          }
          // Group data by robot
          const groupedData = {};
          data.forEach(row => {
            const robot = row.robot;
            const matchRank = Number(row.match_rank);
            const points = Number(row.points);
            const offense = Number(row.offense_scores);
            const successRate = Number(row.success_rate);
            const defense = Number(row.defense_score);
            if (!groupedData[robot]) {
              groupedData[robot] = [];
            }
            groupedData[robot].push({
              x: matchRank,
              points: points,
              offense: offense,
              successRate: successRate,
              defense: defense
            });
          });
          
          // Save globally for metric cycling
          groupedDataGlobal = groupedData;
          
          // Sort each robot's data by match rank
          for (let robot in groupedData) {
            groupedData[robot].sort((a, b) => a.x - b.x);
          }
          
          // Build datasets for the initial metric "points"
          const datasets = [];
          const colors = [
            "rgba(20,96,61,0.8)",
            "rgba(20,55,96,0.8)",
            "rgba(96,20,55,0.8)",
            "rgba(96,61,20,0.8)",
            "rgba(96,23,20,0.8)",
            "rgba(33,91,159,0.8)"
          ];
          let colorIndex = 0;
          chartDatasets = {}; // Reset mapping
          for (let robot in groupedData) {
            const dataset = {
              label: robot,
              data: groupedData[robot].map(pt => ({ x: pt.x, y: pt.points })),
              backgroundColor: colors[colorIndex % colors.length],
              borderColor: colors[colorIndex % colors.length],
              showLine: true, // Connect data points
              fill: false,
              hidden: false
            };
            datasets.push(dataset);
            chartDatasets[robot] = datasets.length - 1;
            colorIndex++;
          }
          
          // Destroy existing chart if any
          if (manualChart) {
            manualChart.destroy();
          }
          
          const ctx = document.getElementById("manualChart").getContext("2d");
          manualChart = new Chart(ctx, {
            type: 'scatter',
            data: {
              datasets: datasets
            },
            options: {
              responsive: false, // Disable responsiveness to keep fixed size
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
                    labelString: 'Points'
                  },
                  ticks: {
                    beginAtZero: true
                  }
                }]
              },
              title: {
                display: true,
                text: 'Metrics by Match'
              },
              legend: {
                display: true,
                position: 'top'
              },
              // Cycle through metrics on chart click
              onClick: function(evt, activeElements) {
                cycleMetrics();
              }
            }
          });
          
          // Generate toggle buttons for each robot (optional if you use built-in legend)
          generateToggleButtons(Object.keys(groupedData));
        })
        .catch(error => {
          console.error("Error fetching chart data:", error);
          alert("An error occurred while fetching the chart data.");
        });
    }

    // Generate a toggle button for each robot dataset.
    function generateToggleButtons(robotList) {
      const container = document.getElementById("toggleContainer");
      container.innerHTML = ""; // Clear previous buttons
      robotList.forEach(robot => {
        const btn = document.createElement("div");
        btn.className = "toggle-button active";  // Active by default
        btn.id = "toggle_" + robot;
        btn.textContent = "Robot " + robot;
        btn.onclick = function() {
          toggleRobotDataset(robot);
        };
        container.appendChild(btn);
      });
    }

    // Toggle the visibility of a given robot's dataset.
    function toggleRobotDataset(robot) {
      if (!manualChart) return;
      const datasetIndex = chartDatasets[robot];
      const dataset = manualChart.data.datasets[datasetIndex];
      dataset.hidden = !dataset.hidden;
      manualChart.update();
      const btn = document.getElementById("toggle_" + robot);
      if (btn) {
        if (dataset.hidden) {
          btn.classList.remove("active");
        } else {
          btn.classList.add("active");
        }
      }
    }

    // Cycle through metrics (points, offense, successRate, defense)
    function cycleMetrics() {
      currentMetricIndex = (currentMetricIndex + 1) % metrics.length;
      const currentMetric = metrics[currentMetricIndex];
      let yLabel;
      switch (currentMetric) {
        case 'points': yLabel = 'Points'; break;
        case 'offense': yLabel = 'Offense Score'; break;
        case 'successRate': yLabel = 'Success Rate'; break;
        case 'defense': yLabel = 'Defense Score'; break;
        default: yLabel = currentMetric;
      }
      // Update each dataset's data based on the new metric (if visible)
      manualChart.data.datasets.forEach(dataset => {
        const robot = dataset.label;
        if (!dataset.hidden && groupedDataGlobal[robot]) {
          dataset.data = groupedDataGlobal[robot].map(pt => ({ x: pt.x, y: pt[currentMetric] }));
        }
      });
      // Update Y-axis label and chart title
      manualChart.options.scales.yAxes[0].scaleLabel.labelString = yLabel;
      manualChart.options.title.text = yLabel + " by Match Rank";
      manualChart.update();
    }

    // Bind the Generate Chart button
    document.getElementById('generateChartBtn').addEventListener('click', fetchManualChart);
    // Also allow cycling metrics by clicking on the canvas
    document.getElementById("manualChart").onclick = function() {
      cycleMetrics();
    };
  </script>
</body>
</html>
