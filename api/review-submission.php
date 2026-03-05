<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/admin/submissions.php');
}

if (!validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Token tidak valid.');
    redirect(SITE_URL . '/admin/submissions.php');
}

$adminUser = getCurrentUser();
$db = getDB();
$submissionId = (int)($_POST['submission_id'] ?? 0);
$action = $_POST['action'] ?? '';
$adminNote = trim($_POST['admin_note'] ?? '');
$redirectUrl = $_POST['redirect'] ?? SITE_URL . '/admin/submissions.php';

if (!in_array($action, ['approve', 'reject'])) {
    flash('error', 'Aksi tidak valid.');
    redirect($redirectUrl);
}

if ($action === 'reject' && empty($adminNote)) {
    flash('error', 'Alasan penolakan wajib diisi.');
    redirect($redirectUrl);
}

// Get submission
$stmt = $db->prepare("SELECT * FROM run_submissions WHERE id=? AND status='pending'");
$stmt->execute([$submissionId]);
$sub = $stmt->fetch();

if (!$sub) {
    flash('error', 'Submission tidak ditemukan atau sudah diproses.');
    redirect($redirectUrl);
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
    
    // Update registration total km and check finisher
    checkAndUpdateFinisher($sub['user_id'], $sub['event_id']);
    
    flash('success', 'Submission berhasil disetujui.');
} else {
    $db->prepare("UPDATE run_submissions SET status='rejected', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")
       ->execute([$adminNote, $adminUser['id'], $submissionId]);
    flash('success', 'Submission ditolak.');
}

$after = ['status' => $action === 'approve' ? 'approved' : 'rejected', 'distance_km' => $distanceKm];
logAudit($adminUser['id'], $action . '_submission', 'run_submissions', $submissionId, $before, $after);

redirect($redirectUrl);
