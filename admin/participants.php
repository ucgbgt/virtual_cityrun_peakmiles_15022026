<?php
$pageTitle = 'Peserta';
$activeNav = 'admin-participants';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db    = getDB();
$event = getActiveEvent();

$search          = $_GET['search']   ?? '';
$filterCategory  = $_GET['category'] ?? '';
$filterStatus    = $_GET['status']   ?? '';
$filterShipping  = $_GET['shipping'] ?? '';
$filterPayment   = $_GET['payment']  ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

// ── Build shared WHERE clause (used by both list query and CSV export) ─────────
$where  = ['1=1'];
$params = [];
if ($event)          { $where[] = 'r.event_id = ?';                       $params[] = $event['id']; }
if ($search)         { $where[] = '(u.name LIKE ? OR u.email LIKE ?)';    $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filterCategory) { $where[] = 'r.category = ?';                       $params[] = $filterCategory; }
if ($filterStatus)   { $where[] = 'r.status = ?';                         $params[] = $filterStatus; }
if ($filterShipping) { $where[] = 'COALESCE(s.status,"not_ready") = ?';   $params[] = $filterShipping; }
if ($filterPayment === 'paid') {
    $where[] = "(r.payment_status='paid' OR r.admin_activated=1)";
} elseif ($filterPayment === 'unpaid') {
    $where[] = "(r.payment_status='unpaid' AND (r.admin_activated IS NULL OR r.admin_activated=0))";
}
$whereStr = implode(' AND ', $where);

// ── CSV Export — MUST run before any HTML output ───────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportSql = "
        SELECT u.name, u.email, u.phone,
               r.category, r.target_km, r.status, r.payment_status,
               CASE
                 WHEN r.payment_status='paid' THEN 'Lunas'
                 WHEN r.admin_activated=1 THEN 'Manual (Admin)'
                 ELSE 'Belum Bayar'
               END AS status_akun,
               r.admin_activated, r.activation_note,
               COALESCE(SUM(rs.distance_km),0) AS total_km,
               r.merchant_order_id, r.payment_reference,
               DATE_FORMAT(r.registered_at,'%d/%m/%Y %H:%i') AS tgl_daftar,
               p.province, p.city, p.jersey_size,
               COALESCE(s.status,'not_ready') AS shipping_status,
               s.courier, s.tracking_number
        FROM registrations r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN user_profiles p ON p.user_id = u.id
        LEFT JOIN shipping s ON s.user_id = r.user_id AND s.event_id = r.event_id
        LEFT JOIN run_submissions rs ON rs.user_id = r.user_id AND rs.event_id = r.event_id AND rs.status = 'approved'
        WHERE $whereStr
        GROUP BY r.id, u.name, u.email, u.phone, r.category, r.target_km, r.status,
                 r.payment_status, r.admin_activated, r.activation_note,
                 r.merchant_order_id, r.payment_reference, r.registered_at,
                 p.province, p.city, p.jersey_size, s.status, s.courier, s.tracking_number
        ORDER BY r.registered_at DESC
    ";
    $exportStmt = $db->prepare($exportSql);
    $exportStmt->execute($params);
    $rows = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'peserta-peakmiles-' . date('Ymd-His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM agar Excel terbaca dengan benar
    fputs($out, "\xEF\xBB\xBF");

    // Header row
    fputcsv($out, [
        'No', 'Nama', 'Email', 'Telepon',
        'Kategori', 'Target KM', 'Total KM', 'Progress %',
        'Status Run', 'Status Akun', 'Catatan Aktivasi',
        'Order ID Pembayaran', 'Referensi Pembayaran',
        'Tanggal Daftar',
        'Kota', 'Provinsi', 'Ukuran Jersey',
        'Status Pengiriman', 'Kurir', 'No. Resi',
    ]);

    $no = 1;
    foreach ($rows as $row) {
        $targetKm  = (float)($row['target_km']  ?? 0);
        $totalKm   = (float)($row['total_km']   ?? 0);
        $progress  = $targetKm > 0 ? round($totalKm / $targetKm * 100, 1) : 0;

        $shippingMap = ['not_ready'=>'Belum Siap','preparing'=>'Disiapkan','shipped'=>'Dikirim','delivered'=>'Terkirim'];

        fputcsv($out, [
            $no++,
            $row['name']               ?? '',
            $row['email']              ?? '',
            $row['phone']              ?? '',
            $row['category']           ?? '',
            $targetKm,
            number_format($totalKm, 2, '.', ''),
            $progress . '%',
            ucfirst($row['status']     ?? ''),
            $row['status_akun']        ?? '',
            $row['activation_note']    ?? '',
            $row['merchant_order_id']  ?? '',
            $row['payment_reference']  ?? '',
            $row['tgl_daftar']         ?? '',
            $row['city']               ?? '',
            $row['province']           ?? '',
            $row['jersey_size']        ?? '',
            $shippingMap[$row['shipping_status']] ?? $row['shipping_status'],
            $row['courier']            ?? '',
            $row['tracking_number']    ?? '',
        ]);
    }

    fclose($out);
    exit;
}

