<?php
$pageTitle = 'Syarat & Ketentuan';
require_once __DIR__ . '/../includes/header.php';
?>
<section style="padding:80px 0;min-height:80vh;">
  <div class="container">
    <div class="text-center mb-5">
      <span class="section-label">Legal</span>
      <h2 class="section-title">Syarat & Ketentuan</h2>
    </div>
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="form-card" style="line-height:1.9;">
          <?php
          $sections = [
            '1. Ketentuan Umum' => 'Dengan mendaftar dan berpartisipasi dalam Budapest Vrtl Hlf Mrthn 2026, peserta dianggap telah membaca, memahami, dan menyetujui seluruh syarat dan ketentuan yang berlaku.',
            '2. Pendaftaran' => 'Pendaftaran dilakukan melalui platform Nusatix.com. Peserta wajib mengisi data yang benar dan akurat. Akun peakmiles.id akan dibuat setelah verifikasi pembayaran.',
            '3. Submission Bukti Lari' => 'Peserta wajib mengupload bukti lari berupa screenshot/foto dari aplikasi lari yang menampilkan jarak dan tanggal. Bukti palsu atau manipulasi akan mengakibatkan diskualifikasi. Maksimal 3 submission per hari. Maksimal 30 km per submission.',
            '4. Verifikasi & Persetujuan' => 'Tim panitia akan mereview setiap submission dalam 1-2 hari kerja. Keputusan panitia bersifat final. Submission yang ditolak tidak akan menambah progres km.',
            '5. Race Pack & Pengiriman' => 'Race pack (jersey & medali) akan dikirimkan setelah event selesai ke alamat yang didaftarkan. Panitia tidak bertanggung jawab atas keterlambatan akibat kesalahan alamat peserta.',
            '6. E-Certificate' => 'E-Certificate akan diterbitkan secara otomatis setelah peserta mencapai target km. Certificate bersifat digital dan tidak dicetak oleh panitia.',
            '7. Pembatalan & Refund' => 'Biaya pendaftaran tidak dapat dikembalikan setelah pembayaran dikonfirmasi, kecuali event dibatalkan oleh panitia.',
            '8. Privasi Data' => 'Data peserta digunakan hanya untuk keperluan event dan pengiriman race pack. Data tidak akan dibagikan kepada pihak ketiga tanpa persetujuan peserta.',
          ];
          foreach ($sections as $title => $content): ?>
          <h5 style="color:#fff;font-weight:700;margin:28px 0 10px;"><?= $title ?></h5>
          <p style="color:var(--gray-light);"><?= $content ?></p>
          <?php endforeach; ?>
          <p style="color:var(--gray-light);margin-top:28px;font-size:13px;">Terakhir diperbarui: Januari 2025</p>
        </div>
      </div>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
