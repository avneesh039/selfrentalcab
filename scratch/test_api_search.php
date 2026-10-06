<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/pricing_helper.php';

function test_search($pickup, $drop) {
    global $pdo;
    $_POST = [
        'pickup_city' => $pickup,
        'drop_city' => $drop,
        'pickup_date' => date('Y-m-d', strtotime('+1 day')),
        'pickup_time' => '10:00',
        'trip_type' => 'one_way',
        'coupon_code' => ''
    ];

    ob_start();
    require __DIR__ . '/../api/search_cabs.php';
    $output = ob_get_clean();

    $data = json_decode($output, true);
    return $data;
}

$test_routes = [
    ['Bilaspur', 'Raipur', 2500, 3000],
    ['Raipur', 'Bilaspur', 2500, 3000],
    ['Bilaspur', 'Raigarh', 3000, 4500],
    ['Raigarh', 'Bilaspur', 3000, 4500],
    ['Raigarh', 'Raipur', 4500, 5500],
    ['Raipur', 'Raigarh', 4500, 5500],
    ['Raigarh', 'Jharsuguda', 2500, 3500],
    ['Jharsuguda', 'Raigarh', 2500, 3500],
    ['Korba', 'Bilaspur', 2200, 3500],
    ['Bilaspur', 'Korba', 2200, 3500],
    ['Korba', 'Raipur', 4500, 5500],
    ['Raipur', 'Korba', 4500, 5500]
];

$all_ok = true;
foreach ($test_routes as $t) {
    list($from, $to, $exp_dzire, $exp_ertiga) = $t;
    $res = test_search($from, $to);
    if (!isset($res['success']) || !$res['success']) {
        echo "[FAIL] $from -> $to : " . ($res['error'] ?? 'Unknown error') . PHP_EOL;
        $all_ok = false;
    } else {
        $has_dzire_price = (strpos($res['html'], number_format($exp_dzire, 2)) !== false);
        $has_ertiga_price = (strpos($res['html'], number_format($exp_ertiga, 2)) !== false);
        if (!$has_dzire_price || !$has_ertiga_price) {
            $all_ok = false;
            echo "[MISMATCH] $from -> $to" . PHP_EOL;
        } else {
            echo "[PASS] $from -> $to : {$res['distance_km']} KM, {$res['est_hours']} Hrs | Dzire ₹" . number_format($exp_dzire) . " | Ertiga ₹" . number_format($exp_ertiga) . PHP_EOL;
        }
    }
}
if ($all_ok) {
    echo ">>> ALL 12 ONE-WAY SEARCH API CALLS PASSED WITH 100% ACCURACY!" . PHP_EOL;
}
