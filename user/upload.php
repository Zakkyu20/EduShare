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

$categories = $pdo->query("SELECT id_category, nama_kategori FROM categories WHERE is_active = 1 ORDER BY nama_kategori")->fetchAll();

$errors = [];
$judul = '';
$deskripsi = '';
$idCategory = 0;
$formatFile = '';

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

    if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'File materi wajib diunggah.';
    } elseif ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Terjadi kesalahan saat mengunggah file. Kode: ' . $_FILES['file']['error'];
    } else {
        $maxBytes = 25 * 1024 * 1024; // 25MB
        if ($_FILES['file']['size'] > $maxBytes) {
            $errors[] = 'Ukuran file melebihi batas maksimal 25 MB.';
        }

        $uploadedExt = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if ($uploadedExt !== $formatFile && !($formatFile === 'doc' && $uploadedExt === 'docx') && !($formatFile === 'ppt' && $uploadedExt === 'pptx')) {
            $errors[] = "Ekstensi file (.{$uploadedExt}) tidak sesuai dengan format yang dipilih ({$formatFile}).";
        }
    }

    if (empty($errors)) {
        $uploadDir = ROOT_PATH . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $safeExt = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $newFilename = 'mat_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $safeExt;
        $destPath = $uploadDir . $newFilename;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $destPath)) {
            $fileSize = (int)$_FILES['file']['size'];

            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO materials (id_user, id_category, judul, deskripsi, nama_file, format_file, file_size, status, tanggal_upload)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
                );
                $stmt->execute([
                    $userId,
                    $idCategory,
                    $judul,
                    $deskripsi,
                    $newFilename,
                    $safeExt,
                    $fileSize
                ]);

                $newId = (int)$pdo->lastInsertId();

                log_activity($userId, 'upload_material', "Mengunggah materi: {$judul}", 'material', $newId);
                log_audit('material_uploaded', 'material', $newId, "Pengguna mengunggah materi \"{$judul}\"", [
                    'format' => $safeExt,
                    'size' => $fileSize,
                    'category_id' => $idCategory
                ]);

                set_flash('success', 'Materi berhasil diunggah dan sedang menunggu verifikasi oleh admin.');
                redirect(url('user/materi_saya.php'));
            } catch (Throwable $e) {
                @unlink($destPath);
                error_log('Upload error: ' . $e->getMessage());
                $errors[] = 'Gagal menyimpan data ke database. Silakan coba kembali.';
            }
        } else {
            $errors[] = 'Gagal memindahkan file yang diunggah ke direktori server.';
        }
    }
}

$pageTitle = 'Upload Materi';
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
            <span class="eyebrow">BAGIKAN PENGETAHUAN</span>
            <h1>Upload Materi Pembelajaran</h1>
            <p>Unggah catatan, modul, bahan ajar, atau referensi belajar Anda.</p>
        </div>
        <a href="<?= e(url('user/materi_saya.php')) ?>" class="btn btn-outline">
            Kembali ke Materi Saya
        </a>
    </div>

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
                <input type="text" id="judul" name="judul" class="form-control" placeholder="Contoh: Pengantar Pemrograman Berorientasi Objek" value="<?= e($judul) ?>" required maxlength="200">
                <small class="form-hint">Gunakan judul yang ringkas, jelas, dan menggambarkan isi materi.</small>
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
                        <option value="">-- Pilih Format --</option>
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
                <textarea id="deskripsi" name="deskripsi" class="form-control" rows="5" placeholder="Tuliskan gambaran ringkas mengenai topik dan pokok bahasan dalam materi ini..." required><?= e($deskripsi) ?></textarea>
            </div>

            <div class="form-group">
                <label for="file">Pilih Berkas Materi <span class="text-danger">*</span></label>
                <div class="file-upload-box">
                    <input type="file" id="file" name="file" required>
                    <p class="file-upload-tip">Maksimal ukuran file: <strong>25 MB</strong>. Format didukung: PDF, DOC, PPT, XLS, ZIP, MP4, TXT.</p>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-large">
                    Unggah Materi Sekarang
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
