<?php
// API - Submit Contact Enquiry
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

try {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($phone) || empty($email) || empty($subject) || empty($message)) {
        throw new Exception("All fields are required.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Please enter a valid email address.");
    }

    $stmt = $pdo->prepare("INSERT INTO `enquiries` (name, phone, email, subject, message) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $phone, $email, $subject, $message]);

    echo json_encode([
        'success' => true,
        'message' => "Your message has been sent successfully. Our team will get back to you shortly."
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
