<?php
// api/maintenance_requests.php
require_once '../config/db.php';
require_once '../includes/helpers.php';

if (!is_logged_in() || !is_owner()) {
    send_json_response('error', 'Unauthorized access.');
}

$owner_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $turf_id = sanitize_input($_GET['turf_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT * FROM maintenance_requests WHERE turf_id = ? AND owner_id = ? ORDER BY created_at DESC");
        $stmt->execute([$turf_id, $owner_id]);
        $requests = $stmt->fetchAll();
        send_json_response('success', 'Requests fetched.', $requests);
    } catch (Exception $e) {
        send_json_response('error', 'Error: ' . $e->getMessage());
    }
} elseif ($method === 'POST') {
    $turf_id = sanitize_input($_POST['turf_id'] ?? 0);
    $type = sanitize_input($_POST['type'] ?? 'maintenance_disable');
    $start = sanitize_input($_POST['start_date'] ?? null);
    $end = sanitize_input($_POST['end_date'] ?? null);
    $reason = sanitize_input($_POST['reason'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!validate_csrf_token($csrf_token)) {
        send_json_response('error', 'CSRF validation failed.');
    }

    try {
        // 1. Verify ownership
        $check = $pdo->prepare("SELECT id FROM turfs WHERE id = ? AND owner_id = ?");
        $check->execute([$turf_id, $owner_id]);
        if (!$check->fetch()) send_json_response('error', 'Access denied.');

        // 2. Check if a pending request already exists for this turf
        $pending = $pdo->prepare("SELECT id FROM maintenance_requests WHERE turf_id = ? AND status = 'pending'");
        $pending->execute([$turf_id]);
        if ($pending->fetch()) {
            send_json_response('error', 'A request is already pending for this turf.');
        }

        $stmt = $pdo->prepare("INSERT INTO maintenance_requests (turf_id, owner_id, request_type, start_date, end_date, reason) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$turf_id, $owner_id, $type, $start, $end, $reason]);
        
        send_json_response('success', 'Request sent to admin for approval.');
    } catch (Exception $e) {
        send_json_response('error', 'Failed to send request: ' . $e->getMessage());
    }
}
?>