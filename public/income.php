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

$years = get_years($pdo, $userId, $budgetId);
if (empty($years)) {
    ensure_year($pdo, $userId, (int)date('Y'), $budgetId);
    $years = get_years($pdo, $userId, $budgetId);
}

$selectedYear = (int)($_GET['year'] ?? $years[0]);
if (!in_array($selectedYear, $years, true)) {
    $selectedYear = $years[0];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $year = (int)($_POST['year'] ?? 0);
    ensure_year($pdo, $userId, $year, $budgetId);

    $stmt = $pdo->prepare('SELECT id FROM income WHERE budget_id = ? AND year = ? AND month = ?');
    $upd = $pdo->prepare('UPDATE income SET salary = ? WHERE id = ?');
    $ins = $pdo->prepare('INSERT INTO income (budget_id, user_id, year, month, salary) VALUES (?, ?, ?, ?, ?)');

    foreach (MONTHS as $m) {
        $val = (float)($_POST['salary'][$m] ?? 0);
        $stmt->execute([$budgetId, $year, $m]);
        $incId = $stmt->fetchColumn();
        if ($incId) {
            $upd->execute([$val, $incId]);
        } else {
            $ins->execute([$budgetId, $userId, $year, $m, $val]);
        }
    }
    header("Location: income.php?year=$year&saved=1");
    exit;
}

if (isset($_GET['saved'])) {
    $flash = "Salary for $selectedYear saved.";
}

$stmt = $pdo->prepare('SELECT month, salary FROM income WHERE budget_id=? AND year=?');
$stmt->execute([$budgetId, $selectedYear]);
$salaries = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'salary', 'month');

$otherByMonth = [];
foreach (MONTHS as $m) {
    $otherByMonth[$m] = get_other_income_total($pdo, $userId, $selectedYear, $m, $budgetId);
}

render_template('income.twig', [
    'activePage' => 'income',
    'pageTitle' => 'Income',
    'flash' => $flash,
    'flashType' => $flashType,
    'selectedYear' => $selectedYear,
    'years' => $years,
    'salaries' => $salaries,
    'otherByMonth' => $otherByMonth,
    'symbol' => $symbol,
]);
