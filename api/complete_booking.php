<?php
// api/complete_booking.php
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
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'CSRF token validation failed.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'completed' WHERE id = ? AND user_id = ? AND status = 'upcoming'");
    $stmt->execute([$booking_id, $user_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'status' => 'success',
            'message' => 'Booking marked as completed.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => 'Unable to complete booking. It might no longer be upcoming.'
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