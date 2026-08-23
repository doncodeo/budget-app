
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h2 class="mb-0 fw-bold"><i class="bi bi-people text-primary me-2"></i>Household / Shared Budgets</h2>
    <p class="text-muted small mb-0">Collaborate with a spouse or partner on shared budgets.</p>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <!-- Active Budget Members -->
    <div class="card p-3 shadow-sm mb-4">
      <h5 class="fw-bold mb-3"><i class="bi bi-shield-check text-success me-2"></i>Active Budget: <?= h($activeBudget['name']) ?></h5>
      <p class="small text-muted mb-3">Members of this budget can view and log income, actuals, and transfers together.</p>

      <div class="table-responsive mb-3">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr><th>Member</th><th>Role</th><th>Joined</th><th class="text-end">Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($members as $m): ?>
              <tr>
                <td class="fw-semibold">
                  <i class="bi bi-person me-1"></i> <?= h($m['username']) ?>
                  <?php if ((int)($m['id'] ?? 0) === (int)$user['id']): ?>
                    <span class="badge bg-secondary-subtle text-secondary ms-1">You</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (($m['role'] ?? '') === 'owner'): ?>
                    <span class="badge bg-primary">Owner</span>
                  <?php else: ?>
                    <span class="badge bg-info">Member</span>
                  <?php endif; ?>
                </td>
                <td class="small text-muted"><?= h($m['created_at']) ?></td>
                <td class="text-end">
                  <?php if ($currentUserRole === 'owner' && (int)$m['id'] !== (int)$user['id']): ?>
                    <button class="btn btn-sm btn-outline-warning p-1 py-0 me-1" type="button" data-bs-toggle="collapse" data-bs-target="#resetPass-<?= $m['id'] ?>" title="Reset Member Password">
                      <i class="bi bi-key"></i>
                    </button>
                    <form method="post" class="d-inline" onsubmit="return confirm('Remove this member from budget?')">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="remove_member">
                      <input type="hidden" name="member_user_id" value="<?= $m['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger p-1 py-0" type="submit" title="Remove Member"><i class="bi bi-person-x"></i></button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
              <?php if ($currentUserRole === 'owner' && (int)$m['id'] !== (int)$user['id']): ?>
                <tr class="collapse bg-light" id="resetPass-<?= $m['id'] ?>">
                  <td colspan="4" class="p-3">
                    <form method="post" class="row g-2 align-items-center">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="reset_member_password">
                      <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                      <div class="col-sm-8">
                        <input type="password" name="new_password" class="form-control form-control-sm" placeholder="Enter new temp password for <?= h($m['username']) ?>" required minlength="8">
                      </div>
                      <div class="col-sm-4">
                        <button class="btn btn-warning btn-sm w-100" type="submit">Set New Password</button>
                      </div>
                    </form>
                  </td>
                </tr>
              <?php endif; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Invite Member Form -->
      <form method="post" class="border-top pt-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="invite_member">
        <label class="form-label small fw-bold">Invite Partner to this Budget</label>
        <div class="input-group input-group-sm">
          <input type="text" name="invite_username" class="form-control" placeholder="Enter existing username" required>
          <button class="btn btn-outline-primary" type="submit">Invite User</button>
        </div>
        <div class="form-text">User must already have an account created on this system.</div>
      </form>
    </div>

    <!-- Switch Active Budget -->
    <div class="card p-3 shadow-sm">
      <h5 class="fw-bold mb-3"><i class="bi bi-arrow-repeat me-2"></i>Switch Active Budget</h5>
      <div class="list-group list-group-flush">
        <?php foreach ($userBudgets as $b): ?>
          <div class="list-group-item d-flex justify-content-between align-items-center px-0">
            <div>
              <strong><?= h($b['name']) ?></strong>
              <div class="small text-muted">Currency: <?= h($b['currency_code'] ?? 'NGN') ?> (<?= h($b['currency_symbol'] ?? '₦') ?>) &bull; Role: <?= h($b['role'] ?? 'owner') ?></div>
            </div>
            <div class="d-flex align-items-center gap-2">
              <?php if ((int)$b['id'] === (int)$activeBudget['id']): ?>
                <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i> Active</span>
              <?php else: ?>
                <form method="post" class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="switch_budget">
                  <input type="hidden" name="budget_id" value="<?= $b['id'] ?>">
                  <button class="btn btn-sm btn-outline-secondary" type="submit">Switch</button>
                </form>
              <?php endif; ?>

              <?php if ($b['role'] === 'owner'): ?>
                <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to PERMANENTLY delete this budget and all its associated data?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_budget">
                  <input type="hidden" name="budget_id" value="<?= $b['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete Budget"><i class="bi bi-trash"></i></button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <!-- Manage / Delete Budget Years -->
    <div class="card p-3 shadow-sm mb-4">
      <h5 class="fw-bold mb-3"><i class="bi bi-calendar-x text-danger me-2"></i>Delete Budget Year</h5>
      <p class="small text-muted mb-2">Select a year to remove from this active budget along with all its logged data.</p>
      <form method="post" onsubmit="return confirm('Are you sure you want to delete this entire budget year and all logged data for it?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_year">
        <div class="input-group input-group-sm">
          <select name="year" class="form-select" required>
            <?php foreach ($budgetYears as $y): ?>
              <option value="<?= $y ?>"><?= $y ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash me-1"></i> Delete Year</button>
        </div>
      </form>
    </div>

    <!-- Create New Shared Budget -->
    <div class="card p-3 shadow-sm">
      <h5 class="fw-bold mb-3"><i class="bi bi-plus-circle text-primary me-2"></i>Create New Shared Budget</h5>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_budget">
        <div class="mb-2">
          <label class="form-label small">Budget Name</label>
          <input type="text" name="budget_name" class="form-control" placeholder="e.g. Family Budget 2025" required>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label small">Currency Code</label>
            <select name="currency_code" class="form-select">
              <option value="NGN">NGN (Naira)</option>
              <option value="USD">USD ($)</option>
              <option value="EUR">EUR (€)</option>
              <option value="GBP">GBP (£)</option>
              <option value="KES">KES (KSh)</option>
              <option value="GHS">GHS (GH₵)</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label small">Symbol</label>
            <input type="text" name="currency_symbol" class="form-control" value="₦" required>
          </div>
        </div>
        <button class="btn btn-primary w-100" type="submit">Create Budget</button>
      </form>
    </div>
  </div>
</div>
