<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit('Materi tidak ditemukan.');
}

$pdo = db();
$stmt = $pdo->prepare("SELECT id_material, nama_file, format_file, judul, status FROM materials WHERE id_material = ? AND status = 'approved'");
$stmt->execute([$id]);
$m = $stmt->fetch();

if (!$m) {
    http_response_code(404);
    exit('Materi tidak ditemukan atau belum disetujui.');
}

$path = __DIR__ . '/uploads/' . $m['nama_file'];
if (!is_file($path)) {
    http_response_code(404);
    exit('File tidak tersedia di server.');
}

// Increment download counter
try {
    $upd = $pdo->prepare("UPDATE materials SET download_count = download_count + 1 WHERE id_material = ?");
    $upd->execute([$id]);

    $u = current_user();
    if ($u) {
        log_activity((int)$u['id_user'], 'download_material', "Mengunduh materi: {$m['judul']}", 'material', $id);
    }
} catch (Throwable $e) {
    error_log('Download counter error: ' . $e->getMessage());
}

// Serve download
$cleanName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $m['judul']);
$filename = $cleanName . '.' . $m['format_file'];

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
