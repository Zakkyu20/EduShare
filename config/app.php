<?php
declare(strict_types=1);

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// Deteksi otomatis lokasi aplikasi agar dapat berjalan via Apache/XAMPP
// maupun PHP built-in server dari dalam folder proyek.
if (PHP_SAPI === 'cli') {
    $baseUrl = '';
} else {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    if (preg_match('#^(.*?)/(?:admin|user|superadmin)/[^/]+$#', $scriptName, $matches)) {
        $baseUrl = rtrim($matches[1], '/');
    } else {
        $baseUrl = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    }
    if ($baseUrl === '/') {
        $baseUrl = '';
    }
}

if (!defined('BASE_URL')) {
    define('BASE_URL', $baseUrl);
}

// Pengaturan keamanan session sebelum session dimulai (Web SAPI saja).
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

// Muat modul inti secara terurut.
require_once __DIR__ . '/database.php';
require_once ROOT_PATH . '/includes/helpers.php';
require_once ROOT_PATH . '/includes/logger.php';
require_once ROOT_PATH . '/middleware/auth.php';
require_once ROOT_PATH . '/middleware/csrf.php';

// Penegakan idle timeout session & sinkronisasi status akun sebelum output HTML.
if (PHP_SAPI !== 'cli' && !empty($_SESSION['user'])) {
    $timeoutMin = max(1, (int)get_setting('session_timeout_min', 60));
    if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > ($timeoutMin * 60)) {
        $timedOutUserId = (int)($_SESSION['user']['id_user'] ?? $_SESSION['user']['id'] ?? 0);
        $timedOutRole   = isset($_SESSION['user']['role']) ? (string)$_SESSION['user']['role'] : null;
        if ($timedOutUserId > 0) {
            log_activity($timedOutUserId, 'session_timeout', 'Sesi berakhir otomatis karena tidak aktif.', 'user', $timedOutUserId);
            log_audit('session_timeout', 'user', $timedOutUserId, 'Sesi berakhir otomatis karena tidak aktif.', [], $timedOutUserId, $timedOutRole);
        }
        $_SESSION = [];
        if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        clear_auth_cache();
        $_SESSION['flash'] = [
            'type'    => 'error',
            'message' => 'Sesi Anda telah berakhir karena tidak aktif. Silakan login kembali.',
        ];
    } else {
        $_SESSION['last_activity'] = time();
        current_user();
    }
}
