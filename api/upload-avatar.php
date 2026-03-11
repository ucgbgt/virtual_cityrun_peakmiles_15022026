<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (!validateCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token tidak valid. Refresh halaman dan coba lagi.']);
    exit;
}

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File terlalu besar (melebihi batas server).',
        UPLOAD_ERR_FORM_SIZE  => 'File terlalu besar.',
        UPLOAD_ERR_PARTIAL    => 'File hanya terupload sebagian. Coba lagi.',
        UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang dipilih.',
        UPLOAD_ERR_NO_TMP_DIR => 'Error server: folder temp tidak ada.',
        UPLOAD_ERR_CANT_WRITE => 'Error server: gagal menulis file.',
    ];
    $errCode = $_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE;
    echo json_encode(['success' => false, 'message' => $uploadErrors[$errCode] ?? 'Gagal upload file.']);
    exit;
}

$file = $_FILES['avatar'];

// Max 2MB untuk avatar
if ($file['size'] > 2 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Ukuran foto maksimal 2MB.']);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'])) {
    echo json_encode(['success' => false, 'message' => 'Format tidak valid. Gunakan JPG, PNG, atau WEBP.']);
    exit;
}

$user = getCurrentUser();
$db   = getDB();

// Hapus avatar lama jika ada
if (!empty($user['avatar'])) {
    $oldPath = AVATAR_PATH . $user['avatar'];
    if (file_exists($oldPath)) unlink($oldPath);
}

// Pastikan folder ada, buat jika belum
if (!is_dir(AVATAR_PATH)) {
    if (!mkdir(AVATAR_PATH, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Gagal membuat folder upload. Hubungi admin.']);
        exit;
    }
}

$ext      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mimeType];
$filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
$dest     = AVATAR_PATH . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan foto. Cek permission folder uploads/avatars/.']);
    exit;
}

$db->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$filename, $user['id']]);

echo json_encode([
    'success'    => true,
    'message'    => 'Foto profil berhasil diperbarui!',
    'avatar_url' => AVATAR_URL . $filename,
]);
