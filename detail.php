<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

$id = (int)($_GET['id'] ?? 0);
$pdo = db();

$stmt = $pdo->prepare(
    "SELECT m.*, c.nama_kategori, u.nama AS pengunggah 
     FROM materials m 
     JOIN categories c ON c.id_category = m.id_category 
     JOIN users u ON u.id_user = m.id_user 
     WHERE m.id_material = ?"
);
$stmt->execute([$id]);
$m = $stmt->fetch();

// If not found or not approved (and not owner or admin), show 404
$user = current_user();
$canViewUnapproved = false;
if ($user) {
    if (in_array($user['role'], ['admin', 'super_admin'], true) || (int)$m['id_user'] === (int)$user['id_user']) {
        $canViewUnapproved = true;
    }
}

if (!$m || ($m['status'] !== 'approved' && !$canViewUnapproved)) {
    http_response_code(404);
    $layoutTitle = 'Materi tidak ditemukan';
    include __DIR__ . '/partials/header.php';
    echo '<div class="empty-state"><h2>Materi tidak ditemukan</h2><p>Materi mungkin belum disetujui atau telah dihapus.</p><a href="' . e(url('materi.php')) . '" class="btn btn-primary" style="margin-top:12px;">Kembali ke Daftar Materi</a></div>';
    include __DIR__ . '/partials/footer.php';
    exit;
}

$layoutTitle = $m['judul'];
include __DIR__ . '/partials/header.php';
?>
<div class="detail-layout">
    <article class="detail-main">
        <a class="back-link" href="<?= e(url('materi.php')) ?>">Kembali ke materi</a>
        <div class="detail-cover">
            <span><?= e(strtoupper($m['format_file'])) ?></span>
            <small>EDUSHARE LEARNING RESOURCE</small>
        </div>

        <?php if ($m['status'] !== 'approved'): ?>
            <div style="background:#fffbeb; border:1px solid #fef08a; padding:10px 14px; border-radius:8px; margin: 15px 0;">
                <span class="badge badge-warning">Status: <?= strtoupper(e($m['status'])) ?></span>
                <span style="font-size:12px; color:#854d0e; margin-left:8px;">Hanya dapat dilihat oleh pengunggah dan administrator.</span>
            </div>
        <?php endif; ?>

        <span class="eyebrow"><?= e($m['nama_kategori']) ?></span>
        <h1><?= e($m['judul']) ?></h1>
        <p class="detail-description"><?= nl2br(e($m['deskripsi'])) ?></p>
        <div class="detail-info">
            <span>Diunggah oleh <b><?= e($m['pengunggah']) ?></b></span>
            <span><?= date('d M Y', strtotime($m['tanggal_upload'])) ?></span>
            <span>Format <?= e(strtoupper($m['format_file'])) ?></span>
            <?php if (!empty($m['download_count'])): ?>
                <span>Diunduh <?= (int)$m['download_count'] ?> kali</span>
            <?php endif; ?>
        </div>
    </article>

    <aside class="download-panel">
        <h3>Materi Pembelajaran</h3>
        <p>File ini dibagikan untuk menunjang proses belajar bersama.</p>

        <?php if ($m['status'] === 'approved'): ?>
            <?php if (is_logged_in()): ?>
                <a class="btn btn-primary btn-block" href="<?= e(url('download.php?id=' . $m['id_material'])) ?>">
                    Unduh Berkas Materi
                </a>
                <small style="display:block; text-align:center; margin-top:8px; color:var(--muted); font-size:11px;">
                    Ukuran: <?= round(((int)$m['file_size']) / 1024, 1) ?> KB • Akses gratis
                </small>
            <?php else: ?>
                <a class="btn btn-primary btn-block" href="<?= e(url('login.php')) ?>">
                    Login untuk Mengunduh
                </a>
                <small style="display:block; text-align:center; margin-top:8px; color:var(--muted); font-size:11px;">
                    Silakan masuk ke akun EduShare Anda untuk mengunduh file ini. Belum punya akun? <a href="<?= e(url('register.php')) ?>">Daftar</a>
                </small>
            <?php endif; ?>
        <?php else: ?>
            <div style="background:#fee2e2; color:#991b1b; padding:12px; border-radius:8px; font-size:12px;">
                Berkas materi belum dapat diunduh karena masih dalam status verifikasi/takedown.
            </div>
        <?php endif; ?>
    </aside>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
