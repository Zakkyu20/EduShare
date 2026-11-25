<?php
declare(strict_types=1);

if (!function_exists('is_logged_in')) {
    require_once dirname(__DIR__) . '/config/app.php';
}

$pageTitle = $layoutTitle ?? 'EduShare';
$currentUser = current_user();
$currentRole = get_user_role();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> | EduShare</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<header class="site-header">
<a class="brand" href="<?= e(url('index.php')) ?>"><span class="brand-mark">E</span> Edu<span>Share</span></a>
<nav class="main-nav">
<a href="<?= e(url('index.php')) ?>">Beranda</a>
<a href="<?= e(url('materi.php')) ?>">Materi</a>
<a href="<?= e(url('materi.php#kategori')) ?>">Kategori</a>
<a href="<?= e(url('tentang.php')) ?>">Tentang</a>
</nav>
<div class="header-actions">
<?php if (is_logged_in()): ?>
    <a class="btn btn-outline btn-small" href="<?= e(role_home_url()) ?>">
        Dashboard <?= $currentRole === 'super_admin' ? 'Super Admin' : ($currentRole === 'admin' ? 'Admin' : 'Saya') ?>
    </a>
    <a class="profile-chip" href="<?= e(url('logout.php')) ?>" title="Klik untuk keluar">
        <?= e($currentUser['nama'] ?? 'Akun') ?> (Logout)
    </a>
<?php else: ?>
    <a class="login-link" href="<?= e(url('login.php')) ?>">Login</a>
    <a class="btn btn-primary btn-small" href="<?= e(url('register.php')) ?>">Daftar</a>
<?php endif; ?>
</div>
<button class="mobile-menu" aria-label="Buka menu">☰</button>
</header>
<main class="container"><?php flash(); ?>
