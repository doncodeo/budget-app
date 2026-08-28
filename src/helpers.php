<?php
declare(strict_types=1);

function fmt_money(?float $amount, ?string $symbol = '₦'): string
{
    $val = (float)($amount ?? 0.0);
    $sym = $symbol ?: '₦';
    return $sym . number_format($val, 0);
}

function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES);
}

function get_active_budget_id(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT active_budget_id FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $budgetId = $stmt->fetchColumn();
    if ($budgetId) {
        return (int)$budgetId;
    }
    // Fallback: get first budget where user is a member
    $stmt = $pdo->prepare('SELECT budget_id FROM budget_members WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $budgetId = $stmt->fetchColumn();
    if ($budgetId) {
        return (int)$budgetId;
    }
    return 0;
}

/** All active default category templates for a budget, ordered by sort_order. */
function get_categories(PDO $pdo, int $userId, ?int $budgetId = null): array
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE (budget_id = ? OR (budget_id IS NULL AND user_id = ?)) AND archived = 0 ORDER BY sort_order, id');
    $stmt->execute([$budgetId, $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Ensure month snapshot exists in monthly_category_budgets and budget_periods. */
function ensure_month_snapshot(PDO $pdo, int $userId, int $year, string $month, ?int $budgetId = null): void
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);

    // 1. Ensure period row exists
    $pStmt = $pdo->prepare('SELECT id FROM budget_periods WHERE budget_id = ? AND year = ? AND month = ?');
    $pStmt->execute([$budgetId, $year, $month]);
    if (!$pStmt->fetch()) {
        $insP = $pdo->prepare('INSERT INTO budget_periods (budget_id, year, month, status) VALUES (?, ?, ?, "open")');
        $insP->execute([$budgetId, $year, $month]);
    }

    // 2. Ensure category snapshots exist for this month
    $cStmt = $pdo->prepare('SELECT COUNT(*) FROM monthly_category_budgets WHERE budget_id = ? AND year = ? AND month = ?');
    $cStmt->execute([$budgetId, $year, $month]);
    $count = (int)$cStmt->fetchColumn();

    if ($count === 0) {
        $activeCategories = get_categories($pdo, $userId, $budgetId);
        $insM = $pdo->prepare('
            INSERT INTO monthly_category_budgets (
                budget_id, user_id, year, month, category_id, category_name, group_name, basis, fixed_amount, percent, is_other, sort_order, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        foreach ($activeCategories as $cat) {
            $insM->execute([
                $budgetId,
                $userId,
                $year,
                $month,
                (int)$cat['id'],
                $cat['name'],
                $cat['group_name'],
                $cat['basis'],
                $cat['fixed_amount'],
                $cat['percent'],
                (int)$cat['is_other'],
                (int)$cat['sort_order'],
                $cat['notes'] ?? ''
            ]);
        }
    }
}

/** Fetch month-specific category snapshot. */
function get_month_categories(PDO $pdo, int $userId, int $year, string $month, ?int $budgetId = null): array
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);
    ensure_month_snapshot($pdo, $userId, $year, $month, $budgetId);

    $stmt = $pdo->prepare('
        SELECT
            id AS snapshot_id,
            category_id AS id,
            category_id,
            budget_id,
            user_id,
            year,
            month,
            category_name AS name,
            group_name,
            basis,
            fixed_amount,
            percent,
            is_other,
            sort_order,
            notes
        FROM monthly_category_budgets
        WHERE budget_id = ? AND year = ? AND month = ?
        ORDER BY sort_order, category_id
    ');
    $stmt->execute([$budgetId, $year, $month]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Check if a budget period is closed. */
function is_period_closed(PDO $pdo, int $userId, int $year, string $month, ?int $budgetId = null): bool
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);
    $stmt = $pdo->prepare('SELECT status FROM budget_periods WHERE budget_id = ? AND year = ? AND month = ?');
    $stmt->execute([$budgetId, $year, $month]);
    $status = $stmt->fetchColumn();
    return $status === 'closed';
}

/** Toggle period status (open vs closed). */
function set_period_status(PDO $pdo, int $userId, int $year, string $month, string $status, ?int $budgetId = null): void
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);
    ensure_month_snapshot($pdo, $userId, $year, $month, $budgetId);
    $closedAt = $status === 'closed' ? date('Y-m-d H:i:s') : null;
    $closedBy = $status === 'closed' ? $userId : null;

    $stmt = $pdo->prepare('UPDATE budget_periods SET status = ?, closed_at = ?, closed_by = ? WHERE budget_id = ? AND year = ? AND month = ?');
    $stmt->execute([$status, $closedAt, $closedBy, $budgetId, $year, $month]);
}

