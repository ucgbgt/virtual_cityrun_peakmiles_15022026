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
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — PeakMiles</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@300;400;500;600;700;800;900&family=Saira:wght@700;800;900&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
/* ── Dashboard Clean Overrides ── */
.page-content { padding: 24px !important; }

/* Card base */
.db-card {
  background: var(--dark-2);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 20px;
  transition: border-color 0.2s;
}
.db-card:hover { border-color: rgba(255,255,255,0.12); }

/* Section titles */
.db-section-title {
  font-size: 11px;
  font-weight: 600;
  color: var(--gray);
  text-transform: uppercase;
  letter-spacing: 0.8px;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.db-section-title i { color: var(--gray); font-size: 11px; }

/* KPI grid */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 12px;
  margin-bottom: 24px;
}
.kpi-item {
  background: var(--dark-2);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 18px 16px;
  transition: border-color 0.2s;
}
.kpi-item:hover { border-color: rgba(255,255,255,0.12); }
.kpi-label {
  font-size: 11px;
  color: var(--gray);
  font-weight: 500;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 6px;
}
.kpi-label i { font-size: 10px; opacity: 0.7; }
.kpi-value {
  font-size: 24px;
  font-weight: 800;
  color: #fff;
  line-height: 1;
  letter-spacing: -0.5px;
}
.kpi-value.sm { font-size: 16px; letter-spacing: 0; }

/* Analytics grid */
.analytics-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

/* Revenue chips */
.rev-chip {
  flex: 1;
  background: rgba(255,255,255,0.03);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 12px;
  text-align: center;
}
.rev-chip-label { font-size: 10px; color: var(--gray); margin-bottom: 4px; font-weight: 600; letter-spacing: 0.5px; }
.rev-chip-val { font-size: 15px; font-weight: 800; line-height: 1.2; }
.rev-chip-sub { font-size: 10px; color: var(--gray); margin-top: 2px; }

/* Legend row */
.legend-row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 8px;
}
.legend-item {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  color: var(--gray-light);
}
.legend-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  flex-shrink: 0;
}

/* Progress bar thin */
.bar-track {
  height: 5px;
  border-radius: 100px;
  overflow: hidden;
  background: rgba(255,255,255,0.04);
}
.bar-track-inner { height: 100%; display: flex; }

/* Donut wrapper */
.donut-wrap {
  position: relative;
  width: 80px;
  height: 80px;
  margin: 0 auto 10px;
}
.donut-center {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  pointer-events: none;
}
.donut-center-val { font-size: 16px; font-weight: 800; color: #fff; }
.donut-center-lbl { font-size: 8px; color: var(--gray); text-transform: uppercase; letter-spacing: 0.5px; }

/* Donut legend */
.donut-legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 3px 0;
  font-size: 11px;
  color: var(--gray-light);
}
.donut-legend-item strong { margin-left: auto; font-size: 12px; }

