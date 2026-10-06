<?php
// Admin Booking Management Module
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/booking_card_helper.php';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';

ensure_payment_columns();

$message = '';
$error = '';
$wa_modal_data = null;

// Handle Status Updates or Driver/Vehicle Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_booking'])) {
        $booking_db_id = intval($_POST['booking_db_id'] ?? 0);
        if ($booking_db_id > 0) {
            try {
                // Update booking
                $stmt = $pdo->prepare("UPDATE `bookings` SET status = 'confirmed', payment_status = 'paid' WHERE id = ?");
                $stmt->execute([$booking_db_id]);

                // Release trip to open dispatch
                $pdo->prepare("UPDATE `trips` SET status = 'open' WHERE booking_id = ? AND status = 'pending_approval'")->execute([$booking_db_id]);
                $pdo->prepare("UPDATE `payments` SET status = 'completed' WHERE booking_id = ?")->execute([$booking_db_id]);

                // Generate AI Booking Confirmation Card & WhatsApp Data
                $card_res = generate_booking_card($booking_db_id);
                $wa_modal_data = get_booking_whatsapp_data($booking_db_id);

                if ($wa_modal_data && !empty($wa_modal_data['passenger_phone'])) {
                    $stmt_not = $pdo->prepare("INSERT INTO `notifications` (type, recipient, message, gateway, status) VALUES (?, ?, ?, 'whatsapp', 'sent')");
                    $stmt_not->execute(['booking_approved', $wa_modal_data['passenger_phone'], $wa_modal_data['message']]);
                }

                $message = "Booking #" . htmlspecialchars($wa_modal_data['booking_id'] ?? $booking_db_id) . " approved! Official AI Booking Pass generated and WhatsApp redirect ready.";
            } catch (Exception $e) {
                $error = "Error approving booking: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['assign_driver'])) {
        $booking_db_id = intval($_POST['booking_db_id'] ?? 0);
        $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
        
        if ($booking_db_id > 0 && $vehicle_id > 0) {
            // Assign vehicle to booking and mark booking as assigned
            $stmt = $pdo->prepare("UPDATE `bookings` SET vehicle_id = ?, status = 'assigned' WHERE id = ?");
            $stmt->execute([$vehicle_id, $booking_db_id]);
            
            // Mark vehicle as on trip
            $stmt_v = $pdo->prepare("UPDATE `vehicles` SET status = 'on_trip' WHERE id = ?");
            $stmt_v->execute([$vehicle_id]);

            // Mark the bidding trip status as assigned
            $stmt_t = $pdo->prepare("UPDATE `trips` SET status = 'assigned' WHERE booking_id = ?");
            $stmt_t->execute([$booking_db_id]);

            // Regenerate AI booking pass with assigned vehicle
            generate_booking_card($booking_db_id);

            $message = "Driver and vehicle assigned successfully.";
        } else {
            $error = "Please select a valid vehicle to assign.";
        }
    } elseif (isset($_POST['update_status'])) {
        $booking_db_id = intval($_POST['booking_db_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $payment_status = trim($_POST['payment_status'] ?? '');

        if ($booking_db_id > 0 && $status) {
            $stmt = $pdo->prepare("UPDATE `bookings` SET status = ?, payment_status = ? WHERE id = ?");
            $stmt->execute([$status, $payment_status, $booking_db_id]);
            
            // If completed or cancelled, release vehicle
            if ($status === 'completed' || $status === 'cancelled') {
                $stmt_fetch = $pdo->prepare("SELECT vehicle_id FROM `bookings` WHERE id = ?");
                $stmt_fetch->execute([$booking_db_id]);
                $v_id = $stmt_fetch->fetchColumn();
                if ($v_id) {
                    $pdo->prepare("UPDATE `vehicles` SET status = 'available' WHERE id = ?")->execute([$v_id]);
                }
            }

            // Also update the linked driver trip status
            if ($status === 'cancelled') {
                $pdo->prepare("UPDATE `trips` SET status = 'cancelled' WHERE booking_id = ?")->execute([$booking_db_id]);
            } elseif ($payment_status === 'paid') {
                $pdo->prepare("UPDATE `trips` SET status = 'open' WHERE booking_id = ? AND status = 'pending_approval'")->execute([$booking_db_id]);
                $pdo->prepare("UPDATE `payments` SET status = 'completed' WHERE booking_id = ?")->execute([$booking_db_id]);
            }

            // Generate AI booking pass if confirmed or assigned
            if (in_array($status, ['confirmed', 'assigned', 'completed'])) {
                generate_booking_card($booking_db_id);
            }

            $message = "Booking status updated successfully.";
        }
    } elseif (isset($_POST['create_booking'])) {
        $name = trim($_POST['passenger_name'] ?? '');
        $phone = trim($_POST['passenger_phone'] ?? '');
        $email = trim($_POST['passenger_email'] ?? '');
        $pickup_city = trim($_POST['pickup_city'] ?? '');
        $drop_city = trim($_POST['drop_city'] ?? '');
        $pickup_date = trim($_POST['pickup_date'] ?? date('Y-m-d'));
        $pickup_time = trim($_POST['pickup_time'] ?? '10:00:00');
        $trip_type = in_array($_POST['trip_type'] ?? '', ['one_way', 'round_trip']) ? $_POST['trip_type'] : 'one_way';
        $category_id = intval($_POST['category_id'] ?? 1);
        $final_total = floatval($_POST['final_total'] ?? 0);
        $pickup_address = trim($_POST['pickup_address'] ?? $pickup_city);
        $drop_address = trim($_POST['drop_address'] ?? $drop_city);
        $payment_status = in_array($_POST['payment_status'] ?? '', ['pending', 'paid']) ? $_POST['payment_status'] : 'pending';
        $payment_method = trim($_POST['payment_method'] ?? 'cash');
        $status = in_array($_POST['status'] ?? '', ['pending', 'assigned', 'in_progress', 'completed']) ? $_POST['status'] : 'pending';

        if ($name && $phone && $pickup_city && $drop_city && $category_id > 0) {
            try {
                if ($pdo) {
                    // Check if customer exists
                    $stmt_u = $pdo->prepare("SELECT id FROM `users` WHERE phone = ? OR email = ?");
                    $stmt_u->execute([$phone, $email ?: $phone . '@guest.avinashtravels.com']);
                    $user_id = $stmt_u->fetchColumn();
                    if (!$user_id) {
                        $hash = password_hash('customer123', PASSWORD_DEFAULT);
                        $stmt_iu = $pdo->prepare("INSERT INTO `users` (name, email, phone, password_hash) VALUES (?, ?, ?, ?)");
                        $stmt_iu->execute([$name, $email ?: $phone . '@guest.avinashtravels.com', $phone, $hash]);
                        $user_id = $pdo->lastInsertId();
                    }

                    // Auto estimate fare if 0
                    if ($final_total <= 0) {
                        require_once __DIR__ . '/../includes/pricing_helper.php';
                        $calc = calculate_trip_fare([
                            'pickup_city' => $pickup_city,
                            'drop_city' => $drop_city,
                            'pickup_date' => $pickup_date,
                            'pickup_time' => $pickup_time,
                            'trip_type' => $trip_type,
                            'category_id' => $category_id
                        ]);
                        $final_total = $calc['final_total'] ?? 1850;
                    }

                    $booking_code = 'AVT-' . date('Ymd') . '-' . rand(1000, 9999);
                    $stmt_b = $pdo->prepare("INSERT INTO `bookings` (booking_id, user_id, trip_type, pickup_city, drop_city, pickup_date, pickup_time, category_id, base_fare, distance_fare, driver_allowance, toll_parking, discount_amount, gst_amount, final_total, payment_method, payment_status, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 300, 150, 0, 0, ?, ?, ?, ?)");
                    $stmt_b->execute([$booking_code, $user_id, $trip_type, $pickup_city, $drop_city, $pickup_date, $pickup_time, $category_id, $final_total * 0.4, $final_total * 0.6, $final_total, $payment_method, $payment_status, $status]);
                    $new_b_id = $pdo->lastInsertId();

                    $stmt_p = $pdo->prepare("INSERT INTO `booking_passengers` (booking_id, name, email, phone, pickup_address, drop_address) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt_p->execute([$new_b_id, $name, $email, $phone, $pickup_address, $drop_address]);

                    $message = "Direct reservation #$booking_code successfully logged!";
                } else {
                    $message = "Reservation simulated successfully.";
                }
            } catch (Exception $e) {
                $error = "Error saving reservation: " . $e->getMessage();
            }
        } else {
            $error = "Please provide Passenger Name, Contact Phone, and Route details.";
        }
    }
}

// Filter query settings
$filter_status = trim($_GET['status'] ?? '');
$search_query = trim($_GET['search'] ?? '');

$bookings = [];
try {
    if ($pdo) {
        $sql = "
            SELECT b.*, bp.name AS passenger_name, bp.phone AS passenger_phone, vc.name AS category_name, v.name AS vehicle_name
            FROM `bookings` b
            INNER JOIN `booking_passengers` bp ON b.id = bp.booking_id
            INNER JOIN `vehicle_categories` vc ON b.category_id = vc.id
            LEFT JOIN `vehicles` v ON b.vehicle_id = v.id
            WHERE 1=1
        ";
        $params = [];

        if ($filter_status) {
            $sql .= " AND b.status = ?";
            $params[] = $filter_status;
        }

        if ($search_query) {
            $sql .= " AND (b.booking_id LIKE ? OR bp.name LIKE ? OR bp.phone LIKE ?)";
            $like = "%$search_query%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= " ORDER BY b.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $bookings = $stmt->fetchAll();
    }
} catch (Exception $e) {
    // Graceful fallback
}

// Fetch categories for manual reservation
$categories = [];
try {
    if ($pdo) {
        $categories = $pdo->query("SELECT id, name FROM `vehicle_categories` ORDER BY base_price ASC")->fetchAll();
    }
} catch (Exception $e) {}

if (empty($categories)) {
    $categories = [
        ['id' => 1, 'name' => 'Sedan (Dzire / Etios)'],
        ['id' => 2, 'name' => 'SUV (Innova Crysta)'],
        ['id' => 3, 'name' => 'MPV (Ertiga / Carens)'],
        ['id' => 4, 'name' => 'Luxury (Mercedes / Audi)'],
        ['id' => 5, 'name' => 'Tempo Traveller (12-26 Seater)'],
        ['id' => 6, 'name' => 'Bus Coach (32-52 Seater)']
    ];
}

// Complete Chhattisgarh locations list
$cg_all_cities = [
    'Raipur', 'Bilaspur', 'Durg', 'Bhilai', 'Korba', 'Raigarh', 'Rajnandgaon', 'Jagdalpur', 
    'Ambikapur', 'Dhamtari', 'Mahasamund', 'Kanker', 'Kawardha', 'Janjgir', 'Champa', 'Bhatapara', 
    'Baloda Bazar', 'Mungeli', 'Gariaband', 'Balod', 'Bemetara', 'Kondagaon', 'Narayanpur', 
    'Dantewada', 'Sukma', 'Bijapur', 'Jashpur', 'Balrampur', 'Surajpur', 'Baikunthpur', 
    'Manendragarh', 'Chirmiri', 'Mohla', 'Sarangarh', 'Khairagarh', 'Gaurela-Pendra-Marwahi', 
    'Sakti', 'Ratanpur', 'Dongargarh', 'Rajim', 'Tilda Newra', 'Simga', 'Saraipali', 'Basna', 
    'Nagpur', 'Jharsuguda'
];
?>

<div>
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-xl font-bold font-poppins text-white flex items-center gap-2">
                <i class="fa-solid fa-calendar-check text-sky-400"></i> Booking Operations & Dispatch
            </h1>
            <p class="text-xs text-slate-400">Monitor live rides, review customer bookings, dispatch verified drivers, or log new walk-in / phone reservations</p>
        </div>
        <div>
            <button onclick="openCreateBookingModal()" class="px-4 py-2 bg-gradient-to-r from-blue-600 via-sky-500 to-cyan-400 hover:from-blue-500 hover:to-sky-300 text-white font-bold rounded-xl text-xs transition-all shadow-lg shadow-sky-500/20 inline-flex items-center gap-2">
                <i class="fa-solid fa-plus-circle"></i> Create Reservation
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-3 rounded-lg text-xs mb-6 flex items-center gap-2">
            <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-rose-500/10 border border-rose-500/20 text-rose-400 p-3 rounded-lg text-xs mb-6 flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Filter and Search Bar -->
    <form method="GET" class="bg-slate-900 border border-slate-800 p-4 rounded-xl flex flex-wrap gap-4 items-center justify-between mb-6 shadow-md">
        <div class="flex flex-wrap gap-3 items-center w-full md:w-auto">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search Booking ID, phone, passenger..." class="bg-slate-950 border border-slate-800 text-xs rounded-lg px-3 py-2 text-white focus:outline-none focus:border-sky-400 w-full sm:w-64">
            
            <select name="status" class="bg-slate-950 border border-slate-800 text-xs rounded-lg px-3 py-2 text-slate-300 focus:outline-none focus:border-sky-400">
                <option value="">All Statuses</option>
                <option value="pending" <?php echo ($filter_status === 'pending') ? 'selected' : ''; ?>>Pending</option>
                <option value="assigned" <?php echo ($filter_status === 'assigned') ? 'selected' : ''; ?>>Assigned</option>
                <option value="in_progress" <?php echo ($filter_status === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                <option value="completed" <?php echo ($filter_status === 'completed') ? 'selected' : ''; ?>>Completed</option>
                <option value="cancelled" <?php echo ($filter_status === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
            </select>
            
            <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-lg text-xs transition-colors border border-slate-700">
                <i class="fa-solid fa-filter mr-1 text-slate-400"></i> Filter
            </button>
            <?php if ($filter_status || $search_query): ?>
                <a href="bookings.php" class="text-xs text-rose-400 hover:underline">Reset</a>
            <?php endif; ?>
        </div>
        <div class="text-[11px] text-slate-400">
            Total records: <strong class="text-white"><?php echo count($bookings); ?></strong>
        </div>
    </form>

    <!-- Bookings Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-lg">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-950 border-b border-slate-850 text-[10px] uppercase font-bold text-slate-400">
                        <th class="p-4">Booking ID</th>
                        <th class="p-4">Passenger Details</th>
                        <th class="p-4">Route</th>
                        <th class="p-4">Date / Time</th>
                        <th class="p-4">Category</th>
                        <th class="p-4">Vehicle Assigned</th>
                        <th class="p-4">Fare</th>
                        <th class="p-4">Payment & Proof</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-850">
                    <?php if (!empty($bookings)): ?>
                        <?php foreach ($bookings as $bk): ?>
                            <?php
                            $status_color = 'bg-amber-500/10 text-amber-400 border border-amber-500/20';
                            if ($bk['status'] === 'completed') $status_color = 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
                            if ($bk['status'] === 'cancelled') $status_color = 'bg-rose-500/10 text-rose-400 border border-rose-500/20';
                            if ($bk['status'] === 'confirmed') $status_color = 'bg-sky-500/10 text-sky-400 border border-sky-500/20';
                            ?>
                            <tr class="hover:bg-slate-900/50 transition-colors">
                                <td class="p-4 font-mono font-bold text-white"><?php echo $bk['booking_id']; ?></td>
                                <td class="p-4">
                                    <strong class="text-white"><?php echo htmlspecialchars($bk['passenger_name']); ?></strong><br>
                                    <span class="text-slate-400 text-[10px]"><?php echo htmlspecialchars($bk['passenger_phone']); ?></span>
                                </td>
                                <td class="p-4">
                                    <span class="text-white"><?php echo htmlspecialchars($bk['pickup_city']); ?></span> 
                                    <i class="fa-solid fa-arrow-right text-sky-400 mx-1"></i> 
                                    <span class="text-white"><?php echo htmlspecialchars($bk['drop_city']); ?></span>
                                </td>
                                <td class="p-4">
                                    <span class="text-white"><?php echo date('d-M-Y', strtotime($bk['pickup_date'])); ?></span><br>
                                    <span class="text-slate-400 text-[10px]"><?php echo date('h:i A', strtotime($bk['pickup_time'])); ?></span>
                                </td>
                                <td class="p-4 text-slate-300"><?php echo htmlspecialchars($bk['category_name']); ?></td>
                                <td class="p-4">
                                    <?php if ($bk['vehicle_name']): ?>
                                        <span class="text-white font-semibold"><?php echo htmlspecialchars($bk['vehicle_name']); ?></span>
                                    <?php else: ?>
                                        <span class="text-slate-500 italic">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-sky-400 font-bold">₹<?php echo number_format($bk['final_total'], 2); ?></td>
                                <td class="p-4">
                                    <div class="space-y-1">
                                        <div>
                                            <?php if ($bk['payment_status'] === 'under_review'): ?>
                                                <a href="payments.php" class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-amber-500/20 text-amber-400 border border-amber-500/40 inline-flex items-center gap-1 animate-pulse hover:bg-amber-500/30">
                                                    ⏳ Under Review
                                                </a>
                                            <?php elseif ($bk['payment_status'] === 'paid'): ?>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                                                    ✓ Paid
                                                </span>
                                            <?php elseif ($bk['payment_status'] === 'rejected'): ?>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/15 text-rose-400 border border-rose-500/30">
                                                    ✗ Rejected
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-800 text-slate-400">
                                                    <?php echo ucfirst($bk['payment_status'] ?: 'Pending'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($bk['payment_screenshot'])): ?>
                                            <div>
                                                <a href="payments.php" class="inline-flex items-center gap-1 text-[10px] text-sky-400 hover:text-sky-300 font-bold bg-sky-500/10 px-1.5 py-0.5 rounded border border-sky-500/20" title="Review in Payment Approvals">
                                                    <i class="fa-solid fa-file-invoice-dollar"></i> View Proof
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($bk['utr_number'])): ?>
                                            <div class="text-[9px] font-mono text-slate-400 truncate max-w-[110px]" title="UTR: <?php echo htmlspecialchars($bk['utr_number']); ?>">
                                                UTR: <?php echo htmlspecialchars($bk['utr_number']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo $status_color; ?>">
                                        <?php echo ucfirst($bk['status']); ?>
                                    </span>
                                </td>
                                <td class="p-4 text-right flex items-center justify-end space-x-1.5 flex-wrap gap-y-1">
                                    <?php if ($bk['status'] === 'pending' || $bk['payment_status'] === 'under_review'): ?>
                                        <form method="POST" class="inline" onsubmit="return confirm('Approve Booking #<?php echo $bk['booking_id']; ?>? This will generate the AI confirmation pass and trigger WhatsApp dispatch.');">
                                            <input type="hidden" name="approve_booking" value="1">
                                            <input type="hidden" name="booking_db_id" value="<?php echo $bk['id']; ?>">
                                            <button type="submit" class="px-2 py-1 bg-emerald-600 hover:bg-emerald-500 text-white text-[10px] font-extrabold rounded shadow-sm inline-flex items-center gap-1" title="Approve Booking & Generate Pass">
                                                <i class="fa-solid fa-circle-check"></i> Approve
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <button type="button" onclick="openWhatsAppCardModal(<?php echo $bk['id']; ?>)" class="px-2 py-1 bg-emerald-700/80 hover:bg-emerald-600 text-emerald-200 hover:text-white text-[10px] font-bold rounded inline-flex items-center gap-1 shadow-sm" title="View AI Confirmation Pass & WhatsApp Message">
                                        <i class="fa-brands fa-whatsapp text-xs"></i> Pass
                                    </button>
                                    <a href="../customer/invoice.php?id=<?php echo $bk['id']; ?>" target="_blank" class="px-2 py-1 bg-slate-950 hover:bg-slate-800 border border-slate-800 text-[10px] font-bold rounded" title="View/Print Invoice">
                                        Invoice
                                    </a>
                                    <button onclick="openAssignModal(<?php echo $bk['id']; ?>, <?php echo $bk['category_id']; ?>)" class="px-2 py-1 bg-gradient-to-r from-blue-600 to-sky-500 hover:from-blue-500 hover:to-sky-400 text-white text-[10px] font-bold rounded">
                                        Assign
                                    </button>
                                    <button onclick="openStatusModal(<?php echo $bk['id']; ?>, '<?php echo $bk['status']; ?>', '<?php echo $bk['payment_status']; ?>')" class="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-[10px] font-bold rounded border border-slate-700">
                                        Status
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="p-8 text-center text-slate-500 italic">No bookings found matching query.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Datalist for Chhattisgarh Locations -->
<datalist id="cg_locations_datalist">
    <?php foreach ($cg_all_cities as $c): ?>
        <option value="<?php echo htmlspecialchars($c); ?>"></option>
    <?php endforeach; ?>
</datalist>

<!-- Create Manual / Direct Booking Modal -->
<div id="create-booking-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-950/85 backdrop-blur-sm p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-2xl p-6 relative my-8 shadow-2xl">
        <button onclick="closeCreateBookingModal()" class="absolute top-5 right-5 text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
        <div class="mb-5">
            <h3 class="text-base font-bold text-white font-poppins flex items-center gap-2">
                <i class="fa-solid fa-file-circle-plus text-sky-400"></i> Create Direct / Phone Reservation
            </h3>
            <p class="text-xs text-slate-400">Log walk-in customers or phone reservations directly with full routing and fare calculation</p>
        </div>

        <form method="POST" class="space-y-4 text-xs">
            <input type="hidden" name="create_booking" value="1">
            
            <div class="bg-slate-950/60 p-4 rounded-xl border border-slate-850 space-y-3">
                <div class="text-[10px] font-bold text-sky-400 uppercase tracking-wider">Passenger Contact Details</div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Full Name *</label>
                        <input type="text" name="passenger_name" placeholder="E.g. Ramesh Patel" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Mobile Phone *</label>
                        <input type="tel" name="passenger_phone" placeholder="9876543210" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Email (Optional)</label>
                        <input type="email" name="passenger_email" placeholder="customer@gmail.com" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400">
                    </div>
                </div>
            </div>

            <div class="bg-slate-950/60 p-4 rounded-xl border border-slate-850 space-y-3">
                <div class="text-[10px] font-bold text-amber-400 uppercase tracking-wider">Chhattisgarh Route & Schedule</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Pickup City (Chhattisgarh) *</label>
                        <input type="text" name="pickup_city" list="cg_locations_datalist" placeholder="Raipur" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Drop City (Chhattisgarh) *</label>
                        <input type="text" name="drop_city" list="cg_locations_datalist" placeholder="Bilaspur" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Pickup Date *</label>
                        <input type="date" name="pickup_date" value="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Pickup Time *</label>
                        <input type="time" name="pickup_time" value="09:00" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Trip Type</label>
                        <select name="trip_type" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400">
                            <option value="one_way">One Way Trip</option>
                            <option value="round_trip">Round Trip</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Exact Pickup Address</label>
                        <input type="text" name="pickup_address" placeholder="Hotel / Airport / Residence address" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Exact Drop Address</label>
                        <input type="text" name="drop_address" placeholder="Destination landmarks or address" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400">
                    </div>
                </div>
            </div>

            <div class="bg-slate-950/60 p-4 rounded-xl border border-slate-850 space-y-3">
                <div class="text-[10px] font-bold text-emerald-400 uppercase tracking-wider">Fleet & Commercial Terms</div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Vehicle Category *</label>
                        <select name="category_id" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400" required>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Agreed Final Fare (₹)</label>
                        <input type="number" step="0.01" name="final_total" placeholder="Leave 0 to auto-calculate" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Payment Method</label>
                        <select name="payment_method" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400">
                            <option value="cash">Cash to Chauffeur</option>
                            <option value="upi">UPI / QR Code</option>
                            <option value="card">Card / NetBanking</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Payment Status</label>
                        <select name="payment_status" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400">
                            <option value="pending">Pending (Unpaid)</option>
                            <option value="paid">Paid in Advance</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Trip Status</label>
                        <select name="status" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400">
                            <option value="pending">Pending (Needs Driver)</option>
                            <option value="assigned">Assigned</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" onclick="closeCreateBookingModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold rounded-lg text-xs transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 bg-gradient-to-r from-blue-600 via-sky-500 to-cyan-400 hover:from-blue-500 hover:to-sky-300 text-white font-bold rounded-lg text-xs shadow-md transition-all">
                    Confirm & Create Booking
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Assign Driver/Vehicle Modal -->
<div id="assign-modal" class="fixed inset-0 z-55 hidden flex items-center justify-center bg-slate-950/85 backdrop-blur-sm p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-xl w-full max-w-md p-6 relative shadow-2xl">
        <button onclick="closeAssignModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
        <h3 class="text-md font-bold text-white font-poppins mb-4">Assign Vehicle & Driver</h3>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="assign_driver" value="1">
            <input type="hidden" name="booking_db_id" id="assign-booking-id">
            
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Select Available Vehicle (matching category)</label>
                <select name="vehicle_id" id="assign-vehicle-select" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-xs text-white focus:outline-none focus:border-sky-400" required>
                    <!-- Dynamically populated via JS -->
                </select>
            </div>
            
            <button type="submit" class="w-full py-2 bg-gradient-to-r from-blue-600 to-sky-500 hover:from-blue-500 hover:to-sky-400 text-white font-bold rounded text-xs">
                Assign Asset & Mark Status
            </button>
        </form>
    </div>
</div>

<!-- Status Edit Modal -->
<div id="status-modal" class="fixed inset-0 z-55 hidden flex items-center justify-center bg-slate-950/85 backdrop-blur-sm p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-xl w-full max-w-md p-6 relative shadow-2xl">
        <button onclick="closeStatusModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
        <h3 class="text-md font-bold text-white font-poppins mb-4">Update Booking Statuses</h3>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="update_status" value="1">
            <input type="hidden" name="booking_db_id" id="status-booking-id">
            
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Trip Status</label>
                <select name="status" id="status-select" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-xs text-white focus:outline-none focus:border-sky-400" required>
                    <option value="pending">Pending</option>
                    <option value="assigned">Assigned</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Payment Status</label>
                <select name="payment_status" id="payment-status-select" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-xs text-white focus:outline-none focus:border-sky-400" required>
                    <option value="under_review">Under Review (Screenshot Uploaded)</option>
                    <option value="paid">Paid (Verified)</option>
                    <option value="pending">Pending</option>
                    <option value="rejected">Rejected</option>
                    <option value="refunded">Refunded</option>
                </select>
            </div>
            
            <button type="submit" class="w-full py-2 bg-gradient-to-r from-blue-600 to-sky-500 hover:from-blue-500 hover:to-sky-400 text-white font-bold rounded text-xs">
                Update Status Settings
            </button>
        </form>
    </div>
</div>

<script>
    function openCreateBookingModal() {
        document.getElementById('create-booking-modal').classList.remove('hidden');
    }

    function closeCreateBookingModal() {
        document.getElementById('create-booking-modal').classList.add('hidden');
    }

    function openAssignModal(bookingId, catId) {
        document.getElementById('assign-booking-id').value = bookingId;
        
        const select = document.getElementById('assign-vehicle-select');
        select.innerHTML = '<option value="">Querying inventory...</option>';
        
        fetch(`vehicles.php?ajax_cat_id=${catId}`)
        .then(res => res.json())
        .then(data => {
            select.innerHTML = '';
            if (data && data.length > 0) {
                data.forEach(v => {
                    select.innerHTML += `<option value="${v.id}">${v.name} (${v.plate_number}) - Status: ${v.status}</option>`;
                });
            } else {
                select.innerHTML = '<option value="">No vehicles available in this category</option>';
            }
        }).catch(err => {
            select.innerHTML = '<option value="">Unable to query vehicles</option>';
        });

        document.getElementById('assign-modal').classList.remove('hidden');
    }
    
    function closeAssignModal() {
        document.getElementById('assign-modal').classList.add('hidden');
    }

    function openStatusModal(bookingId, currentStatus, currentPaymentStatus) {
        document.getElementById('status-booking-id').value = bookingId;
        document.getElementById('status-select').value = currentStatus;
        document.getElementById('payment-status-select').value = currentPaymentStatus;
        
        document.getElementById('status-modal').classList.remove('hidden');
    }
    
    function closeStatusModal() {
        document.getElementById('status-modal').classList.add('hidden');
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
