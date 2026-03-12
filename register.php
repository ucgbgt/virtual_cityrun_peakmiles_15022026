<?php
require_once __DIR__ . '/includes/functions.php';
startSession();

if (isLoggedIn()) {
    redirect(SITE_URL . '/dashboard');
}

$error = '';
$success = '';
$db = getDB();
$event = getActiveEvent();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Token tidak valid. Silakan refresh halaman.';
    } else {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $category = $_POST['category'] ?? '';

        if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($category)) {
            $error = 'Semua field wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid.';
        } elseif (strlen($password) < 6) {
            $error = 'Password minimal 6 karakter.';
        } elseif ($password !== $confirm) {
            $error = 'Konfirmasi password tidak cocok.';
        } elseif (!$event) {
            $error = 'Tidak ada event aktif saat ini.';
        } elseif (!in_array($category, ['10K', '21K'])) {
            $error = 'Pilihan kategori tidak valid.';
        } else {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email sudah terdaftar. Silakan login atau gunakan email lain.';
            } else {
                try {
                    $db->beginTransaction();

                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    $db->prepare("INSERT INTO users (name, email, phone, password_hash, role, is_active) VALUES (?, ?, ?, ?, 'user', 1)")
                       ->execute([$name, $email, $phone, $hash]);
                    $userId = (int)$db->lastInsertId();

                    $targetKm = $category === '10K' ? (float)$event['target_10k'] : (float)$event['target_21k'];
                    $db->prepare("INSERT INTO registrations (user_id, event_id, category, target_km) VALUES (?, ?, ?, ?)")
                       ->execute([$userId, $event['id'], $category, $targetKm]);

                    $db->commit();

                    $_SESSION['user_id']   = $userId;
                    $_SESSION['user_role'] = 'user';
                    $_SESSION['user_name'] = $name;
                    session_regenerate_id(true);

                    // Tampilkan modal sukses, jangan redirect dulu
                    $showSuccessModal = true;
                    $registeredName   = $name;
                    $registeredCategory = $category;
                    $registeredFee    = $category === '21K'
                        ? ($event['fee_21k'] ?? 199000)
                        : ($event['fee_10k'] ?? 179000);

                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Terjadi kesalahan. Silakan coba lagi.';
                }
            }
        }
    }
}

