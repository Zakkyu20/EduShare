<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

if (is_logged_in()) {
    redirect_by_role();
}

$allowRegistration = (string)get_setting('allow_registration', '1') === '1';
$minPasswordLength = max(6, (int)get_setting('min_password_length', '8'));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (!$allowRegistration) {
        if (!headers_sent()) {
            http_response_code(403);
        }
        $error = 'Pendaftaran akun baru saat ini dinonaktifkan oleh administrator.';
    } else {
        $rawNama = $_POST['nama'] ?? '';
        $rawEmail = $_POST['email'] ?? '';
        $rawPass = $_POST['password'] ?? '';
        $rawConfirm = $_POST['confirm'] ?? '';

        if (
            !is_string($rawNama)
            || !is_string($rawEmail)
            || !is_string($rawPass)
            || !is_string($rawConfirm)
        ) {
            $error = 'Format input pendaftaran tidak valid.';
        } else {
            $nama = trim($rawNama);
            $email = strtolower(trim($rawEmail));
            $pass = $rawPass;
            $confirm = $rawConfirm;

            if (
                $nama === ''
                || mb_strlen($nama, 'UTF-8') > 100
                || !filter_var($email, FILTER_VALIDATE_EMAIL)
                || mb_strlen($email, 'UTF-8') > 150
                || mb_strlen($pass, 'UTF-8') < $minPasswordLength
            ) {
                $error = 'Lengkapi data dengan benar. Password minimal ' . $minPasswordLength . ' karakter.';
            } elseif ($pass !== $confirm) {
                $error = 'Konfirmasi password tidak sama.';
            } else {
                try {
                    $stmt = db()->prepare(
                        "INSERT INTO users (nama, email, password, role, status) VALUES (?, ?, ?, 'user', 'active')"
                    );
                    $stmt->execute([$nama, $email, password_hash($pass, PASSWORD_DEFAULT)]);
                    $newUserId = (int)db()->lastInsertId();

                    log_audit(
                        'register_success',
                        'user',
                        $newUserId,
                        'Registrasi pengguna baru.',
                        ['email' => $email, 'role' => 'user'],
                        $newUserId,
                        'user'
                    );
                    log_activity($newUserId, 'register', 'Mendaftar akun baru di EduShare', 'user', $newUserId);

                    set_flash('success', 'Akun berhasil dibuat. Silakan login.');
                    redirect(url('login.php'));
                } catch (PDOException $e) {
                    $error = 'Email sudah digunakan atau terjadi kesalahan.';
                }
            }
        }
    }
}

$layoutTitle = 'Daftar';
include __DIR__ . '/partials/header.php';
?>
<section class="auth-wrap">
    <div class="auth-visual">
        <span class="brand-mark">E</span>
        <h2>Satu materi,<br>banyak kemungkinan.</h2>
        <p>Jadilah bagian dari ruang belajar yang saling mendukung.</p>
    </div>
    <form class="auth-form" method="post">
        <?= csrf_field() ?>
        <span class="eyebrow">MULAI BERBAGI</span>
        <h1>Buat akun EduShare</h1>
        <p>Daftar gratis dan mulai jelajahi materi.</p>
        <?php if ($error !== ''): ?>
            <div class="flash error"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if (!$allowRegistration): ?>
            <div class="flash error">Pendaftaran pengguna baru sedang ditutup sementara oleh administrator.</div>
            <div class="auth-switch">Sudah punya akun? <a href="<?= e(url('login.php')) ?>">Masuk</a></div>
        <?php else: ?>
            <label>Nama lengkap
                <input name="nama" required maxlength="100" value="<?= e(post_string('nama')) ?>" placeholder="Nama kamu">
            </label>
            <label>Email
                <input type="email" name="email" required maxlength="150" value="<?= e(post_string('email')) ?>" placeholder="nama@email.com">
            </label>
            <label>Password
                <input type="password" name="password" required minlength="<?= $minPasswordLength ?>" placeholder="Minimal <?= $minPasswordLength ?> karakter">
            </label>
            <label>Konfirmasi password
                <input type="password" name="confirm" required minlength="<?= $minPasswordLength ?>" placeholder="Ulangi password">
            </label>
            <button class="btn btn-primary btn-block">Buat akun</button>
            <div class="auth-switch">Sudah punya akun? <a href="<?= e(url('login.php')) ?>">Masuk</a></div>
        <?php endif; ?>
    </form>
</section>
<?php include __DIR__ . '/partials/footer.php'; ?>
