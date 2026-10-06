<?php
// Admin Partner Applications Reviewer Module
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Enforce admin login
require_admin();

$message = '';
$error = '';

// Handle Actions (Approve / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');

    if ($id > 0 && in_array($status, ['approved', 'rejected'])) {
        try {
            // Update partner application status
            $stmt = $pdo->prepare("UPDATE `partners` SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);

            // Fetch partner application details to align with drivers status
            $stmt_fetch = $pdo->prepare("SELECT phone, email FROM `partners` WHERE id = ?");
            $stmt_fetch->execute([$id]);
            $partner = $stmt_fetch->fetch();
            
            if ($partner) {
                if ($status === 'approved') {
                    // Automatically approve matching driver profile
                    $stmt_drv = $pdo->prepare("UPDATE `drivers` SET status = 'approved' WHERE phone = ? OR email = ?");
                    $stmt_drv->execute([$partner['phone'], $partner['email']]);
                    
                    // Also approve all document items in driver_documents table
                    $stmt_docs = $pdo->prepare("
                        UPDATE `driver_documents` 
                        SET status = 'approved' 
                        WHERE driver_id = (SELECT id FROM `drivers` WHERE phone = ? OR email = ? LIMIT 1)
                    ");
                    $stmt_docs->execute([$partner['phone'], $partner['email']]);
                } else {
                    // Suspend matching driver
                    $stmt_drv = $pdo->prepare("UPDATE `drivers` SET status = 'pending' WHERE phone = ? OR email = ?");
                    $stmt_drv->execute([$partner['phone'], $partner['email']]);
                }
            }

            $message = "Partner application marked as '$status' successfully.";
        } catch (Exception $e) {
            $error = "Failed to update status: " . $e->getMessage();
        }
    } elseif ($id > 0 && $status === 'delete') {
        try {
            if ($pdo) {
                $pdo->prepare("DELETE FROM `partners` WHERE id = ?")->execute([$id]);
                $message = "Partner application record removed.";
            }
        } catch (Exception $e) {
            $error = "Error removing record: " . $e->getMessage();
        }
    }
}

// Fetch partner applications
$applications = [];
try {
    if ($pdo) {
        $applications = $pdo->query("SELECT * FROM `partners` ORDER BY created_at DESC")->fetchAll();
    }
} catch (Exception $e) {}

// Fallback Chhattisgarh partner applicant data
if (empty($applications)) {
    $applications = [
        ['id' => 1, 'name' => 'Dinesh Chandra Sahu', 'phone' => '9827014455', 'email' => 'dinesh.sahu@gmail.com', 'city' => 'Raipur', 'vehicle_type' => 'Sedan Dzire', 'vehicle_no' => 'CG 04 HA 8899', 'license_no' => 'CG04-2018-002345', 'rc_path' => 'uploads/docs/rc_demo.pdf', 'license_path' => 'uploads/docs/dl_demo.pdf', 'insurance_path' => 'uploads/docs/ins_demo.pdf', 'photo_path' => 'uploads/docs/photo_demo.jpg', 'status' => 'pending', 'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))],
        ['id' => 2, 'name' => 'Hitesh Dewangan', 'phone' => '9406012233', 'email' => 'hitesh.d@yahoo.com', 'city' => 'Bilaspur', 'vehicle_type' => 'SUV Innova', 'vehicle_no' => 'CG 10 BD 4501', 'license_no' => 'CG10-2019-009112', 'rc_path' => 'uploads/docs/rc_demo.pdf', 'license_path' => 'uploads/docs/dl_demo.pdf', 'insurance_path' => '', 'photo_path' => '', 'status' => 'approved', 'created_at' => date('Y-m-d H:i:s', strtotime('-5 days'))],
        ['id' => 3, 'name' => 'Ghanshyam Patel', 'phone' => '9981023456', 'email' => 'ghanshyam.patel@rediffmail.com', 'city' => 'Korba', 'vehicle_type' => 'Ertiga MPV', 'vehicle_no' => 'CG 12 AA 6712', 'license_no' => 'CG12-2020-004561', 'rc_path' => 'uploads/docs/rc_demo.pdf', 'license_path' => 'uploads/docs/dl_demo.pdf', 'insurance_path' => '', 'photo_path' => '', 'status' => 'pending', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))]
    ];
}

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div>
    <div class="mb-6">
        <h1 class="text-xl font-bold font-poppins text-white">Partner Network Applications</h1>
        <p class="text-xs text-slate-400">Review commercial vehicle enlistment requests, view driver profile photos, and approve documents</p>
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

    <!-- Applications Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-lg">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-950 border-b border-slate-850 text-[10px] uppercase font-bold text-slate-400">
                        <th class="p-4">Applicant Name</th>
                        <th class="p-4">Location</th>
                        <th class="p-4">Vehicle Details</th>
                        <th class="p-4">Uploaded Documents</th>
                        <th class="p-4">Submitted Date</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-850">
                    <?php if (!empty($applications)): ?>
                        <?php foreach ($applications as $app): ?>
                            <?php
                            $status_badge = 'bg-amber-500/10 text-amber-400 border border-amber-500/20';
                            if ($app['status'] === 'approved') $status_badge = 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
                            if ($app['status'] === 'rejected') $status_badge = 'bg-rose-500/10 text-rose-400 border border-rose-500/20';
                            ?>
                            <tr class="hover:bg-slate-900/50 transition-colors">
                                <td class="p-4">
                                    <strong class="text-white"><?php echo htmlspecialchars($app['name']); ?></strong><br>
                                    <span class="text-slate-450 text-[10px]"><?php echo htmlspecialchars($app['phone']); ?> | <?php echo htmlspecialchars($app['email']); ?></span>
                                </td>
                                <td class="p-4 text-white"><?php echo htmlspecialchars($app['city']); ?></td>
                                <td class="p-4">
                                    <strong class="text-white"><?php echo htmlspecialchars($app['vehicle_type']); ?> Class</strong><br>
                                    <span class="text-[10px] font-mono text-slate-400">No: <?php echo htmlspecialchars($app['vehicle_no']); ?> | DL: <?php echo htmlspecialchars($app['license_no']); ?></span>
                                </td>
                                <td class="p-4 space-y-1">
                                    <?php if ($app['rc_path']): ?>
                                        <a href="../<?php echo $app['rc_path']; ?>" target="_blank" class="text-amber-400 hover:underline block text-[10px]"><i class="fa-solid fa-file mr-1"></i> Registration Cert (RC)</a>
                                    <?php endif; ?>
                                    <?php if ($app['license_path']): ?>
                                        <a href="../<?php echo $app['license_path']; ?>" target="_blank" class="text-amber-400 hover:underline block text-[10px]"><i class="fa-solid fa-file mr-1"></i> Driving License (DL)</a>
                                    <?php endif; ?>
                                    <?php if ($app['insurance_path']): ?>
                                        <a href="../<?php echo $app['insurance_path']; ?>" target="_blank" class="text-amber-400 hover:underline block text-[10px]"><i class="fa-solid fa-file mr-1"></i> Vehicle Insurance</a>
                                    <?php endif; ?>
                                    <?php if ($app['photo_path']): ?>
                                        <a href="../<?php echo $app['photo_path']; ?>" target="_blank" class="text-amber-400 hover:underline block text-[10px]"><i class="fa-solid fa-image mr-1"></i> Profile Photo</a>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-slate-350"><?php echo date('d M Y', strtotime($app['created_at'])); ?></td>
                                <td class="p-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo $status_badge; ?>">
                                        <?php echo ucfirst($app['status']); ?>
                                    </span>
                                </td>
                                <td class="p-4 text-right whitespace-nowrap">
                                    <div class="flex justify-end items-center space-x-1.5">
                                        <?php if ($app['status'] === 'pending'): ?>
                                            <form method="POST" class="inline-block">
                                                <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                                <input type="hidden" name="status" value="approved">
                                                <button type="submit" class="px-2 py-1 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold rounded text-[10px]">
                                                    Approve
                                                </button>
                                            </form>
                                            <form method="POST" class="inline-block">
                                                <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                                <input type="hidden" name="status" value="rejected">
                                                <button type="submit" class="px-2 py-1 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold rounded text-[10px]">
                                                    Reject
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-slate-400 text-[10px]">Reviewed</span>
                                        <?php endif; ?>
                                        <form method="POST" class="inline-block" onsubmit="return confirm('Delete partner application record?')">
                                            <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                            <input type="hidden" name="status" value="delete">
                                            <button type="submit" class="px-2 py-1 bg-slate-950 hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 border border-slate-800 text-[10px] rounded transition-colors" title="Delete Application">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-500 italic">No partner applications pending review.</td>
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
