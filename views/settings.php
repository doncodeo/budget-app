<?php require __DIR__ . '/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h2 class="mb-0 fw-bold"><i class="bi bi-gear text-primary me-2"></i>Budget Settings</h2>
    <p class="text-muted small mb-0">Define category rules (Fixed vs % of Salary) and manage options.</p>
  </div>
</div>

<div class="row g-4">
  <!-- Categories List -->
  <div class="col-lg-7">
    <div class="card p-3 shadow-sm">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr><th>Category</th><th>Group</th><th>Basis</th><th>Amount / %</th><th>Notes</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($categories as $cat): ?>
              <tr>
                <td><strong><?= h($cat['name']) ?></strong></td>
                <td><span class="badge bg-secondary-subtle text-secondary"><?= h($cat['group_name']) ?></span></td>
                <td><?= h($cat['basis']) ?></td>
                <td class="fw-semibold">
                  <?= $cat['basis'] === 'percent' ? ((float)$cat['percent'] * 100) . '%' : fmt_money((float)$cat['fixed_amount'], $symbol) ?>
                </td>
                <td class="small text-muted"><?= h($cat['notes']) ?></td>
                <td class="text-end">
                  <a href="settings.php?edit=<?= $cat['id'] ?>" class="btn btn-sm btn-link p-0 text-primary me-2"><i class="bi bi-pencil"></i></a>
                  <?php if (!$cat['is_other']): ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('Archive this category?')">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="archive">
                      <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                      <button class="btn btn-sm btn-link text-danger p-0" type="submit"><i class="bi bi-archive"></i></button>
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

  <!-- Form for Add/Edit -->
  <div class="col-lg-5">
    <div class="card p-3 shadow-sm">
      <h5 class="fw-bold mb-3">
        <?php if (!empty($editingCat)): ?>
          <i class="bi bi-pencil-square text-primary me-2"></i>Edit Category
        <?php else: ?>
          <i class="bi bi-plus-circle text-primary me-2"></i>Add Category
        <?php endif; ?>
      </h5>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= !empty($editingCat) ? 'update' : 'create' ?>">
        <?php if (!empty($editingCat)): ?>
          <input type="hidden" name="id" value="<?= $editingCat['id'] ?>">
        <?php endif; ?>

        <div class="mb-2">
          <label class="form-label small">Name</label>
          <input type="text" name="name" class="form-control form-control-sm" required value="<?= h($editingCat['name'] ?? '') ?>">
        </div>
        <div class="mb-2">
          <label class="form-label small">Group Name</label>
          <input type="text" name="group_name" class="form-control form-control-sm" placeholder="e.g. Household & Family" value="<?= h($editingCat['group_name'] ?? '') ?>">
        </div>
        <div class="mb-2">
          <label class="form-label small">Basis</label>
          <select name="basis" class="form-select form-select-sm" id="basisSelect" onchange="toggleBasis()">
            <option value="fixed" <?= ($editingCat['basis'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed Amount</option>
            <option value="percent" <?= ($editingCat['basis'] ?? '') === 'percent' ? 'selected' : '' ?>>% of Salary</option>
          </select>
        </div>
        <div class="mb-2" id="fixedField">
          <label class="form-label small">Fixed Amount (<?= h($symbol) ?>)</label>
          <input type="number" step="0.01" name="fixed_amount" class="form-control form-control-sm" value="<?= $editingCat['fixed_amount'] ?? '0' ?>">
        </div>
        <div class="mb-2" id="percentField" style="display:none">
          <label class="form-label small">% of Salary</label>
          <input type="number" step="0.01" name="percent" class="form-control form-control-sm" value="<?= !empty($editingCat['percent']) ? ((float)$editingCat['percent'] * 100) : '' ?>">
        </div>
        <div class="mb-3">
          <label class="form-label small">Notes</label>
          <textarea name="notes" class="form-control form-control-sm" rows="2"><?= h($editingCat['notes'] ?? '') ?></textarea>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-primary flex-grow-1" type="submit"><?= !empty($editingCat) ? 'Save Changes' : 'Add Category' ?></button>
          <?php if (!empty($editingCat)): ?>
            <a href="settings.php" class="btn btn-outline-secondary">Cancel</a>
          <?php endif; ?>
        </div>
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

<?php require __DIR__ . '/footer.php'; ?>
