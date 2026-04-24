<?php
// api/manage_turfs.php
require_once '../config/db.php';
require_once '../includes/helpers.php';

if (!is_logged_in() || !is_owner()) {
    send_json_response('error', 'Unauthorized access.');
}

$owner_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM turfs WHERE owner_id = ?");
        $stmt->execute([$owner_id]);
        $turfs = $stmt->fetchAll();
        send_json_response('success', 'Turfs fetched successfully.', $turfs);
    } catch (Exception $e) {
        send_json_response('error', 'Error fetching turfs: ' . $e->getMessage());
    }
} elseif ($method === 'POST') {
    $action = $_POST['action'] ?? 'update';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!validate_csrf_token($csrf_token)) {
        send_json_response('error', 'CSRF token validation failed.');
    }

    if ($action === 'update') {
        $turf_id = sanitize_input($_POST['turf_id'] ?? 0);
        $name = sanitize_input($_POST['name'] ?? '');
        $price = sanitize_input($_POST['price'] ?? 0);
        $location = sanitize_input($_POST['location'] ?? '');
        $category = sanitize_input($_POST['category'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');

        try {
            $stmt = $pdo->prepare("UPDATE turfs SET name = ?, price_per_hour = ?, location = ?, sport_category = ?, description = ? WHERE id = ? AND owner_id = ?");
            $stmt->execute([$name, $price, $location, $category, $description, $turf_id, $owner_id]);
            
            if ($stmt->rowCount() > 0) {
                send_json_response('success', 'Turf updated successfully.');
            } else {
                send_json_response('error', 'No changes made or turf not found.');
            }
        } catch (Exception $e) {
            send_json_response('error', 'Update failed: ' . $e->getMessage());
        }
    } elseif ($action === 'add') {
        $name = sanitize_input($_POST['name'] ?? '');
        $price = sanitize_input($_POST['price'] ?? 0);
        $location = sanitize_input($_POST['location'] ?? '');
        $category = sanitize_input($_POST['category'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');

        try {
            $stmt = $pdo->prepare("INSERT INTO turfs (owner_id, name, price_per_hour, location, sport_category, description, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$owner_id, $name, $price, $location, $category, $description]);
            send_json_response('success', 'Turf added successfully and is pending approval.');
        } catch (Exception $e) {
            send_json_response('error', 'Add failed: ' . $e->getMessage());
        }
    } elseif ($action === 'delete') {
        $turf_id = sanitize_input($_POST['turf_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM turfs WHERE id = ? AND owner_id = ?");
            $stmt->execute([$turf_id, $owner_id]);
            send_json_response('success', 'Turf deleted successfully.');
        } catch (Exception $e) {
            send_json_response('error', 'Delete failed: ' . $e->getMessage());
        }
    }
} else {
    send_json_response('error', 'Invalid request method.');
}
?>
