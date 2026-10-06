<?php
$content = file_get_contents('index.php');
$lines = explode("\n", $content);
echo "Total lines: " . count($lines) . "\n";

foreach ($lines as $idx => $line) {
    if (preg_match('/<!--\s*([^-]+?)\s*-->/', $line, $m)) {
        $sec = trim($m[1]);
        if (strlen($sec) > 3 && strlen($sec) < 80) {
            $part = ($idx < 2031) ? "Part 1" : "Part 2";
            echo "[$part Line " . ($idx+1) . "] $sec\n";
        }
    }
}