/** Synchronize category template changes from `categories` to all OPEN `monthly_category_budgets` periods. */
function sync_open_month_category_snapshots(PDO $pdo, int $userId, ?int $budgetId = null): void
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);
    if (!$budgetId) return;

    // Find all open periods for this budget
    $stmt = $pdo->prepare('SELECT year, month FROM budget_periods WHERE budget_id = ? AND status != "closed"');
    $stmt->execute([$budgetId]);
    $openPeriods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $activeCategories = get_categories($pdo, $userId, $budgetId);
    $activeCatIds = array_map('intval', array_column($activeCategories, 'id'));

    foreach ($openPeriods as $period) {
        $year = (int)$period['year'];
        $month = $period['month'];

        foreach ($activeCategories as $cat) {
            $catId = (int)$cat['id'];
            // Check if snapshot row exists
            $check = $pdo->prepare('SELECT id FROM monthly_category_budgets WHERE budget_id = ? AND year = ? AND month = ? AND category_id = ?');
            $check->execute([$budgetId, $year, $month, $catId]);
            if ($check->fetchColumn()) {
                $upd = $pdo->prepare('
                    UPDATE monthly_category_budgets
                    SET category_name = ?, group_name = ?, basis = ?, fixed_amount = ?, percent = ?, is_other = ?, sort_order = ?, notes = ?
                    WHERE budget_id = ? AND year = ? AND month = ? AND category_id = ?
                ');
                $upd->execute([
                    $cat['name'],
                    $cat['group_name'],
                    $cat['basis'],
                    $cat['fixed_amount'],
                    $cat['percent'],
                    (int)$cat['is_other'],
                    (int)$cat['sort_order'],
                    $cat['notes'] ?? '',
                    $budgetId,
                    $year,
                    $month,
                    $catId
                ]);
            } else {
                $ins = $pdo->prepare('
                    INSERT INTO monthly_category_budgets (
                        budget_id, user_id, year, month, category_id, category_name, group_name, basis, fixed_amount, percent, is_other, sort_order, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $ins->execute([
                    $budgetId,
                    $userId,
                    $year,
                    $month,
                    $catId,
                    $cat['name'],
                    $cat['group_name'],
                    $cat['basis'],
                    $cat['fixed_amount'],
                    $cat['percent'],
                    (int)$cat['is_other'],
                    (int)$cat['sort_order'],
                    $cat['notes'] ?? ''
                ]);
            }
        }

        // Remove archived categories from open periods if no transaction history exists for them
        if (!empty($activeCatIds)) {
            $inClause = implode(',', array_fill(0, count($activeCatIds), '?'));
            $delParams = array_merge([$budgetId, $year, $month], $activeCatIds);
            $del = $pdo->prepare("
                DELETE FROM monthly_category_budgets
                WHERE budget_id = ? AND year = ? AND month = ?
                AND category_id NOT IN ($inClause)
            ");
            $del->execute($delParams);
        }
    }
}

/** Update category budget rule for a specific month without altering default template or other months. */
function update_month_category_rule(PDO $pdo, int $userId, int $year, string $month, int $categoryId, string $basis, ?float $fixedAmount, ?float $percent, ?int $budgetId = null): ?string
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);

    if (is_period_closed($pdo, $userId, $year, $month, $budgetId)) {
        return "Period $month $year is closed and cannot be modified.";
    }

    ensure_month_snapshot($pdo, $userId, $year, $month, $budgetId);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('
            UPDATE monthly_category_budgets
            SET basis = ?, fixed_amount = ?, percent = ?
            WHERE budget_id = ? AND year = ? AND month = ? AND category_id = ?
        ');
        $stmt->execute([$basis, $fixedAmount, $percent, $budgetId, $year, $month, $categoryId]);

        $summary = calculate_budget_summary($pdo, $userId, $year, $month, $budgetId);
        if ($summary['budget_status'] === 'Over-allocated') {
            throw new \Exception(sprintf(
                'This change would exceed available income by %s in %s %d.',
                fmt_money($summary['over_allocated_amount']),
                $month,
                $year
            ));
        }

        $pdo->commit();
        return null;
    } catch (\Throwable $e) {
        $pdo->rollBack();
        return $e->getMessage();
    }
}

/** Categories grouped by group_name, preserving sort_order. */
function get_categories_grouped(PDO $pdo, int $userId, ?int $budgetId = null): array
{
    $groups = [];
    foreach (get_categories($pdo, $userId, $budgetId) as $cat) {
        $groups[$cat['group_name']][] = $cat;
    }
    return $groups;
}

/** All years set up for a budget, descending. */
function get_years(PDO $pdo, int $userId, ?int $budgetId = null): array
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);
    $stmt = $pdo->prepare('SELECT DISTINCT year FROM years WHERE (budget_id = ? OR (budget_id IS NULL AND user_id = ?)) ORDER BY year DESC');
    $stmt->execute([$budgetId, $userId]);
    return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'year'));
}

