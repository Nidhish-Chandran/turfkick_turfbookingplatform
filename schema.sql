-- TurfKick Database Schema

CREATE DATABASE IF NOT EXISTS turfkick_db;
USE turfkick_db;

-- 1. Users table (Handles both regular users and turf owners)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'owner') DEFAULT 'user',
    aadhaar_file VARCHAR(255), -- For owners
    license_file VARCHAR(255), -- For owners
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Admins table (Super admins for platform management)
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Turfs table
CREATE TABLE IF NOT EXISTS turfs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    sport_category ENUM('Football', 'Cricket', 'Multi-Sport') NOT NULL,
    price_per_hour DECIMAL(10, 2) NOT NULL,
    description TEXT,
    image_path VARCHAR(255),
    status ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (location),
    INDEX (sport_category)
) ENGINE=InnoDB;

-- 4. Time Slots table (Predefined slots for each turf)
CREATE TABLE IF NOT EXISTS time_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    turf_id INT NOT NULL,
    slot_label VARCHAR(50) NOT NULL, -- e.g., "04:00 PM - 05:00 PM"
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    FOREIGN KEY (turf_id) REFERENCES turfs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. Bookings table
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    turf_id INT NOT NULL,
    slot_id INT NOT NULL,
    booking_date DATE NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    status ENUM('upcoming', 'completed', 'cancelled') DEFAULT 'upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- DATABASE LEVEL CONSTRAINT: Prevent double booking of same turf + same date + same slot
    UNIQUE KEY unique_booking (turf_id, booking_date, slot_id),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (turf_id) REFERENCES turfs(id) ON DELETE CASCADE,
    FOREIGN KEY (slot_id) REFERENCES time_slots(id) ON DELETE CASCADE,
    
    INDEX (booking_date)
) ENGINE=InnoDB;

-- 6. Payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
    payment_method VARCHAR(50),
    transaction_id VARCHAR(255) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Sample Data Insertion

-- Sample Admin
INSERT INTO admins (username, email, password) VALUES 
('admin', 'admin@turfkick.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); -- password: password

-- Sample Owner
INSERT INTO users (name, email, password, role) VALUES 
('Owner One', 'owner@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'owner');

-- Sample User
INSERT INTO users (name, email, password, role) VALUES 
('John Doe', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user');

-- Sample Turf
INSERT INTO turfs (owner_id, name, location, sport_category, price_per_hour, description, status) VALUES 
(2, 'City Football Arena', 'Kochi', 'Football', 1200.00, 'Premium 5-a-side football turf', 'active');

-- Sample Time Slots for the turf
INSERT INTO time_slots (turf_id, slot_label, start_time, end_time) VALUES 
(1, '04:00 PM - 05:00 PM', '16:00:00', '17:00:00'),
(1, '05:00 PM - 06:00 PM', '17:00:00', '18:00:00'),
(1, '06:00 PM - 07:00 PM', '18:00:00', '19:00:00'),
(1, '07:00 PM - 08:00 PM', '19:00:00', '20:00:00'),
(1, '08:00 PM - 09:00 PM', '20:00:00', '21:00:00');
