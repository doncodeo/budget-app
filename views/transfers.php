<?php require __DIR__ . '/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h2 class="mb-0 fw-bold"><i class="bi bi-arrow-left-right text-primary me-2"></i>Transfers Log</h2>
    <p class="text-muted small mb-0">Record sweep or rollover transfers between categories for <?= h($selectedMonth) ?> <?= $selectedYear ?>.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="csv_export.php?type=transfers&year=<?= $selectedYear ?>&month=<?= h($selectedMonth) ?>" class="btn btn-sm btn-outline-success">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
    </a>
  </div>
</div>

<!-- Search & Filtering Bar -->
<div class="card p-3 shadow-sm mb-4">
  <form method="get" class="row g-2 align-items-center">
    <input type="hidden" name="year" value="<?= $selectedYear ?>">
    <input type="hidden" name="month" value="<?= h($selectedMonth) ?>">
    <div class="col-md-5">
      <div class="input-group input-group-sm">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="search" class="form-control" placeholder="Search reason or category..." value="<?= h($filterSearch ?? '') ?>">
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
      <a href="transfers.php" class="btn btn-sm btn-outline-secondary">Reset</a>
    </div>
  </form>
</div>

<div class="row g-4 mb-4">
  <!-- Recurring Templates -->
  <div class="col-12">
    <div class="card p-3 shadow-sm bg-body-tertiary">
      <h6 class="fw-bold mb-2"><i class="bi bi-bookmark-star text-warning me-2"></i>Saved Transfer Templates</h6>
      <?php if (!empty($templates)): ?>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($templates as $t): ?>
            <form method="post" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="apply_template">
              <input type="hidden" name="template_id" value="<?= $t['id'] ?>">
              <input type="hidden" name="year" value="<?= $selectedYear ?>">
              <input type="hidden" name="month" value="<?= h($selectedMonth) ?>">
              <button class="btn btn-sm btn-outline-primary" type="submit">
                <i class="bi bi-lightning-charge me-1"></i> <?= h($t['name']) ?> (<?= fmt_money($t['amount'], $symbol) ?>)
              </button>
            </form>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <small class="text-muted">No saved templates yet. Create one in the form below for 1-click monthly sweeps!</small>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card p-3 shadow-sm mb-4">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>Date</th>
              <th>From Category</th>
              <th>To Category</th>
              <th class="text-end">Amount (<?= h($symbol) ?>)</th>
              <th>Description</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($paginatedLogs as $log): ?>
              <tr>
                <td><?= h($log['transfer_date']) ?></td>
                <td><span class="badge bg-danger-subtle text-danger"><?= h($log['from_category_name']) ?></span></td>
                <td><span class="badge bg-success-subtle text-success"><?= h($log['to_category_name']) ?></span></td>
                <td class="text-end fw-bold"><?= fmt_money($log['amount'], $symbol) ?></td>
                <td class="small text-muted"><?= h($log['description']) ?></td>
                <td class="text-end">
                  <form method="post" class="d-inline" onsubmit="return confirm('Delete this transfer entry?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $log['id'] ?>">
                    <button class="btn btn-sm btn-link text-danger p-0" type="submit"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($paginatedLogs)): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No transfers recorded.</td></tr>
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
      <h5 class="fw-bold mb-3"><i class="bi bi-plus-circle me-2"></i>Record New Transfer</h5>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="year" value="<?= $selectedYear ?>">
        <input type="hidden" name="month" value="<?= h($selectedMonth) ?>">
        <div class="mb-2">
          <label class="form-label small">Transfer Date</label>
          <input type="date" name="transfer_date" class="form-control form-control-sm" required value="<?= date('Y-m-d') ?>">
        </div>
        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label small">From Category</label>
            <select name="from_category_id" class="form-select form-select-sm" required>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= h($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label small">To Category</label>
            <select name="to_category_id" class="form-select form-select-sm" required>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= h($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label small">Amount (<?= h($symbol) ?>)</label>
          <input type="number" step="0.01" name="amount" class="form-control form-control-sm" required placeholder="0.00">
        </div>
        <div class="mb-3">
          <label class="form-label small">Description / Notes</label>
          <input type="text" name="description" class="form-control form-control-sm" placeholder="e.g. Swept gas savings">
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="save_as_template" id="saveTemplate" value="1">
          <label class="form-check-label small" for="saveTemplate">
            Save as a recurring template for 1-click sweeps
          </label>
        </div>
        <button class="btn btn-primary w-100" type="submit">Record Transfer</button>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
