<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_admin();
$user = current_user();
$pageTitle = 'Dashboard Admin';

$pdo = db();

// --- Stats ---
$totalUsers     = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$totalMaterials = (int)$pdo->query("SELECT COUNT(*) FROM materials")->fetchColumn();
$pending        = (int)$pdo->query("SELECT COUNT(*) FROM materials WHERE status='pending'")->fetchColumn();
$approved       = (int)$pdo->query("SELECT COUNT(*) FROM materials WHERE status='approved'")->fetchColumn();
$rejected       = (int)$pdo->query("SELECT COUNT(*) FROM materials WHERE status='rejected'")->fetchColumn();

// --- Recent 10 uploads ---
$recentMaterials = $pdo->query(
    "SELECT m.id_material, m.judul, m.format_file, m.status, m.tanggal_upload,
            u.nama AS pengunggah,
            c.nama_kategori
     FROM materials m
     JOIN users u ON u.id_user = m.id_user
     JOIN categories c ON c.id_category = m.id_category
     ORDER BY m.tanggal_upload DESC
     LIMIT 10"
)->fetchAll();

// --- Chart data: status distribution ---
$statusDist = $pdo->query(
    "SELECT status, COUNT(*) AS jumlah FROM materials GROUP BY status"
)->fetchAll();
$chartLabels = [];
$chartValues = [];
$chartColors = ['pending' => '#f59e0b', 'approved' => '#22c55e', 'rejected' => '#ef4444', 'takedown' => '#9ca3af'];
foreach ($statusDist as $row) {
    $chartLabels[] = ucfirst($row['status']);
    $chartValues[] = (int)$row['jumlah'];
}

// --- Chart data: registrations last 7 days ---
$regStmt = $pdo->query(
    "SELECT DATE(created_at) AS hari, COUNT(*) AS jumlah
     FROM users
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
       AND role = 'user'
     GROUP BY DATE(created_at)
     ORDER BY hari ASC"
);
$regRaw = [];
foreach ($regStmt->fetchAll() as $row) {
    $regRaw[$row['hari']] = (int)$row['jumlah'];
}
$regLabels = [];
$regValues = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $regLabels[] = date('d/m', strtotime($day));
    $regValues[] = $regRaw[$day] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= e($pageTitle) ?> | EduShare Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/dashboard.css')) ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="dashboard-layout">
