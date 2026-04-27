<?php
// api/admin/get_turfs.php
require_once '../../config/db.php';
require_once '../../includes/helpers.php';

require_admin();

try {
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'is_deleted'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_deleted TINYINT(1) DEFAULT 0");
    }
    $hasMaintenanceTable = (bool) $pdo->query("SHOW TABLES LIKE 'maintenance_requests'")->fetch();
    $pendingRequestsSelect = $hasMaintenanceTable
        ? "(SELECT COUNT(*) FROM maintenance_requests WHERE turf_id = t.id AND status = 'pending')"
        : "0";

    $stmt = $pdo->query("
        SELECT
            t.*,
            u.name AS owner_name,
            u.email AS owner_email,
            u.phone AS owner_phone,
            u.is_deleted AS owner_deleted,
            $pendingRequestsSelect AS pending_requests
        FROM turfs t 
        JOIN users u ON t.owner_id = u.id
        ORDER BY pending_requests DESC, t.status ASC, t.name ASC
    ");
    $turfs = $stmt->fetchAll();
    send_json_response('success', 'Turfs fetched.', $turfs);
} catch (Exception $e) {
    send_json_response('error', 'Error: ' . $e->getMessage());
}
?>
