<?php
// api/admin/delete_user.php
require_once '../../config/db.php';
require_once '../../includes/helpers.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response('error', 'Invalid method.');
}

$user_id = (int) ($_POST['user_id'] ?? 0);
$csrf_token = $_POST['csrf_token'] ?? '';

if (!validate_csrf_token($csrf_token)) {
    send_json_response('error', 'CSRF validation failed.');
}

try {
    if ($user_id <= 0) {
        send_json_response('error', 'Invalid user.');
    }

    if ($user_id === (int) ($_SESSION['user_id'] ?? 0)) {
        send_json_response('error', 'You cannot delete your own admin account.');
    }

    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'is_deleted'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_deleted TINYINT(1) DEFAULT 0");
    }

    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        send_json_response('error', 'User not found.');
    }

    if ($user['role'] === 'admin') {
        send_json_response('error', 'Admin accounts cannot be deleted from this panel.');
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE users SET is_deleted = 1 WHERE id = ?");
    $stmt->execute([$user_id]);

    if ($user['role'] === 'owner') {
        $stmt = $pdo->prepare("UPDATE turfs SET status = 'inactive' WHERE owner_id = ?");
        $stmt->execute([$user_id]);
    }

    $pdo->commit();

    send_json_response('success', 'User deleted successfully.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    send_json_response('error', 'Error: ' . $e->getMessage());
}
?>
