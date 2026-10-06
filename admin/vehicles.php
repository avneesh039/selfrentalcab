<?php
// Admin Vehicle & Category CRUD Module
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pricing_helper.php';

// Enforce authentication
require_admin();

// AJAX Handler for booking vehicle query
if (isset($_GET['ajax_cat_id'])) {
    header('Content-Type: application/json');
    $cat_id = intval($_GET['ajax_cat_id']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM `vehicles` WHERE category_id = ? AND status = 'available'");
        $stmt->execute([$cat_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo json_encode([]);
    }
    exit;
}

$message = '';
$error = '';

// 1. Process Actions (Add / Edit / Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_category') {
        $name = trim($_POST['name'] ?? '');
        $seating = intval($_POST['seating_capacity'] ?? 4);
        $luggage = intval($_POST['luggage_capacity'] ?? 2);
        $ac = intval($_POST['ac'] ?? 1);
        $amenities = trim($_POST['amenities'] ?? '');
        $base_price = floatval($_POST['base_price'] ?? 0);
        $per_km = floatval($_POST['per_km_price'] ?? 0);
        $per_hour = floatval($_POST['per_hour_price'] ?? 0);

        // Upload image
        $image_name = 'default_car.png';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $f_name = $_FILES['image']['name'];
            $f_tmp = $_FILES['image']['tmp_name'];
            $ext = strtolower(pathinfo($f_name, PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
                $image_name = 'cat_' . md5(uniqid() . $f_name) . '.' . $ext;
                move_uploaded_file($f_tmp, __DIR__ . '/../uploads/vehicles/' . $image_name);
            }
        }

        if ($name && $base_price > 0 && $per_km > 0) {
            try {
                $stmt = $pdo->prepare("INSERT INTO `vehicle_categories` (name, seating_capacity, luggage_capacity, ac, amenities, image, base_price, per_km_price, per_hour_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                $stmt->execute([$name, $seating, $luggage, $ac, $amenities, $image_name, $base_price, $per_km, $per_hour]);
                $message = "Vehicle Category added successfully.";
            } catch (Exception $e) {
                $error = "Error adding category: " . $e->getMessage();
            }
        } else {
            $error = "Please fill in all category fields.";
        }
    } elseif ($action === 'add_vehicle') {
        $category_id = intval($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $plate = trim($_POST['plate_number'] ?? '');
        $color = trim($_POST['color'] ?? 'White');

        if ($category_id > 0 && $name && $plate) {
            try {
                $stmt = $pdo->prepare("INSERT INTO `vehicles` (category_id, name, plate_number, color, status) VALUES (?, ?, ?, ?, 'available')");
                $stmt->execute([$category_id, $name, $plate, $color]);
                $message = "Vehicle added to inventory successfully.";
            } catch (Exception $e) {
                $error = "Error adding vehicle: " . $e->getMessage();
            }
        } else {
            $error = "Please fill in all vehicle parameters.";
        }
    } elseif ($action === 'delete_category') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM `vehicle_categories` WHERE id = ?")->execute([$id]);
                $message = "Category removed successfully.";
            } catch (Exception $e) {
                $error = "Cannot delete category as vehicles are linked to it.";
            }
        }
    } elseif ($action === 'delete_vehicle') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM `vehicles` WHERE id = ?")->execute([$id]);
                $message = "Vehicle deleted from inventory.";
            } catch (Exception $e) {
                $error = "Cannot delete vehicle: " . $e->getMessage();
            }
        }
    }
}

