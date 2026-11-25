<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_super_admin();

$user = current_user();
$superAdminId = (int)$user['id_user'];
$pdo = db();

// Handle permission toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $role = trim((string)($_POST['role'] ?? ''));
    $permKey = trim((string)($_POST['permission_key'] ?? ''));
    $newVal = (int)($_POST['is_allowed'] ?? 0);

    // Super Admin permissions cannot be turned off
    if ($role === 'super_admin') {
        set_flash('error', 'Hak akses untuk Super Admin bersifat absolut dan tidak dapat dinonaktifkan.');
    } elseif (in_array($role, ['guest', 'user', 'admin'], true)) {
        $upd = $pdo->prepare(
            "UPDATE role_permissions 
             SET is_allowed = ?, updated_by = ?, updated_at = NOW() 
             WHERE role = ? AND permission_key = ?"
        );
        $upd->execute([$newVal, $superAdminId, $role, $permKey]);

        clear_permission_cache();

        log_activity($superAdminId, 'update_permission', "Mengubah izin {$permKey} untuk role {$role} menjadi {$newVal}", 'role_permission');
        log_audit('role_permission_updated', 'role_permission', null, "Super Admin mengubah izin \"{$permKey}\" pada role {$role} menjadi {$newVal}.", [
            'role' => $role,
            'permission_key' => $permKey,
            'is_allowed' => $newVal
        ]);

        set_flash('success', "Izin \"{$permKey}\" untuk role [{$role}] berhasil diperbarui.");
    }
    redirect(url('superadmin/roles.php'));
}

// Fetch all permissions grouped by key
$stmt = $pdo->query("SELECT * FROM role_permissions ORDER BY permission_key ASC, role ASC");
$allPerms = $stmt->fetchAll();

$permMatrix = [];
$permLabels = [];
foreach ($allPerms as $p) {
    $k = $p['permission_key'];
    $r = $p['role'];
    $permMatrix[$k][$r] = (int)$p['is_allowed'];
    $permLabels[$k] = $p['permission_label'];
}

$rolesList = ['guest', 'user', 'admin', 'super_admin'];

$pageTitle = 'Manajemen Role & Hak Akses';
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
            <span class="eyebrow" style="color:var(--gold);">OTORISASI SENTRAL (RBAC)</span>
            <h1>Manajemen Role & Hak Akses</h1>
            <p>Konfigurasi matriks kontrol hak akses granular untuk masing-masing tingkatan pengguna.</p>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px; background:#faf8ff; border:1px solid #ddd6fe;">
        <strong>Panduan Matriks RBAC:</strong>
        <p style="margin:4px 0 0; font-size:12px; color:#4c1d95;">
            Perubahan izin pada matriks di bawah ini langsung berlaku di seluruh layer middleware server-side (<code>has_permission()</code>). Role <strong>Super Admin</strong> selalu memiliki izin penuh secara permanen.
        </p>
    </div>

    <!-- Permissions Matrix Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="min-width:220px;">Fitur / Hak Akses</th>
                        <th style="text-align:center;">Pengunjung (Guest)</th>
                        <th style="text-align:center;">Pengguna (User)</th>
                        <th style="text-align:center;">Administrator (Admin)</th>
                        <th style="text-align:center;">Super Admin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($permMatrix as $permKey => $roleVals): ?>
                        <tr>
                            <td>
                                <strong><?= e($permLabels[$permKey] ?? $permKey) ?></strong>
                                <div style="font-size:11px; color:#777; font-family:monospace;"><?= e($permKey) ?></div>
                            </td>

                            <?php foreach ($rolesList as $r): ?>
                                <td style="text-align:center;">
                                    <?php if ($r === 'super_admin'): ?>
                                        <span class="badge badge-success" title="Selalu Aktif">Selalu On</span>
                                    <?php else: ?>
                                        <?php $allowed = ($roleVals[$r] ?? 0) === 1; ?>
                                        <form method="post" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="role" value="<?= e($r) ?>">
                                            <input type="hidden" name="permission_key" value="<?= e($permKey) ?>">
                                            <input type="hidden" name="is_allowed" value="<?= $allowed ? 0 : 1 ?>">
                                            <button type="submit" class="btn-action <?= $allowed ? 'btn-edit' : 'btn-delete' ?>" style="font-size:11px; padding:4px 10px;">
                                                <?= $allowed ? 'Diizinkan' : 'Dilarang' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>
</div>
<script src="<?= e(url('assets/js/dashboard.js')) ?>"></script>
</body>
</html>
