<?php
// Admin Services Showcase CRUD Module
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Enforce admin login
require_admin();

$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_service') {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        if ($title && $desc) {
            try {
                $stmt = $pdo->prepare("INSERT INTO `services` (title, description, image, status) VALUES (?, ?, 'service_default.png', 'active')");
                $stmt->execute([$title, $desc]);
                $message = "Service class '$title' registered successfully.";
            } catch (Exception $e) {
                $error = "Error adding service: " . $e->getMessage();
            }
        } else {
            $error = "Please enter both service title and description.";
        }
    } elseif ($action === 'delete_service') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM `services` WHERE id = ?")->execute([$id]);
                $message = "Service removed from system.";
            } catch (Exception $e) {
                $error = "Error deleting service: " . $e->getMessage();
            }
        }
    }
}

// Fetch all services
$services = [];
try {
    if ($pdo) {
        $services = $pdo->query("SELECT * FROM `services` ORDER BY id DESC")->fetchAll();
    }
} catch (Exception $e) {}

if (empty($services)) {
    $services = [
        ['id' => 1, 'title' => 'One-Way Outstation Taxi', 'description' => 'Pay only for one-way distance between any Chhattisgarh districts with zero return empty-kilometer surcharge.', 'image' => 'service_default.png', 'status' => 'active'],
        ['id' => 2, 'title' => 'Round-Trip Intercity Tours', 'description' => 'Flexible multi-day packages for family vacations, temple tours to Dongargarh/Ratanpur, and Bastar eco-tourism.', 'image' => 'service_default.png', 'status' => 'active'],
        ['id' => 3, 'title' => 'Raipur Airport Chauffeur Transfers', 'description' => 'Guaranteed on-time pickups from Swami Vivekananda Airport (RPR) with flight tracking and baggage assistance.', 'image' => 'service_default.png', 'status' => 'active'],
        ['id' => 4, 'title' => 'Wedding & VIP Event Fleet', 'description' => 'Decorated luxury Mercedes, Audi, Innova Crysta convoys and Tempo Travellers for marriage events across Chhattisgarh.', 'image' => 'service_default.png', 'status' => 'active'],
        ['id' => 5, 'title' => 'Corporate Mobility & B2B Billing', 'description' => 'Dedicated monthly packages with verified drivers, GST invoices, and priority vehicle dispatch for businesses.', 'image' => 'service_default.png', 'status' => 'active']
    ];
}

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div>
    <div class="mb-6">
        <h1 class="text-xl font-bold font-poppins text-white">Homepage Services Showcase</h1>
        <p class="text-xs text-slate-400">Add operational services cards, write short descriptions, and toggle homepage displays</p>
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
        
        <!-- Add Service Form (4 columns) -->
        <div class="lg:col-span-4 bg-slate-900 border border-slate-800 p-5 rounded-xl h-fit">
            <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-4">Add Service Card</h3>
            
            <form method="POST" class="space-y-4 text-xs">
                <input type="hidden" name="action" value="add_service">
                
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Service Title</label>
                    <input type="text" name="title" placeholder="E.g. Corporate Mobility" class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required>
                </div>
                
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Service Description</label>
                    <textarea name="description" rows="5" placeholder="Provides corporate executives with monthly billing packages, verified drivers, and GPS route logs..." class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required></textarea>
                </div>

                <button type="submit" class="w-full py-2 bg-gradient-to-r from-blue-600 to-sky-500 hover:from-blue-500 hover:to-sky-400 text-white font-bold rounded text-xs transition-colors">
                    Publish Service
                </button>
            </form>
        </div>

        <!-- Services list (8 columns) -->
        <div class="lg:col-span-8 bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-lg">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-950 border-b border-slate-850 text-[10px] uppercase font-bold text-slate-400">
                            <th class="p-4">Service ID</th>
                            <th class="p-4">Service Class Title</th>
                            <th class="p-4">Short Description details</th>
                            <th class="p-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-850">
                        <?php if (!empty($services)): ?>
                            <?php foreach ($services as $srv): ?>
                                <tr class="hover:bg-slate-900/50 transition-colors">
                                    <td class="p-4 font-mono text-slate-450">#<?php echo $srv['id']; ?></td>
                                    <td class="p-4 font-bold text-white"><?php echo htmlspecialchars($srv['title']); ?></td>
                                    <td class="p-4 text-slate-400 max-w-sm whitespace-normal break-words"><?php echo htmlspecialchars($srv['description']); ?></td>
                                    <td class="p-4 text-right">
                                        <form method="POST" onsubmit="return confirm('Remove service card?')">
                                            <input type="hidden" name="action" value="delete_service">
                                            <input type="hidden" name="id" value="<?php echo $srv['id']; ?>">
                                            <button type="submit" class="text-rose-500 hover:text-rose-400 font-bold">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="p-8 text-center text-slate-500 italic">No services listed yet.</td>
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
