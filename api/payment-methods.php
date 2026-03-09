<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireLogin();

header('Content-Type: application/json');

$event = getActiveEvent();
$user  = getCurrentUser();

if (!$event) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada event aktif.']);
    exit;
}

$registration = getUserRegistration($user['id'], $event['id']);
$amount = $registration
    ? ($registration['category'] === '21K' ? (int)($event['fee_21k'] ?? 199000) : (int)($event['fee_10k'] ?? 179000))
    : (int)($event['fee_10k'] ?? 179000);

$merchantCode = DUITKU_MERCHANT_CODE;
$apiKey       = DUITKU_API_KEY;
$datetime     = date('Y-m-d H:i:s');
$signature    = hash('sha256', $merchantCode . $amount . $datetime . $apiKey);

$params = [
    'merchantcode' => $merchantCode,
    'amount'       => $amount,
    'datetime'     => $datetime,
    'signature'    => $signature,
];

$url = DUITKU_BASE_URL . '/paymentmethod/getpaymentmethod';
$ch  = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $result = json_decode($response, true);
    echo json_encode([
        'success' => true,
        'methods' => $result['paymentFee'] ?? [],
        'amount'  => $amount,
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal memuat metode pembayaran.']);
}
