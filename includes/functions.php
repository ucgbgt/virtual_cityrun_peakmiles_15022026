<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_TIMEOUT,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function isLoggedIn(): bool {
    startSession();
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function isAdmin(): bool {
    return isLoggedIn() && $_SESSION['user_role'] === 'admin';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . SITE_URL . '/dashboard');
        exit;
    }
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT u.*, p.dob, p.gender, p.address_full, p.province, p.city, p.postal_code, p.jersey_size
        FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function getActiveEvent(): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM events WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    return $stmt->fetch() ?: null;
}

function getUserRegistration(int $userId, int $eventId): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM registrations WHERE user_id = ? AND event_id = ?");
    $stmt->execute([$userId, $eventId]);
    return $stmt->fetch() ?: null;
}

function getUserTotalKm(int $userId, int $eventId): float {
    $db = getDB();
    $stmt = $db->prepare("SELECT COALESCE(SUM(distance_km), 0) as total FROM run_submissions 
        WHERE user_id = ? AND event_id = ? AND status = 'approved'");
    $stmt->execute([$userId, $eventId]);
    $result = $stmt->fetch();
    return (float)$result['total'];
}

function checkAndUpdateFinisher(int $userId, int $eventId): bool {
    $db = getDB();
    $reg = getUserRegistration($userId, $eventId);
    if (!$reg || $reg['status'] === 'finisher') return false;

    $totalKm = getUserTotalKm($userId, $eventId);
    if ($totalKm >= $reg['target_km']) {
        $db->prepare("UPDATE registrations SET status='finisher', total_km_approved=?, finished_at=NOW() 
            WHERE user_id=? AND event_id=?")->execute([$totalKm, $userId, $eventId]);
        generateCertificate($userId, $eventId);
        return true;
    }
    $db->prepare("UPDATE registrations SET total_km_approved=? WHERE user_id=? AND event_id=?")
        ->execute([$totalKm, $userId, $eventId]);
    return false;
}

function regenerateCertificatesForUser(int $userId): void {
    $db = getDB();
    $certs = $db->prepare("SELECT * FROM certificates WHERE user_id=?");
    $certs->execute([$userId]);
    foreach ($certs->fetchAll() as $cert) {
        $filepath = CERT_PATH . $cert['file_path'];
        if (file_exists($filepath)) unlink($filepath);
        $db->prepare("DELETE FROM certificates WHERE id=?")->execute([$cert['id']]);
        generateCertificate($userId, $cert['event_id']);
    }
}

function generateCertificate(int $userId, int $eventId): ?string {
    $db = getDB();
    // Check if already exists
    $existing = $db->prepare("SELECT * FROM certificates WHERE user_id=? AND event_id=?");
    $existing->execute([$userId, $eventId]);
    if ($existing->fetch()) return null;

    $user = $db->prepare("SELECT u.name, r.category, r.total_km_approved, r.finished_at, e.name as event_name
        FROM users u JOIN registrations r ON u.id=r.user_id JOIN events e ON e.id=r.event_id
        WHERE u.id=? AND e.id=?");
    $user->execute([$userId, $eventId]);
    $data = $user->fetch();
    if (!$data) return null;

    $filename = 'cert_' . $userId . '_' . $eventId . '_' . time() . '.html';
    $filepath = CERT_PATH . $filename;

    $certContent = generateCertificateHTML($data);
    file_put_contents($filepath, $certContent);

    $db->prepare("INSERT INTO certificates (user_id, event_id, file_path) VALUES (?,?,?)")
        ->execute([$userId, $eventId, $filename]);

    return $filename;
}

function generateCertificateHTML(array $data): string {
    $finishedDate = $data['finished_at'] ? date('d F Y', strtotime($data['finished_at'])) : date('d F Y');
    $totalKm = number_format((float)$data['total_km_approved'], 2);
    return '<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>E-Certificate - ' . htmlspecialchars($data['name']) . '</title>
<style>
  @import url("https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Open+Sans:wght@300;400;600&display=swap");
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { width: 1122px; height: 794px; background: #fff; font-family: "Open Sans", sans-serif; display: flex; align-items: center; justify-content: center; }
  .cert { width: 100%; height: 100%; position: relative; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #0f0f0f 0%, #1a1a2e 50%, #16213e 100%); overflow: hidden; }
  .cert::before { content: ""; position: absolute; inset: 20px; border: 2px solid #f97316; opacity: 0.5; }
  .cert::after { content: ""; position: absolute; inset: 28px; border: 1px solid #f97316; opacity: 0.2; }
  .bg-circle { position: absolute; border-radius: 50%; opacity: 0.05; }
  .bg-circle-1 { width: 600px; height: 600px; background: #f97316; top: -200px; right: -100px; }
  .bg-circle-2 { width: 400px; height: 400px; background: #f97316; bottom: -150px; left: -100px; }
  .content { position: relative; z-index: 10; text-align: center; color: #fff; padding: 40px; }
  .brand { font-size: 14px; letter-spacing: 6px; color: #f97316; text-transform: uppercase; margin-bottom: 8px; }
  .cert-title { font-family: "Playfair Display", serif; font-size: 42px; font-weight: 900; color: #fff; margin-bottom: 20px; }
  .presented { font-size: 13px; color: #9ca3af; letter-spacing: 3px; text-transform: uppercase; margin-bottom: 12px; }
  .name { font-family: "Playfair Display", serif; font-size: 52px; font-weight: 700; color: #f97316; margin-bottom: 20px; line-height: 1.2; }
  .desc { font-size: 15px; color: #d1d5db; line-height: 1.8; margin-bottom: 28px; }
  .stats { display: flex; gap: 60px; justify-content: center; margin-bottom: 32px; }
  .stat { text-align: center; }
  .stat-val { font-size: 28px; font-weight: 700; color: #f97316; }
  .stat-label { font-size: 11px; color: #9ca3af; letter-spacing: 2px; text-transform: uppercase; }
  .divider { width: 200px; height: 1px; background: linear-gradient(to right, transparent, #f97316, transparent); margin: 0 auto 24px; }
  .footer { font-size: 11px; color: #6b7280; letter-spacing: 1px; }
  .event-name { font-size: 13px; color: #d1d5db; margin-bottom: 4px; }
</style>
</head>
<body>
<div class="cert">
  <div class="bg-circle bg-circle-1"></div>
  <div class="bg-circle bg-circle-2"></div>
  <div class="content">
    <div class="brand">PeakMiles</div>
    <h1 class="cert-title">FINISHER CERTIFICATE</h1>
    <div class="divider"></div>
    <p class="presented">This is to certify that</p>
    <div class="name">' . htmlspecialchars($data['name']) . '</div>
    <p class="desc">has successfully completed the virtual run challenge<br>and achieved the status of <strong>FINISHER</strong></p>
    <div class="stats">
      <div class="stat">
        <div class="stat-val">' . htmlspecialchars($data['category']) . '</div>
        <div class="stat-label">Category</div>
      </div>
      <div class="stat">
        <div class="stat-val">' . $totalKm . ' km</div>
        <div class="stat-label">Total Distance</div>
      </div>
      <div class="stat">
        <div class="stat-val">' . $finishedDate . '</div>
        <div class="stat-label">Completed On</div>
      </div>
    </div>
    <div class="divider"></div>
    <div class="event-name">' . htmlspecialchars($data['event_name']) . '</div>
    <div class="footer">PeakMiles &bull; peakmiles.id &bull; Virtual Run Event</div>
  </div>
</div>
</body>
</html>';
}

function logAudit(int $actorId, string $action, string $objectType, int $objectId, ?array $before = null, ?array $after = null): void {
    $db = getDB();
    $db->prepare("INSERT INTO audit_logs (actor_id, action, object_type, object_id, before_data, after_data, ip_address) VALUES (?,?,?,?,?,?,?)")
        ->execute([
            $actorId, $action, $objectType, $objectId,
            $before ? json_encode($before) : null,
            $after ? json_encode($after) : null,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
}

function generateCSRFToken(): string {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRF(string $token): bool {
    startSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void {
    startSession();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    startSession();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function formatKm(float $km): string {
    return number_format($km, 2) . ' km';
}

function progressPercent(float $current, float $target): int {
    if ($target <= 0) return 0;
    return min(100, (int)(($current / $target) * 100));
}

function getSetting(string $key, string $default = ''): string {
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT value FROM settings WHERE `key`=?");
        $stmt->execute([$key]);
        $val  = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function setSetting(string $key, string $value): void {
    $db = getDB();
    $db->prepare("INSERT INTO settings (`key`, `value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?, `updated_at`=NOW()")
       ->execute([$key, $value, $value]);
}

function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function timeAgo(string $datetime): string {
    $now = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->diff($then);
    if ($diff->d == 0 && $diff->h == 0) return $diff->i . ' menit lalu';
    if ($diff->d == 0) return $diff->h . ' jam lalu';
    if ($diff->d < 7) return $diff->d . ' hari lalu';
    return $then->format('d M Y');
}
