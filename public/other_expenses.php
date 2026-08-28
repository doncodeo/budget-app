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

    if ($action === 'create') {
        $validator = new Validator($_POST);
        $validator->required('description', 'Description');
        $validator->numeric('amount', 'Amount')->min('amount', 0.01, 'Amount');
        $validator->rangeInt('year', 2000, 2100, 'Year');

        if (!$validator->isValid()) {
            $flash = $validator->getFirstError();
            $flashType = 'danger';
        } else {
            $description = trim($_POST['description']);
            $amount = (float)$_POST['amount'];
            $notes = trim($_POST['notes'] ?? '');
            $date = $_POST['entry_date'] ?: date('Y-m-d');

            $ts = strtotime($date);
            if ($ts !== false) {
                $year = (int)date('Y', $ts);
                $month = MONTHS[(int)date('n', $ts) - 1];
            } else {
                $year = (int)$_POST['year'];
                $month = $_POST['month'];
            }

            ensure_year($pdo, $userId, $year, $budgetId);
            $stmt = $pdo->prepare(
                'INSERT INTO other_expenses (budget_id, user_id, entry_date, year, month, description, amount, notes) VALUES (?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([$budgetId, $userId, $date, $year, $month, $description, $amount, $notes]);
            $flash = 'Expense logged successfully.';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM other_expenses WHERE id=? AND budget_id=?')->execute([$id, $budgetId]);
        $flash = 'Entry removed.';
    }
}

$years = get_years($pdo, $userId, $budgetId);
if (empty($years)) {
    ensure_year($pdo, $userId, (int)date('Y'), $budgetId);
    $years = get_years($pdo, $userId, $budgetId);
}

// Search, filtering & pagination
$filterSearch = trim($_GET['search'] ?? '');
$filterYear = isset($_GET['filter_year']) && $_GET['filter_year'] !== '' ? (int)$_GET['filter_year'] : (isset($_GET['year']) ? (int)$_GET['year'] : null);
$filterMonth = trim($_GET['filter_month'] ?? $_GET['month'] ?? '');

$where = ['budget_id = ?'];
$params = [$budgetId];

if ($filterSearch !== '') {
    $where[] = '(description LIKE ? OR notes LIKE ?)';
    $params[] = "%$filterSearch%";
    $params[] = "%$filterSearch%";
}
if ($filterYear !== null) {
    $where[] = 'year = ?';
    $params[] = $filterYear;
}
if ($filterMonth !== '') {
    $where[] = 'month = ?';
    $params[] = $filterMonth;
}

$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM other_expenses WHERE $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();

$perPage = 15;
$totalPages = (int)ceil($totalRows / $perPage);
$currentPage = max(1, min($totalPages === 0 ? 1 : $totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($currentPage - 1) * $perPage;

$stmt = $pdo->prepare("SELECT * FROM other_expenses WHERE $whereSql ORDER BY year DESC, id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selectedYear = (int)($_GET['year'] ?? $years[0]);
$selectedMonth = $_GET['month'] ?? MONTHS[(int)date('n') - 1];

render_template('other_expenses.twig', [
    'activePage' => 'other_expenses',
    'pageTitle' => 'Other Expenses',
    'flash' => $flash,
    'flashType' => $flashType,
    'selectedYear' => $selectedYear,
    'selectedMonth' => $selectedMonth,
    'years' => $years,
    'entries' => $entries,
    'paginatedLogs' => $entries,
    'symbol' => $symbol,
    'filterSearch' => $filterSearch,
    'filterYear' => $filterYear,
    'filterMonth' => $filterMonth,
    'totalRows' => $totalRows,
    'totalPages' => $totalPages,
    'currentPage' => $currentPage,
]);
