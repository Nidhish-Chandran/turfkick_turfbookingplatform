<?php
// api/submit_review.php
require_once '../config/db.php';
require_once '../includes/helpers.php';

if (!is_logged_in()) {
    send_json_response('error', 'Login required.');
}

$user_id = $_SESSION['user_id'];
$booking_id = sanitize_input($_POST['booking_id'] ?? 0);
$rating = sanitize_input($_POST['rating'] ?? 5);
$comment = sanitize_input($_POST['comment'] ?? '');
$csrf_token = $_POST['csrf_token'] ?? '';

if (!validate_csrf_token($csrf_token)) {
    send_json_response('error', 'CSRF validation failed.');
}

try {
    // Verify booking belongs to user
    $stmt = $pdo->prepare("SELECT turf_id FROM bookings WHERE id = ? AND user_id = ?");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch();
    if (!$booking) throw new Exception("Booking not found.");

    $stmt = $pdo->prepare("INSERT INTO comments (user_id, turf_id, comment) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $booking['turf_id'], $comment]);

    send_json_response('success', 'Review submitted successfully!');
} catch (Exception $e) {
    send_json_response('error', 'Error: ' . $e->getMessage());
}
?>
