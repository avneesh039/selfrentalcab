<?php
// Admin Enquiries Inbox Module
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Enforce admin login
require_admin();

$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE `enquiries` SET is_read = 1 WHERE id = ?")->execute([$id]);
            $message = "Enquiry marked as read.";
        }
    } elseif ($action === 'delete_enquiry') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM `enquiries` WHERE id = ?")->execute([$id]);
            $message = "Enquiry log deleted.";
        }
    }
}

// Fetch enquiries
$enquiries = [];
try {
    if ($pdo) {
        $enquiries = $pdo->query("SELECT * FROM `enquiries` ORDER BY is_read ASC, created_at DESC")->fetchAll();
    }
} catch (Exception $e) {}

if (empty($enquiries)) {
    $enquiries = [
        ['id' => 1, 'name' => 'Rajeshwar Singh', 'email' => 'rajeshwar.singh@gmail.com', 'phone' => '9827018899', 'subject' => 'Corporate Monthly Cab Requirement (Raipur - Bhilai)', 'message' => 'We require 2 Innova Crystas on a monthly billing retainer for executive staff traveling daily between Raipur VIP Road and Bhilai Industrial Zone.', 'is_read' => 0, 'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))],
        ['id' => 2, 'name' => 'Sunita Mahobia', 'email' => 'sunita.m@gmail.com', 'phone' => '9425208811', 'subject' => 'Tempo Traveller for Dongargarh Pilgrimage', 'message' => 'Looking for a 17-20 seater luxury Tempo Traveller for our 3-day family darshan to Maa Bamleshwari Temple Dongargarh and Rajim.', 'is_read' => 1, 'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))]
    ];
}

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div>
    <div class="mb-6">
        <h1 class="text-xl font-bold font-poppins text-white">Contact Enquiry Inbox</h1>
        <p class="text-xs text-slate-400">Review message submissions from customers regarding customizable outstation queries and wedding planning</p>
    </div>

    <?php if ($message): ?>
        <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-3 rounded-lg text-xs mb-6">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Enquiries Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-lg">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-950 border-b border-slate-850 text-[10px] uppercase font-bold text-slate-400">
                        <th class="p-4">Contact Sender</th>
                        <th class="p-4">Contact Details</th>
                        <th class="p-4">Subject</th>
                        <th class="p-4">Message Content details</th>
                        <th class="p-4">Date</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-850">
                    <?php if (!empty($enquiries)): ?>
                        <?php foreach ($enquiries as $enq): ?>
                            <tr class="hover:bg-slate-900/50 transition-colors <?php echo ($enq['is_read'] == 0) ? 'bg-amber-500/5' : ''; ?>">
                                <td class="p-4 font-bold text-white"><?php echo htmlspecialchars($enq['name']); ?></td>
                                <td class="p-4">
                                    <span class="text-slate-200"><?php echo htmlspecialchars($enq['email']); ?></span><br>
                                    <span class="text-[10px] text-slate-450"><?php echo htmlspecialchars($enq['phone']); ?></span>
                                </td>
                                <td class="p-4 text-white font-semibold"><?php echo htmlspecialchars($enq['subject']); ?></td>
                                <td class="p-4 text-slate-400 max-w-sm whitespace-normal break-words"><?php echo htmlspecialchars($enq['message']); ?></td>
                                <td class="p-4 text-slate-350"><?php echo date('d M Y h:i A', strtotime($enq['created_at'])); ?></td>
                                <td class="p-4">
                                    <?php if ($enq['is_read'] == 1): ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-850 text-slate-400 border border-slate-800 uppercase">Read</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20 uppercase">New Message</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex justify-end space-x-1.5">
                                        <?php if ($enq['is_read'] == 0): ?>
                                            <form method="POST" class="inline-block">
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="id" value="<?php echo $enq['id']; ?>">
                                                <button type="submit" class="px-2 py-1 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold rounded text-[10px]">
                                                    Read
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" class="inline-block" onsubmit="return confirm('Delete message log?')">
                                            <input type="hidden" name="action" value="delete_enquiry">
                                            <input type="hidden" name="id" value="<?php echo $enq['id']; ?>">
                                            <button type="submit" class="px-2 py-1 bg-slate-950 hover:bg-rose-500 hover:text-white border border-slate-800 text-[10px] font-bold rounded text-slate-300">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-500 italic">No message enquiries in contact inbox.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
