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
    $isActive = ($registration['payment_status'] ?? 'unpaid') === 'paid' || !empty($registration['admin_activated']);
} else {
    $isActive = false;
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
<link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@300;400;500;600;700;800;900&family=Saira:wght@700;800;900&display=swap" rel="stylesheet">
</head>
<body>
<div class="dashboard-layout">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

  <div class="main-content">
    <div class="topbar">
      <div class="d-flex align-items-center gap-3">
        <button id="sidebarToggle" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;" class="d-lg-none">
          <i class="fa fa-bars"></i>
        </button>
        <div>
          <div class="topbar-title">Dashboard</div>
          <div style="font-size:12px;color:var(--gray-light);">Selamat datang, <?= sanitize($user['name']) ?>!</div>
        </div>
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
      <?php if ($registration && !$isActive): ?>
      <!-- BANNER BELUM BAYAR -->
      <div style="background:linear-gradient(135deg,rgba(239,68,68,0.12),rgba(239,68,68,0.06));border:1px solid rgba(239,68,68,0.35);border-radius:14px;padding:20px 24px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div style="display:flex;align-items:center;gap:16px;">
          <div style="width:44px;height:44px;background:rgba(239,68,68,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fa fa-lock" style="color:#ef4444;font-size:18px;"></i>
          </div>
          <div>
            <div style="font-weight:700;color:#ef4444;font-size:15px;margin-bottom:2px;">
              <span class="status-badge" style="background:rgba(239,68,68,0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.3);margin-right:8px;">INACTIVE</span>
              Akun Belum Aktif
            </div>
            <div style="color:var(--gray-light);font-size:13px;">Belum melakukan pembayaran event. Selesaikan pembayaran untuk mulai submit lari.</div>
          </div>
        </div>
        <button onclick="initPayment()" id="dashPayBtn" class="btn-primary-custom btn-sm-custom" style="flex-shrink:0;">
          <i class="fa fa-credit-card"></i> Bayar Sekarang
        </button>
      </div>
      <?php endif; ?>

      <?php if (!$registration && $event): ?>
      <!-- BANNER BELUM TERDAFTAR -->
      <div style="background:linear-gradient(135deg,rgba(249,115,22,0.12),rgba(249,115,22,0.06));border:1px solid rgba(249,115,22,0.35);border-radius:14px;padding:20px 24px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div style="display:flex;align-items:center;gap:16px;">
          <div style="width:44px;height:44px;background:rgba(249,115,22,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fa fa-running" style="color:var(--primary);font-size:18px;"></i>
          </div>
          <div>
            <div style="font-weight:700;color:#fff;font-size:15px;margin-bottom:2px;">Belum Terdaftar Event</div>
            <div style="color:var(--gray-light);font-size:13px;">Daftarkan dirimu ke <strong style="color:var(--primary);"><?= sanitize($event['name']) ?></strong> untuk mulai berlari!</div>
          </div>
        </div>
        <button onclick="openModal('joinEventModal')" class="btn-primary-custom btn-sm-custom" style="flex-shrink:0;">
          <i class="fa fa-plus-circle"></i> Daftar Event Sekarang
        </button>
      </div>
      <?php endif; ?>

      <?php if ($registration && isset($isFinisher) && $isFinisher): ?>
      <!-- FINISHER BANNER -->
      <div class="finisher-banner">
        <div class="finisher-title"><i class="fa fa-trophy" style="margin-right:8px;"></i>Selamat! Kamu adalah FINISHER!</div>
        <p style="color:var(--gray-light);margin-bottom:20px;">Kamu telah berhasil menyelesaikan target <?= $registration['category'] ?> dengan total <?= formatKm($totalKm) ?>. Luar biasa!</p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
          <?php if ($certificate): ?>
          <a href="<?= SITE_URL ?>/certificate" class="btn-primary-custom">
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
              <span class="status-badge <?= $isFinisher ? 'badge-finisher' : ($isActive ? 'badge-active' : '') ?>"
                    style="<?= (!$isFinisher && !$isActive) ? 'background:rgba(239,68,68,0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.3);' : '' ?>">
                <i class="fa fa-<?= $isFinisher ? 'trophy' : ($isActive ? 'circle' : 'lock') ?>" style="font-size:8px;"></i>
                <?= $isFinisher ? 'FINISHER' : ($isActive ? 'ACTIVE' : 'INACTIVE') ?>
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
              <a href="<?= SITE_URL ?>/submissions" style="font-size:13px;color:var(--primary);text-decoration:none;">
                Lihat Semua <i class="fa fa-arrow-right"></i>
              </a>
            </div>
            <?php if (empty($recentSubs)): ?>
            <div style="padding:40px;text-align:center;color:var(--gray-light);">
              <i class="fa fa-running" style="font-size:36px;margin-bottom:12px;display:block;opacity:0.3;"></i>
              Belum ada submission. Yuk mulai lari!
              <?php if ($registration): ?>
              <div class="mt-3">
                <button onclick="openModal('<?= $isActive ? 'submitRunModal' : 'inactiveNoticeModal' ?>')" class="btn-primary-custom btn-sm-custom">
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
            <a href="<?= SITE_URL ?>/shipping" class="btn-outline-custom btn-sm-custom" style="margin-top:16px;width:100%;justify-content:center;">
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
            <a href="<?= SITE_URL ?>/profile" class="btn-outline-custom btn-sm-custom" style="width:100%;justify-content:center;">
              <i class="fa fa-edit"></i> <?= $profilePct < 100 ? 'Lengkapi Profil' : 'Edit Profil' ?>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- JOIN EVENT MODAL -->
<?php if ($event && !$registration): ?>
<div class="modal-overlay" id="joinEventModal">
  <div class="modal-box" style="max-width:480px;">
    <button class="modal-close" onclick="closeModal('joinEventModal')">&times;</button>
    <div style="text-align:center;margin-bottom:24px;">
      <div style="width:64px;height:64px;background:rgba(249,115,22,0.12);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
        <i class="fa fa-running" style="font-size:28px;color:var(--primary);"></i>
      </div>
      <h3 class="modal-title" style="margin-bottom:6px;">Daftar ke Event</h3>
      <p style="color:var(--gray-light);font-size:13px;margin:0;">
        <strong style="color:#fff;"><?= sanitize($event['name']) ?></strong><br>
        <?= date('d M Y', strtotime($event['start_date'])) ?> — <?= date('d M Y', strtotime($event['end_date'])) ?>
      </p>
    </div>

    <div style="margin-bottom:20px;">
      <div style="font-size:13px;color:var(--gray-light);font-weight:600;margin-bottom:12px;">Pilih Kategori:</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <label id="card-10K" onclick="selectJoinCategory('10K')" style="border:2px solid var(--border);border-radius:12px;padding:16px;cursor:pointer;text-align:center;transition:all 0.2s;">
          <input type="radio" name="joinCat" value="10K" style="display:none;">
          <div style="font-size:22px;font-weight:700;color:var(--primary);margin-bottom:4px;">10K</div>
          <div style="font-size:12px;color:var(--gray-light);margin-bottom:8px;">10 Kilometer</div>
          <div style="font-size:13px;font-weight:600;color:#fff;">Rp <?= number_format($event['fee_10k'] ?? 179000, 0, ',', '.') ?></div>
        </label>
        <label id="card-21K" onclick="selectJoinCategory('21K')" style="border:2px solid var(--border);border-radius:12px;padding:16px;cursor:pointer;text-align:center;transition:all 0.2s;">
          <input type="radio" name="joinCat" value="21K" style="display:none;">
          <div style="font-size:22px;font-weight:700;color:var(--primary);margin-bottom:4px;">21K</div>
          <div style="font-size:12px;color:var(--gray-light);margin-bottom:8px;">21 Kilometer</div>
          <div style="font-size:13px;font-weight:600;color:#fff;">Rp <?= number_format($event['fee_21k'] ?? 199000, 0, ',', '.') ?></div>
        </label>
      </div>
    </div>

    <div id="joinError" class="alert-custom alert-danger" style="display:none;margin-bottom:12px;"></div>
    <div id="joinLoading" style="display:none;text-align:center;padding:12px;">
      <i class="fa fa-spinner fa-spin" style="color:var(--primary);font-size:22px;"></i>
      <div style="color:var(--gray-light);font-size:13px;margin-top:6px;">Mendaftarkan...</div>
    </div>

    <button id="btnJoin" onclick="doJoinEvent()" class="btn-primary-custom" style="width:100%;justify-content:center;padding:14px;" disabled>
      <i class="fa fa-check-circle"></i> Daftar Sekarang
    </button>
    <p style="text-align:center;color:var(--gray-light);font-size:12px;margin-top:12px;">
      Setelah daftar, selesaikan pembayaran untuk mengaktifkan akun.
    </p>
  </div>
</div>

<!-- JOIN SUCCESS + PAYMENT MODAL -->
<div class="modal-overlay" id="joinSuccessModal">
  <div class="modal-box" style="max-width:480px;text-align:center;">
    <div style="width:72px;height:72px;background:rgba(34,197,94,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;border:2px solid rgba(34,197,94,0.3);">
      <i class="fa fa-check" style="font-size:32px;color:var(--success);"></i>
    </div>
    <h3 style="font-size:22px;font-weight:800;color:#fff;margin-bottom:8px;">Pendaftaran Berhasil! 🎉</h3>
    <p style="color:var(--gray-light);font-size:14px;line-height:1.7;margin-bottom:4px;">
      Kamu telah terdaftar di kategori <strong id="joinSuccessCat" style="color:var(--primary);"></strong>.
    </p>
    <div style="background:rgba(249,115,22,0.08);border:1px solid rgba(249,115,22,0.2);border-radius:10px;padding:14px;margin:16px 0;">
      <div style="font-size:12px;color:var(--gray-light);margin-bottom:4px;">Biaya Pendaftaran</div>
      <div id="joinSuccessFee" style="font-size:24px;font-weight:800;color:var(--primary);"></div>
    </div>
    <p style="color:var(--gray-light);font-size:13px;margin-bottom:20px;">
      Akun kamu <strong style="color:var(--warning);">belum aktif</strong> sebelum melakukan pembayaran. Bayar sekarang atau nanti melalui dashboard.
    </p>

    <div id="joinPayLoading" style="padding:20px 0;">
      <i class="fa fa-spinner fa-spin" style="font-size:28px;color:var(--primary);"></i>
      <div style="color:var(--gray-light);margin-top:8px;font-size:14px;">Memuat metode pembayaran...</div>
    </div>
    <div id="joinPayError" class="alert-custom alert-danger" style="display:none;"></div>
    <div id="joinPayMethods" style="display:none;text-align:left;margin-bottom:16px;max-height:240px;overflow-y:auto;"></div>

    <div id="joinPayButtons" style="display:none;flex-direction:column;gap:12px;">
      <button onclick="joinBayarSekarang()" id="btnJoinBayar" class="btn-primary-custom" style="width:100%;justify-content:center;padding:14px;" disabled>
        <i class="fa fa-credit-card"></i> Lanjut Bayar — <span id="joinBayarFeeLabel"></span>
      </button>
      <button onclick="window.location.reload()" class="btn-outline-custom" style="width:100%;justify-content:center;padding:14px;">
        <i class="fa fa-clock"></i> Bayar Nanti
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

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
      <button onclick="closeModal('inactiveNoticeModal'); initPayment();" class="btn-primary-custom" style="padding:10px 20px;">
        <i class="fa fa-credit-card"></i> Bayar Sekarang
      </button>
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
      <div class="row g-3">
        <div class="col-7">
          <div class="form-group">
            <label class="form-label">Jarak (km)</label>
            <input type="number" name="distance_km" class="form-control-custom"
                   placeholder="cth: 5.5" step="0.01" min="0.1" max="30" required>
            <div class="form-error">Maksimal <?= MAX_KM_PER_SUBMISSION ?> km per submission</div>
          </div>
        </div>
        <div class="col-5">
          <div class="form-group">
            <label class="form-label">Waktu</label>
            <input type="time" name="run_time" class="form-control-custom" required>
          </div>
        </div>
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
      <div id="submitRunError" class="alert-custom alert-danger" style="display:none;margin-bottom:12px;"></div>
      <button type="submit" class="btn-primary-custom" style="width:100%;justify-content:center;padding:14px;">
        <i class="fa fa-paper-plane"></i> Submit Lari
      </button>
    </form>
  </div>
</div>

<!-- SUBMIT SUCCESS MODAL -->
<div class="modal-overlay" id="submitSuccessModal">
  <div class="modal-box" style="max-width:420px;text-align:center;">
    <div style="width:80px;height:80px;background:rgba(34,197,94,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;border:2px solid rgba(34,197,94,0.35);">
      <i class="fa fa-check" style="font-size:36px;color:var(--success);"></i>
    </div>
    <h3 style="font-size:22px;font-weight:800;color:#fff;margin-bottom:8px;">Submission Berhasil!</h3>
    <p style="color:var(--gray-light);font-size:14px;line-height:1.7;margin-bottom:16px;">
      Bukti lari <strong id="successDistance" style="color:var(--primary);"></strong> pada <strong id="successDate" style="color:#fff;"></strong><span id="successTimeWrap"> selama <strong id="successTime" style="color:var(--primary);"></strong></span> berhasil dikirim.
    </p>
    <div style="background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);border-radius:12px;padding:16px;margin-bottom:20px;">
      <i class="fa fa-clock" style="color:var(--success);margin-right:8px;"></i>
      <span style="color:var(--gray-light);font-size:13px;">Tim kami akan mereview submission dalam <strong style="color:#fff;">1–2 hari kerja</strong>.</span>
    </div>
    <div style="display:flex;gap:12px;justify-content:center;">
      <button onclick="closeModal('submitSuccessModal');location.reload();" class="btn-primary-custom" style="padding:12px 24px;">
        <i class="fa fa-refresh"></i> Lihat Update
      </button>
      <button onclick="closeModal('submitSuccessModal')" class="btn-outline-custom" style="padding:12px 24px;">
        Tutup
      </button>
    </div>
  </div>
</div>

<div id="lightbox" class="lightbox">
  <button class="lightbox-close" onclick="closeLightbox()"><i class="fa fa-times"></i></button>
  <img id="lightbox-img" src="" alt="">
</div>

<?php if ($registration && !$isActive): ?>
<!-- MODAL BAYAR DARI DASHBOARD -->
<div class="modal-overlay" id="paymentModal">
  <div class="modal-box" style="max-width:420px;text-align:center;">
    <button class="modal-close" onclick="closeModal('paymentModal')">&times;</button>
    <div style="width:60px;height:60px;background:rgba(249,115,22,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
      <i class="fa fa-credit-card" style="font-size:24px;color:var(--primary);"></i>
    </div>
    <h3 style="font-family:'Syne',sans-serif;font-weight:800;color:#fff;margin-bottom:8px;">Selesaikan Pembayaran</h3>
    <p style="color:var(--gray-light);font-size:14px;margin-bottom:16px;">
      Kategori <strong style="color:var(--primary);"><?= $registration['category'] ?></strong> —
      Rp <?= number_format($registration['category'] === '21K' ? ($event['fee_21k'] ?? 199000) : ($event['fee_10k'] ?? 179000), 0, ',', '.') ?>
    </p>
    <div id="dashPayLoading" style="padding:16px 0;">
      <i class="fa fa-spinner fa-spin" style="font-size:28px;color:var(--primary);"></i>
      <div style="color:var(--gray-light);margin-top:8px;font-size:14px;">Memuat metode pembayaran...</div>
    </div>
    <div id="dashPayError" style="display:none;" class="alert-custom alert-danger"></div>
    <div id="dashPayMethods" style="display:none;text-align:left;max-height:220px;overflow-y:auto;margin-bottom:12px;"></div>
    <div id="dashPayButtons" style="display:none;">
      <button onclick="doPayment()" id="btnDashBayar" class="btn-primary-custom" style="width:100%;justify-content:center;padding:14px;" disabled>
        <i class="fa fa-credit-card"></i> Lanjut Pembayaran
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
let dashSelectedMethod = '';

function renderGroupedMethods(groups, containerEl, radioName, selectFn) {
  const catIconMap = {
    qris:            { icon:'fa-qrcode',    color:'#22c55e' },
    virtual_account: { icon:'fa-university', color:'#3b82f6' },
    ewallet:         { icon:'fa-wallet',    color:'#a855f7' },
    retail:          { icon:'fa-store',     color:'#f59e0b' },
    lainnya:         { icon:'fa-ellipsis-h',color:'#6b7280' },
  };
  let html = '';
  groups.forEach(function(group) {
    const meta = catIconMap[group.category] || catIconMap['lainnya'];
    html += `<div style="margin-bottom:14px;">
      <div style="display:flex;align-items:center;gap:7px;margin-bottom:7px;padding-bottom:5px;border-bottom:1px solid rgba(255,255,255,0.06);">
        <i class="fa ${meta.icon}" style="color:${meta.color};font-size:12px;width:14px;text-align:center;"></i>
        <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:${meta.color};">${group.categoryLabel}</span>
      </div>`;
    group.methods.forEach(function(m) {
      html += `<label style="display:flex;align-items:center;gap:10px;padding:9px 12px;border:1px solid var(--border);border-radius:8px;margin-bottom:6px;cursor:pointer;transition:all 0.2s;" class="dash-pay-item">
        <input type="radio" name="${radioName}" value="${m.paymentMethod}" style="accent-color:var(--primary);" onchange="${selectFn}('${m.paymentMethod}',this.closest('.dash-pay-item'))">
        <img src="${m.paymentImage}" style="width:36px;height:24px;object-fit:contain;" onerror="this.style.display='none'">
        <div style="flex:1;"><div style="color:#fff;font-size:12px;font-weight:600;">${m.paymentName}</div></div>
      </label>`;
    });
    html += '</div>';
  });
  containerEl.innerHTML = html;
}

function initPayment() {
  openModal('paymentModal');
  // Load metode pembayaran
  fetch('<?= SITE_URL ?>/api/payment-methods.php')
    .then(r => r.json())
    .then(data => {
      document.getElementById('dashPayLoading').style.display = 'none';
      if (data.success && data.groups && data.groups.length > 0) {
        const container = document.getElementById('dashPayMethods');
        renderGroupedMethods(data.groups, container, 'dashMethod', 'selectDashMethod');
        container.style.display = 'block';
        document.getElementById('dashPayButtons').style.display = 'block';
      } else {
        const errEl = document.getElementById('dashPayError');
        errEl.style.display = 'block';
        errEl.innerHTML = '<i class="fa fa-exclamation-circle"></i> ' + (data.message || 'Tidak ada metode tersedia.');
      }
    })
    .catch(() => {
      document.getElementById('dashPayLoading').style.display = 'none';
      const errEl = document.getElementById('dashPayError');
      errEl.style.display = 'block';
      errEl.innerHTML = '<i class="fa fa-exclamation-circle"></i> Gagal memuat metode pembayaran.';
    });
}

function selectDashMethod(code, el) {
  dashSelectedMethod = code;
  document.querySelectorAll('.dash-pay-item').forEach(function(item) {
    item.style.borderColor = 'var(--border)';
    item.style.background = '';
  });
  el.style.borderColor = 'var(--primary)';
  el.style.background = 'rgba(249,115,22,0.08)';
  document.getElementById('btnDashBayar').disabled = false;
}

function doPayment() {
  if (!dashSelectedMethod) return;
  document.getElementById('dashPayMethods').style.display = 'none';
  document.getElementById('dashPayButtons').style.display = 'none';
  document.getElementById('dashPayLoading').style.display = 'block';
  document.getElementById('dashPayLoading').innerHTML = '<i class="fa fa-spinner fa-spin" style="font-size:28px;color:var(--primary);"></i><div style="color:var(--gray-light);margin-top:8px;font-size:14px;">Menyiapkan pembayaran...</div>';
  document.getElementById('dashPayError').style.display = 'none';

  fetch('<?= SITE_URL ?>/api/payment-create.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ paymentMethod: dashSelectedMethod })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success && data.paymentUrl) {
      window.location.href = data.paymentUrl;
    } else {
      document.getElementById('dashPayLoading').style.display = 'none';
      document.getElementById('dashPayMethods').style.display = 'block';
      document.getElementById('dashPayButtons').style.display = 'block';
      const errEl = document.getElementById('dashPayError');
      errEl.style.display = 'block';
      errEl.innerHTML = '<i class="fa fa-exclamation-circle"></i> ' + (data.message || 'Gagal membuat transaksi.');
    }
  })
  .catch(() => {
    document.getElementById('dashPayLoading').style.display = 'none';
    document.getElementById('dashPayMethods').style.display = 'block';
    document.getElementById('dashPayButtons').style.display = 'block';
    const errEl = document.getElementById('dashPayError');
    errEl.style.display = 'block';
    errEl.innerHTML = '<i class="fa fa-exclamation-circle"></i> Terjadi kesalahan jaringan.';
  });
}

