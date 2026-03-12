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
    SELECT 
        event_name,
        match_no + 1 AS match_number
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

// Option A: fetch() since we're only expecting one row
$row = $activeventStmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    $activeEventName = $row['event_name'];
    $activeMatch     = $row['match_number'];

    // Do something with $activeEventName and $activeMatch...

    //echo "Next Match: $activeMatch<br>";
} else {
    // If there's no row returned, handle the case
    //echo "No row found for the last event!";
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
// --- END of new block ---
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>the Scout Owl</title>

    <style>
        /* Responsive design: Adjust form gap for screens narrower than 330px */
        @media (max-width: 800px) {
            form {
                gap: 8px; /* Reduces the gap between form elements for small screens */
            }
        }

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
        /* Styling for the main heading (h1) */
        h1 {
            text-align: left; /* Aligns the text to the left */
            font-size: 1.2rem; /* Sets the font size */
            margin-top: -10px; /* Adjusts the top margin */
            margin-left: 12px; /* Adds left margin for positioning */
        }

        /* Styling for the form element */
        form {
            padding: 10px; /* Adds padding inside the form */
            display: flex; /* Uses flexbox layout */
            flex-direction: column; /* Arranges children in a column */
            gap: 10px; /* Adds space between form elements */
        }

        /* Styling for form labels */
        label {
            font-size: 0.9rem; /* Sets the font size */
            margin-bottom: 5px; /* Adds space below the label */
        }

        /* Styling for select elements and buttons */
        select, button {
            font-size: 0.9rem; /* Sets the font size */
            padding: 10px; /* Adds padding inside the elements */
            border-radius: 5px; /* Rounds the corners */
            width: 100%; /* Sets the width to 100% of the container */
            box-sizing: border-box; /* Includes padding and border in the element's total width and height */
        }

        /* Additional styling for buttons */
        button {
            padding: 1.5rem; /* Increases padding for larger clickable area */
            cursor: pointer; /* Changes cursor to pointer on hover */
        }

        /* Styling for the submit button */
        .submit-button {
            background-color: #FFF; /* Sets background color to white */
            color: #111; /* Sets text color to dark */
            font-size: 1rem; /* Sets font size */
            border: 1px solid #fff; /* Adds a white border */
        }

        /* Hover effect for the submit button */
        .submit-button:hover {
            background-color: #111; /* Changes background color on hover */
            color: #FFF; /* Changes text color on hover */
            border: 1px solid #fff; /* Maintains border on hover */
        }

        /* Styling for the logo image */
        .logo {
      width: 400px;
      display: block;
      margin: 0 auto 1rem auto;
    }

      

        /* Class to hide elements */
        .hidden {
            display: none; /* Hides the element */
        }

        /* Focus state for select elements */
        select:focus {
            outline: none; /* Removes default outline */
            border-color: #ccc; /* Changes border color on focus */
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


    </style>
    <link rel="stylesheet" href="css/select.css">
</head>
<body>
      <div class="containerOuter">
       <!-- <?php
            echo "Event Name: $activeEventName<br>"; 
            echo "Next Match: $activeMatch<br>";
       ?>-->
    <div class="container">
    <a href="."><img src="images/thescoutowl.png" class="logo"> </a>

 <form id="scoutingForm">
        <label for="eventDropdown">Event:</label> 
        <select id="eventDropdown" name="event" required> 
            <option value="">Select Event</option>
            <?php foreach ($events as $event):?> 
                <option value="<?= htmlspecialchars($event['event_name'])?>"><?= htmlspecialchars($event['event_name'])?></option>
            <?php endforeach;?>
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

    // 1) Keep a reference to these values
    let activeEventName = "<?php echo $activeEventName; ?>";
    let activeMatch     = "<?php echo $activeMatch; ?>";

    // 2) On change for #eventDropdown
    $('#eventDropdown').change(function() {
        var eventName = $(this).val();

        if (eventName) {
            console.log('Selected Event:', eventName);
            $.ajax({
                type: 'POST',
                url: 'php/fetch_data.php',
                data: {
                    event: eventName,
                    action: 'fetchMatches'
                },
                success: function(response) {
                    console.log('AJAX Response for fetchMatches:', response);
                    $('#matchNumberDropdown').html(response);  // Populate matchNumberDropdown
                    $('#robotDropdown').html('<option value="">Select Robot</option>');
                    $('#allianceDisplay').val('');

                    // ***** Now that matchNumberDropdown is populated, set it here! *****
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
            console.log('Selected Event:', eventName);
            console.log('Selected Match Number:', matchNumber);
            $.ajax({
                type: 'POST',
                url: 'php/fetch_data.php',
                data: {
                    event: eventName,
                    match_number: matchNumber,
                    action: 'fetchRobots'
                },
                success: function(response) {
                    console.log('AJAX Response for fetchRobots:', response);
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
            console.log('Selected Robot:', robot);
            $.ajax({
                type: 'POST',
                url: 'php/fetch_data.php',
                data: {
                    event: eventName,
                    match_number: matchNumber,
                    robot: robot,
                    action: 'fetchAlliance'
                },
                success: function(response) {
                    console.log('AJAX Response for fetchAlliance:', response);
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


// 5) On form submit
    $('#submitForm').click(function() {
        var event       = $('#eventDropdown').val();
        var game        = $('#gameDropdown').val(); // <-- GET GAME VALUE
        var matchNumber = $('#matchNumberDropdown').val();
        var robot       = $('#robotDropdown').val();
        var alliance    = $('#allianceDisplay').val();

        // Add 'game' to the validation check
        if (event && game && matchNumber && robot && alliance) { 
            // Add '&game=' parameter to the URL
            window.location.href = `scouter/index.php?event=${encodeURIComponent(event)}&match=${encodeURIComponent(matchNumber)}&robot=${encodeURIComponent(robot)}&alliance=${encodeURIComponent(alliance)}&game=${encodeURIComponent(game)}`; 
        } else {
            alert('Please fill all fields.');
        }
    });

    // 6) Finally: set the #eventDropdown if we have activeEventName
    //    That will trigger the .change(), which will fetch the matches, 
    //    which will then (in success callback) set #matchNumberDropdown.
    if (activeEventName) {
        $('#eventDropdown').val(activeEventName).trigger('change');
    }

});



    </script>
</div>
</div>

</body>
</html>