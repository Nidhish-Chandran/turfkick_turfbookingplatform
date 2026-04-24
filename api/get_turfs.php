<?php
// api/get_turfs.php
require_once '../config/db.php';
require_once '../includes/helpers.php';

try {
    $stmt = $pdo->query("SELECT * FROM turfs WHERE status = 'active'");
    $turfs = $stmt->fetchAll();
    send_json_response('success', 'Turfs fetched successfully.', $turfs);
} catch (Exception $e) {
    send_json_response('error', 'Error fetching turfs: ' . $e->getMessage());
}
?>
