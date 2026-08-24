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

    if ($action === 'create') {
        $validator = new Validator($_POST);
        $validator->numeric('amount', 'Amount')->min('amount', 0.01, 'Amount');
        $validator->rangeInt('year', 2000, 2100, 'Year');

        $from = (int)($_POST['from_category_id'] ?? 0);
        $to = (int)($_POST['to_category_id'] ?? 0);

        if ($from === $to) {
            $flash = 'From and To buckets must be different.';
            $flashType = 'danger';
        } elseif (!$validator->isValid()) {
            $flash = $validator->getFirstError();
            $flashType = 'danger';
        } else {
            $amount = (float)$_POST['amount'];
            $reason = trim($_POST['description'] ?? $_POST['reason'] ?? '');
            $approved = $_POST['approved'] ?? 'Pending';
            $date = $_POST['transfer_date'] ?? $_POST['entry_date'] ?: date('Y-m-d');

            $ts = strtotime($date);
            if ($ts !== false) {
                $year = (int)date('Y', $ts);
                $month = MONTHS[(int)date('n', $ts) - 1];
            } else {
                $year = (int)$_POST['year'];
                $month = $_POST['month'];
            }

            ensure_year($pdo, $userId, $year, $budgetId);

            // Verify source category balance
            $fromCatStmt = $pdo->prepare('SELECT * FROM categories WHERE id = ? AND budget_id = ?');
            $fromCatStmt->execute([$from, $budgetId]);
            $fromCat = $fromCatStmt->fetch(PDO::FETCH_ASSOC);

            $salary = get_salary($pdo, $userId, $year, $month, $budgetId);
            $totalIncome = $salary + get_other_income_total($pdo, $userId, $year, $month, $budgetId);

            if ($totalIncome <= 0) {
                $flash = "Cannot transfer funds: No income has been recorded for $month $year yet. Please log your salary or extra income on the Income page first.";
                $flashType = 'danger';
            } elseif (is_period_closed($pdo, $userId, $year, $month, $budgetId)) {
                $flash = "Period $month $year is closed and cannot be modified.";
                $flashType = 'danger';
            } elseif ($fromCat) {
                $sourceRow = tracker_row($pdo, $userId, $fromCat, $year, $month, $salary, $budgetId);
                if ($amount > $sourceRow['closing'] + 0.001) {
                    $flash = sprintf(
                        'This transfer exceeds the available balance in %s. Available: %s.',
                        $fromCat['name'],
                        fmt_money($sourceRow['closing'], $symbol)
                    );
                    $flashType = 'danger';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO transfers (budget_id, user_id, entry_date, year, month, from_category_id, to_category_id, amount, reason, approved)
                         VALUES (?,?,?,?,?,?,?,?,?,?)'
                    );
                    $stmt->execute([$budgetId, $userId, $date, $year, $month, $from, $to, $amount, $reason, $approved]);

                    if (!empty($_POST['save_as_template'])) {
                        $tmplName = "Sweep from Cat #$from to Cat #$to";
                        $tmplStmt = $pdo->prepare('INSERT INTO transfer_templates (budget_id, name, from_category_id, to_category_id, amount) VALUES (?, ?, ?, ?, ?)');
                        $tmplStmt->execute([$budgetId, $tmplName, $from, $to, $amount]);
                    }

                    $flash = 'Transfer logged. Both buckets have been updated.';
                }
            } else {
                $flash = 'Invalid source category for transfer.';
                $flashType = 'danger';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM transfers WHERE id=? AND budget_id=?')->execute([$id, $budgetId]);
        $flash = 'Transfer removed.';
    } elseif ($action === 'save_template') {
        $name = trim($_POST['template_name'] ?? '');
        $from = (int)($_POST['from_category_id'] ?? 0);
        $to = (int)($_POST['to_category_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);

        if ($name === '' || $amount <= 0 || $from === $to) {
            $flash = 'Invalid transfer template details.';
            $flashType = 'danger';
        } else {
            $stmt = $pdo->prepare('INSERT INTO transfer_templates (budget_id, name, from_category_id, to_category_id, amount) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$budgetId, $name, $from, $to, $amount]);
            $flash = "Recurring transfer template \"$name\" saved!";
        }
    } elseif ($action === 'apply_template') {
        $templateId = (int)($_POST['template_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM transfer_templates WHERE id = ? AND budget_id = ?');
        $stmt->execute([$templateId, $budgetId]);
        $tmpl = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($tmpl) {
            $year = (int)($_GET['year'] ?? date('Y'));
            $month = $_GET['month'] ?? MONTHS[(int)date('n') - 1];
            ensure_year($pdo, $userId, $year, $budgetId);

            $fromCatStmt = $pdo->prepare('SELECT * FROM categories WHERE id = ? AND budget_id = ?');
            $fromCatStmt->execute([$tmpl['from_category_id'], $budgetId]);
            $fromCat = $fromCatStmt->fetch(PDO::FETCH_ASSOC);

            $salary = get_salary($pdo, $userId, $year, $month, $budgetId);
            $sourceRow = $fromCat ? tracker_row($pdo, $userId, $fromCat, $year, $month, $salary, $budgetId) : null;

            if ($sourceRow && (float)$tmpl['amount'] > $sourceRow['closing'] + 0.001) {
                $flash = sprintf(
                    'Cannot apply template: transfer exceeds available balance in %s. Available: %s.',
                    $fromCat['name'],
                    fmt_money($sourceRow['closing'], $symbol)
                );
                $flashType = 'danger';
            } else {
                $ins = $pdo->prepare(
                    'INSERT INTO transfers (budget_id, user_id, entry_date, year, month, from_category_id, to_category_id, amount, reason, approved)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $ins->execute([$budgetId, $userId, date('Y-m-d'), $year, $month, $tmpl['from_category_id'], $tmpl['to_category_id'], $tmpl['amount'], 'Recurring sweep template: ' . $tmpl['name'], 'Yes']);
                $flash = "Applied template \"{$tmpl['name']}\" for $month $year!";
            }
        }
    } elseif ($action === 'delete_template') {
        $templateId = (int)($_POST['template_id'] ?? 0);
        $pdo->prepare('DELETE FROM transfer_templates WHERE id = ? AND budget_id = ?')->execute([$templateId, $budgetId]);
        $flash = 'Template deleted.';
    }
}

$years = get_years($pdo, $userId, $budgetId);
if (empty($years)) {
    ensure_year($pdo, $userId, (int)date('Y'), $budgetId);
    $years = get_years($pdo, $userId, $budgetId);
}
$categories = get_categories($pdo, $userId, $budgetId);

// Fetch saved transfer templates
$tmplStmt = $pdo->prepare('SELECT * FROM transfer_templates WHERE budget_id = ? ORDER BY id DESC');
$tmplStmt->execute([$budgetId]);
$templates = $tmplStmt->fetchAll(PDO::FETCH_ASSOC);

// Search, filtering & pagination for transfers log
$filterSearch = trim($_GET['search'] ?? '');
$filterYear = isset($_GET['filter_year']) && $_GET['filter_year'] !== '' ? (int)$_GET['filter_year'] : (isset($_GET['year']) ? (int)$_GET['year'] : null);
$filterMonth = trim($_GET['filter_month'] ?? $_GET['month'] ?? '');

$where = ['t.budget_id = ?'];
$params = [$budgetId];

if ($filterSearch !== '') {
    $where[] = '(t.reason LIKE ? OR fc.name LIKE ? OR tc.name LIKE ?)';
    $params[] = "%$filterSearch%";
    $params[] = "%$filterSearch%";
    $params[] = "%$filterSearch%";
}
if ($filterYear !== null) {
    $where[] = 't.year = ?';
    $params[] = $filterYear;
}
if ($filterMonth !== '') {
    $where[] = 't.month = ?';
    $params[] = $filterMonth;
}

$whereSql = implode(' AND ', $where);

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM transfers t JOIN categories fc ON fc.id = t.from_category_id JOIN categories tc ON tc.id = t.to_category_id WHERE $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();

$perPage = 15;
$totalPages = (int)ceil($totalRows / $perPage);
$currentPage = max(1, min($totalPages === 0 ? 1 : $totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($currentPage - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT t.*, fc.name AS from_name, tc.name AS to_name
     FROM transfers t
     JOIN categories fc ON fc.id = t.from_category_id
     JOIN categories tc ON tc.id = t.to_category_id
     WHERE $whereSql
     ORDER BY t.year DESC, t.id DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selectedYear = (int)($_GET['year'] ?? $years[0]);
$selectedMonth = $_GET['month'] ?? MONTHS[(int)date('n') - 1];

// Map transfer table fields for view rendering compatibility
$paginatedLogs = array_map(function($t) {
    $t['transfer_date'] = $t['entry_date'];
    $t['from_category_name'] = $t['from_name'];
    $t['to_category_name'] = $t['to_name'];
    $t['description'] = $t['reason'];
    return $t;
}, $transfers);

$categoryBalances = [];
$salary = get_salary($pdo, $userId, $selectedYear, $selectedMonth, $budgetId);
$totalIncome = $salary + get_other_income_total($pdo, $userId, $selectedYear, $selectedMonth, $budgetId);
$monthCategories = get_month_categories($pdo, $userId, $selectedYear, $selectedMonth, $budgetId);
$basePlannedNonBuffer = 0.0;
if ($totalIncome > 0) {
    foreach ($monthCategories as $cat) {
        if ($cat['name'] !== 'Monthly Buffer' && !$cat['is_other']) {
            $basePlannedNonBuffer += category_budget($cat, $salary);
        }
    }
}
$bufferBase = max(0.0, $salary - $basePlannedNonBuffer);

foreach ($monthCategories as $cat) {
    $row = tracker_row($pdo, $userId, $cat, $selectedYear, $selectedMonth, $salary, $budgetId, $bufferBase);
    $categoryBalances[$cat['id']] = [
        'name' => $cat['name'],
        'closing' => round(max(0.0, $row['closing']), 2),
        'closing_raw' => round($row['closing'], 2),
        'formatted' => fmt_money($row['closing'], $symbol),
        'has_income' => ($totalIncome > 0),
    ];
}

render_template('transfers.twig', [
    'activePage' => 'transfers',
    'pageTitle' => 'Transfers',
    'flash' => $flash,
    'flashType' => $flashType,
    'selectedYear' => $selectedYear,
    'selectedMonth' => $selectedMonth,
    'years' => $years,
    'categories' => $categories,
    'categoryBalances' => $categoryBalances,
    'templates' => $templates,
    'transfers' => $transfers,
    'paginatedLogs' => $paginatedLogs,
    'symbol' => $symbol,
    'filterSearch' => $filterSearch,
    'filterYear' => $filterYear,
    'filterMonth' => $filterMonth,
    'totalRows' => $totalRows,
    'totalPages' => $totalPages,
    'currentPage' => $currentPage,
]);
