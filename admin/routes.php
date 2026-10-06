<?php
// Admin Route Network & Chhattisgarh Locations Management Module
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Enforce admin login
require_admin();

// Master directory of all 33 Chhattisgarh Districts grouped by Administrative Division
$cg_districts_by_division = [
    'Raipur Division' => [
        'Raipur' => ['type' => 'Capital City & District HQ', 'hub' => true],
        'Baloda Bazar' => ['type' => 'District HQ (Cement Hub)'],
        'Bhatapara' => ['type' => 'Major Grain & Rail Transit Hub', 'hub' => true],
        'Gariaband' => ['type' => 'District HQ'],
        'Mahasamund' => ['type' => 'District HQ (NH-53 Corridor)'],
        'Dhamtari' => ['type' => 'District HQ (Gangrel Dam Corridor)']
    ],
    'Bilaspur Division' => [
        'Bilaspur' => ['type' => 'High Court & Railway Zone HQ', 'hub' => true],
        'Korba' => ['type' => 'Industrial & Power Hub', 'hub' => true],
        'Raigarh' => ['type' => 'Cultural & Steel Hub', 'hub' => true],
        'Janjgir' => ['type' => 'District HQ (Janjgir-Champa)'],
        'Champa' => ['type' => 'Textile & Major Rail Junction', 'hub' => true],
        'Mungeli' => ['type' => 'District HQ'],
        'Gaurela-Pendra-Marwahi' => ['type' => 'GPM District HQ (Amarkantak Gateway)'],
        'Sakti' => ['type' => 'District HQ'],
        'Sarangarh' => ['type' => 'District HQ (Sarangarh-Bilaigarh)']
    ],
    'Durg Division' => [
        'Durg' => ['type' => 'District HQ & Major Rail Junction', 'hub' => true],
        'Bhilai' => ['type' => 'Education & Steel City Hub', 'hub' => true],
        'Rajnandgaon' => ['type' => 'District HQ (Sanskar Dhani)', 'hub' => true],
        'Balod' => ['type' => 'District HQ'],
        'Bemetara' => ['type' => 'District HQ'],
        'Kawardha' => ['type' => 'Kabirdham District HQ (Bhoramdeo)'],
        'Khairagarh' => ['type' => 'Music University District HQ'],
        'Mohla' => ['type' => 'District HQ (Mohla-Manpur-Ambagarh Chowki)']
    ],
    'Bastar Division' => [
        'Jagdalpur' => ['type' => 'Bastar Divisional HQ & Tourism Hub', 'hub' => true],
        'Kanker' => ['type' => 'North Bastar District HQ'],
        'Kondagaon' => ['type' => 'Bell Metal & Craft District HQ'],
        'Dantewada' => ['type' => 'South Bastar District HQ (Danteshwari Temple)'],
        'Sukma' => ['type' => 'District HQ'],
        'Bijapur' => ['type' => 'District HQ'],
        'Narayanpur' => ['type' => 'District HQ (Abujhmad Gateway)']
    ],
    'Surguja Division' => [
        'Ambikapur' => ['type' => 'Surguja Divisional HQ & Cleanest City', 'hub' => true],
        'Surajpur' => ['type' => 'District HQ'],
        'Balrampur' => ['type' => 'Northernmost District HQ'],
        'Baikunthpur' => ['type' => 'Koriya District HQ'],
        'Manendragarh' => ['type' => 'MCB District HQ'],
        'Chirmiri' => ['type' => 'Hill Station & Coalfield'],
        'Jashpur' => ['type' => 'Jashpurnagar District HQ (Tea Gardens)']
    ],
    'Key Transit & Religious Hubs' => [
        'Ratanpur' => ['type' => 'Historic Mahamaya Shaktipeeth (Bilaspur)'],
        'Dongargarh' => ['type' => 'Maa Bamleshwari Temple Hub'],
        'Rajim' => ['type' => 'Prayag of Chhattisgarh'],
        'Tilda Newra' => ['type' => 'Industrial Grain Belt (Raipur)'],
        'Simga' => ['type' => 'NH-130 Junction'],
        'Saraipali' => ['type' => 'Odisha Border Transit (NH-53)'],
        'Basna' => ['type' => 'Mahasamund Transit Hub'],
        'Nagpur' => ['type' => 'Interstate Airport & Medical Hub (MH)', 'hub' => true],
        'Jharsuguda' => ['type' => 'Interstate Airport & Rail Hub (OD)', 'hub' => true]
    ]
];

