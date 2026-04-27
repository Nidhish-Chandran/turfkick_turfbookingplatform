<?php
// api/get_bookings.php
require_once '../config/db.php';
require_once '../includes/helpers.php';

if (!is_logged_in()) {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Login required',
        'bookings' => []
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare(
        "SELECT b.*, t.name AS turf_name, t.location, t.image_path, ts.slot_label, ts.start_time, " .
        "CASE WHEN b.status = 'cancelled' THEN 'cancelled' " .
        "WHEN b.booking_date < CURDATE() THEN 'completed' " .
        "ELSE 'upcoming' END AS display_status " .
        "FROM bookings b " .
        "JOIN turfs t ON b.turf_id = t.id " .
        "JOIN time_slots ts ON b.slot_id = ts.id " .
        "WHERE b.user_id = ? " .
        "ORDER BY b.booking_date DESC, ts.start_time DESC"
    );
    $stmt->execute([$user_id]);
    $bookings = $stmt->fetchAll();
    echo json_encode([
        'success' => true,
        'status' => 'success',
        'message' => 'Bookings fetched successfully.',
        'bookings' => $bookings,
        'data' => $bookings
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Error fetching bookings: ' . $e->getMessage(),
        'bookings' => []
    ]);
}
?>
