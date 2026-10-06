<?php
require_once __DIR__ . '/../includes/pricing_helper.php';

$test_routes = [
    ['Bilaspur', 'Raipur', 2500, 3000, 'Toll & Parking: Included'],
    ['Raipur', 'Bilaspur', 2500, 3000, 'Toll & Parking: Included'],
    ['Bilaspur', 'Raigarh', 3000, 4500, 'Toll: Included'],
    ['Raigarh', 'Bilaspur', 3000, 4500, 'Toll: Included'],
    ['Raigarh', 'Raipur', 4500, 5500, 'Standard Rate'],
    ['Raipur', 'Raigarh', 4500, 5500, 'Standard Rate'],
    ['Raigarh', 'Jharsuguda', 2500, 3500, 'Toll: Included'],
    ['Jharsuguda', 'Raigarh', 2500, 3500, 'Toll: Included'],
    ['Korba', 'Bilaspur', 2200, 3500, 'Standard Rate'],
    ['Bilaspur', 'Korba', 2200, 3500, 'Standard Rate'],
    ['Korba', 'Raipur', 4500, 5500, 'Toll & Parking: Included'],
    ['Raipur', 'Korba', 4500, 5500, 'Toll & Parking: Included']
];

echo "=== TESTING ALL ONE-WAY ROUTE COMBINATIONS ===" . PHP_EOL;
$all_passed = true;

foreach ($test_routes as $t) {
    list($from, $to, $expected_dzire, $expected_ertiga, $expected_badge) = $t;
    
    // Sedan (Dzire)
    $fare_sedan = calculate_trip_fare([
        'pickup_city' => $from,
        'drop_city' => $to,
        'trip_type' => 'one_way',
        'category_id' => 1
    ]);

    // MPV (Ertiga)
    $fare_mpv = calculate_trip_fare([
        'pickup_city' => $from,
        'drop_city' => $to,
        'trip_type' => 'one_way',
        'category_id' => 3
    ]);

    $dzire_ok = ($fare_sedan['final_total'] == $expected_dzire);
    $ertiga_ok = ($fare_mpv['final_total'] == $expected_ertiga);
    $badge_ok = ($fare_sedan['inclusion_badge'] === $expected_badge);

    if (!$dzire_ok || !$ertiga_ok || !$badge_ok) {
        $all_passed = false;
        echo "[FAIL] $from -> $to | Dzire: {$fare_sedan['final_total']} (exp $expected_dzire), Ertiga: {$fare_mpv['final_total']} (exp $expected_ertiga), Badge: '{$fare_sedan['inclusion_badge']}' (exp '$expected_badge')" . PHP_EOL;
    } else {
        echo "[PASS] $from -> $to | Swift Dzire: ₹{$fare_sedan['final_total']} | Ertiga: ₹{$fare_mpv['final_total']} | {$fare_sedan['inclusion_badge']}" . PHP_EOL;
    }
}

if ($all_passed) {
    echo ">>> ALL 12 ONE-WAY CORRIDORS PASSED 100%!" . PHP_EOL;
}
