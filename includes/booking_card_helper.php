<?php
// Booking Confirmation Card & WhatsApp Integration Helper
// Avinash Travels - Enterprise Dispatch System

require_once __DIR__ . '/db.php';

/**
 * Get base website URL (HTTP/HTTPS)
 */
function get_site_base_url() {
    if (php_sapi_name() === 'cli' || empty($_SERVER['HTTP_HOST'])) {
        return 'https://avinashtaxi.com';
    }
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    
    // Auto-detect web subdirectory if hosted under local folder like /avinashtaxi.com
    $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $base_dir = preg_replace('#/(admin|api|customer|driver|includes).*$#i', '', $script_dir);
    $base_dir = rtrim($base_dir, '/');
    
    return $protocol . $host . ($base_dir ? $base_dir : '');
}

/**
 * Clean phone number for WhatsApp URL (91XXXXXXXXXX)
 */
function format_whatsapp_phone($phone) {
    $clean = preg_replace('/[^\d]/', '', $phone);
    if (strlen($clean) === 10) {
        return '91' . $clean;
    }
    if (strlen($clean) === 12 && substr($clean, 0, 2) === '91') {
        return $clean;
    }
    if (strlen($clean) > 10 && substr($clean, 0, 1) === '0') {
        return '91' . substr($clean, 1);
    }
    return $clean ?: '918120868818';
}

/**
 * Fetch booking details by ID or booking_id string
 */
function get_booking_details_for_card($booking_ref_or_id) {
    if (is_array($booking_ref_or_id)) {
        return $booking_ref_or_id;
    }
    global $pdo;
    if (!$pdo) return null;

    $sql = "
        SELECT b.*, 
               bp.name AS passenger_name, bp.phone AS passenger_phone, bp.email AS passenger_email,
               bp.pickup_address, bp.drop_address, bp.passenger_count,
               vc.name AS category_name,
               v.name AS vehicle_name, v.plate_number,
               d.name AS driver_name, d.phone AS driver_phone
        FROM `bookings` b
        LEFT JOIN `booking_passengers` bp ON b.id = bp.booking_id
        LEFT JOIN `vehicle_categories` vc ON b.category_id = vc.id
        LEFT JOIN `vehicles` v ON b.vehicle_id = v.id
        LEFT JOIN `trips` t ON t.booking_id = b.id
        LEFT JOIN `trip_bids` tb ON tb.trip_id = t.id AND tb.status = 'accepted'
        LEFT JOIN `drivers` d ON tb.driver_id = d.id
        WHERE " . (is_numeric($booking_ref_or_id) ? "b.id = ?" : "b.booking_id = ?") . "
        LIMIT 1
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$booking_ref_or_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error fetching booking details: " . $e->getMessage());
        return null;
    }
}

/**
 * Generate official AI-styled Booking Confirmation Pass Image (PNG)
 * Uses GD library with the luxury AI backdrop generated for Avinash Travels
 */
