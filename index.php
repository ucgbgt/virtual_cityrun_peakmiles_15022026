<?php
$pageTitle = 'Virtual Run 10K & 21K';
require_once __DIR__ . '/includes/header.php';
$event = getActiveEvent();
?>

<!-- HERO SECTION -->
<section class="hero" id="home">
  <div class="hero-bg"></div>
  <div class="hero-grid"></div>
  <div class="container" style="position:relative;z-index:2;">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <div class="hero-badge">
          <i class="fa fa-running"></i>
          <?= $event ? sanitize($event['name']) : 'PeakMiles Virtual Run 2026' ?>
        </div>
        <h1 class="hero-title">Run Your Way.<br><span>Anywhere.</span><br>Anytime.</h1>
        <p class="hero-desc">Ikuti virtual run 10K & 21K dari lokasi mana saja. Kumpulkan kilometer, raih status Finisher, dan dapatkan jersey + medali eksklusif!</p>
        <div class="hero-stats">
          <div class="hero-stat">
            <span class="hero-stat-val counter" data-target="2500">0</span>
            <span class="hero-stat-label">Peserta</span>
          </div>
          <div class="hero-stat">
            <span class="hero-stat-val counter" data-target="1800">0</span>
            <span class="hero-stat-label">Finisher</span>
          </div>
          <div class="hero-stat">
            <span class="hero-stat-val">10K / 21K</span>
            <span class="hero-stat-label">Kategori</span>
          </div>
        </div>
        <div class="d-flex gap-3 flex-wrap">
          <a href="<?= SITE_URL ?>/register.php" class="btn-primary-custom">
            <i class="fa fa-running"></i> Daftar Sekarang
          </a>
          <a href="#how-it-works" class="btn-outline-custom">
            <i class="fa fa-info-circle"></i> Cara Ikut
          </a>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="hero-visual">
          <div class="hero-circle">
            <div class="hero-circle-inner"><i class="fa fa-person-running" style="font-size:52px;color:var(--primary);"></i></div>
            <div class="floating-card float-1">
              <div class="floating-card-title">Kategori Tersedia</div>
              <div class="floating-card-val">10K & 21K</div>
            </div>
            <div class="floating-card float-2">
              <div class="floating-card-title">Hadiah</div>
              <div class="floating-card-val">Jersey + Medali</div>
            </div>
            <div class="floating-card float-3">
              <div class="floating-card-title">E-Certificate</div>
              <div class="floating-card-val">Otomatis <i class="fa fa-trophy" style="color:var(--primary);font-size:13px;"></i></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- EVENT DETAIL SECTION -->
