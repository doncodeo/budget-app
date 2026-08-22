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
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $year = (int)$_POST['year'];
        $month = $_POST['month'];
        $description = trim($_POST['description'] ?? '');
        $amount = (float)$_POST['amount'];
        $notes = trim($_POST['notes'] ?? '');
        $date = $_POST['entry_date'] ?: date('Y-m-d');
        if ($amount <= 0) {
            $flash = 'Amount must be greater than zero.';
            $flashType = 'danger';
        } else {
            ensure_year($pdo, $userId, $year);
            $stmt = $pdo->prepare(
                'INSERT INTO other_expenses (user_id, entry_date, year, month, description, amount, notes) VALUES (?,?,?,?,?,?,?)'
            );
            $stmt->execute([$userId, $date, $year, $month, $description, $amount, $notes]);
            $flash = 'Expense logged.';
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare('DELETE FROM other_expenses WHERE id=? AND user_id=?')->execute([$id, $userId]);
        $flash = 'Entry removed.';
    }
}

$years = get_years($pdo, $userId);
if (empty($years)) {
    ensure_year($pdo, $userId, (int)date('Y'));
    $years = get_years($pdo, $userId);
}

$stmt = $pdo->prepare('SELECT * FROM other_expenses WHERE user_id=? ORDER BY year DESC, id DESC LIMIT 200');
$stmt->execute([$userId]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$activePage = 'other_expenses';
$pageTitle = 'Other Expenses';
require __DIR__ . '/../src/layout_header.php';
?>

<h2 class="mb-3">Other / Unplanned Expense Log</h2>
<p class="text-muted">Anything spent outside the normal budget — emergencies, one-off purchases. It totals automatically into the "Other / Unplanned Expense" line on the <a href="tracker.php">Tracker</a>.</p>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card p-3">
      <h5>Add entry</h5>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">Year</label>
            <select name="year" class="form-select">
              <?php foreach ($years as $y): ?><option value="<?= $y ?>"><?= $y ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Month</label>
            <select name="month" class="form-select">
              <?php foreach (MONTHS as $m): ?><option value="<?= $m ?>"><?= $m ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mb-2 mt-2">
          <label class="form-label">Date</label>
          <input type="date" name="entry_date" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="mb-2">
          <label class="form-label">Description</label>
          <input type="text" name="description" class="form-control" placeholder="e.g. Car repair">
        </div>
        <div class="mb-2">
          <label class="form-label">Amount (<?= h($symbol) ?>)</label>
          <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Notes</label>
          <input type="text" name="notes" class="form-control">
        </div>
        <button class="btn btn-primary w-100" type="submit">Add</button>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card p-3">
      <h5>Recent entries</h5>
      <div class="table-responsive" style="max-height:600px; overflow:auto;">
        <table class="table table-sm">
          <thead><tr><th>Date</th><th>Year</th><th>Month</th><th>Description</th><th class="text-end">Amount</th><th>Notes</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($entries as $e): ?>
            <tr>
              <td><?= h($e['entry_date']) ?></td>
              <td><?= $e['year'] ?></td>
              <td><?= h($e['month']) ?></td>
              <td><?= h($e['description']) ?></td>
              <td class="text-end"><?= fmt_money((float)$e['amount'], $symbol) ?></td>
              <td class="small text-muted"><?= h($e['notes']) ?></td>
              <td>
                <form method="post" onsubmit="return confirm('Delete this entry?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $e['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger">&times;</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$entries): ?><tr><td colspan="7" class="text-muted">No entries yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
