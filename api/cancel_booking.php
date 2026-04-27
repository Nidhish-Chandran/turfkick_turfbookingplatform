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
    // 1. Fetch booking details to check time constraint
    $stmt = $pdo->prepare("
        SELECT b.booking_date, ts.start_time 
        FROM bookings b 
        JOIN time_slots ts ON b.slot_id = ts.id 
        WHERE b.id = ? AND b.user_id = ?
    ");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        send_json_response('error', 'Booking not found.');
    }

    // 2. Check time constraint (1 hour before)
    $booking_datetime = $booking['booking_date'] . ' ' . $booking['start_time'];
    $slot_time = strtotime($booking_datetime);
    $current_time = time();
    $diff_seconds = $slot_time - $current_time;

    if ($diff_seconds < 3600) {
        send_json_response('error', 'Cancellation is only allowed at least 1 hour before the slot time.');
    }

    // 3. Update status
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'upcoming'");
    $stmt->execute([$booking_id, $user_id]);

    if ($stmt->rowCount() > 0) {
        send_json_response('success', 'Booking cancelled successfully.');
    } else {
        send_json_response('error', 'Unable to cancel booking. It might be already processed or cancelled.');
    }
} catch (Exception $e) {
    send_json_response('error', 'Error: ' . $e->getMessage());
}
?>
