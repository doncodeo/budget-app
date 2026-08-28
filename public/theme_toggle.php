<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/render.php';

$user = require_login();
$pdo = get_db();
$userId = (int)$user['id'];

$newTheme = $user['theme'] === 'dark' ? 'light' : 'dark';
$stmt = $pdo->prepare('UPDATE users SET theme = ? WHERE id = ?');
$stmt->execute([$newTheme, $userId]);

$referer = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
header("Location: $referer");
exit;
