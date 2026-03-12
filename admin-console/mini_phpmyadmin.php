<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once '../php/database_connection.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    die("Database connection failed: \$pdo not created in database_connection.php");
}

$validated = $_SESSION['validated'] ?? false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code_input'])) {
    $input = trim($_POST['code_input']);
    $stmt = $pdo->query("SELECT code FROM codes WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $active_code = $row['code'] ?? null;

    if ($active_code && $input === $active_code) {
        $_SESSION['validated'] = true;
        $validated = true;
    } else {
        $error_message = "Invalid code. Try again.";
    }
}

if (!$validated):
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Code Validator</title>
<style>
body {
    background: #1e1e1e;
    color: #fff;
    font-family: monospace;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100vh;
    margin: 0;
}
#validator {
    text-align: center;
    background: rgba(0, 0, 0, 0.8);
    padding: 40px;
    border-radius: 10px;
}
.input-container {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    justify-content: center;
}
.input-box {
    width: 70px;
    height: 70px;
    text-align: center;
    border: 2px solid #fff;
    background: #111;
    color: #fff;
    border-radius: 10px;
    font-size: 32px;
}
.input-box:focus { border-color: #0f0; outline: none; }
button {
    margin-top: 20px;
    padding: 10px 20px;
    background: #444;
    border: none;
    color: #fff;
    font-size: 16px;
    cursor: pointer;
    border-radius: 8px;
}
button:hover { background: #666; }

@media (max-width: 600px) {
    #validator {
        padding: 20px;
        width: 90%;
    }
    .input-box {
        width: 45px;
        height: 45px;
        font-size: 24px;
    }
}
</style>
<script>
function moveFocus(index) {
    const current = document.getElementById('input' + index);
    if (current.value && index < 4) {
        document.getElementById('input' + (index + 1)).focus();
    }
}
function combineCode() {
    let code = '';
    for (let i = 1; i <= 4; i++) code += document.getElementById('input' + i).value;
    document.getElementById('code_input').value = code;
}
</script>
</head>
<body>
<div id="validator">
    <h3>Please enter the active 4-digit code:</h3>
    <form method="POST" onsubmit="combineCode()">
        <div class="input-container">
            <input type="text" id="input1" class="input-box" maxlength="1" oninput="moveFocus(1)" autofocus>
            <input type="text" id="input2" class="input-box" maxlength="1" oninput="moveFocus(2)">
            <input type="text" id="input3" class="input-box" maxlength="1" oninput="moveFocus(3)">
            <input type="text" id="input4" class="input-box" maxlength="1">
        </div>
        <input type="hidden" name="code_input" id="code_input">
        <button type="submit">Validate</button>
    </form>
    <?php if (!empty($error_message)): ?>
        <p style="color: red;"><?= htmlspecialchars($error_message) ?></p>
    <?php endif; ?>
</div>
</body>
</html>
<?php
exit;
endif;

// --- Main interface (shown after validation) ---

// Fetch tables and columns
$tables = [];
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tableName = $row[0];
    $colStmt = $pdo->query("SHOW COLUMNS FROM `$tableName`");
    $cols = $colStmt->fetchAll(PDO::FETCH_ASSOC);
    $tables[$tableName] = $cols;
}

