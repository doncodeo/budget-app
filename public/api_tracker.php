<?php
require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/helpers.php';

header('Content-Type: application/json');

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

check_csrf();

$pdo = get_db();
$userId = (int)$user['id'];
$symbol = $user['currency_symbol'];

$catId = (int)($_POST['category_id'] ?? 0);
$year = (int)($_POST['year'] ?? 0);
$month = $_POST['month'] ?? '';
$actual = (float)($_POST['actual'] ?? 0);

if ($catId <= 0 || $year < 2000 || !in_array($month, MONTHS, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

$budgetId = get_active_budget_id($pdo, $userId);
$catStmt = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND (budget_id = ? OR (budget_id IS NULL AND user_id = ?)) AND archived = 0');
$catStmt->execute([$catId, $budgetId, $userId]);
if (!$catStmt->fetch()) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid category']);
    exit;
}

set_actual($pdo, $userId, $catId, $year, $month, $actual);

// Recalculate month data to return updated row closing balance and total values
$data = tracker_month($pdo, $userId, $year, $month);

$updatedRow = null;
foreach ($data['groups'] as $groupName => $rows) {
    foreach ($rows as $r) {
        if ((int)$r['category']['id'] === $catId) {
            $updatedRow = $r;
            break 2;
        }
    }
}

echo json_encode([
    'success' => true,
    'row' => [
        'closing' => $updatedRow ? $updatedRow['closing'] : 0,
        'closing_formatted' => $updatedRow ? fmt_money($updatedRow['closing'], $symbol) : '',
        'is_positive' => $updatedRow ? ($updatedRow['closing'] >= 0) : true,
    ],
    'totals' => [
        'budget_formatted' => fmt_money($data['totals']['budget'], $symbol),
        'actual_formatted' => fmt_money($data['totals']['actual'], $symbol),
        'in_formatted' => fmt_money($data['totals']['in'], $symbol),
        'out_formatted' => fmt_money($data['totals']['out'], $symbol),
        'closing_formatted' => fmt_money($data['totals']['closing'], $symbol),
        'is_positive' => ($data['totals']['closing'] >= 0),
    ]
]);
