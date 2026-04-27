<?php
// api/admin/toggle_turf.php
require_once '../../config/db.php';
require_once '../../includes/helpers.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response('error', 'Invalid method.');
}

$turf_id = (int) ($_POST['turf_id'] ?? 0);
$status = sanitize_input($_POST['status'] ?? '');
$csrf_token = $_POST['csrf_token'] ?? '';

if (!validate_csrf_token($csrf_token)) {
    send_json_response('error', 'CSRF validation failed.');
}

if (!in_array($status, ['active', 'inactive', 'pending'])) {
    send_json_response('error', 'Invalid status.');
}

try {
    if ($turf_id <= 0) {
        send_json_response('error', 'Invalid turf.');
    }

    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'is_deleted'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_deleted TINYINT(1) DEFAULT 0");
    }

    $stmt = $pdo->prepare("
        SELECT t.id, u.is_deleted
        FROM turfs t
        JOIN users u ON u.id = t.owner_id
        WHERE t.id = ?
    ");
    $stmt->execute([$turf_id]);
    $turf = $stmt->fetch();

    if (!$turf) {
        send_json_response('error', 'Turf not found.');
    }

    if ((int) ($turf['is_deleted'] ?? 0) === 1 && $status === 'active') {
        send_json_response('error', 'Cannot enable a turf owned by a deleted owner.');
    }

    $stmt = $pdo->prepare("UPDATE turfs SET status = ? WHERE id = ?");
    $stmt->execute([$status, $turf_id]);
    send_json_response('success', "Turf status updated to $status.");
} catch (Exception $e) {
    send_json_response('error', 'Error: ' . $e->getMessage());
}
?>