<section class="section section-darker" id="event">
  <div class="container">
    <div class="text-center mb-5">
      <span class="section-label">Detail Event</span>
      <h2 class="section-title">Informasi Virtual Run</h2>
      <p class="section-desc">Pilih kategorimu dan mulai petualangan larimu dari mana saja!</p>
    </div>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="card-custom h-100">
          <div class="card-icon"><i class="fa fa-calendar-alt"></i></div>
          <h4 style="color:#fff;margin-bottom:8px;">Periode Event</h4>
          <p style="color:var(--gray-light);font-size:14px;margin-bottom:12px;">Event berlangsung selama beberapa minggu penuh</p>
          <?php if ($event): ?>
          <div style="color:var(--primary);font-weight:600;">
            <?= date('d M Y', strtotime($event['start_date'])) ?> — <?= date('d M Y', strtotime($event['end_date'])) ?>
          </div>
          <?php else: ?>
          <div style="color:var(--primary);font-weight:600;">1 Feb — 31 Mar 2025</div>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card-custom h-100" style="border-color:rgba(249,115,22,0.3);">
          <div class="card-icon" style="background:rgba(249,115,22,0.2);"><i class="fa fa-trophy"></i></div>
          <h4 style="color:#fff;margin-bottom:8px;">Kategori Lari</h4>
          <p style="color:var(--gray-light);font-size:14px;margin-bottom:16px;">Pilih sesuai kemampuan dan targetmu</p>
          <div style="display:flex;gap:12px;">
            <div style="background:rgba(249,115,22,0.1);border:1px solid rgba(249,115,22,0.3);padding:12px 20px;border-radius:10px;text-align:center;flex:1;">
              <div style="font-size:22px;font-weight:800;color:var(--primary);">10K</div>
              <div style="font-size:11px;color:var(--gray-light);">10 Kilometer</div>
              <div style="font-size:12px;color:var(--primary);margin-top:4px;">Rp 179.000</div>
            </div>
            <div style="background:rgba(249,115,22,0.1);border:1px solid rgba(249,115,22,0.3);padding:12px 20px;border-radius:10px;text-align:center;flex:1;">
              <div style="font-size:22px;font-weight:800;color:var(--primary);">21K</div>
              <div style="font-size:11px;color:var(--gray-light);">21 Kilometer</div>
              <div style="font-size:12px;color:var(--primary);margin-top:4px;">Rp 199.000</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card-custom h-100">
          <div class="card-icon"><i class="fa fa-map-marker-alt"></i></div>
          <h4 style="color:#fff;margin-bottom:8px;">Virtual Run</h4>
          <p style="color:var(--gray-light);font-size:14px;margin-bottom:12px;">Lari dari mana saja — lapangan, jalan, treadmill, atau trek favoritmu!</p>
          <ul style="list-style:none;padding:0;color:var(--gray-light);font-size:13px;">
            <li style="margin-bottom:6px;"><i class="fa fa-check" style="color:var(--primary);margin-right:8px;"></i>Bebas lokasi</li>
            <li style="margin-bottom:6px;"><i class="fa fa-check" style="color:var(--primary);margin-right:8px;"></i>Bisa dibagi beberapa sesi</li>
            <li><i class="fa fa-check" style="color:var(--primary);margin-right:8px;"></i>Submit bukti foto/screenshot</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="section section-dark" id="how-it-works">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-5">
        <span class="section-label">Cara Ikut</span>
        <h2 class="section-title">Mudah & Seru dalam 7 Langkah</h2>
        <p style="color:var(--gray-light);font-size:15px;line-height:1.8;">Ikuti virtual run bersama PeakMiles dengan mudah. Daftar, lari, submit, dan dapatkan penghargaanmu!</p>
        <a href="<?= SITE_URL ?>/register.php" class="btn-primary-custom mt-3">
          <i class="fa fa-arrow-right"></i> Mulai Sekarang
        </a>
      </div>
      <div class="col-lg-7">
        <div class="step-item">
          <div class="step-num">1</div>
          <div class="step-content">
            <h4>Registrasi via Nusatix</h4>
            <p>Klik tombol "Daftar Sekarang", isi form pendaftaran, dan pilih kategori 10K (Rp 179.000) atau 21K (Rp 199.000).</p>
          </div>
        </div>
        <div class="step-item">
          <div class="step-num">2</div>
          <div class="step-content">
            <h4>Login ke Dashboard</h4>
            <p>Setelah registrasi diverifikasi, kamu akan mendapat akses login ke peakmiles.id. Lengkapi profilmu untuk pengiriman race pack.</p>
          </div>
        </div>
        <div class="step-item">
          <div class="step-num">3</div>
          <div class="step-content">
            <h4>Mulai Berlari!</h4>
            <p>Lari dari lokasi mana saja sesuai targetmu. Bisa dilakukan dalam satu sesi atau beberapa sesi.</p>
          </div>
        </div>
        <div class="step-item">
          <div class="step-num">4</div>
          <div class="step-content">
            <h4>Submit Bukti Lari</h4>
            <p>Upload foto/screenshot aplikasi lari sebagai bukti. Sertakan tanggal, jarak, dan catatan opsional.</p>
          </div>
        </div>
        <div class="step-item">
          <div class="step-num">5</div>
          <div class="step-content">
            <h4>Tunggu Verifikasi Admin</h4>
            <p>Tim kami akan mereview bukti larimu dalam 1-2 hari kerja. Jarak akan ditambahkan ke total km setelah disetujui.</p>
          </div>
        </div>
        <div class="step-item">
          <div class="step-num">6</div>
          <div class="step-content">
            <h4>Raih Status Finisher & E-Certificate</h4>
            <p>Begitu total km mencapai target, status berubah jadi FINISHER dan E-Certificate tersedia untuk diunduh otomatis!</p>
          </div>
        </div>
        <div class="step-item">
          <div class="step-num">7</div>
          <div class="step-content">
            <h4>Terima Race Pack</h4>
            <p>Jersey dan medali eksklusif akan dikirim ke alamat yang kamu daftarkan. Pantau status pengiriman di dashboard.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- BENEFITS / RACE PACK -->
