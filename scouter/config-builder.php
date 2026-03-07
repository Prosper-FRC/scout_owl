<?php
// === SERVER-SIDE PHP ===
error_reporting(E_ALL); // Enable error reporting for debugging
ini_set('display_errors', 1);

$gamesDir = __DIR__ . '/games';
$logosDir = __DIR__ . '/games/logos';

// --- CENTRAL POST REQUEST HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_reporting(0); // Suppress warnings for JSON output
    header('Content-Type: application/json');
    
    // Ensure base directories exist
    if (!is_dir($gamesDir)) { @mkdir($gamesDir, 0755, true); }
    if (!is_dir($logosDir)) { @mkdir($logosDir, 0755, true); }

    $action = $_POST['action'] ?? '';

    // --- ACTION 1: SAVE JSON DATA ---
    if ($action === 'save_json') {
        if (isset($_POST['jsondata']) && isset($_POST['filename'])) {
            $filename = basename($_POST['filename']);
            if (empty($filename)) {
                echo json_encode(['status' => 'error', 'message' => 'Filename is empty.']);
                exit;
            }
            $filePath = $gamesDir . '/' . $filename;
            if (file_put_contents($filePath, $_POST['jsondata'])) {
                echo json_encode(['status' => 'success', 'message' => 'Game saved to ' . $filename]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to save file. Check permissions.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Missing JSON data or filename.']);
        }
        exit;
    }

    // --- ACTION 2: UPLOAD BANNER IMAGE ---
    // Requires the 'gd' PHP extension to be enabled
    if ($action === 'upload_banner') {
        if (isset($_FILES['bannerFile']) && $_FILES['bannerFile']['error'] === UPLOAD_ERR_OK) {
            $gameName = $_POST['gameNameForBanner'] ?? '';
            if (empty($gameName)) {
                echo json_encode(['status' => 'error', 'message' => 'Game Name is missing.']);
                exit;
            }

            $tmpName = $_FILES['bannerFile']['tmp_name'];
            $imageInfo = @getimagesize($tmpName);
            if (!$imageInfo) {
                echo json_encode(['status' => 'error', 'message' => 'Uploaded file is not a valid image.']);
                exit;
            }

            $imageType = $imageInfo[2]; // e.g., IMAGETYPE_JPEG
            $imageResource = null;

            // Create an image resource from the uploaded file
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $imageResource = @imagecreatefromjpeg($tmpName);
                    break;
                case IMAGETYPE_GIF:
                    $imageResource = @imagecreatefromgif($tmpName);
                    break;
                case IMAGETYPE_PNG:
                    $imageResource = @imagecreatefrompng($tmpName);
                    break;
                case IMAGETYPE_WEBP:
                    $imageResource = @imagecreatefromwebp($tmpName);
                    break;
                default:
                    echo json_encode(['status' => 'error', 'message' => 'Unsupported image type. Please use PNG, JPG, GIF, or WEBP.']);
                    exit;
            }

            if (!$imageResource) {
                echo json_encode(['status' => 'error', 'message' => 'Failed to process image. It may be corrupt.']);
                exit;
            }
            
            // Define the final destination path
            // Sanitize game name for the filename
            $safeFileName = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $gameName);
            $destPath = $logosDir . '/' . $safeFileName . '.png';

            // Enable transparency for PNGs
            imagepalettetotruecolor($imageResource);
            imagealphablending($imageResource, true);
            imagesavealpha($imageResource, true);

            // Save the resource as a PNG, regardless of original type
            if (imagepng($imageResource, $destPath)) {
                imagedestroy($imageResource); // Free memory
                echo json_encode(['status' => 'success', 'message' => 'Banner uploaded and converted to ' . $safeFileName . '.png']);
            } else {
                imagedestroy($imageResource);
                echo json_encode(['status' => 'error', 'message' => 'Failed to save PNG. Check server permissions for games/logos/']);
            }
            
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No file uploaded or an upload error occurred.']);
        }
        exit;
    }
    
    // No valid action
    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
    exit;
}

