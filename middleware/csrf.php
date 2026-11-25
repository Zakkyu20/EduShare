<?php
declare(strict_types=1);

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(?string $token = null): bool
    {
        $sessionToken = $_SESSION['_csrf_token'] ?? '';
        $submitted = $token ?? ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if (
            !is_string($sessionToken)
            || $sessionToken === ''
            || !is_string($submitted)
            || $submitted === ''
        ) {
            return false;
        }

        return hash_equals($sessionToken, $submitted);
    }
}

if (!function_exists('require_csrf')) {
    function require_csrf(): void
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        if (verify_csrf()) {
            return;
        }

        log_audit(
            'csrf_failed',
            'http_request',
            null,
            'Validasi token CSRF gagal.',
            [
                'method' => $method,
                'uri'    => $_SERVER['REQUEST_URI'] ?? '',
            ]
        );

        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $xrw    = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        if (str_contains($accept, 'application/json') || $xrw === 'xmlhttprequest') {
            if (!headers_sent()) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['error' => 'Token CSRF tidak valid.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        render_403(
            'Permintaan ditolak karena token keamanan (CSRF) tidak valid atau sesi telah kedaluwarsa. Silakan muat ulang halaman dan coba lagi.'
        );
    }
}
