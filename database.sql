-- StrideNation Virtual Run Database Schema
-- MySQL Database

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    is_active TINYINT(1) DEFAULT 1,
    reset_token VARCHAR(100) NULL,
    reset_expires DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- User profiles table
CREATE TABLE IF NOT EXISTS user_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    dob DATE NULL,
    gender ENUM('male', 'female', 'other') NULL,
    address_full TEXT NULL,
    province VARCHAR(100) NULL,
    city VARCHAR(100) NULL,
    postal_code VARCHAR(10) NULL,
    jersey_size ENUM('XS','S','M','L','XL','XXL','XXXL') NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Events table
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    description TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    target_5k DECIMAL(5,2) DEFAULT 5.00,
    target_10k DECIMAL(5,2) DEFAULT 10.00,
    registration_url VARCHAR(500) DEFAULT 'https://nusatix.com',
    is_active TINYINT(1) DEFAULT 1,
    banner_image VARCHAR(300) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Registrations table
CREATE TABLE IF NOT EXISTS registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    category ENUM('5K', '10K') NOT NULL,
    target_km DECIMAL(5,2) NOT NULL,
    total_km_approved DECIMAL(6,2) DEFAULT 0.00,
    status ENUM('active', 'finisher') DEFAULT 'active',
    registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_event (user_id, event_id)
) ENGINE=InnoDB;

-- Run submissions table
CREATE TABLE IF NOT EXISTS run_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    run_date DATE NOT NULL,
    distance_km DECIMAL(5,2) NOT NULL,
    evidence_path VARCHAR(500) NOT NULL,
    notes TEXT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_note TEXT NULL,
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Shipping table
CREATE TABLE IF NOT EXISTS shipping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    status ENUM('not_ready', 'preparing', 'shipped', 'delivered') DEFAULT 'not_ready',
    courier VARCHAR(100) NULL,
    tracking_number VARCHAR(200) NULL,
    shipped_at DATETIME NULL,
    delivered_at DATETIME NULL,
    notes TEXT NULL,
    updated_by INT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Certificates table
CREATE TABLE IF NOT EXISTS certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_event_cert (user_id, event_id)
) ENGINE=InnoDB;

-- Audit logs table
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_id INT NULL,
    action VARCHAR(100) NOT NULL,
    object_type VARCHAR(50) NOT NULL,
    object_id INT NULL,
    before_data JSON NULL,
    after_data JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Seed: Default admin user (password: Admin@123)
INSERT INTO users (name, email, phone, password_hash, role) VALUES
('Admin StrideNation', 'admin@stridenation.id', '08123456789', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TiHsKJlbBzwqSrG5IjBvELLe0.Wi', 'admin');

-- Seed: Default active event
INSERT INTO events (name, slug, description, start_date, end_date, target_5k, target_10k, registration_url) VALUES
('StrideNation Virtual Run 2026', 'stridenation-virtual-run-2026', 
'Event virtual run terbesar yang bisa kamu ikuti dari mana saja! Pilih kategori 5K atau 10K dan buktikan semangatmu sebagai pelari sejati.', 
'2026-01-01', '2026-12-31', 5.00, 10.00, 'https://nusatix.com');
