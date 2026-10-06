<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/pricing_helper.php';

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
