<?php
// api/cancel_booking.php
require_once '../config/db.php';
require_once '../includes/helpers.php';

if (!is_logged_in()) {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Login required'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
    exit;
}

$booking_id = sanitize_input($_POST['booking_id'] ?? 0);
$user_id = $_SESSION['user_id'];
$csrf_token = $_POST['csrf_token'] ?? '';

if (!validate_csrf_token($csrf_token)) {
    send_json_response('error', 'CSRF token validation failed.');
}

try {
    // Ensure the booking belongs to the user and allow cancellation for upcoming or completed bookings
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?");
    $stmt->execute([$booking_id, $user_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'status' => 'success',
            'message' => 'Booking cancelled successfully.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => 'Unable to cancel booking. It may already be cancelled or does not belong to you.'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
