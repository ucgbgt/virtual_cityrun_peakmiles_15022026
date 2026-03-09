-- Migrasi: Buat tabel pengaturan metode pembayaran
-- Jalankan di phpMyAdmin lokal & hosting

CREATE TABLE IF NOT EXISTS payment_method_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_code VARCHAR(10) NOT NULL UNIQUE,
    payment_name VARCHAR(100) NOT NULL,
    category ENUM('qris','virtual_account','ewallet','retail','lainnya') NOT NULL DEFAULT 'lainnya',
    is_enabled TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0
) ENGINE=InnoDB;

INSERT IGNORE INTO payment_method_settings (payment_code, payment_name, category, is_enabled, sort_order) VALUES
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
