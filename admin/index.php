<?php
// Admin Dashboard KPIs & Analytics Page
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';

// Fetch KPI statistics from DB safely
$today_date = date('Y-m-d');
$kpi_today_bookings = 0;
$kpi_total_bookings = 0;
$kpi_pending_bookings = 0;
$kpi_completed_bookings = 0;
$kpi_cancelled_bookings = 0;
$kpi_revenue = 0;
$kpi_active_vehicles = 0;
$kpi_active_drivers = 0;
$kpi_pending_partners = 0;
$kpi_pending_bids = 0;
$kpi_enquiries = 0;
$daily_chart_labels = [];
$daily_chart_data = [];
$monthly_chart_labels = [];
$monthly_chart_data = [];
$cat_labels = ['Sedan', 'SUV', 'MPV', 'Luxury', 'Tempo', 'Bus'];
$cat_data = [12, 8, 15, 4, 3, 1];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_chart_labels[] = date('D (d M)', strtotime($date));
    $daily_chart_data[] = 0;
}
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthly_chart_labels[] = date('M Y', strtotime($month . "-01"));
    $monthly_chart_data[] = 0;
}

if ($pdo) {
    try {
        $kpi_today_bookings = $pdo->query("SELECT COUNT(*) FROM `bookings` WHERE DATE(created_at) = '$today_date'")->fetchColumn() ?: 0;
        $kpi_total_bookings = $pdo->query("SELECT COUNT(*) FROM `bookings`")->fetchColumn() ?: 0;
        $kpi_pending_bookings = $pdo->query("SELECT COUNT(*) FROM `bookings` WHERE status = 'pending'")->fetchColumn() ?: 0;
        $kpi_completed_bookings = $pdo->query("SELECT COUNT(*) FROM `bookings` WHERE status = 'completed'")->fetchColumn() ?: 0;
        $kpi_cancelled_bookings = $pdo->query("SELECT COUNT(*) FROM `bookings` WHERE status = 'cancelled'")->fetchColumn() ?: 0;

        $kpi_revenue = $pdo->query("SELECT SUM(final_total) FROM `bookings` WHERE payment_status = 'paid'")->fetchColumn() ?: 0;

        $kpi_active_vehicles = $pdo->query("SELECT COUNT(*) FROM `vehicles` WHERE status != 'maintenance'")->fetchColumn() ?: 0;
        $kpi_active_drivers = $pdo->query("SELECT COUNT(*) FROM `drivers` WHERE status = 'approved'")->fetchColumn() ?: 0;

        $kpi_pending_partners = $pdo->query("SELECT COUNT(*) FROM `partners` WHERE status = 'pending'")->fetchColumn() ?: 0;
        $kpi_pending_bids = $pdo->query("SELECT COUNT(*) FROM `trip_bids` WHERE status = 'pending'")->fetchColumn() ?: 0;
        $kpi_enquiries = $pdo->query("SELECT COUNT(*) FROM `enquiries` WHERE is_read = 0")->fetchColumn() ?: 0;

        // Daily chart
        $daily_chart_data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $daily_chart_data[] = $pdo->query("SELECT COUNT(*) FROM `bookings` WHERE DATE(created_at) = '$date'")->fetchColumn() ?: 0;
        }

        // Monthly chart
        $monthly_chart_data = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $monthly_chart_data[] = $pdo->query("SELECT SUM(final_total) FROM `bookings` WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month' AND payment_status = 'paid'")->fetchColumn() ?: 0;
        }

        // Category performance
        $cat_perf = $pdo->query("SELECT vc.name, COUNT(b.id) AS count FROM `vehicle_categories` vc LEFT JOIN `bookings` b ON b.category_id = vc.id GROUP BY vc.id")->fetchAll();
        if (!empty($cat_perf)) {
            $cat_labels = [];
            $cat_data = [];
            foreach ($cat_perf as $cp) {
                $cat_labels[] = $cp['name'];
                $cat_data[] = $cp['count'];
            }
        }
    } catch (Exception $e) {}
}
?>

