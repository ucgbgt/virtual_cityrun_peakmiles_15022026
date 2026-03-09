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

// Ambil kode metode yang diaktifkan admin dari DB
$db = getDB();
$enabledRows = $db->query("SELECT payment_code, payment_name, category FROM payment_method_settings WHERE is_enabled = 1 ORDER BY sort_order ASC")->fetchAll();

if (empty($enabledRows)) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada metode pembayaran yang aktif. Hubungi admin.']);
    exit;
}

$enabledCodes = array_column($enabledRows, 'payment_code');
$enabledMeta  = [];
foreach ($enabledRows as $r) {
    $enabledMeta[$r['payment_code']] = ['name' => $r['payment_name'], 'category' => $r['category']];
}

// Panggil Duitku API
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

if ($httpCode !== 200) {
    echo json_encode(['success' => false, 'message' => 'Gagal memuat metode pembayaran dari Duitku.']);
    exit;
}

$result = json_decode($response, true);
$duitkuMethods = $result['paymentFee'] ?? [];

// Filter: hanya tampilkan yang ada di Duitku DAN diaktifkan admin
$categoryOrder = ['qris', 'virtual_account', 'ewallet', 'retail', 'lainnya'];
$categoryLabels = [
    'qris'            => 'QRIS',
    'virtual_account' => 'Virtual Account',
    'ewallet'         => 'E-Wallet',
    'retail'          => 'Retail',
    'lainnya'         => 'Lainnya',
];

$grouped = [];
foreach ($duitkuMethods as $m) {
    $code = $m['paymentMethod'] ?? '';
    if (!in_array($code, $enabledCodes)) continue;

    $meta     = $enabledMeta[$code] ?? null;
    $category = $meta ? $meta['category'] : 'lainnya';

    $grouped[$category][] = [
        'paymentMethod'          => $code,
        'paymentName'            => $m['paymentName'] ?? ($meta['name'] ?? $code),
        'paymentImage'           => $m['paymentImage'] ?? '',
        'totalFee'               => $m['totalFee'] ?? '0',
        'category'               => $category,
        'categoryLabel'          => $categoryLabels[$category] ?? 'Lainnya',
    ];
}

// Susun ulang berdasarkan urutan kategori
$orderedGroups = [];
foreach ($categoryOrder as $cat) {
    if (!empty($grouped[$cat])) {
        $orderedGroups[] = [
            'category'      => $cat,
            'categoryLabel' => $categoryLabels[$cat],
            'methods'       => $grouped[$cat],
        ];
    }
}

echo json_encode([
    'success' => true,
    'groups'  => $orderedGroups,
    'amount'  => $amount,
]);
