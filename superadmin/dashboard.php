<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_super_admin();

$user = current_user();
$pdo = db();

// Total platform stats
$totalUsers      = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$totalAdmins     = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$totalSuperAdmin = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin'")->fetchColumn();
$totalMaterials  = (int)$pdo->query("SELECT COUNT(*) FROM materials")->fetchColumn();
$pendingCount    = (int)$pdo->query("SELECT COUNT(*) FROM materials WHERE status = 'pending'")->fetchColumn();
$newUsersToday   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();

// Chart 1: User growth last 14 days
$userGrowthStmt = $pdo->query(
    "SELECT DATE(created_at) as tgl, COUNT(*) as cnt 
     FROM users 
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) 
     GROUP BY DATE(created_at) 
     ORDER BY tgl ASC"
);
$growthRaw = [];
foreach ($userGrowthStmt->fetchAll() as $r) {
    $growthRaw[$r['tgl']] = (int)$r['cnt'];
}
$growthLabels = [];
$growthValues = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $growthLabels[] = date('d M', strtotime($d));
    $growthValues[] = $growthRaw[$d] ?? 0;
}

// Chart 2: Material uploads last 14 days
$matGrowthStmt = $pdo->query(
    "SELECT DATE(tanggal_upload) as tgl, COUNT(*) as cnt 
     FROM materials 
     WHERE tanggal_upload >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) 
     GROUP BY DATE(tanggal_upload) 
     ORDER BY tgl ASC"
);
$matRaw = [];
foreach ($matGrowthStmt->fetchAll() as $r) {
    $matRaw[$r['tgl']] = (int)$r['cnt'];
}
$matLabels = [];
$matValues = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $matLabels[] = date('d M', strtotime($d));
    $matValues[] = $matRaw[$d] ?? 0;
}

// Recent Audit Logs (last 8)
$auditLogs = $pdo->query(
    "SELECT a.*, u.nama AS actor_name 
     FROM audit_logs a 
     LEFT JOIN users u ON u.id_user = a.actor_id 
     ORDER BY a.created_at DESC 
     LIMIT 8"
)->fetchAll();

// Recent Registrations
$recentUsers = $pdo->query(
    "SELECT id_user, nama, email, role, status, created_at 
     FROM users 
     ORDER BY created_at DESC 
     LIMIT 6"
)->fetchAll();

