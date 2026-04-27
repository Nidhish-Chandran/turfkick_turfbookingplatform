<?php
// api/admin/cancel_booking.php
require_once '../../config/db.php';
require_once '../../includes/helpers.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response('error', 'Invalid method.');
}

$booking_id = (int) ($_POST['booking_id'] ?? 0);
$csrf_token = $_POST['csrf_token'] ?? '';

if (!validate_csrf_token($csrf_token)) {
    send_json_response('error', 'CSRF validation failed.');
}

try {
    if ($booking_id <= 0) {
        send_json_response('error', 'Invalid booking.');
    }

    $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND status = 'upcoming'");
    $stmt->execute([$booking_id]);

    if ($stmt->rowCount() === 0) {
        send_json_response('error', 'Booking not found or already closed.');
    }

    send_json_response('success', 'Booking cancelled by admin.');
} catch (Exception $e) {
    send_json_response('error', 'Error: ' . $e->getMessage());
}
?>
