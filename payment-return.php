<?php
require_once __DIR__ . '/includes/functions.php';
startSession();
requireLogin();

// Params dari Duitku redirect — HANYA untuk display, tidak pernah untuk update DB
$merchantOrderId = trim($_GET['merchantOrderId'] ?? '');
$resultCode      = trim($_GET['resultCode']      ?? '');
$reference       = trim($_GET['reference']       ?? '');

$db   = getDB();
$user = getCurrentUser();

// Status HANYA dibaca dari database — tidak boleh diubah dari sini
// Update payment_status hanya boleh terjadi di api/payment-callback.php (server-to-server + signature verified)
$paymentPaid = false;
$reg         = null;
if ($merchantOrderId) {
    $stmt = $db->prepare("SELECT payment_status, payment_reference FROM registrations WHERE merchant_order_id=? AND user_id=?");
    $stmt->execute([$merchantOrderId, $user['id']]);
    $reg = $stmt->fetch();
    $paymentPaid = ($reg && $reg['payment_status'] === 'paid');
    // Pakai reference dari DB jika ada (lebih terpercaya dari URL param)
    if ($reg && !empty($reg['payment_reference'])) {
        $reference = $reg['payment_reference'];
    }
}

// Jika DB belum terupdate tapi resultCode = 00:
// Callback Duitku mungkin belum tiba (bisa delay beberapa detik).
// Tampilkan status "sedang diproses" — jangan update DB dari sini.
$pendingCallback = (!$paymentPaid && $resultCode === '00');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Status Pembayaran — PeakMiles</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@300;400;500;600;700;800;900&family=Saira:wght@700;800;900&display=swap" rel="stylesheet">
</head>
<body>
<div class="auth-page" style="min-height:100vh;display:flex;align-items:center;justify-content:center;">
  <div style="text-align:center;max-width:440px;padding:40px 24px;">

    <?php if ($paymentPaid): ?>
    <!-- ✅ SUKSES — terkonfirmasi dari database (callback sudah diterima) -->
    <div style="width:80px;height:80px;background:rgba(34,197,94,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;border:2px solid rgba(34,197,94,0.3);">
      <i class="fa fa-check" style="font-size:36px;color:var(--success);"></i>
    </div>
    <h2 style="font-family:'Saira',sans-serif;font-size:28px;font-weight:800;color:#fff;margin-bottom:12px;">Pembayaran Berhasil!</h2>
    <p style="color:var(--gray-light);font-size:15px;line-height:1.7;margin-bottom:8px;">
      Pendaftaran kamu sudah aktif. Selamat bergabung di event PeakMiles!
    </p>
    <?php if ($reference): ?>
    <div style="background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:10px;padding:16px;margin:20px 0;text-align:left;">
      <div style="font-size:12px;color:var(--gray-light);margin-bottom:4px;">Nomor Referensi</div>
      <div style="font-size:14px;font-weight:600;color:#fff;"><?= sanitize($reference) ?></div>
    </div>
    <?php endif; ?>
    <a href="<?= SITE_URL ?>/dashboard" class="btn-primary-custom" style="width:100%;justify-content:center;padding:14px;margin-top:8px;">
      <i class="fa fa-running"></i> Ke Dashboard
    </a>

    <?php elseif ($pendingCallback): ?>
    <!-- ⏳ MENUNGGU KONFIRMASI — resultCode 00 tapi callback server belum tiba -->
    <div style="width:80px;height:80px;background:rgba(59,130,246,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;border:2px solid rgba(59,130,246,0.3);">
      <i class="fa fa-spinner fa-spin" style="font-size:36px;color:var(--info);"></i>
    </div>
    <h2 style="font-family:'Saira',sans-serif;font-size:28px;font-weight:800;color:#fff;margin-bottom:12px;">Sedang Diverifikasi…</h2>
    <p style="color:var(--gray-light);font-size:15px;line-height:1.7;margin-bottom:20px;">
      Pembayaran sedang dikonfirmasi oleh sistem. Akun akan aktif otomatis dalam beberapa saat.<br>
      <span style="font-size:13px;">Halaman ini akan refresh secara otomatis.</span>
    </p>
    <a href="<?= SITE_URL ?>/dashboard" class="btn-outline-custom" style="width:100%;justify-content:center;padding:14px;">
      <i class="fa fa-home"></i> Cek Dashboard
    </a>
    <!-- Auto-refresh untuk menunggu callback masuk (maks 5x) -->
    <script>
    (function() {
      var key = 'pr_refresh_' + <?= json_encode($merchantOrderId) ?>;
      var count = parseInt(sessionStorage.getItem(key) || '0');
      if (count < 5) {
        sessionStorage.setItem(key, count + 1);
        setTimeout(function() { window.location.reload(); }, 4000);
      }
    })();
    </script>

    <?php elseif ($resultCode === '01'): ?>
    <!-- 🕐 PENDING PAYMENT (VA/transfer belum dibayar) -->
    <div style="width:80px;height:80px;background:rgba(245,158,11,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;border:2px solid rgba(245,158,11,0.3);">
      <i class="fa fa-clock" style="font-size:36px;color:var(--warning);"></i>
    </div>
    <h2 style="font-family:'Saira',sans-serif;font-size:28px;font-weight:800;color:#fff;margin-bottom:12px;">Menunggu Pembayaran</h2>
    <p style="color:var(--gray-light);font-size:15px;line-height:1.7;margin-bottom:20px;">
      Tagihan sudah dibuat. Selesaikan pembayaran sesuai instruksi yang dikirim ke email kamu.
      Akun akan aktif otomatis setelah pembayaran dikonfirmasi.
    </p>
    <a href="<?= SITE_URL ?>/dashboard" class="btn-outline-custom" style="width:100%;justify-content:center;padding:14px;">
      <i class="fa fa-home"></i> Ke Dashboard
    </a>

    <?php else: ?>
    <!-- ❌ GAGAL / DIBATALKAN -->
    <div style="width:80px;height:80px;background:rgba(239,68,68,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;border:2px solid rgba(239,68,68,0.3);">
      <i class="fa fa-times" style="font-size:36px;color:var(--danger);"></i>
    </div>
    <h2 style="font-family:'Saira',sans-serif;font-size:28px;font-weight:800;color:#fff;margin-bottom:12px;">Pembayaran Dibatalkan</h2>
    <p style="color:var(--gray-light);font-size:15px;line-height:1.7;margin-bottom:20px;">
      Pembayaran tidak diselesaikan. Akun kamu sudah terdaftar — kamu bisa bayar kapan saja dari dashboard.
    </p>
    <a href="<?= SITE_URL ?>/dashboard" class="btn-primary-custom" style="width:100%;justify-content:center;padding:14px;">
      <i class="fa fa-credit-card"></i> Bayar di Dashboard
    </a>
    <?php endif; ?>

    <p style="margin-top:20px;">
      <a href="<?= SITE_URL ?>/" style="color:var(--gray-light);font-size:13px;text-decoration:none;">
        <i class="fa fa-arrow-left" style="margin-right:4px;"></i> Kembali ke Beranda
      </a>
    </p>
  </div>
</div>
</body>
</html>
