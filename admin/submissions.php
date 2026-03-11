<?php
$pageTitle = 'Manajemen Submission';
$activeNav = 'admin-submissions';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db    = getDB();
$event = getActiveEvent();

$filterStatus = $_GET['status'] ?? '';
$search       = $_GET['search'] ?? '';
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$offset       = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];
if ($event)        { $where[] = 'rs.event_id = ?'; $params[] = $event['id']; }
if ($filterStatus) { $where[] = 'rs.status = ?';   $params[] = $filterStatus; }
if ($search)       { $where[] = '(u.name LIKE ? OR u.email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
$whereStr = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM run_submissions rs JOIN users u ON rs.user_id=u.id WHERE $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

$stmt = $db->prepare("SELECT rs.*, u.name as user_name, u.email, a.name as reviewer_name
    FROM run_submissions rs
    JOIN users u ON rs.user_id = u.id
    LEFT JOIN users a ON rs.reviewed_by = a.id
    WHERE $whereStr ORDER BY rs.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$submissions = $stmt->fetchAll();
$totalPages  = $total ? ceil($total / $perPage) : 1;

// Hitung pending dalam halaman ini untuk badge checkbox
$pendingInPage = count(array_filter($submissions, fn($s) => $s['status'] === 'pending'));

$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manajemen Submission — PeakMiles</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@300;400;500;600;700;800;900&family=Saira:wght@700;800;900&display=swap" rel="stylesheet">
</head>
<body>
<div class="dashboard-layout">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <div class="d-flex align-items-center gap-3">
        <button id="sidebarToggle" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;" class="d-lg-none"><i class="fa fa-bars"></i></button>
        <div>
          <div class="topbar-title">Manajemen Submission</div>
          <div style="font-size:12px;color:var(--gray-light);"><?= $total ?> total · <?= $pendingInPage ?> pending di halaman ini</div>
        </div>
      </div>
    </div>

    <div class="page-content">
      <?php $flash = getFlash(); if ($flash): ?>
      <div class="alert-custom alert-<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'warning' ? 'warning' : 'danger') ?>" style="margin-bottom:16px;">
        <i class="fa fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <?= sanitize($flash['message']) ?>
      </div>
      <?php endif; ?>

      <!-- Filter Bar -->
      <div class="filter-bar">
        <form method="GET" class="row g-2 align-items-end">
          <div class="col-lg-5 col-md-6">
            <input type="text" name="search" class="form-control-custom" placeholder="Cari nama atau email..." value="<?= sanitize($search) ?>">
          </div>
          <div class="col-lg-4 col-md-3">
            <select name="status" class="form-control-custom">
              <option value="">Semua Status</option>
              <option value="pending"  <?= $filterStatus === 'pending'  ? 'selected' : '' ?>>Pending</option>
              <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
              <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
          </div>
          <div class="col-lg-3 col-md-3">
            <button type="submit" class="btn-primary-custom" style="width:100%;justify-content:center;padding:10px 0;border-radius:10px;font-size:13px;">
              <i class="fa fa-search"></i> Cari
            </button>
          </div>
        </form>
      </div>

      <!-- Quick filter tabs -->
      <div class="filter-tabs">
        <?php $tabs = [''=>'Semua','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'];
        foreach ($tabs as $val => $label): ?>
        <a href="?status=<?= $val ?>&search=<?= urlencode($search) ?>"
           class="filter-tab <?= $filterStatus === $val ? 'active' : '' ?>">
          <?= $label ?>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- ══════════════════════════════════════════
           BULK ACTION BAR (muncul saat ada pilihan)
           ══════════════════════════════════════════ -->
      <div id="bulkBar" class="bulk-bar-top">
        <i class="fa fa-layer-group" style="color:var(--primary);font-size:16px;"></i>
        <span id="bulkCount" class="bulk-count">0</span>
        <span style="color:var(--gray-light);font-size:13px;">submission dipilih</span>

        <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;">
          <button onclick="bulkApprove()" class="bulk-btn bulk-btn-delivered">
            <i class="fa fa-check-double"></i> Approve Terpilih
          </button>
          <button onclick="openModal('bulkRejectModal')" class="bulk-btn" style="background:var(--danger);color:#fff;">
            <i class="fa fa-times-circle"></i> Reject Terpilih
          </button>
          <button onclick="clearSelection()" class="bulk-btn bulk-btn-cancel">
            <i class="fa fa-times"></i> Batal
          </button>
        </div>
      </div>

      <!-- Tabel -->
      <div class="table-container">
        <div class="table-header">
          <div class="table-title"><i class="fa fa-file-upload" style="color:var(--primary);margin-right:8px;font-size:14px;"></i>Daftar Submission</div>
          <?php if ($pendingInPage > 0): ?>
          <div style="display:flex;align-items:center;gap:10px;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;color:var(--gray-light);font-size:13px;user-select:none;">
              <input type="checkbox" id="selectAllHeader" class="cb-custom" onchange="toggleSelectAll(this.checked)">
              Pilih semua pending (<span id="pendingPageCount"><?= $pendingInPage ?></span>)
            </label>
          </div>
          <?php endif; ?>
        </div>

        <?php if (empty($submissions)): ?>
        <div style="padding:60px;text-align:center;color:var(--gray-light);">
          <i class="fa fa-inbox" style="font-size:36px;display:block;margin-bottom:12px;opacity:0.3;"></i>
          Tidak ada submission ditemukan.
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
          <table class="table-custom" id="submissionTable">
            <thead>
              <tr>
                <th class="th-cb">
                  <input type="checkbox" id="selectAllTh" class="cb-custom"
                         title="Pilih semua pending di halaman ini"
                         onchange="toggleSelectAll(this.checked)">
                </th>
                <th>Peserta</th>
                <th>Jarak</th>
                <th>Waktu</th>
                <th>Bukti</th>
                <th>Tgl Lari</th>
                <th>Catatan</th>
                <th>Status</th>
                <th>Admin Note</th>
                <th>Reviewer</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($submissions as $sub):
                $isPending = $sub['status'] === 'pending';
              ?>
              <tr id="row-<?= $sub['id'] ?>" class="<?= $isPending ? 'pending-row' : '' ?>">

                <!-- Checkbox (hanya pending) -->
                <td class="td-cb">
                  <?php if ($isPending): ?>
                  <input type="checkbox" class="cb-custom row-cb"
                         value="<?= $sub['id'] ?>"
                         data-id="<?= $sub['id'] ?>"
                         onchange="onRowCheck(this)">
                  <?php else: ?>
                  <span style="display:block;width:17px;height:17px;border-radius:4px;background:rgba(255,255,255,0.04);border:1px solid var(--border);margin:0 auto;"></span>
                  <?php endif; ?>
                </td>

                <!-- Peserta -->
                <td>
                  <div class="cell-name"><?= sanitize($sub['user_name']) ?></div>
                  <div class="cell-sub"><?= sanitize($sub['email']) ?></div>
                </td>

                <!-- Jarak + edit -->
                <td>
                  <div style="font-weight:700;color:var(--primary);"><?= number_format($sub['distance_km'], 2) ?> km</div>
                  <?php if ($isPending): ?>
                  <input type="number" id="km-<?= $sub['id'] ?>" value="<?= $sub['distance_km'] ?>"
                         step="0.01" min="0.1" max="30"
                         title="Edit jarak sebelum approve"
                         style="width:76px;background:var(--dark-4);border:1px solid var(--border);color:#fff;
                                padding:4px 8px;border-radius:6px;font-size:12px;margin-top:4px;">
                  <?php endif; ?>
                </td>

                <!-- Waktu -->
                <td style="font-size:13px;color:var(--gray-light);white-space:nowrap;">
                  <?= !empty($sub['run_time']) ? '<i class="fa fa-clock" style="color:var(--primary);margin-right:4px;"></i>' . substr($sub['run_time'], 0, 5) : '—' ?>
                </td>

                <!-- Bukti -->
                <td>
                  <img src="<?= UPLOAD_URL . sanitize($sub['evidence_path']) ?>" alt="Bukti"
                       class="evidence-thumb"
                       onerror="this.style.opacity='0.3';this.title='File tidak ditemukan';"
                       onclick="openLightbox('<?= UPLOAD_URL . sanitize($sub['evidence_path']) ?>')">
                </td>

                <td style="font-size:13px;white-space:nowrap;"><?= date('d M Y', strtotime($sub['run_date'])) ?></td>
                <td style="color:var(--gray-light);font-size:12px;max-width:120px;"><?= $sub['notes'] ? sanitize(substr($sub['notes'],0,60)) : '-' ?></td>
                <td><span class="status-badge badge-<?= $sub['status'] ?>"><?= ucfirst($sub['status']) ?></span></td>
                <td style="color:var(--gray-light);font-size:12px;max-width:150px;"><?= $sub['admin_note'] ? sanitize($sub['admin_note']) : '-' ?></td>
                <td style="font-size:12px;color:var(--gray-light);"><?= $sub['reviewer_name'] ? sanitize($sub['reviewer_name']) : '-' ?></td>

                <!-- Aksi individual -->
                <td>
                  <?php if ($isPending): ?>
                  <div class="d-flex gap-1" style="flex-wrap:wrap;">
                    <button onclick="approveOne(<?= $sub['id'] ?>)" class="action-btn action-btn-approve" title="Approve">
                      <i class="fa fa-check"></i>
                    </button>
                    <button onclick="showRejectModal(<?= $sub['id'] ?>)" class="action-btn action-btn-reject" title="Reject">
                      <i class="fa fa-times"></i>
                    </button>
                  </div>
                  <?php else: ?>
                  <span style="color:var(--gray-light);font-size:11px;">
                    <?= $sub['reviewed_at'] ? date('d M H:i', strtotime($sub['reviewed_at'])) : '-' ?>
                  </span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination-custom">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="?page=<?= $i ?>&status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($search) ?>"
             class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
          <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
      </div><!-- /table-container -->
    </div><!-- /page-content -->
  </div>
</div>

<!-- ══════════════════════════════════════════
     MODAL: Reject satu submission
     ══════════════════════════════════════════ -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal-box" style="max-width:440px;">
    <button class="modal-close" onclick="closeModal('rejectModal')">&times;</button>
    <h3 class="modal-title" style="color:var(--danger);">
      <i class="fa fa-times-circle" style="margin-right:8px;"></i>Tolak Submission
    </h3>
    <form method="POST" action="<?= SITE_URL ?>/api/review-submission.php" id="rejectForm">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="submission_id" id="rejectSubId">
      <input type="hidden" name="action" value="reject">
      <input type="hidden" name="redirect" value="<?= SITE_URL ?>/admin/submissions.php?status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>">
      <div class="form-group">
        <label class="form-label">Alasan Penolakan *</label>
        <textarea name="admin_note" class="form-control-custom" rows="4"
                  placeholder="Contoh: Bukti tidak jelas, tanggal tidak sesuai, dll." required></textarea>
      </div>
      <div class="d-flex gap-3">
        <button type="submit" class="btn-danger-custom" style="padding:12px 24px;font-size:14px;border-radius:var(--radius);">
          <i class="fa fa-times"></i> Tolak
        </button>
        <button type="button" onclick="closeModal('rejectModal')" class="btn-outline-custom">Batal</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════
     MODAL: Bulk Reject (banyak sekaligus)
     ══════════════════════════════════════════ -->
<div class="modal-overlay" id="bulkRejectModal">
  <div class="modal-box" style="max-width:480px;">
    <button class="modal-close" onclick="closeModal('bulkRejectModal')">&times;</button>
    <h3 class="modal-title" style="color:var(--danger);">
      <i class="fa fa-times-circle" style="margin-right:8px;"></i>Reject Massal
    </h3>
    <div id="bulkRejectInfo" style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);
         border-radius:var(--radius);padding:12px 16px;margin-bottom:20px;font-size:13px;color:var(--gray-light);">
      <i class="fa fa-info-circle" style="color:var(--danger);margin-right:6px;"></i>
      <span id="bulkRejectDesc">0 submission akan ditolak.</span>
      Alasan yang sama akan dikirim ke semua peserta.
    </div>
    <form method="POST" action="<?= SITE_URL ?>/api/bulk-review-submission.php" id="bulkRejectForm">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="reject">
      <input type="hidden" name="filter_status" value="<?= sanitize($filterStatus) ?>">
      <input type="hidden" name="filter_search" value="<?= sanitize($search) ?>">
      <input type="hidden" name="filter_page"   value="<?= $page ?>">
      <div id="bulkIdsReject"></div><!-- IDs diisi oleh JS -->
      <div class="form-group">
        <label class="form-label">Alasan Penolakan * <span style="color:var(--gray-light);font-weight:400;">(untuk semua yang dipilih)</span></label>
        <textarea name="admin_note" id="bulkRejectNote" class="form-control-custom" rows="4"
                  placeholder="Contoh: Bukti tidak memenuhi syarat — screenshot harus menampilkan jarak dan tanggal dengan jelas."
                  required></textarea>
      </div>
      <div class="d-flex gap-3">
        <button type="submit" id="bulkRejectSubmitBtn"
                class="btn-danger-custom"
                style="flex:1;padding:12px;font-size:14px;border-radius:var(--radius);justify-content:center;display:flex;align-items:center;gap:8px;">
          <i class="fa fa-times-circle"></i> <span id="bulkRejectBtnLabel">Tolak Semua Terpilih</span>
        </button>
        <button type="button" onclick="closeModal('bulkRejectModal')" class="btn-outline-custom" style="padding:12px 20px;">
          Batal
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════
     Modal: Bulk Approve konfirmasi
     ══════════════════════════════════════════ -->
<div class="modal-overlay" id="bulkApproveModal">
  <div class="modal-box" style="max-width:420px;">
    <button class="modal-close" onclick="closeModal('bulkApproveModal')">&times;</button>
    <div style="text-align:center;margin-bottom:24px;">
      <div style="font-size:52px;margin-bottom:12px;">✅</div>
      <h3 style="color:#fff;font-weight:800;font-size:20px;margin-bottom:8px;">Approve Massal</h3>
      <p style="color:var(--gray-light);font-size:14px;" id="bulkApproveDesc">
        Kamu akan menyetujui <strong style="color:var(--primary);">0 submission</strong> sekaligus.<br>
        KM akan ditambahkan ke progres masing-masing peserta.
      </p>
    </div>
    <div style="background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);border-radius:var(--radius);
         padding:12px 16px;margin-bottom:20px;font-size:12px;color:var(--gray-light);">
      <i class="fa fa-info-circle" style="color:var(--success);margin-right:6px;"></i>
      Jarak yang digunakan sesuai nilai di kolom edit masing-masing baris.
    </div>
    <form method="POST" action="<?= SITE_URL ?>/api/bulk-review-submission.php" id="bulkApproveForm">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="approve">
      <input type="hidden" name="filter_status" value="<?= sanitize($filterStatus) ?>">
      <input type="hidden" name="filter_search" value="<?= sanitize($search) ?>">
      <input type="hidden" name="filter_page"   value="<?= $page ?>">
      <div id="bulkIdsApprove"></div><!-- IDs diisi oleh JS -->
      <div class="d-flex gap-3">
        <button type="submit" id="bulkApproveSubmitBtn"
                class="btn-success-custom"
                style="flex:1;padding:13px;font-size:14px;border-radius:var(--radius);
                       display:flex;align-items:center;justify-content:center;gap:8px;">
          <i class="fa fa-check-double"></i> <span id="bulkApproveBtnLabel">Ya, Approve Semua</span>
        </button>
        <button type="button" onclick="closeModal('bulkApproveModal')" class="btn-outline-custom" style="padding:13px 20px;">
          Batal
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Lightbox -->
<div id="lightbox" class="lightbox">
  <button class="lightbox-close" onclick="closeLightbox()"><i class="fa fa-times"></i></button>
  <img id="lightbox-img" src="" alt="">
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
// ═══════════════════════════════════════════════
//  State
// ═══════════════════════════════════════════════
let selectedIds = new Set();

// ═══════════════════════════════════════════════
//  Checkbox helpers
// ═══════════════════════════════════════════════
function onRowCheck(cb) {
  const id  = parseInt(cb.value);
  const row = document.getElementById('row-' + id);
  if (cb.checked) {
    selectedIds.add(id);
    row.classList.add('row-checked');
  } else {
    selectedIds.delete(id);
    row.classList.remove('row-checked');
  }
  syncUI();
}

function toggleSelectAll(checked) {
  // Sinkronkan kedua checkbox "select all"
  document.querySelectorAll('#selectAllHeader, #selectAllTh').forEach(el => el.checked = checked);

  document.querySelectorAll('.row-cb').forEach(cb => {
    cb.checked = checked;
    onRowCheck(cb);
  });
}

function clearSelection() {
  selectedIds.clear();
  document.querySelectorAll('.row-cb').forEach(cb => { cb.checked = false; });
  document.querySelectorAll('#selectAllHeader, #selectAllTh').forEach(el => el.checked = false);
  document.querySelectorAll('.pending-row').forEach(row => row.classList.remove('row-checked'));
  syncUI();
}

function syncUI() {
  const count   = selectedIds.size;
  const bulkBar = document.getElementById('bulkBar');

  document.getElementById('bulkCount').textContent = count;
  if (count > 0) { bulkBar.style.display = 'flex'; bulkBar.classList.add('visible'); }
  else { bulkBar.style.display = 'none'; bulkBar.classList.remove('visible'); }

  // Sinkron state checkbox header
  const allCbs   = document.querySelectorAll('.row-cb');
  const allCheck = allCbs.length > 0 && [...allCbs].every(c => c.checked);
  document.querySelectorAll('#selectAllHeader, #selectAllTh').forEach(el => {
    el.checked       = allCheck;
    el.indeterminate = count > 0 && !allCheck;
  });
}

// ═══════════════════════════════════════════════
//  Approve satu item (individual)
// ═══════════════════════════════════════════════
function approveOne(id) {
  const km = document.getElementById('km-' + id)?.value ?? null;
  if (!confirm('Setujui submission ini?')) return;
  const f = document.createElement('form');
  f.method = 'POST';
  f.action = '<?= SITE_URL ?>/api/review-submission.php';
  f.innerHTML = `
    <input name="csrf_token" value="<?= $csrf ?>">
    <input name="submission_id" value="${id}">
    <input name="action" value="approve">
    <input name="redirect" value="<?= SITE_URL ?>/admin/submissions.php?status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>">
    ${km ? '<input name="distance_km" value="' + km + '">' : ''}
  `;
  document.body.appendChild(f);
  f.submit();
}

// ═══════════════════════════════════════════════
//  Reject satu item (individual)
// ═══════════════════════════════════════════════
function showRejectModal(id) {
  document.getElementById('rejectSubId').value = id;
  openModal('rejectModal');
}

// ═══════════════════════════════════════════════
//  Bulk Approve → buka modal konfirmasi
// ═══════════════════════════════════════════════
function bulkApprove() {
  if (selectedIds.size === 0) return;
  const n = selectedIds.size;

  // Tulis deskripsi
  document.getElementById('bulkApproveDesc').innerHTML =
    `Kamu akan menyetujui <strong style="color:var(--primary);">${n} submission</strong> sekaligus.<br>
     KM akan ditambahkan ke progres masing-masing peserta.`;
  document.getElementById('bulkApproveBtnLabel').textContent = `Ya, Approve ${n} Submission`;

  // Isi hidden inputs IDs
  const container = document.getElementById('bulkIdsApprove');
  container.innerHTML = '';
  selectedIds.forEach(id => {
    const inp    = document.createElement('input');
    inp.type     = 'hidden';
    inp.name     = 'ids[]';
    inp.value    = id;
    container.appendChild(inp);
  });

  openModal('bulkApproveModal');
}

// ═══════════════════════════════════════════════
//  Bulk Reject → buka modal alasan
// ═══════════════════════════════════════════════
document.querySelector('#bulkBar button[onclick="openModal(\'bulkRejectModal\')"]')
  ?.addEventListener('click', prepareBulkReject);

function prepareBulkReject() {
  if (selectedIds.size === 0) return;
  const n = selectedIds.size;

  document.getElementById('bulkRejectDesc').textContent = `${n} submission akan ditolak.`;
  document.getElementById('bulkRejectBtnLabel').textContent = `Tolak ${n} Submission`;

  // Isi hidden inputs IDs
  const container = document.getElementById('bulkIdsReject');
  container.innerHTML = '';
  selectedIds.forEach(id => {
    const inp    = document.createElement('input');
    inp.type     = 'hidden';
    inp.name     = 'ids[]';
    inp.value    = id;
    container.appendChild(inp);
  });
}

// Pastikan IDs juga terisi saat modal dibuka lewat onclick di HTML
document.getElementById('bulkRejectModal').addEventListener('click', function(e) {
  if (e.target === this) return; // klik overlay → tutup
});

// Override openModal untuk bulk reject agar isi IDs dulu
const _origOpenModal = window.openModal;
window.openModal = function(id) {
  if (id === 'bulkRejectModal') prepareBulkReject();
  _origOpenModal(id);
};

// Loading state saat submit bulk
document.getElementById('bulkApproveForm')?.addEventListener('submit', function() {
  const btn = document.getElementById('bulkApproveSubmitBtn');
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memproses...';
  btn.disabled  = true;
});
document.getElementById('bulkRejectForm')?.addEventListener('submit', function() {
  const btn = document.getElementById('bulkRejectSubmitBtn');
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memproses...';
  btn.disabled  = true;
});
</script>
</body>
</html>
