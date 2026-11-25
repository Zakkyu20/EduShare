<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_login();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('user/materi_saya.php'));
}

// CSRF protection
require_csrf();

$user = current_user();
$userId = (int)($user['id_user'] ?? 0);

// Sanitise and validate material ID
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'ID materi tidak valid.');
    redirect(url('user/materi_saya.php'));
}

// Fetch material — must exist and belong to the current user
$stmt = db()->prepare(
    "SELECT id_material, judul, nama_file, status
     FROM materials
     WHERE id_material = ? AND id_user = ?
     LIMIT 1"
);
$stmt->execute([$id, $userId]);
$material = $stmt->fetch();

if (!$material) {
    set_flash('error', 'Materi tidak ditemukan atau Anda tidak memiliki akses.');
    redirect(url('user/materi_saya.php'));
}

// Only allow deletion of pending or rejected materials
// (approved materials require admin action to unpublish first)
$allowedStatuses = ['pending', 'rejected'];
if (!in_array($material['status'], $allowedStatuses, true)) {
    set_flash('error', 'Materi yang sudah disetujui tidak dapat dihapus langsung. Hubungi admin.');
    redirect(url('user/materi_saya.php'));
}

$judul    = (string)$material['judul'];
$namaFile = (string)$material['nama_file'];

// Delete the physical file from uploads/
$fileDeleted = safe_unlink_upload($namaFile);

// Delete the database record
try {
    $del = db()->prepare("DELETE FROM materials WHERE id_material = ? AND id_user = ?");
    $del->execute([$id, $userId]);
    $affected = $del->rowCount();
} catch (Throwable $e) {
    error_log('hapus_materi: DB error – ' . $e->getMessage());
    set_flash('error', 'Terjadi kesalahan saat menghapus materi. Silakan coba lagi.');
    redirect(url('user/materi_saya.php'));
}

if ($affected === 0) {
    set_flash('error', 'Materi tidak berhasil dihapus. Silakan coba lagi.');
    redirect(url('user/materi_saya.php'));
}

// Logging
log_activity(
    $userId,
    'delete_material',
    "Menghapus materi: {$judul} (ID: {$id})",
    'material',
    $id
);

log_audit(
    'material_deleted',
    'material',
    $id,
    "Pengguna ID {$userId} menghapus materi \"{$judul}\" (status: {$material['status']}).",
    [
        'judul'        => $judul,
        'nama_file'    => $namaFile,
        'file_deleted' => $fileDeleted,
        'status'       => $material['status'],
    ]
);

set_flash('success', "Materi \"" . e($judul) . "\" berhasil dihapus.");
redirect(url('user/materi_saya.php'));
