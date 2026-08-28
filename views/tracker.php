
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h2 class="mb-0 fw-bold"><i class="bi bi-table text-primary me-2"></i>Budget Tracker</h2>
    <p class="text-muted small mb-0">Overview grid for <?= h($selectedMonth) ?> <?= $selectedYear ?> &bull; Click actual values to edit inline.</p>
  </div>
  <div class="d-flex align-items-center gap-2">
    <form method="post" class="d-inline">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="toggle_period">
      <input type="hidden" name="year" value="<?= $selectedYear ?>">
      <input type="hidden" name="month" value="<?= h($selectedMonth) ?>">
      <input type="hidden" name="target_status" value="<?= !empty($isClosed) ? 'open' : 'closed' ?>">
      <?php if (!empty($isClosed)): ?>
        <button type="submit" class="btn btn-sm btn-outline-warning fw-semibold" onclick="return confirm('Re-open period <?= h($selectedMonth) ?> <?= $selectedYear ?> for edits?')">
          <i class="bi bi-unlock-fill me-1"></i> Re-open Period
        </button>
      <?php else: ?>
        <button type="submit" class="btn btn-sm btn-outline-secondary fw-semibold" onclick="return confirm('Close and lock period <?= h($selectedMonth) ?> <?= $selectedYear ?>?')">
          <i class="bi bi-lock-fill me-1"></i> Close Period
        </button>
      <?php endif; ?>
    </form>
    <a href="csv_export.php?type=tracker&year=<?= $selectedYear ?>&month=<?= h($selectedMonth) ?>" class="btn btn-sm btn-outline-success">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
    </a>
  </div>
</div>

<?php if (!empty($isClosed)): ?>
<div class="alert alert-warning py-2 px-3 shadow-sm mb-4 d-flex align-items-center justify-content-between">
  <div>
    <i class="bi bi-lock-fill me-2 fs-5"></i>
    <strong>Period <?= h($selectedMonth) ?> <?= $selectedYear ?> is closed and locked.</strong> Historical income and budget allocations cannot be altered unless re-opened.
  </div>
  <span class="badge bg-dark">Closed</span>
</div>
<?php endif; ?>

