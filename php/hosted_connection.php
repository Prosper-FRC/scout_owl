<?php
$host = '162.241.225.195';
$dbname = 'prospfv0_frc_scouting';
$username = 'prospfv0_scout_owl';
$password = 'BlueHawks2025!';
try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>