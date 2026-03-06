<?php

// 1) Include DB connection.
include 'php/database_connection.php';

// 2) Get distinct event names from active_event.
$eventQuery = "SELECT DISTINCT event_name FROM active_event";
$eventStmt = $pdo->prepare($eventQuery);
$eventStmt->execute();
$events = $eventStmt->fetchAll(PDO::FETCH_ASSOC);

// 3) Get last event + next match.
$activeventQuery = "
    SELECT event_name, match_no + 1 AS match_number
    FROM scouting_submissions
    WHERE event_name = (
        SELECT event_name
        FROM scouting_submissions
        ORDER BY id DESC
        LIMIT 1
    )
    ORDER BY match_no DESC
    LIMIT 1
";
$activeventStmt = $pdo->prepare($activeventQuery);
$activeventStmt->execute();
$row = $activeventStmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    $activeEventName = $row['event_name'];
    $activeMatch     = $row['match_number'];
} else {
    // fallback: get the first available event from active_event
    $fallbackQuery = "SELECT event_name, MIN(match_number) AS match_number FROM active_event GROUP BY event_name ORDER BY event_name LIMIT 1";
    $fallbackStmt = $pdo->prepare($fallbackQuery);
    $fallbackStmt->execute();
    $fallback = $fallbackStmt->fetch(PDO::FETCH_ASSOC);

    if ($fallback) {
        $activeEventName = $fallback['event_name'];
        $activeMatch     = $fallback['match_number'] ?? 1;
    } else {
        $activeEventName = '';
        $activeMatch     = 1;
    }
}

