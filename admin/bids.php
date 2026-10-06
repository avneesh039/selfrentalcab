<?php
// Admin Driver Bidding and Trip Assignment Module
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Enforce admin login
require_admin();

$message = '';
$error = '';

// Handle Bid Acceptance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_bid'])) {
    $bid_id = intval($_POST['bid_id'] ?? 0);
    
    if ($bid_id > 0) {
        try {
            $pdo->beginTransaction();
            
            // 1. Fetch bid details
            $stmt_bid = $pdo->prepare("SELECT * FROM `trip_bids` WHERE id = ?");
            $stmt_bid->execute([$bid_id]);
            $bid = $stmt_bid->fetch();
            
            if (!$bid) {
                throw new Exception("Bid record not found.");
            }
            
            $trip_id = $bid['trip_id'];
            $driver_id = $bid['driver_id'];
            $bid_amount = $bid['bid_amount'];

            // 2. Fetch Trip details to get Booking ID
            $stmt_tr = $pdo->prepare("SELECT * FROM `trips` WHERE id = ?");
            $stmt_tr->execute([$trip_id]);
            $trip = $stmt_tr->fetch();

            if (!$trip || $trip['status'] !== 'open') {
                throw new Exception("Trip is not open for bidding.");
            }

            $booking_id = $trip['booking_id'];

            // 3. Update accepted bid status
            $pdo->prepare("UPDATE `trip_bids` SET status = 'accepted' WHERE id = ?")->execute([$bid_id]);
            
            // Reject other bids for this trip
            $pdo->prepare("UPDATE `trip_bids` SET status = 'rejected' WHERE trip_id = ? AND id != ?")->execute([$trip_id, $bid_id]);
            
            // 4. Update Trip status to assigned
            $pdo->prepare("UPDATE `trips` SET status = 'assigned' WHERE id = ?")->execute([$trip_id]);

            // 5. Fetch Driver details
            $stmt_dr = $pdo->prepare("SELECT * FROM `drivers` WHERE id = ?");
            $stmt_dr->execute([$driver_id]);
            $driver = $stmt_dr->fetch();

            // Look up driver's vehicle in inventory
            $stmt_v = $pdo->prepare("SELECT id FROM `vehicles` WHERE plate_number = ? LIMIT 1");
            $stmt_v->execute([$driver['vehicle_no']]);
            $v_id = $stmt_v->fetchColumn();

            if (!$v_id) {
                // Auto create/assign a dummy vehicle if it doesn't exist in system
                // (Fall back to booking category default)
                $stmt_get_cat = $pdo->prepare("SELECT category_id FROM `bookings` WHERE id = ?");
                $stmt_get_cat->execute([$booking_id]);
                $cat_id = $stmt_get_cat->fetchColumn();
                
                $stmt_veh_fb = $pdo->prepare("SELECT id FROM `vehicles` WHERE category_id = ? AND status = 'available' LIMIT 1");
                $stmt_veh_fb->execute([$cat_id]);
                $v_id = $stmt_veh_fb->fetchColumn();

                if (!$v_id) {
                    // Create dummy vehicle matching driver license plate
                    $stmt_ins_v = $pdo->prepare("INSERT INTO `vehicles` (category_id, name, plate_number, color, status) VALUES (?, ?, ?, 'White', 'on_trip')");
                    $stmt_ins_v->execute([$cat_id, $driver['vehicle_type'], $driver['vehicle_no']]);
                    $v_id = $pdo->lastInsertId();
                }
            }

            // 6. Update booking with selected vehicle, set status to assigned, adjust final fare if bid changed it
            $stmt_bk = $pdo->prepare("UPDATE `bookings` SET vehicle_id = ?, status = 'assigned', final_total = ? WHERE id = ?");
            $stmt_bk->execute([$v_id, $bid_amount, $booking_id]);

            // Mark vehicle as on trip
            $pdo->prepare("UPDATE `vehicles` SET status = 'on_trip' WHERE id = ?")->execute([$v_id]);

            // 7. Store confirmation notification for driver
            $noti_msg = "Hello Chauffeur " . $driver['name'] . ", you have won the bid for trip from " . $trip['pickup_city'] . " to " . $trip['drop_city'] . " on " . $trip['travel_date'] . ". Expected fare: ₹" . number_format($bid_amount, 2);
            $pdo->prepare("INSERT INTO `notifications` (type, recipient, message, gateway) VALUES ('driver_assigned', ?, ?, 'sms')")->execute([$driver['phone'], $noti_msg]);

            $pdo->commit();
            $message = "Bid accepted. Trip assigned to Chauffeur '" . $driver['name'] . "' and vehicle status set to On Trip.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Assignment failed: " . $e->getMessage();
        }
    }
}

