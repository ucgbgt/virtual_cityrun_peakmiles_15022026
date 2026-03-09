<?php
$pageTitle = 'Status Pengiriman';
$activeNav = 'shipping';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$user = getCurrentUser();
$event = getActiveEvent();
$db = getDB();

$shipping = null;
if ($event) {
    $stmt = $db->prepare("SELECT * FROM shipping WHERE user_id=? AND event_id=?");
    $stmt->execute([$user['id'], $event['id']]);
    $shipping = $stmt->fetch() ?: null;
}

$statusTimeline = [
    'not_ready'  => ['label' => 'Belum Siap', 'icon' => 'fa-hourglass-start', 'desc' => 'Race pack sedang dalam proses persiapan.'],
    'preparing'  => ['label' => 'Sedang Disiapkan', 'icon' => 'fa-box-open', 'desc' => 'Race pack kamu sedang dikemas oleh tim kami.'],
    'shipped'    => ['label' => 'Dalam Pengiriman', 'icon' => 'fa-shipping-fast', 'desc' => 'Paket sedang dalam perjalanan ke alamatmu.'],
    'delivered'  => ['label' => 'Paket Diterima', 'icon' => 'fa-check-circle', 'desc' => 'Paket telah dikonfirmasi diterima. Selamat menikmati race pack-mu!'],
];
$statusOrder   = array_keys($statusTimeline);
$currentStatus = $shipping['status'] ?? 'not_ready';
$currentIdx    = array_search($currentStatus, $statusOrder);
$canConfirm    = $currentStatus === 'shipped';   // tombol konfirmasi hanya muncul saat shipped
$csrf          = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Status Pengiriman — PeakMiles</title>
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
        <div class="topbar-title">Status Pengiriman Race Pack</div>
      </div>
    </div>
    <div class="page-content">
      <?php $flash = getFlash(); if ($flash): ?>
      <div class="alert-custom alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>" style="margin-bottom:20px;">
        <i class="fa fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <?= sanitize($flash['message']) ?>
      </div>
      <?php endif; ?>

      <?php if ($currentStatus === 'delivered'): ?>
      <!-- Banner konfirmasi sudah diterima -->
      <div style="background:linear-gradient(135deg,rgba(34,197,94,0.15),rgba(34,197,94,0.08));border:1px solid rgba(34,197,94,0.3);border-radius:var(--radius-lg);padding:28px;margin-bottom:24px;display:flex;align-items:center;gap:20px;">
        <div style="font-size:38px;flex-shrink:0;color:var(--success);"><i class="fa fa-circle-check"></i></div>
        <div>
          <div style="font-family:'Syne',sans-serif;font-size:20px;font-weight:800;color:#4ade80;margin-bottom:4px;">Paket Sudah Diterima!</div>
          <div style="color:var(--gray-light);font-size:14px;">
            Kamu telah mengkonfirmasi penerimaan race pack pada
            <strong style="color:#fff;"><?= $shipping['delivered_at'] ? date('d M Y, H:i', strtotime($shipping['delivered_at'])) . ' WIB' : '-' ?></strong>.
            Selamat menikmati jersey dan medalimu! <i class="fa fa-medal" style="color:var(--primary);"></i>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="row g-4">
        <div class="col-lg-7">
          <div class="form-card">
            <h5 style="color:#fff;font-weight:700;margin-bottom:28px;"><i class="fa fa-box" style="color:var(--primary);margin-right:8px;"></i>Tracking Pengiriman</h5>

            <?php if (!$shipping): ?>
            <div style="text-align:center;padding:40px 0;color:var(--gray-light);">
              <div style="font-size:44px;margin-bottom:16px;color:var(--primary);opacity:0.4;"><i class="fa fa-box"></i></div>
              <p>Informasi pengiriman belum tersedia. Race pack akan dikirimkan setelah event selesai.</p>
            </div>
            <?php else: ?>

            <!-- Timeline -->
            <div style="position:relative;padding-left:40px;">
              <?php foreach ($statusOrder as $idx => $status):
                $info       = $statusTimeline[$status];
                $isLastStep = $idx === count($statusOrder) - 1;   // step "Terkirim"
                $isDone     = $idx <= $currentIdx;
                $isCurrent  = $idx === $currentIdx;
                // Step terakhir dianggap "pending konfirmasi" saat status=shipped
                $isPendingConfirm = $isLastStep && $canConfirm;
              ?>
              <div style="position:relative;padding-bottom:<?= $isLastStep ? '0' : '36px' ?>;">

                <!-- garis vertikal connector -->
                <?php if (!$isLastStep): ?>
                <div style="position:absolute;left:-20px;top:24px;width:2px;height:100%;
                     background:<?= $isDone ? 'var(--primary)' : 'var(--border)' ?>;"></div>
                <?php endif; ?>

                <!-- dot -->
                <?php
                if ($isPendingConfirm) {
                    $dotBg     = '#f59e0b';
                    $dotBorder = '#f59e0b';
                } elseif ($currentStatus === 'delivered' && $isLastStep) {
                    $dotBg     = 'var(--success)';
                    $dotBorder = 'var(--success)';
                } elseif ($isDone && !$isCurrent) {
                    $dotBg     = 'var(--success)';
                    $dotBorder = 'var(--success)';
                } elseif ($isCurrent) {
                    $dotBg     = 'var(--primary)';
                    $dotBorder = 'var(--primary)';
                } else {
                    $dotBg     = 'var(--dark-4)';
                    $dotBorder = 'var(--border)';
                }
                ?>
                <div style="position:absolute;left:-28px;top:2px;width:20px;height:20px;border-radius:50%;
                     background:<?= $dotBg ?>;border:2px solid <?= $dotBorder ?>;
                     display:flex;align-items:center;justify-content:center;">
                  <?php if ($isPendingConfirm): ?>
                    <i class="fa fa-question" style="color:#fff;font-size:9px;"></i>
                  <?php elseif (($isDone && !$isCurrent) || ($currentStatus === 'delivered' && $isLastStep)): ?>
                    <i class="fa fa-check" style="color:#fff;font-size:9px;"></i>
                  <?php elseif ($isCurrent && !$isLastStep): ?>
                    <div style="width:8px;height:8px;border-radius:50%;background:#fff;"></div>
                  <?php endif; ?>
                </div>

                <!-- konten step -->
                <div style="<?= (!$isDone && !$isPendingConfirm) ? 'opacity:0.35;' : '' ?>">

                  <?php if ($isPendingConfirm): ?>
                  <!-- ===== STEP TERAKHIR: BELUM DIKONFIRMASI (status=shipped) ===== -->
                  <div style="font-weight:700;color:#f59e0b;font-size:15px;margin-bottom:6px;">
                    <i class="fa fa-box-open" style="margin-right:8px;"></i>
                    Konfirmasi Penerimaan
                    <span class="status-badge" style="background:rgba(245,158,11,0.15);color:#f59e0b;margin-left:8px;font-size:10px;">MENUNGGU</span>
                  </div>
                  <div style="font-size:13px;color:var(--gray-light);margin-bottom:16px;">
                    Paket sudah sampai di tanganmu? Klik tombol di bawah untuk mengkonfirmasi penerimaan.
                  </div>
                  <button onclick="openConfirmStep1()" class="btn-primary-custom"
                          style="font-size:13px;padding:10px 22px;background:linear-gradient(135deg,#f59e0b,#d97706);">
                    <i class="fa fa-box-check"></i> Konfirmasi Paket Diterima
                  </button>

                  <?php elseif ($currentStatus === 'delivered' && $isLastStep): ?>
                  <!-- ===== STEP TERAKHIR: SUDAH DIKONFIRMASI ===== -->
                  <div style="font-weight:700;color:var(--success);font-size:15px;">
                    <i class="fa fa-check-circle" style="margin-right:8px;"></i>
                    Paket Diterima
                    <span class="status-badge badge-approved" style="margin-left:8px;font-size:10px;">DIKONFIRMASI</span>
                  </div>
                  <div style="font-size:13px;color:var(--gray-light);margin-top:4px;">
                    Dikonfirmasi pada <?= $shipping['delivered_at'] ? date('d M Y, H:i', strtotime($shipping['delivered_at'])) . ' WIB' : '-' ?>
                  </div>

                  <?php else: ?>
                  <!-- ===== STEP BIASA ===== -->
                  <div style="font-weight:<?= $isCurrent ? '700' : '600' ?>;
                              color:<?= $isCurrent ? 'var(--primary)' : ($isDone ? '#fff' : 'var(--gray-light)') ?>;
                              font-size:15px;">
                    <i class="fa <?= $info['icon'] ?>" style="margin-right:8px;"></i>
                    <?= $info['label'] ?>
                    <?php if ($isCurrent): ?>
                    <span class="status-badge badge-active" style="margin-left:8px;font-size:10px;">SAAT INI</span>
                    <?php endif; ?>
                  </div>
                  <div style="font-size:13px;color:var(--gray-light);margin-top:4px;"><?= $info['desc'] ?></div>
                  <?php endif; ?>

                </div>
              </div>
              <?php endforeach; ?>
            </div>

            <?php if ($shipping['courier'] || $shipping['tracking_number']): ?>
            <div style="background:var(--dark-4);border-radius:var(--radius);padding:20px;margin-top:28px;">
              <div style="font-size:13px;font-weight:700;color:var(--primary);margin-bottom:12px;">
                <i class="fa fa-truck" style="margin-right:6px;"></i> Info Pengiriman
              </div>
              <?php if ($shipping['courier']): ?>
              <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);">
                <span style="font-size:13px;color:var(--gray-light);">Kurir</span>
                <span style="font-size:13px;font-weight:600;color:#fff;"><?= sanitize($shipping['courier']) ?></span>
              </div>
              <?php endif; ?>
              <?php if ($shipping['tracking_number']): ?>
              <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);">
                <span style="font-size:13px;color:var(--gray-light);">Nomor Resi</span>
                <span style="font-size:13px;font-weight:600;color:var(--primary);"><?= sanitize($shipping['tracking_number']) ?></span>
              </div>
              <?php endif; ?>
              <?php if ($shipping['shipped_at']): ?>
              <div style="display:flex;justify-content:space-between;padding:8px 0;">
                <span style="font-size:13px;color:var(--gray-light);">Tanggal Kirim</span>
                <span style="font-size:13px;font-weight:600;color:#fff;"><?= date('d M Y H:i', strtotime($shipping['shipped_at'])) ?></span>
              </div>
              <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="col-lg-5">
          <!-- Alamat Pengiriman -->
          <div class="form-card mb-4">
            <h5 style="color:#fff;font-weight:700;margin-bottom:16px;"><i class="fa fa-map-marker-alt" style="color:var(--primary);margin-right:8px;"></i>Alamat Pengiriman</h5>
            <?php if (!empty($user['address_full'])): ?>
            <div style="font-size:14px;line-height:1.8;color:var(--gray-light);">
              <div style="color:#fff;font-weight:600;margin-bottom:4px;"><?= sanitize($user['name']) ?></div>
              <?= sanitize($user['address_full']) ?><br>
              <?= sanitize($user['city'] ?? '') ?>, <?= sanitize($user['province'] ?? '') ?> <?= sanitize($user['postal_code'] ?? '') ?><br>
              <?= sanitize($user['phone'] ?? '') ?>
            </div>
            <?php else: ?>
            <div style="color:var(--warning);font-size:13px;">
              <i class="fa fa-exclamation-triangle" style="margin-right:6px;"></i>
              Alamat belum diisi. Lengkapi profil untuk memastikan pengiriman tepat sasaran.
            </div>
            <a href="<?= SITE_URL ?>/profile.php" class="btn-primary-custom btn-sm-custom mt-3">
              <i class="fa fa-edit"></i> Lengkapi Profil
            </a>
            <?php endif; ?>
          </div>

          <!-- Race Pack Info -->
          <div class="form-card">
            <h5 style="color:#fff;font-weight:700;margin-bottom:16px;"><i class="fa fa-gift" style="color:var(--primary);margin-right:8px;"></i>Isi Race Pack</h5>
            <div style="display:flex;flex-direction:column;gap:12px;">
              <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(249,115,22,0.12);border:1px solid rgba(249,115,22,0.25);display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:16px;flex-shrink:0;"><i class="fa fa-shirt"></i></div>
                <div>
                  <div style="font-weight:600;color:#fff;font-size:14px;">Jersey Eksklusif</div>
                  <div style="font-size:12px;color:var(--gray-light);">Ukuran: <?= !empty($user['jersey_size']) ? $user['jersey_size'] : '<span style="color:var(--warning);">Belum diisi</span>' ?></div>
                </div>
              </div>
              <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(249,115,22,0.12);border:1px solid rgba(249,115,22,0.25);display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:16px;flex-shrink:0;"><i class="fa fa-medal"></i></div>
                <div>
                  <div style="font-weight:600;color:#fff;font-size:14px;">Medali Finisher</div>
                  <div style="font-size:12px;color:var(--gray-light);">Untuk semua finisher</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php if ($canConfirm): ?>
