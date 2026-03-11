-- StrideNation Virtual Run Database Schema
-- MySQL Database

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL DEFAULT '',
    google_id VARCHAR(100) NULL UNIQUE,
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
    target_10k DECIMAL(5,2) DEFAULT 10.00,
    target_21k DECIMAL(5,2) DEFAULT 21.00,
    fee_10k INT DEFAULT 179000,
    fee_21k INT DEFAULT 199000,
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
    category ENUM('10K', '21K') NOT NULL,
    target_km DECIMAL(5,2) NOT NULL,
    total_km_approved DECIMAL(6,2) DEFAULT 0.00,
    status ENUM('active', 'finisher') DEFAULT 'active',
    payment_status ENUM('unpaid', 'paid') DEFAULT 'unpaid',
    admin_activated TINYINT(1) NOT NULL DEFAULT 0,
    activated_by INT NULL,
    activated_at DATETIME NULL,
    activation_note VARCHAR(255) NULL,
    merchant_order_id VARCHAR(50) NULL,
    payment_reference VARCHAR(255) NULL,
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

-- Payment method settings table
CREATE TABLE IF NOT EXISTS payment_method_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_code VARCHAR(10) NOT NULL UNIQUE,
    payment_name VARCHAR(100) NOT NULL,
    category ENUM('qris','virtual_account','ewallet','retail','lainnya') NOT NULL DEFAULT 'lainnya',
    is_enabled TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0
) ENGINE=InnoDB;

-- Seed: default payment methods
INSERT INTO payment_method_settings (payment_code, payment_name, category, is_enabled, sort_order) VALUES
('SP', 'ShopeePay QRIS', 'qris', 1, 1),
('NQ', 'QRIS Nobu', 'qris', 1, 2),
('GQ', 'Gudang Voucher QRIS', 'qris', 0, 3),
('BC', 'BCA Virtual Account', 'virtual_account', 1, 10),
('M2', 'Mandiri Virtual Account', 'virtual_account', 1, 11),
('BR', 'BRI Virtual Account', 'virtual_account', 1, 12),
('I1', 'BNI Virtual Account', 'virtual_account', 1, 13),
('B1', 'CIMB Niaga Virtual Account', 'virtual_account', 1, 14),
('BT', 'Permata Virtual Account', 'virtual_account', 1, 15),
('BV', 'BSI Virtual Account', 'virtual_account', 0, 16),
('VA', 'Maybank Virtual Account', 'virtual_account', 0, 17),
('NC', 'BNC Virtual Account', 'virtual_account', 0, 18),
('S1', 'Sahabat Sampoerna Virtual Account', 'virtual_account', 0, 19),
('DM', 'Danamon Virtual Account', 'virtual_account', 0, 20),
('A1', 'ATM Bersama', 'virtual_account', 0, 21),
('AG', 'Artha Graha', 'virtual_account', 0, 22),
('DA', 'DANA', 'ewallet', 1, 30),
('OV', 'OVO', 'ewallet', 1, 31),
('SA', 'ShopeePay Apps', 'ewallet', 1, 32),
('LF', 'LinkAja (Fixed)', 'ewallet', 0, 33),
('LA', 'LinkAja (%)', 'ewallet', 0, 34),
('IR', 'Indomaret', 'retail', 1, 40),
('FT', 'Pegadaian / ALFA / Pos', 'retail', 0, 41),
('VC', 'Kartu Kredit', 'lainnya', 0, 50),
('DN', 'Indodana Paylater', 'lainnya', 0, 51),
('AT', 'ATOME', 'lainnya', 0, 52),
('JP', 'Jenius Pay', 'lainnya', 0, 53);

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

-- Seed: Users (password: User@123)
INSERT INTO users (name, email, phone, password_hash, role, is_active) VALUES
('Admin PeakMiles', 'admin@stridenation.id', '08123456789', '$2y$12$JxHuqu1NP2ooNS4bhfNFnuEKrEWYh8iseS3kl/CzbaDMKeyogp1Ve', 'admin', 1),
('Yusuf', 'yusuf@gmail.com', '', '$2y$12$JxHuqu1NP2ooNS4bhfNFnuEKrEWYh8iseS3kl/CzbaDMKeyogp1Ve', 'user', 1);

-- Seed: Default active event
INSERT INTO events (name, slug, description, start_date, end_date, target_10k, target_21k, fee_10k, fee_21k, registration_url) VALUES
('Budapest Vrtl Hlf Mrthn 2026', 'budapest-vrtl-hlf-mrthn-2026', 
'Event virtual run terbesar yang bisa kamu ikuti dari mana saja! Pilih kategori 10K atau 21K dan buktikan semangatmu sebagai pelari sejati.', 
'2026-01-01', '2026-12-31', 10.00, 21.00, 179000, 199000, 'https://nusatix.com');
