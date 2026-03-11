<?php
$pageTitle = 'Manajemen Pengiriman';
$activeNav = 'admin-shipping';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db        = getDB();
$event     = getActiveEvent();
$adminUser = getCurrentUser();
$csrf      = generateCSRFToken();

// ══════════════════════════════════════════════════════════════════════
// ACTION: Single update
// ══════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_single') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) die('Invalid CSRF');
    $userId    = (int)$_POST['user_id'];
    $eventId   = (int)$_POST['event_id'];
    $status    = $_POST['status'];
    $courier   = trim($_POST['courier']          ?? '');
    $tracking  = trim($_POST['tracking_number']  ?? '');
    $shippedAt = in_array($status, ['shipped','delivered']) ? date('Y-m-d H:i:s') : null;

    $existing = $db->prepare("SELECT id FROM shipping WHERE user_id=? AND event_id=?");
    $existing->execute([$userId, $eventId]);
    if ($existing->fetch()) {
        $db->prepare("UPDATE shipping SET status=?,courier=?,tracking_number=?,
                      shipped_at=COALESCE(shipped_at,?),updated_by=?
                      WHERE user_id=? AND event_id=?")
           ->execute([$status, $courier, $tracking, $shippedAt, $adminUser['id'], $userId, $eventId]);
    } else {
        $db->prepare("INSERT INTO shipping (user_id,event_id,status,courier,tracking_number,shipped_at,updated_by)
                      VALUES (?,?,?,?,?,?,?)")
           ->execute([$userId, $eventId, $status, $courier, $tracking, $shippedAt, $adminUser['id']]);
    }
    logAudit($adminUser['id'], 'update_shipping', 'shipping', $userId);
    flash('success', 'Status pengiriman berhasil diperbarui.');
    redirect(SITE_URL . '/admin/shipping');
}

// ══════════════════════════════════════════════════════════════════════
// ACTION: Bulk update
// ══════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_update') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) die('Invalid CSRF');
    if (!$event) { flash('error', 'Tidak ada event aktif.'); redirect(SITE_URL . '/admin/shipping'); }

    $rawIds    = $_POST['selected_ids'] ?? '';
    $userIds   = array_filter(array_map('intval', explode(',', $rawIds)));
    $newStatus = $_POST['bulk_status']   ?? '';
    $courier   = trim($_POST['bulk_courier']   ?? '');
    $tracking  = trim($_POST['bulk_tracking']  ?? '');
    $shippedAt = in_array($newStatus, ['shipped','delivered']) ? date('Y-m-d H:i:s') : null;

    $updated = 0;
    foreach ($userIds as $uid) {
        $ex = $db->prepare("SELECT id FROM shipping WHERE user_id=? AND event_id=?");
        $ex->execute([$uid, $event['id']]);
        if ($ex->fetch()) {
            $db->prepare("UPDATE shipping SET status=?,courier=COALESCE(NULLIF(?,''),courier),
                          tracking_number=COALESCE(NULLIF(?,''),tracking_number),
                          shipped_at=COALESCE(shipped_at,?),updated_by=?
                          WHERE user_id=? AND event_id=?")
               ->execute([$newStatus, $courier, $tracking, $shippedAt, $adminUser['id'], $uid, $event['id']]);
        } else {
            $db->prepare("INSERT INTO shipping (user_id,event_id,status,courier,tracking_number,shipped_at,updated_by)
                          VALUES (?,?,?,?,?,?,?)")
               ->execute([$uid, $event['id'], $newStatus, $courier, $tracking, $shippedAt, $adminUser['id']]);
        }
        $updated++;
    }
    logAudit($adminUser['id'], 'bulk_shipping', 'shipping', 0, null, ['count' => $updated, 'status' => $newStatus]);
    flash('success', "$updated peserta berhasil diupdate ke status '$newStatus'.");
    redirect(SITE_URL . '/admin/shipping');
}

// ══════════════════════════════════════════════════════════════════════
// ACTION: Import tracking CSV
// ══════════════════════════════════════════════════════════════════════
$importResults = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_csv') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) die('Invalid CSRF');
    if (!$event) { flash('error', 'Tidak ada event aktif.'); redirect(SITE_URL . '/admin/shipping'); }

    $matched   = 0;
    $skipped   = 0;
    $errors    = [];
    $matchCol  = $_POST['match_col'] ?? 'phone';
    $courierImp = trim($_POST['import_courier'] ?? '');

    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $header = fgetcsv($handle);
        $row    = 1;
        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            if (count($data) < 2) { $errors[] = "Baris $row: kolom kurang dari 2"; $skipped++; continue; }
            $matchVal = trim($data[0]);
            $resi     = trim($data[1]);
            $courier  = isset($data[2]) ? trim($data[2]) : $courierImp;

            if (empty($matchVal) || empty($resi)) { $skipped++; continue; }

            if ($matchCol === 'phone') {
                $matchVal = preg_replace('/\s+/', '', $matchVal);
                if (substr($matchVal, 0, 3) === '+62') $matchVal = '0' . substr($matchVal, 3);
                if (substr($matchVal, 0, 2) === '62')  $matchVal = '0' . substr($matchVal, 2);
                $userStmt = $db->prepare("SELECT id FROM users WHERE REPLACE(REPLACE(phone,' ',''),'+62','0') LIKE ?");
                $userStmt->execute([$matchVal . '%']);
            } else {
                $userStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $userStmt->execute([$matchVal]);
            }
            $user = $userStmt->fetch();

            if (!$user) { $errors[] = "Baris $row: '$matchVal' tidak ditemukan"; $skipped++; continue; }

            $uid = $user['id'];
            $shippedAt = date('Y-m-d H:i:s');
            $ex = $db->prepare("SELECT id FROM shipping WHERE user_id=? AND event_id=?");
            $ex->execute([$uid, $event['id']]);
            if ($ex->fetch()) {
                $db->prepare("UPDATE shipping SET tracking_number=?,courier=COALESCE(NULLIF(?,''),courier),
                              status='shipped',shipped_at=COALESCE(shipped_at,?),updated_by=?
                              WHERE user_id=? AND event_id=?")
                   ->execute([$resi, $courier, $shippedAt, $adminUser['id'], $uid, $event['id']]);
            } else {
                $db->prepare("INSERT INTO shipping (user_id,event_id,status,courier,tracking_number,shipped_at,updated_by)
                              VALUES (?,?,'shipped',?,?,?,?)")
                   ->execute([$uid, $event['id'], $courier, $resi, $shippedAt, $adminUser['id']]);
            }
            $matched++;
        }
        fclose($handle);
    } else {
        flash('error', 'Gagal membaca file CSV.');
        redirect(SITE_URL . '/admin/shipping');
    }

    logAudit($adminUser['id'], 'import_tracking_csv', 'shipping', 0, null, ['matched' => $matched, 'skipped' => $skipped]);
    $importResults = ['matched' => $matched, 'skipped' => $skipped, 'errors' => $errors];
}

// ══════════════════════════════════════════════════════════════════════
// CSV EXPORT
// ══════════════════════════════════════════════════════════════════════
$filterStatus   = $_GET['status']   ?? '';
$filterCategory = $_GET['category'] ?? '';
$search         = $_GET['search']   ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];
if ($event)          { $where[] = 'r.event_id = ?';                     $params[] = $event['id']; }
if ($search)         { $where[] = '(u.name LIKE ? OR u.email LIKE ?)';  $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filterCategory) { $where[] = 'r.category = ?';                     $params[] = $filterCategory; }
if ($filterStatus === 'no_address') {
    $where[] = '(p.address_full IS NULL OR p.address_full = "")';
} elseif ($filterStatus) {
    $where[] = 'COALESCE(s.status,"not_ready") = ?';
    $params[] = $filterStatus;
}
$whereStr = implode(' AND ', $where);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $expSql = "SELECT u.name, u.email, u.phone, r.category, p.jersey_size,
                      p.address_full, p.city, p.province, p.postal_code,
                      COALESCE(s.status,'not_ready') AS shipping_status,
                      s.courier, s.tracking_number,
                      DATE_FORMAT(s.shipped_at,'%d/%m/%Y %H:%i') AS shipped_at,
                      DATE_FORMAT(r.registered_at,'%d/%m/%Y') AS tgl_daftar
               FROM registrations r
               JOIN users u ON r.user_id = u.id
               LEFT JOIN user_profiles p ON p.user_id = u.id
               LEFT JOIN shipping s ON s.user_id = r.user_id AND s.event_id = r.event_id
               WHERE $whereStr ORDER BY r.registered_at ASC";
    $expStmt = $db->prepare($expSql);
    $expStmt->execute($params);
    $expRows = $expStmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pengiriman-peakmiles-' . date('Ymd-His') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['No','Nama','Email','Telepon','Kategori','Ukuran Jersey',
                   'Alamat Lengkap','Kota','Provinsi','Kode Pos',
                   'Status Kirim','Kurir','No. Resi','Tgl Kirim','Tgl Daftar']);
    $n = 1;
    $shipMap = ['not_ready'=>'Belum Siap','preparing'=>'Disiapkan','shipped'=>'Dikirim','delivered'=>'Terkirim'];
    foreach ($expRows as $r) {
        fputcsv($out, [
            $n++, $r['name'], $r['email'], $r['phone'], $r['category'], $r['jersey_size'],
            $r['address_full'], $r['city'], $r['province'], $r['postal_code'],
            $shipMap[$r['shipping_status']] ?? $r['shipping_status'],
            $r['courier'], $r['tracking_number'], $r['shipped_at'], $r['tgl_daftar'],
        ]);
    }
    fclose($out);
    exit;
}

