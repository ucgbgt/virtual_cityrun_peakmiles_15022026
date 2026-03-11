<?php
$pageTitle = 'Admin Dashboard';
$activeNav = 'admin-dashboard';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db    = getDB();
$event = getActiveEvent();

// ── Stat cards lama ──────────────────────────────────────────────────────────
$totalUsers         = $db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$totalRegistrations = $db->query("SELECT COUNT(*) FROM registrations")->fetchColumn();
$totalFinishers     = $db->query("SELECT COUNT(*) FROM registrations WHERE status='finisher'")->fetchColumn();
$pendingSubs        = $db->query("SELECT COUNT(*) FROM run_submissions WHERE status='pending'")->fetchColumn();
$approvedSubs       = $db->query("SELECT COUNT(*) FROM run_submissions WHERE status='approved'")->fetchColumn();
$rejectedSubs       = $db->query("SELECT COUNT(*) FROM run_submissions WHERE status='rejected'")->fetchColumn();
$totalKmApproved    = $db->query("SELECT COALESCE(SUM(distance_km),0) FROM run_submissions WHERE status='approved'")->fetchColumn();

// ── Auto-migrate kolom admin_activated jika belum ada ────────────────────────
try {
    $colCheck = $db->query("SHOW COLUMNS FROM registrations LIKE 'admin_activated'");
    if ($colCheck->rowCount() === 0) {
        $db->exec("ALTER TABLE registrations
            ADD COLUMN admin_activated TINYINT(1) NOT NULL DEFAULT 0 AFTER payment_status,
            ADD COLUMN activated_by INT NULL,
            ADD COLUMN activated_at DATETIME NULL,
            ADD COLUMN activation_note VARCHAR(255) NULL");
    }
} catch (Exception $e) { /* abaikan jika gagal */ }

// ── Revenue & status akun ─────────────────────────────────────────────────────
$revenueStats = ['total'=>0,'paid_count'=>0,'manual_count'=>0,'unpaid_count'=>0,
                 'rev_10k'=>0,'rev_21k'=>0,'count_10k'=>0,'count_21k'=>0];
if ($event) {
    try {
        $stmt = $db->prepare("
            SELECT
                COUNT(*)                                                                               AS total,
                SUM(CASE WHEN r.payment_status='paid' THEN 1 ELSE 0 END)                              AS paid_count,
                SUM(CASE WHEN r.admin_activated=1 AND r.payment_status!='paid' THEN 1 ELSE 0 END)     AS manual_count,
                SUM(CASE WHEN r.payment_status='unpaid'
                         AND (r.admin_activated IS NULL OR r.admin_activated=0) THEN 1 ELSE 0 END)   AS unpaid_count,
                SUM(CASE WHEN r.payment_status='paid' AND r.category='10K'
                         THEN COALESCE(e.fee_10k,0) ELSE 0 END)                                       AS rev_10k,
                SUM(CASE WHEN r.payment_status='paid' AND r.category='21K'
                         THEN COALESCE(e.fee_21k,0) ELSE 0 END)                                       AS rev_21k,
                SUM(CASE WHEN r.category='10K' THEN 1 ELSE 0 END)                                     AS count_10k,
                SUM(CASE WHEN r.category='21K' THEN 1 ELSE 0 END)                                     AS count_21k
            FROM registrations r
            JOIN events e ON r.event_id = e.id
            WHERE r.event_id = ?
        ");
        $stmt->execute([$event['id']]);
        $revenueStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: $revenueStats;
    } catch (Exception $e) { /* kolom belum ada, gunakan default 0 */ }
}

// ── 7-hari: registrasi ────────────────────────────────────────────────────────
$reg7days = [];
if ($event) {
    $stmt = $db->prepare("SELECT DATE(registered_at) AS d, COUNT(*) AS c
        FROM registrations WHERE event_id=? AND registered_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(registered_at)");
    $stmt->execute([$event['id']]);
    $byDay = [];
    foreach ($stmt->fetchAll() as $r) $byDay[$r['d']] = (int)$r['c'];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $reg7days[] = ['date' => date('d/m', strtotime($d)), 'count' => $byDay[$d] ?? 0];
    }
}

// ── 7-hari: submissions ───────────────────────────────────────────────────────
$sub7days = [];
if ($event) {
    $stmt = $db->prepare("SELECT DATE(created_at) AS d, COUNT(*) AS c
        FROM run_submissions WHERE event_id=? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(created_at)");
    $stmt->execute([$event['id']]);
    $byDay = [];
    foreach ($stmt->fetchAll() as $r) $byDay[$r['d']] = (int)$r['c'];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $sub7days[] = ['date' => date('d/m', strtotime($d)), 'count' => $byDay[$d] ?? 0];
    }
}

// ── Distribusi progress peserta ───────────────────────────────────────────────
$progressDist = ['p0' => 0, 'p25' => 0, 'p50' => 0, 'p75' => 0, 'p100' => 0];
if ($event) {
    try {
        $stmt = $db->prepare("
            SELECT r.target_km, r.status, COALESCE(SUM(rs.distance_km),0) AS total_km
            FROM registrations r
            LEFT JOIN run_submissions rs
                ON rs.user_id=r.user_id AND rs.event_id=r.event_id AND rs.status='approved'
            WHERE r.event_id=?
            GROUP BY r.id, r.target_km, r.status
        ");
        $stmt->execute([$event['id']]);
        foreach ($stmt->fetchAll() as $p) {
            if ($p['status'] === 'finisher' || ($p['target_km'] > 0 && $p['total_km'] >= $p['target_km'])) {
                $progressDist['p100']++;
            } elseif ($p['target_km'] > 0) {
                $pct = $p['total_km'] / $p['target_km'];
                if      ($pct < 0.25) $progressDist['p0']++;
                elseif  ($pct < 0.50) $progressDist['p25']++;
                elseif  ($pct < 0.75) $progressDist['p50']++;
                else                  $progressDist['p75']++;
            } else {
                $progressDist['p0']++;
            }
        }
    } catch (Exception $e) { /* abaikan */ }
}

// ── Leaderboard top 5 ─────────────────────────────────────────────────────────
$leaderboard = [];
if ($event) {
    try {
        $stmt = $db->prepare("
            SELECT u.name, r.category, r.target_km, r.status,
                COALESCE(SUM(rs.distance_km),0) AS total_km
            FROM registrations r
            JOIN users u ON r.user_id=u.id
            LEFT JOIN run_submissions rs
                ON rs.user_id=r.user_id AND rs.event_id=r.event_id AND rs.status='approved'
            WHERE r.event_id=?
            GROUP BY r.id, u.name, r.category, r.target_km, r.status
            ORDER BY total_km DESC
            LIMIT 5
        ");
        $stmt->execute([$event['id']]);
        $leaderboard = $stmt->fetchAll();
    } catch (Exception $e) { /* abaikan */ }
}

// ── Status pengiriman ─────────────────────────────────────────────────────────
$shippingStats = ['not_ready' => 0, 'preparing' => 0, 'shipped' => 0, 'delivered' => 0];
if ($event) {
    try {
        $stmt = $db->prepare("
            SELECT COALESCE(s.status,'not_ready') AS status, COUNT(*) AS c
            FROM registrations r
            LEFT JOIN shipping s ON s.user_id=r.user_id AND s.event_id=r.event_id
            WHERE r.event_id=?
            GROUP BY COALESCE(s.status,'not_ready')
        ");
        $stmt->execute([$event['id']]);
        foreach ($stmt->fetchAll() as $s) {
            if (isset($shippingStats[$s['status']])) $shippingStats[$s['status']] = (int)$s['c'];
        }
    } catch (Exception $e) { /* abaikan */ }
}

// ── Event countdown & progress ────────────────────────────────────────────────
$eventDaysLeft  = 0;
$eventProgress  = 0;
$eventStarted   = false;
if ($event) {
    $now   = time();
    $start = strtotime($event['start_date']);
    $end   = strtotime($event['end_date']);
    $eventStarted  = $now >= $start;
    $eventDaysLeft = max(0, (int)ceil(($end - $now) / 86400));
    $total         = max(1, $end - $start);
    $elapsed       = max(0, $now - $start);
    $eventProgress = min(100, round($elapsed / $total * 100));
}

// ── Recent data lama ──────────────────────────────────────────────────────────
$recentPending = $db->query("SELECT rs.*, u.name AS user_name, u.email
    FROM run_submissions rs JOIN users u ON rs.user_id=u.id
    WHERE rs.status='pending' ORDER BY rs.created_at DESC LIMIT 8")->fetchAll();

$recentFinishers = $db->query("SELECT r.*, u.name AS user_name
    FROM registrations r JOIN users u ON r.user_id=u.id
    WHERE r.status='finisher' ORDER BY r.finished_at DESC LIMIT 5")->fetchAll();

$events = $db->query("SELECT * FROM events ORDER BY is_active DESC, id DESC")->fetchAll();
$csrf   = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — PeakMiles</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@300;400;500;600;700;800;900&family=Saira:wght@700;800;900&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
.chart-card { background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px; }
.chart-card-title { font-weight:700;color:#fff;font-size:14px;margin-bottom:16px;display:flex;align-items:center;gap:8px; }
.chart-card-title i { color:var(--primary); }
.leader-row { display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border); }
.leader-row:last-child { border-bottom:none; }
.leader-rank { width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0; }
.rank-1 { background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff; }
.rank-2 { background:linear-gradient(135deg,#9ca3af,#6b7280);color:#fff; }
.rank-3 { background:linear-gradient(135deg,#f97316,#ea580c);color:#fff; }
.rank-other { background:rgba(255,255,255,0.06);color:var(--gray-light); }
.revenue-pill { background:rgba(249,115,22,0.1);border:1px solid rgba(249,115,22,0.2);border-radius:10px;padding:14px 16px;text-align:center; }
.countdown-ring { width:90px;height:90px;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center;border:3px solid rgba(249,115,22,0.3);flex-shrink:0; }
</style>
</head>
<body>
<div class="dashboard-layout">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <div class="d-flex align-items-center gap-3">
        <button id="sidebarToggle" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;" class="d-lg-none"><i class="fa fa-bars"></i></button>
        <div>
          <div class="topbar-title">Admin Dashboard</div>
          <div style="font-size:12px;color:var(--gray-light);"><?= $event ? sanitize($event['name']) : 'Tidak ada event aktif' ?></div>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button onclick="openModal('addParticipantModal')" class="btn-outline-custom btn-sm-custom">
          <i class="fa fa-user-plus"></i> Tambah Peserta
        </button>
        <a href="<?= SITE_URL ?>/admin/submissions" class="btn-primary-custom btn-sm-custom">
          <i class="fa fa-clock"></i> <?= $pendingSubs ?> Pending
        </a>
      </div>
    </div>

    <div class="page-content">
      <?php $flash = getFlash(); if ($flash): ?>
      <div class="alert-custom alert-<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'warning' ? 'warning' : 'danger') ?>" style="margin-bottom:20px;">
        <i class="fa fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= $flash['message'] ?>
      </div>
      <?php endif; ?>

      <!-- ═══════════════════ ROW 1: Stat Cards ═══════════════════ -->
      <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(59,130,246,0.15);color:var(--info);"><i class="fa fa-users"></i></div>
            <div class="stat-card-label">Total Peserta</div>
            <div class="stat-card-value counter" data-target="<?= $totalRegistrations ?>"><?= $totalRegistrations ?></div>
            <div class="stat-card-sub"><?= $totalUsers ?> akun terdaftar</div>
          </div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(249,115,22,0.15);color:var(--primary);"><i class="fa fa-trophy"></i></div>
            <div class="stat-card-label">Finisher</div>
            <div class="stat-card-value counter" data-target="<?= $totalFinishers ?>"><?= $totalFinishers ?></div>
            <div class="stat-card-sub"><?= $totalRegistrations > 0 ? round(($totalFinishers/$totalRegistrations)*100) : 0 ?>% dari peserta</div>
          </div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(245,158,11,0.15);color:var(--warning);"><i class="fa fa-clock"></i></div>
            <div class="stat-card-label">Submission Pending</div>
            <div class="stat-card-value counter" data-target="<?= $pendingSubs ?>"><?= $pendingSubs ?></div>
            <div class="stat-card-sub">perlu review</div>
          </div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(34,197,94,0.15);color:var(--success);"><i class="fa fa-running"></i></div>
            <div class="stat-card-label">Total KM Approved</div>
            <div class="stat-card-value"><?= number_format((float)$totalKmApproved, 1) ?></div>
            <div class="stat-card-sub">kilometer total</div>
          </div>
        </div>
      </div>

      <!-- ═══════════════════ ROW 2: Revenue + Countdown ═══════════════════ -->
      <div class="row g-4 mb-4">
        <!-- Revenue Summary -->
        <div class="col-lg-8">
          <div class="chart-card h-100">
            <div class="chart-card-title"><i class="fa fa-wallet"></i> Revenue & Status Pembayaran</div>
            <div class="row g-3 mb-4">
              <div class="col-sm-4">
                <div class="revenue-pill">
                  <div style="font-size:11px;color:var(--gray-light);margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px;">Total Revenue</div>
                  <div style="font-size:22px;font-weight:800;color:var(--primary);">
                    Rp <?= number_format((int)$revenueStats['rev_10k'] + (int)$revenueStats['rev_21k'], 0, ',', '.') ?>
                  </div>
                </div>
              </div>
              <div class="col-sm-4">
                <div style="background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);border-radius:10px;padding:14px 16px;text-align:center;">
                  <div style="font-size:11px;color:var(--gray-light);margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px;">Rev 10K</div>
                  <div style="font-size:18px;font-weight:800;color:#22c55e;">Rp <?= number_format((int)$revenueStats['rev_10k'], 0, ',', '.') ?></div>
                  <div style="font-size:11px;color:var(--gray-light);margin-top:2px;"><?= (int)$revenueStats['count_10k'] ?> peserta</div>
                </div>
              </div>
              <div class="col-sm-4">
                <div style="background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.2);border-radius:10px;padding:14px 16px;text-align:center;">
                  <div style="font-size:11px;color:var(--gray-light);margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px;">Rev 21K</div>
                  <div style="font-size:18px;font-weight:800;color:#60a5fa;">Rp <?= number_format((int)$revenueStats['rev_21k'], 0, ',', '.') ?></div>
                  <div style="font-size:11px;color:var(--gray-light);margin-top:2px;"><?= (int)$revenueStats['count_21k'] ?> peserta</div>
                </div>
              </div>
            </div>
            <!-- Payment status breakdown bar -->
            <?php
            $revTotal   = max(1, (int)$revenueStats['total']);
            $paidPct    = round((int)$revenueStats['paid_count']   / $revTotal * 100);
            $manualPct  = round((int)$revenueStats['manual_count'] / $revTotal * 100);
            $unpaidPct  = 100 - $paidPct - $manualPct;
            ?>
            <div style="display:flex;gap:16px;margin-bottom:10px;flex-wrap:wrap;">
              <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--gray-light);">
                <span style="width:10px;height:10px;border-radius:50%;background:#22c55e;display:inline-block;"></span>
                Lunas <strong style="color:#fff;"><?= (int)$revenueStats['paid_count'] ?></strong>
              </div>
              <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--gray-light);">
                <span style="width:10px;height:10px;border-radius:50%;background:#60a5fa;display:inline-block;"></span>
                Manual Admin <strong style="color:#fff;"><?= (int)$revenueStats['manual_count'] ?></strong>
              </div>
              <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--gray-light);">
                <span style="width:10px;height:10px;border-radius:50%;background:#ef4444;display:inline-block;"></span>
                Belum Bayar <strong style="color:#fff;"><?= (int)$revenueStats['unpaid_count'] ?></strong>
              </div>
            </div>
            <div style="height:10px;border-radius:100px;overflow:hidden;background:rgba(255,255,255,0.05);">
              <div style="height:100%;display:flex;">
                <div style="width:<?= $paidPct ?>%;background:#22c55e;transition:width .8s;"></div>
                <div style="width:<?= $manualPct ?>%;background:#60a5fa;transition:width .8s;"></div>
                <div style="width:<?= $unpaidPct ?>%;background:#ef4444;transition:width .8s;"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Event Countdown -->
        <div class="col-lg-4">
          <div class="chart-card h-100 d-flex flex-column justify-content-between">
            <div class="chart-card-title"><i class="fa fa-calendar-alt"></i> Status Event</div>
            <?php if ($event): ?>
            <div class="d-flex align-items-center gap-16" style="gap:20px;margin-bottom:16px;">
              <div class="countdown-ring" style="border-color:<?= $eventDaysLeft <= 7 ? '#ef4444' : ($eventDaysLeft <= 14 ? '#f59e0b' : 'rgba(249,115,22,0.4)') ?>;">
                <div style="font-size:28px;font-weight:900;color:<?= $eventDaysLeft <= 7 ? '#ef4444' : 'var(--primary)' ?>;line-height:1;"><?= $eventDaysLeft ?></div>
                <div style="font-size:10px;color:var(--gray-light);text-transform:uppercase;">hari lagi</div>
              </div>
              <div style="flex:1;">
                <div style="font-size:13px;font-weight:600;color:#fff;margin-bottom:4px;"><?= sanitize($event['name']) ?></div>
                <div style="font-size:12px;color:var(--gray-light);margin-bottom:2px;">
                  <i class="fa fa-calendar" style="margin-right:4px;"></i><?= date('d M Y', strtotime($event['start_date'])) ?>
                </div>
                <div style="font-size:12px;color:var(--gray-light);">
                  <i class="fa fa-flag-checkered" style="margin-right:4px;"></i><?= date('d M Y', strtotime($event['end_date'])) ?>
                </div>
              </div>
            </div>
            <div>
              <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--gray-light);margin-bottom:6px;">
                <span>Progress Event</span><span style="color:var(--primary);font-weight:700;"><?= $eventProgress ?>%</span>
              </div>
              <div style="height:8px;border-radius:100px;overflow:hidden;background:rgba(255,255,255,0.05);">
                <div style="height:100%;width:<?= $eventProgress ?>%;background:linear-gradient(90deg,var(--primary),#fb923c);transition:width 1s;border-radius:100px;"></div>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--gray-light);margin-top:5px;">
                <span><?= date('d M', strtotime($event['start_date'])) ?></span>
                <span><?= date('d M', strtotime($event['end_date'])) ?></span>
              </div>
            </div>
            <?php else: ?>
            <div style="text-align:center;padding:20px;color:var(--gray-light);">Tidak ada event aktif</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ═══════════════════ ROW 3: Tiga Donut ═══════════════════ -->
      <div class="row g-4 mb-4">
        <!-- Donut: Kategori -->
        <div class="col-md-4">
          <div class="chart-card">
            <div class="chart-card-title"><i class="fa fa-running"></i> Kategori Peserta</div>
            <div style="position:relative;height:180px;display:flex;align-items:center;justify-content:center;">
              <canvas id="chartKategori"></canvas>
              <div style="position:absolute;text-align:center;pointer-events:none;">
                <div style="font-size:22px;font-weight:800;color:#fff;"><?= (int)$revenueStats['total'] ?></div>
                <div style="font-size:11px;color:var(--gray-light);">peserta</div>
              </div>
            </div>
            <div style="display:flex;justify-content:center;gap:20px;margin-top:12px;">
              <div style="text-align:center;">
                <div style="font-size:18px;font-weight:800;color:#f97316;"><?= (int)$revenueStats['count_10k'] ?></div>
                <div style="font-size:11px;color:var(--gray-light);">10K</div>
              </div>
              <div style="text-align:center;">
                <div style="font-size:18px;font-weight:800;color:#60a5fa;"><?= (int)$revenueStats['count_21k'] ?></div>
                <div style="font-size:11px;color:var(--gray-light);">21K</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Donut: Status Akun -->
        <div class="col-md-4">
          <div class="chart-card">
            <div class="chart-card-title"><i class="fa fa-credit-card"></i> Status Akun</div>
            <div style="position:relative;height:180px;display:flex;align-items:center;justify-content:center;">
              <canvas id="chartStatusAkun"></canvas>
            </div>
            <div style="display:flex;justify-content:center;gap:16px;margin-top:12px;flex-wrap:wrap;">
              <div style="text-align:center;">
                <div style="font-size:16px;font-weight:800;color:#22c55e;"><?= (int)$revenueStats['paid_count'] ?></div>
                <div style="font-size:10px;color:var(--gray-light);">Lunas</div>
              </div>
              <div style="text-align:center;">
                <div style="font-size:16px;font-weight:800;color:#60a5fa;"><?= (int)$revenueStats['manual_count'] ?></div>
                <div style="font-size:10px;color:var(--gray-light);">Manual</div>
              </div>
              <div style="text-align:center;">
                <div style="font-size:16px;font-weight:800;color:#ef4444;"><?= (int)$revenueStats['unpaid_count'] ?></div>
                <div style="font-size:10px;color:var(--gray-light);">Belum Bayar</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Donut: Pengiriman -->
        <div class="col-md-4">
          <div class="chart-card">
            <div class="chart-card-title"><i class="fa fa-box"></i> Status Pengiriman</div>
            <div style="position:relative;height:180px;display:flex;align-items:center;justify-content:center;">
              <canvas id="chartShipping"></canvas>
            </div>
            <div style="display:flex;justify-content:center;gap:12px;margin-top:12px;flex-wrap:wrap;">
              <?php
              $shipLabels = ['not_ready'=>['Belum','#6b7280'],'preparing'=>['Disiapkan','#f59e0b'],
                             'shipped'=>['Dikirim','#3b82f6'],'delivered'=>['Terkirim','#22c55e']];
              foreach ($shipLabels as $k => [$lbl, $col]): ?>
              <div style="text-align:center;">
                <div style="font-size:16px;font-weight:800;color:<?= $col ?>;"><?= $shippingStats[$k] ?></div>
                <div style="font-size:10px;color:var(--gray-light);"><?= $lbl ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══════════════════ ROW 4: Bar Charts 7-hari ═══════════════════ -->
      <div class="row g-4 mb-4">
        <div class="col-lg-6">
          <div class="chart-card">
            <div class="chart-card-title"><i class="fa fa-user-plus"></i> Registrasi 7 Hari Terakhir</div>
            <div style="height:200px;"><canvas id="chartReg7"></canvas></div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="chart-card">
            <div class="chart-card-title"><i class="fa fa-upload"></i> Submission 7 Hari Terakhir</div>
            <div style="height:200px;"><canvas id="chartSub7"></canvas></div>
          </div>
        </div>
      </div>

      <!-- ═══════════════════ ROW 5: Progress Dist + Leaderboard ═══════════════════ -->
      <div class="row g-4 mb-4">
        <!-- Progress Distribution -->
        <div class="col-lg-7">
          <div class="chart-card">
            <div class="chart-card-title"><i class="fa fa-chart-bar"></i> Distribusi Progress Peserta</div>
            <div style="height:220px;"><canvas id="chartProgress"></canvas></div>
          </div>
        </div>

        <!-- Leaderboard -->
        <div class="col-lg-5">
          <div class="chart-card h-100">
            <div class="chart-card-title"><i class="fa fa-medal"></i> Leaderboard Top 5</div>
            <?php if (empty($leaderboard)): ?>
            <div style="text-align:center;padding:30px;color:var(--gray-light);font-size:13px;">Belum ada data</div>
            <?php else: ?>
            <?php foreach ($leaderboard as $i => $p):
              $pct = $p['target_km'] > 0 ? min(100, round($p['total_km'] / $p['target_km'] * 100)) : 0;
              $rankClass = $i === 0 ? 'rank-1' : ($i === 1 ? 'rank-2' : ($i === 2 ? 'rank-3' : 'rank-other'));
            ?>
            <div class="leader-row">
              <div class="leader-rank <?= $rankClass ?>"><?= $i+1 ?></div>
              <div style="flex:1;min-width:0;">
                <div style="font-weight:600;color:#fff;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                  <?= sanitize($p['name']) ?>
                  <?php if ($p['status'] === 'finisher'): ?>
                  <i class="fa fa-trophy" style="color:#f59e0b;font-size:10px;margin-left:4px;"></i>
                  <?php endif; ?>
                </div>
                <div style="font-size:11px;color:var(--gray-light);"><?= $p['category'] ?> · <?= $pct ?>%</div>
                <div style="height:3px;background:rgba(255,255,255,0.05);border-radius:100px;margin-top:4px;">
                  <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,var(--primary),#fb923c);border-radius:100px;"></div>
                </div>
              </div>
              <div style="text-align:right;flex-shrink:0;">
                <div style="font-size:15px;font-weight:800;color:var(--primary);"><?= number_format($p['total_km'], 1) ?></div>
                <div style="font-size:10px;color:var(--gray-light);">km</div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ═══════════════════ ROW 6: Submission Status + Pending Table ═══════════════════ -->
      <div class="form-card mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div style="font-weight:700;color:#fff;"><i class="fa fa-chart-bar" style="color:var(--primary);margin-right:8px;"></i>Status Submission</div>
          <a href="<?= SITE_URL ?>/admin/submissions" style="font-size:13px;color:var(--primary);text-decoration:none;">Lihat Semua →</a>
        </div>
        <?php $totalSubs = $approvedSubs + $rejectedSubs + $pendingSubs; ?>
        <div class="row g-3 text-center">
          <div class="col-4">
            <div style="font-size:28px;font-weight:800;color:var(--warning);"><?= $pendingSubs ?></div>
            <div style="font-size:11px;color:var(--gray-light);text-transform:uppercase;">Pending</div>
          </div>
          <div class="col-4">
            <div style="font-size:28px;font-weight:800;color:var(--success);"><?= $approvedSubs ?></div>
            <div style="font-size:11px;color:var(--gray-light);text-transform:uppercase;">Approved</div>
          </div>
          <div class="col-4">
            <div style="font-size:28px;font-weight:800;color:var(--danger);"><?= $rejectedSubs ?></div>
            <div style="font-size:11px;color:var(--gray-light);text-transform:uppercase;">Rejected</div>
          </div>
        </div>
        <?php if ($totalSubs > 0): ?>
        <div class="progress-bar-bg mt-3" style="height:8px;">
          <div style="height:100%;background:linear-gradient(90deg,var(--success) <?= round($approvedSubs/$totalSubs*100) ?>%,var(--warning) <?= round($approvedSubs/$totalSubs*100) ?>% <?= round(($approvedSubs+$pendingSubs)/$totalSubs*100) ?>%,var(--danger) <?= round(($approvedSubs+$pendingSubs)/$totalSubs*100) ?>% 100%);border-radius:100px;"></div>
        </div>
        <?php endif; ?>
      </div>

      <div class="row g-4">
        <!-- Pending Submissions Table -->
        <div class="col-lg-8">
          <div class="table-container">
            <div class="table-header">
              <div class="table-title"><i class="fa fa-clock" style="color:var(--warning);margin-right:6px;"></i>Submission Pending</div>
              <a href="<?= SITE_URL ?>/admin/submissions" style="font-size:13px;color:var(--primary);text-decoration:none;">Semua →</a>
            </div>
            <?php if (empty($recentPending)): ?>
            <div style="padding:40px;text-align:center;color:var(--gray-light);">
              <i class="fa fa-check-circle" style="font-size:36px;margin-bottom:12px;display:block;color:var(--success);opacity:0.5;"></i>
              Semua submission sudah diproses!
            </div>
            <?php else: ?>
            <table class="table-custom">
              <thead>
                <tr><th>Peserta</th><th>Jarak</th><th>Bukti</th><th>Tgl Lari</th><th>Aksi</th></tr>
              </thead>
              <tbody>
                <?php foreach ($recentPending as $sub): ?>
                <tr>
                  <td>
                    <div style="font-weight:600;color:#fff;"><?= sanitize($sub['user_name']) ?></div>
                    <div style="font-size:11px;color:var(--gray-light);"><?= sanitize($sub['email']) ?></div>
                  </td>
                  <td style="font-weight:700;color:var(--primary);"><?= number_format($sub['distance_km'], 2) ?> km</td>
                  <td>
                    <img src="<?= UPLOAD_URL . sanitize($sub['evidence_path']) ?>" alt="Bukti" class="evidence-thumb"
                         onclick="openLightbox('<?= UPLOAD_URL . sanitize($sub['evidence_path']) ?>')">
                  </td>
                  <td style="font-size:13px;color:var(--gray-light);"><?= date('d M Y', strtotime($sub['run_date'])) ?></td>
                  <td>
                    <div class="d-flex gap-2">
                      <button onclick="approveSubmission(<?= $sub['id'] ?>)" class="btn-success-custom" style="padding:6px 12px;font-size:12px;"><i class="fa fa-check"></i></button>
                      <button onclick="showRejectModal(<?= $sub['id'] ?>)" class="btn-danger-custom" style="padding:6px 12px;font-size:12px;"><i class="fa fa-times"></i></button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>
        </div>

        <!-- Recent Finishers -->
        <div class="col-lg-4">
          <div class="table-container">
            <div class="table-header">
              <div class="table-title"><i class="fa fa-trophy" style="color:var(--primary);margin-right:6px;"></i>Finisher Terbaru</div>
            </div>
            <?php if (empty($recentFinishers)): ?>
            <div style="padding:30px;text-align:center;color:var(--gray-light);font-size:13px;">Belum ada finisher</div>
            <?php else: ?>
            <div style="padding:8px;">
              <?php foreach ($recentFinishers as $f): ?>
              <div style="display:flex;align-items:center;gap:12px;padding:12px;border-radius:10px;border-bottom:1px solid var(--border);">
                <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dark));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:#fff;flex-shrink:0;">
                  <?= strtoupper(substr($f['user_name'], 0, 1)) ?>
                </div>
                <div style="flex:1;min-width:0;">
                  <div style="font-weight:600;color:#fff;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= sanitize($f['user_name']) ?></div>
                  <div style="font-size:11px;color:var(--gray-light);"><?= $f['category'] ?> · <?= number_format($f['total_km_approved'] ?? 0, 2) ?> km</div>
                </div>
                <span class="status-badge badge-finisher" style="font-size:10px;flex-shrink:0;"><i class="fa fa-trophy"></i></span>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div><!-- /page-content -->
  </div>
</div>

<!-- ══════════════ MODAL: Tambah Peserta ══════════════ -->
<div class="modal-overlay" id="addParticipantModal">
  <div class="modal-box" style="max-width:520px;">
    <button class="modal-close" onclick="closeModal('addParticipantModal')">&times;</button>
    <h3 class="modal-title"><i class="fa fa-user-plus" style="color:var(--primary);margin-right:10px;"></i>Tambah Peserta Baru</h3>
    <div style="background:rgba(249,115,22,0.08);border:1px solid rgba(249,115,22,0.2);border-radius:var(--radius);padding:12px 16px;margin-bottom:20px;font-size:13px;color:var(--gray-light);">
      <i class="fa fa-info-circle" style="color:var(--primary);margin-right:6px;"></i>
      Akun baru akan dibuat dengan password default <strong style="color:#fff;">User@123</strong>.
      Peserta diminta ganti password setelah login pertama.
    </div>
    <form method="POST" action="<?= SITE_URL ?>/api/add-participant.php" id="addParticipantForm">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <div class="form-group">
        <label class="form-label">Nama Lengkap *</label>
        <input type="text" name="name" class="form-control-custom" required placeholder="Nama lengkap peserta" autocomplete="off">
      </div>
      <div class="form-group">
        <label class="form-label">Email *</label>
        <input type="email" name="email" class="form-control-custom" required placeholder="email@peserta.com" autocomplete="off">
        <div style="font-size:12px;color:var(--gray-light);margin-top:4px;">Jika email sudah terdaftar, peserta akan didaftarkan ke event dengan akun yang ada.</div>
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Event</label>
            <select name="event_id" class="form-control-custom">
              <?php foreach ($events as $ev): ?>
              <option value="<?= $ev['id'] ?>" <?= ($event && $ev['id'] === $event['id']) ? 'selected' : '' ?>>
                <?= sanitize($ev['name']) ?><?= $ev['is_active'] ? ' (Aktif)' : '' ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Kategori</label>
            <div style="display:flex;gap:10px;margin-top:2px;">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 16px;border:1px solid var(--border);border-radius:var(--radius);flex:1;transition:border-color .2s;" id="lbl10k">
                <input type="radio" name="category" value="10K" checked style="accent-color:var(--primary);" onchange="highlightCat()">
                <span style="color:#fff;font-weight:600;">10K</span>
              </label>
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 16px;border:1px solid var(--border);border-radius:var(--radius);flex:1;transition:border-color .2s;" id="lbl21k">
                <input type="radio" name="category" value="21K" style="accent-color:var(--primary);" onchange="highlightCat()">
                <span style="color:#fff;font-weight:600;">21K</span>
              </label>
            </div>
          </div>
        </div>
      </div>
      <div class="d-flex gap-3 mt-4">
        <button type="submit" id="addParticipantBtn" class="btn-primary-custom" style="flex:1;justify-content:center;padding:13px;">
          <i class="fa fa-user-plus"></i> Tambah Peserta
        </button>
        <button type="button" onclick="closeModal('addParticipantModal')" class="btn-outline-custom" style="padding:13px 20px;">Batal</button>
      </div>
    </form>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal-box" style="max-width:400px;">
    <button class="modal-close" onclick="closeModal('rejectModal')">&times;</button>
    <h3 class="modal-title" style="color:var(--danger);"><i class="fa fa-times-circle" style="margin-right:8px;"></i>Tolak Submission</h3>
    <form method="POST" action="<?= SITE_URL ?>/api/review-submission.php" id="rejectForm">
      <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
      <input type="hidden" name="submission_id" id="rejectSubId">
      <input type="hidden" name="action" value="reject">
      <div class="form-group">
        <label class="form-label">Alasan Penolakan *</label>
        <textarea name="admin_note" class="form-control-custom" rows="3" placeholder="Tuliskan alasan penolakan..." required></textarea>
      </div>
      <div class="d-flex gap-3">
        <button type="submit" class="btn-danger-custom" style="padding:12px 24px;font-size:14px;border-radius:var(--radius);"><i class="fa fa-times"></i> Tolak</button>
        <button type="button" onclick="closeModal('rejectModal')" class="btn-outline-custom">Batal</button>
      </div>
    </form>
  </div>
</div>

<div id="lightbox" class="lightbox">
  <button class="lightbox-close" onclick="closeLightbox()"><i class="fa fa-times"></i></button>
  <img id="lightbox-img" src="" alt="">
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
// ── Chart.js global defaults ──────────────────────────────────────────────────
Chart.defaults.color          = '#9ca3af';
Chart.defaults.borderColor    = 'rgba(255,255,255,0.06)';
Chart.defaults.font.family    = "'Fira Sans', sans-serif";
Chart.defaults.font.size      = 12;
Chart.defaults.plugins.legend.display = false;

const donutOpts = {
  cutout: '72%',
  plugins: { tooltip: { callbacks: {
    label: ctx => ' ' + ctx.label + ': ' + ctx.raw + ' peserta'
  }}},
  animation: { animateRotate: true, duration: 900 }
};

// ── Donut: Kategori ───────────────────────────────────────────────────────────
new Chart(document.getElementById('chartKategori'), {
  type: 'doughnut',
  data: {
    labels: ['10K', '21K'],
    datasets: [{ data: [<?= (int)$revenueStats['count_10k'] ?>, <?= (int)$revenueStats['count_21k'] ?>],
      backgroundColor: ['#f97316','#3b82f6'],
      borderWidth: 0, hoverOffset: 6 }]
  },
  options: donutOpts
});

// ── Donut: Status Akun ────────────────────────────────────────────────────────
new Chart(document.getElementById('chartStatusAkun'), {
  type: 'doughnut',
  data: {
    labels: ['Lunas','Manual Admin','Belum Bayar'],
    datasets: [{ data: [<?= (int)$revenueStats['paid_count'] ?>,<?= (int)$revenueStats['manual_count'] ?>,<?= (int)$revenueStats['unpaid_count'] ?>],
      backgroundColor: ['#22c55e','#3b82f6','#ef4444'],
      borderWidth: 0, hoverOffset: 6 }]
  },
  options: donutOpts
});

// ── Donut: Pengiriman ─────────────────────────────────────────────────────────
new Chart(document.getElementById('chartShipping'), {
  type: 'doughnut',
  data: {
    labels: ['Belum','Disiapkan','Dikirim','Terkirim'],
    datasets: [{ data: [<?= $shippingStats['not_ready'] ?>,<?= $shippingStats['preparing'] ?>,<?= $shippingStats['shipped'] ?>,<?= $shippingStats['delivered'] ?>],
      backgroundColor: ['#6b7280','#f59e0b','#3b82f6','#22c55e'],
      borderWidth: 0, hoverOffset: 6 }]
  },
  options: donutOpts
});

// ── Bar: Registrasi 7 hari ────────────────────────────────────────────────────
new Chart(document.getElementById('chartReg7'), {
  type: 'bar',
  data: {
    labels: [<?= implode(',', array_map(fn($d) => '"'.$d['date'].'"', $reg7days)) ?>],
    datasets: [{
      label: 'Registrasi',
      data: [<?= implode(',', array_column($reg7days, 'count')) ?>],
      backgroundColor: 'rgba(249,115,22,0.7)',
      borderRadius: 6, borderSkipped: false
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    scales: {
      y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(255,255,255,0.04)' } },
      x: { grid: { display: false } }
    },
    plugins: { legend: { display: false }, tooltip: { callbacks: {
      label: ctx => ' ' + ctx.raw + ' pendaftaran'
    }}}
  }
});

// ── Bar: Submission 7 hari ────────────────────────────────────────────────────
new Chart(document.getElementById('chartSub7'), {
  type: 'bar',
  data: {
    labels: [<?= implode(',', array_map(fn($d) => '"'.$d['date'].'"', $sub7days)) ?>],
    datasets: [{
      label: 'Submission',
      data: [<?= implode(',', array_column($sub7days, 'count')) ?>],
      backgroundColor: 'rgba(34,197,94,0.7)',
      borderRadius: 6, borderSkipped: false
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    scales: {
      y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(255,255,255,0.04)' } },
      x: { grid: { display: false } }
    },
    plugins: { legend: { display: false }, tooltip: { callbacks: {
      label: ctx => ' ' + ctx.raw + ' submission'
    }}}
  }
});

// ── Bar: Distribusi Progress ──────────────────────────────────────────────────
new Chart(document.getElementById('chartProgress'), {
  type: 'bar',
  data: {
    labels: ['0 – 25%', '25 – 50%', '50 – 75%', '75 – 99%', 'Finisher 🏆'],
    datasets: [{
      label: 'Peserta',
      data: [<?= $progressDist['p0'] ?>,<?= $progressDist['p25'] ?>,<?= $progressDist['p50'] ?>,<?= $progressDist['p75'] ?>,<?= $progressDist['p100'] ?>],
      backgroundColor: ['#ef4444','#f59e0b','#3b82f6','#a855f7','#22c55e'],
      borderRadius: 8, borderSkipped: false
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    scales: {
      y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(255,255,255,0.04)' } },
      x: { grid: { display: false } }
    },
    plugins: { legend: { display: false }, tooltip: { callbacks: {
      label: ctx => ' ' + ctx.raw + ' peserta'
    }}}
  }
});

// ── Existing helpers ──────────────────────────────────────────────────────────
function approveSubmission(id) {
  if (!confirm('Setujui submission ini?')) return;
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = '<?= SITE_URL ?>/api/review-submission.php';
  form.innerHTML = `<input name="csrf_token" value="<?= generateCSRFToken() ?>">
    <input name="submission_id" value="${id}"><input name="action" value="approve">`;
  document.body.appendChild(form);
  form.submit();
}
function showRejectModal(id) {
  document.getElementById('rejectSubId').value = id;
  openModal('rejectModal');
}

function highlightCat() {
  const is10k = document.querySelector('[name="category"][value="10K"]').checked;
  document.getElementById('lbl10k').style.borderColor = is10k  ? 'var(--primary)' : 'var(--border)';
  document.getElementById('lbl21k').style.borderColor = !is10k ? 'var(--primary)' : 'var(--border)';
}
highlightCat();

document.getElementById('addParticipantForm')?.addEventListener('submit', function() {
  const btn = document.getElementById('addParticipantBtn');
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memproses...';
  btn.disabled = true;
});

document.addEventListener('DOMContentLoaded', function() {
  const toggle = document.getElementById('sidebarToggle');
  if (toggle) toggle.style.display = 'block';
});
</script>
</body>
</html>
