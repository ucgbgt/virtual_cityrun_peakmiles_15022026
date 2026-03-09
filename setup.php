<?php
/**
 * StrideNation Setup Script
 * Run this once to set up the database.
 * Delete this file after setup is complete!
 */

define('SETUP_KEY', 'stridenation2025setup');

if (!isset($_GET['key']) || $_GET['key'] !== SETUP_KEY) {
    die('Access denied. Use: setup.php?key=' . SETUP_KEY);
}

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'stridenation';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $sql = file_get_contents(__DIR__ . '/database.sql');
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $results = [];
    foreach ($statements as $stmt) {
        if (empty($stmt)) continue;
        try {
            $pdo->exec($stmt);
            $results[] = ['status' => 'ok', 'sql' => substr($stmt, 0, 80) . '...'];
        } catch (PDOException $e) {
            $results[] = ['status' => 'skip', 'sql' => substr($stmt, 0, 80) . '...', 'error' => $e->getMessage()];
        }
    }

    // Create upload directories
    $dirs = [
        __DIR__ . '/uploads/submissions/',
        __DIR__ . '/certificates/',
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($dir . '.gitkeep', '');
    }

    // Update admin password
    $pdo->exec("USE $dbname");
    $hash = password_hash('Admin@123', PASSWORD_BCRYPT, ['cost' => 12]);
    $pdo->prepare("UPDATE users SET password_hash=? WHERE email='admin@stridenation.id'")->execute([$hash]);

    echo '<!DOCTYPE html><html><head><title>Setup StrideNation</title>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@400;600;700&display=swap" rel="stylesheet">';
    echo '<style>body{background:#0f0f0f;color:#e5e7eb;font-family:Inter,sans-serif;padding:40px;max-width:800px;margin:0 auto;}
    h1{color:#f97316;font-size:28px;margin-bottom:8px;} .ok{color:#22c55e;} .skip{color:#f59e0b;} .error{color:#ef4444;}
    .item{padding:8px 12px;border-bottom:1px solid rgba(255,255,255,0.05);font-size:13px;}
    .card{background:#1a1a1a;border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:24px;margin:20px 0;}
    a.btn{display:inline-block;background:#f97316;color:#fff;padding:12px 24px;border-radius:10px;text-decoration:none;font-weight:600;margin-top:16px;}
    code{background:rgba(249,115,22,0.15);color:#f97316;padding:2px 8px;border-radius:4px;font-family:monospace;}
    </style></head><body>';
    echo '<h1>🏃 StrideNation Setup</h1>';
    echo '<p style="color:#9ca3af;">Database setup selesai!</p>';

    echo '<div class="card">';
    echo '<h3 style="color:#fff;margin-bottom:16px;">✅ Setup Berhasil!</h3>';
    echo '<p style="color:#9ca3af;margin-bottom:8px;"><strong style="color:#fff;">Login Admin Default:</strong></p>';
    echo '<p style="color:#9ca3af;">Email: <code>admin@stridenation.id</code></p>';
    echo '<p style="color:#9ca3af;">Password: <code>Admin@123</code></p>';
    echo '<p style="color:#ef4444;margin-top:16px;font-weight:600;">⚠️ HAPUS FILE setup.php SETELAH SETUP SELESAI!</p>';
    echo '<a class="btn" href="http://localhost/stridenation/">Buka Website</a>';
    echo '<a class="btn" href="http://localhost/stridenation/login.php" style="margin-left:12px;background:#1a1a1a;border:1px solid #f97316;color:#f97316;">Login</a>';
    echo '</div>';

    echo '<div class="card">';
    echo '<h4 style="color:#fff;margin-bottom:12px;">Log SQL Statements:</h4>';
    foreach ($results as $r) {
        echo '<div class="item"><span class="' . $r['status'] . '">' . strtoupper($r['status']) . '</span> — ' . htmlspecialchars($r['sql']);
        if (isset($r['error'])) echo ' <span class="error">(' . htmlspecialchars($r['error']) . ')</span>';
        echo '</div>';
    }
    echo '</div>';
    echo '</body></html>';

} catch (PDOException $e) {
    die('<p style="color:red;font-family:monospace;">Database connection failed: ' . $e->getMessage() . '</p>
    <p>Pastikan MySQL berjalan dan konfigurasi di config/database.php sudah benar.</p>');
}
