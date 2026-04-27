<?php
// api/manage_equipment.php
require_once '../config/db.php';
require_once '../includes/helpers.php';

if (!is_logged_in() || !is_owner()) {
    send_json_response('error', 'Unauthorized access.');
}

$owner_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $turf_id = sanitize_input($_GET['turf_id'] ?? 0);
    try {
        if ($turf_id) {
            // Verify ownership
            $check = $pdo->prepare("SELECT id FROM turfs WHERE id = ? AND owner_id = ?");
            $check->execute([$turf_id, $owner_id]);
            if (!$check->fetch()) send_json_response('error', 'Access denied.');

            $stmt = $pdo->prepare("SELECT * FROM equipment WHERE turf_id = ?");
            $stmt->execute([$turf_id]);
        } else {
            // Get all equipment for all owner's turfs
            $stmt = $pdo->prepare("SELECT e.*, t.name as turf_name FROM equipment e JOIN turfs t ON e.turf_id = t.id WHERE t.owner_id = ?");
            $stmt->execute([$owner_id]);
        }
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
        $turf_id = sanitize_input($_POST['turf_id'] ?? 0);
        $name = sanitize_input($_POST['name'] ?? '');
        $price = sanitize_input($_POST['price'] ?? 0);

        try {
            // Verify ownership of turf
            $check = $pdo->prepare("SELECT id FROM turfs WHERE id = ? AND owner_id = ?");
            $check->execute([$turf_id, $owner_id]);
            if (!$check->fetch()) throw new Exception('Access denied.');

            $stmt = $pdo->prepare("INSERT INTO equipment (turf_id, name, price) VALUES (?, ?, ?)");
            $stmt->execute([$turf_id, $name, $price]);
            send_json_response('success', 'Equipment added successfully.');
        } catch (Exception $e) {
            send_json_response('error', 'Add failed: ' . $e->getMessage());
        }
    } elseif ($action === 'delete') {
        $item_id = sanitize_input($_POST['item_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE e FROM equipment e JOIN turfs t ON e.turf_id = t.id WHERE e.id = ? AND t.owner_id = ?");
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
