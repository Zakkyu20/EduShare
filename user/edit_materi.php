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

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'ID materi tidak valid.');
    redirect(url('user/materi_saya.php'));
}

$stmt = $pdo->prepare("SELECT * FROM materials WHERE id_material = ? AND id_user = ?");
$stmt->execute([$id, $userId]);
$materi = $stmt->fetch();

if (!$materi) {
    set_flash('error', 'Materi tidak ditemukan atau Anda tidak memiliki akses.');
    redirect(url('user/materi_saya.php'));
}

// Only pending or rejected can be edited
if (!in_array($materi['status'], ['pending', 'rejected'], true)) {
    set_flash('error', 'Materi yang sudah disetujui tidak dapat diedit langsung. Silakan hubungi admin.');
    redirect(url('user/materi_saya.php'));
}

$categories = $pdo->query("SELECT id_category, nama_kategori FROM categories WHERE is_active = 1 ORDER BY nama_kategori")->fetchAll();

$errors = [];
$judul = (string)$materi['judul'];
$deskripsi = (string)$materi['deskripsi'];
$idCategory = (int)$materi['id_category'];
$formatFile = (string)$materi['format_file'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $judul = trim((string)($_POST['judul'] ?? ''));
    $deskripsi = trim((string)($_POST['deskripsi'] ?? ''));
    $idCategory = (int)($_POST['id_category'] ?? 0);
    $formatFile = strtolower(trim((string)($_POST['format_file'] ?? '')));

    if ($judul === '') {
        $errors[] = 'Judul materi wajib diisi.';
    } elseif (mb_strlen($judul) > 200) {
        $errors[] = 'Judul materi maksimal 200 karakter.';
    }

    if ($deskripsi === '') {
        $errors[] = 'Deskripsi materi wajib diisi.';
    }

    if ($idCategory <= 0) {
        $errors[] = 'Pilih kategori pembelajaran yang valid.';
    }

    $allowedFormats = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip', 'mp4'];
    if (!in_array($formatFile, $allowedFormats, true)) {
        $errors[] = 'Format file tidak didukung.';
    }

    $newFileUploaded = false;
    $newFilename = $materi['nama_file'];
    $fileSize = (int)$materi['file_size'];

    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $maxBytes = 25 * 1024 * 1024;
        if ($_FILES['file']['size'] > $maxBytes) {
            $errors[] = 'Ukuran berkas baru melebihi batas 25 MB.';
        }

        $uploadedExt = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if ($uploadedExt !== $formatFile && !($formatFile === 'doc' && $uploadedExt === 'docx') && !($formatFile === 'ppt' && $uploadedExt === 'pptx')) {
            $errors[] = "Ekstensi file (.{$uploadedExt}) tidak cocok dengan format yang dipilih ({$formatFile}).";
        }

        if (empty($errors)) {
            $uploadDir = ROOT_PATH . '/uploads/';
            $newExt = $uploadedExt;
            $newFilename = 'mat_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $newExt;
            $destPath = $uploadDir . $newFilename;

            if (move_uploaded_file($_FILES['file']['tmp_name'], $destPath)) {
                $fileSize = (int)$_FILES['file']['size'];
                $newFileUploaded = true;
                // Delete old file
                safe_unlink_upload($materi['nama_file']);
            } else {
                $errors[] = 'Gagal menyimpan berkas baru ke direktori server.';
            }
        }
    }

    if (empty($errors)) {
        try {
            // If was rejected, reset to pending for re-review
            $newStatus = 'pending';
            $stmt = $pdo->prepare(
                "UPDATE materials 
                 SET id_category = ?, judul = ?, deskripsi = ?, nama_file = ?, format_file = ?, file_size = ?, status = ?, rejection_reason = NULL, updated_at = NOW()
                 WHERE id_material = ? AND id_user = ?"
            );
            $stmt->execute([
                $idCategory,
                $judul,
                $deskripsi,
                $newFilename,
                $formatFile,
                $fileSize,
                $newStatus,
                $id,
                $userId
            ]);

            log_activity($userId, 'edit_material', "Mengubah materi: {$judul}", 'material', $id);
            log_audit('material_updated', 'material', $id, "Pengguna memperbarui materi \"{$judul}\"", [
                'file_replaced' => $newFileUploaded,
                'status_reset' => ($materi['status'] === 'rejected') ? 'pending' : $materi['status']
            ]);

            set_flash('success', 'Perubahan materi berhasil disimpan. Status materi sekarang Menunggu Verifikasi.');
            redirect(url('user/materi_saya.php'));
        } catch (Throwable $e) {
            error_log('Edit materi error: ' . $e->getMessage());
            $errors[] = 'Terjadi kesalahan saat memperbarui database.';
        }
    }
}

