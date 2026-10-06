<?php
// Admin Common Header Template
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Enforce admin login
require_admin();

$company_name = get_setting('company_name', 'Avinash Travels');
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control Dashboard - <?php echo htmlspecialchars($company_name); ?></title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js CDN for Analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            navy: '#061325',
                            navyLight: '#0D1F3C',
                            royal: '#0066CC',
                            royalHover: '#0052A3',
                            cyan: '#00B4D8',
                            gold: '#0070E0',
                            goldHover: '#0059B3',
                            textDark: '#07162C'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="h-full bg-slate-950 text-slate-100 flex flex-col font-sans">

    <!-- Top Admin Header Bar -->
    <header class="bg-slate-900 border-b border-slate-800 py-3.5 px-6 shrink-0 flex justify-between items-center z-30">
        <div class="flex items-center space-x-3">
            <button id="sidebar-toggle-btn" class="lg:hidden text-slate-450 hover:text-white mr-2 focus:outline-none">
                <i class="fa-solid fa-bars text-xl"></i>
            </button>
            <a href="index.php" class="flex items-center space-x-3">
                <div class="bg-white/95 px-2.5 py-1 rounded-xl shadow-md border border-white/20 inline-flex items-center">
                    <img src="https://avinashtaxi.com/uploads/logo/logo.png" alt="<?php echo htmlspecialchars($company_name); ?>" class="h-8 w-auto object-contain">
                </div>
                <span class="text-sm sm:text-base font-black font-poppins text-white tracking-wider hidden sm:inline-block">
                    OPERATIONS<span class="text-sky-400">PORTAL</span>
                </span>
                <span class="bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 text-[10px] font-extrabold px-2.5 py-0.5 rounded-full uppercase tracking-wider hidden md:inline-block">
                    Enterprise Admin
                </span>
            </a>
        </div>
        
        <?php
        $header_pending_payments = [];
        $header_pending_count = 0;
        if (isset($pdo) && $pdo) {
            try {
                $stmt_hp = $pdo->query("
                    SELECT b.id, b.booking_id, b.final_total, b.utr_number, b.created_at, b.pickup_city, b.drop_city, bp.name AS passenger_name
                    FROM `bookings` b
                    LEFT JOIN `booking_passengers` bp ON b.id = bp.booking_id
                    WHERE b.payment_status = 'under_review'
                    ORDER BY b.created_at DESC
                    LIMIT 5
                ");
                $header_pending_payments = $stmt_hp->fetchAll();
                $header_pending_count = count($header_pending_payments);
            } catch (Exception $e) {}
        }
        ?>

        <div class="flex items-center space-x-3 sm:space-x-4 text-xs">
            <!-- Real-time Payment & Booking Notification Bell -->
            <div class="relative">
                <button id="header-notif-btn" type="button" onclick="toggleHeaderNotifDropdown()" class="relative p-2 bg-slate-800 hover:bg-slate-750 text-slate-300 hover:text-white rounded-lg border border-slate-700 transition-colors focus:outline-none" title="New Payment Submissions">
                    <i class="fa-solid fa-bell text-sm"></i>
                    <?php if ($header_pending_count > 0): ?>
                        <span class="absolute -top-1 -right-1 w-4 h-4 bg-amber-400 text-slate-950 rounded-full font-black text-[9px] flex items-center justify-center animate-pulse">
                            <?php echo $header_pending_count; ?>
                        </span>
                    <?php endif; ?>
                </button>

                <!-- Dropdown -->
                <div id="header-notif-dropdown" class="hidden absolute right-0 mt-2 w-80 sm:w-96 bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl z-50 overflow-hidden">
                    <div class="p-3 bg-slate-950 border-b border-slate-800 flex items-center justify-between">
                        <span class="text-xs font-bold text-white flex items-center gap-2">
                            <i class="fa-solid fa-bolt text-amber-400"></i> Payment & Booking Alerts
                        </span>
                        <?php if ($header_pending_count > 0): ?>
                            <span class="text-[10px] bg-amber-500/20 text-amber-400 font-extrabold px-2 py-0.5 rounded-full border border-amber-500/30">
                                <?php echo $header_pending_count; ?> New
                            </span>
                        <?php else: ?>
                            <span class="text-[10px] text-emerald-400 font-bold">All settled ✓</span>
                        <?php endif; ?>
                    </div>

                    <div class="max-h-72 overflow-y-auto divide-y divide-slate-800 text-xs">
                        <?php if (!empty($header_pending_payments)): ?>
                            <?php foreach ($header_pending_payments as $hp): ?>
                                <a href="payments.php" class="block p-3 hover:bg-slate-850 transition-colors">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="font-mono font-bold text-sky-400"><?php echo htmlspecialchars($hp['booking_id']); ?></span>
                                        <span class="text-amber-400 font-extrabold">₹<?php echo number_format($hp['final_total'], 2); ?></span>
                                    </div>
                                    <p class="text-slate-300 font-semibold truncate"><?php echo htmlspecialchars($hp['passenger_name'] ?: 'Customer'); ?> (<?php echo htmlspecialchars($hp['pickup_city']); ?> → <?php echo htmlspecialchars($hp['drop_city']); ?>)</p>
                                    <div class="text-[10px] text-slate-500 flex items-center justify-between mt-1">
                                        <span>UTR: <?php echo htmlspecialchars($hp['utr_number'] ?: 'Screenshot Attached'); ?></span>
                                        <span class="text-emerald-400 font-bold">Approve →</span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-6 text-center text-slate-500 text-xs italic">
                                <i class="fa-solid fa-circle-check text-2xl text-emerald-400 mb-1 block"></i>
                                No pending payment proofs right now.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="p-2.5 bg-slate-950 border-t border-slate-800 text-center">
                        <a href="payments.php" class="text-[11px] text-sky-400 hover:text-sky-300 font-bold">
                            Open Payment Approvals Desk →
                        </a>
                    </div>
                </div>
            </div>

            <a href="../index.php" target="_blank" class="px-3 py-1.5 bg-slate-800/90 hover:bg-slate-750 text-sky-400 hover:text-sky-300 font-semibold rounded border border-slate-700 transition-colors inline-flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                <span class="hidden sm:inline">View Website</span>
            </a>
            <span class="text-slate-400 hidden lg:inline-block"><i class="fa-solid fa-user-shield text-amber-400 mr-1.5"></i>Operator: <strong class="text-white"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Staff Admin'); ?></strong></span>
            <a href="logout.php" class="px-3.5 py-1.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 font-bold rounded border border-rose-500/30 transition-colors">
                Logout
            </a>
        </div>
    </header>

    <script>
    function toggleHeaderNotifDropdown() {
        const d = document.getElementById('header-notif-dropdown');
        if (d) d.classList.toggle('hidden');
    }
    document.addEventListener('click', function(e) {
        const btn = document.getElementById('header-notif-btn');
        const d = document.getElementById('header-notif-dropdown');
        if (btn && d && !btn.contains(e.target) && !d.contains(e.target)) {
            d.classList.add('hidden');
        }
    });
    </script>

    <div class="flex flex-row flex-grow overflow-hidden relative">