// ── Normal page load ───────────────────────────────────────────────────────────
$countStmt = $db->prepare("SELECT COUNT(*) FROM registrations r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN shipping s ON s.user_id = r.user_id AND s.event_id = r.event_id
    WHERE $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

$stmt = $db->prepare("
    SELECT r.*, u.name, u.email, u.phone,
           p.province, p.city, p.jersey_size,
           COALESCE(s.status,'not_ready') AS shipping_status,
           s.courier, s.tracking_number,
           COALESCE((
               SELECT SUM(rs2.distance_km) FROM run_submissions rs2
               WHERE rs2.user_id=r.user_id AND rs2.event_id=r.event_id AND rs2.status='approved'
           ),0) AS total_km_approved
    FROM registrations r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN user_profiles p ON p.user_id = u.id
    LEFT JOIN shipping s ON s.user_id = r.user_id AND s.event_id = r.event_id
    WHERE $whereStr
    ORDER BY r.registered_at DESC
    LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$participants = $stmt->fetchAll();

$csrf       = generateCSRFToken();
$totalPages = $total ? ceil($total / $perPage) : 1;

// ── Build export URL carrying ALL active filters ───────────────────────────────
$exportParams = http_build_query(array_filter([
    'export'   => 'csv',
    'search'   => $search,
    'category' => $filterCategory,
    'status'   => $filterStatus,
    'payment'  => $filterPayment,
    'shipping' => $filterShipping,
]));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Peserta — PeakMiles Admin</title>
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
        <div class="topbar-title">Daftar Peserta (<?= $total ?>)</div>
      </div>
      <div class="d-flex gap-2">
        <a href="<?= SITE_URL ?>/admin/participants-add" class="btn-primary-custom btn-sm-custom">
          <i class="fa fa-user-plus"></i> Tambah Peserta
        </a>
        <a href="?<?= $exportParams ?>" class="btn-outline-custom btn-sm-custom">
          <i class="fa fa-file-csv"></i> Export CSV
        </a>
      </div>
    </div>


    <div class="page-content">
      <div class="form-card mb-4">
        <form method="GET" class="row g-3">
          <div class="col-md-3">
            <input type="text" name="search" class="form-control-custom" placeholder="Cari nama / email..." value="<?= sanitize($search) ?>">
          </div>
          <div class="col-md-2">
            <select name="category" class="form-control-custom">
              <option value="">Semua Kategori</option>
              <option value="10K" <?= $filterCategory === '10K' ? 'selected' : '' ?>>10K</option>
              <option value="21K" <?= $filterCategory === '21K' ? 'selected' : '' ?>>21K</option>
            </select>
          </div>
          <div class="col-md-2">
            <select name="status" class="form-control-custom">
              <option value="">Semua Status</option>
              <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
              <option value="finisher" <?= $filterStatus === 'finisher' ? 'selected' : '' ?>>Finisher</option>
            </select>
          </div>
          <div class="col-md-2">
            <select name="payment" class="form-control-custom">
              <option value="">Status Bayar</option>
              <option value="paid" <?= $filterPayment === 'paid' ? 'selected' : '' ?>>Aktif</option>
              <option value="unpaid" <?= $filterPayment === 'unpaid' ? 'selected' : '' ?>>Belum Aktif</option>
            </select>
          </div>
          <div class="col-md-2">
            <select name="shipping" class="form-control-custom">
              <option value="">Status Kirim</option>
              <option value="not_ready">Belum Siap</option>
              <option value="preparing">Disiapkan</option>
              <option value="shipped">Dikirim</option>
              <option value="delivered">Terkirim</option>
            </select>
          </div>
          <div class="col-md-1">
            <button type="submit" class="btn-primary-custom" style="width:100%;justify-content:center;padding:10px 0;"><i class="fa fa-filter"></i></button>
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
                <th>Progres</th>
                <th>Status Run</th>
                <th>Pembayaran</th>
                <th>Jersey / Kota</th>
                <th>Status Kirim</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($participants as $p): 
                $pct = $p['target_km'] > 0 ? min(100, round(($p['total_km_approved']/$p['target_km'])*100)) : 0;
                $shippingLabels = ['not_ready'=>'Belum','preparing'=>'Disiapkan','shipped'=>'Dikirim','delivered'=>'Terkirim'];
                $isPaid = ($p['payment_status'] ?? 'unpaid') === 'paid';
                $isAdminActivated = !empty($p['admin_activated']);
                $isAccountActive = $isPaid || $isAdminActivated;
              ?>
              <tr id="row-<?= $p['id'] ?>">
                <td>
                  <div style="font-weight:600;color:#fff;"><?= sanitize($p['name']) ?></div>
                  <div style="font-size:11px;color:var(--gray-light);"><?= sanitize($p['email']) ?></div>
                  <?php if ($p['phone']): ?><div style="font-size:11px;color:var(--gray-light);"><?= sanitize($p['phone']) ?></div><?php endif; ?>
                </td>
                <td><span class="status-badge" style="background:rgba(249,115,22,0.1);color:var(--primary);"><?= $p['category'] ?></span></td>
                <td style="min-width:110px;">
                  <div style="font-size:12px;color:var(--gray-light);margin-bottom:4px;"><?= number_format($p['total_km_approved'],2) ?> / <?= $p['target_km'] ?> km</div>
                  <div class="progress-bar-bg" style="height:6px;">
                    <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,var(--primary),#fb923c);border-radius:100px;"></div>
                  </div>
                  <div style="font-size:11px;color:var(--primary);margin-top:2px;"><?= $pct ?>%</div>
                </td>
                <td><span class="status-badge badge-<?= $p['status'] ?>"><?= $p['status'] === 'finisher' ? '<i class="fa fa-trophy" style="font-size:10px;margin-right:4px;"></i>Finisher' : 'Active' ?></span></td>
                <td id="pay-badge-<?= $p['id'] ?>">
                  <?php if ($isPaid): ?>
                    <span class="status-badge" style="background:rgba(34,197,94,0.12);color:#22c55e;border:1px solid rgba(34,197,94,0.25);" title="Lunas via Duitku"><i class="fa fa-check-circle" style="font-size:10px;margin-right:3px;"></i>Lunas</span>
                  <?php elseif ($isAdminActivated): ?>
                    <span class="status-badge" style="background:rgba(59,130,246,0.12);color:#60a5fa;border:1px solid rgba(59,130,246,0.25);" title="Diaktifkan admin: <?= sanitize($p['activation_note'] ?? '') ?>"><i class="fa fa-user-shield" style="font-size:10px;margin-right:3px;"></i>Manual</span>
                  <?php else: ?>
                    <span class="status-badge" style="background:rgba(239,68,68,0.1);color:#ef4444;border:1px solid rgba(239,68,68,0.2);"><i class="fa fa-clock" style="font-size:10px;margin-right:3px;"></i>Belum Bayar</span>
                  <?php endif; ?>
                </td>
                <td style="font-size:12px;color:var(--gray-light);"><?= $p['jersey_size'] ?: '-' ?><br><?= sanitize($p['city'] ?? '-') ?></td>
                <td><span class="status-badge badge-<?= $p['shipping_status'] ?>"><?= $shippingLabels[$p['shipping_status']] ?? '-' ?></span></td>
                <td>
                  <?php if (!$isAccountActive): ?>
                  <button class="btn-outline-custom btn-sm-custom" style="font-size:11px;padding:5px 10px;border-color:rgba(34,197,94,0.4);color:#22c55e;"
                    onclick="activateParticipant(<?= $p['id'] ?>, 1)">
                    <i class="fa fa-user-check"></i> Aktifkan
                  </button>
                  <?php else: ?>
                  <button class="btn-outline-custom btn-sm-custom" style="font-size:11px;padding:5px 10px;border-color:rgba(239,68,68,0.35);color:#ef4444;"
                    onclick="activateParticipant(<?= $p['id'] ?>, 0)" <?= $isPaid ? 'title="Sudah lunas via pembayaran"' : '' ?>>
                    <i class="fa fa-user-times"></i> Nonaktifkan
                  </button>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($participants)): ?>
              <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--gray-light);">Tidak ada peserta ditemukan.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="pagination-custom">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($filterCategory) ?>&status=<?= urlencode($filterStatus) ?>" 
             class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
          <?php endfor; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
