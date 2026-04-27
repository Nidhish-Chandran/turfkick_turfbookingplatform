<?php
// api/admin/get_bookings.php
require_once '../../config/db.php';
require_once '../../includes/helpers.php';

require_admin();

try {
    $stmt = $pdo->query("
        SELECT b.*, u.name as user_name, u.email as user_email, 
               t.name as turf_name, t.location, 
               ts.slot_label, ts.day_of_week,
               p.amount as paid_amount, p.status as payment_status
        FROM bookings b 
        JOIN users u ON b.user_id = u.id 
        JOIN turfs t ON b.turf_id = t.id 
        JOIN time_slots ts ON b.slot_id = ts.id 
        LEFT JOIN payments p ON b.id = p.booking_id
        ORDER BY b.booking_date DESC, b.created_at DESC
    ");
    $bookings = $stmt->fetchAll();
    send_json_response('success', 'Bookings fetched.', $bookings);
} catch (Exception $e) {
    send_json_response('error', 'Error: ' . $e->getMessage());
}
?>
