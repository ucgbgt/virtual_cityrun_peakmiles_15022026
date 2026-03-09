<?php
$pageTitle = 'Admin Dashboard';
$activeNav = 'admin-dashboard';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db = getDB();
$event = getActiveEvent();

// Stats
$totalUsers = $db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$totalRegistrations = $db->query("SELECT COUNT(*) FROM registrations")->fetchColumn();
$totalFinishers = $db->query("SELECT COUNT(*) FROM registrations WHERE status='finisher'")->fetchColumn();
$pendingSubs = $db->query("SELECT COUNT(*) FROM run_submissions WHERE status='pending'")->fetchColumn();
$approvedSubs = $db->query("SELECT COUNT(*) FROM run_submissions WHERE status='approved'")->fetchColumn();
$rejectedSubs = $db->query("SELECT COUNT(*) FROM run_submissions WHERE status='rejected'")->fetchColumn();
$totalKmApproved = $db->query("SELECT COALESCE(SUM(distance_km),0) FROM run_submissions WHERE status='approved'")->fetchColumn();

// Recent pending submissions
$recentPending = $db->query("SELECT rs.*, u.name as user_name, u.email FROM run_submissions rs 
    JOIN users u ON rs.user_id = u.id WHERE rs.status='pending' ORDER BY rs.created_at DESC LIMIT 8")->fetchAll();

// Recent finishers
$recentFinishers = $db->query("SELECT r.*, u.name as user_name FROM registrations r 
    JOIN users u ON r.user_id=u.id WHERE r.status='finisher' ORDER BY r.finished_at DESC LIMIT 5")->fetchAll();

$events = $db->query("SELECT * FROM events ORDER BY is_active DESC, id DESC")->fetchAll();
$csrf   = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — PeakMiles</title>
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
        <div>
          <div class="topbar-title">Admin Dashboard</div>
          <div style="font-size:12px;color:var(--gray-light);"><?= $event ? sanitize($event['name']) : 'Tidak ada event aktif' ?></div>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button onclick="openModal('addParticipantModal')" class="btn-outline-custom btn-sm-custom">
          <i class="fa fa-user-plus"></i> Tambah Peserta
        </button>
        <a href="<?= SITE_URL ?>/admin/submissions.php" class="btn-primary-custom btn-sm-custom">
          <i class="fa fa-clock"></i> <?= $pendingSubs ?> Pending
        </a>
      </div>
    </div>
    <div class="page-content">
      <?php $flash = getFlash(); if ($flash): ?>
      <div class="alert-custom alert-<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'warning' ? 'warning' : 'danger') ?>"
           style="margin-bottom:20px;line-height:1.7;">
        <i class="fa fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <?= $flash['message'] ?>
      </div>
      <?php endif; ?>

      <!-- Stats Grid -->
      <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(59,130,246,0.15);color:var(--info);"><i class="fa fa-users"></i></div>
            <div class="stat-card-label">Total Peserta</div>
            <div class="stat-card-value counter" data-target="<?= $totalRegistrations ?>"><?= $totalRegistrations ?></div>
            <div class="stat-card-sub"><?= $totalUsers ?> akun terdaftar</div>
          </div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(249,115,22,0.15);color:var(--primary);"><i class="fa fa-trophy"></i></div>
            <div class="stat-card-label">Finisher</div>
            <div class="stat-card-value counter" data-target="<?= $totalFinishers ?>"><?= $totalFinishers ?></div>
            <div class="stat-card-sub"><?= $totalRegistrations > 0 ? round(($totalFinishers/$totalRegistrations)*100) : 0 ?>% dari peserta</div>
          </div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(245,158,11,0.15);color:var(--warning);"><i class="fa fa-clock"></i></div>
            <div class="stat-card-label">Submission Pending</div>
            <div class="stat-card-value counter" data-target="<?= $pendingSubs ?>"><?= $pendingSubs ?></div>
            <div class="stat-card-sub">perlu review</div>
          </div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(34,197,94,0.15);color:var(--success);"><i class="fa fa-running"></i></div>
            <div class="stat-card-label">Total KM Approved</div>
            <div class="stat-card-value"><?= number_format((float)$totalKmApproved, 1) ?></div>
            <div class="stat-card-sub">kilometer total</div>
          </div>
        </div>
      </div>

      <!-- Submission Stats Bar -->
      <div class="form-card mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div style="font-weight:700;color:#fff;"><i class="fa fa-chart-bar" style="color:var(--primary);margin-right:8px;"></i>Status Submission</div>
          <a href="<?= SITE_URL ?>/admin/submissions.php" style="font-size:13px;color:var(--primary);text-decoration:none;">Lihat Semua →</a>
        </div>
        <?php $totalSubs = $approvedSubs + $rejectedSubs + $pendingSubs; ?>
        <div class="row g-3 text-center">
          <div class="col-4">
            <div style="font-size:28px;font-weight:800;color:var(--warning);"><?= $pendingSubs ?></div>
            <div style="font-size:11px;color:var(--gray-light);text-transform:uppercase;">Pending</div>
          </div>
          <div class="col-4">
            <div style="font-size:28px;font-weight:800;color:var(--success);"><?= $approvedSubs ?></div>
            <div style="font-size:11px;color:var(--gray-light);text-transform:uppercase;">Approved</div>
          </div>
          <div class="col-4">
            <div style="font-size:28px;font-weight:800;color:var(--danger);"><?= $rejectedSubs ?></div>
            <div style="font-size:11px;color:var(--gray-light);text-transform:uppercase;">Rejected</div>
          </div>
        </div>
        <?php if ($totalSubs > 0): ?>
        <div class="progress-bar-bg mt-3" style="height:8px;">
          <div style="height:100%;background:linear-gradient(90deg,var(--success) <?= round($approvedSubs/$totalSubs*100) ?>%,var(--warning) <?= round($approvedSubs/$totalSubs*100) ?>% <?= round(($approvedSubs+$pendingSubs)/$totalSubs*100) ?>%,var(--danger) <?= round(($approvedSubs+$pendingSubs)/$totalSubs*100) ?>% 100%);border-radius:100px;"></div>
        </div>
        <?php endif; ?>
      </div>

      <div class="row g-4">
        <!-- Pending Submissions -->
        <div class="col-lg-8">
          <div class="table-container">
            <div class="table-header">
              <div class="table-title"><i class="fa fa-clock" style="color:var(--warning);margin-right:6px;"></i>Submission Pending</div>
              <a href="<?= SITE_URL ?>/admin/submissions.php" style="font-size:13px;color:var(--primary);text-decoration:none;">Semua →</a>
            </div>
            <?php if (empty($recentPending)): ?>
            <div style="padding:40px;text-align:center;color:var(--gray-light);">
              <i class="fa fa-check-circle" style="font-size:36px;margin-bottom:12px;display:block;color:var(--success);opacity:0.5;"></i>
              Semua submission sudah diproses!
            </div>
            <?php else: ?>
            <table class="table-custom">
              <thead>
                <tr>
                  <th>Peserta</th>
                  <th>Jarak</th>
                  <th>Bukti</th>
                  <th>Tgl Lari</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentPending as $sub): ?>
                <tr>
                  <td>
                    <div style="font-weight:600;color:#fff;"><?= sanitize($sub['user_name']) ?></div>
                    <div style="font-size:11px;color:var(--gray-light);"><?= sanitize($sub['email']) ?></div>
                  </td>
                  <td style="font-weight:700;color:var(--primary);"><?= number_format($sub['distance_km'], 2) ?> km</td>
                  <td>
                    <img src="<?= UPLOAD_URL . sanitize($sub['evidence_path']) ?>" alt="Bukti" class="evidence-thumb"
                         onclick="openLightbox('<?= UPLOAD_URL . sanitize($sub['evidence_path']) ?>')">
                  </td>
                  <td style="font-size:13px;color:var(--gray-light);"><?= date('d M Y', strtotime($sub['run_date'])) ?></td>
                  <td>
                    <div class="d-flex gap-2">
                      <button onclick="approveSubmission(<?= $sub['id'] ?>)" class="btn-success-custom" style="padding:6px 12px;font-size:12px;">
                        <i class="fa fa-check"></i>
                      </button>
                      <button onclick="showRejectModal(<?= $sub['id'] ?>)" class="btn-danger-custom" style="padding:6px 12px;font-size:12px;">
                        <i class="fa fa-times"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>
        </div>

        <!-- Recent Finishers -->
        <div class="col-lg-4">
          <div class="table-container">
            <div class="table-header">
              <div class="table-title"><i class="fa fa-trophy" style="color:var(--primary);margin-right:6px;"></i>Finisher Terbaru</div>
            </div>
            <?php if (empty($recentFinishers)): ?>
            <div style="padding:30px;text-align:center;color:var(--gray-light);font-size:13px;">Belum ada finisher</div>
            <?php else: ?>
            <div style="padding:8px;">
              <?php foreach ($recentFinishers as $f): ?>
              <div style="display:flex;align-items:center;gap:12px;padding:12px;border-radius:10px;border-bottom:1px solid var(--border);">
                <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dark));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:#fff;flex-shrink:0;">
                  <?= strtoupper(substr($f['user_name'], 0, 1)) ?>
                </div>
                <div style="flex:1;min-width:0;">
                  <div style="font-weight:600;color:#fff;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= sanitize($f['user_name']) ?></div>
                  <div style="font-size:11px;color:var(--gray-light);"><?= $f['category'] ?> · <?= number_format($f['total_km_approved'], 2) ?> km</div>
                </div>
                <span class="status-badge badge-finisher" style="font-size:10px;flex-shrink:0;"><i class="fa fa-trophy"></i></span>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════
     MODAL: Tambah Peserta Manual
     ══════════════════════════════════════════ -->
