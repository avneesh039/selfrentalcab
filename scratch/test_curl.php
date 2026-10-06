<?php
$ch = curl_init('http://127.0.0.1:8000/api/search_cabs.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'pickup_city' => 'Raipur',
    'drop_city' => 'Bilaspur',
    'pickup_date' => date('Y-m-d'),
    'pickup_time' => '18:00',
    'trip_type' => 'one_way'
]));
$res = curl_exec($ch);
echo "RESPONSE:\n" . $res . "\n";
