-- Add soft delete flag (recommended instead of hard delete)
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) DEFAULT 0;

-- Add index for performance
CREATE INDEX idx_users_role ON users(role);

-- Owner dashboard feature tables and columns
CREATE TABLE IF NOT EXISTS turf_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    turf_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (turf_id) REFERENCES turfs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    price_per_session DECIMAL(10, 2) NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS maintenance_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    turf_id INT NOT NULL,
    owner_id INT NOT NULL,
    request_type VARCHAR(50) DEFAULT 'maintenance_disable',
    start_date DATE,
    end_date DATE,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (turf_id) REFERENCES turfs(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE time_slots ADD COLUMN IF NOT EXISTS day_of_week VARCHAR(20) NULL AFTER turf_id;
ALTER TABLE time_slots ADD COLUMN IF NOT EXISTS slot_name VARCHAR(100) NULL AFTER day_of_week;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS equipment_ids TEXT NULL AFTER slot_id;
ALTER TABLE turfs ADD COLUMN IF NOT EXISTS is_under_maintenance TINYINT(1) DEFAULT 0;
