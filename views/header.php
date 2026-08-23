<?php
/** @var array|null $user */
/** @var string $pageTitle */
/** @var string $activePage */
/** @var array $globalYears */
/** @var array $globalMonths */
/** @var int $globalSelectedYear */
/** @var string $globalSelectedMonth */
/** @var array $currentQueryParams */

$pageTitle = $pageTitle ?? APP_NAME;
$activePage = $activePage ?? '';
$userTheme = $user['theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= h($userTheme) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?> — <?= h(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
  :root {
    --bg-app: #f4f6f8;
    --card-shadow: 0 2px 8px rgba(0,0,0,.06);
    --nav-bg: #1f4e78;
    --color-giving: #6f42c1;
    --color-transport: #0d6efd;
    --color-household: #fd7e14;
    --color-utilities: #0dcaf0;
    --color-savings: #198754;
    --color-flexible: #ffc107;
    --color-other: #6c757d;
  }
  [data-bs-theme="dark"] {
    --bg-app: #121212;
    --card-shadow: 0 2px 8px rgba(0,0,0,.3);
    --nav-bg: #152938;
  }
  body { background-color: var(--bg-app); min-height: 100vh; font-family: system-ui, -apple-system, sans-serif; }
  .navbar-brand { font-weight: 700; letter-spacing: -0.5px; }
  .card { border: none; border-radius: 10px; box-shadow: var(--card-shadow); transition: transform 0.15s ease; }
  .group-row td { background-color: rgba(31, 78, 120, 0.08); font-weight: 600; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; }
  .total-row td { background-color: rgba(25, 135, 84, 0.12); font-weight: 700; }
  th { background-color: var(--nav-bg); color: #fff; }
  .input-cell { font-weight: 600; }
  .negative { color: #dc3545; font-weight: 600; }
  .positive { color: #198754; font-weight: 600; }
  .badge-group { font-size: 0.75rem; padding: 0.35em 0.65em; border-radius: 6px; }
  footer { color: #888; font-size: .85rem; }
  .progress-budget { height: 8px; border-radius: 4px; }
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top shadow-sm" style="background-color: var(--nav-bg);">
  <div class="container-fluid px-4">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= !empty($user) ? 'dashboard.php' : 'about.php' ?>">
      <i class="bi bi-wallet2 fs-4"></i> <?= h(APP_NAME) ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <?php if (!empty($user)): ?>
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link <?= $activePage==='dashboard'?'active fw-bold':'' ?>" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link <?= $activePage==='income'?'active fw-bold':'' ?>" href="income.php"><i class="bi bi-cash-stack"></i> Income</a></li>
        <li class="nav-item"><a class="nav-link <?= $activePage==='tracker'?'active fw-bold':'' ?>" href="tracker.php"><i class="bi bi-table"></i> Tracker</a></li>
        <li class="nav-item"><a class="nav-link <?= $activePage==='transfers'?'active fw-bold':'' ?>" href="transfers.php"><i class="bi bi-arrow-left-right"></i> Transfers</a></li>
        <li class="nav-item"><a class="nav-link <?= $activePage==='other_income'?'active fw-bold':'' ?>" href="other_income.php"><i class="bi bi-box-arrow-in-down"></i> Other Income</a></li>
        <li class="nav-item"><a class="nav-link <?= $activePage==='other_expenses'?'active fw-bold':'' ?>" href="other_expenses.php"><i class="bi bi-box-arrow-up"></i> Other Expenses</a></li>
        <li class="nav-item"><a class="nav-link <?= $activePage==='household'?'active fw-bold':'' ?>" href="household.php"><i class="bi bi-people"></i> Household</a></li>
        <li class="nav-item"><a class="nav-link <?= $activePage==='settings'?'active fw-bold':'' ?>" href="settings.php"><i class="bi bi-gear"></i> Settings</a></li>
        <li class="nav-item"><a class="nav-link <?= $activePage==='about'?'active fw-bold':'' ?>" href="about.php"><i class="bi bi-question-circle"></i> About</a></li>
      </ul>

      <!-- Persistent Year & Month Selector -->
      <?php if (!empty($globalYears)): ?>
      <form method="get" class="d-flex align-items-center me-3" title="Active Budget Period">
        <?php foreach ($currentQueryParams as $k => $v): ?>
          <?php if (!in_array($k, ['year', 'month'], true)): ?>
            <input type="hidden" name="<?= h($k) ?>" value="<?= h((string)$v) ?>">
          <?php endif; ?>
        <?php endforeach; ?>
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-white bg-opacity-10 text-light border-secondary-subtle small fw-medium">
            <i class="bi bi-calendar3 me-1"></i> Period
          </span>
          <select name="year" class="form-select form-select-sm bg-white bg-opacity-10 text-light border-secondary-subtle fw-semibold" aria-label="Select Budget Year" onchange="this.form.submit()">
            <?php foreach ($globalYears as $y): ?>
              <option value="<?= $y ?>" <?= $y === $globalSelectedYear ? 'selected' : '' ?> class="bg-dark text-white"><?= $y ?></option>
            <?php endforeach; ?>
          </select>
          <select name="month" class="form-select form-select-sm bg-white bg-opacity-10 text-light border-secondary-subtle fw-semibold" aria-label="Select Budget Month" onchange="this.form.submit()">
            <?php foreach ($globalMonths as $m): ?>
              <option value="<?= h($m) ?>" <?= $m === $globalSelectedMonth ? 'selected' : '' ?> class="bg-dark text-white"><?= h($m) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>
      <?php endif; ?>

      <div class="d-flex align-items-center gap-2">
        <a href="theme_toggle.php" class="btn btn-sm btn-outline-light" title="Toggle Light/Dark Theme">
          <i class="bi bi-moon-stars"></i>
        </a>
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-light dropdown-toggle d-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i> <?= h($user['username']) ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow">
            <li><h6 class="dropdown-header">Budget: <?= h($user['budget_name'] ?? 'Personal') ?> (<?= h($user['currency_symbol'] ?? '₦') ?>)</h6></li>
            <li><a class="dropdown-item" href="household.php"><i class="bi bi-people me-2"></i> Manage Household</a></li>
            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Log out</a></li>
          </ul>
        </div>
      </div>
      <?php else: ?>
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link <?= $activePage==='about'?'active fw-bold':'' ?>" href="about.php"><i class="bi bi-question-circle"></i> About & Tutorial</a></li>
      </ul>
      <div class="d-flex align-items-center gap-2">
        <a href="login.php" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-in-right me-1"></i> Log In</a>
        <a href="register.php" class="btn btn-sm btn-light text-primary fw-semibold"><i class="bi bi-person-plus me-1"></i> Register</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</nav>

<div class="container-fluid py-4 px-4">

<?php if (!empty($flash)): ?>
  <div class="alert alert-<?= h($flashType ?? 'info') ?> alert-dismissible fade show shadow-sm" role="alert">
    <?= h($flash) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
