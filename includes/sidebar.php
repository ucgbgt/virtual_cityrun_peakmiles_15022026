<?php
require_once __DIR__ . '/functions.php';
requireLogin();
$currentUser = getCurrentUser();
$activeNav = $activeNav ?? '';
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <a href="<?= SITE_URL ?>/">Peak<span>Miles</span></a>
  </div>
  <nav class="sidebar-nav">
    <?php if (isAdmin()): ?>
      <div class="sidebar-label">Admin Panel</div>
      <a href="<?= SITE_URL ?>/admin/index.php" class="sidebar-link <?= $activeNav === 'admin-dashboard' ? 'active' : '' ?>">
        <i class="fa fa-chart-pie"></i> Dashboard
      </a>
      <a href="<?= SITE_URL ?>/admin/participants.php" class="sidebar-link <?= $activeNav === 'admin-participants' ? 'active' : '' ?>">
        <i class="fa fa-users"></i> Peserta
      </a>
      <a href="<?= SITE_URL ?>/admin/submissions.php" class="sidebar-link <?= $activeNav === 'admin-submissions' ? 'active' : '' ?>">
        <i class="fa fa-file-upload"></i> Submission
      </a>
      <a href="<?= SITE_URL ?>/admin/shipping.php" class="sidebar-link <?= $activeNav === 'admin-shipping' ? 'active' : '' ?>">
        <i class="fa fa-shipping-fast"></i> Pengiriman
      </a>
      <a href="<?= SITE_URL ?>/admin/event-settings.php" class="sidebar-link <?= $activeNav === 'admin-event' ? 'active' : '' ?>">
        <i class="fa fa-cog"></i> Event Settings
      </a>
      <a href="<?= SITE_URL ?>/admin/payment-settings.php" class="sidebar-link <?= $activeNav === 'admin-payment' ? 'active' : '' ?>">
        <i class="fa fa-credit-card"></i> Metode Pembayaran
      </a>
      <a href="<?= SITE_URL ?>/admin/admins.php" class="sidebar-link <?= $activeNav === 'admin-admins' ? 'active' : '' ?>">
        <i class="fa fa-user-shield"></i> Manajemen Admin
      </a>
      <div class="sidebar-label" style="margin-top:16px;">User View</div>
    <?php endif; ?>

    <a href="<?= SITE_URL ?>/dashboard.php" class="sidebar-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
      <i class="fa fa-th-large"></i> Dashboard
    </a>
    <a href="<?= SITE_URL ?>/submissions.php" class="sidebar-link <?= $activeNav === 'submissions' ? 'active' : '' ?>">
      <i class="fa fa-list-check"></i> Riwayat Lari
    </a>
    <a href="<?= SITE_URL ?>/shipping.php" class="sidebar-link <?= $activeNav === 'shipping' ? 'active' : '' ?>">
      <i class="fa fa-box"></i> Status Pengiriman
    </a>
    <a href="<?= SITE_URL ?>/certificate.php" class="sidebar-link <?= $activeNav === 'certificate' ? 'active' : '' ?>">
      <i class="fa fa-certificate"></i> E-Certificate
    </a>
    <div class="sidebar-label" style="margin-top:16px;">Akun</div>
    <a href="<?= SITE_URL ?>/profile.php" class="sidebar-link <?= $activeNav === 'profile' ? 'active' : '' ?>">
      <i class="fa fa-user"></i> Profil
    </a>
    <a href="<?= SITE_URL ?>/logout.php" class="sidebar-link" style="color:rgba(239,68,68,0.8);">
      <i class="fa fa-sign-out-alt"></i> Keluar
    </a>
  </nav>
  <div class="sidebar-user">
    <div class="d-flex align-items-center gap-3">
      <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dark));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:#fff;flex-shrink:0;">
        <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
      </div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name"><?= sanitize($currentUser['name']) ?></div>
        <div class="sidebar-user-role"><?= $currentUser['role'] === 'admin' ? 'Administrator' : 'Peserta' ?></div>
      </div>
    </div>
  </div>
</aside>
