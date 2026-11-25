<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

if (is_logged_in()) {
    redirect_by_role();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $rawEmail = $_POST['email'] ?? '';
    $rawPassword = $_POST['password'] ?? '';

    if (!is_string($rawEmail) || !is_string($rawPassword)) {
        $error = 'Format input login tidak valid.';
    } else {
        $email = strtolower(trim($rawEmail));
        $password = $rawPassword;

        if ($email === '' || $password === '') {
            $error = 'Email dan password wajib diisi.';
        } else {
            $stmt = db()->prepare('SELECT id_user, nama, email, password, role, status FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $u = $stmt->fetch();

            if (!$u || !password_verify($password, (string)$u['password'])) {
                log_audit(
                    'login_failed',
                    'user',
                    $u ? (int)$u['id_user'] : null,
                    'Login gagal: kredensial tidak sesuai.',
                    ['email' => $email],
                    $u ? (int)$u['id_user'] : null,
                    $u ? (string)$u['role'] : 'guest'
                );
                $error = 'Email atau password tidak sesuai.';
            } elseif (($u['status'] ?? 'active') !== 'active') {
                log_audit(
                    'login_failed',
                    'user',
                    (int)$u['id_user'],
                    'Login ditolak: status akun tidak aktif.',
                    ['email' => $email, 'status' => $u['status']],
                    (int)$u['id_user'],
                    (string)$u['role']
                );
                $error = 'Akun Anda sedang nonaktif atau ditangguhkan. Hubungi administrator.';
            } else {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_regenerate_id(true);
                }

                $_SESSION['user'] = [
                    'id'      => (int)$u['id_user'],
                    'id_user' => (int)$u['id_user'],
                    'nama'    => (string)$u['nama'],
                    'email'   => (string)$u['email'],
                    'role'    => (string)$u['role'],
                    'status'  => (string)$u['status'],
                ];
                $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['last_activity'] = time();
                clear_auth_cache();

                $upd = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id_user = ?');
                $upd->execute([(int)$u['id_user']]);

                log_audit('login_success', 'user', (int)$u['id_user'], 'Login berhasil.', ['role' => $u['role']]);
                log_activity((int)$u['id_user'], 'login', 'Masuk ke platform EduShare', 'user', (int)$u['id_user']);

                redirect_by_role((string)$u['role']);
            }
        }
    }
}

$layoutTitle = 'Masuk';
include __DIR__ . '/partials/header.php';
?>
<section class="auth-wrap">
    <div class="auth-visual">
        <span class="brand-mark">E</span>
        <h2>Belajar tumbuh<br>bersama komunitas.</h2>
        <p>Temukan materi, bagikan pengetahuan, dan bantu lebih banyak orang belajar.</p>
    </div>
    <form class="auth-form" method="post">
        <?= csrf_field() ?>
        <span class="eyebrow">SELAMAT DATANG KEMBALI</span>
        <h1>Masuk ke EduShare</h1>
        <p>Gunakan akunmu untuk melanjutkan belajar.</p>
        <?php if ($error !== ''): ?>
            <div class="flash error"><?= e($error) ?></div>
        <?php endif; ?>
        <label>Email
            <input type="email" name="email" required autocomplete="email" value="<?= e(post_string('email')) ?>" placeholder="nama@email.com">
        </label>
        <label>Password
            <input type="password" name="password" required autocomplete="current-password" placeholder="Masukkan password">
        </label>
        <button class="btn btn-primary btn-block">Masuk</button>
        <div class="auth-switch">Belum punya akun? <a href="<?= e(url('register.php')) ?>">Daftar sekarang</a></div>
    </form>
</section>
<?php include __DIR__ . '/partials/footer.php'; ?>
