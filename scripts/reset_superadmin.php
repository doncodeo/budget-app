<?php
require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/db.php';

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

$email = $argv[1] ?? 'doncodeo@gmail.com';
$newPassword = $argv[2] ?? 'SuperSecretPassword123!';

$pdo = get_db();
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    echo "User '$email' not found. Creating user...\n";
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
    $stmt->execute([$email, $hash]);
    $userId = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare("INSERT INTO budgets (name, owner_id) VALUES (?, ?)");
    $stmt->execute(["$email's Budget", $userId]);
    $budgetId = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare("INSERT INTO budget_members (budget_id, user_id, role) VALUES (?, ?, 'owner')");
    $stmt->execute([$budgetId, $userId]);
    echo "User '$email' created successfully with budget ID $budgetId.\n";
} else {
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$hash, $user['id']]);
    echo "Password updated successfully for super admin '$email'.\n";
}
