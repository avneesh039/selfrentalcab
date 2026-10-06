<?php
$html = file_get_contents('http://127.0.0.1:8000/index.php');

echo "HTML Length: " . strlen($html) . " bytes\n";
echo "Has DOCTYPE: " . (strpos($html, '<!DOCTYPE html>') !== false ? "YES" : "NO") . "\n";
echo "Has main-header: " . (strpos($html, 'id="main-header"') !== false ? "YES" : "NO") . "\n";
echo "Has booking-widget: " . (strpos($html, 'id="booking-widget"') !== false ? "YES" : "NO") . "\n";
echo "Has one-way-fares: " . (strpos($html, 'id="one-way-fares"') !== false ? "YES" : "NO") . "\n";
echo "Has vehicles: " . (strpos($html, 'id="vehicles"') !== false ? "YES" : "NO") . "\n";
echo "Has testimonials: " . (strpos($html, 'id="testimonials"') !== false ? "YES" : "NO") . "\n";
echo "Has faq: " . (strpos($html, 'id="faq"') !== false ? "YES" : "NO") . "\n";
echo "Has footer: " . (strpos($html, '<footer') !== false ? "YES" : "NO") . "\n";
echo "Has booking-modal: " . (strpos($html, 'id="booking-modal"') !== false ? "YES" : "NO") . "\n";
echo "Number of <html> tags: " . substr_count($html, '<html') . "\n";
echo "Number of </html> tags: " . substr_count($html, '</html>') . "\n";
echo "Number of <body> tags: " . substr_count($html, '<body') . "\n";
echo "Number of </body> tags: " . substr_count($html, '</body>') . "\n";
