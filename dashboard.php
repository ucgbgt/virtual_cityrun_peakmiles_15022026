<?php
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$user = getCurrentUser();
$event = getActiveEvent();
$registration = $event ? getUserRegistration($user['id'], $event['id']) : null;

if ($registration) {
    $totalKm = getUserTotalKm($user['id'], $event['id']);
    $targetKm = (float)$registration['target_km'];
    $progress = progressPercent($totalKm, $targetKm);
    $isFinisher = $registration['status'] === 'finisher';
}

$db = getDB();
// Recent submissions
$recentSubs = [];
if ($event) {
    $stmt = $db->prepare("SELECT * FROM run_submissions WHERE user_id=? AND event_id=? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$user['id'], $event['id']]);
    $recentSubs = $stmt->fetchAll();
}

// Shipping info
$shipping = null;
if ($event) {
    $stmt = $db->prepare("SELECT * FROM shipping WHERE user_id=? AND event_id=?");
    $stmt->execute([$user['id'], $event['id']]);
    $shipping = $stmt->fetch() ?: null;
}

// Certificate
$certificate = null;
if ($event) {
    $stmt = $db->prepare("SELECT * FROM certificates WHERE user_id=? AND event_id=?");
    $stmt->execute([$user['id'], $event['id']]);
    $certificate = $stmt->fetch() ?: null;
}

// Pending count
$pendingCount = 0;
if ($event) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM run_submissions WHERE user_id=? AND event_id=? AND status='pending'");
    $stmt->execute([$user['id'], $event['id']]);
    $pendingCount = $stmt->fetchColumn();
}

