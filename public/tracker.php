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

$flash = null;
$flashType = 'success';

$years = get_years($pdo, $userId);
if (empty($years)) {
    ensure_year($pdo, $userId, (int)date('Y'));
    $years = get_years($pdo, $userId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $year = (int)$_POST['year'];
    $month = $_POST['month'];
    $budgetId = get_active_budget_id($pdo, $userId);

    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_period') {
        $targetStatus = $_POST['target_status'] === 'closed' ? 'closed' : 'open';
        set_period_status($pdo, $userId, $year, $month, $targetStatus, $budgetId);
        $statusLabel = $targetStatus === 'closed' ? 'closed and locked' : 're-opened for edits';
        header("Location: tracker.php?year=$year&month=" . urlencode($month) . "&flash=" . urlencode("Period $month $year is now $statusLabel."));
        exit;
    }

    if (is_period_closed($pdo, $userId, $year, $month, $budgetId)) {
        $flash = "Period $month $year is closed and cannot be modified.";
        $flashType = 'danger';
    } else {
        foreach (($_POST['actual'] ?? []) as $catId => $val) {
            set_actual($pdo, $userId, (int)$catId, $year, $month, (float)$val);
        }
        header("Location: tracker.php?year=$year&month=" . urlencode($month) . "&saved=1");
        exit;
    }
}

$selectedYear = (int)($_GET['year'] ?? $years[0]);
if (!in_array($selectedYear, $years, true)) {
    $selectedYear = $years[0];
}
$selectedMonth = $_GET['month'] ?? MONTHS[(int)date('n') - 1];
if (!in_array($selectedMonth, MONTHS, true)) {
    $selectedMonth = MONTHS[0];
}
if (isset($_GET['saved'])) {
    $flash = 'Actual amounts saved.';
}
if (isset($_GET['flash'])) {
    $flash = urldecode($_GET['flash']);
}

$budgetId = get_active_budget_id($pdo, $userId);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'allocate_income') {
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
            header("Location: tracker.php?year=$year&month=" . urlencode($month) . "&allocated=1");
            exit;
        }
    }
}

if (isset($_GET['allocated'])) {
    $flash = 'Income allocated successfully and reflected on your Tracker.';
}

$data = tracker_month($pdo, $userId, $selectedYear, $selectedMonth);
$summary = calculate_budget_summary($pdo, $userId, $selectedYear, $selectedMonth, $budgetId);
$categories = get_categories($pdo, $userId, $budgetId);
$groupColors = BudgetService::getCategoryGroupColors();
$isClosed = is_period_closed($pdo, $userId, $selectedYear, $selectedMonth, $budgetId);

render_template('tracker.twig', [
    'activePage' => 'tracker',
    'pageTitle' => 'Tracker',
    'flash' => $flash,
    'flashType' => $flashType,
    'selectedYear' => $selectedYear,
    'selectedMonth' => $selectedMonth,
    'isClosed' => $isClosed,
    'data' => $data,
    'groups' => $data['groups'] ?? [],
    'totals' => $data['totals'] ?? [],
    'salary' => $data['salary'] ?? 0.0,
    'summary' => $summary,
    'categories' => $categories,
    'symbol' => $symbol,
    'groupColors' => $groupColors,
]);