/* Leaderboard */
.lb-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 0;
}
.lb-row + .lb-row { border-top: 1px solid rgba(255,255,255,0.04); }
.lb-rank {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: 800;
  flex-shrink: 0;
}
.lb-rank-1 { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; }
.lb-rank-2 { background: linear-gradient(135deg, #94a3b8, #64748b); color: #fff; }
.lb-rank-3 { background: linear-gradient(135deg, #f97316, #ea580c); color: #fff; }
.lb-rank-n { background: rgba(255,255,255,0.06); color: var(--gray); }
.lb-name {
  font-size: 12px;
  font-weight: 600;
  color: #fff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.lb-km {
  font-size: 13px;
  font-weight: 800;
  color: var(--primary);
  flex-shrink: 0;
  margin-left: auto;
}
.lb-km span { font-size: 9px; color: var(--gray); font-weight: 400; }

/* Shipping grid */
.ship-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.ship-item {
  background: rgba(255,255,255,0.02);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 10px 12px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.ship-val { font-size: 16px; font-weight: 800; line-height: 1; }
.ship-lbl { font-size: 10px; color: var(--gray); margin-top: 1px; }

/* Bottom grid */
.bottom-grid {
  display: grid;
  grid-template-columns: 1fr 1.3fr 0.7fr;
  gap: 16px;
}

/* Pending table */
.pending-scroll {
  overflow-y: auto;
  max-height: 280px;
}
.pending-scroll::-webkit-scrollbar { width: 3px; }
.pending-scroll::-webkit-scrollbar-track { background: transparent; }
.pending-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 3px; }

/* Submission stats row */
.sub-stats {
  display: flex;
  justify-content: space-around;
  margin-bottom: 10px;
}
.sub-stat { text-align: center; }
.sub-stat-val { font-size: 20px; font-weight: 800; line-height: 1; }
.sub-stat-lbl { font-size: 10px; color: var(--gray); margin-top: 3px; }

/* Finisher list */
.finisher-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 7px 0;
}
.finisher-row + .finisher-row { border-top: 1px solid rgba(255,255,255,0.04); }
.finisher-avatar {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--primary), #ea580c);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 11px;
  color: #fff;
  flex-shrink: 0;
}
.finisher-name {
  font-size: 12px;
  font-weight: 600;
  color: #fff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.finisher-cat { font-size: 10px; color: var(--gray); }

/* Empty state */
.empty-state {
  text-align: center;
  padding: 24px 12px;
  color: var(--gray);
}
.empty-state i { font-size: 28px; opacity: 0.3; display: block; margin-bottom: 8px; }

/* Event progress section inside card */
.event-progress { padding-top: 14px; border-top: 1px solid var(--border); margin-top: auto; }
.event-progress-header {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  color: var(--gray);
  margin-bottom: 6px;
}
.event-progress-dates {
  display: flex;
  justify-content: space-between;
  font-size: 10px;
  color: var(--gray);
  margin-top: 4px;
}

/* Divider */
.card-divider { border-top: 1px solid var(--border); padding-top: 16px; margin-top: 16px; }

/* ── Responsive ── */
@media (max-width: 1200px) {
  .kpi-grid { grid-template-columns: repeat(3, 1fr); }
  .analytics-grid { grid-template-columns: repeat(2, 1fr); }
  .bottom-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 768px) {
  .page-content { padding: 14px !important; }
  .kpi-grid { grid-template-columns: repeat(3, 1fr); gap: 8px; }
  .kpi-item { padding: 14px 12px; }
  .kpi-value { font-size: 20px; }
  .kpi-value.sm { font-size: 14px; }
  .analytics-grid { grid-template-columns: 1fr; gap: 12px; }
  .bottom-grid { grid-template-columns: 1fr; gap: 12px; }
  .db-card { padding: 16px; }
}
@media (max-width: 480px) {
  .page-content { padding: 10px !important; }
  .kpi-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
  .kpi-item { padding: 12px 10px; border-radius: 10px; }
  .kpi-value { font-size: 18px; }
  .kpi-label { font-size: 10px; }
  .ship-grid { grid-template-columns: 1fr 1fr; }
  .sub-stats { gap: 8px; }
  .topbar .d-flex.gap-2 { gap: 4px !important; }
}
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
          <div class="topbar-title">Dashboard</div>
          <div style="font-size:12px;color:var(--gray-light);"><?= $event ? sanitize($event['name']) : 'Tidak ada event aktif' ?></div>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button onclick="openModal('addParticipantModal')" class="btn-outline-custom btn-sm-custom">
          <i class="fa fa-user-plus"></i><span class="d-none d-sm-inline"> Tambah Peserta</span>
        </button>
        <?php if ($pendingSubs > 0): ?>
        <a href="<?= SITE_URL ?>/admin/submissions" class="btn-primary-custom btn-sm-custom">
          <i class="fa fa-clock"></i> <?= $pendingSubs ?> Pending
        </a>
        <?php endif; ?>
      </div>
    </div>

    <div class="page-content">
      <?php $flash = getFlash(); if ($flash): ?>
      <div class="alert-custom alert-<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'warning' ? 'warning' : 'danger') ?>" style="margin-bottom:16px;">
        <i class="fa fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= $flash['message'] ?>
      </div>
      <?php endif; ?>

      <?php
      $revTotal  = max(1, (int)$revenueStats['total']);
      $paidPct   = round((int)$revenueStats['paid_count']   / $revTotal * 100);
      $manualPct = round((int)$revenueStats['manual_count'] / $revTotal * 100);
      $unpaidPct = 100 - $paidPct - $manualPct;
      $totalSubs = $approvedSubs + $rejectedSubs + $pendingSubs;
      $dayColor  = $eventDaysLeft <= 7 ? '#ef4444' : ($eventDaysLeft <= 14 ? '#f59e0b' : 'var(--primary)');
      ?>

      <!-- KPI Cards -->
      <div class="kpi-grid">
        <div class="kpi-item">
          <div class="kpi-label"><i class="fa fa-users" style="color:#60a5fa;"></i> Peserta</div>
          <div class="kpi-value"><?= $totalRegistrations ?></div>
        </div>
        <div class="kpi-item">
          <div class="kpi-label"><i class="fa fa-trophy" style="color:var(--primary);"></i> Finisher</div>
          <div class="kpi-value"><?= $totalFinishers ?></div>
        </div>
        <div class="kpi-item">
          <div class="kpi-label"><i class="fa fa-running" style="color:#22c55e;"></i> Total KM</div>
          <div class="kpi-value"><?= number_format((float)$totalKmApproved, 0) ?></div>
        </div>
        <div class="kpi-item">
          <div class="kpi-label"><i class="fa fa-wallet" style="color:var(--primary);"></i> Revenue</div>
          <div class="kpi-value sm">Rp<?= number_format(((int)$revenueStats['rev_10k']+(int)$revenueStats['rev_21k'])/1000,0) ?>rb</div>
        </div>
        <div class="kpi-item">
          <div class="kpi-label"><i class="fa fa-clock" style="color:#f59e0b;"></i> Pending</div>
          <div class="kpi-value" style="color:<?= $pendingSubs > 0 ? '#f59e0b' : '#22c55e' ?>;"><?= $pendingSubs ?></div>
        </div>
        <div class="kpi-item">
          <div class="kpi-label"><i class="fa fa-calendar-alt" style="color:<?= $dayColor ?>;"></i> Hari Tersisa</div>
          <div class="kpi-value" style="color:<?= $dayColor ?>;"><?= $eventDaysLeft ?></div>
        </div>
      </div>

      <!-- Analytics Row -->
      <div class="analytics-grid">

        <!-- Revenue & Event Progress -->
        <div class="db-card" style="display:flex;flex-direction:column;">
          <div class="db-section-title"><i class="fa fa-wallet"></i> Revenue</div>
          <div style="display:flex;gap:10px;margin-bottom:16px;">
            <div class="rev-chip">
              <div class="rev-chip-label">10K</div>
              <div class="rev-chip-val" style="color:#22c55e;">Rp<?= number_format((int)$revenueStats['rev_10k']/1000,0) ?>rb</div>
              <div class="rev-chip-sub"><?= (int)$revenueStats['count_10k'] ?> peserta</div>
            </div>
            <div class="rev-chip">
              <div class="rev-chip-label">21K</div>
              <div class="rev-chip-val" style="color:#60a5fa;">Rp<?= number_format((int)$revenueStats['rev_21k']/1000,0) ?>rb</div>
              <div class="rev-chip-sub"><?= (int)$revenueStats['count_21k'] ?> peserta</div>
            </div>
          </div>
          <div class="legend-row">
            <?php foreach([['#22c55e','Lunas',(int)$revenueStats['paid_count']],['#60a5fa','Manual',(int)$revenueStats['manual_count']],['#ef4444','Belum',(int)$revenueStats['unpaid_count']]] as [$c,$l,$n]): ?>
            <div class="legend-item">
              <span class="legend-dot" style="background:<?=$c?>;"></span> <?=$l?> <strong style="color:#fff;"><?=$n?></strong>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="bar-track">
            <div class="bar-track-inner">
              <div style="width:<?= $paidPct ?>%;background:#22c55e;"></div>
              <div style="width:<?= $manualPct ?>%;background:#60a5fa;"></div>
              <div style="width:<?= $unpaidPct ?>%;background:#ef4444;"></div>
            </div>
          </div>
          <?php if ($event): ?>
          <div class="event-progress">
            <div class="event-progress-header">
              <span>Progress Event</span>
              <span style="color:<?=$dayColor?>;font-weight:700;"><?= $eventProgress ?>%</span>
            </div>
            <div class="bar-track">
              <div style="height:100%;width:<?= $eventProgress ?>%;background:linear-gradient(90deg,var(--primary),#fb923c);border-radius:100px;"></div>
            </div>
            <div class="event-progress-dates">
              <span><?= date('d M', strtotime($event['start_date'])) ?></span>
              <span><?= date('d M', strtotime($event['end_date'])) ?></span>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <!-- Donut Charts -->
        <div class="db-card">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;">
            <div style="padding-right:16px;">
              <div class="db-section-title"><i class="fa fa-running"></i> Kategori</div>
              <div class="donut-wrap">
                <canvas id="chartKategori"></canvas>
                <div class="donut-center">
                  <div class="donut-center-val"><?= (int)$revenueStats['total'] ?></div>
                  <div class="donut-center-lbl">total</div>
                </div>
              </div>
              <div class="donut-legend-item">
                <span class="legend-dot" style="background:#f97316;"></span> 10K
                <strong style="color:#f97316;"><?= (int)$revenueStats['count_10k'] ?></strong>
              </div>
              <div class="donut-legend-item">
                <span class="legend-dot" style="background:#3b82f6;"></span> 21K
                <strong style="color:#60a5fa;"><?= (int)$revenueStats['count_21k'] ?></strong>
              </div>
            </div>
            <div style="padding-left:16px;border-left:1px solid var(--border);">
              <div class="db-section-title"><i class="fa fa-credit-card"></i> Status Akun</div>
              <div class="donut-wrap">
                <canvas id="chartStatusAkun"></canvas>
              </div>
              <?php foreach([['#22c55e','Lunas',(int)$revenueStats['paid_count']],['#60a5fa','Manual',(int)$revenueStats['manual_count']],['#ef4444','Belum',(int)$revenueStats['unpaid_count']]] as [$c,$l,$n]): ?>
              <div class="donut-legend-item">
                <span class="legend-dot" style="background:<?=$c?>;"></span> <?=$l?>
                <strong style="color:<?=$c?>;"><?=$n?></strong>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Activity Charts -->
        <div class="db-card" style="display:flex;flex-direction:column;gap:20px;">
          <div>
            <div class="db-section-title"><i class="fa fa-user-plus"></i> Registrasi 7 Hari</div>
            <div style="height:110px;"><canvas id="chartReg7"></canvas></div>
          </div>
          <div class="card-divider" style="margin-top:0;">
            <div class="db-section-title"><i class="fa fa-upload"></i> Submission 7 Hari</div>
            <div style="height:110px;"><canvas id="chartSub7"></canvas></div>
          </div>
        </div>

        <!-- Leaderboard & Shipping -->
        <div class="db-card" style="display:flex;flex-direction:column;">
          <div class="db-section-title"><i class="fa fa-medal"></i> Leaderboard</div>
          <?php if (empty($leaderboard)): ?>
          <div class="empty-state"><i class="fa fa-medal"></i> Belum ada data</div>
          <?php else: ?>
          <?php foreach ($leaderboard as $i => $p):
            $pct2 = $p['target_km'] > 0 ? min(100, round($p['total_km'] / $p['target_km'] * 100)) : 0;
            $rankClass = ['lb-rank-1','lb-rank-2','lb-rank-3','lb-rank-n','lb-rank-n'][$i] ?? 'lb-rank-n';
          ?>
          <div class="lb-row">
            <div class="lb-rank <?= $rankClass ?>"><?= $i+1 ?></div>
            <div style="flex:1;min-width:0;">
              <div class="lb-name">
                <?= sanitize($p['name']) ?>
                <?php if ($p['status']==='finisher'): ?><i class="fa fa-trophy" style="color:#f59e0b;font-size:9px;margin-left:3px;"></i><?php endif; ?>
              </div>
              <div class="bar-track" style="height:2px;margin-top:4px;">
                <div style="height:100%;width:<?= $pct2 ?>%;background:linear-gradient(90deg,var(--primary),#fb923c);border-radius:100px;"></div>
              </div>
            </div>
            <div class="lb-km"><?= number_format($p['total_km'],1) ?><span> km</span></div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>

          <div class="card-divider">
            <div class="db-section-title"><i class="fa fa-box"></i> Status Jersey</div>
            <div class="ship-grid">
              <?php foreach(['not_ready'=>['Belum','#6b7280'],'preparing'=>['Siap','#f59e0b'],'shipped'=>['Kirim','#3b82f6'],'delivered'=>['Tiba','#22c55e']] as $k=>[$lbl,$col]): ?>
              <div class="ship-item">
                <span class="legend-dot" style="background:<?=$col?>;width:8px;height:8px;"></span>
                <div>
                  <div class="ship-val" style="color:<?=$col?>;"><?= $shippingStats[$k] ?></div>
                  <div class="ship-lbl"><?=$lbl?></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom Row -->
      <div class="bottom-grid">

        <!-- Progress Distribution -->
        <div class="db-card">
          <div class="db-section-title"><i class="fa fa-chart-bar"></i> Distribusi Progress</div>
          <div style="height:180px;"><canvas id="chartProgress"></canvas></div>
        </div>

        <!-- Pending Submissions -->
        <div class="db-card">
          <div class="db-section-title" style="margin-bottom:12px;">
            <i class="fa fa-clock" style="color:#f59e0b;"></i> Submission Pending
            <a href="<?= SITE_URL ?>/admin/submissions" style="margin-left:auto;font-size:11px;color:var(--primary);text-decoration:none;font-weight:500;">Lihat semua <i class="fa fa-arrow-right" style="font-size:9px;"></i></a>
          </div>
          <?php if (empty($recentPending)): ?>
          <div class="empty-state">
            <i class="fa fa-check-circle" style="color:#22c55e;"></i> Semua sudah diproses
          </div>
          <?php else: ?>
          <div class="pending-scroll">
            <table class="table-custom" style="font-size:12px;">
              <thead><tr><th>Peserta</th><th>Jarak</th><th>Bukti</th><th style="text-align:right;">Aksi</th></tr></thead>
              <tbody>
              <?php foreach ($recentPending as $sub): ?>
              <tr>
                <td>
                  <div style="font-weight:600;color:#fff;"><?= sanitize($sub['user_name']) ?></div>
                  <div style="font-size:10px;color:var(--gray);"><?= date('d M Y', strtotime($sub['run_date'])) ?></div>
                </td>
                <td><span style="font-weight:700;color:var(--primary);"><?= number_format($sub['distance_km'],1) ?></span> <span style="font-size:10px;color:var(--gray);">km</span></td>
                <td><img src="<?= UPLOAD_URL . sanitize($sub['evidence_path']) ?>" alt="" class="evidence-thumb" style="width:44px;height:44px;border-radius:6px;" onclick="openLightbox('<?= UPLOAD_URL . sanitize($sub['evidence_path']) ?>')"></td>
                <td style="text-align:right;">
                  <div class="d-flex gap-1" style="justify-content:flex-end;">
                    <button onclick="approveSubmission(<?= $sub['id'] ?>)" class="btn-success-custom" style="padding:5px 12px;font-size:11px;border-radius:6px;"><i class="fa fa-check"></i></button>
                    <button onclick="showRejectModal(<?= $sub['id'] ?>)" class="btn-danger-custom" style="padding:5px 12px;font-size:11px;border-radius:6px;"><i class="fa fa-times"></i></button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>

        <!-- Submission Stats + Recent Finishers -->
        <div class="db-card" style="display:flex;flex-direction:column;">
          <div class="db-section-title"><i class="fa fa-chart-pie"></i> Submission</div>
          <div class="sub-stats">
            <?php foreach([['#f59e0b','Pending',$pendingSubs],['#22c55e','Approved',$approvedSubs],['#ef4444','Rejected',$rejectedSubs]] as [$c,$l,$n]): ?>
            <div class="sub-stat">
              <div class="sub-stat-val" style="color:<?=$c?>;"><?=$n?></div>
              <div class="sub-stat-lbl"><?=$l?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php if ($totalSubs > 0): ?>
          <div class="bar-track" style="margin-bottom:4px;">
            <div class="bar-track-inner">
              <div style="width:<?= round($approvedSubs/$totalSubs*100) ?>%;background:#22c55e;"></div>
              <div style="width:<?= round($pendingSubs/$totalSubs*100) ?>%;background:#f59e0b;"></div>
              <div style="width:<?= round($rejectedSubs/$totalSubs*100) ?>%;background:#ef4444;"></div>
            </div>
          </div>
          <?php endif; ?>

          <div class="card-divider" style="flex:1;display:flex;flex-direction:column;">
            <div class="db-section-title"><i class="fa fa-trophy"></i> Finisher Terbaru</div>
            <?php if (empty($recentFinishers)): ?>
            <div class="empty-state" style="padding:12px;"><i class="fa fa-trophy"></i> Belum ada finisher</div>
            <?php else: ?>
            <?php foreach (array_slice($recentFinishers, 0, 4) as $f): ?>
            <div class="finisher-row">
              <div class="finisher-avatar"><?= strtoupper(substr($f['user_name'],0,1)) ?></div>
              <div style="flex:1;min-width:0;">
                <div class="finisher-name"><?= sanitize($f['user_name']) ?></div>
                <div class="finisher-cat"><?= $f['category'] ?></div>
              </div>
              <i class="fa fa-trophy" style="color:#f59e0b;font-size:11px;flex-shrink:0;"></i>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Tambah Peserta -->
<div class="modal-overlay" id="addParticipantModal">
  <div class="modal-box" style="max-width:520px;">
    <button class="modal-close" onclick="closeModal('addParticipantModal')">&times;</button>
    <h3 class="modal-title"><i class="fa fa-user-plus" style="color:var(--primary);margin-right:10px;"></i>Tambah Peserta Baru</h3>
    <div style="background:rgba(249,115,22,0.06);border:1px solid rgba(249,115,22,0.15);border-radius:var(--radius);padding:12px 16px;margin-bottom:20px;font-size:13px;color:var(--gray-light);">
      <i class="fa fa-info-circle" style="color:var(--primary);margin-right:6px;"></i>
      Password default: <strong style="color:#fff;">User@123</strong> — peserta diminta ganti setelah login.
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
        <div style="font-size:12px;color:var(--gray);margin-top:4px;">Jika email sudah terdaftar, peserta akan didaftarkan ke event dengan akun yang ada.</div>
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

<!-- Modal: Reject -->
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
Chart.defaults.color       = '#6b7280';
Chart.defaults.borderColor = 'rgba(255,255,255,0.04)';
Chart.defaults.font.family = "'Fira Sans', sans-serif";
Chart.defaults.font.size   = 11;
Chart.defaults.plugins.legend.display = false;

const donutOpts = {
  cutout: '74%',
  plugins: { tooltip: { backgroundColor: '#1a1a1a', titleColor: '#fff', bodyColor: '#9ca3af', borderColor: 'rgba(255,255,255,0.1)', borderWidth: 1, padding: 10, cornerRadius: 8, callbacks: {
    label: ctx => ' ' + ctx.label + ': ' + ctx.raw + ' peserta'
  }}},
  animation: { animateRotate: true, duration: 800 }
};

new Chart(document.getElementById('chartKategori'), {
  type: 'doughnut',
  data: {
    labels: ['10K', '21K'],
    datasets: [{ data: [<?= (int)$revenueStats['count_10k'] ?>, <?= (int)$revenueStats['count_21k'] ?>],
      backgroundColor: ['#f97316','#3b82f6'], borderWidth: 0, hoverOffset: 4 }]
  },
  options: donutOpts
});

new Chart(document.getElementById('chartStatusAkun'), {
  type: 'doughnut',
  data: {
    labels: ['Lunas','Manual Admin','Belum Bayar'],
    datasets: [{ data: [<?= (int)$revenueStats['paid_count'] ?>,<?= (int)$revenueStats['manual_count'] ?>,<?= (int)$revenueStats['unpaid_count'] ?>],
      backgroundColor: ['#22c55e','#3b82f6','#ef4444'], borderWidth: 0, hoverOffset: 4 }]
  },
  options: donutOpts
});

const barOpts = (suffix) => ({
  responsive: true, maintainAspectRatio: false,
  scales: {
    y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 }, maxTicksLimit: 4, color: '#4b5563' },
         grid: { color: 'rgba(255,255,255,0.03)' }, border: { display: false } },
    x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#4b5563' }, border: { display: false } }
  },
  plugins: { legend: { display: false }, tooltip: {
    backgroundColor: '#1a1a1a', titleColor: '#fff', bodyColor: '#9ca3af',
    borderColor: 'rgba(255,255,255,0.1)', borderWidth: 1, padding: 10, cornerRadius: 8,
    callbacks: { label: ctx => ' ' + ctx.raw + ' ' + suffix }
  }}
});

