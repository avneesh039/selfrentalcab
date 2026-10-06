<?php
// Customer Logout
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['user_id']);
unset($_SESSION['user_name']);
unset($_SESSION['user_email']);
unset($_SESSION['user_phone']);

session_destroy();
header("Location: /index.php");
exit;
