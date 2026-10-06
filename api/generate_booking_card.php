<?php
// API / Web View for Booking Pass Image & Download
// Avinash Travels Raipur
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/booking_card_helper.php';

$booking_id = trim($_GET['id'] ?? $_GET['booking_id'] ?? '');
$action = trim($_GET['action'] ?? 'view');

if (empty($booking_id)) {
    die("Error: Invalid or missing Booking Reference.");
}

$booking = get_booking_details_for_card($booking_id);
if (!$booking) {
    die("Error: Booking record #$booking_id not found.");
}

// Generate card if it doesn't exist
$res = generate_booking_card($booking_id);
$rel_file = $res['file_path'] ?? '';
$abs_file = __DIR__ . '/../' . $rel_file;

// Direct image streaming for WhatsApp/Direct links
if ($action === 'image' || $action === 'raw') {
    if (file_exists($abs_file)) {
        header('Content-Type: image/png');
        header('Content-Length: ' . filesize($abs_file));
        header('Cache-Control: public, max-age=86400');
        readfile($abs_file);
        exit;
    }
}

// Direct Download
if ($action === 'download') {
    if (file_exists($abs_file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="AvinashTravels_Booking_' . preg_replace('/[^a-zA-Z0-9]/', '', $booking_id) . '.png"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($abs_file));
        readfile($abs_file);
        exit;
    }
}

// Otherwise display responsive web view of the official AI pass
$wa_data = get_booking_whatsapp_data($booking_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Pass #<?php echo htmlspecialchars($booking['booking_id']); ?> - Avinash Travels</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Outfit:wght@600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-outfit { font-family: 'Outfit', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-white min-h-screen flex flex-col justify-between p-4 sm:p-8">

    <div class="max-w-4xl mx-auto w-full">
        <!-- Top Bar -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6 pb-4 border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 to-sky-400 flex items-center justify-center font-black text-xl shadow-lg shadow-sky-500/30">
                    A
                </div>
                <div>
                    <h1 class="text-lg font-extrabold font-outfit text-white">AVINASH TRAVELS</h1>
                    <p class="text-xs text-slate-400">Official Booking Pass & Confirmation Card</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="?id=<?php echo urlencode($booking_id); ?>&action=download" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 text-xs font-bold rounded-xl inline-flex items-center gap-2 transition-all">
                    <i class="fa-solid fa-download text-sky-400"></i> Download PNG
                </a>
                <a href="<?php echo htmlspecialchars($wa_data['whatsapp_url'] ?? '#'); ?>" target="_blank" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl inline-flex items-center gap-2 transition-all shadow-lg shadow-emerald-900/30">
                    <i class="fa-brands fa-whatsapp text-sm"></i> Share via WhatsApp
                </a>
            </div>
        </div>

        <!-- Booking Pass Card Graphic -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-3 sm:p-4 shadow-2xl relative overflow-hidden group">
            <div class="text-center mb-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                    <i class="fa-solid fa-circle-check"></i> OFFICIAL AI CONFIRMATION PASS • VERIFIED BY DISPATCH
                </span>
            </div>

            <?php if (file_exists($abs_file)): ?>
                <div class="rounded-2xl overflow-hidden border border-slate-800 shadow-xl bg-slate-950">
                    <img src="../<?php echo htmlspecialchars($rel_file); ?>?v=<?php echo time(); ?>" alt="Official Booking Pass" class="w-full h-auto object-cover rounded-2xl">
                </div>
            <?php else: ?>
                <div class="p-8 text-center text-slate-400">
                    Pass image processing... Please refresh the page.
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Summary & Help Desk -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
            <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl text-xs space-y-1">
                <span class="text-slate-400 uppercase font-bold text-[10px]">Booking Reference</span>
                <p class="font-mono text-base font-bold text-sky-400"><?php echo htmlspecialchars($booking['booking_id']); ?></p>
                <p class="text-slate-500 text-[11px]">Keep this ID handy for chauffeur coordination</p>
            </div>

            <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl text-xs space-y-1">
                <span class="text-slate-400 uppercase font-bold text-[10px]">Route & Schedule</span>
                <p class="font-bold text-white"><?php echo htmlspecialchars($booking['pickup_city']); ?> ➔ <?php echo htmlspecialchars($booking['drop_city']); ?></p>
                <p class="text-amber-400 text-[11px]"><?php echo date('d-M-Y', strtotime($booking['pickup_date'])); ?> at <?php echo date('h:i A', strtotime($booking['pickup_time'])); ?></p>
            </div>

            <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl text-xs space-y-1">
                <span class="text-slate-400 uppercase font-bold text-[10px]">24x7 Customer Helpline</span>
                <p class="font-bold text-emerald-400 text-sm">
                    <a href="tel:8120868818" class="hover:underline">+91 8120868818</a>
                </p>
                <p class="text-slate-500 text-[11px]">Avinash Travels Dispatch Desk, Raipur</p>
            </div>
        </div>

        <div class="text-center mt-8">
            <a href="../#track" class="text-xs text-sky-400 hover:text-sky-300 font-bold inline-flex items-center gap-1.5">
                <i class="fa-solid fa-magnifying-glass"></i> Track Live Ride On Main Website
            </a>
        </div>
    </div>

    <footer class="mt-12 text-center text-xs text-slate-500">
        © <?php echo date('Y'); ?> Avinash Tour & Travels • Chhattisgarh Premium Taxi Service. All rights reserved.
    </footer>

</body>
</html>