<?php if (!empty($summary)): ?>
<!-- Ready To Assign Banner on Tracker -->
<div class="card p-3 shadow-sm mb-4 border-0 <?= $summary['ready_to_assign'] > 0 ? 'bg-primary-subtle border-start border-5 border-primary' : ($summary['budget_status'] === 'Over-allocated' ? 'bg-danger-subtle border-start border-5 border-danger' : 'bg-success-subtle border-start border-5 border-success') ?>">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
      <span class="fw-bold fs-5 me-2">Ready To Assign: <span class="<?= $summary['ready_to_assign'] > 0 ? 'text-primary' : ($summary['ready_to_assign'] < 0 ? 'text-danger' : 'text-success') ?>"><?= fmt_money($summary['ready_to_assign'], $symbol) ?></span></span>
      <span class="badge <?= $summary['budget_status'] === 'Under-allocated' ? 'bg-primary' : ($summary['budget_status'] === 'Balanced' ? 'bg-success' : 'bg-danger') ?>">
        <?= h($summary['budget_status']) ?>
      </span>
      <span class="text-muted small ms-2">
        Total Income: <?= fmt_money($summary['total_income'], $symbol) ?> | Total Allocated: <?= fmt_money($summary['total_allocated'], $symbol) ?>
      </span>
    </div>
    <div>
      <?php if ($summary['ready_to_assign'] > 0): ?>
        <button type="button" class="btn btn-sm btn-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#trackerAllocateModal">
          <i class="bi bi-box-arrow-in-right me-1"></i> Allocate Income
        </button>
      <?php else: ?>
        <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold" data-bs-toggle="modal" data-bs-target="#trackerAllocateModal">
          <i class="bi bi-pencil-square me-1"></i> Allocations
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Allocate Income Modal -->
<div class="modal fade" id="trackerAllocateModal" tabindex="-1" aria-labelledby="trackerAllocateModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content shadow">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="allocate_income">
        <input type="hidden" name="year" value="<?= $selectedYear ?>">
        <input type="hidden" name="month" value="<?= h($selectedMonth) ?>">

        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-bold" id="trackerAllocateModalLabel">
            <i class="bi bi-box-arrow-in-right me-2"></i>Allocate Income — <?= h($selectedMonth) ?> <?= $selectedYear ?>
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-4">
          <div class="bg-light p-3 rounded border mb-3 d-flex justify-content-between align-items-center">
            <div>
              <span class="text-muted small">Ready To Assign:</span>
              <div class="fs-5 fw-bold text-primary" id="trackerReadyVal" data-available="<?= $summary['ready_to_assign'] ?>">
                <?= fmt_money($summary['ready_to_assign'], $symbol) ?>
              </div>
            </div>
            <div class="text-end">
              <span class="text-muted small">Remaining Unassigned:</span>
              <div class="fs-5 fw-bold" id="trackerRemainingVal">
                <?= fmt_money($summary['ready_to_assign'], $symbol) ?>
              </div>
            </div>
          </div>

          <p class="small text-muted mb-3">Assign your available Ready To Assign funds across your existing budget categories:</p>

          <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>Category</th>
                  <th>Group</th>
                  <th class="text-end" style="width: 180px;">Amount to Allocate</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($categories as $cat): ?>
                  <?php if (empty($cat['is_other'])): ?>
                    <tr>
                      <td><strong><?= h($cat['name']) ?></strong></td>
                      <td><span class="badge bg-secondary-subtle text-secondary small"><?= h($cat['group_name']) ?></span></td>
                      <td class="text-end">
                        <div class="input-group input-group-sm">
                          <span class="input-group-text"><?= h($symbol) ?></span>
                          <input type="number" step="0.01" min="0" name="allocations[<?= $cat['id'] ?>]" class="form-control text-end tracker-alloc-input" placeholder="0.00" oninput="updateTrackerAllocationTotals()">
                        </div>
                      </td>
                    </tr>
                  <?php endif; ?>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div id="trackerAllocWarningAlert" class="alert alert-danger py-2 px-3 mt-3 d-none small"></div>
        </div>

        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" id="trackerSaveAllocationBtn" class="btn btn-sm btn-primary px-4">Save Allocation</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function updateTrackerAllocationTotals() {
  const readyAvailable = parseFloat(document.getElementById('trackerReadyVal')?.getAttribute('data-available')) || 0;
  const inputs = document.querySelectorAll('.tracker-alloc-input');
  let totalAllocated = 0;
  inputs.forEach(inp => {
    const val = parseFloat(inp.value) || 0;
    if (val > 0) totalAllocated += val;
  });

  const remaining = readyAvailable - totalAllocated;
  const remainingEl = document.getElementById('trackerRemainingVal');
  const warningEl = document.getElementById('trackerAllocWarningAlert');
  const saveBtn = document.getElementById('trackerSaveAllocationBtn');

  if (remainingEl) {
    remainingEl.textContent = '<?= h($symbol) ?>' + Math.max(0, remaining).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2});
    if (remaining < -0.001) {
      remainingEl.className = 'fs-5 fw-bold text-danger';
      if (warningEl) {
        warningEl.textContent = 'Allocations exceed available income by <?= h($symbol) ?>' + Math.abs(remaining).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2}) + '.';
        warningEl.classList.remove('d-none');
      }
      if (saveBtn) saveBtn.disabled = true;
    } else {
      remainingEl.className = 'fs-5 fw-bold text-success';
      if (warningEl) warningEl.classList.add('d-none');
      if (saveBtn) saveBtn.disabled = false;
    }
  }
}
</script>
<?php endif; ?>

