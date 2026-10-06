<?php
// API - Recalculate Detailed Fare Breakdown Server-side
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/pricing_helper.php';

try {
    $pickup_city = trim($_POST['pickup_city'] ?? '');
    $drop_city = trim($_POST['drop_city'] ?? '');
    $pickup_date = trim($_POST['pickup_date'] ?? '');
    $pickup_time = trim($_POST['pickup_time'] ?? '');
    $trip_type = trim($_POST['trip_type'] ?? 'one_way');
    $category_id = intval($_POST['category_id'] ?? 0);
    $coupon_code = trim($_POST['coupon_code'] ?? '');

    if (empty($pickup_city) || empty($drop_city) || empty($pickup_date) || empty($pickup_time) || $category_id <= 0) {
        throw new Exception("Missing parameters for fare calculation.");
    }

    $fare = calculate_trip_fare([
        'pickup_city' => $pickup_city,
        'drop_city' => $drop_city,
        'pickup_date' => $pickup_date,
        'pickup_time' => $pickup_time,
        'trip_type' => $trip_type,
        'category_id' => $category_id,
        'coupon_code' => $coupon_code
    ]);

    if (isset($fare['error'])) {
        throw new Exception($fare['error']);
    }

    echo json_encode([
        'success' => true,
        'fare' => $fare
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
