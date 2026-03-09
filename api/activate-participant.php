<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

if (!validateCSRF($body['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token tidak valid.']);
    exit;
}

$regId   = (int)($body['reg_id'] ?? 0);
$activate = (int)(bool)($body['activate'] ?? false);
$note    = trim($body['note'] ?? '');
$admin   = getCurrentUser();

if (!$regId) {
    echo json_encode(['success' => false, 'message' => 'ID registrasi tidak valid.']);
    exit;
}

$db = getDB();

// Pastikan registrasi ada
$reg = $db->prepare("SELECT id, payment_status FROM registrations WHERE id = ?");
$reg->execute([$regId]);
$reg = $reg->fetch();

if (!$reg) {
    echo json_encode(['success' => false, 'message' => 'Registrasi tidak ditemukan.']);
    exit;
}

// Jika nonaktifkan tapi sudah bayar via Duitku, tolak
if (!$activate && $reg['payment_status'] === 'paid') {
    echo json_encode(['success' => false, 'message' => 'Tidak bisa menonaktifkan akun yang sudah lunas via pembayaran.']);
    exit;
}

if ($activate) {
    $stmt = $db->prepare("UPDATE registrations SET admin_activated = 1, activated_by = ?, activated_at = NOW(), activation_note = ? WHERE id = ?");
    $stmt->execute([$admin['id'], $note ?: null, $regId]);
} else {
    $stmt = $db->prepare("UPDATE registrations SET admin_activated = 0, activated_by = NULL, activated_at = NULL, activation_note = NULL WHERE id = ?");
    $stmt->execute([$regId]);
}

echo json_encode(['success' => true, 'activate' => $activate]);
