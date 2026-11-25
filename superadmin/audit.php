<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_super_admin();

$user = current_user();
$pdo = db();

$roleFilter = trim((string)($_GET['actor_role'] ?? ''));
$actionFilter = trim((string)($_GET['action'] ?? ''));
$search = trim((string)($_GET['q'] ?? ''));

$sql = "SELECT a.*, u.nama AS actor_name, u.email AS actor_email 
        FROM audit_logs a 
        LEFT JOIN users u ON u.id_user = a.actor_id 
        WHERE 1=1";
$params = [];

if ($roleFilter !== '') {
    $sql .= " AND a.actor_role = ?";
    $params[] = $roleFilter;
}

if ($actionFilter !== '') {
    $sql .= " AND a.action = ?";
    $params[] = $actionFilter;
}

if ($search !== '') {
    $sql .= " AND (a.description LIKE ? OR a.action LIKE ? OR u.nama LIKE ? OR a.ip_address LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= " ORDER BY a.created_at DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Unique actions for filter dropdown
$distinctActions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Audit Log Sistem';
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

    <div class="panel-header">
        <div>
            <span class="eyebrow" style="color:var(--gold);">REKAM JEJAK KEAMANAN</span>
            <h1>Audit Log Aktivitas Sistem</h1>
            <p>Pencatatan riwayat audit lengkap seluruh tindakan penting di platform EduShare.</p>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card" style="margin-bottom: 20px; padding: 16px;">
        <form method="get" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
            <div style="flex:1; min-width:200px;">
                <input type="text" name="q" class="form-control" placeholder="Cari keterangan, aktor, atau IP..." value="<?= e($search) ?>">
            </div>
            <div style="width:160px;">
                <select name="actor_role" class="form-control">
                    <option value="">-- Semua Role --</option>
                    <option value="user" <?= $roleFilter === 'user' ? 'selected' : '' ?>>User</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="super_admin" <?= $roleFilter === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                    <option value="guest" <?= $roleFilter === 'guest' ? 'selected' : '' ?>>Guest</option>
                </select>
            </div>
            <div style="width:200px;">
                <select name="action" class="form-control">
                    <option value="">-- Semua Aksi --</option>
                    <?php foreach ($distinctActions as $act): ?>
                        <option value="<?= e($act) ?>" <?= $actionFilter === $act ? 'selected' : '' ?>><?= e($act) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-small">Filter Log</button>
            <?php if ($search !== '' || $roleFilter !== '' || $actionFilter !== ''): ?>
                <a href="<?= e(url('superadmin/audit.php')) ?>" class="btn btn-outline btn-small">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Log Table -->
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
            <h3>Riwayat Audit (Menampilkan <?= count($logs) ?> rekaman terbaru)</h3>
        </div>
        <?php if (!empty($logs)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Waktu (WIB)</th>
                            <th>Aktor / Pengguna</th>
                            <th>Role</th>
                            <th>Aksi</th>
                            <th>Deskripsi</th>
                            <th>IP Address</th>
                            <th>Metadata</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td style="font-size:11px; white-space:nowrap;"><?= date('d/m/Y H:i:s', strtotime($l['created_at'])) ?></td>
                                <td>
                                    <strong><?= e($l['actor_name'] ?? 'Sistem / Anonim') ?></strong>
                                    <?php if (!empty($l['actor_email'])): ?>
                                        <div class="text-muted" style="font-size:10px;"><?= e($l['actor_email']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-light" style="font-size:10px;"><?= e($l['actor_role'] ?? 'guest') ?></span></td>
                                <td><span class="badge badge-outline" style="font-size:10px;"><?= e($l['action']) ?></span></td>
                                <td style="font-size:12px; max-width:280px;"><?= e($l['description'] ?? '-') ?></td>
                                <td style="font-size:11px; font-family:monospace;"><?= e($l['ip_address'] ?? '-') ?></td>
                                <td>
                                    <?php if (!empty($l['metadata'])): ?>
                                        <button type="button" class="btn-action btn-view" style="font-size:10px;" onclick="showMeta(<?= htmlspecialchars(json_encode($l['metadata']), ENT_QUOTES, 'UTF-8') ?>)">
                                            JSON
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>Tidak ada rekaman audit yang sesuai dengan filter.</p>
            </div>
        <?php endif; ?>
    </div>

</main>
</div>

<!-- Modal View JSON Metadata -->
<div id="metaModal" class="modal-overlay" style="display:none;">
    <div class="modal-box" style="max-width:550px;">
        <h3>Detail Metadata Audit</h3>
        <pre id="metaContent" style="background:#1e1e2f; color:#a5b4fc; padding:14px; border-radius:8px; font-size:12px; overflow-x:auto; margin-top:14px;"></pre>
        <div style="display:flex; justify-content:flex-end; margin-top:16px;">
            <button type="button" class="btn btn-outline" onclick="closeMeta()">Tutup</button>
        </div>
    </div>
</div>

<script src="<?= e(url('assets/js/dashboard.js')) ?>"></script>
<script>
function showMeta(rawJson) {
    try {
        const parsed = typeof rawJson === 'string' ? JSON.parse(rawJson) : rawJson;
        document.getElementById('metaContent').innerText = JSON.stringify(parsed, null, 2);
    } catch(e) {
        document.getElementById('metaContent').innerText = rawJson;
    }
    document.getElementById('metaModal').style.display = 'flex';
}
function closeMeta() {
    document.getElementById('metaModal').style.display = 'none';
}
</script>
</body>
</html>