$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar — PeakMiles</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@300;400;500;600;700;800;900&family=Saira:wght@700;800;900&display=swap" rel="stylesheet">
</head>
<body>
<div class="auth-page">

  <!-- Left panel -->
  <div class="auth-left">
    <div class="auth-left-bg"></div>
    <div style="position:relative;z-index:1;text-align:center;">
      <div style="font-size:56px;margin-bottom:24px;color:var(--primary);line-height:1;">
        <i class="fa fa-person-running"></i>
      </div>
      <h2 style="font-family:'Syne',sans-serif;font-size:32px;font-weight:800;color:#fff;margin-bottom:16px;">
        Bergabung di<br><span style="color:var(--primary);">PeakMiles</span>
      </h2>
      <?php if ($event): ?>
      <div style="background:rgba(249,115,22,0.1);border:1px solid rgba(249,115,22,0.3);border-radius:12px;padding:20px;margin:24px auto;max-width:280px;">
        <div style="font-size:11px;color:var(--primary);font-weight:700;letter-spacing:1px;margin-bottom:8px;">EVENT AKTIF</div>
        <div style="font-size:16px;font-weight:700;color:#fff;margin-bottom:8px;"><?= sanitize($event['name']) ?></div>
        <div style="font-size:12px;color:var(--gray-light);">
          <?= date('d M Y', strtotime($event['start_date'])) ?> — <?= date('d M Y', strtotime($event['end_date'])) ?>
        </div>
        <div style="display:flex;gap:16px;justify-content:center;margin-top:12px;">
          <div style="text-align:center;">
            <div style="font-size:18px;font-weight:800;color:var(--primary);"><?= $event['target_10k'] ?> km</div>
            <div style="font-size:11px;color:var(--gray-light);">Kategori 10K</div>
            <div style="font-size:12px;color:var(--primary);margin-top:2px;">Rp 179.000</div>
          </div>
          <div style="width:1px;background:var(--border);"></div>
          <div style="text-align:center;">
            <div style="font-size:18px;font-weight:800;color:var(--primary);"><?= $event['target_21k'] ?> km</div>
            <div style="font-size:11px;color:var(--gray-light);">Kategori 21K</div>
            <div style="font-size:12px;color:var(--primary);margin-top:2px;">Rp 199.000</div>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <div style="margin-top:24px;display:flex;gap:20px;justify-content:center;">
        <div style="text-align:center;">
          <div style="font-size:24px;color:var(--primary);"><i class="fa fa-file-certificate"></i></div>
          <div style="font-size:11px;color:var(--gray-light);">E-Certificate</div>
        </div>
        <div style="width:1px;background:var(--border);"></div>
        <div style="text-align:center;">
          <div style="font-size:24px;color:var(--primary);"><i class="fa fa-medal"></i></div>
          <div style="font-size:11px;color:var(--gray-light);">Medali</div>
        </div>
        <div style="width:1px;background:var(--border);"></div>
        <div style="text-align:center;">
          <div style="font-size:24px;color:var(--primary);"><i class="fa fa-tshirt"></i></div>
          <div style="font-size:11px;color:var(--gray-light);">Jersey</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Right register form -->
  <div class="auth-right">
    <div class="auth-box">
      <div class="auth-logo">
        <a href="<?= SITE_URL ?>/" style="font-family:'Syne',sans-serif;font-size:24px;font-weight:800;color:#fff;text-decoration:none;">
          Peak<span style="color:var(--primary);">Miles</span>
        </a>
      </div>
      <h1 class="auth-title">Daftar Sekarang</h1>
      <p class="auth-subtitle">Isi data diri kamu untuk bergabung di event virtual run ini.</p>

      <?php if (!$event): ?>
      <div class="alert-custom alert-danger">
        <i class="fa fa-exclamation-circle"></i> Tidak ada event aktif saat ini. Pendaftaran ditutup.
      </div>
      <?php else: ?>

      <?php if ($error): ?>
      <div class="alert-custom alert-danger">
        <i class="fa fa-exclamation-circle"></i> <?= sanitize($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

        <div class="form-group">
          <label class="form-label">Nama Lengkap *</label>
          <input type="text" name="name" class="form-control-custom"
                 placeholder="Masukkan nama lengkap"
                 value="<?= sanitize($_POST['name'] ?? '') ?>" required autofocus>
        </div>

        <div class="form-group">
          <label class="form-label">Email *</label>
          <input type="email" name="email" class="form-control-custom"
                 placeholder="nama@email.com"
                 value="<?= sanitize($_POST['email'] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label">No WhatsApp *</label>
          <div style="position:relative;">
            <span style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--gray-light);font-size:14px;">+62</span>
            <input type="tel" name="phone" class="form-control-custom"
                   placeholder="81234567890"
                   value="<?= sanitize($_POST['phone'] ?? '') ?>"
                   style="padding-left:48px;" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Pilihan Kategori *</label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:4px;">
            <label style="cursor:pointer;">
              <input type="radio" name="category" value="10K" id="cat10k"
                     <?= (($_POST['category'] ?? '') === '10K') ? 'checked' : '' ?>
                     style="display:none;" class="cat-radio">
              <div class="cat-card" id="card10k">
                <div style="font-size:22px;font-weight:800;color:var(--primary);"><?= $event['target_10k'] ?> km</div>
                <div style="font-size:13px;font-weight:700;color:#fff;">Kategori 10K</div>
                <div style="font-size:11px;color:var(--gray-light);margin-top:4px;">Untuk pemula</div>
                <div style="font-size:13px;font-weight:700;color:var(--primary);margin-top:8px;">Rp <?= number_format($event['fee_10k'] ?? 179000, 0, ',', '.') ?></div>
              </div>
            </label>
            <label style="cursor:pointer;">
              <input type="radio" name="category" value="21K" id="cat21k"
                     <?= (($_POST['category'] ?? '') === '21K') ? 'checked' : '' ?>
                     style="display:none;" class="cat-radio">
              <div class="cat-card" id="card21k">
                <div style="font-size:22px;font-weight:800;color:var(--primary);"><?= $event['target_21k'] ?> km</div>
                <div style="font-size:13px;font-weight:700;color:#fff;">Kategori 21K</div>
                <div style="font-size:11px;color:var(--gray-light);margin-top:4px;">Untuk pelari aktif</div>
                <div style="font-size:13px;font-weight:700;color:var(--primary);margin-top:8px;">Rp <?= number_format($event['fee_21k'] ?? 199000, 0, ',', '.') ?></div>
              </div>
            </label>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Password *</label>
          <div style="position:relative;">
            <input type="password" name="password" id="password" class="form-control-custom"
                   placeholder="Minimal 6 karakter" required style="padding-right:44px;">
            <button type="button" onclick="togglePwd('password','pwd-icon')"
                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--gray-light);cursor:pointer;">
              <i class="fa fa-eye" id="pwd-icon"></i>
            </button>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Konfirmasi Password *</label>
          <div style="position:relative;">
            <input type="password" name="confirm_password" id="confirm_password" class="form-control-custom"
                   placeholder="Ulangi password" required style="padding-right:44px;">
            <button type="button" onclick="togglePwd('confirm_password','cpwd-icon')"
                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--gray-light);cursor:pointer;">
              <i class="fa fa-eye" id="cpwd-icon"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-primary-custom" style="width:100%;justify-content:center;padding:14px;margin-top:4px;">
          <i class="fa fa-running"></i> Daftar Sekarang
        </button>
      </form>
      <?php endif; ?>

      <div style="display:flex;align-items:center;gap:12px;margin:20px 0 16px;">
        <div style="flex:1;height:1px;background:var(--border);"></div>
        <span style="color:var(--gray-light);font-size:12px;">atau daftar dengan</span>
        <div style="flex:1;height:1px;background:var(--border);"></div>
      </div>

          <a href="<?= SITE_URL ?>/auth/google/redirect"
         style="display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:13px 20px;border:1px solid var(--border);border-radius:10px;background:var(--dark-3);color:#fff;text-decoration:none;font-size:14px;font-weight:500;transition:all 0.2s;"
         onmouseover="this.style.borderColor='rgba(255,255,255,0.2)';this.style.background='var(--dark-4)'"
         onmouseout="this.style.borderColor='var(--border)';this.style.background='var(--dark-3)'">
        <svg width="18" height="18" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
          <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
          <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
          <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
        </svg>
        Daftar dengan Google
      </a>

      <p style="text-align:center;color:var(--gray-light);font-size:14px;margin-top:20px;">
        Sudah punya akun?
        <a href="<?= SITE_URL ?>/login" style="color:var(--primary);font-weight:600;text-decoration:none;">Login di sini</a>
      </p>
      <p style="text-align:center;margin-top:12px;">
        <a href="<?= SITE_URL ?>/" style="color:var(--gray-light);font-size:13px;text-decoration:none;">
          <i class="fa fa-arrow-left" style="margin-right:4px;"></i> Kembali ke Landing Page
        </a>
      </p>
    </div>
  </div>
