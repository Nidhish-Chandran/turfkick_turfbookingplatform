<?php
// api/manage_turfs.php
require_once '../config/db.php';
require_once '../includes/helpers.php';
require_once '../includes/schema_helpers.php';

if (!is_logged_in() || !is_owner()) {
    send_json_response('error', 'Unauthorized access.');
}

ensure_owner_feature_schema($pdo);

$owner_id = $_SESSION['user_id'];
error_log("Owner ID: " . $owner_id);
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
                $imgStmt = $pdo->prepare("SELECT * FROM turf_images WHERE turf_id = ? ORDER BY is_primary DESC, id DESC");
                $imgStmt->execute([$turf_id]);
                $turf['gallery'] = $imgStmt->fetchAll();
            }
            send_json_response('success', 'Turf details fetched.', $turf);
        } else {
            // Fetch all turfs for owner - Using ID for sorting as created_at doesn't exist
            $stmt = $pdo->prepare("SELECT * FROM turfs WHERE owner_id = ? ORDER BY id DESC");
            $stmt->execute([$owner_id]);
            $turfs = $stmt->fetchAll();
            foreach ($turfs as &$turf) {
                $imgStmt = $pdo->prepare("SELECT image_path FROM turf_images WHERE turf_id = ? ORDER BY is_primary DESC, id DESC LIMIT 1");
                $imgStmt->execute([$turf['id']]);
                $primaryImage = $imgStmt->fetch();
                if (empty($turf['image_path']) && $primaryImage) {
                    $turf['image_path'] = $primaryImage['image_path'];
                }
            }
            error_log("Turfs count: " . count($turfs));
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
                $path = safe_upload_image($_FILES['image'], 'turf_');
                $pdo->prepare("UPDATE turfs SET image_path = ? WHERE id = ?")->execute([$path, $new_id]);
                $pdo->prepare("INSERT INTO turf_images (turf_id, image_path, is_primary) VALUES (?, ?, 1)")->execute([$new_id, $path]);
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
                $path = safe_upload_image($_FILES['image'], 'turf_');
                $pdo->prepare("UPDATE turfs SET image_path = ? WHERE id = ?")->execute([$path, $turf_id]);
                $pdo->prepare("UPDATE turf_images SET is_primary = 0 WHERE turf_id = ?")->execute([$turf_id]);
                $pdo->prepare("INSERT INTO turf_images (turf_id, image_path, is_primary) VALUES (?, ?, 1)")->execute([$turf_id, $path]);
            }
            send_json_response('success', 'Turf updated successfully.');
        } catch (Exception $e) {
            send_json_response('error', 'Update failed: ' . $e->getMessage());
        }
    } elseif ($action === 'upload_gallery') {
        $turf_id = sanitize_input($_POST['turf_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("SELECT id FROM turfs WHERE id = ? AND owner_id = ?");
            $stmt->execute([$turf_id, $owner_id]);
            if (!$stmt->fetch()) {
                send_json_response('error', 'Turf not found.');
            }

            if (!isset($_FILES['images'])) {
                send_json_response('error', 'Choose at least one image.');
            }

            $uploaded = 0;
            foreach ($_FILES['images']['name'] as $index => $name) {
                $file = [
                    'name' => $_FILES['images']['name'][$index],
                    'type' => $_FILES['images']['type'][$index],
                    'tmp_name' => $_FILES['images']['tmp_name'][$index],
                    'error' => $_FILES['images']['error'][$index],
                    'size' => $_FILES['images']['size'][$index],
                ];
                $path = safe_upload_image($file, 'gallery_');
                $pdo->prepare("INSERT INTO turf_images (turf_id, image_path, is_primary) VALUES (?, ?, 0)")->execute([$turf_id, $path]);
                $uploaded++;
            }

            send_json_response('success', "$uploaded image(s) uploaded.");
        } catch (Exception $e) {
            send_json_response('error', 'Upload failed: ' . $e->getMessage());
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
