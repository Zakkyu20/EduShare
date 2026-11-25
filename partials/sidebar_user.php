<?php
// partials/sidebar_user.php
// Requires: current_user() helper available via app.php bootstrap
$sidebarUser = current_user();
$currentPage = $_SERVER['PHP_SELF'];

function userSidebarIsActive(string $path): string {
    global $currentPage;
    return str_contains($currentPage, $path) ? ' active' : '';
}

$userFirstLetter = strtoupper(mb_substr($sidebarUser['nama'] ?? $sidebarUser['name'] ?? 'U', 0, 1));
?>
<aside class="sidebar sidebar-user">
    <div class="brand">
        <a href="<?= url('user/dashboard.php') ?>" class="brand-link">
            <span class="brand-mark">E</span>
            <span class="brand-name">EduShare</span>
        </a>
    </div>

    <div class="user-info">
        <div class="user-avatar user-avatar-purple">
            <?= e($userFirstLetter) ?>
        </div>
        <div class="user-meta">
            <div class="user-name"><?= e($sidebarUser['nama'] ?? $sidebarUser['name'] ?? 'Pengguna') ?></div>
            <span class="role-badge badge-user">Pengguna</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="<?= url('user/dashboard.php') ?>" class="nav-item <?= userSidebarIsActive('user/dashboard') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            <span class="nav-label">Dashboard</span>
        </a>
        <a href="<?= url('materi.php') ?>" class="nav-item <?= userSidebarIsActive('/materi.php') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
            <span class="nav-label">Jelajahi Materi</span>
        </a>
        <a href="<?= url('user/materi_saya.php') ?>" class="nav-item <?= userSidebarIsActive('user/materi_saya') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            <span class="nav-label">Materi Saya</span>
        </a>
        <a href="<?= url('user/upload.php') ?>" class="nav-item <?= userSidebarIsActive('user/upload') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <span class="nav-label">Upload Materi</span>
        </a>
        <a href="<?= url('user/riwayat.php') ?>" class="nav-item <?= userSidebarIsActive('user/riwayat') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span class="nav-label">Riwayat Upload</span>
        </a>
        <a href="<?= url('user/profil.php') ?>" class="nav-item <?= userSidebarIsActive('user/profil') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span class="nav-label">Profil Saya</span>
        </a>
        <a href="<?= url('user/pengaturan.php') ?>" class="nav-item <?= userSidebarIsActive('user/pengaturan') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            <span class="nav-label">Pengaturan</span>
        </a>
    </nav>

    <div class="sidebar-bottom">
        <a href="<?= url('logout.php') ?>" class="logout-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            <span class="nav-label">Keluar</span>
        </a>
    </div>
</aside>
