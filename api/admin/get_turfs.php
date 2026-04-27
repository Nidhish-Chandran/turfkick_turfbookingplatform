<?php
// api/admin/get_turfs.php
require_once '../../config/db.php';
require_once '../../includes/helpers.php';

require_admin();

try {
    $stmt = $pdo->query("
        SELECT t.*, u.name as owner_name, u.email as owner_email,
        (SELECT COUNT(*) FROM bookings WHERE turf_id = t.id) as total_bookings,
        (SELECT COUNT(*) FROM comments WHERE turf_id = t.id) as total_comments
        FROM turfs t 
        JOIN users u ON t.owner_id = u.id 
        ORDER BY t.created_at DESC
    ");
    $turfs = $stmt->fetchAll();
    send_json_response('success', 'Turfs fetched.', $turfs);
} catch (Exception $e) {
    send_json_response('error', 'Error: ' . $e->getMessage());
}
?>