new Chart(document.getElementById('chartReg7'), {
  type: 'bar',
  data: {
    labels: [<?= implode(',', array_map(fn($d) => '"'.$d['date'].'"', $reg7days)) ?>],
    datasets: [{ data: [<?= implode(',', array_column($reg7days, 'count')) ?>],
      backgroundColor: 'rgba(249,115,22,0.6)', hoverBackgroundColor: 'rgba(249,115,22,0.85)', borderRadius: 4, borderSkipped: false }]
  },
  options: barOpts('pendaftaran')
});

new Chart(document.getElementById('chartSub7'), {
  type: 'bar',
  data: {
    labels: [<?= implode(',', array_map(fn($d) => '"'.$d['date'].'"', $sub7days)) ?>],
    datasets: [{ data: [<?= implode(',', array_column($sub7days, 'count')) ?>],
      backgroundColor: 'rgba(34,197,94,0.5)', hoverBackgroundColor: 'rgba(34,197,94,0.8)', borderRadius: 4, borderSkipped: false }]
  },
  options: barOpts('submission')
});

new Chart(document.getElementById('chartProgress'), {
  type: 'bar',
  data: {
    labels: ['0-25%', '25-50%', '50-75%', '75-99%', 'Finish'],
    datasets: [{
      data: [<?= $progressDist['p0'] ?>,<?= $progressDist['p25'] ?>,<?= $progressDist['p50'] ?>,<?= $progressDist['p75'] ?>,<?= $progressDist['p100'] ?>],
      backgroundColor: ['rgba(239,68,68,0.6)','rgba(245,158,11,0.6)','rgba(59,130,246,0.6)','rgba(168,85,247,0.6)','rgba(34,197,94,0.6)'],
      hoverBackgroundColor: ['#ef4444','#f59e0b','#3b82f6','#a855f7','#22c55e'],
      borderRadius: 5, borderSkipped: false
    }]
  },
  options: barOpts('peserta')
});

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
