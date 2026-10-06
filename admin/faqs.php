<?php
// Admin FAQ Accordion CRUD Module
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Enforce admin login
require_admin();

$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_faq') {
        $question = trim($_POST['question'] ?? '');
        $answer = trim($_POST['answer'] ?? '');
        $sort = intval($_POST['sort_order'] ?? 0);

        if ($question && $answer) {
            try {
                $stmt = $pdo->prepare("INSERT INTO `faqs` (question, answer, sort_order) VALUES (?, ?, ?)");
                $stmt->execute([$question, $answer, $sort]);
                $message = "FAQ query added successfully.";
            } catch (Exception $e) {
                $error = "Error adding FAQ: " . $e->getMessage();
            }
        } else {
            $error = "Please fill in both question and answer.";
        }
    } elseif ($action === 'delete_faq') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM `faqs` WHERE id = ?")->execute([$id]);
                $message = "FAQ query removed from catalog.";
            } catch (Exception $e) {
                $error = "Error deleting FAQ: " . $e->getMessage();
            }
        }
    }
}

// Fetch all FAQs
$faqs = [];
try {
    if ($pdo) {
        $faqs = $pdo->query("SELECT * FROM `faqs` ORDER BY sort_order ASC, id DESC")->fetchAll();
    }
} catch (Exception $e) {}

if (empty($faqs)) {
    $faqs = [
        ['id' => 1, 'question' => 'Are all Chhattisgarh districts covered by Avinash Travels?', 'answer' => 'Yes, Avinash Travels operates 24x7 across all 33 districts of Chhattisgarh including Raipur, Bilaspur, Durg, Korba, Bastar, and Surguja.', 'sort_order' => 1],
        ['id' => 2, 'question' => 'Are tolls and driver allowances included in the quote?', 'answer' => 'Driver allowance is included in our transparent billing. Highway tolls and state taxes are calculated upfront during checkout so you face zero hidden surprises.', 'sort_order' => 2],
        ['id' => 3, 'question' => 'Can I book a one-way cab without paying return fare?', 'answer' => 'Yes! We specialize in genuine one-way taxi services across Chhattisgarh where you pay strictly for the distance traveled one-way.', 'sort_order' => 3],
        ['id' => 4, 'question' => 'How can I pay for my cab booking?', 'answer' => 'You can pay via UPI (Google Pay, PhonePe, Paytm), NetBanking, Credit/Debit cards, or directly in cash to the chauffeur upon arrival.', 'sort_order' => 4]
    ];
}

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div>
    <div class="mb-6">
        <h1 class="text-xl font-bold font-poppins text-white">Homepage FAQs Accordion</h1>
        <p class="text-xs text-slate-400">Manage accordion FAQs, write helpful descriptions, and prioritize display orders</p>
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
        
        <!-- Add FAQ Form (4 columns) -->
        <div class="lg:col-span-4 bg-slate-900 border border-slate-800 p-5 rounded-xl h-fit">
            <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-4">Add FAQ Query</h3>
            
            <form method="POST" class="space-y-4 text-xs">
                <input type="hidden" name="action" value="add_faq">
                
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Question Description</label>
                    <input type="text" name="question" placeholder="E.g. Are tolls included in fares?" class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required>
                </div>
                
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Display Sort Order</label>
                    <input type="number" name="sort_order" value="0" class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Helpful Answer</label>
                    <textarea name="answer" rows="5" placeholder="Base fares exclude tolls, which are paid based on actual route usage..." class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required></textarea>
                </div>

                <button type="submit" class="w-full py-2 bg-gradient-to-r from-blue-600 to-sky-500 hover:from-blue-500 hover:to-sky-400 text-white font-bold rounded text-xs transition-colors">
                    Save FAQ
                </button>
            </form>
        </div>

        <!-- FAQs list (8 columns) -->
        <div class="lg:col-span-8 bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-lg">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-950 border-b border-slate-850 text-[10px] uppercase font-bold text-slate-400">
                            <th class="p-4">Sort</th>
                            <th class="p-4">FAQ Question</th>
                            <th class="p-4">Faq Answer Description</th>
                            <th class="p-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-850">
                        <?php if (!empty($faqs)): ?>
                            <?php foreach ($faqs as $f): ?>
                                <tr class="hover:bg-slate-900/50 transition-colors">
                                    <td class="p-4 font-bold text-slate-400"><?php echo $f['sort_order']; ?></td>
                                    <td class="p-4 font-bold text-white max-w-xs whitespace-normal break-words"><?php echo htmlspecialchars($f['question']); ?></td>
                                    <td class="p-4 text-slate-400 max-w-sm whitespace-normal break-words"><?php echo htmlspecialchars($f['answer']); ?></td>
                                    <td class="p-4 text-right">
                                        <form method="POST" onsubmit="return confirm('Remove FAQ query?')">
                                            <input type="hidden" name="action" value="delete_faq">
                                            <input type="hidden" name="id" value="<?php echo $f['id']; ?>">
                                            <button type="submit" class="text-rose-500 hover:text-rose-400 font-bold">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="p-8 text-center text-slate-500 italic">No FAQs added yet.</td>
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
