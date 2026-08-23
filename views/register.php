<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card p-4 mt-5">
      <h3 class="mb-3">Create your account</h3>
      <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-control" required minlength="3" value="<?= h($_POST['username'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required minlength="6">
        </div>
        <div class="mb-3">
          <label class="form-label">Confirm password</label>
          <input type="password" name="confirm" class="form-control" required minlength="6">
        </div>
        <button class="btn btn-primary w-100" type="submit">Sign up</button>
      </form>
      <p class="mt-3 mb-0">Already have an account? <a href="login.php">Log in</a></p>
    </div>
  </div>
</div>
