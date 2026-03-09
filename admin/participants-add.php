<?php
$pageTitle = 'Tambah Peserta';
$activeNav = 'admin-participants';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db = getDB();
$adminUser = getCurrentUser();
$event = getActiveEvent();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) die('Invalid CSRF');

    $name     = trim($_POST['name']  ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $phone    = trim($_POST['phone'] ?? '');
    $category = in_array($_POST['category'] ?? '', ['10K','21K']) ? $_POST['category'] : '10K';
    $eventId  = (int)($_POST['event_id'] ?? ($event['id'] ?? 0));

    $defaultPassword = 'User@123';

    if (empty($name) || empty($email)) {
        $error = 'Nama dan email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (!$eventId) {
        $error = 'Pilih event terlebih dahulu.';
    } else {
        $check = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        $existing = $check->fetch();

        if ($existing) {
            $userId = $existing['id'];
        } else {
            $hash = password_hash($defaultPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            // Fix: 5 kolom → 5 placeholder
            $db->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'user')")
               ->execute([$name, $email, $phone, $hash]);
            $userId = (int)$db->lastInsertId();
            $db->prepare("INSERT INTO user_profiles (user_id) VALUES (?)")->execute([$userId]);
        }

        $regCheck = $db->prepare("SELECT id FROM registrations WHERE user_id = ? AND event_id = ?");
        $regCheck->execute([$userId, $eventId]);
        if ($regCheck->fetch()) {
            $error = 'Peserta sudah terdaftar di event ini.';
        } else {
            $targetKm = $category === '21K' ? ($event['target_21k'] ?? 21) : ($event['target_10k'] ?? 10);
            $db->prepare("INSERT INTO registrations (user_id, event_id, category, target_km) VALUES (?, ?, ?, ?)")
               ->execute([$userId, $eventId, $category, $targetKm]);

            $shpCheck = $db->prepare("SELECT id FROM shipping WHERE user_id = ? AND event_id = ?");
            $shpCheck->execute([$userId, $eventId]);
            if (!$shpCheck->fetch()) {
                $db->prepare("INSERT INTO shipping (user_id, event_id) VALUES (?, ?)")->execute([$userId, $eventId]);
            }

            logAudit($adminUser['id'], 'add_participant', 'registrations', $userId);

            if ($existing) {
                $success = "Peserta berhasil didaftarkan ke event. Akun <strong>$email</strong> sudah ada sebelumnya.";
            } else {
                $success = "Akun berhasil dibuat! Login: <strong>$email</strong> &nbsp;·&nbsp; Password default: <strong>$defaultPassword</strong> (peserta diminta ganti setelah login).";
            }
        }
    }
}

$events = $db->query("SELECT * FROM events ORDER BY id DESC")->fetchAll();
$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tambah Peserta — Budapest Vrtl Hlf Mrthn 2026 Admin</title>
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
        <div class="topbar-title">Tambah Peserta Baru</div>
      </div>
      <a href="<?= SITE_URL ?>/admin/participants.php" class="btn-outline-custom btn-sm-custom">
        <i class="fa fa-arrow-left"></i> Kembali
      </a>
    </div>
    <div class="page-content">
      <?php if ($error): ?>
      <div class="alert-custom alert-danger"><i class="fa fa-exclamation-circle"></i> <?= sanitize($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
      <div class="alert-custom alert-success" style="line-height:1.7;">
        <i class="fa fa-check-circle"></i> <?= $success /* sudah di-escape saat assign */ ?>
        <?php if (!str_contains($success, 'Akun sudah ada')): ?>
        <div style="margin-top:10px;background:rgba(0,0,0,0.2);border-radius:8px;padding:10px 14px;font-size:13px;">
          <i class="fa fa-info-circle" style="margin-right:6px;"></i>
          Sampaikan kredensial ini kepada peserta dan minta mereka segera ganti password.
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div class="row justify-content-center">
        <div class="col-lg-6">
          <div class="form-card">
            <h5 style="color:#fff;font-weight:700;margin-bottom:24px;"><i class="fa fa-user-plus" style="color:var(--primary);margin-right:8px;"></i>Data Peserta</h5>
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <div class="form-group">
                <label class="form-label">Nama Lengkap *</label>
                <input type="text" name="name" class="form-control-custom" required placeholder="Nama lengkap peserta">
              </div>
              <div class="form-group">
                <label class="form-label">Email *</label>
                <input type="email" name="email" class="form-control-custom" required placeholder="email@peserta.com">
                <div style="font-size:12px;color:var(--gray-light);margin-top:4px;">Jika email sudah terdaftar, peserta akan didaftarkan ke event dengan akun yang ada.</div>
              </div>
              <div class="form-group">
                <label class="form-label">Nomor HP</label>
                <input type="tel" name="phone" class="form-control-custom" placeholder="08xx...">
              </div>
              <div class="form-group">
                <label class="form-label">Event</label>
                <select name="event_id" class="form-control-custom">
                  <?php foreach ($events as $ev): ?>
                  <option value="<?= $ev['id'] ?>" <?= ($event && $ev['id'] === $event['id']) ? 'selected' : '' ?>>
                    <?= sanitize($ev['name']) ?> <?= $ev['is_active'] ? '(Aktif)' : '' ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Kategori</label>
                <div style="display:flex;gap:12px;">
                  <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:12px 20px;border:1px solid var(--border);border-radius:var(--radius);flex:1;">
                    <input type="radio" name="category" value="10K" checked style="accent-color:var(--primary);">
                    <span style="color:#fff;font-weight:600;">10K</span>
                  </label>
                  <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:12px 20px;border:1px solid var(--border);border-radius:var(--radius);flex:1;">
                    <input type="radio" name="category" value="21K" style="accent-color:var(--primary);">
                    <span style="color:#fff;font-weight:600;">21K</span>
                  </label>
                </div>
              </div>
              <button type="submit" class="btn-primary-custom" style="width:100%;justify-content:center;padding:14px;">
                <i class="fa fa-user-plus"></i> Tambah Peserta
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
