<?php
$pageTitle = 'Pengaturan Pembayaran';
$activeNav = 'admin-payment';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db = getDB();

// Handle POST - toggle enable/disable
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) die('Invalid CSRF');

    $enabledCodes = $_POST['enabled'] ?? [];

    // Disable semua dulu
    $db->exec("UPDATE payment_method_settings SET is_enabled = 0");

    // Enable yang dicentang
    if (!empty($enabledCodes)) {
        $placeholders = implode(',', array_fill(0, count($enabledCodes), '?'));
        $db->prepare("UPDATE payment_method_settings SET is_enabled = 1 WHERE payment_code IN ($placeholders)")
           ->execute($enabledCodes);
    }

    flash('success', 'Pengaturan metode pembayaran berhasil disimpan.');
    redirect(SITE_URL . '/admin/payment-settings');
}

// Ambil semua metode, grouped by category
$methods = $db->query("SELECT * FROM payment_method_settings ORDER BY sort_order ASC")->fetchAll();

$categoryLabels = [
    'qris'            => ['label' => 'QRIS', 'icon' => 'fa-qrcode', 'color' => '#22c55e'],
    'virtual_account' => ['label' => 'Virtual Account', 'icon' => 'fa-university', 'color' => '#3b82f6'],
    'ewallet'         => ['label' => 'E-Wallet', 'icon' => 'fa-wallet', 'color' => '#a855f7'],
    'retail'          => ['label' => 'Retail', 'icon' => 'fa-store', 'color' => '#f59e0b'],
    'lainnya'         => ['label' => 'Lainnya', 'icon' => 'fa-ellipsis-h', 'color' => '#6b7280'],
];

$grouped = [];
foreach ($methods as $m) {
    $grouped[$m['category']][] = $m;
}

$csrf = generateCSRFToken();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pengaturan Pembayaran — PeakMiles Admin</title>
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
        <div class="topbar-title">Pengaturan Pembayaran</div>
      </div>
      <button form="paymentForm" type="submit" class="btn-primary-custom btn-sm-custom">
        <i class="fa fa-save"></i> Simpan
      </button>
    </div>

    <div class="page-content">
      <?php if ($flash): ?>
      <div class="alert-custom alert-<?= $flash['type'] ?>" style="margin-bottom:20px;">
        <i class="fa fa-check-circle"></i> <?= sanitize($flash['msg'] ?? $flash['message'] ?? '') ?>
      </div>
      <?php endif; ?>

      <div style="color:var(--gray-light);font-size:14px;margin-bottom:24px;">
        <i class="fa fa-info-circle" style="color:var(--primary);margin-right:6px;"></i>
        Pilih metode pembayaran yang akan ditampilkan kepada user. Hanya metode yang dicentang dan tersedia di akun Duitku Anda yang akan muncul.
      </div>

      <form method="POST" id="paymentForm">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

        <?php foreach ($categoryLabels as $catKey => $catInfo): ?>
          <?php if (empty($grouped[$catKey])) continue; ?>
          <div class="form-card mb-4">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
              <div style="width:36px;height:36px;background:<?= $catInfo['color'] ?>22;border-radius:10px;display:flex;align-items:center;justify-content:center;border:1px solid <?= $catInfo['color'] ?>44;">
                <i class="fa <?= $catInfo['icon'] ?>" style="color:<?= $catInfo['color'] ?>;font-size:16px;"></i>
              </div>
              <div>
                <div style="font-weight:700;color:#fff;font-size:15px;"><?= $catInfo['label'] ?></div>
                <div style="font-size:12px;color:var(--gray-light);">
                  <?= count(array_filter($grouped[$catKey], fn($m) => $m['is_enabled'])) ?> dari <?= count($grouped[$catKey]) ?> aktif
                </div>
              </div>
              <button type="button" onclick="toggleAll('<?= $catKey ?>')" style="margin-left:auto;background:none;border:1px solid var(--border);color:var(--gray-light);padding:4px 12px;border-radius:6px;font-size:12px;cursor:pointer;">
                Pilih Semua
              </button>
            </div>

            <div class="row g-3">
              <?php foreach ($grouped[$catKey] as $method): ?>
              <div class="col-md-6 col-lg-4">
                <label class="pay-setting-card <?= $method['is_enabled'] ? 'active' : '' ?>" 
                       data-category="<?= $catKey ?>"
                       style="display:flex;align-items:center;gap:12px;padding:14px 16px;border:1px solid <?= $method['is_enabled'] ? 'rgba(249,115,22,0.4)' : 'var(--border)' ?>;border-radius:10px;cursor:pointer;background:<?= $method['is_enabled'] ? 'rgba(249,115,22,0.06)' : 'var(--surface)' ?>;transition:all 0.2s;user-select:none;">
                  <div style="position:relative;flex-shrink:0;">
                    <input type="checkbox" name="enabled[]" value="<?= $method['payment_code'] ?>"
                           <?= $method['is_enabled'] ? 'checked' : '' ?>
                           onchange="updateCard(this)"
                           style="width:18px;height:18px;accent-color:var(--primary);cursor:pointer;">
                  </div>
                  <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;color:#fff;font-size:13px;"><?= sanitize($method['payment_name']) ?></div>
                    <div style="font-size:11px;color:var(--gray-light);font-family:monospace;"><?= $method['payment_code'] ?></div>
                  </div>
                  <?php if ($method['is_enabled']): ?>
                  <span style="font-size:10px;background:rgba(34,197,94,0.15);color:#22c55e;padding:2px 8px;border-radius:4px;font-weight:600;">ON</span>
                  <?php else: ?>
                  <span style="font-size:10px;background:rgba(107,114,128,0.15);color:var(--gray-light);padding:2px 8px;border-radius:4px;font-weight:600;">OFF</span>
                  <?php endif; ?>
                </label>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <div style="display:flex;justify-content:flex-end;margin-top:8px;">
          <button type="submit" class="btn-primary-custom" style="padding:14px 32px;">
            <i class="fa fa-save"></i> Simpan Pengaturan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
function updateCard(checkbox) {
  const card = checkbox.closest('.pay-setting-card');
  const badge = card.querySelector('span:last-child');
  if (checkbox.checked) {
    card.style.borderColor = 'rgba(249,115,22,0.4)';
    card.style.background  = 'rgba(249,115,22,0.06)';
    badge.style.background = 'rgba(34,197,94,0.15)';
    badge.style.color      = '#22c55e';
    badge.textContent      = 'ON';
  } else {
    card.style.borderColor = 'var(--border)';
    card.style.background  = 'var(--surface)';
    badge.style.background = 'rgba(107,114,128,0.15)';
    badge.style.color      = 'var(--gray-light)';
    badge.textContent      = 'OFF';
  }
}

function toggleAll(category) {
  const checkboxes = document.querySelectorAll(`[data-category="${category}"] input[type="checkbox"]`);
  const allChecked = Array.from(checkboxes).every(c => c.checked);
  checkboxes.forEach(function(cb) {
    cb.checked = !allChecked;
    updateCard(cb);
  });
}
</script>
</body>
</html>
