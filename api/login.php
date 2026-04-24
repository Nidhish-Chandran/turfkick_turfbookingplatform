<?php
// api/login.php
require_once '../config/db.php';
require_once '../includes/helpers.php';

// Prevent direct access if not a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response('error', 'Invalid request method.');
}

$email = sanitize_input($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = sanitize_input($_POST['role'] ?? 'user');
$csrf_token = $_POST['csrf_token'] ?? '';

// Validate CSRF
if (!validate_csrf_token($csrf_token)) {
    send_json_response('error', 'CSRF token validation failed.');
}

if (empty($email) || empty($password)) {
    send_json_response('error', 'Please enter email and password.');
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = ?");
    $stmt->execute([$email, $role]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role'] = $user['role'];

        $redirect = ($user['role'] === 'owner') ? 'owner_dashboard.html' : 'browse_turfs.html';
        
        send_json_response('success', 'Login successful.', ['redirect' => $redirect]);
    } else {
        send_json_response('error', 'Invalid email or password.');
    }
} catch (Exception $e) {
    send_json_response('error', 'Login error: ' . $e->getMessage());
}
?>
