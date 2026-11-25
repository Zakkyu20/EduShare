<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_super_admin();

$user = current_user();
$superAdminId = (int)$user['id_user'];
$pdo = db();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $formAction = trim((string)($_POST['form_action'] ?? ''));

    if ($formAction === 'add') {
        $namaKategori = trim((string)($_POST['nama_kategori'] ?? ''));
        $deskripsi = trim((string)($_POST['deskripsi'] ?? ''));

        if ($namaKategori === '') {
            $errors[] = 'Nama kategori wajib diisi.';
        } else {
            $ins = $pdo->prepare("INSERT INTO categories (nama_kategori, deskripsi, is_active) VALUES (?, ?, 1)");
            $ins->execute([$namaKategori, $deskripsi]);
            $newId = (int)$pdo->lastInsertId();

            log_activity($superAdminId, 'add_category', "Super Admin menambahkan kategori: {$namaKategori}", 'category', $newId);
            log_audit('category_created', 'category', $newId, "Super Admin membuat kategori \"{$namaKategori}\".");

            set_flash('success', "Kategori \"{$namaKategori}\" berhasil ditambahkan.");
            redirect(url('superadmin/kategori.php'));
        }
    } elseif ($formAction === 'edit') {
        $id = (int)($_POST['id_category'] ?? 0);
        $namaKategori = trim((string)($_POST['nama_kategori'] ?? ''));
        $deskripsi = trim((string)($_POST['deskripsi'] ?? ''));

        if ($id > 0 && $namaKategori !== '') {
            $upd = $pdo->prepare("UPDATE categories SET nama_kategori = ?, deskripsi = ? WHERE id_category = ?");
            $upd->execute([$namaKategori, $deskripsi, $id]);

            log_audit('category_updated', 'category', $id, "Super Admin memperbarui kategori \"{$namaKategori}\".");
            set_flash('success', "Kategori \"{$namaKategori}\" berhasil diperbarui.");
            redirect(url('superadmin/kategori.php'));
        }
    } elseif ($formAction === 'toggle') {
        $id = (int)($_POST['id_category'] ?? 0);
        $status = (int)($_POST['status'] ?? 0);
        $newStatus = ($status === 1) ? 0 : 1;

        $upd = $pdo->prepare("UPDATE categories SET is_active = ? WHERE id_category = ?");
        $upd->execute([$newStatus, $id]);

        log_audit('category_toggled', 'category', $id, "Super Admin mengubah status kategori ID {$id} ke {$newStatus}.");
        set_flash('info', 'Status kategori berhasil diperbarui.');
        redirect(url('superadmin/kategori.php'));
    } elseif ($formAction === 'delete') {
        $id = (int)($_POST['id_category'] ?? 0);

        // Count materials
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM materials WHERE id_category = {$id}")->fetchColumn();
        if ($cnt > 0) {
            set_flash('error', "Kategori ini masih memiliki {$cnt} materi yang terhubung. Pindahkan atau hapus materi terlebih dahulu.");
        } else {
            $del = $pdo->prepare("DELETE FROM categories WHERE id_category = ?");
            $del->execute([$id]);

            log_audit('category_deleted', 'category', $id, "Super Admin menghapus permanen kategori ID {$id}.");
            set_flash('success', 'Kategori berhasil dihapus secara permanen.');
        }
        redirect(url('superadmin/kategori.php'));
    }
}

$categories = $pdo->query(
    "SELECT c.*, 
            COUNT(m.id_material) AS total_materi,
            SUM(CASE WHEN m.status = 'approved' THEN 1 ELSE 0 END) AS total_approved
     FROM categories c 
     LEFT JOIN materials m ON m.id_category = c.id_category 
     GROUP BY c.id_category 
     ORDER BY c.nama_kategori ASC"
)->fetchAll();

$pageTitle = 'Manajemen Kategori (Super Admin)';
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
            <span class="eyebrow" style="color:var(--gold);">KLASIFIKASI KONTEN</span>
            <h1>Manajemen Kategori</h1>
            <p>Kelola seluruh taksonomi kategori materi pembelajaran di platform.</p>
        </div>
        <button type="button" class="btn btn-primary" onclick="openAddModal()">
            Tambah Kategori
        </button>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="flash-box error">
            <ul style="margin:0; padding-left:16px;">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th>Deskripsi</th>
                        <th>Materi Terhubung</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td><strong><?= e($c['nama_kategori']) ?></strong></td>
                            <td style="max-width:300px;"><?= e($c['deskripsi'] ?? '-') ?></td>
                            <td>
                                <strong><?= (int)$c['total_materi'] ?></strong> materi
                                <small class="text-muted">(<?= (int)$c['total_approved'] ?> approved)</small>
                            </td>
                            <td>
                                <?php if ((int)$c['is_active'] === 1): ?>
                                    <span class="badge badge-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="btn-action btn-edit" onclick="openEditModal(<?= (int)$c['id_category'] ?>, '<?= e(addslashes($c['nama_kategori'])) ?>', '<?= e(addslashes($c['deskripsi'] ?? '')) ?>')">
                                        Edit
                                    </button>

                                    <form method="post" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="form_action" value="toggle">
                                        <input type="hidden" name="id_category" value="<?= (int)$c['id_category'] ?>">
                                        <input type="hidden" name="status" value="<?= (int)$c['is_active'] ?>">
                                        <button type="submit" class="btn-action <?= (int)$c['is_active'] === 1 ? 'btn-view' : 'btn-edit' ?>">
                                            <?= (int)$c['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>
                                        </button>
                                    </form>

                                    <form method="post" style="display:inline;" onsubmit="return confirm('Hapus kategori ini secara permanen?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="form_action" value="delete">
                                        <input type="hidden" name="id_category" value="<?= (int)$c['id_category'] ?>">
                                        <button type="submit" class="btn-action btn-delete">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>
</div>

<!-- Modal Add/Edit Category -->
<div id="categoryModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3 id="catModalTitle">Tambah Kategori</h3>
        <form method="post" style="margin-top:16px;">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" id="catFormAction" value="add">
            <input type="hidden" name="id_category" id="catId" value="">

            <div class="form-group">
                <label for="modalNamaKategori">Nama Kategori <span class="text-danger">*</span></label>
                <input type="text" id="modalNamaKategori" name="nama_kategori" class="form-control" required maxlength="100">
            </div>

            <div class="form-group">
                <label for="modalDeskripsi">Deskripsi</label>
                <textarea id="modalDeskripsi" name="deskripsi" class="form-control" rows="3"></textarea>
            </div>

            <div class="form-actions" style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-outline" onclick="closeCategoryModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= e(url('assets/js/dashboard.js')) ?>"></script>
<script>
function openAddModal() {
    document.getElementById('catModalTitle').innerText = 'Tambah Kategori';
    document.getElementById('catFormAction').value = 'add';
    document.getElementById('catId').value = '';
    document.getElementById('modalNamaKategori').value = '';
    document.getElementById('modalDeskripsi').value = '';
    document.getElementById('categoryModal').style.display = 'flex';
}
function openEditModal(id, nama, desc) {
    document.getElementById('catModalTitle').innerText = 'Edit Kategori';
    document.getElementById('catFormAction').value = 'edit';
    document.getElementById('catId').value = id;
    document.getElementById('modalNamaKategori').value = nama;
    document.getElementById('modalDeskripsi').value = desc;
    document.getElementById('categoryModal').style.display = 'flex';
}
function closeCategoryModal() {
    document.getElementById('categoryModal').style.display = 'none';
}
</script>
</body>
</html>