<section class="section section-darker" id="race-pack">
  <div class="container">
    <div class="text-center mb-5">
      <span class="section-label">Race Pack</span>
      <h2 class="section-title">Yang Kamu Dapatkan</h2>
      <p class="section-desc">Setiap peserta mendapatkan paket eksklusif PeakMiles yang dikirim ke alamat pendaftaran</p>
    </div>
      <div class="row g-4">
      <div class="col-sm-6 col-lg-3">
        <div class="benefit-card">
          <div class="benefit-icon"><i class="fa fa-shirt"></i></div>
          <div class="benefit-title">Jersey Eksklusif</div>
          <p class="benefit-desc">Jersey running berdesain eksklusif PeakMiles dengan material berkualitas. Tersedia dari XS hingga XXXL.</p>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="benefit-card">
          <div class="benefit-icon"><i class="fa fa-medal"></i></div>
          <div class="benefit-title">Medali Finisher</div>
          <p class="benefit-desc">Medali unik untuk setiap finisher sebagai tanda kebanggaan atas pencapaianmu. Desain berbeda tiap kategori!</p>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="benefit-card">
          <div class="benefit-icon"><i class="fa fa-file-certificate"></i></div>
          <div class="benefit-title">E-Certificate</div>
          <p class="benefit-desc">Sertifikat digital langsung tersedia untuk diunduh begitu kamu mencapai target. Otomatis & instan!</p>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="benefit-card">
          <div class="benefit-icon"><i class="fa fa-trophy"></i></div>
          <div class="benefit-title">Status Finisher</div>
          <p class="benefit-desc">Banggakan pencapaianmu dengan status resmi FINISHER yang terverifikasi di platform PeakMiles.</p>
        </div>
      </div>
    </div>

    <div class="racepack-card mt-5">
      <div class="row align-items-center g-4">
        <div class="col-lg-7" style="position:relative;z-index:1;">
          <h3 style="font-family:'Syne',sans-serif;font-size:28px;font-weight:800;color:#fff;margin-bottom:12px;">Siap untuk Berlari?</h3>
          <p style="color:var(--gray-light);font-size:15px;margin-bottom:24px;">Daftar sekarang melalui Nusatix dan bergabunglah dengan ribuan pelari Indonesia dalam PeakMiles Virtual Run!</p>
          <a href="<?= SITE_URL ?>/register.php" class="btn-primary-custom">
            <i class="fa fa-external-link-alt"></i> Daftar di Nusatix.com
          </a>
        </div>
        <div class="col-lg-5 text-center" style="position:relative;z-index:1;">
          <div style="font-size:64px;line-height:1;color:var(--primary);opacity:0.85;">
            <i class="fa fa-person-running"></i>
          </div>
          <div style="color:var(--gray-light);font-size:13px;margin-top:12px;">Bergabung bersama 2.500+ pelari</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FAQ SECTION -->
