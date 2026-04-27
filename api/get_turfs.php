<?php
// api/get_turfs.php
require_once '../config/db.php';
require_once '../includes/helpers.php';
require_once '../includes/schema_helpers.php';

ensure_owner_feature_schema($pdo);

try {
    $stmt = $pdo->query(
        "SELECT t.* FROM turfs t " .
        "JOIN users u ON t.owner_id = u.id " .
        "WHERE t.status = 'active' AND u.is_deleted = 0"
    );
    $turfs = $stmt->fetchAll();
    foreach ($turfs as &$turf) {
        $imgStmt = $pdo->prepare("SELECT id, image_path, is_primary FROM turf_images WHERE turf_id = ? ORDER BY is_primary DESC, id DESC");
        $imgStmt->execute([$turf['id']]);
        $turf['gallery'] = $imgStmt->fetchAll();
    }
    send_json_response('success', 'Turfs fetched successfully.', $turfs);
} catch (Exception $e) {
    send_json_response('error', 'Error fetching turfs: ' . $e->getMessage());
}
?>
