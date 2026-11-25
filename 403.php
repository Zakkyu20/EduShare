<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

if (!headers_sent()) {
    http_response_code(403);
}

$user = current_user();
$role = get_user_role();
$backUrl = $user !== null ? role_home_url($role) : url('index.php');
$backLabel = $user !== null ? 'Kembali ke Dashboard' : 'Kembali ke Beranda';
$secondaryUrl = $user !== null ? url('index.php') : url('login.php');
$secondaryLabel = $user !== null ? 'Ke Beranda' : 'Masuk ke Akun';
$siteName = (string)get_setting('site_name', 'EduShare');
$errorMessage = $GLOBALS['_edushare_403_message']
    ?? 'Maaf, Anda tidak memiliki izin untuk mengakses halaman atau melakukan tindakan ini.';
$GLOBALS['_edushare_403_message'] = null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Akses Ditolak | <?= e($siteName) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <style>
        .forbidden-shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: linear-gradient(140deg, #faf9ff 0%, #f3efff 55%, #ffffff 100%);
        }
        .forbidden-card {
            width: min(560px, 100%);
            background: #ffffff;
            border: 1px solid #eceaf2;
            border-radius: 22px;
            padding: 40px 36px;
            text-align: center;
            box-shadow: 0 22px 55px rgba(45, 27, 91, 0.08);
        }
        .forbidden-brand {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font: 800 18px 'Plus Jakarta Sans', sans-serif;
            margin-bottom: 24px;
            color: #242238;
        }
        .forbidden-code {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 18px;
            border-radius: 999px;
            background: #f3efff;
            color: #673de6;
            font: 800 13px 'Plus Jakarta Sans', sans-serif;
            letter-spacing: 1px;
            margin-bottom: 16px;
        }
        .forbidden-card h1 {
            margin: 0 0 12px;
            font-size: 28px;
            color: #242238;
        }
        .forbidden-card p {
            margin: 0 0 26px;
            color: #77758b;
            font-size: 14px;
            line-height: 1.75;
        }
        .forbidden-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
        }
        @media (max-width: 650px) {
            .forbidden-card {
                padding: 30px 22px;
            }
            .forbidden-card h1 {
                font-size: 23px;
            }
            .forbidden-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <main class="forbidden-shell">
        <section class="forbidden-card" role="alert">
            <a class="forbidden-brand" href="<?= e(url('index.php')) ?>">
                <span class="brand-mark">E</span>
                <span><?= e($siteName) ?></span>
            </a>
            <div>
                <span class="forbidden-code">HTTP 403 FORBIDDEN</span>
            </div>
            <h1>403 - Akses Ditolak</h1>
            <p><?= e($errorMessage) ?></p>
            <div class="forbidden-actions">
                <a class="btn btn-primary" href="<?= e($backUrl) ?>"><?= e($backLabel) ?></a>
                <a class="btn btn-outline" href="<?= e($secondaryUrl) ?>"><?= e($secondaryLabel) ?></a>
            </div>
        </section>
    </main>
</body>
</html>
