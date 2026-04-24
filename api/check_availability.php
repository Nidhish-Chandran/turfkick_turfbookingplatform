<?php
// api/check_availability.php
require_once '../config/db.php';
require_once '../includes/helpers.php';

$turf_id = sanitize_input($_GET['turf_id'] ?? 0);
$date = sanitize_input($_GET['date'] ?? '');
$slot_id = sanitize_input($_GET['slot_id'] ?? 0);

if (!$turf_id || !$date || !$slot_id) {
    send_json_response('error', 'Missing parameters');
}

try {
    $stmt = $pdo->prepare("SELECT id FROM bookings WHERE turf_id = ? AND booking_date = ? AND slot_id = ? AND status != 'cancelled'");
    $stmt->execute([$turf_id, $date, $slot_id]);
    $booking = $stmt->fetch();

    if ($booking) {
        send_json_response('success', 'Slot already booked', ['available' => false]);
    } else {
        send_json_response('success', 'Slot is available', ['available' => true]);
    }
} catch (Exception $e) {
    send_json_response('error', 'Error: ' . $e->getMessage());
}
?>
