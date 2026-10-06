<?php
// Admin Business Configuration & System API Settings Module
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Enforce admin login
require_admin();

$message = '';
$error = '';

// Helper to save setting safely
function save_meta_setting($key, $value) {
    global $pdo;
    if (!$pdo) return;
    try {
        $stmt = $pdo->prepare("INSERT INTO `settings` (meta_key, meta_value) VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)");
        $stmt->execute([$key, $value]);
    } catch (Exception $e) {}
}

// 1. Process Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_business_settings') {
        save_meta_setting('company_name', trim($_POST['company_name'] ?? 'Avinash Travels'));
        save_meta_setting('company_tagline', trim($_POST['company_tagline'] ?? ''));
        save_meta_setting('company_founder', trim($_POST['company_founder'] ?? 'Ravi Gupta'));
        save_meta_setting('company_established_year', trim($_POST['company_established_year'] ?? '2013'));
        
        save_meta_setting('company_phone', trim($_POST['company_phone'] ?? '8120868818'));
        save_meta_setting('company_whatsapp', trim($_POST['company_whatsapp'] ?? '918120868818'));
        save_meta_setting('company_email', trim($_POST['company_email'] ?? 'info@avinashtravels.com'));
        save_meta_setting('company_support_hours', trim($_POST['company_support_hours'] ?? '24x7 Round the Clock'));
        
        save_meta_setting('company_address', trim($_POST['company_address'] ?? ''));
        save_meta_setting('google_maps_iframe', trim($_POST['google_maps_iframe'] ?? ''));
        
        save_meta_setting('social_facebook', trim($_POST['social_facebook'] ?? '#'));
        save_meta_setting('social_instagram', trim($_POST['social_instagram'] ?? '#'));
        save_meta_setting('social_twitter', trim($_POST['social_twitter'] ?? '#'));
        save_meta_setting('social_youtube', trim($_POST['social_youtube'] ?? '#'));

        save_meta_setting('booking_advance_hours', intval($_POST['booking_advance_hours'] ?? 12));
        save_meta_setting('booking_cancellation_policy', trim($_POST['booking_cancellation_policy'] ?? 'Free cancellations up to 1 hour before pickup time with zero deduction.'));

        $message = "Business profile & contact settings updated successfully.";
    } elseif ($action === 'save_gateway_settings') {
        save_meta_setting('payment_gateway_mode', trim($_POST['payment_gateway_mode'] ?? 'test'));
        save_meta_setting('razorpay_key_id', trim($_POST['razorpay_key_id'] ?? ''));
        save_meta_setting('razorpay_key_secret', trim($_POST['razorpay_key_secret'] ?? ''));
        
        $upi_id = trim($_POST['upi_id'] ?? '8120868818@ybl');
        save_meta_setting('upi_id', $upi_id);
        save_meta_setting('payment_upi_id', $upi_id);

        $qr_mode = in_array($_POST['payment_qr_mode'] ?? '', ['dynamic', 'uploaded']) ? $_POST['payment_qr_mode'] : 'dynamic';
        save_meta_setting('payment_qr_mode', $qr_mode);

        save_meta_setting('bank_name', trim($_POST['bank_name'] ?? 'State Bank of India (SBI)'));
        save_meta_setting('bank_account_name', trim($_POST['bank_account_name'] ?? 'Avinash Tour and Travels'));
        save_meta_setting('bank_account_number', trim($_POST['bank_account_number'] ?? '38920194829'));
        save_meta_setting('bank_ifsc', trim($_POST['bank_ifsc'] ?? 'SBIN0000461'));
        save_meta_setting('bank_branch', trim($_POST['bank_branch'] ?? 'Raipur Main Branch'));

        // Handle QR Code Image Upload
        if (isset($_FILES['payment_qr_image']) && $_FILES['payment_qr_image']['error'] === UPLOAD_ERR_OK) {
            $f_tmp = $_FILES['payment_qr_image']['tmp_name'];
            $f_name = $_FILES['payment_qr_image']['name'];
            $ext = strtolower(pathinfo($f_name, PATHINFO_EXTENSION));

            if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg'])) {
                $dest_dir = __DIR__ . '/../uploads/qr/';
                if (!file_exists($dest_dir)) {
                    mkdir($dest_dir, 0777, true);
                }
                $dest_filename = 'merchant_qr_' . time() . '.' . $ext;
                if (move_uploaded_file($f_tmp, $dest_dir . $dest_filename)) {
                    save_meta_setting('payment_qr_image', 'uploads/qr/' . $dest_filename);
                } else {
                    $error = "Failed to upload QR code image to server.";
                }
            } else {
                $error = "Invalid QR file format. Please upload PNG, JPG, WEBP, or SVG.";
            }
        } elseif (isset($_POST['remove_qr_image']) && $_POST['remove_qr_image'] === '1') {
            save_meta_setting('payment_qr_image', '');
        }

        if (empty($error)) {
            $message = "Payment gateway, UPI ID, and QR code settings saved successfully! Changes are live on the customer checkout page.";
        }
    }
}

