<?php
$pageTitle = 'Manajemen Admin';
$activeNav = 'admin-admins';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db = getDB();
$adminUser = getCurrentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) die('Invalid CSRF');
    
    if ($_POST['action'] === 'create') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Nama, email, dan password wajib diisi.';
        } elseif (strlen($password) < 8) {
            $error = 'Password minimal 8 karakter.';
        } else {
            $check = $db->prepare("SELECT id FROM users WHERE email=?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $error = 'Email sudah digunakan.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->prepare("INSERT INTO users (name,email,phone,password_hash,role) VALUES (?,?,?,?,'admin')")
                   ->execute([$name, $email, $phone, $hash]);
                logAudit($adminUser['id'], 'create_admin', 'users', $db->lastInsertId());
                $success = 'Admin baru berhasil dibuat.';
            }
        }
    } elseif ($_POST['action'] === 'toggle') {
        $userId = (int)$_POST['user_id'];
        if ($userId !== $adminUser['id']) {
            $db->prepare("UPDATE users SET is_active = 1 - is_active WHERE id=?")->execute([$userId]);
            logAudit($adminUser['id'], 'toggle_admin', 'users', $userId);
            $success = 'Status admin diperbarui.';
        } else {
            $error = 'Tidak bisa menonaktifkan akun sendiri.';
        }
    }
}

$admins = $db->query("SELECT * FROM users WHERE role='admin' ORDER BY created_at DESC")->fetchAll();
$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manajemen Admin — PeakMiles</title>
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
        <div class="topbar-title">Manajemen Admin</div>
      </div>
      <button onclick="openModal('addAdminModal')" class="btn-primary-custom btn-sm-custom">
        <i class="fa fa-user-plus"></i> Tambah Admin
      </button>
    </div>
    <div class="page-content">
      <?php if ($error): ?><div class="alert-custom alert-danger"><?= sanitize($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert-custom alert-success"><?= sanitize($success) ?></div><?php endif; ?>

      <div class="table-container">
        <div class="table-header">
          <div class="table-title">Daftar Admin (<?= count($admins) ?>)</div>
        </div>
        <table class="table-custom">
          <thead>
            <tr>
              <th>Admin</th>
              <th>Email</th>
              <th>Telepon</th>
              <th>Status</th>
              <th>Dibuat</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($admins as $a): ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dark));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:#fff;">
                    <?= strtoupper(substr($a['name'], 0, 1)) ?>
                  </div>
                  <div style="font-weight:600;color:#fff;"><?= sanitize($a['name']) ?>
                    <?php if ($a['id'] === $adminUser['id']): ?>
                    <span style="font-size:10px;color:var(--primary);margin-left:6px;">(Kamu)</span>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td style="color:var(--gray-light);"><?= sanitize($a['email']) ?></td>
              <td style="color:var(--gray-light);"><?= sanitize($a['phone'] ?? '-') ?></td>
              <td>
                <span class="status-badge <?= $a['is_active'] ? 'badge-approved' : 'badge-rejected' ?>">
                  <?= $a['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                </span>
              </td>
              <td style="color:var(--gray-light);font-size:13px;"><?= date('d M Y', strtotime($a['created_at'])) ?></td>
              <td>
                <?php if ($a['id'] !== $adminUser['id']): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="user_id" value="<?= $a['id'] ?>">
                  <button type="submit" class="<?= $a['is_active'] ? 'btn-danger-custom' : 'btn-success-custom' ?>" style="font-size:12px;padding:6px 14px;">
                    <?= $a['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                  </button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Add Admin Modal -->
<div class="modal-overlay" id="addAdminModal">
  <div class="modal-box" style="max-width:440px;">
    <button class="modal-close" onclick="closeModal('addAdminModal')">&times;</button>
    <h3 class="modal-title"><i class="fa fa-user-plus" style="color:var(--primary);margin-right:8px;"></i>Tambah Admin Baru</h3>
    <form method="POST" action="">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="create">
      <div class="form-group">
        <label class="form-label">Nama Lengkap *</label>
        <input type="text" name="name" class="form-control-custom" required>
      </div>
      <div class="form-group">
        <label class="form-label">Email *</label>
        <input type="email" name="email" class="form-control-custom" required>
      </div>
      <div class="form-group">
        <label class="form-label">Nomor HP</label>
        <input type="tel" name="phone" class="form-control-custom" placeholder="08xx...">
      </div>
      <div class="form-group">
        <label class="form-label">Password * (min. 8 karakter)</label>
        <input type="password" name="password" class="form-control-custom" required minlength="8">
      </div>
      <button type="submit" class="btn-primary-custom" style="width:100%;justify-content:center;padding:14px;">
        <i class="fa fa-save"></i> Buat Admin
      </button>
    </form>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
