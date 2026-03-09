-- Migrasi: tambah kolom admin_activated ke tabel registrations
-- Jalankan di phpMyAdmin lokal & hosting

ALTER TABLE registrations
    ADD COLUMN IF NOT EXISTS admin_activated TINYINT(1) NOT NULL DEFAULT 0 AFTER payment_status,
    ADD COLUMN IF NOT EXISTS activated_by INT NULL AFTER admin_activated,
    ADD COLUMN IF NOT EXISTS activated_at DATETIME NULL AFTER activated_by,
    ADD COLUMN IF NOT EXISTS activation_note VARCHAR(255) NULL AFTER activated_at;