// Join Event
var joinSelectedCategory  = null;
var joinSelectedPayMethod = '';

function selectJoinCategory(cat) {
  joinSelectedCategory = cat;
  ['10K', '21K'].forEach(function(c) {
    var card = document.getElementById('card-' + c);
    if (!card) return;
    if (c === cat) {
      card.style.borderColor = 'var(--primary)';
      card.style.background  = 'rgba(249,115,22,0.08)';
    } else {
      card.style.borderColor = 'var(--border)';
      card.style.background  = '';
    }
  });
  var btn = document.getElementById('btnJoin');
  if (btn) btn.disabled = false;
}

function doJoinEvent() {
  if (!joinSelectedCategory) return;
  document.getElementById('btnJoin').disabled = true;
  document.getElementById('joinLoading').style.display = 'block';
  document.getElementById('joinError').style.display   = 'none';

  fetch('<?= SITE_URL ?>/api/join-event.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ category: joinSelectedCategory, csrf_token: '<?= htmlspecialchars($csrf) ?>' })
  })
  .then(r => r.json())
  .then(function(data) {
    document.getElementById('joinLoading').style.display = 'none';
    if (data.success) {
      closeModal('joinEventModal');
      // Tampilkan modal sukses + pilih pembayaran
      var feeFormatted = 'Rp ' + data.fee.toLocaleString('id-ID');
      document.getElementById('joinSuccessCat').textContent    = data.category;
      document.getElementById('joinSuccessFee').textContent    = feeFormatted;
      document.getElementById('joinBayarFeeLabel').textContent = feeFormatted;
      openModal('joinSuccessModal');
      loadJoinPayMethods();
    } else {
      var err = document.getElementById('joinError');
      err.style.display = 'block';
      err.textContent = data.message || 'Gagal mendaftar.';
      document.getElementById('btnJoin').disabled = false;
    }
  })
  .catch(function() {
    document.getElementById('joinLoading').style.display = 'none';
    var err = document.getElementById('joinError');
    err.style.display = 'block';
    err.textContent = 'Terjadi kesalahan jaringan.';
    document.getElementById('btnJoin').disabled = false;
  });
}

