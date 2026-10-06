<?php
// Admin Sidebar Menu Template - Full Enterprise Edition
$current_page = basename($_SERVER['PHP_SELF'] ?? '');
?>
<!-- Sidebar Container -->
<aside id="admin-sidebar" class="fixed inset-y-0 left-0 transform -translate-x-full lg:relative lg:translate-x-0 lg:flex flex-col w-64 bg-slate-900 border-r border-slate-800 z-45 transition-transform duration-300 overflow-y-auto shrink-0 py-6 px-4">
    <div class="space-y-6">
        <!-- Admin Profile Badge -->
        <div class="px-3.5 py-3 bg-gradient-to-r from-sky-500/10 to-blue-600/10 border border-sky-500/20 rounded-xl flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <div>
                    <span class="block text-xs font-bold text-white"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Super Admin'); ?></span>
                    <span class="text-[10px] text-sky-400 font-mono block uppercase">Administrator</span>
                </div>
            </div>
            <a href="logout.php" class="text-slate-400 hover:text-rose-400 text-xs p-1" title="Sign Out">
                <i class="fa-solid fa-power-off"></i>
            </a>
        </div>

        <!-- Main Panel Section -->
        <div>
            <span class="block text-[9px] font-extrabold text-slate-500 uppercase tracking-widest px-3 mb-2">Trip Operations</span>
            <nav class="space-y-1">
                <a href="index.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-xs font-semibold <?php echo ($current_page === 'index.php') ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-350 hover:bg-slate-800 hover:text-white'; ?> transition-colors">
                    <i class="fa-solid fa-chart-line w-4"></i>
                    <span>Operations Dashboard</span>
                </a>
                <a href="bookings.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-xs font-semibold <?php echo ($current_page === 'bookings.php') ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-350 hover:bg-slate-800 hover:text-white'; ?> transition-colors">
                    <i class="fa-solid fa-receipt w-4"></i>
                    <span>Manage Bookings</span>
                </a>
                <a href="payments.php" class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-semibold <?php echo ($current_page === 'payments.php') ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-350 hover:bg-slate-800 hover:text-white'; ?> transition-colors">
                    <div class="flex items-center space-x-3">
                        <i class="fa-solid fa-file-invoice-dollar w-4"></i>
                        <span>Payment Approvals</span>
                    </div>
                    <?php
                    $pending_cnt = 0;
                    try {
                        if (isset($pdo)) {
                            $pending_cnt = (int)$pdo->query("SELECT COUNT(*) FROM `bookings` WHERE `payment_status` = 'under_review'")->fetchColumn();
                        }
                    } catch(Exception $e){}
                    if ($pending_cnt > 0):
                    ?>
                    <span class="px-1.5 py-0.5 text-[10px] font-extrabold rounded-full bg-amber-400 text-slate-950 animate-pulse"><?php echo $pending_cnt; ?></span>
                    <?php endif; ?>
                </a>
                <a href="bids.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-xs font-semibold <?php echo ($current_page === 'bids.php') ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-350 hover:bg-slate-800 hover:text-white'; ?> transition-colors">
                    <i class="fa-solid fa-gavel w-4"></i>
                    <span>Trip Bids Dispatch</span>
                </a>
                <a href="enquiries.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-xs font-semibold <?php echo ($current_page === 'enquiries.php') ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-350 hover:bg-slate-800 hover:text-white'; ?> transition-colors">
                    <i class="fa-solid fa-envelope-open-text w-4"></i>
                    <span>Customer Enquiries</span>
                </a>
            </nav>
        </div>

        <!-- Fleet & Route Network -->
        <div>
            <span class="block text-[9px] font-extrabold text-slate-500 uppercase tracking-widest px-3 mb-2">Fleet & Locations</span>
            <nav class="space-y-1">
                <a href="vehicles.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-xs font-semibold <?php echo ($current_page === 'vehicles.php') ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-350 hover:bg-slate-800 hover:text-white'; ?> transition-colors">
                    <i class="fa-solid fa-car w-4"></i>
                    <span>Vehicle Fleet & Classes</span>
                </a>
                <a href="routes.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-xs font-semibold <?php echo ($current_page === 'routes.php') ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-350 hover:bg-slate-800 hover:text-white'; ?> transition-colors">
                    <i class="fa-solid fa-map-location-dot w-4"></i>
                    <span>Routes & CG Locations</span>
                </a>
                <a href="drivers.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-xs font-semibold <?php echo ($current_page === 'drivers.php') ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-350 hover:bg-slate-800 hover:text-white'; ?> transition-colors">
                    <i class="fa-solid fa-user-shield w-4"></i>
                    <span>Driver Roster</span>
                </a>
                <a href="partners.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-xs font-semibold <?php echo ($current_page === 'partners.php') ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-350 hover:bg-slate-800 hover:text-white'; ?> transition-colors">
                    <i class="fa-solid fa-address-card w-4"></i>
                    <span>Attach Cab Partners</span>
                </a>
            </nav>
        </div>

        <!-- Pricing & Promotional Deals -->
        <div>
            <span class="block text-[9px] font-extrabold text-slate-500 uppercase tracking-widest px-3 mb-2">Pricing & Revenue</span>
            <nav class="space-y-1">
                <a href="pricing.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-xs font-semibold <?php echo ($current_page === 'pricing.php') ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-350 hover:bg-slate-800 hover:text-white'; ?> transition-colors">
                    <i class="fa-solid fa-calculator w-4"></i>
                    <span>Fare & Pricing Engine</span>
                </a>
                <a href="offers.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-xs font-semibold <?php echo ($current_page === 'offers.php') ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-350 hover:bg-slate-800 hover:text-white'; ?> transition-colors">
                    <i class="fa-solid fa-tags w-4"></i>
                    <span>Promo Codes & Deals</span>
                </a>
            </nav>
        </div>

        <!-- Content & CMS Control -->
        <div>
            <span class="block text-[9px] font-extrabold text-slate-500 uppercase tracking-widest px-3 mb-2">Website & CMS</span>
            <nav class="space-y-1">
                <a href="cms.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-xs font-semibold <?php echo ($current_page === 'cms.php') ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-350 hover:bg-slate-800 hover:text-white'; ?> transition-colors">
                    <i class="fa-solid fa-sliders w-4"></i>
                    <span>CMS Metrics & About</span>
                </a>
                <a href="services.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-xs font-semibold <?php echo ($current_page === 'services.php') ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-350 hover:bg-slate-800 hover:text-white'; ?> transition-colors">
                    <i class="fa-solid fa-briefcase w-4"></i>
                    <span>Services Showcase</span>
                </a>
                <a href="testimonials.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-xs font-semibold <?php echo ($current_page === 'testimonials.php') ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-350 hover:bg-slate-800 hover:text-white'; ?> transition-colors">
                    <i class="fa-solid fa-star w-4"></i>
                    <span>Customer Reviews</span>
                </a>
                <a href="gallery.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-xs font-semibold <?php echo ($current_page === 'gallery.php') ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-350 hover:bg-slate-800 hover:text-white'; ?> transition-colors">
                    <i class="fa-solid fa-images w-4"></i>
                    <span>Fleet Photo Gallery</span>
                </a>
                <a href="faqs.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-xs font-semibold <?php echo ($current_page === 'faqs.php') ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-350 hover:bg-slate-800 hover:text-white'; ?> transition-colors">
                    <i class="fa-solid fa-circle-question w-4"></i>
                    <span>FAQs Accordion</span>
                </a>
                <a href="customers.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-xs font-semibold <?php echo ($current_page === 'customers.php') ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-350 hover:bg-slate-800 hover:text-white'; ?> transition-colors">
                    <i class="fa-solid fa-users w-4"></i>
                    <span>Registered Customers</span>
                </a>
            </nav>
        </div>

        <!-- Configuration Settings -->
        <div>
            <span class="block text-[9px] font-extrabold text-slate-500 uppercase tracking-widest px-3 mb-2">Global Settings</span>
            <nav class="space-y-1">
                <a href="settings.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-xs font-semibold <?php echo ($current_page === 'settings.php') ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-350 hover:bg-slate-800 hover:text-white'; ?> transition-colors">
                    <i class="fa-solid fa-gear w-4"></i>
                    <span>Business & API Settings</span>
                </a>
                <a href="../index.php" target="_blank" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-xs font-semibold text-sky-400 hover:bg-slate-800 transition-colors">
                    <i class="fa-solid fa-arrow-up-right-from-square w-4"></i>
                    <span>View Public Website</span>
                </a>
            </nav>
        </div>
    </div>
</aside>

<!-- Main Page Work Area Wrapper Open -->
<div class="flex-grow overflow-y-auto p-6 md:p-8 space-y-6">