// --- select the game type
$gamesDir = __DIR__ . '/scouter/games'; // Path relative to this file
$gameFiles = [];
if (is_dir($gamesDir)) {
    $files = glob($gamesDir . '/*.json');
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($files as $f) {
        $gameFiles[] = basename($f); // Get just the filename
    }
}
$latestGame = end($gameFiles) ?: ''; // Find the latest game
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>the Scout Owl</title>

    <style>
        @media (max-width: 800px) { form { gap: 8px; } }

        @font-face { font-family: 'Roboto'; src: url('/../stat_goblin/fonts/roboto/Roboto-Regular.ttf') format('ttf'), url('/../stat_goblin/fonts/roboto/Roboto-Regular.ttf') format('ttf'); font-weight: normal; font-style: normal; }
        @font-face { font-family: 'Griffy'; src: url('/../stat_goblin/fonts/Griffy/Griffy-Regular.ttf') format('ttf'), url('/../stat_goblin/fonts/Griffy/Griffy-Regular.ttf') format('ttf'); font-weight: normal; font-style: normal; }
        @font-face { font-family: 'Comfortaa'; src: url('/../stat_goblin/fonts/Comfortaa/Comfortaa-VariableFont_wght.ttf') format('ttf'), url('/../stat_goblin/fonts/Comfortaa/Comfortaa-VariableFont_wght.ttf') format('ttf'); font-weight: normal; font-style: normal; }

        body, html { font-family: 'Comfortaa', sans-serif; margin:0; padding:0; background:#222; color:#eee; line-height:1.5; text-align:center; }
        h1 { text-align:left; font-size:1.2rem; margin-top:-10px; margin-left:12px; }
        form { padding:10px; display:flex; flex-direction:column; gap:10px; }
        label { font-size:0.9rem; margin-bottom:5px; }
        select, button { font-size:0.9rem; padding:10px; border-radius:5px; width:100%; box-sizing:border-box; }
        button { padding:1.5rem; cursor:pointer; }
        .submit-button { background-color:#FFF; color:#111; font-size:1rem; border:1px solid #fff; }
        .submit-button:hover { background-color:#111; color:#FFF; border:1px solid #fff; }
        .logo { width:400px; display:block; margin:0 auto 1rem auto; }
        .hidden { display:none; }
        select:focus { outline:none; border-color:#ccc; }
        .containerOuter { background-color:#333; border-bottom:1px solid #444; width:100%; padding:1rem; box-sizing:border-box; }
        .container { max-width:800px; margin:auto; }
    </style>

    <link rel="stylesheet" href="css/select.css">
</head>
<body>
<div class="containerOuter">
  <div class="container">
    <a href="."><img src="images/thescoutowl.png" class="logo"></a>

    <form id="scoutingForm">

      <!-- NEW: Field dropdown -->
      <label for="fieldDropdown">Field:</label>
      <select id="fieldDropdown" name="field_id" required>
        <option value="">Select Field</option>
        <?php for ($f = 1; $f <= 6; $f++): ?>
          <option value="<?= $f ?>"><?= $f ?></option>
        <?php endfor; ?>
      </select>

      <label for="eventDropdown">Event:</label>
      <select id="eventDropdown" name="event" required>
        <option value="">Select Event</option>
        <?php foreach ($events as $event): ?>
          <option value="<?= htmlspecialchars($event['event_name']) ?>"><?= htmlspecialchars($event['event_name']) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="gameDropdown">Game:</label>
      <select id="gameDropdown" name="game" required>
        <option value="">Select Game</option>
        <?php foreach ($gameFiles as $g): ?>
          <option value="<?= htmlspecialchars($g) ?>" <?= $g === $latestGame ? 'selected' : '' ?>>
            <?= htmlspecialchars(pathinfo($g, PATHINFO_FILENAME)) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label for="matchNumberDropdown">Match Number:</label>
      <select id="matchNumberDropdown" name="match_number" required>
        <option value="">Select Match Number</option>
      </select>

      <label for="robotDropdown">Robot:</label>
      <select id="robotDropdown" name="robot" required>
        <option value="">Select Robot</option>
      </select>

      <input type="text" id="allianceDisplay" class="hidden" name="alliance" readonly>

      <button type="button" class="submit-button" id="submitForm">Submit</button>
    </form>

    <script src="js/jquery-3.7.1.min.js"></script>

    <script>
      $(document).ready(function() {

        let activeEventName = <?php echo json_encode($activeEventName); ?>;
        let activeMatch     = <?php echo json_encode($activeMatch); ?>;

        // 2) On change for #eventDropdown
        $('#eventDropdown').change(function() {
          var eventName = $(this).val();

          if (eventName) {
            $.ajax({
              type: 'POST',
              url: 'php/fetch_data.php',
              data: { event: eventName, action: 'fetchMatches' },
              success: function(response) {
                $('#matchNumberDropdown').html(response);
                $('#robotDropdown').html('<option value="">Select Robot</option>');
                $('#allianceDisplay').val('');

                if (activeMatch) {
                  $('#matchNumberDropdown').val(activeMatch).trigger('change');
                }
              },
              error: function(xhr, status, error) {
                console.error('AJAX Error in fetchMatches:', status, error);
              }
            });
          } else {
            $('#matchNumberDropdown').html('<option value="">Select Match Number</option>');
            $('#robotDropdown').html('<option value="">Select Robot</option>');
            $('#allianceDisplay').val('');
          }
        });

        // 3) On change for #matchNumberDropdown
        $('#matchNumberDropdown').change(function() {
          var eventName   = $('#eventDropdown').val();
          var matchNumber = $(this).val();

          if (eventName && matchNumber) {
            $.ajax({
              type: 'POST',
              url: 'php/fetch_data.php',
              data: { event: eventName, match_number: matchNumber, action: 'fetchRobots' },
              success: function(response) {
                $('#robotDropdown').html(response);
                $('#allianceDisplay').val('');
              },
              error: function(xhr, status, error) {
                console.error('AJAX Error in fetchRobots:', status, error);
              }
            });
          } else {
            $('#robotDropdown').html('<option value="">Select Robot</option>');
            $('#allianceDisplay').val('');
          }
        });

        // 4) On change for #robotDropdown
        $('#robotDropdown').change(function() {
          var eventName   = $('#eventDropdown').val();
          var matchNumber = $('#matchNumberDropdown').val();
          var robot       = $(this).val();

          if (eventName && matchNumber && robot) {
            $.ajax({
              type: 'POST',
              url: 'php/fetch_data.php',
              data: { event: eventName, match_number: matchNumber, robot: robot, action: 'fetchAlliance' },
              success: function(response) {
                $('#allianceDisplay').val(response);

                if (response == 'Red') {
                  $('#robotDropdown').css('background-color', '#C0392B');
                } else {
                  $('#robotDropdown').css('background-color', '#2C3E50');
                }
              },
              error: function(xhr, status, error) {
                console.error('AJAX Error in fetchAlliance:', status, error);
              }
            });
          } else {
            $('#allianceDisplay').val('');
          }
        });

        // 5) On submit: include field_id in the URL
        $('#submitForm').click(function() {
          var fieldId     = $('#fieldDropdown').val();
          var event       = $('#eventDropdown').val();
          var game        = $('#gameDropdown').val();
          var matchNumber = $('#matchNumberDropdown').val();
          var robot       = $('#robotDropdown').val();
          var alliance    = $('#allianceDisplay').val();

          if (fieldId && event && game && matchNumber && robot && alliance) {
            window.location.href =
              `scouter/index.php?event=${encodeURIComponent(event)}&match=${encodeURIComponent(matchNumber)}&robot=${encodeURIComponent(robot)}&alliance=${encodeURIComponent(alliance)}&game=${encodeURIComponent(game)}&field_id=${encodeURIComponent(fieldId)}`;
          } else {
            alert('Please fill all fields (including Field).');
          }
        });

        // 6) Auto-set event + match as before
        if (activeEventName) {
          $('#eventDropdown').val(activeEventName).trigger('change');
        }
      });
    </script>
  </div>
</div>
</body>
</html>