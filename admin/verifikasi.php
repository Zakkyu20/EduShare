<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_admin();

$user = current_user();
$adminId = (int)$user['id_user'];
$pdo = db();

// Handle moderation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $action = trim((string)($_POST['action'] ?? ''));
    $reason = trim((string)($_POST['rejection_reason'] ?? ''));

    if ($id <= 0) {
        set_flash('error', 'ID materi tidak valid.');
        redirect(url('admin/verifikasi.php'));
    }

    $chk = $pdo->prepare("SELECT id_material, judul, status FROM materials WHERE id_material = ?");
    $chk->execute([$id]);
    $mat = $chk->fetch();

    if (!$mat) {
        set_flash('error', 'Materi tidak ditemukan.');
        redirect(url('admin/verifikasi.php'));
    }

    if ($action === 'approve') {
        $upd = $pdo->prepare(
            "UPDATE materials 
             SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), rejection_reason = NULL, updated_at = NOW()
             WHERE id_material = ?"
        );
        $upd->execute([$adminId, $id]);

        log_activity($adminId, 'approve_material', "Menyetujui materi: {$mat['judul']}", 'material', $id);
        log_audit('material_approved', 'material', $id, "Admin menyetujui materi \"{$mat['judul']}\".", [
            'previous_status' => $mat['status']
        ]);

        set_flash('success', "Materi \"{$mat['judul']}\" berhasil disetujui dan kini aktif dipublikasikan.");
    } elseif ($action === 'reject') {
        if ($reason === '') {
            $reason = 'Materi tidak memenuhi kriteria pedoman pembelajaran EduShare.';
        }

        $upd = $pdo->prepare(
            "UPDATE materials 
             SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), rejection_reason = ?, updated_at = NOW()
             WHERE id_material = ?"
        );
        $upd->execute([$adminId, $reason, $id]);

        log_activity($adminId, 'reject_material', "Menolak materi: {$mat['judul']} (Alasan: {$reason})", 'material', $id);
        log_audit('material_rejected', 'material', $id, "Admin menolak materi \"{$mat['judul']}\".", [
            'reason' => $reason,
            'previous_status' => $mat['status']
        ]);

        set_flash('info', "Materi \"{$mat['judul']}\" telah ditolak dengan catatan alasan penolakan.");
    }

    redirect(url('admin/verifikasi.php'));
}

$tab = trim($_GET['tab'] ?? 'pending');
$validTabs = ['pending', 'all', 'approved', 'rejected'];
if (!in_array($tab, $validTabs, true)) {
    $tab = 'pending';
}

$sql = "SELECT m.*, u.nama AS pengunggah, u.email AS email_pengunggah, c.nama_kategori,
               adm.nama AS nama_reviewer
        FROM materials m 
        JOIN users u ON u.id_user = m.id_user 
        JOIN categories c ON c.id_category = m.id_category 
        LEFT JOIN users adm ON adm.id_user = m.reviewed_by";

if ($tab === 'pending') {
    $sql .= " WHERE m.status = 'pending'";
} elseif ($tab === 'approved') {
    $sql .= " WHERE m.status = 'approved'";
} elseif ($tab === 'rejected') {
    $sql .= " WHERE m.status = 'rejected'";
}
$sql .= " ORDER BY (m.status = 'pending') DESC, m.tanggal_upload DESC";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll();

// Counts for tabs
$counts = [
    'pending' => (int)$pdo->query("SELECT COUNT(*) FROM materials WHERE status = 'pending'")->fetchColumn(),
    'approved' => (int)$pdo->query("SELECT COUNT(*) FROM materials WHERE status = 'approved'")->fetchColumn(),
    'rejected' => (int)$pdo->query("SELECT COUNT(*) FROM materials WHERE status = 'rejected'")->fetchColumn(),
    'all' => (int)$pdo->query("SELECT COUNT(*) FROM materials")->fetchColumn(),
];

