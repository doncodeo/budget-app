
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h2 class="mb-0 fw-bold"><i class="bi bi-box-arrow-in-down text-info me-2"></i>Other Income Log</h2>
    <p class="text-muted small mb-0">Record side-hustles, gifts, dividends, or extra income for <?= h($selectedMonth) ?> <?= $selectedYear ?>.</p>
  </div>
  <div>
    <a href="csv_export.php?type=other_income&year=<?= $selectedYear ?>&month=<?= h($selectedMonth) ?>" class="btn btn-sm btn-outline-success">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
    </a>
  </div>
</div>

<!-- Search & Filter Bar -->
<div class="card p-3 shadow-sm mb-4">
  <form method="get" class="row g-2 align-items-center">
    <input type="hidden" name="year" value="<?= $selectedYear ?>">
    <input type="hidden" name="month" value="<?= h($selectedMonth) ?>">
    <div class="col-md-5">
      <div class="input-group input-group-sm">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="search" class="form-control" placeholder="Search source or notes..." value="<?= h($filterSearch ?? '') ?>">
      </div>
    </div>
    <div class="col-md-3">
      <select name="filter_year" class="form-select form-select-sm">
        <option value="">All Years</option>
        <?php foreach ($years as $y): ?>
          <option value="<?= $y ?>" <?= $y === $filterYear ? 'selected' : '' ?>><?= $y ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select name="filter_month" class="form-select form-select-sm">
        <option value="">All Months</option>
        <?php foreach ($MONTHS as $m): ?>
          <option value="<?= h($m) ?>" <?= $m === $filterMonth ? 'selected' : '' ?>><?= h($m) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
      <button class="btn btn-sm btn-primary w-100" type="submit">Filter</button>
      <a href="other_income.php" class="btn btn-sm btn-outline-secondary">Reset</a>
    </div>
  </form>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card p-3 shadow-sm mb-4">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>Date</th>
              <th>Source / Notes</th>
              <th class="text-end">Amount (<?= h($symbol) ?>)</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($paginatedLogs as $log): ?>
              <tr>
                <td><?= h($log['entry_date']) ?></td>
                <td><?= h($log['source']) ?></td>
                <td class="text-end fw-bold text-success">+<?= fmt_money($log['amount'], $symbol) ?></td>
                <td class="text-end">
                  <form method="post" class="d-inline" onsubmit="return confirm('Delete this entry?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $log['id'] ?>">
                    <button class="btn btn-sm btn-link text-danger p-0" type="submit"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($paginatedLogs)): ?>
              <tr><td colspan="4" class="text-center text-muted py-4">No other income entries recorded.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if (!empty($totalPages) && $totalPages > 1): ?>
        <nav class="mt-3">
          <ul class="pagination pagination-sm justify-content-center mb-0">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
              <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($filterSearch) ?>&filter_year=<?= $filterYear ?>&filter_month=<?= urlencode($filterMonth) ?>"><?= $p ?></a>
              </li>
            <?php endfor; ?>
          </ul>
        </nav>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card p-3 shadow-sm">
      <h5 class="fw-bold mb-3"><i class="bi bi-plus-circle me-2"></i>Add Other Income</h5>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="year" value="<?= $selectedYear ?>">
        <input type="hidden" name="month" value="<?= h($selectedMonth) ?>">
        <div class="mb-2">
          <label class="form-label small">Date</label>
          <input type="date" name="entry_date" class="form-control form-control-sm" required value="<?= date('Y-m-d') ?>">
        </div>
        <div class="mb-2">
          <label class="form-label small">Source / Notes</label>
          <input type="text" name="source" class="form-control form-control-sm" required placeholder="e.g. Freelance project">
        </div>
        <div class="mb-3">
          <label class="form-label small">Amount (<?= h($symbol) ?>)</label>
          <input type="number" step="0.01" name="amount" class="form-control form-control-sm" required placeholder="0.00">
        </div>
        <button class="btn btn-primary w-100" type="submit">Add Income</button>
      </form>
    </div>
  </div>
</div>
