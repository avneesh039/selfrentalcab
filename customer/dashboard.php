<?php
// Customer Dashboard Panel
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_customer();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Retrieve customer details
$stmt_usr = $pdo->prepare("SELECT * FROM `users` WHERE id = ?");
$stmt_usr->execute([$user_id]);
$user = $stmt_usr->fetch();

// 1. Process Profile Update
if (isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $new_pass = trim($_POST['new_password'] ?? '');

    if (empty($name) || empty($email) || empty($phone)) {
        $error = 'Profile name, email, and phone cannot be empty.';
    } else {
        try {
            // Check email/phone uniqueness excluding this user
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE (email = ? OR phone = ?) AND id != ?");
            $stmt->execute([$email, $phone, $user_id]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = 'Email or phone number is already registered by another user.';
            } else {
                if (!empty($new_pass)) {
                    $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                    $stmt_up = $pdo->prepare("UPDATE `users` SET name = ?, email = ?, phone = ?, password_hash = ? WHERE id = ?");
                    $stmt_up->execute([$name, $email, $phone, $hashed, $user_id]);
                } else {
                    $stmt_up = $pdo->prepare("UPDATE `users` SET name = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt_up->execute([$name, $email, $phone, $user_id]);
                }

                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_phone'] = $phone;
                
                // Refresh local user instance
                $user['name'] = $name;
                $user['email'] = $email;
                $user['phone'] = $phone;

                $message = 'Profile updated successfully.';
            }
        } catch (Exception $e) {
            $error = 'Profile update failed: ' . $e->getMessage();
        }
    }
}

// 2. Process Booking Cancellation
if (isset($_GET['cancel_id'])) {
    $cancel_id = intval($_GET['cancel_id']);
    
    // Verify booking belongs to this user and is cancellable
    $stmt_chk = $pdo->prepare("SELECT status, booking_id FROM `bookings` WHERE id = ? AND user_id = ?");
    $stmt_chk->execute([$cancel_id, $user_id]);
    $booking = $stmt_chk->fetch();

    if ($booking) {
        if ($booking['status'] === 'pending' || $booking['status'] === 'assigned') {
            // Cancel booking
            $stmt_cancel = $pdo->prepare("UPDATE `bookings` SET status = 'cancelled' WHERE id = ?");
            $stmt_cancel->execute([$cancel_id]);
            
            // Also cancel the linked driver-trip bidding process if any
            $stmt_trip = $pdo->prepare("UPDATE `trips` SET status = 'cancelled' WHERE booking_id = ?");
            $stmt_trip->execute([$cancel_id]);

            // Queue notification logs
            $noti_msg = "Your booking " . $booking['booking_id'] . " has been cancelled successfully as requested.";
            $stmt_not = $pdo->prepare("INSERT INTO `notifications` (type, recipient, message, gateway) VALUES ('booking_cancelled', ?, ?, 'sms')");
            $stmt_not->execute([$_SESSION['user_phone'], $noti_msg]);

            $message = "Booking " . $booking['booking_id'] . " was cancelled successfully.";
        } else {
            $error = "This booking cannot be cancelled in its current state (" . $booking['status'] . ").";
        }
    } else {
        $error = "Booking not found or access denied.";
    }
}

// 3. Fetch Booking History
$stmt_bks = $pdo->prepare("
    SELECT b.*, vc.name AS category_name, v.name AS vehicle_name, v.plate_number
    FROM `bookings` b
    INNER JOIN `vehicle_categories` vc ON b.category_id = vc.id
    LEFT JOIN `vehicles` v ON b.vehicle_id = v.id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC
");
$stmt_bks->execute([$user_id]);
$bookings = $stmt_bks->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Avinash Travels</title>
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
    <header class="bg-slate-900 border-b border-slate-800 py-3.5 px-6">
        <div class="container mx-auto flex justify-between items-center">
            <a href="../index.php" class="flex items-center space-x-3">
                <div class="bg-white/95 px-2.5 py-1 rounded-xl shadow-md border border-white/20 inline-flex items-center">
                    <img src="https://avinashtaxi.com/uploads/logo/logo.png" alt="Avinash Travels Logo" class="h-9 w-auto object-contain">
                </div>
                <span class="text-lg font-black font-poppins text-white tracking-wider hidden sm:inline-block">
                    CUSTOMER<span class="text-sky-400">PORTAL</span>
                </span>
            </a>
            
            <div class="flex items-center space-x-4">
                <span class="text-xs text-slate-400">Welcome, <strong class="text-white"><?php echo htmlspecialchars($user['name']); ?></strong></span>
                <a href="logout.php" class="px-4 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-bold rounded border border-slate-750 transition-colors">
                    Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Main Workspace -->
    <main class="flex-grow container mx-auto px-4 py-8 grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Sidebar Profile -->
        <div class="lg:col-span-4 bg-slate-900 border border-slate-800 rounded-2xl p-6 h-fit space-y-6">
            <div>
                <h3 class="text-md font-bold text-white font-poppins mb-1">Manage Profile</h3>
                <p class="text-[11px] text-slate-400">Update contact coordinates or change system password</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-rose-500/10 border border-rose-500/20 text-rose-400 p-2.5 rounded text-xs">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-2.5 rounded text-xs">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="update_profile" value="1">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Full Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-xs text-white focus:outline-none focus:border-sky-400" required>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-xs text-white focus:outline-none focus:border-sky-400" required>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Mobile Number</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-xs text-white focus:outline-none focus:border-sky-400" required>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">New Password (Optional)</label>
                    <input type="password" name="new_password" placeholder="Leave empty to keep current" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-xs text-white focus:outline-none focus:border-sky-400">
                </div>
                <button type="submit" class="w-full py-2.5 bg-gradient-to-r from-blue-600 to-sky-500 hover:from-blue-500 hover:to-sky-400 text-white font-bold rounded text-xs transition-colors shadow-md">
                    Update Profile Details
                </button>
            </form>
        </div>

        <!-- Bookings Roster -->
        <div class="lg:col-span-8 bg-slate-900 border border-slate-800 rounded-2xl p-6">
            <h3 class="text-lg font-bold text-white font-poppins mb-6">Your Bookings & Trip History</h3>
            
            <div class="space-y-4">
                <?php if (!empty($bookings)): ?>
                    <?php foreach ($bookings as $bk): ?>
                        <?php
                        $status_label = ucfirst($bk['status']);
                        $status_class = 'bg-sky-500/10 text-sky-400 border-sky-500/20';
                        if ($bk['status'] === 'completed') $status_class = 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
                        if ($bk['status'] === 'cancelled') $status_class = 'bg-rose-500/10 text-rose-400 border-rose-500/20';
                        ?>
                        <div class="bg-slate-950 border border-slate-850 rounded-xl p-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                            <div>
                                <div class="flex items-center space-x-2.5 mb-2">
                                    <span class="text-xs font-mono font-bold text-slate-350"><?php echo $bk['booking_id']; ?></span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border <?php echo $status_class; ?>">
                                        <?php echo $status_label; ?>
                                    </span>
                                </div>
                                <h4 class="text-sm font-bold text-white">
                                    <?php echo htmlspecialchars($bk['pickup_city']); ?> 
                                    <i class="fa-solid fa-arrow-right text-sky-400 text-xs mx-1"></i> 
                                    <?php echo htmlspecialchars($bk['drop_city']); ?>
                                </h4>
                                <p class="text-[11px] text-slate-400 mt-1">
                                    <i class="fa-solid fa-calendar mr-1"></i> <?php echo date('d M Y', strtotime($bk['pickup_date'])); ?> at <?php echo date('h:i A', strtotime($bk['pickup_time'])); ?>
                                </p>
                                <p class="text-[11px] text-slate-400 mt-0.5">
                                    Class: <span class="text-white"><?php echo htmlspecialchars($bk['category_name']); ?></span> | Payable: <span class="text-sky-400 font-bold">₹<?php echo number_format($bk['final_total'], 2); ?></span>
                                </p>
                            </div>
                            
                            <div class="flex gap-2 w-full md:w-auto">
                                <a href="invoice.php?id=<?php echo $bk['id']; ?>" target="_blank" class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-slate-300 hover:text-white border border-slate-750 text-xs font-bold rounded transition-colors inline-block text-center flex-grow md:flex-grow-0">
                                    <i class="fa-solid fa-file-invoice mr-1.5"></i>Invoice
                                </a>
                                
                                <?php if ($bk['status'] === 'pending' || $bk['status'] === 'assigned'): ?>
                                    <a href="dashboard.php?cancel_id=<?php echo $bk['id']; ?>" onclick="return confirm('Are you sure you want to cancel this booking?')" class="px-3.5 py-2 bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white border border-rose-500/20 text-xs font-bold rounded transition-colors inline-block text-center flex-grow md:flex-grow-0">
                                        Cancel Ride
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-slate-950 border border-slate-850 rounded-xl p-8 text-center text-slate-500 text-xs">
                        <i class="fa-solid fa-car-tunnel text-3xl text-slate-700 mb-2 block"></i>
                        No booking history found. Find your trip on the homepage and book instantly.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
