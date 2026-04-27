<?php
// api/get_slots.php
require_once '../config/db.php';
require_once '../includes/helpers.php';
require_once '../includes/schema_helpers.php';

ensure_owner_feature_schema($pdo);

$turf_id = sanitize_input($_GET['turf_id'] ?? 0);
$date = sanitize_input($_GET['date'] ?? '');

if (!$turf_id) {
    send_json_response('error', 'Turf ID is required.');
}

try {
    if ($date) {
        $day = date('l', strtotime($date));
        $stmt = $pdo->prepare("
            SELECT * FROM time_slots
            WHERE turf_id = ? AND (day_of_week = ? OR day_of_week IS NULL OR day_of_week = '')
            ORDER BY start_time ASC
        ");
        $stmt->execute([$turf_id, $day]);
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM time_slots
            WHERE turf_id = ?
            ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), start_time ASC
        ");
        $stmt->execute([$turf_id]);
    }
    $slots = $stmt->fetchAll();
    send_json_response('success', 'Slots fetched successfully.', $slots);
} catch (Exception $e) {
    send_json_response('error', 'Error fetching slots: ' . $e->getMessage());
}
?>
