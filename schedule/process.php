<?php
$data = json_decode(file_get_contents("php://input"), true);
if (empty($data['image'])) {
  http_response_code(400);
  exit("Missing image");
}

$tmp = sys_get_temp_dir()."/schedule_crop.png";
file_put_contents($tmp, base64_decode($data['image']));

$enhanced = sys_get_temp_dir()."/schedule_enhanced.png";
shell_exec(sprintf(
  'magick %s -resize 300%% -colorspace Gray -level 25%%,75%% -sharpen 0x1 %s 2>&1',
  escapeshellarg($tmp), escapeshellarg($enhanced)
));

$project   = "YOUR_PROJECT_ID";
$location  = "us";
$processor = "YOUR_PROCESSOR_ID";
$accessToken = trim(shell_exec("gcloud auth application-default print-access-token"));
$imageData = file_get_contents($enhanced);
$payload = json_encode([
  "rawDocument" => [
    "content"  => base64_encode($imageData),
    "mimeType" => "image/png"
  ]
]);

$ch = curl_init("https://$location-documentai.googleapis.com/v1/projects/$project/locations/$location/processors/$processor:process");
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => [
    "Authorization: Bearer $accessToken",
    "Content-Type: application/json"
  ],
  CURLOPT_POSTFIELDS => $payload
]);
$response = curl_exec($ch);
curl_close($ch);
$res = json_decode($response, true);

$rows=[];
foreach ($res['document']['pages'][0]['tables'] ?? [] as $table) {
  foreach ($table['bodyRows'] as $r) {
    $row=[];
    foreach ($r['cells'] as $c)
      $row[] = htmlspecialchars($c['layout']['textAnchor']['content'] ?? '');
    $rows[]=$row;
  }
}

$html="<table contenteditable='true'>";
foreach($rows as $r){
  $html.="<tr>";
  foreach($r as $c) $html.="<td>$c</td>";
  $html.="</tr>";
}
$html.="</table>";

header('Content-Type: application/json');
echo json_encode([
  "image" => "data:image/png;base64,".base64_encode(file_get_contents($enhanced)),
  "tableHtml" => $html
]);