<?php
require_once __DIR__ . '/includes/functions.php';
startSession();

if (isLoggedIn()) {
    redirect(SITE_URL . '/dashboard.php');
}

$error = '';
$redirect = sanitize($_GET['redirect'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Token tidak valid. Silakan refresh halaman.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Email dan password wajib diisi.';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['name'];
                session_regenerate_id(true);

                $dest = ($user['role'] === 'admin') ? SITE_URL . '/admin/index.php' : SITE_URL . '/dashboard.php';
                if ($redirect && strpos($redirect, SITE_URL) === 0) $dest = $redirect;
                redirect($dest);
            } else {
                $error = 'Email atau password salah. Silakan coba lagi.';
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
<title>Login — Budapest Vrtl Hlf Mrthn 2026</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@300;400;500;600;700;800;900&family=Saira:wght@700;800;900&display=swap" rel="stylesheet">
</head>
<body>
<div class="auth-page">
  <!-- Left decorative panel -->
  <div class="auth-left">
    <div class="auth-left-bg"></div>
    <div style="position:relative;z-index:1;text-align:center;">
      <div style="font-size:56px;margin-bottom:24px;color:var(--primary);line-height:1;">
        <i class="fa fa-person-running"></i>
      </div>
      <h2 style="font-family:'Syne',sans-serif;font-size:32px;font-weight:800;color:#fff;margin-bottom:16px;">
        Selamat Datang di<br><span style="color:var(--primary);">Budapest Vrtl Hlf Mrthn 2026</span>
      </h2>
      <p style="color:var(--gray-light);font-size:15px;line-height:1.7;max-width:320px;margin:0 auto;">
        Platform virtual run terbaik untuk melatih semangat berlarumu dari mana saja.
      </p>
      <div style="margin-top:40px;display:flex;gap:20px;justify-content:center;">
        <div style="text-align:center;">
          <div style="font-size:28px;font-weight:800;color:var(--primary);">5K</div>
          <div style="font-size:11px;color:var(--gray-light);">& 10K</div>
        </div>
        <div style="width:1px;background:var(--border);"></div>
        <div style="text-align:center;">
          <div style="font-size:24px;color:var(--primary);"><i class="fa fa-file-certificate"></i></div>
          <div style="font-size:11px;color:var(--gray-light);">E-Certificate</div>
        </div>
        <div style="width:1px;background:var(--border);"></div>
        <div style="text-align:center;">
          <div style="font-size:24px;color:var(--primary);"><i class="fa fa-medal"></i></div>
          <div style="font-size:11px;color:var(--gray-light);">Medali</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Right login form -->
  <div class="auth-right">
    <div class="auth-box">
      <div class="auth-logo">
        <a href="<?= SITE_URL ?>/" style="font-family:'Syne',sans-serif;font-size:24px;font-weight:800;color:#fff;text-decoration:none;">
          Peak<span style="color:var(--primary);">Miles</span>
        </a>
      </div>
      <h1 class="auth-title">Login ke Akun</h1>
      <p class="auth-subtitle">Masukkan email dan password yang kamu gunakan saat mendaftar.</p>

      <?php if ($error): ?>
      <div class="alert-custom alert-danger">
        <i class="fa fa-exclamation-circle"></i> <?= sanitize($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control-custom" placeholder="nama@email.com" 
                 value="<?= sanitize($_POST['email'] ?? '') ?>" required autofocus>
        </div>
        <div class="form-group">
          <label class="form-label" style="display:flex;justify-content:space-between;">
            Password
            <a href="<?= SITE_URL ?>/forgot-password.php" style="color:var(--primary);font-weight:500;text-decoration:none;font-size:12px;">Lupa Password?</a>
          </label>
          <div style="position:relative;">
            <input type="password" name="password" id="password" class="form-control-custom" placeholder="Masukkan password" required style="padding-right:44px;">
            <button type="button" onclick="togglePwd()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--gray-light);cursor:pointer;">
              <i class="fa fa-eye" id="pwd-icon"></i>
            </button>
          </div>
        </div>
        <button type="submit" class="btn-primary-custom" style="width:100%;justify-content:center;padding:14px;">
          <i class="fa fa-sign-in-alt"></i> Login
        </button>
      </form>

      <p style="text-align:center;color:var(--gray-light);font-size:14px;margin-top:28px;">
        Belum punya akun?
        <a href="<?= SITE_URL ?>/register.php" style="color:var(--primary);font-weight:600;text-decoration:none;">Daftar di sini</a>
      </p>
      <p style="text-align:center;margin-top:12px;">
        <a href="<?= SITE_URL ?>/" style="color:var(--gray-light);font-size:13px;text-decoration:none;">
          <i class="fa fa-arrow-left" style="margin-right:4px;"></i> Kembali ke Landing Page
        </a>
      </p>
    </div>
  </div>
</div>

<script>
function togglePwd() {
  const inp = document.getElementById('password');
  const ico = document.getElementById('pwd-icon');
  if (inp.type === 'password') { inp.type = 'text'; ico.className = 'fa fa-eye-slash'; }
  else { inp.type = 'password'; ico.className = 'fa fa-eye'; }
}
</script>
</body>
</html>
