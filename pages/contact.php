<?php
$pageTitle = 'Kontak';
require_once __DIR__ . '/../includes/header.php';
?>
<section style="padding:80px 0;min-height:80vh;">
  <div class="container">
    <div class="text-center mb-5">
      <span class="section-label">Hubungi Kami</span>
      <h2 class="section-title">Kontak & Dukungan</h2>
      <p class="section-desc">Punya pertanyaan atau kendala? Kami siap membantu!</p>
    </div>
    <div class="row g-5 justify-content-center">
      <div class="col-lg-5">
        <div class="card-custom">
          <div class="card-icon"><i class="fa fa-envelope"></i></div>
          <h4 style="color:#fff;margin-bottom:8px;">Email</h4>
          <p style="color:var(--gray-light);">info@stridenation.id</p>
        </div>
        <div class="card-custom mt-4">
          <div class="card-icon"><i class="fab fa-whatsapp"></i></div>
          <h4 style="color:#fff;margin-bottom:8px;">WhatsApp</h4>
          <p style="color:var(--gray-light);">+62 812-3456-7890</p>
          <a href="https://wa.me/628123456789" target="_blank" class="btn-primary-custom btn-sm-custom mt-2">
            <i class="fab fa-whatsapp"></i> Chat WhatsApp
          </a>
        </div>
        <div class="card-custom mt-4">
          <div class="card-icon"><i class="fab fa-instagram"></i></div>
          <h4 style="color:#fff;margin-bottom:8px;">Instagram</h4>
          <a href="https://instagram.com/stridenation.id" target="_blank" style="color:var(--primary);text-decoration:none;">@stridenation.id</a>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="form-card">
          <h4 style="color:#fff;font-weight:700;margin-bottom:24px;">Kirim Pesan</h4>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Nama</label>
                <input type="text" class="form-control-custom" placeholder="Nama kamu">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" class="form-control-custom" placeholder="email@kamu.com">
              </div>
            </div>
            <div class="col-12">
              <div class="form-group">
                <label class="form-label">Subjek</label>
                <input type="text" class="form-control-custom" placeholder="Topik pertanyaan">
              </div>
            </div>
            <div class="col-12">
              <div class="form-group">
                <label class="form-label">Pesan</label>
                <textarea class="form-control-custom" rows="5" placeholder="Tuliskan pesan atau pertanyaanmu..."></textarea>
              </div>
            </div>
          </div>
          <button class="btn-primary-custom mt-2" onclick="alert('Terima kasih! Pesan kamu akan segera kami balas.')">
            <i class="fa fa-paper-plane"></i> Kirim Pesan
          </button>
        </div>
      </div>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
