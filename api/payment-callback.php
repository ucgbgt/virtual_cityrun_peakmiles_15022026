<?php
require_once __DIR__ . '/../includes/functions.php';

// Duitku mengirim POST ke sini setelah pembayaran
$apiKey          = DUITKU_API_KEY;
$merchantCode    = isset($_POST['merchantCode'])    ? $_POST['merchantCode']    : null;
$amount          = isset($_POST['amount'])          ? $_POST['amount']          : null;
$merchantOrderId = isset($_POST['merchantOrderId']) ? $_POST['merchantOrderId'] : null;
$resultCode      = isset($_POST['resultCode'])      ? $_POST['resultCode']      : null;
$reference       = isset($_POST['reference'])       ? $_POST['reference']       : null;
$signature       = isset($_POST['signature'])       ? $_POST['signature']       : null;

if (empty($merchantCode) || empty($amount) || empty($merchantOrderId) || empty($signature)) {
    http_response_code(400);
    echo 'Bad Parameter';
    exit;
}

// Validasi signature
$calcSignature = md5($merchantCode . $amount . $merchantOrderId . $apiKey);
if ($signature !== $calcSignature) {
    http_response_code(401);
    echo 'Bad Signature';
    exit;
}

// resultCode: 00 = sukses, 01 = gagal
if ($resultCode === '00') {
    $db = getDB();

    // Update payment_status menjadi paid berdasarkan merchant_order_id
    $stmt = $db->prepare("UPDATE registrations SET payment_status='paid', payment_reference=? WHERE merchant_order_id=?");
    $stmt->execute([$reference, $merchantOrderId]);

    if ($stmt->rowCount() > 0) {
        // Ambil data registrasi untuk audit log
        $reg = $db->prepare("SELECT * FROM registrations WHERE merchant_order_id=?");
        $reg->execute([$merchantOrderId]);
        $regData = $reg->fetch();

        if ($regData) {
            logAudit($regData['user_id'], 'payment_success', 'registrations', $regData['id'], null, [
                'merchant_order_id' => $merchantOrderId,
                'amount'            => $amount,
                'reference'         => $reference,
            ]);
        }
    }

    echo 'SUCCESS';
} else {
    // Pembayaran gagal atau dibatalkan, tidak perlu update
    echo 'FAILED';
}
