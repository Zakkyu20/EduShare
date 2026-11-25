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

// --- Stats ---
$stmtTotal = $pdo->prepare('SELECT COUNT(*) FROM materials WHERE id_user = ?');
$stmtTotal->execute([$userId]);
$totalMateri = (int)$stmtTotal->fetchColumn();

$stmtByStatus = $pdo->prepare(
    'SELECT status, COUNT(*) AS jumlah FROM materials WHERE id_user = ? GROUP BY status'
);
$stmtByStatus->execute([$userId]);
$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'takedown' => 0];
foreach ($stmtByStatus->fetchAll() as $row) {
    if (isset($counts[$row['status']])) {
        $counts[$row['status']] = (int)$row['jumlah'];
    }
}

// --- 5 Recent Materials ---
$stmtRecent = $pdo->prepare(
    'SELECT m.id_material, m.judul, m.format_file, m.status, m.tanggal_upload,
            c.nama_kategori
       FROM materials m
       JOIN categories c ON c.id_category = m.id_category
      WHERE m.id_user = ?
      ORDER BY m.tanggal_upload DESC
      LIMIT 5'
);
$stmtRecent->execute([$userId]);
$recentMaterials = $stmtRecent->fetchAll();

$pageTitle = 'Dashboard';
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

    <!-- Flash Message -->
    <?php
    if (!empty($_SESSION['flash']) && is_array($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $fType = htmlspecialchars((string)($f['type'] ?? 'info'), ENT_QUOTES, 'UTF-8');
        $fMsg  = htmlspecialchars((string)($f['message'] ?? ''), ENT_QUOTES, 'UTF-8');
        echo '<div class="flash-box ' . $fType . '">' . $fMsg . '</div>';
    }
    ?>

    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div>
            <div class="eyebrow-white">RUANG BELAJARMU</div>
            <h1>Halo, <?= e($user['nama']) ?>!</h1>
            <p>Kelola dan bagikan materi pembelajaran kamu di EduShare.</p>
        </div>
        <a href="<?= e(url('user/upload.php')) ?>" class="btn btn-light">
            Upload Materi
        </a>
    </div>

    <!-- Stat Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            
            <div class="stat-value"><?= $totalMateri ?></div>
            <div class="stat-label">Total Materi</div>
            <div class="stat-desc">Semua yang kamu unggah</div>
        </div>
        <div class="stat-card">
            
            <div class="stat-value"><?= $counts['approved'] ?></div>
            <div class="stat-label">Disetujui</div>
            <div class="stat-desc">Sudah tampil di EduShare</div>
        </div>
        <div class="stat-card">
            
            <div class="stat-value"><?= $counts['pending'] ?></div>
            <div class="stat-label">Menunggu</div>
            <div class="stat-desc">Dalam proses verifikasi</div>
        </div>
        <div class="stat-card">
            
            <div class="stat-value"><?= $counts['rejected'] ?></div>
            <div class="stat-label">Perlu Revisi</div>
            <div class="stat-desc">Ditolak, bisa direvisi</div>
        </div>
    </div>

    <!-- Recent Materials -->
    <div class="panel">
        <div class="panel-header">
            <div>
                <h2>Materi Terbaru Saya</h2>
                <p class="panel-sub">5 materi terakhir yang kamu unggah</p>
            </div>
            <a href="<?= e(url('user/materi_saya.php')) ?>" class="panel-link">Lihat semua</a>
        </div>

        <?php if (!empty($recentMaterials)): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Judul Materi</th>
                        <th>Kategori</th>
                        <th>Format</th>
                        <th>Tanggal Upload</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentMaterials as $mat): ?>
                    <tr>
                        <td class="td-title"><?= e($mat['judul']) ?></td>
                        <td><?= e($mat['nama_kategori']) ?></td>
                        <td><span class="format-badge"><?= e(strtoupper($mat['format_file'])) ?></span></td>
                        <td><?= date('d M Y', strtotime((string)$mat['tanggal_upload'])) ?></td>
                        <td>
                            <span class="status-badge badge-<?= e($mat['status']) ?>">
                                <?= match($mat['status']) {
                                    'approved' => 'Disetujui',
                                    'pending'  => 'Menunggu',
                                    'rejected' => 'Ditolak',
                                    'takedown' => 'Takedown',
                                    default    => e($mat['status']),
                                } ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-group">
                                <?php if (in_array($mat['status'], ['pending', 'rejected'], true)): ?>
                                <a href="<?= e(url('user/edit_materi.php?id=' . $mat['id_material'])) ?>" class="btn-action btn-edit">Edit</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state-box">
            
            <h3>Belum Ada Materi</h3>
            <p>Mulai kontribusi dengan mengunggah materi pertamamu!</p>
            <a href="<?= e(url('user/upload.php')) ?>" class="btn btn-primary">Upload Materi Pertama</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Links -->
    <div class="panel">
        <div class="panel-header">
            <div>
                <h2>Menu Cepat</h2>
                <p class="panel-sub">Akses fitur yang sering digunakan</p>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;">
            <a href="<?= e(url('user/upload.php')) ?>" style="display:flex;flex-direction:column;align-items:center;gap:8px;padding:20px 16px;background:#f3efff;border-radius:12px;text-decoration:none;color:#5142d8;font-size:12px;font-weight:700;transition:background .15s;" onmouseover="this.style.background='#ede9ff'" onmouseout="this.style.background='#f3efff'">
                Upload Materi
            </a>
            <a href="<?= e(url('user/materi_saya.php')) ?>" style="display:flex;flex-direction:column;align-items:center;gap:8px;padding:20px 16px;background:#e6f9f0;border-radius:12px;text-decoration:none;color:#0a6b3d;font-size:12px;font-weight:700;transition:background .15s;" onmouseover="this.style.background='#d1f5e0'" onmouseout="this.style.background='#e6f9f0'">
                Materi Saya
            </a>
            <a href="<?= e(url('user/riwayat.php')) ?>" style="display:flex;flex-direction:column;align-items:center;gap:8px;padding:20px 16px;background:#fff8e1;border-radius:12px;text-decoration:none;color:#856404;font-size:12px;font-weight:700;transition:background .15s;" onmouseover="this.style.background='#fef3c7'" onmouseout="this.style.background='#fff8e1'">
                Riwayat Upload
            </a>
            <a href="<?= e(url('user/profil.php')) ?>" style="display:flex;flex-direction:column;align-items:center;gap:8px;padding:20px 16px;background:#f0f9ff;border-radius:12px;text-decoration:none;color:#0369a1;font-size:12px;font-weight:700;transition:background .15s;" onmouseover="this.style.background='#e0f2fe'" onmouseout="this.style.background='#f0f9ff'">
                Profil Saya
            </a>
            <a href="<?= e(url('materi.php')) ?>" style="display:flex;flex-direction:column;align-items:center;gap:8px;padding:20px 16px;background:#fce8e8;border-radius:12px;text-decoration:none;color:#991b1b;font-size:12px;font-weight:700;transition:background .15s;" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fce8e8'">
                Jelajahi Materi
            </a>
        </div>
    </div>

</main>
</div>
<script src="<?= e(url('assets/js/dashboard.js')) ?>"></script>
</body>
</html>
