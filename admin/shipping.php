<?php
$pageTitle = 'Manajemen Pengiriman';
$activeNav = 'admin-shipping';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db = getDB();
$event = getActiveEvent();
$adminUser = getCurrentUser();

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_shipping'])) {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) die('Invalid CSRF');
    $userId = (int)$_POST['user_id'];
    $eventId = (int)$_POST['event_id'];
    $status = $_POST['status'];
    $courier = trim($_POST['courier'] ?? '');
    $tracking = trim($_POST['tracking_number'] ?? '');
    $shippedAt = $status === 'shipped' || $status === 'delivered' ? date('Y-m-d H:i:s') : null;

    // Check existing
    $existing = $db->prepare("SELECT id FROM shipping WHERE user_id=? AND event_id=?");
    $existing->execute([$userId, $eventId]);
    if ($existing->fetch()) {
        $db->prepare("UPDATE shipping SET status=?,courier=?,tracking_number=?,shipped_at=COALESCE(shipped_at,?),updated_by=? WHERE user_id=? AND event_id=?")
           ->execute([$status, $courier, $tracking, $shippedAt, $adminUser['id'], $userId, $eventId]);
    } else {
        $db->prepare("INSERT INTO shipping (user_id,event_id,status,courier,tracking_number,shipped_at,updated_by) VALUES (?,?,?,?,?,?,?)")
           ->execute([$userId, $eventId, $status, $courier, $tracking, $shippedAt, $adminUser['id']]);
    }
    logAudit($adminUser['id'], 'update_shipping', 'shipping', $userId);
    flash('success', 'Status pengiriman berhasil diperbarui.');
    redirect(SITE_URL . '/admin/shipping');
}

$filterStatus = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];
if ($event) { $where[] = 'r.event_id = ?'; $params[] = $event['id']; }
if ($search) { $where[] = '(u.name LIKE ? OR u.email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filterStatus) { $where[] = 'COALESCE(s.status,"not_ready") = ?'; $params[] = $filterStatus; }
$whereStr = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM registrations r 
    JOIN users u ON r.user_id=u.id 
    LEFT JOIN shipping s ON s.user_id=r.user_id AND s.event_id=r.event_id WHERE $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

$stmt = $db->prepare("SELECT r.user_id, r.event_id, r.category, r.status as reg_status,
    u.name, u.email, u.phone,
    p.address_full, p.city, p.province, p.postal_code, p.jersey_size,
    COALESCE(s.status,'not_ready') as shipping_status, s.courier, s.tracking_number, s.shipped_at
    FROM registrations r 
    JOIN users u ON r.user_id=u.id
    LEFT JOIN user_profiles p ON p.user_id=u.id
    LEFT JOIN shipping s ON s.user_id=r.user_id AND s.event_id=r.event_id
    WHERE $whereStr ORDER BY r.registered_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$rows = $stmt->fetchAll();
$totalPages = $total ? ceil($total / $perPage) : 1;
$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manajemen Pengiriman — PeakMiles Admin</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@300;400;500;600;700;800;900&family=Saira:wght@700;800;900&display=swap" rel="stylesheet">
</head>
<body>
<div class="dashboard-layout">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <div class="d-flex align-items-center gap-3">
        <button id="sidebarToggle" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;" class="d-lg-none"><i class="fa fa-bars"></i></button>
        <div class="topbar-title">Manajemen Pengiriman</div>
      </div>
      <a href="?export=csv&search=<?= urlencode($search) ?>" class="btn-outline-custom btn-sm-custom">
        <i class="fa fa-file-csv"></i> Export Alamat
      </a>
    </div>

    <?php
    if (isset($_GET['export'])) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=alamat-pengiriman-'.date('Ymd').'.csv');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['Nama','Email','Telepon','Kategori','Ukuran Jersey','Alamat','Kota','Provinsi','Kode Pos','Status Kirim','Kurir','Resi']);
        $expStmt = $db->prepare("SELECT r.user_id, r.event_id, r.category, u.name, u.email, u.phone,
            p.address_full, p.city, p.province, p.postal_code, p.jersey_size,
            COALESCE(s.status,'not_ready') as shipping_status, s.courier, s.tracking_number
            FROM registrations r JOIN users u ON r.user_id=u.id
            LEFT JOIN user_profiles p ON p.user_id=u.id
            LEFT JOIN shipping s ON s.user_id=r.user_id AND s.event_id=r.event_id
            WHERE $whereStr ORDER BY r.registered_at DESC");
        $expStmt->execute($params);
        foreach ($expStmt->fetchAll() as $row) {
            fputcsv($out, [$row['name'],$row['email'],$row['phone'],$row['category'],$row['jersey_size'],
                $row['address_full'],$row['city'],$row['province'],$row['postal_code'],
                $row['shipping_status'],$row['courier'],$row['tracking_number']]);
        }
        fclose($out);
        exit;
    }
    ?>

    <div class="page-content">
      <?php $flash = getFlash(); if ($flash): ?>
      <div class="alert-custom alert-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <div class="form-card mb-4">
        <form method="GET" class="row g-3 align-items-end">
          <div class="col-md-5">
            <input type="text" name="search" class="form-control-custom" placeholder="Cari nama / email..." value="<?= sanitize($search) ?>">
          </div>
          <div class="col-md-4">
            <select name="status" class="form-control-custom">
              <option value="">Semua Status Kirim</option>
              <option value="not_ready" <?= $filterStatus === 'not_ready' ? 'selected' : '' ?>>Belum Siap</option>
              <option value="preparing" <?= $filterStatus === 'preparing' ? 'selected' : '' ?>>Sedang Disiapkan</option>
              <option value="shipped" <?= $filterStatus === 'shipped' ? 'selected' : '' ?>>Dikirim</option>
              <option value="delivered" <?= $filterStatus === 'delivered' ? 'selected' : '' ?>>Terkirim</option>
            </select>
          </div>
          <div class="col-md-3">
            <button type="submit" class="btn-primary-custom" style="width:100%;justify-content:center;"><i class="fa fa-filter"></i> Filter</button>
          </div>
        </form>
      </div>

      <div class="table-container">
        <div style="overflow-x:auto;">
          <table class="table-custom">
            <thead>
              <tr>
                <th>Peserta</th>
                <th>Kategori</th>
                <th>Alamat</th>
                <th>Jersey</th>
                <th>Status Kirim</th>
                <th>Kurir & Resi</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row): ?>
              <tr>
                <td>
                  <div style="font-weight:600;color:#fff;"><?= sanitize($row['name']) ?></div>
                  <div style="font-size:11px;color:var(--gray-light);"><?= sanitize($row['email']) ?></div>
                  <?php if ($row['phone']): ?><div style="font-size:11px;color:var(--gray-light);"><?= sanitize($row['phone']) ?></div><?php endif; ?>
                </td>
                <td><span class="status-badge" style="background:rgba(249,115,22,0.1);color:var(--primary);"><?= $row['category'] ?></span></td>
                <td style="max-width:180px;">
                  <div style="font-size:12px;color:var(--gray-light);line-height:1.5;">
                    <?= $row['address_full'] ? sanitize(substr($row['address_full'],0,80)) . '...' : '<span style="color:var(--danger);">Belum diisi</span>' ?>
                    <br><?= sanitize($row['city'] ?? '-') ?>, <?= sanitize($row['province'] ?? '-') ?> <?= sanitize($row['postal_code'] ?? '') ?>
                  </div>
                </td>
                <td style="color:var(--gray-light);font-size:13px;font-weight:600;"><?= $row['jersey_size'] ?: '-' ?></td>
                <td><span class="status-badge badge-<?= $row['shipping_status'] ?>">
                  <?php $sl=['not_ready'=>'Belum','preparing'=>'Disiapkan','shipped'=>'Dikirim','delivered'=>'Terkirim']; echo $sl[$row['shipping_status']] ?? '-'; ?>
                </span></td>
                <td style="font-size:12px;color:var(--gray-light);">
                  <?php if ($row['courier']): ?>
                  <div><?= sanitize($row['courier']) ?></div>
                  <div style="color:var(--primary);font-weight:600;"><?= sanitize($row['tracking_number'] ?? '') ?></div>
                  <?php else: ?> — <?php endif; ?>
                </td>
                <td>
                  <button onclick="showShippingModal(<?= $row['user_id'] ?>,<?= $row['event_id'] ?>,'<?= $row['shipping_status'] ?>','<?= addslashes($row['courier'] ?? '') ?>','<?= addslashes($row['tracking_number'] ?? '') ?>')"
                          class="btn-primary-custom btn-sm-custom">
                    <i class="fa fa-edit"></i> Update
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($rows)): ?>
              <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--gray-light);">Tidak ada data.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="pagination-custom">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filterStatus) ?>" 
             class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
          <?php endfor; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Shipping Update Modal -->
