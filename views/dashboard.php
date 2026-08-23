<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h2 class="mb-0 fw-bold"><i class="bi bi-speedometer2 text-primary me-2"></i>Dashboard</h2>
    <p class="text-muted small mb-0">Overview for <?= h($selectedMonth) ?> <?= $selectedYear ?> &bull; Budget: <strong><?= h($user['budget_name'] ?? 'Personal') ?></strong></p>
  </div>
</div>

<?php if (!empty($isFirstRun)): ?>
<div class="card bg-primary-subtle border-primary mb-4 p-3 shadow-sm">
  <div class="d-flex align-items-center gap-3">
    <div class="fs-1 text-primary"><i class="bi bi-rocket-takeoff"></i></div>
    <div>
      <h5 class="fw-bold mb-1">Welcome to your Personal Finance Budget!</h5>
      <p class="mb-2 text-secondary small">Follow these 3 quick steps to set up your financial tracking:</p>
      <div class="d-flex flex-wrap gap-2">
        <a href="income.php" class="btn btn-sm btn-primary"><i class="bi bi-1-circle me-1"></i> 1. Set Monthly Salary</a>
        <a href="settings.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-2-circle me-1"></i> 2. Review Categories & Rules</a>
        <a href="tracker.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-3-circle me-1"></i> 3. Log Actuals as You Spend</a>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Budget Status & Ready To Assign Prominent Section -->
<div class="card p-4 shadow-sm mb-4 border-0 <?= $summary['ready_to_assign'] > 0 ? 'bg-primary-subtle border-start border-5 border-primary' : ($summary['budget_status'] === 'Over-allocated' ? 'bg-danger-subtle border-start border-5 border-danger' : 'bg-success-subtle border-start border-5 border-success') ?>">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <h4 class="fw-bold mb-0">
          Ready To Assign: <span class="<?= $summary['ready_to_assign'] > 0 ? 'text-primary' : ($summary['ready_to_assign'] < 0 ? 'text-danger' : 'text-success') ?>"><?= fmt_money($summary['ready_to_assign'], $symbol) ?></span>
        </h4>
        <span class="badge <?= $summary['budget_status'] === 'Under-allocated' ? 'bg-primary' : ($summary['budget_status'] === 'Balanced' ? 'bg-success' : 'bg-danger') ?> fs-6">
          Budget Status: <?= h($summary['budget_status']) ?>
        </span>
      </div>
      <p class="text-secondary small mb-0">
        Total Income: <strong><?= fmt_money($summary['total_income'], $symbol) ?></strong> &bull;
        Total Allocated: <strong><?= fmt_money($summary['total_allocated'], $symbol) ?></strong>
        <?php if ($summary['budget_status'] === 'Over-allocated'): ?>
          &bull; <span class="text-danger fw-bold">Budget exceeds available income by <?= fmt_money($summary['over_allocated_amount'], $symbol) ?>.</span>
        <?php endif; ?>
      </p>
    </div>

    <div>
      <?php if ($summary['ready_to_assign'] > 0): ?>
        <button type="button" class="btn btn-primary fw-semibold px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#allocateModal">
          <i class="bi bi-box-arrow-in-right me-1"></i> Allocate Income
        </button>
      <?php else: ?>
        <button type="button" class="btn btn-outline-secondary fw-semibold px-3" data-bs-toggle="modal" data-bs-target="#allocateModal">
          <i class="bi bi-pencil-square me-1"></i> Allocations
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-4 col-lg-2">
    <div class="card p-3 h-100 border-start border-4 border-info">
      <div class="text-muted small">Salary</div>
      <div class="fs-5 fw-bold text-truncate"><?= fmt_money($salary, $symbol) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-lg-2">
    <div class="card p-3 h-100 border-start border-4 border-info">
      <div class="text-muted small">Other Income</div>
      <div class="fs-5 fw-bold text-truncate"><?= fmt_money($otherIncome, $symbol) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-lg-2">
    <div class="card p-3 h-100 border-start border-4 border-primary">
      <div class="text-muted small">Total Income</div>
      <div class="fs-5 fw-bold text-truncate"><?= fmt_money($totalIncome, $symbol) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-lg-2">
    <div class="card p-3 h-100 border-start border-4 border-secondary">
      <div class="text-muted small">Total Allocated</div>
      <div class="fs-5 fw-bold text-truncate"><?= fmt_money($summary['total_allocated'], $symbol) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-lg-2">
    <div class="card p-3 h-100 border-start border-4 border-warning">
      <div class="text-muted small">Actual Spent</div>
      <div class="fs-5 fw-bold text-truncate"><?= fmt_money($totals['actual'], $symbol) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-lg-2">
    <div class="card p-3 h-100 border-start border-4 border-success">
      <div class="text-muted small">Net Position</div>
      <div class="fs-5 fw-bold <?= ($totals['closing'] >= 0 ? 'positive' : 'negative') ?> text-truncate"><?= fmt_money($totals['closing'], $symbol) ?></div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <!-- Chart 1: Donut Spending by Group -->
  <div class="col-lg-5">
    <div class="card p-3 h-100">
      <h5 class="fw-bold mb-3"><i class="bi bi-pie-chart text-primary me-2"></i>Spending by Group</h5>
      <?php if (!empty($groupBreakdown['data'])): ?>
        <div style="position: relative; height:260px;">
          <canvas id="groupSpendingChart"></canvas>
        </div>
      <?php else: ?>
        <div class="text-muted text-center py-5">No actual spending logged for <?= h($selectedMonth) ?> <?= $selectedYear ?>.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Chart 2: Net Position 6-12 Month Trend -->
  <div class="col-lg-7">
    <div class="card p-3 h-100">
      <h5 class="fw-bold mb-3"><i class="bi bi-graph-up-arrow text-success me-2"></i>Net Position Trend</h5>
      <div style="position: relative; height:260px;">
        <canvas id="netPositionChart"></canvas>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($allocationsHistory)): ?>
