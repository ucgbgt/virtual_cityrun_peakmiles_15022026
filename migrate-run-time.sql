-- ============================================================
-- Migration: Tambah kolom run_time ke tabel run_submissions
-- Jalankan di tab SQL phpMyAdmin hosting
-- ============================================================

ALTER TABLE `run_submissions`
  ADD COLUMN `run_time` TIME NULL DEFAULT NULL AFTER `distance_km`;

-- Jika muncul error "Duplicate column name 'run_time'",
-- berarti kolom sudah ada dan tidak perlu dijalankan lagi.
