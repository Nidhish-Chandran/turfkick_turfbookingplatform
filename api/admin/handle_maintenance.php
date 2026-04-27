<?php
// api/admin/handle_maintenance.php
require_once '../../config/db.php';
require_once '../../includes/helpers.php';
require_once '../../includes/schema_helpers.php';

require_admin();
ensure_owner_feature_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response('error', 'Invalid method.');
}

$request_id = sanitize_input($_POST['request_id'] ?? 0);
$action = sanitize_input($_POST['action'] ?? 'approve'); // approved, rejected
$admin_comment = sanitize_input($_POST['comment'] ?? '');
$csrf_token = $_POST['csrf_token'] ?? '';

if (!validate_csrf_token($csrf_token)) {
    send_json_response('error', 'CSRF validation failed.');
}

try {
    $pdo->beginTransaction();

    // 1. Get request details
    $stmt = $pdo->prepare("SELECT * FROM maintenance_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $req = $stmt->fetch();

    if (!$req) throw new Exception('Request not found.');

    $status = ($action === 'approve') ? 'approved' : 'rejected';

    // 2. Update request
    $updateReq = $pdo->prepare("UPDATE maintenance_requests SET status = ?, admin_comment = ? WHERE id = ?");
    $updateReq->execute([$status, $admin_comment, $request_id]);

    // 3. If approved, apply change to turf
    if ($status === 'approved') {
        $is_maintenance = ($req['request_type'] === 'maintenance_disable') ? 1 : 0;
        $updateTurf = $pdo->prepare("UPDATE turfs SET is_under_maintenance = ? WHERE id = ?");
        $updateTurf->execute([$is_maintenance, $req['turf_id']]);
    }

    $pdo->commit();
    send_json_response('success', 'Request ' . $status . ' successfully.');
} catch (Exception $e) {
    $pdo->rollBack();
    send_json_response('error', $e->getMessage());
}
?>
