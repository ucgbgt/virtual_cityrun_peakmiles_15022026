<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/admin/submissions');
}

if (!validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Token tidak valid. Silakan refresh halaman.');
    redirect(SITE_URL . '/admin/submissions');
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

$ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));

if (empty($ids)) {
    flash('error', 'ID submission tidak valid.');
    redirect($redirect);
}

// Ambil hanya submission yang benar-benar pending
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $db->prepare("SELECT id, user_id, event_id, distance_km FROM run_submissions WHERE id IN ($placeholders) AND status='pending'");
$stmt->execute($ids);
$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($submissions)) {
    flash('warning', 'Tidak ada submission pending yang bisa diproses.');
    redirect($redirect);
}

$submissionIds  = array_column($submissions, 'id');
$idPlaceholders = implode(',', array_fill(0, count($submissionIds), '?'));

// ── 1. Batch UPDATE run_submissions (1 query, bukan N query) ──────────────
if ($action === 'approve') {
    $db->prepare("UPDATE run_submissions SET status='approved', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id IN ($idPlaceholders)")
       ->execute(array_merge([$adminNote ?: null, $adminUser['id']], $submissionIds));
} else {
    $db->prepare("UPDATE run_submissions SET status='rejected', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id IN ($idPlaceholders)")
       ->execute(array_merge([$adminNote, $adminUser['id']], $submissionIds));
}

// ── 2. Log audit per submission (wajib individual untuk audit trail) ───────
$auditAction = $action === 'approve' ? 'bulk_approve_submission' : 'bulk_reject_submission';
$afterStatus = $action === 'approve' ? 'approved' : 'rejected';
foreach ($submissions as $sub) {
    logAudit($adminUser['id'], $auditAction, 'run_submissions', $sub['id'],
        ['status' => 'pending'],
        ['status' => $afterStatus]
    );
}

// ── 3. Batch finisher check (1 SELECT, bukan N×3 query) ───────────────────
if ($action === 'approve') {
    $affectedUsers = [];
    foreach ($submissions as $sub) {
        $key = $sub['user_id'] . '_' . $sub['event_id'];
        $affectedUsers[$key] = ['user_id' => (int)$sub['user_id'], 'event_id' => (int)$sub['event_id']];
    }

    $userIds  = array_unique(array_column($affectedUsers, 'user_id'));
    $eventIds = array_unique(array_column($affectedUsers, 'event_id'));

    $uPh = implode(',', array_fill(0, count($userIds),  '?'));
    $ePh = implode(',', array_fill(0, count($eventIds), '?'));

    // Satu query: dapat registrasi + total KM approved sekaligus
    $stmt = $db->prepare("
        SELECT r.user_id, r.event_id, r.status, r.target_km,
               COALESCE(SUM(rs.distance_km), 0) AS total_km
        FROM registrations r
        LEFT JOIN run_submissions rs
               ON rs.user_id  = r.user_id
              AND rs.event_id = r.event_id
              AND rs.status   = 'approved'
        WHERE r.user_id  IN ($uPh)
          AND r.event_id IN ($ePh)
          AND r.status  != 'finisher'
        GROUP BY r.user_id, r.event_id, r.status, r.target_km
    ");
    $stmt->execute(array_merge($userIds, $eventIds));
    $regs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($regs as $reg) {
        $totalKm  = (float)$reg['total_km'];
        $targetKm = (float)$reg['target_km'];

        if ($totalKm >= $targetKm) {
            $db->prepare("UPDATE registrations SET status='finisher', total_km_approved=?, finished_at=NOW() WHERE user_id=? AND event_id=?")
               ->execute([$totalKm, $reg['user_id'], $reg['event_id']]);
            generateCertificate((int)$reg['user_id'], (int)$reg['event_id']);
        } else {
            $db->prepare("UPDATE registrations SET total_km_approved=? WHERE user_id=? AND event_id=?")
               ->execute([$totalKm, $reg['user_id'], $reg['event_id']]);
        }
    }
}

$label = $action === 'approve' ? 'disetujui' : 'ditolak';
flash('success', count($submissions) . " submission berhasil $label sekaligus.");
redirect($redirect);
