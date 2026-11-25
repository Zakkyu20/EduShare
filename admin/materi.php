<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_admin();

$user = current_user();
$adminId = (int)$user['id_user'];
$pdo = db();

// Handle material actions: takedown / approve / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $action = trim((string)($_POST['action'] ?? ''));

    if ($id > 0) {
        $chk = $pdo->prepare("SELECT id_material, judul, status, nama_file FROM materials WHERE id_material = ?");
        $chk->execute([$id]);
        $mat = $chk->fetch();

        if ($mat) {
            if ($action === 'takedown') {
                $upd = $pdo->prepare("UPDATE materials SET status = 'takedown', reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW() WHERE id_material = ?");
                $upd->execute([$adminId, $id]);
                log_activity($adminId, 'takedown_material', "Menurunkan materi: {$mat['judul']}", 'material', $id);
                log_audit('material_takedown', 'material', $id, "Admin menurunkan (takedown) materi \"{$mat['judul']}\".");
                set_flash('info', "Materi \"{$mat['judul']}\" berhasil ditakedown.");
            } elseif ($action === 'approve') {
                $upd = $pdo->prepare("UPDATE materials SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), rejection_reason = NULL, updated_at = NOW() WHERE id_material = ?");
                $upd->execute([$adminId, $id]);
                log_activity($adminId, 'approve_material', "Menyetujui materi: {$mat['judul']}", 'material', $id);
                log_audit('material_approved', 'material', $id, "Admin menyetujui materi \"{$mat['judul']}\".");
                set_flash('success', "Materi \"{$mat['judul']}\" berhasil disetujui.");
            } elseif ($action === 'delete') {
                safe_unlink_upload($mat['nama_file']);
                $del = $pdo->prepare("DELETE FROM materials WHERE id_material = ?");
                $del->execute([$id]);
                log_activity($adminId, 'delete_material', "Menghapus materi: {$mat['judul']}", 'material', $id);
                log_audit('material_deleted', 'material', $id, "Admin menghapus permanen materi \"{$mat['judul']}\".");
                set_flash('success', "Materi \"{$mat['judul']}\" berhasil dihapus.");
            }
        }
    }
    redirect(url('admin/materi.php'));
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$categoryFilter = (int)($_GET['kategori'] ?? 0);
$search = trim((string)($_GET['q'] ?? ''));

$sql = "SELECT m.*, u.nama AS pengunggah, u.email AS email_pengunggah, c.nama_kategori 
        FROM materials m 
        JOIN users u ON u.id_user = m.id_user 
        JOIN categories c ON c.id_category = m.id_category 
        WHERE 1=1";
$params = [];

if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'approved', 'rejected', 'takedown'], true)) {
    $sql .= " AND m.status = ?";
    $params[] = $statusFilter;
}

if ($categoryFilter > 0) {
    $sql .= " AND m.id_category = ?";
    $params[] = $categoryFilter;
}