<div class="modal-overlay" id="addParticipantModal">
  <div class="modal-box" style="max-width:520px;">
    <button class="modal-close" onclick="closeModal('addParticipantModal')">&times;</button>
    <h3 class="modal-title">
      <i class="fa fa-user-plus" style="color:var(--primary);margin-right:10px;"></i>Tambah Peserta Baru
    </h3>

    <div style="background:rgba(249,115,22,0.08);border:1px solid rgba(249,115,22,0.2);border-radius:var(--radius);
         padding:12px 16px;margin-bottom:20px;font-size:13px;color:var(--gray-light);">
      <i class="fa fa-info-circle" style="color:var(--primary);margin-right:6px;"></i>
      Akun baru akan dibuat dengan password default <strong style="color:#fff;">User@123</strong>.
      Peserta diminta ganti password setelah login pertama.
    </div>

    <form method="POST" action="<?= SITE_URL ?>/api/add-participant.php" id="addParticipantForm">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

      <div class="form-group">
        <label class="form-label">Nama Lengkap *</label>
        <input type="text" name="name" class="form-control-custom" required
               placeholder="Nama lengkap peserta" autocomplete="off">
      </div>

      <div class="form-group">
        <label class="form-label">Email *</label>
        <input type="email" name="email" class="form-control-custom" required
               placeholder="email@peserta.com" autocomplete="off">
        <div style="font-size:12px;color:var(--gray-light);margin-top:4px;">
          Jika email sudah terdaftar, peserta akan didaftarkan ke event dengan akun yang ada.
        </div>
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Event</label>
            <select name="event_id" class="form-control-custom">
              <?php foreach ($events as $ev): ?>
              <option value="<?= $ev['id'] ?>" <?= ($event && $ev['id'] === $event['id']) ? 'selected' : '' ?>>
                <?= sanitize($ev['name']) ?><?= $ev['is_active'] ? ' (Aktif)' : '' ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Kategori</label>
            <div style="display:flex;gap:10px;margin-top:2px;">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 16px;
                     border:1px solid var(--border);border-radius:var(--radius);flex:1;
                     transition:border-color .2s;" id="lbl5k">
                <input type="radio" name="category" value="5K" checked
                       style="accent-color:var(--primary);" onchange="highlightCat()">
                <span style="color:#fff;font-weight:600;">5K</span>
              </label>
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 16px;
                     border:1px solid var(--border);border-radius:var(--radius);flex:1;
                     transition:border-color .2s;" id="lbl10k">
                <input type="radio" name="category" value="10K"
                       style="accent-color:var(--primary);" onchange="highlightCat()">
                <span style="color:#fff;font-weight:600;">10K</span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex gap-3 mt-4">
        <button type="submit" id="addParticipantBtn"
                class="btn-primary-custom"
                style="flex:1;justify-content:center;padding:13px;">
          <i class="fa fa-user-plus"></i> Tambah Peserta
        </button>
        <button type="button" onclick="closeModal('addParticipantModal')"
                class="btn-outline-custom" style="padding:13px 20px;">
          Batal
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal-box" style="max-width:400px;">
    <button class="modal-close" onclick="closeModal('rejectModal')">&times;</button>
    <h3 class="modal-title" style="color:var(--danger);"><i class="fa fa-times-circle" style="margin-right:8px;"></i>Tolak Submission</h3>
    <form method="POST" action="<?= SITE_URL ?>/api/review-submission.php" id="rejectForm">
      <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
      <input type="hidden" name="submission_id" id="rejectSubId">
      <input type="hidden" name="action" value="reject">
      <div class="form-group">
        <label class="form-label">Alasan Penolakan *</label>
        <textarea name="admin_note" class="form-control-custom" rows="3" placeholder="Tuliskan alasan penolakan yang jelas untuk peserta..." required></textarea>
      </div>
      <div class="d-flex gap-3">
        <button type="submit" class="btn-danger-custom" style="padding:12px 24px;font-size:14px;border-radius:var(--radius);">
          <i class="fa fa-times"></i> Tolak Submission
        </button>
        <button type="button" onclick="closeModal('rejectModal')" class="btn-outline-custom">Batal</button>
      </div>
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
function approveSubmission(id) {
  if (!confirm('Setujui submission ini?')) return;
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = '<?= SITE_URL ?>/api/review-submission.php';
  form.innerHTML = `<input name="csrf_token" value="<?= generateCSRFToken() ?>">
    <input name="submission_id" value="${id}"><input name="action" value="approve">`;
  document.body.appendChild(form);
  form.submit();
}
function showRejectModal(id) {
  document.getElementById('rejectSubId').value = id;
  openModal('rejectModal');
}

// Highlight kategori radio
function highlightCat() {
  const is5k = document.querySelector('[name="category"][value="5K"]').checked;
  document.getElementById('lbl5k').style.borderColor  = is5k  ? 'var(--primary)' : 'var(--border)';
  document.getElementById('lbl10k').style.borderColor = !is5k ? 'var(--primary)' : 'var(--border)';
}
highlightCat(); // init

// Loading state saat submit tambah peserta
document.getElementById('addParticipantForm')?.addEventListener('submit', function() {
  const btn = document.getElementById('addParticipantBtn');
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memproses...';
  btn.disabled  = true;
});
</script>
</body>
</html>
