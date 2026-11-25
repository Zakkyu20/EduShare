<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_super_admin();

$user = current_user();
$superAdminId = (int)$user['id_user'];
$pdo = db();

$errors = [];
$generatedPasswordNotice = '';

// Handle POST actions: create admin, toggle status, reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $formAction = trim((string)($_POST['form_action'] ?? ''));

    if ($formAction === 'create_admin') {
        $nama = trim((string)($_POST['nama'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');

        if ($nama === '' || mb_strlen($nama) > 100) {
            $errors[] = 'Nama administrator wajib diisi (maks 100 karakter).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Alamat email tidak valid.';
        }
        if (mb_strlen($password) < 8) {
            $errors[] = 'Password minimal 8 karakter.';
        }

        if (empty($errors)) {
            $chk = $pdo->prepare("SELECT id_user FROM users WHERE email = ?");
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $errors[] = 'Alamat email sudah terdaftar dalam sistem.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $pdo->prepare(
                    "INSERT INTO users (nama, email, password, role, status, created_by, created_at)
                     VALUES (?, ?, ?, 'admin', 'active', ?, NOW())"
                );
                $ins->execute([$nama, $email, $hash, $superAdminId]);
                $newId = (int)$pdo->lastInsertId();

                log_activity($superAdminId, 'create_admin', "Membuat akun administrator baru: {$nama} ({$email})", 'user', $newId);
                log_audit('admin_created', 'user', $newId, "Super Admin membuat akun administrator baru \"{$nama}\" ({$email}).", [
                    'email' => $email,
                    'created_by' => $superAdminId
                ]);

                set_flash('success', "Akun Administrator \"{$nama}\" ({$email}) berhasil dibuat dan dapat langsung login.");
                redirect(url('superadmin/admins.php'));
            }
        }
    } elseif ($formAction === 'toggle_status') {
        $targetId = (int)($_POST['id_user'] ?? 0);
        $newStatus = trim((string)($_POST['new_status'] ?? ''));

        if ($targetId > 0 && in_array($newStatus, ['active', 'inactive', 'suspended'], true)) {
            // Verify target is role 'admin'
            $chk = $pdo->prepare("SELECT id_user, nama, email, role, status FROM users WHERE id_user = ?");
            $chk->execute([$targetId]);
            $target = $chk->fetch();

            if ($target && $target['role'] === 'admin') {
                $upd = $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id_user = ?");
                $upd->execute([$newStatus, $targetId]);

                log_activity($superAdminId, 'toggle_admin_status', "Mengubah status admin {$target['nama']} menjadi {$newStatus}", 'user', $targetId);
                log_audit('admin_status_changed', 'user', $targetId, "Super Admin mengubah status administrator \"{$target['nama']}\" menjadi {$newStatus}.");

                set_flash('info', "Status administrator \"{$target['nama']}\" berhasil diubah menjadi {$newStatus}.");
            } else {
                set_flash('error', 'Target akun bukan administrator.');
            }
        }
        redirect(url('superadmin/admins.php'));
    } elseif ($formAction === 'reset_password') {
        $targetId = (int)($_POST['id_user'] ?? 0);
        $newPass = trim((string)($_POST['new_password'] ?? ''));

        if ($targetId > 0) {
            $chk = $pdo->prepare("SELECT id_user, nama, email, role FROM users WHERE id_user = ?");
            $chk->execute([$targetId]);
            $target = $chk->fetch();

            if ($target && $target['role'] === 'admin') {
                if ($newPass === '') {
                    $newPass = bin2hex(random_bytes(5)) . 'Ed!';
                }
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $upd = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id_user = ?");
                $upd->execute([$hash, $targetId]);

                log_activity($superAdminId, 'reset_admin_password', "Mereset password admin {$target['nama']}", 'user', $targetId);
                log_audit('admin_password_reset', 'user', $targetId, "Super Admin mereset password administrator \"{$target['nama']}\".");

                set_flash('success', "Password administrator \"{$target['nama']}\" berhasil direset menjadi: <code>{$newPass}</code> (Harap catat password ini)");
            }
        }
        redirect(url('superadmin/admins.php'));
    }
}

// Fetch all admins
$admins = $pdo->query(
    "SELECT u.*, creator.nama AS nama_pembuat 
     FROM users u 
     LEFT JOIN users creator ON creator.id_user = u.created_by 
     WHERE u.role = 'admin' 
     ORDER BY u.created_at DESC"
)->fetchAll();

