<?php
// Salin file ini menjadi config/google.php dan isi dengan credentials Anda
// Copy this file to config/google.php and fill in your credentials

define('GOOGLE_CLIENT_ID',     'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');

// Redirect URI harus sama persis dengan yang didaftarkan di Google Console
$_googleHost = $_SERVER['HTTP_HOST'] ?? 'peakmiles.id';
if ($_googleHost === 'localhost') {
    define('GOOGLE_REDIRECT_URI', 'http://localhost/stridenation/auth/google/callback.php');
} else {
    define('GOOGLE_REDIRECT_URI', 'https://' . $_googleHost . '/auth/google/callback.php');
}
unset($_googleHost);

function getGoogleClient(): Google_Client {
    require_once __DIR__ . '/../vendor/autoload.php';

    $client = new Google_Client();
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->setRedirectUri(GOOGLE_REDIRECT_URI);
    $client->addScope('email');
    $client->addScope('profile');
    $client->setAccessType('online');
    $client->setPrompt('select_account');
    return $client;
}
