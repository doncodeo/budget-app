<?php
require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/helpers.php';

$user = require_login();
$pdo = get_db();
$userId = (int)$user['id'];
$symbol = $user['currency_symbol'];

// Handle "add a new year" quick action
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

// Key targets, computed from category settings for the selected month
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

$activePage = 'dashboard';
$pageTitle = 'Dashboard';
require __DIR__ . '/../src/layout_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h2 class="mb-0">Dashboard</h2>
  <form method="get" class="d-flex gap-2">
    <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php foreach ($years as $y): ?>
        <option value="<?= $y ?>" <?= $y === $selectedYear ? 'selected' : '' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
    <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php foreach (MONTHS as $m): ?>
        <option value="<?= $m ?>" <?= $m === $selectedMonth ? 'selected' : '' ?>><?= $m ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4 col-lg-2">
    <div class="card p-3"><div class="text-muted small">Salary</div><div class="fs-5 fw-bold"><?= fmt_money($salary, $symbol) ?></div></div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="card p-3"><div class="text-muted small">Other Income</div><div class="fs-5 fw-bold"><?= fmt_money($otherIncome, $symbol) ?></div></div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="card p-3"><div class="text-muted small">Total Income</div><div class="fs-5 fw-bold"><?= fmt_money($totalIncome, $symbol) ?></div></div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="card p-3"><div class="text-muted small">Planned Budget</div><div class="fs-5 fw-bold"><?= fmt_money($totals['budget'], $symbol) ?></div></div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="card p-3"><div class="text-muted small">Actual Spent</div><div class="fs-5 fw-bold"><?= fmt_money($totals['actual'], $symbol) ?></div></div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="card p-3"><div class="text-muted small">Net Position</div>
      <div class="fs-5 fw-bold <?= $totals['closing'] >= 0 ? 'positive' : 'negative' ?>"><?= fmt_money($totals['closing'], $symbol) ?></div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card p-3">
      <h5>Key targets — <?= h($selectedMonth) ?> <?= $selectedYear ?></h5>
      <table class="table table-sm mb-0">
        <tbody>
          <tr><td>Rent Savings / month</td><td class="text-end"><?= fmt_money($rentSavings['budget'] ?? 0, $symbol) ?></td></tr>
          <tr><td>Emergency Fund / month</td><td class="text-end"><?= fmt_money($emergency['budget'] ?? 0, $symbol) ?></td></tr>
          <tr><td>Investment / month</td><td class="text-end"><?= fmt_money($investment['budget'] ?? 0, $symbol) ?></td></tr>
          <tr><td>Monthly Buffer</td><td class="text-end"><?= fmt_money($buffer['budget'] ?? 0, $symbol) ?></td></tr>
        </tbody>
      </table>
      <a href="settings.php" class="small">Edit these rules on the Settings page &rarr;</a>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card p-3">
      <h5>How to use</h5>
      <ol class="small mb-3">
        <li>Each pay day, enter that month's salary on the <a href="income.php">Income</a> page.</li>
        <li>Extra income goes in the <a href="other_income.php">Other Income</a> log.</li>
        <li>Enter what you actually spent on the <a href="tracker.php">Tracker</a> page.</li>
        <li>Unplanned spending goes in the <a href="other_expenses.php">Other Expenses</a> log.</li>
        <li>To move unused money between buckets, add one row on <a href="transfers.php">Transfers</a> — both buckets update automatically.</li>
        <li>Positive Net Position means money remains for the month.</li>
      </ol>
      <hr>
      <h6>Add a new year</h6>
      <form method="post" class="d-flex gap-2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_year">
        <input type="number" name="new_year" class="form-control form-control-sm" style="max-width:120px" placeholder="e.g. 2028" min="2000" max="2100">
        <button class="btn btn-sm btn-primary" type="submit">Add year</button>
      </form>
      <div class="form-text">Earlier years are never overwritten.</div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