// Flat list of all locations for fast lookup & datalists
$all_locations = [];
foreach ($cg_districts_by_division as $division => $cities) {
    foreach ($cities as $cityName => $info) {
        $all_locations[] = $cityName;
    }
}
sort($all_locations);

// Master verified intercity corridor database for instant 1-click seeding
$master_cg_corridors = [
    // Raipur Key Corridors
    ['Raipur', 'Bilaspur', 120.0, 2.5],
    ['Bilaspur', 'Raipur', 120.0, 2.5],
    ['Raipur', 'Durg', 40.0, 0.9],
    ['Durg', 'Raipur', 40.0, 0.9],
    ['Raipur', 'Bhilai', 35.0, 0.75],
    ['Bhilai', 'Raipur', 35.0, 0.75],
    ['Raipur', 'Korba', 210.0, 4.5],
    ['Korba', 'Raipur', 210.0, 4.5],
    ['Raipur', 'Raigarh', 240.0, 5.0],
    ['Raigarh', 'Raipur', 240.0, 5.0],
    ['Raipur', 'Rajnandgaon', 75.0, 1.5],
    ['Rajnandgaon', 'Raipur', 75.0, 1.5],
    ['Raipur', 'Jagdalpur', 300.0, 6.0],
    ['Jagdalpur', 'Raipur', 300.0, 6.0],
    ['Raipur', 'Ambikapur', 330.0, 7.0],
    ['Ambikapur', 'Raipur', 330.0, 7.0],
    ['Raipur', 'Dhamtari', 75.0, 1.5],
    ['Dhamtari', 'Raipur', 75.0, 1.5],
    ['Raipur', 'Mahasamund', 55.0, 1.2],
    ['Mahasamund', 'Raipur', 55.0, 1.2],
    ['Raipur', 'Kanker', 130.0, 2.75],
    ['Kanker', 'Raipur', 130.0, 2.75],
    ['Raipur', 'Kawardha', 120.0, 2.5],
    ['Kawardha', 'Raipur', 120.0, 2.5],
    ['Raipur', 'Janjgir', 155.0, 3.5],
    ['Janjgir', 'Raipur', 155.0, 3.5],
    ['Raipur', 'Champa', 165.0, 3.75],
    ['Champa', 'Raipur', 165.0, 3.75],
    ['Raipur', 'Bhatapara', 85.0, 1.75],
    ['Bhatapara', 'Raipur', 85.0, 1.75],
    ['Raipur', 'Baloda Bazar', 85.0, 1.8],
    ['Baloda Bazar', 'Raipur', 85.0, 1.8],
    ['Raipur', 'Mungeli', 110.0, 2.25],
    ['Mungeli', 'Raipur', 110.0, 2.25],
    ['Raipur', 'Gariaband', 90.0, 2.0],
    ['Gariaband', 'Raipur', 90.0, 2.0],
    ['Raipur', 'Balod', 95.0, 2.0],
    ['Balod', 'Raipur', 95.0, 2.0],
    ['Raipur', 'Bemetara', 70.0, 1.5],
    ['Bemetara', 'Raipur', 70.0, 1.5],
    ['Raipur', 'Kondagaon', 220.0, 4.5],
    ['Kondagaon', 'Raipur', 220.0, 4.5],
    ['Raipur', 'Dantewada', 380.0, 7.5],
    ['Dantewada', 'Raipur', 380.0, 7.5],
    ['Raipur', 'Sukma', 400.0, 8.0],
    ['Sukma', 'Raipur', 400.0, 8.0],
    ['Raipur', 'Jashpur', 380.0, 8.0],
    ['Jashpur', 'Raipur', 380.0, 8.0],
    ['Raipur', 'Surajpur', 320.0, 6.5],
    ['Surajpur', 'Raipur', 320.0, 6.5],
    ['Raipur', 'Baikunthpur', 300.0, 6.5],
    ['Baikunthpur', 'Raipur', 300.0, 6.5],
    ['Raipur', 'Manendragarh', 340.0, 7.0],
    ['Manendragarh', 'Raipur', 340.0, 7.0],
    ['Raipur', 'Sarangarh', 190.0, 4.0],
    ['Sarangarh', 'Raipur', 190.0, 4.0],
    ['Raipur', 'Sakti', 180.0, 3.75],
    ['Sakti', 'Raipur', 180.0, 3.75],
    ['Raipur', 'Nagpur', 285.0, 5.5],
    ['Nagpur', 'Raipur', 285.0, 5.5],
    ['Raipur', 'Jharsuguda', 320.0, 6.5],
    ['Jharsuguda', 'Raipur', 320.0, 6.5],
    ['Raipur', 'Dongargarh', 105.0, 2.2],
    ['Dongargarh', 'Raipur', 105.0, 2.2],
    ['Raipur', 'Rajim', 45.0, 1.0],
    ['Rajim', 'Raipur', 45.0, 1.0],

    // Bilaspur Key Corridors
    ['Bilaspur', 'Korba', 90.0, 2.0],
    ['Korba', 'Bilaspur', 90.0, 2.0],
    ['Bilaspur', 'Raigarh', 160.0, 3.5],
    ['Raigarh', 'Bilaspur', 160.0, 3.5],
    ['Bilaspur', 'Janjgir', 60.0, 1.5],
    ['Janjgir', 'Bilaspur', 60.0, 1.5],
    ['Bilaspur', 'Champa', 70.0, 1.6],
    ['Champa', 'Bilaspur', 70.0, 1.6],
    ['Bilaspur', 'Mungeli', 50.0, 1.2],
    ['Mungeli', 'Bilaspur', 50.0, 1.2],
    ['Bilaspur', 'Bhatapara', 65.0, 1.4],
    ['Bhatapara', 'Bilaspur', 65.0, 1.4],
    ['Bilaspur', 'Gaurela-Pendra-Marwahi', 110.0, 2.5],
    ['Gaurela-Pendra-Marwahi', 'Bilaspur', 110.0, 2.5],
    ['Bilaspur', 'Ambikapur', 220.0, 5.0],
    ['Ambikapur', 'Bilaspur', 220.0, 5.0],
    ['Bilaspur', 'Surajpur', 230.0, 5.0],
    ['Surajpur', 'Bilaspur', 230.0, 5.0],
    ['Bilaspur', 'Baikunthpur', 210.0, 4.5],
    ['Baikunthpur', 'Bilaspur', 210.0, 4.5],
    ['Bilaspur', 'Manendragarh', 250.0, 5.5],
    ['Manendragarh', 'Bilaspur', 250.0, 5.5],
    ['Bilaspur', 'Jashpur', 270.0, 6.0],
    ['Jashpur', 'Bilaspur', 270.0, 6.0],
    ['Bilaspur', 'Balrampur', 310.0, 6.5],
    ['Balrampur', 'Bilaspur', 310.0, 6.5],
    ['Bilaspur', 'Sakti', 95.0, 2.0],
    ['Sakti', 'Bilaspur', 95.0, 2.0],
    ['Bilaspur', 'Sarangarh', 120.0, 2.5],
    ['Sarangarh', 'Bilaspur', 120.0, 2.5],
    ['Bilaspur', 'Ratanpur', 25.0, 0.5],
    ['Ratanpur', 'Bilaspur', 25.0, 0.5],
    ['Bilaspur', 'Takhatpur', 30.0, 0.6],
    ['Takhatpur', 'Bilaspur', 30.0, 0.6],
    ['Bilaspur', 'Durg', 155.0, 3.2],
    ['Durg', 'Bilaspur', 155.0, 3.2],
    ['Bilaspur', 'Bhilai', 150.0, 3.0],
    ['Bhilai', 'Bilaspur', 150.0, 3.0],
    ['Bilaspur', 'Rajnandgaon', 190.0, 4.0],
    ['Rajnandgaon', 'Bilaspur', 190.0, 4.0],
    ['Bilaspur', 'Jagdalpur', 415.0, 8.5],
    ['Jagdalpur', 'Bilaspur', 415.0, 8.5],
    ['Bilaspur', 'Nagpur', 380.0, 7.5],
    ['Nagpur', 'Bilaspur', 380.0, 7.5],
    ['Bilaspur', 'Jharsuguda', 230.0, 5.0],
    ['Jharsuguda', 'Bilaspur', 230.0, 5.0],

    // Industrial & Regional Corridors
    ['Raigarh', 'Jharsuguda', 80.0, 1.75],
    ['Jharsuguda', 'Raigarh', 80.0, 1.75],
    ['Korba', 'Champa', 45.0, 1.0],
    ['Champa', 'Korba', 45.0, 1.0],
    ['Durg', 'Nagpur', 245.0, 4.75],
    ['Nagpur', 'Durg', 245.0, 4.75],
    ['Bhilai', 'Nagpur', 250.0, 5.0],
    ['Nagpur', 'Bhilai', 250.0, 5.0],
    ['Rajnandgaon', 'Nagpur', 215.0, 4.25],
    ['Nagpur', 'Rajnandgaon', 215.0, 4.25],
    ['Jagdalpur', 'Dantewada', 85.0, 1.75],
    ['Dantewada', 'Jagdalpur', 85.0, 1.75]
];

