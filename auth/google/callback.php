<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/google.php';
startSession();

// Jika user sudah login, redirect ke dashboard
if (isLoggedIn()) {
    redirect(SITE_URL . '/dashboard.php');
}

// Jika ada error dari Google
if (isset($_GET['error'])) {
    flash('error', 'Login dengan Google dibatalkan.');
    redirect(SITE_URL . '/login.php');
}

// Validasi state untuk CRSF
if (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['google_oauth_state'] ?? '')) {
    flash('error', 'Permintaan tidak valid. Silakan coba lagi.');
    redirect(SITE_URL . '/login.php');
}
unset($_SESSION['google_oauth_state']);

if (!isset($_GET['code'])) {
    flash('error', 'Kode otorisasi Google tidak ditemukan.');
    redirect(SITE_URL . '/login.php');
}

try {
    $client = getGoogleClient();
    $token  = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        flash('error', 'Gagal mendapatkan token Google: ' . ($token['error_description'] ?? $token['error']));
        redirect(SITE_URL . '/login.php');
    }

    $client->setAccessToken($token);

    // Ambil info profil dari Google
    $oauth2   = new Google_Service_Oauth2($client);
    $gUser    = $oauth2->userinfo->get();
    $googleId = $gUser->getId();
    $email    = $gUser->getEmail();
    $name     = $gUser->getName();

    if (!$email) {
        flash('error', 'Tidak bisa mendapatkan email dari akun Google.');
        redirect(SITE_URL . '/login.php');
    }

    $db = getDB();

    // Cek apakah user sudah ada (by google_id atau email)
    $stmt = $db->prepare("SELECT * FROM users WHERE google_id = ? OR email = ? LIMIT 1");
    $stmt->execute([$googleId, $email]);
    $user = $stmt->fetch();

    if ($user) {
        // User sudah ada — update google_id jika belum tersimpan
        if (!$user['google_id']) {
            $db->prepare("UPDATE users SET google_id = ? WHERE id = ?")->execute([$googleId, $user['id']]);
        }
        if (!$user['is_active']) {
            flash('error', 'Akun kamu dinonaktifkan. Hubungi admin.');
            redirect(SITE_URL . '/login.php');
        }
    } else {
        // User baru — buat akun otomatis
        $db->prepare("INSERT INTO users (name, email, password_hash, google_id, role, is_active, created_at)
                      VALUES (?, ?, '', ?, 'user', 1, NOW())")
           ->execute([$name, $email, $googleId]);
        $user = $db->prepare("SELECT * FROM users WHERE email = ?")->execute([$email]) ? null : null;
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        flash('success', 'Akun berhasil dibuat via Google. Selamat bergabung!');
    }

    // Set session
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['name'];
    session_regenerate_id(true);

    $dest = ($user['role'] === 'admin') ? SITE_URL . '/admin/index.php' : SITE_URL . '/dashboard.php';
    redirect($dest);

} catch (Exception $e) {
    flash('error', 'Terjadi kesalahan saat login Google: ' . $e->getMessage());
    redirect(SITE_URL . '/login.php');
}
