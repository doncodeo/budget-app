<?php require __DIR__ . '/header.php'; ?>

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

<div class="row g-3 mb-4">
  <div class="col-6 col-md-4 col-lg-2">
    <div class="card p-3 h-100 border-start border-4 border-info">
      <div class="text-muted small">Salary</div>
      <div class="fs-5 fw-bold text-truncate"><?= fmt_money($salary, $symbol) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-lg-2">
    <div class="card p-3 h-100 border-start border-4 border-cyan">
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
      <div class="text-muted small">Planned Budget</div>
      <div class="fs-5 fw-bold text-truncate"><?= fmt_money($totals['budget'], $symbol) ?></div>
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

<script>
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

<?php require __DIR__ . '/footer.php'; ?>
