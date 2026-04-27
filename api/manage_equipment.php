<?php
// api/manage_equipment.php
require_once '../config/db.php';
require_once '../includes/helpers.php';
require_once '../includes/schema_helpers.php';

if (!is_logged_in() || !is_owner()) {
    send_json_response('error', 'Unauthorized access.');
}

$owner_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
ensure_owner_feature_schema($pdo);

if ($method === 'GET') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM equipment WHERE owner_id = ?");
        $stmt->execute([$owner_id]);
        $items = $stmt->fetchAll();
        send_json_response('success', 'Equipment fetched successfully.', $items);
    } catch (Exception $e) {
        send_json_response('error', 'Error fetching equipment: ' . $e->getMessage());
    }
} elseif ($method === 'POST') {
    $action = $_POST['action'] ?? 'add';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!validate_csrf_token($csrf_token)) {
        send_json_response('error', 'CSRF token validation failed.');
    }

    if ($action === 'add') {
        $name = sanitize_input($_POST['name'] ?? '');
        $price = sanitize_input($_POST['price'] ?? 0);

        if ($name === '' || !is_numeric($price) || $price < 0) {
            send_json_response('error', 'Valid item name and price are required.');
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO equipment (owner_id, name, price_per_session) VALUES (?, ?, ?)");
            $stmt->execute([$owner_id, $name, $price]);
            send_json_response('success', 'Equipment added successfully.');
        } catch (Exception $e) {
            send_json_response('error', 'Add failed: ' . $e->getMessage());
        }
    } elseif ($action === 'delete') {
        $item_id = (int) ($_POST['item_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM equipment WHERE id = ? AND owner_id = ?");
            $stmt->execute([$item_id, $owner_id]);
            send_json_response('success', 'Equipment deleted successfully.');
        } catch (Exception $e) {
            send_json_response('error', 'Delete failed: ' . $e->getMessage());
        }
    }
} else {
    send_json_response('error', 'Invalid request method.');
}
?>