function get_salary(PDO $pdo, int $userId, int $year, string $month, ?int $budgetId = null): float
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);
    $stmt = $pdo->prepare('SELECT salary FROM income WHERE (budget_id = ? OR (budget_id IS NULL AND user_id = ?)) AND year = ? AND month = ?');
    $stmt->execute([$budgetId, $userId, $year, $month]);
    $v = $stmt->fetchColumn();
    return $v === false ? 0.0 : (float)$v;
}

function get_other_income_total(PDO $pdo, int $userId, int $year, string $month, ?int $budgetId = null): float
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM other_income WHERE (budget_id = ? OR (budget_id IS NULL AND user_id = ?)) AND year = ? AND month = ?');
    $stmt->execute([$budgetId, $userId, $year, $month]);
    return (float)$stmt->fetchColumn();
}

function get_other_expense_total(PDO $pdo, int $userId, int $year, string $month, ?int $budgetId = null): float
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM other_expenses WHERE (budget_id = ? OR (budget_id IS NULL AND user_id = ?)) AND year = ? AND month = ?');
    $stmt->execute([$budgetId, $userId, $year, $month]);
    return (float)$stmt->fetchColumn();
}

/** Budget amount for one category in one month, per its basis. */
function category_budget(array $category, float $salary): float
{
    if ($category['basis'] === 'percent') {
        return round((float)$category['percent'] * $salary, 2);
    }
    return round((float)$category['fixed_amount'], 2);
}

function get_actual(PDO $pdo, int $userId, int $categoryId, int $year, string $month, ?int $budgetId = null): float
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);
    $stmt = $pdo->prepare('SELECT actual FROM tracker_actuals WHERE (budget_id = ? OR (budget_id IS NULL AND user_id = ?)) AND category_id = ? AND year = ? AND month = ?');
    $stmt->execute([$budgetId, $userId, $categoryId, $year, $month]);
    $v = $stmt->fetchColumn();
    return $v === false ? 0.0 : (float)$v;
}

function set_actual(PDO $pdo, int $userId, int $categoryId, int $year, string $month, float $value, ?int $budgetId = null): void
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);
    // Check if exists
    $stmt = $pdo->prepare('SELECT id FROM tracker_actuals WHERE budget_id = ? AND category_id = ? AND year = ? AND month = ?');
    $stmt->execute([$budgetId, $categoryId, $year, $month]);
    $id = $stmt->fetchColumn();

    if ($id) {
        $stmt = $pdo->prepare('UPDATE tracker_actuals SET actual = ? WHERE id = ?');
        $stmt->execute([$value, $id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO tracker_actuals (budget_id, user_id, category_id, year, month, actual) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$budgetId, $userId, $categoryId, $year, $month, $value]);
    }
}

function get_transfer_in(PDO $pdo, int $userId, int $categoryId, int $year, string $month, ?int $budgetId = null): float
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM transfers WHERE (budget_id = ? OR (budget_id IS NULL AND user_id = ?)) AND to_category_id = ? AND year = ? AND month = ?');
    $stmt->execute([$budgetId, $userId, $categoryId, $year, $month]);
    return (float)$stmt->fetchColumn();
}

function get_transfer_out(PDO $pdo, int $userId, int $categoryId, int $year, string $month, ?int $budgetId = null): float
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM transfers WHERE (budget_id = ? OR (budget_id IS NULL AND user_id = ?)) AND from_category_id = ? AND year = ? AND month = ?');
    $stmt->execute([$budgetId, $userId, $categoryId, $year, $month]);
    return (float)$stmt->fetchColumn();
}

/** Total income allocations for a specific category in a specific month. */
function get_category_allocations_total(PDO $pdo, int $userId, int $categoryId, int $year, string $month, ?int $budgetId = null): float
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM income_allocations WHERE (budget_id = ? OR (budget_id IS NULL AND user_id = ?)) AND category_id = ? AND year = ? AND month = ?');
    $stmt->execute([$budgetId, $userId, $categoryId, $year, $month]);
    return (float)$stmt->fetchColumn();
}