function generate_booking_card($booking_ref_or_id) {
    global $pdo;
    ensure_payment_columns();

    $booking = get_booking_details_for_card($booking_ref_or_id);
    if (!$booking) {
        return ['success' => false, 'error' => 'Booking not found'];
    }

    $booking_id = $booking['booking_id'] ?? ('AVT-' . $booking['id']);
    $out_dir = __DIR__ . '/../uploads/booking_cards/';
    if (!file_exists($out_dir)) {
        @mkdir($out_dir, 0777, true);
    }

    $out_filename = 'pass_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $booking_id) . '.png';
    $out_filepath = $out_dir . $out_filename;
    $relative_path = 'uploads/booking_cards/' . $out_filename;

    $width = 1200;
    $height = 675;

    // Check if GD is available
    if (!function_exists('imagecreatetruecolor')) {
        return [
            'success' => true,
            'file_path' => $relative_path,
            'file_url' => get_site_base_url() . '/api/generate_booking_card.php?id=' . urlencode($booking_id) . '&action=view',
            'booking' => $booking
        ];
    }

    // 1. Create truecolor canvas
    $canvas = imagecreatetruecolor($width, $height);
    imagealphablending($canvas, true);
    imagesavealpha($canvas, true);

    // 2. Load background image (AI generated luxury backdrop)
    $bg_path = __DIR__ . '/../assets/images/ai_booking_bg.jpg';
    $has_bg = false;
    if (file_exists($bg_path)) {
        $bg = @imagecreatefromjpeg($bg_path);
        if ($bg) {
            $bg_w = imagesx($bg);
            $bg_h = imagesy($bg);
            imagecopyresampled($canvas, $bg, 0, 0, 0, 0, $width, $height, $bg_w, $bg_h);
            imagedestroy($bg);
            $has_bg = true;
        }
    }

    if (!$has_bg) {
        // Fallback rich gradient dark background
        $dark_bg = imagecolorallocate($canvas, 10, 15, 30);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $dark_bg);
    }

    // Colors
    $black_dim   = imagecolorallocatealpha($canvas, 5, 10, 20, 40);     // 70% opacity dark overlay
    $card_glass  = imagecolorallocatealpha($canvas, 15, 23, 42, 35);    // Slate 900 glass
    $card_border = imagecolorallocatealpha($canvas, 56, 189, 248, 60);  // Sky border
    $gold_border = imagecolorallocatealpha($canvas, 245, 158, 11, 40);  // Gold border
    $white       = imagecolorallocate($canvas, 255, 255, 255);
    $slate_200   = imagecolorallocate($canvas, 226, 232, 240);
    $slate_400   = imagecolorallocate($canvas, 148, 163, 184);
    $sky_blue    = imagecolorallocate($canvas, 56, 189, 248);
    $emerald_bg  = imagecolorallocatealpha($canvas, 16, 185, 129, 20);
    $emerald_txt = imagecolorallocate($canvas, 52, 211, 153);
    $amber_txt   = imagecolorallocate($canvas, 251, 191, 36);

    // Semi-dark overall tint for readability
    imagefilledrectangle($canvas, 0, 0, $width, $height, $black_dim);

    // Glassmorphic main container (padding 30px)
    $c_x1 = 30;
    $c_y1 = 30;
    $c_x2 = $width - 30;
    $c_y2 = $height - 30;

    imagefilledrectangle($canvas, $c_x1, $c_y1, $c_x2, $c_y2, $card_glass);
    imagerectangle($canvas, $c_x1, $c_y1, $c_x2, $c_y2, $card_border);
    imagerectangle($canvas, $c_x1 + 1, $c_y1 + 1, $c_x2 - 1, $c_y2 - 1, $card_border);

    // Header Bar
    $h_bar_y = 110;
    imagefilledrectangle($canvas, $c_x1, $c_y1, $c_x2, $h_bar_y, imagecolorallocatealpha($canvas, 2, 6, 23, 20));
    imageline($canvas, $c_x1, $h_bar_y, $c_x2, $h_bar_y, $card_border);

    // Font selection
    $font_bold = 'C:\\Windows\\Fonts\\arialbd.ttf';
    $font_reg  = 'C:\\Windows\\Fonts\\arial.ttf';
    $use_ttf   = (function_exists('imagettftext') && file_exists($font_bold));

    $passenger_name = $booking['passenger_name'] ?: 'Valued Customer';
    $passenger_phone = $booking['passenger_phone'] ?: '';
    $city_from = strtoupper($booking['pickup_city'] ?: 'RAIPUR');
    $city_to   = strtoupper($booking['drop_city'] ?: 'DESTINATION');
    $route_txt = $city_from . "  TO  " . $city_to;
    $date_txt = date('d-M-Y', strtotime($booking['pickup_date'])) . ' at ' . date('h:i A', strtotime($booking['pickup_time']));
    $vehicle_txt = $booking['vehicle_name'] ?: ($booking['category_name'] ?: 'Executive Cab');
    $trip_type_txt = ($booking['trip_type'] === 'round_trip') ? 'ROUND TRIP' : 'ONE WAY TRIP';
    $fare_txt = 'INR ' . number_format($booking['final_total'], 2);

    if ($use_ttf) {
        // Brand Title
        imagettftext($canvas, 22, 0, 60, 75, $white, $font_bold, "AVINASH TRAVELS");
        imagettftext($canvas, 10, 0, 60, 95, $sky_blue, $font_bold, "CHHATTISGARH PREMIUM INTERCITY CAB SERVICE • DISPATCH DESK");

        // Verified Badge Pill (Top Right)
        $badge_x1 = 780;
        $badge_y1 = 50;
        $badge_x2 = 1140;
        $badge_y2 = 95;
        imagefilledrectangle($canvas, $badge_x1, $badge_y1, $badge_x2, $badge_y2, $emerald_bg);
        imagerectangle($canvas, $badge_x1, $badge_y1, $badge_x2, $badge_y2, $emerald_txt);
        imagerectangle($canvas, $badge_x1 + 1, $badge_y1 + 1, $badge_x2 - 1, $badge_y2 - 1, $emerald_txt);
        imagefilledellipse($canvas, $badge_x1 + 25, $badge_y1 + 23, 10, 10, $emerald_txt);
        imagettftext($canvas, 12, 0, $badge_x1 + 38, $badge_y1 + 28, $emerald_txt, $font_bold, "BOOKING APPROVED & CONFIRMED");

        // Main Content Grid
        // Left Column: Route & Trip Specifications
        $col1_x = 60;
        
        imagettftext($canvas, 10, 0, $col1_x, 155, $slate_400, $font_bold, "TRAVEL ROUTE");
        imagettftext($canvas, 26, 0, $col1_x, 195, $white, $font_bold, $route_txt);
        imagettftext($canvas, 11, 0, $col1_x, 225, $amber_txt, $font_bold, "TRIP TYPE: " . $trip_type_txt);

        // Schedule & Vehicle
        imagettftext($canvas, 10, 0, $col1_x, 275, $slate_400, $font_bold, "PICKUP DATE & SCHEDULED TIME");
        imagettftext($canvas, 16, 0, $col1_x, 305, $slate_200, $font_bold, $date_txt);

        imagettftext($canvas, 10, 0, $col1_x, 355, $slate_400, $font_bold, "ASSIGNED VEHICLE CATEGORY");
        imagettftext($canvas, 16, 0, $col1_x, 385, $sky_blue, $font_bold, $vehicle_txt);

        imagettftext($canvas, 10, 0, $col1_x, 435, $slate_400, $font_bold, "PASSENGER & CONTACT");
        imagettftext($canvas, 15, 0, $col1_x, 465, $white, $font_bold, $passenger_name . "  (" . $passenger_phone . ")");

        imagettftext($canvas, 10, 0, $col1_x, 515, $slate_400, $font_bold, "PICKUP ADDRESS");
        $clean_addr = substr($booking['pickup_address'] ?: ($booking['pickup_city'] . ' City'), 0, 55);
        imagettftext($canvas, 12, 0, $col1_x, 540, $slate_200, $font_reg, $clean_addr);

        // Divider
        $div_x = 730;
        imageline($canvas, $div_x, 130, $div_x, 580, imagecolorallocatealpha($canvas, 148, 163, 184, 85));

        // Right Column: Fare & Verification Details
        $col2_x = 760;

        imagettftext($canvas, 10, 0, $col2_x, 155, $slate_400, $font_bold, "BOOKING REFERENCE ID");
        imagettftext($canvas, 20, 0, $col2_x, 190, $sky_blue, $font_bold, "#" . $booking_id);

        imagettftext($canvas, 10, 0, $col2_x, 250, $slate_400, $font_bold, "TOTAL FARE (ALL-INCLUSIVE)");
        imagettftext($canvas, 28, 0, $col2_x, 290, $emerald_txt, $font_bold, $fare_txt);
        imagettftext($canvas, 10, 0, $col2_x, 315, $white, $font_bold, "STATUS: PAID & VERIFIED BY ADMIN");

        // Chauffeur Note Card
        $sub_box_y = 350;
        imagefilledrectangle($canvas, $col2_x, $sub_box_y, 1130, $sub_box_y + 115, imagecolorallocatealpha($canvas, 2, 6, 23, 40));
        imagerectangle($canvas, $col2_x, $sub_box_y, 1130, $sub_box_y + 115, $gold_border);
        imagettftext($canvas, 11, 0, $col2_x + 15, $sub_box_y + 30, $amber_txt, $font_bold, "DISPATCH STATUS: READY FOR PICKUP");
        imagettftext($canvas, 10, 0, $col2_x + 15, $sub_box_y + 55, $slate_200, $font_reg, "Driver name & vehicle plate number are dispatched");
        imagettftext($canvas, 10, 0, $col2_x + 15, $sub_box_y + 75, $slate_200, $font_reg, "to your WhatsApp prior to scheduled departure.");
        imagettftext($canvas, 10, 0, $col2_x + 15, $sub_box_y + 98, $sky_blue, $font_bold, "Helpline: +91 8120868818 / 7987000100");

        // Security watermark
        imagettftext($canvas, 9, 0, $col2_x, 540, $slate_400, $font_reg, "Official Computer Generated e-Pass • Avinash Travels Raipur");

        // Footer Bar
        imageline($canvas, $c_x1, 595, $c_x2, 595, $card_border);
        imagettftext($canvas, 10, 0, 60, 625, $slate_400, $font_reg, "Avinash Travels 24x7 Cab Dispatch Service • Raipur, Bilaspur, Bhilai, Durg & All Chhattisgarh • www.avinashtaxi.com");

    } else {
        // Fallback with built-in GD fonts
        imagestring($canvas, 5, 60, 50, "AVINASH TRAVELS - OFFICIAL BOOKING PASS", $white);
        imagestring($canvas, 4, 60, 80, "STATUS: BOOKING APPROVED & CONFIRMED", $emerald_txt);
        imagestring($canvas, 5, 60, 160, "ROUTE: " . $route_txt, $white);
        imagestring($canvas, 4, 60, 200, "PASSENGER: " . $passenger_name . " (" . $passenger_phone . ")", $slate_200);
        imagestring($canvas, 4, 60, 240, "DATE & TIME: " . $date_txt, $amber_txt);
        imagestring($canvas, 4, 60, 280, "VEHICLE: " . $vehicle_txt, $sky_blue);
        imagestring($canvas, 5, 60, 340, "BOOKING REF: #" . $booking_id, $sky_blue);
        imagestring($canvas, 5, 60, 380, "TOTAL FARE: " . $fare_txt . " (PAID / APPROVED)", $emerald_txt);
        imagestring($canvas, 4, 60, 440, "HELPLINE: +91 8120868818 | Avinash Travels Raipur", $white);
    }

    // Save PNG
    imagepng($canvas, $out_filepath, 6);
    imagedestroy($canvas);

    // Save reference in DB
    if ($pdo && !empty($booking['id'])) {
        try {
            $stmt_up = $pdo->prepare("UPDATE `bookings` SET booking_card_image = ? WHERE id = ?");
            $stmt_up->execute([$relative_path, $booking['id']]);
        } catch (Exception $e) {}
    }

    $card_full_url = get_site_base_url() . '/' . $relative_path;

    return [
        'success' => true,
        'file_path' => $relative_path,
        'file_url' => $card_full_url,
        'booking' => $booking
    ];
}

