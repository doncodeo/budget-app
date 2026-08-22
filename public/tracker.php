<?php
require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/helpers.php';

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

$activePage = 'tracker';
$pageTitle = 'Tracker';
require __DIR__ . '/../src/layout_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h2 class="mb-0">Monthly Tracker</h2>
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

<p class="text-muted">Enter Actual (blue). Budget comes from Settings automatically. Transfer In/Out come from the
<a href="transfers.php">Transfers</a> log automatically — no double entry needed.</p>

<div class="card p-3">
<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="year" value="<?= $selectedYear ?>">
  <input type="hidden" name="month" value="<?= h($selectedMonth) ?>">
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>Category</th><th class="text-end">Budget</th><th class="text-end">Actual</th><th class="text-end">In</th><th class="text-end">Out</th><th class="text-end">Closing</th></tr></thead>
      <tbody>
      <?php foreach ($data['groups'] as $groupName => $rows): ?>
        <tr class="group-row"><td colspan="6"><?= h($groupName) ?></td></tr>
        <?php foreach ($rows as $row): $cat = $row['category']; ?>
          <tr>
            <td><?= h($cat['name']) ?></td>
            <td class="text-end"><?= fmt_money($row['budget'], $symbol) ?></td>
            <td class="text-end" style="max-width:130px">
              <?php if ($cat['is_other']): ?>
                <?= fmt_money($row['actual'], $symbol) ?>
                <div class="form-text">from <a href="other_expenses.php">log</a></div>
              <?php else: ?>
                <input type="number" step="0.01" min="0" name="actual[<?= $cat['id'] ?>]"
                       class="form-control form-control-sm input-cell text-end"
                       value="<?= h((string)$row['actual']) ?>">
              <?php endif; ?>
            </td>
            <td class="text-end"><?= fmt_money($row['in'], $symbol) ?></td>
            <td class="text-end"><?= fmt_money($row['out'], $symbol) ?></td>
            <td class="text-end <?= $row['closing'] >= 0 ? 'positive' : 'negative' ?>"><?= fmt_money($row['closing'], $symbol) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr class="total-row">
          <td>TOTAL / NET</td>
          <td class="text-end"><?= fmt_money($data['totals']['budget'], $symbol) ?></td>
          <td class="text-end"><?= fmt_money($data['totals']['actual'], $symbol) ?></td>
          <td class="text-end"><?= fmt_money($data['totals']['in'], $symbol) ?></td>
          <td class="text-end"><?= fmt_money($data['totals']['out'], $symbol) ?></td>
          <td class="text-end"><?= fmt_money($data['totals']['closing'], $symbol) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <button class="btn btn-primary" type="submit">Save actuals</button>
</form>
</div>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
