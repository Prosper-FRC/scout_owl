<?php
//save_table.php

$data = json_decode(file_get_contents("php://input"), true);
if (empty($data['html'])) {
  http_response_code(400);
  exit("No table HTML received");
}

$html = $data['html'];

// --- Convert HTML table to CSV ---
$dom = new DOMDocument();
@$dom->loadHTML($html);
$rows = $dom->getElementsByTagName('tr');

$csv = "";
foreach ($rows as $row) {
  $cells = $row->getElementsByTagName('td');
  $vals = [];
  foreach ($cells as $cell)
    $vals[] = trim($cell->textContent);
  $csv .= implode(',', $vals) . "\n";
}
$file = __DIR__ . "/saved_table.csv";
file_put_contents($file, $csv);

// --- Optional: also store to MySQL ---
/*
$pdo = new PDO("mysql:host=localhost;dbname=stat_owl","root","pw123456");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE IF NOT EXISTS schedule (id INT AUTO_INCREMENT PRIMARY KEY, row_data TEXT)");
$stmt = $pdo->prepare("INSERT INTO schedule (row_data) VALUES (:row)");
foreach (explode("\n",$csv) as $line) {
  if(trim($line)!=='') $stmt->execute(['row'=>$line]);
}
*/

echo "Saved table to saved_table.csv successfully.";