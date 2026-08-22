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

$years = get_years($pdo, $userId);
if (empty($years)) {
    ensure_year($pdo, $userId, (int)date('Y'));
    $years = get_years($pdo, $userId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $year = (int)$_POST['year'];
    $month = $_POST['month'];
    foreach (($_POST['actual'] ?? []) as $catId => $val) {
        set_actual($pdo, $userId, (int)$catId, $year, $month, (float)$val);
    }
    header("Location: tracker.php?year=$year&month=" . urlencode($month) . "&saved=1");
    exit;
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

$data = tracker_month($pdo, $userId, $selectedYear, $selectedMonth);
$groupColors = BudgetService::getCategoryGroupColors();

render_template('tracker.twig', [
    'activePage' => 'tracker',
    'pageTitle' => 'Tracker',
    'flash' => $flash,
    'flashType' => $flashType,
    'selectedYear' => $selectedYear,
    'selectedMonth' => $selectedMonth,
    'data' => $data,
    'symbol' => $symbol,
    'groupColors' => $groupColors,
]);
