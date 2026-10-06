<?php
// Admin Driver Roster & Document Verifier Module
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Enforce admin login
require_admin();

$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['change_status'])) {
        $driver_id = intval($_POST['driver_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');

        if ($driver_id > 0 && in_array($status, ['pending', 'approved', 'suspended'])) {
            try {
                if ($pdo) {
                    $stmt = $pdo->prepare("UPDATE `drivers` SET status = ? WHERE id = ?");
                    $stmt->execute([$status, $driver_id]);
                    $message = "Driver status transitioned to '$status' successfully.";
                }
            } catch (Exception $e) {
                $error = "Error updating driver status: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['verify_doc'])) {
        $doc_id = intval($_POST['doc_id'] ?? 0);
        $doc_status = trim($_POST['doc_status'] ?? '');

        if ($doc_id > 0 && in_array($doc_status, ['pending', 'approved', 'rejected'])) {
            try {
                if ($pdo) {
                    $stmt = $pdo->prepare("UPDATE `driver_documents` SET status = ? WHERE id = ?");
                    $stmt->execute([$doc_status, $doc_id]);
                    $message = "Document verification status updated to '$doc_status'.";
                }
            } catch (Exception $e) {
                $error = "Error updating document: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['add_driver'])) {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $city = trim($_POST['city'] ?? 'Raipur');
        $vehicle_type = trim($_POST['vehicle_type'] ?? 'Sedan Dzire');
        $vehicle_no = strtoupper(trim($_POST['vehicle_no'] ?? ''));
        $rating = floatval($_POST['rating'] ?? 5.0);
        $status = in_array($_POST['status'] ?? '', ['approved', 'pending', 'suspended']) ? $_POST['status'] : 'approved';
        $password = trim($_POST['password'] ?? 'driver123');

        if ($name && $phone && $vehicle_no) {
            try {
                if ($pdo) {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $email_final = $email ?: strtolower(preg_replace('/[^a-z0-9]/', '', $name)) . '_' . substr($phone, -4) . '@driver.avinashtaxi.com';
                    
                    $stmt = $pdo->prepare("INSERT INTO `drivers` (name, phone, email, password_hash, city, vehicle_type, vehicle_no, rating, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $phone, $email_final, $password_hash, $city, $vehicle_type, $vehicle_no, $rating, $status]);
                    $message = "Chauffeur '$name' enrolled successfully with vehicle $vehicle_no!";
                } else {
                    $message = "Driver simulated successfully.";
                }
            } catch (Exception $e) {
                $error = "Failed to add driver: " . $e->getMessage();
            }
        } else {
            $error = "Please provide Driver Name, Mobile Phone, and Vehicle Plate Number.";
        }
    } elseif (isset($_POST['delete_driver'])) {
        $driver_id = intval($_POST['driver_id'] ?? 0);
        if ($driver_id > 0) {
            try {
                if ($pdo) {
                    $pdo->prepare("DELETE FROM `driver_documents` WHERE driver_id = ?")->execute([$driver_id]);
                    $pdo->prepare("DELETE FROM `trip_bids` WHERE driver_id = ?")->execute([$driver_id]);
                    $pdo->prepare("DELETE FROM `drivers` WHERE id = ?")->execute([$driver_id]);
                    $message = "Driver partner record deleted.";
                }
            } catch (Exception $e) {
                $error = "Cannot delete driver: " . $e->getMessage();
            }
        }
    }
}

// Fetch drivers
$drivers = [];
try {
    if ($pdo) {
        $drivers = $pdo->query("SELECT * FROM `drivers` ORDER BY created_at DESC")->fetchAll();
    }
} catch (Exception $e) {}

// If drivers empty, provide Chhattisgarh regional drivers
if (empty($drivers)) {
    $drivers = [
        ['id' => 1, 'name' => 'Rajesh Verma', 'phone' => '9827101234', 'email' => 'rajesh.verma@avinashtaxi.com', 'city' => 'Raipur', 'vehicle_type' => 'Sedan (Dzire)', 'vehicle_no' => 'CG 04 HA 4120', 'rating' => 4.9, 'status' => 'approved', 'created_at' => date('Y-m-d')],
        ['id' => 2, 'name' => 'Santosh Sahu', 'phone' => '9827105678', 'email' => 'santosh.sahu@avinashtaxi.com', 'city' => 'Bilaspur', 'vehicle_type' => 'SUV (Innova Crysta)', 'vehicle_no' => 'CG 10 BD 8844', 'rating' => 5.0, 'status' => 'approved', 'created_at' => date('Y-m-d')],
        ['id' => 3, 'name' => 'Manoj Dewangan', 'phone' => '9425203344', 'email' => 'manoj.d@avinashtaxi.com', 'city' => 'Durg-Bhilai', 'vehicle_type' => 'MPV (Ertiga)', 'vehicle_no' => 'CG 07 CA 9012', 'rating' => 4.8, 'status' => 'approved', 'created_at' => date('Y-m-d')],
        ['id' => 4, 'name' => 'Vikram Baghel', 'phone' => '9407607788', 'email' => 'vikram.b@avinashtaxi.com', 'city' => 'Jagdalpur', 'vehicle_type' => 'SUV (Scorpio/Innova)', 'vehicle_no' => 'CG 17 AB 5566', 'rating' => 4.9, 'status' => 'approved', 'created_at' => date('Y-m-d')],
        ['id' => 5, 'name' => 'Anand Shukla', 'phone' => '9981109900', 'email' => 'anand.s@avinashtaxi.com', 'city' => 'Ambikapur', 'vehicle_type' => 'Tempo Traveller 17S', 'vehicle_no' => 'CG 15 AA 3311', 'rating' => 5.0, 'status' => 'approved', 'created_at' => date('Y-m-d')]
    ];
}

// Fetch pending verifications documents
$pending_docs = [];
try {
    if ($pdo) {
        $pending_docs = $pdo->query("
            SELECT dd.*, d.name AS driver_name, d.phone AS driver_phone
            FROM `driver_documents` dd
            INNER JOIN `drivers` d ON dd.driver_id = d.id
            ORDER BY dd.uploaded_at DESC
        ")->fetchAll();
    }
} catch (Exception $e) {}

// Chhattisgarh cities list
$cg_cities_list = [
    'Raipur', 'Bilaspur', 'Durg', 'Bhilai', 'Korba', 'Raigarh', 'Rajnandgaon', 'Jagdalpur', 
    'Ambikapur', 'Dhamtari', 'Mahasamund', 'Kanker', 'Kawardha', 'Janjgir', 'Champa', 'Bhatapara', 
    'Baloda Bazar', 'Mungeli', 'Gariaband', 'Balod', 'Bemetara', 'Kondagaon', 'Narayanpur', 
    'Dantewada', 'Sukma', 'Bijapur', 'Jashpur', 'Balrampur', 'Surajpur', 'Baikunthpur', 
    'Manendragarh', 'Chirmiri', 'Mohla', 'Sarangarh', 'Khairagarh', 'Gaurela-Pendra-Marwahi', 
    'Sakti', 'Ratanpur', 'Dongargarh', 'Rajim', 'Tilda Newra', 'Simga', 'Nagpur', 'Jharsuguda'
];

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<!-- Datalist for Chhattisgarh cities -->
<datalist id="cg_driver_cities">
    <?php foreach ($cg_cities_list as $city): ?>
        <option value="<?php echo htmlspecialchars($city); ?>"></option>
    <?php endforeach; ?>
</datalist>

<div>
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-xl font-bold font-poppins text-white flex items-center gap-2">
                <i class="fa-solid fa-id-card-clip text-sky-400"></i> Driver Partner & Chauffeur Roster
            </h1>
            <p class="text-xs text-slate-400">Review registered drivers across Chhattisgarh districts, audit license/RC uploads, and verify operational status</p>
        </div>
        <div>
            <button onclick="openAddDriverModal()" class="px-4 py-2 bg-gradient-to-r from-blue-600 via-sky-500 to-cyan-400 hover:from-blue-500 hover:to-sky-300 text-white font-bold rounded-xl text-xs transition-all shadow-lg shadow-sky-500/20 inline-flex items-center gap-2">
                <i class="fa-solid fa-user-plus"></i> Enroll Chauffeur
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

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Document auditing panel (5 columns) -->
        <div class="lg:col-span-5 bg-slate-900 border border-slate-800 p-5 rounded-xl h-fit space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-file-shield text-amber-400"></i> Audit Uploaded Documents
                </h3>
                <span class="text-[10px] text-slate-400"><?php echo count($pending_docs); ?> Records</span>
            </div>
            
            <div class="space-y-3">
                <?php if (!empty($pending_docs)): ?>
                    <?php foreach ($pending_docs as $doc): ?>
                        <div class="bg-slate-950 border border-slate-850 p-4 rounded-lg space-y-3 text-xs">
                            <div class="flex justify-between items-start border-b border-slate-850 pb-2">
                                <div>
                                    <strong class="text-white"><?php echo htmlspecialchars($doc['driver_name']); ?></strong>
                                    <span class="block text-[10px] text-slate-450"><?php echo htmlspecialchars($doc['driver_phone']); ?></span>
                                </div>
                                <span class="bg-slate-900 border border-slate-800 text-slate-350 text-[10px] font-bold px-2 py-0.5 rounded uppercase"><?php echo $doc['doc_type']; ?></span>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <a href="../<?php echo htmlspecialchars($doc['doc_path']); ?>" target="_blank" class="text-amber-400 hover:text-amber-300 font-semibold inline-flex items-center gap-1">
                                    <i class="fa-solid fa-file-invoice"></i> View Document
                                </a>
                                
                                <form method="POST" class="flex gap-1.5">
                                    <input type="hidden" name="verify_doc" value="1">
                                    <input type="hidden" name="doc_id" value="<?php echo $doc['id']; ?>">
                                    <select name="doc_status" onchange="this.form.submit()" class="bg-slate-900 border border-slate-800 text-[10px] rounded px-1.5 py-1 text-slate-300">
                                        <option value="pending" <?php echo ($doc['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                        <option value="approved" <?php echo ($doc['status'] === 'approved') ? 'selected' : ''; ?>>Approve</option>
                                        <option value="rejected" <?php echo ($doc['status'] === 'rejected') ? 'selected' : ''; ?>>Reject</option>
                                    </select>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-slate-950/60 p-6 rounded-lg text-center border border-slate-850">
                        <i class="fa-solid fa-folder-open text-slate-600 text-2xl mb-2 block"></i>
                        <p class="text-slate-400 text-xs">No pending document verifications.</p>
                        <p class="text-slate-500 text-[10px] mt-1">Uploaded driver licenses, commercial permits, and RC certificates will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Driver List (7 columns) -->
        <div class="lg:col-span-7 bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-lg">
            <div class="p-4 bg-slate-950 border-b border-slate-850 flex items-center justify-between">
                <span class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-users text-sky-400"></i> Active Chauffeurs (<?php echo count($drivers); ?>)
                </span>
                <span class="text-[10px] text-slate-400">All Chhattisgarh Hubs</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-950 border-b border-slate-850 text-[10px] uppercase font-bold text-slate-400">
                            <th class="p-4">Driver Name</th>
                            <th class="p-4">Vehicle Details</th>
                            <th class="p-4">City</th>
                            <th class="p-4">Rating</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-850">
                        <?php if (!empty($drivers)): ?>
                            <?php foreach ($drivers as $drv): ?>
                                <?php
                                $status_badge = 'bg-amber-500/10 text-amber-400 border border-amber-500/20';
                                if ($drv['status'] === 'approved') $status_badge = 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
                                if ($drv['status'] === 'suspended') $status_badge = 'bg-rose-500/10 text-rose-400 border border-rose-500/20';
                                ?>
                                <tr class="hover:bg-slate-900/50 transition-colors">
                                    <td class="p-4">
                                        <strong class="text-white"><?php echo htmlspecialchars($drv['name']); ?></strong><br>
                                        <span class="text-slate-400 text-[10px]"><?php echo htmlspecialchars($drv['phone']); ?></span>
                                    </td>
                                    <td class="p-4 text-slate-300">
                                        <strong><?php echo htmlspecialchars($drv['vehicle_type']); ?></strong><br>
                                        <span class="text-[10px] font-mono text-slate-400"><?php echo htmlspecialchars($drv['vehicle_no']); ?></span>
                                    </td>
                                    <td class="p-4 text-white">
                                        <span class="px-2 py-0.5 bg-slate-800 rounded text-[10px] text-slate-300">
                                            <i class="fa-solid fa-location-dot text-rose-400 mr-1"></i><?php echo htmlspecialchars($drv['city']); ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-amber-400 font-semibold">⭐ <?php echo $drv['rating']; ?></td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo $status_badge; ?>">
                                            <?php echo ucfirst($drv['status']); ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-right space-x-1.5 whitespace-nowrap">
                                        <form method="POST" class="inline-block">
                                            <input type="hidden" name="change_status" value="1">
                                            <input type="hidden" name="driver_id" value="<?php echo $drv['id']; ?>">
                                            <select name="status" onchange="this.form.submit()" class="bg-slate-950 border border-slate-800 text-[10px] rounded px-2 py-1 text-slate-300 focus:outline-none">
                                                <option value="pending" <?php echo ($drv['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                <option value="approved" <?php echo ($drv['status'] === 'approved') ? 'selected' : ''; ?>>Approve</option>
                                                <option value="suspended" <?php echo ($drv['status'] === 'suspended') ? 'selected' : ''; ?>>Suspend</option>
                                            </select>
                                        </form>
                                        <form method="POST" class="inline-block" onsubmit="return confirm('Remove driver <?php echo htmlspecialchars($drv['name']); ?>?')">
                                            <input type="hidden" name="delete_driver" value="1">
                                            <input type="hidden" name="driver_id" value="<?php echo $drv['id']; ?>">
                                            <button type="submit" class="px-2 py-1 bg-slate-950 hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 border border-slate-800 text-[10px] rounded transition-colors" title="Delete Driver">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="p-8 text-center text-slate-500 italic">No drivers registered yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Add Driver Modal -->
<div id="add-driver-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-950/85 backdrop-blur-sm p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg p-6 relative my-8 shadow-2xl">
        <button onclick="closeAddDriverModal()" class="absolute top-5 right-5 text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
        <div class="mb-5">
            <h3 class="text-base font-bold text-white font-poppins flex items-center gap-2">
                <i class="fa-solid fa-user-plus text-sky-400"></i> Enroll New Chauffeur / Driver
            </h3>
            <p class="text-xs text-slate-400">Add a verified commercial chauffeur to the Avinash Travels network</p>
        </div>

        <form method="POST" class="space-y-4 text-xs">
            <input type="hidden" name="add_driver" value="1">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Full Name *</label>
                    <input type="text" name="name" placeholder="E.g. Ramesh Patel" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400" required>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Mobile Phone *</label>
                    <input type="tel" name="phone" placeholder="9827101234" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Operating City (Chhattisgarh) *</label>
                    <input type="text" name="city" list="cg_driver_cities" placeholder="Raipur" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400" required>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Email (Optional)</label>
                    <input type="email" name="email" placeholder="driver@avinashtaxi.com" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Vehicle Class *</label>
                    <select name="vehicle_type" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400">
                        <option value="Sedan (Dzire / Etios)">Sedan (Dzire / Etios)</option>
                        <option value="SUV (Innova Crysta)">SUV (Innova Crysta)</option>
                        <option value="MPV (Ertiga / Carens)">MPV (Ertiga / Carens)</option>
                        <option value="Luxury (Mercedes / Audi)">Luxury (Mercedes / Audi)</option>
                        <option value="Tempo Traveller (12-26 Seater)">Tempo Traveller (12-26 Seater)</option>
                        <option value="Bus Coach (32-52 Seater)">Bus Coach (32-52 Seater)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Vehicle Plate Number *</label>
                    <input type="text" name="vehicle_no" placeholder="CG 04 HA 1234" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Star Rating</label>
                    <select name="rating" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400">
                        <option value="5.0">5.0 ⭐⭐⭐⭐⭐</option>
                        <option value="4.9">4.9 ⭐⭐⭐⭐</option>
                        <option value="4.8">4.8 ⭐⭐⭐⭐</option>
                        <option value="4.7">4.7 ⭐⭐⭐⭐</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Initial Status</label>
                    <select name="status" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400">
                        <option value="approved">Approved (Active)</option>
                        <option value="pending">Pending Audit</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">App Password</label>
                    <input type="text" name="password" value="driver123" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400">
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3">
                <button type="button" onclick="closeAddDriverModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold rounded-lg text-xs transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 bg-gradient-to-r from-blue-600 via-sky-500 to-cyan-400 hover:from-blue-500 hover:to-sky-300 text-white font-bold rounded-lg text-xs shadow-md transition-all">
                    Register Driver
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddDriverModal() {
        document.getElementById('add-driver-modal').classList.remove('hidden');
    }

    function closeAddDriverModal() {
        document.getElementById('add-driver-modal').classList.add('hidden');
    }
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
