<?php
// api/get_slots.php
require_once '../config/db.php';
require_once '../includes/helpers.php';

$turf_id = sanitize_input($_GET['turf_id'] ?? 0);

if (!$turf_id) {
    send_json_response('error', 'Turf ID is required.');
}

try {
    $stmt = $pdo->prepare("SELECT * FROM time_slots WHERE turf_id = ?");
    $stmt->execute([$turf_id]);
    $slots = $stmt->fetchAll();
    send_json_response('success', 'Slots fetched successfully.', $slots);
} catch (Exception $e) {
    send_json_response('error', 'Error fetching slots: ' . $e->getMessage());
}
?>
