<?php
// api/owner/get_bookings.php
require_once '../../config/db.php';
require_once '../../includes/helpers.php';
require_once '../../includes/schema_helpers.php';

if (!is_logged_in() || !is_owner()) {
    send_json_response('error', 'Unauthorized access.');
}

$owner_id = $_SESSION['user_id'];
ensure_owner_feature_schema($pdo);

try {
    // Fetch bookings for turfs owned by this owner
    $stmt = $pdo->prepare("
        SELECT b.*, u.name as user_name, ts.slot_label, t.name as turf_name
        FROM bookings b
        JOIN turfs t ON b.turf_id = t.id
        JOIN users u ON b.user_id = u.id
        JOIN time_slots ts ON b.slot_id = ts.id
        WHERE t.owner_id = ?
        ORDER BY b.booking_date DESC, ts.start_time DESC
    ");
    $stmt->execute([$owner_id]);
    $bookings = $stmt->fetchAll();

    foreach ($bookings as &$booking) {
        $ids = json_decode($booking['equipment_ids'] ?? '[]', true);
        if (!is_array($ids) || count($ids) === 0) {
            $booking['equipment_names'] = [];
            continue;
        }

        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            $booking['equipment_names'] = [];
            continue;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT name FROM equipment WHERE owner_id = ? AND id IN ($placeholders)");
        $stmt->execute(array_merge([$owner_id], $ids));
        $booking['equipment_names'] = array_column($stmt->fetchAll(), 'name');
    }
    
    send_json_response('success', 'Bookings fetched successfully.', $bookings);
} catch (Exception $e) {
    send_json_response('error', 'Error: ' . $e->getMessage());
}
?>