</div>

<style>
.cat-card {
  border: 2px solid var(--border);
  border-radius: 12px;
  padding: 16px;
  text-align: center;
  transition: all 0.2s;
  background: var(--surface);
}
.cat-card:hover {
  border-color: var(--primary);
}
.cat-radio:checked + .cat-card {
  border-color: var(--primary);
  background: rgba(249,115,22,0.1);
  box-shadow: 0 0 0 3px rgba(249,115,22,0.2);
}
</style>

<?php if (!empty($showSuccessModal)): ?>
<!-- MODAL PENDAFTARAN BERHASIL -->
<div class="modal-overlay" id="successModal" style="display:flex;">
  <div class="modal-box" style="max-width:480px;text-align:center;">
    <div style="width:72px;height:72px;background:rgba(34,197,94,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;border:2px solid rgba(34,197,94,0.3);">
      <i class="fa fa-check" style="font-size:32px;color:var(--success);"></i>
    </div>
    <h3 style="font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:#fff;margin-bottom:8px;">
      Pendaftaran Berhasil! 🎉
    </h3>
    <p style="color:var(--gray-light);font-size:14px;line-height:1.7;margin-bottom:4px;">
      Selamat <strong style="color:#fff;"><?= sanitize($registeredName) ?></strong>!<br>
      Kamu telah terdaftar di kategori <strong style="color:var(--primary);"><?= sanitize($registeredCategory) ?></strong>.
    </p>
    <div style="background:rgba(249,115,22,0.08);border:1px solid rgba(249,115,22,0.2);border-radius:10px;padding:14px;margin:16px 0;">
      <div style="font-size:12px;color:var(--gray-light);margin-bottom:4px;">Biaya Pendaftaran</div>
      <div style="font-size:24px;font-weight:800;color:var(--primary);">
        Rp <?= number_format($registeredFee, 0, ',', '.') ?>
      </div>
    </div>
    <p style="color:var(--gray-light);font-size:13px;margin-bottom:20px;">
      Akun kamu <strong style="color:var(--warning);">belum aktif</strong> sebelum melakukan pembayaran. Bayar sekarang atau nanti melalui dashboard.
    </p>

    <div id="paymentLoading" style="padding:20px 0;">
      <i class="fa fa-spinner fa-spin" style="font-size:28px;color:var(--primary);"></i>
      <div style="color:var(--gray-light);margin-top:8px;font-size:14px;">Memuat metode pembayaran...</div>
    </div>
    <div id="paymentError" style="display:none;" class="alert-custom alert-danger"></div>

    <div id="paymentMethods" style="display:none;text-align:left;margin-bottom:16px;max-height:240px;overflow-y:auto;"></div>

    <div id="paymentButtons" style="display:none;flex-direction:column;gap:12px;">
      <button onclick="bayarSekarang()" id="btnBayar" class="btn-primary-custom" style="width:100%;justify-content:center;padding:14px;" disabled>
        <i class="fa fa-credit-card"></i> Lanjut Bayar — Rp <?= number_format($registeredFee, 0, ',', '.') ?>
      </button>
      <a href="<?= SITE_URL ?>/dashboard" class="btn-outline-custom" style="width:100%;justify-content:center;padding:14px;text-decoration:none;">
        <i class="fa fa-clock"></i> Bayar Nanti
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
function togglePwd(fieldId, iconId) {
  const inp = document.getElementById(fieldId);
  const ico = document.getElementById(iconId);
  if (inp.type === 'password') { inp.type = 'text'; ico.className = 'fa fa-eye-slash'; }
  else { inp.type = 'password'; ico.className = 'fa fa-eye'; }
}

