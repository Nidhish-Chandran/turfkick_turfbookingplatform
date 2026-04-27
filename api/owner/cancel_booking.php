<?php
// api/owner/cancel_booking.php
require_once '../../config/db.php';
require_once '../../includes/helpers.php';

if (!is_logged_in() || !is_owner()) {
    send_json_response('error', 'Unauthorized access.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response('error', 'Invalid request method');
}

$booking_id = sanitize_input($_POST['booking_id'] ?? 0);
$owner_id = $_SESSION['user_id'];
$csrf_token = $_POST['csrf_token'] ?? '';

if (!validate_csrf_token($csrf_token)) {
    send_json_response('error', 'CSRF token validation failed.');
}

try {
    // Ensure the booking belongs to a turf owned by this owner
    $stmt = $pdo->prepare("
        UPDATE bookings b
        JOIN turfs t ON b.turf_id = t.id
        SET b.status = 'cancelled'
        WHERE b.id = ? AND t.owner_id = ? AND b.status = 'upcoming'
    ");
    $stmt->execute([$booking_id, $owner_id]);

    if ($stmt->rowCount() > 0) {
        send_json_response('success', 'Booking cancelled by owner.');
    } else {
        send_json_response('error', 'Unable to cancel booking.');
    }
} catch (Exception $e) {
    send_json_response('error', 'Error: ' . $e->getMessage());
}
?>