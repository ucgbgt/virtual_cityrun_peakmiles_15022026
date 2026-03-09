<?php
require_once __DIR__ . '/includes/functions.php';
startSession();
requireLogin();

$merchantOrderId = $_GET['merchantOrderId'] ?? '';
$resultCode      = $_GET['resultCode']      ?? '';
$reference       = $_GET['reference']       ?? '';

$db   = getDB();
$user = getCurrentUser();

// Cek status aktual dari database
$paymentPaid = false;
if ($merchantOrderId) {
    $stmt = $db->prepare("SELECT payment_status FROM registrations WHERE merchant_order_id=? AND user_id=?");
    $stmt->execute([$merchantOrderId, $user['id']]);
    $reg = $stmt->fetch();
    $paymentPaid = ($reg && $reg['payment_status'] === 'paid');
}

// Jika resultCode 00 tapi callback belum masuk, update manual
if ($resultCode === '00' && !$paymentPaid && $merchantOrderId) {
    $db->prepare("UPDATE registrations SET payment_status='paid', payment_reference=? WHERE merchant_order_id=? AND user_id=?")
       ->execute([$reference, $merchantOrderId, $user['id']]);
    $paymentPaid = true;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Status Pembayaran — Budapest Vrtl Hlf Mrthn 2026</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@300;400;500;600;700;800;900&family=Saira:wght@700;800;900&display=swap" rel="stylesheet">
</head>
<body>
<div class="auth-page" style="min-height:100vh;display:flex;align-items:center;justify-content:center;">
  <div style="text-align:center;max-width:440px;padding:40px 24px;">

    <?php if ($paymentPaid || $resultCode === '00'): ?>
    <!-- SUKSES -->
    <div style="width:80px;height:80px;background:rgba(34,197,94,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;border:2px solid rgba(34,197,94,0.3);">
      <i class="fa fa-check" style="font-size:36px;color:var(--success);"></i>
    </div>
    <h2 style="font-family:'Syne',sans-serif;font-size:28px;font-weight:800;color:#fff;margin-bottom:12px;">Pembayaran Berhasil!</h2>
    <p style="color:var(--gray-light);font-size:15px;line-height:1.7;margin-bottom:8px;">
      Pendaftaran kamu sudah aktif. Selamat bergabung di Budapest Vrtl Hlf Mrthn 2026!
    </p>
    <?php if ($reference): ?>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:16px;margin:20px 0;text-align:left;">
      <div style="font-size:12px;color:var(--gray-light);margin-bottom:4px;">Nomor Referensi</div>
      <div style="font-size:14px;font-weight:600;color:#fff;"><?= sanitize($reference) ?></div>
    </div>
    <?php endif; ?>
    <a href="<?= SITE_URL ?>/dashboard.php" class="btn-primary-custom" style="width:100%;justify-content:center;padding:14px;margin-top:8px;">
      <i class="fa fa-running"></i> Ke Dashboard
    </a>

    <?php elseif ($resultCode === '01'): ?>
    <!-- PENDING -->
    <div style="width:80px;height:80px;background:rgba(245,158,11,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;border:2px solid rgba(245,158,11,0.3);">
      <i class="fa fa-clock" style="font-size:36px;color:var(--warning);"></i>
    </div>
    <h2 style="font-family:'Syne',sans-serif;font-size:28px;font-weight:800;color:#fff;margin-bottom:12px;">Pembayaran Pending</h2>
    <p style="color:var(--gray-light);font-size:15px;line-height:1.7;margin-bottom:20px;">
      Pembayaran sedang diproses. Akun akan aktif otomatis setelah pembayaran dikonfirmasi.
    </p>
    <a href="<?= SITE_URL ?>/dashboard.php" class="btn-outline-custom" style="width:100%;justify-content:center;padding:14px;">
      <i class="fa fa-home"></i> Ke Dashboard
    </a>

    <?php else: ?>
    <!-- GAGAL / DIBATALKAN -->
    <div style="width:80px;height:80px;background:rgba(239,68,68,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;border:2px solid rgba(239,68,68,0.3);">
      <i class="fa fa-times" style="font-size:36px;color:var(--danger);"></i>
    </div>
    <h2 style="font-family:'Syne',sans-serif;font-size:28px;font-weight:800;color:#fff;margin-bottom:12px;">Pembayaran Dibatalkan</h2>
    <p style="color:var(--gray-light);font-size:15px;line-height:1.7;margin-bottom:20px;">
      Pembayaran tidak diselesaikan. Akun kamu sudah terdaftar, kamu bisa bayar kapan saja dari dashboard.
    </p>
    <a href="<?= SITE_URL ?>/dashboard.php" class="btn-primary-custom" style="width:100%;justify-content:center;padding:14px;">
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