// 2. Fetch everything
$categories = [];
$vehicles = [];
try {
    if ($pdo) {
        $categories = $pdo->query("SELECT * FROM `vehicle_categories` ORDER BY base_price ASC")->fetchAll();
        $vehicles = $pdo->query("
            SELECT v.*, vc.name AS category_name 
            FROM `vehicles` v
            INNER JOIN `vehicle_categories` vc ON v.category_id = vc.id
            ORDER BY v.id DESC
        ")->fetchAll();
    }
} catch (Exception $e) {}

if (empty($categories)) {
    $categories = [
        ['id' => 1, 'name' => 'Sedan (Dzire / Etios)', 'seating_capacity' => 4, 'luggage_capacity' => 2, 'ac' => 1, 'amenities' => 'AC, Music System, Phone Charger', 'base_price' => 500, 'per_km_price' => 11.0, 'per_hour_price' => 120, 'image' => 'sedan.png'],
        ['id' => 2, 'name' => 'SUV (Innova Crysta)', 'seating_capacity' => 7, 'luggage_capacity' => 4, 'ac' => 1, 'amenities' => 'Rear AC, Extra Legroom, High Clearance', 'base_price' => 800, 'per_km_price' => 17.0, 'per_hour_price' => 200, 'image' => 'suv.png'],
        ['id' => 3, 'name' => 'MPV (Ertiga / Carens)', 'seating_capacity' => 6, 'luggage_capacity' => 3, 'ac' => 1, 'amenities' => 'Dual AC, Family Friendly, Reclining Seats', 'base_price' => 650, 'per_km_price' => 14.0, 'per_hour_price' => 160, 'image' => 'ertiga.png'],
        ['id' => 4, 'name' => 'Luxury (Mercedes / Audi)', 'seating_capacity' => 4, 'luggage_capacity' => 3, 'ac' => 1, 'amenities' => 'Leather Interior, Chilled Water, Chauffeur Uniform', 'base_price' => 1500, 'per_km_price' => 32.0, 'per_hour_price' => 450, 'image' => 'luxury.png'],
        ['id' => 5, 'name' => 'Tempo Traveller (12-26 Seater)', 'seating_capacity' => 17, 'luggage_capacity' => 10, 'ac' => 1, 'amenities' => 'Pushback Seats, LED TV, PA System', 'base_price' => 1800, 'per_km_price' => 24.0, 'per_hour_price' => 300, 'image' => 'tempo.png'],
        ['id' => 6, 'name' => 'Bus Coach (32-52 Seater)', 'seating_capacity' => 42, 'luggage_capacity' => 35, 'ac' => 1, 'amenities' => 'Air Suspension, Underbelly Storage, Tour Guide Mic', 'base_price' => 3500, 'per_km_price' => 38.0, 'per_hour_price' => 500, 'image' => 'bus.png']
    ];
}

if (empty($vehicles)) {
    $vehicles = [
        ['id' => 1, 'category_id' => 1, 'name' => 'Maruti Suzuki Dzire VXi', 'category_name' => 'Sedan (Dzire / Etios)', 'plate_number' => 'CG 04 HA 4120', 'color' => 'White', 'status' => 'available'],
        ['id' => 2, 'category_id' => 2, 'name' => 'Toyota Innova Crysta 2.4 VX', 'category_name' => 'SUV (Innova Crysta)', 'plate_number' => 'CG 10 BD 8844', 'color' => 'Silver', 'status' => 'on_trip'],
        ['id' => 3, 'category_id' => 3, 'name' => 'Maruti Suzuki Ertiga ZXi', 'category_name' => 'MPV (Ertiga / Carens)', 'plate_number' => 'CG 07 CA 9012', 'color' => 'White', 'status' => 'available'],
        ['id' => 4, 'category_id' => 4, 'name' => 'Mercedes-Benz E-Class Luxury', 'category_name' => 'Luxury (Mercedes / Audi)', 'plate_number' => 'CG 04 MA 0001', 'color' => 'Black', 'status' => 'available'],
        ['id' => 5, 'category_id' => 5, 'name' => 'Force Urbania 17-Seater', 'category_name' => 'Tempo Traveller (12-26 Seater)', 'plate_number' => 'CG 15 AA 3311', 'color' => 'Silver', 'status' => 'available']
    ];
}

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div>
    <div class="mb-6">
        <h1 class="text-xl font-bold font-poppins text-white">Vehicle Fleet Management</h1>
        <p class="text-xs text-slate-400">Configure vehicle categories, manage physical vehicle inventories, rates, and amenities</p>
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
        
        <!-- Categories CRUD (4 Columns) -->
        <div class="lg:col-span-5 space-y-6">
            <!-- Add Category Card -->
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-xl">
                <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-4">Add Vehicle Class</h3>
                
                <form method="POST" enctype="multipart/form-data" class="space-y-3.5 text-xs">
                    <input type="hidden" name="action" value="add_category">
                    
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Class Name (E.g. Sedan)</label>
                        <input type="text" name="name" placeholder="Sedan" class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Seating Capacity</label>
                            <input type="number" name="seating_capacity" value="4" class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Luggage Capacity</label>
                            <input type="number" name="luggage_capacity" value="2" class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">AC Status</label>
                        <select name="ac" class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400">
                            <option value="1">AC Vehicle</option>
                            <option value="0">Non-AC Vehicle</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Amenities (Comma separated)</label>
                        <input type="text" name="amenities" placeholder="AC, Charging Port, Stereo" class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400">
                    </div>
                    
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Base Price (₹)</label>
                            <input type="number" name="base_price" placeholder="500" class="w-full px-2 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Per KM Rate (₹)</label>
                            <input type="number" name="per_km_price" step="0.5" placeholder="11" class="w-full px-2 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Per Hour Rate (₹)</label>
                            <input type="number" name="per_hour_price" placeholder="150" class="w-full px-2 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Vehicle Image (PNG/JPG)</label>
                        <input type="file" name="image" class="w-full text-[10px] text-slate-400 file:mr-3 file:py-1 file:px-2 file:rounded file:border-0 file:text-[10px] file:font-semibold file:bg-slate-950 file:text-slate-350 hover:file:bg-slate-800 cursor-pointer">
                    </div>

                    <button type="submit" class="w-full py-2 bg-gradient-to-r from-blue-600 to-sky-500 hover:from-blue-500 hover:to-sky-400 text-white font-bold rounded text-xs transition-colors mt-2">
                        Save Category
                    </button>
                </form>
            </div>

            <!-- Categories Roster -->
            <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider p-4 bg-slate-950 border-b border-slate-850">Configured Categories</span>
                <div class="p-4 space-y-3">
                    <?php foreach ($categories as $cat): 
                        $thumb = '../' . get_vehicle_image_path($cat['name'], $cat['image'] ?? '');
                    ?>
                        <div class="bg-slate-950 p-3 rounded-lg border border-slate-800 flex justify-between items-center text-xs">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-9 bg-slate-900 rounded p-1 flex items-center justify-center border border-slate-800">
                                    <img src="<?php echo htmlspecialchars($thumb); ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>" class="max-h-7 max-w-full object-contain" onerror="this.onerror=null; this.src='../assets/images/vehicles/sedan.png';">
                                </div>
                                <div>
                                    <strong class="text-white"><?php echo htmlspecialchars($cat['name']); ?></strong>
                                    <span class="text-slate-500 text-[10px] block"><?php echo $cat['seating_capacity']; ?> Seats | ₹<?php echo $cat['per_km_price']; ?>/KM</span>
                                </div>
                            </div>
                            <form method="POST" onsubmit="return confirm('Deleting category will fail if vehicles are linked. Proceed?')">
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                <button type="submit" class="text-rose-500 hover:text-rose-400 font-bold">Delete</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Inventory List (7 Columns) -->
        <div class="lg:col-span-7 space-y-6">
            <!-- Add Vehicle Form -->
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-xl">
                <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-4">Add Fleet Vehicle</h3>
                
                <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 text-xs items-end">
                    <input type="hidden" name="action" value="add_vehicle">
                    
                    <div class="sm:col-span-2 md:col-span-1">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Select Class</label>
                        <select name="category_id" class="w-full px-2 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Name (E.g. Dzire)</label>
                        <input type="text" name="name" placeholder="Dzire" class="w-full px-2 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Plate Number</label>
                        <input type="text" name="plate_number" placeholder="CG04-ME-1234" class="w-full px-2 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required>
                    </div>
                    <div>
                        <button type="submit" class="w-full py-2 bg-gradient-to-r from-blue-600 to-sky-500 hover:from-blue-500 hover:to-sky-400 text-white font-bold rounded text-xs transition-colors shrink-0">
                            Add Asset
                        </button>
                    </div>
                </form>
            </div>

            <!-- Inventory Table -->
            <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-lg">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-950 border-b border-slate-850 text-[10px] uppercase font-bold text-slate-400">
                                <th class="p-4">Vehicle Model</th>
                                <th class="p-4">Class</th>
                                <th class="p-4">Plate Number</th>
                                <th class="p-4">Status</th>
                                <th class="p-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-850">
                            <?php if (!empty($vehicles)): ?>
                                <?php foreach ($vehicles as $veh): ?>
                                    <tr class="hover:bg-slate-900/50 transition-colors">
                                        <td class="p-4 font-bold text-white"><?php echo htmlspecialchars($veh['name']); ?></td>
                                        <td class="p-4 text-slate-350"><?php echo htmlspecialchars($veh['category_name']); ?></td>
                                        <td class="p-4 font-mono text-white"><?php echo htmlspecialchars($veh['plate_number']); ?></td>
                                        <td class="p-4">
                                            <span class="px-2 py-0.5 rounded text-[9px] font-bold <?php echo ($veh['status'] === 'available') ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : (($veh['status'] === 'on_trip') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'bg-slate-800 text-slate-400'); ?>">
                                                <?php echo ucfirst($veh['status']); ?>
                                            </span>
                                        </td>
                                        <td class="p-4 text-right">
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this vehicle from inventory?')">
                                                <input type="hidden" name="action" value="delete_vehicle">
                                                <input type="hidden" name="id" value="<?php echo $veh['id']; ?>">
                                                <button type="submit" class="text-rose-500 hover:text-rose-400 font-bold">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-slate-500 italic">No vehicles in inventory yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
