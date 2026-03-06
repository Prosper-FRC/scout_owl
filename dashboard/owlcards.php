<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owl Cards - FRC Robot Stats</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
    /* ── Reset & Base ── */
    *, *::before, *::after {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    :root {
        --bg-dark: #0a1628;
        --border: rgba(180, 205, 230, 0.55);
        --border-bright: rgba(210, 225, 240, 0.75);
        --border-width: 1.5px;
        --text-primary: rgba(210, 225, 240, 0.88);
        --text-dim: rgba(180, 200, 220, 0.72);
        --text-bright: rgba(235, 242, 250, 0.95);
        --font-mono: 'Share Tech Mono', monospace;
        --font-display: 'Orbitron', sans-serif;
        --font-body: 'Rajdhani', sans-serif;
    }

    html, body {
        min-height: 100%;
        background: var(--bg-dark);
        color: var(--text-primary);
        font-family: var(--font-body);
        overflow-y: auto;
    }

    /* ── Page Wrapper ── */
    .page-wrapper {
        width: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 3vh 4vw 2vh;
        gap: 1vh;
    }

    /* ── Dashboard Card ── */
    .dashboard {
        width: 100%;
        height: 80vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 2vh 0;
        gap: 1vh;
        border-bottom: var(--border-width) solid var(--border);
        margin-bottom: 2vh;
    }

    .dashboard:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }

    /* ── Search Bar ── */
    .search-container {
        width: 36%;
        max-width: 460px;
        min-width: 240px;
    }

    .search-input {
        width: 100%;
        padding: 9px 22px;
        background: transparent;
        border: var(--border-width) solid var(--border-bright);
        color: var(--text-bright);
        font-family: var(--font-mono);
        font-size: 1.5rem;
        /* 1.05 */
        letter-spacing: 0.06em;
        text-align: center;
        outline: none;
        transition: border-color 0.3s, box-shadow 0.3s;
    }

    .search-input::placeholder {
        color: var(--text-primary);
        opacity: 0.85;
    }

    .search-input:focus {
        border-color: var(--text-bright);
        box-shadow: 0 0 10px rgba(120, 170, 220, 0.1);
    }

    /* ── Back Button ── */
    .back-btn {
        position: fixed;
        top: 12px;
        left: 14px;
        z-index: 200;
        background: transparent;
        border: var(--border-width) solid var(--border);
        color: var(--text-dim);
        font-family: var(--font-mono);
        font-size: 2rem; /*0.75  */
        padding: 5px 10px;
        cursor: pointer;
        letter-spacing: 0.06em;
        text-decoration: none;
        transition: border-color 0.25s, color 0.25s;
    }

    .back-btn:hover {
        border-color: var(--border-bright);
        color: var(--text-bright);
    }

    /* ── Fullscreen Button ── */
    .fullscreen-btn {
        position: fixed;
        top: 12px;
        right: 14px;
        z-index: 200;
        background: transparent;
        border: var(--border-width) solid var(--border);
        color: var(--text-dim);
        font-family: var(--font-mono);
        font-size: 1.55rem; /* .75 */
        padding: 5px 10px;
        cursor: pointer;
        letter-spacing: 0.06em;
        transition: border-color 0.25s, color 0.25s;
    }

    .fullscreen-btn:hover {
        border-color: var(--border-bright);
        color: var(--text-bright);
    }

    /* ── Event Selector ── */
    .event-selector-wrapper {
        position: relative;
        text-align: center;
        margin-bottom: 0.5vh;
    }

    .event-selector-label {
        font-family: var(--font-mono);
        font-size: 1.25rem; /* .82 */
        color: var(--text-dim);
        letter-spacing: 0.1em;
        cursor: pointer;
        padding: 4px 12px;
        border: var(--border-width) solid transparent;
        transition: border-color 0.25s, color 0.25s;
        display: inline-block;
    }

    .event-selector-label:hover {
        color: var(--text-bright);
        border-color: var(--border);
    }

    .event-dropdown {
        display: none;
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: var(--bg-dark);
        border: var(--border-width) solid var(--border-bright);
        z-index: 100;
        min-width: 220px;
        max-height: 250px;
        overflow-y: auto;
    }

    .event-dropdown.open {
        display: block;
    }

    .event-option {
        font-family: var(--font-mono);
        font-size: .98rem; /* .78 */
        color: var(--text-primary);
        letter-spacing: 0.06em;
        padding: 8px 16px;
        cursor: pointer;
        text-align: center;
        transition: background 0.2s, color 0.2s;
    }

    .event-option:hover {
        background: rgba(120, 170, 210, 0.12);
        color: var(--text-bright);
    }

    .event-option.active {
        color: var(--text-bright);
        border-left: 2px solid var(--border-bright);
    }

    /* ── Content Grid ── */
    .content-grid {
        flex: 1;
        width: 100%;
        display: grid;
        grid-template-columns: 12% 1fr 20%;
        gap: 2.5vw;
        min-height: 0;
        padding: 0 3vw;
        align-items: center;
    }

    /* ── Match List (Left) ── */
    .match-list {
        display: flex;
        flex-direction: column;
        gap: 6px;
        justify-content: center;
        height: 70%;
    }

    .match-row {
        border: var(--border-width) solid var(--border);
        padding: 9px 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-family: var(--font-mono);
        font-size: 1.3rem; /* .78 */
        color: var(--text-primary);
        transition: border-color 0.25s;
    }

    .match-row:hover {
        border-color: var(--border-bright);
    }

    .match-label {
        color: var(--text-dim);
    }

    .match-score {
        color: var(--text-bright);
    }

    /* ── Center Column ── */
    .center-column {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1.5vh;
        height: 100%;
        justify-content: center;
        min-height: 0;
    }

    /* ── Team Number ── */
    .team-number {
        font-family: var(--font-display);
        font-weight: 700;
        font-size: clamp(3.5rem, 7.5vw, 7rem);
        color: white;
        /*-webkit-text-stroke: 1.5px var(--border-bright);*/
        letter-spacing: 0.12em;
        line-height: 1;
        text-align: center;
        user-select: none;
    }

    /* ── Team Name ── */
    .team-name {
        font-family: var(--font-mono);
        font-size: 1.8rem; /* 1.5 */
        color: var(--text-dim);
        letter-spacing: 0.08em;
        text-align: center;
        margin-top: -0.5vh;
    }

    /* ── Chart Boxes ── */
    .chart-box {
        border: var(--border-width) solid var(--border);
        position: relative;
    }

    .line-chart-box {
        flex: 1;
        width: 100%;
        padding: 14px 18px;
        min-height: 0;
        max-height: 50vh;
    }

    /* ── Right Column ── */
    .right-column {
        display: flex;
        flex-direction: column;
        gap: 12px;
        height: 70%;
        justify-content: center;
    }

    .bar-chart-single {
        flex: 1;
        padding: 14px 16px;
        min-height: 0;
    }

    /* ── Canvas ── */
    canvas {
        width: 100% !important;
        height: 100% !important;
    }

    /* ── Scrollbar (hidden) ── */
    ::-webkit-scrollbar {
        display: none;
    }

    /* ── Responsive ── */
    @media (max-width: 900px) {
        .content-grid {
            grid-template-columns: 1fr;
            grid-template-rows: auto 1fr auto;
            gap: 2vh;
            padding: 0 2vw;
        }

        .match-list {
            flex-direction: row;
            flex-wrap: wrap;
            justify-content: center;
            height: auto;
        }

        .match-row {
            width: 80px;
        }

        .right-column {
            flex-direction: row;
            height: 200px;
        }

        .search-container {
            width: 70%;
        }

        .team-number {
            font-size: 3rem;
        }
    }
    </style>
