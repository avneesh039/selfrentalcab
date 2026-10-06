<?php
// Admin Dynamic Pricing & Revenue Rules Engine
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pricing_helper.php';

// Enforce admin login
require_admin();

$message = '';
$error = '';

// 1. Process Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_rules') {
        $driver_allowance = floatval($_POST['driver_allowance'] ?? 300.00);
        $toll_parking = floatval($_POST['toll_parking_estimate'] ?? 150.00);
        $night_charge = floatval($_POST['night_charge'] ?? 250.00);
        $weekend_surcharge = floatval($_POST['weekend_surcharge_percent'] ?? 5.00);
        $peak_hour_surcharge = floatval($_POST['peak_hour_surcharge_percent'] ?? 10.00);
        $gst_percent = floatval($_POST['gst_percent'] ?? 5.00);
        $discount_48h = floatval($_POST['early_booking_48h_discount'] ?? 10.00);
        $discount_7d = floatval($_POST['early_booking_7d_discount'] ?? 15.00);
        $discount_15d = floatval($_POST['early_booking_15d_discount'] ?? 20.00);
        $discount_30d = floatval($_POST['early_booking_30d_discount'] ?? 25.00);

        if ($pdo) {
            try {
                // Ensure table exists
                $pdo->exec("CREATE TABLE IF NOT EXISTS `pricing_rules` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `driver_allowance` DECIMAL(10,2) DEFAULT 300.00,
                    `toll_parking_estimate` DECIMAL(10,2) DEFAULT 150.00,
                    `night_charge` DECIMAL(10,2) DEFAULT 250.00,
                    `weekend_surcharge_percent` DECIMAL(5,2) DEFAULT 5.00,
                    `peak_hour_surcharge_percent` DECIMAL(5,2) DEFAULT 10.00,
                    `gst_percent` DECIMAL(5,2) DEFAULT 5.00,
                    `early_booking_48h_discount` DECIMAL(5,2) DEFAULT 10.00,
                    `early_booking_7d_discount` DECIMAL(5,2) DEFAULT 15.00,
                    `early_booking_15d_discount` DECIMAL(5,2) DEFAULT 20.00,
                    `early_booking_30d_discount` DECIMAL(5,2) DEFAULT 25.00,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                $count = $pdo->query("SELECT COUNT(*) FROM `pricing_rules`")->fetchColumn();
                if ($count == 0) {
                    $stmt = $pdo->prepare("INSERT INTO `pricing_rules` 
                        (driver_allowance, toll_parking_estimate, night_charge, weekend_surcharge_percent, peak_hour_surcharge_percent, gst_percent, early_booking_48h_discount, early_booking_7d_discount, early_booking_15d_discount, early_booking_30d_discount) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$driver_allowance, $toll_parking, $night_charge, $weekend_surcharge, $peak_hour_surcharge, $gst_percent, $discount_48h, $discount_7d, $discount_15d, $discount_30d]);
                } else {
                    $stmt = $pdo->prepare("UPDATE `pricing_rules` SET 
                        driver_allowance = ?, toll_parking_estimate = ?, night_charge = ?, weekend_surcharge_percent = ?, peak_hour_surcharge_percent = ?, gst_percent = ?, early_booking_48h_discount = ?, early_booking_7d_discount = ?, early_booking_15d_discount = ?, early_booking_30d_discount = ? 
                        ORDER BY id ASC LIMIT 1");
                    $stmt->execute([$driver_allowance, $toll_parking, $night_charge, $weekend_surcharge, $peak_hour_surcharge, $gst_percent, $discount_48h, $discount_7d, $discount_15d, $discount_30d]);
                }
                $message = "Pricing rules & tax algorithms updated successfully.";
            } catch (Exception $e) {
                $error = "Error updating pricing rules: " . $e->getMessage();
            }
        } else {
            $message = "Pricing rules updated in session.";
        }
    } elseif ($action === 'update_vehicle_rates') {
        $cat_id = intval($_POST['cat_id'] ?? 0);
        $base_price = floatval($_POST['base_price'] ?? 0);
        $per_km = floatval($_POST['per_km_price'] ?? 0);
        $per_hour = floatval($_POST['per_hour_price'] ?? 0);

        if ($cat_id > 0 && $base_price > 0 && $per_km > 0 && $pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE `vehicle_categories` SET base_price = ?, per_km_price = ?, per_hour_price = ? WHERE id = ?");
                $stmt->execute([$base_price, $per_km, $per_hour, $cat_id]);
                $message = "Vehicle class rates updated successfully.";
            } catch (Exception $e) {
                $error = "Error updating vehicle rates: " . $e->getMessage();
            }
        }
    }
}

