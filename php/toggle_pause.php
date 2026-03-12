<?php
session_start();

// Include your database connection file (which creates $pdo)
$database_file = 'database_connection.php';
if (file_exists($database_file)) {
    include $database_file;
} else {
    die("Database connection file not found.");
}

try {
    // If $pdo is not already created by the connection file, create it here:
    if (!isset($pdo)) {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // Get the currently active match
    $sql = "SELECT * FROM matches WHERE active = 1 LIMIT 1";
    $activeMatch = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

    if ($activeMatch) {
   

        $sql = "SELECT * FROM matches WHERE active = 1 LIMIT 1";
        $activeMatch = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

        if ($activeMatch) {
            if ($activeMatch['pause'] == 0) {
                // Pause the match: Store the current timestamp in `paused_at`
                $currentTime = date('Y-m-d H:i:s');
                $sql = "UPDATE matches SET pause = 1, paused_at = :current_time WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':current_time' => $currentTime, ':id' => $activeMatch['id']]);
            } else {
                // Unpause the match: Calculate the duration paused (in seconds)
                $pausedAtTimestamp = strtotime($activeMatch['paused_at']);
                $pausedDuration = time() - $pausedAtTimestamp;

                // Add the paused duration to `total_pause_duration` and reset `paused_at`
                $sql = "UPDATE matches 
                        SET pause = 0, 
                            total_pause_duration = total_pause_duration + :paused_duration, 
                            paused_at = NULL 
                        WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':paused_duration' => $pausedDuration, ':id' => $activeMatch['id']]);
            }
        }
    
    } else {
        echo "No active match found.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
