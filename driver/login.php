<?php
// Driver Sign-in Portal
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in
if (is_driver_logged_in()) {
    header("Location: /driver/dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_identifier = trim($_POST['email_or_phone'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($login_identifier) || empty($password)) {
        $error = 'Please enter all credentials.';
    } else {
        // Retrieve driver record
        $stmt = $pdo->prepare("SELECT * FROM `drivers` WHERE (email = ? OR phone = ?) LIMIT 1");
        $stmt->execute([$login_identifier, $login_identifier]);
        $driver = $stmt->fetch();

        // Enforce status checks (e.g. pending, approved, suspended)
        if ($driver && password_verify($password, $driver['password_hash'])) {
            if ($driver['status'] === 'suspended') {
                $error = 'Your driver account has been suspended. Please contact the administrator.';
            } else {
                $_SESSION['driver_id'] = $driver['id'];
                $_SESSION['driver_name'] = $driver['name'];
                $_SESSION['driver_phone'] = $driver['phone'];
                $_SESSION['driver_status'] = $driver['status'];

                header("Location: /driver/dashboard.php");
                exit;
            }
        } else {
            $error = 'Invalid email/phone or password combination.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Portal - Avinash Travels</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#F8FAFC] text-slate-800 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md bg-white border border-slate-200 rounded-2xl p-8 shadow-lg relative">
        <a href="../index.php" class="absolute top-5 left-5 text-xs font-bold text-slate-500 hover:text-[#20A8D9] transition-colors flex items-center gap-1">
            <i class="fa-solid fa-arrow-left"></i> Homepage
        </a>
        
        <!-- Header -->
        <div class="text-center mt-6 mb-6 flex flex-col items-center">
            <div class="px-3 py-1.5 rounded-xl border border-slate-100 shadow-sm inline-flex items-center mb-3">
                <img src="https://avinashtaxi.com/uploads/logo/logo.png" alt="Avinash Travels Logo" class="h-10 w-auto object-contain">
            </div>
            <span class="text-slate-900 text-lg font-black font-poppins tracking-wider">
                DRIVER<span class="text-[#0099D4]">PORTAL</span>
            </span>
            <p class="text-slate-500 text-xs mt-1">Sign in to review trip assignments & manage trips</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-700 p-3 rounded-xl text-xs mb-6 flex items-center">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Email Address or Mobile</label>
                <input type="text" name="email_or_phone" placeholder="E.g. driver@avinashtravels.com" class="w-full px-3.5 py-2.5 bg-[#F8FAFC] border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:border-[#20A8D9] placeholder:text-slate-400" required>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Password</label>
                <input type="password" name="password" placeholder="••••••••" class="w-full px-3.5 py-2.5 bg-[#F8FAFC] border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:border-[#20A8D9]" required>
            </div>
            
            <button type="submit" class="w-full py-3 bg-[#EF6614] hover:bg-[#D9530F] text-white font-extrabold rounded-xl text-xs uppercase tracking-wider transition-colors shadow-sm">
                Sign In as Driver
            </button>
        </form>

        <div class="mt-6 p-4 bg-[#F4FBFF] border border-[#C8E6F5] rounded-xl text-[11px] text-slate-600 text-center">
            <span>First-time partner? Submit your application on the homepage under "Drive With Us" to generate account credentials.</span>
        </div>
    </div>
</body>
</html>