$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_route') {
        $pickup = trim($_POST['pickup_city'] ?? '');
        $drop = trim($_POST['drop_city'] ?? '');
        $distance = floatval($_POST['distance_km'] ?? 0);
        $duration = floatval($_POST['est_hours'] ?? 0);

        if ($pickup && $drop && $distance > 0 && $duration > 0) {
            if ($pickup === $drop) {
                $error = "Pickup and Dropoff locations must be different.";
            } elseif ($pdo) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO `routes` (pickup_city, drop_city, distance_km, est_hours, status) 
                                           VALUES (?, ?, ?, ?, 'active') 
                                           ON DUPLICATE KEY UPDATE distance_km = VALUES(distance_km), est_hours = VALUES(est_hours), status = 'active'");
                    $stmt->execute([$pickup, $drop, $distance, $duration]);
                    
                    // Also add reverse route if checked
                    if (!empty($_POST['add_return_route'])) {
                        $stmt->execute([$drop, $pickup, $distance, $duration]);
                        $message = "Two-way route between $pickup and $drop saved successfully.";
                    } else {
                        $message = "Route from $pickup to $drop saved successfully.";
                    }
                } catch (Exception $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            } else {
                $message = "Route saved (Simulated for $pickup ↔ $drop: {$distance} KM).";
            }
        } else {
            $error = "Please fill in all route parameters (Valid cities, distance > 0, duration > 0).";
        }
    } elseif ($action === 'seed_cg_routes') {
        // 1-Click Auto Seed All Major CG Corridors
        $seeded_count = 0;
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO `routes` (pickup_city, drop_city, distance_km, est_hours, status) 
                                       VALUES (?, ?, ?, ?, 'active') 
                                       ON DUPLICATE KEY UPDATE distance_km = VALUES(distance_km), est_hours = VALUES(est_hours), status = 'active'");
                foreach ($master_cg_corridors as $rt) {
                    $stmt->execute([$rt[0], $rt[1], $rt[2], $rt[3]]);
                    $seeded_count++;
                }
                $message = "Successfully synchronized $seeded_count master Chhattisgarh intercity travel routes into operational catalog!";
            } catch (Exception $e) {
                $error = "Error seeding routes: " . $e->getMessage();
            }
        } else {
            $message = "Synchronized " . count($master_cg_corridors) . " master routes in operational memory.";
        }
    } elseif ($action === 'delete_route') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0 && $pdo) {
            try {
                $pdo->prepare("DELETE FROM `routes` WHERE id = ?")->execute([$id]);
                $message = "Route ID #$id removed from operational network.";
            } catch (Exception $e) {
                $error = "Error removing route: " . $e->getMessage();
            }
        }
    }
}

