
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h2 class="mb-0 fw-bold"><i class="bi bi-table text-primary me-2"></i>Budget Tracker</h2>
    <p class="text-muted small mb-0">Overview grid for <?= h($selectedMonth) ?> <?= $selectedYear ?> &bull; Click actual values to edit inline.</p>
  </div>
  <div>
    <a href="csv_export.php?type=tracker&year=<?= $selectedYear ?>&month=<?= h($selectedMonth) ?>" class="btn btn-sm btn-outline-success">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
    </a>
  </div>
</div>

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
              <td class="text-end fw-semibold"><?= fmt_money($r['budget'], $symbol) ?></td>
              <td class="text-end input-cell">
                <?php if ($r['category']['is_other']): ?>
                  <span class="text-muted" title="Pulls automatically from Other Expenses log"><?= fmt_money($r['actual'], $symbol) ?></span>
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
            closingEl.textContent = data.row.closing_formatted;
            closingEl.className = `text-end fw-bold closing-cell-${catId} ${data.row.is_positive ? 'positive' : 'negative'}`;
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
