<?php
// Salin file ini menjadi config/google.php dan isi credentials Anda
// Tidak perlu Composer/vendor — menggunakan cURL langsung

define('GOOGLE_CLIENT_ID',     'YOUR_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_CLIENT_SECRET');

$_googleHost = $_SERVER['HTTP_HOST'] ?? 'peakmiles.id';
if ($_googleHost === 'localhost') {
    define('GOOGLE_REDIRECT_URI', 'http://localhost/stridenation/auth/google/callback.php');
} else {
    define('GOOGLE_REDIRECT_URI', 'https://' . $_googleHost . '/auth/google/callback.php');
}
unset($_googleHost);

function googleAuthUrl(string $state): string {
    $params = http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'access_type'   => 'online',
        'prompt'        => 'select_account',
        'state'         => $state,
    ]);
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
}

function googleFetchToken(string $code): ?array {
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'code'          => $code,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response ? json_decode($response, true) : null;
}

function googleGetUserInfo(string $accessToken): ?array {
    $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response ? json_decode($response, true) : null;
}