// Visual feedback for category selection
document.querySelectorAll('.cat-radio').forEach(function(radio) {
  radio.addEventListener('change', function() {
    document.querySelectorAll('.cat-card').forEach(function(card) {
      card.style.borderColor = '';
      card.style.background = '';
      card.style.boxShadow = '';
    });
  });
});

let selectedPaymentMethod = '';
let isPaymentProcessing = false;

// Render grouped payment methods
function renderGroupedMethods(groups, containerEl, radioName, selectFn) {
  const catIconMap = {
    qris:            { icon:'fa-qrcode',    color:'#22c55e', label:'QRIS' },
    virtual_account: { icon:'fa-university', color:'#3b82f6', label:'Virtual Account' },
    ewallet:         { icon:'fa-wallet',    color:'#a855f7', label:'E-Wallet' },
    retail:          { icon:'fa-store',     color:'#f59e0b', label:'Retail' },
    lainnya:         { icon:'fa-ellipsis-h',color:'#6b7280', label:'Lainnya' },
  };
  let html = '';
  groups.forEach(function(group) {
    const meta = catIconMap[group.category] || catIconMap['lainnya'];
    html += `<div style="margin-bottom:16px;">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid rgba(255,255,255,0.06);">
        <i class="fa ${meta.icon}" style="color:${meta.color};font-size:13px;width:16px;text-align:center;"></i>
        <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:${meta.color};">${group.categoryLabel}</span>
      </div>`;
    group.methods.forEach(function(m) {
      html += `<label style="display:flex;align-items:center;gap:12px;padding:10px 14px;border:1px solid var(--border);border-radius:10px;margin-bottom:7px;cursor:pointer;transition:all 0.2s;" class="pay-method-item">
        <input type="radio" name="${radioName}" value="${m.paymentMethod}" style="accent-color:var(--primary);" onchange="${selectFn}('${m.paymentMethod}',this.closest('.pay-method-item'))">
        <img src="${m.paymentImage}" style="width:40px;height:28px;object-fit:contain;" onerror="this.style.display='none'">
        <div style="flex:1;">
          <div style="color:#fff;font-size:13px;font-weight:600;">${m.paymentName}</div>
          ${parseInt(m.totalFee) > 0 ? `<div style="color:var(--gray-light);font-size:11px;">Biaya: Rp ${parseInt(m.totalFee).toLocaleString('id-ID')}</div>` : ''}
        </div>
      </label>`;
    });
    html += '</div>';
  });
  containerEl.innerHTML = html;
}

