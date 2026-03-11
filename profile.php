<?php
$pageTitle = 'Profil';
$activeNav = 'profile';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$user = getCurrentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Token tidak valid.';
    } else {
        $db = getDB();
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $dob = $_POST['dob'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $address = trim($_POST['address_full'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $postal = trim($_POST['postal_code'] ?? '');
        $jersey = $_POST['jersey_size'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($name)) { $error = 'Nama tidak boleh kosong.'; }
        elseif ($newPassword && strlen($newPassword) < 8) { $error = 'Password minimal 8 karakter.'; }
        elseif ($newPassword && $newPassword !== $confirmPassword) { $error = 'Konfirmasi password tidak cocok.'; }
        else {
            $db->prepare("UPDATE users SET name=?, phone=? WHERE id=?")->execute([$name, $phone, $user['id']]);
            if ($newPassword) {
                $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $user['id']]);
            }

            $existing = $db->prepare("SELECT id FROM user_profiles WHERE user_id=?");
            $existing->execute([$user['id']]);
            if ($existing->fetch()) {
                $db->prepare("UPDATE user_profiles SET dob=?,gender=?,address_full=?,province=?,city=?,postal_code=?,jersey_size=? WHERE user_id=?")
                   ->execute([$dob ?: null, $gender ?: null, $address, $province, $city, $postal, $jersey ?: null, $user['id']]);
            } else {
                $db->prepare("INSERT INTO user_profiles (user_id,dob,gender,address_full,province,city,postal_code,jersey_size) VALUES (?,?,?,?,?,?,?,?)")
                   ->execute([$user['id'], $dob ?: null, $gender ?: null, $address, $province, $city, $postal, $jersey ?: null]);
            }
            $success = 'Profil berhasil disimpan!';
            $user = getCurrentUser();
        }
    }
}

$csrf = generateCSRFToken();

$provinces = ['Aceh','Bali','Banten','Bengkulu','DI Yogyakarta','DKI Jakarta','Gorontalo','Jambi','Jawa Barat','Jawa Tengah','Jawa Timur','Kalimantan Barat','Kalimantan Selatan','Kalimantan Tengah','Kalimantan Timur','Kalimantan Utara','Kepulauan Bangka Belitung','Kepulauan Riau','Lampung','Maluku','Maluku Utara','Nusa Tenggara Barat','Nusa Tenggara Timur','Papua','Papua Barat','Riau','Sulawesi Barat','Sulawesi Selatan','Sulawesi Tengah','Sulawesi Tenggara','Sulawesi Utara','Sumatera Barat','Sumatera Selatan','Sumatera Utara'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profil — PeakMiles</title>
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
        <div class="topbar-title">Profil Saya</div>
      </div>
    </div>
    <div class="page-content">
      <?php if ($error): ?>
      <div class="alert-custom alert-danger"><?= sanitize($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
      <div class="alert-custom alert-success"><?= sanitize($success) ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="row g-4">
          <div class="col-lg-4">
            <div class="form-card text-center">
              <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dark));display:flex;align-items:center;justify-content:center;font-weight:800;font-size:32px;color:#fff;margin:0 auto 16px;">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
              </div>
              <div style="font-size:18px;font-weight:700;color:#fff;"><?= sanitize($user['name']) ?></div>
              <div style="font-size:13px;color:var(--gray-light);"><?= sanitize($user['email']) ?></div>
              <div class="mt-2">
                <span class="status-badge" style="background:rgba(249,115,22,0.1);color:var(--primary);">
                  <?= $user['role'] === 'admin' ? 'Administrator' : 'Peserta' ?>
                </span>
              </div>
            </div>
          </div>

          <div class="col-lg-8">
            <div class="form-card">
              <h5 style="color:#fff;font-weight:700;margin-bottom:24px;"><i class="fa fa-user" style="color:var(--primary);margin-right:8px;"></i>Informasi Pribadi</h5>
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="form-label">Nama Lengkap *</label>
                    <input type="text" name="name" class="form-control-custom" value="<?= sanitize($user['name']) ?>" required>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control-custom" value="<?= sanitize($user['email']) ?>" disabled style="opacity:0.6;">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="form-label">Nomor HP</label>
                    <input type="tel" name="phone" class="form-control-custom" placeholder="08xx..." value="<?= sanitize($user['phone'] ?? '') ?>">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="form-label">Tanggal Lahir</label>
                    <input type="date" name="dob" class="form-control-custom" value="<?= $user['dob'] ?? '' ?>">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-control-custom">
                      <option value="">Pilih gender</option>
                      <option value="male" <?= ($user['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Laki-laki</option>
                      <option value="female" <?= ($user['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Perempuan</option>
                      <option value="other" <?= ($user['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Lainnya</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="form-label">Ukuran Jersey</label>
                    <select name="jersey_size" class="form-control-custom">
                      <option value="">Pilih ukuran</option>
                      <?php foreach (['XS','S','M','L','XL','XXL','XXXL'] as $size): ?>
                      <option value="<?= $size ?>" <?= ($user['jersey_size'] ?? '') === $size ? 'selected' : '' ?>><?= $size ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              </div>

              <h5 style="color:#fff;font-weight:700;margin:28px 0 20px;"><i class="fa fa-map-marker-alt" style="color:var(--primary);margin-right:8px;"></i>Alamat Pengiriman</h5>
              <div class="row g-3">
                <div class="col-12">
                  <div class="form-group">
                    <label class="form-label">Alamat Lengkap</label>
                    <textarea name="address_full" class="form-control-custom" rows="3" placeholder="Jalan, nomor rumah, RT/RW, kelurahan, kecamatan..."><?= sanitize($user['address_full'] ?? '') ?></textarea>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="form-label">Provinsi</label>
                    <select name="province" class="form-control-custom">
                      <option value="">Pilih provinsi</option>
                      <?php foreach ($provinces as $prov): ?>
                      <option value="<?= $prov ?>" <?= ($user['province'] ?? '') === $prov ? 'selected' : '' ?>><?= $prov ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Kota</label>
                    <input type="text" name="city" class="form-control-custom" placeholder="Nama kota/kabupaten" value="<?= sanitize($user['city'] ?? '') ?>">
                  </div>
                </div>
                <div class="col-md-2">
                  <div class="form-group">
                    <label class="form-label">Kode Pos</label>
                    <input type="text" name="postal_code" class="form-control-custom" placeholder="12345" maxlength="10" value="<?= sanitize($user['postal_code'] ?? '') ?>">
                  </div>
                </div>
              </div>

              <h5 style="color:#fff;font-weight:700;margin:28px 0 20px;"><i class="fa fa-lock" style="color:var(--primary);margin-right:8px;"></i>Ganti Password</h5>
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="form-label">Password Baru</label>
                    <input type="password" name="new_password" class="form-control-custom" placeholder="Kosongkan jika tidak ganti">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="form-label">Konfirmasi Password</label>
                    <input type="password" name="confirm_password" class="form-control-custom" placeholder="Ulangi password baru">
                  </div>
                </div>
              </div>

              <div class="mt-4 d-flex gap-3">
                <button type="submit" class="btn-primary-custom">
                  <i class="fa fa-save"></i> Simpan Perubahan
                </button>
                <a href="<?= SITE_URL ?>/dashboard" class="btn-outline-custom">Batal</a>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
