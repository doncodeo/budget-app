<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/render.php';

use App\BudgetService;

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

    if ($action === 'add_year') {
        $newYear = (int)($_POST['new_year'] ?? 0);
        if ($newYear >= 2000 && $newYear <= 2100) {
            ensure_year($pdo, $userId, $newYear, $budgetId);
            $flash = "Year $newYear is ready. Enter its monthly salary on the Income page.";
        } else {
            $flash = 'Please enter a valid year.';
            $flashType = 'danger';
        }
    } elseif ($action === 'toggle_period_lock') {
        $year = (int)($_POST['year'] ?? date('Y'));
        $month = $_POST['month'] ?? MONTHS[(int)date('n') - 1];
        $newStatus = is_period_closed($pdo, $userId, $year, $month, $budgetId) ? 'open' : 'closed';
        set_period_status($pdo, $userId, $year, $month, $newStatus, $budgetId);
        header("Location: dashboard.php?year=$year&month=" . urlencode($month) . "&status_toggled=1");
        exit;
    } elseif ($action === 'allocate_income') {
        $year = (int)($_POST['year'] ?? date('Y'));
        $month = $_POST['month'] ?? MONTHS[(int)date('n') - 1];
        if (is_period_closed($pdo, $userId, $year, $month, $budgetId)) {
            $flash = "Period $month $year is closed and cannot be modified.";
            $flashType = 'danger';
        } else {
            $allocationsMap = $_POST['allocations'] ?? [];
            $err = save_income_allocations($pdo, $userId, $year, $month, $allocationsMap, 'Other Income', $budgetId);
            if ($err !== null) {
                $flash = $err;
                $flashType = 'danger';
            } else {
                header("Location: dashboard.php?year=$year&month=" . urlencode($month) . "&allocated=1");
                exit;
            }
        }
    } elseif ($action === 'delete_allocation') {
        $id = (int)($_POST['allocation_id'] ?? 0);
        $year = (int)($_POST['year'] ?? date('Y'));
        $month = $_POST['month'] ?? MONTHS[(int)date('n') - 1];
        if (is_period_closed($pdo, $userId, $year, $month, $budgetId)) {
            $flash = "Period $month $year is closed and cannot be modified.";
            $flashType = 'danger';
        } else {
            $pdo->prepare('DELETE FROM income_allocations WHERE id = ? AND budget_id = ?')->execute([$id, $budgetId]);
            header("Location: dashboard.php?year=$year&month=" . urlencode($month) . "&deleted_alloc=1");
            exit;
        }
    }
}

if (isset($_GET['allocated'])) {
    $flash = 'Income allocated successfully and reflected on your Tracker.';
} elseif (isset($_GET['deleted_alloc'])) {
    $flash = 'Allocation record removed.';
}

$years = get_years($pdo, $userId);
if (empty($years)) {
    ensure_year($pdo, $userId, (int)date('Y'));
    $years = get_years($pdo, $userId);
}

$selectedYear = (int)($_GET['year'] ?? $years[0]);
if (!in_array($selectedYear, $years, true)) {
    $selectedYear = $years[0];
}
$selectedMonth = $_GET['month'] ?? MONTHS[(int)date('n') - 1];
if (!in_array($selectedMonth, MONTHS, true)) {
    $selectedMonth = MONTHS[0];
}

$data = tracker_month($pdo, $userId, $selectedYear, $selectedMonth);
$otherIncome = get_other_income_total($pdo, $userId, $selectedYear, $selectedMonth);
$salary = $data['salary'];
$totalIncome = $salary + $otherIncome;
$totals = $data['totals'];

function find_cat_row(array $groups, string $name): ?array
{
    foreach ($groups as $rows) {
        foreach ($rows as $row) {
            if ($row['category']['name'] === $name) {
                return $row;
            }
        }
    }
    return null;
}

$rentSavings = find_cat_row($data['groups'], 'Rent Savings');
$emergency = find_cat_row($data['groups'], 'Emergency Fund');
$investment = find_cat_row($data['groups'], 'Investment');
$buffer = find_cat_row($data['groups'], 'Monthly Buffer');

$summary = calculate_budget_summary($pdo, $userId, $selectedYear, $selectedMonth, $budgetId);
$categories = get_categories($pdo, $userId, $budgetId);
$allocationsHistory = get_allocations_for_month($pdo, $userId, $selectedYear, $selectedMonth, $budgetId);

$netHistory = BudgetService::getNetPositionHistory($pdo, $userId, $selectedYear, $selectedMonth, 12);
$groupBreakdown = BudgetService::getGroupSpendingBreakdown($data);
$progressList = BudgetService::getCategoryProgressList($data);

// First-run checklist condition: totalIncome == 0
$isFirstRun = ($totalIncome === 0.0 && $totals['actual'] === 0.0);

render_template('dashboard.twig', [
    'activePage' => 'dashboard',
    'pageTitle' => 'Dashboard',
    'flash' => $flash,
    'flashType' => $flashType,
    'selectedYear' => $selectedYear,
    'selectedMonth' => $selectedMonth,
    'salary' => $salary,
    'otherIncome' => $otherIncome,
    'totalIncome' => $totalIncome,
    'totals' => $totals,
    'summary' => $summary,
    'categories' => $categories,
    'allocationsHistory' => $allocationsHistory,
    'rentSavings' => $rentSavings,
    'emergency' => $emergency,
    'investment' => $investment,
    'buffer' => $buffer,
    'symbol' => $symbol,
    'netHistory' => $netHistory,
    'groupBreakdown' => $groupBreakdown,
    'progressList' => $progressList,
    'isFirstRun' => $isFirstRun,
]);
