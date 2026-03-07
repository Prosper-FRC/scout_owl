<?php
require '../php/database_connection.php'; // Ensure path is correct

// Basic Input Validation
if (empty($_POST['cropped_image'])) {
    http_response_code(400);
    exit("Error: Missing cropped image data.");
}
// Event name is optional for OCR, might be needed later if saving here
$event = isset($_POST['event_name']) ? trim($_POST['event_name']) : 'Unknown Event';

// Decode Image Data
$img_data = $_POST['cropped_image'];
if (strpos($img_data, 'data:image/png;base64,') !== 0) {
     http_response_code(400);
     exit("Error: Invalid image data format.");
}
$img = str_replace('data:image/png;base64,', '', $img_data);
$img = base64_decode($img);
if ($img === false) {
    http_response_code(400);
    exit("Error: Failed to decode base64 image data.");
}

// Prepare File Paths
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        http_response_code(500);
        exit("Error: Failed to create upload directory. Check permissions.");
    }
}
$input_file = $uploadDir . 'ocr_input_' . time() . '.png';
$clean_file = $uploadDir . 'ocr_clean_' . time() . '.png'; // Preprocessed file path

// Save Cropped Image
if (!file_put_contents($input_file, $img)) {
    http_response_code(500);
    exit("Error: Failed to save image file. Check permissions for " . $uploadDir);
}

// --- ImageMagick Preprocessing ---
// Normalize is generally safe and helpful for contrast. Thresholding can be risky.
// Let's try normalizing first. If results are still bad, we can add more steps.
$convert_cmd = "convert " . escapeshellarg($input_file) . 
               " -colorspace Gray -normalize " . // Grayscale and normalize contrast
               escapeshellarg($clean_file);
shell_exec($convert_cmd);

// Check if preprocessing created the file
if (!file_exists($clean_file)) {
    // If preprocessing failed, try OCR on the original cropped image
    error_log("ImageMagick preprocessing failed. Trying OCR on original cropped file.");
    $clean_file = $input_file; // Use original as fallback
}

// --- Run Tesseract ---
// Use PSM 3 (Auto Page Segmentation) - Often better for tables than PSM 6
// REMOVED the restrictive whitelist completely
$tesseract_cmd = "tesseract " . escapeshellarg($clean_file) . " stdout --psm 3 -l eng";
$raw_output = shell_exec($tesseract_cmd);

// --- Cleanup Temporary Files ---
if (file_exists($input_file)) { unlink($input_file); }
if (file_exists($clean_file) && $clean_file !== $input_file) { unlink($clean_file); } // Don't delete if it's the input

// --- Return Raw Text ---
if ($raw_output === null) {
    http_response_code(500);
    // Check if Tesseract is installed and in PATH
    exit("Error: Failed to execute Tesseract command. Is it installed and configured correctly?");
}

// Set header to indicate plain text response
header('Content-Type: text/plain');
echo trim($raw_output); // Return the raw text
?>