$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — PeakMiles</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Syne:wght@700;800&display=swap" rel="stylesheet">
</head>
<body>
<div class="dashboard-layout">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

  <div class="main-content">
    <div class="topbar">
      <div class="d-flex align-items-center gap-3">
        <button id="sidebarToggle" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;display:none;" class="d-lg-none">
          <i class="fa fa-bars"></i>
        </button>
        <div>
          <div class="topbar-title">Dashboard</div>
          <div style="font-size:12px;color:var(--gray-light);">Selamat datang, <?= sanitize($user['name']) ?>!</div>
        </div>
      </div>
      <?php if ($registration): ?>
      <button onclick="openModal('submitRunModal')" class="btn-primary-custom btn-sm-custom">
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
      <?php if (!$registration && $event): ?>
      <div class="alert-custom alert-warning mb-4">
        <i class="fa fa-exclamation-triangle"></i>
        <span>Kamu belum terdaftar di event aktif. Hubungi admin jika sudah melakukan pembayaran di Nusatix.</span>
      </div>
      <?php endif; ?>

      <?php if ($registration && isset($isFinisher) && $isFinisher): ?>
      <!-- FINISHER BANNER -->
      <div class="finisher-banner">
        <div class="finisher-title"><i class="fa fa-trophy" style="margin-right:8px;"></i>Selamat! Kamu adalah FINISHER!</div>
        <p style="color:var(--gray-light);margin-bottom:20px;">Kamu telah berhasil menyelesaikan target <?= $registration['category'] ?> dengan total <?= formatKm($totalKm) ?>. Luar biasa!</p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
          <?php if ($certificate): ?>
          <a href="<?= SITE_URL ?>/certificate.php" class="btn-primary-custom">
            <i class="fa fa-download"></i> Download E-Certificate
          </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($registration): ?>
      <!-- PROGRESS SECTION -->
      <div class="progress-section mb-4">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <div style="font-size:12px;color:var(--gray-light);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Progres Lari</div>
            <div class="d-flex align-items-center gap-12">
              <span class="status-badge <?= $isFinisher ? 'badge-finisher' : 'badge-active' ?>">
                <i class="fa fa-<?= $isFinisher ? 'trophy' : 'circle' ?>" style="font-size:8px;"></i>
                <?= $isFinisher ? 'FINISHER' : 'ACTIVE' ?>
              </span>
              <span style="color:var(--gray-light);font-size:13px;margin-left:8px;">Kategori <?= $registration['category'] ?></span>
            </div>
          </div>
          <div class="text-right">
            <div class="progress-km"><?= number_format($totalKm, 2) ?></div>
            <div class="progress-target">/ <?= number_format($targetKm, 2) ?> km</div>
          </div>
        </div>
        <div class="progress-bar-bg">
          <div class="progress-bar-fill" data-width="<?= $progress ?>" style="width:0%"></div>
        </div>
        <div class="progress-labels">
          <span>0 km</span>
          <span style="color:var(--primary);font-weight:600;"><?= $progress ?>% selesai</span>
          <span><?= formatKm($targetKm) ?></span>
        </div>
      </div>
      <?php endif; ?>

      <!-- STATS ROW -->
      <div class="row g-4 mb-4">
        <div class="col-sm-6 col-lg-3">
          <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(249,115,22,0.15);color:var(--primary);">
              <i class="fa fa-running"></i>
            </div>
            <div class="stat-card-label">Total KM</div>
            <div class="stat-card-value"><?= $registration ? number_format($totalKm, 1) : '0' ?></div>
            <div class="stat-card-sub">km yang disetujui</div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(245,158,11,0.15);color:var(--warning);">
              <i class="fa fa-clock"></i>
            </div>
            <div class="stat-card-label">Pending</div>
            <div class="stat-card-value"><?= $pendingCount ?></div>
            <div class="stat-card-sub">submission menunggu</div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(34,197,94,0.15);color:var(--success);">
              <i class="fa fa-check-circle"></i>
            </div>
            <div class="stat-card-label">Status</div>
            <div class="stat-card-value" style="font-size:20px;">
              <?php if ($registration && isset($isFinisher) && $isFinisher): ?>
                <i class="fa fa-trophy" style="color:var(--primary);"></i>
              <?php else: ?>
                <i class="fa fa-person-running" style="color:var(--success);"></i>
              <?php endif; ?>
            </div>
            <div class="stat-card-sub"><?= ($registration && isset($isFinisher) && $isFinisher) ? 'Finisher!' : 'Active Runner' ?></div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(59,130,246,0.15);color:var(--info);">
              <i class="fa fa-box"></i>
            </div>
            <div class="stat-card-label">Race Pack</div>
            <div class="stat-card-value" style="font-size:18px;">
              <?php
              $shippingStatuses = ['not_ready'=>'Belum','preparing'=>'Siap','shipped'=>'Dikirim','delivered'=>'Tiba'];
              echo $shipping ? ($shippingStatuses[$shipping['status']] ?? '-') : 'N/A';
              ?>
            </div>
            <div class="stat-card-sub">status pengiriman</div>
          </div>
        </div>
      </div>

      <div class="row g-4">
        <!-- Recent Submissions -->
        <div class="col-lg-8">
          <div class="table-container">
            <div class="table-header">
              <div class="table-title">Riwayat Submission Terbaru</div>
              <a href="<?= SITE_URL ?>/submissions.php" style="font-size:13px;color:var(--primary);text-decoration:none;">
                Lihat Semua <i class="fa fa-arrow-right"></i>
              </a>
            </div>
            <?php if (empty($recentSubs)): ?>
            <div style="padding:40px;text-align:center;color:var(--gray-light);">
              <i class="fa fa-running" style="font-size:36px;margin-bottom:12px;display:block;opacity:0.3;"></i>
              Belum ada submission. Yuk mulai lari!
              <?php if ($registration): ?>
              <div class="mt-3">
                <button onclick="openModal('submitRunModal')" class="btn-primary-custom btn-sm-custom">
                  <i class="fa fa-plus"></i> Submit Lari Pertama
                </button>
              </div>
              <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="overflow-x:auto;">
              <table class="table-custom">
                <thead>
                  <tr>
                    <th>Tanggal</th>
                    <th>Jarak</th>
                    <th>Status</th>
                    <th>Catatan Admin</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentSubs as $sub): ?>
                  <tr>
                    <td><?= date('d M Y', strtotime($sub['run_date'])) ?></td>
                    <td style="font-weight:600;color:var(--primary);"><?= number_format($sub['distance_km'], 2) ?> km</td>
                    <td>
                      <span class="status-badge badge-<?= $sub['status'] ?>">
                        <?= ucfirst($sub['status']) ?>
                      </span>
                    </td>
                    <td style="color:var(--gray-light);font-size:13px;">
                      <?= $sub['admin_note'] ? sanitize(substr($sub['admin_note'], 0, 40)) . '...' : '-' ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Shipping & Quick Actions -->
        <div class="col-lg-4">
          <!-- Shipping Card -->
          <div class="card-custom mb-4">
            <div class="d-flex align-items-center gap-3 mb-16" style="margin-bottom:16px;">
              <div class="card-icon" style="margin-bottom:0;width:40px;height:40px;font-size:18px;border-radius:10px;">
                <i class="fa fa-shipping-fast"></i>
              </div>
              <div class="fw-bold" style="color:#fff;">Status Pengiriman</div>
            </div>
            <?php if ($shipping): ?>
            <div class="mb-2">
              <span class="status-badge badge-<?= $shipping['status'] ?>">
                <?php
                $labels = ['not_ready'=>'Belum Siap','preparing'=>'Sedang Disiapkan','shipped'=>'Dalam Pengiriman','delivered'=>'Terkirim'];
                echo $labels[$shipping['status']] ?? $shipping['status'];
                ?>
              </span>
            </div>
            <?php if ($shipping['courier']): ?>
            <div style="font-size:13px;color:var(--gray-light);margin-top:8px;">
              <i class="fa fa-truck" style="margin-right:6px;color:var(--primary);"></i>
              <?= sanitize($shipping['courier']) ?>
            </div>
            <?php endif; ?>
            <?php if ($shipping['tracking_number']): ?>
            <div style="font-size:13px;color:var(--gray-light);margin-top:4px;">
              <i class="fa fa-barcode" style="margin-right:6px;color:var(--primary);"></i>
              <?= sanitize($shipping['tracking_number']) ?>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div style="color:var(--gray-light);font-size:13px;">Race pack akan dikirim setelah event selesai.</div>
            <?php endif; ?>
            <a href="<?= SITE_URL ?>/shipping.php" class="btn-outline-custom btn-sm-custom" style="margin-top:16px;width:100%;justify-content:center;">
              Detail Pengiriman
            </a>
          </div>

          <!-- Profile Completeness -->
          <?php
          $profileFields = ['dob','gender','address_full','province','city','postal_code','jersey_size'];
          $filled = 0;
          foreach ($profileFields as $f) { if (!empty($user[$f])) $filled++; }
          $profilePct = (int)(($filled / count($profileFields)) * 100);
          ?>
          <div class="card-custom">
            <div style="font-weight:700;color:#fff;margin-bottom:12px;">
              <i class="fa fa-user-circle" style="color:var(--primary);margin-right:8px;"></i>
              Kelengkapan Profil
            </div>
            <div class="progress-bar-bg" style="margin-bottom:8px;">
              <div class="progress-bar-fill" data-width="<?= $profilePct ?>" style="width:0%;"></div>
            </div>
            <div style="font-size:13px;color:var(--gray-light);margin-bottom:12px;"><?= $profilePct ?>% lengkap</div>
            <?php if ($profilePct < 100): ?>
            <div style="font-size:12px;color:var(--warning);margin-bottom:12px;">
              <i class="fa fa-exclamation-triangle"></i> Lengkapi profil untuk memastikan pengiriman race pack tepat sasaran.
            </div>
            <?php endif; ?>
            <a href="<?= SITE_URL ?>/profile.php" class="btn-outline-custom btn-sm-custom" style="width:100%;justify-content:center;">
              <i class="fa fa-edit"></i> <?= $profilePct < 100 ? 'Lengkapi Profil' : 'Edit Profil' ?>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- SUBMIT RUN MODAL -->
