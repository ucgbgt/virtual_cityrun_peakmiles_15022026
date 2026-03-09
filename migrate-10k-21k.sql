-- Migrasi: Ubah kategori dari 5K/10K menjadi 10K/21K
-- Jalankan di phpMyAdmin lokal (database: stridenation)

-- 1. Tambah kolom baru di tabel events
ALTER TABLE events
    CHANGE COLUMN target_5k target_10k DECIMAL(5,2) DEFAULT 10.00,
    CHANGE COLUMN target_10k target_21k DECIMAL(5,2) DEFAULT 21.00;

ALTER TABLE events
    ADD COLUMN fee_10k INT DEFAULT 179000 AFTER target_21k,
    ADD COLUMN fee_21k INT DEFAULT 199000 AFTER fee_10k;

-- 2. Update nilai kolom target ke angka yang benar
UPDATE events SET target_10k = 10.00, target_21k = 21.00;

-- 3. Ubah ENUM kategori di tabel registrations
ALTER TABLE registrations
    MODIFY COLUMN category ENUM('10K', '21K') NOT NULL;
