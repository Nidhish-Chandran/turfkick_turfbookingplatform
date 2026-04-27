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
    // 1. Verify booking exists and belongs to user
    $stmt = $pdo->prepare("SELECT turf_id FROM bookings WHERE id = ? AND user_id = ?");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        send_json_response('error', 'Booking not found.');
    }

    // 2. Insert review
    $stmt = $pdo->prepare("INSERT INTO reviews (booking_id, user_id, turf_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$booking_id, $user_id, $booking['turf_id'], $rating, $comment]);

    send_json_response('success', 'Thank you for your feedback!');
} catch (Exception $e) {
    send_json_response('error', 'Review failed: ' . $e->getMessage());
}
?>