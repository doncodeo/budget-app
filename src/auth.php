<?php
declare(strict_types=1);

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    if ($user !== null) {
        return $user;
    }
    $stmt = get_db()->prepare('SELECT u.id, u.username, u.active_budget_id, u.theme, b.name AS budget_name, b.currency_symbol, b.currency_code
                               FROM users u
                               LEFT JOIN budgets b ON b.id = u.active_budget_id
                               WHERE u.id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        session_destroy();
        return null;
    }
    $user = $row;
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    return $user;
}

function attempt_register(string $username, string $password, string $confirm): ?string
{
    $username = trim($username);
    if ($username === '' || strlen($username) < 3) {
        return 'Username must be at least 3 characters.';
    }
    if (strlen($password) < 6) {
        return 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        return 'Passwords do not match.';
    }

    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return 'That username is already taken.';
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)')->execute([$username, $hash]);
    $userId = (int)$pdo->lastInsertId();

    seed_new_user($pdo, $userId, (int)date('Y'));

    $_SESSION['user_id'] = $userId;
    return null;
}

function attempt_login(string $username, string $password): ?string
{
    $stmt = get_db()->prepare('SELECT id, password_hash FROM users WHERE username = ?');
    $stmt->execute([trim($username)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !password_verify($password, $row['password_hash'])) {
        return 'Incorrect username or password.';
    }
    $_SESSION['user_id'] = (int)$row['id'];
    session_regenerate_id(true);
    return null;
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

function check_csrf(): void
{
    $token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(400);
        die('Invalid or expired form submission. Please go back and try again.');
    }
}