// Load payment methods saat modal tampil
fetch('<?= SITE_URL ?>/api/payment-methods.php')
  .then(r => r.json())
  .then(data => {
    document.getElementById('paymentLoading').style.display = 'none';
    if (data.success && data.groups && data.groups.length > 0) {
      const container = document.getElementById('paymentMethods');
      renderGroupedMethods(data.groups, container, 'payMethod', 'selectMethod');
      container.style.display = 'block';
      document.getElementById('paymentButtons').style.display = 'flex';
    } else {
      const errEl = document.getElementById('paymentError');
      errEl.style.display = 'block';
      errEl.innerHTML = '<i class="fa fa-exclamation-circle"></i> ' + (data.message || 'Tidak ada metode pembayaran tersedia.');
      document.getElementById('paymentButtons').style.display = 'flex';
    }
  })
  .catch(() => {
    document.getElementById('paymentLoading').style.display = 'none';
    const errEl = document.getElementById('paymentError');
    errEl.style.display = 'block';
    errEl.innerHTML = '<i class="fa fa-exclamation-circle"></i> Gagal memuat metode pembayaran.';
    document.getElementById('paymentButtons').style.display = 'flex';
  });

function selectMethod(code, el) {
  selectedPaymentMethod = code;
  document.querySelectorAll('.pay-method-item').forEach(function(item) {
    item.style.borderColor = 'var(--border)';
    item.style.background = '';
  });
  el.style.borderColor = 'var(--primary)';
  el.style.background = 'rgba(249,115,22,0.08)';
  document.getElementById('btnBayar').disabled = false;
}

function bayarSekarang() {
  if (!selectedPaymentMethod || isPaymentProcessing) return;
  isPaymentProcessing = true;
  document.getElementById('paymentMethods').style.display = 'none';
  document.getElementById('paymentButtons').style.display = 'none';
  document.getElementById('paymentLoading').style.display = 'block';
  document.getElementById('paymentLoading').innerHTML = '<i class="fa fa-spinner fa-spin" style="font-size:28px;color:var(--primary);"></i><div style="color:var(--gray-light);margin-top:8px;font-size:14px;">Menyiapkan pembayaran...</div>';
  document.getElementById('paymentError').style.display = 'none';

  fetch('<?= SITE_URL ?>/api/payment-create.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ paymentMethod: selectedPaymentMethod })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success && data.paymentUrl) {
      window.location.href = data.paymentUrl;
    } else {
      isPaymentProcessing = false;
      document.getElementById('paymentLoading').style.display = 'none';
      document.getElementById('paymentMethods').style.display = 'block';
      document.getElementById('paymentButtons').style.display = 'flex';
      const errEl = document.getElementById('paymentError');
      errEl.style.display = 'block';
      errEl.innerHTML = '<i class="fa fa-exclamation-circle"></i> ' + (data.message || 'Gagal membuat transaksi.');
    }
  })
  .catch(() => {
    isPaymentProcessing = false;
    document.getElementById('paymentLoading').style.display = 'none';
    document.getElementById('paymentMethods').style.display = 'block';
    document.getElementById('paymentButtons').style.display = 'flex';
    const errEl = document.getElementById('paymentError');
    errEl.style.display = 'block';
    errEl.innerHTML = '<i class="fa fa-exclamation-circle"></i> Terjadi kesalahan jaringan.';
  });
}
</script>
</body>
</html>