<!-- ===== MODAL STEP 1: Konfirmasi Awal ===== -->
<div class="modal-overlay" id="confirmStep1">
  <div class="modal-box" style="max-width:460px;">
    <button class="modal-close" onclick="closeModal('confirmStep1')">&times;</button>

    <div style="text-align:center;margin-bottom:24px;">
      <div style="font-size:44px;margin-bottom:12px;color:var(--primary);opacity:0.5;"><i class="fa fa-box-open"></i></div>
      <h3 style="color:#fff;font-weight:800;font-size:20px;margin-bottom:8px;">Konfirmasi Penerimaan Paket</h3>
      <p style="color:var(--gray-light);font-size:14px;line-height:1.7;">
        Apakah kamu benar-benar sudah menerima race pack<br>
        <strong style="color:#fff;">(jersey &amp; medali)</strong> dari PeakMiles?
      </p>
    </div>

    <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.25);border-radius:var(--radius);padding:14px 16px;margin-bottom:24px;">
      <div style="font-size:12px;color:#f59e0b;font-weight:600;margin-bottom:4px;">
        <i class="fa fa-exclamation-triangle" style="margin-right:6px;"></i>PERHATIAN
      </div>
      <div style="font-size:13px;color:var(--gray-light);">
        Setelah dikonfirmasi, status pengiriman tidak bisa dikembalikan.
        Pastikan paket sudah benar-benar ada di tanganmu.
      </div>
    </div>

    <div class="d-flex gap-3">
      <button onclick="closeModal('confirmStep1');openConfirmStep2()"
              class="btn-primary-custom" style="flex:1;justify-content:center;padding:13px;
              background:linear-gradient(135deg,#f59e0b,#d97706);">
        <i class="fa fa-check"></i> Ya, Sudah Diterima
      </button>
      <button onclick="closeModal('confirmStep1')" class="btn-outline-custom" style="flex:1;justify-content:center;">
        Belum
      </button>
    </div>
  </div>
</div>

<!-- ===== MODAL STEP 2: Verifikasi Akhir (ketik konfirmasi) ===== -->
<div class="modal-overlay" id="confirmStep2">
  <div class="modal-box" style="max-width:460px;">
    <button class="modal-close" onclick="closeModal('confirmStep2')">&times;</button>

    <div style="text-align:center;margin-bottom:24px;">
      <div style="font-size:40px;margin-bottom:12px;color:var(--success);"><i class="fa fa-circle-check"></i></div>
      <h3 style="color:#fff;font-weight:800;font-size:20px;margin-bottom:8px;">Verifikasi Terakhir</h3>
      <p style="color:var(--gray-light);font-size:14px;line-height:1.7;">
        Untuk memastikan, ketik kata <strong style="color:var(--primary);font-size:15px;">TERIMA</strong> di bawah ini
        kemudian klik tombol konfirmasi.
      </p>
    </div>

    <form method="POST" action="<?= SITE_URL ?>/api/confirm-delivery.php" id="confirmForm">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <div class="form-group" style="margin-bottom:20px;">
        <input type="text" id="confirmCode" name="confirm_code"
               class="form-control-custom"
               placeholder='Ketik: TERIMA'
               autocomplete="off"
               oninput="checkConfirmCode(this.value)"
               style="text-align:center;font-size:20px;font-weight:700;letter-spacing:4px;">
        <div id="confirmHint" style="font-size:12px;color:var(--gray-light);text-align:center;margin-top:6px;">
          Ketik tepat: <strong style="color:var(--primary);">TERIMA</strong>
        </div>
      </div>
      <button type="submit" id="confirmBtn" disabled
              class="btn-primary-custom"
              style="width:100%;justify-content:center;padding:14px;font-size:15px;
              background:linear-gradient(135deg,var(--success),#16a34a);opacity:0.5;cursor:not-allowed;">
        <i class="fa fa-box-check"></i> Konfirmasi Penerimaan
      </button>
    </form>

    <button onclick="closeModal('confirmStep2');openModal('confirmStep1')"
            style="background:none;border:none;color:var(--gray-light);font-size:13px;
            width:100%;text-align:center;margin-top:12px;cursor:pointer;padding:4px;">
      <i class="fa fa-arrow-left" style="margin-right:4px;"></i> Kembali
    </button>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
function openConfirmStep1() { openModal('confirmStep1'); }
function openConfirmStep2() { openModal('confirmStep2'); document.getElementById('confirmCode').focus(); }

function checkConfirmCode(val) {
  const btn  = document.getElementById('confirmBtn');
  const hint = document.getElementById('confirmHint');
  const ok   = val.trim().toUpperCase() === 'TERIMA';
  btn.disabled   = !ok;
  btn.style.opacity = ok ? '1' : '0.5';
  btn.style.cursor  = ok ? 'pointer' : 'not-allowed';
  hint.innerHTML = ok
    ? '<span style="color:var(--success);"><i class="fa fa-check-circle"></i> Kode benar! Klik tombol konfirmasi.</span>'
    : 'Ketik tepat: <strong style="color:var(--primary);">TERIMA</strong>';
}

// Submit dengan loading state
document.getElementById('confirmForm')?.addEventListener('submit', function() {
  const btn = document.getElementById('confirmBtn');
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memproses...';
  btn.disabled  = true;
});
</script>
</body>
</html>
