<?php
/** @var array|null $user */
/** @var string $pageTitle */
$pageTitle = $pageTitle ?? APP_NAME;
$activePage = $activePage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?> — <?= h(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background-color: #f4f6f8; }
  .navbar-brand { font-weight: 600; }
  .card { border: none; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
  .group-row td { background-color: #d9eaf7; font-weight: 600; }
  .total-row td { background-color: #e2f0d9; font-weight: 700; }
  th { background-color: #1f4e78; color: #fff; }
  .input-cell { color: #0000ff; background-color: #fffde7; }
  .negative { color: #b02a37; }
  .positive { color: #146c2e; }
  footer { color: #888; font-size: .85rem; }
</style>
</head>
<body>
<?php if ($user): ?>
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color:#1f4e78;">
  <div class="container-fluid">
    <a class="navbar-brand" href="dashboard.php"><?= h(APP_NAME) ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link <?= $activePage==='dashboard'?'active fw-bold':'' ?>" href="dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link <?= $activePage==='income'?'active fw-bold':'' ?>" href="income.php">Income</a></li>
        <li class="nav-item"><a class="nav-link <?= $activePage==='tracker'?'active fw-bold':'' ?>" href="tracker.php">Tracker</a></li>
        <li class="nav-item"><a class="nav-link <?= $activePage==='transfers'?'active fw-bold':'' ?>" href="transfers.php">Transfers</a></li>
        <li class="nav-item"><a class="nav-link <?= $activePage==='other_income'?'active fw-bold':'' ?>" href="other_income.php">Other Income</a></li>
        <li class="nav-item"><a class="nav-link <?= $activePage==='other_expenses'?'active fw-bold':'' ?>" href="other_expenses.php">Other Expenses</a></li>
        <li class="nav-item"><a class="nav-link <?= $activePage==='settings'?'active fw-bold':'' ?>" href="settings.php">Settings</a></li>
      </ul>
      <span class="navbar-text text-white me-3">Hi, <?= h($user['username']) ?></span>
      <a class="btn btn-sm btn-outline-light" href="logout.php">Log out</a>
    </div>
  </div>
</nav>
<?php endif; ?>
<div class="container-fluid py-4 px-4">
<?php if (!empty($flash ?? '')): ?>
  <div class="alert alert-<?= h($flashType ?? 'info') ?>"><?= h($flash) ?></div>
<?php endif; ?>
