<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/dashboard');
}

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Tentukan halaman tujuan redirect (dashboard atau submissions)
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$backUrl = strpos($referer, 'submissions') !== false
    ? SITE_URL . '/submissions'
    : SITE_URL . '/dashboard';

function dieWithError($msg) {
    global $isAjax, $backUrl;
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }
    flash('error', $msg);
    redirect($backUrl);
}

if (!validateCSRF($_POST['csrf_token'] ?? '')) {
    dieWithError('Token tidak valid. Silakan refresh halaman dan coba lagi.');
}

$user = getCurrentUser();
$db = getDB();

$eventId = (int)($_POST['event_id'] ?? 0);
$runDate = $_POST['run_date'] ?? '';
$distanceKm = (float)($_POST['distance_km'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

// Validate event
$event = $db->prepare("SELECT * FROM events WHERE id=? AND is_active=1");
$event->execute([$eventId]);
$event = $event->fetch();
if (!$event) {
    dieWithError('Event tidak valid atau tidak aktif.');
}

// Validate registration
$reg = getUserRegistration($user['id'], $eventId);
if (!$reg) {
    dieWithError('Kamu belum terdaftar di event ini. Hubungi admin.');
}

// Cek akun aktif: harus sudah bayar ATAU diaktifkan manual oleh admin
$isActive = ($reg['payment_status'] ?? 'unpaid') === 'paid' || !empty($reg['admin_activated']);
if (!$isActive) {
    dieWithError('Akun kamu belum aktif. Selesaikan pembayaran terlebih dahulu atau hubungi admin.');
}

// Validate dates
if (empty($runDate)) {
    dieWithError('Tanggal lari wajib diisi.');
}
$runDateTs = strtotime($runDate);
$startTs = strtotime($event['start_date']);
$endTs = strtotime($event['end_date']);
if ($runDateTs < $startTs || $runDateTs > $endTs) {
    dieWithError('Tanggal lari harus dalam periode event (' . date('d M Y', $startTs) . ' - ' . date('d M Y', $endTs) . ').');
}
if ($runDate > date('Y-m-d')) {
    dieWithError('Tanggal lari tidak boleh di masa depan.');
}

// Validate distance
if ($distanceKm <= 0) {
    dieWithError('Jarak harus lebih dari 0 km.');
}
if ($distanceKm > MAX_KM_PER_SUBMISSION) {
    dieWithError('Jarak maksimal ' . MAX_KM_PER_SUBMISSION . ' km per submission.');
}

// Rate limit: max 3 submissions per day
$todayCount = $db->prepare("SELECT COUNT(*) FROM run_submissions WHERE user_id=? AND event_id=? AND DATE(created_at)=CURDATE()");
$todayCount->execute([$user['id'], $eventId]);
if ($todayCount->fetchColumn() >= 3) {
    dieWithError('Maksimal 3 submission per hari. Coba lagi besok.');
}

// Validate file upload
if (!isset($_FILES['evidence']) || $_FILES['evidence']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File terlalu besar (melebihi batas server).',
        UPLOAD_ERR_FORM_SIZE  => 'File terlalu besar.',
        UPLOAD_ERR_PARTIAL    => 'File hanya terupload sebagian. Coba lagi.',
        UPLOAD_ERR_NO_FILE    => 'File bukti wajib diupload.',
        UPLOAD_ERR_NO_TMP_DIR => 'Error server: folder temp tidak ada.',
        UPLOAD_ERR_CANT_WRITE => 'Error server: gagal menulis file.',
    ];
    $errCode = $_FILES['evidence']['error'] ?? UPLOAD_ERR_NO_FILE;
    dieWithError($uploadErrors[$errCode] ?? 'Gagal upload file. Coba lagi.');
}

$file = $_FILES['evidence'];
if ($file['size'] > MAX_FILE_SIZE) {
    dieWithError('Ukuran file maksimal 10MB. File kamu: ' . round($file['size']/1024/1024, 1) . 'MB.');
}

// Validate MIME type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
if (!in_array($mimeType, ALLOWED_TYPES)) {
    dieWithError('Format file tidak valid (' . $mimeType . '). Gunakan JPG, PNG, atau WEBP.');
}

// Generate unique filename
$ext = match($mimeType) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    default      => 'jpg'
};
$filename = 'run_' . $user['id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$uploadPath = UPLOAD_PATH . $filename;

if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    dieWithError('Gagal menyimpan file ke server. Cek permission folder uploads/.');
}

// Save to database
$stmt = $db->prepare("INSERT INTO run_submissions (user_id, event_id, run_date, distance_km, evidence_path, notes, status) VALUES (?,?,?,?,?,?,'pending')");
$stmt->execute([$user['id'], $eventId, $runDate, $distanceKm, $filename, $notes]);

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success'  => true,
        'message'  => 'Submission berhasil dikirim!',
        'distance' => number_format($distanceKm, 2),
        'run_date' => date('d M Y', strtotime($runDate)),
    ]);
    exit;
}

flash('success', '✅ Submission berhasil dikirim! Tim kami akan mereview dalam 1-2 hari kerja.');
redirect($backUrl);