// --- 2. LIST FILES (GET) REQUEST ---
// (This runs only on a GET request, as the POST handler always 'exit's)
$gameFiles = [];
if (is_dir($gamesDir)) {
    $files = glob($gamesDir . '/*.json');
    if ($files !== false) {
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($files as $f) {
            if (str_ends_with(basename($f), '.json')) {
                $gameFiles[] = basename($f);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FRC Scouting Config</title>
<style>
/* ==== Fonts ==== */
@font-face{font-family:'Roboto';src:url('/../stat_goblin/fonts/roboto/Roboto-Regular.ttf') format('ttf')}
@font-face{font-family:'Griffy';src:url('/../stat_goblin/fonts/Griffy/Griffy-Regular.ttf') format('ttf')}
@font-face{font-family:'Comfortaa';src:url('/../stat_goblin/fonts/Comfortaa/Comfortaa-VariableFont_wght.ttf') format('ttf')}

/* ==== THEME VARIABLES ==== */
:root {
    --bg-primary: #f4f4f4; --bg-secondary: #ffffff; --text-primary: #111111;
    --text-secondary: #555555; --border-color: #dddddd; --accent-color: #007bff;
    --red-alliance: #C0392B; --blue-alliance: #2C3E50; --neutral-border: #333333;
    --button-bg: #eeeeee; --button-bg-hover: #dddddd; --button-selected-bg: var(--accent-color);
    --button-selected-text: #ffffff; --input-bg: #ffffff; --input-border: #cccccc;
    --header-bg: #333333; --header-text: #ffffff;
    --status-success: #28a745; --status-error: #dc3545;
}
body.dark-mode {
    --bg-primary: #111111; --bg-secondary: #222222; --text-primary: #eeeeee;
    --text-secondary: #bbbbbb; --border-color: #444444; --accent-color: #0d6efd;
    --neutral-border: #cccccc; --button-bg: #333333; --button-bg-hover: #444444;
    --button-selected-bg: var(--accent-color); --button-selected-text: #ffffff;
    --input-bg: #2a2a2a; --input-border: #555555;
    --header-bg: #1a1a1a; --header-text: #eeeeee;
}

/* ==== Base Styles ==== */
body,html{ font-family:'Comfortaa', sans-serif; margin:0; padding:0; background-color: var(--bg-primary); color: var(--text-primary); transition: background-color 0.3s, color 0.3s; min-height: 100vh; }
h2 { color: var(--text-primary); margin-bottom: 15px; font-size: 1.5rem; }
h3 { margin-top: 25px; margin-bottom: 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;}
label { color: var(--text-secondary); display: block; margin-bottom: 5px; text-align: left; }

.container {
    padding: 20px;
    max-width: 900px;
    margin: 0 auto 20px auto;
    background-color: var(--bg-secondary);
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.logo-link { display: block; text-align: center; margin-bottom: 20px; }
.logo { max-width: 200px; height: auto; }

/* ==== Table Styles ==== */
table { width: 100%; border-collapse: collapse; margin-top: 15px; background-color: var(--bg-secondary); }
th, td { border: 1px solid var(--border-color); padding: 8px; text-align: center; vertical-align: middle; }
th { background-color: var(--header-bg); color: var(--header-text); font-weight: bold; }
td { color: var(--text-primary); }
tr:nth-child(even) { background-color: rgba(0,0,0,0.02); }
body.dark-mode tr:nth-child(even) { background-color: rgba(255,255,255,0.03); }

/* ==== Form Element Styles ==== */
input, select {
    width: 100%; box-sizing: border-box; padding: 6px 8px; border: 1px solid var(--input-border);
    border-radius: 4px; background-color: var(--input-bg); color: var(--text-primary); font-size: 0.9rem;
}
input[readonly] { background-color: rgba(0,0,0,0.05); }
body.dark-mode input[readonly] { background-color: rgba(255,255,255,0.05); }

/* ==== Button Styles ==== */
button {
    margin: 3px; cursor: pointer; padding: 6px 10px; border: none;
    border-radius: 4px; font-size: 0.9rem; transition: background-color 0.2s, box-shadow 0.2s;
    background-color: var(--button-bg); color: var(--text-primary); border: 1px solid var(--border-color);
}
button:hover { background-color: var(--button-bg-hover); box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
#addRow { background-color: var(--accent-color); color: white; border-color: var(--accent-color); }
#addRow:hover { background-color: #0056b3; border-color: #0056b3;}
#saveJSON { background-color: #28a745; color: white; border-color: #28a745; }
#saveJSON:hover { background-color: #1e7e34; border-color: #1e7e34;}
#uploadBannerBtn { background-color: #007bff; color: white; border-color: #007bff; }
#uploadBannerBtn:hover { background-color: #0056b3; border-color: #0056b3; }
td button { background-color: #6c757d; color: white; border-color: #6c757d; }
td button:hover { background-color: #5a6268; border-color: #5a6268; }
td button.drag-handle { background-color: #adb5bd; border-color: #adb5bd;}
td button.drag-handle:hover { background-color: #9098a0; border-color: #9098a0; }
td button:last-child { background-color: #dc3545; color: white; border-color: #dc3545;}
td button:last-child:hover { background-color: #b02a37; border-color: #b02a37; }

.import-group { margin-top: 20px; text-align: left; }
.import-group label { display: inline-block; margin-right: 10px; }

/* ==== Upload Form Styles ==== */
#uploadStatus {
    margin-top: 10px; font-size: 0.9rem; padding: 8px; border-radius: 4px;
    display: none; /* Hidden by default */
}
#uploadStatus.success {
    background-color: rgba(40, 167, 69, 0.1); color: var(--status-success); border: 1px solid rgba(40, 167, 69, 0.3);
}
#uploadStatus.error {
    background-color: rgba(220, 53, 69, 0.1); color: var(--status-error); border: 1px solid rgba(220, 53, 69, 0.3);
}

/* ==== Drag & Drop Styles ==== */
.drag-handle { cursor: grab; user-select: none; }
tr.dragging { opacity: 0.6; background: #cfe2ff; }
body.dark-mode tr.dragging { background: #0a2d57; }

/* ==== Theme Toggle Button ==== */
#themeToggle {
    position: fixed; bottom: 15px; right: 15px; background-color: var(--button-bg); color: var(--text-primary);
    border: 1px solid var(--border-color); border-radius: 50%; width: 40px; height: 40px; font-size: 1.2rem;
    cursor: pointer; z-index: 1000; display: flex; align-items: center; justify-content: center;
    transition: background-color 0.3s, color 0.3s, border-color 0.3s;
}
#themeToggle:hover { background-color: var(--button-bg-hover); }
</style>
</head>
<body>
<div class="container">
    <a href="../" class="logo-link">
        <img src="../images/thescoutowl.png" class="logo" alt="The Scout Owl Logo" id="mainLogo">
    </a>

    <h2>Game Configurator</h2>
    
    <label for="gameName">Game Name:</label>
    <input id="gameName" placeholder="e.g., 2025 Reefscape">
    
    <h3>Game Banner</h3>
    <form id="bannerUploadForm" enctype="multipart/form-data">
        <label for="bannerFile">Upload Banner (converts to PNG):</label>
        <input type="file" id="bannerFile" name="bannerFile" accept="image/png, image/jpeg, image/gif, image/webp">
        
        <input type="hidden" name="action" value="upload_banner">
        <input type="hidden" id="gameNameForBanner" name="gameNameForBanner">
        
        <button id="uploadBannerBtn" type="submit">Upload Banner</button>
        <div id="uploadStatus"></div>
    </form>
    
    <h3>Game Buttons</h3>
    <table id="buttonTable">
        <thead>
        <tr>
            <th>Name</th>
            <th>Code (auto)</th>
            <th>Type</th>
            <th>Location</th>
            <th>Layout</th>
            <th>Auton Pts</th>
            <th>Teleop Pts</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody></tbody>
    </table>
    <button id="addRow">+ Add Row</button>
    <br><br>

    <button id="saveJSON">Save JSON Config</button>

    <div class="import-group">
        <label for="importSelect">Import Config from Server:</label>
        <select id="importSelect">
            <option value="">-- Select a game to import --</option>
            <?php foreach ($gameFiles as $file): ?>
                <option value="<?= htmlspecialchars($file) ?>">
                    <?= htmlspecialchars(pathinfo($file, PATHINFO_FILENAME)) // Show filename without .json ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<button id="themeToggle" title="Toggle Light/Dark Mode">☀️</button>

<script>
// === THEME TOGGLE LOGIC ===
const themeToggle = document.getElementById('themeToggle');
const bodyElement = document.body;
const mainLogo = document.getElementById('mainLogo');
function applyTheme(theme) {
    if (theme === 'dark') {
        bodyElement.classList.add('dark-mode');
        themeToggle.textContent = '🌙';
        if (mainLogo) mainLogo.src = '../images/thescoutowl.png'; // Dark mode logo
        localStorage.setItem('configBuilderTheme', 'dark');
    } else {
        bodyElement.classList.remove('dark-mode');
        themeToggle.textContent = '☀️';
        if (mainLogo) mainLogo.src = '../images/thescoutowlb.png'; // Light mode logo
        localStorage.setItem('configBuilderTheme', 'light');
    }
}
const savedTheme = localStorage.getItem('configBuilderTheme') || 'light';
applyTheme(savedTheme);
themeToggle.addEventListener('click', () => {
    applyTheme(bodyElement.classList.contains('dark-mode') ? 'light' : 'dark');
});
// === END THEME TOGGLE LOGIC ===

// === DOM ELEMENTS ===
const tbody = document.querySelector('#buttonTable tbody');
const gameNameInput = document.getElementById('gameName');
const importSelect = document.getElementById('importSelect');
const bannerUploadForm = document.getElementById('bannerUploadForm');
const bannerFileInput = document.getElementById('bannerFile');
const gameNameForBannerInput = document.getElementById('gameNameForBanner');
const uploadStatus = document.getElementById('uploadStatus');

// === TABLE ROW FUNCTIONS ===
function addRow(data = {}) {
    const tr = document.createElement('tr');
    const isSel = (val, dVal) => val === dVal ? 'selected' : '';
    tr.innerHTML = `
        <td><input value="${data.name || ''}" oninput="autoCode(this)"></td>
        <td><input value="${data.code || ''}" readonly></td>
        <td> <select> <option value="offense" ${isSel('offense', data.type)}>Offense</option> <option value="defense" ${isSel('defense', data.type)}>Defense</option> <option value="cooperative" ${isSel('cooperative', data.type)}>Cooperative</option> </select> </td>
        <td><input value="${data.location || ''}"></td>
        <td> <select> <option value="rect-1" ${isSel('rect-1', data.layout || 'rect-1')}>1x Rectangle</option> <option value="rect-2" ${isSel('rect-2', data.layout)}>2x Rect (Half)</option> <option value="circle-2" ${isSel('circle-2', data.layout)}>2x Circle (Half)</option> <option value="square-1" ${isSel('square-1', data.layout)}>1x Square (Tall)</option> <option value="rect-full" ${isSel('rect-full', data.layout)}>Full Rect Group</option> <option value="circle-full" ${isSel('circle-full', data.layout)}>Full Circle Group</option> </select> </td>
        <td><input type="number" value="${data.autonPoints ?? 0}"></td>
        <td><input type="number" value="${data.teleopPoints ?? 0}"></td>
        <td> <button class="drag-handle" draggable="true">☰</button> <button onclick="duplicateRow(this)">⧉</button> <button onclick="deleteRow(this)">🗑️</button> </td>`;
    tbody.appendChild(tr);
}
function autoCode(el) {
    const row = el.closest('tr');
    row.children[1].firstElementChild.value = el.value.toLowerCase().replace(/\s+/g, '_');
}
function duplicateRow(btn) {
    const row = btn.closest('tr');
    const cells = row.querySelectorAll('input,select');
    const data = { name: cells[0].value, code: cells[1].value, type: cells[2].value, location: cells[3].value, layout: cells[4].value, autonPoints: +cells[5].value, teleopPoints: +cells[6].value };
    addRow(data);
}
function deleteRow(btn) { btn.closest('tr').remove(); }
document.getElementById('addRow').onclick = () => addRow();

// === BANNER UPLOAD LOGIC ===
bannerUploadForm.addEventListener('submit', function(e) {
    e.preventDefault(); // Stop normal form submission
    
    const gameName = gameNameInput.value.trim();
    if (!gameName) {
        alert('Please enter a Game Name before uploading a banner.');
        return;
    }
    if (bannerFileInput.files.length === 0) {
        alert('Please select an image file to upload.');
        return;
    }
    
    // Set the hidden game name field
    gameNameForBannerInput.value = gameName;
    
    // Show uploading status
    uploadStatus.textContent = 'Uploading and converting...';
    uploadStatus.className = ''; // Clear old classes
    uploadStatus.style.display = 'block';

    const formData = new FormData(this); // 'this' is the form
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.status === 'success') {
            uploadStatus.textContent = result.message;
            uploadStatus.className = 'success';
            bannerFileInput.value = ''; // Clear the file input
        } else {
            uploadStatus.textContent = 'Error: ' + result.message;
            uploadStatus.className = 'error';
        }
        // Hide status message after 5 seconds
        setTimeout(() => { uploadStatus.style.display = 'none'; }, 5000);
    })
    .catch(err => {
        console.error('Upload error:', err);
        uploadStatus.textContent = 'Upload failed. See console for details.';
        uploadStatus.className = 'error';
        setTimeout(() => { uploadStatus.style.display = 'none'; }, 5000);
    });
});

// === JSON SAVE LOGIC ===
document.getElementById('saveJSON').onclick = () => {
    const gameName = gameNameInput.value.trim();
    if (!gameName) {
        alert('Please enter a Game Name.');
        return;
    }
    
    const filename = `${gameName}.json`;
    const rows = [...tbody.children].map(r => {
        const i = r.querySelectorAll('input,select');
        return { name: i[0].value, code: i[1].value, type: i[2].value, location: i[3].value, layout: i[4].value, autonPoints: +i[5].value, teleopPoints: +i[6].value };
    });
    
    const data = JSON.stringify({ game: gameName, buttons: rows }, null, 2);
    
    const formData = new FormData();
    formData.append('action', 'save_json'); // <-- Specify the action
    formData.append('filename', filename);
    formData.append('jsondata', data);
    
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(response => response.json())
    .then(result => {
        if (result.status === 'success') {
            alert(result.message);
            // Check if the new file is in the dropdown, if not, add it
            if (![...importSelect.options].some(opt => opt.value === filename)) {
                const newOption = new Option(gameName, filename);
                importSelect.add(newOption);
                importSelect.value = filename; // Select the new one
            }
        } else {
            alert('Error: ' + result.message);
        }
    })
    .catch(err => console.error('Save error:', err));
};

// === JSON IMPORT LOGIC ===
importSelect.onchange = e => {
    const filename = e.target.value;
    if (!filename) {
        tbody.innerHTML = '';
        gameNameInput.value = '';
        return;
    }
    // Add cache-busting query parameter
    fetch('games/' + filename + '?t=' + new Date().getTime())
    .then(response => {
        if (!response.ok) throw new Error('File not found');
        return response.text();
    })
    .then(txt => {
        const data = JSON.parse(txt);
        tbody.innerHTML = '';
        (data.buttons || []).forEach(addRow);
        gameNameInput.value = data.game || '';
    })
    .catch(err => alert('Error loading file: ' + err.message));
};

// === DRAG-AND-DROP LOGIC ===
let draggedRow = null;
tbody.addEventListener('dragstart', e => {
    if (e.target.classList.contains('drag-handle')) {
        draggedRow = e.target.closest('tr');
        draggedRow.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    }
});
tbody.addEventListener('dragover', e => {
    e.preventDefault();
    const targetRow = e.target.closest('tr');
    if (draggedRow && targetRow && targetRow !== draggedRow) {
        const rect = targetRow.getBoundingClientRect();
        const midpoint = rect.top + rect.height / 2;
        if (e.clientY < midpoint) {
            tbody.insertBefore(draggedRow, targetRow);
        } else {
            tbody.insertBefore(draggedRow, targetRow.nextSibling);
        }
    }
});
tbody.addEventListener('drop', e => { e.preventDefault(); });
tbody.addEventListener('dragend', () => {
    if (draggedRow) {
        draggedRow.classList.remove('dragging');
        draggedRow = null;
    }
});

// === DEFAULT ROWS ===
if (tbody.children.length === 0) {
    addRow({name: "Picks Up Coral", code: "picks_up_coral", type: "offense", location: "station", layout: "rect-1", autonPoints: 0, teleopPoints: 0});
    addRow({name: "Scores Coral Lvl 1", code: "scores_coral_level_1", type: "offense", location: "reef", layout: "rect-1", autonPoints: 3, teleopPoints: 2});
    addRow({name: "Co-op Action", code: "co_op_action", type: "cooperative", location: "anywhere", layout: "rect-1", autonPoints: 0, teleopPoints: 0});
}
</script>
</body>
</html>