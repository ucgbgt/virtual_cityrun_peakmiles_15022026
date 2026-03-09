<?php
require_once __DIR__ . '/includes/functions.php';
startSession();
if (isLoggedIn()) redirect(SITE_URL . '/dashboard.php');

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $message = 'Token tidak valid.';
        $messageType = 'danger';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Masukkan email yang valid.';
            $messageType = 'danger';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                $db->prepare("UPDATE users SET reset_token=?, reset_expires=? WHERE id=?")->execute([$token, $expires, $user['id']]);
                // In production, send email with token. For now, show token.
                $message = 'Link reset password telah dikirim ke email ' . sanitize($email) . '. Periksa inbox Anda.';
                $messageType = 'success';
            } else {
                // Don't reveal if email exists
                $message = 'Jika email terdaftar, link reset password akan dikirim segera.';
                $messageType = 'success';
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
<title>Lupa Password — Budapest Vrtl Hlf Mrthn 2026</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@300;400;500;600;700;800;900&family=Saira:wght@700;800;900&display=swap" rel="stylesheet">
</head>
<body>
<div class="auth-page">
  <div class="auth-left">
    <div class="auth-left-bg"></div>
    <div style="position:relative;z-index:1;text-align:center;">
      <div style="font-size:52px;margin-bottom:24px;color:var(--primary);line-height:1;">
        <i class="fa fa-lock-open"></i>
      </div>
      <h2 style="font-family:'Syne',sans-serif;font-size:28px;font-weight:800;color:#fff;margin-bottom:16px;">Reset Password</h2>
      <p style="color:var(--gray-light);font-size:15px;line-height:1.7;max-width:280px;margin:0 auto;">
        Jangan khawatir! Masukkan email kamu dan kami akan mengirimkan link untuk reset password.
      </p>
    </div>
  </div>
  <div class="auth-right">
    <div class="auth-box">
      <div class="auth-logo">
        <a href="<?= SITE_URL ?>/" style="font-family:'Syne',sans-serif;font-size:24px;font-weight:800;color:#fff;text-decoration:none;">
          Stride<span style="color:var(--primary);">Nation</span>
        </a>
      </div>
      <h1 class="auth-title">Lupa Password?</h1>
      <p class="auth-subtitle">Masukkan email akun kamu. Kami akan kirimkan link reset password.</p>

      <?php if ($message): ?>
      <div class="alert-custom alert-<?= $messageType ?>">
        <i class="fa <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
        <?= sanitize($message) ?>
      </div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control-custom" placeholder="nama@email.com" required autofocus>
        </div>
        <button type="submit" class="btn-primary-custom" style="width:100%;justify-content:center;padding:14px;">
          <i class="fa fa-paper-plane"></i> Kirim Link Reset
        </button>
      </form>
      <p style="text-align:center;margin-top:24px;">
        <a href="<?= SITE_URL ?>/login.php" style="color:var(--primary);font-size:14px;text-decoration:none;">
          <i class="fa fa-arrow-left" style="margin-right:4px;"></i> Kembali ke Login
        </a>
      </p>
    </div>
  </div>
</div>
</body>
</html>
