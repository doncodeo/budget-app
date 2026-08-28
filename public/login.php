<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';

if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $error = attempt_login($_POST['username'] ?? '', $_POST['password'] ?? '');
    if ($error === null) {
        header('Location: dashboard.php');
        exit;
    }
}

$user = null;
$pageTitle = 'Log in';
require_once __DIR__ . '/../src/render.php';

render_template('login.twig', [
    'user' => $user,
    'pageTitle' => $pageTitle,
    'error' => $error,
]);
