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

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'deactivate_account') {
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        // Fetch user password hash
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id_user = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($confirmPassword, (string)$row['password'])) {
            $errors[] = 'Password konfirmasi tidak tepat. Akun tidak dinonaktifkan.';
        } else {
            // Set status to inactive
            $upd = $pdo->prepare("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id_user = ?");
            $upd->execute([$userId]);

            log_activity($userId, 'deactivate_account', 'Pengguna menonaktifkan akun sendiri', 'user', $userId);
            log_audit('account_deactivated', 'user', $userId, 'Pengguna menonaktifkan akun sendiri.');

            // Destroy session
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }

            set_flash('info', 'Akun Anda telah dinonaktifkan. Anda dapat menghubungi administrator jika ingin mengaktifkannya kembali.');
            redirect(url('login.php'));
        }
    }
}

$pageTitle = 'Pengaturan Akun';
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
            <span class="eyebrow">PREFERENSI & KEAMANAN</span>
            <h1>Pengaturan Akun</h1>
            <p>Kelola pengaturan privasi dan status akun pembelajaran Anda.</p>
        </div>
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

    <!-- Status & Info -->
    <div class="card" style="margin-bottom: 24px;">
        <h3 style="margin-top:0;">Status Akun</h3>
        <p class="text-muted">Informasi mengenai hak akses dan status operasional akun Anda di EduShare.</p>
        <div style="background:#faf8ff; border:1px solid #e0d8fa; padding:16px; border-radius:10px; margin-top:12px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div>
                    <strong>Peran Akses:</strong> <span class="badge badge-primary">PENGGUNA (USER)</span>
                </div>
                <div>
                    <strong>Status:</strong> <span class="badge badge-success">AKTIF</span>
                </div>
                <div>
                    <strong>ID Pengguna:</strong> #<?= $userId ?>
                </div>
            </div>
            <p style="margin:12px 0 0; font-size:12px; color:#555;">
                Sebagai Pengguna, Anda memiliki akses penuh untuk menjelajahi materi, mengunggah materi baru, mengedit/menghapus materi milik sendiri yang belum disetujui, serta mengunduh seluruh materi yang dipublikasikan.
            </p>
        </div>
    </div>

    <!-- Danger Zone: Deactivate Account -->
    <div class="card" style="border: 1px solid #fed7d7;">
        <h3 style="color:#e53e3e; margin-top:0;">Zona Berbahaya: Nonaktifkan Akun</h3>
        <p class="text-muted" style="font-size:13px;">
            Menonaktifkan akun akan mencegah Anda masuk kembali ke platform EduShare. Materi yang sudah dipublikasikan akan tetap dapat diakses kecuali Anda meminta penghapusan kepada administrator.
        </p>

        <form method="post" onsubmit="return confirm('Apakah Anda benar-benar yakin ingin menonaktifkan akun Anda? Tindakan ini akan mengakhiri sesi Anda.');" style="margin-top:16px; max-width:400px;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="deactivate_account">

            <div class="form-group">
                <label for="confirm_password">Masukkan Password Anda untuk Konfirmasi</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder="Password Anda">
            </div>

            <button type="submit" class="btn btn-danger">
                Nonaktifkan Akun Saya
            </button>
        </form>
    </div>

</main>
</div>
<script src="<?= e(url('assets/js/dashboard.js')) ?>"></script>
</body>
</html>
