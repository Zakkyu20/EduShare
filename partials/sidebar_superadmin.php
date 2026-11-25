<?php
// partials/sidebar_superadmin.php
// Requires: current_user() helper available via app.php bootstrap
$sidebarUser = current_user();
$currentPage = $_SERVER['PHP_SELF'];

function superAdminSidebarIsActive(string $path): string {
    global $currentPage;
    return str_contains($currentPage, $path) ? ' active' : '';
}

$saFirstLetter = strtoupper(mb_substr($sidebarUser['nama'] ?? $sidebarUser['name'] ?? 'S', 0, 1));
?>
<aside class="sidebar sidebar-superadmin">
    <div class="brand">
        <a href="<?= url('superadmin/dashboard.php') ?>" class="brand-link">
            <span class="brand-mark">E</span>
            <span class="brand-name">EduShare SA</span>
        </a>
    </div>

    <div class="user-info">
        <div class="user-avatar user-avatar-sa">
            <?= e($saFirstLetter) ?>
        </div>
        <div class="user-meta">
            <div class="user-name"><?= e($sidebarUser['nama'] ?? $sidebarUser['name'] ?? 'Super Admin') ?></div>
            <span class="role-badge badge-super_admin">Super Admin</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="<?= url('superadmin/dashboard.php') ?>" class="nav-item <?= superAdminSidebarIsActive('superadmin/dashboard') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            <span class="nav-label">Dashboard</span>
        </a>
        <a href="<?= url('superadmin/admins.php') ?>" class="nav-item <?= superAdminSidebarIsActive('superadmin/admins') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><circle cx="12" cy="10" r="3"/></svg>
            <span class="nav-label">Manajemen Admin</span>
        </a>
        <a href="<?= url('superadmin/pengguna.php') ?>" class="nav-item <?= superAdminSidebarIsActive('superadmin/pengguna') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span class="nav-label">Manajemen Pengguna</span>
        </a>
        <a href="<?= url('superadmin/materi.php') ?>" class="nav-item <?= superAdminSidebarIsActive('superadmin/materi') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            <span class="nav-label">Semua Materi</span>
        </a>
        <a href="<?= url('superadmin/kategori.php') ?>" class="nav-item <?= superAdminSidebarIsActive('superadmin/kategori') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            <span class="nav-label">Kategori</span>
        </a>
        <a href="<?= url('superadmin/roles.php') ?>" class="nav-item <?= superAdminSidebarIsActive('superadmin/roles') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span class="nav-label">Role &amp; Hak Akses</span>
        </a>
        <a href="<?= url('superadmin/audit.php') ?>" class="nav-item <?= superAdminSidebarIsActive('superadmin/audit') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
            <span class="nav-label">Audit Log</span>
        </a>
        <a href="<?= url('superadmin/konfigurasi.php') ?>" class="nav-item <?= superAdminSidebarIsActive('superadmin/konfigurasi') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            <span class="nav-label">Konfigurasi Sistem</span>
        </a>
        <a href="<?= url('superadmin/keamanan.php') ?>" class="nav-item <?= superAdminSidebarIsActive('superadmin/keamanan') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span class="nav-label">Keamanan</span>
        </a>
        <a href="<?= url('superadmin/profil.php') ?>" class="nav-item <?= superAdminSidebarIsActive('superadmin/profil') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span class="nav-label">Profil</span>
        </a>
    </nav>

    <div class="sidebar-bottom">
        <a href="<?= url('logout.php') ?>" class="logout-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            <span class="nav-label">Keluar</span>
        </a>
    </div>
</aside>
