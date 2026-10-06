<?php
// Admin Homepage CMS & Live Metrics Management Module
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Enforce admin login
require_admin();

$message = '';
$error = '';

// Helper to save setting safely
function save_cms_setting($key, $value) {
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

    if ($action === 'save_cms_content') {
        save_cms_setting('hero_badge_text', trim($_POST['hero_badge_text'] ?? 'SERVICES ACROSS CHHATTISGARH & CENTRAL INDIA'));
        save_cms_setting('hero_headline', trim($_POST['hero_headline'] ?? 'Reliable & Safe Cabs at Affordable Prices'));
        save_cms_setting('hero_subheading', trim($_POST['hero_subheading'] ?? ''));

        save_cms_setting('stat_happy_customers', trim($_POST['stat_happy_customers'] ?? '85K+'));
        save_cms_setting('stat_trips_completed', trim($_POST['stat_trips_completed'] ?? '90,000+'));
        save_cms_setting('stat_active_vehicles', trim($_POST['stat_active_vehicles'] ?? '100+'));
        save_cms_setting('stat_verified_drivers', trim($_POST['stat_verified_drivers'] ?? '24/7 Support'));

        save_cms_setting('about_story', trim($_POST['about_story'] ?? ''));
        save_cms_setting('about_commitment', trim($_POST['about_commitment'] ?? ''));
        save_cms_setting('about_mission', trim($_POST['about_mission'] ?? ''));
        save_cms_setting('about_vision', trim($_POST['about_vision'] ?? ''));

        save_cms_setting('seo_meta_title', trim($_POST['seo_meta_title'] ?? ''));
        save_cms_setting('seo_meta_description', trim($_POST['seo_meta_description'] ?? ''));

        $message = "Homepage CMS text, metrics, and about narrative updated successfully.";
    }
}

// 2. Fetch current values
$hero_badge = get_setting('hero_badge_text', 'SERVICES ACROSS CHHATTISGARH & CENTRAL INDIA');
$hero_headline = get_setting('hero_headline', 'Reliable & Safe Cabs at Affordable Prices');
$hero_subheading = get_setting('hero_subheading', 'Verified Chauffeurs • Fixed One-Way Rates • Clean AC Sedans & Ertiga Fleets • 24x7 Customer Support');

$stat_happy = get_setting('stat_happy_customers', '85K+');
$stat_trips = get_setting('stat_trips_completed', '90,000+');
$stat_fleet = get_setting('stat_active_vehicles', '100+');
$stat_support = get_setting('stat_verified_drivers', '24/7 Support');

$about_story = get_setting('about_story', 'Founded in 2013 by Ravi Gupta, our taxi service began with a simple vision — to provide safe, reliable, comfortable, and hassle-free transportation to every customer. Over the years, we have grown through the trust and support of our customers. Today, we are proud to have completed 90,000+ taxi rides, making every journey a valuable part of our story.');
$about_commitment = get_setting('about_commitment', 'For us, every ride is more than just transportation. It is about punctuality, safety, comfort, and customer satisfaction. We strive to make every journey smooth and convenient, whether it is a local trip, airport transfer, outstation journey, business travel, or a family ride.');
$about_mission = get_setting('about_mission', 'To make every journey safe, comfortable, punctual, and memorable through professional service and customer-first values.');
$about_vision = get_setting('about_vision', 'To become a trusted and preferred mobility partner, delivering dependable transportation services while building long-lasting relationships with our customers.');