// 2. Fetch all current settings
$c_name = get_setting('company_name', 'Avinash Travels');
$c_tagline = get_setting('company_tagline', '90,000+ Rides Completed. One Journey at a Time.');
$c_founder = get_setting('company_founder', 'Ravi Gupta');
$c_year = get_setting('company_established_year', '2013');

$c_phone = get_setting('company_phone', '8120868818');
$c_whatsapp = get_setting('company_whatsapp', '918120868818');
$c_email = get_setting('company_email', 'info@avinashtravels.com');
$c_hours = get_setting('company_support_hours', '24x7 Round the Clock');

$c_address = get_setting('company_address', 'Cab Service, Alakh DHaM Face1, Maharishi chowk, Vikas Nagar, Mangla, Bilaspur, Chhattisgarh 495001');
$c_maps = get_setting('google_maps_iframe', 'https://maps.google.com/maps?q=Avinash+Tour+and+Travels,+Alakh+Dham+Face1,+Maharishi+chowk,+Vikas+Nagar,+Mangla,+Bilaspur,+Chhattisgarh+495001&t=&z=15&ie=UTF8&iwloc=&output=embed');

$c_fb = get_setting('social_facebook', '#');
$c_ig = get_setting('social_instagram', '#');
$c_tw = get_setting('social_twitter', '#');
$c_yt = get_setting('social_youtube', '#');

$c_adv_hours = get_setting('booking_advance_hours', '12');
$c_cancel_policy = get_setting('booking_cancellation_policy', 'Free cancellations up to 1 hour before pickup time with zero deduction.');