<section class="section section-dark" id="faq">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-4">
        <span class="section-label">FAQ</span>
        <h2 class="section-title">Pertanyaan Sering Diajukan</h2>
        <p style="color:var(--gray-light);font-size:15px;line-height:1.7;">Masih ada pertanyaan? Kami siap membantu!</p>
        <a href="<?= SITE_URL ?>/pages/contact.php" class="btn-outline-custom mt-3">
          <i class="fa fa-envelope"></i> Hubungi Kami
        </a>
      </div>
      <div class="col-lg-8">
        <div class="faq-item">
          <button class="faq-question">
            Bagaimana cara mendaftar event ini?
            <i class="fa fa-plus faq-icon"></i>
          </button>
          <div class="faq-answer">Pendaftaran dilakukan melalui platform Nusatix.com. Klik tombol "Daftar Sekarang" di website ini, kamu akan diarahkan ke halaman pendaftaran di Nusatix. Setelah pembayaran berhasil, akun di peakmiles.id akan diaktifkan oleh tim kami.</div>
        </div>
        <div class="faq-item">
          <button class="faq-question">
            Apakah bisa lari lebih dari sekali untuk mencapai target?
            <i class="fa fa-plus faq-icon"></i>
          </button>
          <div class="faq-answer">Ya! Kamu bisa submit bukti lari berkali-kali. Total km dari semua submission yang disetujui akan dijumlahkan. Misalnya untuk 21K, kamu bisa lari 7km, 7km, dan 7km di hari berbeda.</div>
        </div>
        <div class="faq-item">
          <button class="faq-question">
            Bukti lari apa yang harus disubmit?
            <i class="fa fa-plus faq-icon"></i>
          </button>
          <div class="faq-answer">Upload screenshot atau foto dari aplikasi lari (Strava, Nike Run Club, Garmin, dll) yang menampilkan jarak dan tanggal lari. Format file: JPG, PNG, atau WEBP, ukuran maksimal 10MB.</div>
        </div>
        <div class="faq-item">
          <button class="faq-question">
            Berapa lama proses verifikasi submission?
            <i class="fa fa-plus faq-icon"></i>
          </button>
          <div class="faq-answer">Tim kami akan mereview submissionmu dalam 1-2 hari kerja. Kamu akan mendapat notifikasi jika submission disetujui atau ditolak beserta alasannya.</div>
        </div>
        <div class="faq-item">
          <button class="faq-question">
            Kapan race pack dikirim?
            <i class="fa fa-plus faq-icon"></i>
          </button>
          <div class="faq-answer">Race pack (jersey & medali) akan dikirimkan setelah event selesai. Kamu bisa pantau status pengiriman di dashboard masing-masing.</div>
        </div>
        <div class="faq-item">
          <button class="faq-question">
            Apa yang terjadi jika submission saya ditolak?
            <i class="fa fa-plus faq-icon"></i>
          </button>
          <div class="faq-answer">Jika submission ditolak, admin akan memberikan alasan penolakan. Kamu bisa submit ulang dengan bukti yang lebih jelas. Pastikan screenshot menampilkan jarak dan tanggal yang terbaca jelas.</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CTA SECTION -->
<section class="section section-darker" style="padding:70px 0;">
  <div class="container text-center">
    <h2 class="section-title" style="margin-bottom:16px;">Siap Menjadi Finisher?</h2>
    <p style="color:var(--gray-light);font-size:16px;margin-bottom:36px;max-width:500px;margin-left:auto;margin-right:auto;">
      Bergabunglah dengan ribuan pelari dan buktikan semangatmu. Daftar sekarang sebelum kuota habis!
    </p>
    <div class="d-flex gap-3 justify-content-center flex-wrap">
      <a href="<?= SITE_URL ?>/register.php" class="btn-primary-custom" style="font-size:17px;padding:15px 36px;">
        <i class="fa fa-running"></i> Daftar Sekarang
      </a>
      <a href="<?= SITE_URL ?>/login.php" class="btn-outline-custom" style="font-size:17px;padding:15px 36px;">
        <i class="fa fa-sign-in-alt"></i> Login
      </a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
