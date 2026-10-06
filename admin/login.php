<?php
// Admin Authentication Page
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in
if (is_admin_logged_in()) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        if (!$pdo && $email === 'admin@avinashtravels.com' && $password === 'admin123') {
            $_SESSION['admin_id'] = 1;
            $_SESSION['admin_email'] = 'admin@avinashtravels.com';
            $_SESSION['admin_name'] = 'Operations Admin';
            $_SESSION['admin_role'] = 'operator';

            header("Location: index.php");
            exit;
        }

        if ($pdo) {
            $stmt = $pdo->prepare("SELECT * FROM `admins` WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_role'] = $admin['role'];

                header("Location: index.php");
                exit;
            } else {
                $error = 'Invalid email or password combination.';
            }
        } else {
            $error = 'Invalid email or password combination.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Sign In - Avinash Travels</title>
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
<body class="bg-brand-navy min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md bg-brand-navyLight border border-slate-800 rounded-2xl p-8 shadow-2xl relative">
        <a href="../index.php" class="absolute top-4 left-4 text-xs font-bold text-slate-400 hover:text-white transition-colors">
            <i class="fa-solid fa-arrow-left mr-1"></i> Customer Site
        </a>
        
        <!-- Header -->
        <div class="text-center mt-6 mb-8 flex flex-col items-center">
            <div class="bg-white/95 backdrop-blur-sm px-3 py-1.5 rounded-xl shadow-md border border-white/20 inline-flex items-center mb-3">
                <img src="https://avinashtaxi.com/uploads/logo/logo.png" alt="Avinash Travels Logo" class="h-10 w-auto object-contain">
            </div>
            <span class="text-white text-lg font-black font-poppins tracking-wider">
                ADMIN<span class="text-sky-400">PANEL</span>
            </span>
            <p class="text-slate-400 text-xs mt-1">Manage routes, fleet rates, partner registrations, and invoices</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-500/10 border border-rose-500/20 text-rose-400 p-3 rounded-lg text-xs mb-6 flex items-center">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Admin Email Address</label>
                <input type="email" name="email" placeholder="admin@avinashtravels.com" class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-sky-400 placeholder:text-slate-650" required>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Password</label>
                <input type="password" name="password" placeholder="••••••••" class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-sky-400" required>
            </div>
            
            <button type="submit" class="w-full py-3 bg-gradient-to-r from-blue-600 to-sky-500 hover:from-blue-500 hover:to-sky-400 text-white font-bold rounded-lg text-sm transition-colors shadow-lg shadow-blue-500/25">
                Secure Sign In
            </button>
        </form>

        <div class="text-center mt-6 text-xs text-slate-500">
            <span>Secure administrative login portal. All activities logged.</span>
        </div>
    </div>
</body>
</html>
