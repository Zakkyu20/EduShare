<?php
declare(strict_types=1);

$envHost = getenv('EDUSHARE_DB_HOST');
$envDb   = getenv('EDUSHARE_DB_NAME');
$envUser = getenv('EDUSHARE_DB_USER');
$envPass = getenv('EDUSHARE_DB_PASS');

$host    = ($envHost !== false && $envHost !== '') ? $envHost : 'localhost';
$db      = ($envDb !== false && $envDb !== '') ? $envDb : 'edushare';
$user    = ($envUser !== false && $envUser !== '') ? $envUser : 'root';
$pass    = ($envPass !== false) ? $envPass : '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $GLOBALS['pdo'] = $pdo;
} catch (PDOException $e) {
    error_log('EduShare DB connection error: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    exit('Koneksi database gagal. Periksa konfigurasi di config/database.php dan pastikan database edushare sudah dibuat.');
}

if (!function_exists('db')) {
    function db(): PDO
    {
        return $GLOBALS['pdo'];
    }
}