function loadJoinPayMethods() {
  document.getElementById('joinPayLoading').style.display = 'block';
  document.getElementById('joinPayMethods').style.display = 'none';
  document.getElementById('joinPayButtons').style.display = 'none';
  document.getElementById('joinPayError').style.display   = 'none';
  joinSelectedPayMethod = '';

  fetch('<?= SITE_URL ?>/api/payment-methods.php')
    .then(r => r.json())
    .then(function(data) {
      document.getElementById('joinPayLoading').style.display = 'none';
      if (data.success && data.groups && data.groups.length > 0) {
        var container = document.getElementById('joinPayMethods');
        renderGroupedMethods(data.groups, container, 'joinMethod', 'selectJoinMethod');
        container.style.display = 'block';
        document.getElementById('joinPayButtons').style.display = 'flex';
      } else {
        var errEl = document.getElementById('joinPayError');
        errEl.style.display = 'block';
        errEl.innerHTML = '<i class="fa fa-exclamation-circle"></i> ' + (data.message || 'Tidak ada metode tersedia.');
        document.getElementById('joinPayButtons').style.display = 'flex';
      }
    })
    .catch(function() {
      document.getElementById('joinPayLoading').style.display = 'none';
      var errEl = document.getElementById('joinPayError');
      errEl.style.display = 'block';
      errEl.innerHTML = '<i class="fa fa-exclamation-circle"></i> Gagal memuat metode pembayaran.';
      document.getElementById('joinPayButtons').style.display = 'flex';
    });
}

