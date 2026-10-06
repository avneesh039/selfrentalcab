<?php
// API - Fetch WhatsApp Share Data & AI Booking Pass
// Avinash Travels Enterprise
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/booking_card_helper.php';

try {
    $id = trim($_GET['id'] ?? $_POST['id'] ?? $_GET['booking_id'] ?? $_POST['booking_id'] ?? '');

    if (empty($id)) {
        throw new Exception("Missing booking ID parameter.");
    }

    $data = get_booking_whatsapp_data($id);
    if (!$data) {
        throw new Exception("Booking not found or failed to generate pass.");
    }

    echo json_encode([
        'success' => true,
        'booking_id' => $data['booking_id'],
        'passenger_name' => $data['passenger_name'],
        'passenger_phone' => $data['passenger_phone'],
        'whatsapp_url' => $data['whatsapp_url'],
        'card_url' => $data['card_url'],
        'card_relative' => $data['card_relative'],
        'message' => $data['message'],
        'track_url' => $data['track_url']
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
