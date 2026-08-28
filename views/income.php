
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h2 class="mb-0 fw-bold"><i class="bi bi-cash-stack text-success me-2"></i>Income Log</h2>
    <p class="text-muted small mb-0">Record primary monthly salary and view combined total income for <?= $selectedYear ?>.</p>
  </div>
  <div>
    <a href="csv_export.php?type=income&year=<?= $selectedYear ?>" class="btn btn-sm btn-outline-success">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
    </a>
  </div>
</div>

<div class="card p-3 shadow-sm mb-4">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="year" value="<?= $selectedYear ?>">
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>Month</th>
            <th class="text-end" style="width: 30%;">Monthly Salary (<?= h($symbol) ?>)</th>
            <th class="text-end" style="width: 25%;">Other Income (<?= h($symbol) ?>)</th>
            <th class="text-end" style="width: 25%;">Total Income (<?= h($symbol) ?>)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($MONTHS as $m): ?>
            <?php
              $sal = (float)($salaries[$m] ?? 0);
              $oth = (float)($otherByMonth[$m] ?? 0);
              $tot = $sal + $oth;
              $isClosed = !empty($closedMonths[$m]);
            ?>
            <tr class="<?= $isClosed ? 'table-light' : '' ?>">
              <td class="fw-semibold">
                <?= h($m) ?>
                <?php if ($isClosed): ?>
                  <span class="badge bg-secondary ms-1" style="font-size: 0.65rem;" title="Period is closed and locked"><i class="bi bi-lock-fill me-1"></i>Locked</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <input type="number" step="0.01" name="salary[<?= h($m) ?>]" class="form-control form-control-sm text-end" value="<?= $sal ?: '' ?>" placeholder="0.00" <?= $isClosed ? 'readonly' : '' ?>>
              </td>
              <td class="text-end text-muted"><?= fmt_money($oth, $symbol) ?></td>
              <td class="text-end fw-bold positive"><?= fmt_money($tot, $symbol) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="d-flex justify-content-end mt-3">
      <button class="btn btn-primary" type="submit">Save <?= $selectedYear ?> Salaries</button>
    </div>
  </form>
</div>
