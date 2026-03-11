<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireLogin();

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

$category = trim($body['category'] ?? '');
if (!in_array($category, ['10K', '21K'])) {
    echo json_encode(['success' => false, 'message' => 'Kategori tidak valid.']);
    exit;
}

$user  = getCurrentUser();
$event = getActiveEvent();

if (!$event) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada event aktif saat ini.']);
    exit;
}

// Cek sudah terdaftar?
$existing = getUserRegistration($user['id'], $event['id']);
if ($existing) {
    echo json_encode(['success' => false, 'message' => 'Kamu sudah terdaftar di event ini.']);
    exit;
}

$targetKm   = $category === '21K' ? (float)($event['target_21k'] ?? 21) : (float)($event['target_10k'] ?? 10);
$fee        = $category === '21K' ? (int)($event['fee_21k'] ?? 199000) : (int)($event['fee_10k'] ?? 179000);

$db = getDB();
$db->prepare("INSERT INTO registrations
    (user_id, event_id, category, target_km, status, payment_status, registered_at)
    VALUES (?, ?, ?, ?, 'active', 'unpaid', NOW())")
   ->execute([$user['id'], $event['id'], $category, $targetKm]);

echo json_encode([
    'success'  => true,
    'category' => $category,
    'fee'      => $fee,
    'message'  => 'Berhasil bergabung ke event ' . $event['name'] . ' kategori ' . $category . '!',
]);
