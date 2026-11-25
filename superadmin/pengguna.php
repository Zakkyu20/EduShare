<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_super_admin();

$user = current_user();
$superAdminId = (int)$user['id_user'];
$pdo = db();

// Handle role or status changes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $targetId = (int)($_POST['id_user'] ?? 0);
    $formAction = trim((string)($_POST['form_action'] ?? ''));

    if ($targetId > 0 && $targetId !== $superAdminId) {
        $chk = $pdo->prepare("SELECT id_user, nama, email, role, status FROM users WHERE id_user = ?");
        $chk->execute([$targetId]);
        $target = $chk->fetch();

        if ($target) {
            if ($formAction === 'change_role') {
                $newRole = trim((string)($_POST['new_role'] ?? ''));
                if (in_array($newRole, ['user', 'admin'], true)) {
                    $upd = $pdo->prepare("UPDATE users SET role = ?, updated_at = NOW() WHERE id_user = ?");
                    $upd->execute([$newRole, $targetId]);

                    log_activity($superAdminId, 'change_user_role', "Mengubah role {$target['nama']} dari {$target['role']} menjadi {$newRole}", 'user', $targetId);
                    log_audit('user_role_changed', 'user', $targetId, "Super Admin mengubah role \"{$target['nama']}\" ({$target['email']}) menjadi {$newRole}.", [
                        'old_role' => $target['role'],
                        'new_role' => $newRole
                    ]);

                    set_flash('success', "Role \"{$target['nama']}\" berhasil diubah menjadi {$newRole}.");
                }
            } elseif ($formAction === 'change_status') {
                $newStatus = trim((string)($_POST['new_status'] ?? ''));
                if (in_array($newStatus, ['active', 'inactive', 'suspended'], true)) {
                    $upd = $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id_user = ?");
                    $upd->execute([$newStatus, $targetId]);

                    log_activity($superAdminId, 'change_user_status', "Mengubah status {$target['nama']} menjadi {$newStatus}", 'user', $targetId);
                    log_audit('user_status_changed', 'user', $targetId, "Super Admin mengubah status pengguna \"{$target['nama']}\" menjadi {$newStatus}.");

                    set_flash('info', "Status \"{$target['nama']}\" berhasil diubah menjadi {$newStatus}.");
                }
            }
        }
    }
    redirect(url('superadmin/pengguna.php'));
}