$pageTitle = 'Verifikasi Materi';
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
            <span class="eyebrow">MODERASI KONTEN</span>
            <h1>Verifikasi Materi Pembelajaran</h1>
            <p>Tinjau materi yang diunggah pengguna sebelum dipublikasikan ke seluruh platform.</p>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-bar">
        <div class="tab-pills">
            <a href="<?= e(url('admin/verifikasi.php?tab=pending')) ?>" class="tab-pill <?= $tab === 'pending' ? 'active' : '' ?>">
                Menunggu Verifikasi (<?= $counts['pending'] ?>)
            </a>
            <a href="<?= e(url('admin/verifikasi.php?tab=approved')) ?>" class="tab-pill <?= $tab === 'approved' ? 'active' : '' ?>">
                Telah Disetujui (<?= $counts['approved'] ?>)
            </a>
            <a href="<?= e(url('admin/verifikasi.php?tab=rejected')) ?>" class="tab-pill <?= $tab === 'rejected' ? 'active' : '' ?>">
                Ditolak (<?= $counts['rejected'] ?>)
            </a>
            <a href="<?= e(url('admin/verifikasi.php?tab=all')) ?>" class="tab-pill <?= $tab === 'all' ? 'active' : '' ?>">
                Semua (<?= $counts['all'] ?>)
            </a>
        </div>
    </div>

    <div class="card">
        <?php if (!empty($rows)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Materi</th>
                            <th>Pengunggah</th>
                            <th>Kategori & Format</th>
                            <th>Tanggal Masuk</th>
                            <th>Status</th>
                            <th>Tindakan Verifikasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td>
                                    <strong><?= e($r['judul']) ?></strong>
                                    <div class="text-muted" style="font-size:12px; margin-top:4px; max-width:320px;">
                                        <?= e(mb_strimwidth($r['deskripsi'] ?? '', 0, 100, '...')) ?>
                                    </div>
                                    <?php if (!empty($r['rejection_reason'])): ?>
                                        <div class="rejection-note" style="margin-top:6px;">
                                            <strong>Alasan Ditolak:</strong> <?= e($r['rejection_reason']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><strong><?= e($r['nama']) ?></strong></div>
                                    <small class="text-muted"><?= e($r['email_pengunggah']) ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-light"><?= e($r['nama_kategori']) ?></span>
                                    <div style="margin-top:4px;">
                                        <span class="badge badge-outline"><?= strtoupper(e($r['format_file'])) ?></span>
                                        <small class="text-muted"><?= round(((int)$r['file_size']) / 1024, 1) ?> KB</small>
                                    </div>
                                </td>
                                <td><?= date('d M Y, H:i', strtotime($r['tanggal_upload'])) ?></td>
                                <td>
                                    <?php if ($r['status'] === 'approved'): ?>
                                        <span class="badge badge-success">Disetujui</span>
                                        <?php if (!empty($r['nama_reviewer'])): ?>
                                            <div style="font-size:10px; color:#555; margin-top:2px;">Oleh: <?= e($r['nama_reviewer']) ?></div>
                                        <?php endif; ?>
                                    <?php elseif ($r['status'] === 'pending'): ?>
                                        <span class="badge badge-warning">Menunggu</span>
                                    <?php elseif ($r['status'] === 'rejected'): ?>
                                        <span class="badge badge-danger">Ditolak</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><?= e($r['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- Detail preview button -->
                                        <a href="<?= e(url('detail.php?id=' . $r['id_material'])) ?>" class="btn-action btn-view" target="_blank" title="Lihat detail">Preview</a>

                                        <?php if ($r['status'] === 'pending'): ?>
                                            <!-- Approve Form -->
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Setujui materi ini untuk dipublikasikan?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$r['id_material'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-success btn-small">Setujui</button>
                                            </form>

                                            <!-- Reject with Reason Trigger -->
                                            <button type="button" class="btn btn-danger btn-small" onclick="openRejectModal(<?= (int)$r['id_material'] ?>, '<?= e(addslashes($r['judul'])) ?>')">
                                                Tolak...
                                            </button>
                                        <?php elseif ($r['status'] === 'rejected'): ?>
                                            <!-- Option to approve previously rejected -->
                                            <form method="post" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$r['id_material'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-outline btn-small">Setujui Ulang</button>
                                            </form>
                                        <?php elseif ($r['status'] === 'approved'): ?>
                                            <!-- Option to revoke / reject -->
                                            <button type="button" class="btn btn-outline btn-small" style="color:#e53e3e;" onclick="openRejectModal(<?= (int)$r['id_material'] ?>, '<?= e(addslashes($r['judul'])) ?>')">
                                                Cabut Publikasi
                                            </button>
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
                
                <h3>Tidak ada antrean materi pada kategori ini</h3>
                <p>Seluruh materi telah selesai diproses.</p>
            </div>
        <?php endif; ?>
    </div>

</main>
</div>

<!-- Modal Reject with Reason -->
<div id="rejectModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3 id="modalTitle">Tolak Materi Pembelajaran</h3>
        <p id="modalDesc" class="text-muted" style="font-size:13px;"></p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="rejectMaterialId" value="">
            <input type="hidden" name="action" value="reject">

            <div class="form-group" style="margin-top:16px;">
                <label for="rejection_reason">Alasan Penolakan <span class="text-danger">*</span></label>
                <textarea id="rejection_reason" name="rejection_reason" class="form-control" rows="4" required placeholder="Contoh: Dokumen tidak lengkap, topik tidak sesuai kategori, atau format tidak dapat dibuka."></textarea>
                <small class="form-hint">Alasan ini akan ditampilkan kepada pengunggah materi agar dapat diperbaiki.</small>
            </div>

            <div class="form-actions" style="margin-top:20px; display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeRejectModal()">Batal</button>
                <button type="submit" class="btn btn-danger">Tolak Materi</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= e(url('assets/js/dashboard.js')) ?>"></script>
<script>
function openRejectModal(id, title) {
    document.getElementById('rejectMaterialId').value = id;
    document.getElementById('modalDesc').innerText = 'Anda akan menolak materi: "' + title + '"';
    document.getElementById('rejectModal').style.display = 'flex';
}
function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}
</script>
</body>
</html>
