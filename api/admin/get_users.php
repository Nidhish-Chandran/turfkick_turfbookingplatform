<?php
// api/admin/get_users.php
require_once '../../config/db.php';
require_once '../../includes/helpers.php';

require_admin();

try {
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'is_deleted'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_deleted TINYINT(1) DEFAULT 0");
    }

    $stmt = $pdo->query("
        SELECT
            u.id,
            u.name,
            u.email,
            u.phone,
            u.role,
            u.created_at,
            u.is_deleted,
            CASE WHEN u.role = 'owner' THEN COUNT(t.id) ELSE 0 END AS turf_count
        FROM users u
        LEFT JOIN turfs t ON t.owner_id = u.id
        WHERE u.is_deleted = 0
        GROUP BY u.id, u.name, u.email, u.phone, u.role, u.created_at, u.is_deleted
        ORDER BY u.created_at DESC
    ");
    $users = $stmt->fetchAll();
    send_json_response('success', 'Users fetched.', $users);
} catch (Exception $e) {
    send_json_response('error', 'Error: ' . $e->getMessage());
}
?>
