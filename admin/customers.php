<?php
// Admin Customer Database Module
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Enforce admin login
require_admin();

$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['change_status'])) {
        $user_id = intval($_POST['user_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');

        if ($user_id > 0 && in_array($status, ['active', 'suspended'])) {
            try {
                if ($pdo) {
                    $stmt = $pdo->prepare("UPDATE `users` SET status = ? WHERE id = ?");
                    $stmt->execute([$status, $user_id]);
                    $message = "Customer account transitioned to '$status' successfully.";
                }
            } catch (Exception $e) {
                $error = "Error updating status: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['add_customer'])) {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? 'customer123');

        if ($name && $phone) {
            try {
                if ($pdo) {
                    $email_final = $email ?: strtolower(preg_replace('/[^a-z0-9]/', '', $name)) . '_' . substr($phone, -4) . '@guest.avinashtaxi.com';
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO `users` (name, email, phone, password_hash, status) VALUES (?, ?, ?, ?, 'active')");
                    $stmt->execute([$name, $email_final, $phone, $hash]);
                    $message = "Customer account for '$name' created successfully.";
                } else {
                    $message = "Customer added in simulated mode.";
                }
            } catch (Exception $e) {
                $error = "Failed to add customer: " . $e->getMessage();
            }
        } else {
            $error = "Customer Name and Contact Phone are required.";
        }
    } elseif (isset($_POST['delete_customer'])) {
        $user_id = intval($_POST['user_id'] ?? 0);
        if ($user_id > 0) {
            try {
                if ($pdo) {
                    $pdo->prepare("DELETE FROM `users` WHERE id = ?")->execute([$user_id]);
                    $message = "Customer profile deleted.";
                }
            } catch (Exception $e) {
                $error = "Error deleting customer: " . $e->getMessage();
            }
        }
    }
}

// Search and filter
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$customers = [];
try {
    if ($pdo) {
        $sql = "
            SELECT u.*, COUNT(b.id) AS bookings_count
            FROM `users` u
            LEFT JOIN `bookings` b ON u.id = b.user_id
            WHERE 1=1
        ";
        $params = [];

        if ($search) {
            $sql .= " AND (u.name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)";
            $like = "%$search%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($status_filter) {
            $sql .= " AND u.status = ?";
            $params[] = $status_filter;
        }

        $sql .= " GROUP BY u.id ORDER BY u.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $customers = $stmt->fetchAll();
    }
} catch (Exception $e) {}

// Fallback demo data
if (empty($customers) && !$search && !$status_filter) {
    $customers = [
        ['id' => 1, 'name' => 'Amitabh Agrawal', 'email' => 'amitabh.a@bhilaisteel.com', 'phone' => '9827112233', 'bookings_count' => 14, 'created_at' => date('Y-m-d H:i:s', strtotime('-15 days')), 'status' => 'active'],
        ['id' => 2, 'name' => 'Pooja Tiwari', 'email' => 'pooja.t@raipurnews.in', 'phone' => '9425219988', 'bookings_count' => 8, 'created_at' => date('Y-m-d H:i:s', strtotime('-30 days')), 'status' => 'active'],
        ['id' => 3, 'name' => 'Dr. K. L. Chandrakar', 'email' => 'dr.chandrakar@aimmsraipur.org', 'phone' => '9893044556', 'bookings_count' => 22, 'created_at' => date('Y-m-d H:i:s', strtotime('-60 days')), 'status' => 'active'],
        ['id' => 4, 'name' => 'Vindhya Enterprises (Corporate)', 'email' => 'logistics@vindhya-infra.com', 'phone' => '9752001122', 'bookings_count' => 45, 'created_at' => date('Y-m-d H:i:s', strtotime('-90 days')), 'status' => 'active'],
        ['id' => 5, 'name' => 'Saurabh Jaiswal', 'email' => 'saurabh.j@gmail.com', 'phone' => '9907123456', 'bookings_count' => 3, 'created_at' => date('Y-m-d H:i:s', strtotime('-5 days')), 'status' => 'active']
    ];
}

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div>
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-xl font-bold font-poppins text-white flex items-center gap-2">
                <i class="fa-solid fa-address-book text-sky-400"></i> Customer & Corporate Directory
            </h1>
            <p class="text-xs text-slate-400">Manage registered riders, monitor passenger travel volumes, and configure VIP corporate accounts</p>
        </div>
        <div>
            <button onclick="openAddCustomerModal()" class="px-4 py-2 bg-gradient-to-r from-blue-600 via-sky-500 to-cyan-400 hover:from-blue-500 hover:to-sky-300 text-white font-bold rounded-xl text-xs transition-all shadow-lg shadow-sky-500/20 inline-flex items-center gap-2">
                <i class="fa-solid fa-user-plus"></i> Add Customer / Corporate
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

    <!-- Search & Filter Bar -->
    <form method="GET" class="bg-slate-900 border border-slate-800 p-4 rounded-xl flex flex-wrap gap-4 items-center justify-between mb-6 shadow-md">
        <div class="flex flex-wrap gap-3 items-center w-full md:w-auto">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name, phone, email..." class="bg-slate-950 border border-slate-800 text-xs rounded-lg px-3 py-2 text-white focus:outline-none focus:border-sky-400 w-full sm:w-64">
            
            <select name="status" class="bg-slate-950 border border-slate-800 text-xs rounded-lg px-3 py-2 text-slate-300 focus:outline-none focus:border-sky-400">
                <option value="">All Accounts</option>
                <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Active Only</option>
                <option value="suspended" <?php echo ($status_filter === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
            </select>
            
            <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-lg text-xs transition-colors border border-slate-700">
                <i class="fa-solid fa-filter mr-1 text-slate-400"></i> Filter
            </button>
            <?php if ($search || $status_filter): ?>
                <a href="customers.php" class="text-xs text-rose-400 hover:underline">Reset</a>
            <?php endif; ?>
        </div>
        <div class="text-[11px] text-slate-400">
            Total registered: <strong class="text-white"><?php echo count($customers); ?></strong>
        </div>
    </form>

    <!-- Customers Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-lg">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-950 border-b border-slate-850 text-[10px] uppercase font-bold text-slate-400">
                        <th class="p-4">Customer ID</th>
                        <th class="p-4">Name</th>
                        <th class="p-4">Contact Info</th>
                        <th class="p-4">Bookings Count</th>
                        <th class="p-4">Registration Date</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-850">
                    <?php if (!empty($customers)): ?>
                        <?php foreach ($customers as $c): ?>
                            <?php
                            $status_class = ($c['status'] === 'active') ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20';
                            ?>
                            <tr class="hover:bg-slate-900/50 transition-colors">
                                <td class="p-4 font-mono text-slate-400">#<?php echo $c['id']; ?></td>
                                <td class="p-4">
                                    <strong class="text-white block"><?php echo htmlspecialchars($c['name']); ?></strong>
                                    <?php if (strpos(strtolower($c['name']), 'corporate') !== false || strpos(strtolower($c['name']), 'enterprises') !== false): ?>
                                        <span class="bg-sky-500/10 text-sky-400 text-[9px] font-bold px-1.5 py-0.5 rounded border border-sky-500/20 uppercase">Corporate B2B</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4">
                                    <span class="text-slate-200"><?php echo htmlspecialchars($c['email']); ?></span><br>
                                    <span class="text-[10px] text-slate-450"><?php echo htmlspecialchars($c['phone']); ?></span>
                                </td>
                                <td class="p-4">
                                    <span class="text-sky-400 font-bold text-xs bg-sky-950/40 border border-sky-800/40 px-2.5 py-1 rounded-full">
                                        <i class="fa-solid fa-car-side mr-1 text-[10px]"></i><?php echo $c['bookings_count']; ?> Trips
                                    </span>
                                </td>
                                <td class="p-4 text-slate-350"><?php echo date('d M Y', strtotime($c['created_at'])); ?></td>
                                <td class="p-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo $status_class; ?>">
                                        <?php echo ucfirst($c['status']); ?>
                                    </span>
                                </td>
                                <td class="p-4 text-right space-x-1.5 whitespace-nowrap">
                                    <form method="POST" class="inline-block">
                                        <input type="hidden" name="change_status" value="1">
                                        <input type="hidden" name="user_id" value="<?php echo $c['id']; ?>">
                                        <select name="status" onchange="this.form.submit()" class="bg-slate-950 border border-slate-800 text-[10px] rounded px-2 py-1 text-slate-300 focus:outline-none">
                                            <option value="active" <?php echo ($c['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                            <option value="suspended" <?php echo ($c['status'] === 'suspended') ? 'selected' : ''; ?>>Suspend</option>
                                        </select>
                                    </form>
                                    <form method="POST" class="inline-block" onsubmit="return confirm('Delete customer profile for <?php echo htmlspecialchars($c['name']); ?>?')">
                                        <input type="hidden" name="delete_customer" value="1">
                                        <input type="hidden" name="user_id" value="<?php echo $c['id']; ?>">
                                        <button type="submit" class="px-2 py-1 bg-slate-950 hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 border border-slate-800 text-[10px] rounded transition-colors" title="Delete Customer">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-500 italic">No customers found matching filter criteria.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Customer Modal -->
<div id="add-customer-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-950/85 backdrop-blur-sm p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 relative my-8 shadow-2xl">
        <button onclick="closeAddCustomerModal()" class="absolute top-5 right-5 text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
        <div class="mb-5">
            <h3 class="text-base font-bold text-white font-poppins flex items-center gap-2">
                <i class="fa-solid fa-user-plus text-sky-400"></i> Add Customer / Corporate Profile
            </h3>
            <p class="text-xs text-slate-400">Register direct clients or recurring corporate accounts</p>
        </div>

        <form method="POST" class="space-y-4 text-xs">
            <input type="hidden" name="add_customer" value="1">

            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Customer / Organization Name *</label>
                <input type="text" name="name" placeholder="E.g. Jindal Steel Logistics" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400" required>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Mobile Phone Number *</label>
                <input type="tel" name="phone" placeholder="9827012345" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400" required>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Email Address</label>
                <input type="email" name="email" placeholder="corporate@company.com" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400">
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Default Password</label>
                <input type="text" name="password" value="customer123" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-white focus:outline-none focus:border-sky-400">
            </div>

            <div class="flex items-center justify-end gap-3 pt-3">
                <button type="button" onclick="closeAddCustomerModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold rounded-lg text-xs transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 bg-gradient-to-r from-blue-600 via-sky-500 to-cyan-400 hover:from-blue-500 hover:to-sky-300 text-white font-bold rounded-lg text-xs shadow-md transition-all">
                    Save Account
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddCustomerModal() {
        document.getElementById('add-customer-modal').classList.remove('hidden');
    }

    function closeAddCustomerModal() {
        document.getElementById('add-customer-modal').classList.add('hidden');
    }
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