if ($search !== '') {
    $sql .= " AND (m.judul LIKE ? OR m.deskripsi LIKE ? OR u.nama LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= " ORDER BY m.tanggal_upload DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll();

$categories = $pdo->query("SELECT id_category, nama_kategori FROM categories ORDER BY nama_kategori")->fetchAll();

$pageTitle = 'Manajemen Materi';
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
</head>
<body>
<div class="dashboard-layout">
<?php include __DIR__ . '/../partials/sidebar_admin.php'; ?>
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
            <span class="eyebrow">PENGELOLAAN KONTEN</span>
            <h1>Manajemen Seluruh Materi</h1>
            <p>Telusuri, pantau, dan kelola seluruh materi pembelajaran di EduShare.</p>
        </div>
        <a href="<?= e(url('admin/verifikasi.php')) ?>" class="btn btn-outline">
            Antrean Verifikasi
        </a>
    </div>

    <!-- Filter Bar -->
    <div class="card" style="margin-bottom: 20px; padding: 16px;">
        <form method="get" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
            <div style="flex:1; min-width:200px;">
                <input type="text" name="q" class="form-control" placeholder="Cari judul materi atau pengunggah..." value="<?= e($search) ?>">
            </div>
            <div style="width:180px;">
                <select name="status" class="form-control">
                    <option value="">-- Semua Status --</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    <option value="takedown" <?= $statusFilter === 'takedown' ? 'selected' : '' ?>>Takedown</option>
                </select>
            </div>
            <div style="width:200px;">
                <select name="kategori" class="form-control">
                    <option value="">-- Semua Kategori --</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id_category'] ?>" <?= $categoryFilter === (int)$c['id_category'] ? 'selected' : '' ?>>
                            <?= e($c['nama_kategori']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-small">Filter</button>
            <?php if ($search !== '' || $statusFilter !== '' || $categoryFilter > 0): ?>
                <a href="<?= e(url('admin/materi.php')) ?>" class="btn btn-outline btn-small">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Table -->
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
            <h3>Daftar Materi (<?= count($materials) ?> data)</h3>
        </div>
        <?php if (!empty($materials)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Materi</th>
                            <th>Pengunggah</th>
                            <th>Kategori</th>
                            <th>Format</th>
                            <th>Unduhan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materials as $m): ?>
                            <tr>
                                <td>
                                    <strong><?= e($m['judul']) ?></strong>
                                    <div class="text-muted" style="font-size:11px; margin-top:2px;">
                                        <?= date('d M Y, H:i', strtotime($m['tanggal_upload'])) ?>
                                    </div>
                                    <?php if (!empty($m['rejection_reason'])): ?>
                                        <div class="rejection-note" style="margin-top:4px;">
                                            Alasan ditolak: <?= e($m['rejection_reason']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><strong><?= e($m['nama']) ?></strong></div>
                                    <small class="text-muted"><?= e($m['email_pengunggah']) ?></small>
                                </td>
                                <td><span class="badge badge-light"><?= e($m['nama_kategori']) ?></span></td>
                                <td>
                                    <span class="badge badge-outline"><?= strtoupper(e($m['format_file'])) ?></span>
                                    <small class="text-muted"><?= round(((int)$m['file_size']) / 1024, 1) ?> KB</small>
                                </td>
                                <td><?= (int)$m['download_count'] ?> kali</td>
                                <td>
                                    <?php if ($m['status'] === 'approved'): ?>
                                        <span class="badge badge-success">Disetujui</span>
                                    <?php elseif ($m['status'] === 'pending'): ?>
                                        <span class="badge badge-warning">Menunggu</span>
                                    <?php elseif ($m['status'] === 'rejected'): ?>
                                        <span class="badge badge-danger">Ditolak</span>
                                    <?php elseif ($m['status'] === 'takedown'): ?>
                                        <span class="badge badge-secondary">Takedown</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="<?= e(url('detail.php?id=' . $m['id_material'])) ?>" class="btn-action btn-view" target="_blank" title="Lihat Materi">Lihat</a>

                                        <?php if ($m['status'] === 'approved'): ?>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Takedown materi ini dari publikasi?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$m['id_material'] ?>">
                                                <input type="hidden" name="action" value="takedown">
                                                <button type="submit" class="btn-action btn-delete" title="Takedown Materi">Takedown</button>
                                            </form>
                                        <?php elseif (in_array($m['status'], ['pending', 'rejected', 'takedown'], true)): ?>
                                            <form method="post" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$m['id_material'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn-action btn-edit" title="Setujui Materi">Setujui</button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="post" style="display:inline;" onsubmit="return confirm('Hapus permanen berkas dan materi ini?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$m['id_material'] ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn-action btn-delete" title="Hapus Permanen">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>Tidak ada materi yang sesuai kriteria pencarian.</p>
            </div>
        <?php endif; ?>
    </div>

</main>
</div>
<script src="<?= e(url('assets/js/dashboard.js')) ?>"></script>
</body>
</html>
