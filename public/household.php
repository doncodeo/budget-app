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
$budgetId = get_active_budget_id($pdo, $userId);

$flash = null;
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_budget') {
        $validator = new Validator($_POST);
        $validator->required('budget_name', 'Budget Name');
        $validator->required('currency_code', 'Currency Code');
        $validator->required('currency_symbol', 'Currency Symbol');

        if (!$validator->isValid()) {
            $flash = $validator->getFirstError();
            $flashType = 'danger';
        } else {
            $name = trim($_POST['budget_name']);
            $code = strtoupper(trim($_POST['currency_code']));
            $symbol = trim($_POST['currency_symbol']);

            $stmt = $pdo->prepare("INSERT INTO budgets (name, owner_id, currency_code, currency_symbol) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $userId, $code, $symbol]);
            $newBudgetId = (int)$pdo->lastInsertId();

            $pdo->prepare("INSERT INTO budget_members (budget_id, user_id, role) VALUES (?, ?, 'owner')")->execute([$newBudgetId, $userId]);
            $pdo->prepare("UPDATE users SET active_budget_id = ? WHERE id = ?")->execute([$newBudgetId, $userId]);

            // Seed categories for new budget
            $cStmt = $pdo->prepare(
                'INSERT INTO categories (budget_id, user_id, name, group_name, basis, fixed_amount, percent, notes, is_other, sort_order)
                 VALUES (:budget_id, :user_id, :name, :group_name, :basis, :fixed_amount, :percent, :notes, :is_other, :sort_order)'
            );
            foreach (DEFAULT_CATEGORIES as $i => $cat) {
                [$cName, $cGroup, $cBasis, $cFixed, $cPercent, $cNotes, $cIsOther] = $cat;
                $cStmt->execute([
                    ':budget_id' => $newBudgetId,
                    ':user_id' => $userId,
                    ':name' => $cName,
                    ':group_name' => $cGroup,
                    ':basis' => $cBasis,
                    ':fixed_amount' => $cFixed,
                    ':percent' => $cPercent,
                    ':notes' => $cNotes,
                    ':is_other' => $cIsOther,
                    ':sort_order' => $i,
                ]);
            }
            ensure_year($pdo, $userId, (int)date('Y'), $newBudgetId);

            header('Location: household.php?created=1');
            exit;
        }
    } elseif ($action === 'switch_budget') {
        $targetBudgetId = (int)($_POST['budget_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT budget_id FROM budget_members WHERE budget_id = ? AND user_id = ?");
        $stmt->execute([$targetBudgetId, $userId]);
        if ($stmt->fetch()) {
            $pdo->prepare("UPDATE users SET active_budget_id = ? WHERE id = ?")->execute([$targetBudgetId, $userId]);
            header('Location: household.php?switched=1');
            exit;
        }
    } elseif ($action === 'invite_member') {
        $inviteUsername = trim($_POST['invite_username'] ?? '');
        $uStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $uStmt->execute([$inviteUsername]);
        $targetUserId = $uStmt->fetchColumn();

        if (!$targetUserId) {
            $flash = "User \"$inviteUsername\" does not exist.";
            $flashType = 'danger';
        } else {
            $mStmt = $pdo->prepare("SELECT 1 FROM budget_members WHERE budget_id = ? AND user_id = ?");
            $mStmt->execute([$budgetId, $targetUserId]);
            if ($mStmt->fetch()) {
                $flash = "User \"$inviteUsername\" is already a member of this budget.";
                $flashType = 'danger';
            } else {
                $pdo->prepare("INSERT INTO budget_members (budget_id, user_id, role) VALUES (?, ?, 'member')")->execute([$budgetId, $targetUserId]);
                $flash = "User \"$inviteUsername\" invited successfully!";
            }
        }
    } elseif ($action === 'remove_member') {
        $memberUserId = (int)($_POST['member_user_id'] ?? 0);
        if ($memberUserId !== $userId) {
            $pdo->prepare("DELETE FROM budget_members WHERE budget_id = ? AND user_id = ?")->execute([$budgetId, $memberUserId]);
            $flash = "Member removed.";
        }
    } elseif ($action === 'update_currency') {
        $code = strtoupper(trim($_POST['currency_code'] ?? 'NGN'));
        $symbol = trim($_POST['currency_symbol'] ?? '₦');
        $pdo->prepare("UPDATE budgets SET currency_code = ?, currency_symbol = ? WHERE id = ?")->execute([$code, $symbol, $budgetId]);
        $flash = "Currency settings updated.";
    }
}

if (isset($_GET['created'])) $flash = "New shared budget created!";
if (isset($_GET['switched'])) $flash = "Active budget switched.";

// Get active budget details
$bStmt = $pdo->prepare("SELECT b.*, u.username AS owner_name FROM budgets b JOIN users u ON u.id = b.owner_id WHERE b.id = ?");
$bStmt->execute([$budgetId]);
$activeBudget = $bStmt->fetch(PDO::FETCH_ASSOC);

// Get budget members
$mStmt = $pdo->prepare("SELECT u.id, u.username, bm.role, u.created_at FROM budget_members bm JOIN users u ON u.id = bm.user_id WHERE bm.budget_id = ?");
$mStmt->execute([$budgetId]);
$members = $mStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all budgets current user belongs to
$ubStmt = $pdo->prepare("SELECT b.*, bm.role FROM budgets b JOIN budget_members bm ON bm.budget_id = b.id WHERE bm.user_id = ? ORDER BY b.id DESC");
$ubStmt->execute([$userId]);
$userBudgets = $ubStmt->fetchAll(PDO::FETCH_ASSOC);

// Find current user's role in active budget
$currentUserRole = 'member';
foreach ($members as $m) {
    if ((int)$m['id'] === $userId) {
        $currentUserRole = $m['role'];
        break;
    }
}

render_template('household.twig', [
    'activePage' => 'household',
    'pageTitle' => 'Household & Shared Budgets',
    'flash' => $flash,
    'flashType' => $flashType,
    'activeBudget' => $activeBudget,
    'members' => $members,
    'userBudgets' => $userBudgets,
    'currentUserRole' => $currentUserRole,
]);
