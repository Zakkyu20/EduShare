<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_admin();

$user = current_user();
$pdo = db();

// Total numbers
$totalMaterials = (int)$pdo->query("SELECT COUNT(*) FROM materials")->fetchColumn();
$totalApproved  = (int)$pdo->query("SELECT COUNT(*) FROM materials WHERE status = 'approved'")->fetchColumn();
$totalPending   = (int)$pdo->query("SELECT COUNT(*) FROM materials WHERE status = 'pending'")->fetchColumn();
$totalUsers     = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$totalDownloads = (int)$pdo->query("SELECT SUM(download_count) FROM materials")->fetchColumn();

// Top 5 Contributors
$topUploaders = $pdo->query(
    "SELECT u.nama, u.email, 
            COUNT(m.id_material) AS total_upload,
            SUM(CASE WHEN m.status = 'approved' THEN 1 ELSE 0 END) AS total_approved,
            SUM(m.download_count) AS total_downloads
     FROM users u
     JOIN materials m ON m.id_user = u.id_user
     GROUP BY u.id_user
     ORDER BY total_approved DESC, total_upload DESC
     LIMIT 5"
)->fetchAll();

// Most popular categories
$popularCategories = $pdo->query(
    "SELECT c.nama_kategori, 
            COUNT(m.id_material) AS total_materi,
            SUM(m.download_count) AS total_downloads
     FROM categories c
     LEFT JOIN materials m ON m.id_category = c.id_category
     GROUP BY c.id_category
     ORDER BY total_materi DESC
     LIMIT 6"
)->fetchAll();

// Monthly upload trend (last 6 months)
$monthlyTrend = $pdo->query(
    "SELECT DATE_FORMAT(tanggal_upload, '%Y-%m') AS bulan, COUNT(*) AS jumlah
     FROM materials
     WHERE tanggal_upload >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(tanggal_upload, '%Y-%m')
     ORDER BY bulan ASC"
)->fetchAll();

$monthLabels = [];
$monthValues = [];
foreach ($monthlyTrend as $mt) {
    $monthLabels[] = date('M Y', strtotime($mt['bulan'] . '-01'));
    $monthValues[] = (int)$mt['jumlah'];
}

$pageTitle = 'Laporan dan Statistik';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= e($pageTitle) ?> | EduShare Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/dashboard.css')) ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="dashboard-layout">
<?php include __DIR__ . '/../partials/sidebar_admin.php'; ?>
<main class="content-area">

    <div class="panel-header">
        <div>
            <span class="eyebrow">ANALITIK PLATFORM</span>
            <h1>Laporan & Statistik Pembelajaran</h1>
            <p>Ringkasan performa pertumbuhan materi, partisipasi pengguna, dan distribusi konten.</p>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            
            <div class="stat-value"><?= $totalMaterials ?></div>
            <div class="stat-label">Total Materi</div>
            <div class="stat-desc">Semua status</div>
        </div>
        <div class="stat-card">
            
            <div class="stat-value"><?= $totalApproved ?></div>
            <div class="stat-label">Materi Disetujui</div>
            <div class="stat-desc"><?= $totalMaterials > 0 ? round(($totalApproved / $totalMaterials) * 100, 1) : 0 ?>% dari total</div>
        </div>
        <div class="stat-card">
            
            <div class="stat-value"><?= $totalUsers ?></div>
            <div class="stat-label">Pengguna Aktif</div>
            <div class="stat-desc">Komunitas belajar</div>
        </div>
        <div class="stat-card">
            
            <div class="stat-value"><?= $totalDownloads ?></div>
            <div class="stat-label">Total Unduhan</div>
            <div class="stat-desc">Akses file materi</div>
        </div>
    </div>

    <!-- Chart: Monthly Uploads -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header">
            <h3>Tren Unggahan Materi (6 Bulan Terakhir)</h3>
        </div>
        <div style="height: 280px; position:relative;">
            <canvas id="monthlyChart"></canvas>
        </div>
    </div>

    <!-- Tables Grid -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 24px;">
        <!-- Top Uploaders -->
        <div class="card">
            <div class="card-header">
                <h3>Top Kontributor Materi</h3>
            </div>
            <?php if (!empty($topUploaders)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Kontributor</th>
                                <th>Disetujui</th>
                                <th>Total Unduhan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topUploaders as $idx => $tu): ?>
                                <tr>
                                    <td>
                                        <strong><?= $idx + 1 ?>. <?= e($tu['nama']) ?></strong>
                                        <div class="text-muted" style="font-size:11px;"><?= e($tu['email']) ?></div>
                                    </td>
                                    <td><span class="badge badge-success"><?= (int)$tu['total_approved'] ?></span></td>
                                    <td><?= (int)$tu['total_downloads'] ?> kali</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state"><p>Belum ada kontributor.</p></div>
            <?php endif; ?>
        </div>

        <!-- Category Distribution -->
        <div class="card">
            <div class="card-header">
                <h3>Kategori Paling Diminati</h3>
            </div>
            <?php if (!empty($popularCategories)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Materi</th>
                                <th>Total Unduhan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($popularCategories as $pc): ?>
                                <tr>
                                    <td><strong><?= e($pc['nama_kategori']) ?></strong></td>
                                    <td><?= (int)$pc['total_materi'] ?> materi</td>
                                    <td><?= (int)$pc['total_downloads'] ?> kali</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state"><p>Belum ada data kategori.</p></div>
            <?php endif; ?>
        </div>
    </div>

</main>
</div>

<script src="<?= e(url('assets/js/dashboard.js')) ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($monthLabels) ?>,
            datasets: [{
                label: 'Jumlah Materi Diunggah',
                data: <?= json_encode($monthValues) ?>,
                backgroundColor: 'rgba(81, 66, 216, 0.75)',
                borderColor: '#5142d8',
                borderWidth: 1.5,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });
});
</script>
</body>
</html>
