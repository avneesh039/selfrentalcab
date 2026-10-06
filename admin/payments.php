<?php
// Admin Payment Approvals & Execution Module - Avinash Travels Enterprise
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/booking_card_helper.php';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';

ensure_payment_columns();

$message = '';
$error = '';
$wa_modal_data = null;

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'approve_payment') {
        $booking_db_id = intval($_POST['booking_id'] ?? 0);
        $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
        $admin_note = trim($_POST['admin_note'] ?? 'Payment verified and approved by admin.');

        if ($booking_db_id > 0) {
            try {
                // Fetch booking details
                $stmt_bk = $pdo->prepare("
                    SELECT b.*, bp.name AS passenger_name, bp.phone AS passenger_phone, bp.email AS passenger_email
                    FROM `bookings` b
                    LEFT JOIN `booking_passengers` bp ON b.id = bp.booking_id
                    WHERE b.id = ?
                ");
                $stmt_bk->execute([$booking_db_id]);
                $bk = $stmt_bk->fetch();

                if ($bk) {
                    $new_status = ($vehicle_id > 0) ? 'assigned' : 'confirmed';

                    // 1. Update Booking
                    if ($vehicle_id > 0) {
                        $stmt_up = $pdo->prepare("UPDATE `bookings` SET payment_status = 'paid', status = ?, vehicle_id = ?, admin_notes = ? WHERE id = ?");
                        $stmt_up->execute([$new_status, $vehicle_id, $admin_note, $booking_db_id]);
                        
                        // Mark vehicle as on trip
                        $pdo->prepare("UPDATE `vehicles` SET status = 'on_trip' WHERE id = ?")->execute([$vehicle_id]);
                    } else {
                        $stmt_up = $pdo->prepare("UPDATE `bookings` SET payment_status = 'paid', status = ?, admin_notes = ? WHERE id = ?");
                        $stmt_up->execute([$new_status, $admin_note, $booking_db_id]);
                    }

                    // 2. Release & Execute Trip (make available for dispatch)
                    $stmt_tr = $pdo->prepare("UPDATE `trips` SET status = ? WHERE booking_id = ?");
                    $stmt_tr->execute([($vehicle_id > 0 ? 'assigned' : 'open'), $booking_db_id]);

                    // 3. Update Payments log table
                    $stmt_pay = $pdo->prepare("UPDATE `payments` SET status = 'completed', updated_at = NOW() WHERE booking_id = ?");
                    $stmt_pay->execute([$booking_db_id]);

                    // 4. Generate AI Booking Confirmation Card & WhatsApp Data
                    $card_res = generate_booking_card($booking_db_id);
                    $wa_data = get_booking_whatsapp_data($booking_db_id);

                    if ($wa_data && !empty($wa_data['passenger_phone'])) {
                        $stmt_not = $pdo->prepare("INSERT INTO `notifications` (type, recipient, message, gateway, status) VALUES (?, ?, ?, 'whatsapp', 'sent')");
                        $stmt_not->execute(['payment_approved', $wa_data['passenger_phone'], $wa_data['message']]);
                    }

                    $wa_modal_data = $wa_data;
                    $message = "Payment for Booking #" . htmlspecialchars($booking_code) . " approved! Official AI Booking Pass generated and WhatsApp redirect ready.";
                } else {
                    $error = "Booking record not found.";
                }
            } catch (Exception $e) {
                $error = "Error approving payment: " . $e->getMessage();
            }
        } else {
            $error = "Invalid booking ID.";
        }
    } elseif ($action === 'reject_payment') {
        $booking_db_id = intval($_POST['booking_id'] ?? 0);
        $reject_reason = trim($_POST['reject_reason'] ?? 'Payment screenshot / UTR verification failed.');

        if ($booking_db_id > 0) {
            try {
                // Update booking
                $stmt_up = $pdo->prepare("UPDATE `bookings` SET payment_status = 'rejected', admin_notes = ? WHERE id = ?");
                $stmt_up->execute([$reject_reason, $booking_db_id]);

                // Put trip on hold/cancel
                $stmt_tr = $pdo->prepare("UPDATE `trips` SET status = 'cancelled' WHERE booking_id = ? AND status = 'pending_approval'");
                $stmt_tr->execute([$booking_db_id]);

                // Update payment record
                $stmt_pay = $pdo->prepare("UPDATE `payments` SET status = 'failed' WHERE booking_id = ?");
                $stmt_pay->execute([$booking_db_id]);

                $message = "Payment rejected. Notification recorded with reason: " . htmlspecialchars($reject_reason);
            } catch (Exception $e) {
                $error = "Error rejecting payment: " . $e->getMessage();
            }
        }
    } elseif ($action === 'save_gateway_settings') {
        $upi_id = trim($_POST['upi_id'] ?? '8120868818@ybl');
        $bank_name = trim($_POST['bank_name'] ?? 'State Bank of India (SBI)');
        $account_name = trim($_POST['account_name'] ?? 'Avinash Tour and Travels');
        $account_number = trim($_POST['account_number'] ?? '38920194829');
        $ifsc_code = trim($_POST['ifsc_code'] ?? 'SBIN0000461');
        $branch = trim($_POST['branch'] ?? 'Raipur Main Branch');
        $qr_mode = in_array($_POST['payment_qr_mode'] ?? '', ['dynamic', 'uploaded']) ? $_POST['payment_qr_mode'] : 'dynamic';

        set_setting('upi_id', $upi_id);
        set_setting('payment_upi_id', $upi_id);
        set_setting('payment_qr_mode', $qr_mode);
        set_setting('bank_name', $bank_name);
        set_setting('bank_account_name', $account_name);
        set_setting('bank_account_number', $account_number);
        set_setting('bank_ifsc', $ifsc_code);
        set_setting('bank_branch', $branch);

        // Handle QR Code Upload
        if (isset($_FILES['payment_qr_image']) && $_FILES['payment_qr_image']['error'] === UPLOAD_ERR_OK) {
            $f_tmp = $_FILES['payment_qr_image']['tmp_name'];
            $f_name = $_FILES['payment_qr_image']['name'];
            $ext = strtolower(pathinfo($f_name, PATHINFO_EXTENSION));

            if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg'])) {
                $dest_dir = __DIR__ . '/../uploads/qr/';
                if (!file_exists($dest_dir)) {
                    mkdir($dest_dir, 0777, true);
                }
                $dest_filename = 'merchant_qr_' . time() . '.' . $ext;
                if (move_uploaded_file($f_tmp, $dest_dir . $dest_filename)) {
                    set_setting('payment_qr_image', 'uploads/qr/' . $dest_filename);
                } else {
                    $error = "Failed to upload QR image to server.";
                }
            } else {
                $error = "Invalid QR file format. Please upload PNG, JPG, WEBP, or SVG.";
            }
        } elseif (isset($_POST['remove_qr_image']) && $_POST['remove_qr_image'] === '1') {
            set_setting('payment_qr_image', '');
        }

        if (empty($error)) {
            $message = "Payment gateway, UPI ID, and QR code settings saved successfully! Changes are live on customer checkout.";
        }
    }
}

