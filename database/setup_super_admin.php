<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("403 Forbidden: database/setup_super_admin.php can only be executed via CLI.\n");
}

require_once dirname(__DIR__) . '/config/database.php';

function printUsage(): void
{
    echo <<<TEXT
EduShare Super Admin Bootstrap CLI

Usage:
  php database/setup_super_admin.php [options]

Options:
  --email=<email>      Email for Super Admin (default: superadmin@edushare.com)
  --nama=<name>        Display name (default: Super Admin EduShare)
  --password=<pass>    Explicit password (min 8 chars). If omitted, generates a 16-char CSPRNG password.
  --reset-password     Reset password for existing super_admin account (sets status='active')
  -h, --help           Show this help message

TEXT;
}

function generateStrongPassword(int $length = 16): string
{
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower = 'abcdefghijkmnopqrstuvwxyz';
    $digits = '23456789';
    $symbols = '!@#$%^&*-_=+';
    $all = $upper . $lower . $digits . $symbols;

    $chars = [
        $upper[random_int(0, strlen($upper) - 1)],
        $lower[random_int(0, strlen($lower) - 1)],
        $digits[random_int(0, strlen($digits) - 1)],
        $symbols[random_int(0, strlen($symbols) - 1)],
    ];

    for ($i = count($chars); $i < $length; $i++) {
        $chars[] = $all[random_int(0, strlen($all) - 1)];
    }

    // Fisher-Yates shuffle with CSPRNG random_int
    for ($i = count($chars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        $tmp = $chars[$i];
        $chars[$i] = $chars[$j];
        $chars[$j] = $tmp;
    }

    return implode('', $chars);
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
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

// Strict CLI flag parsing and validation (reject unknown flags/arguments)
$rawArgs = array_slice($argv, 1);
$allowedValueFlags = ['email', 'nama', 'password'];
$allowedBoolFlags = ['reset-password', 'help'];

$parsed = [
    'email' => 'superadmin@edushare.com',
    'nama' => 'Super Admin EduShare',
    'password' => null,
    'reset-password' => false,
    'help' => false,
];
$explicitlyProvided = [
    'email' => false,
    'nama' => false,
];

$argCount = count($rawArgs);
for ($i = 0; $i < $argCount; $i++) {
    $arg = $rawArgs[$i];

    if ($arg === '-h' || $arg === '--help') {
        $parsed['help'] = true;
        continue;
    }

    if (str_starts_with($arg, '--')) {
        $body = substr($arg, 2);
        if (str_contains($body, '=')) {
            [$key, $val] = explode('=', $body, 2);
            if (!in_array($key, $allowedValueFlags, true)) {
                fwrite(STDERR, "[ERROR] Unknown option: --{$key}\n");
                exit(1);
            }
            $parsed[$key] = $val;
            if (array_key_exists($key, $explicitlyProvided)) {
                $explicitlyProvided[$key] = true;
            }
        } else {
            if (in_array($body, $allowedBoolFlags, true)) {
                $parsed[$body] = true;
            } elseif (in_array($body, $allowedValueFlags, true)) {
                if ($i + 1 >= $argCount || str_starts_with($rawArgs[$i + 1], '-')) {
                    fwrite(STDERR, "[ERROR] Option --{$body} requires a value.\n");
                    exit(1);
                }
                $parsed[$body] = $rawArgs[++$i];
                if (array_key_exists($body, $explicitlyProvided)) {
                    $explicitlyProvided[$body] = true;
                }
            } else {
                fwrite(STDERR, "[ERROR] Unknown option: --{$body}\n");
                exit(1);
            }
        }
        continue;
    }

    fwrite(STDERR, "[ERROR] Unexpected argument: {$arg}\n");
    exit(1);
}

if ($parsed['help']) {
    printUsage();
    exit(0);
}

try {
    // Check prerequisite schema from M1 migration
    if (!columnExists($pdo, 'users', 'status') || !tableExists($pdo, 'audit_logs')) {
        fwrite(STDERR, "[ERROR] Prerequisite schema missing (users.status or audit_logs). Run `php database/migrate.php` first.\n");
        exit(1);
    }

    $email = trim((string) $parsed['email']);
    $nama = trim((string) $parsed['nama']);
    $resetPassword = (bool) $parsed['reset-password'];

    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 150) {
        fwrite(STDERR, "[ERROR] Invalid --email value.\n");
        exit(1);
    }
    if ($nama === '' || mb_strlen($nama, 'UTF-8') > 100) {
        fwrite(STDERR, "[ERROR] Invalid --nama value (must be 1..100 characters).\n");
        exit(1);
    }

    $plainPassword = $parsed['password'];
    $wasGenerated = false;
    if ($plainPassword !== null) {
        $plainPassword = (string) $plainPassword;
        if (strlen($plainPassword) < 8) {
            fwrite(STDERR, "[ERROR] --password must be at least 8 characters long.\n");
            exit(1);
        }
    } else {
        $plainPassword = generateStrongPassword(16);
        $wasGenerated = true;
    }

    // Check if a super_admin already exists
    $existingStmt = $pdo->query(
        "SELECT id_user, nama, email, role, status, created_at
         FROM users
         WHERE role = 'super_admin'
         ORDER BY id_user ASC
         LIMIT 1"
    );
    $existingSuperAdmin = $existingStmt->fetch();

    if ($existingSuperAdmin !== false && !$resetPassword) {
        echo "=== Super Admin Already Exists (Idempotent Check) ===\n";
        echo "No changes were made to the database.\n";
        echo "ID     : {$existingSuperAdmin['id_user']}\n";
        echo "Nama   : {$existingSuperAdmin['nama']}\n";
        echo "Email  : {$existingSuperAdmin['email']}\n";
        echo "Role   : {$existingSuperAdmin['role']}\n";
        echo "Status : {$existingSuperAdmin['status']}\n";
        echo "Note   : Pass --reset-password to rotate the existing Super Admin password.\n";
        exit(0);
    }

    $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);

    if ($existingSuperAdmin !== false && $resetPassword) {
        $targetId = (int) $existingSuperAdmin['id_user'];
        $targetEmail = $explicitlyProvided['email'] ? $email : (string) $existingSuperAdmin['email'];
        $targetNama = $explicitlyProvided['nama'] ? $nama : (string) $existingSuperAdmin['nama'];

        if ($explicitlyProvided['email'] && $targetEmail !== $existingSuperAdmin['email']) {
            $colStmt = $pdo->prepare('SELECT id_user, role FROM users WHERE email = ? AND id_user <> ? LIMIT 1');
            $colStmt->execute([$targetEmail, $targetId]);
            $collision = $colStmt->fetch();
            if ($collision !== false) {
                fwrite(STDERR, "[ERROR] Email '{$targetEmail}' is already used by user ID {$collision['id_user']} (role: {$collision['role']}).\n");
                exit(1);
            }
        }

        $pdo->beginTransaction();

        $updStmt = $pdo->prepare(
            "UPDATE users
             SET nama = ?, email = ?, password = ?, status = 'active', updated_at = NOW()
             WHERE id_user = ? AND role = 'super_admin'"
        );
        $updStmt->execute([$targetNama, $targetEmail, $passwordHash, $targetId]);

        $meta = json_encode([
            'mode' => 'reset_password',
            'via' => 'cli',
            'password_generated' => $wasGenerated,
        ], JSON_UNESCAPED_UNICODE);

        $auditStmt = $pdo->prepare(
            "INSERT INTO audit_logs (actor_id, actor_role, action, target_type, target_id, description, metadata, ip_address, user_agent)
             VALUES (?, 'super_admin', 'super_admin_bootstrap', 'user', ?, ?, ?, '127.0.0.1', 'CLI setup_super_admin.php')"
        );
        $auditStmt->execute([
            $targetId,
            $targetId,
            'Super Admin password reset via CLI bootstrap script',
            $meta,
        ]);

        $pdo->commit();

        echo "=== Super Admin Password Reset Successfully ===\n";
        echo "ID       : {$targetId}\n";
        echo "Nama     : {$targetNama}\n";
        echo "Email    : {$targetEmail}\n";
        echo "Role     : super_admin\n";
        echo "Status   : active\n";
        echo "Password : {$plainPassword}\n";
        echo "Notice   : Store these credentials securely and change/rotate if needed.\n";
        exit(0);
    }

    // No super_admin exists yet: refuse if target email belongs to an existing user/admin
    $colStmt = $pdo->prepare('SELECT id_user, role FROM users WHERE email = ? LIMIT 1');
    $colStmt->execute([$email]);
    $collision = $colStmt->fetch();
    if ($collision !== false) {
        fwrite(STDERR, "[ERROR] Email '{$email}' already exists (ID {$collision['id_user']}, role: {$collision['role']}). Refusing to overwrite or escalate.\n");
        exit(1);
    }

    $pdo->beginTransaction();

    $insStmt = $pdo->prepare(
        "INSERT INTO users (nama, email, password, role, status, created_at)
         VALUES (?, ?, ?, 'super_admin', 'active', NOW())"
    );
    $insStmt->execute([$nama, $email, $passwordHash]);
    $newId = (int) $pdo->lastInsertId();

    $meta = json_encode([
        'mode' => 'initial_create',
        'via' => 'cli',
        'password_generated' => $wasGenerated,
    ], JSON_UNESCAPED_UNICODE);

    $auditStmt = $pdo->prepare(
        "INSERT INTO audit_logs (actor_id, actor_role, action, target_type, target_id, description, metadata, ip_address, user_agent)
         VALUES (?, 'super_admin', 'super_admin_bootstrap', 'user', ?, ?, ?, '127.0.0.1', 'CLI setup_super_admin.php')"
    );
    $auditStmt->execute([
        $newId,
        $newId,
        'Initial Super Admin account bootstrapped via CLI',
        $meta,
    ]);

    $pdo->commit();

    echo "=== Super Admin Created Successfully ===\n";
    echo "ID       : {$newId}\n";
    echo "Nama     : {$nama}\n";
    echo "Email    : {$email}\n";
    echo "Role     : super_admin\n";
    echo "Status   : active\n";
    echo "Password : {$plainPassword}\n";
    echo "Notice   : Store these credentials securely. Re-running without --reset-password will not overwrite.\n";
    exit(0);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "[ERROR] Super Admin setup failed: " . $e->getMessage() . "\n");
    exit(1);
}
