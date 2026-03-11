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
/* ── Dashboard compact overrides ── */
.page-content { padding: 18px 20px !important; }
.cc { background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px; }
.cc-title { font-size:11px;font-weight:700;color:var(--gray-light);text-transform:uppercase;letter-spacing:.6px;margin-bottom:12px;display:flex;align-items:center;gap:6px; }
.cc-title i { color:var(--primary);font-size:11px; }
/* KPI strip */
.kpi-card { background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:12px 14px;display:flex;align-items:center;gap:12px; }
.kpi-icon { width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0; }
.kpi-val { font-size:20px;font-weight:800;color:#fff;line-height:1; }
.kpi-lbl { font-size:10px;color:var(--gray-light);margin-top:2px; }
/* Leaderboard */
.lr { display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid rgba(255,255,255,0.04); }
.lr:last-child { border-bottom:none; }
.lrank { width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;flex-shrink:0; }
.rank-1{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;}
.rank-2{background:linear-gradient(135deg,#9ca3af,#6b7280);color:#fff;}
.rank-3{background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;}
.rank-o{background:rgba(255,255,255,0.06);color:var(--gray-light);}
/* Pending table compact */
.tbl-scroll { overflow-y:auto;max-height:300px; }
.tbl-scroll::-webkit-scrollbar{width:4px}
.tbl-scroll::-webkit-scrollbar-track{background:transparent}
.tbl-scroll::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.1);border-radius:4px}
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
      <div class="alert-custom alert-<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'warning' ? 'warning' : 'danger') ?>" style="margin-bottom:14px;">
        <i class="fa fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= $flash['message'] ?>
      </div>
      <?php endif; ?>

      <?php
      $revTotal  = max(1, (int)$revenueStats['total']);
      $paidPct   = round((int)$revenueStats['paid_count']   / $revTotal * 100);
      $manualPct = round((int)$revenueStats['manual_count'] / $revTotal * 100);
      $unpaidPct = 100 - $paidPct - $manualPct;
      $totalSubs = $approvedSubs + $rejectedSubs + $pendingSubs;
      $dayColor  = $eventDaysLeft <= 7 ? '#ef4444' : ($eventDaysLeft <= 14 ? '#f59e0b' : '#f97316');
      ?>

      <!-- ══ ROW 1: 6 KPI TILES ══ -->
      <div class="row g-2 mb-3">
        <div class="col-6 col-md-4 col-xl-2">
          <div class="kpi-card">
            <div class="kpi-icon" style="background:rgba(59,130,246,0.15);color:#60a5fa;"><i class="fa fa-users"></i></div>
            <div><div class="kpi-val"><?= $totalRegistrations ?></div><div class="kpi-lbl">Peserta</div></div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <div class="kpi-card">
            <div class="kpi-icon" style="background:rgba(249,115,22,0.15);color:#f97316;"><i class="fa fa-trophy"></i></div>
            <div><div class="kpi-val"><?= $totalFinishers ?></div><div class="kpi-lbl">Finisher</div></div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <div class="kpi-card">
            <div class="kpi-icon" style="background:rgba(34,197,94,0.15);color:#22c55e;"><i class="fa fa-running"></i></div>
            <div><div class="kpi-val"><?= number_format((float)$totalKmApproved,0) ?></div><div class="kpi-lbl">Total KM</div></div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <div class="kpi-card">
            <div class="kpi-icon" style="background:rgba(249,115,22,0.15);color:#f97316;"><i class="fa fa-wallet"></i></div>
            <div><div class="kpi-val" style="font-size:14px;margin-top:1px;">Rp <?= number_format(((int)$revenueStats['rev_10k']+(int)$revenueStats['rev_21k'])/1000,0)?>rb</div><div class="kpi-lbl">Revenue</div></div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <div class="kpi-card">
            <div class="kpi-icon" style="background:rgba(245,158,11,0.15);color:#f59e0b;"><i class="fa fa-clock"></i></div>
            <div><div class="kpi-val" style="color:<?= $pendingSubs > 0 ? '#f59e0b' : '#22c55e' ?>;"><?= $pendingSubs ?></div><div class="kpi-lbl">Pending Review</div></div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <div class="kpi-card">
            <div class="kpi-icon" style="background:rgba(<?= $eventDaysLeft<=7 ? '239,68,68' : '249,115,22' ?>,0.15);color:<?= $dayColor ?>;"><i class="fa fa-calendar-alt"></i></div>
            <div><div class="kpi-val" style="color:<?= $dayColor ?>;"><?= $eventDaysLeft ?></div><div class="kpi-lbl">Hari Tersisa</div></div>
          </div>
        </div>
      </div>

      <!-- ══ ROW 2: ANALYTICS 4-COL ══ -->
      <div class="row g-3 mb-3">

        <!-- Col A: Revenue + Payment bar + Event progress -->
        <div class="col-md-6 col-lg-3">
          <div class="cc h-100" style="display:flex;flex-direction:column;gap:12px;">
            <div class="cc-title"><i class="fa fa-wallet"></i> Revenue</div>
            <!-- Revenue numbers -->
            <div style="display:flex;gap:8px;">
              <div style="flex:1;background:rgba(34,197,94,0.07);border:1px solid rgba(34,197,94,0.15);border-radius:8px;padding:10px;text-align:center;">
                <div style="font-size:10px;color:var(--gray-light);margin-bottom:3px;">10K</div>
                <div style="font-size:14px;font-weight:800;color:#22c55e;">Rp<?= number_format((int)$revenueStats['rev_10k']/1000,0)?>rb</div>
                <div style="font-size:10px;color:var(--gray-light);"><?= (int)$revenueStats['count_10k'] ?> org</div>
              </div>
              <div style="flex:1;background:rgba(59,130,246,0.07);border:1px solid rgba(59,130,246,0.15);border-radius:8px;padding:10px;text-align:center;">
                <div style="font-size:10px;color:var(--gray-light);margin-bottom:3px;">21K</div>
                <div style="font-size:14px;font-weight:800;color:#60a5fa;">Rp<?= number_format((int)$revenueStats['rev_21k']/1000,0)?>rb</div>
                <div style="font-size:10px;color:var(--gray-light);"><?= (int)$revenueStats['count_21k'] ?> org</div>
              </div>
            </div>
            <!-- Payment legend + bar -->
            <div>
              <div style="display:flex;justify-content:space-between;margin-bottom:6px;flex-wrap:wrap;gap:4px;">
                <?php foreach([['#22c55e','Lunas',(int)$revenueStats['paid_count']],['#60a5fa','Manual',(int)$revenueStats['manual_count']],['#ef4444','Belum',(int)$revenueStats['unpaid_count']]] as [$c,$l,$n]): ?>
                <div style="display:flex;align-items:center;gap:4px;font-size:10px;color:var(--gray-light);">
                  <span style="width:7px;height:7px;border-radius:50%;background:<?=$c?>;display:inline-block;flex-shrink:0;"></span><?=$l?> <strong style="color:#fff;"><?=$n?></strong>
                </div>
                <?php endforeach; ?>
              </div>
              <div style="height:6px;border-radius:100px;overflow:hidden;background:rgba(255,255,255,0.05);">
                <div style="height:100%;display:flex;">
                  <div style="width:<?= $paidPct ?>%;background:#22c55e;"></div>
                  <div style="width:<?= $manualPct ?>%;background:#60a5fa;"></div>
                  <div style="width:<?= $unpaidPct ?>%;background:#ef4444;"></div>
                </div>
              </div>
            </div>
            <!-- Event progress -->
            <?php if ($event): ?>
            <div style="margin-top:auto;padding-top:10px;border-top:1px solid var(--border);">
              <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--gray-light);margin-bottom:5px;">
                <span><i class="fa fa-calendar-alt" style="margin-right:3px;"></i>Progress Event</span>
                <span style="color:<?=$dayColor?>;font-weight:700;"><?= $eventProgress ?>%</span>
              </div>
              <div style="height:5px;border-radius:100px;overflow:hidden;background:rgba(255,255,255,0.05);">
                <div style="height:100%;width:<?= $eventProgress ?>%;background:linear-gradient(90deg,var(--primary),#fb923c);border-radius:100px;"></div>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:9px;color:var(--gray-light);margin-top:4px;">
                <span><?= date('d M', strtotime($event['start_date'])) ?></span>
                <span><?= date('d M', strtotime($event['end_date'])) ?></span>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Col B: 2 Donuts stacked -->
        <div class="col-md-6 col-lg-3">
          <div class="cc h-100" style="display:flex;flex-direction:column;gap:16px;">
            <!-- Donut Kategori -->
            <div>
              <div class="cc-title"><i class="fa fa-running"></i> Kategori</div>
              <div style="display:flex;align-items:center;gap:12px;">
                <div style="position:relative;width:90px;height:90px;flex-shrink:0;">
                  <canvas id="chartKategori"></canvas>
                  <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none;">
                    <div style="font-size:16px;font-weight:800;color:#fff;"><?= (int)$revenueStats['total'] ?></div>
                    <div style="font-size:9px;color:var(--gray-light);">total</div>
                  </div>
                </div>
                <div style="flex:1;">
                  <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                    <span style="width:8px;height:8px;border-radius:50%;background:#f97316;flex-shrink:0;"></span>
                    <span style="font-size:12px;color:var(--gray-light);">10K</span>
                    <strong style="color:#f97316;font-size:14px;margin-left:auto;"><?= (int)$revenueStats['count_10k'] ?></strong>
                  </div>
                  <div style="display:flex;align-items:center;gap:6px;">
                    <span style="width:8px;height:8px;border-radius:50%;background:#3b82f6;flex-shrink:0;"></span>
                    <span style="font-size:12px;color:var(--gray-light);">21K</span>
                    <strong style="color:#60a5fa;font-size:14px;margin-left:auto;"><?= (int)$revenueStats['count_21k'] ?></strong>
                  </div>
                </div>
              </div>
            </div>
            <div style="border-top:1px solid var(--border);padding-top:14px;">
              <div class="cc-title"><i class="fa fa-credit-card"></i> Status Akun</div>
              <div style="display:flex;align-items:center;gap:12px;">
                <div style="position:relative;width:90px;height:90px;flex-shrink:0;">
                  <canvas id="chartStatusAkun"></canvas>
                </div>
                <div style="flex:1;">
                  <?php foreach([['#22c55e','Lunas',(int)$revenueStats['paid_count']],['#60a5fa','Manual',(int)$revenueStats['manual_count']],['#ef4444','Belum',(int)$revenueStats['unpaid_count']]] as [$c,$l,$n]): ?>
                  <div style="display:flex;align-items:center;gap:6px;margin-bottom:5px;">
                    <span style="width:7px;height:7px;border-radius:50%;background:<?=$c?>;flex-shrink:0;"></span>
                    <span style="font-size:11px;color:var(--gray-light);"><?=$l?></span>
                    <strong style="color:<?=$c?>;font-size:13px;margin-left:auto;"><?=$n?></strong>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Col C: Activity bars stacked -->
        <div class="col-md-6 col-lg-3">
          <div class="cc h-100" style="display:flex;flex-direction:column;gap:16px;">
            <div>
              <div class="cc-title"><i class="fa fa-user-plus"></i> Registrasi 7 Hari</div>
              <div style="height:115px;"><canvas id="chartReg7"></canvas></div>
            </div>
            <div style="border-top:1px solid var(--border);padding-top:14px;">
              <div class="cc-title"><i class="fa fa-upload"></i> Submission 7 Hari</div>
              <div style="height:115px;"><canvas id="chartSub7"></canvas></div>
            </div>
          </div>
        </div>

        <!-- Col D: Leaderboard + Shipping -->
        <div class="col-md-6 col-lg-3">
          <div class="cc h-100" style="display:flex;flex-direction:column;gap:16px;">
            <div>
              <div class="cc-title"><i class="fa fa-medal"></i> Leaderboard Top 5</div>
              <?php if (empty($leaderboard)): ?>
              <div style="text-align:center;padding:16px;color:var(--gray-light);font-size:12px;">Belum ada data</div>
              <?php else: ?>
              <?php foreach ($leaderboard as $i => $p):
                $pct2 = $p['target_km'] > 0 ? min(100, round($p['total_km'] / $p['target_km'] * 100)) : 0;
                $rankClass = ['rank-1','rank-2','rank-3','rank-o','rank-o'][$i] ?? 'rank-o';
              ?>
              <div class="lr">
                <div class="lrank <?= $rankClass ?>"><?= $i+1 ?></div>
                <div style="flex:1;min-width:0;">
                  <div style="font-size:12px;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    <?= sanitize($p['name']) ?>
                    <?php if ($p['status']==='finisher'): ?><i class="fa fa-trophy" style="color:#f59e0b;font-size:9px;margin-left:3px;"></i><?php endif; ?>
                  </div>
                  <div style="height:2px;background:rgba(255,255,255,0.05);border-radius:100px;margin-top:3px;">
                    <div style="height:100%;width:<?= $pct2 ?>%;background:linear-gradient(90deg,var(--primary),#fb923c);border-radius:100px;"></div>
                  </div>
                </div>
                <div style="font-size:13px;font-weight:800;color:var(--primary);flex-shrink:0;margin-left:8px;"><?= number_format($p['total_km'],1) ?><span style="font-size:9px;color:var(--gray-light);font-weight:400;"> km</span></div>
              </div>
              <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <!-- Shipping mini -->
            <div style="border-top:1px solid var(--border);padding-top:12px;">
              <div class="cc-title"><i class="fa fa-box"></i> Status Jersey</div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
                <?php foreach(['not_ready'=>['Belum','#6b7280'],'preparing'=>['Siap','#f59e0b'],'shipped'=>['Kirim','#3b82f6'],'delivered'=>['Tiba','#22c55e']] as $k=>[$lbl,$col]): ?>
                <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:7px;padding:7px 10px;display:flex;align-items:center;gap:8px;">
                  <span style="width:7px;height:7px;border-radius:50%;background:<?=$col?>;flex-shrink:0;"></span>
                  <div>
                    <div style="font-size:14px;font-weight:800;color:<?=$col?>;"><?= $shippingStats[$k] ?></div>
                    <div style="font-size:9px;color:var(--gray-light);"><?=$lbl?></div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ ROW 3: PROGRESS + OPERATIONS ══ -->
      <div class="row g-3">
        <!-- Progress distribution -->
        <div class="col-md-12 col-lg-4">
          <div class="cc h-100">
            <div class="cc-title"><i class="fa fa-chart-bar"></i> Distribusi Progress</div>
            <div style="height:160px;"><canvas id="chartProgress"></canvas></div>
          </div>
        </div>

        <!-- Pending submissions -->
        <div class="col-md-7 col-lg-5">
          <div class="cc h-100">
            <div class="cc-title" style="margin-bottom:10px;">
              <i class="fa fa-clock" style="color:#f59e0b;"></i> Submission Pending
              <a href="<?= SITE_URL ?>/admin/submissions" style="margin-left:auto;font-size:10px;color:var(--primary);text-decoration:none;">Lihat semua →</a>
            </div>
            <?php if (empty($recentPending)): ?>
            <div style="text-align:center;padding:30px;color:var(--gray-light);">
              <i class="fa fa-check-circle" style="font-size:28px;color:#22c55e;opacity:.5;display:block;margin-bottom:8px;"></i>Semua sudah diproses!
            </div>
            <?php else: ?>
            <div class="tbl-scroll">
              <table class="table-custom" style="font-size:12px;">
                <thead><tr><th>Peserta</th><th>Jarak</th><th>Bukti</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($recentPending as $sub): ?>
                <tr>
                  <td>
                    <div style="font-weight:600;color:#fff;font-size:12px;"><?= sanitize($sub['user_name']) ?></div>
                    <div style="font-size:10px;color:var(--gray-light);"><?= date('d M', strtotime($sub['run_date'])) ?></div>
                  </td>
                  <td style="font-weight:700;color:var(--primary);"><?= number_format($sub['distance_km'],1) ?> km</td>
                  <td><img src="<?= UPLOAD_URL . sanitize($sub['evidence_path']) ?>" alt="" class="evidence-thumb" onclick="openLightbox('<?= UPLOAD_URL . sanitize($sub['evidence_path']) ?>')"></td>
                  <td>
                    <div class="d-flex gap-1">
                      <button onclick="approveSubmission(<?= $sub['id'] ?>)" class="btn-success-custom" style="padding:4px 10px;font-size:11px;"><i class="fa fa-check"></i></button>
                      <button onclick="showRejectModal(<?= $sub['id'] ?>)" class="btn-danger-custom" style="padding:4px 10px;font-size:11px;"><i class="fa fa-times"></i></button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Recent finishers + submission stats -->
        <div class="col-md-5 col-lg-3">
          <div class="cc h-100" style="display:flex;flex-direction:column;gap:14px;">
            <!-- Submission stats compact -->
            <div>
              <div class="cc-title"><i class="fa fa-chart-pie"></i> Submission</div>
              <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                <?php foreach([['#f59e0b','Pending',$pendingSubs],['#22c55e','Approved',$approvedSubs],['#ef4444','Rejected',$rejectedSubs]] as [$c,$l,$n]): ?>
                <div style="text-align:center;">
                  <div style="font-size:18px;font-weight:800;color:<?=$c?>;"><?=$n?></div>
                  <div style="font-size:9px;color:var(--gray-light);"><?=$l?></div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php if ($totalSubs > 0): ?>
              <div style="height:4px;border-radius:100px;overflow:hidden;background:rgba(255,255,255,0.05);">
                <div style="height:100%;display:flex;">
                  <div style="width:<?= round($approvedSubs/$totalSubs*100) ?>%;background:#22c55e;"></div>
                  <div style="width:<?= round($pendingSubs/$totalSubs*100) ?>%;background:#f59e0b;"></div>
                  <div style="width:<?= round($rejectedSubs/$totalSubs*100) ?>%;background:#ef4444;"></div>
                </div>
              </div>
              <?php endif; ?>
            </div>
            <!-- Finisher terbaru -->
            <div style="border-top:1px solid var(--border);padding-top:12px;flex:1;">
              <div class="cc-title"><i class="fa fa-trophy"></i> Finisher Terbaru</div>
              <?php if (empty($recentFinishers)): ?>
              <div style="font-size:11px;color:var(--gray-light);padding:8px 0;">Belum ada finisher</div>
              <?php else: ?>
              <?php foreach (array_slice($recentFinishers, 0, 4) as $f): ?>
              <div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.04);">
                <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--primary),#ea580c);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0;">
                  <?= strtoupper(substr($f['user_name'],0,1)) ?>
                </div>
                <div style="flex:1;min-width:0;">
                  <div style="font-size:12px;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= sanitize($f['user_name']) ?></div>
                  <div style="font-size:10px;color:var(--gray-light);"><?= $f['category'] ?></div>
                </div>
                <i class="fa fa-trophy" style="color:#f59e0b;font-size:11px;flex-shrink:0;"></i>
              </div>
              <?php endforeach; ?>
              <?php endif; ?>
            </div>
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

