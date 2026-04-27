<?php
// api/update_complaint.php
require_once '../config/db.php';
require_once '../includes/helpers.php';

if (!is_logged_in() || (!is_admin() && !is_owner())) {
    send_json_response('error', 'Unauthorized access.');
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$complaint_id = sanitize_input($_POST['complaint_id'] ?? 0);
$status = sanitize_input($_POST['status'] ?? 'Pending');
$response_text = sanitize_input($_POST['response'] ?? '');
$csrf_token = $_POST['csrf_token'] ?? '';

if (!validate_csrf_token($csrf_token)) {
    send_json_response('error', 'CSRF validation failed.');
}

try {
    // If owner, verify the complaint is for their turf
    if ($role === 'owner') {
        $stmt = $pdo->prepare("
            SELECT c.id FROM complaints c
            JOIN turfs t ON c.turf_id = t.id
            WHERE c.id = ? AND t.owner_id = ?
        ");
        $stmt->execute([$complaint_id, $user_id]);
        if (!$stmt->fetch()) {
            send_json_response('error', 'Access denied.');
        }
    }

    $stmt = $pdo->prepare("UPDATE complaints SET status = ?, response = ? WHERE id = ?");
    $stmt->execute([$status, $response_text, $complaint_id]);

    send_json_response('success', 'Complaint updated successfully.');
} catch (Exception $e) {
    send_json_response('error', 'Error: ' . $e->getMessage());
}
?>
