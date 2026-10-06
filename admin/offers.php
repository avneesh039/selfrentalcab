<?php
// Admin Offers / Coupons Management Module
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Enforce admin login
require_admin();

$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_offer') {
        $title = trim($_POST['title'] ?? '');
        $code = trim($_POST['coupon_code'] ?? '');
        $pickup = trim($_POST['pickup_city'] ?? '');
        $drop = trim($_POST['drop_city'] ?? '');
        $special = floatval($_POST['special_price'] ?? 0);
        $original = floatval($_POST['original_price'] ?? 0);
        $discount = intval($_POST['discount_percent'] ?? 0);
        $validity = trim($_POST['validity'] ?? '');

        // Upload image
        $image_name = 'default_offer.jpg';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $f_name = $_FILES['image']['name'];
            $f_tmp = $_FILES['image']['tmp_name'];
            $ext = strtolower(pathinfo($f_name, PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
                $image_name = 'offer_' . md5(uniqid() . $f_name) . '.' . $ext;
                move_uploaded_file($f_tmp, __DIR__ . '/../uploads/offers/' . $image_name);
            }
        }

        if ($title && $code && $pickup && $drop && $special > 0 && $original > 0 && $discount > 0 && $validity) {
            try {
                $stmt = $pdo->prepare("INSERT INTO `offers` (title, coupon_code, pickup_city, drop_city, special_price, original_price, discount_percent, validity, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                $stmt->execute([$title, $code, $pickup, $drop, $special, $original, $discount, $validity, $image_name]);
                $message = "Deal coupon '$code' created successfully.";
            } catch (Exception $e) {
                $error = "Error adding offer: " . $e->getMessage();
            }
        } else {
            $error = "Please fill in all offer details.";
        }
    } elseif ($action === 'delete_offer') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM `offers` WHERE id = ?")->execute([$id]);
                $message = "Promo coupon code removed.";
            } catch (Exception $e) {
                $error = "Error removing offer: " . $e->getMessage();
            }
        }
    }
}

// Fetch active offers
$offers = [];
try {
    if ($pdo) {
        $offers = $pdo->query("SELECT * FROM `offers` ORDER BY validity ASC")->fetchAll();
    }
} catch (Exception $e) {}

if (empty($offers)) {
    $offers = [
        ['id' => 1, 'coupon_code' => 'AVTCHHATTISGARH', 'title' => 'All-Chhattisgarh Explorer Deal', 'pickup_city' => 'Raipur', 'drop_city' => 'Jagdalpur', 'special_price' => 4200.0, 'original_price' => 5200.0, 'discount_percent' => 20, 'validity' => date('Y-m-d', strtotime('+30 days')), 'status' => 'active'],
        ['id' => 2, 'coupon_code' => 'BILAS10', 'title' => 'Bilaspur Super Shuttle Special', 'pickup_city' => 'Bilaspur', 'drop_city' => 'Raipur', 'special_price' => 1650.0, 'original_price' => 1950.0, 'discount_percent' => 15, 'validity' => date('Y-m-d', strtotime('+45 days')), 'status' => 'active'],
        ['id' => 3, 'coupon_code' => 'AMB20', 'title' => 'Surguja Hill Station Package', 'pickup_city' => 'Raipur', 'drop_city' => 'Ambikapur', 'special_price' => 4800.0, 'original_price' => 6000.0, 'discount_percent' => 20, 'validity' => date('Y-m-d', strtotime('+60 days')), 'status' => 'active']
    ];
}

// Complete Chhattisgarh locations list
$cg_offer_cities = [
    'Raipur', 'Bilaspur', 'Durg', 'Bhilai', 'Korba', 'Raigarh', 'Rajnandgaon', 'Jagdalpur', 
    'Ambikapur', 'Dhamtari', 'Mahasamund', 'Kanker', 'Kawardha', 'Janjgir', 'Champa', 'Bhatapara', 
    'Baloda Bazar', 'Mungeli', 'Gariaband', 'Balod', 'Bemetara', 'Kondagaon', 'Narayanpur', 
    'Dantewada', 'Sukma', 'Bijapur', 'Jashpur', 'Balrampur', 'Surajpur', 'Baikunthpur', 
    'Manendragarh', 'Chirmiri', 'Mohla', 'Sarangarh', 'Khairagarh', 'Gaurela-Pendra-Marwahi', 
    'Sakti', 'Ratanpur', 'Dongargarh', 'Rajim', 'Tilda Newra', 'Simga', 'Saraipali', 'Basna', 
    'Nagpur', 'Jharsuguda'
];

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<!-- Datalist for Chhattisgarh locations -->
<datalist id="cg_offer_cities">
    <?php foreach ($cg_offer_cities as $c): ?>
        <option value="<?php echo htmlspecialchars($c); ?>"></option>
    <?php endforeach; ?>
