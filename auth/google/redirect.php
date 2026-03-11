<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/google.php';
startSession();

if (isLoggedIn()) {
    redirect(SITE_URL . '/dashboard.php');
}

// Buat state token untuk proteksi CSRF
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

header('Location: ' . googleAuthUrl($state));
exit;
