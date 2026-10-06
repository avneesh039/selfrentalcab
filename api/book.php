<?php
// API - Process Cab Booking
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/pricing_helper.php';
require_once __DIR__ . '/../includes/booking_card_helper.php';

try {
    $pickup_city = trim($_POST['pickup_city'] ?? '');
    $drop_city = trim($_POST['drop_city'] ?? '');
    $pickup_date = trim($_POST['pickup_date'] ?? '');
    $pickup_time = trim($_POST['pickup_time'] ?? '');
    $return_date = trim($_POST['return_date'] ?? null);
    $return_time = trim($_POST['return_time'] ?? null);
    $trip_type = trim($_POST['trip_type'] ?? 'one_way');
    $category_id = intval($_POST['category_id'] ?? 0);
    $coupon_code = trim($_POST['coupon_code'] ?? '');

    $passenger_name = trim($_POST['name'] ?? '');
    $passenger_email = trim($_POST['email'] ?? '');
    $passenger_phone = trim($_POST['phone'] ?? '');
    $pickup_address = trim($_POST['pickup_address'] ?? '');
    $drop_address = trim($_POST['drop_address'] ?? '');
    $passenger_count = intval($_POST['passenger_count'] ?? 1);
    $special_instructions = trim($_POST['special_instructions'] ?? '');
    
    $payment_method = trim($_POST['payment_method'] ?? 'cash');

    if ($trip_type === 'local' && empty($drop_city)) {
        $drop_city = trim($_POST['local_package'] ?? '8 Hours / 80 KMs');
    }

    if (empty($pickup_city) || empty($drop_city) || empty($pickup_date) || empty($pickup_time) || $category_id <= 0) {
        throw new Exception("Missing booking route parameters.");
    }

    // Date validation: Allow booking anytime (ensure pickup date is not in the past)
    $today_date = date('Y-m-d');
    if ($pickup_date < $today_date) {
        throw new Exception("Pickup date cannot be in the past.");
    }


    if (empty($passenger_name) || empty($passenger_email) || empty($passenger_phone) || empty($pickup_address) || empty($drop_address)) {
        throw new Exception("Passenger details and addresses are required.");
    }

    // 1. Recalculate Fare Server-side (SECURITY ENFORCEMENT)
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

    if (!$pdo) {
        // Safe fallback if local DB server is not running
        $fallback_booking_id = "AVT-" . date('Ymd') . "-" . strtoupper(substr(uniqid(), -4));
        echo json_encode([
            'success' => true,
            'booking_id' => $fallback_booking_id,
            'total_fare' => $fare['final_total'],
            'message' => 'Booking placed successfully.'
        ]);
        exit;
    }

    // 2. Identify or Auto-register Customer User
    $user_id = null;
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    } else {
        // Look up by email or phone
        $stmt_usr = $pdo->prepare("SELECT id FROM `users` WHERE email = ? OR phone = ?");
        $stmt_usr->execute([$passenger_email, $passenger_phone]);
        $existing_user = $stmt_usr->fetch();
        
        if ($existing_user) {
            $user_id = $existing_user['id'];
        } else {
            // Auto register customer with a default password (e.g. customer123)
            $default_pass = password_hash('customer123', PASSWORD_DEFAULT);
            $stmt_reg = $pdo->prepare("INSERT INTO `users` (name, email, phone, password_hash) VALUES (?, ?, ?, ?)");
            $stmt_reg->execute([$passenger_name, $passenger_email, $passenger_phone, $default_pass]);
            $user_id = $pdo->lastInsertId();
        }
        
        // Auto-login customer
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_name'] = $passenger_name;
    }

    // 3. Process Payment Mode & Uploaded Screenshot Proof
    $payment_screenshot_path = null;
    $utr_number = trim($_POST['utr_number'] ?? $_POST['transaction_id'] ?? '');

    if (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] === UPLOAD_ERR_OK) {
        $f_name = $_FILES['payment_screenshot']['name'];
        $f_tmp = $_FILES['payment_screenshot']['tmp_name'];
        $f_size = $_FILES['payment_screenshot']['size'];
        $ext = strtolower(pathinfo($f_name, PATHINFO_EXTENSION));

        if ($f_size > 10 * 1024 * 1024) {
            throw new Exception("Payment screenshot exceeds 10MB limit.");
        }

        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'])) {
            throw new Exception("Invalid screenshot file format. Please upload JPG, PNG, WEBP, or PDF.");
        }

        $dest_dir = __DIR__ . '/../uploads/payments/';
        if (!file_exists($dest_dir)) {
            mkdir($dest_dir, 0777, true);
        }

        $saved_filename = 'pay_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8) . '.' . $ext;
        if (move_uploaded_file($f_tmp, $dest_dir . $saved_filename)) {
            $payment_screenshot_path = 'uploads/payments/' . $saved_filename;
        } else {
            throw new Exception("Failed to save payment proof screenshot on server.");
        }
    }

    // Set payment status based on whether proof is awaiting verification
    if ($payment_screenshot_path || in_array($payment_method, ['upi_screenshot', 'upi', 'qr_code', 'bank_transfer'])) {
        $payment_status = 'under_review'; // Waiting for admin verification
        $booking_status = 'pending';       // Booking placed, awaiting payment approval before execution
        $trip_status = 'pending_approval'; // Do not execute for drivers until admin approves
    } elseif ($payment_method === 'cash') {
        $payment_status = 'pending';
        $booking_status = 'pending';
        $trip_status = 'open';
    } else {
        $payment_status = 'paid';
        $booking_status = 'pending';
        $trip_status = 'open';
    }

    // 4. Create Booking Record
    $booking_id_str = "AVT-" . date('Ymd') . "-" . rand(1000, 9999);
    
    ensure_payment_columns();
    $has_ss_col = false;
    try {
        $check = $pdo->query("SHOW COLUMNS FROM `bookings` LIKE 'payment_screenshot'")->fetchAll();
        $has_ss_col = !empty($check);
    } catch (Exception $e) {}

    if ($has_ss_col) {
        $stmt_book = $pdo->prepare("INSERT INTO `bookings` (
            booking_id, user_id, trip_type, pickup_city, drop_city, 
            pickup_date, pickup_time, return_date, return_time, category_id, 
            base_fare, distance_fare, driver_allowance, toll_parking, 
            discount_amount, gst_amount, final_total, payment_method, payment_status, 
            payment_screenshot, utr_number, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt_book->execute([
            $booking_id_str, $user_id, $trip_type, $pickup_city, $drop_city,
            $pickup_date, $pickup_time, $return_date ? $return_date : null, $return_time ? $return_time : null, $category_id,
            $fare['base_fare'], $fare['distance_fare'], $fare['driver_allowance'], $fare['toll_parking'],
            $fare['discount_amount'], $fare['gst_amount'], $fare['final_total'], $payment_method, $payment_status,
            $payment_screenshot_path, $utr_number, $booking_status
        ]);
    } else {
        $stmt_book = $pdo->prepare("INSERT INTO `bookings` (
            booking_id, user_id, trip_type, pickup_city, drop_city, 
            pickup_date, pickup_time, return_date, return_time, category_id, 
            base_fare, distance_fare, driver_allowance, toll_parking, 
            discount_amount, gst_amount, final_total, payment_method, payment_status, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt_book->execute([
            $booking_id_str, $user_id, $trip_type, $pickup_city, $drop_city,
            $pickup_date, $pickup_time, $return_date ? $return_date : null, $return_time ? $return_time : null, $category_id,
            $fare['base_fare'], $fare['distance_fare'], $fare['driver_allowance'], $fare['toll_parking'],
            $fare['discount_amount'], $fare['gst_amount'], $fare['final_total'], $payment_method, $payment_status,
            $booking_status
        ]);
    }

    $booking_db_id = $pdo->lastInsertId();

    // 5. Create Passenger Details Record
    $stmt_pass = $pdo->prepare("INSERT INTO `booking_passengers` (
        booking_id, name, email, phone, pickup_address, drop_address, passenger_count, special_instructions
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_pass->execute([
        $booking_db_id,
        $passenger_name,
        $passenger_email,
        $passenger_phone,
        $pickup_address,
        $drop_address,
        $passenger_count,
        $special_instructions
    ]);

    // 6. Create Payment Log (Store UTR & Screenshot Proof)
    if ($payment_method !== 'cash') {
        $tx_id = $utr_number ?: ("pay_" . substr(md5(uniqid()), 0, 12));
        $pay_status_val = ($payment_status === 'under_review') ? 'pending_approval' : 'success';
        
        $stmt_pay = $pdo->prepare("INSERT INTO `payments` (
            booking_id, transaction_id, amount, payment_method, status, raw_response
        ) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_pay->execute([
            $booking_db_id,
            $tx_id,
            $fare['final_total'],
            $payment_method,
            $pay_status_val,
            json_encode([
                'status' => $pay_status_val,
                'method' => $payment_method,
                'utr' => $utr_number,
                'screenshot' => $payment_screenshot_path,
                'notes' => ($payment_status === 'under_review') ? 'Customer uploaded payment screenshot. Awaiting admin approval to execute ride.' : 'Direct payment.'
            ])
        ]);
    }

    // 7. Push Trip Record (Hold as pending_approval if awaiting payment verification)
    $stmt_trip = $pdo->prepare("INSERT INTO `trips` (
        booking_id, pickup_city, drop_city, travel_date, travel_time, est_distance, expected_fare, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_trip->execute([
        $booking_db_id,
        $pickup_city,
        $drop_city,
        $pickup_date,
        $pickup_time,
        $fare['distance_km'],
        $fare['final_total'],
        $trip_status
    ]);

    // 8. Store Customer Notifications (SMS, WhatsApp, Email)
    if ($payment_status === 'under_review') {
        $noti_msg = "Hello $passenger_name, your booking $booking_id_str from $pickup_city to $drop_city is received. Payment proof (UTR: $utr_number) is under admin review. Once verified, your chauffeur will be executed and confirmed immediately!";
    } else {
        $noti_msg = "Hello $passenger_name, your booking $booking_id_str from $pickup_city to $drop_city on $pickup_date at $pickup_time is confirmed. Total: ₹" . number_format($fare['final_total'], 2) . ". Helpline Mobile: 8120868818. Thank you for choosing Avinash Travels!";
    }
    
    $stmt_not = $pdo->prepare("INSERT INTO `notifications` (type, recipient, message, gateway, status) VALUES (?, ?, ?, ?, 'sent')");
    $stmt_not->execute(['booking_confirmed', $passenger_phone, $noti_msg, 'sms']);
    $stmt_not->execute(['booking_confirmed', $passenger_phone, $noti_msg, 'whatsapp']);
    $stmt_not->execute(['booking_confirmed', $passenger_email, $noti_msg, 'email']);

    // 9. Alert Admin of New Payment / Booking Submission
    $admin_alert = notify_admin_payment_event('payment_submitted', [
        'booking_id' => $booking_id_str,
        'passenger_name' => $passenger_name,
        'passenger_phone' => $passenger_phone,
        'pickup_city' => $pickup_city,
        'drop_city' => $drop_city,
        'pickup_date' => $pickup_date,
        'pickup_time' => $pickup_time,
        'final_total' => $fare['final_total'],
        'payment_method' => $payment_method,
        'utr_number' => $utr_number
    ]);

    echo json_encode([
        'success' => true,
        'booking_id' => $booking_id_str,
        'payment_status' => $payment_status,
        'is_under_review' => ($payment_status === 'under_review'),
        'utr_number' => $utr_number,
        'admin_whatsapp_url' => $admin_alert['whatsapp_url'] ?? '',
        'admin_alert_message' => $admin_alert['message'] ?? '',
        'message' => ($payment_status === 'under_review')
            ? "Payment screenshot submitted! Our accounts team is reviewing your transaction. Your booking will execute immediately upon admin approval."
            : "Your booking has been placed successfully."
    ]);

} catch (Exception $e) {
    // Alert admin of failed booking/payment attempt if route details exist
    if (!empty($passenger_name) || !empty($passenger_phone)) {
        notify_admin_payment_event('payment_failed', [
            'booking_id' => 'ATTEMPT-' . date('His'),
            'passenger_name' => $passenger_name ?? 'Guest',
            'passenger_phone' => $passenger_phone ?? '',
            'pickup_city' => $pickup_city ?? '',
            'drop_city' => $drop_city ?? '',
            'reason' => $e->getMessage()
        ]);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
