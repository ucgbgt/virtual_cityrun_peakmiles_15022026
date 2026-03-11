-- ============================================================
-- Migration: Buat tabel settings + seed max_daily_submissions
-- Jalankan di tab SQL phpMyAdmin hosting
-- ============================================================

CREATE TABLE IF NOT EXISTS `settings` (
  `key`        VARCHAR(100) NOT NULL,
  `value`      VARCHAR(255) NOT NULL DEFAULT '',
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `settings` (`key`, `value`) VALUES ('max_daily_submissions', '3');
