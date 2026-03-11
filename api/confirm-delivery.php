<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/shipping');
}

if (!validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Token tidak valid. Silakan refresh halaman.');
    redirect(SITE_URL . '/shipping');
}

// Double verification token check
if (($_POST['confirm_code'] ?? '') !== 'TERIMA') {
    flash('error', 'Kode konfirmasi salah.');
    redirect(SITE_URL . '/shipping');
}

$user = getCurrentUser();
$db   = getDB();
$event = getActiveEvent();

if (!$event) {
    flash('error', 'Event tidak ditemukan.');
    redirect(SITE_URL . '/shipping');
}

// Pastikan status saat ini adalah 'shipped'
$stmt = $db->prepare("SELECT * FROM shipping WHERE user_id=? AND event_id=?");
$stmt->execute([$user['id'], $event['id']]);
$shipping = $stmt->fetch();

if (!$shipping) {
    flash('error', 'Data pengiriman tidak ditemukan.');
    redirect(SITE_URL . '/shipping');
}

if ($shipping['status'] !== 'shipped') {
    flash('error', 'Konfirmasi hanya dapat dilakukan saat paket berstatus "Dalam Pengiriman".');
    redirect(SITE_URL . '/shipping');
}

// Update status ke delivered
$db->prepare("UPDATE shipping SET status='delivered', delivered_at=NOW() WHERE user_id=? AND event_id=?")
   ->execute([$user['id'], $event['id']]);

logAudit($user['id'], 'confirm_delivery', 'shipping', $shipping['id'],
    ['status' => 'shipped'],
    ['status' => 'delivered', 'confirmed_by' => 'user']
);

flash('success', 'Penerimaan paket berhasil dikonfirmasi! Terima kasih, selamat menikmati race pack-mu!');
redirect(SITE_URL . '/shipping');
