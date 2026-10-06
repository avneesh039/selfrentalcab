<?php
// API - Submit Driver Bid for Trip
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

try {
    if (!is_driver_logged_in()) {
        throw new Exception("Unauthorized session. Please log in as a driver.");
    }

    $driver_id = $_SESSION['driver_id'];

    // Verify driver is approved
    $stmt_drv = $pdo->prepare("SELECT status FROM `drivers` WHERE id = ?");
    $stmt_drv->execute([$driver_id]);
    $driver_status = $stmt_drv->fetchColumn();

    if ($driver_status !== 'approved') {
        throw new Exception("Your driver status is current $driver_status. Only approved drivers can place bids.");
    }

    $trip_id = intval($_POST['trip_id'] ?? 0);
    $bid_amount = floatval($_POST['bid_amount'] ?? 0);
    $comments = trim($_POST['comments'] ?? '');

    if ($trip_id <= 0 || $bid_amount <= 0) {
        throw new Exception("Invalid trip ID or bid amount.");
    }

    // Verify trip is open
    $stmt_tr = $pdo->prepare("SELECT status FROM `trips` WHERE id = ?");
    $stmt_tr->execute([$trip_id]);
    $trip_status = $stmt_tr->fetchColumn();

    if (!$trip_status) {
        throw new Exception("Trip not found.");
    }

    if ($trip_status !== 'open') {
        throw new Exception("This trip is no longer open for bidding (Status: $trip_status).");
    }

    // Insert or update bid (driver_trip constraint in schema.sql handles conflict resolution if they bid again)
    $stmt_bid = $pdo->prepare("
        INSERT INTO `trip_bids` (trip_id, driver_id, bid_amount, comments, status) 
        VALUES (?, ?, ?, ?, 'pending')
        ON DUPLICATE KEY UPDATE bid_amount = VALUES(bid_amount), comments = VALUES(comments), status = 'pending'
    ");
    $stmt_bid->execute([$trip_id, $driver_id, $bid_amount, $comments]);

    echo json_encode([
        'success' => true,
        'message' => "Your bid of ₹" . number_format($bid_amount, 2) . " has been submitted successfully."
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
