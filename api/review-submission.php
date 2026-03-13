<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireAdmin();

$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

function respond(bool $ok, string $msg, array $extra = [], string $redirect = ''): void {
    global $isAjax;
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
        exit;
    }
    if ($ok) flash('success', $msg); else flash('error', $msg);
    redirect($redirect ?: SITE_URL . '/admin/submissions.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed.');
}

if (!validateCSRF($_POST['csrf_token'] ?? '')) {
    respond(false, 'Token tidak valid. Silakan refresh halaman.');
}

$adminUser    = getCurrentUser();
$db           = getDB();
$submissionId = (int)($_POST['submission_id'] ?? 0);
$action       = $_POST['action'] ?? '';
$adminNote    = trim($_POST['admin_note'] ?? '');
$redirectUrl  = $_POST['redirect'] ?? SITE_URL . '/admin/submissions.php';

if (!in_array($action, ['approve', 'reject'])) {
    respond(false, 'Aksi tidak valid.', [], $redirectUrl);
}

if ($action === 'reject' && empty($adminNote)) {
    respond(false, 'Alasan penolakan wajib diisi.', [], $redirectUrl);
}

$stmt = $db->prepare("SELECT * FROM run_submissions WHERE id=? AND status='pending'");
$stmt->execute([$submissionId]);
$sub = $stmt->fetch();

if (!$sub) {
    respond(false, 'Submission tidak ditemukan atau sudah diproses.', [], $redirectUrl);
}

// Handle distance edit
$distanceKm = $sub['distance_km'];
if ($action === 'approve' && isset($_POST['distance_km'])) {
    $newKm = (float)$_POST['distance_km'];
    if ($newKm > 0 && $newKm <= MAX_KM_PER_SUBMISSION) {
        $distanceKm = $newKm;
    }
}

$before = ['status' => $sub['status'], 'distance_km' => $sub['distance_km']];

if ($action === 'approve') {
    $db->prepare("UPDATE run_submissions SET status='approved', distance_km=?, admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")
       ->execute([$distanceKm, $adminNote ?: null, $adminUser['id'], $submissionId]);
    checkAndUpdateFinisher($sub['user_id'], $sub['event_id']);
    $msg = 'Submission berhasil disetujui.';
} else {
    $db->prepare("UPDATE run_submissions SET status='rejected', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")
       ->execute([$adminNote, $adminUser['id'], $submissionId]);
    $msg = 'Submission ditolak.';
}

$after = ['status' => $action === 'approve' ? 'approved' : 'rejected', 'distance_km' => $distanceKm];
logAudit($adminUser['id'], $action . '_submission', 'run_submissions', $submissionId, $before, $after);

respond(true, $msg, [
    'action'      => $action,
    'distance_km' => $distanceKm,
    'admin_name'  => $adminUser['name'],
    'admin_note'  => $adminNote,
], $redirectUrl);
