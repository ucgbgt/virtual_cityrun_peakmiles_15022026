<?php
require_once __DIR__ . '/functions.php';
startSession();
$flash = getFlash();
$currentUser = isLoggedIn() ? getCurrentUser() : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? sanitize($pageTitle) . ' — ' : '' ?><?= SITE_NAME ?></title>
<meta name="description" content="PeakMiles Virtual Run 5K & 10K. Lari dari mana saja, kapan saja. Daftar sekarang dan buktikan semangatmu!">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Syne:wght@700;800&display=swap" rel="stylesheet">
</head>
<body>

<?php if ($flash): ?>
<div id="flash-msg" class="alert-custom alert-<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'danger' : $flash['type']) ?>" 
     style="position:fixed;top:16px;right:16px;z-index:9999;max-width:380px;box-shadow:var(--shadow);">
  <i class="fa <?= $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
  <?= sanitize($flash['message']) ?>
  <button onclick="this.parentElement.remove()" style="background:none;border:none;color:inherit;margin-left:auto;cursor:pointer;font-size:16px;">&times;</button>
</div>
<script>setTimeout(()=>{ const el=document.getElementById('flash-msg'); if(el) el.remove(); }, 5000);</script>
<?php endif; ?>

<nav class="navbar navbar-expand-lg">
  <div class="container">
    <a href="<?= SITE_URL ?>/" class="navbar-brand">Peak<span>Miles</span></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav ms-auto align-items-center gap-1">
        <li class="nav-item"><a href="<?= SITE_URL ?>/#event" class="nav-link">Event</a></li>
        <li class="nav-item"><a href="<?= SITE_URL ?>/#how-it-works" class="nav-link">Cara Ikut</a></li>
        <li class="nav-item"><a href="<?= SITE_URL ?>/#race-pack" class="nav-link">Race Pack</a></li>
        <li class="nav-item"><a href="<?= SITE_URL ?>/#faq" class="nav-link">FAQ</a></li>
        <li class="nav-item"><a href="<?= SITE_URL ?>/pages/contact.php" class="nav-link">Kontak</a></li>
        <?php if (isLoggedIn()): ?>
          <li class="nav-item">
            <a href="<?= SITE_URL ?>/dashboard.php" class="btn-primary-custom btn-sm-custom" style="margin-left:8px;">
              <i class="fa fa-th-large"></i> Dashboard
            </a>
          </li>
        <?php else: ?>
          <li class="nav-item">
            <a href="<?= SITE_URL ?>/login.php" class="btn-outline-custom btn-sm-custom" style="margin-left:8px;">Login</a>
          </li>
          <li class="nav-item">
            <a href="<?= NUSATIX_URL ?>" target="_blank" class="btn-primary-custom btn-sm-custom">
              <i class="fa fa-running"></i> Daftar Sekarang
            </a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
