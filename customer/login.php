<?php
// Customer Login & Registration Portal
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in
if (is_customer_logged_in()) {
    header("Location: /customer/dashboard.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'login') {
        $login_identifier = trim($_POST['email_or_phone'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($login_identifier) || empty($password)) {
            $error = 'Please enter all login credentials.';
        } else {
            // Retrieve customer record
            $stmt = $pdo->prepare("SELECT * FROM `users` WHERE (email = ? OR phone = ?) AND status = 'active' LIMIT 1");
            $stmt->execute([$login_identifier, $login_identifier]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Set sessions
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_phone'] = $user['phone'];

                header("Location: /customer/dashboard.php");
                exit;
            } else {
                $error = 'Invalid credentials or suspended account.';
            }
        }
    } elseif ($action === 'register') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($name) || empty($email) || empty($phone) || empty($password)) {
            $error = 'Please fill all registration fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Verify if exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE email = ? OR phone = ?");
            $stmt->execute([$email, $phone]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'A customer with this email or phone is already registered.';
            } else {
                $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                $stmt_ins = $pdo->prepare("INSERT INTO `users` (name, email, phone, password_hash, status) VALUES (?, ?, ?, ?, 'active')");
                $stmt_ins->execute([$name, $email, $phone, $hashed_pass]);

                $success = 'Account created successfully! You can now log in below.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Portal - Avinash Travels</title>
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
                CUSTOMER<span class="text-[#0099D4]">PORTAL</span>
            </span>
            <p class="text-slate-500 text-xs mt-1">Manage bookings, cancellation requests & receipts</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-700 p-3 rounded-xl text-xs mb-6 flex items-center">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 p-3 rounded-xl text-xs mb-6 flex items-center">
                <i class="fa-solid fa-circle-check mr-2"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Tab Headers -->
        <div class="flex border-b border-slate-200 mb-6 text-sm font-semibold">
            <button id="tab-login-btn" onclick="toggleForm('login')" class="w-1/2 text-center pb-3 text-[#0099D4] border-b-2 border-[#0099D4] font-bold focus:outline-none transition-colors">
                Customer Sign In
            </button>
            <button id="tab-register-btn" onclick="toggleForm('register')" class="w-1/2 text-center pb-3 text-slate-500 hover:text-slate-900 focus:outline-none transition-colors">
                Register Account
            </button>
        </div>

        <!-- Login Form -->
        <form id="login-form" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="login">
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Email Address or Mobile</label>
                <input type="text" name="email_or_phone" placeholder="E.g. customer@gmail.com" class="w-full px-3.5 py-2.5 bg-[#F8FAFC] border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:border-[#20A8D9] placeholder:text-slate-400" required>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Password</label>
                <input type="password" name="password" placeholder="••••••••" class="w-full px-3.5 py-2.5 bg-[#F8FAFC] border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:border-[#20A8D9]" required>
            </div>
            <button type="submit" class="w-full py-3 bg-[#EF6614] hover:bg-[#D9530F] text-white font-extrabold rounded-xl text-xs uppercase tracking-wider transition-colors shadow-sm">
                Sign In to Dashboard
            </button>
        </form>

        <!-- Register Form -->
        <form id="register-form" method="POST" class="hidden space-y-4">
            <input type="hidden" name="action" value="register">
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Full Name</label>
                <input type="text" name="name" placeholder="E.g. Rahul Sharma" class="w-full px-3.5 py-2.5 bg-[#F8FAFC] border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:border-[#20A8D9]" required>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Email Address</label>
                <input type="email" name="email" placeholder="customer@gmail.com" class="w-full px-3.5 py-2.5 bg-[#F8FAFC] border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:border-[#20A8D9]" required>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Mobile Number</label>
                <input type="tel" name="phone" placeholder="10 digit phone number" class="w-full px-3.5 py-2.5 bg-[#F8FAFC] border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:border-[#20A8D9]" required>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Password</label>
                <input type="password" name="password" placeholder="••••••••" class="w-full px-3.5 py-2.5 bg-[#F8FAFC] border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:border-[#20A8D9]" required>
            </div>
            <button type="submit" class="w-full py-3 bg-[#EF6614] hover:bg-[#D9530F] text-white font-extrabold rounded-xl text-xs uppercase tracking-wider transition-colors shadow-sm">
                Create Customer Account
            </button>
        </form>

        <div class="text-center mt-6 text-xs text-slate-500">
            <span>Safe & Verified Cab Rides across Central India.</span>
        </div>
    </div>

    <script>
        function toggleForm(formType) {
            const loginForm = document.getElementById('login-form');
            const regForm = document.getElementById('register-form');
            const loginBtn = document.getElementById('tab-login-btn');
            const regBtn = document.getElementById('tab-register-btn');

            if (formType === 'login') {
                loginForm.classList.remove('hidden');
                regForm.classList.add('hidden');
                loginBtn.className = "w-1/2 text-center pb-3 text-[#0099D4] border-b-2 border-[#0099D4] font-bold focus:outline-none transition-colors";
                regBtn.className = "w-1/2 text-center pb-3 text-slate-500 hover:text-slate-900 focus:outline-none transition-colors";
            } else {
                regForm.classList.remove('hidden');
                loginForm.classList.add('hidden');
                regBtn.className = "w-1/2 text-center pb-3 text-[#0099D4] border-b-2 border-[#0099D4] font-bold focus:outline-none transition-colors";
                loginBtn.className = "w-1/2 text-center pb-3 text-slate-500 hover:text-slate-900 focus:outline-none transition-colors";
            }
        }
    </script>
</body>
</html>
