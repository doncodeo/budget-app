<?php
declare(strict_types=1);

function fmt_money(float $amount, string $symbol = '₦'): string
{
    return $symbol . number_format($amount, 0);
}

function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES);
}

/** All active categories for a user, ordered by sort_order. */
function get_categories(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE user_id = ? AND archived = 0 ORDER BY sort_order, id');
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Categories grouped by group_name, preserving sort_order. */
function get_categories_grouped(PDO $pdo, int $userId): array
{
    $groups = [];
    foreach (get_categories($pdo, $userId) as $cat) {
        $groups[$cat['group_name']][] = $cat;
    }
    return $groups;
}

/** All years a user has set up, descending. */
function get_years(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT year FROM years WHERE user_id = ? ORDER BY year DESC');
    $stmt->execute([$userId]);
    return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'year'));
}

function get_salary(PDO $pdo, int $userId, int $year, string $month): float
{
    $stmt = $pdo->prepare('SELECT salary FROM income WHERE user_id = ? AND year = ? AND month = ?');
    $stmt->execute([$userId, $year, $month]);
    $v = $stmt->fetchColumn();
    return $v === false ? 0.0 : (float)$v;
}

function get_other_income_total(PDO $pdo, int $userId, int $year, string $month): float
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM other_income WHERE user_id = ? AND year = ? AND month = ?');
    $stmt->execute([$userId, $year, $month]);
    return (float)$stmt->fetchColumn();
}

function get_other_expense_total(PDO $pdo, int $userId, int $year, string $month): float
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM other_expenses WHERE user_id = ? AND year = ? AND month = ?');
    $stmt->execute([$userId, $year, $month]);
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

function get_actual(PDO $pdo, int $userId, int $categoryId, int $year, string $month): float
{
    $stmt = $pdo->prepare('SELECT actual FROM tracker_actuals WHERE user_id = ? AND category_id = ? AND year = ? AND month = ?');
    $stmt->execute([$userId, $categoryId, $year, $month]);
    $v = $stmt->fetchColumn();
    return $v === false ? 0.0 : (float)$v;
}

function set_actual(PDO $pdo, int $userId, int $categoryId, int $year, string $month, float $value): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO tracker_actuals (user_id, category_id, year, month, actual) VALUES (?, ?, ?, ?, ?)
         ON CONFLICT(category_id, year, month) DO UPDATE SET actual = excluded.actual'
    );
    $stmt->execute([$userId, $categoryId, $year, $month, $value]);
}

function get_transfer_in(PDO $pdo, int $userId, int $categoryId, int $year, string $month): float
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM transfers WHERE user_id = ? AND to_category_id = ? AND year = ? AND month = ?');
    $stmt->execute([$userId, $categoryId, $year, $month]);
    return (float)$stmt->fetchColumn();
}

function get_transfer_out(PDO $pdo, int $userId, int $categoryId, int $year, string $month): float
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM transfers WHERE user_id = ? AND from_category_id = ? AND year = ? AND month = ?');
    $stmt->execute([$userId, $categoryId, $year, $month]);
    return (float)$stmt->fetchColumn();
}

/**
 * Full tracker row for one category/month:
 * ['budget'=>, 'actual'=>, 'in'=>, 'out'=>, 'closing'=>]
 */
function tracker_row(PDO $pdo, int $userId, array $category, int $year, string $month, float $salary): array
{
    $budget = category_budget($category, $salary);
    $actual = $category['is_other']
        ? get_other_expense_total($pdo, $userId, $year, $month)
        : get_actual($pdo, $userId, (int)$category['id'], $year, $month);
    $in = get_transfer_in($pdo, $userId, (int)$category['id'], $year, $month);
    $out = get_transfer_out($pdo, $userId, (int)$category['id'], $year, $month);
    $closing = $budget - $actual + $in - $out;

    return compact('budget', 'actual', 'in', 'out', 'closing');
}

/** Full tracker grid for a whole month: every category with its computed row. */
function tracker_month(PDO $pdo, int $userId, int $year, string $month): array
{
    $salary = get_salary($pdo, $userId, $year, $month);
    $grouped = get_categories_grouped($pdo, $userId);
    $result = [];
    $totals = ['budget' => 0, 'actual' => 0, 'in' => 0, 'out' => 0, 'closing' => 0];

    foreach ($grouped as $groupName => $cats) {
        $rows = [];
        foreach ($cats as $cat) {
            $row = tracker_row($pdo, $userId, $cat, $year, $month, $salary);
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
