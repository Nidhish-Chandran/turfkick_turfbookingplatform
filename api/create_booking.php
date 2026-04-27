<?php
// api/create_booking.php
require_once '../config/db.php';
require_once '../includes/helpers.php';
require_once '../includes/schema_helpers.php';

if (!is_logged_in()) {
    send_json_response('error', 'Login required.');
}

$user_id = $_SESSION['user_id'];
ensure_owner_feature_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response('error', 'Invalid method.');
}

$turf_id = (int) ($_POST['turf_id'] ?? 0);
$slot_id = (int) ($_POST['slot_id'] ?? 0);
$date = sanitize_input($_POST['date'] ?? '');
$csrf_token = $_POST['csrf_token'] ?? '';
$equipment_ids = $_POST['equipment_ids'] ?? '[]'; // JSON string of IDs

if (!validate_csrf_token($csrf_token)) {
    send_json_response('error', 'CSRF validation failed.');
}

try {
    if (!$turf_id || !$slot_id || !$date) {
        send_json_response('error', 'Turf, slot, and date are required.');
    }

    $equipment = json_decode($equipment_ids, true);
    if (!is_array($equipment)) {
        $equipment = [];
    }
    $equipment = array_values(array_filter(array_map('intval', $equipment)));

    $stmt = $pdo->prepare("
        SELECT t.id, t.owner_id, t.price_per_hour, ts.day_of_week
        FROM turfs t
        JOIN time_slots ts ON ts.turf_id = t.id
        WHERE t.id = ? AND ts.id = ? AND t.status = 'active'
    ");
    $stmt->execute([$turf_id, $slot_id]);
    $bookingTarget = $stmt->fetch();

    if (!$bookingTarget) {
        send_json_response('error', 'Selected turf or slot is not available.');
    }

    if (!empty($bookingTarget['day_of_week']) && $bookingTarget['day_of_week'] !== date('l', strtotime($date))) {
        send_json_response('error', 'Selected slot is not available on this date.');
    }

    $total_price = (float) $bookingTarget['price_per_hour'];
    if ($equipment) {
        $placeholders = implode(',', array_fill(0, count($equipment), '?'));
        $params = array_merge([$bookingTarget['owner_id']], $equipment);
        $stmt = $pdo->prepare("SELECT id, price_per_session FROM equipment WHERE owner_id = ? AND status = 'active' AND id IN ($placeholders)");
        $stmt->execute($params);
        $validEquipment = $stmt->fetchAll();

        if (count($validEquipment) !== count($equipment)) {
            send_json_response('error', 'One or more selected equipment items are unavailable.');
        }

        foreach ($validEquipment as $item) {
            $total_price += (float) $item['price_per_session'];
        }
    }

    // 1. Double check availability
    $check = $pdo->prepare("SELECT id FROM bookings WHERE turf_id = ? AND slot_id = ? AND booking_date = ? AND status != 'cancelled'");
    $check->execute([$turf_id, $slot_id, $date]);
    if ($check->fetch()) {
        send_json_response('error', 'This slot was just booked by someone else.');
    }

    // 2. Create booking
    $stmt = $pdo->prepare("INSERT INTO bookings (user_id, turf_id, slot_id, equipment_ids, booking_date, total_price, status) VALUES (?, ?, ?, ?, ?, ?, 'upcoming')");
    $stmt->execute([$user_id, $turf_id, $slot_id, json_encode($equipment), $date, $total_price]);
    
    $booking_id = $pdo->lastInsertId();

    // 3. Create placeholder payment record
    $stmt = $pdo->prepare("INSERT INTO payments (booking_id, amount, payment_method, payment_status) VALUES (?, ?, 'Online', 'success')");
    $stmt->execute([$booking_id, $total_price]);

    send_json_response('success', 'Booking confirmed! See you at the turf.');
} catch (Exception $e) {
    send_json_response('error', 'Booking failed: ' . $e->getMessage());
}
?>
