<?php
// partials/sidebar_admin.php
// Requires: current_user() helper available via app.php bootstrap
$sidebarUser = current_user();
$currentPage = $_SERVER['PHP_SELF'];

function adminSidebarIsActive(string $path): string {
    global $currentPage;
    return str_contains($currentPage, $path) ? ' active' : '';
}

$adminFirstLetter = strtoupper(mb_substr($sidebarUser['nama'] ?? $sidebarUser['name'] ?? 'A', 0, 1));
?>
<aside class="sidebar sidebar-admin">
    <div class="brand">
        <a href="<?= url('admin/dashboard.php') ?>" class="brand-link">
            <span class="brand-mark">E</span>
            <span class="brand-name">EduShare Admin</span>
        </a>
    </div>

    <div class="user-info">
        <div class="user-avatar user-avatar-admin">
            <?= e($adminFirstLetter) ?>
        </div>
        <div class="user-meta">
            <div class="user-name"><?= e($sidebarUser['nama'] ?? $sidebarUser['name'] ?? 'Admin') ?></div>
            <span class="role-badge badge-admin">Admin</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="<?= url('admin/dashboard.php') ?>" class="nav-item <?= adminSidebarIsActive('admin/dashboard') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            <span class="nav-label">Dashboard</span>
        </a>
        <a href="<?= url('admin/verifikasi.php') ?>" class="nav-item <?= adminSidebarIsActive('admin/verifikasi') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
            <span class="nav-label">Verifikasi Materi</span>
        </a>
        <a href="<?= url('admin/materi.php') ?>" class="nav-item <?= adminSidebarIsActive('admin/materi') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            <span class="nav-label">Manajemen Materi</span>
        </a>
        <a href="<?= url('admin/kategori.php') ?>" class="nav-item <?= adminSidebarIsActive('admin/kategori') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            <span class="nav-label">Kategori</span>
        </a>
        <a href="<?= url('admin/pengguna.php') ?>" class="nav-item <?= adminSidebarIsActive('admin/pengguna') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span class="nav-label">Pengguna</span>
        </a>
        <a href="<?= url('admin/laporan.php') ?>" class="nav-item <?= adminSidebarIsActive('admin/laporan') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            <span class="nav-label">Laporan</span>
        </a>
        <a href="<?= url('admin/profil.php') ?>" class="nav-item <?= adminSidebarIsActive('admin/profil') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span class="nav-label">Profil Admin</span>
        </a>
    </nav>

    <div class="sidebar-bottom">
        <a href="<?= url('logout.php') ?>" class="logout-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            <span class="nav-label">Keluar</span>
        </a>
    </div>
</aside>
