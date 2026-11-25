<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_admin();

$user = current_user();
$adminId = (int)$user['id_user'];
$pdo = db();

$stmt = $pdo->prepare("SELECT * FROM users WHERE id_user = ?");
$stmt->execute([$adminId]);
$profile = $stmt->fetch();

$errorsProfile = [];
$errorsPassword = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = trim((string)($_POST['form_action'] ?? ''));

    if ($action === 'update_profile') {
        $nama = trim((string)($_POST['nama'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));

        if ($nama === '' || mb_strlen($nama) > 100) {
            $errorsProfile[] = 'Nama administrator wajib diisi.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorsProfile[] = 'Alamat email tidak valid.';
        }

        if (empty($errorsProfile)) {
            $check = $pdo->prepare("SELECT id_user FROM users WHERE email = ? AND id_user != ?");
            $check->execute([$email, $adminId]);
            if ($check->fetch()) {
                $errorsProfile[] = 'Alamat email sudah digunakan oleh akun lain.';
            } else {
                $upd = $pdo->prepare("UPDATE users SET nama = ?, email = ?, updated_at = NOW() WHERE id_user = ?");
                $upd->execute([$nama, $email, $adminId]);

                $_SESSION['user']['nama'] = $nama;
                $_SESSION['user']['email'] = $email;
                clear_auth_cache();

                log_activity($adminId, 'update_admin_profile', "Memperbarui profil administrator: {$nama}", 'user', $adminId);
                log_audit('admin_profile_updated', 'user', $adminId, "Administrator memperbarui data profil.");

                set_flash('success', 'Profil Administrator berhasil diperbarui.');
                redirect(url('admin/profil.php'));
            }
        }
    } elseif ($action === 'change_password') {
        $currentPass = (string)($_POST['current_password'] ?? '');
        $newPass = (string)($_POST['new_password'] ?? '');
        $confirmPass = (string)($_POST['confirm_password'] ?? '');

        if (!password_verify($currentPass, (string)$profile['password'])) {
            $errorsPassword[] = 'Password saat ini salah.';
        }

        if (mb_strlen($newPass) < 8) {
            $errorsPassword[] = 'Password baru minimal 8 karakter.';
        }

        if ($newPass !== $confirmPass) {
            $errorsPassword[] = 'Konfirmasi password baru tidak cocok.';
        }

        if (empty($errorsPassword)) {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id_user = ?");
            $upd->execute([$hash, $adminId]);

            log_activity($adminId, 'change_admin_password', 'Mengubah password administrator', 'user', $adminId);
            log_audit('admin_password_changed', 'user', $adminId, "Administrator mengubah password akun.");

            set_flash('success', 'Password berhasil diubah.');
            redirect(url('admin/profil.php'));
        }
    }
}

// Moderation stats
$totalReviewed = (int)$pdo->query("SELECT COUNT(*) FROM materials WHERE reviewed_by = {$adminId}")->fetchColumn();

$pageTitle = 'Profil Admin';
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
            <span class="eyebrow">PENGATURAN AKUN OPERASIONAL</span>
            <h1>Profil Administrator</h1>
            <p>Kelola data akun pengelola operasional EduShare.</p>
        </div>
    </div>

    <!-- Overview Card -->
    <div class="card profile-header-card">
        <div class="profile-header-main">
            <div class="profile-avatar-large" style="background: linear-gradient(135deg, #4338ca 0%, #6366f1 100%);">
                <?= strtoupper(substr($profile['nama'], 0, 1)) ?>
            </div>
            <div class="profile-meta-info">
                <h2><?= e($profile['nama']) ?></h2>
                <div class="profile-tags">
                    <span class="badge badge-primary">ADMINISTRATOR</span>
                    <span class="badge badge-success"><span class="status-dot"></span>Status: Aktif</span>
                    <span class="profile-date">Terdaftar sejak <?= date('d M Y', strtotime($profile['created_at'])) ?></span>
                </div>
            </div>
        </div>
        <div class="profile-stats-bar">
            <div class="profile-stat-item">
                <span class="stat-number"><?= $totalReviewed ?></span>
                <span class="stat-desc">Total Materi Diverifikasi</span>
            </div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
        <!-- Edit Profile -->
        <div class="card card-form">
            <h3 style="margin-top:0; margin-bottom:16px;">Data Akun Admin</h3>
            <?php if (!empty($errorsProfile)): ?>
                <div class="flash-box error">
                    <ul style="margin:0; padding-left:16px;">
                        <?php foreach ($errorsProfile as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="update_profile">

                <div class="form-group">
                    <label for="nama">Nama Lengkap</label>
                    <input type="text" id="nama" name="nama" class="form-control" value="<?= e($profile['nama']) ?>" required maxlength="100">
                </div>

                <div class="form-group">
                    <label for="email">Alamat Email</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?= e($profile['email']) ?>" required maxlength="150">
                </div>

                <button type="submit" class="btn btn-primary">Simpan Profil Admin</button>
            </form>
        </div>

        <!-- Change Password -->
        <div class="card card-form">
            <h3 style="margin-top:0; margin-bottom:16px;">Ganti Password</h3>
            <?php if (!empty($errorsPassword)): ?>
                <div class="flash-box error">
                    <ul style="margin:0; padding-left:16px;">
                        <?php foreach ($errorsPassword as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="change_password">

                <div class="form-group">
                    <label for="current_password">Password Saat Ini</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="new_password">Password Baru</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required placeholder="Minimal 8 karakter">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password Baru</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary">Ubah Password</button>
            </form>
        </div>
    </div>

</main>
</div>
<script src="<?= e(url('assets/js/dashboard.js')) ?>"></script>
</body>
</html>
