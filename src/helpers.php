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

/**
 * Full tracker row for one category/month:
 * ['budget'=>, 'actual'=>, 'in'=>, 'out'=>, 'closing'=>]
 */
function tracker_row(PDO $pdo, int $userId, array $category, int $year, string $month, float $salary, ?int $budgetId = null): array
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);
    $budget = category_budget($category, $salary);
    $actual = $category['is_other']
        ? get_other_expense_total($pdo, $userId, $year, $month, $budgetId)
        : get_actual($pdo, $userId, (int)$category['id'], $year, $month, $budgetId);
    $in = get_transfer_in($pdo, $userId, (int)$category['id'], $year, $month, $budgetId);
    $out = get_transfer_out($pdo, $userId, (int)$category['id'], $year, $month, $budgetId);
    $closing = $budget - $actual + $in - $out;

    return compact('budget', 'actual', 'in', 'out', 'closing');
}

/** Full tracker grid for a whole month: every category with its computed row. */
function tracker_month(PDO $pdo, int $userId, int $year, string $month, ?int $budgetId = null): array
{
    $budgetId = $budgetId ?? get_active_budget_id($pdo, $userId);
    $salary = get_salary($pdo, $userId, $year, $month, $budgetId);
    $grouped = get_categories_grouped($pdo, $userId, $budgetId);
    $result = [];
    $totals = ['budget' => 0.0, 'actual' => 0.0, 'in' => 0.0, 'out' => 0.0, 'closing' => 0.0];

    foreach ($grouped as $groupName => $cats) {
        $rows = [];
        foreach ($cats as $cat) {
            $row = tracker_row($pdo, $userId, $cat, $year, $month, $salary, $budgetId);
            $row['category'] = $cat;
            $rows[] = $row;
            foreach (['budget', 'actual', 'in', 'out', 'closing'] as $k) {
                $totals[$k] += $row[$k];
            }
        }
        $result[$groupName] = $rows;
    }

    return ['groups' => $result, 'totals' => $totals, 'salary' => $salary];
}