<?php include __DIR__ . '/../partials/sidebar_admin.php'; ?>
<main class="content-area">

  <?php flash(); ?>

  <!-- Page header -->
  <div class="page-header">
    <div>
      <div class="eyebrow-white" style="color:#5142d8;font-size:10px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;">OVERVIEW</div>
      <h1>Dashboard Admin</h1>
      <p class="page-sub">Ringkasan aktivitas dan statistik platform EduShare.</p>
    </div>
    <a href="<?= e(url('admin/verifikasi.php')) ?>" class="btn-action btn-edit" style="font-size:12px;padding:9px 16px;">
      Verifikasi Materi <?php if ($pending > 0): ?><span style="background:#ef4444;color:#fff;border-radius:20px;padding:1px 7px;font-size:10px;margin-left:4px;"><?= $pending ?></span><?php endif; ?>
    </a>
  </div>

  <!-- Welcome banner -->
  <div class="welcome-banner">
    <div>
      <div class="eyebrow-white">EDUSHARE ADMIN PANEL</div>
      <h1>Selamat datang, <?= e($user['nama']) ?>!</h1>
      <p>Pantau aktivitas konten, verifikasi materi, dan kelola pengguna dari sini.</p>
    </div>
    
  </div>

  <!-- Stat cards -->
  <div class="stats-grid" style="grid-template-columns:repeat(5,1fr);">
    <div class="stat-card">
      
      <div class="stat-value"><?= number_format($totalUsers) ?></div>
      <div class="stat-label">Total Pengguna</div>
      <div class="stat-desc">Akun terdaftar</div>
    </div>
    <div class="stat-card">
      
      <div class="stat-value"><?= number_format($totalMaterials) ?></div>
      <div class="stat-label">Total Materi</div>
      <div class="stat-desc">Seluruh unggahan</div>
    </div>
    <div class="stat-card">
      
      <div class="stat-value"><?= number_format($pending) ?></div>
      <div class="stat-label">Pending</div>
      <div class="stat-desc">Menunggu verifikasi</div>
    </div>
    <div class="stat-card">
      
      <div class="stat-value"><?= number_format($approved) ?></div>
      <div class="stat-label">Disetujui</div>
      <div class="stat-desc">Materi terpublikasi</div>
    </div>
    <div class="stat-card">
      
      <div class="stat-value"><?= number_format($rejected) ?></div>
      <div class="stat-label">Ditolak</div>
      <div class="stat-desc">Tidak memenuhi syarat</div>
    </div>
  </div>

  <!-- Charts row -->
  <div style="display:grid;grid-template-columns:1fr 1.6fr;gap:20px;margin-bottom:20px;">

    <div class="panel" style="margin-bottom:0;">
      <div class="panel-header">
        <div>
          <h2>Distribusi Status</h2>
          <p class="panel-sub">Sebaran status seluruh materi</p>
        </div>
      </div>
      <div style="max-width:220px;margin:0 auto;">
        <canvas id="chartDoughnut" height="220"></canvas>
      </div>
    </div>

    <div class="panel" style="margin-bottom:0;">
      <div class="panel-header">
        <div>
          <h2>Registrasi 7 Hari Terakhir</h2>
          <p class="panel-sub">Jumlah pengguna baru per hari</p>
        </div>
      </div>
      <canvas id="chartLine" height="200"></canvas>
    </div>

  </div>

  <!-- Recent uploads table -->
  <div class="panel">
    <div class="panel-header">
      <div>
        <h2>Unggahan Terbaru</h2>
        <p class="panel-sub">10 materi yang baru ditambahkan ke platform</p>
      </div>
      <a href="<?= e(url('admin/materi.php')) ?>" class="panel-link">Lihat semua</a>
    </div>

    <?php if (empty($recentMaterials)): ?>
    <div class="empty-state-box">
      
      <h3>Belum ada materi</h3>
      <p>Belum ada materi yang diunggah ke platform.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Judul Materi</th>
            <th>Pengunggah</th>
            <th>Kategori</th>
            <th>Format</th>
            <th>Tanggal</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentMaterials as $m): ?>
          <tr>
            <td class="td-title">
              <?= e($m['judul']) ?>
            </td>
            <td><?= e($m['pengunggah']) ?></td>
            <td><?= e($m['nama_kategori']) ?></td>
            <td><span class="format-badge"><?= e(strtoupper($m['format_file'])) ?></span></td>
            <td><?= date('d/m/Y', strtotime($m['tanggal_upload'])) ?></td>
            <td>
              <span class="status-badge badge-<?= e($m['status']) ?>">
                <?= e(ucfirst($m['status'])) ?>
              </span>
            </td>
            <td>
              <div class="action-group">
                <?php if ($m['status'] === 'pending'): ?>
                <form method="post" action="<?= e(url('admin/verifikasi.php')) ?>" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="approve">
                  <input type="hidden" name="id" value="<?= (int)$m['id_material'] ?>">
                  <button type="submit" class="btn btn-success btn-small" title="Setujui">Setujui</button>
                </form>
                <button type="button" class="btn btn-danger btn-small"
                        title="Tolak"
                        onclick="openRejectModal(<?= (int)$m['id_material'] ?>, <?= json_encode($m['judul']) ?>)">Tolak</button>
                <?php else: ?>
                <a href="<?= e(url('admin/materi.php')) ?>?id=<?= (int)$m['id_material'] ?>" class="btn btn-outline btn-small" title="Lihat detail">Lihat</a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</main>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal-box" style="text-align:left;max-width:460px;">
    
    <h3>Tolak Materi</h3>
    <p>Berikan alasan penolakan untuk "<strong id="rejectTitle"></strong>".</p>
    <form method="post" action="<?= e(url('admin/verifikasi.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reject">
      <input type="hidden" name="id" id="rejectId">
      <div class="form-group" style="margin-bottom:16px;">
        <label class="form-label">Alasan Penolakan <span class="req">*</span></label>
        <textarea name="rejection_reason" class="form-control" rows="4" required placeholder="Jelaskan alasan penolakan..."></textarea>
      </div>
      <div class="modal-actions" style="justify-content:flex-end;">
        <button type="button" class="btn-action btn-view" onclick="closeRejectModal()">Batal</button>
        <button type="submit" class="btn-action btn-delete">Tolak Materi</button>
      </div>
    </form>
  </div>
</div>

<script src="<?= e(url('assets/js/dashboard.js')) ?>"></script>
<script>
// Reject modal helpers
function openRejectModal(id, title) {
    document.getElementById('rejectId').value = id;
    document.getElementById('rejectTitle').textContent = title;
    document.getElementById('rejectModal').classList.add('open');
}
function closeRejectModal() {
    document.getElementById('rejectModal').classList.remove('open');
}
document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) closeRejectModal();
});

// Doughnut chart – status distribution
(function() {
    var labels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
    var values = <?= json_encode($chartValues) ?>;
    var colorMap = {
        'Pending': '#f59e0b',
        'Approved': '#22c55e',
        'Rejected': '#ef4444',
        'Takedown': '#9ca3af'
    };
    var colors = labels.map(function(l) { return colorMap[l] || '#5142d8'; });

    new Chart(document.getElementById('chartDoughnut'), {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 12, padding: 12 } }
            },
            cutout: '65%',
            responsive: true,
            maintainAspectRatio: true
        }
    });
})();

// Line chart – registrations last 7 days
(function() {
    var labels = <?= json_encode($regLabels) ?>;
    var values = <?= json_encode($regValues) ?>;

    new Chart(document.getElementById('chartLine'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Pengguna Baru',
                data: values,
                borderColor: '#5142d8',
                backgroundColor: 'rgba(81,66,216,.08)',
                fill: true,
                tension: .4,
                pointBackgroundColor: '#5142d8',
                pointRadius: 4,
                borderWidth: 2
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, font: { size: 11 } },
                    grid: { color: '#f3f1f7' }
                },
                x: {
                    ticks: { font: { size: 11 } },
                    grid: { display: false }
                }
            },
            responsive: true,
            maintainAspectRatio: true
        }
    });
})();
</script>
</body>
</html>
