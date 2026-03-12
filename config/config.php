<?php
// Set timezone ke WIB (Indonesia)
date_default_timezone_set('Asia/Jakarta');

define('SITE_NAME', 'PeakMiles');
$_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host     = $_SERVER['HTTP_HOST'] ?? 'peakmiles.id';
$_subdir   = ($_host === 'localhost') ? '/stridenation' : '';
define('SITE_URL', $_protocol . '://' . $_host . $_subdir);
unset($_protocol, $_host, $_subdir);
define('SITE_TAGLINE', 'Run Your Way. Anywhere. Anytime.');
define('NUSATIX_URL', 'https://nusatix.com');

define('UPLOAD_PATH', __DIR__ . '/../uploads/submissions/');
define('AVATAR_PATH', __DIR__ . '/../uploads/avatars/');
define('CERT_PATH', __DIR__ . '/../certificates/');
define('UPLOAD_URL', SITE_URL . '/uploads/submissions/');
define('AVATAR_URL', SITE_URL . '/uploads/avatars/');
define('CERT_URL', SITE_URL . '/certificates/');

define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('MAX_KM_PER_SUBMISSION', 30);
define('SESSION_TIMEOUT', 3600 * 8); // 8 hours

// Allowed image types
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// Waajo WhatsApp Gateway
define('WAAJO_APIKEY', '');   // Isi dengan API key dari waajo.id

// Duitku Payment Gateway
define('DUITKU_MERCHANT_CODE', 'D20995');             // ganti dengan merchant code dari Duitku
define('DUITKU_API_KEY',       'a6ccb034e663d5f024d71070f33b231c');
define('DUITKU_SANDBOX',       false);                // false = production
define('DUITKU_BASE_URL',      DUITKU_SANDBOX
    ? 'https://sandbox.duitku.com/webapi/api/merchant'
    : 'https://passport.duitku.com/webapi/api/merchant');
