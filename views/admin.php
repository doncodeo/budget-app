<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h2 class="mb-0 fw-bold"><i class="bi bi-shield-lock-fill text-danger me-2"></i>Platform Administration</h2>
    <p class="text-muted small mb-0">Super Admin Dashboard — Platform User Management, Password Reset & User Maintenance</p>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card p-3 shadow-sm border-start border-4 border-primary">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <span class="text-muted small text-uppercase fw-semibold">Total Registered Users</span>
          <h3 class="fw-bold mb-0 mt-1"><?= count($users) ?></h3>
        </div>
        <div class="fs-1 text-primary opacity-50"><i class="bi bi-people-fill"></i></div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card p-3 shadow-sm border-start border-4 border-warning">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <span class="text-muted small text-uppercase fw-semibold">Super Admin Account</span>
          <h3 class="fw-bold mb-0 mt-1 text-warning">
            <?php
              $admins = array_filter($users, fn($u) => !empty($u['is_super_admin']));
              $adminUser = reset($admins);
              echo h($adminUser['username'] ?? 'None');
            ?>
          </h3>
        </div>
        <div class="fs-1 text-warning opacity-50"><i class="bi bi-key-fill"></i></div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header bg-white py-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-list-ul me-2"></i>Platform User Registry</h5>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3">ID</th>
            <th>Username</th>
            <th>Role</th>
            <th>Active Budget</th>
            <th>Household Members</th>
            <th>Registered Date</th>
            <th class="text-end pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td class="ps-3 fw-bold text-muted">#<?= (int)$u['id'] ?></td>
            <td class="fw-semibold text-dark">
              <i class="bi bi-person-circle me-1 text-secondary"></i> <?= h($u['username']) ?>
            </td>
            <td>
              <?php if (!empty($u['is_super_admin'])): ?>
                <span class="badge bg-danger"><i class="bi bi-shield-fill-check me-1"></i> Super Admin</span>
              <?php else: ?>
                <span class="badge bg-secondary"><i class="bi bi-person me-1"></i> User</span>
              <?php endif; ?>
            </td>
            <td class="small text-muted">
              <?= h($u['budget_name'] ?? 'No Budget') ?>
            </td>
            <td>
              <span class="badge bg-info text-dark">
                <i class="bi bi-people me-1"></i> <?= (int)$u['household_members_count'] ?> member(s)
              </span>
            </td>
            <td class="small text-muted">
              <?= h(date('M d, Y', strtotime($u['created_at']))) ?>
            </td>
            <td class="text-end pe-3">
              <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resetPasswordModal<?= (int)$u['id'] ?>">
                  <i class="bi bi-key me-1"></i> Reset Password
                </button>
                <?php if (empty($u['is_super_admin']) && (int)$u['id'] !== (int)$user['id']): ?>
                  <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal<?= (int)$u['id'] ?>">
                    <i class="bi bi-trash me-1"></i> Delete
                  </button>
                <?php endif; ?>
              </div>

              <!-- Reset Password Modal -->
              <div class="modal fade text-start" id="resetPasswordModal<?= (int)$u['id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <div class="modal-content">
                    <form method="post" action="admin.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="reset_password">
                      <input type="hidden" name="target_user_id" value="<?= (int)$u['id'] ?>">
                      <div class="modal-header">
                        <h5 class="modal-title fw-bold text-warning"><i class="bi bi-key me-2"></i>Reset Password for <?= h($u['username']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <p class="small text-muted mb-3">
                          Enter a new password for user <strong><?= h($u['username']) ?></strong>. They will immediately be able to log in with this new password.
                        </p>
                        <div class="mb-3">
                          <label class="form-label fw-semibold">New Password</label>
                          <input type="password" name="new_password" class="form-control" placeholder="At least 6 characters" required minlength="6">
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning"><i class="bi bi-check-circle me-1"></i> Save New Password</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>

              <?php if (empty($u['is_super_admin']) && (int)$u['id'] !== (int)$user['id']): ?>
              <!-- Delete User Modal -->
              <div class="modal fade text-start" id="deleteUserModal<?= (int)$u['id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <div class="modal-content">
                    <form method="post" action="admin.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_user">
                      <input type="hidden" name="target_user_id" value="<?= (int)$u['id'] ?>">
                      <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>Delete User <?= h($u['username']) ?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <p class="small text-muted mb-0">
                          Are you sure you want to permanently delete user account <strong><?= h($u['username']) ?></strong> (#<?= (int)$u['id'] ?>) from the platform? This action cannot be undone.
                        </p>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i> Permanently Delete User</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
              <?php endif; ?>

            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
