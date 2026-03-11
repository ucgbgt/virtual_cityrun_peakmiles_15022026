<?php
/**
 * Duitku Payment Callback — server-to-server only
 * Satu-satunya endpoint yang boleh mengubah payment_status menjadi 'paid'.
 * Semua verifikasi dilakukan di sini sebelum update database.
 */
require_once __DIR__ . '/../includes/functions.php';

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$apiKey          = DUITKU_API_KEY;
$ourMerchantCode = DUITKU_MERCHANT_CODE;

$merchantCode    = $_POST['merchantCode']    ?? null;
$amount          = $_POST['amount']          ?? null;
$merchantOrderId = $_POST['merchantOrderId'] ?? null;
$resultCode      = $_POST['resultCode']      ?? null;
$reference       = $_POST['reference']       ?? null;
$signature       = $_POST['signature']       ?? null;

// 1. Cek field wajib
if (empty($merchantCode) || empty($amount) || empty($merchantOrderId) || empty($signature)) {
    http_response_code(400);
    echo 'Bad Parameter';
    exit;
}

// 2. Verifikasi merchantCode cocok dengan akun kita
if ($merchantCode !== $ourMerchantCode) {
    http_response_code(403);
    echo 'Invalid Merchant';
    exit;
}

// 3. Verifikasi signature Duitku (HMAC MD5)
$calcSignature = md5($merchantCode . $amount . $merchantOrderId . $apiKey);
if (!hash_equals($calcSignature, $signature)) {
    http_response_code(401);
    echo 'Bad Signature';
    exit;
}

// 4. Ambil data registrasi dari DB dan verifikasi amount
$db = getDB();
$stmt = $db->prepare("
    SELECT r.id, r.user_id, r.category, r.payment_status,
           e.fee_10k, e.fee_21k
    FROM registrations r
    JOIN events e ON r.event_id = e.id
    WHERE r.merchant_order_id = ?
    LIMIT 1
");
$stmt->execute([$merchantOrderId]);
$regData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$regData) {
    // Order tidak ditemukan di database kita
    http_response_code(404);
    echo 'Order Not Found';
    exit;
}

// 5. Verifikasi amount sesuai dengan harga yang ada di database kita
$expectedAmount = $regData['category'] === '21K'
    ? (int)($regData['fee_21k'] ?? 199000)
    : (int)($regData['fee_10k'] ?? 179000);

if ((int)$amount !== $expectedAmount) {
    // Amount tidak cocok — kemungkinan replay attack atau manipulasi
    http_response_code(400);
    echo 'Amount Mismatch';
    exit;
}

// 6. resultCode: '00' = sukses
if ($resultCode === '00') {
    // Idempotent: sudah paid tidak perlu update ulang
    if ($regData['payment_status'] === 'paid') {
        echo 'SUCCESS';
        exit;
    }

    $upd = $db->prepare("
        UPDATE registrations
        SET payment_status = 'paid', payment_reference = ?
        WHERE id = ? AND payment_status != 'paid'
    ");
    $upd->execute([$reference, $regData['id']]);

    logAudit($regData['user_id'], 'payment_success', 'registrations', $regData['id'], null, [
        'merchant_order_id' => $merchantOrderId,
        'amount'            => $amount,
        'reference'         => $reference,
        'rows_updated'      => $upd->rowCount(),
    ]);

    echo 'SUCCESS';
} else {
    // Gagal / dibatalkan — tidak perlu update
    logAudit($regData['user_id'], 'payment_failed', 'registrations', $regData['id'], null, [
        'merchant_order_id' => $merchantOrderId,
        'result_code'       => $resultCode,
    ]);
    echo 'FAILED';
}
