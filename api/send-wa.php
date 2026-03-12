<?php
/**
 * Kirim pesan WhatsApp ke peserta via Waajo API.
 * Menerima POST JSON: { user_ids: [1,2,3] }
 * Menggunakan template dari settings key 'wa_address_template'.
 */
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$apiKey = WAAJO_APIKEY;
if (empty($apiKey)) {
    echo json_encode(['success' => false, 'message' => 'WAAJO_APIKEY belum diisi di config.php.']);
    exit;
}

$input   = json_decode(file_get_contents('php://input'), true) ?? [];
$userIds = array_values(array_filter(array_map('intval', $input['user_ids'] ?? [])));

if (empty($userIds)) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada peserta dipilih.']);
    exit;
}

$db    = getDB();
$event = getActiveEvent();

if (!$event) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada event aktif.']);
    exit;
}

$template = getSetting(
    'wa_address_template',
    'Hi Kak {nama}, silakan segera mengisi data alamat pada https://peakmiles.id/profile agar nanti admin PeakMiles dapat segera mendata pengiriman Race Pack sesuai dengan data peserta Virtual Run.'
);

// Ambil data user yang termasuk peserta aktif event ini
$placeholders = implode(',', array_fill(0, count($userIds), '?'));
$stmt = $db->prepare("
    SELECT u.id, u.name, u.phone
    FROM users u
    JOIN registrations r ON r.user_id = u.id
    WHERE u.id IN ($placeholders)
      AND r.event_id = ?
      AND (r.payment_status = 'paid' OR r.admin_activated = 1)
");
$stmt->execute(array_merge($userIds, [$event['id']]));
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($users)) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada peserta valid ditemukan.']);
    exit;
}

$waUrl  = 'https://api.waajo.id/go-omni-v2/public/whatsapp/send-text';
$adminId = getCurrentUser()['id'];

$sent   = 0;
$failed = 0;
$errors = [];

foreach ($users as $user) {
    if (empty($user['phone'])) {
        $failed++;
        $errors[] = sanitize($user['name']) . ': Nomor HP kosong';
        continue;
    }

    // Normalisasi nomor: buang spasi & karakter non-digit, lalu konversi ke format 62xxx
    $phone = preg_replace('/[^0-9]/', '', $user['phone']);
    if (substr($phone, 0, 3) === '620') {
        // Kasus +620... yang salah, abaikan dan perbaiki
        $phone = '62' . substr($phone, 3);
    } elseif (substr($phone, 0, 2) === '62') {
        // Sudah benar: 628xxx
    } elseif (substr($phone, 0, 1) === '0') {
        $phone = '62' . substr($phone, 1);
    } else {
        $phone = '62' . $phone;
    }

    $text = str_replace('{nama}', $user['name'], $template);

    $payload = json_encode([
        'recipient_number' => $phone,
        'text'             => $text,
        'is_mode_safe'     => true,
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $waUrl,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        $sent++;
        logAudit($adminId, 'send_wa_address_reminder', 'users', $user['id'], null, [
            'phone' => $phone,
        ]);
    } else {
        $failed++;
        $detail = $curlErr ?: ($httpCode ? 'HTTP ' . $httpCode : 'Tidak ada respon');
        $errors[] = sanitize($user['name']) . ' (' . $phone . '): ' . $detail;
    }
}

echo json_encode([
    'success' => $sent > 0,
    'sent'    => $sent,
    'failed'  => $failed,
    'errors'  => $errors,
]);