// ── Bar shared options (compact) ──────────────────────────────────────────────
const barOpts = (tooltipSuffix) => ({
  responsive: true, maintainAspectRatio: false,
  scales: {
    y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 }, maxTicksLimit: 4 },
         grid: { color: 'rgba(255,255,255,0.04)' } },
    x: { grid: { display: false }, ticks: { font: { size: 10 } } }
  },
  plugins: { legend: { display: false }, tooltip: { callbacks: {
    label: ctx => ' ' + ctx.raw + ' ' + tooltipSuffix
  }}}
});

// ── Bar: Registrasi 7 hari ────────────────────────────────────────────────────
new Chart(document.getElementById('chartReg7'), {
  type: 'bar',
  data: {
    labels: [<?= implode(',', array_map(fn($d) => '"'.$d['date'].'"', $reg7days)) ?>],
    datasets: [{ data: [<?= implode(',', array_column($reg7days, 'count')) ?>],
      backgroundColor: 'rgba(249,115,22,0.75)', borderRadius: 5, borderSkipped: false }]
  },
  options: barOpts('pendaftaran')
});

// ── Bar: Submission 7 hari ────────────────────────────────────────────────────
new Chart(document.getElementById('chartSub7'), {
  type: 'bar',
  data: {
    labels: [<?= implode(',', array_map(fn($d) => '"'.$d['date'].'"', $sub7days)) ?>],
    datasets: [{ data: [<?= implode(',', array_column($sub7days, 'count')) ?>],
      backgroundColor: 'rgba(34,197,94,0.75)', borderRadius: 5, borderSkipped: false }]
  },
  options: barOpts('submission')
});

// ── Bar: Distribusi Progress ──────────────────────────────────────────────────
new Chart(document.getElementById('chartProgress'), {
  type: 'bar',
  data: {
    labels: ['0-25%', '25-50%', '50-75%', '75-99%', 'Finish'],
    datasets: [{
      data: [<?= $progressDist['p0'] ?>,<?= $progressDist['p25'] ?>,<?= $progressDist['p50'] ?>,<?= $progressDist['p75'] ?>,<?= $progressDist['p100'] ?>],
      backgroundColor: ['#ef4444','#f59e0b','#3b82f6','#a855f7','#22c55e'],
      borderRadius: 6, borderSkipped: false
    }]
  },
  options: barOpts('peserta')
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

</script>
</body>
</html>
