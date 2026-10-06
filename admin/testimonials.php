<?php
// Admin Testimonials CRUD Module
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Enforce admin login
require_admin();

$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_testimonial') {
        $name = trim($_POST['name'] ?? '');
        $rating = intval($_POST['rating'] ?? 5);
        $review = trim($_POST['review'] ?? '');
        $route = trim($_POST['route'] ?? '');

        if ($name && $rating >= 1 && $rating <= 5 && $review && $route) {
            try {
                $stmt = $pdo->prepare("INSERT INTO `testimonials` (name, rating, review, route, photo, status) VALUES (?, ?, ?, ?, 'default_user.png', 'active')");
                $stmt->execute([$name, $rating, $review, $route]);
                $message = "Testimonial from '$name' added successfully.";
            } catch (Exception $e) {
                $error = "Error adding testimonial: " . $e->getMessage();
            }
        } else {
            $error = "Please fill in all testimonial fields.";
        }
    } elseif ($action === 'delete_testimonial') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM `testimonials` WHERE id = ?")->execute([$id]);
                $message = "Testimonial removed successfully.";
            } catch (Exception $e) {
                $error = "Error deleting testimonial: " . $e->getMessage();
            }
        }
    }
}

// Fetch testimonials
$testimonials = [];
try {
    if ($pdo) {
        $testimonials = $pdo->query("SELECT * FROM `testimonials` ORDER BY id DESC")->fetchAll();
    }
} catch (Exception $e) {}

if (empty($testimonials)) {
    $testimonials = [
        ['id' => 1, 'name' => 'Ashish Verma', 'rating' => 5, 'route' => 'Raipur to Jagdalpur', 'review' => 'Best outstation taxi service in Chhattisgarh! The Innova Crysta was spotless, air conditioning was fantastic, and the driver was extremely polite.', 'photo' => 'default_user.png', 'status' => 'active'],
        ['id' => 2, 'name' => 'Dr. Priya Sharma', 'rating' => 5, 'route' => 'Bilaspur to Raipur Airport', 'review' => 'Prompt 4:30 AM pickup from Bilaspur to catch my early morning flight at Swami Vivekananda Airport. Smooth driving and zero stress.', 'photo' => 'default_user.png', 'status' => 'active'],
        ['id' => 3, 'name' => 'Sunil Agrawal', 'rating' => 5, 'route' => 'Durg to Nagpur', 'review' => 'Booked for elderly parents to travel to Nagpur hospital. The chauffeur drove very patiently and took care of luggage. Highly recommended!', 'photo' => 'default_user.png', 'status' => 'active']
    ];
}

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div>
    <div class="mb-6">
        <h1 class="text-xl font-bold font-poppins text-white">Testimonial Reviews Manager</h1>
        <p class="text-xs text-slate-400">Review feedback submissions, add new reviews, and manage ratings shown on the homepage</p>
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
        
        <!-- Add Testimonial Form (4 columns) -->
        <div class="lg:col-span-4 bg-slate-900 border border-slate-800 p-5 rounded-xl h-fit">
            <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-4">Add Customer Review</h3>
            
            <form method="POST" class="space-y-4 text-xs">
                <input type="hidden" name="action" value="add_testimonial">
                
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Customer Name</label>
                    <input type="text" name="name" placeholder="E.g. Ashish Verma" class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required>
                </div>
                
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Rating Stars</label>
                    <select name="rating" class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400">
                        <option value="5">⭐⭐⭐⭐⭐ (5 Stars)</option>
                        <option value="4">⭐⭐⭐⭐ (4 Stars)</option>
                        <option value="3">⭐⭐⭐ (3 Stars)</option>
                        <option value="2">⭐⭐ (2 Stars)</option>
                        <option value="1">⭐ (1 Star)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Travel Route</label>
                    <input type="text" name="route" placeholder="E.g. Raipur to Nagpur" class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Feedback Message</label>
                    <textarea name="review" rows="4" placeholder="Chauffeur was verified, vehicles were neat..." class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required></textarea>
                </div>

                <button type="submit" class="w-full py-2 bg-gradient-to-r from-blue-600 to-sky-500 hover:from-blue-500 hover:to-sky-400 text-white font-bold rounded text-xs transition-colors">
                    Publish Testimonial
                </button>
            </form>
        </div>

        <!-- Testimonials List (8 columns) -->
        <div class="lg:col-span-8 bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-lg">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-950 border-b border-slate-850 text-[10px] uppercase font-bold text-slate-400">
                            <th class="p-4">Customer</th>
                            <th class="p-4">Route Scope</th>
                            <th class="p-4">Review Text</th>
                            <th class="p-4">Rating</th>
                            <th class="p-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-850">
                        <?php if (!empty($testimonials)): ?>
                            <?php foreach ($testimonials as $t): ?>
                                <tr class="hover:bg-slate-900/50 transition-colors">
                                    <td class="p-4 font-bold text-white"><?php echo htmlspecialchars($t['name']); ?></td>
                                    <td class="p-4 text-slate-350"><?php echo htmlspecialchars($t['route']); ?></td>
                                    <td class="p-4 text-slate-400 max-w-sm whitespace-normal break-words"><?php echo htmlspecialchars($t['review']); ?></td>
                                    <td class="p-4 text-amber-500 font-semibold">⭐ <?php echo $t['rating']; ?>.0</td>
                                    <td class="p-4 text-right">
                                        <form method="POST" onsubmit="return confirm('Confirm deleting this testimonial?')">
                                            <input type="hidden" name="action" value="delete_testimonial">
                                            <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                            <button type="submit" class="text-rose-500 hover:text-rose-400 font-bold">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="p-8 text-center text-slate-500 italic">No testimonials added yet.</td>
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
