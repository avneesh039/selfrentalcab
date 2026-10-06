<?php
// Driver Panel Workspace
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_driver();

$driver_id = $_SESSION['driver_id'];
$message = '';
$error = '';

// Retrieve driver data
$stmt_drv = $pdo->prepare("SELECT * FROM `drivers` WHERE id = ?");
$stmt_drv->execute([$driver_id]);
$driver = $stmt_drv->fetch();

// 1. Process Document Upload
if (isset($_POST['upload_doc'])) {
    $doc_type = trim($_POST['doc_type'] ?? '');
    
    if (empty($doc_type)) {
        $error = 'Document type is required.';
    } elseif (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select a valid file to upload.';
    } else {
        try {
            $file_name = $_FILES['document']['name'];
            $file_tmp = $_FILES['document']['tmp_name'];
            $file_size = $_FILES['document']['size'];
            $file_type = $_FILES['document']['type'];
            
            // Limit to 5MB
            if ($file_size > 5 * 1024 * 1024) {
                throw new Exception("File size limit exceeded. Max limit is 5MB.");
            }

            $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
            $allowed_mimes = ['image/jpeg', 'image/png', 'application/pdf'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed_extensions) || !in_array($file_type, $allowed_mimes)) {
                throw new Exception("Invalid file type. Allowed: JPG, PNG, PDF.");
            }

            $upload_dir = __DIR__ . '/../uploads/documents/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $new_filename = strtolower($doc_type) . '_' . md5(uniqid() . $file_name) . '.' . $ext;
            $dest_path = $upload_dir . $new_filename;

            if (move_uploaded_file($file_tmp, $dest_path)) {
                $doc_path = 'uploads/documents/' . $new_filename;
                
                // Save to driver_documents
                $stmt_doc = $pdo->prepare("INSERT INTO `driver_documents` (driver_id, doc_type, doc_path, status) VALUES (?, ?, ?, 'pending')");
                $stmt_doc->execute([$driver_id, strtoupper($doc_type), $doc_path]);

                $message = "Document (" . strtoupper($doc_type) . ") uploaded successfully. Pending verification.";
            } else {
                throw new Exception("Failed to move file to destination.");
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// 2. Process Bid Submission
if (isset($_POST['submit_bid'])) {
    $trip_id = intval($_POST['trip_id'] ?? 0);
    $bid_amount = floatval($_POST['bid_amount'] ?? 0);
    $comments = trim($_POST['comments'] ?? '');

    if ($driver['status'] !== 'approved') {
        $error = 'Only approved driver partners can place trip bids.';
    } elseif ($trip_id <= 0 || $bid_amount <= 0) {
        $error = 'Please enter a valid trip and bid amount.';
    } else {
        try {
            // Check trip status
            $stmt_tr = $pdo->prepare("SELECT status FROM `trips` WHERE id = ?");
            $stmt_tr->execute([$trip_id]);
            $trip_status = $stmt_tr->fetchColumn();

            if ($trip_status !== 'open') {
                $error = 'This trip is no longer open for bidding.';
            } else {
                // Save bid
                $stmt_bid = $pdo->prepare("
                    INSERT INTO `trip_bids` (trip_id, driver_id, bid_amount, comments, status) 
                    VALUES (?, ?, ?, ?, 'pending')
                    ON DUPLICATE KEY UPDATE bid_amount = VALUES(bid_amount), comments = VALUES(comments), status = 'pending'
                ");
                $stmt_bid->execute([$trip_id, $driver_id, $bid_amount, $comments]);
                $message = "Bid of ₹" . number_format($bid_amount, 2) . " submitted successfully.";
            }
        } catch (Exception $e) {
            $error = 'Failed to submit bid: ' . $e->getMessage();
        }
    }
}

// 3. Process Trip Completion
if (isset($_GET['complete_booking_id'])) {
    $complete_booking_id = intval($_GET['complete_booking_id']);
    
    // Verify driver is indeed assigned to this booking
    $stmt_chk = $pdo->prepare("
        SELECT b.id FROM `bookings` b
        INNER JOIN `vehicles` v ON b.vehicle_id = v.id
        WHERE b.id = ? AND b.status = 'in_progress' AND (
            SELECT driver_id FROM `trip_bids` WHERE trip_id = (SELECT id FROM `trips` WHERE booking_id = b.id LIMIT 1) AND status = 'accepted' LIMIT 1
        ) = ?
    ");
    $stmt_chk->execute([$complete_booking_id, $driver_id]);
    
    if ($stmt_chk->fetch()) {
        // Update booking status to completed, payment to paid
        $pdo->prepare("UPDATE `bookings` SET status = 'completed', payment_status = 'paid' WHERE id = ?")->execute([$complete_booking_id]);
        
        // Mark vehicle as available
        $pdo->prepare("UPDATE `vehicles` SET status = 'available' WHERE id = (SELECT vehicle_id FROM `bookings` WHERE id = ? LIMIT 1)")->execute([$complete_booking_id]);
        
        $message = "Trip marked as completed. Vehicle returned to available pool.";
    } else {
        $error = "Action unauthorized or trip already completed.";
    }
}

// 4. Fetch Available Bidding Trips
$open_trips = [];
if ($driver['status'] === 'approved') {
    $open_trips = $pdo->query("
        SELECT t.*, b.trip_type, b.pickup_date, b.pickup_time, vc.name AS category_name,
               (SELECT bid_amount FROM `trip_bids` WHERE trip_id = t.id AND driver_id = $driver_id LIMIT 1) AS my_bid_amount,
               (SELECT status FROM `trip_bids` WHERE trip_id = t.id AND driver_id = $driver_id LIMIT 1) AS my_bid_status
        FROM `trips` t
        INNER JOIN `bookings` b ON t.booking_id = b.id
        INNER JOIN `vehicle_categories` vc ON b.category_id = vc.id
        WHERE t.status = 'open'
        ORDER BY t.travel_date ASC, t.travel_time ASC
    ")->fetchAll();
}

// 5. Fetch Active / Won Trips assigned to this driver
$assigned_trips = $pdo->query("
    SELECT b.*, bp.name AS passenger_name, bp.phone AS passenger_phone, bp.pickup_address, bp.drop_address, bp.passenger_count, bp.special_instructions,
           vc.name AS category_name, t.id AS trip_db_id
    FROM `bookings` b
    INNER JOIN `booking_passengers` bp ON b.id = bp.booking_id
    INNER JOIN `vehicle_categories` vc ON b.category_id = vc.id
    INNER JOIN `trips` t ON b.id = t.booking_id
    INNER JOIN `trip_bids` tb ON t.id = tb.trip_id AND tb.driver_id = $driver_id AND tb.status = 'accepted'
    WHERE b.status IN ('assigned', 'in_progress')
    ORDER BY b.pickup_date ASC
")->fetchAll();

// 6. Fetch Uploaded Documents
$uploaded_docs = $pdo->query("SELECT * FROM `driver_documents` WHERE driver_id = $driver_id ORDER BY uploaded_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Panel - Avinash Travels</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col">

    <!-- Header bar -->
    <header class="bg-slate-900 border-b border-slate-800 py-4 px-6">
        <div class="container mx-auto flex justify-between items-center">
            <a href="../index.php" class="flex items-center space-x-3">
                <div class="bg-white/95 px-2.5 py-1 rounded-xl shadow-md border border-white/20 inline-flex items-center">
                    <img src="https://avinashtaxi.com/uploads/logo/logo.png" alt="Avinash Travels Logo" class="h-9 w-auto object-contain">
                </div>
                <span class="text-lg font-black font-poppins text-white tracking-wider hidden sm:inline-block">
                    DRIVER<span class="text-sky-400">PORTAL</span>
                </span>
            </a>
            
            <div class="flex items-center space-x-4">
                <span class="text-xs text-slate-400">Driver Partner: <strong class="text-white"><?php echo htmlspecialchars($driver['name']); ?></strong></span>
                <a href="logout.php" class="px-4 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-bold rounded border border-slate-750 transition-colors">
                    Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Workspace Container -->
    <main class="flex-grow container mx-auto px-4 py-8 grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Left Panel: Driver Stats, Docs Status -->
        <div class="lg:col-span-4 space-y-6">
            
            <!-- Profile Card -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
                <div class="text-center pb-4 border-b border-slate-800 mb-4">
                    <div class="w-14 h-14 bg-sky-500/10 text-sky-400 rounded-full flex items-center justify-center text-2xl mx-auto font-bold mb-2">
                        <?php echo substr($driver['name'], 0, 1); ?>
                    </div>
                    <h3 class="font-bold text-white"><?php echo htmlspecialchars($driver['name']); ?></h3>
                    <p class="text-xs text-slate-400 mt-1"><?php echo htmlspecialchars($driver['vehicle_type']); ?> Class • <?php echo htmlspecialchars($driver['vehicle_no']); ?></p>
                    
                    <!-- Approval Banner -->
                    <?php if ($driver['status'] === 'approved'): ?>
                        <span class="inline-block mt-3 bg-emerald-500/10 text-emerald-400 text-[10px] font-bold px-2.5 py-0.5 rounded border border-emerald-500/20 uppercase">Approved Chauffeur</span>
                    <?php else: ?>
                        <span class="inline-block mt-3 bg-amber-500/10 text-amber-400 text-[10px] font-bold px-2.5 py-0.5 rounded border border-amber-500/20 uppercase">Pending Verification</span>
                    <?php endif; ?>
                </div>

                <div class="text-xs text-slate-400 space-y-2">
                    <p><strong>Mobile:</strong> <?php echo htmlspecialchars($driver['phone']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($driver['email']); ?></p>
                    <p><strong>Operating City:</strong> <?php echo htmlspecialchars($driver['city']); ?></p>
                    <p><strong>Rating:</strong> ⭐ <?php echo number_format($driver['rating'], 1); ?></p>
                </div>
            </div>

            <!-- Upload Documents Form -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
                <div>
                    <h3 class="text-sm font-bold text-white font-poppins mb-1">Verify Documents</h3>
                    <p class="text-[11px] text-slate-400 mb-4">Submit vehicle RC, driver license or insurance details for verification review</p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-rose-500/10 border border-rose-500/20 text-rose-400 p-2.5 rounded text-xs mb-4">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($message): ?>
                    <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-2.5 rounded text-xs mb-4">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="upload_doc" value="1">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Document Category</label>
                        <select name="doc_type" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-xs text-white focus:outline-none focus:border-sky-400" required>
                            <option value="LICENSE">Driving License (DL)</option>
                            <option value="RC">Registration Certificate (RC)</option>
                            <option value="INSURANCE">Vehicle Insurance</option>
                            <option value="PHOTO">Profile Photo</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Select File (JPG, PNG, PDF up to 5MB)</label>
                        <input type="file" name="document" class="w-full text-xs text-slate-400 file:mr-3 file:py-1.5 file:px-2.5 file:rounded file:border-0 file:text-[11px] file:font-semibold file:bg-slate-950 file:text-slate-300 hover:file:bg-slate-850 cursor-pointer" required>
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-gradient-to-r from-blue-600 to-sky-500 hover:from-blue-500 hover:to-sky-400 text-white font-bold rounded text-xs transition-colors shadow-md">
                        Upload Document
                    </button>
                </form>
            </div>

            <!-- Document List -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
                <h3 class="text-xs font-bold text-white font-poppins uppercase tracking-wider mb-3">Submitted Documents</h3>
                <div class="space-y-2">
                    <?php if (!empty($uploaded_docs)): ?>
                        <?php foreach ($uploaded_docs as $doc): ?>
                            <?php
                            $badge = 'bg-amber-500/10 text-amber-400 border-amber-500/20';
                            if ($doc['status'] === 'approved') $badge = 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
                            if ($doc['status'] === 'rejected') $badge = 'bg-rose-500/10 text-rose-400 border-rose-500/20';
                            ?>
                            <div class="bg-slate-950 p-2.5 rounded border border-slate-850 flex justify-between items-center text-xs">
                                <span><?php echo $doc['doc_type']; ?></span>
                                <span class="px-2 py-0.5 rounded text-[9px] font-bold border <?php echo $badge; ?>">
                                    <?php echo $doc['status']; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-slate-500 text-xs italic">No documents submitted yet.</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Right Panel: Trip Bidding Board, Active/Won Trips -->
        <div class="lg:col-span-8 space-y-8">
            
            <!-- Bidding board for approved partners -->
            <?php if ($driver['status'] === 'approved'): ?>
                
                <!-- Won/Assigned Active Trips -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
                    <h3 class="text-md font-bold text-white font-poppins mb-4 flex items-center">
                        <i class="fa-solid fa-route text-emerald-400 mr-2"></i> Won Trips (Assigned & In Progress)
                    </h3>
                    
                    <div class="space-y-4">
                        <?php if (!empty($assigned_trips)): ?>
                            <?php foreach ($assigned_trips as $t): ?>
                                <div class="bg-slate-950 border border-slate-850 rounded-xl p-5 space-y-4">
                                    <div class="flex justify-between items-start border-b border-slate-850 pb-3">
                                        <div>
                                            <span class="text-[10px] font-bold text-emerald-400 uppercase tracking-wider block mb-1">Trip Assigned</span>
                                            <h4 class="text-md font-bold text-white">
                                                <?php echo htmlspecialchars($t['pickup_city']); ?> 
                                                <i class="fa-solid fa-arrow-right text-sky-400 text-xs mx-1"></i> 
                                                <?php echo htmlspecialchars($t['drop_city']); ?>
                                            </h4>
                                            <p class="text-xs text-slate-400 mt-1"><i class="fa-solid fa-calendar mr-1"></i> <?php echo date('d M Y', strtotime($t['pickup_date'])); ?> at <?php echo date('h:i A', strtotime($t['pickup_time'])); ?></p>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-xs text-slate-400 block">All inclusive Fare</span>
                                            <span class="text-lg font-bold text-sky-400">₹<?php echo number_format($t['final_total'], 2); ?></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Passenger contact info -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs text-slate-300">
                                        <div>
                                            <p class="mb-1"><strong class="text-slate-400">Passenger:</strong> <?php echo htmlspecialchars($t['passenger_name']); ?></p>
                                            <p class="mb-1"><strong class="text-slate-400">Phone:</strong> <a href="tel:<?php echo $t['passenger_phone']; ?>" class="text-amber-500 hover:underline"><?php echo htmlspecialchars($t['passenger_phone']); ?></a></p>
                                            <p><strong class="text-slate-400">Passengers count:</strong> <?php echo $t['passenger_count']; ?></p>
                                        </div>
                                        <div>
                                            <p class="mb-1"><strong class="text-slate-400">Pickup:</strong> <?php echo htmlspecialchars($t['pickup_address']); ?></p>
                                            <p class="mb-1"><strong class="text-slate-400">Drop:</strong> <?php echo htmlspecialchars($t['drop_address']); ?></p>
                                            <?php if ($t['special_instructions']): ?>
                                                <p><strong class="text-slate-400">Note:</strong> <?php echo htmlspecialchars($t['special_instructions']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="pt-3 border-t border-slate-850 flex justify-between items-center gap-3">
                                        <?php if ($t['status'] === 'assigned'): ?>
                                            <!-- Start Trip status change -->
                                            <a href="/admin/bookings.php?id=<?php echo $t['id']; ?>&status=in_progress" class="hidden"></a>
                                            <span class="text-[11px] text-slate-450 italic"><i class="fa-solid fa-circle-exclamation mr-1 text-sky-400"></i>Admin updates status to In Progress. Contact admin if ready to leave.</span>
                                        <?php elseif ($t['status'] === 'in_progress'): ?>
                                            <a href="dashboard.php?complete_booking_id=<?php echo $t['id']; ?>" onclick="return confirm('Confirm completing this trip. Vehicle will return to available pool.')" class="w-full sm:w-auto px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold rounded text-xs text-center transition-colors">
                                                Mark Trip as Completed
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-slate-500 text-xs italic">No active or won trips assigned yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Biddable board -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
                    <h3 class="text-md font-bold text-white font-poppins mb-4 flex items-center">
                        <i class="fa-solid fa-gavel text-sky-400 mr-2"></i> Active Trip Bidding Board
                    </h3>
                    
                    <div class="space-y-4">
                        <?php if (!empty($open_trips)): ?>
                            <?php foreach ($open_trips as $t): ?>
                                <div class="bg-slate-950 border border-slate-850 rounded-xl p-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                    <div>
                                        <div class="flex items-center space-x-2.5 mb-1.5">
                                            <span class="bg-slate-900 text-slate-400 text-[10px] font-bold px-2 py-0.5 rounded border border-slate-800"><?php echo strtoupper(str_replace('_', ' ', $t['trip_type'])); ?></span>
                                            <span class="text-xs text-slate-400 font-semibold"><?php echo $t['est_distance']; ?> KM Est.</span>
                                        </div>
                                        <h4 class="text-md font-bold text-white">
                                            <?php echo htmlspecialchars($t['pickup_city']); ?> 
                                            <i class="fa-solid fa-arrow-right text-sky-400 text-xs mx-1"></i> 
                                            <?php echo htmlspecialchars($t['drop_city']); ?>
                                        </h4>
                                        <p class="text-xs text-slate-400 mt-1"><i class="fa-solid fa-calendar mr-1"></i> <?php echo date('d M Y', strtotime($t['travel_date'])); ?> at <?php echo date('h:i A', strtotime($t['travel_time'])); ?></p>
                                        <p class="text-xs text-slate-450 mt-1">Vehicle category required: <strong class="text-white"><?php echo htmlspecialchars($t['category_name']); ?></strong></p>
                                    </div>
                                    
                                    <!-- Bidding Action / Bid status -->
                                    <div class="w-full md:w-auto text-right">
                                        <?php if ($t['my_bid_status']): ?>
                                            <!-- Already bid -->
                                            <div class="bg-slate-900 p-3 rounded border border-slate-800 text-left min-w-[200px]">
                                                <span class="text-[10px] text-slate-450 uppercase block font-semibold">Your Submitted Bid</span>
                                                <div class="flex justify-between items-center mt-1">
                                                    <span class="text-sm font-bold text-sky-400">₹<?php echo number_format($t['my_bid_amount'], 2); ?></span>
                                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold <?php echo ($t['my_bid_status'] === 'accepted') ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : (($t['my_bid_status'] === 'rejected') ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20'); ?>">
                                                        <?php echo ucfirst($t['my_bid_status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <!-- Place Bid Form -->
                                            <form method="POST" class="flex flex-col sm:flex-row gap-2 max-w-sm ml-auto">
                                                <input type="hidden" name="submit_bid" value="1">
                                                <input type="hidden" name="trip_id" value="<?php echo $t['id']; ?>">
                                                <input type="number" name="bid_amount" placeholder="Bid Amount (₹)" min="1" step="10" class="px-3 py-2 bg-slate-900 border border-slate-800 rounded text-xs text-white focus:outline-none focus:border-sky-400 w-full sm:w-28" required>
                                                <input type="text" name="comments" placeholder="Comments" class="px-3 py-2 bg-slate-900 border border-slate-800 rounded text-xs text-white focus:outline-none focus:border-sky-400 w-full sm:w-36">
                                                <button type="submit" class="px-4 py-2 bg-gradient-to-r from-blue-600 to-sky-500 hover:from-blue-500 hover:to-sky-400 text-white font-bold rounded text-xs transition-colors shrink-0 shadow-md">
                                                    Bid Now
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-slate-500 text-xs italic">No open trips matching your coordinates are available for bidding today.</p>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- Pending application lock -->
                <div class="bg-amber-500/10 border border-amber-500/20 rounded-2xl p-8 text-center max-w-lg mx-auto">
                    <i class="fa-solid fa-triangle-exclamation text-sky-400 text-4xl mb-4 block"></i>
                    <h3 class="text-lg font-bold text-white font-poppins mb-2">Partner Application Under Review</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">Thank you for submitting your driver-partner registration form. Our safety team is verifying your license, RC, and insurance details. You will gain bidding credentials immediately upon approval.</p>
                </div>
            <?php endif; ?>

        </div>
    </main>
</body>
</html>
