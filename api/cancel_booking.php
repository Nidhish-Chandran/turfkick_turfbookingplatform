<?php
// api/cancel_booking.php
require_once '../config/db.php';
require_once '../includes/helpers.php';

if (!is_logged_in()) {
    send_json_response('error', 'Login required');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response('error', 'Invalid request method');
}

$booking_id = sanitize_input($_POST['booking_id'] ?? 0);
$user_id = $_SESSION['user_id'];
$csrf_token = $_POST['csrf_token'] ?? '';

if (!validate_csrf_token($csrf_token)) {
    send_json_response('error', 'CSRF token validation failed.');
}

try {
    // Ensure the booking belongs to the user and is still upcoming
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'upcoming'");
    $stmt->execute([$booking_id, $user_id]);

    if ($stmt->rowCount() > 0) {
        send_json_response('success', 'Booking cancelled successfully.');
    } else {
        send_json_response('error', 'Unable to cancel booking. It might be too late or already cancelled.');
    }
} catch (Exception $e) {
    send_json_response('error', 'Error: ' . $e->getMessage());
}
?>