$pageTitle = 'Edit Materi';
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
            <span class="eyebrow">PENGELOLAAN KONTEN</span>
            <h1>Edit Materi Pembelajaran</h1>
            <p>Perbarui informasi materi atau unggah revisi berkas materi Anda.</p>
        </div>
        <a href="<?= e(url('user/materi_saya.php')) ?>" class="btn btn-outline">
            Kembali ke Materi Saya
        </a>
    </div>

    <?php if ($materi['status'] === 'rejected' && !empty($materi['rejection_reason'])): ?>
        <div class="flash-box error" style="margin-bottom:20px;">
            <strong>Catatan Penolakan dari Admin:</strong>
            <p style="margin:4px 0 0;"><?= e($materi['rejection_reason']) ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="flash-box error">
            <strong>Terjadi kesalahan:</strong>
            <ul style="margin: 8px 0 0 20px; padding: 0;">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card card-form">
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="judul">Judul Materi <span class="text-danger">*</span></label>
                <input type="text" id="judul" name="judul" class="form-control" value="<?= e($judul) ?>" required maxlength="200">
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="id_category">Kategori Pembelajaran <span class="text-danger">*</span></label>
                    <select id="id_category" name="id_category" class="form-control" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id_category'] ?>" <?= $idCategory === (int)$cat['id_category'] ? 'selected' : '' ?>>
                                <?= e($cat['nama_kategori']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label for="format_file">Format File <span class="text-danger">*</span></label>
                    <select id="format_file" name="format_file" class="form-control" required>
                        <option value="pdf" <?= $formatFile === 'pdf' ? 'selected' : '' ?>>PDF Document (.pdf)</option>
                        <option value="doc" <?= $formatFile === 'doc' ? 'selected' : '' ?>>Word Document (.doc, .docx)</option>
                        <option value="ppt" <?= $formatFile === 'ppt' ? 'selected' : '' ?>>PowerPoint Presentation (.ppt, .pptx)</option>
                        <option value="xls" <?= $formatFile === 'xls' ? 'selected' : '' ?>>Excel Spreadsheet (.xls, .xlsx)</option>
                        <option value="zip" <?= $formatFile === 'zip' ? 'selected' : '' ?>>Archive Zip (.zip)</option>
                        <option value="mp4" <?= $formatFile === 'mp4' ? 'selected' : '' ?>>Video Pembelajaran (.mp4)</option>
                        <option value="txt" <?= $formatFile === 'txt' ? 'selected' : '' ?>>Plain Text (.txt)</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="deskripsi">Deskripsi & Rangkuman Materi <span class="text-danger">*</span></label>
                <textarea id="deskripsi" name="deskripsi" class="form-control" rows="5" required><?= e($deskripsi) ?></textarea>
            </div>

            <div class="form-group">
                <label>Berkas Saat Ini</label>
                <div style="background:#f4f2ff; padding:10px 14px; border-radius:8px; font-size:13px; color:#4338ca; margin-bottom:10px;">
                    <strong><?= e($materi['nama_file']) ?></strong> (Format: <?= strtoupper(e($materi['format_file'])) ?>)
                </div>
                <label for="file">Ganti Berkas (Opsional)</label>
                <div class="file-upload-box">
                    <input type="file" id="file" name="file">
                    <p class="file-upload-tip">Kosongkan jika tidak ingin mengubah berkas saat ini.</p>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-large">
                    Simpan Perubahan
                </button>
                <a href="<?= e(url('user/materi_saya.php')) ?>" class="btn btn-outline">Batal</a>
            </div>
        </form>
    </div>

</main>
</div>
<script src="<?= e(url('assets/js/dashboard.js')) ?>"></script>
</body>
</html>