$pageTitle = 'Dashboard Super Admin';
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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="dashboard-layout">
<?php include __DIR__ . '/../partials/sidebar_superadmin.php'; ?>
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

    <div class="panel-header">
        <div>
            <span class="eyebrow" style="color:var(--gold);">PUSAT KONTROL UTAMA</span>
            <h1>Dashboard Super Admin</h1>
            <p>Pemantauan integritas sistem, keamanan data, operasional admin, dan perkembangan ekosistem EduShare.</p>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="<?= e(url('superadmin/admins.php')) ?>" class="btn btn-primary" style="background:#1e1b4b; border-color:#3730a3;">
                Kelola Admin
            </a>
            <a href="<?= e(url('superadmin/konfigurasi.php')) ?>" class="btn btn-outline">
                Konfigurasi
            </a>
        </div>
    </div>

    <!-- Alert if pending materials exist -->
    <?php if ($pendingCount > 0): ?>
        <div style="background:#fffbeb; border:1px solid #fde68a; border-left:4px solid #f59e0b; padding:14px 18px; border-radius:10px; margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <strong style="color:#b45309;">Antrean Moderasi:</strong>
                <span style="color:#78350f;"> Terdapat <?= $pendingCount ?> materi yang menunggu verifikasi dari tim administrator.</span>
            </div>
            <a href="<?= e(url('superadmin/materi.php?status=pending')) ?>" class="btn btn-outline btn-small" style="background:#fff;">Periksa Materi</a>
        </div>
    <?php endif; ?>

    <!-- 6 Stat Cards -->
    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
        <div class="stat-card">
            
            <div class="stat-value"><?= $totalUsers ?></div>
            <div class="stat-label">Total Pengguna</div>
            <div class="stat-desc">Role User</div>
        </div>
        <div class="stat-card">
            
            <div class="stat-value"><?= $totalAdmins ?></div>
            <div class="stat-label">Administrator</div>
            <div class="stat-desc">Operasional konten</div>
        </div>
        <div class="stat-card">
            
            <div class="stat-value"><?= $totalSuperAdmin ?></div>
            <div class="stat-label">Super Admin</div>
            <div class="stat-desc">Akses tertinggi</div>
        </div>
        <div class="stat-card">
            
            <div class="stat-value"><?= $totalMaterials ?></div>
            <div class="stat-label">Total Materi</div>
            <div class="stat-desc">Seluruh status</div>
        </div>
        <div class="stat-card">
            
            <div class="stat-value"><?= $pendingCount ?></div>
            <div class="stat-label">Perlu Review</div>
            <div class="stat-desc">Pending approval</div>
        </div>
        <div class="stat-card">
            
            <div class="stat-value"><?= $newUsersToday ?></div>
            <div class="stat-label">Daftar Hari Ini</div>
            <div class="stat-desc">Pertumbuhan harian</div>
        </div>
    </div>

    <!-- Charts Row -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 24px; margin-bottom: 24px;">
        <div class="card">
            <div class="card-header">
                <h3>Pertumbuhan Pengguna Baru (14 Hari)</h3>
            </div>
            <div style="height: 250px; position:relative;">
                <canvas id="userGrowthChart"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3>Unggahan Materi Baru (14 Hari)</h3>
            </div>
            <div style="height: 250px; position:relative;">
                <canvas id="matGrowthChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Logs & Recent Registrations -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 24px;">
        <!-- Audit Log Summary -->
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                <h3 style="margin:0;">Aktivitas Sistem & Audit Terkini</h3>
                <a href="<?= e(url('superadmin/audit.php')) ?>" class="btn-action btn-view">Lihat Semua</a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Aktor</th>
                            <th>Aksi</th>
                            <th>Deskripsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditLogs as $log): ?>
                            <tr>
                                <td style="font-size:11px;"><?= date('d/m H:i', strtotime($log['created_at'])) ?></td>
                                <td>
                                    <strong><?= e($log['actor_name'] ?? 'Sistem') ?></strong>
                                    <div style="font-size:10px; color:#666;"><?= e($log['actor_role'] ?? '-') ?></div>
                                </td>
                                <td><span class="badge badge-outline" style="font-size:10px;"><?= e($log['action']) ?></span></td>
                                <td style="font-size:11px; max-width:200px;"><?= e($log['description'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Users -->
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                <h3 style="margin:0;">Pengguna Baru Terdaftar</h3>
                <a href="<?= e(url('superadmin/pengguna.php')) ?>" class="btn-action btn-view">Semua Pengguna</a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Pengguna</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $ru): ?>
                            <tr>
                                <td>
                                    <strong><?= e($ru['nama']) ?></strong>
                                    <div class="text-muted" style="font-size:11px;"><?= e($ru['email']) ?></div>
                                </td>
                                <td><span class="badge badge-primary"><?= e($ru['role']) ?></span></td>
                                <td>
                                    <?php if ($ru['status'] === 'active'): ?>
                                        <span class="badge badge-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><?= e($ru['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:11px;"><?= date('d/m/Y', strtotime($ru['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>
</div>

<script src="<?= e(url('assets/js/dashboard.js')) ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // User growth chart
    new Chart(document.getElementById('userGrowthChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= json_encode($growthLabels) ?>,
            datasets: [{
                label: 'Pendaftaran Akun',
                data: <?= json_encode($growthValues) ?>,
                borderColor: '#1e1b4b',
                backgroundColor: 'rgba(30, 27, 75, 0.1)',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    // Material growth chart
    new Chart(document.getElementById('matGrowthChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($matLabels) ?>,
            datasets: [{
                label: 'Materi Diunggah',
                data: <?= json_encode($matValues) ?>,
                backgroundColor: 'rgba(245, 158, 11, 0.8)',
                borderColor: '#d97706',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
});
</script>
</body>
</html>
