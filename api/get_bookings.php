<?php
// api/get_bookings.php
require_once '../config/db.php';
require_once '../includes/helpers.php';

if (!is_logged_in()) {
    send_json_response('error', 'Login required');
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT b.*, t.name as turf_name, t.location, t.image_path, ts.slot_label, ts.start_time
        FROM bookings b
        JOIN turfs t ON b.turf_id = t.id
        JOIN time_slots ts ON b.slot_id = ts.id
        WHERE b.user_id = ?
        ORDER BY b.booking_date DESC, ts.start_time DESC
    ");
    $stmt->execute([$user_id]);
    $bookings = $stmt->fetchAll();
    send_json_response('success', 'Bookings fetched successfully.', $bookings);
} catch (Exception $e) {
    send_json_response('error', 'Error fetching bookings: ' . $e->getMessage());
}
?>
