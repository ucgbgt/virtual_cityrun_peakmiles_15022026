-- Tabel history semua invoice yang pernah dibuat per registrasi
-- Solusi untuk masalah: jika user membuat invoice baru (overwrite merchant_order_id lama),
-- lalu membayar invoice yang lama, callback tetap bisa ditemukan dan diproses.

CREATE TABLE IF NOT EXISTS payment_invoices (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration_id INT UNSIGNED     NOT NULL,
    user_id         INT UNSIGNED     NOT NULL,
    event_id        INT UNSIGNED     NOT NULL,
    merchant_order_id VARCHAR(50)    NOT NULL,
    payment_method  VARCHAR(20)      NOT NULL DEFAULT '',
    amount          INT UNSIGNED     NOT NULL,
    payment_reference VARCHAR(255)   NULL,
    status          ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
    created_at      TIMESTAMP        DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_merchant_order (merchant_order_id),
    KEY idx_registration (registration_id),
    KEY idx_user_event   (user_id, event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
