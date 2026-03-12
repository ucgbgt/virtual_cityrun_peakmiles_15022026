<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$selectedMethod = trim($input['paymentMethod'] ?? '');

if (empty($selectedMethod)) {
    echo json_encode(['success' => false, 'message' => 'Pilih metode pembayaran terlebih dahulu.']);
    exit;
}

$db    = getDB();
$user  = getCurrentUser();
$event = getActiveEvent();

if (!$event) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada event aktif.']);
    exit;
}

$registration = getUserRegistration($user['id'], $event['id']);

if (!$registration) {
    echo json_encode(['success' => false, 'message' => 'Pendaftaran tidak ditemukan.']);
    exit;
}

if ($registration['payment_status'] === 'paid') {
    echo json_encode(['success' => false, 'message' => 'Pembayaran sudah selesai.']);
    exit;
}

// Generate unique merchant order ID
$merchantOrderId = 'PM-' . $user['id'] . '-' . $event['id'] . '-' . time();

// Tentukan nominal berdasarkan kategori
$amount = $registration['category'] === '21K'
    ? (int)($event['fee_21k'] ?? 199000)
    : (int)($event['fee_10k'] ?? 179000);

$merchantCode    = DUITKU_MERCHANT_CODE;
$apiKey          = DUITKU_API_KEY;
$productDetails  = 'Pendaftaran ' . $event['name'] . ' Kategori ' . $registration['category'];
$callbackUrl     = SITE_URL . '/api/payment-callback.php';
$returnUrl       = SITE_URL . '/payment-return.php';
$expiryPeriod    = 60; // 60 menit

$signature = md5($merchantCode . $merchantOrderId . $amount . $apiKey);

$nameParts = explode(' ', $user['name'], 2);
$firstName = $nameParts[0];
$lastName  = $nameParts[1] ?? '';

$params = [
    'merchantCode'    => $merchantCode,
    'paymentAmount'   => $amount,
    'paymentMethod'   => $selectedMethod,
    'merchantOrderId' => $merchantOrderId,
    'productDetails'  => $productDetails,
    'additionalParam' => '',
    'merchantUserInfo'=> $user['email'],
    'customerVaName'  => $user['name'],
    'email'           => $user['email'],
    'phoneNumber'     => $user['phone'] ?? '',
    'itemDetails'     => [[
        'name'     => $productDetails,
        'price'    => $amount,
        'quantity' => 1,
    ]],
    'customerDetail' => [
        'firstName'   => $firstName,
        'lastName'    => $lastName,
        'email'       => $user['email'],
        'phoneNumber' => $user['phone'] ?? '',
    ],
    'callbackUrl'  => $callbackUrl,
    'returnUrl'    => $returnUrl,
    'signature'    => $signature,
    'expiryPeriod' => $expiryPeriod,
];

$url = DUITKU_BASE_URL . '/v2/inquiry';
$ch  = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen(json_encode($params)),
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $result = json_decode($response, true);

    if (isset($result['paymentUrl'])) {
        // Simpan merchant_order_id dan reference ke registrations (untuk referensi cepat)
        $db->prepare("UPDATE registrations SET merchant_order_id=?, payment_reference=? WHERE user_id=? AND event_id=?")
           ->execute([$merchantOrderId, $result['reference'] ?? '', $user['id'], $event['id']]);

        // Simpan ke tabel payment_invoices agar semua invoice tersimpan sebagai history.
        // Ini mencegah invoice lama yang sudah dibayar user tidak bisa dikenali callback
        // karena merchant_order_id di registrations sudah ditimpa invoice baru.
        $db->prepare("
            INSERT IGNORE INTO payment_invoices
                (registration_id, user_id, event_id, merchant_order_id, payment_method, amount)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $registration['id'],
            $user['id'],
            $event['id'],
            $merchantOrderId,
            $selectedMethod,
            $amount,
        ]);

        logAudit($user['id'], 'payment_initiated', 'registrations', $registration['id'], null, [
            'merchant_order_id' => $merchantOrderId,
            'amount'            => $amount,
            'category'          => $registration['category'],
        ]);

        echo json_encode([
            'success'    => true,
            'paymentUrl' => $result['paymentUrl'],
            'reference'  => $result['reference'] ?? '',
            'vaNumber'   => $result['vaNumber'] ?? '',
            'amount'     => $amount,
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $result['statusMessage'] ?? 'Gagal membuat transaksi.']);
    }
} else {
    $err = json_decode($response);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . ($err->Message ?? $httpCode)]);
}
