<?php
require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/helpers.php';

$user = require_login();
$pdo = get_db();
$userId = (int)$user['id'];
$symbol = $user['currency_symbol'];

$type = $_GET['type'] ?? 'tracker';
$year = (int)($_GET['year'] ?? date('Y'));
$month = $_GET['month'] ?? MONTHS[(int)date('n') - 1];

$filename = "export_{$type}_{$year}_{$month}.csv";

header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"$filename\"");

$output = fopen('php://output', 'w');

if ($type === 'tracker') {
    fputcsv($output, ['Group', 'Category', 'Basis', 'Budget', 'Actual', 'In', 'Out', 'Closing']);
    $data = tracker_month($pdo, $userId, $year, $month);
    foreach ($data['groups'] as $groupName => $rows) {
        foreach ($rows as $r) {
            $cat = $r['category'];
            fputcsv($output, [
                $groupName,
                $cat['name'],
                $cat['basis'],
                $r['budget'],
                $r['actual'],
                $r['in'],
                $r['out'],
                $r['closing']
            ]);
        }
    }
    fputcsv($output, ['TOTAL', '', '', $data['totals']['budget'], $data['totals']['actual'], $data['totals']['in'], $data['totals']['out'], $data['totals']['closing']]);
} elseif ($type === 'transfers') {
    fputcsv($output, ['ID', 'Date', 'Year', 'Month', 'From Category', 'To Category', 'Amount', 'Reason', 'Approved']);
    $budgetId = get_active_budget_id($pdo, $userId);
    $stmt = $pdo->prepare('SELECT t.*, fc.name AS from_name, tc.name AS to_name
                           FROM transfers t
                           JOIN categories fc ON fc.id = t.from_category_id
                           JOIN categories tc ON tc.id = t.to_category_id
                           WHERE t.budget_id = ? ORDER BY t.id DESC');
    $stmt->execute([$budgetId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'], $row['entry_date'], $row['year'], $row['month'],
            $row['from_name'], $row['to_name'], $row['amount'], $row['reason'], $row['approved']
        ]);
    }
} elseif ($type === 'other_income') {
    fputcsv($output, ['ID', 'Date', 'Year', 'Month', 'Source', 'Amount', 'Notes']);
    $budgetId = get_active_budget_id($pdo, $userId);
    $stmt = $pdo->prepare('SELECT * FROM other_income WHERE budget_id = ? ORDER BY id DESC');
    $stmt->execute([$budgetId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'], $row['entry_date'], $row['year'], $row['month'],
            $row['source'], $row['amount'], $row['notes']
        ]);
    }
} elseif ($type === 'other_expenses') {
    fputcsv($output, ['ID', 'Date', 'Year', 'Month', 'Description', 'Amount', 'Notes']);
    $budgetId = get_active_budget_id($pdo, $userId);
    $stmt = $pdo->prepare('SELECT * FROM other_expenses WHERE budget_id = ? ORDER BY id DESC');
    $stmt->execute([$budgetId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'], $row['entry_date'], $row['year'], $row['month'],
            $row['description'], $row['amount'], $row['notes']
        ]);
    }
}

fclose($output);
exit;
