<?php
require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/helpers.php';

$user = require_login();
$pdo = get_db();
$userId = (int)$user['id'];

$flash = null;
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $group = trim($_POST['group_name'] ?? '') ?: 'General';
        $basis = $_POST['basis'] === 'percent' ? 'percent' : 'fixed';
        $fixed = $basis === 'fixed' ? (float)($_POST['fixed_amount'] ?? 0) : null;
        $percent = $basis === 'percent' ? ((float)($_POST['percent'] ?? 0) / 100) : null;
        $notes = trim($_POST['notes'] ?? '');

        if ($name === '') {
            $flash = 'Category name is required.';
            $flashType = 'danger';
        } elseif ($action === 'create') {
            $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM categories')->fetchColumn();
            $stmt = $pdo->prepare(
                'INSERT INTO categories (user_id, name, group_name, basis, fixed_amount, percent, notes, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $name, $group, $basis, $fixed, $percent, $notes, $maxOrder + 1]);
            $flash = "\"$name\" added.";
        } else {
            $catId = (int)($_POST['category_id'] ?? 0);
            $stmt = $pdo->prepare(
                'UPDATE categories SET name=?, group_name=?, basis=?, fixed_amount=?, percent=?, notes=?
                 WHERE id=? AND user_id=?'
            );
            $stmt->execute([$name, $group, $basis, $fixed, $percent, $notes, $catId, $userId]);
            $flash = "\"$name\" updated.";
        }
    } elseif ($action === 'delete') {
        $catId = (int)($_POST['category_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT is_other FROM categories WHERE id=? AND user_id=?');
        $stmt->execute([$catId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $pdo->prepare('DELETE FROM categories WHERE id=? AND user_id=?')->execute([$catId, $userId]);
            $flash = 'Category deleted.';
        }
    }
}

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$editCat = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id=? AND user_id=?');
    $stmt->execute([$editId, $userId]);
    $editCat = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$categories = get_categories($pdo, $userId);

$activePage = 'settings';
$pageTitle = 'Settings';
require __DIR__ . '/../src/layout_header.php';
?>

<h2 class="mb-3">Budget Settings — Category Rules</h2>
<p class="text-muted">Choose <strong>Fixed</strong> to keep a ₦ amount the same every month, or <strong>% of Salary</strong>
to recalculate the monthly budget automatically whenever that month's salary changes.</p>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card p-3">
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead><tr><th>Category</th><th>Group</th><th>Basis</th><th>Amount</th><th>Notes</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($categories as $cat): ?>
            <tr>
              <td><?= h($cat['name']) ?><?= $cat['is_other'] ? ' <span class="badge bg-secondary">auto</span>' : '' ?></td>
              <td><?= h($cat['group_name']) ?></td>
              <td><?= $cat['basis'] === 'percent' ? '% of Salary' : 'Fixed' ?></td>
              <td>
                <?= $cat['basis'] === 'percent'
                    ? number_format(((float)$cat['percent']) * 100, 2) . '%'
                    : fmt_money((float)$cat['fixed_amount'], $user['currency_symbol']) ?>
              </td>
              <td class="small text-muted"><?= h($cat['notes']) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-secondary" href="settings.php?edit=<?= $cat['id'] ?>">Edit</a>
                <?php if (!$cat['is_other']): ?>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this category? Its history stays in the log tables but will no longer appear on the Tracker.');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card p-3">
      <h5><?= $editCat ? 'Edit category' : 'Add a category' ?></h5>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editCat ? 'update' : 'create' ?>">
        <?php if ($editCat): ?><input type="hidden" name="category_id" value="<?= $editCat['id'] ?>"><?php endif; ?>

        <div class="mb-2">
          <label class="form-label">Name</label>
          <input type="text" name="name" class="form-control" required value="<?= h($editCat['name'] ?? '') ?>">
        </div>
        <div class="mb-2">
          <label class="form-label">Group</label>
          <input type="text" name="group_name" class="form-control" placeholder="e.g. Household & Family"
                 value="<?= h($editCat['group_name'] ?? '') ?>">
        </div>
        <div class="mb-2">
          <label class="form-label">Basis</label>
          <select name="basis" class="form-select" id="basisSelect" onchange="toggleBasis()">
            <option value="fixed" <?= (($editCat['basis'] ?? 'fixed') === 'fixed') ? 'selected' : '' ?>>Fixed ₦ amount</option>
            <option value="percent" <?= (($editCat['basis'] ?? '') === 'percent') ? 'selected' : '' ?>>% of Salary</option>
          </select>
        </div>
        <div class="mb-2" id="fixedField">
          <label class="form-label">Fixed amount (₦)</label>
          <input type="number" step="0.01" name="fixed_amount" class="form-control"
                 value="<?= h((string)($editCat['fixed_amount'] ?? '0')) ?>">
        </div>
        <div class="mb-2" id="percentField" style="display:none">
          <label class="form-label">% of Salary</label>
          <input type="number" step="0.01" name="percent" class="form-control"
                 value="<?= h((string)(isset($editCat['percent']) ? round($editCat['percent'] * 100, 4) : '')) ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="2"><?= h($editCat['notes'] ?? '') ?></textarea>
        </div>
        <button class="btn btn-primary" type="submit"><?= $editCat ? 'Save changes' : 'Add category' ?></button>
        <?php if ($editCat): ?><a href="settings.php" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
      </form>
    </div>
  </div>
</div>

<script>
function toggleBasis() {
  const isPercent = document.getElementById('basisSelect').value === 'percent';
  document.getElementById('fixedField').style.display = isPercent ? 'none' : '';
  document.getElementById('percentField').style.display = isPercent ? '' : 'none';
}
toggleBasis();
</script>

<?php require __DIR__ . '/../src/layout_footer.php'; ?>
