<?php
// delete_by_event.php
require_once '../php/database_connection.php';

// Fetch distinct event names
function getEventNames(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT DISTINCT event_name
        FROM scouting_submissions
        ORDER BY event_name
    ");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$message = '';
// If a delete request is submitted, perform deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['event_name'])) {
    $event = $_POST['event_name'];
    $stmt  = $pdo->prepare("
        DELETE FROM scouting_submissions
        WHERE event_name = :event
    ");
    $stmt->execute(['event' => $event]);
    $deletedCount = $stmt->rowCount();
    $message = sprintf(
        '%d entr%s deleted for event "%s".',
        $deletedCount,
        $deletedCount === 1 ? 'y was' : 'ies were',
        htmlspecialchars($event)
    );
}

// Otherwise (or after delete), fetch list of events
$events = getEventNames($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">  
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Owl Delete Scouting Entries</title>
  <style>
    @font-face {
      font-family: 'Roboto';
      src: url('/../stat_goblin/fonts/roboto/Roboto-Regular.ttf') format('ttf');
    }
    @font-face {
      font-family: 'Griffy';
      src: url('/../stat_goblin/fonts/Griffy/Griffy-Regular.ttf') format('ttf');
    }
    @font-face {
      font-family: 'Comfortaa';
      src: url('/../stat_goblin/fonts/Comfortaa/Comfortaa-VariableFont_wght.ttf') format('ttf');
    }
    body, html {
      font-family: 'Comfortaa', sans-serif;
      margin: 0;
      padding: 0;
      background: #222;
      color: #eee;
      line-height: 1.5;
      text-align: center;
    }
    #containerOuter {
      background-color: #333;
      border-bottom: 1px solid #444;
      width: 100%;
      padding: 1rem;
      box-sizing: border-box;
    }
    #container {
      background-color: #333;
      max-width: 800px;
      margin: auto;
    }
    .logo {
      width: 400px;
      display: block;
      margin: 0 auto 1rem auto;
    }
    form {
      margin-top: 2rem;
    }
    select, button {
      font-family: inherit;
      font-size: 1rem;
      padding: 0.5rem 1rem;
      margin: 0.5rem;
      border: 1px solid #555;
      border-radius: 4px;
      background: #444;
      color: #eee;
      cursor: pointer;
    }
    select:focus, button:focus {
      outline: none;
      border-color: #888;
    }
    button:hover {
      background: #555;
    }
    select { min-width: 240px; }
    #message {
      margin-top: 1rem;
      font-weight: bold;
      color: #f66;
    }
  </style>
  <link rel="stylesheet" href="../css/select.css">
</head>
<body>
  <div id="containerOuter">
    <div id="container">
      <a href="..">
        <img src="../images/owlupload.png" class="logo" alt="Logo">
      </a>
      <h2>Delete Scouting Submissions by Event</h2>
      <?php if ($message): ?>
        <div id="message"><?= $message ?></div>
      <?php endif; ?>
      <form method="post" action="">
        <label for="event_name">Select Event to Delete:</label><br>
        <select id="event_name" name="event_name" required>
          <option value="" disabled selected>EVENT</option>
          <?php foreach ($events as $e): ?>
            <option value="<?= htmlspecialchars($e) ?>">
              <?= htmlspecialchars($e) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="submit">Delete Entries</button>
      </form>
    </div>
  </div>
</body>
</html>
