<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("403 Forbidden: database/migrate.php can only be executed via CLI.\n");
}

require_once dirname(__DIR__) . '/config/database.php';

function getColumnType(PDO $pdo, string $table, string $column): ?string
{
    $stmt = $pdo->prepare(
        'SELECT COLUMN_TYPE
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    $val = $stmt->fetchColumn();
    return $val === false ? null : (string) $val;
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    return getColumnType($pdo, $table, $column) !== null;
}

function indexExists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);
    return (int) $stmt->fetchColumn() > 0;
}

function foreignKeyExists(PDO $pdo, string $table, string $constraint): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND CONSTRAINT_NAME = ?
           AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
    );
    $stmt->execute([$table, $constraint]);
    return (int) $stmt->fetchColumn() > 0;
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function normalizeEnumType(?string $type): string
{
    return strtolower(preg_replace('/\s+/', '', (string) $type));
}

function logMigration(string $status, string $message): void
{
    echo sprintf("[%s] %s\n", $status, $message);
}

$applied = 0;
$skipped = 0;

try {
    echo "=== EduShare M1 Database Migration ===\n";

    // Ensure prerequisite core tables exist
    foreach (['users', 'categories', 'materials'] as $coreTable) {
        if (!tableExists($pdo, $coreTable)) {
            fwrite(STDERR, "[ERROR] Core table `{$coreTable}` does not exist. Import base schema first.\n");
            exit(1);
        }
    }

    // -------------------------------------------------------------------------
    // Step A: ENUM upgrades (DDL outside transaction due to MariaDB implicit commit)
    // -------------------------------------------------------------------------
    $usersRoleType = normalizeEnumType(getColumnType($pdo, 'users', 'role'));
    if ($usersRoleType !== "enum('user','admin','super_admin')") {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('user','admin','super_admin') NOT NULL DEFAULT 'user'");
        logMigration('APPLY', "ALTER TABLE users.role -> ENUM('user','admin','super_admin')");
        $applied++;
    } else {
        logMigration('SKIP', 'users.role already upgraded');
        $skipped++;
    }

    $materialsStatusType = normalizeEnumType(getColumnType($pdo, 'materials', 'status'));
    if ($materialsStatusType !== "enum('pending','approved','rejected','takedown')") {
        $pdo->exec("ALTER TABLE materials MODIFY COLUMN status ENUM('pending','approved','rejected','takedown') NOT NULL DEFAULT 'pending'");
        logMigration('APPLY', "ALTER TABLE materials.status -> ENUM('pending','approved','rejected','takedown')");
        $applied++;
    } else {
        logMigration('SKIP', 'materials.status already upgraded');
        $skipped++;
    }

    // -------------------------------------------------------------------------
    // Step B: New columns on existing tables
    // -------------------------------------------------------------------------
    $columnMigrations = [
        [
            'table' => 'users',
            'column' => 'status',
            'sql' => "ALTER TABLE users ADD COLUMN status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active' AFTER role",
        ],
        [
            'table' => 'users',
            'column' => 'last_login_at',
            'sql' => "ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL DEFAULT NULL AFTER status",
        ],
        [
            'table' => 'users',
            'column' => 'created_by',
            'sql' => "ALTER TABLE users ADD COLUMN created_by INT(11) NULL DEFAULT NULL AFTER last_login_at",
        ],
        [
            'table' => 'users',
            'column' => 'updated_at',
            'sql' => "ALTER TABLE users ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
        ],
        [
            'table' => 'materials',
            'column' => 'file_size',
            'sql' => "ALTER TABLE materials ADD COLUMN file_size INT(11) NOT NULL DEFAULT 0 AFTER format_file",
        ],
        [
            'table' => 'materials',
            'column' => 'download_count',
            'sql' => "ALTER TABLE materials ADD COLUMN download_count INT(11) NOT NULL DEFAULT 0 AFTER file_size",
        ],
        [
            'table' => 'materials',
            'column' => 'reviewed_by',
            'sql' => "ALTER TABLE materials ADD COLUMN reviewed_by INT(11) NULL DEFAULT NULL AFTER status",
        ],
        [
            'table' => 'materials',
            'column' => 'reviewed_at',
            'sql' => "ALTER TABLE materials ADD COLUMN reviewed_at DATETIME NULL DEFAULT NULL AFTER reviewed_by",
        ],
        [
            'table' => 'materials',
            'column' => 'rejection_reason',
            'sql' => "ALTER TABLE materials ADD COLUMN rejection_reason TEXT NULL DEFAULT NULL AFTER reviewed_at",
        ],
        [
            'table' => 'materials',
            'column' => 'updated_at',
            'sql' => "ALTER TABLE materials ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER tanggal_upload",
        ],
        [
            'table' => 'categories',
            'column' => 'is_active',
            'sql' => "ALTER TABLE categories ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER deskripsi",
        ],
        [
            'table' => 'categories',
            'column' => 'created_at',
            'sql' => "ALTER TABLE categories ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER is_active",
        ],
    ];

    foreach ($columnMigrations as $item) {
        if (!columnExists($pdo, $item['table'], $item['column'])) {
            $pdo->exec($item['sql']);
            logMigration('APPLY', "Added column {$item['table']}.{$item['column']}");
            $applied++;
        } else {
            logMigration('SKIP', "Column {$item['table']}.{$item['column']} already exists");
            $skipped++;
        }
    }

    // -------------------------------------------------------------------------
    // Step C: Indexes on existing tables
    // -------------------------------------------------------------------------
    $indexMigrations = [
        [
            'table' => 'users',
            'index' => 'idx_users_role_status',
            'sql' => "ALTER TABLE users ADD INDEX idx_users_role_status (role, status)",
        ],
        [
            'table' => 'users',
            'index' => 'idx_users_created_by',
            'sql' => "ALTER TABLE users ADD INDEX idx_users_created_by (created_by)",
        ],
        [
            'table' => 'materials',
            'index' => 'idx_materials_status_tanggal',
            'sql' => "ALTER TABLE materials ADD INDEX idx_materials_status_tanggal (status, tanggal_upload)",
        ],
        [
            'table' => 'materials',
            'index' => 'idx_materials_reviewed_by',
            'sql' => "ALTER TABLE materials ADD INDEX idx_materials_reviewed_by (reviewed_by)",
        ],
        [
            'table' => 'categories',
            'index' => 'idx_categories_active',
            'sql' => "ALTER TABLE categories ADD INDEX idx_categories_active (is_active)",
        ],
    ];

    foreach ($indexMigrations as $item) {
        if (!indexExists($pdo, $item['table'], $item['index'])) {
            $pdo->exec($item['sql']);
            logMigration('APPLY', "Added index {$item['table']}.{$item['index']}");
            $applied++;
        } else {
            logMigration('SKIP', "Index {$item['table']}.{$item['index']} already exists");
            $skipped++;
        }
    }

    // -------------------------------------------------------------------------
    // Step D: Foreign keys on existing tables
    // -------------------------------------------------------------------------
    $fkMigrations = [
        [
            'table' => 'users',
            'constraint' => 'fk_users_created_by',
            'sql' => "ALTER TABLE users ADD CONSTRAINT fk_users_created_by FOREIGN KEY (created_by) REFERENCES users(id_user) ON DELETE SET NULL",
        ],
        [
            'table' => 'materials',
            'constraint' => 'fk_materials_reviewed_by',
            'sql' => "ALTER TABLE materials ADD CONSTRAINT fk_materials_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id_user) ON DELETE SET NULL",
        ],
    ];

    foreach ($fkMigrations as $item) {
        if (!foreignKeyExists($pdo, $item['table'], $item['constraint'])) {
            $pdo->exec($item['sql']);
            logMigration('APPLY', "Added foreign key {$item['table']}.{$item['constraint']}");
            $applied++;
        } else {
            logMigration('SKIP', "Foreign key {$item['table']}.{$item['constraint']} already exists");
            $skipped++;
        }
    }

    // -------------------------------------------------------------------------
    // Step E: New tables (InnoDB, utf8mb4_unicode_ci, signed INT(11))
    // -------------------------------------------------------------------------
    $newTables = [
        'role_permissions' => "
            CREATE TABLE role_permissions (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'system_settings' => "
            CREATE TABLE system_settings (
                setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
                setting_group ENUM('system','security') NOT NULL DEFAULT 'system',
                setting_value TEXT NULL,
                description VARCHAR(255) NULL DEFAULT NULL,
                updated_by INT(11) NULL DEFAULT NULL,
                updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_settings_group (setting_group),
                KEY idx_settings_updated_by (updated_by),
                CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id_user) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'audit_logs' => "
            CREATE TABLE audit_logs (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'activity_logs' => "
            CREATE TABLE activity_logs (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
    ];

    foreach ($newTables as $tableName => $createSql) {
        if (!tableExists($pdo, $tableName)) {
            $pdo->exec($createSql);
            logMigration('APPLY', "Created table {$tableName}");
            $applied++;
        } else {
            logMigration('SKIP', "Table {$tableName} already exists");
            $skipped++;
        }
    }

    // -------------------------------------------------------------------------
    // Step F: DML Seeds (wrapped in transaction)
    // -------------------------------------------------------------------------
    $permissionsSeed = [
        ['guest', 'browse_materials', 'Menjelajahi daftar materi terpublikasi', 1],
        ['guest', 'view_material_detail', 'Melihat detail informasi materi', 1],
        ['guest', 'download_material', 'Mengunduh file materi', 0],
        ['guest', 'upload_material', 'Mengunggah materi pembelajaran', 0],

        ['user', 'browse_materials', 'Menjelajahi daftar materi terpublikasi', 1],
        ['user', 'view_material_detail', 'Melihat detail informasi materi', 1],
        ['user', 'download_material', 'Mengunduh file materi', 1],
        ['user', 'upload_material', 'Mengunggah materi pembelajaran', 1],
        ['user', 'edit_own_material', 'Mengedit materi milik sendiri', 1],
        ['user', 'delete_own_material', 'Menghapus materi milik sendiri', 1],

        ['admin', 'browse_materials', 'Menjelajahi daftar materi terpublikasi', 1],
        ['admin', 'view_material_detail', 'Melihat detail informasi materi', 1],
        ['admin', 'download_material', 'Mengunduh file materi', 1],
        ['admin', 'verify_material', 'Memverifikasi (setujui/tolak) materi', 1],
        ['admin', 'manage_materials', 'Mengelola dan takedown materi', 1],
        ['admin', 'manage_categories', 'Mengelola kategori pembelajaran', 1],
        ['admin', 'view_users', 'Melihat daftar dan aktivitas pengguna', 1],
        ['admin', 'view_reports', 'Melihat laporan dan statistik platform', 1],

        ['super_admin', 'browse_materials', 'Menjelajahi daftar materi terpublikasi', 1],
        ['super_admin', 'view_material_detail', 'Melihat detail informasi materi', 1],
        ['super_admin', 'download_material', 'Mengunduh file materi', 1],
        ['super_admin', 'upload_material', 'Mengunggah materi pembelajaran', 1],
        ['super_admin', 'edit_own_material', 'Mengedit materi milik sendiri', 1],
        ['super_admin', 'delete_own_material', 'Menghapus materi milik sendiri', 1],
        ['super_admin', 'verify_material', 'Memverifikasi (setujui/tolak) materi', 1],
        ['super_admin', 'manage_materials', 'Mengelola dan takedown materi', 1],
        ['super_admin', 'manage_categories', 'Mengelola kategori pembelajaran', 1],
        ['super_admin', 'view_users', 'Melihat daftar dan aktivitas pengguna', 1],
        ['super_admin', 'view_reports', 'Melihat laporan dan statistik platform', 1],
        ['super_admin', 'manage_admins', 'Mengelola akun administrator', 1],
        ['super_admin', 'manage_roles', 'Mengelola role dan hak akses', 1],
        ['super_admin', 'view_audit_logs', 'Melihat audit log dan log aktivitas sistem', 1],
        ['super_admin', 'manage_system_settings', 'Mengelola konfigurasi sistem', 1],
        ['super_admin', 'manage_security_settings', 'Mengelola pengaturan keamanan', 1],
    ];

    $settingsSeed = [
        ['site_name', 'system', 'EduShare', 'Nama platform yang ditampilkan pada antarmuka aplikasi'],
        ['site_tagline', 'system', 'Ruang Berbagi Materi Pembelajaran Terbuka', 'Slogan atau deskripsi singkat platform EduShare'],
        ['maintenance_mode', 'system', '0', 'Status mode pemeliharaan sistem (1 = aktif, 0 = nonaktif)'],
        ['max_upload_mb', 'system', '10', 'Batas maksimum ukuran file materi yang diunggah (MB)'],
        ['allowed_extensions', 'system', 'pdf,doc,docx,ppt,pptx', 'Daftar ekstensi file materi yang diizinkan (pisahkan dengan koma)'],
        ['allow_registration', 'security', '1', 'Izinkan pendaftaran akun pengguna baru secara publik (1 = ya, 0 = tidak)'],
        ['session_timeout_min', 'security', '60', 'Batas waktu tidak aktif session sebelum kedaluwarsa (menit)'],
        ['min_password_length', 'security', '8', 'Panjang minimal karakter password akun baru'],
    ];

    $pdo->beginTransaction();

    $permStmt = $pdo->prepare(
        'INSERT IGNORE INTO role_permissions (role, permission_key, permission_label, is_allowed)
         VALUES (?, ?, ?, ?)'
    );
    $permInserted = 0;
    foreach ($permissionsSeed as $row) {
        $permStmt->execute($row);
        $permInserted += $permStmt->rowCount();
    }

    $settingStmt = $pdo->prepare(
        'INSERT IGNORE INTO system_settings (setting_key, setting_group, setting_value, description)
         VALUES (?, ?, ?, ?)'
    );
    $settingInserted = 0;
    foreach ($settingsSeed as $row) {
        $settingStmt->execute($row);
        $settingInserted += $settingStmt->rowCount();
    }

    $pdo->commit();

    if ($permInserted > 0) {
        logMigration('APPLY', "Seeded {$permInserted} role_permissions rows");
        $applied++;
    } else {
        logMigration('SKIP', 'All 34 role_permissions seed rows already present');
        $skipped++;
    }

    if ($settingInserted > 0) {
        logMigration('APPLY', "Seeded {$settingInserted} system_settings rows");
        $applied++;
    } else {
        logMigration('SKIP', 'All 8 system_settings seed rows already present');
        $skipped++;
    }

    echo "=== Migration complete: {$applied} applied, {$skipped} skipped ===\n";
    exit(0);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "[ERROR] Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