/** Total income allocations for all categories in a specific month. */
function get_total_allocations_for_month(PDO $pdo, int $userId, int $year, string $month, ?int $budgetId = null): float
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM income_allocations WHERE (budget_id = ? OR (budget_id IS NULL AND user_id = ?)) AND year = ? AND month = ?');
    $stmt->execute([$budgetId, $userId, $year, $month]);
    return (float)$stmt->fetchColumn();
}

/** Get list of allocation records for a month (for audit trail). */
function get_allocations_for_month(PDO $pdo, int $userId, int $year, string $month, ?int $budgetId = null): array
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);
    $stmt = $pdo->prepare('
        SELECT a.*, c.name AS category_name, u.username AS created_by_username
        FROM income_allocations a
        JOIN categories c ON c.id = a.category_id
        JOIN users u ON u.id = a.user_id
        WHERE (a.budget_id = ? OR (a.budget_id IS NULL AND a.user_id = ?)) AND a.year = ? AND a.month = ?
        ORDER BY a.id DESC
    ');
    $stmt->execute([$budgetId, $userId, $year, $month]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Calculate complete monthly budget summary. */
function calculate_budget_summary(PDO $pdo, int $userId, int $year, string $month, ?int $budgetId = null): array
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);
    $salary = get_salary($pdo, $userId, $year, $month, $budgetId);
    $otherIncome = get_other_income_total($pdo, $userId, $year, $month, $budgetId);
    $totalIncome = $salary + $otherIncome;

    $categories = get_month_categories($pdo, $userId, $year, $month, $budgetId);
    $basePlanned = 0.0;
    $hasBufferCategory = false;

    if ($totalIncome > 0) {
        foreach ($categories as $cat) {
            if ($cat['name'] === 'Monthly Buffer') {
                $hasBufferCategory = true;
                continue;
            }
            if (!$cat['is_other']) {
                $basePlanned += category_budget($cat, $salary);
            }
        }
    } else {
        foreach ($categories as $cat) {
            if ($cat['name'] === 'Monthly Buffer') {
                $hasBufferCategory = true;
            }
        }
    }

    $bufferBase = round(max(0.0, $salary - $basePlanned), 2);
    $totalBasePlanned = round($basePlanned + ($hasBufferCategory ? $bufferBase : 0.0), 2);

    $totalAllocations = round(get_total_allocations_for_month($pdo, $userId, $year, $month, $budgetId), 2);
    $totalAllocated = round($totalBasePlanned + $totalAllocations, 2);

    $readyToAssign = round($totalIncome - $totalAllocated, 2);
    if (abs($readyToAssign) < 0.001) {
        $readyToAssign = 0.0;
    }

    $status = 'Balanced';
    $overAllocatedAmount = 0.0;
    if ($readyToAssign > 0.001) {
        $status = 'Under-allocated';
    } elseif ($readyToAssign < -0.001) {
        $status = 'Over-allocated';
        $overAllocatedAmount = abs($readyToAssign);
    }

    return [
        'salary' => $salary,
        'other_income' => $otherIncome,
        'total_income' => $totalIncome,
        'base_planned' => $basePlanned,
        'buffer_base' => $bufferBase,
        'has_buffer' => $hasBufferCategory,
        'total_base_planned' => $totalBasePlanned,
        'total_allocations' => $totalAllocations,
        'total_allocated' => $totalAllocated,
        'ready_to_assign' => round($readyToAssign, 2),
        'budget_status' => $status,
        'over_allocated_amount' => round($overAllocatedAmount, 2),
    ];
}

/**
 * Atomically record allocations from map of category_id => amount.
 * Returns null on success or error string on validation failure.
 */
function save_income_allocations(PDO $pdo, int $userId, int $year, string $month, array $allocationsMap, string $sourceType = 'Other Income', ?int $budgetId = null): ?string
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);

    $allocsToSave = [];
    $totalProposed = 0.0;

    $categories = get_categories($pdo, $userId, $budgetId);
    $validCatIds = array_map('intval', array_column($categories, 'id'));

    foreach ($allocationsMap as $catIdStr => $amount) {
        $catId = (int)$catIdStr;
        if ($amount === '' || $amount === null) {
            continue;
        }
        if (!is_numeric($amount)) {
            return 'Please enter valid numerical amounts for category allocations.';
        }
        $amt = (float)$amount;
        if ($amt < 0) {
            return 'Allocation amounts cannot be negative.';
        }
        if ($amt > 0) {
            if (!in_array($catId, $validCatIds, true)) {
                return 'Invalid budget category selected.';
            }
            $allocsToSave[$catId] = $amt;
            $totalProposed += $amt;
        }
    }

    if ($totalProposed <= 0) {
        return 'Please enter an amount to allocate.';
    }

    $summary = calculate_budget_summary($pdo, $userId, $year, $month, $budgetId);
    $available = $summary['ready_to_assign'];

    if ($totalProposed > $available + 0.001) {
        return sprintf(
            'Cannot allocate %s. Only %s is available to assign.',
            fmt_money($totalProposed),
            fmt_money(max(0.0, $available))
        );
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('
            INSERT INTO income_allocations (budget_id, user_id, year, month, category_id, amount, source_type)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        foreach ($allocsToSave as $catId => $amt) {
            $stmt->execute([$budgetId, $userId, $year, $month, $catId, $amt, $sourceType]);
        }
        $pdo->commit();
        return null;
    } catch (\Throwable $e) {
        $pdo->rollBack();
        return 'Failed to save income allocation: ' . $e->getMessage();
    }
}

