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

$filterStatus = trim($_GET['status'] ?? '');
$search = trim($_GET['q'] ?? '');

$sql = "SELECT m.*, c.nama_kategori 
        FROM materials m 
        JOIN categories c ON c.id_category = m.id_category 
        WHERE m.id_user = ?";
$params = [$userId];

if (in_array($filterStatus, ['pending', 'approved', 'rejected'], true)) {
    $sql .= " AND m.status = ?";
    $params[] = $filterStatus;
}

if ($search !== '') {
    $sql .= " AND (m.judul LIKE ? OR m.deskripsi LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= " ORDER BY m.tanggal_upload DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll();

// Counts for tabs
$countsStmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM materials WHERE id_user = ? GROUP BY status");
$countsStmt->execute([$userId]);
$counts = ['all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($countsStmt->fetchAll() as $row) {
    if (isset($counts[$row['status']])) {
        $counts[$row['status']] = (int)$row['cnt'];
    }
    $counts['all'] += (int)$row['cnt'];
}

$pageTitle = 'Materi Saya';
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

    <div class="panel-header">
        <div>
            <span class="eyebrow">KOLEKSI PEMBELAJARAN</span>
            <h1>Materi Saya</h1>
            <p>Kelola bahan pembelajaran yang telah Anda unggah.</p>
        </div>
        <a href="<?= e(url('user/upload.php')) ?>" class="btn btn-primary">
            Upload Materi Baru
        </a>
    </div>

    <!-- Filter Tabs & Search -->
    <div class="filter-bar">
        <div class="tab-pills">
            <a href="<?= e(url('user/materi_saya.php')) ?>" class="tab-pill <?= $filterStatus === '' ? 'active' : '' ?>">
                Semua (<?= $counts['all'] ?>)
            </a>
            <a href="<?= e(url('user/materi_saya.php?status=approved')) ?>" class="tab-pill <?= $filterStatus === 'approved' ? 'active' : '' ?>">
                Disetujui (<?= $counts['approved'] ?>)
            </a>
            <a href="<?= e(url('user/materi_saya.php?status=pending')) ?>" class="tab-pill <?= $filterStatus === 'pending' ? 'active' : '' ?>">
                Menunggu (<?= $counts['pending'] ?>)
            </a>
            <a href="<?= e(url('user/materi_saya.php?status=rejected')) ?>" class="tab-pill <?= $filterStatus === 'rejected' ? 'active' : '' ?>">
                Ditolak (<?= $counts['rejected'] ?>)
            </a>
        </div>
        <form class="search-box" method="get" action="<?= e(url('user/materi_saya.php')) ?>">
            <?php if ($filterStatus !== ''): ?>
                <input type="hidden" name="status" value="<?= e($filterStatus) ?>">
            <?php endif; ?>
            <input type="text" name="q" placeholder="Cari materi saya..." value="<?= e($search) ?>">
            <button type="submit" class="btn btn-outline btn-small">Cari</button>
            <?php if ($search !== ''): ?>
                <a href="<?= e(url('user/materi_saya.php' . ($filterStatus !== '' ? '?status=' . urlencode($filterStatus) : ''))) ?>" class="btn btn-outline btn-small">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Data Table -->
    <div class="card">
        <?php if (!empty($materials)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Materi</th>
                            <th>Kategori</th>
                            <th>Format</th>
                            <th>Tanggal Upload</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materials as $m): ?>
                            <tr>
                                <td>
                                    <div class="material-title-cell">
                                        <strong><?= e($m['judul']) ?></strong>
                                        <small class="text-muted"><?= e(mb_strimwidth($m['deskripsi'] ?? '', 0, 80, '...')) ?></small>
                                        <?php if ($m['status'] === 'rejected' && !empty($m['rejection_reason'])): ?>
                                            <div class="rejection-note">
                                                <strong>Alasan Penolakan:</strong> <?= e($m['rejection_reason']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><span class="badge badge-light"><?= e($m['nama_kategori']) ?></span></td>
                                <td><span class="badge badge-outline"><?= strtoupper(e($m['format_file'])) ?></span></td>
                                <td><?= date('d M Y, H:i', strtotime($m['tanggal_upload'])) ?></td>
                                <td>
                                    <?php if ($m['status'] === 'approved'): ?>
                                        <span class="badge badge-success">Disetujui</span>
                                    <?php elseif ($m['status'] === 'pending'): ?>
                                        <span class="badge badge-warning">Menunggu</span>
                                    <?php elseif ($m['status'] === 'rejected'): ?>
                                        <span class="badge badge-danger">Ditolak</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><?= e($m['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($m['status'] === 'approved'): ?>
                                            <a href="<?= e(url('detail.php?id=' . $m['id_material'])) ?>" class="btn-action btn-view" title="Lihat di Beranda" target="_blank">Lihat</a>
                                        <?php endif; ?>

                                        <?php if (in_array($m['status'], ['pending', 'rejected'], true)): ?>
                                            <a href="<?= e(url('user/edit_materi.php?id=' . $m['id_material'])) ?>" class="btn-action btn-edit" title="Edit Materi">Edit</a>
                                            <form method="post" action="<?= e(url('user/hapus_materi.php')) ?>" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus materi ini?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$m['id_material'] ?>">
                                                <button type="submit" class="btn-action btn-delete" title="Hapus Materi">Hapus</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                
                <h3>Belum ada materi ditemukan</h3>
                <p>Materi yang Anda unggah akan ditampilkan di halaman ini.</p>
                <a href="<?= e(url('user/upload.php')) ?>" class="btn btn-primary" style="margin-top:12px;">Upload Materi Sekarang</a>
            </div>
        <?php endif; ?>
    </div>

</main>
</div>
<script src="<?= e(url('assets/js/dashboard.js')) ?>"></script>
</body>
</html>
