-- Admin Panel Updates
ALTER TABLE users MODIFY COLUMN role ENUM('user', 'owner', 'admin') DEFAULT 'user';
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) DEFAULT 0;

-- Add a sample admin if not exists
INSERT INTO users (name, email, password, role) 
VALUES ('System Admin', 'admin@turfkick.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')
ON DUPLICATE KEY UPDATE role='admin';
