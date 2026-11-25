-- EduShare RBAC 4-Tier Upgrade Migration (001_rbac_upgrade.sql)
-- Target: MariaDB 10.4+ / InnoDB / utf8mb4_unicode_ci
-- Safe, non-destructive upgrade preserving existing users, categories, and materials.
-- NOTE: Do NOT hardcode super_admin accounts or passwords here.
--       Run `php database/setup_super_admin.php` from CLI after migration.

-- 1. Upgrade `users` table
ALTER TABLE users
    MODIFY COLUMN role ENUM('user','admin','super_admin') NOT NULL DEFAULT 'user';

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active' AFTER role,
    ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL DEFAULT NULL AFTER status,
    ADD COLUMN IF NOT EXISTS created_by INT(11) NULL DEFAULT NULL AFTER last_login_at,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE users
    ADD INDEX IF NOT EXISTS idx_users_role_status (role, status),
    ADD INDEX IF NOT EXISTS idx_users_created_by (created_by);

ALTER TABLE users
    ADD CONSTRAINT fk_users_created_by
        FOREIGN KEY IF NOT EXISTS (created_by) REFERENCES users(id_user) ON DELETE SET NULL;

-- 2. Upgrade `materials` table
ALTER TABLE materials
    MODIFY COLUMN status ENUM('pending','approved','rejected','takedown') NOT NULL DEFAULT 'pending';

ALTER TABLE materials
    ADD COLUMN IF NOT EXISTS file_size INT(11) NOT NULL DEFAULT 0 AFTER format_file,
    ADD COLUMN IF NOT EXISTS download_count INT(11) NOT NULL DEFAULT 0 AFTER file_size,
    ADD COLUMN IF NOT EXISTS reviewed_by INT(11) NULL DEFAULT NULL AFTER status,
    ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL DEFAULT NULL AFTER reviewed_by,
    ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL DEFAULT NULL AFTER reviewed_at,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER tanggal_upload;

ALTER TABLE materials
    ADD INDEX IF NOT EXISTS idx_materials_status_tanggal (status, tanggal_upload),
    ADD INDEX IF NOT EXISTS idx_materials_reviewed_by (reviewed_by);

ALTER TABLE materials
    ADD CONSTRAINT fk_materials_reviewed_by
        FOREIGN KEY IF NOT EXISTS (reviewed_by) REFERENCES users(id_user) ON DELETE SET NULL;

-- 3. Upgrade `categories` table
ALTER TABLE categories
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER deskripsi,
    ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER is_active;

ALTER TABLE categories
    ADD INDEX IF NOT EXISTS idx_categories_active (is_active);

-- 4. Create `role_permissions` table
CREATE TABLE IF NOT EXISTS role_permissions (
    id_permission INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    role ENUM('guest','user','admin','super_admin') NOT NULL,
    permission_key VARCHAR(100) NOT NULL,
    permission_label VARCHAR(150) NOT NULL,
    is_allowed TINYINT(1) NOT NULL DEFAULT 1,
    updated_by INT(11) NULL DEFAULT NULL,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_role_perm (role, permission_key),
    KEY idx_role_perm_updated_by (updated_by),
    CONSTRAINT fk_role_perm_updated_by FOREIGN KEY (updated_by) REFERENCES users(id_user) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create `system_settings` table
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_group ENUM('system','security') NOT NULL DEFAULT 'system',
    setting_value TEXT NULL,
    description VARCHAR(255) NULL DEFAULT NULL,
    updated_by INT(11) NULL DEFAULT NULL,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_settings_group (setting_group),
    KEY idx_settings_updated_by (updated_by),
    CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id_user) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Create `audit_logs` table
CREATE TABLE IF NOT EXISTS audit_logs (
    id_audit INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    actor_id INT(11) NULL DEFAULT NULL,
    actor_role VARCHAR(30) NULL DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50) NULL DEFAULT NULL,
    target_id INT(11) NULL DEFAULT NULL,
    description TEXT NULL,
    metadata LONGTEXT NULL,
    ip_address VARCHAR(45) NULL DEFAULT NULL,
    user_agent VARCHAR(255) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_actor (actor_id),
    KEY idx_audit_action_date (action, created_at),
    KEY idx_audit_target (target_type, target_id),
    KEY idx_audit_created_at (created_at),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users(id_user) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Create `activity_logs` table