/**
 * Generate full WhatsApp redirect data for approved booking
 */
function get_booking_whatsapp_data($booking_ref_or_id) {
    $card_res = generate_booking_card($booking_ref_or_id);
    $booking = $card_res['booking'] ?? get_booking_details_for_card($booking_ref_or_id);

    if (!$booking) {
        return null;
    }

    $booking_id = $booking['booking_id'] ?? ('AVT-' . $booking['id']);
    $passenger_name = $booking['passenger_name'] ?: 'Customer';
    $passenger_phone = $booking['passenger_phone'] ?: '';
    $wa_phone = format_whatsapp_phone($passenger_phone);

    $route_info = $booking['pickup_city'] . ' ➔ ' . $booking['drop_city'];
    $trip_type = ($booking['trip_type'] === 'round_trip') ? 'Round Trip' : 'One Way';
    $date_time = date('d-M-Y', strtotime($booking['pickup_date'])) . ' at ' . date('h:i A', strtotime($booking['pickup_time']));
    $vehicle_name = $booking['vehicle_name'] ?: ($booking['category_name'] ?: 'Premium Cab');
    $fare_amount = number_format($booking['final_total'], 2);
    $pickup_address = $booking['pickup_address'] ?: $booking['pickup_city'];

    $site_url = get_site_base_url();
    $card_url = $card_res['file_url'] ?? ($site_url . '/uploads/booking_cards/pass_' . $booking_id . '.png');
    $track_url = $site_url . '/#track';

    // WhatsApp Message Body
    $wa_message = "🎉 *BOOKING CONFIRMED & APPROVED - AVINASH TRAVELS* 🚖\n"
        . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        . "Namaste *{$passenger_name}*,\n\n"
        . "Great news! Your cab reservation has been *VERIFIED & OFFICIALLY APPROVED* by our dispatch desk.\n\n"
        . "📋 *Booking ID:* {$booking_id}\n"
        . "🛣 *Route:* {$route_info} ({$trip_type})\n"
        . "📅 *Pickup Date & Time:* {$date_time}\n"
        . "🚘 *Vehicle Category:* {$vehicle_name}\n"
        . "💰 *Total Fare:* ₹{$fare_amount} (Status: PAID / APPROVED)\n"
        . "📍 *Pickup Address:* {$pickup_address}\n\n"
        . "🖼 *Official AI Booking Confirmation Pass:*\n"
        . "{$card_url}\n\n"
        . "📲 *Track Live Trip Status:*\n"
        . "{$track_url}\n\n"
        . "📞 *24x7 Raipur Dispatch Helpline:* +91 8120868818 / +91 7987000100\n\n"
        . "Thank you for choosing Avinash Travels! Have a pleasant and safe journey.";

    $whatsapp_redirect_url = "https://wa.me/{$wa_phone}?text=" . rawurlencode($wa_message);

    return [
        'booking_id' => $booking_id,
        'passenger_name' => $passenger_name,
        'passenger_phone' => $passenger_phone,
        'wa_phone' => $wa_phone,
        'message' => $wa_message,
        'whatsapp_url' => $whatsapp_redirect_url,
        'card_url' => $card_url,
        'card_relative' => $card_res['file_path'] ?? '',
        'track_url' => $track_url
    ];
}

