<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../php/database_connection.php'; // Ensure this path is correct

$event_name_from_post = ''; // Store event name across requests

// --- Part 1: Handle Final Submission of Edited Data ---
// This part remains mostly the same, receiving data from the editable table
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_edited_data'])) {
    $event_name = trim($_POST['event_name']); if (empty($event_name)) { die("Error: Event name missing."); }
    $matches = $_POST['match'] ?? []; $blue1s = $_POST['blue1'] ?? []; $blue2s = $_POST['blue2'] ?? []; $blue3s = $_POST['blue3'] ?? []; $red1s = $_POST['red1'] ?? []; $red2s = $_POST['red2'] ?? []; $red3s = $_POST['red3'] ?? [];
    $inserted_count = 0; $error_messages = [];
    if (count($matches) > 0) { $delete = $pdo->prepare("DELETE FROM active_event WHERE event_name = ?"); $insert = $pdo->prepare("INSERT INTO active_event (event_name, match_number, robot, alliance) VALUES (?, ?, ?, ?)"); $pdo->beginTransaction(); try { if (!$delete->execute([$event_name])) { throw new PDOException("Failed to delete existing data."); } for ($i = 0; $i < count($matches); $i++) { $match_num_raw = trim($matches[$i]); $match_num = filter_var($match_num_raw, FILTER_VALIDATE_INT); $b1_raw = trim($blue1s[$i]); $b1 = filter_var($b1_raw, FILTER_SANITIZE_NUMBER_INT); $b2_raw = trim($blue2s[$i]); $b2 = filter_var($b2_raw, FILTER_SANITIZE_NUMBER_INT); $b3_raw = trim($blue3s[$i]); $b3 = filter_var($b3_raw, FILTER_SANITIZE_NUMBER_INT); $r1_raw = trim($red1s[$i]);  $r1 = filter_var($r1_raw, FILTER_SANITIZE_NUMBER_INT); $r2_raw = trim($red2s[$i]);  $r2 = filter_var($r2_raw, FILTER_SANITIZE_NUMBER_INT); $r3_raw = trim($red3s[$i]);  $r3 = filter_var($r3_raw, FILTER_SANITIZE_NUMBER_INT); if ($match_num !== false && $match_num > 0) { $match_inserted_this_row = false; if (!empty($b1)) { $insert->execute([$event_name, $match_num, $b1, 'Blue']); $match_inserted_this_row = true;} if (!empty($b2)) { $insert->execute([$event_name, $match_num, $b2, 'Blue']); $match_inserted_this_row = true;} if (!empty($b3)) { $insert->execute([$event_name, $match_num, $b3, 'Blue']); $match_inserted_this_row = true;} if (!empty($r1)) { $insert->execute([$event_name, $match_num, $r1, 'Red']);  $match_inserted_this_row = true;} if (!empty($r2)) { $insert->execute([$event_name, $match_num, $r2, 'Red']);  $match_inserted_this_row = true;} if (!empty($r3)) { $insert->execute([$event_name, $match_num, $r3, 'Red']);  $match_inserted_this_row = true;} if ($match_inserted_this_row) { $inserted_count++; } elseif (empty($b1_raw.$b2_raw.$b3_raw.$r1_raw.$r2_raw.$r3_raw)) { /* Skip silently */ } else { $error_messages[] = "Row " . ($i + 1) . " (Match {$match_num_raw}): Invalid robot numbers."; } } elseif (!empty($match_num_raw) || !empty($b1_raw.$b2_raw.$b3_raw.$r1_raw.$r2_raw.$r3_raw)) { $error_messages[] = "Row " . ($i + 1) . ": Invalid/missing match number ('{$match_num_raw}')."; } } $pdo->commit(); echo "<!DOCTYPE html><html><head><title>Result</title><style>body{font-family: Arial;} .container{max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ccc;} h3.success{color: #28a745;} h3.error{color: #dc3545;} ul{list-style-type: none; padding-left: 0;} li{margin-bottom: 5px; color: #dc3545;} .button-link{display:inline-block; margin-top:15px; padding:10px 15px; background-color:#007bff; color:white; text-decoration:none; border-radius:4px;}</style></head><body><div class='container'>"; if ($inserted_count > 0) { echo "<h3 class='success'>✅ Successfully processed data for " . $inserted_count . " matches.</h3>"; } else { echo "<h3 class='error'>⚠️ No valid match data submitted.</h3>"; } if (!empty($error_messages)) { echo "<p><strong>Issues:</strong></p><ul>"; foreach ($error_messages as $msg) { echo "<li>" . htmlspecialchars($msg) . "</li>"; } echo "</ul>"; } echo '<p><a href="?" class="button-link">Upload another schedule</a></p>'; echo "</div></body></html>"; exit; } catch (PDOException $e) { $pdo->rollBack(); error_log("DB Error: " . $e->getMessage()); die("Database error. Check logs."); }
    } else { echo "<!DOCTYPE html><html><head><title>Result</title><style>/* styles */</style></head><body><div class='container'><h3 class='error'>⚠️ No match data rows submitted.</h3><p><a href='?' class='button-link'>Try Again</a></p></div></body></html>"; exit; }
}
// --- End Part 2 ---

