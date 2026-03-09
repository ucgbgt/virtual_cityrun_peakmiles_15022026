-- Jalankan file ini di phpMyAdmin lokal (database: stridenation)
-- Login: admin@stridenation.id / User@123

INSERT INTO users (name, email, phone, password_hash, role, is_active)
VALUES (
    'Admin Local',
    'admin@stridenation.id',
    '08123456789',
    '$2y$12$JxHuqu1NP2ooNS4bhfNFnuEKrEWYh8iseS3kl/CzbaDMKeyogp1Ve',
    'admin',
    1
)
ON DUPLICATE KEY UPDATE
    password_hash = '$2y$12$JxHuqu1NP2ooNS4bhfNFnuEKrEWYh8iseS3kl/CzbaDMKeyogp1Ve',
    role = 'admin',
    is_active = 1;
