<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_super_admin();

$user = current_user();
$superAdminId = (int)$user['id_user'];
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $action = trim((string)($_POST['action'] ?? ''));

    if ($id > 0) {
        $chk = $pdo->prepare("SELECT id_material, judul, nama_file, status FROM materials WHERE id_material = ?");
        $chk->execute([$id]);
        $mat = $chk->fetch();

        if ($mat) {
            if ($action === 'force_approve') {
                $upd = $pdo->prepare("UPDATE materials SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), rejection_reason = NULL, updated_at = NOW() WHERE id_material = ?");
                $upd->execute([$superAdminId, $id]);
                log_audit('material_force_approved', 'material', $id, "Super Admin melakukan force-approve materi \"{$mat['judul']}\".");
                set_flash('success', "Materi \"{$mat['judul']}\" berhasil disetujui secara langsung.");
            } elseif ($action === 'force_reject') {
                $reason = trim((string)($_POST['reason'] ?? 'Ditolak oleh Super Administrator.'));
                $upd = $pdo->prepare("UPDATE materials SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), rejection_reason = ?, updated_at = NOW() WHERE id_material = ?");
                $upd->execute([$superAdminId, $reason, $id]);
                log_audit('material_force_rejected', 'material', $id, "Super Admin menolak materi \"{$mat['judul']}\".", ['reason' => $reason]);
                set_flash('info', "Materi \"{$mat['judul']}\" ditolak.");
            } elseif ($action === 'takedown') {
                $upd = $pdo->prepare("UPDATE materials SET status = 'takedown', reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW() WHERE id_material = ?");
                $upd->execute([$superAdminId, $id]);
                log_audit('material_takedown', 'material', $id, "Super Admin menurunkan materi \"{$mat['judul']}\".");
                set_flash('info', "Materi \"{$mat['judul']}\" berhasil ditakedown.");
            } elseif ($action === 'delete') {
                safe_unlink_upload($mat['nama_file']);
                $del = $pdo->prepare("DELETE FROM materials WHERE id_material = ?");
                $del->execute([$id]);
                log_audit('material_deleted_permanent', 'material', $id, "Super Admin menghapus permanen materi \"{$mat['judul']}\".");
                set_flash('success', "Materi \"{$mat['judul']}\" dan berkasnya berhasil dihapus permanen.");
            }
        }
    }
    redirect(url('superadmin/materi.php'));
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$search = trim((string)($_GET['q'] ?? ''));

$sql = "SELECT m.*, u.nama AS pengunggah, u.email AS email_pengunggah, c.nama_kategori,
               adm.nama AS nama_reviewer
        FROM materials m 
        JOIN users u ON u.id_user = m.id_user 
        JOIN categories c ON c.id_category = m.id_category 
        LEFT JOIN users adm ON adm.id_user = m.reviewed_by 
        WHERE 1=1";
$params = [];

if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'approved', 'rejected', 'takedown'], true)) {
    $sql .= " AND m.status = ?";
    $params[] = $statusFilter;
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

$pageTitle = 'Manajemen Seluruh Materi (Super Admin)';
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
            <span class="eyebrow" style="color:var(--gold);">KONTROL MATERI TERTINGGI</span>
            <h1>Manajemen Seluruh Materi</h1>
            <p>Kontrol penuh atas seluruh materi pembelajaran: force-approve, takedown, dan hapus permanen.</p>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card" style="margin-bottom: 20px; padding: 16px;">
        <form method="get" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
            <div style="flex:1; min-width:220px;">
                <input type="text" name="q" class="form-control" placeholder="Cari materi atau pengunggah..." value="<?= e($search) ?>">
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
            <button type="submit" class="btn btn-primary btn-small">Filter</button>
            <?php if ($search !== '' || $statusFilter !== ''): ?>
                <a href="<?= e(url('superadmin/materi.php')) ?>" class="btn btn-outline btn-small">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Materials Table -->
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
            <h3>Daftar Materi (<?= count($materials) ?> item)</h3>
        </div>
        <?php if (!empty($materials)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Materi</th>
                            <th>Pengunggah</th>
                            <th>Kategori & Format</th>
                            <th>Status & Reviewer</th>
                            <th>Aksi Otoritas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materials as $m): ?>
                            <tr>
                                <td>
                                    <strong><?= e($m['judul']) ?></strong>
                                    <div class="text-muted" style="font-size:11px; margin-top:2px;">
                                        ID: #<?= $m['id_material'] ?> • <?= date('d M Y, H:i', strtotime($m['tanggal_upload'])) ?>
                                    </div>
                                    <?php if (!empty($m['rejection_reason'])): ?>
                                        <div class="rejection-note" style="margin-top:4px;">
                                            Catatan: <?= e($m['rejection_reason']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><strong><?= e($m['nama']) ?></strong></div>
                                    <small class="text-muted"><?= e($m['email_pengunggah']) ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-light"><?= e($m['nama_kategori']) ?></span>
                                    <div style="margin-top:4px;">
                                        <span class="badge badge-outline"><?= strtoupper(e($m['format_file'])) ?></span>
                                        <small class="text-muted"><?= round(((int)$m['file_size']) / 1024, 1) ?> KB</small>
                                    </div>
                                </td>
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
                                    <?php if (!empty($m['nama_reviewer'])): ?>
                                        <div style="font-size:10px; color:#555; margin-top:3px;">Reviewer: <?= e($m['nama_reviewer']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="<?= e(url('detail.php?id=' . $m['id_material'])) ?>" class="btn-action btn-view" target="_blank">Lihat</a>

                                        <?php if ($m['status'] !== 'approved'): ?>
                                            <form method="post" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$m['id_material'] ?>">
                                                <input type="hidden" name="action" value="force_approve">
                                                <button type="submit" class="btn-action btn-edit" title="Force Approve">Setujui</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($m['status'] === 'approved'): ?>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Takedown materi ini?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$m['id_material'] ?>">
                                                <input type="hidden" name="action" value="takedown">
                                                <button type="submit" class="btn-action btn-delete">Takedown</button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="post" style="display:inline;" onsubmit="return confirm('PERINGATAN: Hapus materi dan file ini secara permanen dari server?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$m['id_material'] ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn-action btn-delete" style="color:#b91c1c;">Hapus</button>
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
                <p>Tidak ada materi yang ditemukan.</p>
            </div>
        <?php endif; ?>
    </div>

</main>
</div>
<script src="<?= e(url('assets/js/dashboard.js')) ?>"></script>
</body>
</html>
