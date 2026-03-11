<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();
$event = getActiveEvent();

if (!$event) {
    flash('error', 'Event tidak ditemukan.');
    redirect(SITE_URL . '/certificate');
}

$cert = $db->prepare("SELECT * FROM certificates WHERE user_id=? AND event_id=?");
$cert->execute([$user['id'], $event['id']]);
$cert = $cert->fetch();

if (!$cert) {
    flash('error', 'Certificate belum tersedia.');
    redirect(SITE_URL . '/certificate');
}

$filePath = CERT_PATH . $cert['file_path'];
if (!file_exists($filePath)) {
    flash('error', 'File certificate tidak ditemukan.');
    redirect(SITE_URL . '/certificate');
}

// For HTML certificates, serve the file
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: inline; filename="Budapest Vrtl Hlf Mrthn 2026-certificate-' . $user['id'] . '.html"');
readfile($filePath);
