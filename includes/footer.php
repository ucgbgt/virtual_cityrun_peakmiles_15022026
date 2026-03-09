<footer class="footer">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-4">
        <div class="footer-brand">Peak<span>Miles</span></div>
        <p class="footer-desc">Platform virtual run terbaik untuk kamu yang ingin berlari dari mana saja. Bergabunglah bersama ribuan pelari Indonesia!</p>
      </div>
      <div class="col-lg-2 col-6">
        <div class="footer-heading">Event</div>
        <a href="<?= SITE_URL ?>/#event" class="footer-link">Detail Event</a>
        <a href="<?= SITE_URL ?>/#how-it-works" class="footer-link">Cara Ikut</a>
        <a href="<?= SITE_URL ?>/#race-pack" class="footer-link">Race Pack</a>
        <a href="<?= SITE_URL ?>/#faq" class="footer-link">FAQ</a>
      </div>
      <div class="col-lg-2 col-6">
        <div class="footer-heading">Info</div>
        <a href="<?= SITE_URL ?>/pages/contact.php" class="footer-link">Kontak</a>
        <a href="<?= SITE_URL ?>/pages/terms.php" class="footer-link">Syarat & Ketentuan</a>
        <a href="<?= SITE_URL ?>/pages/privacy.php" class="footer-link">Privasi</a>
      </div>
      <div class="col-lg-4">
        <div class="footer-heading">Ikuti Kami</div>
        <div class="d-flex gap-2 mb-3">
          <a href="https://instagram.com/peakmiles.id" target="_blank" class="btn-outline-custom btn-sm-custom" style="padding:8px 12px;" title="@peakmiles.id"><i class="fab fa-instagram"></i></a>
          <a href="#" class="btn-outline-custom btn-sm-custom" style="padding:8px 12px;"><i class="fab fa-tiktok"></i></a>
          <a href="#" class="btn-outline-custom btn-sm-custom" style="padding:8px 12px;"><i class="fab fa-facebook"></i></a>
        </div>
        <div class="footer-link" style="cursor:default;"><i class="fa fa-envelope me-2" style="color:var(--primary)"></i> info@peakmiles.id</div>
        <div class="footer-link" style="cursor:default;"><i class="fa fa-phone me-2" style="color:var(--primary)"></i> +62 851-1120-6025</div>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> PeakMiles. All rights reserved.</span>
      <span>Made with <i class="fa fa-heart" style="color:var(--primary)"></i> for Indonesian Runners</span>
    </div>
  </div>
</footer>

<div id="lightbox" class="lightbox">
  <button class="lightbox-close" onclick="closeLightbox()"><i class="fa fa-times"></i></button>
  <img id="lightbox-img" src="" alt="">
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
