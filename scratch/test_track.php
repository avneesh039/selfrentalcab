<?php
$ch = curl_init('http://127.0.0.1:8000/api/track_booking.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'query' => '8120868818'
]));
$res = curl_exec($ch);
echo "TRACKING RES:\n" . substr($res, 0, 300) . "\n";