// 2. Fetch current rules
$rules = null;
if ($pdo) {
    try {
        $rules = $pdo->query("SELECT * FROM `pricing_rules` ORDER BY id ASC LIMIT 1")->fetch();
    } catch (Exception $e) {}
}
if (!$rules) {
    $rules = [
        'driver_allowance' => 300.00,
        'toll_parking_estimate' => 150.00,
        'night_charge' => 250.00,
        'weekend_surcharge_percent' => 5.00,
        'peak_hour_surcharge_percent' => 10.00,
        'gst_percent' => 5.00,
        'early_booking_48h_discount' => 10.00,
        'early_booking_7d_discount' => 15.00,
        'early_booking_15d_discount' => 20.00,
        'early_booking_30d_discount' => 25.00
    ];
}

// Fetch categories for preview & quick adjust
$categories = [];
if ($pdo) {
    try {
        $categories = $pdo->query("SELECT * FROM `vehicle_categories` WHERE status = 'active' ORDER BY base_price ASC")->fetchAll();
    } catch (Exception $e) {}
}
if (empty($categories)) {
    $categories = [
        ['id' => 1, 'name' => 'Sedan', 'base_price' => 500.00, 'per_km_price' => 11.00, 'per_hour_price' => 150.00],
        ['id' => 2, 'name' => 'SUV', 'base_price' => 800.00, 'per_km_price' => 15.00, 'per_hour_price' => 200.00],
        ['id' => 3, 'name' => 'MPV', 'base_price' => 1000.00, 'per_km_price' => 16.50, 'per_hour_price' => 220.00],
        ['id' => 4, 'name' => 'Luxury', 'base_price' => 2000.00, 'per_km_price' => 30.00, 'per_hour_price' => 450.00],
        ['id' => 5, 'name' => 'Tempo Traveller', 'base_price' => 1800.00, 'per_km_price' => 22.00, 'per_hour_price' => 350.00],
        ['id' => 6, 'name' => 'Bus', 'base_price' => 4000.00, 'per_km_price' => 45.00, 'per_hour_price' => 800.00]
    ];
}

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-[#20A8D9]/15 text-[#20A8D9] border border-[#20A8D9]/30 uppercase tracking-wider">
                CORE PRICING ALGORITHM
            </span>
            <h1 class="text-2xl font-black font-poppins text-white mt-1">Fare & Pricing Engine</h1>
            <p class="text-xs text-slate-400 mt-1">Configure allowance fees, toll estimates, night charges, GST rates, and advance booking discounts</p>
        </div>
        <span class="px-3 py-1 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold rounded-full">
            <i class="fa-solid fa-bolt mr-1"></i> Live Calculation Active
        </span>
    </div>

    <?php if ($message): ?>
        <div class="bg-emerald-500/10 border border-emerald-500/25 text-emerald-400 p-4 rounded-xl text-xs flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-base"></i>
            <span class="font-semibold"><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-rose-500/10 border border-rose-500/25 text-rose-400 p-4 rounded-xl text-xs flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation text-base"></i>
            <span class="font-semibold"><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- Left: Pricing Parameters Form (7 Columns) -->
        <div class="lg:col-span-7 bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl">
            <div class="flex items-center gap-2 mb-6 pb-4 border-b border-slate-800">
                <div class="w-8 h-8 rounded-lg bg-sky-500/20 text-sky-400 flex items-center justify-center font-bold text-sm">
                    <i class="fa-solid fa-sliders"></i>
                </div>
                <div>
                    <h3 class="text-sm font-extrabold text-white uppercase tracking-wider">Operational Cost Parameters</h3>
                    <p class="text-[11px] text-slate-400">These parameters apply automatically to dynamic outstation cab searches</p>
                </div>
            </div>

            <form method="POST" class="space-y-6 text-xs">
                <input type="hidden" name="action" value="update_rules">

                <!-- 1. Driver & Surcharge Rules -->
                <div>
                    <h4 class="text-xs font-bold text-sky-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                        <i class="fa-solid fa-id-card-clip"></i> Driver Allowances & Surcharges
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Driver Daily Allowance (₹)</label>
                            <input type="number" name="driver_allowance" value="<?php echo htmlspecialchars($rules['driver_allowance']); ?>" step="10" min="0" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white font-bold text-sm focus:outline-none focus:border-sky-400" required>
                            <span class="text-[10px] text-slate-500 block mt-1">Per day (doubled on round trips)</span>
                        </div>

                        <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Toll & Parking Estimate (₹)</label>
                            <input type="number" name="toll_parking_estimate" value="<?php echo htmlspecialchars($rules['toll_parking_estimate']); ?>" step="10" min="0" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white font-bold text-sm focus:outline-none focus:border-sky-400" required>
                            <span class="text-[10px] text-slate-500 block mt-1">Default estimated highway tolls</span>
                        </div>

                        <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Night Drive Surcharge (₹)</label>
                            <input type="number" name="night_charge" value="<?php echo htmlspecialchars($rules['night_charge']); ?>" step="10" min="0" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white font-bold text-sm focus:outline-none focus:border-sky-400" required>
                            <span class="text-[10px] text-slate-500 block mt-1">Between 10:00 PM and 6:00 AM</span>
                        </div>
                    </div>
                </div>

                <!-- 2. Surge Multipliers & Tax -->
                <div>
                    <h4 class="text-xs font-bold text-sky-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                        <i class="fa-solid fa-chart-line-up"></i> Surge Percentages & Tax
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Weekend Surcharge (%)</label>
                            <input type="number" name="weekend_surcharge_percent" value="<?php echo htmlspecialchars($rules['weekend_surcharge_percent']); ?>" step="0.5" min="0" max="100" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white font-bold text-sm focus:outline-none focus:border-sky-400" required>
                            <span class="text-[10px] text-slate-500 block mt-1">Saturday and Sunday pickups</span>
                        </div>

                        <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Peak Hour Surcharge (%)</label>
                            <input type="number" name="peak_hour_surcharge_percent" value="<?php echo htmlspecialchars($rules['peak_hour_surcharge_percent']); ?>" step="0.5" min="0" max="100" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white font-bold text-sm focus:outline-none focus:border-sky-400" required>
                            <span class="text-[10px] text-slate-500 block mt-1">8-10 AM & 5-7 PM rush</span>
                        </div>

                        <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">GST Tax Rate (%)</label>
                            <input type="number" name="gst_percent" value="<?php echo htmlspecialchars($rules['gst_percent']); ?>" step="0.5" min="0" max="28" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white font-bold text-sm focus:outline-none focus:border-sky-400" required>
                            <span class="text-[10px] text-slate-500 block mt-1">Standard transport GST rate</span>
                        </div>
                    </div>
                </div>

                <!-- 3. Advance Booking Discounts -->
                <div>
                    <h4 class="text-xs font-bold text-sky-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                        <i class="fa-solid fa-gift"></i> Early Booking Discount Tiers
                    </h4>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 text-center">
                            <span class="text-[10px] font-bold text-slate-400 block mb-1">48 Hrs Ahead</span>
                            <div class="flex items-center justify-center gap-1">
                                <input type="number" name="early_booking_48h_discount" value="<?php echo htmlspecialchars($rules['early_booking_48h_discount']); ?>" step="1" min="0" max="50" class="w-16 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-center text-white font-bold text-xs focus:outline-none focus:border-sky-400" required>
                                <span class="text-slate-400 font-bold">%</span>
                            </div>
                        </div>

                        <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 text-center">
                            <span class="text-[10px] font-bold text-slate-400 block mb-1">7 Days Ahead</span>
                            <div class="flex items-center justify-center gap-1">
                                <input type="number" name="early_booking_7d_discount" value="<?php echo htmlspecialchars($rules['early_booking_7d_discount']); ?>" step="1" min="0" max="50" class="w-16 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-center text-white font-bold text-xs focus:outline-none focus:border-sky-400" required>
                                <span class="text-slate-400 font-bold">%</span>
                            </div>
                        </div>

                        <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 text-center">
                            <span class="text-[10px] font-bold text-slate-400 block mb-1">15 Days Ahead</span>
                            <div class="flex items-center justify-center gap-1">
                                <input type="number" name="early_booking_15d_discount" value="<?php echo htmlspecialchars($rules['early_booking_15d_discount']); ?>" step="1" min="0" max="50" class="w-16 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-center text-white font-bold text-xs focus:outline-none focus:border-sky-400" required>
                                <span class="text-slate-400 font-bold">%</span>
                            </div>
                        </div>

                        <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 text-center">
                            <span class="text-[10px] font-bold text-slate-400 block mb-1">30 Days Ahead</span>
                            <div class="flex items-center justify-center gap-1">
                                <input type="number" name="early_booking_30d_discount" value="<?php echo htmlspecialchars($rules['early_booking_30d_discount']); ?>" step="1" min="0" max="50" class="w-16 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-center text-white font-bold text-xs focus:outline-none focus:border-sky-400" required>
                                <span class="text-slate-400 font-bold">%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full py-3.5 bg-gradient-to-r from-blue-600 to-sky-500 hover:from-blue-500 hover:to-sky-400 text-white font-black rounded-xl text-xs uppercase tracking-wider shadow-lg shadow-blue-500/25 transition-all">
                    <i class="fa-solid fa-floppy-disk mr-1.5"></i> Save & Deploy Pricing Engine Rules
                </button>
            </form>
        </div>

        <!-- Right: Vehicle Category Rates Quick Adjust & Live Fare Simulator (5 Columns) -->
        <div class="lg:col-span-5 space-y-6">
            
            <!-- Category Per-KM Rates -->
            <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl">
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-800">
                    <h3 class="text-xs font-extrabold text-white uppercase tracking-wider">Fleet Vehicle Base Rates</h3>
                    <a href="vehicles.php" class="text-[11px] text-[#20A8D9] hover:underline">Manage Fleet →</a>
                </div>

                <div class="space-y-3">
                    <?php foreach ($categories as $c): ?>
                        <form method="POST" class="bg-slate-950 p-3 rounded-xl border border-slate-800/80 flex items-center justify-between gap-2">
                            <input type="hidden" name="action" value="update_vehicle_rates">
                            <input type="hidden" name="cat_id" value="<?php echo $c['id']; ?>">
                            
                            <div>
                                <span class="font-bold text-white text-xs block"><?php echo htmlspecialchars($c['name']); ?></span>
                                <span class="text-[10px] text-slate-500">Base ₹<?php echo $c['base_price']; ?></span>
                            </div>

                            <div class="flex items-center gap-1.5">
                                <div>
                                    <span class="text-[9px] text-slate-500 block text-right">Per KM</span>
                                    <input type="number" name="per_km_price" value="<?php echo $c['per_km_price']; ?>" step="0.5" min="1" class="w-16 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-center text-white font-bold text-xs focus:outline-none focus:border-sky-400">
                                </div>
                                <input type="hidden" name="base_price" value="<?php echo $c['base_price']; ?>">
                                <input type="hidden" name="per_hour_price" value="<?php echo $c['per_hour_price']; ?>">
                                <button type="submit" class="p-2 bg-slate-800 hover:bg-sky-500 hover:text-white text-slate-400 rounded text-xs transition-colors mt-3" title="Save rate">
                                    <i class="fa-solid fa-check"></i>
                                </button>
                            </div>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Live Fare Simulator Widget -->
            <div class="bg-gradient-to-br from-slate-900 to-slate-950 border border-sky-500/30 p-6 rounded-2xl shadow-xl">
                <div class="flex items-center gap-2 mb-4 pb-3 border-b border-slate-800">
                    <span class="w-7 h-7 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xs">
                        <i class="fa-solid fa-calculator"></i>
                    </span>
                    <div>
                        <h3 class="text-xs font-bold text-white uppercase tracking-wider">Live Fare Calculation Simulator</h3>
                        <p class="text-[10px] text-slate-400">Test pricing outcomes with active rules</p>
                    </div>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">One-Way Distance (KM)</label>
                        <input type="number" id="sim-km" value="120" min="10" step="5" oninput="runFareSimulation()" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-lg text-white font-bold focus:outline-none focus:border-sky-400">
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Vehicle Class</label>
                            <select id="sim-cat" onchange="runFareSimulation()" class="w-full px-2.5 py-2 bg-slate-900 border border-slate-800 rounded-lg text-white font-bold text-xs focus:outline-none focus:border-sky-400">
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?php echo $c['per_km_price']; ?>" data-base="<?php echo $c['base_price']; ?>"><?php echo htmlspecialchars($c['name']); ?> (₹<?php echo $c['per_km_price']; ?>/km)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Trip Type</label>
                            <select id="sim-trip-type" onchange="runFareSimulation()" class="w-full px-2.5 py-2 bg-slate-900 border border-slate-800 rounded-lg text-white font-bold text-xs focus:outline-none focus:border-sky-400">
                                <option value="one_way">One Way</option>
                                <option value="round_trip">Round Trip</option>
                            </select>
                        </div>
                    </div>

                    <!-- Calculated Breakdown -->
                    <div class="mt-4 p-3.5 bg-slate-950 rounded-xl border border-slate-800 space-y-1.5 text-[11px]">
                        <div class="flex justify-between text-slate-400">
                            <span>Base Rate:</span>
                            <span id="sim-res-base" class="text-white font-medium">₹500.00</span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Distance Fare:</span>
                            <span id="sim-res-dist" class="text-white font-medium">₹1,320.00</span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Driver Allowance:</span>
                            <span id="sim-res-driver" class="text-white font-medium">₹300.00</span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Tolls & Parking:</span>
                            <span id="sim-res-toll" class="text-white font-medium">₹150.00</span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>GST (<?php echo $rules['gst_percent']; ?>%):</span>
                            <span id="sim-res-gst" class="text-white font-medium">₹113.50</span>
                        </div>
                        <div class="flex justify-between border-t border-slate-800 pt-2 text-sm font-black text-[#0099D4]">
                            <span class="text-white">Customer Quote:</span>
                            <span id="sim-res-total">₹2,383.50</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<script>
function runFareSimulation() {
    const km = parseFloat(document.getElementById('sim-km').value) || 0;
    const catSelect = document.getElementById('sim-cat');
    const perKm = parseFloat(catSelect.value) || 11;
    const baseFare = parseFloat(catSelect.options[catSelect.selectedIndex].dataset.base) || 500;
    const tripType = document.getElementById('sim-trip-type').value;

    const calcKm = (tripType === 'round_trip') ? (km * 2) : km;
    const distFare = calcKm * perKm;
    
    let driverAllowance = <?php echo floatval($rules['driver_allowance']); ?>;
    if (tripType === 'round_trip') driverAllowance *= 2;
    
    const tollParking = <?php echo floatval($rules['toll_parking_estimate']); ?>;
    const gstRate = <?php echo floatval($rules['gst_percent']); ?> / 100;

    const subtotal = baseFare + distFare + driverAllowance + tollParking;
    const gstAmount = subtotal * gstRate;
    const finalTotal = subtotal + gstAmount;

    document.getElementById('sim-res-base').innerText = '₹' + baseFare.toFixed(2);
    document.getElementById('sim-res-dist').innerText = '₹' + distFare.toFixed(2);
    document.getElementById('sim-res-driver').innerText = '₹' + driverAllowance.toFixed(2);
    document.getElementById('sim-res-toll').innerText = '₹' + tollParking.toFixed(2);
    document.getElementById('sim-res-gst').innerText = '₹' + gstAmount.toFixed(2);
    document.getElementById('sim-res-total').innerText = '₹' + Math.round(finalTotal).toLocaleString('en-IN') + '.00';
}
runFareSimulation();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