function activateParticipant(regId, activate) {
  const action = activate ? 'aktifkan' : 'nonaktifkan';
  let note = '';
  if (activate) {
    note = prompt('Catatan aktivasi (opsional, contoh: "Jalur undangan", "Transfer manual"):') ?? '';
  } else {
    if (!confirm('Yakin ingin nonaktifkan akun peserta ini?')) return;
  }

  const btn = document.querySelector(`#row-${regId} button`);
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>'; }

  fetch('<?= SITE_URL ?>/api/activate-participant.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ reg_id: regId, activate: activate, note: note, csrf_token: '<?= $csrf ?>' })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      const badgeEl = document.getElementById(`pay-badge-${regId}`);
      const row = document.getElementById(`row-${regId}`);
      if (activate) {
        badgeEl.innerHTML = '<span class="status-badge" style="background:rgba(59,130,246,0.12);color:#60a5fa;border:1px solid rgba(59,130,246,0.25);"><i class="fa fa-user-shield" style="font-size:10px;margin-right:3px;"></i>Manual</span>';
        row.querySelector('td:last-child').innerHTML = '<button class="btn-outline-custom btn-sm-custom" style="font-size:11px;padding:5px 10px;border-color:rgba(239,68,68,0.35);color:#ef4444;" onclick="activateParticipant(' + regId + ', 0)"><i class="fa fa-user-times"></i> Nonaktifkan</button>';
      } else {
        badgeEl.innerHTML = '<span class="status-badge" style="background:rgba(239,68,68,0.1);color:#ef4444;border:1px solid rgba(239,68,68,0.2);"><i class="fa fa-clock" style="font-size:10px;margin-right:3px;"></i>Belum Bayar</span>';
        row.querySelector('td:last-child').innerHTML = '<button class="btn-outline-custom btn-sm-custom" style="font-size:11px;padding:5px 10px;border-color:rgba(34,197,94,0.4);color:#22c55e;" onclick="activateParticipant(' + regId + ', 1)"><i class="fa fa-user-check"></i> Aktifkan</button>';
      }
    } else {
      alert('Gagal: ' + (data.message || 'Terjadi kesalahan.'));
      location.reload();
    }
  })
  .catch(() => { alert('Gagal terhubung ke server.'); location.reload(); });
}
</script>
</body>
</html>