// Fetch KPI metrics
$count_under_review = 0;
$total_amount_pending = 0.0;
$count_approved_today = 0;
$count_rejected_total = 0;

try {
    if ($pdo) {
        $stmt_rev = $pdo->query("SELECT COUNT(*) AS total_count, COALESCE(SUM(final_total), 0) AS total_val FROM `bookings` WHERE payment_status = 'under_review'");
        $row_rev = $stmt_rev->fetch();
        $count_under_review = intval($row_rev['total_count'] ?? 0);
        $total_amount_pending = floatval($row_rev['total_val'] ?? 0.0);

        $stmt_app = $pdo->query("SELECT COUNT(*) FROM `bookings` WHERE payment_status = 'paid' AND DATE(created_at) = CURDATE()");
        $count_approved_today = intval($stmt_app->fetchColumn() ?? 0);

        $stmt_rej = $pdo->query("SELECT COUNT(*) FROM `bookings` WHERE payment_status = 'rejected'");
        $count_rejected_total = intval($stmt_rej->fetchColumn() ?? 0);
    }
} catch (Exception $e) {}

// Fetch Pending Queue
$pending_bookings = [];
try {
    if ($pdo) {
        $stmt_q = $pdo->query("
            SELECT b.*, bp.name AS passenger_name, bp.phone AS passenger_phone, bp.email AS passenger_email,
                   bp.pickup_address, bp.drop_address, vc.name AS category_name
            FROM `bookings` b
            LEFT JOIN `booking_passengers` bp ON b.id = bp.booking_id
            LEFT JOIN `vehicle_categories` vc ON b.category_id = vc.id
            WHERE b.payment_status = 'under_review'
            ORDER BY b.created_at DESC
        ");
        $pending_bookings = $stmt_q->fetchAll();
    }
} catch (Exception $e) {}

// Fetch Approved History (Last 30)
$approved_bookings = [];
try {
    if ($pdo) {
        $stmt_h = $pdo->query("
            SELECT b.*, bp.name AS passenger_name, bp.phone AS passenger_phone, vc.name AS category_name,
                   v.name AS vehicle_name
            FROM `bookings` b
            LEFT JOIN `booking_passengers` bp ON b.id = bp.booking_id
            LEFT JOIN `vehicle_categories` vc ON b.category_id = vc.id
            LEFT JOIN `vehicles` v ON b.vehicle_id = v.id
            WHERE b.payment_status = 'paid'
            ORDER BY b.created_at DESC
            LIMIT 30
        ");
        $approved_bookings = $stmt_h->fetchAll();
    }
} catch (Exception $e) {}

// Fetch Rejected History
$rejected_bookings = [];
try {
    if ($pdo) {
        $stmt_r = $pdo->query("
            SELECT b.*, bp.name AS passenger_name, bp.phone AS passenger_phone, vc.name AS category_name
            FROM `bookings` b
            LEFT JOIN `booking_passengers` bp ON b.id = bp.booking_id
            LEFT JOIN `vehicle_categories` vc ON b.category_id = vc.id
            WHERE b.payment_status = 'rejected'
            ORDER BY b.created_at DESC
            LIMIT 20
        ");
        $rejected_bookings = $stmt_r->fetchAll();
    }
} catch (Exception $e) {}

// Fetch available vehicles for quick dispatch modal
$available_vehicles = [];
try {
    if ($pdo) {
        $available_vehicles = $pdo->query("SELECT id, name, plate_number, category_id FROM `vehicles` WHERE status = 'available' ORDER BY name ASC")->fetchAll();
    }
} catch (Exception $e) {}

// Current Tab
$active_tab = $_GET['tab'] ?? 'pending';

// Gateway Settings
$curr_upi_id = get_setting('upi_id', get_setting('payment_upi_id', '8120868818@ybl'));
$curr_qr_img = get_setting('payment_qr_image', '');
$curr_qr_mode = get_setting('payment_qr_mode', 'dynamic');
$curr_bank_name = get_setting('bank_name', 'State Bank of India (SBI)');
$curr_acc_name = get_setting('bank_account_name', 'Avinash Tour and Travels');
$curr_acc_num = get_setting('bank_account_number', '38920194829');
$curr_ifsc = get_setting('bank_ifsc', 'SBIN0000461');
$curr_branch = get_setting('bank_branch', 'Raipur Main Branch');
?>

<div class="flex-1 p-6 md:p-8 overflow-y-auto space-y-6">
    <!-- Header Banner -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-500/15 text-amber-400 border border-amber-500/30 uppercase tracking-wider">
                    PAYMENT SETTLEMENT & VERIFICATION
                </span>
                <span class="text-xs text-slate-500 font-semibold">• Direct Screenshot Gateway</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black font-poppins text-white">Payment Proof Approvals & Ride Execution</h1>
            <p class="text-xs text-slate-400 mt-1">Review uploaded customer UPI screenshots & UTRs, execute ride dispatch, or reject illegitimate transactions</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="bookings.php" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-750 text-slate-200 text-xs font-bold rounded-xl border border-slate-700 transition-colors inline-flex items-center gap-1.5">
                <i class="fa-solid fa-receipt"></i> View All Bookings
            </a>
            <button onclick="switchTab('settings')" class="px-3.5 py-2 bg-sky-500/10 hover:bg-sky-500/20 text-sky-400 text-xs font-bold rounded-xl border border-sky-500/30 transition-colors inline-flex items-center gap-1.5">
                <i class="fa-solid fa-gear"></i> Gateway QR Settings
            </button>
        </div>
    </div>

    <!-- Feedback Alerts -->
    <?php if ($message): ?>
        <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 p-4 rounded-xl text-xs flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-circle-check text-base"></i>
            <div><?php echo $message; ?></div>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-rose-500/10 border border-rose-500/30 text-rose-400 p-4 rounded-xl text-xs flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-triangle-exclamation text-base"></i>
            <div><?php echo $error; ?></div>
        </div>
    <?php endif; ?>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl relative overflow-hidden group hover:border-amber-500/40 transition-colors">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Awaiting Verification</span>
                    <h3 class="text-2xl font-black font-poppins text-amber-400 mt-1 flex items-center gap-2">
                        <?php echo $count_under_review; ?>
                        <?php if ($count_under_review > 0): ?>
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-400 animate-ping"></span>
                        <?php endif; ?>
                    </h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center text-lg border border-amber-500/20">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
            </div>
            <p class="text-[11px] text-slate-500 mt-2">Requires admin review to execute</p>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl relative overflow-hidden group hover:border-sky-500/40 transition-colors">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Pending Revenue Value</span>
                    <h3 class="text-2xl font-black font-poppins text-white mt-1">₹<?php echo number_format($total_amount_pending, 2); ?></h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-sky-500/10 text-sky-400 flex items-center justify-center text-lg border border-sky-500/20">
                    <i class="fa-solid fa-sack-dollar"></i>
                </div>
            </div>
            <p class="text-[11px] text-slate-500 mt-2">Held pending payment confirmation</p>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl relative overflow-hidden group hover:border-emerald-500/40 transition-colors">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Approved Today</span>
                    <h3 class="text-2xl font-black font-poppins text-emerald-400 mt-1"><?php echo $count_approved_today; ?></h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-lg border border-emerald-500/20">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
            </div>
            <p class="text-[11px] text-slate-500 mt-2">Executed rides today</p>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl relative overflow-hidden group hover:border-rose-500/40 transition-colors">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Rejected Proofs</span>
                    <h3 class="text-2xl font-black font-poppins text-rose-400 mt-1"><?php echo $count_rejected_total; ?></h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center text-lg border border-rose-500/20">
                    <i class="fa-solid fa-circle-xmark"></i>
                </div>
            </div>
            <p class="text-[11px] text-slate-500 mt-2">Invalid or fake submissions</p>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-800 pb-3">
        <button onclick="switchTab('pending')" id="tab-btn-pending" class="tab-btn px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 <?php echo ($active_tab === 'pending') ? 'bg-amber-500 text-slate-950 shadow-md' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800'; ?>">
            <i class="fa-solid fa-hourglass-start"></i> Pending Approvals
            <?php if ($count_under_review > 0): ?>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold <?php echo ($active_tab === 'pending') ? 'bg-slate-950 text-amber-400' : 'bg-amber-500 text-slate-950'; ?>">
                    <?php echo $count_under_review; ?>
                </span>
            <?php endif; ?>
        </button>

        <button onclick="switchTab('approved')" id="tab-btn-approved" class="tab-btn px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 <?php echo ($active_tab === 'approved') ? 'bg-emerald-600 text-white shadow-md' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800'; ?>">
            <i class="fa-solid fa-clipboard-check"></i> Approved History (<?php echo count($approved_bookings); ?>)
        </button>

        <button onclick="switchTab('rejected')" id="tab-btn-rejected" class="tab-btn px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 <?php echo ($active_tab === 'rejected') ? 'bg-rose-600 text-white shadow-md' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800'; ?>">
            <i class="fa-solid fa-ban"></i> Rejected Proofs (<?php echo count($rejected_bookings); ?>)
        </button>

        <button onclick="switchTab('settings')" id="tab-btn-settings" class="tab-btn px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 <?php echo ($active_tab === 'settings') ? 'bg-sky-500 text-white shadow-md' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800'; ?>">
            <i class="fa-solid fa-qrcode"></i> UPI & Bank Config
        </button>
    </div>

    <!-- TAB 1: PENDING APPROVAL QUEUE -->
    <div id="tab-content-pending" class="space-y-4 <?php echo ($active_tab === 'pending') ? '' : 'hidden'; ?>">
        <?php if (!empty($pending_bookings)): ?>
            <div class="grid grid-cols-1 gap-4">
                <?php foreach ($pending_bookings as $p): ?>
                    <?php
                    $ss_path = $p['payment_screenshot'] ?? '';
                    $utr_val = $p['utr_number'] ?? 'Not provided';
                    $has_ss = !empty($ss_path) && file_exists(__DIR__ . '/../' . $ss_path);
                    $full_img_url = $has_ss ? ('../' . htmlspecialchars($ss_path)) : '';
                    ?>
                    <div class="bg-slate-900 border-2 border-amber-500/40 hover:border-amber-500 rounded-2xl p-5 sm:p-6 transition-all shadow-xl">
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                            <!-- Left: Screenshot Preview Box -->
                            <div class="lg:col-span-3 text-center flex flex-col items-center justify-center bg-slate-950 p-4 rounded-xl border border-slate-800">
                                <span class="text-[10px] uppercase font-bold text-amber-400 block mb-2 tracking-wider">
                                    <i class="fa-solid fa-receipt mr-1"></i> Customer Payment Proof
                                </span>
                                <?php if ($has_ss): ?>
                                    <div class="relative group cursor-pointer overflow-hidden rounded-lg border border-slate-700 bg-black/40" onclick="openScreenshotModal('<?php echo $full_img_url; ?>', '<?php echo htmlspecialchars($p['booking_id']); ?>', '<?php echo htmlspecialchars($utr_val); ?>')">
                                        <img src="<?php echo $full_img_url; ?>" alt="Payment Proof" class="w-40 h-44 object-cover group-hover:scale-105 transition-transform duration-300">
                                        <div class="absolute inset-0 bg-slate-950/60 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity text-white text-xs font-bold gap-1">
                                            <i class="fa-solid fa-magnifying-glass-plus text-base"></i> View High-Res
                                        </div>
                                    </div>
                                    <button type="button" onclick="openScreenshotModal('<?php echo $full_img_url; ?>', '<?php echo htmlspecialchars($p['booking_id']); ?>', '<?php echo htmlspecialchars($utr_val); ?>')" class="mt-2 text-sky-400 hover:text-sky-300 text-xs font-bold inline-flex items-center gap-1">
                                        <i class="fa-solid fa-expand"></i> Full Size Lightbox
                                    </button>
                                <?php else: ?>
                                    <div class="w-36 h-36 rounded-lg bg-slate-900 border border-dashed border-slate-700 flex flex-col items-center justify-center text-slate-500 p-2 text-center text-xs">
                                        <i class="fa-solid fa-file-excel text-2xl text-rose-400 mb-1"></i>
                                        <span>No image file uploaded</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Center: Booking & Transaction Details -->
                            <div class="lg:col-span-6 space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800 pb-2">
                                    <div>
                                        <span class="text-[10px] uppercase font-mono text-slate-400">Reference:</span>
                                        <strong class="text-base font-mono text-sky-400 ml-1"><?php echo htmlspecialchars($p['booking_id']); ?></strong>
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-500/15 text-amber-400 border border-amber-500/30 uppercase tracking-wider animate-pulse">
                                        ⏳ Awaiting Approval
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                    <div>
                                        <span class="text-slate-400 block text-[10px] uppercase font-bold">Passenger:</span>
                                        <strong class="text-white text-sm"><?php echo htmlspecialchars($p['passenger_name']); ?></strong>
                                        <div class="mt-0.5 flex items-center gap-2">
                                            <a href="tel:<?php echo htmlspecialchars($p['passenger_phone']); ?>" class="text-sky-400 hover:underline">
                                                <i class="fa-solid fa-phone mr-1"></i><?php echo htmlspecialchars($p['passenger_phone']); ?>
                                            </a>
                                            <a href="https://wa.me/91<?php echo preg_replace('/[^\d]/', '', $p['passenger_phone']); ?>?text=Hi%20<?php echo urlencode($p['passenger_name']); ?>%2C%20regarding%20your%20Avinash%20Travels%20booking%20<?php echo urlencode($p['booking_id']); ?>" target="_blank" class="text-emerald-400 hover:text-emerald-300 font-bold" title="Chat on WhatsApp">
                                                <i class="fa-brands fa-whatsapp text-sm"></i>
                                            </a>
                                        </div>
                                    </div>

                                    <div>
                                        <span class="text-slate-400 block text-[10px] uppercase font-bold">Trip Route & Date:</span>
                                        <span class="text-white font-semibold"><?php echo htmlspecialchars($p['pickup_city']); ?> → <?php echo htmlspecialchars($p['drop_city']); ?></span>
                                        <div class="text-[11px] text-slate-400 mt-0.5">
                                            <i class="fa-regular fa-calendar mr-1"></i><?php echo date('d-M-Y', strtotime($p['pickup_date'])); ?> at <?php echo date('h:i A', strtotime($p['pickup_time'])); ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Transaction Info Box -->
                                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                                    <div>
                                        <span class="text-slate-500 block text-[10px] uppercase">Fare Amount:</span>
                                        <strong class="text-amber-400 text-base font-bold">₹<?php echo number_format($p['final_total'], 2); ?></strong>
                                    </div>
                                    <div>
                                        <span class="text-slate-500 block text-[10px] uppercase">Payment Method:</span>
                                        <span class="text-slate-200 font-semibold uppercase"><?php echo htmlspecialchars($p['payment_method']); ?></span>
                                    </div>
                                    <div>
                                        <span class="text-slate-500 block text-[10px] uppercase">UTR / Ref No:</span>
                                        <div class="flex items-center gap-1.5">
                                            <code class="text-sky-300 font-bold text-xs bg-slate-900 px-1.5 py-0.5 rounded border border-slate-700"><?php echo htmlspecialchars($utr_val); ?></code>
                                            <button type="button" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($utr_val); ?>'); alert('UTR copied: <?php echo htmlspecialchars($utr_val); ?>');" class="text-slate-400 hover:text-white" title="Copy UTR">
                                                <i class="fa-regular fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-[11px] text-slate-400">
                                    <strong class="text-slate-300">Pickup Location:</strong> <?php echo htmlspecialchars($p['pickup_address']); ?>
                                </div>
                            </div>

                            <!-- Right: Decision Actions -->
                            <div class="lg:col-span-3 flex flex-col gap-2.5 justify-center border-t lg:border-t-0 lg:border-l border-slate-800 pt-4 lg:pt-0 lg:pl-6">
                                <button type="button" onclick="openApproveModal(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['booking_id']); ?>', '<?php echo htmlspecialchars($p['passenger_name']); ?>', '<?php echo number_format($p['final_total'], 2); ?>', <?php echo intval($p['category_id']); ?>)" class="w-full px-4 py-3 bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white font-extrabold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-emerald-900/30 transition-all flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-circle-check text-sm"></i> Approve & Execute Ride
                                </button>

                                <button type="button" onclick="openRejectModal(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['booking_id']); ?>')" class="w-full px-4 py-2.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 border border-rose-500/30 font-bold text-xs rounded-xl transition-colors flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-circle-xmark"></i> Reject Payment Proof
                                </button>

                                <a href="tel:<?php echo htmlspecialchars($p['passenger_phone']); ?>" class="w-full px-4 py-2 bg-slate-800 hover:bg-slate-750 text-slate-300 text-xs font-semibold rounded-xl text-center transition-colors">
                                    <i class="fa-solid fa-headset mr-1"></i> Call Customer
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-12 text-center space-y-3">
                <div class="w-16 h-16 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center text-3xl mx-auto">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <h3 class="text-lg font-bold text-white">Payment Approval Queue is Clear!</h3>
                <p class="text-xs text-slate-400 max-w-md mx-auto">All customer payment screenshots and UPI transactions have been verified and processed. New submissions will appear here instantly.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 2: APPROVED PAYMENTS AUDIT HISTORY -->
    <div id="tab-content-approved" class="space-y-4 <?php echo ($active_tab === 'approved') ? '' : 'hidden'; ?>">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-lg">
            <div class="p-4 border-b border-slate-800 flex justify-between items-center">
                <h3 class="text-sm font-bold text-white font-poppins">Verified & Settled Transactions History</h3>
                <span class="text-xs text-slate-400">Showing last <?php echo count($approved_bookings); ?> transactions</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-950 text-slate-400 text-[10px] uppercase font-bold border-b border-slate-800">
                            <th class="p-3.5">Booking Ref</th>
                            <th class="p-3.5">Passenger</th>
                            <th class="p-3.5">Route</th>
                            <th class="p-3.5">Amount</th>
                            <th class="p-3.5">Proof / UTR</th>
                            <th class="p-3.5">Execution Status</th>
                            <th class="p-3.5">Verified Date</th>
                            <th class="p-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <?php if (!empty($approved_bookings)): ?>
                            <?php foreach ($approved_bookings as $ab): ?>
                                <?php
                                $ss = $ab['payment_screenshot'] ?? '';
                                $has_ss = !empty($ss) && file_exists(__DIR__ . '/../' . $ss);
                                ?>
                                <tr class="hover:bg-slate-850/50 transition-colors">
                                    <td class="p-3.5 font-mono font-bold text-sky-400"><?php echo htmlspecialchars($ab['booking_id']); ?></td>
                                    <td class="p-3.5">
                                        <strong class="text-white"><?php echo htmlspecialchars($ab['passenger_name']); ?></strong><br>
                                        <span class="text-[10px] text-slate-400"><?php echo htmlspecialchars($ab['passenger_phone']); ?></span>
                                    </td>
                                    <td class="p-3.5 text-slate-300">
                                        <?php echo htmlspecialchars($ab['pickup_city']); ?> → <?php echo htmlspecialchars($ab['drop_city']); ?>
                                    </td>
                                    <td class="p-3.5 text-emerald-400 font-bold">₹<?php echo number_format($ab['final_total'], 2); ?></td>
                                    <td class="p-3.5">
                                        <div class="flex items-center gap-2">
                                            <?php if ($has_ss): ?>
                                                <button type="button" onclick="openScreenshotModal('../<?php echo htmlspecialchars($ss); ?>', '<?php echo htmlspecialchars($ab['booking_id']); ?>', '<?php echo htmlspecialchars($ab['utr_number'] ?? ''); ?>')" class="px-2 py-0.5 bg-sky-500/10 hover:bg-sky-500/20 text-sky-400 border border-sky-500/30 rounded text-[10px] font-bold inline-flex items-center gap-1">
                                                    <i class="fa-solid fa-image"></i> Proof
                                                </button>
                                            <?php endif; ?>
                                            <span class="font-mono text-[11px] text-slate-300"><?php echo htmlspecialchars($ab['utr_number'] ?: 'Direct'); ?></span>
                                        </div>
                                    </td>
                                    <td class="p-3.5">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                                            ✓ <?php echo ucfirst($ab['status']); ?>
                                        </span>
                                    </td>
                                    <td class="p-3.5 text-slate-400 text-[11px]">
                                        <?php echo date('d-M-Y H:i', strtotime($ab['created_at'])); ?>
                                    </td>
                                    <td class="p-3.5 text-right flex items-center justify-end gap-1.5">
                                        <button type="button" onclick="openWhatsAppCardModal(<?php echo $ab['id']; ?>)" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-[10px] font-bold inline-flex items-center gap-1 shadow-sm" title="Send AI Booking Pass via WhatsApp">
                                            <i class="fa-brands fa-whatsapp text-xs"></i> WhatsApp Pass
                                        </button>
                                        <a href="../api/generate_booking_card.php?id=<?php echo urlencode($ab['booking_id']); ?>&action=download" target="_blank" class="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-sky-400 border border-slate-750 rounded text-[10px] font-bold" title="Download AI Pass PNG">
                                            <i class="fa-solid fa-download"></i>
                                        </a>
                                        <a href="../customer/invoice.php?id=<?php echo $ab['id']; ?>" target="_blank" class="px-2 py-1 bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-300 rounded text-[10px] font-bold" title="View Invoice">
                                            Invoice
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="p-8 text-center text-slate-500 italic">No approved payments found yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 3: REJECTED PROOFS -->
    <div id="tab-content-rejected" class="space-y-4 <?php echo ($active_tab === 'rejected') ? '' : 'hidden'; ?>">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-lg">
            <div class="p-4 border-b border-slate-800 flex justify-between items-center">
                <h3 class="text-sm font-bold text-white font-poppins">Rejected Payment Submissions</h3>
                <span class="text-xs text-slate-400">Total: <?php echo count($rejected_bookings); ?></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-950 text-slate-400 text-[10px] uppercase font-bold border-b border-slate-800">
                            <th class="p-3.5">Booking Ref</th>
                            <th class="p-3.5">Customer</th>
                            <th class="p-3.5">Route</th>
                            <th class="p-3.5">Amount</th>
                            <th class="p-3.5">Rejection Reason</th>
                            <th class="p-3.5">Date</th>
                            <th class="p-3.5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <?php if (!empty($rejected_bookings)): ?>
                            <?php foreach ($rejected_bookings as $rb): ?>
                                <tr class="hover:bg-slate-850/50 transition-colors">
                                    <td class="p-3.5 font-mono font-bold text-rose-400"><?php echo htmlspecialchars($rb['booking_id']); ?></td>
                                    <td class="p-3.5">
                                        <strong class="text-white"><?php echo htmlspecialchars($rb['passenger_name']); ?></strong><br>
                                        <span class="text-[10px] text-slate-400"><?php echo htmlspecialchars($rb['passenger_phone']); ?></span>
                                    </td>
                                    <td class="p-3.5 text-slate-300">
                                        <?php echo htmlspecialchars($rb['pickup_city']); ?> → <?php echo htmlspecialchars($rb['drop_city']); ?>
                                    </td>
                                    <td class="p-3.5 text-slate-400 font-bold">₹<?php echo number_format($rb['final_total'], 2); ?></td>
                                    <td class="p-3.5 text-rose-300 text-[11px]">
                                        <?php echo htmlspecialchars($rb['admin_notes'] ?: 'Invalid transaction reference'); ?>
                                    </td>
                                    <td class="p-3.5 text-slate-400 text-[11px]">
                                        <?php echo date('d-M-Y', strtotime($rb['created_at'])); ?>
                                    </td>
                                    <td class="p-3.5 text-right">
                                        <a href="tel:<?php echo htmlspecialchars($rb['passenger_phone']); ?>" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-sky-400 rounded text-[10px] font-bold">
                                            Contact
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-500 italic">No rejected payments recorded.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 4: UPI & GATEWAY CONFIGURATION -->
    <div id="tab-content-settings" class="space-y-6 <?php echo ($active_tab === 'settings') ? '' : 'hidden'; ?>">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl max-w-4xl">
            <div class="border-b border-slate-800 pb-4 mb-6">
                <h3 class="text-base font-bold text-white font-poppins flex items-center gap-2">
                    <i class="fa-solid fa-qrcode text-sky-400"></i> Business UPI & Direct Bank Settlement Configuration
                </h3>
                <p class="text-xs text-slate-400 mt-1">Configure company UPI details and bank accounts shown on the customer checkout payment gateway</p>
            </div>

            <form method="POST" enctype="multipart/form-data" class="space-y-5 text-xs">
                <input type="hidden" name="action" value="save_gateway_settings">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-1.5">
                        <label class="block text-[10px] font-bold text-amber-400 uppercase tracking-wider">Company Business UPI ID (VPA) *</label>
                        <input type="text" name="upi_id" value="<?php echo htmlspecialchars($curr_upi_id); ?>" class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-xl font-mono text-white text-sm font-bold focus:outline-none focus:border-sky-400" required>
                        <p class="text-[10px] text-slate-400">Reflects live across customer checkout and provides instant 1-click copy.</p>
                    </div>

                    <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-300 uppercase tracking-wider">Account Holder / Beneficiary Name *</label>
                        <input type="text" name="account_name" value="<?php echo htmlspecialchars($curr_acc_name); ?>" class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm font-semibold focus:outline-none focus:border-sky-400" required>
                        <p class="text-[10px] text-slate-400">Official business or proprietor name registered with bank.</p>
                    </div>
                </div>

                <!-- QR Mode & Custom QR Upload -->
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="block text-[10px] font-bold text-sky-400 uppercase tracking-wider">
                            <i class="fa-solid fa-qrcode mr-1"></i> QR Code Display Mode & Scanner Upload
                        </label>
                        <span class="text-[10px] text-slate-500">PNG, JPG, WEBP, SVG</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="flex items-start gap-2.5 p-3 bg-slate-900 rounded-xl border border-slate-800 hover:border-slate-700 cursor-pointer">
                            <input type="radio" name="payment_qr_mode" value="dynamic" <?php echo ($curr_qr_mode !== 'uploaded') ? 'checked' : ''; ?> class="mt-0.5 text-sky-500">
                            <div>
                                <span class="block text-white font-bold text-xs">Auto Dynamic QR Code</span>
                                <span class="block text-[10px] text-slate-400">Encodes live ride fare amount into QR scan</span>
                            </div>
                        </label>
                        <label class="flex items-start gap-2.5 p-3 bg-slate-900 rounded-xl border border-slate-800 hover:border-slate-700 cursor-pointer">
                            <input type="radio" name="payment_qr_mode" value="uploaded" <?php echo ($curr_qr_mode === 'uploaded') ? 'checked' : ''; ?> class="mt-0.5 text-sky-500">
                            <div>
                                <span class="block text-white font-bold text-xs">Uploaded Custom QR Code Image</span>
                                <span class="block text-[10px] text-slate-400">Displays your official PhonePe / GPay QR scan poster</span>
                            </div>
                        </label>
                    </div>

                    <!-- Existing QR Preview if uploaded -->
                    <?php if (!empty($curr_qr_img) && file_exists(__DIR__ . '/../' . $curr_qr_img)): ?>
                        <div class="bg-slate-900 p-3 rounded-xl border border-slate-800 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <img src="../<?php echo htmlspecialchars($curr_qr_img); ?>?v=<?php echo time(); ?>" alt="Merchant QR" class="w-16 h-16 object-contain rounded-lg border border-slate-700 bg-white p-1">
                                <div>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 block mb-1">
                                        ✓ Uploaded QR Active
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-mono"><?php echo basename($curr_qr_img); ?></span>
                                </div>
                            </div>
                            <label class="text-[11px] text-rose-400 hover:text-rose-300 flex items-center gap-1 cursor-pointer">
                                <input type="checkbox" name="remove_qr_image" value="1" class="text-rose-500">
                                <span>Remove Image</span>
                            </label>
                        </div>
                    <?php endif; ?>

                    <div class="relative border-2 border-dashed border-slate-700 hover:border-sky-400 rounded-xl p-3.5 text-center cursor-pointer transition-colors bg-slate-900/50" onclick="document.getElementById('pay_tab_qr_file').click()">
                        <input type="file" name="payment_qr_image" id="pay_tab_qr_file" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="hidden">
                        <div class="space-y-1">
                            <i class="fa-solid fa-cloud-arrow-up text-sky-400 text-xl"></i>
                            <p class="text-xs font-bold text-slate-300">Click to upload new QR Code image</p>
                            <p class="text-[10px] text-slate-500">Upload your PhonePe / Paytm / GPay QR standee or poster</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Bank Name</label>
                        <input type="text" name="bank_name" value="<?php echo htmlspecialchars($curr_bank_name); ?>" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white text-xs focus:outline-none focus:border-sky-400" required>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Account Number</label>
                        <input type="text" name="account_number" value="<?php echo htmlspecialchars($curr_acc_num); ?>" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl font-mono text-white text-xs focus:outline-none focus:border-sky-400" required>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">IFSC Code</label>
                        <input type="text" name="ifsc_code" value="<?php echo htmlspecialchars($curr_ifsc); ?>" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl font-mono text-white text-xs uppercase focus:outline-none focus:border-sky-400" required>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Branch</label>
                        <input type="text" name="branch" value="<?php echo htmlspecialchars($curr_branch); ?>" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white text-xs focus:outline-none focus:border-sky-400">
                    </div>
                </div>

                <!-- Preview QR Card -->
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 flex flex-col sm:flex-row items-center gap-4">
                    <?php if ($curr_qr_mode === 'uploaded' && !empty($curr_qr_img) && file_exists(__DIR__ . '/../' . $curr_qr_img)): ?>
                        <img src="../<?php echo htmlspecialchars($curr_qr_img); ?>?v=<?php echo time(); ?>" alt="Active Custom QR" class="w-24 h-24 rounded-lg bg-white p-1 object-contain border border-slate-700">
                        <div class="text-xs text-slate-400">
                            <strong class="text-emerald-400 block mb-1"><i class="fa-solid fa-circle-check mr-1"></i> Custom QR Active on Frontend</strong>
                            Customers will scan this uploaded merchant QR code during checkout.
                        </div>
                    <?php else: ?>
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=upi%3A%2F%2Fpay%3Fpa%3D<?php echo urlencode($curr_upi_id); ?>%26pn%3D<?php echo urlencode($curr_acc_name); ?>" alt="Preview QR" class="w-24 h-24 rounded-lg bg-white p-1">
                        <div class="text-xs text-slate-400">
                            <strong class="text-sky-400 block mb-1"><i class="fa-solid fa-bolt mr-1"></i> Live Dynamic QR Generation Active</strong>
                            The system dynamically attaches the exact booking total to this QR code during checkout.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="pt-4 border-t border-slate-800 text-right">
                    <button type="submit" class="px-6 py-2.5 bg-sky-500 hover:bg-sky-400 text-white font-bold rounded-xl text-xs uppercase tracking-wider transition-colors shadow-lg shadow-sky-500/20">
                        <i class="fa-solid fa-floppy-disk mr-1.5"></i> Save & Reflect on Frontend Checkout
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: High-Res Screenshot Lightbox -->
<div id="screenshot-lightbox-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/90 backdrop-blur-md p-4 overflow-y-auto">
    <div class="relative max-w-4xl w-full bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-2xl my-8">
        <div class="flex items-center justify-between px-6 py-4 bg-slate-950 border-b border-slate-800">
            <div>
                <h3 class="text-sm font-bold text-white font-poppins flex items-center gap-2">
                    <i class="fa-solid fa-receipt text-amber-400"></i> Payment Proof Screenshot
                </h3>
                <p class="text-[11px] text-slate-400">Booking: <span id="lb-booking-ref" class="text-sky-400 font-mono font-bold">-</span> | UTR: <span id="lb-utr" class="text-amber-400 font-mono font-bold">-</span></p>
            </div>
            <button onclick="closeScreenshotModal()" class="text-slate-400 hover:text-white text-xl p-1 focus:outline-none">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="p-6 flex flex-col items-center justify-center max-h-[75vh] overflow-y-auto bg-slate-950/80">
            <img id="lb-image" src="" alt="Proof High Res" class="max-w-full max-h-[65vh] object-contain rounded-xl border border-slate-800 shadow-2xl">
        </div>

        <div class="px-6 py-4 bg-slate-950 border-t border-slate-800 flex items-center justify-between">
            <a id="lb-download-btn" href="" download class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold inline-flex items-center gap-1.5">
                <i class="fa-solid fa-download"></i> Download Full Image
            </a>
            <button onclick="closeScreenshotModal()" class="px-5 py-2 bg-slate-800 hover:bg-slate-750 text-white rounded-xl text-xs font-bold">
                Close
            </button>
        </div>
    </div>
</div>

<!-- MODAL: Approve & Execute Ride -->
<div id="approve-booking-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 overflow-y-auto">
    <div class="relative max-w-lg w-full bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-2xl p-6 my-8">
        <button onclick="closeApproveModal()" class="absolute top-5 right-5 text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>

        <div class="mb-4">
            <span class="w-10 h-10 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 flex items-center justify-center text-lg mb-2">
                <i class="fa-solid fa-circle-check"></i>
            </span>
            <h3 class="text-base font-bold text-white font-poppins">Approve Payment & Execute Trip</h3>
            <p class="text-xs text-slate-400 mt-0.5">Booking Ref: <strong id="app-modal-ref" class="text-sky-400 font-mono">-</strong></p>
        </div>

        <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800 mb-4 text-xs space-y-1">
            <div class="flex justify-between">
                <span class="text-slate-400">Passenger:</span>
                <span class="text-white font-bold" id="app-modal-name">-</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-400">Verified Amount:</span>
                <span class="text-emerald-400 font-bold" id="app-modal-amount">-</span>
            </div>
        </div>

        <form method="POST" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="approve_payment">
            <input type="hidden" name="booking_id" id="app-modal-booking-id">

            <!-- Optional Driver Quick Dispatch -->
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
                    Assign Fleet Vehicle Now (Optional):
                </label>
                <select name="vehicle_id" id="app-modal-vehicle" class="w-full px-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white text-xs focus:outline-none focus:border-sky-400">
                    <option value="0">-- Leave for Dispatch Queue (Release to Open Bids) --</option>
                    <?php foreach ($available_vehicles as $veh): ?>
                        <option value="<?php echo $veh['id']; ?>">
                            <?php echo htmlspecialchars($veh['name']); ?> (<?php echo htmlspecialchars($veh['plate_number']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[10px] text-slate-500 mt-1">If unassigned, ride opens in Driver Dispatch Portal for bidding.</p>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
                    Admin Approval Verification Note:
                </label>
                <input type="text" name="admin_note" value="Payment verified via screenshot & bank ledger. Approved for execution." class="w-full px-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white text-xs focus:outline-none focus:border-sky-400">
            </div>

            <div class="bg-emerald-500/10 border border-emerald-500/20 p-3 rounded-xl text-[11px] text-emerald-400 flex items-center gap-2">
                <i class="fa-solid fa-bell"></i> Passenger will be notified via SMS/WhatsApp automatically.
            </div>

            <div class="pt-3 border-t border-slate-800 flex justify-end gap-2">
                <button type="button" onclick="closeApproveModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold rounded-xl text-xs">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs uppercase tracking-wider shadow-lg shadow-emerald-900/40">
                    <i class="fa-solid fa-bolt mr-1"></i> Confirm & Execute
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Reject Payment -->
<div id="reject-booking-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 overflow-y-auto">
    <div class="relative max-w-md w-full bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-2xl p-6 my-8">
        <button onclick="closeRejectModal()" class="absolute top-5 right-5 text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>

        <div class="mb-4">
            <span class="w-10 h-10 rounded-full bg-rose-500/10 text-rose-400 border border-rose-500/30 flex items-center justify-center text-lg mb-2">
                <i class="fa-solid fa-ban"></i>
            </span>
            <h3 class="text-base font-bold text-white font-poppins">Reject Payment Proof</h3>
            <p class="text-xs text-slate-400 mt-0.5">Booking Ref: <strong id="rej-modal-ref" class="text-rose-400 font-mono">-</strong></p>
        </div>

        <form method="POST" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="reject_payment">
            <input type="hidden" name="booking_id" id="rej-modal-booking-id">

            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
                    Select / Specify Rejection Reason: *
                </label>
                <select onchange="document.getElementById('rej-reason-input').value = this.value" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white text-xs mb-2">
                    <option value="">-- Choose Common Reason --</option>
                    <option value="UTR / Transaction reference not found in bank statement.">UTR not found in bank ledger</option>
                    <option value="Payment amount transferred is less than required fare.">Amount is less than fare</option>
                    <option value="Screenshot is blurred, forged or illegible.">Blurred or unreadable screenshot</option>
                    <option value="Duplicate payment screenshot used in previous booking.">Duplicate screenshot</option>
                </select>
                <input type="text" name="reject_reason" id="rej-reason-input" placeholder="Enter reason to notify customer..." class="w-full px-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white text-xs focus:outline-none focus:border-rose-400" required>
            </div>

            <p class="text-[11px] text-slate-500">The trip will be held and customer alerted to provide valid transaction proof.</p>

            <div class="pt-3 border-t border-slate-800 flex justify-end gap-2">
                <button type="button" onclick="closeRejectModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold rounded-xl text-xs">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 bg-rose-600 hover:bg-rose-500 text-white font-bold rounded-xl text-xs uppercase tracking-wider shadow-lg shadow-rose-900/40">
                    Reject Payment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function switchTab(tab) {
        const tabs = ['pending', 'approved', 'rejected', 'settings'];
        tabs.forEach(t => {
            const content = document.getElementById(`tab-content-${t}`);
            const btn = document.getElementById(`tab-btn-${t}`);
            if (content) content.classList.add('hidden');
            if (btn) {
                btn.classList.remove('bg-amber-500', 'bg-emerald-600', 'bg-rose-600', 'bg-sky-500', 'text-slate-950', 'text-white');
                btn.classList.add('bg-slate-900', 'text-slate-400', 'border', 'border-slate-800');
            }
        });

        const activeContent = document.getElementById(`tab-content-${tab}`);
        const activeBtn = document.getElementById(`tab-btn-${tab}`);
        if (activeContent) activeContent.classList.remove('hidden');
        if (activeBtn) {
            activeBtn.classList.remove('bg-slate-900', 'text-slate-400', 'border-slate-800');
            if (tab === 'pending') activeBtn.classList.add('bg-amber-500', 'text-slate-950', 'shadow-md');
            else if (tab === 'approved') activeBtn.classList.add('bg-emerald-600', 'text-white', 'shadow-md');
            else if (tab === 'rejected') activeBtn.classList.add('bg-rose-600', 'text-white', 'shadow-md');
            else if (tab === 'settings') activeBtn.classList.add('bg-sky-500', 'text-white', 'shadow-md');
        }
    }

    // Lightbox modal
    function openScreenshotModal(imgUrl, bookingRef, utr) {
        document.getElementById('lb-image').src = imgUrl;
        document.getElementById('lb-booking-ref').innerText = bookingRef;
        document.getElementById('lb-utr').innerText = utr;
        document.getElementById('lb-download-btn').href = imgUrl;
        document.getElementById('screenshot-lightbox-modal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeScreenshotModal() {
        document.getElementById('screenshot-lightbox-modal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    // Approve Modal
    function openApproveModal(id, ref, name, amount, catId) {
        document.getElementById('app-modal-booking-id').value = id;
        document.getElementById('app-modal-ref').innerText = ref;
        document.getElementById('app-modal-name').innerText = name;
        document.getElementById('app-modal-amount').innerText = '₹' + amount;
        document.getElementById('approve-booking-modal').classList.remove('hidden');
    }

    function closeApproveModal() {
        document.getElementById('approve-booking-modal').classList.add('hidden');
    }

    // Reject Modal
    function openRejectModal(id, ref) {
        document.getElementById('rej-modal-booking-id').value = id;
        document.getElementById('rej-modal-ref').innerText = ref;
        document.getElementById('reject-booking-modal').classList.remove('hidden');
    }

    function closeRejectModal() {
        document.getElementById('reject-booking-modal').classList.add('hidden');
    }

    // WhatsApp Pass & Redirect Modal
    let waCountdownInterval = null;

    function openWhatsAppCardModal(bookingId) {
        const modal = document.getElementById('whatsapp-card-modal');
        const passImg = document.getElementById('wa-pass-img');
        const sendBtn = document.getElementById('wa-send-btn');
        const dlBtn = document.getElementById('wa-dl-btn');
        const msgBox = document.getElementById('wa-msg-text');
        const titleRef = document.getElementById('wa-modal-ref');
        const titleCust = document.getElementById('wa-modal-cust');

        titleRef.innerText = 'Loading...';
        titleCust.innerText = '';
        passImg.src = '';
        passImg.classList.add('opacity-40');
        modal.classList.remove('hidden');

        fetch(`../api/booking_whatsapp.php?id=${bookingId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    titleRef.innerText = '#' + data.booking_id;
                    titleCust.innerText = data.passenger_name + ' (' + data.passenger_phone + ')';
                    passImg.src = `../${data.card_relative}?v=${Date.now()}`;
                    passImg.classList.remove('opacity-40');
                    sendBtn.href = data.whatsapp_url;
                    dlBtn.href = `../api/generate_booking_card.php?id=${encodeURIComponent(data.booking_id)}&action=download`;
                    msgBox.value = data.message;
                } else {
                    alert(data.error || 'Failed to generate WhatsApp pass data.');
                    closeWhatsAppCardModal();
                }
            })
            .catch(err => {
                alert('Connection error retrieving WhatsApp pass.');
                closeWhatsAppCardModal();
            });
    }

    function closeWhatsAppCardModal() {
        const modal = document.getElementById('whatsapp-card-modal');
        modal.classList.add('hidden');
        if (waCountdownInterval) {
            clearInterval(waCountdownInterval);
            waCountdownInterval = null;
        }
    }

    function copyWhatsAppText() {
        const txt = document.getElementById('wa-msg-text');
        txt.select();
        navigator.clipboard.writeText(txt.value).then(() => {
            alert('WhatsApp message copied to clipboard!');
        });
    }

    function cancelWaCountdown() {
        if (waCountdownInterval) {
            clearInterval(waCountdownInterval);
            waCountdownInterval = null;
        }
        const banner = document.getElementById('wa-countdown-banner');
        if (banner) banner.classList.add('hidden');
    }

    <?php if (!empty($wa_modal_data)): ?>
    // Auto-launch modal after approval
    window.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('whatsapp-card-modal');
        const passImg = document.getElementById('wa-pass-img');
        const sendBtn = document.getElementById('wa-send-btn');
        const dlBtn = document.getElementById('wa-dl-btn');
        const msgBox = document.getElementById('wa-msg-text');
        const titleRef = document.getElementById('wa-modal-ref');
        const titleCust = document.getElementById('wa-modal-cust');

        titleRef.innerText = '#<?php echo addslashes($wa_modal_data['booking_id']); ?>';
        titleCust.innerText = '<?php echo addslashes($wa_modal_data['passenger_name']); ?> (<?php echo addslashes($wa_modal_data['passenger_phone']); ?>)';
        passImg.src = '../<?php echo addslashes($wa_modal_data['card_relative']); ?>?v=<?php echo time(); ?>';
        passImg.classList.remove('opacity-40');
        sendBtn.href = '<?php echo $wa_modal_data['whatsapp_url']; ?>';
        dlBtn.href = '../api/generate_booking_card.php?id=<?php echo urlencode($wa_modal_data['booking_id']); ?>&action=download';
        msgBox.value = <?php echo json_encode($wa_modal_data['message']); ?>;

        modal.classList.remove('hidden');

        // Auto-redirect countdown: 3 seconds to open WhatsApp
        let timeLeft = 3;
        const countSpan = document.getElementById('wa-countdown-num');
        const banner = document.getElementById('wa-countdown-banner');
        if (banner) banner.classList.remove('hidden');

        waCountdownInterval = setInterval(() => {
            timeLeft--;
            if (countSpan) countSpan.innerText = timeLeft;
            if (timeLeft <= 0) {
                clearInterval(waCountdownInterval);
                waCountdownInterval = null;
                if (banner) banner.innerHTML = '<span class="text-emerald-400 font-bold"><i class="fa-solid fa-arrow-up-right-from-square"></i> WhatsApp window opened in new tab.</span>';
                window.open('<?php echo $wa_modal_data['whatsapp_url']; ?>', '_blank');
            }
        }, 1000);
    });
    <?php endif; ?>
</script>

<!-- MODAL: WhatsApp Notification & AI Confirmation Pass -->
<div id="whatsapp-card-modal" class="fixed inset-0 z-55 hidden flex items-center justify-center bg-black/85 backdrop-blur-md p-4 overflow-y-auto">
    <div class="relative max-w-2xl w-full bg-slate-900 border-2 border-emerald-500/50 rounded-2xl overflow-hidden shadow-2xl p-6 my-8">
        <button onclick="closeWhatsAppCardModal()" class="absolute top-5 right-5 text-slate-400 hover:text-white text-xl">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center text-2xl shadow-lg shadow-emerald-900/30">
                <i class="fa-brands fa-whatsapp"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-white font-poppins flex items-center gap-2">
                    Booking Approved! WhatsApp Notification
                </h3>
                <p class="text-xs text-slate-400">
                    Booking: <strong id="wa-modal-ref" class="text-sky-400 font-mono">-</strong> • Passenger: <span id="wa-modal-cust" class="text-slate-200 font-bold">-</span>
                </p>
            </div>
        </div>

        <!-- Optional Auto Redirect Banner -->
        <div id="wa-countdown-banner" class="hidden mb-4 p-3 bg-emerald-500/15 border border-emerald-500/30 rounded-xl text-xs flex items-center justify-between text-emerald-200">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-paper-plane text-emerald-400 animate-bounce"></i>
                <span>Auto-launching WhatsApp in <strong id="wa-countdown-num" class="text-white text-sm">3</strong> seconds...</span>
            </div>
            <button type="button" onclick="cancelWaCountdown()" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded text-[11px] font-bold">
                Stay on Admin Desk
            </button>
        </div>

        <!-- AI Booking Confirmation Pass Graphic Preview -->
        <div class="mb-4 bg-slate-950 p-2.5 rounded-xl border border-slate-800">
            <div class="flex items-center justify-between mb-2 px-1">
                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">
                    <i class="fa-solid fa-sparkles text-amber-400 mr-1"></i> Generated AI Booking Pass (Attached in Link)
                </span>
                <a id="wa-dl-btn" href="#" download class="text-[11px] text-sky-400 hover:text-sky-300 font-bold inline-flex items-center gap-1">
                    <i class="fa-solid fa-download"></i> Download PNG Pass
                </a>
            </div>
            <div class="rounded-lg overflow-hidden border border-slate-850 max-h-52 overflow-y-auto bg-black flex items-center justify-center">
                <img id="wa-pass-img" src="" alt="AI Booking Confirmation Pass" class="w-full object-cover rounded-lg transition-opacity duration-300">
            </div>
        </div>

        <!-- WhatsApp Message Textarea Preview -->
        <div class="mb-5">
            <div class="flex items-center justify-between mb-1.5">
                <label class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">
                    WhatsApp Message Text:
                </label>
                <button type="button" onclick="copyWhatsAppText()" class="text-slate-400 hover:text-white text-[11px] font-bold inline-flex items-center gap-1">
                    <i class="fa-regular fa-copy"></i> Copy Message
                </button>
            </div>
            <textarea id="wa-msg-text" rows="5" class="w-full p-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-300 font-mono text-[11px] focus:outline-none focus:border-emerald-500"></textarea>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-slate-800">
            <button type="button" onclick="closeWhatsAppCardModal()" class="w-full sm:w-auto px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-xl">
                Close
            </button>
            <a id="wa-send-btn" href="#" target="_blank" onclick="cancelWaCountdown()" class="w-full sm:w-auto px-6 py-3 bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-500 hover:from-emerald-500 hover:to-teal-400 text-white font-extrabold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-emerald-900/40 inline-flex items-center justify-center gap-2 transition-all">
                <i class="fa-brands fa-whatsapp text-lg"></i> Send WhatsApp Message Now
            </a>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
