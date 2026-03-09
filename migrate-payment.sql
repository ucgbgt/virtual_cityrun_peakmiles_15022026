-- Migrasi: Tambah kolom payment ke tabel registrations
-- Jalankan di phpMyAdmin lokal (database: stridenation) dan hosting (bayarqri_peakmiles)

ALTER TABLE registrations
    ADD COLUMN payment_status ENUM('unpaid', 'paid') DEFAULT 'unpaid' AFTER status,
    ADD COLUMN merchant_order_id VARCHAR(50) NULL AFTER payment_status,
    ADD COLUMN payment_reference VARCHAR(255) NULL AFTER merchant_order_id;
