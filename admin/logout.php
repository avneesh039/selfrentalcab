<?php
// Admin Logout
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['admin_id']);
unset($_SESSION['admin_email']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_role']);

session_destroy();
header("Location: /admin/login.php");
exit;
