<?php
$content = file_get_contents('index.php');
$lines = explode("\n", $content);

// Keep lines 0 to 120 (lines 1 to 121 in 1-based indexing)
$php_header = array_slice($lines, 0, 121);

// Find where the second <!DOCTYPE html> or <html starts
$second_html_idx = -1;
for ($i = 2000; $i < count($lines); $i++) {
    if (trim($lines[$i]) === '<html lang="en" class="scroll-smooth">') {
        $second_html_idx = $i;
        break;
    }
}

if ($second_html_idx === -1) {
    die("Could not find second HTML tag!\n");
}

echo "Found second HTML at line: " . ($second_html_idx + 1) . "\n";

$body_part = array_slice($lines, $second_html_idx);

// Combine PHP header + <!DOCTYPE html> + body_part
$new_content = implode("\n", $php_header) . "\n<!DOCTYPE html>\n" . implode("\n", $body_part);

file_put_contents('index.php', $new_content);
echo "New index.php total lines: " . count(explode("\n", $new_content)) . "\n";
