<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_login();

$user = current_user();
if (in_array($user['role'], ['admin', 'super_admin'], true)) {
    redirect_by_role();
}

$userId = (int)$user['id_user'];
$pdo = db();

// Fetch all materials ever uploaded by this user, ordered by tanggal_upload DESC
$stmt = $pdo->prepare(
    "SELECT m.*, c.nama_kategori 
     FROM materials m 
     JOIN categories c ON c.id_category = m.id_category 
     WHERE m.id_user = ? 
     ORDER BY m.tanggal_upload DESC"
);
$stmt->execute([$userId]);
$history = $stmt->fetchAll();

// Activity log for uploads
$actStmt = $pdo->prepare(
    "SELECT * FROM activity_logs 
     WHERE id_user = ? 
     ORDER BY created_at DESC 
     LIMIT 20"
);
$actStmt->execute([$userId]);
$activities = $actStmt->fetchAll();

$pageTitle = 'Riwayat Upload';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= e($pageTitle) ?> | EduShare</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/dashboard.css')) ?>">
</head>
<body>
<div class="dashboard-layout">
<?php include __DIR__ . '/../partials/sidebar_user.php'; ?>
<main class="content-area">

    <div class="panel-header">
        <div>
            <span class="eyebrow">CATATAN & LOG</span>
            <h1>Riwayat Upload & Aktivitas</h1>
            <p>Daftar lengkap riwayat materi yang pernah Anda kontribusikan ke platform.</p>
        </div>
        <a href="<?= e(url('user/upload.php')) ?>" class="btn btn-primary">
            Upload Materi
        </a>
    </div>

    <!-- History Table -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header">
            <h3>Daftar Materi yang Pernah Diunggah</h3>
        </div>
        <?php if (!empty($history)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Judul Materi</th>
                            <th>Kategori</th>
                            <th>Format & Ukuran</th>
                            <th>Tanggal Upload</th>
                            <th>Status Verifikasi</th>
                            <th>Catatan Admin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $idx => $item): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td>
                                    <strong><?= e($item['judul']) ?></strong>
                                    <div class="text-muted" style="font-size:11px;"><?= e(mb_strimwidth($item['deskripsi'] ?? '', 0, 70, '...')) ?></div>
                                </td>
                                <td><span class="badge badge-light"><?= e($item['nama_kategori']) ?></span></td>
                                <td>
                                    <span class="badge badge-outline"><?= strtoupper(e($item['format_file'])) ?></span>
                                    <small class="text-muted"><?= round(((int)$item['file_size']) / 1024, 1) ?> KB</small>
                                </td>
                                <td><?= date('d M Y, H:i', strtotime($item['tanggal_upload'])) ?></td>
                                <td>
                                    <?php if ($item['status'] === 'approved'): ?>
                                        <span class="badge badge-success">Disetujui</span>
                                    <?php elseif ($item['status'] === 'pending'): ?>
                                        <span class="badge badge-warning">Menunggu</span>
                                    <?php elseif ($item['status'] === 'rejected'): ?>
                                        <span class="badge badge-danger">Ditolak</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><?= e($item['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($item['rejection_reason'])): ?>
                                        <span class="text-danger" style="font-size:12px;"><?= e($item['rejection_reason']) ?></span>
                                    <?php elseif ($item['status'] === 'approved'): ?>
                                        <span class="text-success" style="font-size:12px;">Materi aktif & dapat diunduh publik</span>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:12px;">Sedang ditinjau</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>Belum ada riwayat unggahan.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- User Activity Timeline -->
    <div class="card">
        <div class="card-header">
            <h3>Log Aktivitas Akun</h3>
        </div>
        <?php if (!empty($activities)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Aktivitas</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activities as $act): ?>
                            <tr>
                                <td style="width: 180px;"><?= date('d M Y, H:i:s', strtotime($act['created_at'])) ?></td>
                                <td><span class="badge badge-outline"><?= e($act['action']) ?></span></td>
                                <td><?= e($act['description'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>Belum ada riwayat aktivitas yang tercatat.</p>
            </div>
        <?php endif; ?>
    </div>

</main>
</div>
<script src="<?= e(url('assets/js/dashboard.js')) ?>"></script>
</body>
</html>
