<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_super_admin();

$user = current_user();
$superAdminId = (int)$user['id_user'];
$pdo = db();

// Handle security policy update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'force_logout_all') {
        // Log the event
        log_activity($superAdminId, 'force_logout_all', 'Super Admin memicu force logout pada seluruh sesi akun lain', 'security');
        log_audit('force_logout_triggered', 'security', null, 'Super Admin memicu force logout pada seluruh sesi sistem.');

        // Update token or timestamp in system_settings
        $stmt = $pdo->prepare(
            "INSERT INTO system_settings (setting_key, setting_value, updated_by, updated_at) 
             VALUES ('force_logout_time', ?, ?, NOW()) 
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()"
        );
        $stmt->execute([(string)time(), $superAdminId]);

        set_flash('success', 'Perintah logout paksa berhasil disimpan.');
        redirect(url('superadmin/keamanan.php'));
    }
}

// Failed logins in last 24 hours
$failedCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM audit_logs 
     WHERE action = 'login_failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
)->fetchColumn();

// Successful logins in last 24 hours
$successCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM audit_logs 
     WHERE action = 'login_success' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
)->fetchColumn();

// Recent Security Logs
$securityLogs = $pdo->query(
    "SELECT a.*, u.nama AS actor_name 
     FROM audit_logs a 
     LEFT JOIN users u ON u.id_user = a.actor_id 
     WHERE a.action IN ('login_failed', 'login_success', 'password_changed', 'admin_password_reset', 'force_logout_triggered') 
     ORDER BY a.created_at DESC 
     LIMIT 15"
)->fetchAll();

$pageTitle = 'Pengaturan Keamanan';
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
            <span class="eyebrow" style="color:var(--gold);">PERLINDUNGAN INFRASTRUKTUR</span>
            <h1>Pengaturan & Pemantauan Keamanan</h1>
            <p>Status proteksi sesi, deteksi anomali autentikasi, dan kebijakan keamanan tingkat tinggi.</p>
        </div>
    </div>

    <!-- Security Stat Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            
            <div class="stat-value">PDO Prepared</div>
            <div class="stat-label">Database Security</div>
            <div class="stat-desc">Proteksi injeksi SQL 100%</div>
        </div>
        <div class="stat-card">
            
            <div class="stat-value">Bcrypt</div>
            <div class="stat-label">Hashing Password</div>
            <div class="stat-desc">password_hash() standar kuat</div>
        </div>
        <div class="stat-card">
            
            <div class="stat-value">CSRF Token</div>
            <div class="stat-label">Proteksi Formulir</div>
            <div class="stat-desc">Anti Cross-Site Request Forgery</div>
        </div>
        <div class="stat-card">
            
            <div class="stat-value"><?= $failedCount ?> kali</div>
            <div class="stat-label">Gagal Login (24 Jam)</div>
            <div class="stat-desc"><?= $successCount ?> kali berhasil</div>
        </div>
    </div>

    <!-- Security Actions Card -->
    <div class="card" style="margin-bottom: 24px;">
        <h3 style="margin-top:0;">Tindakan Darurat Keamanan</h3>
        <p class="text-muted" style="font-size:13px;">
            Fitur ini dapat digunakan jika terjadi indikasi penyusupan akun atau pembaruan kebijakan keamanan darurat.
        </p>

        <form method="post" onsubmit="return confirm('Apakah Anda yakin ingin memicu pembaruan sesi keamanan global?');" style="margin-top:14px;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="force_logout_all">
            <button type="submit" class="btn btn-outline" style="border-color:#b91c1c; color:#b91c1c;">
                Reset Waktu Sesi Global
            </button>
        </form>
    </div>

    <!-- Security Event Log -->
    <div class="card">
        <div class="card-header">
            <h3>Log Autentikasi & Keamanan Terkini</h3>
        </div>
        <?php if (!empty($securityLogs)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Aktor / Target</th>
                            <th>Peristiwa</th>
                            <th>Keterangan</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($securityLogs as $s): ?>
                            <tr>
                                <td style="font-size:11px; white-space:nowrap;"><?= date('d/m/Y H:i:s', strtotime($s['created_at'])) ?></td>
                                <td><?= e($s['actor_name'] ?? 'Anonim') ?></td>
                                <td>
                                    <?php if ($s['action'] === 'login_failed'): ?>
                                        <span class="badge badge-danger">Gagal Login</span>
                                    <?php elseif ($s['action'] === 'login_success'): ?>
                                        <span class="badge badge-success">Login Berhasil</span>
                                    <?php else: ?>
                                        <span class="badge badge-outline"><?= e($s['action']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:12px;"><?= e($s['description'] ?? '-') ?></td>
                                <td style="font-size:11px; font-family:monospace;"><?= e($s['ip_address'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><p>Belum ada rekaman log keamanan.</p></div>
        <?php endif; ?>
    </div>

</main>
</div>
<script src="<?= e(url('assets/js/dashboard.js')) ?>"></script>
</body>
</html>