$search = trim((string)($_GET['q'] ?? ''));
$roleFilter = trim((string)($_GET['role'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));

$sql = "SELECT u.*, 
               COUNT(m.id_material) AS total_materi,
               SUM(CASE WHEN m.status = 'approved' THEN 1 ELSE 0 END) AS total_approved
        FROM users u 
        LEFT JOIN materials m ON m.id_user = u.id_user 
        WHERE 1=1";
$params = [];

if ($roleFilter !== '' && in_array($roleFilter, ['user', 'admin', 'super_admin'], true)) {
    $sql .= " AND u.role = ?";
    $params[] = $roleFilter;
}

if ($statusFilter !== '' && in_array($statusFilter, ['active', 'inactive', 'suspended'], true)) {
    $sql .= " AND u.status = ?";
    $params[] = $statusFilter;
}

if ($search !== '') {
    $sql .= " AND (u.nama LIKE ? OR u.email LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= " GROUP BY u.id_user ORDER BY (u.role = 'super_admin') DESC, (u.role = 'admin') DESC, u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usersList = $stmt->fetchAll();

$pageTitle = 'Manajemen Seluruh Pengguna';
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
            <span class="eyebrow" style="color:var(--gold);">KONTROL AKUN SENTRAL</span>
            <h1>Manajemen Seluruh Pengguna</h1>
            <p>Kelola data seluruh akun (User, Administrator, Super Admin), ubah hak role, dan status akun.</p>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card" style="margin-bottom: 20px; padding: 16px;">
        <form method="get" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
            <div style="flex:1; min-width:200px;">
                <input type="text" name="q" class="form-control" placeholder="Cari nama atau email..." value="<?= e($search) ?>">
            </div>
            <div style="width:160px;">
                <select name="role" class="form-control">
                    <option value="">-- Semua Role --</option>
                    <option value="user" <?= $roleFilter === 'user' ? 'selected' : '' ?>>User</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="super_admin" <?= $roleFilter === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                </select>
            </div>
            <div style="width:160px;">
                <select name="status" class="form-control">
                    <option value="">-- Semua Status --</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Aktif</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                    <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-small">Filter</button>
            <?php if ($search !== '' || $roleFilter !== '' || $statusFilter !== ''): ?>
                <a href="<?= e(url('superadmin/pengguna.php')) ?>" class="btn btn-outline btn-small">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
            <h3>Daftar Pengguna Platform (<?= count($usersList) ?> orang)</h3>
        </div>
        <?php if (!empty($usersList)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Akun Pengguna</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Materi Diunggah</th>
                            <th>Daftar / Login</th>
                            <th>Kelola Role & Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usersList as $u): ?>
                            <tr>
                                <td>
                                    <strong><?= e($u['nama']) ?></strong>
                                    <div class="text-muted" style="font-size:11px;"><?= e($u['email']) ?></div>
                                    <div style="font-size:10px; color:#888;">ID: #<?= $u['id_user'] ?></div>
                                </td>
                                <td>
                                    <?php if ($u['role'] === 'super_admin'): ?>
                                        <span class="badge badge-warning" style="background:#fef3c7; color:#b45309; border:1px solid #fcd34d;">Super Admin</span>
                                    <?php elseif ($u['role'] === 'admin'): ?>
                                        <span class="badge badge-primary" style="background:#ede9fe; color:#5b21b6;">Administrator</span>
                                    <?php else: ?>
                                        <span class="badge badge-light">Pengguna (User)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['status'] === 'active'): ?>
                                        <span class="badge badge-success">Aktif</span>
                                    <?php elseif ($u['status'] === 'inactive'): ?>
                                        <span class="badge badge-secondary">Nonaktif</span>
                                    <?php elseif ($u['status'] === 'suspended'): ?>
                                        <span class="badge badge-danger">Suspended</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= (int)$u['total_materi'] ?></strong>
                                    <small class="text-muted">(<?= (int)$u['total_approved'] ?> approved)</small>
                                </td>
                                <td style="font-size:11px;">
                                    <div>Daftar: <?= date('d/m/Y', strtotime($u['created_at'])) ?></div>
                                    <div class="text-muted">Login: <?= !empty($u['last_login_at']) ? date('d/m H:i', strtotime($u['last_login_at'])) : '-' ?></div>
                                </td>
                                <td>
                                    <?php if ((int)$u['id_user'] === $superAdminId || $u['role'] === 'super_admin'): ?>
                                        <span class="text-muted" style="font-size:11px;">Akun Utama (Terkunci)</span>
                                    <?php else: ?>
                                        <div style="display:flex; flex-direction:column; gap:6px;">
                                            <!-- Change Role Form -->
                                            <form method="post" style="display:flex; gap:4px; align-items:center;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="form_action" value="change_role">
                                                <input type="hidden" name="id_user" value="<?= (int)$u['id_user'] ?>">
                                                <select name="new_role" class="form-control" style="padding:2px 6px; font-size:11px; width:auto;">
                                                    <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                </select>
                                                <button type="submit" class="btn btn-outline btn-small" style="padding:2px 6px; font-size:10px;">Ubah Role</button>
                                            </form>

                                            <!-- Change Status Form -->
                                            <form method="post" style="display:flex; gap:4px; align-items:center;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="form_action" value="change_status">
                                                <input type="hidden" name="id_user" value="<?= (int)$u['id_user'] ?>">
                                                <select name="new_status" class="form-control" style="padding:2px 6px; font-size:11px; width:auto;">
                                                    <option value="active" <?= $u['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                                                    <option value="inactive" <?= $u['status'] === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                                                    <option value="suspended" <?= $u['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                                </select>
                                                <button type="submit" class="btn btn-outline btn-small" style="padding:2px 6px; font-size:10px;">Status</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>Tidak ada pengguna yang sesuai.</p>
            </div>
        <?php endif; ?>
    </div>

</main>
</div>
<script src="<?= e(url('assets/js/dashboard.js')) ?>"></script>
</body>
</html>
