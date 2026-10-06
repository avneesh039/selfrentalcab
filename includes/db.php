<?php
// Database Connection Manager
// Avinash Travels - Premium Cab Booking Platform

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Enforce Indian Standard Time (IST)
date_default_timezone_set('Asia/Kolkata');

// Database Connection Attempts (Local and Production support)
$db_configs = [
    [
        'host' => '127.0.0.1',
        'dbname' => 'avinashtravels',
        'username' => 'root',
        'password' => ''
    ],
    [
        'host' => 'localhost',
        'dbname' => 'avinashtravels',
        'username' => 'root',
        'password' => ''
    ],
    [
        'host' => 'localhost',
        'dbname' => 'lakshyas_avinashtour',
        'username' => 'lakshyas_avinashtour',
        'password' => 'avinash123@'
    ]
];

$pdo = null;
foreach ($db_configs as $cfg) {
    try {
        $dsn = "mysql:host={$cfg['host']};dbname={$cfg['dbname']};charset=utf8mb4";
        $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 2
        ]);
        if ($pdo) {
            break;
        }
    } catch (PDOException $e) {
        $pdo = null;
    }
}

if (!$pdo) {
    error_log("Database Connection Failed: Unable to connect to MySQL database.");
}

/**
 * Fetch a global setting value from database
 */
function get_setting($key, $default = '') {
    global $pdo;
    static $settings_cache = [];
    
    if (isset($settings_cache[$key])) {
        return $settings_cache[$key];
    }
    
    if (!$pdo) {
        return $default;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT meta_value FROM `settings` WHERE meta_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        $settings_cache[$key] = ($val !== false) ? $val : $default;
        return $settings_cache[$key];
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * Safely ensure payment screenshot columns exist in database
 */
function ensure_payment_columns() {
    global $pdo;
    static $checked = false;
    if ($checked || !$pdo) return;
    $checked = true;
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `bookings` LIKE 'payment_screenshot'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE `bookings` ADD `payment_screenshot` VARCHAR(255) NULL AFTER `payment_status`");
        }
        $cols2 = $pdo->query("SHOW COLUMNS FROM `bookings` LIKE 'utr_number'")->fetchAll();
        if (empty($cols2)) {
            $pdo->exec("ALTER TABLE `bookings` ADD `utr_number` VARCHAR(100) NULL AFTER `payment_screenshot`");
        }
        $cols3 = $pdo->query("SHOW COLUMNS FROM `payments` LIKE 'screenshot_path'")->fetchAll();
        if (empty($cols3)) {
            $pdo->exec("ALTER TABLE `payments` ADD `screenshot_path` VARCHAR(255) NULL AFTER `raw_response`");
        }
        $cols4 = $pdo->query("SHOW COLUMNS FROM `bookings` LIKE 'admin_notes'")->fetchAll();
        if (empty($cols4)) {
            $pdo->exec("ALTER TABLE `bookings` ADD `admin_notes` TEXT NULL AFTER `utr_number`");
        }
        $cols5 = $pdo->query("SHOW COLUMNS FROM `bookings` LIKE 'booking_card_image'")->fetchAll();
        if (empty($cols5)) {
            $pdo->exec("ALTER TABLE `bookings` ADD `booking_card_image` VARCHAR(255) NULL AFTER `admin_notes`");
        }
    } catch (Exception $e) {}
}

/**
 * Save or update a global setting value in database
 */
function set_setting($key, $val) {
    global $pdo;
    if (!$pdo) return false;
    try {
        $stmt = $pdo->prepare("INSERT INTO `settings` (meta_key, meta_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE meta_value = ?");
        return $stmt->execute([$key, $val, $val]);
    } catch (PDOException $e) {
        return false;
    }
}
