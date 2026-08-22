<?php
require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/render.php';

use App\BudgetService;

$user = require_login();
$pdo = get_db();
$userId = (int)$user['id'];
$symbol = $user['currency_symbol'];

$flash = null;
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_year') {
    check_csrf();
    $newYear = (int)($_POST['new_year'] ?? 0);
    if ($newYear >= 2000 && $newYear <= 2100) {
        ensure_year($pdo, $userId, $newYear);
        $flash = "Year $newYear is ready. Enter its monthly salary on the Income page.";
    } else {
        $flash = 'Please enter a valid year.';
        $flashType = 'danger';
    }
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