// ══════════════════════════════════════════════════════════════════════
// KPI STATS
// ══════════════════════════════════════════════════════════════════════
$kpi = ['not_ready' => 0, 'preparing' => 0, 'shipped' => 0, 'delivered' => 0, 'no_address' => 0];
if ($event) {
    $kpiStmt = $db->prepare("
        SELECT COALESCE(s.status,'not_ready') AS st, COUNT(*) AS c
        FROM registrations r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN shipping s ON s.user_id = r.user_id AND s.event_id = r.event_id
        WHERE r.event_id = ?
        GROUP BY COALESCE(s.status,'not_ready')
    ");
    $kpiStmt->execute([$event['id']]);
    foreach ($kpiStmt->fetchAll() as $k) $kpi[$k['st']] = (int)$k['c'];

    $naStmt = $db->prepare("
        SELECT COUNT(*) FROM registrations r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN user_profiles p ON p.user_id = u.id
        WHERE r.event_id = ? AND (p.address_full IS NULL OR p.address_full = '')
    ");
    $naStmt->execute([$event['id']]);
    $kpi['no_address'] = (int)$naStmt->fetchColumn();
}
$kpiTotal = $kpi['not_ready'] + $kpi['preparing'] + $kpi['shipped'] + $kpi['delivered'];
$kpiDonePercent = $kpiTotal > 0 ? round($kpi['delivered'] / $kpiTotal * 100) : 0;

// ══════════════════════════════════════════════════════════════════════
// JERSEY SIZE SUMMARY
// ══════════════════════════════════════════════════════════════════════
$jerseyOrder  = ['XS','S','M','L','XL','XXL','XXXL'];
$jerseyData   = array_fill_keys($jerseyOrder, 0);
$jerseyOther  = 0;
if ($event) {
    $jStmt = $db->prepare("
        SELECT COALESCE(UPPER(TRIM(p.jersey_size)),'') AS sz, COUNT(*) AS c
        FROM registrations r
        LEFT JOIN user_profiles p ON p.user_id = r.user_id
        WHERE r.event_id = ?
        GROUP BY COALESCE(UPPER(TRIM(p.jersey_size)),'')
    ");
    $jStmt->execute([$event['id']]);
    foreach ($jStmt->fetchAll(PDO::FETCH_KEY_PAIR) as $sz => $cnt) {
        $sz = strtoupper(trim($sz));
        if (isset($jerseyData[$sz])) {
            $jerseyData[$sz] = (int)$cnt;
        } else {
            $jerseyOther += (int)$cnt;
        }
    }
}
$jerseyTotal = array_sum($jerseyData) + $jerseyOther;

// ══════════════════════════════════════════════════════════════════════
// MAIN LIST QUERY
// ══════════════════════════════════════════════════════════════════════
$countStmt = $db->prepare("SELECT COUNT(*) FROM registrations r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN user_profiles p ON p.user_id = u.id
    LEFT JOIN shipping s ON s.user_id = r.user_id AND s.event_id = r.event_id
    WHERE $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

$stmt = $db->prepare("
    SELECT r.user_id, r.event_id, r.category,
           u.name, u.email, u.phone,
           p.address_full, p.city, p.province, p.postal_code, p.jersey_size,
           COALESCE(s.status,'not_ready') AS shipping_status,
           s.courier, s.tracking_number,
           DATE_FORMAT(s.shipped_at,'%d %b %Y') AS shipped_at
    FROM registrations r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN user_profiles p ON p.user_id = u.id
    LEFT JOIN shipping s ON s.user_id = r.user_id AND s.event_id = r.event_id
    WHERE $whereStr
    ORDER BY FIELD(COALESCE(s.status,'not_ready'),'not_ready','preparing','shipped','delivered'),
             r.registered_at ASC
    LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$rows = $stmt->fetchAll();
$totalPages = $total ? ceil($total / $perPage) : 1;

$exportUrl = '?' . http_build_query(array_filter([
    'export'   => 'csv',
    'search'   => $search,
    'status'   => $filterStatus,
    'category' => $filterCategory,
]));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manajemen Pengiriman — PeakMiles Admin</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@300;400;500;600;700;800;900&family=Saira:wght@700;800;900&display=swap" rel="stylesheet">
<style>
/* ── Shipping Page ── */
.ship-kpi-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr) 1.4fr;
  gap: 12px;
  margin-bottom: 16px;
}
.ship-kpi-item {
  background: var(--dark-2);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 16px;
  cursor: pointer;
  transition: border-color .2s, transform .15s, background .2s;
  display: flex;
  align-items: center;
  gap: 12px;
}
.ship-kpi-item:hover { border-color: rgba(255,255,255,0.1); transform: translateY(-1px); }
.ship-kpi-item.active { border-color: var(--primary); background: rgba(249,115,22,0.05); }
.s-kpi-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.s-kpi-val { font-size: 22px; font-weight: 800; color: #fff; line-height: 1; letter-spacing: -.5px; }
.s-kpi-lbl { font-size: 10px; color: var(--gray); text-transform: uppercase; letter-spacing: .5px; margin-top: 3px; font-weight: 600; }

.ship-kpi-progress {
  background: var(--dark-2);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 8px;
  transition: border-color .2s;
}
.ship-kpi-progress:hover { border-color: rgba(255,255,255,0.1); }
.prog-header { display: flex; justify-content: space-between; align-items: center; }
.prog-label { font-size: 11px; font-weight: 600; color: var(--gray); text-transform: uppercase; letter-spacing: .5px; }
.prog-pct { font-size: 20px; font-weight: 800; color: #22c55e; line-height: 1; }
.prog-bar { height: 5px; border-radius: 100px; background: rgba(255,255,255,0.05); overflow: hidden; }
.prog-fill { height: 100%; background: linear-gradient(90deg,#22c55e,#4ade80); border-radius: 100px; transition: width .4s; }
.prog-sub { font-size: 11px; color: var(--gray); }

.jersey-card {
  background: var(--dark-2);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 14px 20px;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}
.jersey-card-label { display: flex; align-items: center; gap: 7px; flex-shrink: 0; }
.jersey-card-label i { color: var(--primary); font-size: 13px; }
.jersey-card-label span { font-size: 11px; font-weight: 600; color: var(--gray); text-transform: uppercase; letter-spacing: .6px; }
.jersey-sizes { display: flex; align-items: center; flex-wrap: wrap; flex: 1; }
.jersey-sz { display: flex; flex-direction: column; align-items: center; padding: 5px 14px; border-right: 1px solid rgba(255,255,255,0.06); gap: 2px; }
.jersey-sz:last-child { border-right: none; }
.jersey-sz-lbl { font-size: 10px; font-weight: 700; color: var(--gray); text-transform: uppercase; letter-spacing: .4px; }
.jersey-sz-val { font-size: 17px; font-weight: 800; color: var(--gray); line-height: 1; }
.jersey-sz.has-data .jersey-sz-val { color: var(--primary); }
.jersey-sz.is-total .jersey-sz-val { color: #fff; font-size: 15px; }
.jersey-sz.is-total .jersey-sz-lbl { color: var(--gray-light); }

.ship-filter {
  background: var(--dark-2);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 14px 20px;
  margin-bottom: 16px;
}

.table-card {
  background: var(--dark-2);
  border: 1px solid var(--border);
  border-radius: 14px;
  overflow: hidden;
  margin-bottom: 10px;
}
.table-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
}
.table-card-title { font-size: 13px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 8px; }
.table-card-title i { color: var(--primary); }
.table-card-meta { font-size: 12px; color: var(--gray); }

.ss-badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 100px; font-size: 11px; font-weight: 600; border: 1px solid; white-space: nowrap; }
.ss-not-ready { background: rgba(107,114,128,.1); color: #9ca3af; border-color: rgba(107,114,128,.25); }
.ss-preparing  { background: rgba(245,158,11,.1);  color: #f59e0b; border-color: rgba(245,158,11,.25); }
.ss-shipped    { background: rgba(59,130,246,.1);  color: #60a5fa; border-color: rgba(59,130,246,.25); }
.ss-delivered  { background: rgba(34,197,94,.1);   color: #22c55e; border-color: rgba(34,197,94,.25); }

.jersey-pill { display: inline-block; margin-top: 5px; font-size: 11px; background: rgba(249,115,22,0.1); color: var(--primary); padding: 2px 9px; border-radius: 6px; font-weight: 700; }
.tracking-num { font-family: monospace; font-size: 11px; font-weight: 700; color: var(--primary); }
.copy-btn { background: none; border: none; color: var(--gray); cursor: pointer; padding: 0 3px; font-size: 10px; transition: color .15s; }
.copy-btn:hover { color: #fff; }

.table-pagination { display: flex; align-items: center; gap: 5px; padding: 14px 20px; border-top: 1px solid var(--border); flex-wrap: wrap; }
.pg-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 32px; height: 32px; padding: 0 8px; border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none; color: var(--gray-light); background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07); transition: all .15s; }
.pg-btn:hover { background: rgba(249,115,22,0.1); border-color: rgba(249,115,22,0.3); color: var(--primary); }
.pg-btn.active { background: var(--primary); border-color: var(--primary); color: #fff; }

.list-footer { font-size: 12px; color: var(--gray); padding-bottom: 80px; margin-top: 8px; }

@media (max-width: 1200px) { .ship-kpi-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 768px) {
  .ship-kpi-grid { grid-template-columns: repeat(3, 1fr); gap: 8px; }
  .jersey-card { padding: 10px 14px; }
  .jersey-sz { padding: 4px 10px; }
  .jersey-sz-val { font-size: 15px; }
  .table-card-header { flex-direction: column; align-items: flex-start; gap: 4px; padding: 14px 16px; }
  .ship-filter { padding: 12px 14px; }
}
@media (max-width: 480px) {
  .ship-kpi-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
  .jersey-card { flex-direction: column; align-items: flex-start; gap: 8px; }
  .jersey-sizes { gap: 0; }
  .jersey-sz { padding: 3px 7px; }
  .jersey-sz-val { font-size: 13px; }
  .jersey-sz-lbl { font-size: 9px; }
  .ship-kpi-item { padding: 12px; }
  .s-kpi-val { font-size: 18px; }
  .s-kpi-icon { width: 32px; height: 32px; font-size: 12px; }
}
</style>
</head>
<body>
<div class="dashboard-layout">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-content">

    <!-- TOPBAR -->
    <div class="topbar">
      <div class="d-flex align-items-center gap-3">
        <button id="sidebarToggle" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;" class="d-lg-none"><i class="fa fa-bars"></i></button>
        <div>
          <div class="topbar-title">Manajemen Pengiriman</div>
          <div style="font-size:12px;color:var(--gray-light);"><?= $event ? sanitize($event['name']) : 'Tidak ada event aktif' ?></div>
        </div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <button onclick="openModal('importModal')" class="btn-outline-custom btn-sm-custom">
          <i class="fa fa-file-import"></i><span class="d-none d-sm-inline"> Import CSV</span>
        </button>
        <a href="<?= $exportUrl ?>" class="btn-outline-custom btn-sm-custom">
          <i class="fa fa-file-csv"></i><span class="d-none d-sm-inline"> Export</span>
        </a>
      </div>
    </div>

    <div class="page-content">

      <?php $flash = getFlash(); if ($flash): ?>
      <div class="alert-custom alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>" style="margin-bottom:16px;">
        <i class="fa fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <?= sanitize($flash['message']) ?>
      </div>
      <?php endif; ?>

      <?php if ($importResults !== null): ?>
      <div class="alert-custom alert-<?= $importResults['matched'] > 0 ? 'success' : 'warning' ?>" style="margin-bottom:16px;">
        <i class="fa fa-file-import"></i>
        Import selesai: <strong><?= $importResults['matched'] ?> berhasil</strong>, <?= $importResults['skipped'] ?> dilewati.
        <?php if (!empty($importResults['errors'])): ?>
        <details style="margin-top:8px;font-size:12px;">
          <summary style="cursor:pointer;color:var(--warning);">Lihat detail error (<?= count($importResults['errors']) ?>)</summary>
          <ul style="margin:6px 0 0 16px;">
            <?php foreach (array_slice($importResults['errors'], 0, 10) as $e): ?>
            <li><?= sanitize($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </details>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- KPI Grid -->
      <div class="ship-kpi-grid">
        <?php
        $kpiDefs = [
            ['not_ready',  $kpi['not_ready'],  'Belum Siap',    '#6b7280', 'fa-box-open'],
            ['preparing',  $kpi['preparing'],  'Disiapkan',     '#f59e0b', 'fa-box'],
            ['shipped',    $kpi['shipped'],    'Dikirim',       '#3b82f6', 'fa-shipping-fast'],
            ['delivered',  $kpi['delivered'],  'Terkirim',      '#22c55e', 'fa-check-circle'],
            ['no_address', $kpi['no_address'], 'Alamat Kosong', '#ef4444', 'fa-exclamation-triangle'],
        ];
        foreach ($kpiDefs as [$val, $count, $label, $color, $icon]):
        ?>
        <div class="ship-kpi-item <?= $filterStatus === $val ? 'active' : '' ?>"
             onclick="filterByStatus('<?= $val ?>')" title="Filter: <?= $label ?>">
          <div class="s-kpi-icon" style="background:<?= $color ?>1a;color:<?= $color ?>;"><i class="fa <?= $icon ?>"></i></div>
          <div>
            <div class="s-kpi-val" <?= ($count > 0 && $val === 'no_address') ? 'style="color:#ef4444;"' : '' ?>><?= $count ?></div>
            <div class="s-kpi-lbl"><?= $label ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <!-- Progress tile -->
        <div class="ship-kpi-progress">
          <div class="prog-header">
            <span class="prog-label">Selesai Terkirim</span>
            <span class="prog-pct"><?= $kpiDonePercent ?>%</span>
          </div>
          <div class="prog-bar"><div class="prog-fill" style="width:<?= $kpiDonePercent ?>%;"></div></div>
          <div class="prog-sub"><?= $kpi['delivered'] ?> dari <?= $kpiTotal ?> peserta</div>
        </div>
      </div>

      <!-- Jersey Summary -->
      <div class="jersey-card">
        <div class="jersey-card-label">
          <i class="fa fa-tshirt"></i>
          <span>Jersey</span>
        </div>
        <div class="jersey-sizes">
          <?php foreach ($jerseyOrder as $sz): $cnt = $jerseyData[$sz]; ?>
          <div class="jersey-sz <?= $cnt > 0 ? 'has-data' : '' ?>">
            <span class="jersey-sz-lbl"><?= $sz ?></span>
            <span class="jersey-sz-val"><?= $cnt ?></span>
          </div>
          <?php endforeach; ?>
          <?php if ($jerseyOther > 0): ?>
          <div class="jersey-sz">
            <span class="jersey-sz-lbl">Lainnya</span>
            <span class="jersey-sz-val" style="color:var(--gray-light);"><?= $jerseyOther ?></span>
          </div>
          <?php endif; ?>
          <div class="jersey-sz is-total">
            <span class="jersey-sz-lbl">Total</span>
            <span class="jersey-sz-val"><?= $jerseyTotal ?></span>
          </div>
        </div>
      </div>

      <!-- Filter Bar -->
      <div class="ship-filter">
        <form method="GET" class="row g-2 align-items-end" id="filterForm">
          <div class="col-md-4 col-sm-6">
            <input type="text" name="search" class="form-control-custom" placeholder="Cari nama / email..." value="<?= sanitize($search) ?>">
          </div>
          <div class="col-md-3 col-sm-6">
            <select name="status" class="form-control-custom" id="statusFilter">
              <option value="">Semua Status Kirim</option>
              <option value="not_ready"  <?= $filterStatus === 'not_ready'  ? 'selected' : '' ?>>Belum Siap</option>
              <option value="preparing"  <?= $filterStatus === 'preparing'  ? 'selected' : '' ?>>Sedang Disiapkan</option>
              <option value="shipped"    <?= $filterStatus === 'shipped'    ? 'selected' : '' ?>>Dikirim</option>
              <option value="delivered"  <?= $filterStatus === 'delivered'  ? 'selected' : '' ?>>Terkirim</option>
              <option value="no_address" <?= $filterStatus === 'no_address' ? 'selected' : '' ?>>&#9888; Alamat Kosong</option>
            </select>
          </div>
          <div class="col-md-2 col-sm-6">
            <select name="category" class="form-control-custom">
              <option value="">Semua Kategori</option>
              <option value="10K" <?= $filterCategory === '10K' ? 'selected' : '' ?>>10K</option>
              <option value="21K" <?= $filterCategory === '21K' ? 'selected' : '' ?>>21K</option>
            </select>
          </div>
          <div class="col-md-2 col-sm-6">
            <button type="submit" class="btn-primary-custom" style="width:100%;justify-content:center;padding:10px 0;">
              <i class="fa fa-filter"></i> Filter
            </button>
          </div>
          <?php if ($filterStatus || $search || $filterCategory): ?>
          <div class="col-auto">
            <a href="?" class="btn-outline-custom" style="padding:10px 16px;" title="Reset filter">
              <i class="fa fa-times"></i>
            </a>
          </div>
          <?php endif; ?>
        </form>
      </div>

      <!-- Table -->
      <div class="table-card">
        <div class="table-card-header">
          <div class="table-card-title"><i class="fa fa-shipping-fast"></i> Data Pengiriman</div>
          <div class="table-card-meta">Hal. <?= $page ?> / <?= $totalPages ?> &middot; <?= $total ?> peserta</div>
        </div>
        <div style="overflow-x:auto;">
          <table class="table-custom" id="shippingTable">
            <thead>
              <tr>
                <th class="th-cb">
                  <input type="checkbox" id="checkAll" class="cb-custom" title="Pilih Semua">
                </th>
                <th>Peserta</th>
                <th>Kat.</th>
                <th>Alamat &amp; Jersey</th>
                <th>Status</th>
                <th>Kurir &amp; Resi</th>
                <th style="width:90px;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row):
                $hasAddr    = !empty($row['address_full']);
                $ssBadge    = ['not_ready'=>'ss-not-ready','preparing'=>'ss-preparing','shipped'=>'ss-shipped','delivered'=>'ss-delivered'];
                $ssLabel    = ['not_ready'=>'Belum','preparing'=>'Disiapkan','shipped'=>'Dikirim','delivered'=>'Terkirim'];
                $badgeClass = $ssBadge[$row['shipping_status']] ?? 'ss-not-ready';
              ?>
              <tr data-uid="<?= $row['user_id'] ?>">
                <td class="td-cb">
                  <input type="checkbox" class="row-check cb-custom" value="<?= $row['user_id'] ?>">
                </td>
                <td>
                  <div class="cell-name"><?= sanitize($row['name']) ?></div>
                  <div class="cell-sub"><?= sanitize($row['email']) ?></div>
                  <?php if ($row['phone']): ?>
                  <div class="cell-sub"><?= sanitize($row['phone']) ?></div>
                  <?php endif; ?>
                </td>
                <td><span class="badge-category"><?= $row['category'] ?></span></td>
                <td style="max-width:200px;">
                  <?php if ($hasAddr): ?>
                  <div class="cell-sub" style="line-height:1.5;">
                    <?= sanitize(substr($row['address_full'], 0, 60)) ?><?= strlen($row['address_full']) > 60 ? '&#8230;' : '' ?>
                    <br><?= sanitize($row['city'] ?? '-') ?>, <?= sanitize($row['province'] ?? '-') ?>
                    <?php if ($row['postal_code']): ?> <?= sanitize($row['postal_code']) ?><?php endif; ?>
                  </div>
                  <?php else: ?>
                  <span style="font-size:11px;color:#ef4444;display:flex;align-items:center;gap:4px;"><i class="fa fa-exclamation-triangle"></i> Belum diisi</span>
                  <?php endif; ?>
                  <?php if ($row['jersey_size']): ?>
                  <span class="jersey-pill"><?= sanitize($row['jersey_size']) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="ss-badge <?= $badgeClass ?>"><?= $ssLabel[$row['shipping_status']] ?? '-' ?></span>
                  <?php if ($row['shipped_at']): ?>
                  <div class="cell-sub" style="margin-top:4px;"><?= $row['shipped_at'] ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($row['courier']): ?>
                  <div class="cell-sub"><?= sanitize($row['courier']) ?></div>
                  <?php endif; ?>
                  <?php if ($row['tracking_number']): ?>
                  <div>
                    <span class="tracking-num"><?= sanitize($row['tracking_number']) ?></span>
                    <button onclick="copyText('<?= addslashes($row['tracking_number']) ?>')"
                            class="copy-btn" title="Copy resi"><i class="fa fa-copy"></i></button>
                  </div>
                  <?php else: ?><span class="cell-sub">&#8212;</span><?php endif; ?>
                </td>
                <td>
                  <button onclick="openUpdateModal(<?= $row['user_id'] ?>,<?= $row['event_id'] ?>,'<?= $row['shipping_status'] ?>','<?= addslashes($row['courier'] ?? '') ?>','<?= addslashes($row['tracking_number'] ?? '') ?>')"
                          class="action-btn action-btn-edit" style="width:100%;justify-content:center;">
                    <i class="fa fa-edit"></i> Update
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($rows)): ?>
              <tr><td colspan="7" style="text-align:center;padding:48px 20px;color:var(--gray);">
                <i class="fa fa-box-open" style="font-size:32px;display:block;margin-bottom:10px;opacity:.2;"></i>
                Tidak ada data.
              </td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="table-pagination">
          <?php for ($i = 1; $i <= min($totalPages, 15); $i++): ?>
          <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filterStatus) ?>&category=<?= urlencode($filterCategory) ?>"
             class="pg-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
          <?php endfor; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="list-footer" id="listFooter">
        Menampilkan <?= count($rows) ?> dari <?= $total ?> peserta<?php if ($filterStatus || $search): ?> (difilter)<?php endif; ?>
      </div>

    </div><!-- /page-content -->
  </div>
</div>

<!-- ══════ BULK ACTION BAR ══════ -->
<div id="bulkBar" class="bulk-bar-bottom">
  <div style="display:flex;align-items:center;gap:8px;min-width:110px;">
    <div class="kpi-icon" style="background:rgba(249,115,22,0.15);color:var(--primary);width:32px;height:32px;">
      <i class="fa fa-check-square" style="font-size:14px;"></i>
    </div>
    <div>
      <div style="font-size:15px;font-weight:700;color:#fff;line-height:1;"><span id="selectedCount">0</span> dipilih</div>
      <div style="font-size:10px;color:var(--gray-light);">peserta</div>
    </div>
  </div>
  <div style="width:1px;height:32px;background:rgba(255,255,255,0.08);flex-shrink:0;"></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;flex:1;">
    <button onclick="openBulkModal('preparing')" class="bulk-btn bulk-btn-preparing">
      <i class="fa fa-box"></i> Disiapkan
    </button>
    <button onclick="openBulkModal('shipped')" class="bulk-btn bulk-btn-shipped">
      <i class="fa fa-shipping-fast"></i> Tandai Dikirim
    </button>
    <button onclick="openBulkModal('delivered')" class="bulk-btn bulk-btn-delivered">
      <i class="fa fa-check-circle"></i> Terkirim
    </button>
  </div>
  <button onclick="clearSelection()" class="bulk-btn bulk-btn-cancel" style="margin-left:auto;">
    <i class="fa fa-times"></i> Batal
  </button>
</div>

<!-- ══════ MODAL: Single Update ══════ -->
<div class="modal-overlay" id="updateModal">
  <div class="modal-box" style="max-width:440px;">
    <button class="modal-close" onclick="closeModal('updateModal')">&times;</button>
    <h3 class="modal-title"><i class="fa fa-shipping-fast" style="color:var(--primary);margin-right:8px;"></i>Update Pengiriman</h3>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="update_single">
      <input type="hidden" name="user_id"  id="upUserId">
      <input type="hidden" name="event_id" id="upEventId">
      <div class="form-group">
        <label class="form-label">Status Pengiriman</label>
        <select name="status" id="upStatus" class="form-control-custom">
          <option value="not_ready">Belum Siap</option>
          <option value="preparing">Sedang Disiapkan</option>
          <option value="shipped">Dalam Pengiriman</option>
          <option value="delivered">Terkirim</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Nama Kurir</label>
        <input type="text" name="courier" id="upCourier" class="form-control-custom" placeholder="JNE, J&T, SiCepat, dll.">
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Nomor Resi</label>
        <input type="text" name="tracking_number" id="upTracking" class="form-control-custom" placeholder="Nomor resi pengiriman">
      </div>
      <div class="d-flex gap-3 mt-4">
        <button type="submit" class="btn-primary-custom" style="flex:1;justify-content:center;padding:12px;">
          <i class="fa fa-save"></i> Simpan
        </button>
        <button type="button" onclick="closeModal('updateModal')" class="btn-outline-custom" style="padding:12px 20px;">Batal</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════ MODAL: Bulk Update ══════ -->
<div class="modal-overlay" id="bulkModal">
  <div class="modal-box" style="max-width:480px;">
    <button class="modal-close" onclick="closeModal('bulkModal')">&times;</button>
    <h3 class="modal-title"><i class="fa fa-layer-group" style="color:var(--primary);margin-right:8px;"></i>Bulk Update Pengiriman</h3>
    <div id="bulkStatusInfo" class="info-box info-box-primary">
      Mengubah status <strong id="bulkStatusLabel"></strong> untuk <strong id="bulkCountLabel"></strong> peserta yang dipilih.
    </div>
    <form method="POST" id="bulkForm">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="bulk_update">
      <input type="hidden" name="bulk_status" id="bulkStatusInput">
      <input type="hidden" name="selected_ids" id="bulkIdsInput">
      <div class="form-group">
        <label class="form-label">Kurir <span style="color:var(--gray-light);font-weight:400;">(opsional)</span></label>
        <input type="text" name="bulk_courier" class="form-control-custom" placeholder="JNE, J&T, SiCepat, dll." id="bulkCourierInput">
      </div>
      <div class="form-group" style="margin-bottom:0;" id="bulkTrackingGroup">
        <label class="form-label">No. Resi <span style="color:var(--gray-light);font-weight:400;">(opsional)</span></label>
        <input type="text" name="bulk_tracking" class="form-control-custom" placeholder="Kosongkan jika resi berbeda tiap peserta">
      </div>
      <div class="d-flex gap-3 mt-4">
        <button type="submit" class="btn-primary-custom" style="flex:1;justify-content:center;padding:12px;">
          <i class="fa fa-save"></i> Update Semua
        </button>
        <button type="button" onclick="closeModal('bulkModal')" class="btn-outline-custom" style="padding:12px 20px;">Batal</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════ MODAL: Import CSV ══════ -->
<div class="modal-overlay" id="importModal">
  <div class="modal-box" style="max-width:520px;">
    <button class="modal-close" onclick="closeModal('importModal')">&times;</button>
    <h3 class="modal-title"><i class="fa fa-file-import" style="color:var(--primary);margin-right:8px;"></i>Import Resi dari CSV</h3>

    <div class="info-box info-box-info" style="margin-bottom:20px;">
      <div style="font-weight:700;color:#60a5fa;margin-bottom:8px;font-size:13px;"><i class="fa fa-info-circle" style="margin-right:6px;"></i>Format CSV</div>
      <div style="font-size:12px;color:var(--gray-light);line-height:1.8;">
        Kolom 1: Nomor telepon <em>atau</em> email peserta<br>
        Kolom 2: Nomor resi<br>
        Kolom 3: Nama kurir <em>(opsional)</em><br>
        <span style="font-size:11px;">Baris pertama diabaikan (header).</span>
      </div>
      <div style="margin-top:10px;background:rgba(0,0,0,0.3);border-radius:6px;padding:8px 12px;font-family:monospace;font-size:11px;color:#9ca3af;">
        telepon,resi,kurir<br>
        08123456789,JNE12345678,JNE<br>
        08987654321,JT98765432,J&amp;T
      </div>
      <a href="data:text/csv;charset=utf-8,%EF%BB%BFtelepon%2Cresi%2Ckurir%0A08123456789%2CJNE12345678%2CJNE%0A08987654321%2CJT98765432%2CJ%26T"
         download="template-import-resi.csv"
         style="font-size:12px;color:var(--primary);text-decoration:none;display:inline-block;margin-top:8px;">
        <i class="fa fa-download" style="margin-right:4px;"></i>Download Template CSV
      </a>
    </div>

    <form method="POST" enctype="multipart/form-data" id="importForm">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="import_csv">
      <div class="form-group">
        <label class="form-label">Kolom 1 sebagai</label>
        <div class="d-flex gap-3">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;color:#fff;font-size:14px;">
            <input type="radio" name="match_col" value="phone" checked style="accent-color:var(--primary);"> Nomor Telepon
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;color:#fff;font-size:14px;">
            <input type="radio" name="match_col" value="email" style="accent-color:var(--primary);"> Email
          </label>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Kurir Default <span style="color:var(--gray-light);font-weight:400;">(jika kolom 3 kosong)</span></label>
        <input type="text" name="import_courier" class="form-control-custom" placeholder="Contoh: JNE">
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">File CSV</label>
        <div class="csv-drop" id="csvDrop" onclick="document.getElementById('csvFile').click()">
          <i class="fa fa-cloud-upload-alt" style="font-size:28px;color:var(--primary);margin-bottom:8px;display:block;"></i>
          <div style="font-size:14px;color:#fff;">Klik atau drag &amp; drop file CSV</div>
          <div id="csvFileName" style="font-size:12px;color:var(--gray-light);margin-top:6px;">Belum ada file dipilih</div>
        </div>
        <input type="file" name="csv_file" id="csvFile" accept=".csv,text/csv" style="display:none;" required>
      </div>
      <div class="d-flex gap-3 mt-4">
        <button type="submit" class="btn-primary-custom" style="flex:1;justify-content:center;padding:12px;" id="importBtn">
          <i class="fa fa-file-import"></i> Proses Import
        </button>
        <button type="button" onclick="closeModal('importModal')" class="btn-outline-custom" style="padding:12px 20px;">Batal</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
// ── KPI filter ─────────────────────────────────────────────────────────────
function filterByStatus(val) {
  document.getElementById('statusFilter').value = val;
  document.getElementById('filterForm').submit();
}

// ── Single update modal ────────────────────────────────────────────────────
function openUpdateModal(uid, eid, status, courier, tracking) {
  document.getElementById('upUserId').value   = uid;
  document.getElementById('upEventId').value  = eid;
  document.getElementById('upStatus').value   = status;
  document.getElementById('upCourier').value  = courier;
  document.getElementById('upTracking').value = tracking;
  openModal('updateModal');
}

// ── Bulk selection ─────────────────────────────────────────────────────────
const bulkBar  = document.getElementById('bulkBar');
const selCount = document.getElementById('selectedCount');

function getSelected() {
  return [...document.querySelectorAll('.row-check:checked')].map(cb => cb.value);
}
function updateBulkBar() {
  const sel = getSelected();
  selCount.textContent = sel.length;
  bulkBar.classList.toggle('show', sel.length > 0);
  document.getElementById('listFooter').style.paddingBottom = sel.length > 0 ? '80px' : '0';
  document.querySelectorAll('.row-check').forEach(cb => {
    cb.closest('tr').classList.toggle('selected', cb.checked);
  });
}
function clearSelection() {
  document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
  document.getElementById('checkAll').checked = false;
  updateBulkBar();
}

document.getElementById('checkAll').addEventListener('change', function() {
  document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
  updateBulkBar();
});
document.querySelectorAll('.row-check').forEach(cb => cb.addEventListener('change', updateBulkBar));

// ── Bulk modal ─────────────────────────────────────────────────────────────
const statusLabels = { preparing:'Disiapkan', shipped:'Dikirim', delivered:'Terkirim' };
function openBulkModal(status) {
  const sel = getSelected();
  if (sel.length === 0) { alert('Pilih minimal 1 peserta.'); return; }
  document.getElementById('bulkStatusInput').value      = status;
  document.getElementById('bulkIdsInput').value         = sel.join(',');
  document.getElementById('bulkStatusLabel').textContent = statusLabels[status] || status;
  document.getElementById('bulkCountLabel').textContent  = sel.length + ' peserta';
  document.getElementById('bulkTrackingGroup').style.display = status === 'shipped' ? 'block' : 'none';
  openModal('bulkModal');
}

// ── Import CSV UI ──────────────────────────────────────────────────────────
const csvFile = document.getElementById('csvFile');
const csvDrop = document.getElementById('csvDrop');
const csvName = document.getElementById('csvFileName');

csvFile.addEventListener('change', () => {
  csvName.textContent = csvFile.files[0]?.name || 'Belum ada file dipilih';
  csvDrop.style.borderColor = csvFile.files[0] ? 'var(--primary)' : '';
});
csvDrop.addEventListener('dragover', e => { e.preventDefault(); csvDrop.classList.add('over'); });
csvDrop.addEventListener('dragleave', () => csvDrop.classList.remove('over'));
csvDrop.addEventListener('drop', e => {
  e.preventDefault(); csvDrop.classList.remove('over');
  const f = e.dataTransfer.files[0];
  if (f) {
    const dt = new DataTransfer(); dt.items.add(f);
    csvFile.files = dt.files;
    csvName.textContent = f.name;
    csvDrop.style.borderColor = 'var(--primary)';
  }
});
document.getElementById('importForm').addEventListener('submit', () => {
  document.getElementById('importBtn').innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memproses...';
  document.getElementById('importBtn').disabled = true;
});

// ── Copy tracking ──────────────────────────────────────────────────────────
function copyText(text) {
  navigator.clipboard?.writeText(text).then(() => {
    event.target.closest('button').innerHTML = '<i class="fa fa-check" style="color:#22c55e;"></i>';
    setTimeout(() => { event.target.closest('button').innerHTML = '<i class="fa fa-copy"></i>'; }, 1500);
  });
}
</script>
</body>
</html>
