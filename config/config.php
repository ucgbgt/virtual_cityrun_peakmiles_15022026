<?php
// Set timezone ke WIB (Indonesia)
date_default_timezone_set('Asia/Jakarta');

define('SITE_NAME', 'PeakMiles');
define('SITE_URL', 'https://peakmiles.id');
define('SITE_TAGLINE', 'Run Your Way. Anywhere. Anytime.');
define('NUSATIX_URL', 'https://nusatix.com');

define('UPLOAD_PATH', __DIR__ . '/../uploads/submissions/');
define('CERT_PATH', __DIR__ . '/../certificates/');
define('UPLOAD_URL', SITE_URL . '/uploads/submissions/');
define('CERT_URL', SITE_URL . '/certificates/');

define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('MAX_KM_PER_SUBMISSION', 30);
define('SESSION_TIMEOUT', 3600 * 8); // 8 hours

// Allowed image types
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
