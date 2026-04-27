<?php
// includes/schema_helpers.php

function column_exists($pdo, $table, $column) {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetch();
}

function table_exists($pdo, $table) {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$table]);
    return (bool) $stmt->fetch();
}

function ensure_owner_feature_schema($pdo) {
    if (!table_exists($pdo, 'turf_images')) {
        $pdo->exec("
            CREATE TABLE turf_images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                turf_id INT NOT NULL,
                image_path VARCHAR(255) NOT NULL,
                is_primary TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (turf_id) REFERENCES turfs(id) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ");
    }

    if (!table_exists($pdo, 'equipment')) {
        $pdo->exec("
            CREATE TABLE equipment (
                id INT AUTO_INCREMENT PRIMARY KEY,
                owner_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                price_per_session DECIMAL(10, 2) NOT NULL DEFAULT 0,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ");
    }

    if (!table_exists($pdo, 'maintenance_requests')) {
        $pdo->exec("
            CREATE TABLE maintenance_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                turf_id INT NOT NULL,
                owner_id INT NOT NULL,
                request_type VARCHAR(50) DEFAULT 'maintenance_disable',
                start_date DATE NULL,
                end_date DATE NULL,
                reason TEXT,
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (turf_id) REFERENCES turfs(id) ON DELETE CASCADE,
                FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ");
    }

    if (!column_exists($pdo, 'time_slots', 'day_of_week')) {
        $pdo->exec("ALTER TABLE time_slots ADD COLUMN day_of_week VARCHAR(20) NULL AFTER turf_id");
    }

    if (!column_exists($pdo, 'time_slots', 'slot_name')) {
        $pdo->exec("ALTER TABLE time_slots ADD COLUMN slot_name VARCHAR(100) NULL AFTER day_of_week");
    }

    if (!column_exists($pdo, 'bookings', 'equipment_ids')) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN equipment_ids TEXT NULL AFTER slot_id");
    }

    if (!column_exists($pdo, 'turfs', 'is_under_maintenance')) {
        $pdo->exec("ALTER TABLE turfs ADD COLUMN is_under_maintenance TINYINT(1) DEFAULT 0");
    }

    if (table_exists($pdo, 'maintenance_requests') && !column_exists($pdo, 'maintenance_requests', 'admin_comment')) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN admin_comment TEXT NULL");
    }
}

function safe_upload_image($file, $prefix = '') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        throw new Exception('Only JPG, PNG, WEBP, and GIF images are allowed.');
    }

    if (!is_dir('../uploads')) {
        mkdir('../uploads', 0777, true);
    }

    $filename = time() . '_' . $prefix . uniqid() . '.' . $ext;
    $target = '../uploads/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new Exception('Image upload failed.');
    }

    return 'uploads/' . $filename;
}
?>
