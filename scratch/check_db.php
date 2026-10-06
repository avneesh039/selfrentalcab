<?php
require_once __DIR__ . '/../includes/db.php';
if ($pdo) {
    echo "DB Connected!\n";
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(', ', $tables) . "\n";
    foreach ($tables as $t) {
        $count = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo " - $t ($count rows)\n";
    }
} else {
    echo "No DB connection from includes/db.php\n";
    // Check if root without password connects
    try {
        $test_pdo = new PDO("mysql:host=localhost;charset=utf8mb4", 'root', '');
        echo "Connected to MySQL as root!\n";
        $dbs = $test_pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
        echo "Databases: " . implode(', ', $dbs) . "\n";
    } catch (Exception $e) {
        echo "Root connect error: " . $e->getMessage() . "\n";
    }
}
