<?php
declare(strict_types=1);

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        if (!is_scalar($value) && !($value instanceof \Stringable)) {
            return '';
        }

        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('post_string')) {
    function post_string(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? null;
        return is_string($value) ? $value : $default;
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return BASE_URL . '/' . ltrim($path, '/');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path, int $statusCode = 302): never
    {
        $cleanPath = str_replace(["\r", "\n", "\0"], '', $path);
        header('Location: ' . $cleanPath, true, $statusCode);
        exit;
    }
}

if (!function_exists('set_flash')) {
    function set_flash(string $type, string $message): void
    {
        $_SESSION['flash'] = [
            'type'    => $type,
            'message' => $message,
        ];
    }
}

if (!function_exists('flash')) {
    function flash(?string $type = null, ?string $message = null): void
    {
        if ($type !== null && $message !== null) {
            set_flash($type, $message);
            return;
        }

        if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
            return;
        }

        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $flashType = (string)($f['type'] ?? 'info');
        $flashMsg  = (string)($f['message'] ?? '');
        echo '<div class="flash ' . e($flashType) . '">' . e($flashMsg) . '</div>';
    }
}

if (!function_exists('clear_settings_cache')) {
    function clear_settings_cache(): void
    {
        $GLOBALS['_edushare_settings_cache'] = null;
    }
}

if (!function_exists('get_setting')) {
    function get_setting(string $key, mixed $default = null): mixed
    {
        if (!isset($GLOBALS['_edushare_settings_cache']) || !is_array($GLOBALS['_edushare_settings_cache'])) {
            $cache = [];
            try {
                $stmt = db()->query('SELECT setting_key, setting_value FROM system_settings');
                foreach ($stmt->fetchAll() as $row) {
                    $cache[(string)$row['setting_key']] = $row['setting_value'];
                }
            } catch (\Throwable $e) {
                error_log('EduShare get_setting error: ' . $e->getMessage());
            }
            $GLOBALS['_edushare_settings_cache'] = $cache;
        }

        $settings = $GLOBALS['_edushare_settings_cache'];
        if (array_key_exists($key, $settings) && $settings[$key] !== null) {
            return $settings[$key];
        }

        return $default;
    }
}

if (!function_exists('format_bytes')) {
    function format_bytes(int|float $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = (int)floor(log((float)$bytes, 1024));
        $pow = max(0, min($pow, count($units) - 1));
        $value = (float)$bytes / (1024 ** $pow);
        $decimals = $pow === 0 ? 0 : max(0, $precision);

        return round($value, $decimals) . ' ' . $units[$pow];
    }
}

if (!function_exists('safe_unlink_upload')) {
    function safe_unlink_upload(?string $filename): bool
    {
        if ($filename === null) {
            return false;
        }

        $filename = trim($filename);
        if (
            $filename === ''
            || str_contains($filename, "\0")
            || str_contains($filename, '/')
            || str_contains($filename, '\\')
            || str_starts_with($filename, '.')
            || strtolower($filename) === '.htaccess'
            || basename($filename) !== $filename
        ) {
            return false;
        }

        $uploadsDir = realpath(ROOT_PATH . '/uploads');
        if ($uploadsDir === false || !is_dir($uploadsDir)) {
            return false;
        }

        $target = realpath($uploadsDir . DIRECTORY_SEPARATOR . $filename);
        if ($target === false || !is_file($target)) {
            return false;
        }

        $prefix = $uploadsDir . DIRECTORY_SEPARATOR;
        if (DIRECTORY_SEPARATOR === '\\') {
            if (!str_starts_with(strtolower($target), strtolower($prefix))) {
                return false;
            }
        } elseif (!str_starts_with($target, $prefix)) {
            return false;
        }

        return @unlink($target);
    }
}

if (!function_exists('current_url_path')) {
    function current_url_path(): string
    {
        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $parsedPath = parse_url($requestUri, PHP_URL_PATH);
        $path = str_replace('\\', '/', is_string($parsedPath) ? $parsedPath : '/');

        if (BASE_URL !== '' && ($path === BASE_URL || str_starts_with($path, BASE_URL . '/'))) {
            $path = substr($path, strlen(BASE_URL));
        }

        return '/' . ltrim((string)$path, '/');
    }
}

if (!function_exists('is_maintenance_mode')) {
    function is_maintenance_mode(): bool
    {
        return (string)get_setting('maintenance_mode', '0') === '1';
    }
}
