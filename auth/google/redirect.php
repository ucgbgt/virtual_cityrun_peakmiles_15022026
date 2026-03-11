<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/google.php';
startSession();

if (isLoggedIn()) {
    redirect(SITE_URL . '/dashboard.php');
}

// Buat state token untuk CSRF protection
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

$client = getGoogleClient();
$client->setState($state);

$authUrl = $client->createAuthUrl();
header('Location: ' . $authUrl);
exit;