</head>
<body>

<a class="back-btn" href="/stat_owl/dashboard" title="Back to Dashboard">&#x2190; DASHBOARD</a>
<button class="fullscreen-btn" id="fullscreenBtn" title="Toggle fullscreen">&#x26F6; FULLSCREEN</button>

<?php
    // Sample data — replace with real API calls to The Blue Alliance or Statbotics

    // blue alliance api key
    $blueAllianceApiKey = 'oMwHQOfUGOOgd8Hj3XZmNtZ9346DIzMExjS2L87nCwO23tnoG8ZRjrcJbkPE6n6l';

    // Match label prefix — change this to customize match labels (e.g., "Q", "M", "Match ")
    $matchLabelPrefix = "Q";

    include '../php/database_connection.php';

    // Fetch team nickname from The Blue Alliance API
    function getTeamName($teamNumber, $apiKey) {
        $url = "https://www.thebluealliance.com/api/v3/team/frc" . urlencode($teamNumber);
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => "X-TBA-Auth-Key: $apiKey\r\nAccept: application/json\r\n",
                'timeout' => 5,
            ]
        ];
        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);
        if ($response !== false) {
            $data = json_decode($response, true);
            if (isset($data['nickname'])) {
                return $data['nickname'];
            }
        }
        return '';
    }

    function getSampleData($teamNumber, $eventName, $pdo, $apiKey, $labelPrefix = "Q") {

        // ── Team Name from TBA ──
        $teamName = getTeamName($teamNumber, $apiKey);

        // ── Fuel Graph: fuel points per match (line chart + match list) ──
        $stmt = $pdo->prepare("SELECT match_no, SUM(points) AS total_points
            FROM scouting_submissions
            WHERE event_name = :eventName AND robot = :robot AND result = 'Success'
            AND action IN ('score_1_fuel','score_2_fuel','score_3_fuel','score_4_fuel')
            GROUP BY match_no ORDER BY match_no");
        $stmt->execute([':eventName' => $eventName, ':robot' => $teamNumber]);
        $fuelResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $matches = [];
        $perfLabels = [];
        $perfData = [];
        foreach ($fuelResults as $row) {
            $label = $labelPrefix . $row['match_no'];
            $score = (int)$row['total_points'];
            $matches[]    = ["match" => $label, "score" => $score];
            $perfLabels[] = $label;
            $perfData[]   = $score;
        }
        $performanceLabels = json_encode($perfLabels);
        $performanceData   = json_encode($perfData);

        // ── Average FPM (Fuel Points per Match) ──
        $stmt = $pdo->prepare("SELECT SUM(points) / NULLIF(COUNT(DISTINCT match_no), 0) AS avg_fpm
            FROM scouting_submissions
            WHERE event_name = :eventName AND robot = :robot AND result = 'Success'
            AND action IN ('score_1_fuel','score_2_fuel','score_3_fuel','score_4_fuel')");
        $stmt->execute([':eventName' => $eventName, ':robot' => $teamNumber]);
        $avgResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $avgLine = $avgResult && $avgResult['avg_fpm'] !== null
            ? round((float)$avgResult['avg_fpm'], 1) : 0;

        // ── Climb Graph: successful climbs per level ──
        $stmt = $pdo->prepare("SELECT action, COUNT(*) AS cnt
            FROM scouting_submissions
            WHERE event_name = :eventName AND robot = :robot AND result = 'Success'
            AND action IN ('climb_lv_1','climb_lv_2','climb_lv_3')
            GROUP BY action ORDER BY action");
        $stmt->execute([':eventName' => $eventName, ':robot' => $teamNumber]);
        $climbResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $climbMap = ['climb_lv_1' => 0, 'climb_lv_2' => 0, 'climb_lv_3' => 0];
        foreach ($climbResults as $row) {
            if (isset($climbMap[$row['action']])) {
                $climbMap[$row['action']] = (int)$row['cnt'];
            }
        }
        $climbLabels = json_encode(["Level 1 Climb", "Level 2 Climb", "Level 3 Climb"]);
        $climbData   = json_encode(array_values($climbMap));

        // ── Fuel Success Rate (%) ──
        $stmt = $pdo->prepare("SELECT 100 * (
            (SUM(CASE WHEN action = 'score_4_fuel' THEN 4 ELSE 0 END) +
             SUM(CASE WHEN action = 'score_3_fuel' THEN 3 ELSE 0 END) +
             SUM(CASE WHEN action = 'score_2_fuel' THEN 2 ELSE 0 END) +
             SUM(CASE WHEN action = 'score_1_fuel' THEN 1 ELSE 0 END)
            -
             SUM(CASE WHEN result = 'Failure' AND action = 'score_4_fuel' THEN 4 ELSE 0 END) -
             SUM(CASE WHEN result = 'Failure' AND action = 'score_3_fuel' THEN 3 ELSE 0 END) -
             SUM(CASE WHEN result = 'Failure' AND action = 'score_2_fuel' THEN 2 ELSE 0 END) -
             SUM(CASE WHEN result = 'Failure' AND action = 'score_1_fuel' THEN 1 ELSE 0 END))
            / NULLIF(
             SUM(CASE WHEN action = 'score_4_fuel' THEN 4 ELSE 0 END) +
             SUM(CASE WHEN action = 'score_3_fuel' THEN 3 ELSE 0 END) +
             SUM(CASE WHEN action = 'score_2_fuel' THEN 2 ELSE 0 END) +
             SUM(CASE WHEN action = 'score_1_fuel' THEN 1 ELSE 0 END), 0)
            ) AS success_rate
            FROM scouting_submissions
            WHERE event_name = :eventName AND robot = :robot
            AND action IN ('score_1_fuel','score_2_fuel','score_3_fuel','score_4_fuel')");
        $stmt->execute([':eventName' => $eventName, ':robot' => $teamNumber]);
        $fsr = $stmt->fetch(PDO::FETCH_ASSOC);
        $fuelSuccessRate = $fsr && $fsr['success_rate'] !== null
            ? round((float)$fsr['success_rate'], 1) : 0;

        // ── Defense count ──
        $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt
            FROM scouting_submissions
            WHERE event_name = :eventName AND robot = :robot
            AND result = 'Success' AND action = 'defense'");
        $stmt->execute([':eventName' => $eventName, ':robot' => $teamNumber]);
        $defResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $defenseCount = $defResult ? (int)$defResult['cnt'] : 0;

        // ── Auton: average fuel points per match in autonomous (time_sec < 23) ──
        $stmt = $pdo->prepare("SELECT SUM(points) / NULLIF(COUNT(DISTINCT match_no), 0) AS auton_avg
            FROM scouting_submissions
            WHERE event_name = :eventName AND robot = :robot AND result = 'Success'
            AND time_sec < 23
            AND action IN ('score_1_fuel','score_2_fuel','score_3_fuel','score_4_fuel')");
        $stmt->execute([':eventName' => $eventName, ':robot' => $teamNumber]);
        $autonResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $autonAvg = $autonResult && $autonResult['auton_avg'] !== null
            ? round((float)$autonResult['auton_avg'], 1) : 0;

        // ── Sidebar stats (change labels and values here) ──
        $stats = [
            ['label' => 'Fuel Success %',  'value' => $fuelSuccessRate . '%'],
            ['label' => 'Avg Fuel Shot per Match',  'value' => $avgLine . ' FPM'],
            ['label' => 'Auton Avg Points',  'value' => $autonAvg],
            ['label' => 'Defense Count',  'value' => $defenseCount],
        ];

        return [
            'teamNumber'        => $teamNumber,
            'teamName'          => $teamName,
            'eventName'         => $eventName,
            'stats'             => $stats,
            'performanceLabels' => $performanceLabels,
            'performanceData'   => $performanceData,
            'avgLine'           => $avgLine,
            'climbLabels'       => $climbLabels,
            'climbData'         => $climbData,
            'fuelSuccessRate'   => $fuelSuccessRate,
            'defenseCount'      => $defenseCount,
            'autonAvg'          => $autonAvg,
        ];
    }

    // Fetch all distinct events from DB
    $evtStmt = $pdo->prepare("SELECT DISTINCT event_name FROM scouting_submissions ORDER BY event_name");
    $evtStmt->execute();
    $allEvents = $evtStmt->fetchAll(PDO::FETCH_COLUMN);

    // Use the selected event from the URL, or fall back to the first event (or 'random')
    if (isset($_GET['event']) && $_GET['event'] !== '') {
        $eventNameQuery = $_GET['event'];
    } elseif (!empty($allEvents)) {
        $eventNameQuery = $allEvents[0];
    } else {
        $eventNameQuery = 'random';
    }

    // Fetch distinct robots for the selected event
    $query = "SELECT DISTINCT robot FROM scouting_submissions WHERE event_name = :eventName";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':eventName' => $eventNameQuery]);
    $robots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build list of team numbers from DB, fall back to sample data if empty
    $teamNumbers = [];
    foreach ($robots as $robot) {
        $teamNumbers[] = $robot['robot'];
    }
    if (empty($teamNumbers)) {
        $teamNumbers = ["5411", "1234", "9999"];
    }

    // ── JSON API mode: return data for AJAX polling ──
    if (isset($_GET['format']) && $_GET['format'] === 'json') {
        header('Content-Type: application/json');
        $allTeamData = [];
        foreach ($teamNumbers as $team) {
            $allTeamData[] = getSampleData($team, $eventNameQuery, $pdo, $blueAllianceApiKey, $matchLabelPrefix);
        }
        echo json_encode($allTeamData);
        exit;
    }

?>

<!-- Chart.js Global Defaults (set once) -->
<script>
    Chart.defaults.color = 'rgba(200, 215, 230, 0.7)';
    Chart.defaults.borderColor = 'rgba(160, 190, 220, 0.18)';
    Chart.defaults.font.family = "'Share Tech Mono', monospace";
    Chart.defaults.font.size = 10;

    window.owlCharts = {};
</script>

<div class="page-wrapper">

    <!-- Search Bar -->
    <div class="search-container">
        <input 
            type="text" 
            id="teamSearch"
            class="search-input" 
            placeholder="Search For a Robot"
            autocomplete="off"
        >
    </div>

    <!-- Event Selector -->
    <div class="event-selector-wrapper" id="eventSelector">
        <span class="event-selector-label" id="eventLabel">
            <?php echo htmlspecialchars($eventNameQuery); ?> &#9662;
        </span>
        <div class="event-dropdown" id="eventDropdown">
            <?php foreach ($allEvents as $evt): ?>
                <div class="event-option<?php echo $evt === $eventNameQuery ? ' active' : ''; ?>"
                     data-event="<?php echo htmlspecialchars($evt); ?>">
                    <?php echo htmlspecialchars($evt); ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

<?php foreach ($teamNumbers as $index => $team):
    $sampleData = getSampleData($team, $eventNameQuery, $pdo, $blueAllianceApiKey, $matchLabelPrefix);
    $teamNumber = $sampleData['teamNumber'];
    $teamName = $sampleData['teamName'];
    $eventName = $sampleData['eventName'];
    $stats = $sampleData['stats'];
    $performanceLabels = $sampleData['performanceLabels'];
    $performanceData = $sampleData['performanceData'];
    $avgLine = $sampleData['avgLine'];
    $climbLabels = $sampleData['climbLabels'];
    $climbData = $sampleData['climbData'];

    $lineChartId = "lineChart_$index";
    $barChartId = "barChart_$index";
?>

    <div class="dashboard" data-team="<?php echo htmlspecialchars($teamNumber); ?>" data-name="<?php echo htmlspecialchars(strtolower($teamName)); ?>" data-index="<?php echo $index; ?>">

        <!-- Main Content Grid -->
        <div class="content-grid">

            <!-- Left: Stats List -->
            <div class="match-list">
                <?php foreach ($stats as $s): ?>
                    <div class="match-row">
                        <span class="match-label"><?php echo htmlspecialchars($s['label']); ?></span>
                        <span class="match-score"><?php echo htmlspecialchars($s['value']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Center Column -->
            <div class="center-column">
                <!-- Team Number & Name -->
                <div class="team-number"><?php echo htmlspecialchars($teamNumber); ?></div>
                <?php if ($teamName): ?>
                    <div class="team-name"><?php echo htmlspecialchars($teamName); ?></div>
                <?php endif; ?>

                <!-- Line Chart -->
                <div class="chart-box line-chart-box">
                    <canvas id="<?php echo $lineChartId; ?>"></canvas>
                </div>
            </div>

            <!-- Right: Horizontal bar chart -->
            <div class="right-column">
                <div class="chart-box bar-chart-single">
                    <canvas id="<?php echo $barChartId; ?>"></canvas>
                </div>
            </div>

        </div>
    </div>

    <script>
    (function() {
        const perfLabels = <?php echo $performanceLabels; ?>;
        const perfData = <?php echo $performanceData; ?>;
        const avgValue = <?php echo $avgLine; ?>;
        const climbLabels = <?php echo $climbLabels; ?>;
        const climbData = <?php echo $climbData; ?>;

        const gridColor = 'rgba(150, 180, 210, 0.15)';
        const lineColor = 'rgba(200, 220, 240, 0.75)';
        const pointColor = 'rgba(220, 235, 250, 0.9)';
        const pointBorderColor = 'rgba(160, 190, 220, 0.55)';
        const dashedColor = 'rgba(180, 200, 220, 0.45)';
        const barBorderColor = 'rgba(190, 210, 230, 0.55)';
        const barBg = 'rgba(120, 170, 210, 0.12)';
        const tickColor = 'rgba(190, 210, 225, 0.55)';
        const axisColor = 'rgba(160, 190, 220, 0.25)';

        function makeScales(showX) {
            return {
                x: {
                    display: showX,
                    grid: { color: gridColor, drawTicks: false },
                    ticks: { color: tickColor, padding: 6 },
                    border: { color: axisColor }
                },
                y: {
                    grid: { color: gridColor, drawTicks: false },
                    ticks: { color: tickColor, padding: 6 },
                    border: { color: axisColor },
                    beginAtZero: true,
                }
            };
        }

        /* ── Line Chart ── */
        const lineCtx = document.getElementById('<?php echo $lineChartId; ?>').getContext('2d');
        const avgDataset = new Array(perfLabels.length).fill(avgValue);

        window.owlCharts['<?php echo $lineChartId; ?>'] = new Chart(lineCtx, {
            type: 'line',
            data: {
                labels: perfLabels,
                datasets: [
                    {
                        label: 'Performance',
                        data: perfData,
                        borderColor: lineColor,
                        backgroundColor: 'transparent',
                        pointBackgroundColor: pointColor,
                        pointBorderColor: pointBorderColor,
                        pointBorderWidth: 1,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        borderWidth: 1.2,
                        tension: 0,
                    },
                    {
                        label: 'Average',
                        data: avgDataset,
                        borderColor: dashedColor,
                        borderDash: [7, 5],
                        borderWidth: 1,
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        fill: false,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(8, 20, 38, 0.92)',
                        borderColor: 'rgba(140, 180, 220, 0.3)',
                        borderWidth: 1,
                    }
                },
                scales: makeScales(true)
            }
        });

        /* ── Horizontal Bar Chart (Climb Levels) ── */
        const barCtx = document.getElementById('<?php echo $barChartId; ?>').getContext('2d');

        window.owlCharts['<?php echo $barChartId; ?>'] = new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: climbLabels,
                datasets: [{
                    data: climbData,
                    backgroundColor: barBg,
                    borderColor: barBorderColor,
                    borderWidth: 1,
                    barPercentage: 0.55,
                    categoryPercentage: 0.7,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: {
                    backgroundColor: 'rgba(8, 20, 38, 0.92)',
                    borderColor: 'rgba(140, 180, 220, 0.3)',
                    borderWidth: 1,
                }},
                scales: {
                    x: {
                        grid: { color: gridColor, drawTicks: false },
                        ticks: { color: tickColor, padding: 6 },
                        border: { color: axisColor },
                        beginAtZero: true,
                    },
                    y: {
                        grid: { color: gridColor, drawTicks: false },
                        ticks: { color: tickColor, padding: 8 },
                        border: { color: axisColor },
                    }
                }
            }
        });
    })();
    </script>

<?php endforeach; ?>

</div><!-- end page-wrapper -->

<script>
(function() {
    const searchInput = document.getElementById('teamSearch');
    const wrapper = document.querySelector('.page-wrapper');
    const cards = Array.from(wrapper.querySelectorAll('.dashboard'));

    // Remember the original order so we can restore it when the search is cleared
    const originalOrder = cards.slice();

    searchInput.addEventListener('input', function() {
        const query = this.value.trim();

        if (query === '') {
            // Restore original order
            originalOrder.forEach(card => wrapper.appendChild(card));
            return;
        }

        // Find the card whose team number or team name contains the search query
        const lowerQuery = query.toLowerCase();
        const match = cards.find(card =>
            card.dataset.team.includes(query) ||
            card.dataset.name.includes(lowerQuery)
        );

        if (match) {
            // Move the matched card to the top (right after the search container)
            wrapper.insertBefore(match, wrapper.querySelector('.dashboard'));
        }
    });
})();

// ── Event Selector Dropdown ──
(function() {
    const label = document.getElementById('eventLabel');
    const dropdown = document.getElementById('eventDropdown');
    const options = dropdown.querySelectorAll('.event-option');

    // Toggle dropdown on click
    label.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.classList.toggle('open');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function() {
        dropdown.classList.remove('open');
    });

    // Prevent clicks inside dropdown from closing it prematurely
    dropdown.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    // When an event is selected, reload the page with that event
    options.forEach(function(opt) {
        opt.addEventListener('click', function() {
            const selectedEvent = this.dataset.event;
            const url = new URL(window.location.href);
            url.searchParams.set('event', selectedEvent);
            window.location.href = url.toString();
        });
    });
})();

