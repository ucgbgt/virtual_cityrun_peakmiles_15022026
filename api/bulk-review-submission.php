<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/admin/submissions.php');
}

if (!validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Token tidak valid. Silakan refresh halaman.');
    redirect(SITE_URL . '/admin/submissions.php');
}

$adminUser = getCurrentUser();
$db        = getDB();
$action    = $_POST['action'] ?? '';
$ids       = $_POST['ids'] ?? [];
$adminNote = trim($_POST['admin_note'] ?? '');
$redirect  = SITE_URL . '/admin/submissions.php?' . http_build_query([
    'status' => $_POST['filter_status'] ?? '',
    'search' => $_POST['filter_search'] ?? '',
    'page'   => $_POST['filter_page']   ?? 1,
]);

// Validasi dasar
if (!in_array($action, ['approve', 'reject'])) {
    flash('error', 'Aksi tidak valid.');
    redirect($redirect);
}

if (empty($ids) || !is_array($ids)) {
    flash('error', 'Tidak ada submission yang dipilih.');
    redirect($redirect);
}

if ($action === 'reject' && empty($adminNote)) {
    flash('error', 'Alasan penolakan wajib diisi untuk bulk reject.');
    redirect($redirect);
}

// Sanitasi: pastikan semua ID adalah integer
$ids = array_map('intval', $ids);
$ids = array_filter($ids, fn($id) => $id > 0);

if (empty($ids)) {
    flash('error', 'ID submission tidak valid.');
    redirect($redirect);
}

// Ambil hanya submission yang benar-benar pending
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $db->prepare("SELECT * FROM run_submissions WHERE id IN ($placeholders) AND status='pending'");
$stmt->execute($ids);
$submissions = $stmt->fetchAll();

if (empty($submissions)) {
    flash('warning', 'Tidak ada submission pending yang bisa diproses.');
    redirect($redirect);
}

$successCount = 0;
$affectedUsers = []; // untuk update finisher setelah approve

foreach ($submissions as $sub) {
    if ($action === 'approve') {
        $db->prepare("UPDATE run_submissions SET status='approved', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")
           ->execute([$adminNote ?: null, $adminUser['id'], $sub['id']]);

        logAudit($adminUser['id'], 'bulk_approve_submission', 'run_submissions', $sub['id'],
            ['status' => 'pending'],
            ['status' => 'approved']
        );

        // Tandai user+event yang perlu dicek finisher
        $key = $sub['user_id'] . '_' . $sub['event_id'];
        $affectedUsers[$key] = ['user_id' => $sub['user_id'], 'event_id' => $sub['event_id']];

    } else {
        $db->prepare("UPDATE run_submissions SET status='rejected', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")
           ->execute([$adminNote, $adminUser['id'], $sub['id']]);

        logAudit($adminUser['id'], 'bulk_reject_submission', 'run_submissions', $sub['id'],
            ['status' => 'pending'],
            ['status' => 'rejected', 'reason' => $adminNote]
        );
    }
    $successCount++;
}

// Setelah semua approve selesai, cek finisher untuk tiap user yang terpengaruh
if ($action === 'approve') {
    foreach ($affectedUsers as $entry) {
        checkAndUpdateFinisher($entry['user_id'], $entry['event_id']);
    }
}

$label = $action === 'approve' ? 'disetujui' : 'ditolak';
flash('success', "$successCount submission berhasil $label sekaligus.");
redirect($redirect);
