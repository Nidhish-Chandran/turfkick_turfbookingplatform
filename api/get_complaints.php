<?php
// api/get_complaints.php
require_once '../config/db.php';
require_once '../includes/helpers.php';

if (!is_logged_in()) {
    send_json_response('error', 'Login required.');
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

try {
    if ($role === 'admin') {
        $stmt = $pdo->query("
            SELECT c.*, u.name as user_name, t.name as turf_name 
            FROM complaints c
            JOIN users u ON c.user_id = u.id
            JOIN turfs t ON c.turf_id = t.id
            ORDER BY c.created_at DESC
        ");
    } elseif ($role === 'owner') {
        $stmt = $pdo->prepare("
            SELECT c.*, u.name as user_name, t.name as turf_name 
            FROM complaints c
            JOIN users u ON c.user_id = u.id
            JOIN turfs t ON c.turf_id = t.id
            WHERE t.owner_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT c.*, t.name as turf_name 
            FROM complaints c
            JOIN turfs t ON c.turf_id = t.id
            WHERE c.user_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$user_id]);
    }

    $complaints = $stmt->fetchAll();
    send_json_response('success', 'Complaints fetched.', $complaints);
} catch (Exception $e) {
    send_json_response('error', 'Error: ' . $e->getMessage());
}
?>
