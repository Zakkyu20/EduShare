<?php
declare(strict_types=1);

if (!function_exists('clear_auth_cache')) {
    function clear_auth_cache(): void
    {
        $GLOBALS['_edushare_auth_user'] = null;
        $GLOBALS['_edushare_auth_loaded'] = false;
        $GLOBALS['_edushare_auth_uid'] = null;
    }
}

if (!function_exists('clear_permission_cache')) {
    function clear_permission_cache(): void
    {
        $GLOBALS['_edushare_perm_cache'] = null;
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        $sessionUserId = (int)($_SESSION['user']['id_user'] ?? $_SESSION['user']['id'] ?? 0);
        if ($sessionUserId <= 0) {
            clear_auth_cache();
            return null;
        }

        if (
            !empty($GLOBALS['_edushare_auth_loaded'])
            && ($GLOBALS['_edushare_auth_uid'] ?? null) === $sessionUserId
        ) {
            return $GLOBALS['_edushare_auth_user'];
        }

        try {
            $stmt = db()->prepare('SELECT id_user, nama, email, role, status FROM users WHERE id_user = ? LIMIT 1');
            $stmt->execute([$sessionUserId]);
            $row = $stmt->fetch();
        } catch (\Throwable $e) {
            error_log('EduShare current_user error: ' . $e->getMessage());
            clear_auth_cache();
            return null;
        }

        if (!$row || ($row['status'] ?? '') !== 'active') {
            unset($_SESSION['user'], $_SESSION['_csrf_token'], $_SESSION['last_activity']);
            clear_auth_cache();
            if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
            return null;
        }

        $user = [
            'id'      => (int)$row['id_user'],
            'id_user' => (int)$row['id_user'],
            'nama'    => (string)$row['nama'],
            'email'   => (string)$row['email'],
            'role'    => (string)$row['role'],
            'status'  => (string)$row['status'],
        ];

        $_SESSION['user'] = $user;
        $GLOBALS['_edushare_auth_user'] = $user;
        $GLOBALS['_edushare_auth_loaded'] = true;
        $GLOBALS['_edushare_auth_uid'] = $user['id_user'];

        return $user;
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        return current_user() !== null;
    }
}

if (!function_exists('get_user_role')) {
    function get_user_role(): string
    {
        $user = current_user();
        if ($user !== null && in_array($user['role'], ['user', 'admin', 'super_admin'], true)) {
            return $user['role'];
        }
        return 'guest';
    }
}

if (!function_exists('role_home_url')) {
    function role_home_url(?string $role = null): string
    {
        $resolvedRole = $role ?? get_user_role();
        return match ($resolvedRole) {
            'super_admin' => url('superadmin/dashboard.php'),
            'admin'       => url('admin/dashboard.php'),
            'user'        => url('user/dashboard.php'),
            default       => url('index.php'),
        };
    }
}

if (!function_exists('redirect_by_role')) {
    function redirect_by_role(?string $role = null): void
    {
        redirect(role_home_url($role));
    }
}

if (!function_exists('has_permission')) {
    function has_permission(string $permissionKey, ?string $role = null): bool
    {
        $targetRole = $role ?? get_user_role();
        if (!in_array($targetRole, ['guest', 'user', 'admin', 'super_admin'], true)) {
            return false;
        }

        if (
            $targetRole === 'super_admin'
            && in_array($permissionKey, [
                'manage_admins',
                'manage_roles',
                'view_audit_logs',
                'manage_system_settings',
                'manage_security_settings',
            ], true)
        ) {
            return true;
        }

        if (!isset($GLOBALS['_edushare_perm_cache']) || !is_array($GLOBALS['_edushare_perm_cache'])) {
            $cache = [];
            try {
                $stmt = db()->query('SELECT role, permission_key, is_allowed FROM role_permissions');
                foreach ($stmt->fetchAll() as $row) {
                    $r = (string)$row['role'];
                    $k = (string)$row['permission_key'];
                    $cache[$r][$k] = ((int)$row['is_allowed']) === 1;
                }
            } catch (\Throwable $e) {
                error_log('EduShare has_permission error: ' . $e->getMessage());
            }
            $GLOBALS['_edushare_perm_cache'] = $cache;
        }

        return (bool)($GLOBALS['_edushare_perm_cache'][$targetRole][$permissionKey] ?? false);
    }
}

if (!function_exists('render_403')) {
    function render_403(?string $message = null): never
    {
        $GLOBALS['_edushare_403_message'] = $message;
        if (!headers_sent()) {
            http_response_code(403);
        }
        include ROOT_PATH . '/403.php';
        exit;
    }
}

if (!function_exists('require_login')) {
    function require_login(): void
    {
        if (!is_logged_in()) {
            set_flash('error', 'Silakan login terlebih dahulu.');
            redirect(url('login.php'));
        }
    }
}

if (!function_exists('require_role')) {
    function require_role(array|string $roles): void
    {
        require_login();
        $allowed = is_array($roles) ? array_values($roles) : [$roles];
        $currentRole = get_user_role();

        if (!in_array($currentRole, $allowed, true)) {
            log_audit(
                'access_denied',
                'route',
                null,
                'Akses role ditolak.',
                [
                    'required_roles' => $allowed,
                    'actual_role'    => $currentRole,
                    'uri'            => $_SERVER['REQUEST_URI'] ?? '',
                ]
            );
            render_403();
        }
    }
}

if (!function_exists('require_admin')) {
    function require_admin(): void
    {
        require_role(['admin', 'super_admin']);
    }
}

if (!function_exists('require_super_admin')) {
    function require_super_admin(): void
    {
        require_role(['super_admin']);
    }
}

if (!function_exists('require_permission')) {
    function require_permission(string $permissionKey): void
    {
        if (has_permission($permissionKey)) {
            return;
        }

        if (!is_logged_in()) {
            require_login();
        }

        log_audit(
            'permission_denied',
            'permission',
            null,
            'Akses permission ditolak.',
            [
                'permission' => $permissionKey,
                'role'       => get_user_role(),
                'uri'        => $_SERVER['REQUEST_URI'] ?? '',
            ]
        );
        render_403();
    }
}