<div class="modal-overlay" id="submitRunModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('submitRunModal')">&times;</button>
    <h3 class="modal-title"><i class="fa fa-running" style="color:var(--primary);margin-right:8px;"></i>Submit Bukti Lari</h3>

    <form method="POST" action="<?= SITE_URL ?>/api/submit-run.php" enctype="multipart/form-data" id="submitForm">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <?php if ($event): ?>
      <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">Tanggal Lari</label>
        <input type="date" name="run_date" class="form-control-custom" required 
               max="<?= date('Y-m-d', strtotime('+1 day')) ?>"
               <?php if ($event): ?>
               min="<?= $event['start_date'] ?>"
               <?php endif; ?>>
      </div>
      <div class="form-group">
        <label class="form-label">Jarak (km)</label>
        <input type="number" name="distance_km" class="form-control-custom" 
               placeholder="cth: 5.5" step="0.01" min="0.1" max="30" required>
        <div class="form-error">Maksimal <?= MAX_KM_PER_SUBMISSION ?> km per submission</div>
      </div>
      <div class="form-group">
        <label class="form-label">Bukti Lari <span style="color:var(--gray-light);font-weight:400;">(JPG/PNG/WEBP, maks 10MB)</span></label>
        <div class="file-upload-area">
          <input type="file" name="evidence" accept="image/jpeg,image/png,image/webp" required style="display:none;">
          <div class="file-upload-icon"><i class="fa fa-cloud-upload-alt"></i></div>
          <div class="file-upload-text">
            <strong>Klik untuk upload</strong> atau drag & drop<br>
            <span style="font-size:12px;">Screenshot Strava, Nike Run Club, Garmin, dll.</span>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Catatan <span style="color:var(--gray-light);font-weight:400;">(opsional)</span></label>
        <textarea name="notes" class="form-control-custom" rows="2" placeholder="Rute, kondisi, atau info tambahan..."></textarea>
      </div>
      <button type="submit" class="btn-primary-custom" style="width:100%;justify-content:center;padding:14px;">
        <i class="fa fa-paper-plane"></i> Submit Lari
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
<script>
// Show sidebar toggle on mobile
document.addEventListener('DOMContentLoaded', function() {
  const toggle = document.getElementById('sidebarToggle');
  if (toggle) toggle.style.display = 'block';
  
  // Handle form submission
  document.getElementById('submitForm')?.addEventListener('submit', function(e) {
    const btn = this.querySelector('button[type="submit"]');
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Mengirim...';
    btn.disabled = true;
  });
});
</script>
</body>
</html>