$pageTitle = 'Manajemen Administrator';
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
        $fMsg  = (string)($f['message'] ?? ''); // allow safe code tag inside
        echo '<div class="flash-box ' . $fType . '">' . $fMsg . '</div>';
    }
    ?>

    <div class="panel-header">
        <div>
            <span class="eyebrow" style="color:var(--gold);">OTORITAS TINGGI</span>
            <h1>Manajemen Administrator</h1>
            <p>Kelola staf operasional platform, buat akun admin baru, dan kelola kredensial akses.</p>
        </div>
        <button type="button" class="btn btn-primary" onclick="openCreateAdminModal()">
            Buat Akun Administrator
        </button>
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

    <!-- Admins Table -->
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
            <h3>Daftar Administrator Terdaftar (<?= count($admins) ?> akun)</h3>
        </div>
        <?php if (!empty($admins)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Administrator</th>
                            <th>Status</th>
                            <th>Dibuat Oleh</th>
                            <th>Tanggal Dibuat</th>
                            <th>Login Terakhir</th>
                            <th>Aksi Kelola</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $adm): ?>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <div style="width:36px; height:36px; border-radius:50%; background:var(--purple-dark); color:#fff; display:grid; place-items:center; font-weight:700;">
                                            A
                                        </div>
                                        <div>
                                            <strong><?= e($adm['nama']) ?></strong>
                                            <div class="text-muted" style="font-size:12px;"><?= e($adm['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($adm['status'] === 'active'): ?>
                                        <span class="badge badge-success">Aktif</span>
                                    <?php elseif ($adm['status'] === 'inactive'): ?>
                                        <span class="badge badge-secondary">Nonaktif</span>
                                    <?php elseif ($adm['status'] === 'suspended'): ?>
                                        <span class="badge badge-danger">Ditangguhkan</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($adm['nama_pembuat'] ?? 'Super Admin') ?></td>
                                <td><?= date('d M Y', strtotime($adm['created_at'])) ?></td>
                                <td><?= !empty($adm['last_login_at']) ? date('d/m/Y H:i', strtotime($adm['last_login_at'])) : '-' ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- Toggle Status -->
                                        <form method="post" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="form_action" value="toggle_status">
                                            <input type="hidden" name="id_user" value="<?= (int)$adm['id_user'] ?>">
                                            <input type="hidden" name="new_status" value="<?= $adm['status'] === 'active' ? 'inactive' : 'active' ?>">
                                            <button type="submit" class="btn-action <?= $adm['status'] === 'active' ? 'btn-delete' : 'btn-edit' ?>">
                                                <?= $adm['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?>
                                            </button>
                                        </form>

                                        <!-- Reset Password Trigger -->
                                        <button type="button" class="btn-action btn-view" onclick="openResetModal(<?= (int)$adm['id_user'] ?>, '<?= e(addslashes($adm['nama'])) ?>')">
                                            Reset Password
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>Belum ada akun administrator yang terdaftar.</p>
            </div>
        <?php endif; ?>
    </div>

</main>
</div>

<!-- Modal Create Admin -->
<div id="createAdminModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3>Buat Akun Administrator Baru</h3>
        <p class="text-muted" style="font-size:12px;">Akun admin memiliki wewenang untuk memverifikasi dan mengelola konten materi pembelajaran.</p>
        <form method="post" style="margin-top:16px;">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="create_admin">

            <div class="form-group">
                <label for="adminNama">Nama Lengkap Administrator <span class="text-danger">*</span></label>
                <input type="text" id="adminNama" name="nama" class="form-control" required maxlength="100" placeholder="Nama staf admin">
            </div>

            <div class="form-group">
                <label for="adminEmail">Alamat Email <span class="text-danger">*</span></label>
                <input type="email" id="adminEmail" name="email" class="form-control" required maxlength="150" placeholder="admin@edushare.com">
            </div>

            <div class="form-group">
                <label for="adminPassword">Password Awal <span class="text-danger">*</span></label>
                <input type="password" id="adminPassword" name="password" class="form-control" required minlength="8" placeholder="Minimal 8 karakter">
            </div>

            <div class="form-actions" style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-outline" onclick="closeCreateAdminModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Akun Admin</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Reset Password -->
<div id="resetPasswordModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3 id="resetModalTitle">Reset Password Administrator</h3>
        <form method="post" style="margin-top:16px;">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="reset_password">
            <input type="hidden" name="id_user" id="resetUserId" value="">

            <div class="form-group">
                <label for="customPassword">Password Baru (Opsional)</label>
                <input type="password" id="customPassword" name="new_password" class="form-control" placeholder="Kosongkan untuk membuat password acak">
                <small class="form-hint">Jika dikosongkan, sistem akan otomatis menghasilkan password sementara yang aman.</small>
            </div>

            <div class="form-actions" style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-outline" onclick="closeResetModal()">Batal</button>
                <button type="submit" class="btn btn-danger">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= e(url('assets/js/dashboard.js')) ?>"></script>
<script>
function openCreateAdminModal() {
    document.getElementById('createAdminModal').style.display = 'flex';
}
function closeCreateAdminModal() {
    document.getElementById('createAdminModal').style.display = 'none';
}
function openResetModal(id, nama) {
    document.getElementById('resetUserId').value = id;
    document.getElementById('resetModalTitle').innerText = 'Reset Password: ' + nama;
    document.getElementById('resetPasswordModal').style.display = 'flex';
}
function closeResetModal() {
    document.getElementById('resetPasswordModal').style.display = 'none';
}
</script>
</body>
</html>
