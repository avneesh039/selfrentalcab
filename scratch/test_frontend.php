<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/pricing_helper.php';

echo "=== 1. TESTING DATABASE & SETTINGS ===\n";
echo "Company Name: " . get_setting('company_name', 'Not found') . "\n";
echo "Phone: " . get_setting('company_phone', 'Not found') . "\n";

echo "\n=== 2. TESTING VEHICLE CATEGORIES ===\n";
$cats = $pdo ? $pdo->query("SELECT * FROM vehicle_categories WHERE status='active'")->fetchAll() : [];
echo "Active vehicle categories in DB: " . count($cats) . "\n";
foreach ($cats as $c) {
    echo " - [ID {$c['id']}] {$c['name']} (₹{$c['base_price']} base, ₹{$c['per_km_price']}/km)\n";
}

echo "\n=== 3. TESTING ON-WAY CORRIDORS ===\n";
$corridors = get_fixed_oneway_routes();
echo "Total fixed corridors: " . count($corridors) . "\n";
foreach (array_slice($corridors, 0, 4) as $k => $cor) {
    echo " - $k: {$cor['from']} to {$cor['to']} ({$cor['distance_km']} km) -> Dzire: ₹{$cor['prices']['Dzire']}, Ertiga: ₹{$cor['prices']['Ertiga']}\n";
}

echo "\n=== 4. TESTING SEARCH CABS API ===\n";
$today = date('Y-m-d');
$now_time = date('H:i', strtotime('+1 hour'));

$post_data = [
    'pickup_city' => 'Raipur',
    'drop_city' => 'Bilaspur',
    'pickup_date' => $today,
    'pickup_time' => $now_time,
    'trip_type' => 'one_way'
];

$ch = curl_init('http://127.0.0.1:8000/api/search_cabs.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
$resp = curl_exec($ch);
curl_close($ch);

$search_res = json_decode($resp, true);
if ($search_res && !empty($search_res['success'])) {
    echo "✅ Search successful! Available cabs: " . count($search_res['cabs']) . "\n";
    foreach ($search_res['cabs'] as $cab) {
        echo "   * {$cab['category_name']} ({$cab['car_models']}): ₹{$cab['fare']['total_fare']} ({$cab['distance_km']} km)\n";
    }
} else {
    echo "❌ Search failed: " . ($search_res['message'] ?? $resp) . "\n";
}

echo "\n=== 5. CHECKING IMAGE ASSETS IN HOMEPAGE ===\n";
$index_html = file_get_contents(__DIR__ . '/../index.php');
preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/', $index_html, $img_matches);
echo "Total <img> tags: " . count($img_matches[1]) . "\n";
$unique_imgs = array_unique($img_matches[1]);
foreach ($unique_imgs as $src) {
    if (strpos($src, 'http') === 0) {
        echo " - Remote: $src\n";
    } else {
        $path = __DIR__ . '/../' . ltrim($src, '/');
        $exists = file_exists($path) ? "✅ Exists" : "⚠️ Missing";
        echo " - Local ($exists): $src\n";
    }
}
