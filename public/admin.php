<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/render.php';

$currentUser = require_super_admin();
$pdo = get_db();

$flash = null;
$flashType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'reset_password') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';

        if ($targetUserId <= 0) {
            $flash = 'Invalid user selected.';
            $flashType = 'danger';
        } elseif (strlen($newPassword) < 6) {
            $flash = 'New password must be at least 6 characters.';
            $flashType = 'danger';
        } else {
            $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
            $stmt->execute([$targetUserId]);
            $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$targetUser) {
                $flash = 'Target user not found.';
                $flashType = 'danger';
            } else {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $upd->execute([$hash, $targetUserId]);
                $flash = "Password for user '{$targetUser['username']}' has been successfully reset.";
                $flashType = 'success';
            }
        }
    } elseif ($action === 'delete_user') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);

        if ($targetUserId <= 0) {
            $flash = 'Invalid user selected.';
            $flashType = 'danger';
        } elseif ($targetUserId === (int)$currentUser['id']) {
            $flash = 'You cannot delete your own Super Admin account.';
            $flashType = 'danger';
        } else {
            $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
            $stmt->execute([$targetUserId]);
            $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$targetUser) {
                $flash = 'Target user not found.';
                $flashType = 'danger';
            } else {
                $pdo->beginTransaction();
                try {
                    // Delete related user records and memberships prior to user row deletion
                    $pdo->prepare('DELETE FROM income_allocations WHERE user_id = ?')->execute([$targetUserId]);
                    $pdo->prepare('DELETE FROM tracker_actuals WHERE user_id = ?')->execute([$targetUserId]);
                    $pdo->prepare('DELETE FROM other_income WHERE user_id = ?')->execute([$targetUserId]);
                    $pdo->prepare('DELETE FROM other_expenses WHERE user_id = ?')->execute([$targetUserId]);
                    $pdo->prepare('DELETE FROM transfers WHERE user_id = ?')->execute([$targetUserId]);
                    $pdo->prepare('DELETE FROM income WHERE user_id = ?')->execute([$targetUserId]);
                    $pdo->prepare('DELETE FROM categories WHERE user_id = ?')->execute([$targetUserId]);
                    $pdo->prepare('DELETE FROM budget_members WHERE user_id = ?')->execute([$targetUserId]);
                    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$targetUserId]);
                    $pdo->commit();

                    $flash = "User '{$targetUser['username']}' has been deleted from the platform.";
                    $flashType = 'success';
                } catch (\Throwable $e) {
                    $pdo->rollBack();
                    $flash = 'Failed to delete user: ' . $e->getMessage();
                    $flashType = 'danger';
                }
            }
        }
    }
}

// Fetch user registry with active budget info and household member counts
$users = $pdo->query("
    SELECT
        u.id,
        u.username,
        u.is_super_admin,
        u.created_at,
        b.name AS budget_name,
        (
            SELECT COUNT(*)
            FROM budget_members bm
            WHERE bm.budget_id = u.active_budget_id
        ) AS household_members_count
    FROM users u
    LEFT JOIN budgets b ON b.id = u.active_budget_id
    ORDER BY u.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

render_template('admin.twig', [
    'activePage' => 'admin',
    'pageTitle' => 'Platform Administration',
    'users' => $users,
    'flash' => $flash,
    'flashType' => $flashType,
]);
