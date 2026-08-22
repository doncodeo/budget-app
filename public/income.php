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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $year = (int)($_POST['year'] ?? 0);
    ensure_year($pdo, $userId, $year);
    $stmt = $pdo->prepare(
        'INSERT INTO income (user_id, year, month, salary) VALUES (?, ?, ?, ?)
         ON CONFLICT(user_id, year, month) DO UPDATE SET salary = excluded.salary'
    );
    foreach (MONTHS as $m) {
        $val = (float)($_POST['salary'][$m] ?? 0);
        $stmt->execute([$userId, $year, $m, $val]);
    }
    $flash = "Salary for $year saved.";
    header("Location: income.php?year=$year&saved=1");
    exit;
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
if (isset($_GET['saved'])) {
    $flash = "Salary for $selectedYear saved.";
}

$stmt = $pdo->prepare('SELECT month, salary FROM income WHERE user_id=? AND year=?');
$stmt->execute([$userId, $selectedYear]);
$salaries = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'salary', 'month');

$otherByMonth = [];
foreach (MONTHS as $m) {
    $otherByMonth[$m] = get_other_income_total($pdo, $userId, $selectedYear, $m);
}

$activePage = 'income';
$pageTitle = 'Income';
require __DIR__ . '/../src/layout_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h2 class="mb-0">Income — <?= $selectedYear ?></h2>
  <form method="get" class="d-flex gap-2">
    <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php foreach ($years as $y): ?>
        <option value="<?= $y ?>" <?= $y === $selectedYear ? 'selected' : '' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<p class="text-muted">Enter each month's salary as it's paid — bonuses or raises are simply that month's higher number.
Other Income is totalled automatically from the <a href="other_income.php">Other Income Log</a>.</p>

<div class="card p-3">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="year" value="<?= $selectedYear ?>">
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead><tr><th>Month</th><th>Salary (<?= h($symbol) ?>)</th><th>Other Income</th><th>Total Income</th></tr></thead>
        <tbody>
        <?php $totalSalary = 0; $totalOther = 0; foreach (MONTHS as $m): $sal = (float)($salaries[$m] ?? 0); $oth = $otherByMonth[$m]; $totalSalary += $sal; $totalOther += $oth; ?>
          <tr>
            <td><?= $m ?></td>
            <td><input type="number" step="0.01" min="0" name="salary[<?= $m ?>]" class="form-control form-control-sm input-cell" value="<?= h((string)$sal) ?>"></td>
            <td><?= fmt_money($oth, $symbol) ?></td>
            <td class="fw-semibold"><?= fmt_money($sal + $oth, $symbol) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="total-row">
            <td>TOTAL</td>
            <td><?= fmt_money($totalSalary, $symbol) ?></td>
            <td><?= fmt_money($totalOther, $symbol) ?></td>
            <td><?= fmt_money($totalSalary + $totalOther, $symbol) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <button class="btn btn-primary" type="submit">Save salaries</button>
  </form>
</div>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