<!-- Content Area -->
<div>
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold font-poppins text-white">Administrative control center</h1>
            <p class="text-xs text-slate-400">Control center KPI aggregates and business tracking charts</p>
        </div>
        <div class="text-xs text-slate-400 font-mono">
            System Clock: <?php echo date('d-M-Y H:i A'); ?>
        </div>
    </div>

    <!-- KPI Cards Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <!-- Card 1 -->
        <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl">
            <span class="text-[10px] font-bold text-slate-500 uppercase block tracking-wider mb-1">Today's Bookings</span>
            <div class="flex justify-between items-end">
                <span class="text-2xl font-bold text-white"><?php echo $kpi_today_bookings; ?></span>
                <span class="text-xs text-sky-400"><i class="fa-solid fa-calendar-day"></i></span>
            </div>
        </div>
        <!-- Card 2 -->
        <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl">
            <span class="text-[10px] font-bold text-slate-500 uppercase block tracking-wider mb-1">Total Bookings</span>
            <div class="flex justify-between items-end">
                <span class="text-2xl font-bold text-white"><?php echo $kpi_total_bookings; ?></span>
                <span class="text-xs text-sky-400"><i class="fa-solid fa-list-check"></i></span>
            </div>
        </div>
        <!-- Card 3 -->
        <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl">
            <span class="text-[10px] font-bold text-slate-500 uppercase block tracking-wider mb-1">Pending Bookings</span>
            <div class="flex justify-between items-end">
                <span class="text-2xl font-bold text-white"><?php echo $kpi_pending_bookings; ?></span>
                <span class="text-xs text-amber-500"><i class="fa-solid fa-clock-rotate-left"></i></span>
            </div>
        </div>
        <!-- Card 4 -->
        <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl">
            <span class="text-[10px] font-bold text-slate-500 uppercase block tracking-wider mb-1">Completed / Cancelled</span>
            <div class="flex justify-between items-end">
                <span class="text-xl font-bold text-slate-200"><?php echo $kpi_completed_bookings; ?> <span class="text-slate-500">/</span> <span class="text-rose-500"><?php echo $kpi_cancelled_bookings; ?></span></span>
                <span class="text-xs text-emerald-500"><i class="fa-solid fa-circle-check"></i></span>
            </div>
        </div>
        
        <!-- Card 5 -->
        <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl">
            <span class="text-[10px] font-bold text-slate-500 uppercase block tracking-wider mb-1">Confirmed Trips</span>
            <div class="flex justify-between items-end">
                <span class="text-2xl font-bold text-emerald-400"><?php echo ($pdo) ? $pdo->query("SELECT COUNT(*) FROM `bookings` WHERE status = 'confirmed'")->fetchColumn() : 0; ?></span>
                <span class="text-xs text-emerald-500"><i class="fa-solid fa-route"></i></span>
            </div>
        </div>
        <!-- Card 6 -->
        <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl">
            <span class="text-[10px] font-bold text-slate-500 uppercase block tracking-wider mb-1">Vehicles / Drivers</span>
            <div class="flex justify-between items-end">
                <span class="text-xl font-bold text-white"><?php echo $kpi_active_vehicles; ?> <span class="text-slate-500">/</span> <?php echo $kpi_active_drivers; ?></span>
                <span class="text-xs text-sky-400"><i class="fa-solid fa-car-side"></i></span>
            </div>
        </div>
        <!-- Card 7 -->
        <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl">
            <span class="text-[10px] font-bold text-slate-500 uppercase block tracking-wider mb-1">Pending Partners / Bids</span>
            <div class="flex justify-between items-end">
                <span class="text-xl font-bold text-white"><?php echo $kpi_pending_partners; ?> <span class="text-slate-500">/</span> <?php echo $kpi_pending_bids; ?></span>
                <span class="text-xs text-sky-400"><i class="fa-solid fa-user-plus"></i></span>
            </div>
        </div>
        <!-- Card 8 -->
        <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl animate-pulse">
            <span class="text-[10px] font-bold text-slate-500 uppercase block tracking-wider mb-1">Pending Enquiries</span>
            <div class="flex justify-between items-end">
                <span class="text-2xl font-bold text-amber-500"><?php echo $kpi_enquiries; ?></span>
                <span class="text-xs text-amber-500"><i class="fa-solid fa-envelope-open-text"></i></span>
            </div>
        </div>
    </div>

    <!-- Analytics Charts Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Chart 1: Daily Bookings -->
        <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-xl p-5">
            <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-4">Daily booking trends</h3>
            <div class="h-64">
                <canvas id="dailyBookingsChart"></canvas>
            </div>
        </div>
        <!-- Chart 2: Category distribution -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
            <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-4">Vehicle Category Performance</h3>
            <div class="h-64 flex items-center justify-center">
                <canvas id="categoryDistributionChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Monthly Revenue Trend -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 mb-8">
        <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-4">Monthly Paid Revenue Analytics</h3>
        <div class="h-64">
            <canvas id="monthlyRevenueChart"></canvas>
        </div>
    </div>

    <!-- Quick Shortcuts Panel -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
        <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-4">Operations quick shortcuts</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-3 text-center text-xs font-semibold">
            <a href="bookings.php" class="bg-slate-950 hover:bg-slate-800 p-3 rounded-lg border border-slate-850 flex flex-col justify-center items-center gap-1.5 transition-colors">
                <i class="fa-solid fa-receipt text-amber-500 text-lg"></i> Bookings
            </a>
            <a href="drivers.php" class="bg-slate-950 hover:bg-slate-800 p-3 rounded-lg border border-slate-850 flex flex-col justify-center items-center gap-1.5 transition-colors">
                <i class="fa-solid fa-user-shield text-amber-500 text-lg"></i> Drivers
            </a>
            <a href="partners.php" class="bg-slate-950 hover:bg-slate-800 p-3 rounded-lg border border-slate-850 flex flex-col justify-center items-center gap-1.5 transition-colors">
                <i class="fa-solid fa-address-card text-amber-500 text-lg"></i> Partners
            </a>
            <a href="bids.php" class="bg-slate-950 hover:bg-slate-800 p-3 rounded-lg border border-slate-850 flex flex-col justify-center items-center gap-1.5 transition-colors">
                <i class="fa-solid fa-gavel text-amber-500 text-lg"></i> Driver Bids
            </a>
            <a href="vehicles.php" class="bg-slate-950 hover:bg-slate-800 p-3 rounded-lg border border-slate-850 flex flex-col justify-center items-center gap-1.5 transition-colors">
                <i class="fa-solid fa-car text-amber-500 text-lg"></i> Vehicles
            </a>
            <a href="enquiries.php" class="bg-slate-950 hover:bg-slate-800 p-3 rounded-lg border border-slate-850 flex flex-col justify-center items-center gap-1.5 transition-colors">
                <i class="fa-solid fa-envelope-open-text text-amber-500 text-lg"></i> Enquiries
            </a>
        </div>
    </div>
