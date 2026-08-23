<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card p-4 mt-5">
      <h3 class="mb-3">Log in</h3>
      <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-control" required value="<?= h($_POST['username'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button class="btn btn-primary w-100" type="submit">Log in</button>
      </form>
      <p class="mt-3 mb-0">No account yet? <a href="register.php">Create one</a></p>
    </div>
  </div>
</div>
