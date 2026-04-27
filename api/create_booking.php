<?php
// api/create_booking.php
require_once '../config/db.php';
require_once '../includes/helpers.php';

if (!is_logged_in()) {
    send_json_response('error', 'Login required.');
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response('error', 'Invalid method.');
}

$turf_id = sanitize_input($_POST['turf_id'] ?? 0);
$slot_id = sanitize_input($_POST['slot_id'] ?? 0);
$date = sanitize_input($_POST['date'] ?? '');
$csrf_token = $_POST['csrf_token'] ?? '';
$equipment_ids_json = $_POST['equipment_ids'] ?? '[]'; // JSON string of IDs
$equipment_ids = json_decode($equipment_ids_json, true);

if (!validate_csrf_token($csrf_token)) {
    send_json_response('error', 'CSRF validation failed.');
}

try {
    $pdo->beginTransaction();

    // 1. Fetch Turf Price
    $stmt = $pdo->prepare("SELECT price_per_hour FROM turfs WHERE id = ?");
    $stmt->execute([$turf_id]);
    $turf = $stmt->fetch();
    if (!$turf) throw new Exception("Turf not found.");
    $total_price = $turf['price_per_hour'];

    // 2. Add Equipment Prices
    if (!empty($equipment_ids)) {
        $in  = str_repeat('?,', count($equipment_ids) - 1) . '?';
        $sql = "SELECT price FROM equipment WHERE id IN ($in) AND turf_id = ?";
        $stmt = $pdo->prepare($sql);
        $params = array_merge($equipment_ids, [$turf_id]);
        $stmt->execute($params);
        $items = $stmt->fetchAll();
        foreach ($items as $item) {
            $total_price += $item['price'];
        }
    }

    // 3. Double check availability
    $check = $pdo->prepare("SELECT id FROM bookings WHERE turf_id = ? AND slot_id = ? AND booking_date = ? AND status != 'cancelled'");
    $check->execute([$turf_id, $slot_id, $date]);
    if ($check->fetch()) {
        throw new Exception('This slot was just booked by someone else.');
    }

    // 4. Create booking
    $stmt = $pdo->prepare("INSERT INTO bookings (user_id, turf_id, slot_id, equipment_ids, booking_date, total_price, status) VALUES (?, ?, ?, ?, ?, ?, 'upcoming')");
    $stmt->execute([$user_id, $turf_id, $slot_id, $equipment_ids_json, $date, $total_price]);
    
    $booking_id = $pdo->lastInsertId();

    // 5. Create payment record
    $stmt = $pdo->prepare("INSERT INTO payments (booking_id, amount, payment_method, status) VALUES (?, ?, 'Online', 'completed')");
    $stmt->execute([$booking_id, $total_price]);

    $pdo->commit();
    send_json_response('success', 'Booking confirmed! Total: ₹' . $total_price);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    send_json_response('error', 'Booking failed: ' . $e->getMessage());
}
?>
