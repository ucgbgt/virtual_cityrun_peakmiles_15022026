<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/admin');
}

if (!validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Token tidak valid. Silakan refresh halaman.');
    redirect(SITE_URL . '/admin');
}

$db        = getDB();
$adminUser = getCurrentUser();
$event     = getActiveEvent();

$name     = trim($_POST['name']     ?? '');
$email    = strtolower(trim($_POST['email'] ?? ''));
$category = in_array($_POST['category'] ?? '', ['10K','21K']) ? $_POST['category'] : '10K';
$eventId  = (int)($_POST['event_id'] ?? ($event['id'] ?? 0));
$redirect = SITE_URL . '/admin/index.php';

// Validasi input
if (empty($name) || empty($email)) {
    flash('error', 'Nama dan email wajib diisi.');
    redirect($redirect);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('error', 'Format email tidak valid.');
    redirect($redirect);
}
if (!$eventId) {
    flash('error', 'Tidak ada event aktif. Buat event terlebih dahulu.');
    redirect($redirect);
}

// Cek apakah email sudah ada
$checkUser = $db->prepare("SELECT id, name FROM users WHERE email = ?");
$checkUser->execute([$email]);
$existingUser = $checkUser->fetch();

$defaultPassword = 'User@123';
$isNewUser       = false;

if ($existingUser) {
    $userId = $existingUser['id'];
} else {
    // Buat akun baru
    $hash = password_hash($defaultPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    $db->prepare("INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, 'user', 1)")
       ->execute([$name, $email, $hash]);
    $userId    = (int)$db->lastInsertId();
    $isNewUser = true;

    // Buat profil kosong
    $db->prepare("INSERT INTO user_profiles (user_id) VALUES (?)")->execute([$userId]);
}

// Cek apakah sudah terdaftar di event ini
$regCheck = $db->prepare("SELECT id FROM registrations WHERE user_id = ? AND event_id = ?");
$regCheck->execute([$userId, $eventId]);
if ($regCheck->fetch()) {
    flash('warning', "Email <strong>$email</strong> sudah terdaftar di event ini.");
    redirect($redirect);
}

// Daftarkan ke event
$ev         = $db->prepare("SELECT * FROM events WHERE id = ?")->execute([$eventId]) ? $db->prepare("SELECT * FROM events WHERE id = ?") : null;
$evStmt     = $db->prepare("SELECT * FROM events WHERE id = ?");
$evStmt->execute([$eventId]);
$evData     = $evStmt->fetch();
$targetKm   = ($category === '21K') ? ($evData['target_21k'] ?? 21) : ($evData['target_10k'] ?? 10);

$db->prepare("INSERT INTO registrations (user_id, event_id, category, target_km) VALUES (?, ?, ?, ?)")
   ->execute([$userId, $eventId, $category, $targetKm]);

// Buat record shipping
$shpCheck = $db->prepare("SELECT id FROM shipping WHERE user_id = ? AND event_id = ?");
$shpCheck->execute([$userId, $eventId]);
if (!$shpCheck->fetch()) {
    $db->prepare("INSERT INTO shipping (user_id, event_id) VALUES (?, ?)")->execute([$userId, $eventId]);
}

// Audit log
logAudit($adminUser['id'], 'add_participant_manual', 'users', $userId, null, [
    'name'     => $name,
    'email'    => $email,
    'category' => $category,
    'event_id' => $eventId,
    'new_user' => $isNewUser,
]);

if ($isNewUser) {
    flash('success', "Peserta <strong>$name</strong> berhasil ditambahkan. Login: <code>$email</code> / Password default: <code>$defaultPassword</code>");
} else {
    flash('success', "Peserta <strong>$name</strong> berhasil didaftarkan ke event (akun sudah ada).");
}

redirect($redirect);