$seo_title = get_setting('seo_meta_title', 'Avinash Travels - Reliable & Safe Cabs and Taxi Services at Affordable Prices');
$seo_desc = get_setting('seo_meta_description', 'Book affordable outstation cabs, one-way drops, round trips, and airport transfers with Avinash Travels. Chauffeur included, guaranteed fixed fares, sanitized cars, 24/7 helpline.');

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-[#20A8D9]/15 text-[#20A8D9] border border-[#20A8D9]/30 uppercase tracking-wider">
                CONTENT & METRICS MANAGEMENT
            </span>
            <h1 class="text-2xl font-black font-poppins text-white mt-1">Homepage CMS & Counters</h1>
            <p class="text-xs text-slate-400 mt-1">Update live counter badges, about us narrative, mission, and hero section banners</p>
        </div>
        <a href="../index.php#about" target="_blank" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-sky-400 text-xs font-bold rounded-xl border border-slate-700 transition-colors flex items-center gap-1.5">
            <i class="fa-solid fa-eye"></i> View Live About Section
        </a>
    </div>

    <?php if ($message): ?>
        <div class="bg-emerald-500/10 border border-emerald-500/25 text-emerald-400 p-4 rounded-xl text-xs flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-base"></i>
            <span class="font-semibold"><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6 text-xs">
        <input type="hidden" name="action" value="save_cms_content">

        <!-- 1. Live Performance Metrics Counters -->
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl">
            <div class="flex items-center gap-2 mb-4 pb-3 border-b border-slate-800">
                <div class="w-8 h-8 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold text-sm">
                    <i class="fa-solid fa-chart-simple"></i>
                </div>
                <div>
                    <h3 class="text-sm font-extrabold text-white uppercase tracking-wider">Live Trust & Performance Counters</h3>
                    <p class="text-[11px] text-slate-400">Displayed prominently in the hero section and About Us counter strip</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Happy Customers Counter</label>
                    <input type="text" name="stat_happy_customers" value="<?php echo htmlspecialchars($stat_happy); ?>" placeholder="85K+" class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-white font-extrabold text-lg focus:outline-none focus:border-sky-400" required>
                    <span class="text-[10px] text-slate-500 block mt-1">Total satisfied passengers served</span>
                </div>

                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Trips Completed Counter</label>
                    <input type="text" name="stat_trips_completed" value="<?php echo htmlspecialchars($stat_trips); ?>" placeholder="90,000+" class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-white font-extrabold text-lg focus:outline-none focus:border-sky-400" required>
                    <span class="text-[10px] text-slate-500 block mt-1">Milestone total completed taxi trips</span>
                </div>

                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Active Vehicles in Fleet</label>
                    <input type="text" name="stat_active_vehicles" value="<?php echo htmlspecialchars($stat_fleet); ?>" placeholder="100+" class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-white font-extrabold text-lg focus:outline-none focus:border-sky-400" required>
                    <span class="text-[10px] text-slate-500 block mt-1">Active inspected cabs on the road</span>
                </div>

                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Verified Chauffeurs / Support</label>
                    <input type="text" name="stat_verified_drivers" value="<?php echo htmlspecialchars($stat_support); ?>" placeholder="24/7 Support" class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-white font-extrabold text-lg focus:outline-none focus:border-sky-400" required>
                    <span class="text-[10px] text-slate-500 block mt-1">Support badge / driver count</span>
                </div>
            </div>
        </div>

        <!-- 2. Hero Section Headlines -->
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-slate-800">
                <div class="w-8 h-8 rounded-lg bg-sky-500/20 text-sky-400 flex items-center justify-center font-bold text-sm">
                    <i class="fa-solid fa-bullhorn"></i>
                </div>
                <div>
                    <h3 class="text-sm font-extrabold text-white uppercase tracking-wider">Hero Section Headlines</h3>
                    <p class="text-[11px] text-slate-400">Main value proposition on the top of the homepage</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Top Pill Badge</label>
                    <input type="text" name="hero_badge_text" value="<?php echo htmlspecialchars($hero_badge); ?>" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white font-semibold focus:outline-none focus:border-sky-400" required>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Main Headline</label>
                    <input type="text" name="hero_headline" value="<?php echo htmlspecialchars($hero_headline); ?>" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white font-bold focus:outline-none focus:border-sky-400" required>
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Hero Subtitle</label>
                <input type="text" name="hero_subheading" value="<?php echo htmlspecialchars($hero_subheading); ?>" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-sky-400">
            </div>
        </div>

        <!-- 3. About Us & Company Story -->
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-slate-800">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-sm">
                    <i class="fa-solid fa-book-open"></i>
                </div>
                <div>
                    <h3 class="text-sm font-extrabold text-white uppercase tracking-wider">About Us Story & Mission Narrative</h3>
                    <p class="text-[11px] text-slate-400">The historical journey, core commitments, mission, and long-term vision</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Founder Journey Story</label>
                    <textarea name="about_story" rows="4" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white leading-relaxed focus:outline-none focus:border-sky-400"><?php echo htmlspecialchars($about_story); ?></textarea>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Quality & Safety Commitment</label>
                    <textarea name="about_commitment" rows="4" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white leading-relaxed focus:outline-none focus:border-sky-400"><?php echo htmlspecialchars($about_commitment); ?></textarea>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2 border-t border-slate-800/80">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Mission Statement</label>
                    <textarea name="about_mission" rows="3" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white leading-relaxed focus:outline-none focus:border-sky-400"><?php echo htmlspecialchars($about_mission); ?></textarea>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Vision Statement</label>
                    <textarea name="about_vision" rows="3" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white leading-relaxed focus:outline-none focus:border-sky-400"><?php echo htmlspecialchars($about_vision); ?></textarea>
                </div>
            </div>
        </div>

        <!-- 4. Search Engine Optimization (SEO) -->
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-slate-800">
                <div class="w-8 h-8 rounded-lg bg-indigo-500/20 text-indigo-400 flex items-center justify-center font-bold text-sm">
                    <i class="fa-solid fa-globe"></i>
                </div>
                <div>
                    <h3 class="text-sm font-extrabold text-white uppercase tracking-wider">SEO Meta Tags</h3>
                    <p class="text-[11px] text-slate-400">Search engine title tags and meta descriptions for Google indexing</p>
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">SEO Page Title Tag</label>
                <input type="text" name="seo_meta_title" value="<?php echo htmlspecialchars($seo_title); ?>" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-sky-400">
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">SEO Meta Description</label>
                <textarea name="seo_meta_description" rows="2" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-sky-400"><?php echo htmlspecialchars($seo_desc); ?></textarea>
            </div>
        </div>

        <button type="submit" class="w-full py-4 bg-[#EF6614] hover:bg-[#D9530F] text-white font-black rounded-xl text-xs uppercase tracking-wider shadow-lg shadow-orange-500/20 transition-all flex items-center justify-center gap-2">
            <i class="fa-solid fa-floppy-disk text-sm"></i>
            <span>Deploy CMS & Counter Updates to Live Website</span>
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
