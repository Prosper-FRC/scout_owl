<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../php/database_connection.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

/*
|--------------------------------------------------------------------------
| AJAX ENDPOINTS
|--------------------------------------------------------------------------
*/
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $ajax = $_GET['ajax'];

        if ($ajax === 'matches') {
            $eventName = trim($_GET['event_name'] ?? '');
            if ($eventName === '') {
                echo json_encode([]);
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT DISTINCT match_no AS match_number
                FROM scouting_submissions
                WHERE event_name = :event_name
                  AND match_no IS NOT NULL
                  AND match_no <> ''
                ORDER BY CAST(match_no AS UNSIGNED) ASC
            ");
            $stmt->execute(['event_name' => $eventName]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }

        if ($ajax === 'robots') {
            $eventName = trim($_GET['event_name'] ?? '');
            $matchNo   = trim($_GET['match_number'] ?? '');

            if ($eventName === '' || $matchNo === '') {
                echo json_encode(['error' => 'Missing event_name or match_number']);
                exit;
            }

            $sql = "
                SELECT
                    rm.robot,
                    rm.alliance,
                    COALESCE(es.match_count, 0) AS match_count,
                    ROUND(COALESCE(es.avg_points, 0), 2) AS avg_points,
                    ROUND(COALESCE(es.success_rate, 0), 1) AS success_rate,
                    ROUND(COALESCE(es.avg_success_actions, 0), 2) AS avg_success_actions,
                    ROUND(COALESCE(es.avg_total_actions, 0), 2) AS avg_total_actions,
                    ROUND(
                        CASE
                            WHEN COALESCE(es.avg_success_actions, 0) > 0 THEN 150 / es.avg_success_actions
                            ELSE 0
                        END
                    , 2) AS avg_cycle_time,
                    ROUND(COALESCE(es.auton_actions, 0), 2) AS auton_actions,
                    ROUND(COALESCE(es.defense_actions, 0), 2) AS defense_actions,
                    COALESCE(hs.high_score, 0) AS high_score,
                    COALESCE(ta.top_action, 'N/A') AS top_action
                FROM
                (
                    SELECT
                        s.robot,
                        CASE
                            WHEN LOWER(MAX(s.alliance)) = 'blue' THEN 'Blue'
                            WHEN LOWER(MAX(s.alliance)) = 'red' THEN 'Red'
                            ELSE COALESCE(MAX(s.alliance), 'Unknown')
                        END AS alliance
                    FROM scouting_submissions s
                    WHERE s.event_name = :event_name_match
                      AND s.match_no = :match_no
                    GROUP BY s.robot
                ) rm
                LEFT JOIN
                (
                    SELECT
                        robot,
                        COUNT(DISTINCT match_no) AS match_count,
                        COALESCE(SUM(points) / NULLIF(COUNT(DISTINCT match_no), 0), 0) AS avg_points,
                        COALESCE(SUM(CASE WHEN result = 'success' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0), 0) AS success_rate,
                        COALESCE(SUM(CASE WHEN result = 'success' AND points > 0 THEN 1 ELSE 0 END) / NULLIF(COUNT(DISTINCT match_no), 0), 0) AS avg_success_actions,
                        COALESCE(COUNT(*) / NULLIF(COUNT(DISTINCT match_no), 0), 0) AS avg_total_actions,
                        COALESCE(SUM(CASE WHEN time_sec <= 15 AND result = 'success' AND points > 0 THEN 1 ELSE 0 END) / NULLIF(COUNT(DISTINCT match_no), 0), 0) AS auton_actions,
                        COALESCE(SUM(CASE WHEN LOWER(action) LIKE '%def%' THEN 1 ELSE 0 END) / NULLIF(COUNT(DISTINCT match_no), 0), 0) AS defense_actions
                    FROM scouting_submissions
                    WHERE event_name = :event_name_summary
                    GROUP BY robot
                ) es
                    ON rm.robot = es.robot
                LEFT JOIN
                (
                    SELECT robot, MAX(match_points) AS high_score
                    FROM
                    (
                        SELECT robot, match_no, SUM(points) AS match_points
                        FROM scouting_submissions
                        WHERE event_name = :event_name_high
                        GROUP BY robot, match_no
                    ) t
                    GROUP BY robot
                ) hs
                    ON rm.robot = hs.robot
                LEFT JOIN
                (
                    SELECT
                        x.robot,
                        SUBSTRING_INDEX(
                            GROUP_CONCAT(x.action ORDER BY x.action_count DESC, x.action ASC SEPARATOR ','),
                            ',',
                            1
                        ) AS top_action
                    FROM
                    (
                        SELECT robot, action, COUNT(*) AS action_count
                        FROM scouting_submissions
                        WHERE event_name = :event_name_action
                          AND result = 'success'
                          AND points > 0
                        GROUP BY robot, action
                    ) x
                    GROUP BY x.robot
                ) ta
                    ON rm.robot = ta.robot
                ORDER BY
                    CASE
                        WHEN rm.alliance = 'Blue' THEN 1
                        WHEN rm.alliance = 'Red' THEN 2
                        ELSE 3
                    END,
                    CAST(rm.robot AS UNSIGNED) ASC
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'event_name_match'   => $eventName,
                'match_no'           => $matchNo,
                'event_name_summary' => $eventName,
                'event_name_high'    => $eventName,
                'event_name_action'  => $eventName
            ]);

            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }

        if ($ajax === 'trend') {
            $eventName = trim($_GET['event_name'] ?? '');
            $robotList = trim($_GET['robot_list'] ?? '');

            if ($eventName === '' || $robotList === '') {
                echo json_encode([]);
                exit;
            }

            $robots = array_filter(array_map('trim', explode(',', $robotList)), fn($v) => $v !== '');
            if (empty($robots)) {
                echo json_encode([]);
                exit;
            }

            $placeholders = implode(',', array_fill(0, count($robots), '?'));

            $sql = "
                SELECT
                    robot,
                    match_no,
                    SUM(points) AS points,
                    COALESCE(SUM(CASE WHEN result = 'success' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0), 0) AS success_rate,
                    SUM(CASE WHEN LOWER(action) LIKE '%def%' THEN 1 ELSE 0 END) AS defense_actions
                FROM scouting_submissions
                WHERE event_name = ?
                  AND robot IN ($placeholders)
                GROUP BY robot, match_no
                ORDER BY CAST(match_no AS UNSIGNED) ASC, CAST(robot AS UNSIGNED) ASC
            ";

            $params = array_merge([$eventName], $robots);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }

        echo json_encode(['error' => 'Invalid AJAX action']);
        exit;

    } catch (Throwable $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| INITIAL PAGE LOAD
|--------------------------------------------------------------------------
*/
try {
    $eventQuery = $pdo->query("
        SELECT DISTINCT event_name
        FROM scouting_submissions
        WHERE event_name IS NOT NULL
          AND event_name <> ''
        ORDER BY event_name ASC
    ");
    $events = $eventQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error fetching events: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Match Prediction</title>
    <link rel="stylesheet" href="../css/select.css">
    <script src="../js/Chart.bundle.js"></script>
    <style>
        @font-face {
            font-family: 'Comfortaa';
            src: url('/../stat_goblin/fonts/Comfortaa/Comfortaa-VariableFont_wght.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        html, body {
            margin: 0;
            padding: 0;
            font-family: 'Comfortaa', sans-serif;
            background: #222;
            color: #eee;
        }

        .containerOuter {
            background: #333;
            border-bottom: 1px solid #444;
            width: 100%;
            padding: 1rem;
            box-sizing: border-box;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        .logo {
            width: 360px;
            display: block;
            margin: 0 auto 1rem auto;
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .grid-item {
            display: flex;
            flex-direction: column;
        }

        .grid-item label {
            margin-bottom: 0.35rem;
            font-weight: bold;
        }

        .grid-item select {
            padding: 0.65rem;
            border-radius: 6px;
            border: 1px solid #555;
            background: #444;
            color: #eee;
            font-size: 1rem;
            width: 100%;
            box-sizing: border-box;
        }

        .card {
            background: #fff;
            color: #333;
            border-radius: 10px;
            width: calc(100% - 2rem);
            max-width: 1100px;
            margin: 1rem auto;
            padding: 1rem;
            box-sizing: border-box;
            overflow-x: auto;
        }

.prediction-card {
    border: none;
    background: #fff;
    color: #000;
}

        #predictionHeader {
            padding: 0.5rem 0;
            font-size: 1rem;
            line-height: 1.7;
        }

        .predictTable {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0 1.5rem 0;
            font-size: 0.9rem;
            box-shadow: 0 0 14px rgba(0,0,0,0.12);
        }

        .predictTable thead tr {
            background: #9A7E6F;
            color: #fff;
        }

        .predictTable th, .predictTable td {
            padding: 8px 10px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }

        .predictTable tbody tr:nth-child(even) {
            background: #f3f3f3;
        }

        .robot-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            padding: 1rem;
            justify-content: center;
        }

        .robot-card {
            width: 100%;
            max-width: 520px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .stat-box {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 0.75rem;
            text-align: center;
            background: #fafafa;
        }

        .stat-title {
            display: block;
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 0.25rem;
        }

        .stat-value {
            font-size: 1rem;
            font-weight: bold;
        }

        .robot-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .alliance-blue {
            color: #0b63ce;
            font-weight: bold;
        }

        .alliance-red {
            color: #c73333;
            font-weight: bold;
        }

        .alliance-unknown {
            color: #777;
            font-weight: bold;
        }

        .chart-wrap {
            width: 100%;
            height: 240px;
            margin-top: 1rem;
        }

        #matchTrendContainer {
            display: none;
        }

        #matchTrendChart {
            width: 100%;
            height: 420px;
        }

        .loader {
            width: 85px;
            height: 50px;
            --g1: conic-gradient(from 90deg at left 3px top 3px,#0000 90deg,#fff 0);
            --g2: conic-gradient(from -90deg at bottom 3px right 3px,#0000 90deg,#fff 0);
            background: var(--g1),var(--g1),var(--g1), var(--g2),var(--g2),var(--g2);
            background-position: left,center,right;
            background-repeat: no-repeat;
            animation: l10 1s infinite alternate;
            margin: 1rem auto;
        }

        @keyframes l10 {
            0%, 2%   { background-size:25px 50%,25px 50%,25px 50% }
            20%      { background-size:25px 25%,25px 50%,25px 50% }
            40%      { background-size:25px 100%,25px 25%,25px 50% }
            60%      { background-size:25px 50%,25px 100%,25px 25% }
            80%      { background-size:25px 50%,25px 50%,25px 100% }
            98%,100% { background-size:25px 50%,25px 50%,25px 50% }
        }

        .loader2 {
            color: #fff;
            font-weight: bold;
            font-family: monospace;
            display: inline-grid;
            font-size: 1.05rem;
            margin: auto;
            text-align: center;
            width: 100%;
        }

        .loader2:before,
        .loader2:after {
            content: "Processing Match Prediction...";
            grid-area: 1/1;
            -webkit-mask-size: 2ch 100%,100% 100%;
            -webkit-mask-repeat: no-repeat;
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            animation: l37 1s infinite;
        }

        .loader2:before {
            -webkit-mask-image: linear-gradient(#000 0 0), linear-gradient(#000 0 0);
        }

        .loader2:after {
            -webkit-mask-image: linear-gradient(#000 0 0);
            transform: scaleY(0.5);
        }

        @keyframes l37 {
            0%    {-webkit-mask-position:1ch 0,0 0}
            12.5% {-webkit-mask-position:100% 0,0 0}
            25%   {-webkit-mask-position:4ch 0,0 0}
            37.5% {-webkit-mask-position:8ch 0,0 0}
            50%   {-webkit-mask-position:2ch 0,0 0}
            62.5% {-webkit-mask-position:100% 0,0 0}
            75%   {-webkit-mask-position:0ch 0,0 0}
            87.5% {-webkit-mask-position:6ch 0,0 0}
            100%  {-webkit-mask-position:3ch 0,0 0}
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

        .blue-win { animation: blueWinAnim 2s; }
        .red-win  { animation: redWinAnim 2s; }
        .tie-win  { animation: tieWinAnim 2s; }

        @media (max-width: 900px) {
            .grid-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 640px) {
            .grid-container,
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .logo {
                width: 260px;
            }
        }
    </style>
</head>
<body>
    <div class="containerOuter">
        <div class="container">
            <a href="."><img src="../images/owlAnalytics.png" class="logo" alt="Logo"></a>

            <div class="grid-container">
                <div class="grid-item">
                    <label for="eventDropdown">Select an Event:</label>
                    <select id="eventDropdown">
                        <option value="">-- Select Event --</option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?php echo htmlspecialchars($event['event_name']); ?>">
                                <?php echo htmlspecialchars($event['event_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid-item">
                    <label for="matchDropdown">Select a Match:</label>
                    <select id="matchDropdown">
                        <option value="">-- Select Match --</option>
                    </select>
                </div>

                <div class="grid-item">
                    <label for="sortOption">Sort Robot Cards By:</label>
                    <select id="sortOption">
                        <option value="alliance">Alliance</option>
                        <option value="avg_points">Avg Points</option>
                        <option value="success_rate">Success Rate</option>
                        <option value="avg_cycle_time">Cycle Time</option>
                        <option value="defense_actions">Defense Actions</option>
                        <option value="high_score">High Score</option>
                    </select>
                </div>

                <div class="grid-item">
                    <label for="robotToggleDropdown">Robot (Hide/Show):</label>
                    <select id="robotToggleDropdown">
                        <option value="">-- Select Robot --</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div id="matchTrendContainer" class="card">
        <canvas id="matchTrendChart"></canvas>
    </div>

    <div id="predictionCard" class="card prediction-card">
        <div id="predictionHeader"></div>
        <div id="predictionResult"></div>
        <div id="predictionCharts"></div>
    </div>

    <div id="robotContainer" class="robot-cards"></div>

    <script>
        let fetchedRobots = [];
        let aggregatedData = {};
        let hiddenRobots = [];
        let trendChart = null;

        function allianceClass(alliance) {
            const a = String(alliance || '').toLowerCase();
            if (a === 'blue') return 'alliance-blue';
            if (a === 'red') return 'alliance-red';
            return 'alliance-unknown';
        }

        function fetchMatches() {
            const eventName = document.getElementById('eventDropdown').value;
            const matchDropdown = document.getElementById('matchDropdown');
            const robotContainer = document.getElementById('robotContainer');

            matchDropdown.innerHTML = "<option value=''>-- Select Match --</option>";
            robotContainer.innerHTML = '';
            resetPredictionCard();
            hideTrendChart();

            if (!eventName) return;

            fetch(`match_prediction.php?ajax=matches&event_name=${encodeURIComponent(eventName)}`)
                .then(r => r.json())
                .then(data => {
                    data.forEach(match => {
                        const option = document.createElement('option');
                        option.value = match.match_number;
                        option.textContent = 'Match ' + match.match_number;
                        matchDropdown.appendChild(option);
                    });
                })
                .catch(err => {
                    console.error('Error fetching matches:', err);
                });
        }

        function fetchRobotCards() {
            const eventName = document.getElementById('eventDropdown').value;
            const matchNumber = document.getElementById('matchDropdown').value;
            const robotContainer = document.getElementById('robotContainer');

            robotContainer.innerHTML = '';
            if (!eventName || !matchNumber) return;

            fetch(`match_prediction.php?ajax=robots&event_name=${encodeURIComponent(eventName)}&match_number=${encodeURIComponent(matchNumber)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        robotContainer.innerHTML = `<div class="card"><span style="color:red;">${data.error}</span></div>`;
                        return;
                    }

                    fetchedRobots = Array.isArray(data) ? data : [];
                    hiddenRobots = [];
                    populateRobotToggleDropdown();
                    updateRobotCards();
                    fetchPrediction();
                    fetchTrendChart();
                })
                .catch(err => {
                    console.error('Error fetching robots:', err);
                    robotContainer.innerHTML = `<div class="card"><span style="color:red;">Error loading robot data.</span></div>`;
                });
        }

        function populateRobotToggleDropdown() {
            const dropdown = document.getElementById('robotToggleDropdown');
            dropdown.innerHTML = "<option value=''>-- Select Robot --</option>";

            const uniqueRobots = [...new Set(fetchedRobots.map(r => String(r.robot).trim()))];
            uniqueRobots.forEach(robotNum => {
                const option = document.createElement('option');
                option.value = robotNum;
                option.textContent = robotNum;
                dropdown.appendChild(option);
            });
        }

        function toggleRobotFilter(robotId) {
            const targetId = robotId || document.getElementById('robotToggleDropdown').value.trim();
            if (!targetId) return;

            const idx = hiddenRobots.indexOf(targetId);
            if (idx === -1) {
                hiddenRobots.push(targetId);
            } else {
                hiddenRobots.splice(idx, 1);
            }

            updateRobotCards();

            if (!robotId) {
                document.getElementById('robotToggleDropdown').value = '';
            }
        }

        function updateRobotCards() {
            const sortBy = document.getElementById('sortOption').value;
            let sorted = [...fetchedRobots];

            if (sortBy === 'alliance') {
                const order = { 'Blue': 1, 'Red': 2, 'Unknown': 3 };
                sorted.sort((a, b) => (order[a.alliance] || 99) - (order[b.alliance] || 99) || (Number(a.robot) - Number(b.robot)));
            } else if (sortBy === 'avg_cycle_time') {
                sorted.sort((a, b) => (a[sortBy] || 9999) - (b[sortBy] || 9999));
            } else {
                sorted.sort((a, b) => (Number(b[sortBy]) || 0) - (Number(a[sortBy]) || 0));
            }

            const finalList = sorted.filter(robot => !hiddenRobots.includes(String(robot.robot)));
            displayRobotCards(finalList);
        }

        function displayRobotCards(robots) {
            const container = document.getElementById('robotContainer');
            let html = '';

            if (!robots.length) {
                container.innerHTML = '<div class="card">No robots to display.</div>';
                return;
            }

            robots.forEach(robot => {
                html += `
                    <div class="robot-card card" id="robot_${robot.robot}">
                        <div class="robot-header">
                            <h3>Robot ${robot.robot}</h3>
                            <div class="${allianceClass(robot.alliance)}">${robot.alliance || 'Unknown'}</div>
                        </div>

                        <div class="stats-grid">
                            <div class="stat-box">
                                <span class="stat-title">Matches Played</span>
                                <span class="stat-value">${robot.match_count || 0}</span>
                            </div>
                            <div class="stat-box">
                                <span class="stat-title">Avg Points / Match</span>
                                <span class="stat-value">${Number(robot.avg_points || 0).toFixed(2)}</span>
                            </div>
                            <div class="stat-box">
                                <span class="stat-title">Success Rate</span>
                                <span class="stat-value">${Number(robot.success_rate || 0).toFixed(1)}%</span>
                            </div>
                            <div class="stat-box">
                                <span class="stat-title">Avg Cycle Time</span>
                                <span class="stat-value">${Number(robot.avg_cycle_time || 0).toFixed(2)} sec</span>
                            </div>
                            <div class="stat-box">
                                <span class="stat-title">Auton Actions / Match</span>
                                <span class="stat-value">${Number(robot.auton_actions || 0).toFixed(2)}</span>
                            </div>
                            <div class="stat-box">
                                <span class="stat-title">Defense Actions / Match</span>
                                <span class="stat-value">${Number(robot.defense_actions || 0).toFixed(2)}</span>
                            </div>
                            <div class="stat-box">
                                <span class="stat-title">High Score</span>
                                <span class="stat-value">${robot.high_score || 0}</span>
                            </div>
                            <div class="stat-box">
                                <span class="stat-title">Favorite Scoring Action</span>
                                <span class="stat-value">${robot.top_action || 'N/A'}</span>
                            </div>
                        </div>

                        <div class="chart-wrap">
                            <canvas id="chart_${robot.robot}"></canvas>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;

            robots.forEach(robot => {
                const canvas = document.getElementById(`chart_${robot.robot}`);
                if (!canvas) return;

                const ctx = canvas.getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['Avg Pts', 'Success %', 'Auton', 'Defense'],
                        datasets: [{
                            data: [
                                Number(robot.avg_points) || 0,
                                Number(robot.success_rate) || 0,
                                Number(robot.auton_actions) || 0,
                                Number(robot.defense_actions) || 0
                            ],
                            backgroundColor: [
                                'rgba(20, 61, 96, 0.6)',
                                'rgba(39, 102, 123, 0.6)',
                                'rgba(160, 200, 120, 0.6)',
                                'rgba(190, 49, 68, 0.6)'
                            ],
                            borderColor: [
                                'rgb(20, 61, 96)',
                                'rgb(39, 102, 123)',
                                'rgb(160, 200, 120)',
                                'rgb(190, 49, 68)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        legend: { display: false },
                        scales: {
                            yAxes: [{
                                ticks: { beginAtZero: true }
                            }]
                        }
                    }
                });
            });
        }

        function showPredictionLoading() {
            const predictionContainer = document.getElementById('predictionCard');
            predictionContainer.style.display = 'block';
            predictionContainer.style.backgroundColor = '#222';
            document.getElementById('predictionHeader').innerHTML = '';
            document.getElementById('predictionResult').innerHTML = '';
            document.getElementById('predictionCharts').innerHTML = `<div class="loader"></div><div class="loader2"></div>`;
        }

        function resetPredictionCard() {
            document.getElementById('predictionCard').style.display = 'block';
            document.getElementById('predictionCard').style.backgroundColor = '#222';
            document.getElementById('predictionHeader').innerHTML = '';
            document.getElementById('predictionResult').innerHTML = '';
            document.getElementById('predictionCharts').innerHTML = '';
            aggregatedData = {};
        }

        function fetchPrediction() {
            const eventName = document.getElementById('eventDropdown').value;
            const matchNumber = document.getElementById('matchDropdown').value;

            if (!eventName || !matchNumber) {
                resetPredictionCard();
                return;
            }

            const blueAlliance = [];
            const redAlliance = [];

            fetchedRobots.forEach(robot => {
                if ((robot.alliance || '').toLowerCase() === 'blue') {
                    blueAlliance.push(String(robot.robot).trim());
                } else if ((robot.alliance || '').toLowerCase() === 'red') {
                    redAlliance.push(String(robot.robot).trim());
                }
            });

            if (blueAlliance.length !== 3 || redAlliance.length !== 3) {
                document.getElementById('predictionHeader').innerHTML = '';
                document.getElementById('predictionResult').innerHTML = `<span style="color:#ffb3b3;">Prediction requires 3 blue robots and 3 red robots.</span>`;
                document.getElementById('predictionCharts').innerHTML = '';
                return;
            }

            showPredictionLoading();

            const histWeight = 0.5;
            const apiUrl =
                `https://theconspiracyshirtcompany.com/predict/` +
                `?event_name=${encodeURIComponent(eventName)}` +
                `&match_no=${encodeURIComponent(matchNumber)}` +
                `&blue_alliance=${encodeURIComponent(blueAlliance.join(','))}` +
                `&red_alliance=${encodeURIComponent(redAlliance.join(','))}` +
                `&hist_weight=${encodeURIComponent(histWeight)}`;

            fetch(apiUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    aggregatedData = data;
                    updatePredictionDisplay();
                })
                .catch(error => {
                    console.error('Prediction error:', error);
                    document.getElementById('predictionHeader').innerHTML = '';
                    document.getElementById('predictionResult').innerHTML = `<span style="color:#ffb3b3;">Prediction Error: ${error.message}</span>`;
                    document.getElementById('predictionCharts').innerHTML = '';
                });
        }

        function updatePredictionDisplay() {
            const blueStats = Array.isArray(aggregatedData.blue_stats) ? aggregatedData.blue_stats : [];
            const redStats  = Array.isArray(aggregatedData.red_stats) ? aggregatedData.red_stats : [];

            let headerHtml = '';
            headerHtml += `<strong>Event:</strong> ${aggregatedData.event_name || ''}<br>`;
            headerHtml += `<strong>Match No:</strong> ${aggregatedData.match_no || ''}<br>`;
            headerHtml += `<strong>Blue Alliance Score:</strong> ${aggregatedData.blue_score ?? ''}<br>`;
            headerHtml += `<strong>Red Alliance Score:</strong> ${aggregatedData.red_score ?? ''}<br>`;
            headerHtml += `<strong>Predicted Winner:</strong> ${aggregatedData.predicted_winner || ''}<br><br>`;

            const headerEl = document.getElementById('predictionHeader');
            headerEl.innerHTML = headerHtml;
            headerEl.classList.remove('blue-win', 'red-win', 'tie-win');

            const predictedWinner = String(aggregatedData.predicted_winner || '').toLowerCase();
            if (predictedWinner.includes('blue')) {
                headerEl.classList.add('blue-win');
            } else if (predictedWinner.includes('red')) {
                headerEl.classList.add('red-win');
            } else {
                headerEl.classList.add('tie-win');
            }

            let chartsHtml = '';

            chartsHtml += `
                <h2 style="color:#111;">Blue Alliance Stats</h2>
                <table class="predictTable">
                    <thead>
                        <tr>
                            <th>Robot</th>
                            <th>Matches</th>
                            <th>Avg PPM</th>
                            <th>Next Points</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            blueStats.forEach(stat => {
                chartsHtml += `
                    <tr>
                        <td>${stat.robot ?? ''}</td>
                        <td>${stat.matches ?? ''}</td>
                        <td>${stat.avg_points_per_match ?? ''}</td>
                        <td>${stat.predicted_next_points ?? ''}</td>
                    </tr>
                `;
            });
            chartsHtml += `</tbody></table>`;

            chartsHtml += `
                <h2 style="color:#111;">Red Alliance Stats</h2>
                <table class="predictTable">
                    <thead>
                        <tr>
                            <th>Robot</th>
                            <th>Matches</th>
                            <th>Avg PPM</th>
                            <th>Next Points</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            redStats.forEach(stat => {
                chartsHtml += `
                    <tr>
                        <td>${stat.robot ?? ''}</td>
                        <td>${stat.matches ?? ''}</td>
                        <td>${stat.avg_points_per_match ?? ''}</td>
                        <td>${stat.predicted_next_points ?? ''}</td>
                    </tr>
                `;
            });
            chartsHtml += `</tbody></table>`;

            chartsHtml += `<div style="margin-bottom:1rem;"><h3 style="color:#111;">Blue Alliance: Success Rate Slope</h3><canvas id="blueSuccessChart" style="width:100%; height:300px;"></canvas></div>`;
            chartsHtml += `<div style="margin-bottom:1rem;"><h3 style="color:#111;">Blue Alliance: Total Events Slope</h3><canvas id="blueEventsChart" style="width:100%; height:300px;"></canvas></div>`;
            chartsHtml += `<div style="margin-bottom:1rem;"><h3 style="color:#111;">Blue Alliance: Points Slope</h3><canvas id="bluePointsChart" style="width:100%; height:300px;"></canvas></div>`;
            chartsHtml += `<div style="margin-bottom:1rem;"><h3 style="color:#111;">Red Alliance: Success Rate Slope</h3><canvas id="redSuccessChart" style="width:100%; height:300px;"></canvas></div>`;
            chartsHtml += `<div style="margin-bottom:1rem;"><h3 style="color:#111;">Red Alliance: Total Events Slope</h3><canvas id="redEventsChart" style="width:100%; height:300px;"></canvas></div>`;
            chartsHtml += `<div style="margin-bottom:1rem;"><h3 style="color:#111;">Red Alliance: Points Slope</h3><canvas id="redPointsChart" style="width:100%; height:300px;"></canvas></div>`;

            document.getElementById('predictionCard').style.backgroundColor = '#fff';
            document.getElementById('predictionCharts').innerHTML = chartsHtml;

            buildHorizontalBarChart('blueSuccessChart', blueStats, 'success_rate_slope', 'Success Rate Slope', 'rgb(41, 37, 44)');
            buildHorizontalBarChart('blueEventsChart', blueStats, 'total_events_slope', 'Total Events Slope', 'rgb(216, 233, 240)');
            buildHorizontalBarChart('bluePointsChart', blueStats, 'points_slope', 'Points Slope', 'rgb(51, 66, 91)');
            buildHorizontalBarChart('redSuccessChart', redStats, 'success_rate_slope', 'Success Rate Slope', 'rgb(135, 35, 65)');
            buildHorizontalBarChart('redEventsChart', redStats, 'total_events_slope', 'Total Events Slope', 'rgb(190, 49, 68)');
            buildHorizontalBarChart('redPointsChart', redStats, 'points_slope', 'Points Slope', 'rgb(225, 117, 100)');
        }

        function buildHorizontalBarChart(canvasId, rows, field, label, color) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;

            const labels = rows.map(r => r.robot);
            const values = rows.map(r => Number(r[field] || 0));

            new Chart(canvas.getContext('2d'), {
                type: 'horizontalBar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: label,
                        data: values,
                        backgroundColor: color
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        xAxes: [{ ticks: { beginAtZero: true } }]
                    },
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                }
            });
        }

        function fetchTrendChart() {
            const eventName = document.getElementById('eventDropdown').value;
            if (!eventName || !fetchedRobots.length) {
                hideTrendChart();
                return;
            }

            const robotList = fetchedRobots.map(r => r.robot).join(',');
            fetch(`match_prediction.php?ajax=trend&event_name=${encodeURIComponent(eventName)}&robot_list=${encodeURIComponent(robotList)}`)
                .then(r => r.json())
                .then(data => {
                    renderTrendChart(data);
                })
                .catch(err => {
                    console.error('Trend chart error:', err);
                    hideTrendChart();
                });
        }

        function hideTrendChart() {
            document.getElementById('matchTrendContainer').style.display = 'none';
            if (trendChart) {
                trendChart.destroy();
                trendChart = null;
            }
        }

        function renderTrendChart(rows) {
            if (!Array.isArray(rows) || !rows.length) {
                hideTrendChart();
                return;
            }

            const grouped = {};
            rows.forEach(row => {
                const robot = String(row.robot);
                if (!grouped[robot]) grouped[robot] = [];
                grouped[robot].push({
                    x: Number(row.match_no),
                    y: Number(row.points || 0)
                });
            });

            const colors = [
                'rgba(20,96,61,.7)',
                'rgba(20,55,96,.7)',
                'rgba(96,20,55,.7)',
                'rgba(96,61,20,.7)',
                'rgba(96,23,20,.7)',
                'rgba(33,91,159,.7)'
            ];

            const datasets = Object.keys(grouped).map((robot, index) => ({
                label: robot,
                data: grouped[robot].sort((a, b) => a.x - b.x),
                borderColor: colors[index % colors.length],
                backgroundColor: colors[index % colors.length],
                fill: false,
                showLine: true,
                lineTension: 0.1
            }));

            const canvas = document.getElementById('matchTrendChart');
            const ctx = canvas.getContext('2d');

            if (trendChart) {
                trendChart.destroy();
            }

            trendChart = new Chart(ctx, {
                type: 'scatter',
                data: { datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    title: {
                        display: true,
                        text: 'Selected Match Robots: Points by Match'
                    },
                    legend: {
                        position: 'bottom'
                    },
                    scales: {
                        xAxes: [{
                            type: 'linear',
                            position: 'bottom',
                            scaleLabel: {
                                display: true,
                                labelString: 'Match Number'
                            },
                            ticks: {
                                stepSize: 1
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
                    }
                }
            });

            document.getElementById('matchTrendContainer').style.display = 'block';
        }

        document.getElementById('eventDropdown').addEventListener('change', fetchMatches);
        document.getElementById('matchDropdown').addEventListener('change', function() {
            if (!this.value) {
                resetPredictionCard();
                document.getElementById('robotContainer').innerHTML = '';
                hideTrendChart();
                return;
            }
            fetchRobotCards();
        });
        document.getElementById('sortOption').addEventListener('change', updateRobotCards);
        document.getElementById('robotToggleDropdown').addEventListener('change', function() {
            toggleRobotFilter();
        });
    </script>
</body>
</html>