</datalist>

<div>
    <div class="mb-6">
        <h1 class="text-xl font-bold font-poppins text-white flex items-center gap-2">
            <i class="fa-solid fa-tags text-sky-400"></i> Promotional Offers & Route Coupons
        </h1>
        <p class="text-xs text-slate-400">Create new promo codes, configure discount scales, and set route-specific flat offers for Chhattisgarh travelers</p>
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

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Add Offer Form (5 Columns) -->
        <div class="lg:col-span-4 bg-slate-900 border border-slate-800 p-5 rounded-xl h-fit shadow-lg">
            <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                <i class="fa-solid fa-ticket text-sky-400"></i> Create Promo Offer
            </h3>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-3.5 text-xs">
                <input type="hidden" name="action" value="add_offer">
                
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Offer Title</label>
                    <input type="text" name="title" placeholder="Weekend Nagpur Trip Slasher" class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required>
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Coupon Code</label>
                        <input type="text" name="coupon_code" placeholder="NGP20" class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400 uppercase" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Discount (%)</label>
                        <input type="number" name="discount_percent" placeholder="20" class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Pickup City</label>
                        <input type="text" name="pickup_city" list="cg_offer_cities" placeholder="Raipur" class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Dropoff City</label>
                        <input type="text" name="drop_city" list="cg_offer_cities" placeholder="Nagpur" class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Promo Price (₹)</label>
                        <input type="number" name="special_price" placeholder="3800" class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Original Price (₹)</label>
                        <input type="number" name="original_price" placeholder="4800" class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Validity Expiry Date</label>
                        <input type="date" name="validity" class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Banner Image (PNG/JPG)</label>
                    <input type="file" name="image" class="w-full text-[10px] text-slate-400 file:mr-3 file:py-1 file:px-2 file:rounded file:border-0 file:text-[10px] file:font-semibold file:bg-slate-950 file:text-slate-350 hover:file:bg-slate-800 cursor-pointer">
                </div>

                <button type="submit" class="w-full py-2 bg-gradient-to-r from-blue-600 to-sky-500 hover:from-blue-500 hover:to-sky-400 text-white font-bold rounded text-xs transition-colors">
                    Publish Promo Code
                </button>
            </form>
        </div>

        <!-- Offers Table (8 Columns) -->
        <div class="lg:col-span-8 bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-lg">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-950 border-b border-slate-850 text-[10px] uppercase font-bold text-slate-400">
                            <th class="p-4">Coupon</th>
                            <th class="p-4">Campaign Title</th>
                            <th class="p-4">Route Scope</th>
                            <th class="p-4">Promo Price</th>
                            <th class="p-4">Original</th>
                            <th class="p-4">Expiry Date</th>
                            <th class="p-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-850">
                        <?php if (!empty($offers)): ?>
                            <?php foreach ($offers as $off): ?>
                                <tr class="hover:bg-slate-900/50 transition-colors">
                                    <td class="p-4 font-mono font-bold text-sky-400"><?php echo htmlspecialchars($off['coupon_code']); ?></td>
                                    <td class="p-4 font-bold text-white"><?php echo htmlspecialchars($off['title']); ?></td>
                                    <td class="p-4 text-slate-350">
                                        <?php echo htmlspecialchars($off['pickup_city']); ?> 
                                        <i class="fa-solid fa-arrow-right text-[10px] mx-1 text-slate-500"></i> 
                                        <?php echo htmlspecialchars($off['drop_city']); ?>
                                    </td>
                                    <td class="p-4 text-white font-semibold">₹<?php echo number_format($off['special_price'], 2); ?> (Save <?php echo $off['discount_percent']; ?>%)</td>
                                    <td class="p-4 text-slate-500 line-through">₹<?php echo number_format($off['original_price'], 2); ?></td>
                                    <td class="p-4 text-white"><?php echo date('d M Y', strtotime($off['validity'])); ?></td>
                                    <td class="p-4 text-right">
                                        <form method="POST" onsubmit="return confirm('Remove this offer?')">
                                            <input type="hidden" name="action" value="delete_offer">
                                            <input type="hidden" name="id" value="<?php echo $off['id']; ?>">
                                            <button type="submit" class="text-rose-500 hover:text-rose-400 font-bold">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-500 italic">No promo offers running today.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