$c_gw_mode = get_setting('payment_gateway_mode', 'test');
$c_rzp_key = get_setting('razorpay_key_id', 'rzp_test_samplekey123');
$c_rzp_sec = get_setting('razorpay_key_secret', '••••••••••••••••');
$c_upi = get_setting('upi_id', get_setting('payment_upi_id', '8120868818@ybl'));
$c_qr_img = get_setting('payment_qr_image', '');
$c_qr_mode = get_setting('payment_qr_mode', 'dynamic');
$c_bank_name = get_setting('bank_name', 'State Bank of India (SBI)');
$c_acc_name = get_setting('bank_account_name', 'Avinash Tour and Travels');
$c_acc_num = get_setting('bank_account_number', '38920194829');
$c_ifsc = get_setting('bank_ifsc', 'SBIN0000461');
$c_branch = get_setting('bank_branch', 'Raipur Main Branch');

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-[#20A8D9]/15 text-[#20A8D9] border border-[#20A8D9]/30 uppercase tracking-wider">
                CORE SYSTEM CONFIGURATION
            </span>
            <h1 class="text-2xl font-black font-poppins text-white mt-1">Business & System Settings</h1>
            <p class="text-xs text-slate-400 mt-1">Manage official company contact info, address coordinates, social handles, and payment gateway keys</p>
        </div>
        <a href="../index.php" target="_blank" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-sky-400 text-xs font-bold rounded-xl border border-slate-700 transition-colors flex items-center gap-1.5">
            <i class="fa-solid fa-eye"></i> View Live Site
        </a>
    </div>

    <?php if ($message): ?>
        <div class="bg-emerald-500/10 border border-emerald-500/25 text-emerald-400 p-4 rounded-xl text-xs flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-base"></i>
            <span class="font-semibold"><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- Left: Company Identity & Contact (7 Columns) -->
        <div class="lg:col-span-7 bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl space-y-6">
            <div class="flex items-center gap-2 pb-4 border-b border-slate-800">
                <div class="w-8 h-8 rounded-lg bg-sky-500/20 text-sky-400 flex items-center justify-center font-bold text-sm">
                    <i class="fa-solid fa-building"></i>
                </div>
                <div>
                    <h3 class="text-sm font-extrabold text-white uppercase tracking-wider">Business Identity & Helplines</h3>
                    <p class="text-[11px] text-slate-400">Public profile details displayed across the website and dispatch vouchers</p>
                </div>
            </div>

            <form method="POST" class="space-y-4 text-xs">
                <input type="hidden" name="action" value="save_business_settings">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Company Trade Name</label>
                        <input type="text" name="company_name" value="<?php echo htmlspecialchars($c_name); ?>" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white font-bold focus:outline-none focus:border-sky-400" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Brand Tagline</label>
                        <input type="text" name="company_tagline" value="<?php echo htmlspecialchars($c_tagline); ?>" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-sky-400">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Founder / CEO</label>
                        <input type="text" name="company_founder" value="<?php echo htmlspecialchars($c_founder); ?>" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-sky-400">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Established Year</label>
                        <input type="text" name="company_established_year" value="<?php echo htmlspecialchars($c_year); ?>" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white font-mono focus:outline-none focus:border-sky-400">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-800/80">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">24x7 Helpline Mobile</label>
                        <input type="text" name="company_phone" value="<?php echo htmlspecialchars($c_phone); ?>" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white font-bold font-mono focus:outline-none focus:border-sky-400" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">WhatsApp Number (with country code)</label>
                        <input type="text" name="company_whatsapp" value="<?php echo htmlspecialchars($c_whatsapp); ?>" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white font-bold font-mono focus:outline-none focus:border-sky-400" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Support Email</label>
                        <input type="email" name="company_email" value="<?php echo htmlspecialchars($c_email); ?>" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-sky-400" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Operating Hours</label>
                        <input type="text" name="company_support_hours" value="<?php echo htmlspecialchars($c_hours); ?>" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-sky-400">
                    </div>
                </div>

                <div class="pt-2 border-t border-slate-800/80">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Physical Office Address</label>
                    <textarea name="company_address" rows="2" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-sky-400"><?php echo htmlspecialchars($c_address); ?></textarea>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Google Maps Embed URL</label>
                    <input type="text" name="google_maps_iframe" value="<?php echo htmlspecialchars($c_maps); ?>" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white text-[11px] focus:outline-none focus:border-sky-400">
                </div>

                <div class="pt-2 border-t border-slate-800/80">
                    <span class="block text-[10px] font-bold text-sky-400 uppercase tracking-wider mb-2">
                        <i class="fa-solid fa-clock-rotate-left mr-1"></i> Advance Booking Rules & Lead Time
                    </span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Min Advance Notice (Hours)</label>
                            <input type="number" name="booking_advance_hours" value="<?php echo htmlspecialchars($c_adv_hours); ?>" min="1" max="72" class="w-full px-3 py-1.5 bg-slate-950 border border-slate-800 rounded-lg text-white font-mono text-xs focus:outline-none focus:border-sky-400">
                            <span class="text-[9px] text-slate-500">Currently enforced to 12 hrs (starts next day).</span>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Cancellation & Refund Policy</label>
                            <input type="text" name="booking_cancellation_policy" value="<?php echo htmlspecialchars($c_cancel_policy); ?>" class="w-full px-3 py-1.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:outline-none focus:border-sky-400">
                            <span class="text-[9px] text-slate-500">Displayed on customer checkout modal.</span>
                        </div>
                    </div>
                </div>

                <div class="pt-2 border-t border-slate-800/80">
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Social Channels</span>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <input type="text" name="social_facebook" value="<?php echo htmlspecialchars($c_fb); ?>" placeholder="Facebook URL" class="px-2.5 py-1.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs">
                        <input type="text" name="social_instagram" value="<?php echo htmlspecialchars($c_ig); ?>" placeholder="Instagram URL" class="px-2.5 py-1.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs">
                        <input type="text" name="social_twitter" value="<?php echo htmlspecialchars($c_tw); ?>" placeholder="Twitter/X URL" class="px-2.5 py-1.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs">
                        <input type="text" name="social_youtube" value="<?php echo htmlspecialchars($c_yt); ?>" placeholder="YouTube URL" class="px-2.5 py-1.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs">
                    </div>
                </div>

                <button type="submit" class="w-full py-3 bg-[#EF6614] hover:bg-[#D9530F] text-white font-extrabold rounded-xl text-xs uppercase tracking-wider shadow-lg shadow-orange-500/20 transition-all mt-4">
                    <i class="fa-solid fa-floppy-disk mr-1.5"></i> Save Business Coordinates
                </button>
            </form>
        </div>

        <!-- Right: Online Payments, UPI ID & QR Code Upload (5 Columns) -->
        <div class="lg:col-span-5 bg-slate-900 border-2 border-sky-500/40 p-6 rounded-2xl shadow-xl space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                <div class="flex items-center gap-2">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 text-slate-950 flex items-center justify-center font-bold text-base shadow-sm">
                        <i class="fa-solid fa-qrcode"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-extrabold text-white uppercase tracking-wider">UPI ID & QR Code Settings</h3>
                        <p class="text-[11px] text-sky-400 font-semibold">Reflects live on Customer Checkout Modal</p>
                    </div>
                </div>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 uppercase">
                    Live Frontend Sync
                </span>
            </div>

            <form method="POST" enctype="multipart/form-data" class="space-y-4 text-xs">
                <input type="hidden" name="action" value="save_gateway_settings">

                <!-- 1. UPI ID Configuration -->
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2">
                    <label class="block text-[10px] font-bold text-amber-400 uppercase tracking-wider">
                        <i class="fa-solid fa-wallet mr-1"></i> Official Business UPI ID (VPA) *
                    </label>
                    <input type="text" name="upi_id" value="<?php echo htmlspecialchars($c_upi); ?>" placeholder="8120868818@ybl or company@upi" class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white font-mono text-sm font-bold focus:outline-none focus:border-amber-400" required>
                    <p class="text-[10px] text-slate-400">
                        This UPI ID is displayed on customer booking checkout with an instant 1-click copy button.
                    </p>
                </div>

                <!-- 2. QR Code Mode Selection -->
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2.5">
                    <label class="block text-[10px] font-bold text-slate-300 uppercase tracking-wider">
                        Choose QR Code Display Mode:
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <label class="flex items-start gap-2.5 p-2.5 bg-slate-900 rounded-xl border border-slate-800 hover:border-slate-700 cursor-pointer">
                            <input type="radio" name="payment_qr_mode" value="dynamic" <?php echo ($c_qr_mode !== 'uploaded') ? 'checked' : ''; ?> class="mt-0.5 text-sky-500">
                            <div>
                                <span class="block text-white font-bold text-xs">Auto Dynamic QR</span>
                                <span class="block text-[10px] text-slate-400">Encodes live ride fare into QR scan</span>
                            </div>
                        </label>
                        <label class="flex items-start gap-2.5 p-2.5 bg-slate-900 rounded-xl border border-slate-800 hover:border-slate-700 cursor-pointer">
                            <input type="radio" name="payment_qr_mode" value="uploaded" <?php echo ($c_qr_mode === 'uploaded') ? 'checked' : ''; ?> class="mt-0.5 text-sky-500">
                            <div>
                                <span class="block text-white font-bold text-xs">Custom QR Image</span>
                                <span class="block text-[10px] text-slate-400">Displays your uploaded scanner</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- 3. Upload Custom QR Code Image Box -->
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="block text-[10px] font-bold text-slate-300 uppercase tracking-wider">
                            Upload Official QR Code Scanner (PhonePe / GPay / Paytm)
                        </label>
                        <span class="text-[10px] text-slate-500">PNG, JPG, WEBP, SVG</span>
                    </div>

                    <!-- Current QR Code Preview -->
                    <?php if (!empty($c_qr_img) && file_exists(__DIR__ . '/../' . $c_qr_img)): ?>
                        <div class="bg-slate-900 p-3 rounded-xl border border-slate-800 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <img src="../<?php echo htmlspecialchars($c_qr_img); ?>?v=<?php echo time(); ?>" alt="Merchant QR" class="w-16 h-16 object-contain rounded-lg border border-slate-700 bg-white p-1">
                                <div>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 block mb-1">
                                        ✓ Uploaded QR Active
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-mono"><?php echo basename($c_qr_img); ?></span>
                                </div>
                            </div>
                            <label class="text-[11px] text-rose-400 hover:text-rose-300 flex items-center gap-1 cursor-pointer">
                                <input type="checkbox" name="remove_qr_image" value="1" class="text-rose-500">
                                <span>Remove Image</span>
                            </label>
                        </div>
                    <?php endif; ?>

                    <!-- Upload Input Dropzone -->
                    <div class="relative border-2 border-dashed border-slate-700 hover:border-sky-400 rounded-xl p-3.5 text-center cursor-pointer transition-colors bg-slate-900/50" onclick="document.getElementById('admin_payment_qr_file').click()">
                        <input type="file" name="payment_qr_image" id="admin_payment_qr_file" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="hidden" onchange="handleAdminQrPreview(event)">
                        <div id="admin-qr-dropzone-text" class="space-y-1">
                            <i class="fa-solid fa-cloud-arrow-up text-sky-400 text-xl"></i>
                            <p class="text-xs font-bold text-slate-300">Click to select new QR Code photo</p>
                            <p class="text-[10px] text-slate-500">Upload your QR scan standee or mobile screenshot</p>
                        </div>
                        <div id="admin-qr-preview-box" class="hidden flex items-center justify-center gap-2 pt-1">
                            <img id="admin-qr-preview-img" src="" alt="Selected QR" class="w-16 h-16 object-contain rounded bg-white p-1 border border-slate-600">
                            <span class="text-xs font-bold text-emerald-400" id="admin-qr-filename">-</span>
                        </div>
                    </div>
                </div>

                <!-- 4. Bank Account Details -->
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-3">
                    <label class="block text-[10px] font-bold text-slate-300 uppercase tracking-wider">
                        <i class="fa-solid fa-building-columns text-sky-400 mr-1"></i> Direct Bank Transfer Coordinates (Optional)
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <span class="block text-[10px] text-slate-400 mb-1">Bank Name:</span>
                            <input type="text" name="bank_name" value="<?php echo htmlspecialchars($c_bank_name); ?>" placeholder="State Bank of India" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-lg text-white text-xs focus:outline-none focus:border-sky-400">
                        </div>
                        <div>
                            <span class="block text-[10px] text-slate-400 mb-1">Account Holder Name:</span>
                            <input type="text" name="bank_account_name" value="<?php echo htmlspecialchars($c_acc_name); ?>" placeholder="Avinash Tour and Travels" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-lg text-white text-xs focus:outline-none focus:border-sky-400">
                        </div>
                        <div>
                            <span class="block text-[10px] text-slate-400 mb-1">Account Number:</span>
                            <input type="text" name="bank_account_number" value="<?php echo htmlspecialchars($c_acc_num); ?>" placeholder="38920194829" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-lg text-white font-mono text-xs focus:outline-none focus:border-sky-400">
                        </div>
                        <div>
                            <span class="block text-[10px] text-slate-400 mb-1">IFSC Code:</span>
                            <input type="text" name="bank_ifsc" value="<?php echo htmlspecialchars($c_ifsc); ?>" placeholder="SBIN0000461" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-lg text-white font-mono text-xs uppercase focus:outline-none focus:border-sky-400">
                        </div>
                    </div>
                </div>

                <!-- 5. Optional Razorpay Keys -->
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-3">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                        Online Cards & Netbanking Gateway (Razorpay)
                    </label>
                    <div class="grid grid-cols-2 gap-2 mb-2">
                        <label class="flex items-center gap-2 p-2 bg-slate-900 rounded-lg border border-slate-800 cursor-pointer">
                            <input type="radio" name="payment_gateway_mode" value="test" <?php echo ($c_gw_mode === 'test') ? 'checked' : ''; ?> class="text-sky-500">
                            <span class="text-slate-300 font-semibold text-xs">Sandbox</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 bg-slate-900 rounded-lg border border-slate-800 cursor-pointer">
                            <input type="radio" name="payment_gateway_mode" value="live" <?php echo ($c_gw_mode === 'live') ? 'checked' : ''; ?> class="text-emerald-500">
                            <span class="text-emerald-400 font-bold text-xs">Live</span>
                        </label>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <span class="block text-[10px] text-slate-400 mb-1">Key ID:</span>
                            <input type="text" name="razorpay_key_id" value="<?php echo htmlspecialchars($c_rzp_key); ?>" placeholder="rzp_test_..." class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-lg text-white font-mono text-xs focus:outline-none focus:border-sky-400">
                        </div>
                        <div>
                            <span class="block text-[10px] text-slate-400 mb-1">Key Secret:</span>
                            <input type="password" name="razorpay_key_secret" value="<?php echo htmlspecialchars($c_rzp_sec); ?>" placeholder="Secret" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-lg text-white font-mono text-xs focus:outline-none focus:border-sky-400">
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full py-3 bg-gradient-to-r from-emerald-600 via-teal-500 to-sky-500 hover:from-emerald-500 hover:to-sky-400 text-white font-black rounded-xl text-xs uppercase tracking-wider shadow-lg shadow-emerald-500/20 transition-all">
                    <i class="fa-solid fa-cloud-arrow-up mr-1.5"></i> Save & Reflect on Frontend Checkout
                </button>
            </form>

            <div class="p-4 bg-slate-950 rounded-xl border border-slate-800 space-y-2 text-xs">
                <span class="font-bold text-slate-300 block"><i class="fa-solid fa-circle-info text-sky-400 mr-1.5"></i>Database Connection Status:</span>
                <p class="text-slate-400 text-[11px]">
                    <?php if ($pdo): ?>
                        <span class="text-emerald-400 font-bold"><i class="fa-solid fa-circle-check"></i> Connected to MySQL Database (Live PDO Active)</span>
                    <?php else: ?>
                        <span class="text-amber-400 font-bold"><i class="fa-solid fa-triangle-exclamation"></i> Offline Cache Mode Active</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

    </div>
</div>

<script>
function handleAdminQrPreview(event) {
    const file = event.target.files && event.target.files[0];
    if (!file) return;

    const previewBox = document.getElementById('admin-qr-preview-box');
    const previewImg = document.getElementById('admin-qr-preview-img');
    const dropzoneText = document.getElementById('admin-qr-dropzone-text');
    const filenameSpan = document.getElementById('admin-qr-filename');

    filenameSpan.innerText = file.name;
    const reader = new FileReader();
    reader.onload = function(e) {
        previewImg.src = e.target.result;
        dropzoneText.classList.add('hidden');
        previewBox.classList.remove('hidden');
    };
    reader.readAsDataURL(file);
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

