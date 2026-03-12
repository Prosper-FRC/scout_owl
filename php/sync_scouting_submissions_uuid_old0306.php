<?php
/**
 * sync_scouting_submissions_uuid.php
 *
 * PURPOSE
 *   Sync scouting_submissions rows from LOCAL database -> HOSTED database.
 *   Uses uuid (unique) for perfect de-duplication / upsert.
 *
 * HOW IT WORKS
 *   1) Read rows from LOCAL filtered by:
 *        event_name = POST[event]   (required)
 *        match_no   = POST[match_number] (optional)
 *        game       = POST[game] (optional)
 *      If local table has an 'uploaded' column, it syncs only rows where uploaded is NULL or 0.
 *
 *   2) Insert into HOSTED using:
 *        INSERT ... ON DUPLICATE KEY UPDATE ...
 *      This requires HOSTED has UNIQUE(uuid).
 *
 *   3) Optionally mark LOCAL rows uploaded=1 after successful sync.
 *
 * REQUEST
 *   POST fields:
 *     event (required)          -> filters event_name
 *     match_number (optional)   -> filters match_no
 *     game (optional)           -> filters game
 *
 * RESPONSE (JSON)
 *   success: true/false
 *   message: string
 *   local_rows: number of local rows selected
 *   hosted_affected: MySQL "affected rows" count for upsert operations (see note below)
 *
 * NOTE ABOUT hosted_affected
 *   MySQL upsert rowCount() semantics:
 *     - insert often counts as 1
 *     - update that changes values often counts as 2
 *     - update that doesn't change values can count as 0
 *
 * REQUIREMENTS
 *   - Both DBs are MySQL/MariaDB
 *   - Both tables have: uuid CHAR(36) and UNIQUE KEY (uuid)
 *   - Connection files MUST create: $pdo (PDO instance)
 *
 * SECURITY
 *   - There is NO auth here. Do not expose publicly.
 */

declare(strict_types=1);

// Helpful while building/debugging; you can turn off later
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Return JSON so your UI can show success/failure
header('Content-Type: application/json; charset=utf-8');

/* -----------------------------------------------------------
   CONFIG
   ----------------------------------------------------------- */

// These connection files must define: $pdo (PDO instance)
$LOCAL_CONN_FILE  = __DIR__ . '/local_connection.php';
$HOSTED_CONN_FILE = __DIR__ . '/database_connection.php';

// Table to sync
$TABLE = 'scouting_submissions';

// How many rows to upsert per statement (avoid huge SQL)
$BATCH_SIZE = 500;

// Mark local uploaded=1 after sync (only if local table has uploaded column)
$MARK_LOCAL_UPLOADED = true;

/* -----------------------------------------------------------
   HELPER FUNCTIONS
   ----------------------------------------------------------- */

/**
 * Load a PDO connection from a PHP file that defines $pdo.
 */
function requirePdo(string $file): PDO {
  if (!is_file($file)) {
    throw new RuntimeException("Connection file not found: {$file}");
  }

  require $file;

  if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException("{$file} must define \$pdo as a PDO instance.");
  }

  // Throw exceptions on SQL errors
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  // Use real prepares
  $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

  return $pdo;
}

/**
 * Quote a MySQL identifier (table or column name) with backticks.
 * DO NOT use this for values.
 */
function q(string $ident): string {
  return '`' . str_replace('`', '``', $ident) . '`';
}

/**
 * Return a list of column names in a table.
 */
function columns(PDO $pdo, string $table): array {
  $rows = $pdo->query("SHOW COLUMNS FROM " . q($table))->fetchAll(PDO::FETCH_ASSOC);
  if (!$rows) return [];
  return array_map(fn($r) => $r['Field'], $rows);
}

/**
 * Split an array into chunks of size $size.
 */
function chunk(array $rows, int $size): array {
  $out = [];
  for ($i = 0; $i < count($rows); $i += $size) {
    $out[] = array_slice($rows, $i, $size);
  }
  return $out;
}

/* -----------------------------------------------------------
   MAIN
   ----------------------------------------------------------- */