/**
 * Notify admin of payment events (submitted, approved, rejected, failed)
 */
function notify_admin_payment_event($event_type, $data) {
    global $pdo;
    $admin_phone = get_setting('company_phone', '8120868818');
    $admin_wa_phone = format_whatsapp_phone($admin_phone);
    $site_url = get_site_base_url();

    $booking_id = $data['booking_id'] ?? 'AVT-XXXX';
    $name = $data['passenger_name'] ?? ($data['name'] ?? 'Customer');
    $phone = $data['passenger_phone'] ?? ($data['phone'] ?? '');
    $route = ($data['pickup_city'] ?? '') . ' ➔ ' . ($data['drop_city'] ?? '');
    $amount = number_format(floatval($data['final_total'] ?? $data['amount'] ?? 0), 2);
    $utr = $data['utr_number'] ?? 'N/A';
    $date = ($data['pickup_date'] ?? date('Y-m-d')) . ' ' . ($data['pickup_time'] ?? '');
    $reason = $data['reason'] ?? '';

    if ($event_type === 'payment_submitted') {
        $msg = "🚨 *NEW PAYMENT PROOF SUBMITTED (AWAITING APPROVAL)*\n"
             . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
             . "A customer has submitted payment verification:\n\n"
             . "📋 *Booking ID:* {$booking_id}\n"
             . "👤 *Passenger:* {$name} ({$phone})\n"
             . "🛣 *Route:* {$route}\n"
             . "📅 *Pickup:* {$date}\n"
             . "💰 *Amount:* ₹{$amount}\n"
             . "🔢 *UTR / Ref No:* {$utr}\n\n"
             . "👉 *Review & 1-Click Approve in Admin:* \n"
             . "{$site_url}/admin/payments.php";
    } elseif ($event_type === 'payment_failed') {
        $msg = "⚠️ *PAYMENT ATTEMPT FAILED / UNSUCCESSFUL*\n"
             . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
             . "A payment transaction could not be completed:\n\n"
             . "📋 *Booking ID:* {$booking_id}\n"
             . "👤 *Passenger:* {$name} ({$phone})\n"
             . "🛣 *Route:* {$route}\n"
             . "💰 *Attempted Amount:* ₹{$amount}\n"
             . "❌ *Reason / Error:* " . ($reason ?: 'Transaction was cancelled or declined by bank') . "\n\n"
             . "📞 Call customer to assist: tel:{$phone}\n"
             . "Check live status: {$site_url}/admin/bookings.php";
    } elseif ($event_type === 'payment_rejected') {
        $msg = "❌ *PAYMENT PROOF REJECTED BY ADMIN*\n"
             . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
             . "Booking ID: {$booking_id}\n"
             . "Passenger: {$name} ({$phone})\n"
             . "Reason: {$reason}\n"
             . "Trip status set to on-hold.";
    } else {
        $msg = "ℹ️ *PAYMENT EVENT: " . strtoupper($event_type) . "* for Booking #{$booking_id}";
    }

    if ($pdo) {
        try {
            $stmt = $pdo->prepare("INSERT INTO `notifications` (type, recipient, message, gateway, status) VALUES (?, 'admin', ?, 'whatsapp', 'sent')");
            $stmt->execute([$event_type, $msg]);
        } catch (Exception $e) {}
    }

    return [
        'event' => $event_type,
        'message' => $msg,
        'admin_phone' => $admin_phone,
        'whatsapp_url' => "https://wa.me/{$admin_wa_phone}?text=" . rawurlencode($msg)
    ];
}

