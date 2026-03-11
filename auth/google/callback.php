<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/google.php';
startSession();

if (isLoggedIn()) {
    redirect(SITE_URL . '/dashboard.php');
}

// Jika user membatalkan
if (isset($_GET['error'])) {
    flash('error', 'Login dengan Google dibatalkan.');
    redirect(SITE_URL . '/login.php');
}

// Validasi state (CSRF protection)
if (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['google_oauth_state'] ?? '')) {
    flash('error', 'Permintaan tidak valid. Silakan coba lagi.');
    redirect(SITE_URL . '/login.php');
}
unset($_SESSION['google_oauth_state']);

if (empty($_GET['code'])) {
    flash('error', 'Kode otorisasi Google tidak ditemukan.');
    redirect(SITE_URL . '/login.php');
}

// Tukar code → access token
$tokenData = googleFetchToken($_GET['code']);

if (empty($tokenData['access_token'])) {
    $errMsg = $tokenData['error_description'] ?? ($tokenData['error'] ?? 'Unknown error');
    flash('error', 'Gagal mendapatkan token Google: ' . $errMsg);
    redirect(SITE_URL . '/login.php');
}

// Ambil profil user dari Google
$gUser = googleGetUserInfo($tokenData['access_token']);

if (empty($gUser['email'])) {
    flash('error', 'Tidak bisa mendapatkan email dari akun Google.');
    redirect(SITE_URL . '/login.php');
}

$googleId = $gUser['id']    ?? '';
$email    = $gUser['email'] ?? '';
$name     = $gUser['name']  ?? $email;

$db = getDB();

// Cek apakah user sudah ada (by google_id atau email)
$stmt = $db->prepare("SELECT * FROM users WHERE google_id = ? OR email = ? LIMIT 1");
$stmt->execute([$googleId, $email]);
$user = $stmt->fetch();

if ($user) {
    // User sudah ada
    if (!$user['is_active']) {
        flash('error', 'Akun kamu dinonaktifkan. Hubungi admin.');
        redirect(SITE_URL . '/login.php');
    }
    // Tautkan google_id jika belum ada
    if (!$user['google_id'] && $googleId) {
        $db->prepare("UPDATE users SET google_id = ? WHERE id = ?")->execute([$googleId, $user['id']]);
    }
} else {
    // User baru — buat akun otomatis
    $db->prepare("INSERT INTO users (name, email, password_hash, google_id, role, is_active, created_at)
                  VALUES (?, ?, '', ?, 'user', 1, NOW())")
       ->execute([$name, $email, $googleId]);

    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    flash('success', 'Akun berhasil dibuat! Selamat bergabung di PeakMiles.');
}

// Set session login
$_SESSION['user_id']   = $user['id'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['user_name'] = $user['name'];
session_regenerate_id(true);

$dest = ($user['role'] === 'admin') ? SITE_URL . '/admin/index.php' : SITE_URL . '/dashboard.php';
redirect($dest);
