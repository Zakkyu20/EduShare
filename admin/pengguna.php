<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_admin();

$user = current_user();
$adminId = (int)$user['id_user'];
$pdo = db();

// Handle toggle user status (active / inactive / suspended)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $targetId = (int)($_POST['id_user'] ?? 0);
    $newStatus = trim((string)($_POST['new_status'] ?? ''));

    if ($targetId > 0 && in_array($newStatus, ['active', 'inactive', 'suspended'], true)) {
        // Ensure target is role 'user' only (Admin cannot modify other admins or super_admins)
        $chk = $pdo->prepare("SELECT id_user, nama, email, role, status FROM users WHERE id_user = ?");
        $chk->execute([$targetId]);
        $target = $chk->fetch();

        if ($target && $target['role'] === 'user') {
            $upd = $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id_user = ?");
            $upd->execute([$newStatus, $targetId]);

            log_activity($adminId, 'change_user_status', "Mengubah status pengguna {$target['nama']} menjadi {$newStatus}", 'user', $targetId);
            log_audit('user_status_changed', 'user', $targetId, "Admin mengubah status pengguna \"{$target['nama']}\" ({$target['email']}) menjadi {$newStatus}.", [
                'previous_status' => $target['status'],
                'new_status' => $newStatus
            ]);

            set_flash('success', "Status pengguna \"{$target['nama']}\" berhasil diubah menjadi {$newStatus}.");
        } else {
            set_flash('error', 'Anda hanya memiliki wewenang untuk mengelola pengguna dengan role Pengguna (User).');
        }
    }
    redirect(url('admin/pengguna.php'));
}

$search = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));

// Only fetch role = 'user'
$sql = "SELECT u.id_user, u.nama, u.email, u.status, u.created_at, u.last_login_at,
               COUNT(m.id_material) AS total_materi,
               SUM(CASE WHEN m.status = 'approved' THEN 1 ELSE 0 END) AS total_approved
        FROM users u 
        LEFT JOIN materials m ON m.id_user = u.id_user 
        WHERE u.role = 'user'";
$params = [];

if ($statusFilter !== '' && in_array($statusFilter, ['active', 'inactive', 'suspended'], true)) {
    $sql .= " AND u.status = ?";
    $params[] = $statusFilter;
}

if ($search !== '') {
    $sql .= " AND (u.nama LIKE ? OR u.email LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= " GROUP BY u.id_user ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Manajemen Pengguna';
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
            <span class="eyebrow">PEMANTAUAN KOMUNITAS</span>
            <h1>Manajemen Pengguna</h1>
            <p>Pantau data, aktivitas materi, dan status akun pengguna terdaftar.</p>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="card" style="margin-bottom: 20px; padding: 16px;">
        <form method="get" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
            <div style="flex:1; min-width:220px;">
                <input type="text" name="q" class="form-control" placeholder="Cari nama atau email pengguna..." value="<?= e($search) ?>">
            </div>
            <div style="width:180px;">
                <select name="status" class="form-control">
                    <option value="">-- Semua Status --</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Aktif</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                    <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-small">Cari Pengguna</button>
            <?php if ($search !== '' || $statusFilter !== ''): ?>
                <a href="<?= e(url('admin/pengguna.php')) ?>" class="btn btn-outline btn-small">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
            <h3>Daftar Pengguna (<?= count($users) ?> orang)</h3>
        </div>
        <?php if (!empty($users)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Pengguna</th>
                            <th>Status Akun</th>
                            <th>Materi Diunggah</th>
                            <th>Tanggal Bergabung</th>
                            <th>Login Terakhir</th>
                            <th>Ubah Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <div style="width:36px; height:36px; border-radius:50%; background:var(--lavender); color:var(--purple); display:grid; place-items:center; font-weight:700;">
                                            <?= strtoupper(substr($u['nama'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <strong><?= e($u['nama']) ?></strong>
                                            <div class="text-muted" style="font-size:12px;"><?= e($u['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($u['status'] === 'active'): ?>
                                        <span class="badge badge-success">Aktif</span>
                                    <?php elseif ($u['status'] === 'inactive'): ?>
                                        <span class="badge badge-secondary">Nonaktif</span>
                                    <?php elseif ($u['status'] === 'suspended'): ?>
                                        <span class="badge badge-danger">Ditangguhkan</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= (int)$u['total_materi'] ?></strong> diunggah
                                    <small class="text-muted">(<?= (int)$u['total_approved'] ?> disetujui)</small>
                                </td>
                                <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                                <td><?= !empty($u['last_login_at']) ? date('d M Y, H:i', strtotime($u['last_login_at'])) : '-' ?></td>
                                <td>
                                    <form method="post" style="display:inline-flex; gap:6px; align-items:center;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id_user" value="<?= (int)$u['id_user'] ?>">
                                        <select name="new_status" class="form-control" style="padding:4px 8px; font-size:12px; width:auto;">
                                            <option value="active" <?= $u['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                                            <option value="inactive" <?= $u['status'] === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                                            <option value="suspended" <?= $u['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                        </select>
                                        <button type="submit" class="btn btn-outline btn-small" style="padding:4px 8px;">Ubah</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>Tidak ada pengguna yang sesuai kriteria.</p>
            </div>
        <?php endif; ?>
    </div>

</main>
</div>
<script src="<?= e(url('assets/js/dashboard.js')) ?>"></script>
</body>
</html>
