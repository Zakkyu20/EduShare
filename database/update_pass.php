<?php
$pdo = new PDO('mysql:host=localhost;dbname=edushare;charset=utf8mb4', 'root', '');
$hash = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = 'user@edushare.com'");
$stmt->execute([$hash]);
echo "Updated user@edushare.com to admin123\n";