// ── Fullscreen Toggle ──
(function() {
    const btn = document.getElementById('fullscreenBtn');
    btn.addEventListener('click', function() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen();
        } else {
            document.exitFullscreen();
        }
    });

    document.addEventListener('fullscreenchange', function() {
        btn.textContent = document.fullscreenElement ? '\u26F6 EXIT' : '\u26F6 FULLSCREEN';
    });
})();

// ── Auto-refresh data every second ──
(function() {
    const url = new URL(window.location.href);
    url.searchParams.set('format', 'json');

    setInterval(function() {
        fetch(url.toString())
            .then(res => res.json())
            .then(teams => {
                teams.forEach(function(data, i) {
                    const card = document.querySelector('.dashboard[data-index="' + i + '"]');
                    if (!card) return;

                    // Update stats in the sidebar
                    const rows = card.querySelectorAll('.match-row');
                    if (data.stats) {
                        data.stats.forEach(function(s, si) {
                            if (rows[si]) {
                                rows[si].querySelector('.match-label').textContent = s.label;
                                rows[si].querySelector('.match-score').textContent = s.value;
                            }
                        });
                    }

                    // Update line chart
                    const lineChart = window.owlCharts['lineChart_' + i];
                    if (lineChart && data.performanceLabels) {
                        const labels = JSON.parse(data.performanceLabels);
                        const perfData = JSON.parse(data.performanceData);
                        lineChart.data.labels = labels;
                        lineChart.data.datasets[0].data = perfData;
                        lineChart.data.datasets[1].data = new Array(labels.length).fill(data.avgLine);
                        lineChart.update('none');
                    }

                    // Update bar chart
                    const barChart = window.owlCharts['barChart_' + i];
                    if (barChart && data.climbLabels) {
                        barChart.data.labels = JSON.parse(data.climbLabels);
                        barChart.data.datasets[0].data = JSON.parse(data.climbData);
                        barChart.update('none');
                    }
                });
            })
            .catch(function() {});
    }, 1000);
})();
</script>

</body>
</html>
