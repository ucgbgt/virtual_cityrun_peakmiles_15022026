-- Migrasi: tambah kolom google_id ke tabel users
-- Jalankan di phpMyAdmin lokal & hosting
-- Jika error "Duplicate column", berarti kolom sudah ada (abaikan saja)

ALTER TABLE users
    MODIFY COLUMN password_hash VARCHAR(255) NOT NULL DEFAULT '';

ALTER TABLE users
    ADD COLUMN google_id VARCHAR(100) NULL UNIQUE AFTER password_hash;
