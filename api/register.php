<?php
// api/register.php
require_once '../config/db.php';
require_once '../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response('error', 'Invalid request method.');
}

$name = sanitize_input($_POST['name'] ?? '');
$email = sanitize_input($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = sanitize_input($_POST['role'] ?? 'user');
$turf_name = sanitize_input($_POST['turf_name'] ?? '');
$csrf_token = $_POST['csrf_token'] ?? '';

if (!validate_csrf_token($csrf_token)) {
    send_json_response('error', 'CSRF token validation failed.');
}

if (empty($name) || empty($email) || empty($password)) {
    send_json_response('error', 'Please fill all required fields.');
}

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        send_json_response('error', 'Email already registered.');
    }

    // Handle file uploads for owners
    $aadhaar_path = '';
    $license_path = '';
    if ($role === 'owner') {
        if (isset($_FILES['aadhaar']) && $_FILES['aadhaar']['error'] === UPLOAD_ERR_OK) {
            $aadhaar_path = 'uploads/' . time() . '_' . $_FILES['aadhaar']['name'];
            move_uploaded_file($_FILES['aadhaar']['tmp_name'], '../' . $aadhaar_path);
        }
        if (isset($_FILES['license']) && $_FILES['license']['error'] === UPLOAD_ERR_OK) {
            $license_path = 'uploads/' . time() . '_' . $_FILES['license']['name'];
            move_uploaded_file($_FILES['license']['tmp_name'], '../' . $license_path);
        }
    }

    // Insert user
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, aadhaar_file, license_file) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $email, $hashed_password, $role, $aadhaar_path, $license_path]);
    $user_id = $pdo->lastInsertId();

    // If owner, create initial turf entry
    if ($role === 'owner' && !empty($turf_name)) {
        $stmt = $pdo->prepare("INSERT INTO turfs (owner_id, name, location, sport_category, price_per_hour) VALUES (?, ?, 'Not Specified', 'Multi-Sport', 0)");
        $stmt->execute([$user_id, $turf_name]);
    }

    $pdo->commit();
    send_json_response('success', 'Registration successful! You can now login.');
} catch (Exception $e) {
    $pdo->rollBack();
    send_json_response('error', 'Registration failed: ' . $e->getMessage());
}
?>