<div class="modal-overlay" id="shippingModal">
  <div class="modal-box" style="max-width:440px;">
    <button class="modal-close" onclick="closeModal('shippingModal')">&times;</button>
    <h3 class="modal-title"><i class="fa fa-shipping-fast" style="color:var(--primary);margin-right:8px;"></i>Update Pengiriman</h3>
    <form method="POST" action="">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="update_shipping" value="1">
      <input type="hidden" name="user_id" id="shpUserId">
      <input type="hidden" name="event_id" id="shpEventId">
      <div class="form-group">
        <label class="form-label">Status Pengiriman</label>
        <select name="status" id="shpStatus" class="form-control-custom">
          <option value="not_ready">Belum Siap</option>
          <option value="preparing">Sedang Disiapkan</option>
          <option value="shipped">Dalam Pengiriman</option>
          <option value="delivered">Terkirim</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Nama Kurir</label>
        <input type="text" name="courier" id="shpCourier" class="form-control-custom" placeholder="JNE, J&T, SiCepat, dll.">
      </div>
      <div class="form-group">
        <label class="form-label">Nomor Resi</label>
        <input type="text" name="tracking_number" id="shpTracking" class="form-control-custom" placeholder="Masukkan nomor resi">
      </div>
      <div class="d-flex gap-3">
        <button type="submit" class="btn-primary-custom" style="flex:1;justify-content:center;"><i class="fa fa-save"></i> Simpan</button>
        <button type="button" onclick="closeModal('shippingModal')" class="btn-outline-custom">Batal</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
function showShippingModal(userId, eventId, status, courier, tracking) {
  document.getElementById('shpUserId').value = userId;
  document.getElementById('shpEventId').value = eventId;
  document.getElementById('shpStatus').value = status;
  document.getElementById('shpCourier').value = courier;
  document.getElementById('shpTracking').value = tracking;
  openModal('shippingModal');
}
</script>
</body>
</html>