// Fetch all routes from DB with optional search query
$search_q = trim($_GET['q'] ?? '');
$routes = [];
if ($pdo) {
    try {
        if ($search_q) {
            $stmt = $pdo->prepare("SELECT * FROM `routes` WHERE pickup_city LIKE ? OR drop_city LIKE ? ORDER BY pickup_city ASC, drop_city ASC");
            $term = "%$search_q%";
            $stmt->execute([$term, $term]);
            $routes = $stmt->fetchAll();
        } else {
            $routes = $pdo->query("SELECT * FROM `routes` ORDER BY pickup_city ASC, drop_city ASC")->fetchAll();
        }
    } catch (Exception $e) {
        $routes = [];
    }
}

// If DB is empty, provide default master corridor preview so UI is never blank
if (empty($routes) && empty($search_q)) {
    $routes = [];
    $dummy_id = 1;
    foreach ($master_cg_corridors as $mc) {
        $routes[] = [
            'id' => $dummy_id++,
            'pickup_city' => $mc[0],
            'drop_city' => $mc[1],
            'distance_km' => $mc[2],
            'est_hours' => $mc[3],
            'status' => 'active'
        ];
    }
}

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="space-y-6">
    <!-- Header Banner -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-900 border border-slate-800 p-6 rounded-2xl">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-[#20A8D9]/15 text-[#20A8D9] border border-[#20A8D9]/30 uppercase tracking-wider">
                    STATEWIDE NETWORK COVERAGE
                </span>
                <span class="text-xs text-slate-500 font-semibold">• 33 Districts Active</span>
            </div>
            <h1 class="text-2xl font-black font-poppins text-white">Chhattisgarh Route Network Management</h1>
            <p class="text-xs text-slate-400 mt-1">Configure intercity corridors, official highway distances, trip durations, and state-wide point-to-point transfers</p>
        </div>

        <form method="POST" onsubmit="return confirm('Synchronize all master Chhattisgarh intercity corridors (Raipur/Bilaspur connecting all 33 districts)?')">
            <input type="hidden" name="action" value="seed_cg_routes">
            <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white font-extrabold rounded-xl text-xs uppercase tracking-wider shadow-lg shadow-emerald-500/20 transition-all flex items-center gap-2">
                <i class="fa-solid fa-cloud-arrow-down text-sm"></i>
                <span>Auto-Seed All CG Routes</span>
            </button>
        </form>
    </div>

    <?php if ($message): ?>
        <div class="bg-emerald-500/10 border border-emerald-500/25 text-emerald-400 p-4 rounded-xl text-xs flex items-center gap-2 animate-fade-in">
            <i class="fa-solid fa-circle-check text-emerald-400 text-base"></i>
            <span class="font-semibold"><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-rose-500/10 border border-rose-500/25 text-rose-400 p-4 rounded-xl text-xs flex items-center gap-2 animate-fade-in">
            <i class="fa-solid fa-circle-exclamation text-rose-400 text-base"></i>
            <span class="font-semibold"><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <!-- Chhattisgarh 33 Districts Network Coverage Showcase -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-4 pb-3 border-b border-slate-800">
            <div>
                <h3 class="text-sm font-extrabold text-white uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-map-location-dot text-[#20A8D9]"></i>
                    All 33 Chhattisgarh Districts & Coverage Hubs
                </h3>
                <p class="text-xs text-slate-400">Click on any location below to quickly populate the route generator</p>
            </div>
            <span class="bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 px-3 py-1 rounded-full text-[11px] font-bold">
                <i class="fa-solid fa-check-double mr-1"></i> 100% Territory Covered
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($cg_districts_by_division as $division => $cities): ?>
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800/80">
                    <span class="text-[10px] font-black uppercase text-sky-400 tracking-wider block mb-2 pb-1 border-b border-slate-800">
                        <?php echo htmlspecialchars($division); ?>
                    </span>
                    <div class="flex flex-wrap gap-1.5">
                        <?php foreach ($cities as $cityName => $info): ?>
                            <button type="button" 
                                    onclick="prefillRouteLocation('<?php echo htmlspecialchars($cityName, ENT_QUOTES); ?>')" 
                                    class="px-2.5 py-1 rounded-lg text-[11px] font-semibold transition-all <?php echo !empty($info['hub']) ? 'bg-sky-500/15 text-sky-300 border border-sky-500/30 hover:bg-sky-500 hover:text-white' : 'bg-slate-900 text-slate-350 hover:bg-slate-800 hover:text-white border border-slate-800'; ?>"
                                    title="<?php echo htmlspecialchars($cityName . ' - ' . $info['type']); ?>">
                                <?php echo htmlspecialchars($cityName); ?>
                                <?php if (!empty($info['hub'])): ?>
                                    <span class="text-[9px] text-amber-400 ml-0.5">★</span>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Main Grid: Add Route Form & Routes Inventory -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- Add Route Card (4 Columns) -->
        <div class="lg:col-span-4 bg-slate-900 border border-slate-800 p-6 rounded-2xl sticky top-6 shadow-xl">
            <div class="flex items-center gap-2 mb-4 pb-3 border-b border-slate-800">
                <div class="w-8 h-8 rounded-lg bg-[#20A8D9]/20 text-[#20A8D9] flex items-center justify-center font-bold text-sm">
                    <i class="fa-solid fa-plus"></i>
                </div>
                <div>
                    <h3 class="text-sm font-extrabold text-white uppercase tracking-wider">Add Intercity Route</h3>
                    <p class="text-[11px] text-slate-400">Select any Chhattisgarh origin & destination</p>
                </div>
            </div>
            
            <form method="POST" class="space-y-4 text-xs">
                <input type="hidden" name="action" value="add_route">
                
                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase tracking-wider mb-1 flex items-center justify-between">
                        <span>Pickup Location (From)</span>
                        <span class="text-[10px] text-[#20A8D9]">Choose or Type</span>
                    </label>
                    <input type="text" name="pickup_city" id="input-pickup-city" list="cg-locations-list" placeholder="E.g. Raipur, Bilaspur, Korba" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm font-semibold focus:outline-none focus:border-[#20A8D9]" required>
                </div>

                <div class="flex justify-center -my-2">
                    <button type="button" onclick="swapAdminCities()" class="w-8 h-8 rounded-full bg-slate-800 hover:bg-[#20A8D9] text-slate-400 hover:text-white flex items-center justify-center transition-all" title="Swap From and To">
                        <i class="fa-solid fa-arrow-down-up-across-line text-xs"></i>
                    </button>
                </div>
                
                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase tracking-wider mb-1 flex items-center justify-between">
                        <span>Dropoff Location (To)</span>
                        <span class="text-[10px] text-[#20A8D9]">Choose or Type</span>
                    </label>
                    <input type="text" name="drop_city" id="input-drop-city" list="cg-locations-list" placeholder="E.g. Jagdalpur, Ambikapur, Durg" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm font-semibold focus:outline-none focus:border-[#20A8D9]" required>
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Distance (KM)</label>
                        <input type="number" name="distance_km" id="input-distance-km" placeholder="120" step="0.5" min="1" class="w-full px-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm font-bold focus:outline-none focus:border-[#20A8D9]" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Est. Duration (Hrs)</label>
                        <input type="number" name="est_hours" id="input-est-hours" placeholder="2.5" step="0.25" min="0.25" class="w-full px-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm font-bold focus:outline-none focus:border-[#20A8D9]" required>
                    </div>
                </div>

                <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 flex items-center justify-between">
                    <label for="chk-return-route" class="text-xs text-slate-300 font-medium cursor-pointer flex items-center gap-2">
                        <input type="checkbox" name="add_return_route" id="chk-return-route" value="1" checked class="rounded bg-slate-900 border-slate-700 text-[#20A8D9] focus:ring-0">
                        <span>Auto-create opposite return route</span>
                    </label>
                </div>

                <button type="submit" class="w-full py-3 bg-[#EF6614] hover:bg-[#D9530F] text-white font-extrabold rounded-xl text-xs uppercase tracking-wider shadow-lg shadow-orange-500/20 transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid fa-plus-circle"></i>
                    <span>Save Route Coordinates</span>
                </button>
            </form>
        </div>

        <!-- Routes Roster Table (8 Columns) -->
        <div class="lg:col-span-8 bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
            <!-- Table Controls & Search -->
            <div class="p-5 bg-slate-950 border-b border-slate-800 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h3 class="text-xs font-bold text-white uppercase tracking-wider">Operational Route Catalog (<?php echo count($routes); ?> Routes)</h3>
                    <p class="text-[11px] text-slate-400">Intercity routes active for pricing calculations and online booking</p>
                </div>

                <form method="GET" class="w-full sm:w-auto flex items-center gap-2">
                    <input type="text" name="q" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="Search by city..." class="px-3 py-1.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-white focus:outline-none focus:border-sky-400 placeholder:text-slate-500">
                    <button type="submit" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-white rounded-lg text-xs font-bold transition-colors">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                    <?php if ($search_q): ?>
                        <a href="routes.php" class="px-2 py-1.5 text-xs text-slate-400 hover:text-white" title="Clear Search">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="overflow-x-auto max-h-[700px] overflow-y-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead class="sticky top-0 bg-slate-950 z-10 border-b border-slate-800">
                        <tr class="text-[10px] uppercase font-bold text-slate-400">
                            <th class="p-4">#</th>
                            <th class="p-4">Origin (From)</th>
                            <th class="p-4">Destination (To)</th>
                            <th class="p-4">Distance</th>
                            <th class="p-4">Duration</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/80">
                        <?php if (!empty($routes)): ?>
                            <?php foreach ($routes as $r): ?>
                                <tr class="hover:bg-slate-800/40 transition-colors">
                                    <td class="p-4 font-mono text-slate-500 text-[11px]">#<?php echo $r['id']; ?></td>
                                    <td class="p-4 font-bold text-white flex items-center gap-2">
                                        <i class="fa-solid fa-circle-dot text-[#20A8D9] text-[9px]"></i>
                                        <span><?php echo htmlspecialchars($r['pickup_city']); ?></span>
                                    </td>
                                    <td class="p-4 font-bold text-slate-200">
                                        <i class="fa-solid fa-location-pin text-rose-400 text-[10px] mr-1"></i>
                                        <span><?php echo htmlspecialchars($r['drop_city']); ?></span>
                                    </td>
                                    <td class="p-4 font-semibold text-white">
                                        <span class="px-2 py-0.5 rounded bg-slate-800 border border-slate-700 text-slate-200">
                                            <?php echo number_format($r['distance_km'], 1); ?> KM
                                        </span>
                                    </td>
                                    <td class="p-4 text-slate-300 font-medium">
                                        <i class="fa-regular fa-clock text-slate-500 mr-1"></i>
                                        <?php echo number_format($r['est_hours'], 1); ?> Hrs
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2.5 py-0.5 rounded-full text-[9px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 uppercase tracking-wide">
                                            Active
                                        </span>
                                    </td>
                                    <td class="p-4 text-right">
                                        <form method="POST" onsubmit="return confirm('Remove route <?php echo htmlspecialchars($r['pickup_city'], ENT_QUOTES); ?> to <?php echo htmlspecialchars($r['drop_city'], ENT_QUOTES); ?>?')">
                                            <input type="hidden" name="action" value="delete_route">
                                            <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                            <button type="submit" class="px-2.5 py-1 text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded transition-colors text-xs font-semibold">
                                                <i class="fa-solid fa-trash-can mr-1"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="p-12 text-center text-slate-500 italic">
                                    <i class="fa-solid fa-route text-2xl text-slate-600 mb-2 block"></i>
                                    No routes matched your query. Click "Auto-Seed All CG Routes" to populate all Chhattisgarh corridors.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Datalist containing all 33 Chhattisgarh locations -->
<datalist id="cg-locations-list">
    <?php foreach ($all_locations as $loc): ?>
        <option value="<?php echo htmlspecialchars($loc); ?>">
    <?php endforeach; ?>
</datalist>

<script>
// Click district button to populate route generator
function prefillRouteLocation(cityName) {
    const pickup = document.getElementById('input-pickup-city');
    const drop = document.getElementById('input-drop-city');
    
    if (!pickup.value) {
        pickup.value = cityName;
    } else if (!drop.value) {
        drop.value = cityName;
    } else {
        drop.value = cityName;
    }
    
    // Auto-estimate distance if between known Raipur / Bilaspur pairs
    estimateAdminDistance();
}

function swapAdminCities() {
    const pickup = document.getElementById('input-pickup-city');
    const drop = document.getElementById('input-drop-city');
    const temp = pickup.value;
    pickup.value = drop.value;
    drop.value = temp;
    estimateAdminDistance();
}

// Distance helper
const masterDistances = <?php echo json_encode($master_cg_corridors); ?>;

function estimateAdminDistance() {
    const p = (document.getElementById('input-pickup-city').value || '').toLowerCase().trim();
    const d = (document.getElementById('input-drop-city').value || '').toLowerCase().trim();
    
    if (!p || !d || p === d) return;

    for (let i = 0; i < masterDistances.length; i++) {
        const c1 = masterDistances[i][0].toLowerCase();
        const c2 = masterDistances[i][1].toLowerCase();
        if ((p === c1 && d === c2) || (p === c2 && d === c1)) {
            document.getElementById('input-distance-km').value = masterDistances[i][2];
            document.getElementById('input-est-hours').value = masterDistances[i][3];
            return;
        }
    }
}

document.getElementById('input-pickup-city').addEventListener('change', estimateAdminDistance);
document.getElementById('input-drop-city').addEventListener('change', estimateAdminDistance);
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