function selectJoinMethod(code, el) {
  joinSelectedPayMethod = code;
  document.querySelectorAll('#joinPayMethods .pay-method-item').forEach(function(item) {
    item.style.borderColor = 'var(--border)';
    item.style.background  = '';
  });
  el.style.borderColor = 'var(--primary)';
  el.style.background  = 'rgba(249,115,22,0.08)';
  document.getElementById('btnJoinBayar').disabled = false;
}

function joinBayarSekarang() {
  if (!joinSelectedPayMethod) return;
  document.getElementById('joinPayMethods').style.display = 'none';
  document.getElementById('joinPayButtons').style.display = 'none';
  document.getElementById('joinPayError').style.display   = 'none';
  document.getElementById('joinPayLoading').style.display = 'block';
  document.getElementById('joinPayLoading').innerHTML =
    '<i class="fa fa-spinner fa-spin" style="font-size:28px;color:var(--primary);"></i>' +
    '<div style="color:var(--gray-light);margin-top:8px;font-size:14px;">Menyiapkan pembayaran...</div>';

  fetch('<?= SITE_URL ?>/api/payment-create.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ paymentMethod: joinSelectedPayMethod })
  })
  .then(r => r.json())
  .then(function(data) {
    if (data.success && data.paymentUrl) {
      window.location.href = data.paymentUrl;
    } else {
      document.getElementById('joinPayLoading').style.display = 'none';
      document.getElementById('joinPayMethods').style.display = 'block';
      document.getElementById('joinPayButtons').style.display = 'flex';
      var errEl = document.getElementById('joinPayError');
      errEl.style.display = 'block';
      errEl.innerHTML = '<i class="fa fa-exclamation-circle"></i> ' + (data.message || 'Gagal membuat transaksi.');
    }
  })
  .catch(function() {
    document.getElementById('joinPayLoading').style.display = 'none';
    document.getElementById('joinPayMethods').style.display = 'block';
    document.getElementById('joinPayButtons').style.display = 'flex';
    var errEl = document.getElementById('joinPayError');
    errEl.style.display = 'block';
    errEl.innerHTML = '<i class="fa fa-exclamation-circle"></i> Terjadi kesalahan jaringan.';
  });
}

