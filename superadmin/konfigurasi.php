<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_super_admin();

$user = current_user();
$superAdminId = (int)$user['id_user'];
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $settings = [
        'site_name'            => trim((string)($_POST['site_name'] ?? 'EduShare')),
        'site_description'     => trim((string)($_POST['site_description'] ?? '')),
        'allow_registration'   => isset($_POST['allow_registration']) ? '1' : '0',
        'maintenance_mode'     => isset($_POST['maintenance_mode']) ? '1' : '0',
        'min_password_length'  => (string)max(6, min(32, (int)($_POST['min_password_length'] ?? 8))),
        'session_timeout_min'  => (string)max(5, min(1440, (int)($_POST['session_timeout_min'] ?? 60))),
    ];

    $stmt = $pdo->prepare(
        "INSERT INTO system_settings (setting_key, setting_value, updated_by, updated_at) 
         VALUES (?, ?, ?, NOW()) 
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()"
    );

    foreach ($settings as $key => $val) {
        $stmt->execute([$key, $val, $superAdminId]);
    }

    log_activity($superAdminId, 'update_system_settings', 'Memperbarui konfigurasi sistem platform', 'system_setting');
    log_audit('system_settings_updated', 'system_setting', null, 'Super Admin memperbarui konfigurasi sistem platform.', $settings);

    set_flash('success', 'Konfigurasi sistem berhasil disimpan dan langsung diterapkan.');
    redirect(url('superadmin/konfigurasi.php'));
}

// Read current settings
$currentSettings = [];
$rows = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll();
foreach ($rows as $r) {
    $currentSettings[$r['setting_key']] = $r['setting_value'];
}

$pageTitle = 'Konfigurasi Sistem';
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
            <span class="eyebrow" style="color:var(--gold);">PARAMETER GLOBAL</span>
            <h1>Konfigurasi Sistem Platform</h1>
            <p>Kelola pengaturan operasional global, batas registrasi, dan mode pemeliharaan platform.</p>
        </div>
    </div>

    <div class="card card-form">
        <form method="post">
            <?= csrf_field() ?>

            <h3 style="margin-top:0; border-bottom:1px solid var(--line); padding-bottom:10px;">Identitas Platform</h3>

            <div class="form-group">
                <label for="site_name">Nama Platform</label>
                <input type="text" id="site_name" name="site_name" class="form-control" value="<?= e($currentSettings['site_name'] ?? 'EduShare') ?>" required maxlength="100">
            </div>

            <div class="form-group">
                <label for="site_description">Deskripsi Singkat Platform</label>
                <textarea id="site_description" name="site_description" class="form-control" rows="2"><?= e($currentSettings['site_description'] ?? 'Platform Berbagi Materi dan Kolaborasi Pembelajaran') ?></textarea>
            </div>

            <h3 style="margin-top:28px; border-bottom:1px solid var(--line); padding-bottom:10px;">Akses & Registrasi</h3>

            <div class="form-group">
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                    <input type="checkbox" name="allow_registration" value="1" <?= ($currentSettings['allow_registration'] ?? '1') === '1' ? 'checked' : '' ?> style="width:18px; height:18px;">
                    <div>
                        <strong>Izinkan Registrasi Pengguna Publik</strong>
                        <div class="text-muted" style="font-size:12px;">Jika dinonaktifkan, halaman pendaftaran akun baru akan ditutup sementara.</div>
                    </div>
                </label>
            </div>

            <div class="form-group">
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                    <input type="checkbox" name="maintenance_mode" value="1" <?= ($currentSettings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?> style="width:18px; height:18px;">
                    <div>
                        <strong style="color:#b91c1c;">Mode Pemeliharaan (Maintenance Mode)</strong>
                        <div class="text-muted" style="font-size:12px;">Hanya Super Admin & Admin yang dapat mengakses sistem saat mode ini aktif.</div>
                    </div>
                </label>
            </div>

            <h3 style="margin-top:28px; border-bottom:1px solid var(--line); padding-bottom:10px;">Keamanan Sesi & Kredensial</h3>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="min_password_length">Panjang Minimal Password (Karakter)</label>
                    <input type="number" id="min_password_length" name="min_password_length" class="form-control" value="<?= (int)($currentSettings['min_password_length'] ?? 8) ?>" min="6" max="32" required>
                    <small class="form-hint">Standar keamanan minimal 8 karakter.</small>
                </div>

                <div class="form-group col-md-6">
                    <label for="session_timeout_min">Waktu Habis Sesi Otomatis (Menit)</label>
                    <input type="number" id="session_timeout_min" name="session_timeout_min" class="form-control" value="<?= (int)($currentSettings['session_timeout_min'] ?? 60) ?>" min="5" max="1440" required>
                    <small class="form-hint">Sesi akan otomatis berakhir jika pengguna tidak beraktivitas.</small>
                </div>
            </div>

            <div class="form-actions" style="margin-top:24px;">
                <button type="submit" class="btn btn-primary btn-large">
                    Simpan Semua Konfigurasi
                </button>
            </div>
        </form>
    </div>

</main>
</div>
<script src="<?= e(url('assets/js/dashboard.js')) ?>"></script>
</body>
</html>
