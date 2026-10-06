<?php
// API - Track Booking Status
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/booking_card_helper.php';

try {
    $booking_id = trim($_POST['booking_id'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($booking_id) || empty($phone)) {
        throw new Exception("Please enter both Booking ID and Mobile Number.");
    }

    // Query booking details, passenger details, and optionally assigned driver
    $stmt = $pdo->prepare("
        SELECT b.*, bp.name AS passenger_name, bp.phone AS passenger_phone, bp.pickup_address, bp.drop_address, bp.passenger_count, bp.special_instructions,
               v.name AS vehicle_name, v.plate_number, vc.name AS category_name,
               d.name AS driver_name, d.phone AS driver_phone, d.rating AS driver_rating
        FROM `bookings` b
        INNER JOIN `booking_passengers` bp ON b.id = bp.booking_id
        INNER JOIN `vehicle_categories` vc ON b.category_id = vc.id
        LEFT JOIN `vehicles` v ON b.vehicle_id = v.id
        LEFT JOIN `drivers` d ON b.vehicle_id IS NOT NULL AND v.id IS NOT NULL AND (
            -- driver is assigned to this booking trip
            d.id = (SELECT driver_id FROM `trip_bids` WHERE trip_id = (SELECT id FROM `trips` WHERE booking_id = b.id LIMIT 1) AND status = 'accepted' LIMIT 1)
        )
        WHERE b.booking_id = ?
    ");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        throw new Exception("Booking not found. Please verify the Booking ID.");
    }

    // Security: Check if phone matches passenger phone or user phone
    if (trim($booking['passenger_phone']) !== $phone) {
        throw new Exception("Mobile Number does not match the passenger record.");
    }

    // Determine status badge classes and timeline steps
    $status = $booking['status'];
    
    // Status indicators
    $step1 = "completed"; // Booked
    $step2 = "pending";   // Driver Assigned
    $step3 = "pending";   // On the way
    $step4 = "pending";   // Completed

    if ($status === 'assigned') {
        $step2 = "completed";
    } elseif ($status === 'in_progress') {
        $step2 = "completed";
        $step3 = "completed";
    } elseif ($status === 'completed') {
        $step2 = "completed";
        $step3 = "completed";
        $step4 = "completed";
    } elseif ($status === 'cancelled') {
        $step1 = "cancelled";
        $step2 = "cancelled";
        $step3 = "cancelled";
        $step4 = "cancelled";
    }

    $status_title = ucfirst(str_replace('_', ' ', $status));
    $status_color = 'bg-blue-600 text-white shadow-sm';
    if ($status === 'completed') $status_color = 'bg-emerald-500 text-white';
    if ($status === 'cancelled') $status_color = 'bg-rose-500 text-white';

    $pay_banner = "";
    if (($booking['payment_status'] ?? '') === 'under_review') {
        $utr_str = htmlspecialchars($booking['utr_number'] ?? 'Under Review');
        $pay_banner = "
        <div class='mb-6 bg-amber-500/10 border border-amber-500/30 rounded-xl p-4 flex items-start gap-3 text-xs text-amber-200'>
            <i class='fa-solid fa-hourglass-half text-amber-400 text-lg mt-0.5 animate-pulse'></i>
            <div class='flex-1'>
                <strong class='text-amber-400 block font-bold text-sm'>Payment Proof Submitted (Awaiting Admin Approval)</strong>
                <p class='text-slate-300 mt-0.5'>Your payment screenshot and UTR (<span class='font-mono font-bold text-amber-300'>$utr_str</span>) are being verified by our accounts desk. Once approved, chauffeur and vehicle execution will complete instantly.</p>
                <div class='mt-2 flex items-center gap-3'>
                    <a href='https://wa.me/918120868818?text=" . urlencode("Hi Avinash Travels, checking status for Booking #" . $booking_id . " with payment screenshot.") . "' target='_blank' class='inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-lg text-[11px] transition-colors'>
                        <i class='fa-brands fa-whatsapp'></i> WhatsApp Desk (Instant Check)
                    </a>
                </div>
            </div>
        </div>
        ";
    } elseif (($booking['payment_status'] ?? '') === 'rejected') {
        $rej_reason = htmlspecialchars($booking['admin_notes'] ?? 'Transaction could not be verified in bank ledger.');
        $pay_banner = "
        <div class='mb-6 bg-rose-500/10 border border-rose-500/30 rounded-xl p-4 flex items-start gap-3 text-xs text-rose-200'>
            <i class='fa-solid fa-circle-exclamation text-rose-400 text-lg mt-0.5'></i>
            <div>
                <strong class='text-rose-400 block font-bold'>Payment Verification Declined</strong>
                <p class='text-slate-300 mt-0.5'>Reason: $rej_reason</p>
                <p class='mt-1 text-slate-400'>Please contact our 24x7 customer support helpline at <a href='tel:8120868818' class='text-sky-400 font-bold underline'>8120868818</a> to re-submit proof or switch to cash payment.</p>
            </div>
        </div>
        ";
    }

    $card_banner = '';
    if (in_array($status, ['confirmed', 'assigned', 'in_progress', 'completed']) || ($booking['payment_status'] ?? '') === 'paid') {
        $card_res = generate_booking_card($booking['id']);
        $card_rel = $card_res['file_path'] ?? '';
        $wa_data = get_booking_whatsapp_data($booking['id']);
        $wa_url = $wa_data['whatsapp_url'] ?? '#';

        $card_banner = "
        <div class='mb-6 bg-slate-950 border border-slate-800 rounded-2xl p-4 shadow-xl'>
            <div class='flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-3'>
                <div>
                    <span class='text-[10px] uppercase font-bold text-amber-400 tracking-wider flex items-center gap-1.5'>
                        <i class='fa-solid fa-sparkles text-amber-400'></i> Official AI Booking Confirmation Pass
                    </span>
                    <p class='text-xs text-slate-300 mt-0.5'>Your reservation has been officially approved & verified by Avinash Travels dispatch desk.</p>
                </div>
                <div class='flex items-center gap-2'>
                    <a href='/api/generate_booking_card.php?id=" . urlencode($booking_id) . "&action=download' class='px-3 py-1.5 bg-slate-900 hover:bg-slate-800 border border-slate-700 text-sky-400 text-xs font-bold rounded-xl inline-flex items-center gap-1.5 transition-colors'>
                        <i class='fa-solid fa-download'></i> Download Pass (PNG)
                    </a>
                    <a href='" . htmlspecialchars($wa_url) . "' target='_blank' class='px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl inline-flex items-center gap-1.5 transition-colors shadow-md'>
                        <i class='fa-brands fa-whatsapp'></i> WhatsApp Pass
                    </a>
                </div>
            </div>
            <div class='rounded-xl overflow-hidden border border-slate-850 bg-black max-h-56 overflow-y-auto flex items-center justify-center'>
                <img src='/" . htmlspecialchars($card_rel) . "?v=" . time() . "' alt='Official AI Booking Pass' class='w-full object-cover rounded-xl'>
            </div>
        </div>
        ";
    }

    $html = "
    <div class='bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-xl'>
        <!-- Header -->
        <div class='flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-slate-800 pb-4 mb-6 gap-2'>
            <div>
                <h3 class='text-lg font-bold text-slate-200'>Booking Tracking Details</h3>
                <p class='text-xs text-slate-400'>ID: <span class='text-white font-mono'>$booking_id</span></p>
            </div>
            <span class='px-3 py-1 rounded text-xs font-semibold $status_color'>$status_title</span>
        </div>

        $pay_banner
        $card_banner

        <!-- Tracking Timeline -->
        <div class='grid grid-cols-4 text-center mb-8 relative pt-4'>
            <div class='absolute top-6 left-[12.5%] right-[12.5%] h-0.5 bg-slate-800 z-0'></div>
            
            <!-- Step 1 -->
            <div class='flex flex-col items-center z-10'>
                <div class='w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold " . ($step1 === 'cancelled' ? 'bg-rose-500 text-white' : 'bg-gradient-to-r from-blue-600 to-sky-500 text-white') . "'>1</div>
                <span class='text-xs font-semibold mt-2 " . ($step1 === 'cancelled' ? 'text-rose-400' : 'text-slate-300') . "'>" . ($step1 === 'cancelled' ? 'Cancelled' : 'Confirmed') . "</span>
            </div>
            <!-- Step 2 -->
            <div class='flex flex-col items-center z-10'>
                <div class='w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold " . ($step2 === 'completed' ? 'bg-gradient-to-r from-blue-600 to-sky-500 text-white' : ($step2 === 'cancelled' ? 'bg-slate-800 text-slate-500' : 'bg-slate-800 text-slate-400')) . "'>2</div>
                <span class='text-xs font-semibold mt-2 " . ($step2 === 'completed' ? 'text-slate-300' : 'text-slate-500') . "'>Assigned</span>
            </div>
            <!-- Step 3 -->
            <div class='flex flex-col items-center z-10'>
                <div class='w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold " . ($step3 === 'completed' ? 'bg-gradient-to-r from-blue-600 to-sky-500 text-white' : 'bg-slate-800 text-slate-400') . "'>3</div>
                <span class='text-xs font-semibold mt-2 " . ($step3 === 'completed' ? 'text-slate-300' : 'text-slate-500') . "'>In Progress</span>
            </div>
            <!-- Step 4 -->
            <div class='flex flex-col items-center z-10'>
                <div class='w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold " . ($step4 === 'completed' ? 'bg-emerald-500 text-white' : 'bg-slate-800 text-slate-400') . "'>4</div>
                <span class='text-xs font-semibold mt-2 " . ($step4 === 'completed' ? 'text-emerald-400' : 'text-slate-500') . "'>Completed</span>
            </div>
        </div>

        <!-- Trip Details -->
        <div class='grid grid-cols-1 md:grid-cols-2 gap-6 border-b border-slate-800 pb-6 mb-6'>
            <div>
                <h4 class='text-xs font-bold text-amber-500 uppercase tracking-wider mb-2'>Trip Specifications</h4>
                <p class='text-sm text-slate-300 mb-1'><strong class='text-slate-400'>Route:</strong> " . htmlspecialchars($booking['pickup_city']) . " → " . htmlspecialchars($booking['drop_city']) . "</p>
                <p class='text-sm text-slate-300 mb-1'><strong class='text-slate-400'>Type:</strong> " . ($booking['trip_type'] === 'one_way' ? 'One Way' : 'Round Trip') . "</p>
                <p class='text-sm text-slate-300 mb-1'><strong class='text-slate-400'>Date & Time:</strong> " . date('d M Y', strtotime($booking['pickup_date'])) . " at " . date('h:i A', strtotime($booking['pickup_time'])) . "</p>
                " . ($booking['return_date'] ? "<p class='text-sm text-slate-300 mb-1'><strong class='text-slate-400'>Return:</strong> " . date('d M Y', strtotime($booking['return_date'])) . " at " . date('h:i A', strtotime($booking['return_time'])) . "</p>" : "") . "
                <p class='text-sm text-slate-300'><strong class='text-slate-400'>Passenger:</strong> " . htmlspecialchars($booking['passenger_name']) . " (" . htmlspecialchars($booking['passenger_count']) . " pax)</p>
            </div>
            <div>
                <h4 class='text-xs font-bold text-amber-500 uppercase tracking-wider mb-2'>Address Details</h4>
                <p class='text-sm text-slate-300 mb-2'><strong class='text-slate-400'>Pickup:</strong> " . htmlspecialchars($booking['pickup_address']) . "</p>
                <p class='text-sm text-slate-300'><strong class='text-slate-400'>Dropoff:</strong> " . htmlspecialchars($booking['drop_address']) . "</p>
            </div>
        </div>

        <!-- Vehicle & Driver assignment -->
        <div class='grid grid-cols-1 md:grid-cols-2 gap-6'>
            <!-- Vehicle Info -->
            <div class='bg-slate-950 p-4 rounded-lg border border-slate-800'>
                <h4 class='text-xs font-bold text-slate-400 uppercase tracking-wider mb-2'>Vehicle Info</h4>";
                
                if ($booking['vehicle_id']) {
                    $html .= "
                    <p class='text-sm text-slate-200 font-bold'>" . htmlspecialchars($booking['vehicle_name']) . "</p>
                    <p class='text-xs text-slate-400'>Category: " . htmlspecialchars($booking['category_name']) . "</p>
                    <span class='inline-block mt-2 bg-amber-500/10 text-amber-400 text-xs px-2.5 py-0.5 rounded border border-amber-500/20 font-mono'>" . htmlspecialchars($booking['plate_number']) . "</span>";
                } else {
                    $html .= "
                    <p class='text-sm text-slate-500 italic'>Vehicle matching in progress...</p>";
                }
                
                $html .= "
            </div>

            <!-- Driver Info -->
            <div class='bg-slate-950 p-4 rounded-lg border border-slate-800'>
                <h4 class='text-xs font-bold text-slate-400 uppercase tracking-wider mb-2'>Driver Info</h4>";
                
                if ($booking['driver_name']) {
                    $html .= "
                    <p class='text-sm text-slate-200 font-bold'>" . htmlspecialchars($booking['driver_name']) . "</p>
                    <p class='text-xs text-slate-400'>Chauffeur Rating: ⭐ " . $booking['driver_rating'] . "</p>
                    <a href='tel:" . $booking['driver_phone'] . "' class='inline-flex items-center text-xs text-amber-500 font-semibold mt-2 hover:underline'>
                        <svg class='w-3.5 h-3.5 mr-1' fill='currentColor' viewBox='0 0 20 20'><path d='M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z'></path></svg>
                        Call Driver (" . htmlspecialchars($booking['driver_phone']) . ")
                    </a>";
                } else {
                    $html .= "
                    <p class='text-sm text-slate-500 italic mb-1'>Driver assignment in progress...</p>
                    <p class='text-xs text-slate-400'>Need urgent update? <a href='tel:8120868818' class='text-sky-400 font-bold hover:underline'>Call Mobile 8120868818</a></p>";
                }
                
                $html .= "
            </div>
        </div>";

    $html .= "
        <!-- Cost Breakdown -->
        <div class='mt-6 pt-4 border-t border-slate-800 flex justify-between items-center bg-slate-950/40 p-4 rounded-lg'>
            <div>
                <span class='text-xs text-slate-400'>Total Fare paid/payable</span>
                <p class='text-lg font-bold text-white'>₹" . number_format($booking['final_total'], 2) . "</p>
            </div>
            <div class='text-right'>
                <span class='text-xs text-slate-400'>Payment Method</span>
                <p class='text-sm font-semibold text-slate-300'>" . strtoupper($booking['payment_method']) . " (" . ucfirst($booking['payment_status']) . ")</p>
            </div>
        </div>
    </div>";

    echo json_encode([
        'success' => true,
        'html' => $html
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
