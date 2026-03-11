<?php
// Buffer output so stray PHP notices don't corrupt JSON
ob_start();

require_once __DIR__ . '/../includes/functions.php';
startSession();
requireAdmin();

// Discard any buffered output (notices/warnings) and set clean JSON header
ob_end_clean();
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

$regId    = (int)($body['reg_id']   ?? 0);
$activate = (int)(bool)($body['activate'] ?? false);
$note     = trim($body['note'] ?? '');
$admin    = getCurrentUser();

if (!$regId) {
    echo json_encode(['success' => false, 'message' => 'ID registrasi tidak valid.']);
    exit;
}

try {
    $db = getDB();

    // Cek kolom admin_activated — auto-migrate jika belum ada
    $cols = $db->query("SHOW COLUMNS FROM registrations LIKE 'admin_activated'")->rowCount();
    if ($cols === 0) {
        $db->exec("ALTER TABLE registrations
            ADD COLUMN admin_activated TINYINT(1) DEFAULT 0 AFTER payment_reference,
            ADD COLUMN activated_by INT NULL,
            ADD COLUMN activated_at DATETIME NULL,
            ADD COLUMN activation_note VARCHAR(255) NULL");
    }

    // Pastikan registrasi ada
    $stmt = $db->prepare("SELECT id, payment_status FROM registrations WHERE id = ?");
    $stmt->execute([$regId]);
    $reg = $stmt->fetch();

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
        $db->prepare("UPDATE registrations SET admin_activated = 1, activated_by = ?, activated_at = NOW(), activation_note = ? WHERE id = ?")
           ->execute([$admin['id'], $note ?: null, $regId]);
    } else {
        $db->prepare("UPDATE registrations SET admin_activated = 0, activated_by = NULL, activated_at = NULL, activation_note = NULL WHERE id = ?")
           ->execute([$regId]);
    }

    echo json_encode(['success' => true, 'activate' => $activate]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
