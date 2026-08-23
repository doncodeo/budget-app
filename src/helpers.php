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

/** All active categories for a budget, ordered by sort_order. */
function get_categories(PDO $pdo, int $userId, ?int $budgetId = null): array
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE (budget_id = ? OR (budget_id IS NULL AND user_id = ?)) AND archived = 0 ORDER BY sort_order, id');
    $stmt->execute([$budgetId, $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    return (float)$category['fixed_amount'];
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

    $categories = get_categories($pdo, $userId, $budgetId);
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

    $bufferBase = max(0.0, $salary - $basePlanned);
    $totalBasePlanned = $basePlanned + ($hasBufferCategory ? $bufferBase : 0.0);

    $totalAllocations = get_total_allocations_for_month($pdo, $userId, $year, $month, $budgetId);
    $totalAllocated = $totalBasePlanned + $totalAllocations;

    $readyToAssign = $totalIncome - $totalAllocated;

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

    if ($category['name'] === 'Monthly Buffer') {
        if ($calculatedBufferBase === null) {
            $cats = get_categories($pdo, $userId, $budgetId);
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

    $actual = $category['is_other']
        ? get_other_expense_total($pdo, $userId, $year, $month, $budgetId)
        : get_actual($pdo, $userId, (int)$category['id'], $year, $month, $budgetId);
    $in = get_transfer_in($pdo, $userId, (int)$category['id'], $year, $month, $budgetId);
    $out = get_transfer_out($pdo, $userId, (int)$category['id'], $year, $month, $budgetId);
    $closing = $budget - $actual + $in - $out;

    return compact('budget', 'actual', 'in', 'out', 'closing', 'allocations');
}

/** Full tracker grid for a whole month: every category with its computed row. */
function tracker_month(PDO $pdo, int $userId, int $year, string $month, ?int $budgetId = null): array
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);
    $salary = get_salary($pdo, $userId, $year, $month, $budgetId);
    $categories = get_categories($pdo, $userId, $budgetId);

    $basePlannedNonBuffer = 0.0;
    foreach ($categories as $cat) {
        if ($cat['name'] !== 'Monthly Buffer' && !$cat['is_other']) {
            $basePlannedNonBuffer += category_budget($cat, $salary);
        }
    }
    $bufferBase = max(0.0, $salary - $basePlannedNonBuffer);

    $grouped = get_categories_grouped($pdo, $userId, $budgetId);
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
