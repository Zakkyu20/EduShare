<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    require_csrf();
}

$sessionUser = (isset($_SESSION['user']) && is_array($_SESSION['user'])) ? $_SESSION['user'] : null;
if ($sessionUser !== null) {
    $uid = (int)($sessionUser['id_user'] ?? $sessionUser['id'] ?? 0);
    $role = isset($sessionUser['role']) ? (string)$sessionUser['role'] : null;
    if ($uid > 0) {
        log_audit('logout', 'user', $uid, 'Pengguna logout.', ['role' => $role], $uid, $role);
        log_activity($uid, 'logout', 'Keluar dari platform EduShare', 'user', $uid);
    }
}

$_SESSION = [];

if (ini_get('session.use_cookies') && !headers_sent()) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $params['path'] ?? '/',
        'domain'   => $params['domain'] ?? '',
        'secure'   => (bool)($params['secure'] ?? false),
        'httponly' => (bool)($params['httponly'] ?? true),
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

clear_auth_cache();
redirect(url('index.php'));
