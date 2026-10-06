<?php
// Session Authentication Helper
// Avinash Travels - Premium Cab Booking Platform

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Admin Authentication Checks
 */
function is_admin_logged_in() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function require_admin() {
    if (!is_admin_logged_in()) {
        header("Location: /admin/login.php");
        exit;
    }
}

/**
 * Customer Authentication Checks
 */
function is_customer_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function require_customer() {
    if (!is_customer_logged_in()) {
        header("Location: /customer/login.php");
        exit;
    }
}

/**
 * Driver Authentication Checks
 */
function is_driver_logged_in() {
    return isset($_SESSION['driver_id']) && !empty($_SESSION['driver_id']);
}

function require_driver() {
    if (!is_driver_logged_in()) {
        header("Location: /driver/login.php");
        exit;
    }
}
