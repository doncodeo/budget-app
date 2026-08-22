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
        $from = (int)$_POST['from_category_id'];
        $to = (int)$_POST['to_category_id'];
        $amount = (float)$_POST['amount'];
        $reason = trim($_POST['reason'] ?? '');
        $approved = $_POST['approved'] ?? 'Pending';
        $date = $_POST['entry_date'] ?: date('Y-m-d');

        if ($from === $to) {
            $flash = 'From and To buckets must be different.';
            $flashType = 'danger';
        } elseif ($amount <= 0) {
            $flash = 'Amount must be greater than zero.';
            $flashType = 'danger';
        } else {
            ensure_year($pdo, $userId, $year);
            $stmt = $pdo->prepare(
                'INSERT INTO transfers (user_id, entry_date, year, month, from_category_id, to_category_id, amount, reason, approved)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([$userId, $date, $year, $month, $from, $to, $amount, $reason, $approved]);
            $flash = 'Transfer logged. Both buckets have been updated.';
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare('DELETE FROM transfers WHERE id=? AND user_id=?')->execute([$id, $userId]);
        $flash = 'Transfer removed.';
    }
}

$years = get_years($pdo, $userId);
if (empty($years)) {
    ensure_year($pdo, $userId, (int)date('Y'));
    $years = get_years($pdo, $userId);
}
$categories = get_categories($pdo, $userId);

$stmt = $pdo->prepare(
    'SELECT t.*, fc.name AS from_name, tc.name AS to_name
     FROM transfers t
     JOIN categories fc ON fc.id = t.from_category_id
     JOIN categories tc ON tc.id = t.to_category_id
     WHERE t.user_id = ?
     ORDER BY t.year DESC, t.id DESC
     LIMIT 200'
);
$stmt->execute([$userId]);
$transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$activePage = 'transfers';
$pageTitle = 'Transfers';
require __DIR__ . '/../src/layout_header.php';
?>

<h2 class="mb-3">Transfer / Rollover Log</h2>
<p class="text-muted">Example: Gas budget <?= h($symbol) ?>10,000, actual <?= h($symbol) ?>0 &rarr; log one row: Gas &rarr; Emergency Fund, <?= h($symbol) ?>10,000.
Both buckets' In/Out and Closing on the Tracker update automatically.</p>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card p-3">
      <h5>Log a transfer</h5>
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
          <label class="form-label">From bucket</label>
          <select name="from_category_id" class="form-select" required>
            <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">To bucket</label>
          <select name="to_category_id" class="form-select" required>
            <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Amount (<?= h($symbol) ?>)</label>
          <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Reason / note</label>
          <input type="text" name="reason" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Approved?</label>
          <select name="approved" class="form-select">
            <option>Yes</option><option selected>Pending</option><option>No</option>
          </select>
        </div>
        <button class="btn btn-primary w-100" type="submit">Log transfer</button>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card p-3">
      <h5>Recent transfers</h5>
      <div class="table-responsive" style="max-height:600px; overflow:auto;">
        <table class="table table-sm">
          <thead><tr><th>Date</th><th>Year</th><th>Month</th><th>From</th><th>To</th><th class="text-end">Amount</th><th>Note</th><th>Approved</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($transfers as $t): ?>
            <tr>
              <td><?= h($t['entry_date']) ?></td>
              <td><?= $t['year'] ?></td>
              <td><?= h($t['month']) ?></td>
              <td><?= h($t['from_name']) ?></td>
              <td><?= h($t['to_name']) ?></td>
              <td class="text-end"><?= fmt_money((float)$t['amount'], $symbol) ?></td>
              <td class="small text-muted"><?= h($t['reason']) ?></td>
              <td><?= h($t['approved']) ?></td>
              <td>
                <form method="post" onsubmit="return confirm('Delete this transfer?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $t['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger">&times;</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$transfers): ?><tr><td colspan="9" class="text-muted">No transfers logged yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
