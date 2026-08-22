<?php
require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/render.php';

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

        if (!$validator->isValid()) {
            $flash = $validator->getFirstError();
            $flashType = 'danger';
        } else {
            $name = trim($_POST['name']);
            $group = trim($_POST['group_name'] ?? '') ?: 'General';
            $basis = $_POST['basis'] === 'percent' ? 'percent' : 'fixed';
            $fixed = $basis === 'fixed' ? (float)($_POST['fixed_amount'] ?? 0) : null;
            $percent = $basis === 'percent' ? ((float)($_POST['percent'] ?? 0) / 100) : null;
            $notes = trim($_POST['notes'] ?? '');

            if ($action === 'create') {
                $maxOrderStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM categories WHERE budget_id = ?');
                $maxOrderStmt->execute([$budgetId]);
                $maxOrder = (int)$maxOrderStmt->fetchColumn();

                $stmt = $pdo->prepare(
                    'INSERT INTO categories (budget_id, user_id, name, group_name, basis, fixed_amount, percent, notes, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$budgetId, $userId, $name, $group, $basis, $fixed, $percent, $notes, $maxOrder + 1]);
                $flash = "\"$name\" added.";
            } else {
                $catId = (int)($_POST['id'] ?? $_POST['category_id'] ?? 0);
                $stmt = $pdo->prepare(
                    'UPDATE categories SET name=?, group_name=?, basis=?, fixed_amount=?, percent=?, notes=?
                     WHERE id=? AND budget_id=?'
                );
                $stmt->execute([$name, $group, $basis, $fixed, $percent, $notes, $catId, $budgetId]);
                $flash = "\"$name\" updated.";
            }
        }
    } elseif ($action === 'delete' || $action === 'archive') {
        $catId = (int)($_POST['id'] ?? $_POST['category_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT is_other FROM categories WHERE id=? AND budget_id=?');
        $stmt->execute([$catId, $budgetId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !$row['is_other']) {
            $pdo->prepare('DELETE FROM categories WHERE id=? AND budget_id=?')->execute([$catId, $budgetId]);
            $flash = 'Category deleted.';
        }
    }
}

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$editCat = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id=? AND budget_id=?');
    $stmt->execute([$editId, $budgetId]);
    $editCat = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$categories = get_categories($pdo, $userId, $budgetId);

render_template('settings.twig', [
    'activePage' => 'settings',
    'pageTitle' => 'Settings',
    'flash' => $flash,
    'flashType' => $flashType,
    'categories' => $categories,
    'editCat' => $editCat,
    'editingCat' => $editCat,
    'symbol' => $symbol,
]);