<input type="hidden" name="csrf_token_hidden" value="<?= h(csrf_token()) ?>">
<div class="card p-3 shadow-sm mb-4">
  <div class="table-responsive">
    <table class="table table-bordered table-sm align-middle mb-0" id="trackerTable">
      <thead>
        <tr>
          <th style="width: 25%;">Category Name</th>
          <th class="text-end" style="width: 15%;">Budget</th>
          <th class="text-end" style="width: 18%;">Actual Spent</th>
          <th class="text-end" style="width: 14%;">Transfers In</th>
          <th class="text-end" style="width: 14%;">Transfers Out</th>
          <th class="text-end" style="width: 14%;">Closing Balance</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($groups as $groupName => $rows): ?>
          <tr class="group-row">
            <td colspan="6" class="py-2">
              <span class="badge badge-group me-2" style="background-color: <?= h($rows[0]['group_color'] ?? '#1f4e78') ?>; color: #fff;">
                <?= h($groupName) ?>
              </span>
            </td>
          </tr>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="ps-4">
                <strong><?= h($r['category']['name']) ?></strong>
                <?php if ($r['category']['notes']): ?>
                  <div class="small text-muted"><?= h($r['category']['notes']) ?></div>
                <?php endif; ?>
              </td>
              <td class="text-end fw-semibold">
                <?= fmt_money($r['budget'], $symbol) ?>
                <?php if ($salary > 0): ?>
                  <div class="small text-muted fw-normal" style="font-size: 0.75rem;">
                    <?= number_format(($r['budget'] / $salary) * 100, 2) ?>% of salary
                  </div>
                <?php endif; ?>
              </td>
              <td class="text-end input-cell">
                <?php if ($r['category']['is_other'] || !empty($isClosed)): ?>
                  <span class="<?= !empty($isClosed) ? 'fw-semibold text-body' : 'text-muted' ?>" title="<?= !empty($isClosed) ? 'Period is locked' : 'Pulls automatically from Other Expenses log' ?>"><?= fmt_money($r['actual'], $symbol) ?></span>
                <?php else: ?>
                  <input type="number" step="0.01" class="form-control form-control-sm text-end border-0 bg-transparent actual-input"
                         data-cat-id="<?= $r['category']['id'] ?>"
                         data-year="<?= $selectedYear ?>"
                         data-month="<?= h($selectedMonth) ?>"
                         value="<?= $r['actual'] ?: '' ?>" placeholder="0">
                <?php endif; ?>
              </td>
              <td class="text-end text-success">+<?= fmt_money($r['in'], $symbol) ?></td>
              <td class="text-end text-danger">-<?= fmt_money($r['out'], $symbol) ?></td>
              <td class="text-end fw-bold closing-cell-<?= $r['category']['id'] ?> <?= ($r['closing'] >= 0 ? 'positive' : 'negative') ?>">
                <?= fmt_money($r['closing'], $symbol) ?>
                <?php if ($r['closing'] < 0): ?>
                  <span class="badge bg-danger ms-1" style="font-size: 0.65rem;">Overspent</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>

        <tr class="total-row table-active">
          <td>TOTALS</td>
          <td class="text-end" id="totalBudget"><?= fmt_money($totals['budget'], $symbol) ?></td>
          <td class="text-end" id="totalActual"><?= fmt_money($totals['actual'], $symbol) ?></td>
          <td class="text-end text-success" id="totalIn">+<?= fmt_money($totals['in'], $symbol) ?></td>
          <td class="text-end text-danger" id="totalOut">-<?= fmt_money($totals['out'], $symbol) ?></td>
          <td class="text-end <?= ($totals['closing'] >= 0 ? 'positive' : 'negative') ?>" id="totalClosing">
            <?= fmt_money($totals['closing'], $symbol) ?>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const inputs = document.querySelectorAll('.actual-input');
  inputs.forEach(input => {
    input.addEventListener('blur', function() {
      const catId = this.dataset.catId;
      const year = this.dataset.year;
      const month = this.dataset.month;
      const actual = this.value;

      const csrfVal = document.querySelector('input[name="csrf_token_hidden"]')?.value || '';
      const formData = new FormData();
      formData.append('csrf', csrfVal);
      formData.append('category_id', catId);
      formData.append('year', year);
      formData.append('month', month);
      formData.append('actual', actual);

      fetch('api_tracker.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          const closingEl = document.querySelector(`.closing-cell-${catId}`);
          if (closingEl) {
            if (!data.row.is_positive) {
              closingEl.innerHTML = `${data.row.closing_formatted} <span class="badge bg-danger ms-1" style="font-size: 0.65rem;">Overspent</span>`;
              closingEl.className = `text-end fw-bold closing-cell-${catId} negative`;
            } else {
              closingEl.textContent = data.row.closing_formatted;
              closingEl.className = `text-end fw-bold closing-cell-${catId} positive`;
            }
          }
          document.getElementById('totalBudget').textContent = data.totals.budget_formatted;
          document.getElementById('totalActual').textContent = data.totals.actual_formatted;
          document.getElementById('totalIn').textContent = '+' + data.totals.in_formatted;
          document.getElementById('totalOut').textContent = '-' + data.totals.out_formatted;
          const totalClosingEl = document.getElementById('totalClosing');
          totalClosingEl.textContent = data.totals.closing_formatted;
          totalClosingEl.className = `text-end ${data.totals.is_positive ? 'positive' : 'negative'}`;
        }
      });
    });
  });
});
</script>
