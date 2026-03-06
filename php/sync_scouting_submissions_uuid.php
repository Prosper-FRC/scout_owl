<?php
/**
 * sync_scouting_submissions_uuid.php
 *
 * Sync LOCAL -> HOSTED
 * - Only LOCAL rows where (uploaded IS NULL OR uploaded = 0)
 * - Only rows whose uuid does NOT already exist on HOSTED
 * - Batch-based for speed
 *
 * Returns JSON:
 *   success, message, local_rows, to_insert, hosted_affected, local_marked_uploaded
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

$LOCAL_CONN_FILE  = __DIR__ . '/local_connection.php';   // must define $pdo
$HOSTED_CONN_FILE = __DIR__ . '/hosted_connection.php';  // must define $pdo

$TABLE = 'scouting_submissions';

// Bigger batch sizes = fewer round trips (usually faster)
$SELECT_BATCH_SIZE = 5000;   // read this many candidate local rows at a time
$UUID_CHECK_BATCH  = 5000;   // check these uuids on hosted at a time
$INSERT_BATCH_SIZE = 1500;   // rows per multi-row INSERT (avoid giant SQL)
$MARK_LOCAL_UPLOADED = true;

function jsonFail(string $message, int $http = 500): void {
  http_response_code($http);
  echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_SLASHES);
  exit;
}
function jsonOk(array $payload): void {
  http_response_code(200);
  echo json_encode($payload, JSON_UNESCAPED_SLASHES);
  exit;
}
function requirePdo(string $file): PDO {
  if (!is_file($file)) throw new RuntimeException("Connection file not found: {$file}");
  require $file;
  if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException("{$file} must define \$pdo as a PDO instance.");
  }
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  return $pdo;
}
function q(string $ident): string {
  return '`' . str_replace('`', '``', $ident) . '`';
}
function columns(PDO $pdo, string $table): array {
  $rows = $pdo->query("SHOW COLUMNS FROM " . q($table))->fetchAll();
  if (!$rows) return [];
  return array_map(fn($r) => $r['Field'], $rows);
}
function chunk(array $arr, int $size): array {
  $out = [];
  for ($i=0; $i<count($arr); $i += $size) $out[] = array_slice($arr, $i, $size);
  return $out;
}

$local = null;
$hosted = null;

try {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    jsonFail('Use POST.', 405);
  }

  $local  = requirePdo($LOCAL_CONN_FILE);
  $hosted = requirePdo($HOSTED_CONN_FILE);

  $localCols  = columns($local, $TABLE);
  $hostedCols = columns($hosted, $TABLE);

  if (!$localCols)  throw new RuntimeException("Local table not found: {$TABLE}");
  if (!$hostedCols) throw new RuntimeException("Hosted table not found: {$TABLE}");

  if (!in_array('uuid', $localCols, true))  throw new RuntimeException("Local {$TABLE} missing uuid column.");
  if (!in_array('uuid', $hostedCols, true)) throw new RuntimeException("Hosted {$TABLE} missing uuid column.");

  $hasUploaded = in_array('uploaded', $localCols, true);
  if (!$hasUploaded) {
    throw new RuntimeException("Local {$TABLE} missing uploaded column (required for this mode).");
  }

  // Sync only columns that exist in BOTH, excluding auto/managed fields.
  $commonCols = array_values(array_intersect($localCols, $hostedCols));

  // Do NOT sync id/timestamp/uploaded (uploaded is local-side state)
  $colsToSync = array_values(array_diff($commonCols, ['id', 'timestamp', 'uploaded']));

  // Ensure uuid is included
  if (!in_array('uuid', $colsToSync, true)) $colsToSync[] = 'uuid';

  // Put uuid first for readability
  usort($colsToSync, function($a, $b){
    if ($a === 'uuid') return -1;
    if ($b === 'uuid') return 1;
    return strcmp($a, $b);
  });

  $colListSql = implode(',', array_map('q', $colsToSync));

  // Count total candidates (not uploaded)
  $countSql = "SELECT COUNT(*) AS c
               FROM " . q($TABLE) . "
               WHERE (uploaded IS NULL OR uploaded = 0)
                 AND uuid IS NOT NULL
                 AND uuid <> ''";
  $candidateCount = (int)$local->query($countSql)->fetchColumn();

  if ($candidateCount === 0) {
    jsonOk([
      'success' => true,
      'message' => 'Nothing to sync.',
      'local_rows' => 0,
      'to_insert' => 0,
      'hosted_affected' => 0,
      'local_marked_uploaded' => 0,
    ]);
  }

  $hosted->beginTransaction();

  $scanned = 0;         // candidates scanned from local
  $toInsertTotal = 0;   // candidates not found on hosted
  $insertedTotal = 0;   // rows inserted (rowCount)
  $markedLocalTotal = 0;

  // Page through local candidates using id cursor for stability
  $lastId = 0;

  while (true) {
    $selSql = "SELECT " . $colListSql . ", " . q('id') . " AS __local_id
               FROM " . q($TABLE) . "
               WHERE (uploaded IS NULL OR uploaded = 0)
                 AND uuid IS NOT NULL
                 AND uuid <> ''
                 AND id > :last_id
               ORDER BY id ASC
               LIMIT {$SELECT_BATCH_SIZE}";
    $sel = $local->prepare($selSql);
    $sel->execute([':last_id' => $lastId]);
    $batchRows = $sel->fetchAll();

    if (!$batchRows) break;

    // advance cursor
    $lastId = (int)end($batchRows)['__local_id'];

    $scanned += count($batchRows);

    // Extract UUIDs from this local batch
    $uuids = [];
    foreach ($batchRows as $r) {
      if (!empty($r['uuid'])) $uuids[] = (string)$r['uuid'];
    }
    $uuids = array_values(array_unique($uuids));
    if (!$uuids) continue;

    // Find which of these uuids already exist on hosted
    $existing = [];
    foreach (chunk($uuids, $UUID_CHECK_BATCH) as $uuidChunk) {
      $ph = [];
      $p = [];
      foreach ($uuidChunk as $i => $u) {
        $k = ":u{$i}";
        $ph[] = $k;
        $p[$k] = $u;
      }
      $chkSql = "SELECT uuid FROM " . q($TABLE) . " WHERE uuid IN (" . implode(',', $ph) . ")";
      $chk = $hosted->prepare($chkSql);
      $chk->execute($p);
      foreach ($chk->fetchAll() as $row) {
        $existing[(string)$row['uuid']] = true;
      }
    }

    // Filter local rows to only those not on hosted
    $newRows = [];
    $newUuids = [];
    foreach ($batchRows as $r) {
      $u = (string)($r['uuid'] ?? '');
      if ($u === '' || isset($existing[$u])) continue;

      // strip helper column
      unset($r['__local_id']);
      $newRows[] = $r;
      $newUuids[] = $u;
    }

    if (!$newRows) continue;

    $toInsertTotal += count($newRows);

    // Insert in smaller chunks to keep SQL size sane
    foreach (chunk($newRows, $INSERT_BATCH_SIZE) as $insRows) {
      $valueGroups = [];
      $binds = [];
      $pi = 0;

      foreach ($insRows as $r) {
        $phRow = [];
        foreach ($colsToSync as $c) {
          $k = ":p{$pi}";
          $phRow[] = $k;
          $binds[$k] = $r[$c] ?? null;
          $pi++;
        }
        $valueGroups[] = '(' . implode(',', $phRow) . ')';
      }

      $insSql = "INSERT INTO " . q($TABLE) . " ({$colListSql}) VALUES " . implode(',', $valueGroups);
      $ins = $hosted->prepare($insSql);
      $ins->execute($binds);
      $insertedTotal += $ins->rowCount();
    }

    // Mark uploaded for rows we inserted
    if ($MARK_LOCAL_UPLOADED) {
      $newUuids = array_values(array_unique($newUuids));
      foreach (chunk($newUuids, 900) as $uuidChunk) {
        $ph = [];
        $p = [];
        foreach ($uuidChunk as $i => $u) {
          $k = ":mu{$i}";
          $ph[] = $k;
          $p[$k] = $u;
        }
        $updSql = "UPDATE " . q($TABLE) . " SET uploaded = 1 WHERE uuid IN (" . implode(',', $ph) . ")";
        $st = $local->prepare($updSql);
        $st->execute($p);
        $markedLocalTotal += $st->rowCount();
      }
    }
  }

  $hosted->commit();

  jsonOk([
    'success' => true,
    'message' => 'Sync completed.',
    // local_rows should be a number your UI expects
    'local_rows' => $scanned,             // candidates scanned (uploaded null/0)
    'to_insert' => $toInsertTotal,        // actually missing on hosted
    // keep the name your UI expects
    'hosted_affected' => $insertedTotal,  // inserted rows
    'local_marked_uploaded' => $markedLocalTotal,
  ]);

} catch (Throwable $e) {
  try { if ($hosted instanceof PDO && $hosted->inTransaction()) $hosted->rollBack(); } catch (Throwable $ignored) {}
  // local updates are per-batch; if you want strict atomicity, move local marking to after hosted commit.
  error_log("SYNC ERROR: " . $e->getMessage());
  jsonFail($e->getMessage(), 500);
}