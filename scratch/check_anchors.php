<?php
$html = file_get_contents('http://127.0.0.1:8000/index.php');

preg_match_all('/href="(#[a-zA-Z0-9_-]+)"/', $html, $matches);
$anchors = array_unique($matches[1]);

echo "Checking anchor targets:\n";
foreach ($anchors as $a) {
    $target_id = substr($a, 1);
    $found = (strpos($html, 'id="' . $target_id . '"') !== false);
    echo "Anchor $a -> Target id=\"$target_id\": " . ($found ? "✅ FOUND" : "❌ MISSING") . "\n";
}
