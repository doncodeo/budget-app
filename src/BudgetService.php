<?php
declare(strict_types=1);

namespace App;

class BudgetService
{
    public static function getCategoryGroupColors(): array
    {
        return [
            'Giving' => '#6f42c1',
            'Transport & Feeding' => '#0d6efd',
            'Household & Family' => '#fd7e14',
            'Communication & Utilities' => '#0dcaf0',
            'Future & Savings' => '#198754',
            'Flexible' => '#ffc107',
            'Other' => '#6c757d',
            'General' => '#20c997',
        ];
    }

    public static function getNetPositionHistory(\PDO $pdo, int $userId, int $currentYear, string $currentMonth, int $monthsCount = 12): array
    {
        $budgetId = get_active_budget_id($pdo, $userId);
        $result = [];

        $monthIndex = array_search($currentMonth, MONTHS, true);
        if ($monthIndex === false) {
            $monthIndex = 0;
        }

        $y = $currentYear;
        $mIdx = $monthIndex;

        $monthsToFetch = [];
        for ($i = 0; $i < $monthsCount; $i++) {
            array_unshift($monthsToFetch, ['year' => $y, 'month' => MONTHS[$mIdx]]);
            $mIdx--;
            if ($mIdx < 0) {
                $mIdx = 11;
                $y--;
            }
        }

        foreach ($monthsToFetch as $item) {
            $yr = $item['year'];
            $mo = $item['month'];
            $data = tracker_month($pdo, $userId, $yr, $mo, $budgetId);
            $salary = $data['salary'];
            $otherInc = get_other_income_total($pdo, $userId, $yr, $mo, $budgetId);
            $totalInc = $salary + $otherInc;
            $net = $data['totals']['closing'];

            $result[] = [
                'label' => "$mo $yr",
                'net' => $net,
                'income' => $totalInc,
                'actual' => $data['totals']['actual'],
                'budget' => $data['totals']['budget'],
            ];
        }

        return $result;
    }

    public static function getGroupSpendingBreakdown(array $trackerMonthData): array
    {
        $colors = self::getCategoryGroupColors();
        $labels = [];
        $data = [];
        $backgroundColors = [];

        foreach ($trackerMonthData['groups'] as $groupName => $rows) {
            $groupActual = 0.0;
            foreach ($rows as $row) {
                $groupActual += $row['actual'];
            }
            if ($groupActual > 0) {
                $labels[] = $groupName;
                $data[] = round($groupActual, 2);
                $backgroundColors[] = $colors[$groupName] ?? '#6c757d';
            }
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'colors' => $backgroundColors,
        ];
    }

    public static function getCategoryProgressList(array $trackerMonthData): array
    {
        $list = [];
        foreach ($trackerMonthData['groups'] as $groupName => $rows) {
            foreach ($rows as $row) {
                $cat = $row['category'];
                $budget = (float)$row['budget'];
                $actual = (float)$row['actual'];
                $pct = $budget > 0 ? min(100, round(($actual / $budget) * 100, 1)) : ($actual > 0 ? 100 : 0);

                $status = 'green';
                $statusClass = 'success';
                if ($budget > 0) {
                    $ratio = $actual / $budget;
                    if ($ratio > 1.0) {
                        $status = 'red';
                        $statusClass = 'danger';
                    } elseif ($ratio >= 0.85) {
                        $status = 'amber';
                        $statusClass = 'warning';
                    }
                } else if ($actual > 0) {
                    $status = 'red';
                    $statusClass = 'danger';
                }

                $groupColors = self::getCategoryGroupColors();

                $list[] = [
                    'id' => $cat['id'],
                    'name' => $cat['name'],
                    'group_name' => $groupName,
                    'group_color' => $groupColors[$groupName] ?? '#1f4e78',
                    'budget' => $budget,
                    'actual' => $actual,
                    'percent' => $pct,
                    'status' => $status,
                    'status_class' => $statusClass,
                ];
            }
        }
        return $list;
    }
}
