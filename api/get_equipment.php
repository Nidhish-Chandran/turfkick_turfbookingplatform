<?php
// api/get_equipment.php
require_once '../config/db.php';
require_once '../includes/helpers.php';
require_once '../includes/schema_helpers.php';

ensure_owner_feature_schema($pdo);

$turf_id = sanitize_input($_GET['turf_id'] ?? 0);

if (!$turf_id) {
    send_json_response('error', 'Turf ID required.');
}

try {
    // First, find the owner_id of the turf
    $stmt = $pdo->prepare("SELECT owner_id FROM turfs WHERE id = ?");
    $stmt->execute([$turf_id]);
    $turf = $stmt->fetch();

    if (!$turf) {
        send_json_response('error', 'Turf not found.');
    }

    $owner_id = $turf['owner_id'];

    // Now fetch equipment for this owner
    $stmt = $pdo->prepare("SELECT * FROM equipment WHERE owner_id = ? AND status = 'active'");
    $stmt->execute([$owner_id]);
    $items = $stmt->fetchAll();

    send_json_response('success', 'Equipment fetched successfully.', $items);
} catch (Exception $e) {
    send_json_response('error', 'Error: ' . $e->getMessage());
}
?>