CREATE TABLE IF NOT EXISTS activity_logs (
    id_activity INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_user INT(11) NULL DEFAULT NULL,
    activity_type VARCHAR(60) NOT NULL,
    entity_type VARCHAR(50) NULL DEFAULT NULL,
    entity_id INT(11) NULL DEFAULT NULL,
    summary VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_activity_user_date (id_user, created_at),
    KEY idx_activity_type_date (activity_type, created_at),
    CONSTRAINT fk_activity_user FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Seed `role_permissions` (34 rows across 4 roles)
INSERT IGNORE INTO role_permissions (role, permission_key, permission_label, is_allowed) VALUES
    ('guest', 'browse_materials', 'Menjelajahi daftar materi terpublikasi', 1),
    ('guest', 'view_material_detail', 'Melihat detail informasi materi', 1),
    ('guest', 'download_material', 'Mengunduh file materi', 0),
    ('guest', 'upload_material', 'Mengunggah materi pembelajaran', 0),
    ('user', 'browse_materials', 'Menjelajahi daftar materi terpublikasi', 1),
    ('user', 'view_material_detail', 'Melihat detail informasi materi', 1),
    ('user', 'download_material', 'Mengunduh file materi', 1),
    ('user', 'upload_material', 'Mengunggah materi pembelajaran', 1),
    ('user', 'edit_own_material', 'Mengedit materi milik sendiri', 1),
    ('user', 'delete_own_material', 'Menghapus materi milik sendiri', 1),
    ('admin', 'browse_materials', 'Menjelajahi daftar materi terpublikasi', 1),
    ('admin', 'view_material_detail', 'Melihat detail informasi materi', 1),
    ('admin', 'download_material', 'Mengunduh file materi', 1),
    ('admin', 'verify_material', 'Memverifikasi (setujui/tolak) materi', 1),
    ('admin', 'manage_materials', 'Mengelola dan takedown materi', 1),
    ('admin', 'manage_categories', 'Mengelola kategori pembelajaran', 1),
    ('admin', 'view_users', 'Melihat daftar dan aktivitas pengguna', 1),
    ('admin', 'view_reports', 'Melihat laporan dan statistik platform', 1),
    ('super_admin', 'browse_materials', 'Menjelajahi daftar materi terpublikasi', 1),
    ('super_admin', 'view_material_detail', 'Melihat detail informasi materi', 1),
    ('super_admin', 'download_material', 'Mengunduh file materi', 1),
    ('super_admin', 'upload_material', 'Mengunggah materi pembelajaran', 1),
    ('super_admin', 'edit_own_material', 'Mengedit materi milik sendiri', 1),
    ('super_admin', 'delete_own_material', 'Menghapus materi milik sendiri', 1),
    ('super_admin', 'verify_material', 'Memverifikasi (setujui/tolak) materi', 1),
    ('super_admin', 'manage_materials', 'Mengelola dan takedown materi', 1),
    ('super_admin', 'manage_categories', 'Mengelola kategori pembelajaran', 1),
    ('super_admin', 'view_users', 'Melihat daftar dan aktivitas pengguna', 1),
    ('super_admin', 'view_reports', 'Melihat laporan dan statistik platform', 1),
    ('super_admin', 'manage_admins', 'Mengelola akun administrator', 1),
    ('super_admin', 'manage_roles', 'Mengelola role dan hak akses', 1),
    ('super_admin', 'view_audit_logs', 'Melihat audit log dan log aktivitas sistem', 1),
    ('super_admin', 'manage_system_settings', 'Mengelola konfigurasi sistem', 1),
    ('super_admin', 'manage_security_settings', 'Mengelola pengaturan keamanan', 1);

-- 9. Seed `system_settings` (8 rows)
INSERT IGNORE INTO system_settings (setting_key, setting_group, setting_value, description) VALUES
    ('site_name', 'system', 'EduShare', 'Nama platform yang ditampilkan pada antarmuka aplikasi'),
    ('site_tagline', 'system', 'Ruang Berbagi Materi Pembelajaran Terbuka', 'Slogan atau deskripsi singkat platform EduShare'),
    ('maintenance_mode', 'system', '0', 'Status mode pemeliharaan sistem (1 = aktif, 0 = nonaktif)'),
    ('max_upload_mb', 'system', '10', 'Batas maksimum ukuran file materi yang diunggah (MB)'),
    ('allowed_extensions', 'system', 'pdf,doc,docx,ppt,pptx', 'Daftar ekstensi file materi yang diizinkan (pisahkan dengan koma)'),
    ('allow_registration', 'security', '1', 'Izinkan pendaftaran akun pengguna baru secara publik (1 = ya, 0 = tidak)'),
    ('session_timeout_min', 'security', '60', 'Batas waktu tidak aktif session sebelum kedaluwarsa (menit)'),
    ('min_password_length', 'security', '8', 'Panjang minimal karakter password akun baru');
