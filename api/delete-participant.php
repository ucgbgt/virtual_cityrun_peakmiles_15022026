<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!validateCSRF($data['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token tidak valid.']);
    exit;
}

$userId = (int)($data['user_id'] ?? 0);
if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'User tidak valid.']);
    exit;
}

$db = getDB();

// Safety: jangan hapus akun admin
$targetUser = $db->prepare("SELECT id, name, role FROM users WHERE id = ?");
$targetUser->execute([$userId]);
$targetUser = $targetUser->fetch();

if (!$targetUser) {
    echo json_encode(['success' => false, 'message' => 'Peserta tidak ditemukan.']);
    exit;
}
if ($targetUser['role'] === 'admin') {
    echo json_encode(['success' => false, 'message' => 'Akun admin tidak bisa dihapus dari sini.']);
    exit;
}

// Hapus semua data terkait user secara berurutan
try {
    $db->beginTransaction();

    // 1. Hapus file bukti lari dari disk
    $subs = $db->prepare("SELECT evidence_path FROM run_submissions WHERE user_id = ?");
    $subs->execute([$userId]);
    foreach ($subs->fetchAll() as $sub) {
        if (!empty($sub['evidence_path'])) {
            $path = UPLOAD_PATH . $sub['evidence_path'];
            if (file_exists($path)) @unlink($path);
        }
    }

    // 2. Hapus file sertifikat dari disk
    $certs = $db->prepare("SELECT file_path FROM certificates WHERE user_id = ?");
    $certs->execute([$userId]);
    foreach ($certs->fetchAll() as $cert) {
        if (!empty($cert['file_path'])) {
            $path = CERT_PATH . $cert['file_path'];
            if (file_exists($path)) @unlink($path);
        }
    }

    // 3. Hapus foto avatar dari disk
    $avatarRow = $db->prepare("SELECT avatar FROM users WHERE id = ?");
    $avatarRow->execute([$userId]);
    $avatarFile = $avatarRow->fetchColumn();
    if ($avatarFile) {
        $path = AVATAR_PATH . $avatarFile;
        if (file_exists($path)) @unlink($path);
    }

    // 4. Hapus dari semua tabel database
    $db->prepare("DELETE FROM run_submissions WHERE user_id = ?")->execute([$userId]);
    $db->prepare("DELETE FROM certificates   WHERE user_id = ?")->execute([$userId]);
    $db->prepare("DELETE FROM shipping       WHERE user_id = ?")->execute([$userId]);
    $db->prepare("DELETE FROM registrations  WHERE user_id = ?")->execute([$userId]);
    $db->prepare("DELETE FROM user_profiles  WHERE user_id = ?")->execute([$userId]);
    $db->prepare("DELETE FROM users          WHERE id = ? AND role != 'admin'")->execute([$userId]);

    $db->commit();

    $admin = getCurrentUser();
    logAudit($admin['id'], 'delete_participant', 'users', $userId, ['name' => $targetUser['name']], null);

    echo json_encode(['success' => true, 'message' => 'Peserta "' . $targetUser['name'] . '" berhasil dihapus beserta semua datanya.']);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus: ' . $e->getMessage()]);
}
