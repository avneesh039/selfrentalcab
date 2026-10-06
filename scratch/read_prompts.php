<?php
$lines = file('C:/Users/acer/.gemini/antigravity-ide/brain/6972c4d6-36f9-45d1-9136-31f3ad20007b/.system_generated/logs/transcript.jsonl');
foreach ($lines as $l) {
    $obj = json_decode($l, true);
    if ($obj && isset($obj['type']) && $obj['type'] === 'USER_INPUT') {
        echo "USER: " . trim($obj['content']) . "\n---\n";
    }
}