document.addEventListener('DOMContentLoaded', function() {
  // Handle form submission via AJAX
  document.getElementById('submitForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const form = this;
    const btn = form.querySelector('button[type="submit"]');
    const errEl = document.getElementById('submitRunError');
    const origHTML = btn.innerHTML;

    errEl.style.display = 'none';
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Mengirim...';
    btn.disabled = true;

    fetch(form.action, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: new FormData(form)
    })
    .then(r => r.json())
    .then(function(data) {
      if (data.success) {
        closeModal('submitRunModal');
        form.reset();
        // Reset file upload label jika ada
        const fileText = form.querySelector('.file-upload-text strong');
        if (fileText) fileText.textContent = 'Klik untuk upload';
        // Isi detail success modal
        document.getElementById('successDistance').textContent = data.distance + ' km';
        document.getElementById('successDate').textContent = data.run_date;
        var timeWrap = document.getElementById('successTimeWrap');
        if (data.run_time) {
          document.getElementById('successTime').textContent = data.run_time;
          timeWrap.style.display = 'inline';
        } else {
          timeWrap.style.display = 'none';
        }
        openModal('submitSuccessModal');
      } else {
        errEl.innerHTML = '<i class="fa fa-exclamation-circle"></i> ' + data.message;
        errEl.style.display = 'block';
        btn.innerHTML = origHTML;
        btn.disabled = false;
      }
    })
    .catch(function() {
      errEl.innerHTML = '<i class="fa fa-exclamation-circle"></i> Terjadi kesalahan jaringan. Coba lagi.';
      errEl.style.display = 'block';
      btn.innerHTML = origHTML;
      btn.disabled = false;
    });
  });

  // Auto-open join event modal jika belum terdaftar
  <?php if ($event && !$registration): ?>
  openModal('joinEventModal');
  <?php endif; ?>
});
</script>
</body>
</html>