try {
  // Only allow POST requests
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    throw new RuntimeException("Use POST.");
  }

  // Read POST inputs
  $event = trim((string)($_POST['event'] ?? ''));
  $match_number = isset($_POST['match_number']) ? trim((string)$_POST['match_number']) : '';
  $game = isset($_POST['game']) ? trim((string)$_POST['game']) : '';

  if ($event === '') {
    throw new RuntimeException("POST 'event' is required.");
  }

  // Connect to both databases
  $local  = requirePdo($LOCAL_CONN_FILE);
  $hosted = requirePdo($HOSTED_CONN_FILE);
  echo " $hosted";
  // Get column lists from both tables
  $localCols  = columns($local, $TABLE);
  $hostedCols = columns($hosted, $TABLE);

  if (!$localCols)  throw new RuntimeException("Local table not found: {$TABLE}");
  if (!$hostedCols) throw new RuntimeException("Hosted table not found: {$TABLE}");

  // Ensure uuid exists on both sides
  if (!in_array('uuid', $localCols, true) || !in_array('uuid', $hostedCols, true)) {
    throw new RuntimeException("Both local and hosted {$TABLE} must have a 'uuid' column.");
  }

  /**
   * Decide what columns to sync:
   * - Only columns that exist in BOTH tables
   * - Exclude:
   *     id        (auto-increment differs between DBs)
   *     timestamp (host generates it; local may differ)
   */
  $commonCols = array_values(array_intersect($localCols, $hostedCols));
  $colsToSync = array_values(array_diff($commonCols, ['id', 'timestamp']));

  // Put uuid first (not required, just readable)
  usort($colsToSync, function($a, $b) {
    if ($a === 'uuid') return -1;
    if ($b === 'uuid') return 1;
    return strcmp($a, $b);
  });

  // Build WHERE clause for selecting from LOCAL
  $whereParts = ["event_name = :event"];
  $whereParams = [':event' => $event];

  if ($match_number !== '') {
    $whereParts[] = "match_no = :match_no";
    $whereParams[':match_no'] = $match_number;
  }

  if ($game !== '') {
    $whereParts[] = "game = :game";
    $whereParams[':game'] = $game;
  }

  // If local table has uploaded column, only sync rows not uploaded yet
  $hasUploaded = in_array('uploaded', $localCols, true);
  if ($hasUploaded) {
    $whereParts[] = "(uploaded IS NULL OR uploaded = 0)";
  }

  $whereSql = "WHERE " . implode(" AND ", $whereParts);

  // Select rows from LOCAL
  $selectSql = "SELECT " . implode(',', array_map('q', $colsToSync)) .
               " FROM " . q($TABLE) . " " . $whereSql;

  $sel = $local->prepare($selectSql);
  $sel->execute($whereParams);
  $rows = $sel->fetchAll(PDO::FETCH_ASSOC);

  $localCount = count($rows);

  // If nothing matched, return success (nothing to do)
  if ($localCount === 0) {
    echo json_encode([
      'success' => true,
      'message' => 'Nothing to sync.',
      'event' => $event,
      'match_number' => $match_number,
      'game' => $game,
      'local_rows' => 0,
      'hosted_affected' => 0,
    ]);
    exit;
  }

  /**
   * Build UPSERT SQL for HOSTED:
   * - We insert all synced columns
   * - On duplicate uuid, we update everything except uuid
   *
   * This requires HOSTED has UNIQUE(uuid).
   */
  $alias = 'n';

  $updateCols = array_values(array_diff($colsToSync, ['uuid']));

  $updateSql = $updateCols
    ? implode(', ', array_map(fn($c) => q($c) . " = {$alias}." . q($c), $updateCols))
    : (q('uuid') . " = " . q('uuid')); // no-op if uuid is only col

  $colListSql = implode(',', array_map('q', $colsToSync));

  // Do hosted operations inside a transaction so it either all works or none
  $hosted->beginTransaction();

  $affectedTotal = 0;

  foreach (chunk($rows, $BATCH_SIZE) as $batch) {
    $valueGroups = []; // "( :p0,:p1,... ),( :pN,... )"
    $binds = [];       // placeholder => value
    $i = 0;

    foreach ($batch as $row) {
      $ph = [];
      foreach ($colsToSync as $col) {
        $p = ":p{$i}";
        $ph[] = $p;
        $binds[$p] = $row[$col];
        $i++;
      }
      $valueGroups[] = '(' . implode(',', $ph) . ')';
    }

    $sql = "INSERT INTO " . q($TABLE) . " ({$colListSql}) VALUES " . implode(',', $valueGroups) .
           " AS {$alias} ON DUPLICATE KEY UPDATE {$updateSql}";

    $ins = $hosted->prepare($sql);
    $ins->execute($binds);
    $affectedTotal += $ins->rowCount();
  }

  // Commit hosted changes
  $hosted->commit();

  // Optionally mark local uploaded=1 for the synced uuids
  $markedLocal = 0;

  if ($MARK_LOCAL_UPLOADED && $hasUploaded) {
    $uuids = array_values(array_filter(array_map(fn($r) => $r['uuid'] ?? null, $rows)));
    $uuids = array_values(array_unique($uuids));

    $local->beginTransaction();

    foreach (chunk($uuids, 900) as $uuidBatch) {
      $in = [];
      $p = [];

      foreach ($uuidBatch as $idx => $u) {
        $k = ":u{$idx}";
        $in[] = $k;
        $p[$k] = $u;
      }

      $upd = "UPDATE " . q($TABLE) . " SET uploaded = 1 WHERE uuid IN (" . implode(',', $in) . ")";
      $st = $local->prepare($upd);
      $st->execute($p);

      $markedLocal += $st->rowCount();
    }

    $local->commit();
  }

  // Success response
  echo json_encode([
    'success' => true,
    'message' => 'Sync completed.',
    'event' => $event,
    'match_number' => $match_number,
    'game' => $game,
    'local_rows' => $localCount,
    'hosted_affected' => $affectedTotal,
    'local_marked_uploaded' => $markedLocal,
  ]);

} catch (Throwable $e) {
  // Roll back transactions if something failed
  try { if (isset($hosted) && $hosted instanceof PDO && $hosted->inTransaction()) $hosted->rollBack(); } catch (Throwable $ignored) {}
  try { if (isset($local)  && $local  instanceof PDO && $local->inTransaction())  $local->rollBack(); } catch (Throwable $ignored) {}

  http_response_code(500);

  echo json_encode([
    'success' => false,
    'message' => $e->getMessage(),
  ]);
}