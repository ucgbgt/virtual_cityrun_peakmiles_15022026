<?php
$pageTitle = 'Event Settings';
$activeNav = 'admin-event';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db = getDB();
$adminUser = getCurrentUser();
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) die('Invalid CSRF');
    $eventId = (int)($_POST['event_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $target10k = (float)($_POST['target_10k'] ?? 10);
    $target21k = (float)($_POST['target_21k'] ?? 21);
    $fee10k    = (int)($_POST['fee_10k'] ?? 179000);
    $fee21k    = (int)($_POST['fee_21k'] ?? 199000);
    $regUrl = trim($_POST['registration_url'] ?? 'https://nusatix.com');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));

    if ($eventId) {
        $db->prepare("UPDATE events SET name=?,slug=?,description=?,start_date=?,end_date=?,target_10k=?,target_21k=?,fee_10k=?,fee_21k=?,registration_url=?,is_active=? WHERE id=?")
           ->execute([$name,$slug,$desc,$startDate,$endDate,$target10k,$target21k,$fee10k,$fee21k,$regUrl,$isActive,$eventId]);
        $flash = ['type'=>'success','msg'=>'Event berhasil diperbarui!'];
    } else {
        $db->prepare("INSERT INTO events (name,slug,description,start_date,end_date,target_10k,target_21k,fee_10k,fee_21k,registration_url,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$name,$slug,$desc,$startDate,$endDate,$target10k,$target21k,$fee10k,$fee21k,$regUrl,$isActive]);
        $flash = ['type'=>'success','msg'=>'Event baru berhasil dibuat!'];
    }
    logAudit($adminUser['id'], 'update_event', 'events', $eventId ?: $db->lastInsertId());
}

$events = $db->query("SELECT * FROM events ORDER BY id DESC")->fetchAll();
$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Event Settings — PeakMiles Admin</title>
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
        <div class="topbar-title">Event Settings</div>
      </div>
      <button onclick="openModal('newEventModal')" class="btn-primary-custom btn-sm-custom">
        <i class="fa fa-plus"></i> Event Baru
      </button>
    </div>
    <div class="page-content">
      <?php if ($flash): ?>
      <div class="alert-custom alert-<?= $flash['type'] ?>"><?= sanitize($flash['msg']) ?></div>
      <?php endif; ?>

      <!-- Events List -->
      <?php foreach ($events as $ev): ?>
      <div class="form-card mb-4" style="border-color:<?= $ev['is_active'] ? 'rgba(249,115,22,0.3)' : 'var(--border)' ?>;">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px;">
              <h5 style="color:#fff;font-weight:700;margin:0;"><?= sanitize($ev['name']) ?></h5>
              <?php if ($ev['is_active']): ?>
              <span class="status-badge badge-approved">AKTIF</span>
              <?php else: ?>
              <span class="status-badge badge-rejected">NONAKTIF</span>
              <?php endif; ?>
            </div>
            <div style="font-size:13px;color:var(--gray-light);">
              <?= date('d M Y', strtotime($ev['start_date'])) ?> — <?= date('d M Y', strtotime($ev['end_date'])) ?>
              &nbsp;·&nbsp; 10K: <?= $ev['target_10k'] ?> km (Rp <?= number_format($ev['fee_10k'] ?? 179000, 0, ',', '.') ?>) &nbsp;·&nbsp; 21K: <?= $ev['target_21k'] ?> km (Rp <?= number_format($ev['fee_21k'] ?? 199000, 0, ',', '.') ?>)
            </div>
          </div>
          <button onclick="editEvent(<?= htmlspecialchars(json_encode($ev)) ?>)" class="btn-outline-custom btn-sm-custom">
            <i class="fa fa-edit"></i> Edit
          </button>
        </div>
        <?php if ($ev['description']): ?>
        <p style="color:var(--gray-light);font-size:14px;"><?= sanitize($ev['description']) ?></p>
        <?php endif; ?>
        <div style="font-size:12px;color:var(--gray-light);margin-top:8px;">
          <i class="fa fa-link" style="color:var(--primary);margin-right:6px;"></i>
          <a href="<?= sanitize($ev['registration_url']) ?>" target="_blank" style="color:var(--primary);text-decoration:none;"><?= sanitize($ev['registration_url']) ?></a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- New/Edit Event Modal -->
