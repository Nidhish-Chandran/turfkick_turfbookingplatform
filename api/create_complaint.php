<?php
// api/create_complaint.php
require_once '../config/db.php';
require_once '../includes/helpers.php';

if (!is_logged_in()) {
    send_json_response('error', 'Login required.');
}

$user_id = $_SESSION['user_id'];
$turf_id = sanitize_input($_POST['turf_id'] ?? 0);
$message = sanitize_input($_POST['description'] ?? ''); // Map description from UI to message
$csrf_token = $_POST['csrf_token'] ?? '';

if (!validate_csrf_token($csrf_token)) {
    send_json_response('error', 'CSRF validation failed.');
}

if (empty($message) || !$turf_id) {
    send_json_response('error', 'Message and turf are required.');
}

try {
    $stmt = $pdo->prepare("INSERT INTO complaints (user_id, turf_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $turf_id, $message]);
    send_json_response('success', 'Complaint raised successfully.');
} catch (Exception $e) {
    send_json_response('error', 'Error: ' . $e->getMessage());
}
?>
