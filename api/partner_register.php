<?php
// API - Partner Registration
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

try {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $vehicle_type = trim($_POST['vehicle_type'] ?? '');
    $vehicle_no = trim($_POST['vehicle_no'] ?? '');
    $license_no = trim($_POST['license_no'] ?? '');

    if (empty($name) || empty($phone) || empty($email) || empty($city) || empty($vehicle_type) || empty($vehicle_no) || empty($license_no)) {
        throw new Exception("All registration fields are required.");
    }

    // Check if phone or email already in use
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `partners` WHERE phone = ? OR email = ?");
    $stmt->execute([$phone, $email]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("An application with this phone or email has already been submitted.");
    }

    // Helper to upload files safely
    $upload_dir = __DIR__ . '/../uploads/documents/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $uploaded_paths = [
        'rc' => null,
        'license' => null,
        'insurance' => null,
        'photo' => null
    ];

    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
    $allowed_mimes = ['image/jpeg', 'image/png', 'application/pdf'];

    foreach (['rc', 'license', 'insurance', 'photo'] as $file_key) {
        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
            $file_name = $_FILES[$file_key]['name'];
            $file_tmp = $_FILES[$file_key]['tmp_name'];
            $file_size = $_FILES[$file_key]['size'];
            $file_type = $_FILES[$file_key]['type'];

            // Validate Size (limit: 5MB)
            if ($file_size > 5 * 1024 * 1024) {
                throw new Exception("File upload size limit exceeded for $file_key. Max size is 5MB.");
            }

            // Validate Extension and MIME type (Security rule 31)
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_extensions) || !in_array($file_type, $allowed_mimes)) {
                throw new Exception("Invalid file format for $file_key. Allowed types: JPG, PNG, PDF.");
            }

            // Create safe unique filename
            $new_filename = $file_key . '_' . md5(uniqid() . $file_name) . '.' . $ext;
            $dest_path = $upload_dir . $new_filename;

            if (move_uploaded_file($file_tmp, $dest_path)) {
                $uploaded_paths[$file_key] = 'uploads/documents/' . $new_filename;
            } else {
                throw new Exception("Failed to save uploaded file $file_key.");
            }
        }
    }

    // Insert into database
    $stmt_ins = $pdo->prepare("INSERT INTO `partners` (
        name, phone, email, city, vehicle_type, vehicle_no, license_no, 
        rc_path, license_path, insurance_path, photo_path, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");

    $stmt_ins->execute([
        $name, $phone, $email, $city, $vehicle_type, $vehicle_no, $license_no,
        $uploaded_paths['rc'],
        $uploaded_paths['license'],
        $uploaded_paths['insurance'],
        $uploaded_paths['photo']
    ]);

    // Also auto-create a pending driver account so they can log in eventually!
    // Password will default to driver123 (hashed)
    $driver_pass = password_hash('driver123', PASSWORD_DEFAULT);
    $stmt_drv = $pdo->prepare("INSERT INTO `drivers` (name, phone, email, password_hash, city, vehicle_type, vehicle_no, status)
                                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt_drv->execute([$name, $phone, $email, $driver_pass, $city, $vehicle_type, $vehicle_no]);
    $driver_id = $pdo->lastInsertId();

    // Link uploaded docs to driver documents table
    $stmt_doc = $pdo->prepare("INSERT INTO `driver_documents` (driver_id, doc_type, doc_path, status) VALUES (?, ?, ?, 'pending')");
    foreach ($uploaded_paths as $type => $path) {
        if ($path) {
            $stmt_doc->execute([$driver_id, strtoupper($type), $path]);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Application submitted successfully. Our team will review your documents and verify your partner status shortly."
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
