<?php
$pageTitle = 'E-Certificate';
$activeNav = 'certificate';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$user = getCurrentUser();
$event = getActiveEvent();
$db = getDB();

$registration = $event ? getUserRegistration($user['id'], $event['id']) : null;
$isFinisher   = $registration && $registration['status'] === 'finisher';
$totalKm      = $registration ? getUserTotalKm($user['id'], $event['id']) : 0;

// Ambil sertifikat milik user yang sedang login
$certificate = null;
if ($event) {
    $stmt = $db->prepare("SELECT * FROM certificates WHERE user_id = ? AND event_id = ?");
    $stmt->execute([$user['id'], $event['id']]);
    $certificate = $stmt->fetch() ?: null;
}

// Auto-generate: jika sudah finisher tapi sertifikat belum ada → buat sekarang
if ($isFinisher && !$certificate && $event) {
    $filename = generateCertificate($user['id'], $event['id']);
    if ($filename) {
        $stmt = $db->prepare("SELECT * FROM certificates WHERE user_id = ? AND event_id = ?");
        $stmt->execute([$user['id'], $event['id']]);
        $certificate = $stmt->fetch() ?: null;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>E-Certificate — PeakMiles</title>
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
        <div class="topbar-title">E-Certificate</div>
      </div>
    </div>
    <div class="page-content">
      <?php if ($isFinisher && $certificate): ?>
      <!-- Finisher! -->
      <div class="finisher-banner mb-4">
        <div class="finisher-title"><i class="fa fa-trophy" style="margin-right:8px;"></i>Selamat! Kamu adalah FINISHER!</div>
        <p style="color:var(--gray-light);margin-bottom:24px;">
          E-Certificate kamu telah siap diunduh. Bagikan pencapaianmu di Budapest Vrtl Hlf Mrthn 2026!
        </p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
          <a href="<?= CERT_URL . sanitize($certificate['file_path']) ?>" target="_blank" class="btn-primary-custom">
            <i class="fa fa-eye"></i> Lihat Certificate
          </a>
          <a href="<?= SITE_URL ?>/api/download-cert.php" class="btn-outline-custom">
            <i class="fa fa-download"></i> Download PDF
          </a>
        </div>
      </div>

      <!-- Certificate Preview -->
      <div class="form-card" style="padding:0;overflow:hidden;">
        <div style="background:linear-gradient(135deg,#0f0f0f,#1a1a2e);min-height:300px;display:flex;align-items:center;justify-content:center;">
          <iframe src="<?= CERT_URL . sanitize($certificate['file_path']) ?>" 
                  style="width:100%;height:500px;border:none;" title="Certificate Preview"></iframe>
        </div>
      </div>

      <div class="row g-4 mt-2">
        <div class="col-md-4">
          <div class="stat-card text-center">
            <div style="font-size:28px;margin-bottom:8px;color:var(--primary);"><i class="fa fa-person-running"></i></div>
            <div class="stat-card-label">Kategori</div>
            <div class="stat-card-value" style="font-size:24px;"><?= $registration['category'] ?></div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="stat-card text-center">
            <div style="font-size:28px;margin-bottom:8px;color:var(--primary);"><i class="fa fa-route"></i></div>
            <div class="stat-card-label">Total Jarak</div>
            <div class="stat-card-value" style="font-size:24px;"><?= number_format($totalKm, 2) ?></div>
            <div class="stat-card-sub">kilometer</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="stat-card text-center">
            <div style="font-size:28px;margin-bottom:8px;color:var(--primary);"><i class="fa fa-calendar-check"></i></div>
            <div class="stat-card-label">Selesai Pada</div>
            <div class="stat-card-value" style="font-size:18px;"><?= $registration['finished_at'] ? date('d M Y', strtotime($registration['finished_at'])) : '-' ?></div>
          </div>
        </div>
      </div>

      <?php elseif ($isFinisher && !$certificate): ?>
      <!-- Fallback: finisher tapi gagal generate (jarang terjadi) -->
      <div class="form-card text-center" style="padding:60px;">
        <div style="font-size:44px;margin-bottom:20px;color:var(--warning);"><i class="fa fa-triangle-exclamation"></i></div>
        <h4 style="color:#fff;margin-bottom:8px;">Gagal Membuat Sertifikat</h4>
        <p style="color:var(--gray-light);margin-bottom:20px;">
          Terjadi kesalahan saat membuat sertifikat. Coba refresh halaman atau hubungi admin.
        </p>
        <button onclick="location.reload()" class="btn-primary-custom">
          <i class="fa fa-rotate-right"></i> Coba Lagi
        </button>
      </div>

      <?php else: ?>
      <!-- Not yet finisher -->
      <div class="form-card">
        <div class="row align-items-center g-4">
          <div class="col-lg-7">
            <h4 style="color:#fff;font-weight:700;margin-bottom:12px;">E-Certificate Belum Tersedia</h4>
            <p style="color:var(--gray-light);line-height:1.7;margin-bottom:20px;">
              E-Certificate akan tersedia secara otomatis setelah kamu mencapai target km dan mendapatkan status <strong style="color:var(--primary);">FINISHER</strong>.
            </p>
            <?php if ($registration): ?>
            <div style="background:var(--dark-4);border-radius:var(--radius);padding:16px;margin-bottom:20px;">
              <div style="font-size:13px;color:var(--gray-light);margin-bottom:8px;">Progres kamu saat ini:</div>
              <div class="progress-bar-bg mb-2">
                <?php $pct = progressPercent($totalKm, (float)$registration['target_km']); ?>
                <div class="progress-bar-fill" data-width="<?= $pct ?>" style="width:0%;"></div>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:13px;">
                <span style="color:var(--primary);font-weight:600;"><?= number_format($totalKm, 2) ?> km</span>
                <span style="color:var(--gray-light);">Target: <?= formatKm((float)$registration['target_km']) ?></span>
              </div>
            </div>
            <?php endif; ?>
            <a href="<?= SITE_URL ?>/dashboard.php" class="btn-primary-custom">
              <i class="fa fa-running"></i> Lanjut Berlari
            </a>
          </div>
          <div class="col-lg-5 text-center">
            <div style="font-size:80px;opacity:0.15;color:var(--primary);"><i class="fa fa-file-certificate"></i></div>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
