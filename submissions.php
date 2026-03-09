<?php
$pageTitle = 'Riwayat Lari';
$activeNav = 'submissions';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$user = getCurrentUser();
$event = getActiveEvent();
$registration = $event ? getUserRegistration($user['id'], $event['id']) : null;
$db = getDB();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$total = 0;
$submissions = [];
if ($event) {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM run_submissions WHERE user_id=? AND event_id=?");
    $countStmt->execute([$user['id'], $event['id']]);
    $total = $countStmt->fetchColumn();

    $stmt = $db->prepare("SELECT * FROM run_submissions WHERE user_id=? AND event_id=? ORDER BY run_date DESC, created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute([$user['id'], $event['id'], $perPage, $offset]);
    $submissions = $stmt->fetchAll();
}
$totalPages = $total ? ceil($total / $perPage) : 1;
$totalKm = $registration ? getUserTotalKm($user['id'], $event['id']) : 0;
$isActive = $registration
    ? (($registration['payment_status'] ?? 'unpaid') === 'paid' || !empty($registration['admin_activated']))
    : false;
$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Riwayat Lari — PeakMiles</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@300;400;500;600;700;800;900&family=Saira:wght@700;800;900&display=swap" rel="stylesheet">
</head>
<body>
<div class="dashboard-layout">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <div class="d-flex align-items-center gap-3">
        <button id="sidebarToggle" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;" class="d-lg-none"><i class="fa fa-bars"></i></button>
        <div class="topbar-title">Riwayat Lari</div>
      </div>
      <?php if ($registration && $isActive): ?>
      <button onclick="openModal('submitRunModal')" class="btn-primary-custom btn-sm-custom">
        <i class="fa fa-plus"></i> Submit Lari
      </button>
      <?php elseif ($registration && !$isActive): ?>
      <button onclick="openModal('inactiveNoticeModal')" class="btn-primary-custom btn-sm-custom">
        <i class="fa fa-plus"></i> Submit Lari
      </button>
      <?php endif; ?>
    </div>
    <div class="page-content">
      <?php $flash = getFlash(); if ($flash): ?>
      <div class="alert-custom alert-<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'danger' : $flash['type']) ?>" style="margin-bottom:20px;">
        <i class="fa fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <?= sanitize($flash['message']) ?>
      </div>
      <?php endif; ?>
      <!-- Stats -->
      <div class="row g-4 mb-4">
        <div class="col-sm-4">
          <div class="stat-card">
            <div class="stat-card-label">Total KM Approved</div>
            <div class="stat-card-value"><?= number_format($totalKm, 2) ?></div>
            <div class="stat-card-sub">kilometer disetujui</div>
          </div>
        </div>
        <div class="col-sm-4">
          <div class="stat-card">
            <div class="stat-card-label">Total Submission</div>
            <div class="stat-card-value"><?= $total ?></div>
            <div class="stat-card-sub">total semua</div>
          </div>
        </div>
        <div class="col-sm-4">
          <div class="stat-card">
            <div class="stat-card-label">Kategori</div>
            <div class="stat-card-value"><?= $registration ? $registration['category'] : '-' ?></div>
            <div class="stat-card-sub">target <?= $registration ? formatKm((float)$registration['target_km']) : '-' ?></div>
          </div>
        </div>
      </div>

      <div class="table-container">
        <div class="table-header">
          <div class="table-title">Semua Submission (<?= $total ?>)</div>
        </div>
        <?php if (empty($submissions)): ?>
        <div style="padding:60px;text-align:center;color:var(--gray-light);">
          <i class="fa fa-running" style="font-size:48px;margin-bottom:16px;display:block;opacity:0.2;"></i>
          Belum ada submission. Mulai lari dan submit buktimu!
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
          <table class="table-custom">
            <thead>
              <tr>
                <th>Tanggal Lari</th>
                <th>Jarak</th>
                <th>Bukti</th>
                <th>Catatan</th>
                <th>Status</th>
                <th>Admin Note</th>
                <th>Disubmit</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($submissions as $sub): ?>
              <tr>
                <td style="white-space:nowrap;"><?= date('d M Y', strtotime($sub['run_date'])) ?></td>
                <td style="font-weight:700;color:var(--primary);white-space:nowrap;"><?= number_format($sub['distance_km'], 2) ?> km</td>
                <td>
                  <?php if ($sub['evidence_path']): ?>
                  <img src="<?= UPLOAD_URL . sanitize($sub['evidence_path']) ?>" alt="Bukti" class="evidence-thumb"
                       onclick="openLightbox('<?= UPLOAD_URL . sanitize($sub['evidence_path']) ?>')">
                  <?php else: ?> — <?php endif; ?>
                </td>
                <td style="color:var(--gray-light);font-size:13px;max-width:150px;">
                  <?= $sub['notes'] ? sanitize(substr($sub['notes'], 0, 60)) : '-' ?>
                </td>
                <td>
                  <span class="status-badge badge-<?= $sub['status'] ?>">
                    <?= ucfirst($sub['status']) ?>
                  </span>
                </td>
                <td style="color:var(--gray-light);font-size:13px;max-width:200px;">
                  <?= $sub['admin_note'] ? sanitize($sub['admin_note']) : '-' ?>
                </td>
                <td style="color:var(--gray-light);font-size:12px;white-space:nowrap;"><?= timeAgo($sub['created_at']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="pagination-custom">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="?page=<?= $i ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
          <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- SUBMIT RUN MODAL -->
<!-- INACTIVE NOTICE MODAL -->
<div class="modal-overlay" id="inactiveNoticeModal">
  <div class="modal-box" style="max-width:420px;text-align:center;">
    <button class="modal-close" onclick="closeModal('inactiveNoticeModal')">&times;</button>
    <div style="width:64px;height:64px;background:rgba(239,68,68,0.12);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
      <i class="fa fa-lock" style="font-size:28px;color:#ef4444;"></i>
    </div>
    <h3 class="modal-title" style="color:#ef4444;margin-bottom:10px;">Akun Belum Aktif</h3>
    <p style="color:var(--gray-light);font-size:14px;line-height:1.6;margin-bottom:24px;">
      Akun kamu belum aktif. Selesaikan pembayaran terlebih dahulu atau hubungi admin.
    </p>
    <div style="display:flex;gap:12px;justify-content:center;">
      <button onclick="closeModal('inactiveNoticeModal')" class="btn-outline-custom" style="padding:10px 20px;">
        Tutup
      </button>
      <a href="<?= SITE_URL ?>/dashboard.php" class="btn-primary-custom" style="padding:10px 20px;text-decoration:none;">
        <i class="fa fa-credit-card"></i> Bayar Sekarang
      </a>
    </div>
  </div>
</div>

<div class="modal-overlay" id="submitRunModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('submitRunModal')">&times;</button>
    <h3 class="modal-title"><i class="fa fa-running" style="color:var(--primary);margin-right:8px;"></i>Submit Bukti Lari</h3>
    <form method="POST" action="<?= SITE_URL ?>/api/submit-run.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <?php if ($event): ?><input type="hidden" name="event_id" value="<?= $event['id'] ?>"><?php endif; ?>
      <div class="form-group">
        <label class="form-label">Tanggal Lari</label>
        <input type="date" name="run_date" class="form-control-custom" required max="<?= date('Y-m-d', strtotime('+1 day')) ?>"
               <?php if ($event): ?>min="<?= $event['start_date'] ?>"<?php endif; ?>>
      </div>
      <div class="form-group">
        <label class="form-label">Jarak (km) — maks 30km</label>
        <input type="number" name="distance_km" class="form-control-custom" placeholder="cth: 3.5" step="0.01" min="0.1" max="30" required>
      </div>
      <div class="form-group">
        <label class="form-label">Bukti Lari</label>
        <div class="file-upload-area">
          <input type="file" name="evidence" accept="image/jpeg,image/png,image/webp" required style="display:none;">
          <div class="file-upload-icon"><i class="fa fa-cloud-upload-alt"></i></div>
          <div class="file-upload-text"><strong>Klik untuk upload</strong> atau drag & drop</div>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Catatan (opsional)</label>
        <textarea name="notes" class="form-control-custom" rows="2" placeholder="Rute, kondisi, dll..."></textarea>
      </div>
      <button type="submit" class="btn-primary-custom" style="width:100%;justify-content:center;padding:14px;">
        <i class="fa fa-paper-plane"></i> Submit
      </button>
    </form>
  </div>
</div>

<div id="lightbox" class="lightbox">
  <button class="lightbox-close" onclick="closeLightbox()"><i class="fa fa-times"></i></button>
  <img id="lightbox-img" src="" alt="">
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