// Query handling
$query = '';
$result = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['query'])) {
    $query = trim($_POST['query']);
    try {
        $stmt = $pdo->query($query);
        if (preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)/i', $query)) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $result = "Query executed successfully. Rows affected: " . $stmt->rowCount();
        }
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>mini phpMyAdmin</title>
<style>
html, body {
    height: 100%;
    margin: 0;
    padding: 0;
}
body {
    display: flex; 
    background: #fff;
    font-family: monospace;
    color: #111;
    min-height: 100vh; 
}
.sidebar {
    position: fixed; 
    top: 0;
    left: 0;
    width: 25%;
    height: 100vh; 
    background: #eee;
    padding: 10px;
    overflow-y: auto;
    box-sizing: border-box; 
}
.main {
    margin-left: 25%; 
    flex: 1;
    padding: 20px;
    box-sizing: border-box; 
    overflow-y: auto; 
    height: 100vh; 
}
h2 { margin: 0 0 10px; }
table { border-collapse: collapse; width: 100%; margin-top: 10px; }
td, th { border: 1px solid #444; padding: 4px 6px; }
th { background: #333; color:#fff;}
textarea {
    width: 100%;
    height: 100px;
    background: #111;
    color: #eee;
    border: 1px solid #444;
    padding: 6px;
    resize: vertical; 
    overflow: auto; 
    box-sizing: border-box;
}

button { background: #444; color: #fff; border: none; padding: 8px 16px; cursor: pointer; margin-top: 5px; }
button:hover { background: #666; }
button:disabled { background: #999; cursor: not-allowed; }
.table-name { font-weight: bold; color: #111; cursor: pointer; }
.column-info { margin-left: 10px; color: #444; }
.hidden { display: none; }
#headerbar img{width:100%;}

.table-wrapper {
    overflow-x: auto;
    white-space: nowrap;
}

@media (max-width: 768px) {
    body {
        display: block; /* Turn off flexbox for stacking */
    }
    .sidebar {
        position: relative; /* Un-fix the sidebar */
        width: 100%;
        height: auto; /* Let content define its height */
        border-bottom: 2px solid #ccc;
    }
    .main {
        margin-left: 0; /* Remove the sidebar margin */
        height: auto;
        overflow-y: visible; /* Let the whole page scroll */
        padding: 10px; /* Reduce padding */
    }
}
</style>
<script>
function toggleTable(id) {
  document.getElementById(id).classList.toggle('hidden');
}
</script>
</head>
<body>
<div class="sidebar">
    <div id="headerbar">
            <img src="../images/miniphpmyadmin.png">
        
    </div>
  <h2>Tables</h2>
  <?php foreach ($tables as $table => $cols): ?>
    <div>
      <div class="table-name" onclick="toggleTable('<?= $table ?>')"><?= htmlspecialchars($table) ?></div>
      <div id="<?= $table ?>" class="hidden">
        <?php foreach ($cols as $col): ?>
          <div class="column-info"><?= htmlspecialchars($col['Field']) ?> (<?= htmlspecialchars($col['Type']) ?>)</div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="main">
  <h2>SQL Query</h2>
  <form method="POST">
    <textarea name="query"><?= htmlspecialchars($query) ?></textarea><br>
    <button type="submit">Run</button>
  </form>

  <?php if ($error): ?>
    <div style="color:red;"><strong>Error:</strong> <?= htmlspecialchars($error) ?></div>
  <?php elseif ($result): ?>
    <?php if (is_array($result)): ?>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <?php foreach (array_keys($result[0] ?? []) as $col): ?>
                <th><?= htmlspecialchars($col) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($result as $row): ?>
              <tr>
                <?php foreach ($row as $val): ?>
                  <td><?= htmlspecialchars($val ?? '') ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div><?= htmlspecialchars($result ?? '') ?></div>
    <?php endif; ?>
  <?php endif; ?>
</div>


<script>
// --- Textarea Resizer Script ---
const ta = document.querySelector('textarea[name="query"]');
if (ta) {
  ta.addEventListener('mousedown', e => {
    if (e.offsetY > ta.clientHeight - 16) {
      const startY = e.clientY, startH = ta.clientHeight;
      const move = ev => ta.style.height = (startH + (ev.clientY - startY)) + 'px';
      const up = () => { window.removeEventListener('mousemove', move); window.removeEventListener('mouseup', up); };
      window.addEventListener('mousemove', move);
      window.addEventListener('mouseup', up);
      e.preventDefault();
    }
  });
}
</script>

<script>
// --- Disable submit button script ---
const queryTextArea = document.querySelector('textarea[name="query"]');
const submitButton = document.querySelector('.main form button[type="submit"]');

function validateQueryInput() {
  if (queryTextArea.value.trim() === '') {
    submitButton.disabled = true;
    submitButton.style.opacity = '0.5';
  } else {
    submitButton.disabled = false;
    submitButton.style.opacity = '1';
  }
}

// Check when the page first loads
validateQueryInput();

// Check every time the user types in the box
queryTextArea.addEventListener('input', validateQueryInput);
</script>

</body>
</html>