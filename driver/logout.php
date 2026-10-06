<?php
// Driver Logout
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['driver_id']);
unset($_SESSION['driver_name']);
unset($_SESSION['driver_phone']);
unset($_SESSION['driver_status']);

session_destroy();
header("Location: /index.php");
exit;
