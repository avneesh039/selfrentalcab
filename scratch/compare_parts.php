<?php
$content = file_get_contents('index.php');
$lines = explode("\n", $content);

echo "Lines 122-2031 (Part 1 length): " . (2031 - 122) . " lines\n";
echo "Lines 2033-5006 (Part 2 length): " . (5006 - 2033) . " lines\n";

// Check forms and IDs in Part 1
preg_match_all('/id="([^"]+)"/', implode("\n", array_slice($lines, 122, 2031-122)), $m1);
echo "Part 1 IDs: " . count($m1[1]) . " IDs: " . implode(", ", array_slice(array_unique($m1[1]), 0, 20)) . "...\n";

// Check forms and IDs in Part 2
preg_match_all('/id="([^"]+)"/', implode("\n", array_slice($lines, 2033)), $m2);
echo "Part 2 IDs: " . count($m2[1]) . " IDs: " . implode(", ", array_slice(array_unique($m2[1]), 0, 20)) . "...\n";
