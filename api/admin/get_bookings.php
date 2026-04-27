<?php
// api/admin/get_bookings.php
require_once '../../config/db.php';
require_once '../../includes/helpers.php';

require_admin();

try {
    $stmt = $pdo->query("
        SELECT
            b.*,
            u.name AS user_name,
            u.email AS user_email,
            t.name AS turf_name,
            t.location AS turf_location,
            owner.name AS owner_name,
            owner.email AS owner_email,
            ts.slot_label
        FROM bookings b 
        LEFT JOIN users u ON b.user_id = u.id 
        LEFT JOIN turfs t ON b.turf_id = t.id
        LEFT JOIN users owner ON t.owner_id = owner.id
        LEFT JOIN time_slots ts ON b.slot_id = ts.id 
        ORDER BY b.booking_date DESC, b.created_at DESC
    ");
    $bookings = $stmt->fetchAll();
    send_json_response('success', 'Bookings fetched.', $bookings);
} catch (Exception $e) {
    send_json_response('error', 'Error: ' . $e->getMessage());
}
?>