// Fetch all trips currently open for bidding, with count of bids
$open_trips = [];
try {
    if ($pdo) {
        $open_trips = $pdo->query("
            SELECT t.*, b.trip_type, vc.name AS category_name,
                   (SELECT COUNT(*) FROM `trip_bids` WHERE trip_id = t.id) AS bids_count
            FROM `trips` t
            INNER JOIN `bookings` b ON t.booking_id = b.id
            INNER JOIN `vehicle_categories` vc ON b.category_id = vc.id
            WHERE t.status = 'open'
            ORDER BY t.travel_date ASC
        ")->fetchAll();
    }
} catch (Exception $e) {}

// Fallback demo trips across Chhattisgarh
if (empty($open_trips)) {
    $open_trips = [
        ['id' => 101, 'booking_id' => 1, 'pickup_city' => 'Raipur', 'drop_city' => 'Jagdalpur', 'travel_date' => date('Y-m-d', strtotime('+1 day')), 'travel_time' => '07:00:00', 'est_distance' => 300.0, 'expected_fare' => 4500.0, 'trip_type' => 'one_way', 'category_name' => 'SUV (Innova Crysta)', 'bids_count' => 3, 'status' => 'open'],
        ['id' => 102, 'booking_id' => 2, 'pickup_city' => 'Bilaspur', 'drop_city' => 'Ambikapur', 'travel_date' => date('Y-m-d', strtotime('+2 days')), 'travel_time' => '08:30:00', 'est_distance' => 220.0, 'expected_fare' => 3200.0, 'trip_type' => 'one_way', 'category_name' => 'Sedan (Dzire)', 'bids_count' => 2, 'status' => 'open'],
        ['id' => 103, 'booking_id' => 3, 'pickup_city' => 'Durg', 'drop_city' => 'Nagpur', 'travel_date' => date('Y-m-d', strtotime('+3 days')), 'travel_time' => '06:00:00', 'est_distance' => 245.0, 'expected_fare' => 3800.0, 'trip_type' => 'round_trip', 'category_name' => 'MPV (Ertiga)', 'bids_count' => 2, 'status' => 'open']
    ];
}

// If a trip is selected, fetch bids submitted for it
$selected_trip_id = intval($_GET['trip_id'] ?? ($open_trips[0]['id'] ?? 0));
$selected_trip = null;
$trip_bids = [];

if ($selected_trip_id > 0) {
    try {
        if ($pdo) {
            $stmt_st = $pdo->prepare("SELECT * FROM `trips` WHERE id = ?");
            $stmt_st->execute([$selected_trip_id]);
            $selected_trip = $stmt_st->fetch();
            
            if ($selected_trip) {
                $stmt_b = $pdo->prepare("
                    SELECT tb.*, d.name AS driver_name, d.phone AS driver_phone, d.rating AS driver_rating, d.vehicle_no, d.vehicle_type
                    FROM `trip_bids` tb
                    INNER JOIN `drivers` d ON tb.driver_id = d.id
                    WHERE tb.trip_id = ?
                    ORDER BY tb.bid_amount ASC
                ");
                $stmt_b->execute([$selected_trip_id]);
                $trip_bids = $stmt_b->fetchAll();
            }
        }
    } catch (Exception $e) {}

    // Fallback if matching trip is from demo array
    if (!$selected_trip) {
        foreach ($open_trips as $t) {
            if ($t['id'] == $selected_trip_id) {
                $selected_trip = $t;
                break;
            }
        }
    }

    if (empty($trip_bids) && $selected_trip) {
        $exp = $selected_trip['expected_fare'] ?? 4000;
        $trip_bids = [
            ['id' => 201, 'trip_id' => $selected_trip_id, 'driver_id' => 1, 'driver_name' => 'Rajesh Verma', 'driver_phone' => '9827101234', 'driver_rating' => 4.9, 'vehicle_no' => 'CG 04 HA 4120', 'vehicle_type' => 'Sedan Dzire', 'bid_amount' => $exp - 150, 'comments' => 'Available immediately, clean AC vehicle.', 'status' => 'pending'],
            ['id' => 202, 'trip_id' => $selected_trip_id, 'driver_id' => 2, 'driver_name' => 'Santosh Sahu', 'driver_phone' => '9827105678', 'driver_rating' => 5.0, 'vehicle_no' => 'CG 10 BD 8844', 'vehicle_type' => 'Innova Crysta', 'bid_amount' => $exp, 'comments' => 'Expert route experience with GPS navigation.', 'status' => 'pending'],
            ['id' => 203, 'trip_id' => $selected_trip_id, 'driver_id' => 3, 'driver_name' => 'Manoj Dewangan', 'driver_phone' => '9425203344', 'driver_rating' => 4.8, 'vehicle_no' => 'CG 07 CA 9012', 'vehicle_type' => 'Ertiga MPV', 'bid_amount' => $exp + 100, 'comments' => 'Comfortable seating with extra boot luggage.', 'status' => 'pending']
        ];
    }
}

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div>
    <div class="mb-6">
        <h1 class="text-xl font-bold font-poppins text-white">Trip Bidding & Assignment</h1>
        <p class="text-xs text-slate-400">Review open biddable trips, compare driver bids/ratings, and assign winning chauffeurs</p>
    </div>

    <?php if ($message): ?>
        <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-3 rounded-lg text-xs mb-6">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-rose-500/10 border border-rose-500/20 text-rose-400 p-3 rounded-lg text-xs mb-6">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Open Trips Board (6 columns) -->
        <div class="lg:col-span-6 bg-slate-900 border border-slate-800 rounded-xl p-5 h-fit space-y-4">
            <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-2">Biddable Trips Board</h3>
            
            <div class="space-y-3">
                <?php if (!empty($open_trips)): ?>
                    <?php foreach ($open_trips as $t): ?>
                        <div class="bg-slate-950 border border-slate-850 p-4 rounded-lg flex justify-between items-center text-xs <?php echo ($selected_trip_id === intval($t['id'])) ? 'border-amber-500' : ''; ?>">
                            <div>
                                <span class="text-[9px] font-bold text-slate-450 uppercase block mb-1"><?php echo strtoupper(str_replace('_', ' ', $t['trip_type'])); ?> • Category: <?php echo htmlspecialchars($t['category_name']); ?></span>
                                <h4 class="text-sm font-bold text-white mb-1">
                                    <?php echo htmlspecialchars($t['pickup_city']); ?> 
                                    <i class="fa-solid fa-arrow-right text-sky-400 mx-1 text-[10px]"></i> 
                                    <?php echo htmlspecialchars($t['drop_city']); ?>
                                </h4>
                                <span class="text-[10px] text-slate-400"><i class="fa-solid fa-calendar mr-1"></i> <?php echo date('d M', strtotime($t['travel_date'])); ?> at <?php echo date('h:i A', strtotime($t['travel_time'])); ?></span>
                            </div>
                            
                            <div class="text-right">
                                <span class="block text-[10px] text-slate-400 mb-1">Fare: ₹<?php echo number_format($t['expected_fare'], 2); ?></span>
                                <a href="bids.php?trip_id=<?php echo $t['id']; ?>" class="px-2.5 py-1 bg-gradient-to-r from-blue-600 to-sky-500 hover:from-blue-500 hover:to-sky-400 text-white text-[10px] font-bold rounded">
                                    Review Bids (<?php echo $t['bids_count']; ?>)
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-slate-500 text-xs italic">No active trips open for bidding today.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bids Comparer Panel (6 columns) -->
        <div class="lg:col-span-6 bg-slate-900 border border-slate-800 rounded-xl p-5">
            <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-4">Compare Driver Bids</h3>
            
            <?php if ($selected_trip): ?>
                <div class="mb-4 bg-slate-950 p-3.5 rounded border border-slate-850 text-xs">
                    <span class="block text-[10px] text-slate-500 uppercase font-semibold mb-1">Selected Ride Coordinates</span>
                    <strong class="text-white"><?php echo htmlspecialchars($selected_trip['pickup_city']); ?> → <?php echo htmlspecialchars($selected_trip['drop_city']); ?></strong>
                    <span class="block text-slate-400 text-[10px] mt-0.5"><?php echo date('d-M-Y', strtotime($selected_trip['travel_date'])); ?> | Expected Fare: ₹<?php echo number_format($selected_trip['expected_fare'], 2); ?></span>
                </div>

                <div class="space-y-3">
                    <?php if (!empty($trip_bids)): ?>
                        <?php foreach ($trip_bids as $bid): ?>
                            <div class="bg-slate-950 border border-slate-850 p-4 rounded-lg space-y-3 text-xs flex justify-between items-center">
                                <div>
                                    <div class="flex items-center space-x-2">
                                        <strong class="text-white"><?php echo htmlspecialchars($bid['driver_name']); ?></strong>
                                        <span class="text-amber-500">⭐ <?php echo $bid['driver_rating']; ?></span>
                                    </div>
                                    <span class="block text-[10px] text-slate-400 mt-0.5">Veh: <?php echo htmlspecialchars($bid['vehicle_type']); ?> (<?php echo htmlspecialchars($bid['vehicle_no']); ?>)</span>
                                    <?php if ($bid['comments']): ?>
                                        <span class="block text-[10px] text-slate-500 italic mt-1">"<?php echo htmlspecialchars($bid['comments']); ?>"</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="text-right">
                                    <span class="block text-sm font-extrabold text-sky-400 mb-1.5">₹<?php echo number_format($bid['bid_amount'], 2); ?></span>
                                    <form method="POST" onsubmit="return confirm('Accept this bid? Other bids will be automatically rejected.')">
                                        <input type="hidden" name="accept_bid" value="1">
                                        <input type="hidden" name="bid_id" value="<?php echo $bid['id']; ?>">
                                        <button type="submit" class="px-3 py-1 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold rounded text-[10px]">
                                            Accept Bid
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-slate-500 text-xs italic">No bids submitted for this trip yet.</p>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="text-center py-10 text-slate-500 text-xs">
                    <i class="fa-solid fa-gavel text-3xl mb-2 text-slate-700 block"></i>
                    Select an active biddable trip from the left board to review and compare driver bids.
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