</div>

<script>
    // Initialize Chart.js elements
    
    // 1. Daily Bookings Chart
    const ctxDaily = document.getElementById('dailyBookingsChart').getContext('2d');
    new Chart(ctxDaily, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($daily_chart_labels); ?>,
            datasets: [{
                label: 'Bookings Count',
                data: <?php echo json_encode($daily_chart_data); ?>,
                borderColor: '#0070E0',
                backgroundColor: 'rgba(0, 112, 224, 0.15)',
                borderWidth: 2,
                fill: true,
                tension: 0.35
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#94A3B8', stepSize: 1 }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#94A3B8' }
                }
            }
        }
    });

    // 2. Category Distribution Chart
    const ctxCat = document.getElementById('categoryDistributionChart').getContext('2d');
    new Chart(ctxCat, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($cat_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($cat_data); ?>,
                backgroundColor: ['#0070E0', '#00B4D8', '#10B981', '#3B82F6', '#8B5CF6', '#EC4899'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: '#94A3B8', boxWidth: 12, font: { size: 10 } }
                }
            }
        }
    });

    // 3. Monthly Revenue Chart
    const ctxRev = document.getElementById('monthlyRevenueChart').getContext('2d');
    new Chart(ctxRev, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($monthly_chart_labels); ?>,
            datasets: [{
                label: 'Paid Revenue (₹)',
                data: <?php echo json_encode($monthly_chart_data); ?>,
                backgroundColor: 'rgba(16, 185, 129, 0.75)',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#94A3B8' }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#94A3B8' }
                }
            }
        }
    });
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
