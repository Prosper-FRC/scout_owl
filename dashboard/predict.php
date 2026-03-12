<?php
// predict.php

// Get parameters from the query string
$event_name = isset($_GET['event_name']) ? $_GET['event_name'] : '';
$match_no = isset($_GET['match_no']) ? $_GET['match_no'] : '';
$blue_alliance = isset($_GET['blue_alliance']) ? $_GET['blue_alliance'] : '';
$red_alliance = isset($_GET['red_alliance']) ? $_GET['red_alliance'] : '';
$hist_weight = isset($_GET['hist_weight']) ? $_GET['hist_weight'] : 0.1;
$hist_weight = .1;

// Validate required parameters
if (empty($event_name) || empty($match_no) || empty($blue_alliance) || empty($red_alliance)) {
    echo json_encode(["error" => "Missing required parameters."]);
    exit;
}

// Build the Python API URL (adjust port if necessary)
$api_url = "https://theconspiracyshirtcompany.com/predict?event_name=" . urlencode($event_name) .
           "&match_no=" . urlencode($match_no) .
           "&blue_alliance=" . urlencode($blue_alliance) .
           "&red_alliance=" . urlencode($red_alliance) .
           "&hist_weight=" . urlencode($hist_weight);

// Initialize cURL and get API response
$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
if(curl_errno($ch)){
    echo json_encode(["error" => "cURL Error: " . curl_error($ch)]);
    exit;
}
curl_close($ch);

// --- DEBUG: Show raw response from Python ---
echo $response;
exit;
// --- END DEBUG ---

// Decode and return the JSON response from the Python API
// $result = json_decode($response, true);
// if (json_last_error() !== JSON_ERROR_NONE) {
//     echo json_encode(["error" => "JSON Decode Error: " . json_last_error_msg()]);
//     exit;
// }

// echo json_encode($result);
?>
