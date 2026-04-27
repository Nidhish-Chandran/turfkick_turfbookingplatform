-- Master Fix for TurfKick

USE turfkick_db;

-- 1. Fix Users table
ALTER TABLE users MODIFY COLUMN role ENUM('user', 'owner', 'admin') DEFAULT 'user';

-- 2. Fix Time Slots table
ALTER TABLE time_slots ADD COLUMN IF NOT EXISTS day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL;
ALTER TABLE time_slots ADD COLUMN IF NOT EXISTS slot_name VARCHAR(100);

-- 3. Fix Bookings table
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS equipment_ids TEXT;

-- 4. Fix Payments table
ALTER TABLE payments CHANGE COLUMN payment_status status ENUM('pending', 'success', 'failed', 'completed') DEFAULT 'pending';

-- 5. Fix Equipment table
-- Drop existing foreign key if it exists
SET @fk_name = (SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'equipment' AND COLUMN_NAME = 'owner_id' AND TABLE_SCHEMA = 'turfkick_db');
SET @s = IF(@fk_name IS NOT NULL, CONCAT('ALTER TABLE equipment DROP FOREIGN KEY ', @fk_name), 'SELECT "No FK to drop"');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Transform columns
ALTER TABLE equipment CHANGE COLUMN owner_id turf_id INT NOT NULL;
ALTER TABLE equipment CHANGE COLUMN price_per_session price DECIMAL(10,2) NOT NULL;
ALTER TABLE equipment ADD CONSTRAINT fk_equipment_turf FOREIGN KEY (turf_id) REFERENCES turfs(id) ON DELETE CASCADE;

-- 6. Reviews / Comments System
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    turf_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (turf_id) REFERENCES turfs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comment_id INT NOT NULL,
    owner_id INT NOT NULL,
    reply TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. Complaint System
CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    turf_id INT NOT NULL,
    message TEXT NOT NULL,
    status ENUM('Pending', 'In Progress', 'Resolved') DEFAULT 'Pending',
    response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (turf_id) REFERENCES turfs(id) ON DELETE CASCADE
) ENGINE=InnoDB;