<div class="card p-3 mb-4 shadow-sm">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-clock-history text-primary me-2"></i>Income Allocation History — <?= h($selectedMonth) ?> <?= $selectedYear ?></h5>
    <span class="badge bg-primary-subtle text-primary"><?= count($allocationsHistory) ?> Allocations</span>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr>
          <th>Category</th>
          <th class="text-end">Amount</th>
          <th>Source</th>
          <th>Allocated By</th>
          <th>Date</th>
          <th class="text-end">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($allocationsHistory as $alloc): ?>
          <tr>
            <td><strong><?= h($alloc['category_name']) ?></strong></td>
            <td class="text-end fw-bold text-primary"><?= fmt_money($alloc['amount'], $symbol) ?></td>
            <td><span class="badge bg-light text-dark border"><?= h($alloc['source_type']) ?></span></td>
            <td><small class="text-muted"><i class="bi bi-person me-1"></i><?= h($alloc['created_by_username']) ?></small></td>
            <td><small class="text-muted"><?= h($alloc['created_at']) ?></small></td>
            <td class="text-end">
              <form method="post" class="d-inline" onsubmit="return confirm('Remove this allocation? It will return to Ready To Assign.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_allocation">
                <input type="hidden" name="allocation_id" value="<?= $alloc['id'] ?>">
                <input type="hidden" name="year" value="<?= $selectedYear ?>">
                <input type="hidden" name="month" value="<?= h($selectedMonth) ?>">
                <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Delete Allocation"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="row g-4 mb-4">
  <!-- Budget vs Actual Progress Bars -->
  <div class="col-lg-7">
    <div class="card p-3">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0"><i class="bi bi-bar-chart-steps text-info me-2"></i>Category Budget vs Actual</h5>
        <span class="badge bg-secondary-subtle text-secondary">Progress</span>
      </div>
      <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Category</th>
              <th class="text-end">Budget</th>
              <th class="text-end">Actual</th>
              <th style="width: 35%;">Progress</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($progressList as $item): ?>
              <tr>
                <td>
                  <span class="badge badge-group me-1" style="background-color: <?= h($item['group_color'] ?? '#1f4e78') ?>; color: #fff;">
                    <?= h($item['group_name']) ?>
                  </span>
                  <strong><?= h($item['name']) ?></strong>
                </td>
                <td class="text-end"><?= fmt_money($item['budget'], $symbol) ?></td>
                <td class="text-end"><?= fmt_money($item['actual'], $symbol) ?></td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1 progress-budget">
                      <div class="progress-bar bg-<?= h($item['status_class'] ?? 'success') ?>" role="progressbar" style="width: <?= $item['percent'] ?>%;"></div>
                    </div>
                    <small class="text-muted fw-bold" style="min-width: 40px;"><?= $item['percent'] ?>%</small>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Key Targets & Quick Year Add -->
  <div class="col-lg-5">
    <div class="card p-3 mb-4">
      <h5 class="fw-bold mb-3"><i class="bi bi-bullseye text-warning me-2"></i>Key Targets — <?= h($selectedMonth) ?> <?= $selectedYear ?></h5>
      <table class="table table-sm mb-2">
        <tbody>
          <tr><td>Rent Savings / month</td><td class="text-end fw-semibold"><?= fmt_money($rentSavings['budget'] ?? 0, $symbol) ?></td></tr>
          <tr><td>Emergency Fund / month</td><td class="text-end fw-semibold"><?= fmt_money($emergency['budget'] ?? 0, $symbol) ?></td></tr>
          <tr><td>Investment / month</td><td class="text-end fw-semibold"><?= fmt_money($investment['budget'] ?? 0, $symbol) ?></td></tr>
          <tr><td>Monthly Buffer</td><td class="text-end fw-semibold"><?= fmt_money($buffer['budget'] ?? 0, $symbol) ?></td></tr>
        </tbody>
      </table>
      <a href="settings.php" class="small text-decoration-none">Edit rules on Settings page &rarr;</a>
    </div>

    <div class="card p-3">
      <h5 class="fw-bold mb-2"><i class="bi bi-calendar-plus text-primary me-2"></i>Add a New Year</h5>
      <form method="post" class="d-flex gap-2 align-items-center">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_year">
        <input type="number" name="new_year" class="form-control form-control-sm" style="max-width:140px" placeholder="e.g. 2028" min="2000" max="2100" required>
        <button class="btn btn-sm btn-primary" type="submit">Add Year</button>
      </form>
      <div class="form-text mt-1">Earlier years are never touched or overwritten.</div>
    </div>
  </div>
