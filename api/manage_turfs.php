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
    $turf_id = sanitize_input($_GET['turf_id'] ?? 0);
    try {
        if ($turf_id && $turf_id !== '0') {
            // Fetch single turf with gallery
            $stmt = $pdo->prepare("SELECT * FROM turfs WHERE id = ? AND owner_id = ?");
            $stmt->execute([$turf_id, $owner_id]);
            $turf = $stmt->fetch();
            if ($turf) {
                $imgStmt = $pdo->prepare("SELECT * FROM turf_images WHERE turf_id = ?");
                $imgStmt->execute([$turf_id]);
                $turf['gallery'] = $imgStmt->fetchAll();
            }
            send_json_response('success', 'Turf details fetched.', $turf);
        } else {
            // Fetch all turfs for owner - Using ID for sorting as created_at doesn't exist
            $stmt = $pdo->prepare("SELECT * FROM turfs WHERE owner_id = ? ORDER BY id DESC");
            $stmt->execute([$owner_id]);
            $turfs = $stmt->fetchAll();
            send_json_response('success', 'Turfs fetched.', $turfs);
        }
    } catch (Exception $e) {
        send_json_response('error', 'Database error: ' . $e->getMessage());
    }
} elseif ($method === 'POST') {
    $action = $_POST['action'] ?? 'add';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!validate_csrf_token($csrf_token)) {
        send_json_response('error', 'CSRF validation failed.');
    }

    $name = sanitize_input($_POST['name'] ?? '');
    $price = sanitize_input($_POST['price'] ?? 0);
    $location = sanitize_input($_POST['location'] ?? '');
    $category = sanitize_input($_POST['category'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');

    if ($action === 'add') {
        try {
            $stmt = $pdo->prepare("INSERT INTO turfs (owner_id, name, price_per_hour, location, sport_category, description, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$owner_id, $name, $price, $location, $category, $description]);
            $new_id = $pdo->lastInsertId();
            
            // Handle primary image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $filename = time() . '_' . uniqid() . '.' . $ext;
                $target = '../uploads/' . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                    $path = 'uploads/' . $filename;
                    $pdo->prepare("UPDATE turfs SET image_path = ? WHERE id = ?")->execute([$path, $new_id]);
                    $pdo->prepare("INSERT INTO turf_images (turf_id, image_path, is_primary) VALUES (?, ?, 1)")->execute([$new_id, $path]);
                }
            }
            
            send_json_response('success', 'New turf added successfully.');
        } catch (Exception $e) {
            send_json_response('error', 'Add failed: ' . $e->getMessage());
        }
    } elseif ($action === 'update') {
        $turf_id = sanitize_input($_POST['turf_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("UPDATE turfs SET name = ?, price_per_hour = ?, location = ?, sport_category = ?, description = ? WHERE id = ? AND owner_id = ?");
            $stmt->execute([$name, $price, $location, $category, $description, $turf_id, $owner_id]);
            
            // Primary image update
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $filename = time() . '_' . uniqid() . '.' . $ext;
                $target = '../uploads/' . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                    $path = 'uploads/' . $filename;
                    $pdo->prepare("UPDATE turfs SET image_path = ? WHERE id = ?")->execute([$path, $turf_id]);
                    $pdo->prepare("INSERT INTO turf_images (turf_id, image_path, is_primary) VALUES (?, ?, 1)")->execute([$turf_id, $path]);
                }
            }
            send_json_response('success', 'Turf updated successfully.');
        } catch (Exception $e) {
            send_json_response('error', 'Update failed: ' . $e->getMessage());
        }
    } elseif ($action === 'delete_image') {
        $image_id = sanitize_input($_POST['image_id'] ?? 0);
        try {
            // Verify ownership through join
            $stmt = $pdo->prepare("SELECT ti.* FROM turf_images ti JOIN turfs t ON ti.turf_id = t.id WHERE ti.id = ? AND t.owner_id = ?");
            $stmt->execute([$image_id, $owner_id]);
            $img = $stmt->fetch();
            
            if ($img) {
                // Delete file
                if (file_exists('../' . $img['image_path'])) {
                    unlink('../' . $img['image_path']);
                }
                // Delete from DB
                $pdo->prepare("DELETE FROM turf_images WHERE id = ?")->execute([$image_id]);
                send_json_response('success', 'Image removed.');
            } else {
                send_json_response('error', 'Image not found.');
            }
        } catch (Exception $e) {
            send_json_response('error', 'Delete failed: ' . $e->getMessage());
        }
    }
}
?>