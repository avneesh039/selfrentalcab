<?php
// API - Get Cities autocomplete list
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

try {
    // Fetch unique cities from routes
    $stmt = $pdo->query("SELECT DISTINCT pickup_city AS city FROM `routes` WHERE status = 'active' 
                         UNION 
                         SELECT DISTINCT drop_city AS city FROM `routes` WHERE status = 'active'");
    $cities = [];
    while ($row = $stmt->fetch()) {
        $cities[] = $row['city'];
    }
    
    echo json_encode(['success' => true, 'cities' => $cities]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
