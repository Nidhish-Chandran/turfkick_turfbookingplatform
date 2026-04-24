<?php
// api/create_booking.php
require_once '../config/db.php';
require_once '../includes/helpers.php';

if (!is_logged_in()) {
    send_json_response('error', 'Login required');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response('error', 'Invalid request method');
}

$user_id = $_SESSION['user_id'];
$turf_id = sanitize_input($_POST['turf_id'] ?? 0);
$slot_id = sanitize_input($_POST['slot_id'] ?? 0);
$date = sanitize_input($_POST['date'] ?? '');
$price = sanitize_input($_POST['price'] ?? 0);
$csrf_token = $_POST['csrf_token'] ?? '';

if (!validate_csrf_token($csrf_token)) {
    send_json_response('error', 'CSRF token validation failed.');
}

if (!$turf_id || !$slot_id || !$date) {
    send_json_response('error', 'Missing booking details');
}

try {
    $pdo->beginTransaction();

    // 1. Double check availability with FOR UPDATE to lock the check and prevent race conditions
    $stmt = $pdo->prepare("SELECT id FROM bookings WHERE turf_id = ? AND booking_date = ? AND slot_id = ? AND status != 'cancelled' FOR UPDATE");
    $stmt->execute([$turf_id, $date, $slot_id]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        send_json_response('error', 'Slot was just booked by someone else.');
    }

    // 2. Insert booking
    $stmt = $pdo->prepare("INSERT INTO bookings (user_id, turf_id, slot_id, booking_date, total_price) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $turf_id, $slot_id, $date, $price]);
    $booking_id = $pdo->lastInsertId();

    // 3. Create pending payment
    $stmt = $pdo->prepare("INSERT INTO payments (booking_id, amount, payment_status) VALUES (?, ?, 'pending')");
    $stmt->execute([$booking_id, $price]);

    $pdo->commit();
    send_json_response('success', 'Booking confirmed!', ['booking_id' => $booking_id]);
} catch (Exception $e) {
    $pdo->rollBack();
    send_json_response('error', 'Booking failed: ' . $e->getMessage());
}
?>