</div>

<!-- Allocate Income Modal -->
<div class="modal fade" id="allocateModal" tabindex="-1" aria-labelledby="allocateModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content shadow">
      <form method="post" id="allocateIncomeForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="allocate_income">
        <input type="hidden" name="year" value="<?= $selectedYear ?>">
        <input type="hidden" name="month" value="<?= h($selectedMonth) ?>">

        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-bold" id="allocateModalLabel">
            <i class="bi bi-box-arrow-in-right me-2"></i>Allocate Income — <?= h($selectedMonth) ?> <?= $selectedYear ?>
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-4">
          <div class="bg-light p-3 rounded border mb-3 d-flex justify-content-between align-items-center">
            <div>
              <span class="text-muted small">Ready To Assign:</span>
              <div class="fs-5 fw-bold text-primary" id="readyToAssignVal" data-available="<?= $summary['ready_to_assign'] ?>">
                <?= fmt_money($summary['ready_to_assign'], $symbol) ?>
              </div>
            </div>
            <div class="text-end">
              <span class="text-muted small">Remaining Unassigned:</span>
              <div class="fs-5 fw-bold" id="remainingVal">
                <?= fmt_money($summary['ready_to_assign'], $symbol) ?>
              </div>
            </div>
          </div>

          <p class="small text-muted mb-3">Assign your available Ready To Assign funds across your existing budget categories for <?= h($selectedMonth) ?> <?= $selectedYear ?>:</p>

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
                          <input type="number" step="0.01" min="0" name="allocations[<?= $cat['id'] ?>]" class="form-control text-end alloc-input" placeholder="0.00" oninput="updateAllocationTotals()">
                        </div>
                      </td>
                    </tr>
                  <?php endif; ?>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div id="allocWarningAlert" class="alert alert-danger py-2 px-3 mt-3 d-none small"></div>
        </div>

        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" id="saveAllocationBtn" class="btn btn-sm btn-primary px-4">Save Allocation</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function updateAllocationTotals() {
  const readyAvailable = parseFloat(document.getElementById('readyToAssignVal').getAttribute('data-available')) || 0;
  const inputs = document.querySelectorAll('.alloc-input');
  let totalAllocated = 0;
  inputs.forEach(inp => {
    const val = parseFloat(inp.value) || 0;
    if (val > 0) totalAllocated += val;
  });

  const remaining = readyAvailable - totalAllocated;
  const remainingEl = document.getElementById('remainingVal');
  const warningEl = document.getElementById('allocWarningAlert');
  const saveBtn = document.getElementById('saveAllocationBtn');

  if (remainingEl) {
    remainingEl.textContent = '<?= h($symbol) ?>' + Math.max(0, remaining).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2});
    if (remaining < -0.001) {
      remainingEl.className = 'fs-5 fw-bold text-danger';
      warningEl.textContent = 'Allocations exceed available income by <?= h($symbol) ?>' + Math.abs(remaining).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2}) + '.';
      warningEl.classList.remove('d-none');
      saveBtn.disabled = true;
    } else {
      remainingEl.className = 'fs-5 fw-bold text-success';
      warningEl.classList.add('d-none');
      saveBtn.disabled = false;
    }
  }
}

document.addEventListener('DOMContentLoaded', function() {
  // Donut Chart
  const groupCanvas = document.getElementById('groupSpendingChart');
  if (groupCanvas) {
    const groupData = <?= json_encode($groupBreakdown) ?>;
    new Chart(groupCanvas, {
      type: 'doughnut',
      data: {
        labels: groupData.labels,
        datasets: [{
          data: groupData.data,
          backgroundColor: groupData.colors,
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 12 } }
        }
      }
    });
  }

  // Net Position Trend Line Chart
  const netCanvas = document.getElementById('netPositionChart');
  if (netCanvas) {
    const netHistory = <?= json_encode($netHistory) ?>;
    const labels = netHistory.map(h => h.label);
    const netValues = netHistory.map(h => h.net);

    new Chart(netCanvas, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Net Position (<?= h($symbol) ?>)',
          data: netValues,
          borderColor: '#198754',
          backgroundColor: 'rgba(25, 135, 84, 0.1)',
          fill: true,
          tension: 0.3,
          pointRadius: 4,
          pointHoverRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            grid: { color: 'rgba(0,0,0,0.05)' }
          },
          x: {
            grid: { display: false }
          }
        },
        plugins: {
          legend: { display: false }
        }
      }
    });
  }
});
</script>