<div class="modal-overlay" id="newEventModal">
  <div class="modal-box" style="max-width:560px;">
    <button class="modal-close" onclick="closeModal('newEventModal')">&times;</button>
    <h3 class="modal-title" id="eventModalTitle"><i class="fa fa-calendar-plus" style="color:var(--primary);margin-right:8px;"></i>Buat Event Baru</h3>
    <form method="POST" action="" id="eventForm">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="event_id" id="formEventId" value="0">
      <div class="form-group">
        <label class="form-label">Nama Event *</label>
                <input type="text" name="name" id="formEventName" class="form-control-custom" required placeholder="PeakMiles Virtual Run 2026">
      </div>
      <div class="form-group">
        <label class="form-label">Deskripsi</label>
        <textarea name="description" id="formEventDesc" class="form-control-custom" rows="3"></textarea>
      </div>
      <div class="row g-3">
        <div class="col-6">
          <div class="form-group">
            <label class="form-label">Tanggal Mulai *</label>
            <input type="date" name="start_date" id="formEventStart" class="form-control-custom" required>
          </div>
        </div>
        <div class="col-6">
          <div class="form-group">
            <label class="form-label">Tanggal Selesai *</label>
            <input type="date" name="end_date" id="formEventEnd" class="form-control-custom" required>
          </div>
        </div>
        <div class="col-6">
          <div class="form-group">
            <label class="form-label">Target 10K (km)</label>
            <input type="number" name="target_10k" id="formTarget10k" class="form-control-custom" value="10" step="0.5" min="1">
          </div>
        </div>
        <div class="col-6">
          <div class="form-group">
            <label class="form-label">Target 21K (km)</label>
            <input type="number" name="target_21k" id="formTarget21k" class="form-control-custom" value="21" step="0.5" min="1">
          </div>
        </div>
        <div class="col-6">
          <div class="form-group">
            <label class="form-label">Biaya 10K (Rp)</label>
            <input type="number" name="fee_10k" id="formFee10k" class="form-control-custom" value="179000" min="0">
          </div>
        </div>
        <div class="col-6">
          <div class="form-group">
            <label class="form-label">Biaya 21K (Rp)</label>
            <input type="number" name="fee_21k" id="formFee21k" class="form-control-custom" value="199000" min="0">
          </div>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">URL Registrasi (Nusatix)</label>
        <input type="url" name="registration_url" id="formRegUrl" class="form-control-custom" value="https://nusatix.com">
      </div>
      <div class="form-group" style="display:flex;align-items:center;gap:12px;">
        <input type="checkbox" name="is_active" id="formIsActive" checked style="width:18px;height:18px;accent-color:var(--primary);">
        <label for="formIsActive" class="form-label" style="margin:0;cursor:pointer;">Event Aktif</label>
      </div>
      <button type="submit" class="btn-primary-custom" style="width:100%;justify-content:center;padding:14px;">
        <i class="fa fa-save"></i> Simpan Event
      </button>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
function editEvent(ev) {
  document.getElementById('eventModalTitle').innerHTML = '<i class="fa fa-edit" style="color:var(--primary);margin-right:8px;"></i>Edit Event';
  document.getElementById('formEventId').value = ev.id;
  document.getElementById('formEventName').value = ev.name;
  document.getElementById('formEventDesc').value = ev.description || '';
  document.getElementById('formEventStart').value = ev.start_date;
  document.getElementById('formEventEnd').value = ev.end_date;
  document.getElementById('formTarget10k').value = ev.target_10k;
  document.getElementById('formTarget21k').value = ev.target_21k;
  document.getElementById('formFee10k').value = ev.fee_10k;
  document.getElementById('formFee21k').value = ev.fee_21k;
  document.getElementById('formRegUrl').value = ev.registration_url;
  document.getElementById('formIsActive').checked = ev.is_active == 1;
  openModal('newEventModal');
}
</script>
</body>
</html>
