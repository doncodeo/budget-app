<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/render.php';

use App\Validator;

$user = require_login();
$pdo = get_db();
$userId = (int)$user['id'];
$symbol = $user['currency_symbol'];
$budgetId = get_active_budget_id($pdo, $userId);

$flash = null;
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $validator = new Validator($_POST);
        $validator->required('name', 'Category Name');

        $catId = (int)($_POST['id'] ?? $_POST['category_id'] ?? 0);
        if ($catId > 0) {
            $checkCat = $pdo->prepare('SELECT name FROM categories WHERE id = ? AND budget_id = ?');
            $checkCat->execute([$catId, $budgetId]);
            $existingName = $checkCat->fetchColumn();
            if ($existingName === 'Monthly Buffer') {
                $flash = 'Monthly Buffer is dynamically calculated as the residual of your base monthly plan and cannot be manually modified.';
                $flashType = 'danger';
            }
        }

        if ($flashType !== 'danger' && !$validator->isValid()) {
            $flash = $validator->getFirstError();
            $flashType = 'danger';
        } elseif ($flashType !== 'danger') {
            $name = trim($_POST['name']);
            $group = trim($_POST['group_name'] ?? '') ?: 'General';
            $basis = $_POST['basis'] === 'percent' ? 'percent' : 'fixed';
            $fixed = $basis === 'fixed' ? (float)($_POST['fixed_amount'] ?? 0) : null;
            $percent = $basis === 'percent' ? ((float)($_POST['percent'] ?? 0) / 100) : null;
            $notes = trim($_POST['notes'] ?? '');

            $pdo->beginTransaction();
            try {
                if ($action === 'create') {
                    $maxOrderStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM categories WHERE budget_id = ?');
                    $maxOrderStmt->execute([$budgetId]);
                    $maxOrder = (int)$maxOrderStmt->fetchColumn();

                    $stmt = $pdo->prepare(
                        'INSERT INTO categories (budget_id, user_id, name, group_name, basis, fixed_amount, percent, notes, sort_order)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$budgetId, $userId, $name, $group, $basis, $fixed, $percent, $notes, $maxOrder + 1]);
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE categories SET name=?, group_name=?, basis=?, fixed_amount=?, percent=?, notes=?
                         WHERE id=? AND budget_id=?'
                    );
                    $stmt->execute([$name, $group, $basis, $fixed, $percent, $notes, $catId, $budgetId]);
                }

                // Sync category snapshots to open months first so validation evaluates the new rule state
                sync_open_month_category_snapshots($pdo, $userId, $budgetId);

                // Check over-allocation across all configured years and months
                $checkYears = get_years($pdo, $userId, $budgetId);
                foreach ($checkYears as $y) {
                    foreach (MONTHS as $m) {
                        $sum = calculate_budget_summary($pdo, $userId, $y, $m, $budgetId);
                        if ($sum['budget_status'] === 'Over-allocated') {
                            throw new \Exception(sprintf(
                                'This change would exceed available income by %s in %s %d.',
                                fmt_money($sum['over_allocated_amount'], $symbol),
                                $m,
                                $y
                            ));
                        }
                    }
                }

                $pdo->commit();
                $flash = $action === 'create' ? "\"$name\" added." : "\"$name\" updated.";
            } catch (\Throwable $e) {
                $pdo->rollBack();
                $flash = $e->getMessage();
                $flashType = 'danger';
            }
        }
    } elseif ($action === 'delete' || $action === 'archive') {
        $catId = (int)($_POST['id'] ?? $_POST['category_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT is_other, name FROM categories WHERE id=? AND budget_id=?');
        $stmt->execute([$catId, $budgetId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !$row['is_other'] && $row['name'] !== 'Monthly Buffer') {
            // Soft delete by setting archived = 1 to preserve historical financial records
            $pdo->prepare('UPDATE categories SET archived = 1 WHERE id=? AND budget_id=?')->execute([$catId, $budgetId]);
            sync_open_month_category_snapshots($pdo, $userId, $budgetId);
            $flash = 'Category archived to preserve historical financial records.';
        }
    } elseif ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPass, $user['password_hash'])) {
            $flash = 'Current password is incorrect.';
            $flashType = 'danger';
        } elseif (strlen($newPass) < 8) {
            $flash = 'New password must be at least 8 characters long.';
            $flashType = 'danger';
        } elseif ($newPass !== $confirmPass) {
            $flash = 'New passwords do not match.';
            $flashType = 'danger';
        } else {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$newHash, $userId]);
            $flash = 'Your password has been changed successfully.';
        }
    }
}

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$editCat = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id=? AND budget_id=?');
    $stmt->execute([$editId, $budgetId]);
    $cat = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($cat && $cat['name'] === 'Monthly Buffer') {
        $flash = 'Monthly Buffer is dynamically calculated as the residual of your base monthly plan and cannot be manually modified.';
        $flashType = 'info';
    } else {
        $editCat = $cat;
    }
}

$categories = get_categories($pdo, $userId, $budgetId);

$selectedYear = (int)($_SESSION['selected_year'] ?? date('Y'));
$selectedMonth = $_SESSION['selected_month'] ?? date('M');
$activeSalary = get_salary($pdo, $userId, $selectedYear, $selectedMonth, $budgetId);

render_template('settings.twig', [
    'activePage' => 'settings',
    'pageTitle' => 'Settings',
    'flash' => $flash,
    'flashType' => $flashType,
    'categories' => $categories,
    'editCat' => $editCat,
    'editingCat' => $editCat,
    'symbol' => $symbol,
    'activeSalary' => $activeSalary,
]);