/**
 * Full tracker row for one category/month:
 * ['budget'=>, 'actual'=>, 'in'=>, 'out'=>, 'closing'=>, 'allocations'=>]
 */
function tracker_row(PDO $pdo, int $userId, array $category, int $year, string $month, float $salary, ?int $budgetId = null, ?float $calculatedBufferBase = null): array
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);
    $otherIncome = get_other_income_total($pdo, $userId, $year, $month, $budgetId);
    $totalIncome = $salary + $otherIncome;

    if ($totalIncome <= 0) {
        $baseBudget = 0.0;
    } elseif ($category['name'] === 'Monthly Buffer') {
        if ($calculatedBufferBase === null) {
            $cats = get_month_categories($pdo, $userId, $year, $month, $budgetId);
            $nonBuf = 0.0;
            foreach ($cats as $c) {
                if ($c['name'] !== 'Monthly Buffer' && !$c['is_other']) {
                    $nonBuf += category_budget($c, $salary);
                }
            }
            $calculatedBufferBase = max(0.0, $salary - $nonBuf);
        }
        $baseBudget = $calculatedBufferBase;
    } else {
        $baseBudget = category_budget($category, $salary);
    }

    $allocations = get_category_allocations_total($pdo, $userId, (int)$category['id'], $year, $month, $budgetId);
    $budget = $baseBudget + $allocations;

    $actual = round($category['is_other']
        ? get_other_expense_total($pdo, $userId, $year, $month, $budgetId)
        : get_actual($pdo, $userId, (int)$category['id'], $year, $month, $budgetId), 2);
    $in = round(get_transfer_in($pdo, $userId, (int)$category['id'], $year, $month, $budgetId), 2);
    $out = round(get_transfer_out($pdo, $userId, (int)$category['id'], $year, $month, $budgetId), 2);
    $closing = round($budget - $actual + $in - $out, 2);
    if (abs($closing) < 0.001) {
        $closing = 0.0;
    }

    return compact('budget', 'actual', 'in', 'out', 'closing', 'allocations');
}

/** Full tracker grid for a whole month: every category with its computed row. */
function tracker_month(PDO $pdo, int $userId, int $year, string $month, ?int $budgetId = null): array
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);
    $salary = get_salary($pdo, $userId, $year, $month, $budgetId);
    $categories = get_month_categories($pdo, $userId, $year, $month, $budgetId);

    $basePlannedNonBuffer = 0.0;
    if ($salary + get_other_income_total($pdo, $userId, $year, $month, $budgetId) > 0) {
        foreach ($categories as $cat) {
            if ($cat['name'] !== 'Monthly Buffer' && !$cat['is_other']) {
                $basePlannedNonBuffer += category_budget($cat, $salary);
            }
        }
    }
    $bufferBase = max(0.0, $salary - $basePlannedNonBuffer);

    $grouped = [];
    foreach ($categories as $cat) {
        $grouped[$cat['group_name']][] = $cat;
    }
    $groupColors = \App\BudgetService::getCategoryGroupColors();
    $result = [];
    $totals = ['budget' => 0.0, 'actual' => 0.0, 'in' => 0.0, 'out' => 0.0, 'closing' => 0.0];

    foreach ($grouped as $groupName => $cats) {
        $rows = [];
        foreach ($cats as $cat) {
            $row = tracker_row($pdo, $userId, $cat, $year, $month, $salary, $budgetId, $bufferBase);
            $row['category'] = $cat;
            $row['group_color'] = $groupColors[$groupName] ?? '#1f4e78';
            $rows[] = $row;
            foreach (['budget', 'actual', 'in', 'out', 'closing'] as $k) {
                $totals[$k] += $row[$k];
            }
        }
        $result[$groupName] = $rows;
    }

    return ['groups' => $result, 'totals' => $totals, 'salary' => $salary];
}