// If not final submission, just display the page structure
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload and Parse Match Schedule (Client-Side OCR)</title>
    <link rel="stylesheet" href="https://unpkg.com/cropperjs@1.5.13/dist/cropper.min.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; background-color: #f4f4f4; color: #333;}
        .container { max-width: 900px; margin: 20px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1, h3 { text-align: center; color: #333; }
        form { display: flex; flex-direction: column; gap: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        input[type="text"], input[type="file"], button { padding: 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; box-sizing: border-box; }
        input[type="file"] { border: none; }
        button[type="submit"], button[type="button"], .button-link { background-color: #007bff; color: white; cursor: pointer; transition: background-color 0.3s ease; border: none; font-weight: bold; text-align: center; text-decoration: none; display: inline-block;}
        button[type="submit"]:hover, button[type="button"]:hover, .button-link:hover { background-color: #0056b3; }
        #previewContainer { max-width: 100%; margin-top: 15px; border: 1px solid #ddd; display: none; }
        #preview { display: block; max-width: 100%; height: auto; }
        h3.success { color: #28a745; }
        h4 { margin-top: 20px; color: #555; }
        pre { background-color: #eee; padding: 15px; border-radius: 4px; border: 1px solid #ccc; white-space: pre-wrap; word-wrap: break-word; font-family: monospace; max-height: 200px; overflow-y: auto;}

        /* Editable Table Styles */
        #editSection { margin-top: 30px; border-top: 2px solid #eee; padding-top: 20px; }
        #editableScheduleTable { width: 100%; border-collapse: collapse; margin-top: 15px; table-layout: fixed; }
        #editableScheduleTable th, #editableScheduleTable td { border: 1px solid #ccc; padding: 8px; text-align: center; }
        #editableScheduleTable th input[type="text"] { font-weight: bold; background-color: #f0f0f0; border: none; width: 95%; box-sizing: border-box; text-align: center;}
        #editableScheduleTable td input[type="text"] { width: 95%; padding: 5px; border: 1px solid #ddd; border-radius: 3px; text-align: center; box-sizing: border-box; }
        #editableScheduleTable th:first-child, #editableScheduleTable td:first-child { width: 12%; }
        #editableScheduleTable th:not(:first-child), #editableScheduleTable td:not(:first-child) { width: 14.6%; }

        #submitEditedData { background-color: #28a745; margin-top: 15px; width: 100%; }
        #submitEditedData:hover { background-color: #1e7e34; }
        #addRowButton { background-color: #6c757d; margin-top: 10px; width: auto; padding: 8px 15px; }
        #addRowButton:hover { background-color: #5a6268; }
        .hidden { display: none; }
        #ocrStatus { text-align: center; font-weight: bold; margin-top: 15px; display: none; }
        #runOcrButton { background-color: #ffc107; color: #333; margin-top: 10px; display: none; } /* Yellow OCR button */
        #runOcrButton:hover { background-color: #e0a800; }
    </style>
    <script src='https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js'></script>
</head>
<body>
<div class="container">
    <h1>Upload and Parse Match Schedule (Client-Side OCR)</h1>

    <form id="uploadForm"> <div>
            <label for="event_name_upload">Event Name:</label>
            <input type="text" id="event_name_upload" name="event_name" placeholder="e.g. NTX Tournament" required>
        </div>
        <div>
            <label for="imageInput">Select image (JPG/PNG):</label>
            <input type="file" name="image" id="imageInput" accept="image/jpeg, image/png" required>
        </div>
        <div id="previewContainer">
             <img id="preview" alt="Image preview">
        </div>
        <button type="button" id="runOcrButton">Run OCR on Cropped Area</button>
    </form>

    <div id="ocrStatus">Initializing Tesseract.js...</div>

    <div id="editSection" class="hidden">
        <h3>Review and Edit Schedule</h3>
        <p>Event: <strong id="editEventNameDisplay"></strong></p>

        <form method="POST" id="editForm"> <input type="hidden" name="event_name" id="finalEventNameInput">
            <input type="hidden" name="submit_edited_data" value="1">

            <table id="editableScheduleTable">
                <thead>
                    <tr>
                        <th><input type="text" name="header_match" value="Match" readonly style="background:#eee; font-weight:bold; border:none;"></th>
                        <th><input type="text" name="header_blue1" value="Blue 1" style="font-weight:bold; border:none; background:none;"></th>
                        <th><input type="text" name="header_blue2" value="Blue 2" style="font-weight:bold; border:none; background:none;"></th>
                        <th><input type="text" name="header_blue3" value="Blue 3" style="font-weight:bold; border:none; background:none;"></th>
                        <th><input type="text" name="header_red1" value="Red 1" style="font-weight:bold; border:none; background:none;"></th>
                        <th><input type="text" name="header_red2" value="Red 2" style="font-weight:bold; border:none; background:none;"></th>
                        <th><input type="text" name="header_red3" value="Red 3" style="font-weight:bold; border:none; background:none;"></th>
                    </tr>
                </thead>
                <tbody>
                    </tbody>
            </table>
            <button type="button" id="addRowButton">+ Add Match Row</button>
            <button type="submit" id="submitEditedData">Submit Corrected Data to Database</button>
        </form>

        <h4>Raw Tesseract Output:</h4>
        <pre id="rawOcrOutput"></pre>
    </div>

</div>

<script src="https://unpkg.com/cropperjs@1.5.13/dist/cropper.min.js"></script>
<script>
    // --- Global Variables ---
    let cropper;
    const imageInput = document.getElementById('imageInput');
    const preview = document.getElementById('preview');
    const previewContainer = document.getElementById('previewContainer');
    const runOcrButton = document.getElementById('runOcrButton');
    const ocrStatus = document.getElementById('ocrStatus');
    const editSection = document.getElementById('editSection');
    const editEventNameDisplay = document.getElementById('editEventNameDisplay');
    const finalEventNameInput = document.getElementById('finalEventNameInput');
    const tableBody = document.querySelector('#editableScheduleTable tbody');
    const addRowButton = document.getElementById('addRowButton');
    const rawOcrOutputPre = document.getElementById('rawOcrOutput');
    const uploadForm = document.getElementById('uploadForm'); // Need reference to hide later

    // --- Image Loading and Cropper Initialization ---
    imageInput.addEventListener('change', e => {
        const file = e.target.files[0];
        runOcrButton.style.display = 'none'; // Hide OCR button until image loaded
        editSection.classList.add('hidden'); // Hide edit section
        ocrStatus.style.display = 'none';    // Hide status
        if (!file || !['image/jpeg', 'image/png'].includes(file.type)) {
            alert('Please select a valid JPG or PNG image.');
            preview.src = ''; previewContainer.style.display = 'none'; if (cropper) cropper.destroy();
            return;
        }
        const reader = new FileReader();
        reader.onload = (event) => {
            preview.src = event.target.result;
            previewContainer.style.display = 'block';
            if (cropper) { cropper.destroy(); }
            cropper = new Cropper(preview, { aspectRatio: NaN, viewMode: 1, background: false, autoCropArea: 0.9 });
            runOcrButton.style.display = 'block'; // Show OCR button
        };
        reader.readAsDataURL(file);
    });

// --- Run OCR Button Click ---
runOcrButton.addEventListener('click', async () => {
    // --- MOVED eventName DEFINITION HERE ---
    const eventName = document.getElementById('event_name_upload').value.trim();
    const imageFile = imageInput.files[0]; // Get file early too

    // --- Validation ---
    if (!eventName) {
        alert('Please enter an Event Name.');
        return;
    }
     if (!imageFile) { // Check if image file exists
        alert('Please select an image file.');
        return;
    }
    if (!cropper) {
        alert('Cropper not initialized. Please re-select image.');
        return;
    }
    // --- End Validation ---


    ocrStatus.textContent = 'Processing OCR... Please wait.';
    ocrStatus.style.display = 'block';
    runOcrButton.disabled = true;

    try {
        const croppedCanvas = cropper.getCroppedCanvas();
        if (!croppedCanvas) { throw new Error('Could not get cropped canvas.'); }
        const imageDataUrl = croppedCanvas.toDataURL('image/png');

        // --- TESSERACT INITIALIZATION ---
        const worker = await Tesseract.createWorker('eng', 1, {
             logger: m => console.log(m.status, m.progress),
        });
        // --- END TESSERACT ---

       const imageData = croppedCanvas.toDataURL("image/png");
const form = new FormData();
form.append("cropped_image", imageData);
form.append("event_name", eventName);

const response = await fetch("schedule_ocr.php", { method: "POST", body: form });
const text = await response.text(); // plain OCR text

        await worker.terminate();

        // --- Parse OCR Text ---
        const parsedData = parseOcrText(text);

        // --- Display Results ---
        // Now eventName is defined and can be used safely
         if(editEventNameDisplay) editEventNameDisplay.textContent = eventName;
         if(finalEventNameInput) finalEventNameInput.value = eventName;
         if(rawOcrOutputPre) rawOcrOutputPre.textContent = text;
         if(tableBody) tableBody.innerHTML = ''; // Clear previous results

         if (parsedData.length > 0) {
             parsedData.forEach(match => addTableRow(match));
         } else {
             addTableRow({});
             alert("OCR processing finished, but couldn't parse structured match data. Please check raw output and edit the table manually.");
         }
         if(editSection) editSection.classList.remove('hidden');
         if(uploadForm) uploadForm.classList.add('hidden');
         if(ocrStatus) ocrStatus.style.display = 'none';


    } catch (error) {
        console.error('Error during Tesseract.js OCR:', error);
        alert(`OCR Error: ${error.message || error}`);
        if(ocrStatus) ocrStatus.textContent = 'OCR Failed.';
    } finally {
        runOcrButton.disabled = false; // Re-enable button
    }
});

    // --- Client-Side OCR Parsing Function ---
    function parseOcrText(ocrText) {
        const scheduleData = [];
        const lines = ocrText.trim().split('\n');
        let current_match = null;

        lines.forEach(line => {
            line = line.trim();
            if (!line) return;
            const matchResult = line.match(/(?:Quals?|Qualification)\s+(\d+)/i);
            if (matchResult && matchResult[1]) {
                 current_match = parseInt(matchResult[1], 10);
            }
            const parts = line.split(/\s+/);
            const robot_numbers = parts.filter(part => /^\d{2,5}$/.test(part.trim()));
            if (robot_numbers.length === 6 && current_match !== null) {
                scheduleData.push({ match: current_match, blue1: robot_numbers[0] || '', blue2: robot_numbers[1] || '', blue3: robot_numbers[2] || '', red1:  robot_numbers[3] || '', red2:  robot_numbers[4] || '', red3:  robot_numbers[5] || '' });
                current_match = null; // Reset match number
            }
        });
        return scheduleData;
    }

    // --- Add Row Button Logic ---
    if(addRowButton && tableBody) {
        addRowButton.addEventListener('click', () => { addTableRow({}); });
    }

    // --- Helper function to add a row to the table ---
    function addTableRow(matchData) {
        if (!tableBody) return;
        const row = tableBody.insertRow();
        row.innerHTML = `
            <td><input type="text" name="match[]" value="${matchData.match || ''}" size="4"></td>
            <td><input type="text" name="blue1[]" value="${matchData.blue1 || ''}" size="6"></td>
            <td><input type="text" name="blue2[]" value="${matchData.blue2 || ''}" size="6"></td>
            <td><input type="text" name="blue3[]" value="${matchData.blue3 || ''}" size="6"></td>
            <td><input type="text" name="red1[]" value="${matchData.red1 || ''}" size="6"></td>
            <td><input type="text" name="red2[]" value="${matchData.red2 || ''}" size="6"></td>
            <td><input type="text" name="red3[]" value="${matchData.red3 || ''}" size="6"></td>
        `;
    }

</script>
</body>
</html>